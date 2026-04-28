<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use App\Models\Planeacion\ReqProgramaTejido;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class LiberarOrdenesFelTamanhoTest extends TestCase
{
    private LiberarOrdenesController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new LiberarOrdenesController;
    }

    private function method(string $name): ReflectionMethod
    {
        $ref = new ReflectionClass(LiberarOrdenesController::class);
        $m = $ref->getMethod($name);
        $m->setAccessible(true);

        return $m;
    }

    public function test_es_invent_size_fel_es_cierto_cuando_la_cadena_contiene_fel(): void
    {
        $m = $this->method('esInventSizeFel');

        $this->assertTrue($m->invoke($this->controller, 'XFEL-40'));
        $this->assertTrue($m->invoke($this->controller, 'fel'));
        $this->assertFalse($m->invoke($this->controller, ''));
        $this->assertFalse($m->invoke($this->controller, null));
        $this->assertFalse($m->invoke($this->controller, 'STD'));
    }

    public function test_aplicar_ajuste_fel_tamanho_duplica_saldo_y_divide_mts_y_pzas(): void
    {
        $m = $this->method('aplicarAjusteFelTamanho');

        $inventSizeId = 'MODELO-FEL';
        $saldo = 10;
        $mts = 100.0;
        $pzas = 400.0;

        $m->invokeArgs($this->controller, [$inventSizeId, &$saldo, &$mts, &$pzas]);

        $this->assertSame(20, $saldo);
        $this->assertSame(50.0, $mts);
        $this->assertSame(200.0, $pzas);
    }

    public function test_aplicar_ajuste_fel_tamanho_sin_fel_no_modifica(): void
    {
        $m = $this->method('aplicarAjusteFelTamanho');

        $inventSizeId = 'NORMAL';
        $saldo = 10;
        $mts = 100.0;
        $pzas = 400.0;

        $m->invokeArgs($this->controller, [$inventSizeId, &$saldo, &$mts, &$pzas]);

        $this->assertSame(10, $saldo);
        $this->assertSame(100.0, $mts);
        $this->assertSame(400.0, $pzas);
    }

    public function test_request_tiene_mts_pzas_rollo_desde_cliente(): void
    {
        $m = $this->method('requestTieneMtsPzasRolloDesdeCliente');

        $this->assertFalse($m->invoke($this->controller, []));
        $this->assertFalse($m->invoke($this->controller, ['mtsRollo' => null, 'pzasRollo' => '']));
        $this->assertTrue($m->invoke($this->controller, ['mtsRollo' => '12.5']));
        $this->assertTrue($m->invoke($this->controller, ['pzasRollo' => '100']));
        $this->assertTrue($m->invoke($this->controller, ['mtsRollo' => 0]));
    }

    public function test_aplicar_ajuste_fel_saldo_y_mts_pzas_en_dos_llamadas(): void
    {
        $mSaldo = $this->method('aplicarAjusteFelSaldoMarbete');
        $mMts = $this->method('aplicarAjusteFelMtsYpzas');

        $saldo = 5;
        $mts = 80.0;
        $pzas = 320.0;

        $mSaldo->invokeArgs($this->controller, ['FEL', &$saldo]);
        $mMts->invokeArgs($this->controller, ['FEL', &$mts, &$pzas]);

        $this->assertSame(10, $saldo);
        $this->assertSame(40.0, $mts);
        $this->assertSame(160.0, $pzas);
    }

    public function test_validar_metricas_rechaza_saldo_marbete_cero_con_pedido(): void
    {
        $m = $this->method('validarMetricasProduccionParaLiberacion');
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

        $msg = $m->invoke($this->controller, $r);
        $this->assertNotNull($msg);
        $this->assertStringContainsStringIgnoringCase('marbetes', (string) $msg);
    }

    public function test_validar_metricas_pasa_con_pedido_y_metricas_completas(): void
    {
        $m = $this->method('validarMetricasProduccionParaLiberacion');
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

        $this->assertNull($m->invoke($this->controller, $r));
    }

    public function test_validar_metricas_rechaza_saldo_pedido_cero_o_nulo(): void
    {
        $m = $this->method('validarMetricasProduccionParaLiberacion');
        $r = new ReqProgramaTejido;
        $r->Id = 2;
        $r->NoTiras = 8;
        $r->SaldoPedido = 0;
        $r->SaldoMarbete = 10;

        $msg = $m->invoke($this->controller, $r);
        $this->assertNotNull($msg);
        $this->assertStringContainsStringIgnoringCase('saldo pedido', (string) $msg);
    }

    public function test_validar_metricas_rechaza_tiras_cero_o_nulas(): void
    {
        $m = $this->method('validarMetricasProduccionParaLiberacion');
        $r = new ReqProgramaTejido;
        $r->Id = 3;
        $r->NoTiras = 0;
        $r->SaldoPedido = 800;

        $msg = $m->invoke($this->controller, $r);
        $this->assertNotNull($msg);
        $this->assertStringContainsStringIgnoringCase('tiras', (string) $msg);
    }

    public function test_obtener_peso_rollo_felpa_es_90_desde_tamano_clave(): void
    {
        $m = $this->method('obtenerPesoRollo');
        $r = new ReqProgramaTejido;
        $r->TamanoClave = 'FELPA6598';

        $this->assertSame(90.0, $m->invoke($this->controller, $r));
    }

    public function test_aplicar_ajuste_fel_tamanho_para_felpa_sin_string_fel_en_inventsize(): void
    {
        $m = $this->method('aplicarAjusteFelTamanho');

        $r = new ReqProgramaTejido;
        $r->TamanoClave = 'FELPA123';
        $r->NombreProducto = 'X';
        $r->InventSizeId = 'STD';

        $inventSizeId = 'STD';
        $saldo = 11;
        $mts = 100.0;
        $pzas = 400.0;

        $m->invokeArgs($this->controller, [$inventSizeId, &$saldo, &$mts, &$pzas, $r]);

        $this->assertSame(22, $saldo);
        $this->assertSame(50.0, $mts);
        $this->assertSame(200.0, $pzas);
    }
}
