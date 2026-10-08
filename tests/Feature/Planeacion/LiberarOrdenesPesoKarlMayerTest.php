<?php

namespace Tests\Feature\Planeacion;

use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use Illuminate\Http\Request;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Caracterización: la pantalla de Liberar Órdenes manda al TS el peso fijo de rollo
 * Karl Mayer (27.5 kg) en data-pagina. La fuente es LiberarMarbetesCalculator.
 */
class LiberarOrdenesPesoKarlMayerTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSuperficies();
        $this->sembrarFixtures();
    }

    public function test_la_vista_expone_el_peso_karl_mayer(): void
    {
        $this->actingAs($this->usuarioConPermisos([2 => ['acceso']]));

        $html = app(LiberarOrdenesController::class)
            ->index(Request::create('/planeacion/programa-tejido/liberar-ordenes'))
            ->render();

        $this->assertStringContainsString('"pesoKarlMayer":"27.5"', $html);
    }
}
