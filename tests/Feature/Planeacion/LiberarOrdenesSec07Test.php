<?php

namespace Tests\Feature\Planeacion;

use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * SEC-07 en Liberar Órdenes (PT-TS 1): un error inesperado no manda el detalle interno
 * (SQL, tablas) al usuario; sí un código de referencia. Los rechazos de negocio (422) no
 * cambian: los cubre LiberarOrdenesLiberarTest.
 */
class LiberarOrdenesSec07Test extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSuperficies();
        $this->sembrarFixtures();
    }

    public function test_la_pantalla_no_muestra_el_error_de_sql(): void
    {
        Schema::connection('sqlsrv')->drop('ReqProgramaTejido');

        $view = app(LiberarOrdenesController::class)->index(Request::create('/planeacion/programa-tejido/liberar-ordenes'));
        $error = $view->getData()['error'] ?? '';

        $this->assertStringStartsWith('No se pudieron cargar los datos (ref: ', $error);
        $this->assertStringNotContainsString('SQLSTATE', $error);
        $this->assertStringNotContainsString('ReqProgramaTejido', $error);
    }

    public function test_guardar_marbetes_con_error_responde_generico_con_trace_id(): void
    {
        Schema::connection('sqlsrv')->drop('ReqProgramaTejido');

        $response = $this->actingAs($this->usuarioConPermisos([2 => ['acceso', 'crear', 'modificar']]))
            ->postJson('/planeacion/programa-tejido/marbetes', ['id' => 1]);

        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Error al guardar marbetes.');
        $this->assertNotEmpty($response->json('trace_id'));
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }
}
