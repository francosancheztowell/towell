<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\Liberar\LiberarMarbetesCalculator;
use Tests\TestCase;

/**
 * Fixtures fijos de la cadena Excel extraída de LiberarOrdenesController.
 * SaldoMarbete = TECHO (ceil), no REDONDEAR — no cambiar a ROUND.
 */
class LiberarMarbetesCalculatorTest extends TestCase
{
    private LiberarMarbetesCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new LiberarMarbetesCalculator;
    }

    public function test_repeticiones_trunca_hacia_cero_como_excel(): void
    {
        $this->assertSame(57, $this->calc->repeticionesDesdePesoRollo(41.5, 121, 6));
        $this->assertSame(30, $this->calc->repeticionesDesdePesoRollo(41.0, 455, 3));
        $this->assertNull($this->calc->repeticionesDesdePesoRollo(41.5, 0, 6));
    }

    public function test_saldo_marbete_usa_ceil_no_round(): void
    {
        // 143.23 → 144 con ceil; con ROUND sería 143.
        $this->assertSame(144, $this->calc->saldoMarbeteDesdeFormula(12891, 3, 30));
        // 12.003 → 13; con ROUND sería 12.
        $this->assertSame(13, $this->calc->saldoMarbeteDesdeFormula(4105, 6, 57));
        $this->assertSame(12, $this->calc->saldoMarbeteDesdeFormula(4104, 6, 57));
        $this->assertSame(0, $this->calc->saldoMarbeteDesdeFormula(1000, 0, 5));
    }

    public function test_base_pedido_prioriza_total_sobre_saldo(): void
    {
        $r = new ReqProgramaTejido;
        $r->TotalPedido = 5000;
        $r->SaldoPedido = 100;
        $r->Produccion = 50;

        $this->assertSame(5000.0, $this->calc->basePedido($r));

        $vieja = new ReqProgramaTejido;
        $vieja->SaldoPedido = 800;
        $this->assertSame(800.0, $this->calc->basePedido($vieja));
    }

    public function test_calcular_orden_36643_ignora_pzas_viejas(): void
    {
        $r = new ReqProgramaTejido;
        $r->PesoCrudo = 121;
        $r->NoTiras = 6;
        $r->LargoCrudo = 50;
        $r->TotalPedido = 4104;
        $r->InventSizeId = 'STD';
        $r->PzasRollo = 636;
        $r->TotalRollos = 7;
        $r->TotalPzas = 4452;

        $out = $this->calc->calcular($r, 41.5);

        $this->assertSame(57, $out['repeticiones']);
        $this->assertSame(342.0, $out['pzasRollo']);
        $this->assertSame(12.0, $out['totalRollos']);
        $this->assertSame(4104.0, $out['totalPzas']);
        $this->assertSame(12, $out['saldoMarbete']);
        $this->assertFalse($out['esFel']);
    }

    public function test_calcular_aplica_ajuste_fel_mts_pzas_y_marca_es_fel(): void
    {
        $r = new ReqProgramaTejido;
        $r->PesoCrudo = 455;
        $r->NoTiras = 3;
        $r->LargoCrudo = 142;
        $r->TotalPedido = 12891;
        $r->InventSizeId = 'FEL';

        $out = $this->calc->calcular($r, 41.0);

        $this->assertTrue($out['esFel']);
        $this->assertSame(30, $out['repeticiones']);
        $this->assertEqualsWithDelta(21.3, $out['mtsRollo'], 0.001);
        $this->assertEqualsWithDelta(45.0, $out['pzasRollo'], 0.001);
        $this->assertEqualsWithDelta(287.0, $out['totalRollos'], 0.001);
        $this->assertSame(287, $out['saldoMarbete']);
    }

    public function test_ajuste_fel_por_tamano_clave_felpa_sin_fel_en_inventsize(): void
    {
        $r = new ReqProgramaTejido;
        $r->TamanoClave = 'FELPA6598';
        $r->InventSizeId = 'STD';

        $this->assertSame(90.0, $this->calc->obtenerPesoRollo($r));
        $this->assertTrue($this->calc->debeAplicarAjusteFormatoFelRollo('STD', $r));

        $saldo = 11;
        $mts = 100.0;
        $pzas = 400.0;
        $this->calc->aplicarAjusteFelTamanho('STD', $saldo, $mts, $pzas, $r);

        $this->assertSame(22, $saldo);
        $this->assertSame(50.0, $mts);
        $this->assertSame(200.0, $pzas);
    }

    public function test_karl_mayer_omite_ajuste_fel_y_usa_peso_fijo(): void
    {
        $r = new ReqProgramaTejido;
        $r->SalonTejidoId = 'KARL MAYER';
        $r->NoTelarId = '401';
        $r->TamanoClave = 'FELPA123';
        $r->InventSizeId = 'FEL';

        $this->assertSame(LiberarMarbetesCalculator::PESO_ROLLO_KG_KARL_MAYER, $this->calc->obtenerPesoRollo($r));
        $this->assertFalse($this->calc->debeAplicarAjusteFormatoFelRollo('FEL', $r));

        $saldo = 10;
        $mts = 80.0;
        $pzas = 200.0;
        $this->calc->aplicarAjusteFelTamanho('FEL', $saldo, $mts, $pzas, $r);
        $this->assertSame(10, $saldo);
        $this->assertSame(80.0, $mts);
        $this->assertSame(200.0, $pzas);
    }

    public function test_override_repeticiones_manda_sobre_formula(): void
    {
        $r = new ReqProgramaTejido;
        $r->PesoCrudo = 455;
        $r->NoTiras = 3;
        $r->LargoCrudo = 142;
        $r->TotalPedido = 12891;
        $r->InventSizeId = 'STD';

        $out = $this->calc->calcular($r, 41.0, 20.0);

        $this->assertSame(20, $out['repeticiones']);
        $this->assertSame(60.0, $out['pzasRollo']);
        $this->assertEqualsWithDelta(28.4, $out['mtsRollo'], 0.001);
    }
}
