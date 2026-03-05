<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqProgramaTejido;
use Tests\TestCase;

class TejidoHelpersTest extends TestCase
{

    public function test_calcular_horas_prod_con_valores_validos_retorna_mayor_a_cero(): void
    {
        $horas = TejidoHelpers::calcularHorasProd(
            vel: 100.0,
            efic: 0.85,
            cantidad: 100.0,
            noTiras: 2.0,
            total: 50.0,
            luchaje: 1.0,
            repeticiones: 1.0
        );

        $this->assertIsFloat($horas);
        $this->assertGreaterThan(0, $horas);
    }

    public function test_calcular_horas_prod_con_eficiencia_cero_retorna_cero(): void
    {
        $horas = TejidoHelpers::calcularHorasProd(
            vel: 100.0,
            efic: 0.0,
            cantidad: 100.0,
            noTiras: 2.0,
            total: 50.0,
            luchaje: 1.0,
            repeticiones: 1.0
        );

        $this->assertSame(0.0, $horas);
    }

    public function test_sanitize_number_acepta_strings_numericos(): void
    {
        $this->assertSame(123.45, TejidoHelpers::sanitizeNumber('123.45'));
        $this->assertSame(1000.0, TejidoHelpers::sanitizeNumber('1,000'));
        $this->assertSame(0.0, TejidoHelpers::sanitizeNumber(null));
    }

    public function test_obtener_modelo_params_sin_tamano_clave_retorna_estructura_base(): void
    {
        $programa = new ReqProgramaTejido([
            'TamanoClave' => null,
            'NoTiras' => 4,
            'Luchaje' => 2,
            'Repeticiones' => 1,
        ]);

        $params = TejidoHelpers::obtenerModeloParams($programa);

        $this->assertArrayHasKey('total', $params);
        $this->assertArrayHasKey('no_tiras', $params);
        $this->assertArrayHasKey('luchaje', $params);
        $this->assertArrayHasKey('repeticiones', $params);
        $this->assertSame(0.0, $params['total']);
        $this->assertSame(4.0, $params['no_tiras']);
    }
}
