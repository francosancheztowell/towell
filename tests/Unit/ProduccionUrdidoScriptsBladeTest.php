<?php

namespace Tests\Unit;

use App\Models\Urdido\UrdProgramaUrdido;
use Tests\TestCase;

class ProduccionUrdidoScriptsBladeTest extends TestCase
{
    public function test_scripts_do_not_read_id_when_orden_is_a_string(): void
    {
        $html = view('modulos.urdido.produccion._scripts', [
            'orden' => 'folio-no-es-objeto',
            'maxKgNeto' => 700,
            'isKarlMayer' => false,
        ])->render();

        $this->assertStringContainsString('No hay orden seleccionada', $html);
        $this->assertStringNotContainsString('const ordenId', $html);
    }

    public function test_scripts_emit_orden_id_from_model(): void
    {
        $orden = new UrdProgramaUrdido;
        $orden->Id = 42;

        $html = view('modulos.urdido.produccion._scripts', [
            'orden' => $orden,
            'maxKgNeto' => 700,
            'isKarlMayer' => false,
        ])->render();

        $this->assertStringContainsString('const ordenId = 42;', $html);
    }
}
