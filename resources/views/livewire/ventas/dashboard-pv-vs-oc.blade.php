@vite(['resources/css/ventas/dashboard.css', 'resources/js/ventas/dashboard.js'])

<div
    class="ventas-pvoc-dashboard"
    data-ventas-pvoc-dashboard
    data-compara-url="{{ route('ventas.datos.compara') }}"
>
    <div class="pvoc-content">
        <div class="pvoc-tabs" role="tablist" aria-label="Vistas del dashboard">
            <button type="button" class="is-active" role="tab" aria-selected="true" data-pvoc-tab="summary">Compara</button>
            <button type="button" role="tab" aria-selected="false" data-pvoc-tab="history">Ventas históricas</button>
        </div>

        <section class="pvoc-card" data-pvoc-panel="summary">
            <div class="vh-subtabs" role="tablist" aria-label="Secciones de Compara">
                <button type="button" class="is-active" role="tab" aria-selected="true" data-pvoc-subtab="resumen">Resumen General</button>
                <button type="button" role="tab" aria-selected="false" data-pvoc-subtab="analisis">Análisis Histórico</button>
            </div>

            <div class="pvoc-subpanel" role="tabpanel" data-pvoc-subpanel="resumen">
                <div class="pvoc-card-header">
                </div>
                <div class="pvoc-table-scroll" data-pvoc-table="summary">@include('livewire.ventas.partials.cargando')</div>
            </div>

            <div class="pvoc-subpanel is-hidden" role="tabpanel" data-pvoc-subpanel="analisis">
                <div class="pvoc-card-header">
                    {{-- <div class="pvoc-card-title">
                        <h2>Comparativo Año › Mes</h2>
                    </div> --}}
                    <div class="pvoc-card-actions">
                        <label class="pvoc-inline-label">Desglose:
                            <select data-pvoc-desglose></select>
                        </label>
                        <div class="pvoc-columns" data-pvoc-columns="analisis">
                            <button type="button" class="pvoc-button pvoc-button-small" data-pvoc-columns-toggle aria-haspopup="true" aria-expanded="false">
                                Columnas <i class="fa-solid fa-caret-down" aria-hidden="true"></i>
                            </button>
                            <div class="pvoc-columns-menu" data-pvoc-columns-menu hidden></div>
                        </div>
                    </div>
                </div>
                <div class="pvoc-table-scroll" data-pvoc-table="analisis">@include('livewire.ventas.partials.cargando')</div>
            </div>
        </section>

        <section
            class="pvoc-card is-hidden"
            data-pvoc-panel="history"
            data-ventas-historicas
            data-historico-url="{{ route('ventas.datos.historico') }}"
            wire:ignore
        >
            {{-- Filtros de Ventas históricas; se abre con el mismo botón "Filtrar" del navbar. --}}
            <div id="vh-filter-panel" class="pvoc-filter-panel" role="dialog" aria-labelledby="vh-filter-panel-title" data-vh-filter-panel hidden>
                <div class="pvoc-filter-panel-header">
                    <h2 id="vh-filter-panel-title">Filtrar Ventas históricas</h2>
                    <button type="button" class="pvoc-filter-panel-close" data-pvoc-filter-close aria-label="Cerrar">&times;</button>
                </div>
                <div class="pvoc-filter-fields" data-vh-slicers>@include('livewire.ventas.partials.cargando')</div>
                <div class="pvoc-filter-panel-footer">
                    <button type="button" class="pvoc-button pvoc-button-small" data-vh-clear-all>Limpiar filtros</button>
                </div>
            </div>
            <div class="vh-subtabs" role="tablist" aria-label="Reportes de ventas históricas" data-vh-subtabs></div>
            <div class="vh-reports" data-vh-reports>@include('livewire.ventas.partials.cargando')</div>
        </section>
    </div>

    {{-- Filtros de Compara (Resumen General y Análisis Histórico); se abre con el botón "Filtrar" del navbar. --}}
    <div id="pvoc-filter-panel" class="pvoc-filter-panel" role="dialog" aria-labelledby="pvoc-filter-panel-title" data-pvoc-filter-panel hidden>
        <div class="pvoc-filter-panel-header">
            <h2 id="pvoc-filter-panel-title">Filtrar Compara</h2>
            <button type="button" class="pvoc-filter-panel-close" data-pvoc-filter-close aria-label="Cerrar">&times;</button>
        </div>
        <div class="pvoc-filter-fields" data-pvoc-filters></div>
        <div class="pvoc-filter-panel-footer">
            <button type="button" class="pvoc-button pvoc-button-small" data-pvoc-clear>Limpiar filtros</button>
        </div>
    </div>
</div>
