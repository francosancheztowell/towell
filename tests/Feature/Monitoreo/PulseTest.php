<?php

namespace Tests\Feature\Monitoreo;

use App\Services\Monitoreo\Legado;
use App\Services\Monitoreo\PulseConexion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\TestCase;

/**
 * Fase 14: Pulse en /admin/pulse sobre la conexión SQLite `pulse`.
 * phpunit.xml trae PULSE_ENABLED=false; los casos "encendido" lo prenden en runtime.
 */
class PulseTest extends TestCase
{
    use PreparaMonitoreo;

    private const MIGRACION = '2026_09_24_000010_create_pulse_tables.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararMonitoreo();
    }

    private function migrarPulse(): Migration
    {
        /** @var Migration $migracion */
        $migracion = require database_path('migrations/'.self::MIGRACION);
        DB::usingConnection(PulseConexion::NOMBRE, fn () => $migracion->up());

        return $migracion;
    }

    private function encenderPulse(): void
    {
        config()->set('pulse.enabled', true);
        $this->migrarPulse();
    }

    public function test_conexion_pulse_es_sqlite_dedicada(): void
    {
        $this->assertSame('sqlite', config('database.connections.pulse.driver'));
        $this->assertSame('wal', config('database.connections.pulse.journal_mode'));
        $this->assertSame(5000, config('database.connections.pulse.busy_timeout'));
        $this->assertSame(PulseConexion::NOMBRE, config('pulse.storage.database.connection'));
        $this->assertSame('admin/pulse', config('pulse.path'));
    }

    public function test_migracion_se_salta_con_pulse_apagado(): void
    {
        $this->migrarPulse();

        $this->assertFalse(Schema::connection('pulse')->hasTable('pulse_entries'));
    }

    public function test_migracion_crea_tablas_solo_en_la_conexion_pulse(): void
    {
        $this->encenderPulse();

        foreach (['pulse_values', 'pulse_entries', 'pulse_aggregates'] as $tabla) {
            $this->assertTrue(Schema::connection('pulse')->hasTable($tabla), $tabla);
            $this->assertFalse(Schema::connection('sqlsrv')->hasTable($tabla), $tabla.' no va a sqlsrv');
        }
    }

    public function test_crea_el_archivo_sqlite_si_falta(): void
    {
        $archivo = sys_get_temp_dir().'/towell-pulse-'.uniqid().'/pulse.sqlite';

        PulseConexion::asegurarArchivo($archivo);

        $this->assertFileExists($archivo);
        unlink($archivo);
        rmdir(dirname($archivo));
    }

    public function test_sistemas_abre_pulse_con_aviso_si_esta_apagado(): void
    {
        $admin = $this->crearUsuario(['numero_empleado' => '1', 'area' => 'Sistemas']);

        $this->actingAs($admin)->get('/admin/pulse')->assertOk()->assertSee('Pulse está apagado');
    }

    public function test_sistemas_ve_las_tarjetas_con_pulse_encendido(): void
    {
        $this->encenderPulse();
        $admin = $this->crearUsuario(['numero_empleado' => '1', 'area' => 'Sistemas']);

        $this->actingAs($admin)->get('/admin/pulse')
            ->assertOk()
            // Las tarjetas son componentes Livewire lazy: se ve el componente, no su contenido.
            ->assertSee('pulse.slow-requests', false)
            ->assertSee('pulse.slow-queries', false)
            ->assertSee('pulse.usage', false)
            ->assertDontSee('pulse.exceptions', false)
            ->assertDontSee('pulse.servers', false)
            ->assertDontSee('Pulse está apagado');
    }

    public function test_otra_area_recibe_403_y_el_invitado_va_al_login(): void
    {
        $tejido = $this->crearUsuario(['area' => 'Tejido']);

        $this->actingAs($tejido)->get('/admin/pulse')->assertForbidden();
        $this->assertFalse(Gate::forUser($tejido)->allows('viewPulse'));

        $this->nuevoProceso();
        $this->get('/admin/pulse')->assertRedirect('/login');
    }

    public function test_recorders_segun_el_contexto(): void
    {
        $recorders = config('pulse.recorders');

        foreach ([Recorders\SlowRequests::class, Recorders\SlowQueries::class, Recorders\SlowJobs::class,
            Recorders\SlowOutgoingRequests::class, Recorders\UserRequests::class] as $encendido) {
            $this->assertArrayHasKey($encendido, $recorders);
        }
        foreach ([Recorders\Exceptions::class, Recorders\Servers::class, Recorders\CacheInteractions::class] as $apagado) {
            $this->assertArrayNotHasKey($apagado, $recorders, $apagado.' debe estar apagado');
        }

        $this->assertSame(1000, $recorders[Recorders\SlowRequests::class]['threshold']);
        $this->assertSame(500, $recorders[Recorders\SlowQueries::class]['threshold']);
        $this->assertTrue($recorders[Recorders\SlowQueries::class]['location']);
        $this->assertContains('#^/telemetria/#', $recorders[Recorders\SlowRequests::class]['ignore']);
        $this->assertContains('#^/admin/pulse#', $recorders[Recorders\UserRequests::class]['ignore']);
    }

    public function test_usuario_de_pulse_es_el_de_towell(): void
    {
        $usuario = $this->crearUsuario(['nombre' => 'Ana Sistemas', 'numero_empleado' => '4321', 'area' => 'Sistemas']);

        $resuelto = Pulse::resolveUsers(collect([$usuario->idusuario]))->find($usuario->idusuario);

        $this->assertSame('Ana Sistemas', $resuelto->name);
        $this->assertSame('#4321', $resuelto->extra);
    }

    public function test_legado_cuenta_en_pulse_y_no_lanza_apagado(): void
    {
        Legado::registrar('/viejo/ruta');
        $this->assertSame(0, Pulse::ingest());

        $this->encenderPulse();
        Pulse::startRecording();
        Legado::registrar('viejo/ruta');
        Pulse::ingest();

        $this->assertSame('/viejo/ruta', DB::connection('pulse')->table('pulse_entries')->where('type', 'legado')->value('key'));
    }

    public function test_livewire_update_se_muestrea_en_user_requests(): void
    {
        $this->encenderPulse();
        config()->set('pulse.livewire_sample_rate', 0);
        Pulse::startRecording();

        // Pulse filtra al ingerir, que ocurre al terminar la misma request.
        $this->app->instance('request', Request::create('/livewire-abc/update', 'POST'));
        for ($i = 0; $i < 5; $i++) {
            Pulse::record('user_request', '1')->count();
        }
        Pulse::record('slow_request', 'x', 1500)->max()->count();
        Pulse::ingest();

        $this->app->instance('request', Request::create('/produccionProceso'));
        Pulse::record('user_request', '1')->count();
        Pulse::ingest();

        $tipos = DB::connection('pulse')->table('pulse_entries')->pluck('type')->all();
        $this->assertSame(1, count(array_keys($tipos, 'user_request')), 'solo cuenta la request que no es de Livewire');
        $this->assertContains('slow_request', $tipos, 'las lentas de Livewire no se muestrean');
    }
}
