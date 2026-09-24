<?php

namespace Tests\Feature\Monitoreo;

use App\Services\Monitoreo\Monitoreo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\TestCase;

class AlertasTelegramTest extends TestCase
{
    use PreparaMonitoreo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararMonitoreo();
        config()->set('app.debug', false);
        config()->set('services.telegram.bot_token', 'bot-prueba');

        Schema::connection('sqlsrv')->create('dbo.SYSMensajes', function (Blueprint $t) {
            $t->increments('Id');
            $t->string('Token')->nullable();
            $t->boolean('Activo')->default(true);
            $t->boolean('ErroresSistema')->default(false);
            $t->boolean('Andon')->default(false);
        });
        DB::connection('sqlsrv')->table('dbo.SYSMensajes')->insert([
            ['Token' => '111', 'Activo' => 1, 'ErroresSistema' => 1, 'Andon' => 0],
            ['Token' => '222', 'Activo' => 1, 'ErroresSistema' => 1, 'Andon' => 0],
            ['Token' => '333', 'Activo' => 1, 'ErroresSistema' => 0, 'Andon' => 1],
            ['Token' => '444', 'Activo' => 0, 'ErroresSistema' => 1, 'Andon' => 0],
        ]);

        Route::middleware('web')->get('/_prueba/falla/{n}', fn ($n) => throw new RuntimeException('Falla tipo '.str_repeat('X', (int) $n)));
    }

    public function test_error_nuevo_envia_una_vez_por_chat_y_el_repetido_no(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->get('/_prueba/falla/1')->assertStatus(500);
        $this->get('/_prueba/falla/1')->assertStatus(500);

        Http::assertSentCount(2);
        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), 'botbot-prueba/sendMessage')
            && in_array($r['chat_id'], ['111', '222'], true)
            && str_contains($r['text'], 'ERROR NUEVO')
            && str_contains($r['text'], '/admin/errores/')
            && ! isset($r['parse_mode']));

        $this->assertNotNull(DB::connection(Monitoreo::CONEXION_ERRORES)->table('SYSMonError')->value('AlertadoEn'));
    }

    public function test_regresion_vuelve_a_alertar(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->get('/_prueba/falla/1');
        DB::connection(Monitoreo::CONEXION_ERRORES)->table('SYSMonError')->update(['Estado' => 'resuelto']);
        $this->get('/_prueba/falla/1');

        Http::assertSentCount(4);
        Http::assertSent(fn (ClientRequest $r) => str_contains($r['text'], 'REGRESIÓN'));
    }

    public function test_respeta_el_tope_por_hora(): void
    {
        config()->set('monitoreo.errores.telegram_max_hora', 2);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        foreach ([1, 2, 3, 4] as $n) {
            $this->get('/_prueba/falla/'.$n);
        }

        // 2 alertas × 2 chats; los errores 3 y 4 quedan registrados sin alerta.
        Http::assertSentCount(4);
        $this->assertSame(4, DB::connection(Monitoreo::CONEXION_ERRORES)->table('SYSMonError')->count());
    }

    public function test_fallo_de_telegram_no_afecta_la_request(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

        $this->get('/_prueba/falla/1')->assertStatus(500)->assertSee('Código de referencia', false);

        $this->assertNull(DB::connection(Monitoreo::CONEXION_ERRORES)->table('SYSMonError')->value('AlertadoEn'));
    }

    public function test_migracion_de_la_columna_es_idempotente(): void
    {
        Schema::connection('sqlsrv')->create('SYSMensajes', fn (Blueprint $t) => $t->increments('Id'));

        $this->migrarMonitoreo('sqlsrv', '2026_09_24_000002_add_errores_sistema_to_sysmensajes.php');
        $this->migrarMonitoreo('sqlsrv', '2026_09_24_000002_add_errores_sistema_to_sysmensajes.php');

        $this->assertTrue(Schema::connection('sqlsrv')->hasColumn('SYSMensajes', 'ErroresSistema'));
    }
}
