<?php

namespace Tests\Unit;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Services\Planeacion\SaldoMarbeteCodificacionService;
use Tests\TestCase;

class SaldoMarbeteCodificacionServiceTest extends TestCase
{
    private SaldoMarbeteCodificacionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SaldoMarbeteCodificacionService;
    }

    public function test_felpa_duplica_marbetes_cuando_el_nombre_contiene_felpa(): void
    {
        $c = new CatCodificados;
        $c->Id = 1;
        $c->Nombre = 'TOALLA FELPA TEST';
        $c->Pedido = 10000;
        $c->NoTiras = 4;
        $c->P_crudo = 50;

        $result = $this->service->calcularParaCatCodificados($c);

        $this->assertTrue($result['ok']);
        $this->assertSame(12, $result['valor']);
    }

    public function test_rechaza_si_falta_pedido(): void
    {
        $c = new CatCodificados;
        $c->Id = 2;
        $c->Nombre = 'X';

        $result = $this->service->calcularParaCatCodificados($c);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['valor']);
        $this->assertStringContainsStringIgnoringCase('pedido', (string) $result['message']);
    }
}
