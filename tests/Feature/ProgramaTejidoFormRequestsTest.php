<?php

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Tests\TestCase;

class ProgramaTejidoFormRequestsTest extends TestCase
{
    private function usuario(): Usuario
    {
        $u = new Usuario(['idusuario' => 1, 'nombre' => 'T', 'contrasenia' => 'x', 'numero_empleado' => '1', 'area' => 'X']);
        $u->idusuario = 1;
        return $u;
    }

    // --- DUPLICAR ---

    public function test_duplicar_sin_salon_tejido_id_devuelve_422(): void
    {
        $res = $this->actingAs($this->usuario())
            ->postJson(route('programa-tejido.duplicar-telar'), [
                'no_telar_id' => 'T01',
                'destinos'    => [['telar' => 'T02']],
            ]);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['salon_tejido_id']);
    }

    public function test_duplicar_sin_no_telar_id_devuelve_422(): void
    {
        $res = $this->actingAs($this->usuario())
            ->postJson(route('programa-tejido.duplicar-telar'), [
                'salon_tejido_id' => 'S01',
                'destinos'        => [['telar' => 'T02']],
            ]);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['no_telar_id']);
    }

    public function test_duplicar_sin_destinos_devuelve_422(): void
    {
        $res = $this->actingAs($this->usuario())
            ->postJson(route('programa-tejido.duplicar-telar'), [
                'salon_tejido_id' => 'S01',
                'no_telar_id'     => 'T01',
            ]);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['destinos']);
    }

    // --- DIVIDIR SALDO ---

    public function test_dividir_saldo_sin_salon_devuelve_422(): void
    {
        $res = $this->actingAs($this->usuario())
            ->postJson(route('programa-tejido.dividir-saldo'), [
                'no_telar_id' => 'T01',
                'destinos'    => [['telar' => 'T02']],
            ]);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['salon_tejido_id']);
    }

    public function test_dividir_saldo_destinos_telar_requerido(): void
    {
        $res = $this->actingAs($this->usuario())
            ->postJson(route('programa-tejido.dividir-saldo'), [
                'salon_tejido_id' => 'S01',
                'no_telar_id'     => 'T01',
                'destinos'        => [['salon_destino' => 'S01']], // sin 'telar'
            ]);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['destinos.0.telar']);
    }

    // --- DIVIDIR TELAR ---

    public function test_dividir_telar_sin_posicion_devuelve_422(): void
    {
        $res = $this->actingAs($this->usuario())
            ->postJson(route('programa-tejido.dividir-telar'), [
                'salon_tejido_id' => 'S01',
                'no_telar_id'     => 'T01',
                'nuevo_telar'     => 'T02',
                // sin posicion_division
            ]);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['posicion_division']);
    }
}
