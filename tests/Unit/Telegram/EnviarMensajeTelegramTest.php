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

    public function test_el_worker_vacia_la_cola_y_se_sale_solo(): void
    {
        config()->set('queue.default', 'database');
        config()->set('queue.php_cli', PHP_BINARY);

        $comando = EnviarMensajeTelegram::comandoWorker();

        $this->assertNotNull($comando);
        $this->assertStringContainsString(escapeshellarg(PHP_BINARY), $comando);
        $this->assertStringContainsString('queue:work', $comando);
        $this->assertStringContainsString(escapeshellarg('database'), $comando);
        // Sin estas dos banderas el worker se quedaría vivo para siempre, uno por aviso.
        $this->assertStringContainsString('--stop-when-empty', $comando);
        $this->assertStringContainsString('--max-time=60', $comando);
        $this->assertStringContainsString('--tries=1', $comando);
    }

    public function test_con_cola_sync_no_hay_worker_que_lanzar(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('queue.php_cli', PHP_BINARY);

        $this->assertNull(EnviarMensajeTelegram::comandoWorker());
    }

    public function test_un_php_cli_inexistente_cae_al_php_junto_al_ini(): void
    {
        config()->set('queue.default', 'database');
        config()->set('queue.php_cli', 'C:\\no\\existe\\php.exe');

        $comando = EnviarMensajeTelegram::comandoWorker();

        // En la consola de tests siempre hay algún php real (PHP_BINARY o junto al php.ini).
        $this->assertNotNull($comando);
        $this->assertStringNotContainsString('no\\existe', $comando);
    }

    public function test_un_aviso_de_hace_mas_de_30_minutos_en_la_cola_no_se_envia(): void
    {
        Http::fake();
        Log::spy();
        $job = $this->desdeLaCola($this->job(['111']), time() - EnviarMensajeTelegram::VIGENCIA_SEGUNDOS - 1);

        $job->handle(app(TelegramEnvio::class));

        Http::assertNothingSent();
        Log::shouldHaveReceived('info')->once()->withArgs(fn (string $mensaje, array $contexto) => str_contains($mensaje, 'vencido')
            && $contexto['folio'] === 'F-1');
    }

    /** Pasó el 28-sep: jobs de hace 10 minutos, encolados con el código anterior, se tiraban como viejos. */
    public function test_un_aviso_reciente_de_la_cola_se_envia(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);
        $job = $this->desdeLaCola($this->job(['111']), time() - 600);

        $job->handle(app(TelegramEnvio::class));

        Http::assertSentCount(1);
    }

    public function test_en_linea_sin_cola_siempre_se_envia(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->job(['111'])->handle(app(TelegramEnvio::class));

        Http::assertSentCount(1);
    }

    /** Así lo entrega el worker: con el payload del renglón de `jobs`, que trae createdAt. */
    private function desdeLaCola(EnviarMensajeTelegram $job, int $createdAt): EnviarMensajeTelegram
    {
        $renglon = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $renglon->shouldReceive('payload')->andReturn(['createdAt' => $createdAt]);
        $job->setJob($renglon);

        return $job;
    }

    public function test_en_tests_encolar_no_lanza_un_worker_real(): void
    {
        $this->assertFalse((bool) config('queue.autoworker'), 'phpunit.xml debe apagar QUEUE_AUTOWORKER.');
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
