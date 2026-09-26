<?php

namespace Tests\Feature\Planeacion;

use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * 02-MUESTRAS-LIBERAR.md, lo que adelanta PT-05: R6 (guard 422 sin DDL de marbetes) y
 * R7 (volver a la grilla de la superficie). El prefijo "M" (R1–R5, R8, R9) va en PT-06.
 */
class LiberarMuestrasTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSuperficies();
        $this->sembrarFixtures();
    }

    private function payload(): array
    {
        return ['registros' => [['id' => 1, 'bomId' => 'LMAT1', 'bomName' => 'L.MAT 1']]];
    }

    public function test_liberar_muestra_sin_ddl_responde_422(): void
    {
        $antes = $this->fotoSuperficies();

        $this->actingAs($this->usuarioConPermisos([5 => ['crear']]))
            ->postJson('/planeacion/muestras/liberar-ordenes/procesar', $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('capacidad', 'marbetes')
            ->assertJsonPath('superficie', 'muestras');

        $this->assertEquals($antes, $this->fotoSuperficies());
    }

    public function test_el_guard_no_frena_a_programa(): void
    {
        // Sin registros: llega a la validación de siempre, no al guard de capacidad.
        $this->actingAs($this->usuarioConPermisos([2 => ['crear']]))
            ->postJson('/planeacion/programa-tejido/liberar-ordenes/procesar', ['registros' => []])
            ->assertStatus(422)
            ->assertJsonMissingPath('capacidad')
            ->assertJsonValidationErrors('registros');
    }

    public function test_liberar_muestra_redirige_a_muestras(): void
    {
        $this->assertSame(route('muestras.index'), LiberarOrdenesController::urlRegreso(ProgramaTejidoSurface::Muestras));
        $this->assertSame(route('catalogos.req-programa-tejido'), LiberarOrdenesController::urlRegreso(ProgramaTejidoSurface::Programa));
    }
}
