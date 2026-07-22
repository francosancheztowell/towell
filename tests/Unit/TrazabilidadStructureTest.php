<?php

namespace Tests\Unit;

use App\Services\Trazabilidad\TrazabilidadProduccionService;
use ReflectionMethod;
use Tests\TestCase;

class TrazabilidadStructureTest extends TestCase
{
    public function test_index_is_a_small_composition_view_with_external_assets(): void
    {
        $view = file_get_contents(resource_path('views/modulos/trazabilidad/index.blade.php'));

        $this->assertLessThan(100, count(file(resource_path('views/modulos/trazabilidad/index.blade.php'))));
        $this->assertStringContainsString("@include('modulos.trazabilidad._filters')", $view);
        $this->assertStringContainsString("@include('modulos.trazabilidad._modal_rollos_maquina')", $view);
        $this->assertStringContainsString("@include('modulos.trazabilidad._modal_flog_imagen')", $view);
        $this->assertStringContainsString("@include('modulos.programa-tejido.modal.redbooth')", $view);
        $this->assertStringContainsString("@vite('resources/css/trazabilidad/index.css')", $view);
        $this->assertStringContainsString("@vite('resources/js/trazabilidad/index.js')", $view);
        $this->assertStringNotContainsString('<style>', $view);
    }

    public function test_redbooth_button_resolves_the_selected_flog_orders(): void
    {
        $view = file_get_contents(resource_path('views/modulos/trazabilidad/index.blade.php'));
        $script = file_get_contents(resource_path('js/trazabilidad/index.js'));

        $this->assertStringContainsString('id="btn-redbooth"', $view);
        $this->assertStringContainsString("'redbooth' => route('trazabilidad.redbooth')", $view);
        $this->assertStringContainsString('abrirRedboothDelFlog', $script);
        $this->assertStringContainsString('RUTA_REDBOOTH', $script);
        $this->assertStringContainsString('window.abrirModalRedboothProgramaTejido', $script);
        $this->assertStringNotContainsString("$('#btn-exportar').on('click'", $script);
    }

    public function test_filters_are_preserved_in_browser_url(): void
    {
        $script = file_get_contents(resource_path('js/trazabilidad/index.js'));

        $this->assertStringContainsString('function sincronizarUrl(', $script);
        $this->assertStringContainsString('query.set(campo, String(valor))', $script);
        $this->assertStringNotContainsString("replaceState(null, '', RUTA)", $script);
    }

    public function test_back_to_summary_button_uses_blue_background_and_white_text(): void
    {
        $script = file_get_contents(resource_path('js/trazabilidad/index.js'));

        $this->assertStringContainsString('data-volver-resumen', $script);
        $this->assertStringContainsString('bg-blue-500', $script);
        $this->assertStringContainsString('text-white', $script);
    }

    public function test_initial_filters_do_not_render_the_color_control(): void
    {
        $filters = file_get_contents(resource_path('views/modulos/trazabilidad/_filters.blade.php'));
        $result = file_get_contents(resource_path('views/modulos/trazabilidad/_resultado.blade.php'));

        $this->assertStringNotContainsString('id="filtro-color"', $filters);
        $this->assertStringNotContainsString('name="color"', $filters);
        $this->assertStringNotContainsString('Artículo, Tamaño, Color o Mes', $result);
    }

    public function test_metric_bar_and_badges_are_hidden_from_the_filter_form(): void
    {
        $filters = file_get_contents(resource_path('views/modulos/trazabilidad/_filters.blade.php'));

        $this->assertStringNotContainsString('data-metrica=', $filters);
        $this->assertStringNotContainsString('id="resumen-conteos"', $filters);
        $this->assertStringNotContainsString('id="meses-badges"', $filters);
        $this->assertStringContainsString('type="hidden" name="metrica"', $filters);
    }

    public function test_filtered_screen_renders_only_the_first_summary_section(): void
    {
        $result = file_get_contents(resource_path('views/modulos/trazabilidad/_resultado.blade.php'));

        $this->assertStringContainsString("@include('modulos.trazabilidad.resumen._flog'", $result);
        $this->assertStringContainsString("@include('modulos.trazabilidad.resumen._avance'", $result);
        $this->assertStringContainsString("@include('modulos.trazabilidad.resumen._trazabilidad'", $result);
        $this->assertStringContainsString("@include('modulos.trazabilidad.resumen._ventas')", $result);
        $this->assertStringNotContainsString('data-tab="trazabilidad"', $result);
        $this->assertStringNotContainsString('data-tab="produccion"', $result);
        $this->assertStringNotContainsString('data-tab="flogs"', $result);
    }

    public function test_sales_quadrant_is_front_end_only(): void
    {
        $sales = file_get_contents(resource_path('views/modulos/trazabilidad/resumen/_ventas.blade.php'));

        $this->assertStringContainsString('data-sales-frontend-only', $sales);
        $this->assertStringContainsString('Ventas pendiente de conexión', $sales);
        $this->assertStringNotContainsString('$resumen', $sales);
    }

    public function test_each_summary_card_has_its_detail_destination(): void
    {
        $base = resource_path('views/modulos/trazabilidad/resumen');

        $this->assertStringContainsString('data-resumen-detalle="flogs"', file_get_contents($base.'/_flog.blade.php'));
        $this->assertStringContainsString('data-resumen-detalle="produccion"', file_get_contents($base.'/_avance.blade.php'));
        $this->assertStringContainsString('data-resumen-detalle="trazabilidad"', file_get_contents($base.'/_trazabilidad.blade.php'));
        $this->assertStringContainsString('data-resumen-detalle="ventas"', file_get_contents($base.'/_ventas.blade.php'));
    }

    public function test_flog_primary_fields_share_the_first_three_column_row(): void
    {
        $flog = file_get_contents(resource_path('views/modulos/trazabilidad/resumen/_flog.blade.php'));

        $this->assertStringContainsString('sm:grid-cols-3', $flog);
        $this->assertLessThan(strpos($flog, "'etiqueta' => 'Artículo'"), strpos($flog, "'etiqueta' => 'No. Flog'"));
        $this->assertLessThan(strpos($flog, "'etiqueta' => 'Tamaño'"), strpos($flog, "'etiqueta' => 'Artículo'"));
        $this->assertStringContainsString("['Pedido', \$pedido", $flog);
    }

    public function test_order_card_shows_invoiced_pending_and_their_bar(): void
    {
        $flog = file_get_contents(resource_path('views/modulos/trazabilidad/resumen/_flog.blade.php'));

        $this->assertStringContainsString("['Facturado', \$facturado", $flog);
        $this->assertStringContainsString("['Pendiente', \$pendiente", $flog);
        $this->assertStringContainsString('Distribución de facturación', $flog);
        $this->assertStringContainsString('bg-emerald-500', $flog);
        $this->assertStringContainsString('bg-amber-400', $flog);
    }

    public function test_second_card_is_the_order_progress_table_shell(): void
    {
        $advance = file_get_contents(resource_path('views/modulos/trazabilidad/resumen/_avance.blade.php'));

        $this->assertStringContainsString('data-tabla-avance-pedido', $advance);
        foreach (['Flog', 'Tam.', 'Orden', 'Telar', 'Progr.', 'Prod.', 'Pedido', 'Ini.', 'Fin'] as $column) {
            $this->assertStringContainsString('>'.$column.'</th>', $advance);
        }
        $this->assertStringContainsString('$tablaAvancePedido', $advance);
        $this->assertStringContainsString("\$fila['programado']", $advance);
        $this->assertStringContainsString("\$fila['produccion']", $advance);
        $this->assertStringContainsString("\$fila['pedido']", $advance);
        $this->assertStringContainsString("\$fila['telar']", $advance);
        $this->assertStringContainsString("\$fila['enProceso']", $advance);
        $this->assertLessThan(strpos($advance, '>Tam.</th>'), strpos($advance, '>Orden</th>'));
        $this->assertStringContainsString('w-full table-auto', $advance);
        $this->assertStringNotContainsString('w-[32%]', $advance);
        $this->assertStringNotContainsString('min-w-[1020px]', $advance);
        $this->assertStringContainsString('overflow-x-hidden', $advance);
        $this->assertStringContainsString('class="whitespace-nowrap"', $advance);
    }

    public function test_order_progress_dates_never_include_time(): void
    {
        $method = new ReflectionMethod(TrazabilidadProduccionService::class, 'formatearSoloFecha');

        $this->assertSame(
            '21/07/26',
            $method->invoke(app(TrazabilidadProduccionService::class), '2026-07-21 14:35:59')
        );
    }

    public function test_order_progress_table_uses_only_the_loom_number(): void
    {
        $service = file_get_contents(app_path('Services/Trazabilidad/TrazabilidadProduccionService.php'));

        $this->assertStringContainsString("preg_replace('/\\D+/'", $service);
        $this->assertStringContainsString("ltrim(\$telarDigitos, '0')", $service);
    }

    public function test_order_progress_table_keeps_blue_headers_visible_and_separates_columns(): void
    {
        $advance = file_get_contents(resource_path('views/modulos/trazabilidad/resumen/_avance.blade.php'));

        $this->assertSame(9, substr_count($advance, 'sticky top-0 z-10'));
        $this->assertSame(9, substr_count($advance, 'bg-blue-600'));
        $this->assertStringContainsString('border-r border-blue-100', $advance);
        $this->assertStringContainsString('even:bg-blue-50/40', $advance);
    }

    public function test_temporary_billing_rule_uses_zero_and_order_difference(): void
    {
        $service = file_get_contents(app_path('Services/Trazabilidad/TrazabilidadResumenService.php'));

        $this->assertStringContainsString('$facturado = 0.0;', $service);
        $this->assertStringContainsString('max(0, $pedido - $facturado)', $service);
        $this->assertStringNotContainsString('buildFacturacion(', $service);
    }

    public function test_third_card_uses_traceability_colors_and_fixed_area_order(): void
    {
        $card = file_get_contents(resource_path('views/modulos/trazabilidad/resumen/_trazabilidad.blade.php'));
        $service = file_get_contents(app_path('Services/Trazabilidad/TrazabilidadResumenService.php'));

        $this->assertStringContainsString("['tint']", $card);
        $this->assertStringContainsString("['text']", $card);
        $this->assertStringContainsString("['dot']", $card);
        $this->assertStringContainsString('$this->matrixService->areasFijas', $service);
        $this->assertStringContainsString("['fechaInicio']", $card);
        $this->assertStringContainsString("['fechaFin']", $card);
        $this->assertStringContainsString('round((float) ($fila->piezas ?? 0), 0)', $service);
        $this->assertStringContainsString('round((float) ($fila->kilos ?? 0), 1)', $service);
    }

    public function test_global_navbar_hides_the_stop_button_in_trazabilidad_only(): void
    {
        $navbar = file_get_contents(resource_path('views/components/navbar/navbar.blade.php'));

        $this->assertStringContainsString("!request()->routeIs('trazabilidad.*')", $navbar);
        $this->assertStringContainsString('mantenimiento/nuevo-paro', $navbar);
        $this->assertStringContainsString('$showParoButton', $navbar);
    }
}
