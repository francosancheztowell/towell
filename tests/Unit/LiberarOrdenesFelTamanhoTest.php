<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
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
}
