<?php

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Tests\TestCase;

class ProgramaTejidoVincularDuplicarDividirJsonTest extends TestCase
{
    private function actingUsuario(): Usuario
    {
        $user = new Usuario([
            'idusuario' => 1,
            'numero_empleado' => '00001',
            'nombre' => 'Test User',
            'contrasenia' => 'hashed',
            'area' => 'TEST',
        ]);
        $user->idusuario = 1;

        return $user;
    }

    public function test_vincular_registros_existentes_validation_returns_json_422_programa_y_muestras(): void
    {
        $user = $this->actingUsuario();

        foreach (['programa-tejido.vincular-registros-existentes', 'muestras.vincular-registros-existentes'] as $routeName) {
            $response = $this->actingAs($user)->postJson(route($routeName), [
                'registros_ids' => [1],
            ]);

            $response->assertUnprocessable();
            $response->assertHeader('content-type', 'application/json');
            $response->assertJsonStructure(['message', 'errors' => ['registros_ids']]);
        }
    }

    public function test_duplicar_telar_validation_returns_json_422_with_vincular_true_programa_y_muestras(): void
    {
        $user = $this->actingUsuario();

        foreach (['programa-tejido.duplicar-telar', 'muestras.duplicar-telar'] as $routeName) {
            $response = $this->actingAs($user)->postJson(route($routeName), [
                'vincular' => true,
            ]);

            $response->assertUnprocessable();
            $response->assertHeader('content-type', 'application/json');
            $response->assertJsonStructure(['message', 'errors']);
        }
    }

    public function test_dividir_saldo_validation_returns_json_422_programa_y_muestras(): void
    {
        $user = $this->actingUsuario();

        foreach (['programa-tejido.dividir-saldo', 'muestras.dividir-saldo'] as $routeName) {
            $response = $this->actingAs($user)->postJson(route($routeName), []);

            $response->assertUnprocessable();
            $response->assertHeader('content-type', 'application/json');
            $response->assertJsonStructure(['message', 'errors']);
        }
    }
}
