<?php

declare(strict_types=1);

namespace Tests\Unit\Telegram;

use App\Jobs\Telegram\EnviarMensajeTelegram;
use App\Services\Telegram\TelegramEnvio;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PERF-13: el aviso de un efecto secundario va a la cola `database`; si la cola no
 * está disponible, sale en línea y la acción no se cae.
 */
class EnviarMensajeTelegramTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.telegram.bot_token', 'TOKEN');
    }

    public function test_reintenta_una_vez_solo_los_chats_con_falla_pasajera_y_loguea_los_que_fallan(): void
    {
        $intentos = [];
        Http::fake(function (Request $request) use (&$intentos) {
            $chat = $request['chat_id'];
            $intentos[$chat] = ($intentos[$chat] ?? 0) + 1;

            return match ($chat) {
                'inestable' => $intentos[$chat] === 1 ? Http::response('', 502) : Http::response(['ok' => true]),
                'rechaza' => Http::response(['ok' => false], 400),
                default => Http::response(['ok' => true]),
            };
        });
        Log::spy();

        $this->job(['inestable', 'rechaza', 'bien'])->handle(app(TelegramEnvio::class));

        $this->assertSame(['inestable' => 2, 'rechaza' => 1, 'bien' => 1], $intentos);
        Log::shouldHaveReceived('log')->once()->withArgs(fn (string $nivel, string $mensaje, array $contexto) => $nivel === 'error'
            && $mensaje === 'Telegram: fallo al enviar'
            && $contexto['folio'] === 'F-1'
            && $contexto['chat_id'] === 'rechaza'
            && $contexto['status'] === 400);
    }

    public function test_encolar_guarda_el_aviso_en_la_tabla_jobs_sin_tocar_telegram(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        config()->set('queue.default', 'database');
        Http::fake();

        EnviarMensajeTelegram::encolar($this->job(['111', '222']));

        Http::assertNothingSent();
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertStringContainsString('EnviarMensajeTelegram', (string) DB::table('jobs')->value('payload'));
    }

    public function test_sin_tabla_jobs_se_envia_en_linea(): void
    {
        config()->set('queue.default', 'database');
        Http::fake(['*' => Http::response(['ok' => true])]);
        Log::spy();

        EnviarMensajeTelegram::encolar($this->job(['111', '222']));

        Http::assertSentCount(2);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $mensaje) => str_contains($mensaje, 'no se pudo encolar'));
    }

    public function test_nunca_lanza_aunque_telegram_explote(): void
    {
        Http::fake(fn () => throw new \RuntimeException('boom'));
        Log::spy();

        $this->job(['111'])->handle(app(TelegramEnvio::class));

        Log::shouldHaveReceived('log')->once()->withArgs(fn (string $nivel, string $mensaje, array $contexto) => $contexto['chat_id'] === '111'
            && $contexto['response'] === 'boom');
    }

    /**
     * @param  list<string>  $chatIds
     */
    private function job(array $chatIds): EnviarMensajeTelegram
    {
        return new EnviarMensajeTelegram(
            chatIds: $chatIds,
            texto: 'Aviso',
            extra: ['parse_mode' => 'Markdown'],
            mensajeLog: 'Telegram: fallo al enviar',
            contextoLog: ['folio' => 'F-1'],
            nivelLog: 'error',
        );
    }
}
