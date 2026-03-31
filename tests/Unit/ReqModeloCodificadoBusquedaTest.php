<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqModelosCodificados;
use Tests\TestCase;

class ReqModeloCodificadoBusquedaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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

    public function test_busqueda_prefijo_encuentra_registro(): void
    {
        ReqModelosCodificados::create([
            'TamanoClave' => 'ABC123',
            'SalonTejidoId' => 'JAC1',
        ]);

        $result = TejidoHelpers::obtenerModeloPorTamanoClave('ABC', 'JAC1');

        $this->assertNotNull($result);
        $this->assertEquals('ABC123', $result->TamanoClave);
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
