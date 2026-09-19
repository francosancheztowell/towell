<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProgramBoardRouteContractTest extends TestCase
{
    public function test_current_program_board_routes_are_authenticated(): void
    {
        foreach ([
            'urdido.programar.urdido' => 'urdido/programar-urdido',
            'engomado.programar.engomado' => 'engomado/programar-engomado',
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

    public function test_legacy_aliases_are_permanent_redirects_to_the_livewire_board(): void
    {
        foreach ([
            'urdido.programar.urdido.legacy' => [
                'uri' => 'urdido/programar-urdido/legacy',
                'target' => '/urdido/programar-urdido',
            ],
            'engomado.programar.engomado.legacy' => [
                'uri' => 'engomado/programar-engomado/legacy',
                'target' => '/engomado/programar-engomado',
            ],
        ] as $name => $expected) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route);
            $this->assertInstanceOf(IlluminateRoute::class, $route);
            $this->assertSame($expected['uri'], $route->uri());
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertSame(301, $route->defaults['status'] ?? null);
            $this->assertSame($expected['target'], $route->defaults['destination'] ?? null);
        }
    }

    public function test_verificar_en_proceso_routes_are_removed(): void
    {
        $this->assertFalse(Route::getRoutes()->hasNamedRoute('urdido.programar.urdido.verificar.en.proceso'));
        $this->assertFalse(Route::getRoutes()->hasNamedRoute('engomado.programar.engomado.verificar.en.proceso'));

        $this->get('/urdido/programar-urdido/verificar-en-proceso')->assertNotFound();
        $this->get('/engomado/programar-engomado/verificar-en-proceso')->assertNotFound();
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
