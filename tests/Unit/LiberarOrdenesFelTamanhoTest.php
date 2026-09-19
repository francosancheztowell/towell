<?php

namespace Tests\Unit;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\Liberar\LiberarMarbetesCalculator;
use App\Services\Planeacion\Liberar\LiberarValidacionesService;
use Tests\TestCase;

class LiberarOrdenesFelTamanhoTest extends TestCase
{
    private LiberarMarbetesCalculator $calculator;

    private LiberarValidacionesService $validaciones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new LiberarMarbetesCalculator;
        $this->validaciones = new LiberarValidacionesService;
    }

    public function test_es_invent_size_fel_es_cierto_cuando_la_cadena_contiene_fel(): void
    {
        $this->assertTrue($this->calculator->esInventSizeFel('XFEL-40'));
        $this->assertTrue($this->calculator->esInventSizeFel('fel'));
        $this->assertFalse($this->calculator->esInventSizeFel(''));
        $this->assertFalse($this->calculator->esInventSizeFel(null));
        $this->assertFalse($this->calculator->esInventSizeFel('STD'));
    }

    public function test_aplicar_ajuste_fel_tamanho_duplica_saldo_y_divide_mts_y_pzas(): void
    {
        $inventSizeId = 'MODELO-FEL';
        $saldo = 10;
        $mts = 100.0;
        $pzas = 400.0;

        $this->calculator->aplicarAjusteFelTamanho($inventSizeId, $saldo, $mts, $pzas);

        $this->assertSame(20, $saldo);
        $this->assertSame(50.0, $mts);
        $this->assertSame(200.0, $pzas);
    }

    public function test_aplicar_ajuste_fel_tamanho_sin_fel_no_modifica(): void
    {
        $inventSizeId = 'NORMAL';
        $saldo = 10;
        $mts = 100.0;
        $pzas = 400.0;

        $this->calculator->aplicarAjusteFelTamanho($inventSizeId, $saldo, $mts, $pzas);

        $this->assertSame(10, $saldo);
        $this->assertSame(100.0, $mts);
        $this->assertSame(400.0, $pzas);
    }

    /**
     * Caso real de la orden 36734 (FELPA6808 / FEL): la grilla manda MtsRollo ya
     * dividido, pero PzasRollo lo recalcula el servidor. Los dos ajustes tienen que
     * ser independientes — si comparten guard, PzasRollo se guarda sin dividir
     * (146 en vez de 73) y TotalPzas sale al doble.
     */
    public function test_ajuste_fel_de_pzas_es_independiente_del_de_mts(): void
    {
        // Repeticiones 73 × NoTiras 2, recalculado siempre en servidor.
        $pzas = 146.0;
        $this->calculator->aplicarAjusteFelPzasRollo('FEL', $pzas);
        $this->assertSame(73.0, $pzas);

        // MtsRollo que llegó del request ya dividido: no debe volver a dividirse,
        // por eso liberar() ni siquiera llama al ajuste en ese caso.
        $mts = 37.23;
        $this->calculator->aplicarAjusteFelMtsRollo('NORMAL', $mts);
        $this->assertSame(37.23, $mts);

        // TotalPzas = TotalRollos × PzasRollo ya ajustado.
        $this->assertSame(12045.0, 165 * $pzas);
    }

    public function test_aplicar_ajuste_fel_saldo_y_mts_pzas_en_dos_llamadas(): void
    {
        $saldo = 5;
        $mts = 80.0;
        $pzas = 320.0;

        $this->calculator->aplicarAjusteFelSaldoMarbete('FEL', $saldo);
        $this->calculator->aplicarAjusteFelMtsYpzas('FEL', $mts, $pzas);

        $this->assertSame(10, $saldo);
        $this->assertSame(40.0, $mts);
        $this->assertSame(160.0, $pzas);
    }

    public function test_validar_metricas_rechaza_saldo_marbete_cero_con_pedido(): void
    {
        $r = new ReqProgramaTejido;
        $r->Id = 99;
        $r->NombreProducto = 'Test';
        $r->ItemId = 'ITEM';
        $r->NoTiras = 6;
        $r->SaldoPedido = 1000;
        $r->Repeticiones = 5;
        $r->SaldoMarbete = 0;
        $r->MtsRollo = 10.0;
        $r->PzasRollo = 20.0;
        $r->TotalRollos = 2;
        $r->TotalPzas = 40.0;

        $msg = $this->validaciones->validarMetricasProduccionParaLiberacion($r);
        $this->assertNotNull($msg);
        $this->assertStringContainsStringIgnoringCase('marbetes', (string) $msg);
    }

    public function test_validar_metricas_pasa_con_pedido_y_metricas_completas(): void
    {
        $r = new ReqProgramaTejido;
        $r->Id = 1;
        $r->NoTiras = 4;
        $r->SaldoPedido = 500;
        $r->Repeticiones = 10;
        $r->SaldoMarbete = 5;
        $r->MtsRollo = 12.5;
        $r->PzasRollo = 100;
        $r->TotalRollos = 5;
        $r->TotalPzas = 500;
        $r->PesoCrudo = 100;
        $r->Ancho = 30;
        $r->LargoCrudo = 50;
        $r->Densidad = 1.5;

        $this->assertNull($this->validaciones->validarMetricasProduccionParaLiberacion($r));
    }

    public function test_validar_metricas_rechaza_saldo_pedido_cero_o_nulo(): void
    {
        $r = new ReqProgramaTejido;
        $r->Id = 2;
        $r->NoTiras = 8;
        $r->SaldoPedido = 0;
        $r->SaldoMarbete = 10;

        $msg = $this->validaciones->validarMetricasProduccionParaLiberacion($r);
        $this->assertNotNull($msg);
        $this->assertStringContainsStringIgnoringCase('saldo pedido', (string) $msg);
    }

    public function test_validar_metricas_rechaza_tiras_cero_o_nulas(): void
    {
        $r = new ReqProgramaTejido;
        $r->Id = 3;
        $r->NoTiras = 0;
        $r->SaldoPedido = 800;

        $msg = $this->validaciones->validarMetricasProduccionParaLiberacion($r);
        $this->assertNotNull($msg);
        $this->assertStringContainsStringIgnoringCase('tiras', (string) $msg);
    }

    public function test_obtener_peso_rollo_felpa_es_90_desde_tamano_clave(): void
    {
        $r = new ReqProgramaTejido;
        $r->TamanoClave = 'FELPA6598';

        $this->assertSame(90.0, $this->calculator->obtenerPesoRollo($r));
    }

    public function test_aplicar_ajuste_fel_tamanho_para_felpa_sin_string_fel_en_inventsize(): void
    {
        $r = new ReqProgramaTejido;
        $r->TamanoClave = 'FELPA123';
        $r->NombreProducto = 'X';
        $r->InventSizeId = 'STD';

        $inventSizeId = 'STD';
        $saldo = 11;
        $mts = 100.0;
        $pzas = 400.0;

        $this->calculator->aplicarAjusteFelTamanho($inventSizeId, $saldo, $mts, $pzas, $r);

        $this->assertSame(22, $saldo);
        $this->assertSame(50.0, $mts);
        $this->assertSame(200.0, $pzas);
    }
}
