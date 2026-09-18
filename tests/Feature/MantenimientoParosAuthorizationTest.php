<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * BUG-003 (slice Mantto): store/finalizar de paros no se autorizan con el menú.
 * userCan('crear'|'modificar', 'Solicitudes') es el candado real.
 */
class MantenimientoParosAuthorizationTest extends TestCase
{
    /**
     * Siembra la memoización de userPermissions() sin tocar SYSUsuariosRoles.
     *
     * @param  array<string, int>  $permisos  Ej. ['acceso' => 1, 'crear' => 1]. Vacío = sin fila del módulo.
     */
    private function actuandoComo(array $permisos = []): Usuario
    {
        $modulo = 'Solicitudes';
        $idrol = 77;

        $usuario = new Usuario(['nombre' => 'Test Paros']);
        $usuario->idusuario = 999777;

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

    private function assertGuestAuthBlocked(string $method, string $uri): void
    {
        $response = $this->call($method, $uri);

        $this->assertContains(
            $response->status(),
            [302, 401],
            "{$method} {$uri} should redirect or return 401 when unauthenticated, got {$response->status()}"
        );

        if ($response->status() === 302) {
            $response->assertRedirect(route('login'));
        }
    }

    public function test_guest_no_puede_crear_paro(): void
    {
        $this->assertGuestAuthBlocked('POST', '/api/mantenimiento/paros');
    }

    public function test_guest_no_puede_finalizar_paro(): void
    {
        $this->assertGuestAuthBlocked('PUT', '/api/mantenimiento/paros/1/finalizar');
    }

    public function test_guest_json_no_puede_crear_ni_finalizar_paro(): void
    {
        $store = $this->postJson('/api/mantenimiento/paros', []);
        $this->assertContains(
            $store->status(),
            [401, 302],
            "JSON POST /api/mantenimiento/paros should return 401 or 302, got {$store->status()}"
        );

        $finalizar = $this->putJson('/api/mantenimiento/paros/1/finalizar', []);
        $this->assertContains(
            $finalizar->status(),
            [401, 302],
            "JSON PUT finalizar should return 401 or 302, got {$finalizar->status()}"
        );
    }

    public function test_autenticado_sin_crear_no_puede_reportar_paro(): void
    {
        $usuario = $this->actuandoComo(['acceso' => 1]);

        $this->actingAs($usuario)
            ->postJson('/api/mantenimiento/paros', [
                'depto' => 'Tejedores',
                'maquina' => 'T-01',
                'falla_id' => 1,
            ])
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'error' => 'No tienes permiso para reportar paros.',
            ]);
    }

    public function test_autenticado_sin_modificar_no_puede_finalizar_paro(): void
    {
        $usuario = $this->actuandoComo(['acceso' => 1, 'crear' => 1]);

        $this->actingAs($usuario)
            ->putJson('/api/mantenimiento/paros/1/finalizar', [
                'atendio' => 'Operador',
                'calidad' => 5,
            ])
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'error' => 'No tienes permiso para finalizar paros.',
            ]);
    }

    public function test_con_permiso_crear_el_store_no_devuelve_403(): void
    {
        $usuario = $this->actuandoComo(['acceso' => 1, 'crear' => 1]);

        $response = $this->actingAs($usuario)->postJson('/api/mantenimiento/paros', []);

        $this->assertNotSame(
            403,
            $response->status(),
            'Con permiso crear el alta no debe cortarse por AuthZ (se espera 422 de validación).'
        );
        $response->assertStatus(422);
    }

    public function test_rutas_de_mutacion_exigen_auth(): void
    {
        $this->assertRouteHasAuth('api.mantenimiento.paros.store');
        $this->assertRouteHasAuth('api.mantenimiento.paros.finalizar');
    }

    private function assertRouteHasAuth(string $routeName): void
    {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "No se encontro la ruta [{$routeName}].");
        $this->assertInstanceOf(IlluminateRoute::class, $route);
        $this->assertContains('auth', $route->gatherMiddleware(), "Middleware auth faltante en [{$routeName}].");
    }
}
