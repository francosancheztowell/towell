<?php

namespace Tests\Unit;

use App\Models\Planeacion\ReqModelosCodificados;
use App\Services\Planeacion\ProgramaTejido\Helpers\TejidoHelpers;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ReqModeloCodificadoBusquedaTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
    }

    public function test_busqueda_exacta_encuentra_registro(): void
    {
        ReqModelosCodificados::create([
            'TamanoClave' => 'TEST ABC',
            'SalonTejidoId' => 'JAC1',
            'ItemId' => 'ITEM1',
        ]);

        $result = TejidoHelpers::obtenerModeloPorTamanoClave('TEST ABC', 'JAC1');

        $this->assertNotNull($result);
        $this->assertEquals('TEST ABC', $result->TamanoClave);
    }

    public function test_busqueda_exacta_con_espacios_normaliza(): void
    {
        ReqModelosCodificados::create([
            'TamanoClave' => 'TEST  ABC',
            'SalonTejidoId' => 'JAC1',
        ]);

        $result = TejidoHelpers::obtenerModeloPorTamanoClave('TEST ABC', 'JAC1');

        $this->assertNotNull($result);
    }

    public function test_clave_incompleta_no_trae_otro_modelo(): void
    {
        // La TamanoClave ya trae la talla: "MANOS9" traía MANOS941 (otro artículo) y la grilla lo guardaba.
        ReqModelosCodificados::create(['TamanoClave' => 'MANOS941', 'SalonTejidoId' => 'JAC1', 'ItemId' => '941']);

        $this->assertNull(TejidoHelpers::obtenerModeloPorTamanoClave('MANOS9', 'JAC1'));
        $this->assertNull(TejidoHelpers::obtenerModeloPorTamanoClave('ANOS94', 'JAC1'));
        $this->assertSame('941', TejidoHelpers::obtenerModeloPorTamanoClave('manos941', 'JAC1')?->ItemId);
    }

    public function test_clave_repetida_en_el_salon_toma_la_mas_reciente(): void
    {
        ReqModelosCodificados::create(['TamanoClave' => 'TAPETE7474', 'SalonTejidoId' => 'JAC1', 'InventSizeId' => 'TAP', 'FechaTejido' => '2022-09-22']);
        ReqModelosCodificados::create(['TamanoClave' => 'TAPETE7474', 'SalonTejidoId' => 'JAC1', 'InventSizeId' => 'MAN', 'FechaTejido' => '2026-03-06']);

        $this->assertSame('MAN', TejidoHelpers::obtenerModeloPorTamanoClave('TAPETE7474', 'JAC1')?->InventSizeId);
    }

    public function test_busqueda_sin_resultados_retorna_null(): void
    {
        $result = TejidoHelpers::obtenerModeloPorTamanoClave('INEXISTENTE', 'JAC1');

        $this->assertNull($result);
    }

    public function test_busqueda_sin_salon_busca_en_todos(): void
    {
        ReqModelosCodificados::create([
            'TamanoClave' => 'TESTALL',
            'SalonTejidoId' => 'JAC1',
        ]);

        $result = TejidoHelpers::obtenerModeloPorTamanoClave('TESTALL', null);

        $this->assertNotNull($result);
        $this->assertEquals('TESTALL', $result->TamanoClave);
    }
}
