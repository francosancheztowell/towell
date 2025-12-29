@extends('layouts.app')

@section('page-title', 'Codificación')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-buttons.catalog-actions route="codificacion" :showFilters="true" />
    </div>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="relative bg-white rounded-lg shadow-sm flex flex-col" style="height: calc(100vh);">

            {{-- Loading overlay único --}}
            <div
                id="loading-overlay"
                class="absolute inset-0 bg-white hidden items-center text-center justify-center z-20"
            >
                <div class="flex flex-col items-center gap-4">
                    <div class="h-12 w-12 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                    <div class="text-center">
                        <p id="loading-message" class="text-sm font-medium text-gray-700">Cargando datos...</p>
                        <p id="loading-count" class="text-sm text-gray-500 mt-1"></p>
                    </div>
                </div>
            </div>

            {{-- Contenedor tabla + scroffffll --}}
            <div
                id="table-container"
                class="relative flex-1 overflow-y-auto overflow-x-auto"
                style="max-height: calc(100vh - 110px);"
            >
                @php
                    $columnas = $columnas ?? [];
                    $columnLabels = [
                        'JulioRizo' => 'No Julio Rizo',
                        'JulioPie' => 'No Julio Pie',
                        'EfiInicial' => 'Eficiencia de Inicio',
                        'EfiFinal' => 'Eficiencia Final',
                        'DesperdicioTrama' => 'Desperdicio Trama',
                    ];
                @endphp

                <table id="mainTable" class="w-full min-w-full text-[11px] leading-tight">
                    <thead class="bg-blue-500 text-white sticky top-0 z-10">
                        <tr>
                            @foreach($columnas as $columna)
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap border-b border-blue-600/70">
                                    <span class="block truncate">{{ $columnLabels[$columna] ?? $columna }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody id="catcodificacion-body" class="bg-white text-gray-800">
                        {{-- El contenido se llena por JS --}}
                    </tbody>
                </table>
            </div>

            {{-- Paginación fija abajo --}}
            <div
                id="pagination-container"
                class="px-4 border-t border-gray-200 bg-white flex-shrink-0 z-20"
            >
                <div class="flex items-center gap-2">
                    <button
                        id="pagination-prev"
                        class="inline-flex items-center gap-1 px-3 py-1 rounded border border-gray-300 bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        disabled
                    >
                        <i class="fas fa-chevron-left text-[10px]"></i>
                        <span>Anterior</span>
                    </button>

                    <div class="flex flex-col sm:flex-row sm:items-center sm:gap-2 text-gray-700">
                        <span>
                            Página
                            <span id="pagination-current" class="font-semibold">1</span>
                            de
                            <span id="pagination-total-pages" class="font-semibold">1</span>
                        </span>
                        <span class="text-gray-500">
                            · Mostrando
                            <span id="pagination-start">0</span> -
                            <span id="pagination-end">0</span>
                            de
                            <span id="pagination-total">0</span>
                        </span>
                    </div>

                    <button
                        id="pagination-next"
                        class="inline-flex items-center gap-1 px-3 py-1 rounded border border-gray-300 bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition"
                    >
                        <span>Siguiente</span>
                        <i class="fas fa-chevron-right text-[10px]"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        #table-container {
            position: relative;
            overflow-y: auto;
            overflow-x: auto;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            flex: 1;
            min-height: 0;
        }

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

        #table-container::-webkit-scrollbar:horizontal {
            height: 14px;
        }

        #table-container {
            scrollbar-width: auto;
            scrollbar-color: #6b7280 #e5e7eb;
        }

        #mainTable {
            position: relative;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: max-content;
            table-layout: auto;
        }

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

        #mainTable tbody td {
            border-right: 1px solid rgba(0, 0, 0, 0.05);
            white-space: nowrap;
            position: relative;
        }

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
    </style>

    {{-- Script principal --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        (function () {
            // =========================
            //   CONFIG / ESTADO
            // =========================
            const CONFIG = {
                columnas: {!! json_encode($columnas ?? []) !!},
                apiUrl: {!! json_encode($apiUrl ?? '/planeacion/codificacion/api/all-fast') !!},
                totalRegistros: {{ isset($totalRegistros) ? (int) $totalRegistros : 0 }},
            };

            const state = {
                data: [],
                filtered: [],
                filtros: [],       // { columna: index, valor: string }
                page: 1,
                perPage: 500,
                total: CONFIG.totalRegistros || 0,
                loading: false,
                selectedRowIndex: null, // Índice global de la fila seleccionada
            };

            // =========================
            //   HELPERS DOM
            // =========================
            const $  = (sel, ctx = document) => ctx.querySelector(sel);
            const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

            const getCsrf = () =>
                document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            function setLoading(isLoading, message = 'Cargando datos...', count = '') {
                state.loading = isLoading;
                const overlay  = $('#loading-overlay');
                const messageEl = $('#loading-message');
                const countEl   = $('#loading-count');

                if (!overlay) return;

                if (messageEl) messageEl.textContent = message;
                if (countEl)   countEl.textContent   = count;

                if (isLoading) {
                    overlay.classList.remove('hidden');
                    overlay.classList.add('flex');
                } else {
                    overlay.classList.add('hidden');
                    overlay.classList.remove('flex');
                }
            }

            // =========================
            //   TOAST SIMPLE (fallback)
            // =========================
            function internalToast(message, type = 'info') {
                const colors = {
                    success: 'bg-green-600',
                    error: 'bg-red-600',
                    warning: 'bg-yellow-500',
                    info: 'bg-blue-600',
                };

                let container = $('#toast-notification');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'toast-notification';
                    container.className = 'fixed top-4 right-4 z-[9999] max-w-sm w-full';
                    document.body.appendChild(container);
                }

                container.innerHTML =
                    '<div class="rounded-lg shadow-lg text-white px-4 py-3 ' + (colors[type] || colors.info) + '">' +
                        '<div class="flex items-center justify-between gap-3">' +
                            '<span class="text-sm">' + message + '</span>' +
                            '<button type="button" class="text-sm font-bold hover:opacity-80" onclick="this.closest(\'#toast-notification\').remove()">' +
                                '&times;' +
                            '</button>' +
                        '</div>' +
                    '</div>';

                setTimeout(() => {
                    container?.remove();
                }, 3500);
            }

            // Si ya existe showToast global, úsalo; si no, define el nuestro
            const showToast = window.showToast || internalToast;
            if (!window.showToast) {
                window.showToast = showToast;
            }

            // =========================
            //   CARGA DE DATOS
            // =========================
            async function loadData() {
                if (!CONFIG.apiUrl || state.loading) return;

                setLoading(true, 'Cargando datos...', '');

                try {
                    const resp = await fetch(CONFIG.apiUrl, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' },
                    });

                    if (!resp.ok) {
                        throw new Error('HTTP ' + resp.status);
                    }

                    const json = await resp.json();

                    if (!json.s) {
                        throw new Error(json.e || 'Error al cargar datos');
                    }

                    const columnas = json.c || CONFIG.columnas;
                    const raw      = json.d || [];
                    const totalRows = raw.length;

                    setLoading(true, 'Procesando registros...', totalRows.toLocaleString() + ' registros');

                    state.data = raw.map(rowArr => {
                        const obj = {};
                        for (let j = 0; j < columnas.length; j++) {
                            obj[columnas[j]] = rowArr[j] ?? null;
                        }
                        return obj;
                    });

                    state.filtered = [...state.data];
                    state.total    = json.t || state.data.length;

                    if (state.filtros.length) {
                        aplicarFiltrosAND();
                    }

                    renderPage();
                    updateFilterCount();

                    setLoading(false);
                } catch (error) {
                    const tbody = $('#catcodificacion-body');
                    if (tbody) {
                        tbody.innerHTML =
                            '<tr>' +
                                '<td colspan="' + CONFIG.columnas.length + '" class="py-16 text-center">' +
                                    '<div class="flex flex-col items-center gap-2">' +
                                        '<i class="fas fa-exclamation-triangle text-red-500 text-3xl"></i>' +
                                        '<p class="text-red-600 font-medium">Error al cargar datos</p>' +
                                        '<p class="text-sm text-gray-500">' + error.message + '</p>' +
                                        '<button type="button" class="mt-2 px-3 py-1.5 text-sm rounded bg-blue-500 text-white hover:bg-blue-600" onclick="loadData()">' +
                                            'Reintentar' +
                                        '</button>' +
                                    '</div>' +
                                '</td>' +
                            '</tr>';
                    }
                    showToast('Error al cargar datos', 'error');
                    setLoading(false);
                }
            }

            // =========================
            //   RENDER / PAGINACIÓN
            // =========================
            function renderPage() {
                const tbody = $('#catcodificacion-body');
                if (!tbody) return;

                const totalCols = CONFIG.columnas.length;
                const data = state.filtered;

                if (!data.length) {
                    tbody.innerHTML =
                        '<tr>' +
                            '<td colspan="' + totalCols + '" class="py-16 text-center">' +
                                '<div class="flex flex-col items-center gap-2">' +
                                    '<i class="fas fa-search text-gray-300 text-4xl"></i>' +
                                    '<p class="text-gray-500 text-sm font-medium">No hay datos para mostrar</p>' +
                                '</div>' +
                            '</td>' +
                        '</tr>';
                    updatePagination();
                    return;
                }

                const startIndex = (state.page - 1) * state.perPage;
                const endIndex   = startIndex + state.perPage;
                const pageData   = data.slice(startIndex, endIndex);

                const fragment = document.createDocumentFragment();

                pageData.forEach((row, rowIndex) => {
                    const globalIndex = startIndex + rowIndex;
                    const tr = document.createElement('tr');
                    tr.className = 'cursor-pointer transition-colors';
                    tr.dataset.index = globalIndex;

                    // Verificar si esta fila está seleccionada
                    const isSelected = state.selectedRowIndex === globalIndex;

                    // Aplicar estilos según selección
                    if (isSelected) {
                        tr.classList.add('bg-blue-500', 'text-white');
                    } else {
                        tr.classList.add('hover:bg-gray-50');
                    }

                    // Evento click para seleccionar/deseleccionar
                    tr.addEventListener('click', () => {
                        // Si ya está seleccionada, deseleccionar
                        if (state.selectedRowIndex === globalIndex) {
                            state.selectedRowIndex = null;
                            tr.classList.remove('bg-blue-500', 'text-white');
                            tr.classList.add('hover:bg-gray-50');
                            tr.querySelectorAll('td').forEach(td => {
                                td.classList.remove('text-white');
                                td.classList.add('text-gray-700');
                            });
                        } else {
                            // Deseleccionar fila anterior si existe
                            const prevSelected = tbody.querySelector('tr.bg-blue-500');
                            if (prevSelected) {
                                prevSelected.classList.remove('bg-blue-500', 'text-white');
                                prevSelected.classList.add('hover:bg-gray-50');
                                prevSelected.querySelectorAll('td').forEach(td => {
                                    td.classList.remove('text-white');
                                    td.classList.add('text-gray-700');
                                });
                            }

                            // Seleccionar nueva fila
                            state.selectedRowIndex = globalIndex;
                            tr.classList.add('bg-blue-500', 'text-white');
                            tr.classList.remove('hover:bg-gray-50');
                            tr.querySelectorAll('td').forEach(td => {
                                td.classList.remove('text-gray-700');
                                td.classList.add('text-white');
                            });
                        }
                    });

                    for (const col of CONFIG.columnas) {
                        const td = document.createElement('td');
                        td.className = 'px-3 py-1.5 border-b border-gray-100 whitespace-nowrap text-[11px] ' +
                            (isSelected ? 'text-white' : 'text-gray-700');
                        const value = row[col] ?? '';
                        td.textContent = value !== null ? String(value) : '';
                        tr.appendChild(td);
                    }

                    fragment.appendChild(tr);
                });

                tbody.innerHTML = '';
                tbody.appendChild(fragment);
                updatePagination();
            }

            function updatePagination() {
                const total      = state.filtered.length;
                const totalPages = Math.max(1, Math.ceil(total / state.perPage));

                state.page = Math.min(state.page, totalPages);

                const start = total ? (state.page - 1) * state.perPage + 1 : 0;
                const end   = total ? Math.min(state.page * state.perPage, total) : 0;

                const currentEl    = $('#pagination-current');
                const totalPagesEl = $('#pagination-total-pages');
                const startEl      = $('#pagination-start');
                const endEl        = $('#pagination-end');
                const totalEl      = $('#pagination-total');

                if (currentEl)    currentEl.textContent    = state.page;
                if (totalPagesEl) totalPagesEl.textContent = totalPages;
                if (startEl)      startEl.textContent      = start.toLocaleString();
                if (endEl)        endEl.textContent        = end.toLocaleString();
                if (totalEl)      totalEl.textContent      = total.toLocaleString();

                const prev = $('#pagination-prev');
                const next = $('#pagination-next');

                if (prev) prev.disabled = state.page <= 1;
                if (next) next.disabled = state.page >= totalPages;
            }

            // =========================
            //   FILTROS
            // =========================
            function aplicarFiltrosAND() {
                if (!state.data.length) {
                    state.filtered = [];
                    return;
                }

                if (!state.filtros.length) {
                    state.filtered = [...state.data];
                } else {
                    state.filtered = state.data.filter(row => {
                        return state.filtros.every(f => {
                            const colName = CONFIG.columnas[f.columna];
                            if (!colName) return true;

                            const cell   = String(row[colName] ?? '').toLowerCase();
                            const needle = f.valor.toLowerCase().trim();
                            return cell.includes(needle);
                        });
                    });
                }

                state.page = 1;
                state.selectedRowIndex = null; // Limpiar selección al filtrar
                renderPage();
                updateFilterCount();
            }

            function updateFilterCount() {
                const counter = document.getElementById('filter-count');
                if (!counter) return;

                const count = state.filtros.length;
                if (count > 0) {
                    counter.textContent = count;
                    counter.classList.remove('hidden');
                } else {
                    counter.classList.add('hidden');
                }
            }

            function filtrarCodificacion() {
                Swal.fire({
                    html: `
                        <div class="text-left">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-base font-semibold text-gray-800">Filtrar datos</h2>
                                <button type="button" id="btn-close-modal" class="text-gray-400 hover:text-red-500 text-xl">&times;</button>
                            </div>

                            <div class="space-y-4">
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <select
                                        id="filtro-columna"
                                        class="flex-1 px-3 py-2 border rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option value="">Columna...</option>
                                    </select>

                                    <input
                                        type="text"
                                        id="filtro-valor"
                                        class="flex-1 px-3 py-2 border rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Valor a buscar..."
                                    >

                                    <button
                                        type="button"
                                        id="btn-add-filter"
                                        class="px-3 py-2 bg-blue-500 text-white rounded-md text-sm hover:bg-blue-600"
                                    >
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>

                                <div id="modal-active-filters" class="space-y-2 hidden">
                                    <p class="text-sm font-semibold text-gray-700">Filtros activos:</p>
                                    <div id="modal-filters-list" class="flex flex-wrap gap-2"></div>
                                </div>

                                <div id="btn-clear-container" class="hidden">
                                    <button
                                        type="button"
                                        id="btn-clear-filters"
                                        class="w-full px-3 py-2 bg-red-500 text-white rounded-md text-sm hover:bg-red-600"
                                    >
                                        Limpiar todos los filtros
                                    </button>
                                </div>
                            </div>
                        </div>
                    `,
                    showConfirmButton: false,
                    showCancelButton: false,
                    width: 560,
                    didOpen: () => {
                        // Poblar select columnas
                        const colSelect = document.getElementById('filtro-columna');
                        if (colSelect) {
                            CONFIG.columnas.forEach((col, idx) => {
                                const option = document.createElement('option');
                                option.value = idx;
                                option.textContent = col;
                                colSelect.appendChild(option);
                            });
                        }

                        const activeFilters = document.getElementById('modal-active-filters');
                        const clearContainer = document.getElementById('btn-clear-container');
                        if (state.filtros.length > 0) {
                            activeFilters?.classList.remove('hidden');
                            clearContainer?.classList.remove('hidden');
                        }

                        const closeBtn = document.getElementById('btn-close-modal');
                        closeBtn?.addEventListener('click', () => Swal.close());

                        document.getElementById('btn-add-filter')?.addEventListener('click', addFilterFromModal);
                        document.getElementById('btn-clear-filters')?.addEventListener('click', () => {
                            state.filtros = [];
                            aplicarFiltrosAND();
                            renderModalFilters();
                            Swal.close();
                        });

                        const valorInput = document.getElementById('filtro-valor');
                        valorInput?.addEventListener('keydown', e => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                addFilterFromModal();
                            }
                        });

                        renderModalFilters();
                    }
                });
            }

            function addFilterFromModal() {
                const colSelect  = document.getElementById('filtro-columna');
                const valueInput = document.getElementById('filtro-valor');
                if (!colSelect || !valueInput) return;

                const columna = parseInt(colSelect.value, 10);
                const valor   = valueInput.value.trim();

                if (Number.isNaN(columna)) {
                    showToast('Selecciona una columna', 'warning');
                    return;
                }
                if (!valor) {
                    showToast('Ingresa un valor para filtrar', 'warning');
                    return;
                }

                const exists = state.filtros.some(
                    f => f.columna === columna && f.valor.toLowerCase() === valor.toLowerCase()
                );
                if (exists) {
                    showToast('Este filtro ya existe', 'warning');
                    return;
                }

                state.filtros.push({ columna, valor });
                valueInput.value = '';
                colSelect.selectedIndex = 0;

                aplicarFiltrosAND();
                renderModalFilters();

                if (!state.filtered.length) {
                    showToast('No se encontraron resultados', 'warning');
                } else {
                    showToast(state.filtered.length + ' registros encontrados', 'success');
                }
            }

            function renderModalFilters() {
                const container = document.getElementById('modal-active-filters');
                const list      = document.getElementById('modal-filters-list');
                const clearBox  = document.getElementById('btn-clear-container');

                if (!container || !list) return;

                if (!state.filtros.length) {
                    container.classList.add('hidden');
                    clearBox?.classList.add('hidden');
                    return;
                }

                container.classList.remove('hidden');
                clearBox?.classList.remove('hidden');

                list.innerHTML = state.filtros.map((filtro, index) => {
                    const colName = CONFIG.columnas[filtro.columna] || 'Columna';
                    return '' +
                        '<span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-[10px]">' +
                            '<span class="font-semibold">' + colName + ':</span>' +
                            '<span>"' + filtro.valor + '"</span>' +
                            '<button type="button" class="ml-1 hover:text-red-600 font-bold" onclick="removeFilterFromModal(' + index + ')">' +
                                '&times;' +
                            '</button>' +
                        '</span>';
                }).join('');
            }

            function removeFilterFromModal(index) {
                state.filtros.splice(index, 1);
                aplicarFiltrosAND();
                renderModalFilters();
                showToast('Filtro eliminado', 'info');
            }

            function limpiarFiltrosCodificacion() {
                state.filtros = [];
                aplicarFiltrosAND();
                showToast('Filtros limpiados', 'info');
            }

            // =========================
            //   EXCEL
            // =========================
            function subirExcelCatCodificacion() {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = '.xlsx,.xls';

                input.onchange = e => {
                    const file = e.target.files?.[0];
                    if (!file) return;

                    const sizeMB = file.size / 1024 / 1024;
                    if (sizeMB > 10) {
                        showToast('El archivo máximo permitido es de 10MB', 'warning');
                        return;
                    }

                    Swal.fire({
                        title: '¿Procesar Excel?',
                        text: file.name + ' (' + sizeMB.toFixed(2) + ' MB)',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Procesar',
                        cancelButtonText: 'Cancelar',
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
                    html: '<p class="text-sm text-gray-600">Estamos procesando tu archivo, esto puede tardar unos minutos.</p>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => Swal.showLoading()
                });

                fetch('/planeacion/codificacion/excel', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': getCsrf(),
                    },
                    body: formData,
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data?.poll_url) {
                        pollImportProgress(data.data.poll_url);
                        return;
                    }

                    Swal.close();
                    showToast(data.message || 'Error al procesar el archivo', 'error');
                })
                .catch(error => {
                    Swal.close();
                    showToast(error.message, 'error');
                });
            }

            function pollImportProgress(url, attempts = 0) {
                if (attempts > 600) {
                    Swal.close();
                    showToast('Tiempo de espera agotado al procesar el archivo', 'warning');
                    return;
                }

                fetch(url)
                    .then(r => r.json())
                    .then(result => {
                        if (!result.success || !result.data) {
                            setTimeout(() => pollImportProgress(url, attempts + 1), 1000);
                            return;
                        }

                        const data     = result.data;
                        const processed = data.processed_rows ?? 0;
                        const total     = data.total_rows ?? '?';
                        const created   = data.created ?? 0;
                        const updated   = data.updated ?? 0;
                        const percent   = result.percent ?? 0;

                        Swal.update({
                            html:
                                '<div class="text-sm text-gray-700 text-left">' +
                                    '<p class="mb-2">Procesando archivo...</p>' +
                                    '<p class="font-semibold mb-2">' + processed + '/' + total + ' filas (' + percent + '%)</p>' +
                                    '<p class="text-sm text-gray-600 mb-1">Creados: ' + created + ' · Actualizados: ' + updated + '</p>' +
                                '</div>'
                        });

                        if (data.status === 'done') {
                            Swal.close();
                            Swal.fire({
                                title: 'Importación completa',
                                icon: 'success',
                                html:
                                    '<div class="text-sm text-gray-700 text-left">' +
                                        '<p>Registros creados: <strong>' + created + '</strong></p>' +
                                        '<p>Registros actualizados: <strong>' + updated + '</strong></p>' +
                                    '</div>',
                            }).then(() => location.reload());
                            return;
                        }

                        setTimeout(() => pollImportProgress(url, attempts + 1), 1000);
                    })
                    .catch(() => {
                        setTimeout(() => pollImportProgress(url, attempts + 1), 1000);
                    });
            }

            // =========================
            //   INIT
            // =========================
            function initPaginationEvents() {
                const prev = $('#pagination-prev');
                const next = $('#pagination-next');

                if (prev) {
                    prev.addEventListener('click', () => {
                        if (state.page > 1 && !state.loading) {
                            state.page--;
                            renderPage();
                        }
                    });
                }

                if (next) {
                    next.addEventListener('click', () => {
                        const totalPages = Math.max(1, Math.ceil(state.filtered.length / state.perPage));
                        if (state.page < totalPages && !state.loading) {
                            state.page++;
                            renderPage();
                        }
                    });
                }
            }

            document.addEventListener('DOMContentLoaded', () => {
                initPaginationEvents();
                loadData();
            });

            // =========================
            //   EXPOSE GLOBAL
            // =========================
            window.subirExcelCatCodificacion   = subirExcelCatCodificacion;
            window.procesarExcel               = procesarExcel;
            window.pollImportProgress          = pollImportProgress;
            window.filtrarCodificacion         = filtrarCodificacion;
            window.limpiarFiltrosCodificacion  = limpiarFiltrosCodificacion;
            window.removeFilterFromModal       = removeFilterFromModal;
            window.loadData                    = loadData;
        })();
    </script>
@endsection
