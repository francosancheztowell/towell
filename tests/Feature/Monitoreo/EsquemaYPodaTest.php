<?php

namespace Tests\Feature\Monitoreo;

use Illuminate\Support\Facades\Schema;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\TestCase;

class EsquemaYPodaTest extends TestCase
{
    use PreparaMonitoreo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararMonitoreo();
    }

    public function test_la_migracion_crea_las_seis_tablas_y_es_idempotente(): void
    {
        foreach (['SYSMonDispositivo', 'SYSMonSesion', 'SYSMonVista', 'SYSMonError', 'SYSMonErrorEvento', 'SYSMonAcceso'] as $tabla) {
            $this->assertTrue(Schema::connection('sqlsrv')->hasTable($tabla), $tabla);
        }

        // Segunda corrida: no truena por tablas existentes.
        $this->migrarMonitoreo('sqlsrv');

        $this->assertTrue(Schema::connection('sqlsrv')->hasColumns('SYSMonDispositivo', [
            'Uuid', 'UaHash', 'UltimaSesionId', 'CierreSolicitadoEn', 'CierreSolicitadoPor', 'VersionFront', 'Pantalla',
        ]));
    }

    public function test_la_poda_borra_lo_viejo_y_conserva_lo_reciente(): void
    {
        $viejo = now()->subDays(400);
        $reciente = now()->subDay();

        foreach ([$viejo, $reciente] as $fecha) {
            $this->mon('SYSMonAcceso')->insert(['Fecha' => $fecha, 'Tipo' => 'login', 'Ip' => '1.1.1.1']);
            $this->mon('SYSMonVista')->insert([
                'Uuid' => (string) \Illuminate\Support\Str::uuid(), 'DispositivoId' => 1, 'UsuarioId' => 1,
                'Ruta' => 'r', 'Url' => '/r', 'Tipo' => 'carga', 'Inicio' => $fecha,
            ]);
            $this->mon('SYSMonSesion')->insert([
                'DispositivoId' => 1, 'UsuarioId' => 1, 'Origen' => 'login', 'Ip' => '1.1.1.1',
                'Inicio' => $fecha, 'UltimaActividad' => $fecha,
            ]);
            $this->mon('SYSMonErrorEvento')->insert(['ErrorId' => 1, 'Fecha' => $fecha]);
        }

        foreach (['resuelto' => $viejo, 'nuevo' => $viejo] as $estado => $fecha) {
            $this->mon('SYSMonError')->insert([
                'Huella' => sha1($estado), 'Origen' => 'php', 'Clase' => 'X', 'Mensaje' => 'm',
                'Estado' => $estado, 'Ocurrencias' => 1, 'PrimeraVez' => $fecha, 'UltimaVez' => $fecha,
            ]);
        }

        $this->artisan('model:prune', ['--model' => [
            \App\Models\Sistema\Monitoreo\MonVista::class,
            \App\Models\Sistema\Monitoreo\MonErrorEvento::class,
            \App\Models\Sistema\Monitoreo\MonSesion::class,
            \App\Models\Sistema\Monitoreo\MonAcceso::class,
            \App\Models\Sistema\Monitoreo\MonError::class,
        ]])->assertSuccessful();

        foreach (['SYSMonAcceso', 'SYSMonVista', 'SYSMonSesion', 'SYSMonErrorEvento'] as $tabla) {
            $this->assertSame(1, $this->mon($tabla)->count(), $tabla);
        }

        // Un error viejo sin resolver se queda: todavía hay que atenderlo.
        $this->assertSame(['nuevo'], $this->mon('SYSMonError')->pluck('Estado')->all());
    }

    public function test_cerrar_expiradas_marca_sesiones_sin_actividad(): void
    {
        $this->mon('SYSMonSesion')->insert([
            ['DispositivoId' => 1, 'UsuarioId' => 1, 'Origen' => 'login', 'Ip' => 'x', 'Inicio' => now()->subHours(5), 'UltimaActividad' => now()->subHours(3)],
            ['DispositivoId' => 2, 'UsuarioId' => 1, 'Origen' => 'login', 'Ip' => 'x', 'Inicio' => now()->subHour(), 'UltimaActividad' => now()->subMinutes(5)],
        ]);

        $this->assertSame(1, app(\App\Services\Monitoreo\SesionService::class)->cerrarExpiradas());
        $this->assertSame('expirada', $this->mon('SYSMonSesion')->where('DispositivoId', 1)->value('MotivoFin'));
        $this->assertNull($this->mon('SYSMonSesion')->where('DispositivoId', 2)->value('Fin'));
    }
}
