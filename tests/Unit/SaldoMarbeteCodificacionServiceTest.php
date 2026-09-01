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

    /** NoMarbete = TotalRollos − ProduccionMarbetes (pendientes). */
    public function test_resta_marbetes_producidos_a_total_rollos(): void
    {
        $c = new CatCodificados;
        $c->Id = 1;
        $c->TotalRollos = 35;
        $c->ProduccionMarbetes = 26;

        $result = $this->service->calcularParaCatCodificados($c);

        $this->assertTrue($result['ok']);
        $this->assertSame(9, $result['valor']);
    }

    public function test_sin_produccion_marbetes_es_total_rollos(): void
    {
        $c = new CatCodificados;
        $c->Id = 1;
        $c->TotalRollos = 58;

        $this->assertSame(58, $this->service->calcularParaCatCodificados($c)['valor']);
    }

    public function test_no_baja_de_cero(): void
    {
        $c = new CatCodificados;
        $c->Id = 1;
        $c->TotalRollos = 10;
        $c->ProduccionMarbetes = 12;

        $this->assertSame(0, $this->service->calcularParaCatCodificados($c)['valor']);
    }

    public function test_rechaza_si_falta_total_rollos(): void
    {
        $c = new CatCodificados;
        $c->Id = 2;
        $c->Nombre = 'X';

        $result = $this->service->calcularParaCatCodificados($c);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['valor']);
        $this->assertStringContainsStringIgnoringCase('TotalRollos', (string) $result['message']);
    }
}
