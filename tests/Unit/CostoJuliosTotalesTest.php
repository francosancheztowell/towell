<?php

namespace Tests\Unit;

use App\Livewire\Costos\CostoJulios;
use PHPUnit\Framework\TestCase;

class CostoJuliosTotalesTest extends TestCase
{
    public function test_suma_conceptos_visibles_y_pondera_el_costo_por_kg(): void
    {
        $julios = collect([
            ['kg' => 100.0, 'mp' => 4000.0, 'MOD' => 100.0, 'GtsF' => 50.0],
            ['kg' => 300.0, 'mp' => 15000.0, 'MOD' => 200.0, 'GtsF' => 150.0],
            ['kg' => null, 'mp' => null, 'MOD' => null, 'GtsF' => null], // julio sin captura: no rompe ni suma
        ]);

        $absorbente = CostoJulios::totales($julios, ['MOD' => 'MOD', 'GtsF' => 'GtsF']);
        $this->assertSame(3, $absorbente['julios']);
        $this->assertSame(500.0, $absorbente['conversion']);
        $this->assertSame(19500.0, $absorbente['total']);
        $this->assertSame(48.75, $absorbente['totalKg']); // 19500 / 400, ponderado por kg

        // Directo: GtsF no viene en los conceptos y no entra al total.
        $directo = CostoJulios::totales($julios, ['MOD' => 'MOD']);
        $this->assertSame(19300.0, $directo['total']);

        $this->assertNull(CostoJulios::totales(collect(), [])['totalKg']);
    }
}
