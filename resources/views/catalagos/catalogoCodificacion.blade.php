@extends('layouts.app')

@section('page-title', 'Modelos Codificados')

@section('navbar-right')
<div class="flex items-center gap-2">
<x-buttons.catalog-actions route="codificacion" :showFilters="true" />
    <!-- Botón Fijar Columnas -->
    <button type="button" onclick="openPinColumnsModal()"
            class="w-9 h-9 flex items-center justify-center rounded-full bg-yellow-500 text-white hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-400 transition-colors"
            title="Fijar columnas" aria-label="Fijar columnas">
        <i class="fa-solid fa-thumbtack text-sm"></i>
    </button>
    <!-- Botón Ocultar Columnas -->
    <button type="button" onclick="openHideColumnsModal()"
            class="w-9 h-9 flex items-center justify-center rounded-full bg-red-500 text-white hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-400 transition-colors"
            title="Ocultar columnas" aria-label="Ocultar columnas">
        <i class="fa-solid fa-eye-slash text-sm"></i>
    </button>
</div>
@endsection

@section('content')
<div class="container-fluid">
    <div class="bg-white rounded-lg shadow-sm flex flex-col" style="height: calc(100vh);">
        {{-- Loading inicial --}}
        <div
            id="loading-overlay"
            class="absolute inset-0 bg-white/90 flex items-center justify-center z-10"
        >
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-500 border-t-transparent"></div>
        </div>

        {{-- Contenedor de tabla con scroll --}}
        <div
            id="table-container"
            class="relative flex-1 overflow-y-auto overflow-x-auto"
            style="max-height: calc(100vh - 110px);"
        >
            <table id="mainTable" class="w-full min-w-full">
                <thead class="bg-blue-500">
                    <tr>
                        @foreach($columnas as $idx => $col)
                            <th
                                class="column-{{ $idx }} px-3 py-2 text-left text-sm font-medium text-white whitespace-nowrap border-b border-blue-400 bg-blue-500"
                                data-index="{{ $idx }}"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate">{{ $col }}</span>
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            class="sort-btn sort-btn-asc p-1 bg-blue-600 hover:bg-blue-700 text-white rounded transition-shadow hover:shadow-md"
                                            title="Ordenar ascendente"
                                            data-sort="asc"
                                            data-column="{{ $idx }}"
                                        >
                                            <i class="fas fa-arrow-up"></i>
                                        </button>
                                        <button
                                            type="button"
                                            class="sort-btn sort-btn-desc p-1 bg-blue-600 hover:bg-blue-700 text-white rounded transition-shadow hover:shadow-md hidden"
                                            title="Ordenar descendente"
                                            data-sort="desc"
                                            data-column="{{ $idx }}"
                                        >
                                            <i class="fas fa-arrow-down"></i>
                                        </button>
                                    </div>
                                    </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody id="codificacion-body" class="divide-y divide-gray-200">
                    <tr id="loading-row">
                        <td colspan="{{ count($columnas) }}" class="text-center py-20">
                            <div class="inline-block animate-spin rounded-full h-10 w-10 border-4 border-blue-500 border-t-transparent mb-3"></div>
                            <p class="text-gray-600 font-medium">Cargando datos...</p>
                            <p class="text-sm text-gray-400 mt-1">Por favor espera</p>
                            </td>
                        </tr>
                </tbody>
            </table>
        </div>

        {{-- Paginación fija en la parte inferior --}}
        <div
            id="pagination-container"
            class="px-4 border-t border-gray-200 bg-white flex-shrink-0 z-20"
        >
            <div class="flex items-center justify-end flex-wrap gap-1">
                <div class="flex items-center gap-1">
                    <button
                        id="pagination-prev"
                        class="px-3 py-1 border rounded text-sm bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        disabled
                    >
                        <i class="fas fa-chevron-left mr-1"></i> Anterior
                    </button>
                    <span
                        id="pagination-info"
                        class="px-2 p-2 py-0 text-xs text-gray-700 font-medium"
                    >
                        Página <span id="pagination-current">1</span> de <span id="pagination-total-pages">1</span>
                    </span>
                    <span
                        id="pagination-details"
                        class="px-2 py-0 text-xs text-gray-500 font-normal"
                    >
                        Mostrando <span id="pagination-start">0</span> - <span id="pagination-end">0</span> de <span id="pagination-total">0</span>
                    </span>
                    <button
                        id="pagination-next"
                        class="px-3 py-1 border rounded text-sm bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        Siguiente <i class="fas fa-chevron-right ml-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Menú contextual (click derecho) --}}
<div id="context-menu" class="hidden fixed bg-white border border-gray-300 rounded-lg shadow-lg z-[99999] py-1 min-w-[150px]" style="z-index: 99999 !important; opacity: 1 !important; background-color: #ffffff !important;">
    <button
        id="context-menu-edit"
        class="w-full text-left px-4 py-2 text-sm text-blue-700 hover:text-blue-800 bg-white hover:bg-gray-100 flex items-center gap-2 border-b border-gray-200"
    >
        <i class="fas fa-edit text-sm text-blue-600 hover:text-blue-800"></i>
        <span>Editar</span>
    </button>
    <button
        id="context-menu-duplicate"
        class="w-full text-left px-4 py-2 text-sm text-blue-700 hover:text-blue-800 bg-white hover:bg-gray-100 flex items-center gap-2 border-b border-gray-200"
    >
        <i class="fas fa-copy text-sm text-blue-600 hover:text-blue-800"></i>
        <span>Duplicar</span>
    </button>
    <button
        id="context-menu-delete"
        class="w-full text-left px-4 py-2 text-sm text-red-700 hover:text-red-800 bg-white hover:bg-gray-100 flex items-center gap-2"
    >
        <i class="fas fa-trash text-sm text-red-600 hover:text-red-800"></i>
        <span>Eliminar</span>
    </button>
</div>

<style>
    /* ============================================
       CONTENEDOR DE TABLA CON SCROLL VISIBLE
       ============================================ */
    #table-container {
    position: relative;
        overflow-y: auto;
        overflow-x: auto;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        flex: 1;
        min-height: 0;
        contain: layout paint;
        will-change: scroll-position;
        scrollbar-gutter: stable both-edges;
    }

    /* Scrollbar personalizada - Siempre visible y más grande */
    #table-container::-webkit-scrollbar {
        width: 14px;
        height: 14px;
    }

    #table-container::-webkit-scrollbar-track {
        background: #e5e7eb;
        border-radius: 7px;
    }

    #table-container::-webkit-scrollbar-thumb {
        background: #6b7280;
        border-radius: 7px;
        border: 2px solid #e5e7eb;
    }

    #table-container::-webkit-scrollbar-thumb:hover {
        background: #4b5563;
    }

    /* Scrollbar horizontal - Más visible */
    #table-container::-webkit-scrollbar:horizontal {
        height: 14px;
    }

    /* Para Firefox */
    #table-container {
        scrollbar-width: auto;
        scrollbar-color: #6b7280 #e5e7eb;
    }

    /* ============================================
       ESTILOS DE TABLA
       ============================================ */
#mainTable {
        position: relative;
    width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        min-width: max-content;
        table-layout: auto;
        transform: translateZ(0);
    }

    /* ============================================
       ENCABEZADO STICKY - FIJO ARRIBA
       ============================================ */
    #mainTable thead {
        position: -webkit-sticky !important;
        position: sticky !important;
        top: 0 !important;
        left: 0 !important;
        z-index: 1000 !important;
        background-color: #3b82f6 !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    #mainTable thead tr {
        background-color: #3b82f6 !important;
        position: relative;
}

#mainTable thead th {
        position: -webkit-sticky !important;
        position: sticky !important;
        top: 0 !important;
        z-index: 1001 !important;
        background-color: #3b82f6 !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        border-bottom: 2px solid #2563eb !important;
        border-right: 1px solid rgba(255, 255, 255, 0.2);
        white-space: nowrap;
    }

    #mainTable thead th:last-child {
        border-right: none;
    }

    /* ============================================
       CUERPO DE TABLA
       ============================================ */
    #mainTable tbody tr {
        position: relative;
        z-index: 0;
    }

    #mainTable tbody tr.data-row {
        height: 36px;
    }

    #mainTable tbody tr.spacer-row,
    #mainTable tbody tr.spacer-row td {
        border: 0 !important;
        padding: 0 !important;
        height: 0;
        pointer-events: none;
    }

    #mainTable tbody td {
        border-right: 1px solid rgba(0, 0, 0, 0.05);
        white-space: nowrap;
        z-index: 0;
        position: relative;
        /* ⚡ OPTIMIZACIÓN: Mejorar rendimiento de renderizado - Solo contain, will-change se aplica dinámicamente */
        contain: layout style paint;
    }

    /* ⚡ OPTIMIZACIÓN: Mejorar rendimiento de scroll - Desactivar transiciones durante scroll */
    .scrolling #codificacion-body tr {
        transition: none !important;
    }
    .scrolling #mainTable thead,
    .scrolling #mainTable thead tr,
    .scrolling #mainTable thead th {
        box-shadow: none !important;
    }
    .scrolling #mainTable tbody td,
    .scrolling #mainTable tbody tr {
        transition: none !important;
    }
    .scrolling .pinned-column {
        box-shadow: none !important;
    }

    /* ⚡ OPTIMIZACIÓN: Mejorar rendimiento de scroll - Solo contain, sin will-change constante */
    #mainTable tbody tr {
        contain: layout style paint;
    }

    /* ⚡ OPTIMIZACIÓN: Desactivar will-change después de renderizar para mejor rendimiento */
    #mainTable tbody tr.rendered {
        will-change: auto;
    }

    /* ============================================
       COLUMNAS FIJADAS
       ============================================ */
.pinned-column {
        background-color: #fffbeb !important;
    }

    #mainTable thead th.pinned-column {
        background-color: #1b0bf5 !important;
    color: #fff !important;
        position: -webkit-sticky !important;
        position: sticky !important;
        top: 0 !important;
        z-index: 1020 !important;
        box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2) !important;
    }

    #mainTable tbody td.pinned-column {
        background-color: #fffbeb !important;
        position: sticky !important;
        z-index: 100 !important;
    }

    /* ============================================
       CONTENEDOR PRINCIPAL
       ============================================ */
    .container-fluid {
        position: relative;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .bg-white.rounded-lg.shadow-sm {
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 0;
    }

    /* ============================================
       FOOTER DE PAGINACIÓN - MÁS DELGADO
       ============================================ */
    #pagination-container {
        padding-top: 0.375rem !important;
        padding-bottom: 0.375rem !important;
        min-height: auto !important;
        max-height: 44px !important;
    }

    #pagination-container button {
        padding-top: 0.25rem !important;
        padding-bottom: 0.25rem !important;
        font-size: 0.875rem !important;
        line-height: 1.3 !important;
    }

    #pagination-container span {
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        line-height: 1.2 !important;
    }

    #pagination-container .flex {
        gap: 0.5rem !important;
    }

    /* ============================================
       LOADING OVERLAY
       ============================================ */
    #table-loading-overlay {
        pointer-events: none;
    }

    /* ============================================
       MEJORAS VISUALES PARA TABLA
       ============================================ */
    #mainTable tbody td {
    white-space: nowrap;
}

    /* Indicador visual cuando hay scroll horizontal */
    #table-container::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(to right, transparent, #d1d5db 50%, transparent);
        pointer-events: none;
        z-index: 999;
    }

    /* ============================================
       MENÚ CONTEXTUAL (CLICK DERECHO)
       ============================================ */
    #context-menu {
        display: none;
        background-color: #ffffff !important;
        z-index: 99999 !important;
        opacity: 1 !important;
        position: fixed !important;
    }

    #context-menu:not(.hidden) {
        display: block;
        background-color: #ffffff !important;
        z-index: 99999 !important;
        opacity: 1 !important;
    }

    #context-menu button {
        transition: background-color 0.15s ease;
        background-color: #ffffff !important;
        opacity: 1 !important;
    }

    #context-menu button:hover {
        background-color: #f3f4f6 !important;
    }

    #context-menu button:active {
        background-color: #e5e7eb !important;
    }
</style>


{{-- JS principal --}}
<script>
{{-- Modal Duplicar/Importar Codificación - Debe estar FUERA del IIFE para estar disponible globalmente --}}
@include('catalagos.modal._duplicar-importar-codificacion')

(() => {
    // ============================================
    //  CONFIGURACIÓN / ESTADO
    // ============================================
    const CONFIG = {
        apiUrl: @json($apiUrl),
        columnas: @json($columnasConfig),
        camposModelo: @json(array_keys($camposModelo)),
        tiposCampo: @json($camposModelo),
        totalEstimado: {{ $totalRegistros }}
    };

const state = {
        rawData: [],
        filteredData: [],
    currentPage: 1,
    itemsPerPage: 1000,
        currentSort: { col: null, dir: null },
        selectedId: null,
        filtrosDinamicos: [],
        isLoading: true
    };

    const VIRTUAL = {
        enabled: true,
        threshold: 240,
        overscan: 12,
        rowHeight: 36
    };

    const virtualState = {
        active: false,
        pageData: [],
        start: 0,
        end: 0,
        rowHeight: VIRTUAL.rowHeight,
        topSpacer: null,
        bottomSpacer: null,
        topSpacerCell: null,
        bottomSpacerCell: null,
        columnClasses: null
    };

    let virtualRaf = null;

    // Estado de columnas ocultas y fijadas
    let hiddenColumns = [];
    let pinnedColumns = [];

    // ============================================
    //  HELPERS DOM / DATA
    // ============================================
    const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

const utils = {
        // ⚡ OPTIMIZACIÓN: Cache de fechas formateadas
        _dateCache: new Map(),

        formatDate(value) {
            if (!value) return '';

            // ⚡ OPTIMIZACIÓN: Cachear fechas formateadas
            const cached = this._dateCache.get(value);
            if (cached !== undefined) return cached;

            const d = new Date(value);
            if (Number.isNaN(d.getTime())) {
                this._dateCache.set(value, value);
                return value;
            }

            const day   = String(d.getDate()).padStart(2, '0');
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const year  = d.getFullYear();
            const formatted = `${day}/${month}/${year}`;

            // Limitar tamaño del cache
            if (this._dateCache.size > 1000) {
                const firstKey = this._dateCache.keys().next().value;
                this._dateCache.delete(firstKey);
            }

            this._dateCache.set(value, formatted);
            return formatted;
        },

        formatValue(value, type) {
            if (value === null || value === undefined) return '';
            if (type === 'date') return this.formatDate(value);
            if (type === 'zero') return value == 0 ? '' : String(value);
            return String(value);
        },

        parseForSort(value, type) {
            if (value === null || value === undefined || value === '') {
                return type === 'number' ? -Infinity : '';
            }

            if (type === 'date') {
                const d = new Date(value);
                return Number.isNaN(d.getTime()) ? 0 : d.getTime();
            }

            if (type === 'number') {
                const num = parseFloat(String(value).replace(/,/g, ''));
                return Number.isNaN(num) ? 0 : num;
            }

            return String(value).toLowerCase();
        },

        detectType(values) {
            let nums = 0;
            let dates = 0;
            let total = 0;

            for (const value of values.slice(0, 100)) {
                if (!value) continue;
                total++;

                const str = String(value);
                if (!Number.isNaN(new Date(value).getTime()) && str.includes('-')) {
                    dates++;
                } else if (!Number.isNaN(parseFloat(str.replace(/,/g, '')))) {
                    nums++;
                }
            }

            if (dates / Math.max(total, 1) > 0.5) return 'date';
            if (nums / Math.max(total, 1) > 0.5) return 'number';
            return 'text';
        }
    };

    // ============================================
    //  TOAST NOTIFICATIONS
    // ============================================
function showToast(message, type = 'info') {
    let toast = $('#toast-notification');

    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast-notification';
            toast.className = 'fixed top-4 right-4 z-[9999] max-w-sm w-full';
        document.body.appendChild(toast);
    }

        const colors = {
            success: 'bg-green-600',
            error:   'bg-red-600',
            warning: 'bg-yellow-600',
            info:    'bg-blue-600'
        };

    toast.innerHTML = `
            <div class="${colors[type] || colors.info} text-white px-4 py-3 rounded-lg shadow-lg">
            <div class="flex items-center justify-between gap-4">
                    <span class="text-sm">${message}</span>
                    <button
                        type="button"
                        class="opacity-80 hover:opacity-100"
                        onclick="this.closest('#toast-notification')?.remove()"
                    >
                        &times;
                </button>
            </div>
            </div>
        `;

    setTimeout(() => toast?.remove(), 3500);
}

    // ============================================
    //  LOADING TABLA
    // ============================================
    function showTableLoading(show) {
        let overlay = $('#table-loading-overlay');
        const container = $('#table-container');

        if (!container) return;

        if (show) {
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'table-loading-overlay';
                overlay.className = 'absolute inset-0 bg-white/80 flex items-center justify-center z-30';
                overlay.innerHTML = `
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-500 border-t-transparent"></div>
                `;
                container.appendChild(overlay);
            }
            overlay.style.display = 'flex';
            overlay.classList.remove('hidden');
        } else {
            if (overlay) {
                overlay.style.display = 'none';
                overlay.classList.add('hidden');
                setTimeout(() => {
                    if (overlay && overlay.parentNode) {
                        overlay.remove();
                    }
                }, 50);
            }
        }
    }

    // =====================f=======================
    //  CARGA DE DATOS
    // ============================================
    function buildApiUrl(baseUrl, force) {
        if (!force) return baseUrl;
        try {
            const url = new URL(baseUrl, window.location.origin);
            url.searchParams.set('nocache', '1');
            url.searchParams.set('ts', Date.now().toString());
            return url.toString();
        } catch (error) {
            const joiner = baseUrl.includes('?') ? '&' : '?';
            return `${baseUrl}${joiner}nocache=1&ts=${Date.now()}`;
        }
    }

    async function loadData(options = {}) {
        const force = options.force === true;
        const loadingEl  = $('#loading-overlay');

        if (!CONFIG.apiUrl) {
            loadingEl?.classList.add('hidden');
            showToast('URL de API no configurada', 'error');
            return;
        }

        if (loadingEl) {
            loadingEl.classList.remove('hidden');
        }

        try {
            // ⚡ OPTIMIZACIÓN: Usar AbortController para cancelar si es necesario
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // Timeout de 30 segundos
            const apiUrl = buildApiUrl(CONFIG.apiUrl, force);

            const response = await fetch(apiUrl, {
                method: 'GET',
                headers: { Accept: 'application/json' },
                cache: force ? 'no-store' : 'default',
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();
            if (!result.s) {
                throw new Error(result.e || 'Error desconocido');
            }

            const columns = result.c;

            // ⚡ OPTIMIZACIÓN: Crear objetos directamente sin múltiples iteraciones
            // Usar Array pre-allocado para mejor rendimiento
            const dataLength = result.d.length;
            state.rawData = new Array(dataLength);

            for (let i = 0; i < dataLength; i++) {
                const row = result.d[i];
                const obj = {};
                const len = columns.length;
                for (let j = 0; j < len; j++) {
                    obj[columns[j]] = row[j];
                }
                if (obj.Id !== undefined && obj.Id !== null && obj.Id !== '') {
                    const parsedId = Number(obj.Id);
                    if (!Number.isNaN(parsedId)) {
                        obj.Id = parsedId;
                    }
                }
                state.rawData[i] = obj;
            }

            state.filteredData = [...state.rawData];

            // ⚡ OPTIMIZACIÓN: Renderizar usando requestAnimationFrame para mejor rendimiento
            requestAnimationFrame(() => {
                renderPage();
                if (loadingEl) loadingEl.classList.add('hidden');
                state.isLoading = false;
            });
        } catch (error) {
            if (!loadingEl) return;

            // Si fue cancelado, mostrar mensaje específico
            if (error.name === 'AbortError') {
                loadingEl.innerHTML = `
                    <div class="text-center">
                        <i class="fas fa-clock text-yellow-500 text-4xl mb-4"></i>
                        <p class="text-yellow-600 font-medium">Tiempo de espera agotado</p>
                        <p class="text-sm text-gray-500 mt-2">La carga está tomando más tiempo del esperado</p>
                        <button
                            type="button"
                            class="mt-4 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                            onclick="location.reload()"
                        >
                            Reintentar
                        </button>
                    </div>
                `;
                return;
            }

            loadingEl.innerHTML = `
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                    <p class="text-red-600 font-medium">Error al cargar datos</p>
                    <p class="text-sm text-gray-500 mt-2">${error.message}</p>
                    <button
                        type="button"
                        class="mt-4 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                        onclick="location.reload()"
                    >
                        Reintentar
                </button>
                </div>
            `;
        }
    }

    // ============================================
    //  RENDER / PAGINACIÓN
    // ============================================
    function updatePagination() {
        const total      = state.filteredData.length;
        const totalPages = Math.ceil(total / state.itemsPerPage) || 1;
        const start      = total ? (state.currentPage - 1) * state.itemsPerPage + 1 : 0;
        const end        = Math.min(state.currentPage * state.itemsPerPage, total);

        // Actualizar información de página
        const currentPageEl = $('#pagination-current');
        const totalPagesEl  = $('#pagination-total-pages');

        if (currentPageEl) currentPageEl.textContent = state.currentPage;
        if (totalPagesEl)  totalPagesEl.textContent  = totalPages;

        // Actualizar detalles de registros
        const startEl  = $('#pagination-start');
        const endEl    = $('#pagination-end');
        const totalEl  = $('#pagination-total');

        if (startEl) startEl.textContent = start.toLocaleString();
        if (endEl)   endEl.textContent   = end.toLocaleString();
        if (totalEl) totalEl.textContent = total.toLocaleString();

        // Actualizar botones
        const prevBtn = $('#pagination-prev');
        const nextBtn = $('#pagination-next');

        if (prevBtn) prevBtn.disabled = state.currentPage <= 1;
        if (nextBtn) nextBtn.disabled = state.currentPage >= totalPages;
    }

    // ⚡ OPTIMIZACIÓN: Cache de valores formateados para evitar recalcular
    const formatCache = new Map();
    const CACHE_SIZE_LIMIT = 10000; // Limitar tamaño del cache

    function clearFormatCache() {
        if (formatCache.size > CACHE_SIZE_LIMIT) {
            formatCache.clear();
        }
    }

    function resetVirtualState() {
        virtualState.active = false;
        virtualState.pageData = [];
        virtualState.start = 0;
        virtualState.end = 0;
        virtualState.topSpacer = null;
        virtualState.bottomSpacer = null;
        virtualState.topSpacerCell = null;
        virtualState.bottomSpacerCell = null;
        virtualState.columnClasses = null;
    }

    function buildColumnClasses(camposLen) {
        const baseCellClass = 'px-3 py-2 text-sm whitespace-nowrap text-gray-700';
        const columnClasses = new Array(camposLen);
        for (let idx = 0; idx < camposLen; idx++) {
            columnClasses[idx] = `column-${idx} ${baseCellClass}`;
        }
        return columnClasses;
    }

    function getPinnedOffsets() {
        if (!pinnedColumns.length) return null;
        let left = 0;
        const offsets = {};
        pinnedColumns.forEach((idx, order) => {
            const th = $(`th.column-${idx}`);
            if (!th || th.style.display === 'none') return;
            const width = th.offsetWidth || th.getBoundingClientRect().width || 0;
            offsets[idx] = { left, order };
            left += width;
        });
        return offsets;
    }

    function scheduleVirtualUpdate(force = false) {
        if (!virtualState.active) return;
        if (virtualRaf) return;
        virtualRaf = requestAnimationFrame(() => {
            virtualRaf = null;
            updateVirtualRows(force);
        });
    }

    function updateVirtualRows(force = false) {
        if (!virtualState.active) return;

        const tbody = $('#codificacion-body');
        const container = $('#table-container');
        if (!tbody || !container) return;

        const total = virtualState.pageData.length;
        if (!total) return;

        const rowHeight = virtualState.rowHeight || VIRTUAL.rowHeight;
        const viewportHeight = Math.max(container.clientHeight, rowHeight);
        const scrollTop = container.scrollTop || 0;

        const visibleCount = Math.ceil(viewportHeight / rowHeight);
        let start = Math.floor(scrollTop / rowHeight) - VIRTUAL.overscan;
        if (start < 0) start = 0;
        let end = Math.min(total, start + visibleCount + VIRTUAL.overscan * 2);

        if (!force && start === virtualState.start && end === virtualState.end) return;

        virtualState.start = start;
        virtualState.end = end;

        const topHeight = start * rowHeight;
        const bottomHeight = Math.max(0, (total - end) * rowHeight);

        if (virtualState.topSpacerCell) {
            virtualState.topSpacerCell.style.height = `${topHeight}px`;
        }
        if (virtualState.bottomSpacerCell) {
            virtualState.bottomSpacerCell.style.height = `${bottomHeight}px`;
        }

        let node = virtualState.topSpacer?.nextSibling;
        while (node && node !== virtualState.bottomSpacer) {
            const next = node.nextSibling;
            node.remove();
            node = next;
        }

        const fragment = document.createDocumentFragment();
        const camposModelo = CONFIG.camposModelo;
        const tiposCampo = CONFIG.tiposCampo;
        const camposLen = camposModelo.length;
        const columnClasses = virtualState.columnClasses || (virtualState.columnClasses = buildColumnClasses(camposLen));
        const pinnedOffsets = getPinnedOffsets();

        previousSelectedRow = null;

        for (let i = start; i < end; i++) {
            const row = virtualState.pageData[i];
            const tr = document.createElement('tr');
            const rowId = row.Id;
            const isSelected = rowId === state.selectedId;

            tr.dataset.id = rowId;
            tr.className = isSelected
                ? 'data-row cursor-pointer transition-colors bg-blue-500 text-white selected-row rendered'
                : 'data-row cursor-pointer transition-colors hover:bg-gray-50 rendered';

            if (isSelected) {
                previousSelectedRow = tr;
            }

            for (let idx = 0; idx < camposLen; idx++) {
                const campo = camposModelo[idx];
                const td = document.createElement('td');
                td.className = columnClasses[idx];

                if (hiddenColumns.includes(idx)) {
                    td.style.display = 'none';
                }

                if (pinnedOffsets && pinnedOffsets[idx]) {
                    const meta = pinnedOffsets[idx];
                    td.style.position = 'sticky';
                    td.style.left = `${meta.left}px`;
                    td.style.zIndex = String(100 + meta.order);
                    td.classList.add('pinned-column');
                }

                const cacheKey = `${rowId}_${campo}_${row[campo]}`;
                let formattedValue = formatCache.get(cacheKey);
                if (formattedValue === undefined) {
                    formattedValue = utils.formatValue(row[campo], tiposCampo[campo]);
                    formatCache.set(cacheKey, formattedValue);
                }
                td.textContent = formattedValue;
                tr.appendChild(td);
            }

            fragment.appendChild(tr);
        }

        if (virtualState.bottomSpacer?.parentNode) {
            virtualState.bottomSpacer.parentNode.insertBefore(fragment, virtualState.bottomSpacer);
        }
    }

    function renderPageVirtual(pageData, camposLen, totalCols) {
        const tbody = $('#codificacion-body');
        if (!tbody) return;

        virtualState.active = true;
        virtualState.rowHeight = VIRTUAL.rowHeight;
        virtualState.pageData = pageData;
        virtualState.start = 0;
        virtualState.end = 0;
        virtualState.columnClasses = buildColumnClasses(camposLen);

        previousSelectedRow = null;

        tbody.innerHTML = '';

        const topRow = document.createElement('tr');
        const topCell = document.createElement('td');
        topRow.className = 'spacer-row';
        topCell.colSpan = totalCols;
        topCell.style.height = '0px';
        topRow.appendChild(topCell);

        const bottomRow = document.createElement('tr');
        const bottomCell = document.createElement('td');
        bottomRow.className = 'spacer-row';
        bottomCell.colSpan = totalCols;
        bottomCell.style.height = '0px';
        bottomRow.appendChild(bottomCell);

        tbody.appendChild(topRow);
        tbody.appendChild(bottomRow);

        virtualState.topSpacer = topRow;
        virtualState.bottomSpacer = bottomRow;
        virtualState.topSpacerCell = topCell;
        virtualState.bottomSpacerCell = bottomCell;

        updateVirtualRows(true);
    }

    // ⚡ OPTIMIZACIÓN: Delegación de eventos en lugar de listeners por fila
    let eventDelegationSetup = false;
    let isScrolling = false;
    let scrollTimeout = null;
    let scrollRaf = null;

    function setupEventDelegation() {
        if (eventDelegationSetup) return;
        const tbody = $('#codificacion-body');
        const container = $('#table-container');
        if (!tbody) return;

        // ⚡ OPTIMIZACIÓN: Detectar scroll y desactivar transiciones
        if (container) {
            container.addEventListener('scroll', () => {
                if (scrollRaf) return;
                scrollRaf = requestAnimationFrame(() => {
                    scrollRaf = null;
                if (!isScrolling) {
                    isScrolling = true;
                    document.body.classList.add('scrolling');
                    container.classList.add('scrolling');
                }

                if (virtualState.active) {
                    scheduleVirtualUpdate();
                }

                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    isScrolling = false;
                    document.body.classList.remove('scrolling');
                    container.classList.remove('scrolling');
                    }, 120);
                });
            }, { passive: true });
        }

        // Click en fila - Usar passive para mejor rendimiento
        tbody.addEventListener('click', (e) => {
            const tr = e.target.closest('tr[data-id]');
            if (tr) {
                const id = parseInt(tr.dataset.id);
                if (!isNaN(id)) selectRow(tr, id);
            }
        }, { passive: true });

        // Click derecho en fila
        tbody.addEventListener('contextmenu', (e) => {
            const tr = e.target.closest('tr[data-id]');
            if (tr) {
                e.preventDefault();
                const id = parseInt(tr.dataset.id);
                if (!isNaN(id)) showContextMenu(e, id);
            }
        }, { passive: false });

        eventDelegationSetup = true;
    }

    // ⚡ OPTIMIZACIÓN: Renderizado por chunks para mejor rendimiento (solo para tablas muy grandes)
    function renderPageChunked(pageData, camposModelo, tiposCampo, camposLen, tbody, startIdx = 0, chunkSize = 30) {
        const endIdx = Math.min(startIdx + chunkSize, pageData.length);
        const fragment = document.createDocumentFragment();

        // ⚡ OPTIMIZACIÓN: Pre-calcular clases base una sola vez
        const baseClass = 'data-row cursor-pointer transition-colors';
        const selectedClassBase = 'bg-blue-500 text-white selected-row';
        const hoverClassBase = 'hover:bg-gray-50';
        const baseCellClass = 'px-3 py-2 text-sm whitespace-nowrap text-gray-700';

        // ⚡ OPTIMIZACIÓN: Pre-crear array de clases por columna
        const columnClasses = [];
        for (let idx = 0; idx < camposLen; idx++) {
            columnClasses[idx] = `column-${idx} ${baseCellClass}`;
        }

        for (let i = startIdx; i < endIdx; i++) {
            const row = pageData[i];
            const tr = document.createElement('tr');
            const isSelected = row.Id === state.selectedId;
            const rowId = row.Id;

            tr.dataset.id = rowId;

            //  OPTIMIZACIÓN: Usar clases pre-calculadas y marcar como renderizado
            tr.className = isSelected
                ? `${baseClass} ${selectedClassBase} rendered`
                : `${baseClass} ${hoverClassBase} rendered`;

            //  OPTIMIZACIÓN: Renderizar celdas con clases pre-calculadas
            for (let idx = 0; idx < camposLen; idx++) {
                const campo = camposModelo[idx];
                const td = document.createElement('td');
                td.className = columnClasses[idx];

                //  OPTIMIZACIÓN: Cachear valores formateados
                const cacheKey = `${rowId}_${campo}_${row[campo]}`;
                let formattedValue = formatCache.get(cacheKey);

                if (formattedValue === undefined) {
                    formattedValue = utils.formatValue(row[campo], tiposCampo[campo]);
                    formatCache.set(cacheKey, formattedValue);
                }

                td.textContent = formattedValue;
                tr.appendChild(td);
            }

            fragment.appendChild(tr);
        }

        // Agregar chunk al DOM
        tbody.appendChild(fragment);

        // Si quedan más filas, continuar en el siguiente frame
        if (endIdx < pageData.length) {
            //  OPTIMIZACIÓN: Usar requestAnimationFrame directamente (más rápido que idleCallback)
            requestAnimationFrame(() => {
                renderPageChunked(pageData, camposModelo, tiposCampo, camposLen, tbody, endIdx, chunkSize);
            });
        } else {
            // Terminó el renderizado, aplicar visibilidad y actualizar paginación
            requestAnimationFrame(() => {
                updatePagination();
                applyColumnVisibility();
            });
        }
    }

    function renderPage() {
        const tbody     = $('#codificacion-body');
        const totalCols = CONFIG.columnas.length;

        if (!tbody) return;

        // Sin datos
        if (!state.filteredData.length) {
            resetVirtualState();
            tbody.innerHTML = `
                <tr>
                    <td colspan="${totalCols}" class="text-center py-16">
                        <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                        <p class="text-gray-500 font-medium">
                            ${state.filtrosDinamicos.length
                                ? 'No se encontraron resultados'
                                : 'No hay datos disponibles'}
                        </p>
                        ${
                            state.filtrosDinamicos.length
                                ? '<p class="text-sm text-gray-400 mt-2">Intenta con otros filtros</p>'
                                : ''
                        }
                    </td>
                </tr>
            `;
            updatePagination();
            return;
        }

        const start = (state.currentPage - 1) * state.itemsPerPage;
        const pageData = state.filteredData.slice(start, start + state.itemsPerPage);
        const useVirtual = VIRTUAL.enabled && pageData.length >= VIRTUAL.threshold;

        const showLoading = pageData.length > 200;
        if (showLoading) showTableLoading(true);

        try {
            //  OPTIMIZACIÓN: Limpiar cache periódicamente
            clearFormatCache();

            //  OPTIMIZACIÓN: Configurar delegación de eventos una sola vez
            setupEventDelegation();

            const camposModelo = CONFIG.camposModelo;
            const tiposCampo = CONFIG.tiposCampo;
            const camposLen = camposModelo.length;

            if (useVirtual) {
                renderPageVirtual(pageData, camposLen, totalCols);
                updatePagination();
                applyColumnVisibility();
                if (showLoading) {
                    setTimeout(() => showTableLoading(false), 30);
                }
                return;
            }

            resetVirtualState();

            const fragment = document.createDocumentFragment();

            //  OPTIMIZACIÓN: Para tablas grandes, renderizar por chunks más pequeños
            if (pageData.length > 200) {
                // Limpiar tbody primero
                tbody.innerHTML = '';
                previousSelectedRow = null; // Reset selección al renderizar nueva página
                //  OPTIMIZACIÓN: Chunks más pequeños (30 filas) para mejor responsividad durante scroll
                renderPageChunked(pageData, camposModelo, tiposCampo, camposLen, tbody, 0, 30);
                if (showLoading) {
                    setTimeout(() => showTableLoading(false), 30);
                }
            } else {
                //  OPTIMIZACIÓN: Para tablas pequeñas, renderizar todo de una vez con optimizaciones
                // Pre-calcular clases base para evitar concatenaciones repetidas
                const baseClass = 'data-row cursor-pointer transition-colors';
                const baseCellClass = 'px-3 py-2 text-sm whitespace-nowrap text-gray-700';
                const selectedClassBase = 'bg-blue-500 text-white selected-row';
                const hoverClassBase = 'hover:bg-gray-50';

                //  OPTIMIZACIÓN: Pre-crear template de clases por columna
                const columnClasses = [];
                for (let idx = 0; idx < camposLen; idx++) {
                    columnClasses[idx] = `column-${idx} ${baseCellClass}`;
                }

                for (let i = 0; i < pageData.length; i++) {
                    const row = pageData[i];
                    const tr = document.createElement('tr');
                    const isSelected = row.Id === state.selectedId;
                    const rowId = row.Id;

                    tr.dataset.id = rowId;
                    //  OPTIMIZACIÓN: Usar clases pre-calculadas y marcar como renderizado
                    tr.className = isSelected
                        ? `${baseClass} ${selectedClassBase} rendered`
                        : `${baseClass} ${hoverClassBase} rendered`;

                    //  OPTIMIZACIÓN: Renderizar celdas con clases pre-calculadas
                    for (let idx = 0; idx < camposLen; idx++) {
                        const campo = camposModelo[idx];
                        const td = document.createElement('td');
                        td.className = columnClasses[idx];

                        //  OPTIMIZACIÓN: Cachear valores formateados
                        const cacheKey = `${rowId}_${campo}_${row[campo]}`;
                        let formattedValue = formatCache.get(cacheKey);
                        if (formattedValue === undefined) {
                            formattedValue = utils.formatValue(row[campo], tiposCampo[campo]);
                            formatCache.set(cacheKey, formattedValue);
                        }
                        td.textContent = formattedValue;
                        tr.appendChild(td);
                    }

                    fragment.appendChild(tr);
                }

            //  OPTIMIZACIÓN: Renderizar directamente sin requestAnimationFrame para tablas pequeñas (más rápido)
                previousSelectedRow = null; // Reset selección al renderizar nueva página
                tbody.innerHTML = '';
                tbody.appendChild(fragment);
                updatePagination();
                applyColumnVisibility();
                if (showLoading) showTableLoading(false);
            }
        } catch (error) {
            if (showLoading) showTableLoading(false);
            console.error('Error renderizando página:', error);
        }
    }

    //  OPTIMIZACIÓN: Función separada para aplicar visibilidad de columnas
    function applyColumnVisibility() {
        // Aplicar columnas ocultas después de renderizar
        for (let i = 0; i < hiddenColumns.length; i++) {
            hideColumn(hiddenColumns[i], true);
        }

        // Actualizar posiciones de columnas fijadas
        requestAnimationFrame(() => {
            updatePinnedColumnsPositions();
        });
    }

    function goToPage(page) {
        const totalPages = Math.ceil(state.filteredData.length / state.itemsPerPage) || 1;
        if (page < 1 || page > totalPages) return;
        state.currentPage = page;
        renderPage();
        const container = $('#table-container');
        if (container) container.scrollTop = 0;
    }

    // ============================================
    //  ORDENAMIENTO
    // ============================================
    function sortColumn(colIndex, dir) {
        if (!CONFIG.camposModelo[colIndex]) return;

        const needsLoading = state.filteredData.length > 500;
        if (needsLoading) showTableLoading(true);

        // Reset visual de botones
        $$('#mainTable thead th .sort-btn').forEach(btn => {
            const isDesc = btn.classList.contains('sort-btn-desc');
            btn.classList.toggle('hidden', isDesc);
        });

        const th = $(`#mainTable thead th[data-index="${colIndex}"]`);
        if (th) {
            const ascBtn  = th.querySelector('.sort-btn-asc');
            const descBtn = th.querySelector('.sort-btn-desc');

            if (dir === 'asc') {
                ascBtn?.classList.add('hidden');
                descBtn?.classList.remove('hidden');
            } else {
                descBtn?.classList.add('hidden');
                ascBtn?.classList.remove('hidden');
            }
        }

        try {
            const campo = CONFIG.camposModelo[colIndex];
            const tipo  = CONFIG.tiposCampo[campo];

            //  OPTIMIZACIÓN: Detectar tipo solo una vez y cachear
            let sortType;
            if (tipo === 'date') {
                sortType = 'date';
            } else if (tipo === 'zero') {
                sortType = 'number';
            } else {
                // Solo detectar tipo en una muestra pequeña para mejor rendimiento
                const sample = state.filteredData.length > 1000
                    ? state.filteredData.slice(0, 100).map(r => r[campo])
                    : state.filteredData.map(r => r[campo]);
                sortType = utils.detectType(sample);
            }

            //  OPTIMIZACIÓN: Ordenamiento optimizado con comparación directa
            const multiplier = dir === 'asc' ? 1 : -1;
            state.filteredData.sort((a, b) => {
                const va = utils.parseForSort(a[campo], sortType);
                const vb = utils.parseForSort(b[campo], sortType);

                if (va < vb) return -1 * multiplier;
                if (va > vb) return 1 * multiplier;
                return 0;
            });

            state.currentSort = { col: colIndex, dir };
    state.currentPage = 1;
            renderPage();
        } catch (error) {
            // Error silencioso
        } finally {
            if (needsLoading) {
                showTableLoading(false);
            }
        }
    }

    // ============================================
    //  SELECCIÓN DE FILA
    // ============================================
    //  OPTIMIZACIÓN: Cache de fila seleccionada anterior para actualización rápida
    let previousSelectedRow = null;

    function selectRow(row, id) {
        //  OPTIMIZACIÓN: Solo actualizar si cambió la selección
        if (state.selectedId === id && previousSelectedRow === row) {
            return; // Ya está seleccionada
        }

        //  OPTIMIZACIÓN: Actualizar solo la fila anterior (sin buscar todas con querySelector)
        if (previousSelectedRow && previousSelectedRow !== row) {
            previousSelectedRow.classList.remove('bg-blue-500', 'text-white', 'selected-row');
            previousSelectedRow.classList.add('hover:bg-gray-50');
            //  OPTIMIZACIÓN: Actualizar celdas de la fila anterior
            const prevCells = previousSelectedRow.querySelectorAll('td');
            prevCells.forEach(td => {
                td.classList.remove('text-white');
                td.classList.add('text-gray-700');
            });
        }

        //  OPTIMIZACIÓN: Actualizar nueva fila seleccionada
        row.classList.remove('hover:bg-gray-50');
        row.classList.add('bg-blue-500', 'text-white', 'selected-row');
        //  OPTIMIZACIÓN: Actualizar celdas de la nueva fila
        const cells = row.querySelectorAll('td');
        cells.forEach(td => {
            td.classList.remove('text-gray-700');
            td.classList.add('text-white');
        });

        previousSelectedRow = row;
        state.selectedId = id;

        const editBtn   = $('#btn-editar');
    const deleteBtn = $('#btn-eliminar');
        if (editBtn)   editBtn.disabled   = false;
        if (deleteBtn) deleteBtn.disabled = false;
}

    // ============================================
    //  MENÚ CONTEXTUAL (CLICK DERECHO)
    // ============================================
    function showContextMenu(event, rowId) {
        const contextMenu = $('#context-menu');
        if (!contextMenu) return;

        // Seleccionar la fila si no está seleccionada
        if (state.selectedId !== rowId) {
            const row = $(`tr[data-id="${rowId}"]`);
            if (row) selectRow(row, rowId);
        }

        // Posicionar el menú
        let x = event.clientX;
        let y = event.clientY;

        // Asegurar que el menú no se salga de la pantalla
        const menuWidth = 150;
        const menuHeight = 150; // Aumentado para 3 botones
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;

        if (x + menuWidth > windowWidth) {
            x = windowWidth - menuWidth - 10;
        }
        if (y + menuHeight > windowHeight) {
            y = windowHeight - menuHeight - 10;
        }

        contextMenu.style.left = `${x}px`;
        contextMenu.style.top = `${y}px`;
        contextMenu.classList.remove('hidden');

        // Cerrar el menú al hacer click fuera, scroll, o en otra parte
        const closeMenu = (e) => {
            if (!contextMenu.contains(e.target)) {
                contextMenu.classList.add('hidden');
                document.removeEventListener('click', closeMenu);
                document.removeEventListener('contextmenu', closeMenu);
                window.removeEventListener('scroll', closeMenu, true);
            }
        };

        setTimeout(() => {
            document.addEventListener('click', closeMenu);
            document.addEventListener('contextmenu', closeMenu);
            window.addEventListener('scroll', closeMenu, true);
        }, 10);
    }

    function duplicarCodificacion() {
        if (!state.selectedId) {
            showToast('Selecciona un registro', 'warning');
            return;
        }

        // Abrir modal de duplicar/importar codificación
        // Verificar si SweetAlert2 está disponible
        if (typeof Swal === 'undefined') {
            console.error('SweetAlert2 no está disponible');
            showToast('Error: SweetAlert2 no está cargado', 'error');
            return;
        }

        if (typeof window.abrirModalDuplicarImportarCodificacion === 'function') {
            window.abrirModalDuplicarImportarCodificacion(state.selectedId);
        } else {
            console.error('abrirModalDuplicarImportarCodificacion no está disponible. window:', Object.keys(window).filter(k => k.includes('Modal')));
            showToast('Error: Modal no disponible. Revisa la consola para más detalles.', 'error');
        }
    }

    // ============================================
    //  CRUD (REDIRECCIONES)
    // ============================================
function agregarCodificacion() {
    window.location.href = '/planeacion/catalogos/codificacion-modelos/create';
}

function editarCodificacion() {
        if (!state.selectedId) {
            showToast('Selecciona un registro', 'warning');
            return;
        }

    window.location.href = `/planeacion/catalogos/codificacion-modelos/${state.selectedId}/edit`;
}

function eliminarCodificacion() {
        if (!state.selectedId) {
            showToast('Selecciona un registro', 'warning');
            return;
        }

    Swal.fire({
            title: '¿Eliminar registro?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (!result.isConfirmed) return;

        fetch(`/planeacion/catalogos/codificacion-modelos/${state.selectedId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json'
            }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showToast('Error: ' + (data.message || 'No se pudo eliminar'), 'error');
                return;
            }

            // Actualizar estado local sin recargar toda la página
            // Remover el registro del estado local
            const deleteId = Number(state.selectedId);
            state.rawData = state.rawData.filter(r => Number(r.Id) !== deleteId);
            state.filteredData = state.filteredData.filter(r => Number(r.Id) !== deleteId);
            state.selectedId = null;

            // Re-renderizar la tabla con el estado actualizado
            state.currentPage = 1;
            renderPage();
            updatePagination();
            showToast('Registro eliminado', 'success');
        })
        .catch(error => {
            showToast('Error: ' + error.message, 'error');
        });
    });
    }

    // ============================================
    //  FILTROS
    // ============================================
    function aplicarFiltrosAND() {
        if (!state.rawData.length) {
            showToast('Espera a que carguen los datos', 'warning');
            return;
        }

        if (!state.filtrosDinamicos.length) {
            state.filteredData = [...state.rawData];
        } else {
            const filtrosPorColumna = {};

            state.filtrosDinamicos.forEach(filtro => {
                const key = String(filtro.columna);
                if (!filtrosPorColumna[key]) filtrosPorColumna[key] = [];
                filtrosPorColumna[key].push(filtro.valor.toLowerCase().trim());
            });

            state.filteredData = state.rawData.filter(row => {
                for (const [colIdxStr, valores] of Object.entries(filtrosPorColumna)) {
                    const colIdx = parseInt(colIdxStr, 10);
                    const campo  = CONFIG.camposModelo[colIdx];

                    if (!campo) continue;

                    const valorCelda = String(row[campo] ?? '').toLowerCase().trim();
                    const match = valores.some(valor => valorCelda.includes(valor));

                    if (!match) return false;
                }

                return true;
            });
        }

        state.currentPage = 1;
        renderPage();
        updateActiveFiltersUI();
    }

    function applyFilters() {
        aplicarFiltrosAND();

        if (!state.filteredData.length) {
            showToast('No se encontraron resultados', 'warning');
            return;
        }

        showToast(`${state.filteredData.length} de ${state.rawData.length} registros`, 'success');
    }

    function updateActiveFiltersUI() {
        const container = $('#active-filters-container');
        if (container) container.classList.add('hidden');
    }

    function removeFilter(index) {
        state.filtrosDinamicos.splice(index, 1);
        aplicarFiltrosAND();
        showToast(
            state.filteredData.length ? `${state.filteredData.length} registros` : 'Filtro eliminado',
            'info'
        );
    }

    function filtrarCodificacion() {
        Swal.fire({
            html: `
                <div class="relative">
                    <div class="flex items-center justify-between mb-4 pb-3">
                        <h2 class="text-lg font-semibold text-gray-800">Filtrar Datos</h2>
                        <button
                            type="button"
                            id="btn-close-modal"
                            class="text-gray-400 hover:text-red-600 text-2xl leading-none"
                        >
                            &times;
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div class="flex gap-2">
                            <select
                                id="filtro-columna"
                                class="flex-1 px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">Columna...</option>
                                ${
                                    CONFIG.columnas
                                        .map(c => `<option value="${c.index}">${c.nombre}</option>`)
                                        .join('')
                                }
                            </select>

                            <input
                                type="text"
                                id="filtro-valor"
                                class="flex-1 px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Valor a buscar..."
                            >

                            <button
                                type="button"
                                id="btn-add-filter"
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors"
                            >
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>

                        <div id="modal-active-filters" class="${state.filtrosDinamicos.length ? '' : 'hidden'}">
                            <div id="modal-filters-list" class="flex flex-wrap gap-2"></div>
                        </div>

                        <div id="btn-clear-container" class="${state.filtrosDinamicos.length ? '' : 'hidden'} mt-2">
                            <span id="filter-count" class="ml-2 text-xs text-gray-500"></span>
                        </div>
                    </div>
                </div>
            `,
            width: '550px',
            showConfirmButton: false,
            showCancelButton: false,
            showCloseButton: false,
            didOpen: () => {
                renderModalFilters();

                const closeBtn = $('#btn-close-modal');
                if (closeBtn) {
                    closeBtn.onclick = () => Swal.close();
                }

                const addBtn = $('#btn-add-filter');
                if (addBtn) {
                    addBtn.onclick = addFilterFromModal;
                }

                const valorInput = $('#filtro-valor');
                if (valorInput) {
                    valorInput.onkeydown = event => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            addFilterFromModal();
                        }
                    };
                }

                const clearBtn = $('#btn-clear-filters');
                if (clearBtn) {
                    clearBtn.onclick = () => {
                        limpiarFiltrosCodificacion();
                        Swal.close();
                    };
                }

                const colSelect = $('#filtro-columna');
                colSelect?.focus();
            }
        });
    }

    function addFilterFromModal() {
        const colSelect  = $('#filtro-columna');
        const valueInput = $('#filtro-valor');

        if (!colSelect || !valueInput) return;

        const columnaIdx = parseInt(colSelect.value, 10);
        const valor      = valueInput.value.trim();

        if (Number.isNaN(columnaIdx) || columnaIdx < 0) {
            showToast('Selecciona una columna', 'warning');
            colSelect.focus();
            return;
        }

        if (!valor) {
            showToast('Ingresa un valor', 'warning');
            valueInput.focus();
            return;
        }

        const exists = state.filtrosDinamicos.some(
            f => f.columna === columnaIdx && f.valor.toLowerCase() === valor.toLowerCase()
        );

        if (exists) {
            showToast('Este filtro ya existe', 'warning');
            return;
        }

        state.filtrosDinamicos.push({ columna: columnaIdx, valor });

        valueInput.value = '';
        colSelect.selectedIndex = 0;
        valueInput.focus();

        aplicarFiltrosAND();
        renderModalFilters();

        if (!state.filteredData.length) {
            showToast('No se encontraron resultados', 'warning');
        } else {
            showToast(`${state.filteredData.length} de ${state.rawData.length} registros`, 'success');
        }
    }

    function renderModalFilters() {
        const container      = $('#modal-active-filters');
        const list           = $('#modal-filters-list');
        const counter        = $('#filter-count');
        const clearContainer = $('#btn-clear-container');

        if (!container || !list) return;

        if (!state.filtrosDinamicos.length) {
            container.classList.add('hidden');
            clearContainer?.classList.add('hidden');
            return;
        }

        container.classList.remove('hidden');
        clearContainer?.classList.remove('hidden');
        if (counter) counter.textContent = state.filtrosDinamicos.length;

        list.innerHTML = state.filtrosDinamicos
            .map((filtro, index) => {
                const colName = CONFIG.columnas[filtro.columna]?.nombre || 'Columna';
                return `
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                        <strong>${colName}:</strong> "${filtro.valor}"
                        <button
                            type="button"
                            class="ml-1 hover:text-red-600 font-bold"
                            onclick="removeFilterFromModal(${index})"
                        >
                            &times;
                        </button>
                    </span>
                `;
            })
            .join('');
    }

    function removeFilterFromModal(index) {
        state.filtrosDinamicos.splice(index, 1);
        aplicarFiltrosAND();
        renderModalFilters();
        showToast(
            state.filteredData.length ? `${state.filteredData.length} registros` : 'Filtro eliminado',
            'info'
        );
    }

    function limpiarFiltrosCodificacion() {
        state.filtrosDinamicos = [];
        aplicarFiltrosAND();
        showToast('Filtros limpiados', 'info');
    }

    // ============================================
    //  EXCEL
    // ============================================
    function subirExcelCodificacion() {
        const input = document.createElement('input');
        input.type   = 'file';
        input.accept = '.xlsx,.xls';

        input.onchange = event => {
            const file = event.target.files?.[0];
        if (!file) return;

            const sizeMB = file.size / 1024 / 1024;
            if (sizeMB > 10) {
                showToast('Máximo 10MB', 'error');
                return;
            }

        Swal.fire({
            title: '¿Procesar Excel?',
                text: `${file.name} (${sizeMB.toFixed(2)} MB)`,
            icon: 'question',
            showCancelButton: true,
                confirmButtonText: 'Procesar'
            }).then(result => {
                if (result.isConfirmed) {
                    procesarExcel(file);
                }
            });
        };

    input.click();
}

function procesarExcel(file) {
    const formData = new FormData();
    formData.append('archivo_excel', file);

        Swal.fire({
            title: 'Procesando...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

    fetch('/planeacion/catalogos/codificacion-modelos/excel', {
        method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').content
            },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
                if (data.success && data.data?.poll_url) {
            pollImportProgress(data.data.poll_url);
                    return;
                }

                Swal.fire({
                    title: 'Error',
                    text: data.message || 'Ocurrió un error al procesar el archivo',
                    icon: 'error'
                });
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error',
                    text: error.message,
                    icon: 'error'
                });
            });
    }

    function pollImportProgress(url, attempts = 0) {
        if (attempts > 600) {
            Swal.fire({ title: 'Timeout', icon: 'warning' });
            return;
        }

        fetch(url)
            .then(r => r.json())
            .then(result => {
            if (result.success && result.data) {
                    const {
                        status,
                        processed_rows = 0,
                        total_rows = '?',
                        created = 0,
                        updated = 0
                    } = result.data;

                    Swal.update({
                        html: `${processed_rows}/${total_rows} (${result.percent || 0}%)`
                    });

                if (status === 'done') {
                    Swal.close();
                        Swal.fire({
                            title: '¡Éxito!',
                            text: `Nuevos: ${created}, Actualizados: ${updated}`,
                            icon: 'success'
                        }).then(() => location.reload());
                        return;
                    }

                    setTimeout(() => pollImportProgress(url, attempts + 1), 1000);
                    return;
                }

                setTimeout(() => pollImportProgress(url, attempts + 1), 1000);
            })
            .catch(() => {
                setTimeout(() => pollImportProgress(url, attempts + 1), 1000);
            });
    }

    // ============================================
    //  OCULTAR / FIJAR COLUMNAS
    // ============================================
    function showColumn(index, silent = false) {
        $$(`.column-${index}`).forEach(el => {
            el.style.display = '';
            el.style.visibility = '';
        });
        if (Array.isArray(hiddenColumns)) {
            const idx = hiddenColumns.indexOf(index);
            if (idx > -1) {
                hiddenColumns.splice(idx, 1);
            }
        }
        if (!silent && typeof showToast === 'function') {
            showToast(`Columna visible`, 'info');
        }
    }

    function hideColumn(index, silent = false) {
        $$(`.column-${index}`).forEach(el => el.style.display = 'none');
        if (!hiddenColumns.includes(index)) hiddenColumns.push(index);
        if (!silent && typeof showToast === 'function') {
            showToast(`Columna oculta`, 'info');
        }
    }

    function togglePinColumn(index) {
        const exists = pinnedColumns.includes(index);
        if (exists) {
            pinnedColumns = pinnedColumns.filter(i => i !== index);
        } else {
            pinnedColumns.push(index);
        }
        pinnedColumns.sort((a, b) => a - b);
        updatePinnedColumnsPositions();
    }

    function updatePinnedColumnsPositions() {
        // Limpiar estilos de todas las columnas primero
        const allIdx = [...new Set($$('th[class*="column-"]').map(th => {
            const match = th.className.match(/column-(\d+)/);
            return match ? parseInt(match[1]) : null;
        }).filter(idx => idx !== null))];

        allIdx.forEach(idx => {
            $$(`.column-${idx}`).forEach(el => {
                if (el.tagName === 'TH') {
                    // Restaurar estilos básicos de encabezado (se aplicarán nuevos si está fijado)
                    el.style.left = '';
                    el.classList.remove('pinned-column');
                    // Los estilos sticky top se mantienen del CSS base
                    if (!pinnedColumns.includes(idx)) {
                        el.style.backgroundColor = '#3b82f6';
                    }
                } else {
                    // Limpiar todos los estilos de las celdas
                    el.style.position = '';
                    el.style.top = '';
                    el.style.left = '';
                    el.style.zIndex = '';
                    el.style.backgroundColor = '';
                    el.style.color = '';
                    el.classList.remove('pinned-column');
                }
            });
        });

        // Aplicar fijados en orden
        let left = 0;
        pinnedColumns.forEach((idx, order) => {
            const th = $(`th.column-${idx}`);
            if (!th || th.style.display === 'none') return;

            const width = th.offsetWidth || th.getBoundingClientRect().width;
            $$(`.column-${idx}`).forEach(el => {
                if (el.tagName === 'TH') {
                    // Encabezado: sticky tanto en top (0) como en left
                    el.style.position = '-webkit-sticky';
                    el.style.position = 'sticky';
                    el.style.top = '0';
                    el.style.left = `${left}px`;
                    el.style.zIndex = String(1020 + order);
                    el.style.backgroundColor = '#f59e0b';
                    el.style.color = '#fff';
                    el.style.boxShadow = '2px 2px 4px rgba(0, 0, 0, 0.2)';
                } else {
                    // Celda del cuerpo: solo sticky en left (no en top)
                    el.style.position = 'sticky';
                    el.style.left = `${left}px`;
                    el.style.zIndex = String(100 + order);
                    el.style.backgroundColor = '#fffbeb';
                }
                el.classList.add('pinned-column');
            });
            left += width;
        });
    }

    function openPinColumnsModal() {
        const columns = CONFIG.columnas.map((col, idx) => ({
            label: col.nombre,
            index: idx
        }));

        let html = `
            <div class="text-left">
                <p class="text-sm text-gray-600 mb-4">Selecciona las columnas que deseas fijar a la izquierda de la tabla:</p>
                <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-2">
        `;

        columns.forEach((col) => {
            const isPinned = pinnedColumns.includes(col.index);
            html += `
                <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                    <span class="text-sm text-gray-700">${col.label}</span>
                    <input type="checkbox" ${isPinned ? 'checked' : ''}
                           class="w-4 h-4 text-yellow-600 bg-gray-100 border-gray-300 rounded focus:ring-yellow-500 column-toggle-pin"
                           data-column-index="${col.index}">
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;

        Swal.fire({
            title: 'Fijar Columnas',
            html: html,
            showCancelButton: true,
            confirmButtonText: 'Aplicar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#6b7280',
            width: '500px',
            didOpen: () => {
                document.querySelectorAll('#swal2-html-container .column-toggle-pin').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const columnIndex = parseInt(this.dataset.columnIndex);
                        togglePinColumn(columnIndex);
                        // Actualizar checkbox
                        this.checked = pinnedColumns.includes(columnIndex);
                    });
                });
        }
    });
}

    function openHideColumnsModal() {
        const columns = CONFIG.columnas.map((col, idx) => ({
            label: col.nombre,
            index: idx
        }));

        let html = `
            <div class="text-left">
                <p class="text-sm text-gray-600 mb-4">Selecciona las columnas que deseas ocultar:</p>
                <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-2">
        `;

        columns.forEach((col) => {
            const isHidden = hiddenColumns.includes(col.index);
            html += `
                <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                    <span class="text-sm text-gray-700">${col.label}</span>
                    <input type="checkbox" ${isHidden ? 'checked' : ''}
                           class="w-4 h-4 text-red-600 bg-gray-100 border-gray-300 rounded focus:ring-red-500 column-toggle-hide"
                           data-column-index="${col.index}">
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;

        Swal.fire({
            title: 'Ocultar Columnas',
            html: html,
            showCancelButton: true,
            confirmButtonText: 'Aplicar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            width: '500px',
            didOpen: () => {
                document.querySelectorAll('#swal2-html-container .column-toggle-hide').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const columnIndex = parseInt(this.dataset.columnIndex);
                        if (this.checked) {
                            hideColumn(columnIndex);
                        } else {
                            showColumn(columnIndex);
                        }
                    });
                });
            }
        });
    }

    // ============================================
    //  INICIALIZACIÓN
    // ============================================
    function init() {
        // ⚡ OPTIMIZACIÓN: Configurar delegación de eventos antes de cargar datos
        setupEventDelegation();

        // Sort buttons (delegación de eventos para mejor rendimiento)
        const thead = $('#mainTable thead');
        if (thead) {
            thead.addEventListener('click', (e) => {
                const btn = e.target.closest('.sort-btn-asc, .sort-btn-desc');
                if (btn) {
                    e.stopPropagation();
                    const colIndex = Number(btn.dataset.column);
                    const isAsc = btn.classList.contains('sort-btn-asc');
                    sortColumn(colIndex, isAsc ? 'asc' : 'desc');
                }
            });
        }

        // Paginación
        const prev = $('#pagination-prev');
        const next = $('#pagination-next');

        if (prev) prev.onclick = () => goToPage(state.currentPage - 1);
        if (next) next.onclick = () => goToPage(state.currentPage + 1);

        window.addEventListener('resize', () => {
            if (virtualState.active) {
                scheduleVirtualUpdate(true);
            }
        });

        // Menú contextual
        const editBtn = $('#context-menu-edit');
        if (editBtn) {
            editBtn.onclick = (e) => {
                e.stopPropagation();
                $('#context-menu')?.classList.add('hidden');
                editarCodificacion();
            };
        }

        const duplicateBtn = $('#context-menu-duplicate');
        if (duplicateBtn) {
            duplicateBtn.onclick = (e) => {
                e.stopPropagation();
                $('#context-menu')?.classList.add('hidden');
                duplicarCodificacion();
            };
        }

        const deleteBtn = $('#context-menu-delete');
        if (deleteBtn) {
            deleteBtn.onclick = (e) => {
                e.stopPropagation();
                $('#context-menu')?.classList.add('hidden');
                eliminarCodificacion();
            };
        }

        // Cargar datos
        loadData();
    }

    document.addEventListener('DOMContentLoaded', init);

    // ============================================
    //  EXPOSE GLOBAL (compat)
    // ============================================
    window.agregarCodificacion       = agregarCodificacion;
    window.editarCodificacion        = editarCodificacion;
    window.eliminarCodificacion      = eliminarCodificacion;
    window.filtrarCodificacion       = filtrarCodificacion;
    window.limpiarFiltrosCodificacion = limpiarFiltrosCodificacion;
    window.applyFilters              = applyFilters;
    window.subirExcelCodificacion    = subirExcelCodificacion;
    window.removeFilterFromModal     = removeFilterFromModal;
    window.removeFilter              = removeFilter;
    window.openPinColumnsModal       = openPinColumnsModal;
    window.openHideColumnsModal      = openHideColumnsModal;
    window.hideColumn                = hideColumn;
    window.showColumn                = showColumn;
    window.togglePinColumn           = togglePinColumn;
    window.loadData                  = () => loadData({ force: true }); // Refrescar sin cache después de crear/editar/eliminar
    window.renderPage                = renderPage; // Exponer renderPage para actualizar vista sin recargar
    window.addCodificacionRecords = function(newRecords) {
        // Función helper para agregar registros al estado sin recargar
        if (!Array.isArray(newRecords)) {
            newRecords = [newRecords];
        }

        let agregados = 0;
        newRecords.forEach(reg => {
            if (reg && reg.Id !== undefined && reg.Id !== null && reg.Id !== '') {
                const parsedId = Number(reg.Id);
                const normalizedId = Number.isNaN(parsedId) ? reg.Id : parsedId;
                reg.Id = normalizedId;
                // Verificar que no exista ya en el estado
                const existe = state.rawData.find(r => Number(r.Id) === Number(normalizedId));
                if (!existe) {
                    state.rawData.unshift(reg); // Agregar al inicio
                    agregados++;
                }
            }
        });

        if (agregados > 0) {
            // Actualizar filteredData y renderizar
            state.filteredData = [...state.rawData];
            state.currentPage = 1; // Volver a la primera página
            renderPage();
            updatePagination();
        }
    };
})();
</script>
@endsection
