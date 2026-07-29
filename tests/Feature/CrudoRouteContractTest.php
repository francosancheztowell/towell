<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CrudoRouteContractTest extends TestCase
{
    public function test_crudo_dashboard_route_is_authenticated_and_stable(): void
    {
        $route = Route::getRoutes()->getByName('crudo.index');

        $this->assertNotNull($route);
        $this->assertInstanceOf(IlluminateRoute::class, $route);
        $this->assertSame('Crudo', $route->uri());
        $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $route->methods());
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
    }
}
