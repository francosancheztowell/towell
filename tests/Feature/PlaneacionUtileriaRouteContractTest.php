<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PlaneacionUtileriaRouteContractTest extends TestCase
{
    public function test_planeacion_utileria_route_contracts_remain_stable(): void
    {
        $this->assertRouteContract(
            routeName: 'planeacion.utileria.index',
            expectedUri: 'planeacion/utileria',
            expectedMethods: ['GET', 'HEAD'],
            expectedMiddleware: ['web', 'auth'],
        );

        $this->assertRouteContract(
            routeName: 'planeacion.utileria.finalizar.procesar',
            expectedUri: 'planeacion/utileria/finalizar/procesar',
            expectedMethods: ['POST'],
            expectedMiddleware: ['web', 'auth'],
        );

        $this->assertRouteContract(
            routeName: 'planeacion.utileria.mover.procesar',
            expectedUri: 'planeacion/utileria/mover/procesar',
            expectedMethods: ['POST'],
            expectedMiddleware: ['web', 'auth'],
        );
    }

    private function assertRouteContract(
        string $routeName,
        string $expectedUri,
        array $expectedMethods,
        array $expectedMiddleware
    ): void {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "No se encontro la ruta [$routeName].");
        $this->assertInstanceOf(IlluminateRoute::class, $route);
        $this->assertSame($expectedUri, $route->uri(), "URI inesperada en [$routeName].");
        $this->assertEqualsCanonicalizing($expectedMethods, $route->methods(), "Metodos HTTP inesperados en [$routeName].");

        $assignedMiddleware = $route->gatherMiddleware();
        foreach ($expectedMiddleware as $middleware) {
            $this->assertContains($middleware, $assignedMiddleware, "Middleware [$middleware] faltante en [$routeName].");
        }
    }
}
