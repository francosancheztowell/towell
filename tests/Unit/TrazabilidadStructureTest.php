<?php

namespace Tests\Unit;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Trazabilidad\TrazaProduccion;
use App\Services\Trazabilidad\TrazabilidadProduccionService;
use ReflectionMethod;
use Tests\TestCase;

class TrazabilidadStructureTest extends TestCase
{
    public function test_index_is_a_small_composition_view_with_external_assets(): void
    {
        $view = file_get_contents(resource_path('views/modulos/trazabilidad/index.blade.php'));

        $this->assertLessThan(100, count(file(resource_path('views/modulos/trazabilidad/index.blade.php'))));
        $this->assertStringContainsString('<livewire:trazabilidad.index />', $view);
        $this->assertStringContainsString('id="resultado-detalle"', $view);
        $this->assertStringContainsString("@include('modulos.trazabilidad._modal_rollos_maquina')", $view);
        $this->assertStringContainsString("@include('modulos.trazabilidad._modal_flog_imagen')", $view);
        $this->assertStringContainsString("@include('modulos.programa-tejido.modal.redbooth')", $view);
        $this->assertStringContainsString("@vite('resources/css/trazabilidad/index.css')", $view);
        $this->assertStringContainsString("@vite('resources/js/trazabilidad/index.ts')", $view);
        $this->assertStringNotContainsString('<style>', $view);
    }

    public function test_redbooth_button_resolves_the_selected_flog_orders(): void
    {
        $view = file_get_contents(resource_path('views/modulos/trazabilidad/index.blade.php'));
        $script = file_get_contents(resource_path('js/trazabilidad/redbooth.ts'));
        // La lógica del modal salió del Blade a su entrada Vite (HANDOFF PT-05 B4, PT 03).
        $modal = file_get_contents(resource_path('js/modulos/redbooth/modal.ts'));

        $this->assertStringContainsString('id="btn-redbooth"', $view);
        $this->assertStringContainsString("'redbooth' => route('trazabilidad.redbooth')", $view);
        $this->assertStringContainsString('openForSelectedFlog', $script);
        $this->assertStringContainsString('window.abrirModalRedboothProgramaTejido', $script);
        $this->assertStringContainsString('data.primerVinculo', $script);
        $this->assertStringContainsString('flogAsignacion: flog', $script);
        $this->assertStringNotContainsString("input: 'select'", $script);
        $this->assertStringContainsString('payload.flog_asignacion = flogAsignacion', $modal);
    }

    public function test_filters_are_preserved_in_browser_url(): void
    {
        $component = file_get_contents(app_path('Livewire/Trazabilidad/Index.php'));

        $this->assertSame(3, substr_count($component, '#[Url('));
        foreach (['$flog', '$articulo', '$tamano'] as $property) {
            $this->assertStringContainsString("public string {$property}", $component);
        }
        $this->assertStringNotContainsString('history.replaceState', $component);
    }

    public function test_hidden_color_month_and_metric_filters_are_gone(): void
    {
        $filters = file_get_contents(resource_path('views/livewire/trazabilidad/index.blade.php'));
        $loader = file_get_contents(resource_path('js/trazabilidad/detail-loader.ts'));

        foreach (['filtro-color', 'filtro-mes', 'filtro-metrica'] as $id) {
            $this->assertStringNotContainsString($id, $filters);
            $this->assertStringNotContainsString($id, $loader);
        }
    }

    public function test_loading_indicator_is_not_tied_to_a_named_action(): void
    {
        $filters = file_get_contents(resource_path('views/livewire/trazabilidad/index.blade.php'));
        $selects = file_get_contents(resource_path('js/trazabilidad/filter-selects.ts'));

        // Los filtros llegan por evento: un wire:target por nombre de acción nunca coincidía.
        $this->assertStringNotContainsString('wire:target', $filters);
        $this->assertStringContainsString('wire:loading', $filters);
        // Tom Select se rearma también cuando la petición falla.
        $this->assertStringContainsString("hook('commit'", $selects);
        $this->assertStringContainsString('fail(', $selects);
    }

    public function test_detail_panel_can_go_back_and_retry_after_an_error(): void
    {
        $view = file_get_contents(resource_path('views/modulos/trazabilidad/index.blade.php'));
        $loader = file_get_contents(resource_path('js/trazabilidad/detail-loader.ts'));

        $this->assertStringContainsString('data-volver-resumen', $view);
        $this->assertStringContainsString('data-detalle-reintentar', $view);
        $this->assertStringContainsString("'[data-detalle-reintentar]'", $loader);
        $this->assertStringContainsString('this.opener.focus()', $loader);
    }

    public function test_details_use_dedicated_get_endpoints_without_the_legacy_part_switch(): void
    {
        $routes = file_get_contents(base_path('routes/modules/trazabilidad.php'));
        $loader = file_get_contents(resource_path('js/trazabilidad/detail-loader.ts'));
        $controller = file_get_contents(app_path('Http/Controllers/Trazabilidad/TrazabilidadDetailController.php'));

        foreach (['details.matrix', 'details.production', 'details.flog'] as $routeName) {
            $this->assertStringContainsString("name('{$routeName}')", $routes);
        }
        foreach (['function matrix(', 'function production(', 'function flog('] as $method) {
            $this->assertStringContainsString($method, $controller);
        }
        $this->assertStringContainsString('AbortController', $loader);
        $this->assertStringContainsString('CACHE_TTL_MS', $loader);
        $this->assertStringNotContainsString('part:', $loader);
    }

    public function test_frontend_entry_is_small_strict_typescript_and_delegates_behaviors(): void
    {
        $entryPath = resource_path('js/trazabilidad/index.ts');
        $entry = file_get_contents($entryPath);
        $tsconfig = file_get_contents(base_path('tsconfig.json'));

        $this->assertLessThan(100, count(file($entryPath)));
        foreach ([
            './detail-loader',
            './filter-selects',
            './flog-image-viewer',
            './matrix-detail',
            './production-detail',
            './redbooth',
        ] as $module) {
            $this->assertStringContainsString("from '{$module}'", $entry);
        }
        $this->assertStringContainsString('"strict": true', $tsconfig);
        $this->assertFileDoesNotExist(resource_path('js/trazabilidad/index.js'));

        $livewireView = file_get_contents(resource_path('views/livewire/trazabilidad/index.blade.php'));
        $this->assertStringNotContainsString('<script>', $livewireView);
        $this->assertStringNotContainsString('@script', $livewireView);
    }

    public function test_filtered_screen_renders_only_the_first_summary_section(): void
    {
        $result = file_get_contents(resource_path('views/modulos/trazabilidad/_resultado.blade.php'));

        $this->assertStringContainsString("@include('modulos.trazabilidad.resumen._flog'", $result);
        $this->assertStringContainsString("@include('modulos.trazabilidad.resumen._avance'", $result);
        $this->assertStringContainsString("@include('modulos.trazabilidad.resumen._trazabilidad'", $result);
        $this->assertStringContainsString("@include('modulos.trazabilidad.resumen._ventas')", $result);
        $this->assertStringNotContainsString('data-tab=', $result);
    }

    public function test_sales_card_is_only_a_coming_soon_placeholder(): void
    {
        $sales = file_get_contents(resource_path('views/modulos/trazabilidad/resumen/_ventas.blade.php'));

        $this->assertStringContainsString('Próximamente', $sales);
        $this->assertStringNotContainsString('$resumen', $sales);
        $this->assertStringNotContainsString('<table', $sales);
        $this->assertStringNotContainsString('detalle=', $sales);
        $this->assertStringNotContainsString(
            'ventas',
            file_get_contents(resource_path('js/trazabilidad/detail-loader.ts')),
        );
    }

    public function test_each_summary_card_has_its_detail_destination(): void
    {
        $base = resource_path('views/modulos/trazabilidad/resumen');

        $this->assertStringContainsString('detalle="flogs"', file_get_contents($base.'/_flog.blade.php'));
        $this->assertStringContainsString('detalle="produccion"', file_get_contents($base.'/_avance.blade.php'));
        $this->assertStringContainsString('detalle="trazabilidad"', file_get_contents($base.'/_trazabilidad.blade.php'));
        $this->assertStringContainsString(
            'data-resumen-detalle="{{ $detalle }}"',
            file_get_contents(resource_path('views/components/trazabilidad/tarjeta.blade.php')),
        );
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
        $this->assertStringContainsString('aria-label="Facturado', $flog);
    }

    public function test_second_card_is_the_weaving_program_table(): void
    {
        $advance = file_get_contents(resource_path('views/modulos/trazabilidad/resumen/_avance.blade.php'));

        $this->assertStringContainsString('titulo="Programa tejido"', $advance);
        $this->assertStringContainsString('data-tabla-avance-pedido', $advance);
        foreach (['Flog', 'Orden', 'Tam.', 'Telar', 'Progr.', 'Saldo', 'Inicio', 'Fin'] as $column) {
            $this->assertStringContainsString('>'.$column.'</flux:table.column>', $advance);
        }
        $this->assertStringNotContainsString('>Prod.</flux:table.column>', $advance);
        foreach (['programado', 'restante', 'telar', 'enProceso'] as $field) {
            $this->assertStringContainsString("\$fila['{$field}']", $advance);
        }
        $this->assertLessThan(strpos($advance, '>Tam.</flux:table.column>'), strpos($advance, '>Orden</flux:table.column>'));
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

    public function test_third_card_uses_traceability_colors_and_fixed_area_order(): void
    {
        $card = file_get_contents(resource_path('views/modulos/trazabilidad/resumen/_trazabilidad.blade.php'));
        $service = file_get_contents(app_path('Services/Trazabilidad/TrazabilidadResumenService.php'));

        $this->assertStringContainsString("['dot']", $card);
        $this->assertStringContainsString('$this->matrixService->areasPara(', $service);
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

    public function test_filter_scope_is_the_single_definition_and_skips_empty_and_excepted_filters(): void
    {
        $query = TrazaProduccion::query()->filtrados(['flog' => ' F-1 ', 'articulo' => '', 'tamano' => 'MB'], 'tamano');

        // VARCHAR explícito: un parámetro NVARCHAR impedía buscar en el índice de Flogs.
        $this->assertStringContainsString('[Flogs] = CAST(? AS varchar(100))', $query->toSql());
        $this->assertStringNotContainsString('Articulo', $query->toSql());
        $this->assertStringNotContainsString('Tamano', $query->toSql());
        $this->assertSame(['F-1'], $query->getBindings());

        $ordenes = CatCodificados::query()->ordenesTejido(['36160', '36191']);
        $this->assertStringContainsString('[OrdenTejido] IN (CAST(? AS varchar(30)), CAST(? AS varchar(30)))', $ordenes->toSql());
        $this->assertSame(['36160', '36191'], $ordenes->getBindings());
        $this->assertStringContainsString('1 = 0', CatCodificados::query()->ordenesTejido([])->toSql());

        foreach (['Resumen', 'Matrix', 'Produccion', 'FilterOptions'] as $service) {
            $code = file_get_contents(app_path("Services/Trazabilidad/Trazabilidad{$service}Service.php"));
            $this->assertStringContainsString('->filtrados(', $code, $service);
            $this->assertStringNotContainsString("where('Tamano'", $code, $service);
        }
    }
}
