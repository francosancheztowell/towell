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

    public function test_mp_kardex_reparte_el_importe_del_kardex_y_suma_igual(): void
    {
        $julios = collect([
            ['mes' => 9, 'maquina' => 'Mc Coy 1', 'mp' => 1000.0],
            ['mes' => 9, 'maquina' => 'Mc Coy 1', 'mp' => 3000.0],
            ['mes' => 9, 'maquina' => 'Karl Mayer', 'mp' => 500.0],  // su máquina no viene en el kardex: se queda
            ['mes' => 10, 'maquina' => 'Mc Coy 1', 'mp' => 700.0],   // mes sin kardex: se queda
        ]);

        $ajustados = CostoJulios::ajustarAlKardex($julios, [9 => ['Mc Coy 1' => ['kg' => 0.0, 'imp' => 4400.0]], 10 => null]);

        $this->assertEqualsWithDelta([1100.0, 3300.0, 500.0, 700.0], $ajustados->pluck('mp')->all(), 0.0001);
        $this->assertEqualsWithDelta([1000.0, 3000.0, 500.0, 700.0], $ajustados->pluck('mpFormula')->all(), 0.0001);
        $this->assertEqualsWithDelta(4400.0, $ajustados->where('mes', 9)->where('maquina', 'Mc Coy 1')->sum('mp'), 0.0001);
    }

    public function test_veredicto_marca_hilo_y_sabana_fuera_y_cuenta_sin_costo(): void
    {
        // 151 de 206 kg: merma 26.7 %, fuera; sábana con $5 o más de diferencia: no cuadra; 3 julios sin costo.
        $fuera = CostoJulios::veredicto(151.0, 206.0, ['julios' => 1000.0, 'sab' => 1010.0], 3);
        $this->assertSame(['fuera', 'fuera', 'fuera'], array_column($fuera, 'estado'));
        $this->assertStringContainsString('Merma del hilo 26.7 %', $fuera[0]['titulo']);

        // Merma 3 % y $4 de redondeo: cuadra; sin julios pendientes no hay tercer punto.
        $ok = CostoJulios::veredicto(97.0, 100.0, ['julios' => 1000.0, 'sab' => 1004.0], 0);
        $this->assertSame(['ok', 'ok'], array_column($ok, 'estado'));

        // Bordes del rango: 8 % cuadra, 9 % no; más hilo en julios que en el kardex (merma negativa) tampoco.
        $this->assertFalse(CostoJulios::mermaFuera(CostoJulios::merma(92.0, 100.0)));
        $this->assertTrue(CostoJulios::mermaFuera(CostoJulios::merma(91.0, 100.0)));
        $this->assertTrue(CostoJulios::mermaFuera(CostoJulios::merma(101.0, 100.0)));

        // Julios sin kg (mes abierto o captura pendiente) van primero en el veredicto.
        $this->assertStringStartsWith('2 julios sin kg', CostoJulios::veredicto(0.0, null, null, 0, 2)[0]['titulo']);

        // Sin kardex ni cuota real: no hay con qué comparar, no se inventa un cuadre.
        $this->assertSame(['sin', 'sin'], array_column(CostoJulios::veredicto(50.0, null, null, 0), 'estado'));
    }
}
