@vite(['resources/css/ventas/dashboard.css', 'resources/js/ventas/dashboard.js'])

<div
    class="ventas-pvoc-dashboard"
    data-ventas-pvoc-dashboard
    data-dashboard='@json($dashboard)'
>
    @if ($dataError)
        <div class="ventas-pvoc-alert" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span>{{ $dataError }}</span>
        </div>
    @endif

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

            {{-- Filtros compartidos por Resumen General y Análisis Histórico. --}}
            <div class="pvoc-filters pvoc-filters-shared" aria-label="Filtros de Compara">
                <div class="pvoc-filter-fields" data-pvoc-filters></div>
                <button type="button" class="pvoc-button pvoc-button-small" data-pvoc-clear>Limpiar</button>
            </div>

            <div class="pvoc-subpanel" role="tabpanel" data-pvoc-subpanel="resumen">
                <div class="pvoc-card-header">
                    <div>
                        <h2>Comparativo Empresa › Tipo de pedido › Cliente</h2>
                        <p>Clic en <strong>▸</strong> para expandir · doble clic en un cliente para ver artículos.</p>
                    </div>
                    <div class="pvoc-card-actions">
                        <button type="button" class="pvoc-button pvoc-button-small" data-pvoc-expand="summary">Expandir todo</button>
                        <button type="button" class="pvoc-button pvoc-button-small" data-pvoc-collapse="summary">Colapsar todo</button>
                    </div>
                </div>
                <div class="pvoc-table-scroll" data-pvoc-table="summary"></div>
            </div>

            <div class="pvoc-subpanel is-hidden" role="tabpanel" data-pvoc-subpanel="analisis">
                <div class="pvoc-card-header">
                    <div class="pvoc-card-title">
                        <h2>Comparativo Año › Mes</h2>
                        <p>Doble clic en un mes para desglosar</p>
                    </div>
                    <div class="pvoc-card-actions">
                        <label class="pvoc-inline-label">Desglose:
                            <select data-pvoc-desglose></select>
                        </label>
                        <button type="button" class="pvoc-button pvoc-button-small" data-pvoc-expand="analisis">Expandir todo</button>
                        <button type="button" class="pvoc-button pvoc-button-small" data-pvoc-collapse="analisis">Colapsar todo</button>
                        <div class="pvoc-columns" data-pvoc-columns="analisis">
                            <button type="button" class="pvoc-button pvoc-button-small" data-pvoc-columns-toggle aria-haspopup="true" aria-expanded="false">
                                Columnas <i class="fa-solid fa-caret-down" aria-hidden="true"></i>
                            </button>
                            <div class="pvoc-columns-menu" data-pvoc-columns-menu hidden></div>
                        </div>
                    </div>
                </div>
                <div class="pvoc-table-scroll" data-pvoc-table="analisis"></div>
            </div>
        </section>

        <section
            class="pvoc-card is-hidden"
            data-pvoc-panel="history"
            data-ventas-historicas
            data-historico='@json($historico)'
            wire:ignore
        >
            <div class="pvoc-card-header">
                <div>
                    <h2>Ventas históricas</h2>
                    <p>Elige uno o varios valores en cada filtro; los atenuados no tienen datos con la selección actual. Fuente: facturación (TwHistoricosVentas).</p>
                </div>
            </div>
            @if ($historicoError)
                <div class="ventas-pvoc-alert" role="alert">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <span>{{ $historicoError }}</span>
                </div>
            @endif
            <div class="pvoc-filters" aria-label="Filtros de ventas históricas">
                <div class="pvoc-filter-fields" data-vh-slicers></div>
                <button type="button" class="pvoc-button pvoc-button-small" data-vh-clear-all>Limpiar</button>
            </div>
            <div class="vh-summary" data-vh-summary></div>
            <div class="vh-subtabs" role="tablist" aria-label="Reportes de ventas históricas" data-vh-subtabs></div>
            <div class="vh-reports" data-vh-reports></div>
        </section>
    </div>

    <div class="pvoc-toast" aria-live="polite" data-pvoc-toast></div>
</div>
