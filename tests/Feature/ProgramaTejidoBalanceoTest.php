<?php

namespace Tests\Feature;

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
}
