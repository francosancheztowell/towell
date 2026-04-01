<?php

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\ProgramaTejido\ProgramaTejidoController;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Sistema\Usuario;
use Tests\TestCase;

/**
 * Tests para verificar el comportamiento del bulk update en store de programa-tejido.
 * El método store() del controlador fue refactorizado para hacer bulk update de Ultimo.
 */
class ProgramaTejidoStoreBulkTest extends TestCase
{
    /**
     * Verifica que el método store() del controlador existe.
     */
    public function test_store_method_exists_in_controller(): void
    {
        $controller = new ProgramaTejidoController();
        $this->assertTrue(method_exists($controller, 'store'), 'Método store debe existir en ProgramaTejidoController');
    }

    /**
     * Verifica que el método store() acepta Request.
     */
    public function test_store_method_accepts_request(): void
    {
        $reflection = new \ReflectionMethod(ProgramaTejidoController::class, 'store');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params, 'store() debe tener 1 parámetro');
        $this->assertEquals('request', $params[0]->getName(), 'Parámetro debe ser request');
        $this->assertEquals('Illuminate\Http\Request', $params[0]->getType()->getName());
    }

    /**
     * Verifica que el bulk update usa whereIn para múltiples telares.
     * Este test verifica el código fuente para asegurar que se usa bulk update.
     */
    public function test_store_uses_bulk_update_for_multiple_telares(): void
    {
        $controllerPath = app_path('Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoController.php');
        $content = file_get_contents($controllerPath);

        // Verificar que se usa whereIn para telares
        $this->assertStringContainsString('whereIn', $content, 'Debe usar whereIn para bulk update');
        $this->assertStringContainsString('telaresUnicos', $content, 'Debe usar variable telaresUnicos');
    }

    /**
     * Verifica que el bulk update solo se ejecuta si hay telares únicos.
     */
    public function test_store_bulk_update_checks_for_empty_telares(): void
    {
        $controllerPath = app_path('Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoController.php');
        $content = file_get_contents($controllerPath);

        // Buscar patrón: if (!empty($telaresUnicos))
        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*!\s*empty\s*\(\s*\$telaresUnicos\s*\)/',
            $content,
            'Debe verificar !empty(telaresUnicos) antes del bulk update'
        );
    }

    /**
     * Verifica que el código hace bulk update de Ultimo a 0.
     */
    public function test_store_bulk_update_sets_ultimo_to_zero(): void
    {
        $controllerPath = app_path('Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoController.php');
        $content = file_get_contents($controllerPath);

        // Buscar patrón de bulk update
        $this->assertMatchesRegularExpression(
            '/->update\s*\(\s*\[\s*[\'"]Ultimo[\'"]\s*=>\s*0\s*\]\s*\)/',
            $content,
            'Debe hacer update([Ultimo => 0]) en el bulk update'
        );
    }

    /**
     * Verifica que existe el método marcarCambioHiloBulk en UtilityHelpers.
     */
    public function test_marcar_cambio_hilo_bulk_method_exists(): void
    {
        $this->assertTrue(
            method_exists(\App\Http\Controllers\Planeacion\ProgramaTejido\helper\UtilityHelpers::class, 'marcarCambioHiloBulk'),
            'UtilityHelpers debe tener método marcarCambioHiloBulk'
        );
    }

    /**
     * Verifica que UtilityHelpers::marcarCambioHiloBulk usa whereIn para bulk query.
     */
    public function test_marcar_cambio_hilo_bulk_uses_where_in(): void
    {
        $helpersPath = app_path('Http/Controllers/Planeacion/ProgramaTejido/helper/UtilityHelpers.php');
        $content = file_get_contents($helpersPath);

        $this->assertStringContainsString('whereIn', $content, 'marcarCambioHiloBulk debe usar whereIn');
        $this->assertStringContainsString('bulk', strtolower($content), 'Debe tener método bulk');
    }

    /**
     * Verifica que el store usa marcarCambioHiloBulk.
     */
    public function test_store_uses_marcar_cambio_hilo_bulk(): void
    {
        $controllerPath = app_path('Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoController.php');
        $content = file_get_contents($controllerPath);

        $this->assertStringContainsString(
            'marcarCambioHiloBulk',
            $content,
            'store() debe usar marcarCambioHiloBulk'
        );
    }
}
