<?php

namespace Tests\Unit\Planeacion;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use ReflectionMethod;
use Tests\TestCase;

/**
 * PT-01 · 01.5 — Congela los resultados ACTUALES de las fórmulas de Programa Tejido.
 *
 * No afirma que sean correctos: afirma que no cambian sin una decisión de negocio
 * (01-CONTEXT, invariante "fórmulas y redondeos"). Si un refactor mueve un decimal,
 * este test lo delata. Valores obtenidos corriendo el código tal cual el 2026-09-24.
 */
class ProgramaTejidoFormulaCharacterizationTest extends TestCase
{
    private const DELTA = 1e-9;

    private function programa(array $atributos): ReqProgramaTejido
    {
        $p = new ReqProgramaTejido;
        $p->setRawAttributes($atributos);

        return $p;
    }

    public function test_horas_prod_normaliza_eficiencia_porcentual_y_devuelve_cero_sin_modelo(): void
    {
        // vel, efic, cantidad, noTiras, total, luchaje, repeticiones
        $this->assertEqualsWithDelta(2.9319129226493743, TejidoHelpers::calcularHorasProdFromParams(400, 85, 800, 2, 120, 30, 20), self::DELTA);
        // 85 y 0.85 son lo mismo: > 1 se divide entre 100.
        $this->assertEqualsWithDelta(2.9319129226493743, TejidoHelpers::calcularHorasProdFromParams(400, 0.85, 800, 2, 120, 30, 20), self::DELTA);
        // Cualquier parámetro del modelo en 0 → 0 horas (no excepción).
        $this->assertSame(0.0, TejidoHelpers::calcularHorasProdFromParams(400, 85, 800, 2, 120, 30, 0));
    }

    public function test_formulas_de_eficiencia_con_modelo_completo(): void
    {
        $p = $this->programa([
            'Id' => 1, 'VelocidadSTD' => 400, 'EficienciaSTD' => 85, 'SaldoPedido' => 800, 'PesoCrudo' => 450,
            'FechaInicio' => '2026-09-01 06:30:00', 'FechaFinal' => '2026-09-03 18:00:00',
            'AplicacionId' => 'NA', 'AnchoToalla' => 50, 'LargoToalla' => 90,
        ]);

        $formulas = TejidoHelpers::calcularFormulasEficiencia(
            $p, ['no_tiras' => 2, 'total' => 120, 'luchaje' => 30, 'repeticiones' => 20], true, true
        );

        $this->assertSame([
            'StdToaHra' => 321.01,
            'PesoGRM2' => 1000.0,
            'DiasEficiencia' => 2.48,
            'StdDia' => 6548.6,
            'ProdKgDia' => 2946.87,
            'HorasProd' => 2.93,
            'DiasJornada' => 0.12,
            'StdHrsEfect' => 13.45,
            'ProdKgDia2' => 145.21,
            'EntregaCte' => '2026-09-15 18:00:00', // AplicacionId NA → 12 días
            'EntregaPT' => '2026-09-15',           // día 15 del mes de FechaFinal
            'EntregaProduc' => '2026-09-03',
            'PTvsCte' => 0.0,
        ], $formulas);
    }

    public function test_formulas_de_eficiencia_sin_modelo_omiten_std_y_con_aplicacion_usan_16_dias(): void
    {
        $p = $this->programa([
            'Id' => 1, 'VelocidadSTD' => 400, 'EficienciaSTD' => 85, 'SaldoPedido' => 800, 'PesoCrudo' => 450,
            'FechaInicio' => '2026-09-01 06:30:00', 'FechaFinal' => '2026-09-03 18:00:00',
            'AplicacionId' => 'APL1', 'AnchoToalla' => 50, 'LargoToalla' => 90,
        ]);

        $formulas = TejidoHelpers::calcularFormulasEficiencia(
            $p, ['no_tiras' => 0, 'total' => 0, 'luchaje' => 0, 'repeticiones' => 0], true, true
        );

        $this->assertSame([
            'PesoGRM2' => 1000.0,
            'DiasEficiencia' => 2.48,
            'StdHrsEfect' => 13.45,
            'ProdKgDia2' => 145.21,
            'EntregaCte' => '2026-09-19 18:00:00',
            'EntregaPT' => '2026-09-15',
            'EntregaProduc' => '2026-08-30',
            'PTvsCte' => -4.0, // signo actual: EntregaCte posterior a EntregaPT da negativo
        ], $formulas);
    }

    public function test_consumos_de_linea_diaria_del_observer(): void
    {
        $observer = new ReqProgramaTejidoObserver;
        $p = $this->programa([
            'PasadasTrama' => 20, 'CalibreTrama2' => 12, 'AnchoToalla' => 50,
            'PasadasComb1' => 10, 'CalibreComb12' => 16,
            'LargoCrudo' => 70, 'MedidaPlano' => 5, 'CalibrePie2' => 10, 'CuentaPie' => 60, 'NoTiras' => 2,
        ]);

        $llamar = fn (string $metodo, ...$args) => (new ReflectionMethod($observer, $metodo))->invoke($observer, ...$args);

        $this->assertEqualsWithDelta(0.04921583333333332, $llamar('calcularTrama', $p, 100.0), self::DELTA);
        $this->assertEqualsWithDelta(0.018455937499999995, $llamar('calcularCombinacion', $p, 1, 100.0), self::DELTA);
        $this->assertEqualsWithDelta(0.06535725, $llamar('calcularPie', $p, 100.0), self::DELTA);
        $this->assertEqualsWithDelta(717.6553672316385, $llamar('calcularMtsPie', $p, 2.5), self::DELTA);

        // Sin insumos → null, nunca 0 ni excepción.
        $vacio = $this->programa([]);
        $this->assertNull($llamar('calcularTrama', $vacio, 100.0));
        $this->assertNull($llamar('calcularCombinacion', $vacio, 3, 100.0));
        $this->assertNull($llamar('calcularPie', $vacio, 100.0));
        $this->assertNull($llamar('calcularMtsPie', $vacio, 2.5));
    }

    public function test_el_observer_solo_recalcula_por_sus_campos_disparadores(): void
    {
        $this->assertSame(
            ['TamanoClave', 'InventSizeId', 'PesoCrudo', 'NoTiras', 'LargoCrudo', 'SaldoPedido', 'TotalPedido', 'Produccion'],
            ReqProgramaTejidoObserver::CAMPOS_RECALC_FORMULA
        );
    }
}
