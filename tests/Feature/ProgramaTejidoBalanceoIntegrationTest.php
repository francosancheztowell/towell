<?php

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Sistema\Usuario;
use App\Services\Planeacion\ProgramaTejido\DividirTejido;
use App\Services\Planeacion\ProgramaTejido\DuplicarTejido;
use App\Services\Planeacion\ProgramaTejido\FormulasEficiencia;
use Illuminate\Support\Facades\Route;
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

    /** Sin FechaFinal y con EntregaPT/EntregaCte guardadas: solo pedido_inherit usa el fallback. */
    private function programaConEntregasGuardadas(): ReqProgramaTejido
    {
        $programa = new ReqProgramaTejido;
        $programa->FechaInicio = '2026-10-01 06:30:00';
        $programa->FechaFinal = null;
        $programa->EntregaPT = '2026-10-15';
        $programa->EntregaCte = '2026-10-20 00:00:00';

        return $programa;
    }

    private function programaConFechaFinal(): ReqProgramaTejido
    {
        $programa = new ReqProgramaTejido;
        $programa->FechaInicio = '2026-10-01 06:30:00';
        $programa->FechaFinal = '2026-10-20 08:00:00';

        return $programa;
    }

    /** Invoca el calcularFormulasEficiencia (privado o público) de la clase de operación. */
    private function formulasDe(string $clase, ReqProgramaTejido $programa): array
    {
        return (new \ReflectionMethod($clase, 'calcularFormulasEficiencia'))->invoke(null, $programa);
    }

    public function test_balancear_no_usa_fallback_entrega_cte(): void
    {
        $formulas = FormulasEficiencia::calcularFormulasEficienciaPorContexto(
            $this->programaConEntregasGuardadas(),
            FormulasEficiencia::FORMULAS_CTX_BALANCEAR
        );

        $this->assertArrayNotHasKey('EntregaCte', $formulas);
        $this->assertArrayNotHasKey('PTvsCte', $formulas);
        $this->assertSame('2026-10-03', $formulas['EntregaProduc']);
    }

    public function test_pedido_inherit_usa_fallback_entrega_cte(): void
    {
        $formulas = FormulasEficiencia::calcularFormulasEficienciaPorContexto(
            $this->programaConEntregasGuardadas(),
            FormulasEficiencia::FORMULAS_CTX_PEDIDO_INHERIT
        );

        $this->assertArrayNotHasKey('EntregaCte', $formulas);
        $this->assertEqualsWithDelta(-5.0, $formulas['PTvsCte'], 0.001);
    }

    public function test_ambos_contextos_calculan_entrega_cte_y_pt_vs_cte_desde_fecha_final(): void
    {
        foreach ([FormulasEficiencia::FORMULAS_CTX_BALANCEAR, FormulasEficiencia::FORMULAS_CTX_PEDIDO_INHERIT] as $ctx) {
            $formulas = FormulasEficiencia::calcularFormulasEficienciaPorContexto($this->programaConFechaFinal(), $ctx);

            // Sin AplicacionId: 12 días de entrega.
            $this->assertSame('2026-11-01 08:00:00', $formulas['EntregaCte'], $ctx);
            $this->assertSame('2026-10-15', $formulas['EntregaPT'], $ctx);
            $this->assertEqualsWithDelta(-17.0, $formulas['PTvsCte'], 0.001, $ctx);
        }
    }

    /** Balancear, Duplicar y Dividir pasan su contexto: se ve en el fallback de EntregaCte. */
    public function test_operaciones_usan_su_contexto(): void
    {
        $this->assertArrayNotHasKey('PTvsCte', $this->formulasDe(BalancearTejido::class, $this->programaConEntregasGuardadas()));
        $this->assertEqualsWithDelta(-5.0, $this->formulasDe(DuplicarTejido::class, $this->programaConEntregasGuardadas())['PTvsCte'], 0.001);
        $this->assertEqualsWithDelta(-5.0, $this->formulasDe(DividirTejido::class, $this->programaConEntregasGuardadas())['PTvsCte'], 0.001);
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
            $route = Route::getRoutes()->getByName($ruta);
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
