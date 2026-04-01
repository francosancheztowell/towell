<?php

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Tests\TestCase;

class ProgramaTejidoSmokeTest extends TestCase
{
    /**
     * Verifica que la ruta principal de programa-tejido responde correctamente.
     * Este test existe para garantizar que cambios en vistas/JS no rompan el flujo básico.
     */
    public function test_programa_tejido_index_route_returns_view(): void
    {
        $user = new Usuario([
            'idusuario'       => 1,
            'numero_empleado' => '00001',
            'nombre'          => 'Test User',
            'contrasenia'     => 'hashed',
            'area'            => 'TEST',
        ]);
        $user->idusuario = 1;

        $response = $this->actingAs($user)->get(route('catalogos.req-programa-tejido'));

        $response->assertStatus(200);
    }

    public function test_balancear_route_returns_view(): void
    {
        $user = new Usuario([
            'idusuario'       => 1,
            'numero_empleado' => '00001',
            'nombre'          => 'Test User',
            'contrasenia'     => 'hashed',
            'area'            => 'TEST',
        ]);
        $user->idusuario = 1;

        $response = $this->actingAs($user)->get(route('programa-tejido.balancear'));

        $response->assertStatus(200);
    }
}
