<?php

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Tests\TestCase;

class ProgramaTejidoIndexSmokeTest extends TestCase
{
    public function test_ruta_programa_tejido_devuelve_200_con_usuario_autenticado(): void
    {
        $user = new Usuario([
            'idusuario' => 1,
            'numero_empleado' => '00001',
            'nombre' => 'Test',
            'contrasenia' => 'hashed',
            'area' => 'TEST',
        ]);
        $user->idusuario = 1;

        if (config('database.connections.sqlsrv.driver') === 'sqlite' || config('database.default') !== 'sqlsrv') {
            $this->markTestSkipped('Requiere conexión SQL Server real');
        }

        $response = $this->actingAs($user)->get('/planeacion/programa-tejido');
        $response->assertStatus(200);
    }
}
