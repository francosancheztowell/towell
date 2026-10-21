<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class KardexRouteContractTest extends TestCase
{
    public function test_kardex_route_matches_sysroles_and_requires_module_access(): void
    {
        $route = Route::getRoutes()->getByName('kardex.index');

        $this->assertInstanceOf(IlluminateRoute::class, $route);
        $this->assertSame('kardex', $route->uri());
        $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $route->methods());
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
        // SYSRoles Kardex is idrol 209 (210 is "Calificar Julios").
        $this->assertContains('module.permission:acceso,209', $route->gatherMiddleware());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/kardex')->assertRedirect();
    }
}
