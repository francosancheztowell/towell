<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Models\Sistema\SYSMensaje;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * PERF-13: los botones "Enviar a Telegram" siguen síncronos, pero ahora dicen a cuántos
 * destinatarios llegó el envío. Mismo contrato (`success`, `message`) + `enviados`/`destinatarios`.
 */
class BotonesTelegramTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('services.telegram.bot_token', 'TOKEN');
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");
        $this->createAuthTable();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAs($this->createUsuario(), 'web');

        Schema::connection('sqlsrv')->create('dbo.SYSMensajes', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Token')->nullable();
            $table->boolean('Activo')->nullable();
            $table->boolean('CorteSEF')->nullable();
            $table->boolean('MarcasFinales')->nullable();
        });
        foreach (['bien', 'caido', 'otro'] as $token) {
            SYSMensaje::query()->insert(['Token' => $token, 'Activo' => 1, 'CorteSEF' => 1, 'MarcasFinales' => 1]);
        }
    }

    public function test_imagen_de_cortes_reporta_a_cuantos_llego(): void
    {
        $this->telegramConUnChatCaido();

        $this->postImagenCortes()
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Imagen enviada por Telegram a 2 de 3 destinatarios',
                'enviados' => 2,
                'destinatarios' => 3,
            ]);

        // Todos salen en la misma tanda: el chat caído no impide que los otros dos reciban.
        foreach (['bien', 'otro'] as $chat) {
            Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/sendDocument')
                && collect($request->data())->pluck('contents', 'name')['chat_id'] === $chat);
        }
    }

    public function test_imagen_de_cortes_reporta_el_fallo_total(): void
    {
        Http::fake(['*' => Http::response(['ok' => false], 403)]);

        $this->postImagenCortes()
            ->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Imagen: no se pudo enviar por Telegram (0 de 3 destinatarios)',
                'enviados' => 0,
                'destinatarios' => 3,
            ]);
    }

    public function test_marcas_reporta_a_cuantos_llego(): void
    {
        $this->tablasMarcas();
        $this->telegramConUnChatCaido();

        $this->postJson('/modulo-marcas/reporte/notificar-telegram', ['fecha' => '2026-09-24'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Reporte enviado por Telegram a 2 de 3 destinatarios',
                'enviados' => 2,
                'destinatarios' => 3,
            ]);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/sendDocument') && $request->isMultipart());
    }

    public function test_marcas_ya_no_dice_exito_si_telegram_fallo(): void
    {
        $this->tablasMarcas();
        Http::fake(['*' => Http::failedConnection()]);

        $this->postJson('/modulo-marcas/reporte/notificar-telegram', ['fecha' => '2026-09-24'])
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Reporte: no se pudo enviar por Telegram (0 de 3 destinatarios)');
    }

    private function postImagenCortes(): \Illuminate\Testing\TestResponse
    {
        return $this->post('/modulo-cortes-de-eficiencia/visualizar/notificar-telegram-imagen', [
            'imagen' => UploadedFile::fake()->image('corte.png', 20, 20),
            'fecha' => '2026-09-24',
        ], ['Accept' => 'application/json']);
    }

    private function telegramConUnChatCaido(): void
    {
        Http::fake(function (Request $request) {
            $chat = collect($request->data())->pluck('contents', 'name')['chat_id'] ?? null;

            return $chat === 'caido' ? Http::failedConnection() : Http::response(['ok' => true]);
        });
    }

    private function tablasMarcas(): void
    {
        Schema::connection('sqlsrv')->create('TejMarcas', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->date('Date')->nullable();
            $table->string('Turno')->nullable();
        });
    }
}
