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
        // AX consumió 151 de 206 kg de julios: el hilo no cuadra; sábana con $5 o más de diferencia; 3 julios sin costo.
        $fuera = CostoJulios::veredicto(206.0, 151.0, ['julios' => 1000.0, 'sab' => 1010.0], 3, 0, 2);
        $this->assertSame(['fuera', 'fuera', 'fuera'], array_column($fuera, 'estado'));
        $this->assertStringContainsString('-55 kg', $fuera[0]['detalle']);
        $this->assertStringContainsString('en 2 folios', $fuera[0]['detalle']);

        // Kg iguales salvo redondeo y $4 de diferencia: cuadra; sin julios pendientes no hay tercer punto.
        $ok = CostoJulios::veredicto(299.9, 299.89999, ['julios' => 1000.0, 'sab' => 1004.0], 0);
        $this->assertSame(['ok', 'ok'], array_column($ok, 'estado'));

        // El total puede cuadrar con folios que se compensan entre sí: si hay folios sin cuadrar, no cuadra.
        $this->assertSame('fuera', CostoJulios::veredicto(100.0, 100.0, null, 0, 0, 2)[0]['estado']);

        // Julios sin kg (mes abierto o captura pendiente) van primero en el veredicto.
        $this->assertStringStartsWith('2 julios sin kg', CostoJulios::veredicto(0.0, null, null, 0, 2)[0]['titulo']);

        // Sin consumo de AX ni cuota real: no hay con qué comparar, no se inventa un cuadre.
        $this->assertSame(['sin', 'sin'], array_column(CostoJulios::veredicto(50.0, null, null, 0), 'estado'));
    }

    public function test_folios_sin_cuadrar_compara_julios_del_folio_contra_su_consumo_de_ax(): void
    {
        $julios = collect([
            ['folio' => '00345', 'maquina' => 'Mc Coy 3', 'kg' => 1140.25],
            ['folio' => '00345', 'maquina' => 'Mc Coy 3', 'kg' => 1140.25],
            ['folio' => '00350', 'maquina' => 'Mc Coy 1', 'kg' => 299.89999], // redondeo del float: cuadra
            ['folio' => '00471', 'maquina' => 'Mc Coy 1', 'kg' => 603.3],     // AX lo consumió el mes siguiente
            ['folio' => '00500', 'maquina' => 'Mc Coy 2', 'kg' => null],      // sin kg y sin consumo: cuadra
        ]);
        $consumo = [
            '00345' => ['loc' => 'MC3', 'kg' => 1140.25],   // consumo incompleto: la mitad
            '00350' => ['loc' => 'MC1', 'kg' => 299.9],
            '00650' => ['loc' => 'KM1', 'kg' => 958.8],     // consumo de un folio sin julios este mes
        ];

        $folios = CostoJulios::foliosSinCuadrar($julios, $consumo);

        $this->assertSame(['00345', '00650', '00471'], array_column($folios, 'folio')); // la mayor diferencia primero
        $this->assertSame(['folio' => '00345', 'maquina' => 'Mc Coy 3', 'kgJulios' => 2280.5, 'kgAx' => 1140.25, 'dif' => -1140.25], $folios[0]);
        $this->assertSame('Karl Mayer', $folios[1]['maquina']); // sin julios: la máquina sale de la localidad de AX
        $this->assertSame(958.8, $folios[1]['dif']);
        $this->assertSame([], CostoJulios::foliosSinCuadrar(collect([['folio' => '1', 'maquina' => 'X', 'kg' => 5.0]]), ['1' => ['loc' => 'MC1', 'kg' => 5.2]]));
    }
}
