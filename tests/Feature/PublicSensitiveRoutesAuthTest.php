<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicSensitiveRoutesAuthTest extends TestCase
{
    /**
     * Endpoints that were public (BUG-001 / BUG-002). Guests must not reach them.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function formerPublicSensitiveEndpoints(): array
    {
        return [
            'obtener empleados GET' => ['GET', '/obtener-empleados/Tejedores'],
        ];
    }

    #[DataProvider('formerPublicSensitiveEndpoints')]
    public function test_guest_cannot_hit_former_public_sensitive_endpoints(string $method, string $uri): void
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

    public function test_guest_json_cannot_list_empleados(): void
    {
        $response = $this->getJson('/obtener-empleados/Tejedores');

        $this->assertContains(
            $response->status(),
            [401, 302],
            "JSON GET /obtener-empleados/{area} should return 401 or 302, got {$response->status()}"
        );
    }

    public function test_sensitive_named_routes_require_auth_middleware(): void
    {
        $this->assertRouteHasAuth('usuarios.obtener-empleados');

        // /modulos-sin-auth se retiro (ERP-F0-08): la gestion vive solo en configuracion/utileria/modulos.
        foreach (['modulos.sin.auth.index', 'modulos.gestion.index'] as $legacy) {
            $this->assertNull(
                Route::getRoutes()->getByName($legacy),
                "Legacy name {$legacy} must not remain registered."
            );
        }
    }

    private function assertRouteHasAuth(string $routeName): void
    {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "No se encontro la ruta [{$routeName}].");
        $this->assertInstanceOf(IlluminateRoute::class, $route);
        $this->assertContains('auth', $route->gatherMiddleware(), "Middleware auth faltante en [{$routeName}].");
    }
}
