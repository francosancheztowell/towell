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

    public function test_veredicto_marca_kg_y_sabana_fuera_y_cuenta_sin_costo(): void
    {
        // AX recibió 151 de 206 kg de julios: los kg no cuadran; sábana con $5 o más de diferencia; 3 julios sin costo.
        $fuera = CostoJulios::veredicto(206.0, 151.0, ['julios' => 1000.0, 'sab' => 1010.0], 3, 0, ['kg' => 2, 'maquina' => 1]);
        $this->assertSame(['fuera', 'fuera', 'fuera'], array_column($fuera, 'estado'));
        $this->assertStringContainsString('-55 kg', $fuera[0]['detalle']);
        $this->assertStringContainsString('2 folios con otros kg que AX; 1 folio en otra máquina', $fuera[0]['detalle']);

        // Kg iguales salvo redondeo y $4 de diferencia: cuadra; sin julios pendientes no hay tercer punto.
        $ok = CostoJulios::veredicto(299.9, 299.89999, ['julios' => 1000.0, 'sab' => 1004.0], 0);
        $this->assertSame(['ok', 'ok'], array_column($ok, 'estado'));

        // El total cuadra pero un folio está en otra máquina: por máquina no cuadra.
        $this->assertSame('fuera', CostoJulios::veredicto(100.0, 100.0, null, 0, 0, ['kg' => 0, 'maquina' => 1])[0]['estado']);

        // Julios sin kg (mes abierto o captura pendiente) van primero en el veredicto.
        $this->assertStringStartsWith('2 julios sin kg', CostoJulios::veredicto(0.0, null, null, 0, 2)[0]['titulo']);

        // Sin kardex de julios ni cuota real: no hay con qué comparar, no se inventa un cuadre.
        $this->assertSame(['sin', 'sin'], array_column(CostoJulios::veredicto(50.0, null, null, 0), 'estado'));
    }

    public function test_folios_distintos_marca_maquina_y_kg_contra_lo_recibido_en_ax(): void
    {
        $julios = collect([
            ['folio' => '00507', 'maquina' => 'Mc Coy 2', 'kg' => 305.5],
            ['folio' => '00507', 'maquina' => 'Mc Coy 2', 'kg' => 305.5],
            ['folio' => '00509', 'maquina' => 'Mc Coy 2', 'kg' => 1796.39999], // redondeo del float: cuadra
            ['folio' => '00600', 'maquina' => 'Mc Coy 1', 'kg' => 500.0],      // AX no lo recibió este mes
            ['folio' => '00700', 'maquina' => 'Mc Coy 2', 'kg' => null],       // sin kg ni recepción: cuadra
        ]);
        $recibido = [
            '00507' => ['loc' => 'MC3', 'kg' => 611.0],     // mismos kg, otra máquina
            '00509' => ['loc' => 'MC2', 'kg' => 1796.4],
            '00650' => ['loc' => 'KM1', 'kg' => 958.8],     // recibido sin julios en UrdProduccionUrdido
        ];

        $folios = CostoJulios::foliosDistintos($julios, $recibido);

        $this->assertSame(['00650', '00600', '00507'], array_column($folios, 'folio')); // la mayor diferencia de kg primero
        $this->assertSame(['folio' => '00507', 'maquina' => 'Mc Coy 2', 'maquinaAx' => 'Mc Coy 3', 'kgJulios' => 611.0, 'kgAx' => 611.0, 'dif' => 0.0, 'maquinaDistinta' => true], $folios[2]);
        $this->assertSame(['', 'Karl Mayer', false], [$folios[0]['maquina'], $folios[0]['maquinaAx'], $folios[0]['maquinaDistinta']]);
        $this->assertSame(-500.0, $folios[1]['dif']);
    }
}
