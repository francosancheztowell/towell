<?php

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DesarrolladoresRouteContractTest extends TestCase
{
    /**
     * GET /desarrolladores without authentication must redirect (302) or return 401.
     */
    public function test_index_sin_auth_redirige(): void
    {
        $response = $this->get('/tejedores/desarrolladores');

        $this->assertContains(
            $response->status(),
            [302, 401],
            "GET /tejedores/desarrolladores should redirect or return 401 when unauthenticated, got {$response->status()}"
        );
    }

    /**
     * POST /desarrolladores without authentication must redirect (302) or return 401.
     */
    public function test_store_sin_auth_redirige(): void
    {
        $response = $this->post('/desarrolladores');

        $this->assertContains(
            $response->status(),
            [302, 401],
            "POST /desarrolladores should redirect or return 401 when unauthenticated, got {$response->status()}"
        );
    }

    /**
     * Simula un usuario autenticado sembrando la memoizacion de permisos en el
     * contenedor, tal como la construye userPermissions(). Asi el test no escribe
     * en SYSUsuariosRoles ni depende de los datos reales del servidor.
     *
     * @param  array<string, int>  $permisos  Ej. ['acceso' => 1]. Vacio = sin el modulo asignado.
     */
    private function actuandoComo(string $modulo, int $idrol, array $permisos = []): Usuario
    {
        $usuario = new Usuario(['nombre' => 'Test']);
        $usuario->idusuario = 999999;

        app()->instance('permisos.roles', collect([
            mb_strtolower($modulo) => (object) ['idrol' => $idrol, 'modulo' => $modulo],
        ]));

        $filas = $permisos === []
            ? collect()
            : collect([$idrol => (object) array_merge(
                ['acceso' => 0, 'crear' => 0, 'modificar' => 0, 'eliminar' => 0, 'registrar' => 0],
                $permisos
            )]);

        app()->instance('permisos.usuario.'.$usuario->idusuario, $filas);

        return $usuario;
    }

    /**
     * Un usuario autenticado SIN el modulo asignado no puede entrar al listado.
     * Este es el test que falla si se quita el abort_unless del controlador.
     */
    public function test_index_autenticado_sin_permiso_da_403(): void
    {
        $usuario = $this->actuandoComo('Desarrolladores', 48);

        $this->actingAs($usuario)->get('/tejedores/desarrolladores')->assertForbidden();
    }

    /**
     * Y tampoco puede capturar: POST /desarrolladores mueve ordenes de produccion.
     */
    public function test_store_autenticado_sin_permiso_da_403(): void
    {
        $usuario = $this->actuandoComo('Desarrolladores', 48);

        $this->actingAs($usuario)->post('/desarrolladores')->assertForbidden();
    }

    /**
     * El modulo de muestras se gobierna con su propio rol (189), no con el de Desarrolladores.
     */
    public function test_muestras_autenticado_sin_permiso_da_403(): void
    {
        $usuario = $this->actuandoComo('Desarrolladores Muestras', 189);

        $this->actingAs($usuario)->post('/desarrolladores-muestras')->assertForbidden();
    }

    /**
     * /desarrolladores quedo como enlace historico: debe redirigir permanentemente
     * a la URL que el menu tiene guardada en SYSRoles.Ruta. La ruta vive dentro del
     * grupo 'auth', asi que el invitado va antes al login: hay que autenticarse.
     */
    public function test_url_legacy_redirige_a_la_del_menu(): void
    {
        $usuario = $this->actuandoComo('Desarrolladores', 48);

        $this->actingAs($usuario)
            ->get('/desarrolladores')
            ->assertRedirect('/tejedores/desarrolladores')
            ->assertStatus(301);
    }

    /**
     * All expected desarrolladores named routes must be registered.
     */
    public function test_rutas_desarrolladores_existen(): void
    {
        $routes = Route::getRoutes();

        $this->assertNotNull(
            $routes->getByName('tejedores.desarrolladores'),
            "Named route 'tejedores.desarrolladores' must exist"
        );

        $this->assertNotNull(
            $routes->getByName('desarrolladores.store'),
            "Named route 'desarrolladores.store' must exist"
        );
    }
}
