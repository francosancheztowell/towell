<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Http\Controllers\Atadores\ProgramaAtadores\AtadoresController;
use App\Jobs\Telegram\EnviarMensajeTelegram;
use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Sistema\SYSMensaje;
use App\Models\Tejido\TejTrama;
use App\Services\Tejido\InventarioTrama\RequerimientoStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * PERF-13: terminar atado, notificar montado de julio y solicitar trama responden sin
 * esperar a Telegram. El aviso (mismo texto, mismos destinatarios) queda en la cola.
 */
class AvisosDiferidosTest extends TestCase
{
    use UsesSqlsrvSqlite;

    /** Lo que tarda cada envío del Telegram lento simulado. */
    private const SEGUNDOS_TELEGRAM_LENTO = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('services.telegram.bot_token', 'TOKEN');
        config()->set('services.telegram.chat_id', '');
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");

        Schema::connection('sqlsrv')->create('dbo.SYSMensajes', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Token')->nullable();
            $table->boolean('Activo')->nullable();
            foreach (SYSMensaje::columnasModuloPermitidas() as $columna) {
                $table->boolean($columna)->nullable();
            }
        });

        Queue::fake();
        Http::fake(function () {
            sleep(self::SEGUNDOS_TELEGRAM_LENTO);

            return Http::response(['ok' => true]);
        });
    }

    public function test_notificar_montado_de_julio_responde_sin_esperar_a_telegram(): void
    {
        $this->prepararMontadoDeJulio();
        $this->destinatarios('NotificarAtadoJulio', ['chat-a', 'chat-b', 'chat-c']);

        $inicio = microtime(true);
        $this->postJson('/tejedores/atadodejulio/notificar', ['id' => 2, 'horaParo' => '07:05:09'])
            ->assertOk()
            ->assertJsonPath('success', true);
        $segundos = microtime(true) - $inicio;

        $this->assertLessThan(self::SEGUNDOS_TELEGRAM_LENTO, $segundos, 'La respuesta esperó a Telegram.');
        Http::assertNothingSent();
        Queue::assertPushed(EnviarMensajeTelegram::class, fn (EnviarMensajeTelegram $job) => $job->chatIds === ['chat-a', 'chat-b', 'chat-c']
            && str_starts_with($job->texto, '*ATADO DE JULIO NOTIFICADO*')
            && str_contains($job->texto, '*No. Julio:* RESERVADO')
            && $job->extra === ['parse_mode' => 'Markdown']
            && $job->mensajeLog === 'Error al enviar notificacion de atado de julio a Telegram');
    }

    public function test_atado_terminado_encola_el_aviso_a_los_destinatarios_de_atadores(): void
    {
        $this->destinatarios('Atadores', ['chat-1', 'chat-2']);

        $this->atadoTerminado();

        Http::assertNothingSent();
        Queue::assertPushed(EnviarMensajeTelegram::class, fn (EnviarMensajeTelegram $job) => $job->chatIds === ['chat-1', 'chat-2']
            && str_starts_with($job->texto, "ATADO TERMINADO\n\nTelar: 401\nBarra: 1\n")
            && str_contains($job->texto, 'Hora Arranque: 07:30')
            && str_contains($job->texto, 'Operador: Ana (77)')
            && $job->extra === []
            && $job->contextoLog === ['no_julio' => 'J-9', 'no_orden' => 'OP-9']);
    }

    public function test_atado_terminado_sin_destinatarios_usa_el_chat_global(): void
    {
        config()->set('services.telegram.chat_id', 'global');

        $this->atadoTerminado();

        Queue::assertPushed(EnviarMensajeTelegram::class, fn (EnviarMensajeTelegram $job) => $job->chatIds === ['global']);
    }

    public function test_solicitar_trama_encola_el_aviso_sin_esperar_a_telegram(): void
    {
        $this->destinatarios('InvTrama', ['chat-t']);
        $requerimiento = (new TejTrama)->forceFill(['Folio' => 'RT-7', 'Turno' => '2', 'numero_empleado' => '55']);

        $inicio = microtime(true);
        app(RequerimientoStatusService::class)->enviarTelegram($requerimiento);

        $this->assertLessThan(self::SEGUNDOS_TELEGRAM_LENTO, microtime(true) - $inicio);
        Http::assertNothingSent();
        Queue::assertPushed(EnviarMensajeTelegram::class, fn (EnviarMensajeTelegram $job) => $job->chatIds === ['chat-t']
            && str_starts_with($job->texto, "📦 *SOLICITAR CONSUMO TRAMA*\nFolio: RT-7\n")
            && $job->contextoLog === ['folio' => 'RT-7']
            && $job->nivelLog === 'error');
    }

    public function test_sin_bot_configurado_no_se_encola_nada(): void
    {
        config()->set('services.telegram.bot_token', '');
        $this->destinatarios('Atadores', ['chat-1']);

        $this->atadoTerminado();

        Queue::assertNothingPushed();
    }

    /**
     * @param  list<string>  $tokens
     */
    private function destinatarios(string $modulo, array $tokens): void
    {
        foreach ($tokens as $token) {
            SYSMensaje::query()->insert(['Token' => $token, 'Activo' => 1, $modulo => 1]);
        }
        SYSMensaje::query()->insert(['Token' => 'inactivo', 'Activo' => 0, $modulo => 1]);
    }

    private function atadoTerminado(): void
    {
        $montado = (new AtaMontadoTelasModel)->forceFill([
            'NoTelarId' => '401',
            'Tipo' => '1',
            'NoJulio' => 'J-9',
            'NoProduccion' => 'OP-9',
            'MergaKg' => 1.5,
        ]);
        $usuario = (object) ['nombre' => 'Ana', 'numero_empleado' => '77'];

        $controller = app(AtadoresController::class);
        (new ReflectionMethod($controller, 'enviarNotificacionTelegramAtadoTerminado'))
            ->invoke($controller, $montado, $usuario, '07:30');
    }

    private function prepararMontadoDeJulio(): void
    {
        $this->createAuthTable();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $schema = Schema::connection('sqlsrv');

        $schema->create('TelTelaresOperador', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('numero_empleado')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
        });
        $schema->create('tej_inventario_telares', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no_telar')->nullable();
            $table->string('tipo')->nullable();
            $table->string('status')->nullable();
            $table->string('cuenta')->nullable();
            $table->float('calibre')->nullable();
            $table->string('tipo_atado')->nullable();
            $table->string('no_julio')->nullable();
            $table->string('no_orden')->nullable();
            $table->float('metros')->nullable();
            $table->boolean('Reservado')->nullable();
            $table->date('fecha')->nullable();
            $table->string('turno')->nullable();
            $table->string('horaParo')->nullable();
            $table->timestamps();
        });
        $schema->create('TejNotificaTejedor', function (Blueprint $table) {
            $table->increments('id');
            $table->string('telar')->nullable();
            $table->string('tipo')->nullable();
            $table->string('hora')->nullable();
            $table->string('NomEmpleado')->nullable();
            $table->string('NoEmpleado')->nullable();
            $table->boolean('Reserva')->nullable();
            $table->string('no_julio')->nullable();
            $table->string('no_orden')->nullable();
            $table->date('Fecha')->nullable();
        });

        $this->actingAs($this->createUsuario(['numero_empleado' => '1001']), 'web');
        DB::connection('sqlsrv')->table('TelTelaresOperador')->insert(['numero_empleado' => '1001', 'NoTelarId' => '300']);
        DB::connection('sqlsrv')->table('tej_inventario_telares')->insert([
            'id' => 2, 'no_telar' => '300', 'tipo' => 'Rizo', 'status' => 'Activo', 'cuenta' => '2500',
            'no_julio' => 'RESERVADO', 'no_orden' => 'ORD-OK', 'metros' => 800, 'Reservado' => 1,
        ]);
    }
}
