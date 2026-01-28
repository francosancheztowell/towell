@extends('layouts.app')

@section('page-title', 'Modelos Codificados')

@section('navbar-right')
<div class="flex items-center gap-2">
<x-buttons.catalog-actions route="codificacion" :showFilters="true" :showExcel="false" />
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
                <colgroup id="mainTable-colgroup">
                    @foreach($columnas as $idx => $col)
                        <col data-index="{{ $idx }}">
                    @endforeach
                </colgroup>
                <thead class="bg-blue-500">
                    <tr>
                        @foreach($columnas as $idx => $col)
                            <th
                                class="column-{{ $idx }} px-3 py-2 text-left text-sm font-medium text-white whitespace-nowrap border-b border-blue-400 bg-blue-500"
                                data-index="{{ $idx }}"
                                data-column="{{ $camposModelo[$idx] ?? '' }}"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="truncate">{{ $col }}</span>
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

{{-- Menú contextual (click derecho en filas) --}}
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

{{-- Menú contextual para encabezados (click derecho) --}}
<div id="context-menu-header" class="hidden fixed bg-white border border-gray-300 rounded-lg shadow-lg z-50 py-1 min-w-[180px]">
    <button id="context-menu-header-filtrar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
        <i class="fas fa-filter text-yellow-500"></i>
        <span>Filtrar</span>
    </button>
    <button id="context-menu-header-fijar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-yellow-50 hover:text-yellow-700 flex items-center gap-2">
        <i class="fas fa-thumbtack text-blue-500"></i>
        <span>Fijar</span>
    </button>
    <button id="context-menu-header-ocultar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 flex items-center gap-2">
        <i class="fas fa-eye-slash text-red-500"></i>
        <span>Ocultar</span>
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
        scrollbar-gutter: stable;
    }

    /* Scrollbar personalizada - Siempre visible y más grande */
    #table-container::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    #table-container::-webkit-scrollbar-track {
        background: #e5e7eb;
        border-radius: 7px;
    }

    #table-container::-webkit-scrollbar-thumb {
        background: #6b7280;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
    }

    #table-container::-webkit-scrollbar-thumb:hover {
        background: #4b5563;
    }

    /* Scrollbar horizontal - Más visible */
    #table-container::-webkit-scrollbar:horizontal {
        height: 8px;
    }

    /* Para Firefox */
    #table-container {
        scrollbar-width: thin;
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
    }

    #mainTable.locked-layout {
        table-layout: fixed;
    }

    #mainTable th,
    #mainTable td {
        box-sizing: border-box;
    }

    #mainTable.locked-layout th,
    #mainTable.locked-layout td {
        overflow: hidden;
        text-overflow: ellipsis;
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

    /* Colores alternados (zebra striping) - Filas impares gris, pares blancas */
    /* Usar clases row-odd y row-even agregadas por JavaScript */
    #mainTable tbody tr.data-row.row-odd:not(.selected-row) {
        background-color: #f9fafb !important; /* gray-50 */
    }

    #mainTable tbody tr.data-row.row-even:not(.selected-row) {
        background-color: #ffffff !important; /* white */
    }

    /* Asegurar que las filas seleccionadas mantengan el color azul */
    #mainTable tbody tr.data-row.selected-row {
        background-color: rgb(59, 130, 246) !important; /* blue-500 */
        color: white !important;
    }

    /* Hover en filas con colores alternados */
    #mainTable tbody tr.data-row.row-odd:not(.selected-row):hover {
        background-color: #f3f4f6 !important; /* gray-100 */
    }

    #mainTable tbody tr.data-row.row-even:not(.selected-row):hover {
        background-color: #f9fafb !important; /* gray-50 */
    }

    #mainTable tbody tr.data-row.selected-row:hover {
        background-color: rgb(37, 99, 235) !important; /* blue-600 */
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

    /* ============================================
       MENÚ CONTEXTUAL DE ENCABEZADOS
       ============================================ */
    #context-menu-header {
        display: none;
        background-color: #ffffff !important;
        z-index: 99999 !important;
        opacity: 1 !important;
        position: fixed !important;
    }

    #context-menu-header:not(.hidden) {
        display: block;
        background-color: #ffffff !important;
        z-index: 99999 !important;
        opacity: 1 !important;
    }

    #context-menu-header button {
        transition: background-color 0.15s ease;
        background-color: #ffffff !important;
        opacity: 1 !important;
    }

    #context-menu-header button:hover {
        background-color: #f3f4f6 !important;
    }

    #context-menu-header button:active {
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
    const columnWidthState = {
        locked: false,
        widths: []
    };

    let resizeRaf = null;

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
            const columnsLen = columns.length;

            // ⚡ OPTIMIZACIÓN: Crear objetos directamente sin múltiples iteraciones
            // Usar Array pre-allocado para mejor rendimiento
            const data = result.d;
            const dataLength = data.length;
            const rawData = new Array(dataLength);

            for (let i = 0; i < dataLength; i++) {
                const row = data[i];
                const obj = {};
                for (let j = 0; j < columnsLen; j++) {
                    obj[columns[j]] = row[j];
                }
                if (obj.Id !== undefined && obj.Id !== null && obj.Id !== '') {
                    const parsedId = Number(obj.Id);
                    if (!Number.isNaN(parsedId)) {
                        obj.Id = parsedId;
                    }
                }
                rawData[i] = obj;
            }

            state.rawData = rawData;
            state.filteredData = rawData.slice();

            // ⚡ OPTIMIZACIÓN: Renderizar usando requestAnimationFrame para mejor rendimiento
            requestAnimationFrame(() => {
                renderPage();
                if (loadingEl) loadingEl.classList.add('hidden');
                state.isLoading = false;
                // Actualizar iconos después de cargar datos
                setTimeout(() => {
                    updateColumnFilterIcons();
                    updateColumnPinIcons();
                }, 100);
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
            
            // Agregar clase para zebra striping (par/impar)
            const zebraClass = i % 2 === 0 ? 'row-even' : 'row-odd';
            
            // No incluir hover:bg-gray-50 ya que el hover se maneja con CSS
            tr.className = isSelected
                ? 'data-row cursor-pointer transition-colors bg-blue-500 text-white selected-row rendered'
                : `data-row cursor-pointer transition-colors ${zebraClass} rendered`;

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

            // Agregar clase para zebra striping (par/impar) - usar índice i directamente
            const zebraClass = i % 2 === 0 ? 'row-even' : 'row-odd';

            //  OPTIMIZACIÓN: Usar clases pre-calculadas y marcar como renderizado
            // No incluir hoverClassBase ya que el hover se maneja con CSS
            tr.className = isSelected
                ? `${baseClass} ${selectedClassBase} rendered`
                : `${baseClass} ${zebraClass} rendered`;

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
                setTimeout(() => {
                    updateColumnFilterIcons();
                    updateColumnPinIcons();
                }, 50);
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
                updateColumnFilterIcons();
                updateColumnPinIcons();
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
                    
                    // Agregar clase para zebra striping (par/impar)
                    const zebraClass = i % 2 === 0 ? 'row-even' : 'row-odd';
                    
                    //  OPTIMIZACIÓN: Usar clases pre-calculadas y marcar como renderizado
                    // No incluir hoverClassBase ya que el hover se maneja con CSS
                    tr.className = isSelected
                        ? `${baseClass} ${selectedClassBase} rendered`
                        : `${baseClass} ${zebraClass} rendered`;

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
                updateColumnFilterIcons();
                updateColumnPinIcons();
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
            lockColumnWidths();
            updatePinnedColumnsPositions();
            // Actualizar iconos después de actualizar posiciones
            updateColumnFilterIcons();
            updateColumnPinIcons();
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
            // No agregar hover:bg-gray-50 ya que el hover se maneja con CSS
            //  OPTIMIZACIÓN: Actualizar celdas de la fila anterior
            const prevCells = previousSelectedRow.querySelectorAll('td');
            prevCells.forEach(td => {
                td.classList.remove('text-white');
                td.classList.add('text-gray-700');
            });
        }

        //  OPTIMIZACIÓN: Actualizar nueva fila seleccionada
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
        // Actualizar iconos de filtro después de aplicar filtros
        updateColumnFilterIcons();
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
        updateColumnFilterIcons();
        showToast(
            state.filteredData.length ? `${state.filteredData.length} registros` : 'Filtro eliminado',
            'info'
        );
    }

    function limpiarFiltrosCodificacion() {
        state.filtrosDinamicos = [];
        aplicarFiltrosAND();
        updateColumnFilterIcons();
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
    //  ACTUALIZAR ICONOS DE FILTROS Y FIJADOS
    // ============================================
    function updateColumnFilterIcons() {
        const thead = $('#mainTable thead');
        if (!thead) return;

        // Obtener todas las columnas con filtros activos
        const filteredColumns = new Set();
        state.filtrosDinamicos.forEach(f => {
            if (f.columna !== undefined && f.columna !== null) {
                filteredColumns.add(f.columna);
            }
        });

        // Recorrer todos los encabezados
        const allHeaders = $$('th[data-index]', thead);
        allHeaders.forEach(th => {
            const columnIndex = parseInt(th.dataset.index, 10);
            if (Number.isNaN(columnIndex)) return;

            // Buscar o crear el icono de filtro
            let filterIcon = th.querySelector('.column-filter-icon');

            if (filteredColumns.has(columnIndex)) {
                // La columna tiene filtros activos - mostrar icono
                if (!filterIcon) {
                    filterIcon = document.createElement('i');
                    filterIcon.className = 'fas fa-filter column-filter-icon text-yellow-400 ml-1 text-xs cursor-pointer hover:text-yellow-500';
                    filterIcon.title = 'Columna filtrada - Click para quitar filtro';
                    filterIcon.style.cursor = 'pointer';

                    // Agregar event listener para eliminar filtro al hacer clic
                    filterIcon.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        // Eliminar filtros de esta columna
                        state.filtrosDinamicos = state.filtrosDinamicos.filter(f => f.columna !== columnIndex);

                        // Aplicar filtros actualizados
                        aplicarFiltrosAND();

                        showToast('Filtro removido de la columna', 'info');
                    });

                    // Insertar el icono después del texto del encabezado
                    const headerContent = th.querySelector('div');
                    if (headerContent) {
                        // Verificar si ya existe un icono de pin para insertar después
                        const existingPinIcon = headerContent.querySelector('.column-pin-icon');
                        if (existingPinIcon) {
                            headerContent.insertBefore(filterIcon, existingPinIcon);
                        } else {
                            headerContent.appendChild(filterIcon);
                        }
                    } else {
                        th.appendChild(filterIcon);
                    }
                }
                filterIcon.style.display = 'inline-block';
            } else {
                // La columna no tiene filtros - ocultar icono
                if (filterIcon) {
                    filterIcon.style.display = 'none';
                }
            }
        });
    }

    function updateColumnPinIcons() {
        const thead = $('#mainTable thead');
        if (!thead) return;

        // Obtener todas las columnas fijadas
        const pinnedIndices = new Set(pinnedColumns);

        // Recorrer todos los encabezados
        const allHeaders = $$('th[data-index]', thead);
        allHeaders.forEach(th => {
            const columnIndex = parseInt(th.dataset.index, 10);
            if (Number.isNaN(columnIndex)) return;

            // Buscar o crear el icono de fijado
            let pinIcon = th.querySelector('.column-pin-icon');

            if (pinnedIndices.has(columnIndex)) {
                // La columna está fijada - mostrar icono
                if (!pinIcon) {
                    pinIcon = document.createElement('i');
                    pinIcon.className = 'fas fa-thumbtack column-pin-icon text-white ml-1 text-xs cursor-pointer hover:text-yellow-300';
                    pinIcon.title = 'Desfijar columna';
                    pinIcon.dataset.columnIndex = String(columnIndex);

                    // Agregar event listener para desfijar al hacer clic
                    pinIcon.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        const idx = parseInt(pinIcon.dataset.columnIndex || String(columnIndex), 10);
                        if (!Number.isNaN(idx)) {
                            togglePinColumn(idx);
                            showToast('Columna desfijada', 'info');
                        }
                    });

                    // Insertar el icono después del texto del encabezado (o después del icono de filtro si existe)
                    const headerContent = th.querySelector('div');
                    if (headerContent) {
                        // Insertar después del icono de filtro si existe, sino al final
                        const existingFilterIcon = headerContent.querySelector('.column-filter-icon');
                        if (existingFilterIcon) {
                            headerContent.insertBefore(pinIcon, existingFilterIcon.nextSibling);
                        } else {
                            headerContent.appendChild(pinIcon);
                        }
                    } else {
                        th.appendChild(pinIcon);
                    }
                }
                pinIcon.style.display = 'inline-block';
            } else {
                // La columna no está fijada - ocultar icono
                if (pinIcon) {
                    pinIcon.style.display = 'none';
                }
            }
        });
    }

    // ============================================
    //  OCULTAR / FIJAR COLUMNAS
    // ============================================
    function getColElements() {
        const colgroup = $('#mainTable-colgroup');
        return colgroup ? Array.from(colgroup.children) : [];
    }

    function lockColumnWidths(force = false) {
        if (columnWidthState.locked && !force) return;

        const table = $('#mainTable');
        const ths = $$('#mainTable thead th');
        const cols = getColElements();
        if (!table || !ths.length || !cols.length) return;

        const widths = [];
        const count = Math.min(ths.length, cols.length);
        for (let i = 0; i < count; i++) {
            let width = Math.ceil(ths[i].getBoundingClientRect().width);
            if (!width) {
                width = columnWidthState.widths[i] || 0;
            }
            widths.push(width);
            cols[i].style.width = `${width}px`;
            cols[i].style.minWidth = `${width}px`;
            cols[i].style.maxWidth = `${width}px`;
        }

        const hasWidth = widths.some(w => w > 0);
        if (!hasWidth) return;

        columnWidthState.widths = widths;
        columnWidthState.locked = true;
        table.classList.add('locked-layout');
    }

    function setColVisibility(index, visible) {
        const cols = getColElements();
        const col = cols[index];
        if (!col) return;
        col.style.display = visible ? '' : 'none';
        if (visible && columnWidthState.widths[index]) {
            const width = columnWidthState.widths[index];
            col.style.width = `${width}px`;
            col.style.minWidth = `${width}px`;
            col.style.maxWidth = `${width}px`;
        }
    }

    function showColumn(index, silent = false) {
        $$(`.column-${index}`).forEach(el => {
            el.style.display = '';
            el.style.visibility = '';
        });
        setColVisibility(index, true);
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
        setColVisibility(index, false);
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
        // Actualizar iconos de fijado después de cambiar estado
        updateColumnPinIcons();
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

        // Actualizar iconos después de cambiar posiciones
        updateColumnPinIcons();
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
    //  MENÚ CONTEXTUAL DE ENCABEZADOS
    // ============================================
    (function initContextMenuHeader() {
        const menu = $('#context-menu-header');
        if (!menu) return;

        let menuColumnIndex = null;
        let menuColumnField = null;

        function hide() {
            menu.classList.add('hidden');
            menuColumnIndex = null;
            menuColumnField = null;
        }

        function show(e, columnIndex, columnField) {
            // Cerrar el menú de filas si está abierto
            const rowMenu = $('#context-menu');
            if (rowMenu && !rowMenu.classList.contains('hidden')) {
                rowMenu.classList.add('hidden');
            }

            menuColumnIndex = columnIndex;
            menuColumnField = columnField;
            menu.style.left = e.clientX + 'px';
            menu.style.top = e.clientY + 'px';

            const rect = menu.getBoundingClientRect();
            if (rect.right > window.innerWidth) menu.style.left = (e.clientX - rect.width) + 'px';
            if (rect.bottom > window.innerHeight) menu.style.top = (e.clientY - rect.height) + 'px';

            menu.classList.remove('hidden');
        }

        // Cerrar al hacer click fuera
        document.addEventListener('click', (e) => {
            if (!menu.classList.contains('hidden') && !menu.contains(e.target)) {
                hide();
            }
        });

        // Cerrar con Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !menu.classList.contains('hidden')) {
                hide();
            }
        });

        // Listener de contextmenu en los encabezados
        const thead = $('#mainTable thead');
        if (thead) {
            thead.addEventListener('contextmenu', (e) => {
                const th = e.target.closest('th');
                if (!th) return;

                e.preventDefault();
                e.stopPropagation();

                // Obtener índice de columna
                let columnIndex = parseInt(th.dataset.index, 10);
                if (Number.isNaN(columnIndex)) {
                    const classMatch = th.className.match(/column-(\d+)/);
                    if (classMatch) {
                        columnIndex = parseInt(classMatch[1], 10);
                    }
                }

                // Obtener campo de la columna
                const columnField = CONFIG.camposModelo[columnIndex] || null;

                if (Number.isNaN(columnIndex) || !columnField) {
                    console.error('[contextMenuHeader] No se pudo obtener índice o campo:', {
                        index: columnIndex,
                        field: columnField
                    });
                    return;
                }

                show(e, columnIndex, columnField);
            });
        }

        // Botón Filtrar
        $('#context-menu-header-filtrar')?.addEventListener('click', () => {
            const savedIndex = menuColumnIndex;
            const savedField = menuColumnField;
            hide();

            if (savedIndex !== null && savedIndex >= 0 && savedField) {
                openFilterModalForColumn(savedIndex, savedField);
            } else {
                showToast('No se pudo obtener información de la columna', 'error');
            }
        });

        // Botón Fijar
        $('#context-menu-header-fijar')?.addEventListener('click', () => {
            const savedIndex = menuColumnIndex;
            hide();

            if (savedIndex !== null && savedIndex >= 0) {
                togglePinColumn(savedIndex);
                showToast('Columna fijada/desfijada', 'info');
            } else {
                showToast('No se pudo obtener el índice de la columna', 'error');
            }
        });

        // Botón Ocultar
        $('#context-menu-header-ocultar')?.addEventListener('click', () => {
            const savedIndex = menuColumnIndex;
            hide();

            if (savedIndex !== null && savedIndex >= 0) {
                hideColumn(savedIndex);
                showToast('Columna oculta', 'info');
            } else {
                showToast('No se pudo obtener el índice de la columna', 'error');
            }
        });
    })();

    // ============================================
    //  FUNCIÓN PARA ABRIR MODAL DE FILTRO POR COLUMNA
    // ============================================
    function openFilterModalForColumn(columnIndex, columnField) {
        if (columnIndex === null || columnIndex === undefined || columnIndex < 0) {
            showToast('No se pudo obtener el índice de la columna', 'error');
            return;
        }

        if (!columnField || typeof columnField !== 'string') {
            showToast('No se pudo obtener el campo de la columna', 'error');
            return;
        }

        // Obtener el label de la columna
        const columnLabel = CONFIG.columnas[columnIndex]?.nombre || columnField;

        // Obtener valores únicos de la columna desde los datos filtrados
        const uniqueValues = new Set();
        const valueCounts = new Map();

        state.filteredData.forEach(row => {
            const value = row[columnField];
            if (value !== null && value !== undefined && value !== '') {
                const valueStr = String(value).trim();
                if (valueStr) {
                    if (!valueCounts.has(valueStr)) {
                        uniqueValues.add(valueStr);
                        valueCounts.set(valueStr, { value: valueStr, count: 0 });
                    }
                    valueCounts.get(valueStr).count++;
                }
            }
        });

        const sortedValues = Array.from(uniqueValues).sort();

        if (sortedValues.length === 0) {
            showToast('No hay valores para filtrar en esta columna', 'info');
            return;
        }

        // Crear HTML del modal
        const escapedLabel = String(columnLabel).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        let html = `
            <div class="text-left">
                <p class="text-sm text-gray-600 mb-4">Filtrar por: <strong>${escapedLabel}</strong></p>
                <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-2">
                    <div class="mb-2 pb-2 border-b border-gray-200">
                        <input type="text" id="filterSearchInput" placeholder="Buscar..." 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div id="filterCheckboxesContainer" class="space-y-1">
        `;

        sortedValues.forEach(value => {
            const entry = valueCounts.get(value);
            const count = entry ? entry.count : 0;
            const escapedValue = String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const displayValue = escapedValue;

            html += `
                <label class="flex items-center justify-between p-2 hover:bg-gray-50 rounded cursor-pointer filter-checkbox-item" data-value="${escapedValue}">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 filter-checkbox" value="${escapedValue}">
                        <span class="text-sm text-gray-700">${displayValue}</span>
                    </div>
                    <span class="text-xs text-gray-500">(${count})</span>
                </label>
            `;
        });

        html += '</div></div></div>';

        Swal.fire({
            title: 'Filtrar Columna',
            html: html,
            showCancelButton: true,
            confirmButtonText: 'Aplicar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6b7280',
            width: '500px',
            didOpen: () => {
                // Restaurar estado de checkboxes si hay filtros activos para esta columna
                const activeFiltersForColumn = state.filtrosDinamicos.filter(f => f.columna === columnIndex);
                if (activeFiltersForColumn.length > 0) {
                    activeFiltersForColumn.forEach(filter => {
                        const filterValue = String(filter.valor || '').trim();
                        const label = Array.from(document.querySelectorAll('.filter-checkbox-item')).find(l => {
                            return l.dataset.value === filterValue;
                        });
                        if (label) {
                            const checkbox = label.querySelector('.filter-checkbox');
                            if (checkbox) checkbox.checked = true;
                        }
                    });
                }

                // Búsqueda en tiempo real
                const searchInput = document.getElementById('filterSearchInput');
                const container = document.getElementById('filterCheckboxesContainer');
                const items = container.querySelectorAll('.filter-checkbox-item');

                searchInput?.addEventListener('input', (e) => {
                    const searchTerm = e.target.value.toLowerCase();
                    items.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        item.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                });

                searchInput?.focus();
            },
            preConfirm: () => {
                const checked = Array.from(document.querySelectorAll('.filter-checkbox:checked'));
                const selectedValues = checked.map(cb => cb.value);

                // Eliminar filtros existentes de esta columna
                state.filtrosDinamicos = state.filtrosDinamicos.filter(f => f.columna !== columnIndex);

                // Agregar nuevos filtros
                selectedValues.forEach(value => {
                    state.filtrosDinamicos.push({ columna: columnIndex, valor: value });
                });

                aplicarFiltrosAND();
                updateColumnFilterIcons();
                return true;
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
            if (resizeRaf) return;
            resizeRaf = requestAnimationFrame(() => {
                resizeRaf = null;
                columnWidthState.locked = false;
                lockColumnWidths(true);
                updatePinnedColumnsPositions();
                if (virtualState.active) {
                    scheduleVirtualUpdate(true);
                }
            });
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
    window.updateColumnFilterIcons   = updateColumnFilterIcons;
    window.updateColumnPinIcons      = updateColumnPinIcons;
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
