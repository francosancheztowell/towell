<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProgramaTejidoRouteContractTest extends TestCase
{
    public function test_programa_tejido_core_route_contracts_remain_stable(): void
    {
        $this->assertRouteContract(
            routeName: 'catalogos.req-programa-tejido',
            expectedUri: 'planeacion/programa-tejido',
            expectedMethods: ['GET', 'HEAD'],
            expectedMiddleware: ['web', 'auth'],
        );

        $this->assertRouteContract(
            routeName: 'programa-tejido.balancear',
            expectedUri: 'planeacion/programa-tejido/balancear',
            expectedMethods: ['GET', 'HEAD'],
            expectedMiddleware: ['web', 'auth'],
        );

        $this->assertRouteContract(
            routeName: 'verdetallesgrupobalanceo',
            expectedUri: 'planeacion/programa-tejido/ver-detalles-grupo-balanceo/{ordCompartida}',
            expectedMethods: ['GET', 'HEAD'],
            expectedMiddleware: ['web', 'auth'],
        );

        $this->assertRouteContract(
            routeName: 'programa-tejido.update',
            expectedUri: 'planeacion/programa-tejido/{id}',
            expectedMethods: ['PUT'],
            expectedMiddleware: ['web', 'auth'],
        );
    }

    public function test_programa_tejido_auxiliary_named_route_contract_remains_stable(): void
    {
        $this->assertRouteContract(
            routeName: 'programa-tejido.salon-tejido-options',
            expectedUri: 'programa-tejido/salon-tejido-options',
            expectedMethods: ['GET', 'HEAD'],
            expectedMiddleware: ['web', 'auth'],
        );

        $this->assertRouteContract(
            routeName: 'programa-tejido.registros-ord-compartida',
            expectedUri: 'planeacion/programa-tejido/registros-ord-compartida/{ordCompartida}',
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
