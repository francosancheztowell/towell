<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TejidoReportesRouteContractTest extends TestCase
{
    public function test_promedio_paros_eficiencia_report_routes_remain_stable(): void
    {
        $this->assertRouteContract(
            routeName: 'tejido.reportes.promedio-paros-eficiencia',
            expectedUri: 'tejido/reportes/promedio-paros-eficiencia',
            expectedMethods: ['GET', 'HEAD'],
            expectedMiddleware: ['web', 'auth'],
        );

        $this->assertRouteContract(
            routeName: 'tejido.reportes.promedio-paros-eficiencia.excel',
            expectedUri: 'tejido/reportes/promedio-paros-eficiencia/excel',
            expectedMethods: ['GET', 'HEAD'],
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
