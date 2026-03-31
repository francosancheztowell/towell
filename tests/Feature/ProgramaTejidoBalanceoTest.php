<?php

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\ProgramaTejido\ProgramaTejidoBalanceoController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProgramaTejidoBalanceoTest extends TestCase
{
    public function test_preview_fechas_balanceo_sin_auth_no_autoriza(): void
    {
        $response = $this->postJson('/planeacion/programa-tejido/preview-fechas-balanceo', []);

        $this->assertTrue(in_array($response->status(), [302, 401], true), 'Debe redirigir o retornar 401 sin auth');
    }

    public function test_preview_fechas_balanceo_ruta_existe(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('programa-tejido.preview-fechas-balanceo');
        $this->assertNotNull($route);
        $this->assertStringContainsString('preview-fechas-balanceo', $route->uri());
    }

    public function test_ver_detalles_grupo_balanceo_apunta_a_programa_tejido_balanceo_controller(): void
    {
        $route = Route::getRoutes()->getByName('verdetallesgrupobalanceo');
        $this->assertNotNull($route);
        $this->assertSame(ProgramaTejidoBalanceoController::class.'@verDetallesGrupoBalanceo', $route->getActionName());
    }
}
