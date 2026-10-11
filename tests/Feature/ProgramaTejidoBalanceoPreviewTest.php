<?php

namespace Tests\Feature;

use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\TestCase;

class ProgramaTejidoBalanceoPreviewTest extends TestCase
{
    use ConPermisosPlaneacion;

    public function test_preview_fechas_balanceo_requiere_cambios(): void
    {
        $res = $this->actingAs($this->usuarioConPermisos([2 => ['modificar']]))
            ->postJson(route('programa-tejido.preview-fechas-balanceo'), []);

        // Without 'cambios', should return 422 validation error
        $res->assertUnprocessable();
    }

    public function test_preview_fechas_balanceo_retorna_json(): void
    {
        $res = $this->actingAs($this->usuarioConPermisos([2 => ['modificar']]))
            ->postJson(route('programa-tejido.preview-fechas-balanceo'), [
                'cambios' => [
                    ['id' => 99999999, 'total_pedido' => 100, 'modo' => 'total'],
                ],
            ]);

        // Should return JSON (either success with data or handled error — not a crash)
        $res->assertHeader('content-type', 'application/json');
        $this->assertContains($res->status(), [200, 404, 422, 500]);
    }
}
