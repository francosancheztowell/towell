<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProgramaTejidoOperacionesTest extends TestCase
{
    public function test_move_to_position_sin_auth_no_autoriza(): void
    {
        $response = $this->postJson('/planeacion/programa-tejido/1/prioridad/mover', [
            'nueva_posicion' => 1,
        ]);

        $this->assertTrue(in_array($response->status(), [302, 401], true), 'Debe redirigir o retornar 401 sin auth');
    }

    public function test_ruta_move_to_position_existe(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('programa-tejido.prioridad.mover');
        $this->assertNotNull($route);
        $this->assertStringContainsString('prioridad/mover', $route->uri());
    }
}
