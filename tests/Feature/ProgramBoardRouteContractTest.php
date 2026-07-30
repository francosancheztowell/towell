<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProgramBoardRouteContractTest extends TestCase
{
    public function test_current_and_legacy_alias_program_routes_are_authenticated(): void
    {
        foreach ([
            'urdido.programar.urdido' => 'urdido/programar-urdido',
            'urdido.programar.urdido.legacy' => 'urdido/programar-urdido/legacy',
            'engomado.programar.engomado' => 'engomado/programar-engomado',
            'engomado.programar.engomado.legacy' => 'engomado/programar-engomado/legacy',
        ] as $name => $uri) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route);
            $this->assertInstanceOf(IlluminateRoute::class, $route);
            $this->assertSame($uri, $route->uri());
            $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $route->methods());
            $this->assertContains('auth', $route->gatherMiddleware());
        }
    }
}
