<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EngomadoReportesRouteContractTest extends TestCase
{
    public function test_control_merma_report_routes_remain_stable(): void
    {
        $this->assertRouteContract(
            routeName: 'engomado.reportes.control-merma',
            expectedUri: 'engomado/reportesengomado/control-merma',
            expectedMethods: ['GET', 'HEAD'],
            expectedMiddleware: ['web', 'auth'],
        );

        $this->assertRouteContract(
            routeName: 'engomado.reportes.control-merma.excel',
            expectedUri: 'engomado/reportesengomado/control-merma/excel',
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
