<?php

declare(strict_types=1);

namespace Tests\Feature\Tejedores;

use Illuminate\Support\Facades\Schema;
use Tests\Feature\ProgramaUrdEng\Concerns\InventarioUrdEngSqlite;
use Tests\TestCase;

/**
 * Contrato de los endpoints JSON de Inv Telas (InventarioTelaresController) que ya no
 * llevan try/catch propio: el caso feliz no cambia y un error de BD sale por el handler
 * central (500, success:false, trace_id) sin el texto de la excepción.
 */
class InventarioTelaresEndpointsTest extends TestCase
{
    use InventarioUrdEngSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararInventario();
    }

    protected function tearDown(): void
    {
        $this->tearDownInventario();
        parent::tearDown();
    }

    public function test_verificar_turnos_ocupados_devuelve_ocupados_y_disponibles(): void
    {
        $this->telar(['no_telar' => '201', 'tipo' => 'Rizo', 'fecha' => '2026-10-06', 'turno' => '2']);
        $this->telar(['no_telar' => '201', 'tipo' => 'Pie', 'fecha' => '2026-10-06', 'turno' => '1']);

        $this->actingAs($this->usuarioCon(['Inv Telas' => ['acceso']]))
            ->getJson(route('inventario.telares.modulo.verificar.turnos.ocupados', [
                'no_telar' => '201', 'tipo' => 'RIZO', 'fecha' => '2026-10-06',
            ]))
            ->assertOk()
            ->assertExactJson(['success' => true, 'turnos_ocupados' => [2], 'turnos_disponibles' => [1, 3]]);
    }

    public function test_error_de_bd_responde_500_generico_sin_exponer_la_excepcion(): void
    {
        Schema::connection('sqlsrv')->drop('tej_inventario_telares');
        config()->set('app.debug', false);

        $r = $this->actingAs($this->usuarioCon(['Inv Telas' => ['acceso']]))
            ->getJson(route('inventario.telares.modulo.verificar.turnos.ocupados', [
                'no_telar' => '201', 'tipo' => 'Rizo', 'fecha' => '2026-10-06',
            ]))
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'trace_id']);

        $this->assertStringNotContainsString('SQLSTATE', $r->getContent());
        $this->assertStringNotContainsString('tej_inventario_telares', $r->getContent());
    }
}
