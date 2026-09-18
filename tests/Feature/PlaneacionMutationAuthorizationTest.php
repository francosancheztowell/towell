<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * BUG-003 esqueleto: mutaciones P0 de Planeación no pueden ejecutarse
 * solo con sesión. userCan() se resuelve desde la memoización del helper
 * (igual que DesarrolladoresRouteContractTest) para no tocar SQL Server.
 */
class PlaneacionMutationAuthorizationTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function mutationEndpoints(): array
    {
        return [
            'liberar programa tejido' => ['POST', '/planeacion/programa-tejido/liberar-ordenes/procesar'],
            'liberar muestras' => ['POST', '/planeacion/muestras/liberar-ordenes/procesar'],
            'guardar lmat' => ['POST', '/planeacion/lmat/api/guardar'],
            'mover ordenes' => ['POST', '/planeacion/utileria/mover/procesar'],
            'finalizar ordenes' => ['POST', '/planeacion/utileria/finalizar/procesar'],
        ];
    }

    #[DataProvider('mutationEndpoints')]
    public function test_guest_no_puede_pegarle_a_mutaciones_de_planeacion(string $method, string $uri): void
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

    #[DataProvider('mutationEndpoints')]
    public function test_guest_json_no_puede_pegarle_a_mutaciones_de_planeacion(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);

        $this->assertContains(
            $response->status(),
            [401, 302],
            "JSON {$method} {$uri} should return 401 or 302, got {$response->status()}"
        );
    }

    #[DataProvider('mutationEndpoints')]
    public function test_autenticado_sin_permiso_de_modulo_recibe_403(string $method, string $uri): void
    {
        $usuario = $this->usuarioSinPermisoDeModulo();

        $this->actingAs($usuario)->json($method, $uri)->assertForbidden();
    }

    public function test_rutas_p0_declaran_middleware_modulo_permiso(): void
    {
        $this->assertRouteHasModuloPermiso(
            'programa-tejido.liberar-ordenes.procesar',
            'modulo.permiso:registrar,Programa Tejido',
        );
        $this->assertRouteHasModuloPermiso(
            'muestras.liberar-ordenes.procesar',
            'modulo.permiso:registrar,Programa Tejido',
        );
        $this->assertRouteHasModuloPermiso(
            'planeacion.lmat.guardar',
            'modulo.permiso:modificar,Codificación',
        );
        $this->assertRouteHasModuloPermiso(
            'planeacion.utileria.mover.procesar',
            'modulo.permiso:modificar,Utilería',
        );
        $this->assertRouteHasModuloPermiso(
            'planeacion.utileria.finalizar.procesar',
            'modulo.permiso:modificar,Utilería',
        );
    }

    /**
     * Siembra la memoización de userPermissions() sin SYSUsuariosRoles.
     * El usuario existe en el guard; no tiene fila de permiso para ningún módulo.
     */
    private function usuarioSinPermisoDeModulo(): Usuario
    {
        $usuario = new Usuario(['nombre' => 'Sin Permiso Planeacion']);
        $usuario->idusuario = 999003;

        app()->instance('permisos.roles', collect([
            'programa tejido' => (object) ['idrol' => 1, 'modulo' => 'Programa Tejido'],
            'codificación' => (object) ['idrol' => 2, 'modulo' => 'Codificación'],
            'utilería' => (object) ['idrol' => 3, 'modulo' => 'Utilería'],
        ]));
        app()->instance('permisos.usuario.'.$usuario->idusuario, collect());

        return $usuario;
    }

    private function assertRouteHasModuloPermiso(string $routeName, string $expectedMiddleware): void
    {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "No se encontro la ruta [{$routeName}].");
        $this->assertInstanceOf(IlluminateRoute::class, $route);
        $this->assertContains('auth', $route->gatherMiddleware(), "Middleware auth faltante en [{$routeName}].");
        $this->assertContains(
            $expectedMiddleware,
            $route->gatherMiddleware(),
            "Middleware [{$expectedMiddleware}] faltante en [{$routeName}].",
        );
    }
}
