<?php

declare(strict_types=1);

namespace Tests\Feature\Perf;

use App\Http\Middleware\SetSqlContextInfo;
use App\Services\Monitoreo\ContextoSql;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Mockery;
use Tests\TestCase;

/**
 * PERF-07: SetSqlContextInfo se mide (Server-Timing `ctx` + Pulse) sin cambiar su semántica:
 * sigue sellando el contexto en cada request con SQL Server, con o sin sesión.
 */
final class SetSqlContextInfoTest extends TestCase
{
    private function conexionSqlsrv(): Connection
    {
        $conexion = Mockery::mock(Connection::class);
        $conexion->shouldReceive('getDriverName')->andReturn('sqlsrv');
        $conexion->shouldReceive('statement')
            ->once()
            ->with('EXEC dbo.sp_SetAppContext ?, ?, ?', [null, null, '10.0.0.7'])
            ->andReturnUsing(function () {
                usleep(2000);

                return true;
            });
        DB::shouldReceive('connection')->andReturn($conexion);

        return $conexion;
    }

    private function correr(Request $request, Response $respuesta): Response
    {
        return (new SetSqlContextInfo)->handle($request, fn () => $respuesta);
    }

    public function test_con_sqlsrv_sella_el_contexto_y_anexa_ctx_al_server_timing(): void
    {
        config()->set('monitoreo.enabled', true);
        config()->set('pulse.enabled', false);
        $this->conexionSqlsrv();

        $respuesta = new Response('ok');
        $respuesta->headers->set('Server-Timing', 'app;dur=10.0, db;dur=4.0;desc="2 q"');

        $request = Request::create('/tejido', 'GET', server: ['REMOTE_ADDR' => '10.0.0.7']);
        $header = $this->correr($request, $respuesta)->headers->get('Server-Timing');

        $this->assertMatchesRegularExpression('/^app;dur=10\.0, db;dur=4\.0;desc="2 q", ctx;dur=(\d+\.\d)$/', $header);
        preg_match('/ctx;dur=(\d+\.\d)/', $header, $m);
        $this->assertGreaterThanOrEqual(2.0, (float) $m[1]);
    }

    public function test_sin_server_timing_previo_el_header_solo_trae_ctx_y_pulse_agrega_por_tipo(): void
    {
        config()->set('monitoreo.enabled', true);
        config()->set('pulse.enabled', true);
        $this->conexionSqlsrv();
        Pulse::shouldReceive('record')
            ->once()
            ->with(ContextoSql::TIPO, 'livewire', Mockery::type('int'))
            ->andReturnSelf();
        Pulse::shouldReceive('avg', 'max', 'count')->andReturnSelf();

        $request = Request::create('/livewire/update', 'POST', server: ['REMOTE_ADDR' => '10.0.0.7']);
        $request->headers->set('X-Livewire', '1');

        $header = $this->correr($request, new Response('ok'))->headers->get('Server-Timing');

        $this->assertMatchesRegularExpression('/^ctx;dur=\d+\.\d$/', $header);
    }

    public function test_con_el_monitoreo_apagado_sella_igual_pero_no_mide(): void
    {
        config()->set('monitoreo.enabled', false);
        $this->conexionSqlsrv();

        $request = Request::create('/tejido', 'GET', server: ['REMOTE_ADDR' => '10.0.0.7']);

        $this->assertNull($this->correr($request, new Response('ok'))->headers->get('Server-Timing'));
    }

    public function test_con_sqlite_no_ejecuta_nada_ni_mide(): void
    {
        $request = Request::create('/tejido', 'GET');

        $this->assertNull($this->correr($request, new Response('ok'))->headers->get('Server-Timing'));
    }
}
