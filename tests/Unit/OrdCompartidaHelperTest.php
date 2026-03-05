<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\OrdCompartidaHelper;
use App\Models\Planeacion\ReqProgramaTejido;
use Tests\TestCase;

class OrdCompartidaHelperTest extends TestCase
{
    public function test_obtener_nuevo_ord_compartida_disponible_retorna_entero_positivo(): void
    {
        $resultado = OrdCompartidaHelper::obtenerNuevoOrdCompartidaDisponible();

        $this->assertIsInt($resultado);
        $this->assertGreaterThanOrEqual(1, $resultado);
    }

    public function test_obtener_nuevo_ord_compartida_disponible_retorna_valor_no_en_uso(): void
    {
        $resultado = OrdCompartidaHelper::obtenerNuevoOrdCompartidaDisponible();

        $existe = ReqProgramaTejido::where('OrdCompartida', $resultado)->exists();
        $this->assertFalse($existe, 'El OrdCompartida retornado no debe existir en BD');
    }
}
