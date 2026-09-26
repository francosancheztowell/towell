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
        {{-- <div class="pvoc-anio-selector">
            <label class="pvoc-select-label" for="pvoc-anio">Año
                <select id="pvoc-anio" wire:model.live="anio">
                    @foreach ($anios ?? [] as $anioOption)
                        <option value="{{ $anioOption }}">{{ $anioOption }}</option>
                    @endforeach
                </select>
            </label>
        </div> --}}

        <div class="pvoc-tabs" role="tablist" aria-label="Vistas del dashboard">
            <button type="button" class="is-active" role="tab" aria-selected="true" data-pvoc-tab="summary">Resumen general</button>
            <button type="button" role="tab" aria-selected="false" data-pvoc-tab="history">Ventas históricas</button>
        </div>

        <section class="pvoc-card" data-pvoc-panel="summary">
            <div class="pvoc-card-header">
                <div>
                    <h2>Comparativo Empresa › Tipo de pedido › Cliente</h2>
                    <p>Clic en <strong>▸</strong> para expandir · doble clic en un cliente para ver artículos.</p>
                </div>
                <div class="pvoc-card-actions">
                    <button type="button" class="pvoc-button pvoc-button-small" data-pvoc-expand>Expandir todo</button>
                    <button type="button" class="pvoc-button pvoc-button-small" data-pvoc-collapse>Colapsar todo</button>
                </div>
            </div>
            <div class="pvoc-filters" aria-label="Filtros del resumen general">
                <div class="pvoc-filter-fields" data-pvoc-filters></div>
                <button type="button" class="pvoc-button pvoc-button-small" data-pvoc-clear>Limpiar</button>
            </div>
            <div class="pvoc-table-scroll" data-pvoc-table="summary"></div>
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
