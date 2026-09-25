<?php

namespace Tests\Feature\Seguridad;

use App\Models\Sistema\Usuario;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\TestCase;

/**
 * SEC-05 — module.permission:<accion>,<modulo>,auditar registra authz_denegaria y deja pasar.
 * Sin el tercer parámetro el middleware sigue devolviendo 403.
 */
class ModuloPermisoAuditarTest extends TestCase
{
    use PreparaMonitoreo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararMonitoreo();

        Route::middleware(['web', 'auth'])->group(function () {
            Route::post('/_seg/enforce', fn () => response()->json(['ok' => true]))
                ->middleware('module.permission:crear,45')->name('seg.enforce');
            Route::post('/_seg/auditar', fn () => response()->json(['ok' => true]))
                ->middleware('module.permission:crear,45,auditar')->name('seg.auditar');
            Route::post('/_seg/auditar-modificar', fn () => response()->json(['ok' => true]))
                ->middleware('module.permission:modificar,45,auditar')->name('seg.auditar.modificar');
            Route::post('/_seg/modo-raro', fn () => response()->json(['ok' => true]))
                ->middleware('module.permission:crear,45,otro');
        });
        Route::getRoutes()->refreshNameLookups();
    }

    /**
     * @param  array<int, list<string>>  $permisos  idrol => acciones
     */
    private function usuario(array $permisos = []): Usuario
    {
        $usuario = $this->crearUsuario(['numero_empleado' => '4521']);

        app()->instance('permisos.roles', collect([
            'programa atadores' => (object) ['idrol' => 45, 'modulo' => 'Programa Atadores'],
        ]));

        $filas = [];
        foreach ($permisos as $idrol => $acciones) {
            $fila = ['acceso' => 0, 'crear' => 0, 'modificar' => 0, 'eliminar' => 0, 'registrar' => 0];
            foreach ($acciones as $accion) {
                $fila[$accion] = 1;
            }
            $filas[$idrol] = (object) $fila;
        }
        app()->instance('permisos.usuario.'.$usuario->idusuario, collect($filas));

        return $usuario;
    }

    private function denegaciones()
    {
        return DB::connection('sqlsrv')->table('SYSMonAcceso')->where('Tipo', 'authz_denegaria');
    }

    public function test_sin_modo_auditar_el_comportamiento_no_cambia(): void
    {
        $this->actingAs($this->usuario())
            ->postJson('/_seg/enforce')
            ->assertForbidden()
            ->assertExactJson(['message' => 'No tienes permiso para esta acción.']);

        $this->actingAs($this->usuario())->post('/_seg/enforce')->assertForbidden();

        $this->actingAs($this->usuario([45 => ['crear']]))->postJson('/_seg/enforce')->assertOk();
        $this->assertSame(0, $this->denegaciones()->count());
    }

    public function test_un_modo_desconocido_no_abre_la_ruta(): void
    {
        $this->actingAs($this->usuario())->postJson('/_seg/modo-raro')->assertForbidden();
        $this->assertSame(0, $this->denegaciones()->count());
    }

    public function test_auditar_con_permiso_pasa_sin_registrar(): void
    {
        $this->actingAs($this->usuario([45 => ['crear']]))->postJson('/_seg/auditar')->assertOk();

        $this->assertSame(0, $this->denegaciones()->count());
    }

    public function test_auditar_sin_permiso_registra_y_deja_pasar(): void
    {
        $usuario = $this->usuario([45 => ['acceso', 'modificar']]);

        $this->actingAs($usuario)->postJson('/_seg/auditar')->assertOk()->assertJson(['ok' => true]);

        $fila = $this->denegaciones()->first();
        $this->assertNotNull($fila);
        $this->assertSame($usuario->idusuario, (int) $fila->UsuarioId);
        $this->assertSame('4521', $fila->NumeroEmpleado);
        $this->assertSame('crear · 45 · POST seg.auditar', $fila->Motivo);
    }

    public function test_una_fila_por_usuario_ruta_y_accion_por_hora(): void
    {
        $usuario = $this->usuario();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($usuario)->postJson('/_seg/auditar')->assertOk();
        }
        $this->assertSame(1, $this->denegaciones()->count());

        // Otra acción u otro usuario sí cuentan aparte.
        $this->actingAs($usuario)->postJson('/_seg/auditar-modificar')->assertOk();
        $this->actingAs($this->usuario())->postJson('/_seg/auditar')->assertOk();
        $this->assertSame(3, $this->denegaciones()->count());

        $this->travel(61)->minutes();
        $this->actingAs($usuario)->postJson('/_seg/auditar')->assertOk();
        $this->assertSame(4, $this->denegaciones()->count());
    }

    public function test_con_monitoreo_apagado_deja_pasar_sin_registrar(): void
    {
        config()->set('monitoreo.enabled', false);

        $this->actingAs($this->usuario())->postJson('/_seg/auditar')->assertOk();
        $this->assertSame(0, $this->denegaciones()->count());
    }

    public function test_si_falla_la_cache_deja_pasar(): void
    {
        $usuario = $this->usuario();
        Cache::shouldReceive('add')->andThrow(new RuntimeException('redis caído'));

        $this->actingAs($usuario)->postJson('/_seg/auditar')->assertOk();
        $this->assertSame(0, $this->denegaciones()->count());
    }
}
