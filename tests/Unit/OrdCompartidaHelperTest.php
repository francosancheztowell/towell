<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\OrdCompartidaHelper;
use Tests\TestCase;

class OrdCompartidaHelperTest extends TestCase
{
    public function test_obtener_ord_compartida_desde_registro_array_retorna_int(): void
    {
        $resultado = OrdCompartidaHelper::obtenerOrdCompartidaDesdeRegistro(['NoProduccion' => '12345']);

        $this->assertSame(12345, $resultado);
    }

    public function test_obtener_ord_compartida_desde_registro_sin_no_produccion_retorna_null(): void
    {
        $this->assertNull(OrdCompartidaHelper::obtenerOrdCompartidaDesdeRegistro(['NoProduccion' => null]));
        $this->assertNull(OrdCompartidaHelper::obtenerOrdCompartidaDesdeRegistro(['NoProduccion' => '']));
        $this->assertNull(OrdCompartidaHelper::obtenerOrdCompartidaDesdeRegistro(['NoProduccion' => '   ']));
        $this->assertNull(OrdCompartidaHelper::obtenerOrdCompartidaDesdeRegistro(['NoProduccion' => 'abc']));
        $this->assertNull(OrdCompartidaHelper::obtenerOrdCompartidaDesdeRegistro(null));
    }
}
