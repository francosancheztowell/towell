<?php

namespace Tests\Feature\Planeacion;

use App\Http\Controllers\Planeacion\ProgramaTejido\RedboothProgramaTejidoController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * PT-02 · decisión 01.3 — capacidades B de Muestras: guard 422 explícito + acción oculta en
 * la UI de Muestras. Capacidades A: el código no cambia (el DDL va por database/sql/pt_*.sql),
 * salvo producción, que ya persiste las fórmulas (ProgramaTejidoInvariantTest).
 */
class ProgramaTejidoCapacidadesMuestrasTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSuperficies();
        $this->sembrarFixtures();
    }

    public function test_descargar_en_muestras_responde_422_antes_de_leer_o_escribir(): void
    {
        DB::enableQueryLog();

        $this->actingAs($this->usuarioConPermisos([5 => ['registrar']]))
            ->postJson(route('muestras.descargar-programa'), ['fecha_inicial' => '2026-09-01'])
            ->assertStatus(422)
            ->assertJsonPath('capacidad', 'descarga')
            ->assertJsonPath('superficie', 'muestras')
            ->assertJsonPath('message', 'La acción no está disponible en Muestras.');

        // Ni siquiera consulta líneas: antes leía MuestrasProgramaLine y pisaba ProgramaTejido.txt.
        $this->assertSame([], array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'ProgramaLine')));
    }

    public function test_descargar_en_programa_sigue_disponible(): void
    {
        // Sin fecha: la validación responde 422 de validación, no el guard de capacidad.
        $this->actingAs($this->usuarioConPermisos([2 => ['registrar']]))
            ->postJson(route('programa-tejido.descargar-programa'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha_inicial')
            ->assertJsonMissingPath('capacidad');
    }

    public function test_muestras_no_tiene_rutas_de_redbooth(): void
    {
        $redboothMuestras = array_filter(
            array_map(fn ($r) => $r->uri(), Route::getRoutes()->getRoutes()),
            fn (string $uri) => str_starts_with($uri, 'planeacion/muestras') && str_contains($uri, 'redbooth')
        );

        $this->assertSame([], array_values($redboothMuestras));
    }

    public function test_redbooth_desde_muestras_responde_422_si_se_enruta(): void
    {
        // Defensa: si alguien agrega la ruta, el controller no llega a IdRedbooth (500 en Muestras).
        Route::middleware('web')->get('planeacion/muestras/redbooth/proyectos', [RedboothProgramaTejidoController::class, 'projectOptions']);
        Route::middleware('web')->delete('planeacion/muestras/redbooth/{programa}', [RedboothProgramaTejidoController::class, 'destroy']);

        $usuario = $this->usuarioConPermisos([5 => ['modificar']]);
        $this->actingAs($usuario)->getJson('/planeacion/muestras/redbooth/proyectos')
            ->assertStatus(422)->assertJsonPath('capacidad', 'redbooth');
        $this->actingAs($usuario)->deleteJson('/planeacion/muestras/redbooth/1')
            ->assertStatus(422)->assertJsonPath('capacidad', 'redbooth');
    }

    public function test_finalizacion_opera_solo_sobre_programa(): void
    {
        // Finalización B: queda como hoy, explícito. Utilería nunca cae en el contexto Muestras.
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'planeacion/utileria')) {
                $this->assertFalse(
                    \App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface::fromPath($route->uri())->esMuestras(),
                    $route->uri()
                );
            }
        }
        $this->assertFalse(config('planeacion.superficies.muestras.capacidades.finalizacion'));
    }

    public function test_la_pantalla_de_muestras_oculta_las_acciones_b(): void
    {
        $html = $this->pantalla('/planeacion/muestras', [5 => ['acceso', 'crear', 'modificar', 'eliminar', 'registrar']]);

        $this->assertStringNotContainsString('id="contextMenuRedbooth"', $html);
        $this->assertStringContainsString('"descarga":false', $html, 'PT_BOOT.capacidades llega al front');
        $this->assertStringContainsString('"redbooth":false', $html);
        $this->assertStringContainsString(json_encode(route('muestras.recalcular-fechas')), $html);
    }

    public function test_la_pantalla_de_programa_conserva_sus_acciones(): void
    {
        $html = $this->pantalla('/planeacion/programa-tejido', [2 => ['acceso', 'crear', 'modificar', 'eliminar', 'registrar']]);

        $this->assertStringContainsString('id="contextMenuRedbooth"', $html);
        $this->assertStringContainsString('"descarga":true', $html);
        $this->assertStringContainsString(json_encode(route('programa-tejido.recalcular-fechas')), $html);
    }

    public function test_los_valores_de_bd_se_escapan_en_ambas_superficies(): void
    {
        foreach (['programa' => 2, 'muestras' => 5] as $superficie => $idrol) {
            DB::table(config("planeacion.superficies.{$superficie}.tabla"))->where('Id', 1)
                ->update(['Observaciones' => '<script>alert("pt")</script>', 'NombreProyecto' => '<img src=x onerror=alert(1)>']);

            $uri = $superficie === 'muestras' ? '/planeacion/muestras' : '/planeacion/programa-tejido';
            $html = $this->pantalla($uri, [$idrol => ['acceso']]);

            $this->assertStringNotContainsString('<script>alert("pt")</script>', $html, $superficie);
            $this->assertStringNotContainsString('<img src=x onerror', $html, $superficie);
            $this->assertStringContainsString('&lt;script&gt;alert(&quot;pt&quot;)&lt;/script&gt;', $html, $superficie);
        }
    }

    public function test_error_de_lectura_no_se_muestra_como_vacio(): void
    {
        \Illuminate\Support\Facades\Schema::drop('MuestrasPrograma');

        $html = $this->pantalla('/planeacion/muestras', [5 => ['acceso']]);

        $this->assertStringContainsString('data-estado="error"', $html);
        $this->assertStringNotContainsString('Cargar Archivo Excel', $html, 'Un error no invita a reimportar');
        $this->assertStringNotContainsString('SQLSTATE', $html, 'El detalle técnico se queda en el log');
    }

    public function test_sin_registros_se_muestra_el_estado_vacio(): void
    {
        DB::table('MuestrasPrograma')->delete();

        $html = $this->pantalla('/planeacion/muestras', [5 => ['acceso']]);

        $this->assertStringNotContainsString('data-estado="error"', $html);
        $this->assertStringContainsString('No hay registros', $html);
    }

    /**
     * @param  array<int, list<string>>  $permisos
     */
    private function pantalla(string $uri, array $permisos): string
    {
        $respuesta = $this->actingAs($this->usuarioConPermisos($permisos))->get($uri);
        $respuesta->assertOk();

        return (string) $respuesta->getContent();
    }
}
