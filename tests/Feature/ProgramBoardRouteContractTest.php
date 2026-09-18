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

    public function test_urdido_livewire_alias_redirects_to_the_default_board(): void
    {
        $route = Route::getRoutes()->getByName('urdido.programar.urdido.livewire');

        $this->assertNotNull($route);
        $this->assertInstanceOf(IlluminateRoute::class, $route);
        $this->assertSame('urdido/programar-urdido/livewire', $route->uri());
        $this->assertContains('auth', $route->gatherMiddleware());
    }

    public function test_guest_cannot_open_program_boards_or_mutate_status(): void
    {
        foreach ([
            ['GET', '/urdido/programar-urdido'],
            ['GET', '/engomado/programar-engomado'],
            ['GET', '/urdido/programar-urdido/livewire'],
            ['POST', '/urdido/programar-urdido/actualizar-status'],
            ['POST', '/engomado/programar-engomado/actualizar-status'],
        ] as [$method, $uri]) {
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

        $json = $this->postJson('/engomado/programar-engomado/actualizar-status', [
            'id' => 1,
            'status' => 'En Proceso',
        ]);
        $this->assertContains($json->status(), [401, 302]);
    }
}
