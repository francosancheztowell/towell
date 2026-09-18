<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Paros de mantenimiento: Franco pidió revertir el gate de módulo.
 * Guest sigue bloqueado; cualquier usuario autenticado (sin Solicitudes) no recibe 403 de userCan.
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

    private function assertNoUserCanForbidden(int $status, mixed $payload): void
    {
        $this->assertNotSame(
            403,
            $status,
            'El API de paros no debe devolver 403 por userCan(Solicitudes); solo se exige sesión.'
        );

        if (! is_array($payload)) {
            return;
        }

        $error = (string) ($payload['error'] ?? '');
        $this->assertStringNotContainsString('No tienes permiso para reportar paros.', $error);
        $this->assertStringNotContainsString('No tienes permiso para finalizar paros.', $error);
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

    public function test_autenticado_sin_permiso_de_modulo_puede_reportar_paro(): void
    {
        $usuario = $this->actuandoComo([]);

        $response = $this->actingAs($usuario)->postJson('/api/mantenimiento/paros', []);

        $this->assertNoUserCanForbidden($response->status(), $response->json());
        $response->assertStatus(422);
    }

    public function test_autenticado_sin_permiso_de_modulo_puede_finalizar_paro(): void
    {
        $usuario = $this->actuandoComo(['acceso' => 1]);

        $response = $this->actingAs($usuario)->putJson('/api/mantenimiento/paros/1/finalizar', [
            'atendio' => 'Operador',
            'calidad' => 5,
        ]);

        $this->assertNoUserCanForbidden($response->status(), $response->json());
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
