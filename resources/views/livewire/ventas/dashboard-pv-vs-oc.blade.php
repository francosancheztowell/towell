@vite(['resources/css/ventas/dashboard.css', 'resources/js/ventas/dashboard.ts'])

<div
    class="ventas-pvoc-dashboard"
    data-ventas-pvoc-dashboard
    data-compara-url="{{ route('ventas.datos.compara', ['v' => $versionDatos]) }}"
    data-compara-anios-url="{{ route('ventas.datos.compara.anios', ['v' => $versionDatos]) }}"
    data-version-url="{{ route('ventas.datos.version') }}"
    data-datos-version="{{ $versionDatos }}"
>
    {{-- Aviso de datos nuevos (datos-version.ts): aparece cuando se publica otra versión de los datos. --}}
    <div class="pvoc-datos-nuevos" role="status" data-pvoc-datos-nuevos hidden>
        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
        <span>Hay información nueva de Ventas.</span>
        <button type="button" class="pvoc-button pvoc-button-small" data-pvoc-datos-recargar>Actualizar</button>
    </div>
    {{-- Sidebar de secciones y subsecciones (sidebar.ts); se oculta con su botón y se recuerda por navegador. --}}
    <div class="pvoc-layout">
    <aside class="pvoc-sidebar" data-pvoc-sidebar aria-label="Secciones de Ventas">
        <button type="button" class="pvoc-sidebar-toggle" data-pvoc-sidebar-toggle aria-controls="pvoc-sidebar-nav" aria-expanded="true" aria-label="Ocultar secciones" title="Ocultar secciones">
            <i class="fa-solid fa-angles-left" aria-hidden="true"></i>
        </button>
        <nav id="pvoc-sidebar-nav" class="pvoc-sidebar-nav">
            <div class="pvoc-nav-group is-current" data-pvoc-nav-group="summary">
                <button type="button" class="pvoc-nav-section" data-pvoc-nav-section aria-expanded="true" aria-controls="pvoc-nav-summary">
                    <i class="fa-solid fa-chevron-down pvoc-nav-chevron" aria-hidden="true"></i>
                    <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i><span>Compara</span>
                </button>
                <ul id="pvoc-nav-summary" class="pvoc-nav-items">
                    {{-- VtasR. vs PlanVtas oculta por ahora: para volver a mostrarla, restaurar su botón y su subpanel. --}}
                    <li><button type="button" class="pvoc-nav-item is-active" aria-current="page" data-pvoc-nav="summary" data-pvoc-nav-sub="resumen">Resumen General</button></li>
                    <li><button type="button" class="pvoc-nav-item" data-pvoc-nav="summary" data-pvoc-nav-sub="analisis">Análisis Histórico</button></li>
                    <li><button type="button" class="pvoc-nav-item" data-pvoc-nav="pedidos" data-pvoc-nav-sub="pedidos">Pedidos Históricos</button></li>
                </ul>
            </div>
            <div class="pvoc-nav-group" data-pvoc-nav-group="history">
                <button type="button" class="pvoc-nav-section" data-pvoc-nav-section aria-expanded="true" aria-controls="pvoc-nav-history">
                    <i class="fa-solid fa-chevron-down pvoc-nav-chevron" aria-hidden="true"></i>
                    <i class="fa-solid fa-chart-line" aria-hidden="true"></i><span>Ventas históricas</span>
                </button>
                {{-- Los reportes los pone sidebar.ts desde HISTORY_SECTIONS (ventas-historicas.ts). --}}
                <ul id="pvoc-nav-history" class="pvoc-nav-items" data-pvoc-nav-history></ul>
            </div>
        </nav>
    </aside>

    <div class="pvoc-content">
        <section class="pvoc-card" data-pvoc-panel="summary">
            <div class="pvoc-subpanel" role="region" aria-label="Resumen General" data-pvoc-subpanel="resumen">
                <div class="pvoc-card-header">
                </div>
                <div class="pvoc-table-scroll" data-pvoc-table="summary">@include('livewire.ventas.partials.cargando')</div>
                {{-- KPIs de cierre (closingKpis en compara.ts), debajo de la tabla y con sus mismos filtros. --}}
                <div class="pvoc-kpis" data-pvoc-kpis aria-live="polite"></div>
            </div>

            <div class="pvoc-subpanel is-hidden" role="region" aria-label="Análisis Histórico" data-pvoc-subpanel="analisis">
                <div class="pvoc-card-header">
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

            {{-- Zoom tipo Excel (zoom.ts): también Ctrl + rueda sobre la tabla. --}}
            <div class="pvoc-zoom" data-pvoc-zoom role="group" aria-label="Zoom de la tabla">
                <button type="button" class="pvoc-zoom-fit" data-pvoc-zoom-fit title="Ajustar al ancho para ver todas las columnas">
                    <i class="fa-solid fa-left-right" aria-hidden="true"></i> Ajustar
                </button>
                {{-- Anchos de columna (column-resize.ts): se arrastra el borde del encabezado; doble clic lo ajusta. --}}
                <button type="button" class="pvoc-zoom-widths" data-pvoc-widths-reset title="Quitar los anchos que se ajustaron a mano en esta tabla">
                    <i class="fa-solid fa-arrows-left-right-to-line" aria-hidden="true"></i> Anchos automáticos
                </button>
                <button type="button" class="pvoc-zoom-step" data-pvoc-zoom-step="-1" aria-label="Alejar" title="Alejar">&minus;</button>
                <input type="range" step="10" value="90" data-pvoc-zoom-range aria-label="Zoom">
                <button type="button" class="pvoc-zoom-step" data-pvoc-zoom-step="1" aria-label="Acercar" title="Acercar">+</button>
                <button type="button" class="pvoc-zoom-value" data-pvoc-zoom-reset title="Volver al tamaño normal (90 %)"><span data-pvoc-zoom-value>90 %</span></button>
            </div>
        </section>

        {{-- Pedidos Históricos (pedidos-historicos.ts): árbol Empresa › Tipo › Cliente › OC › Artículo de TwHistoricosPedidos. --}}
        <section
            class="pvoc-card ph-panel is-hidden"
            data-pvoc-panel="pedidos"
            data-pedidos-historicos
            data-pedidos-url="{{ route('ventas.datos.pedidos', ['v' => $versionDatos]) }}"
            data-pedidos-anios-url="{{ route('ventas.datos.pedidos.anios', ['v' => $versionDatos]) }}"
            aria-label="Pedidos Históricos"
            wire:ignore
        >
            <div id="ph-filter-panel" class="pvoc-filter-panel" role="dialog" aria-labelledby="ph-filter-panel-title" data-ph-filter-panel hidden>
                <div class="pvoc-filter-panel-header">
                    <h2 id="ph-filter-panel-title">Filtrar Pedidos Históricos</h2>
                    <button type="button" class="pvoc-filter-panel-close" data-pvoc-filter-close aria-label="Cerrar">&times;</button>
                </div>
                <div class="pvoc-filter-fields" data-ph-slicers>@include('livewire.ventas.partials.cargando')</div>
                <div class="pvoc-filter-panel-footer">
                    <button type="button" class="pvoc-button pvoc-button-small" data-ph-clear-all>Limpiar filtros</button>
                </div>
            </div>

            <div class="ph-head">
                <div class="ph-chips" data-ph-chips></div>
            </div>
            <div class="pvoc-table-scroll ph-scroll" data-ph-table>@include('livewire.ventas.partials.cargando')</div>
            {{-- KPIs debajo de la tabla, igual que en Resumen General. --}}
            <div class="ph-kpis" data-ph-kpis aria-live="polite"></div>
        </section>

        <section
            class="pvoc-card is-hidden"
            data-pvoc-panel="history"
            data-ventas-historicas
            data-historico-url="{{ route('ventas.datos.historico', ['v' => $versionDatos]) }}"
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
            <div class="vh-reports" data-vh-reports>@include('livewire.ventas.partials.cargando')</div>
        </section>
    </div>
    </div>

    {{-- Filtros de Compara (VtasR. vs PlanVtas, Resumen General y Análisis Histórico); se abre con el botón "Filtrar" del navbar. --}}
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
