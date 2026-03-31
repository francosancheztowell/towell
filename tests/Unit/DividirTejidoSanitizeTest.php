<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use Tests\TestCase;

class DividirTejidoSanitizeTest extends TestCase
{
    public function test_sanitize_inline_coincide_con_tejido_helpers(): void
    {
        $valor = '1,000.50';

        $resultadoActual = (float) str_replace(',', '', $valor);

        $resultadoEsperado = TejidoHelpers::sanitizeNumber($valor);

        $this->assertEquals($resultadoEsperado, $resultadoActual);
    }

    public function test_sanitize_con_valor_nulo_retorna_cero(): void
    {
        $this->assertEquals(0.0, TejidoHelpers::sanitizeNumber(null));
    }

    public function test_sanitize_con_valor_vacio_retorna_cero(): void
    {
        $this->assertEquals(0.0, TejidoHelpers::sanitizeNumber(''));
    }

    public function test_sanitize_con_valor_sin_comas_retorna_numero(): void
    {
        $this->assertEquals(1500.5, TejidoHelpers::sanitizeNumber('1500.5'));
    }

    public function test_sanitize_inline_no_usa_tejido_helpers_en_linea_130(): void
    {
        $valor = '1,000.50';

        $resultadoInline = (float) str_replace(',', '', $valor);

        $this->assertEquals(1000.5, $resultadoInline);
        $this->assertEquals(1000.5, TejidoHelpers::sanitizeNumber($valor));
    }
}
