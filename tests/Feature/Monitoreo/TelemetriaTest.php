<?php

namespace Tests\Feature\Monitoreo;

use App\Http\Middleware\ProgramaTejidoContext;
use App\Http\Middleware\SetSqlContextInfo;
use App\Models\Sistema\Monitoreo\MonDispositivo;
use App\Models\Sistema\Usuario;
use App\Services\Monitoreo\CierreRemotoService;
use App\Services\Monitoreo\DispositivoService;
use App\Services\Monitoreo\Monitoreo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\TestCase;

class TelemetriaTest extends TestCase
{
    use PreparaMonitoreo;

    private Usuario $usuario;

    private string $uuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararMonitoreo();
        $this->usuario = $this->crearUsuario();
        $this->uuid = (string) Str::uuid();
    }

    private function comoTablet()
    {
        return $this->actingAs($this->usuario)->withCredentials()->withCookie(DispositivoService::COOKIE, $this->uuid);
    }

    public function test_requieren_sesion(): void
    {
        foreach (['latido', 'vista', 'error', 'dispositivo/nombre'] as $ruta) {
            $this->postJson('/telemetria/'.$ruta, [])->assertUnauthorized();
        }
    }

    public function test_rutas_sin_contexto_sql_ni_programa_tejido_y_con_throttle(): void
    {
        $ruta = Route::getRoutes()->getByName('telemetria.latido');

        $this->assertContains(SetSqlContextInfo::class, $ruta->excludedMiddleware());
        $this->assertContains(ProgramaTejidoContext::class, $ruta->excludedMiddleware());
        $this->assertContains('throttle:telemetria', $ruta->middleware());
        $this->assertContains('web', $ruta->middleware());
    }

    public function test_latido_actualiza_el_dispositivo_y_responde_intervalo(): void
    {
        $this->comoTablet()->postJson('/telemetria/latido', [
            'ruta' => 'tejido.inventario?x=1', 'visible' => true, 'inactivoSeg' => 42,
            'version' => 'abc123', 'pantalla' => '1280x800',
        ])->assertOk()->assertExactJson(['cerrar' => false, 'intervalo' => 60]);

        $d = $this->mon('SYSMonDispositivo')->where('Uuid', $this->uuid)->first();
        $this->assertSame('tejido.inventario', $d->UltimaRuta);
        $this->assertSame(42, (int) $d->InactivoSeg);
        $this->assertSame('abc123', $d->VersionFront);
        $this->assertSame('1280x800', $d->Pantalla);
        $this->assertSame((int) $this->usuario->idusuario, (int) $d->UltimoUsuarioId);

        $this->comoTablet()->postJson('/telemetria/latido', ['ruta' => 'x', 'visible' => false, 'pantalla' => '<script>'])
            ->assertJson(['intervalo' => 300]);
        $d = $this->mon('SYSMonDispositivo')->where('Uuid', $this->uuid)->first();
        $this->assertSame(0, (int) $d->Visible);
        $this->assertSame('1280x800', $d->Pantalla, 'Pantalla inválida se ignora.');
    }

    public function test_latido_responde_cerrar_true_tras_solicitar_cierre(): void
    {
        $this->comoTablet()->postJson('/telemetria/latido', ['ruta' => 'x', 'visible' => true])->assertJson(['cerrar' => false]);

        $admin = $this->crearUsuario(['numero_empleado' => '1', 'area' => 'Sistemas']);
        app(CierreRemotoService::class)->solicitar(MonDispositivo::where('Uuid', $this->uuid)->firstOrFail(), $admin);

        // El latido no desloguea: responde cerrar=true y el cliente recarga.
        $this->comoTablet()->postJson('/telemetria/latido', ['ruta' => 'x', 'visible' => true])
            ->assertOk()->assertJson(['cerrar' => true]);
        $this->assertAuthenticated();
    }

    public function test_vista_es_idempotente_y_recorta(): void
    {
        $vista = (string) Str::uuid();
        $cuerpo = [
            'uuid' => $vista, 'tipo' => 'suave', 'ruta' => str_repeat('r', 400).'?token=abc',
            'url' => 'https://towell.local/tejido/inventario?folio=123&token=secreto#x',
            'nav' => ['ttfb' => 120, 'dom' => 800, 'carga' => 9999999, 'kb' => 350],
            'st' => ['app' => 230.6, 'db' => 40, 'q' => 12],
        ];

        $this->comoTablet()->postJson('/telemetria/vista', $cuerpo)->assertNoContent();
        $this->comoTablet()->postJson('/telemetria/vista', $cuerpo)->assertNoContent();

        $this->assertSame(1, $this->mon('SYSMonVista')->count());
        $v = $this->mon('SYSMonVista')->first();
        $this->assertSame(150, mb_strlen($v->Ruta));
        $this->assertSame('/tejido/inventario', $v->Url);
        $this->assertSame('suave', $v->Tipo);
        $this->assertSame(600000, (int) $v->CargaMs, 'Enteros acotados a 600 000 ms.');
        $this->assertSame(231, (int) $v->ServidorMs);
        $this->assertSame(12, (int) $v->ConsultasN);
        $this->assertStringNotContainsString('secreto', json_encode($v));
    }

    public function test_fin_de_vista_acepta_formdata_de_beacon(): void
    {
        $vista = (string) Str::uuid();
        $this->comoTablet()->postJson('/telemetria/vista', ['uuid' => $vista, 'ruta' => 'r', 'url' => '/r'])->assertNoContent();

        // Dos horas visible: el tope de 600 000 ms es para tiempos de carga, no para permanencia.
        $this->comoTablet()->post('/telemetria/vista/'.$vista.'/fin', ['visibleMs' => '7200000', '_token' => 'x'])
            ->assertNoContent();

        $v = $this->mon('SYSMonVista')->first();
        $this->assertSame(7200000, (int) $v->VisibleMs);
        $this->assertNotNull($v->Fin);
    }

    public function test_fin_de_vista_de_otro_usuario_no_se_toca(): void
    {
        $vista = (string) Str::uuid();
        $this->comoTablet()->postJson('/telemetria/vista', ['uuid' => $vista, 'ruta' => 'r', 'url' => '/r']);

        $otro = $this->crearUsuario(['numero_empleado' => '2']);
        $this->actingAs($otro)->postJson('/telemetria/vista/'.$vista.'/fin', ['visibleMs' => 1])->assertNoContent();

        $this->assertNull($this->mon('SYSMonVista')->value('Fin'));
    }

    public function test_error_de_cliente_se_agrupa_y_se_limita_por_dispositivo(): void
    {
        config()->set('monitoreo.errores.throttle_cliente_min', 3);
        $errores = DB::connection(Monitoreo::CONEXION_ERRORES);

        for ($i = 0; $i < 5; $i++) {
            $this->comoTablet()->postJson('/telemetria/error', [
                'origen' => 'js', 'mensaje' => "TypeError: Cannot read properties of undefined (reading 'x{$i}')",
                'fuente' => 'https://towell.local/build/assets/app-abc.js?v=1', 'linea' => 10, 'col' => 5,
                'stack' => 'at foo (app.js:10:5)', 'url' => '/tejido?folio=9',
            ])->assertNoContent();
        }

        $error = $errores->table('SYSMonError')->first();
        $this->assertSame('js', $error->Origen);
        $this->assertSame('TypeError', $error->Clase);
        $this->assertSame('/build/assets/app-abc.js', $error->Archivo);
        $this->assertSame(3, (int) $error->Ocurrencias, 'El excedente se descarta en silencio.');
        $this->assertSame('/tejido', $errores->table('SYSMonErrorEvento')->value('Url'));
    }

    public function test_nombre_del_dispositivo(): void
    {
        $this->comoTablet()->postJson('/telemetria/dispositivo/nombre', ['nombre' => str_repeat('T', 100)])->assertNoContent();

        $this->assertSame(str_repeat('T', 80), $this->mon('SYSMonDispositivo')->where('Uuid', $this->uuid)->value('Nombre'));
    }

    public function test_nunca_500_aunque_falten_las_tablas(): void
    {
        foreach (['SYSMonDispositivo', 'SYSMonSesion', 'SYSMonVista'] as $tabla) {
            Schema::connection('sqlsrv')->drop($tabla);
        }
        Schema::connection(Monitoreo::CONEXION_ERRORES)->drop('SYSMonError');

        $this->comoTablet()->postJson('/telemetria/latido', ['visible' => true])->assertOk();
        $this->comoTablet()->postJson('/telemetria/vista', ['uuid' => (string) Str::uuid()])->assertNoContent();
        $this->comoTablet()->postJson('/telemetria/vista/'.Str::uuid().'/fin', [])->assertNoContent();
        $this->comoTablet()->postJson('/telemetria/error', ['mensaje' => 'x'])->assertNoContent();
        $this->comoTablet()->postJson('/telemetria/dispositivo/nombre', ['nombre' => 'x'])->assertNoContent();
    }

    public function test_kill_switch_responde_204_sin_escribir(): void
    {
        config()->set('monitoreo.enabled', false);

        $this->comoTablet()->postJson('/telemetria/latido', ['visible' => true])->assertNoContent();
        $this->comoTablet()->postJson('/telemetria/vista', ['uuid' => (string) Str::uuid(), 'ruta' => 'r'])->assertNoContent();
        $this->comoTablet()->postJson('/telemetria/error', ['mensaje' => 'x'])->assertNoContent();

        $this->assertSame(0, $this->mon('SYSMonDispositivo')->count());
        $this->assertSame(0, $this->mon('SYSMonVista')->count());
        $this->assertSame(0, DB::connection(Monitoreo::CONEXION_ERRORES)->table('SYSMonError')->count());
    }
}
