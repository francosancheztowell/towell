<?php

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\DuplicarTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Sistema\Usuario;
use Tests\TestCase;

/**
 * Tests de integración para BalancearTejido.
 * Verifica estructura, métodos públicos y lógica de negocio.
 */
class ProgramaTejidoBalanceoIntegrationTest extends TestCase
{
    private function actingUsuario(): Usuario
    {
        $user = new Usuario([
            'idusuario' => 1,
            'numero_empleado' => '00001',
            'nombre' => 'Test User',
            'contrasenia' => 'hashed',
            'area' => 'TEST',
        ]);
        $user->idusuario = 1;

        return $user;
    }

    /**
     * Verifica que los métodos públicos de BalancearTejido existen.
     */
    public function test_balancear_metodos_publicos_existen(): void
    {
        $metodosEsperados = [
            'previewFechas',
            'actualizarPedidos',
            'balancearAutomatico',
            'calcularPedidoParaFechaObjetivo',
            'clearCalendarioLinesCache',
        ];

        foreach ($metodosEsperados as $metodo) {
            $this->assertTrue(
                method_exists(BalancearTejido::class, $metodo),
                "BalancearTejido debe tener método público: {$metodo}"
            );
        }
    }

    /**
     * Verifica que previewFechas valida correctamente.
     */
    public function test_preview_fechas_validation_requiere_cambios(): void
    {
        $user = $this->actingUsuario();

        $response = $this->actingAs($user)->postJson(
            route('programa-tejido.preview-fechas-balanceo'),
            []
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cambios', 'ord_compartida']);
    }

    /**
     * Verifica que previewFechas valida estructura de cambios.
     */
    public function test_preview_fechas_validation_requiere_id_y_total_pedido(): void
    {
        $user = $this->actingUsuario();

        $response = $this->actingAs($user)->postJson(
            route('programa-tejido.preview-fechas-balanceo'),
            [
                'cambios' => [
                    ['id' => 1], // falta total_pedido
                ],
                'ord_compartida' => 123,
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cambios.0.total_pedido']);
    }

    /**
     * Verifica que calcularFormulasEficiencia usa params correctos.
     */
    public function test_calcular_formulas_usa_include_pt_vs_cte_true(): void
    {
        $reflection = new \ReflectionMethod(BalancearTejido::class, 'calcularFormulasEficiencia');
        $this->assertTrue($reflection->isPrivate(), 'calcularFormulasEficiencia debe ser privado');

        // Verificar via código fuente que se usan los params correctos
        $path = app_path('Http/Controllers/Planeacion/ProgramaTejido/funciones/BalancearTejido.php');
        $content = file_get_contents($path);

        // BalancearTejido usa includePTvsCte=true para mostrar diferencia vs compromiso
        $this->assertStringContainsString(
            'true, true, false',
            $content,
            'BalancearTejido debe usar params (true, true, false) - includePTvsCte=true'
        );
    }

    /**
     * Verifica que DuplicarTejido usa params diferentes (fallbackEntregaCte=true).
     */
    public function test_duplicar_usa_fallback_entrega_cte_true(): void
    {
        $path = app_path('Http/Controllers/Planeacion/ProgramaTejido/funciones/DuplicarTejido.php');
        $content = file_get_contents($path);

        // DuplicarTejido usa fallbackEntregaCte=true porque duplicados heredan fechas
        $this->assertStringContainsString(
            'true, true, true',
            $content,
            'DuplicarTejido debe usar params (true, true, true) - fallbackEntregaCte=true'
        );
    }

    /**
     * Verifica que TejidoHelpers tiene método calcularFormulasEficiencia público.
     */
    public function test_tejido_helpers_calcular_formulas_existe(): void
    {
        $this->assertTrue(
            method_exists(TejidoHelpers::class, 'calcularFormulasEficiencia'),
            'TejidoHelpers debe tener método público calcularFormulasEficiencia'
        );

        $reflection = new \ReflectionMethod(TejidoHelpers::class, 'calcularFormulasEficiencia');
        $this->assertTrue($reflection->isPublic(), 'calcularFormulasEficiencia debe ser público en TejidoHelpers');
    }

    /**
     * Verifica que el cache de calendarios se puede limpiar.
     */
    public function test_clear_calendario_lines_cache_funciona(): void
    {
        BalancearTejido::clearCalendarioLinesCache();

        // Si no tira excepción, el método existe y es callable
        $this->assertTrue(true, 'clearCalendarioLinesCache ejecutó sin errores');
    }

    /**
     * Verifica que las rutas de balanceo existen.
     */
    public function test_rutas_balanceo_existen(): void
    {
        $rutasEsperadas = [
            'programa-tejido.balancear',
            'programa-tejido.preview-fechas-balanceo',
            'programa-tejido.actualizar-pedidos-balanceo',
            'programa-tejido.balancear-automatico',
        ];

        foreach ($rutasEsperadas as $ruta) {
            $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($ruta);
            $this->assertNotNull($route, "Ruta debe existir: {$ruta}");
        }
    }

    /**
     * Verifica que BalancearTejido usa whereIn para bulk operations.
     */
    public function test_balancear_usa_where_in_para_consultas_bulk(): void
    {
        $path = app_path('Http/Controllers/Planeacion/ProgramaTejido/funciones/BalancearTejido.php');
        $content = file_get_contents($path);

        $this->assertStringContainsString(
            'whereIn',
            $content,
            'BalancearTejido debe usar whereIn para consultas bulk'
        );
    }
}
