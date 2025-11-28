@extends('layouts.app')

@section('page-title', 'Catálogo de Codificación')

@section('navbar-right')
<x-buttons.catalog-actions route="codificacion" :showFilters="true" />
@endsection

@section('content')
<div class="container-fluid">
    <div class="bg-white rounded-lg shadow-sm">
        {{-- Contenedor de tabla --}}
        <div class="relative overflow-auto h-[calc(100vh-180px)] min-h-[700px]" id="table-container">
            {{-- Loading inicial --}}
            <div id="loading-overlay" class="absolute inset-0 bg-white bg-opacity-90 flex items-center justify-center z-40">
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-500 border-t-transparent mb-4"></div>
                    <p class="text-gray-600 font-medium">Cargando <span id="loading-count">0</span> registros...</p>
                    <p class="text-sm text-gray-400 mt-1" id="loading-progress"></p>
                </div>
            </div>

            <table id="mainTable" class="w-full border-collapse">
                <thead class="sticky top-0 z-50 bg-blue-500">
                    <tr>
                        @foreach($columnas as $idx => $col)
                            <th class="column-{{ $idx }} px-3 py-2 text-left text-sm font-medium text-white whitespace-nowrap border-b border-blue-400 shadow-sm" data-index="{{ $idx }}">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate">{{ $col }}</span>
                                    <div class="relative">
                                        <button type="button" class="sort-btn sort-btn-asc p-1 bg-blue-600 hover:bg-blue-700 text-white rounded transition-shadow hover:shadow-md" title="Ordenar ascendente" data-sort="asc" data-column="{{ $idx }}">
                                            <i class="fas fa-arrow-up"></i>
                                        </button>
                                        <button type="button" class="sort-btn sort-btn-desc p-1 bg-blue-600 hover:bg-blue-700 text-white rounded transition-shadow hover:shadow-md hidden" title="Ordenar descendente" data-sort="desc" data-column="{{ $idx }}">
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

        {{-- Paginación --}}
        <div id="pagination-container" class="px-4 py-3 border-t border-gray-200 bg-white sticky bottom-0 z-20">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="text-sm text-gray-700">
                    Mostrando <span id="pagination-from" class="font-medium">0</span>
                    a <span id="pagination-to" class="font-medium">0</span>
                    de <span id="pagination-total" class="font-medium">{{ $totalRegistros }}</span> registros
                </div>
                <div class="flex items-center gap-2">
                    <button id="pagination-prev" class="px-3 py-1.5 border rounded text-sm bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors" disabled>
                        <i class="fas fa-chevron-left mr-1"></i> Anterior
                    </button>
                    <span id="pagination-info" class="px-3 py-1 text-sm text-gray-700 font-medium">Página 1</span>
                    <button id="pagination-next" class="px-3 py-1.5 border rounded text-sm bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        Siguiente <i class="fas fa-chevron-right ml-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Configuración desde servidor
const CONFIG = {
    apiUrl: @json($apiUrl),
    columnas: @json($columnasConfig),
    camposModelo: @json(array_keys($camposModelo)),
    tiposCampo: @json($camposModelo),
    totalEstimado: {{ $totalRegistros }}
};

// Estado global optimizado
const state = {
    rawData: [],           // Datos crudos del servidor
    filteredData: [],      // Datos filtrados
    currentPage: 1,
    itemsPerPage: 500,
    currentSort: { col: null, dir: null },
    selectedId: null,
    filtrosDinamicos: [],
    isLoading: true
};

// Utilidades DOM
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

// Utilidades de datos
const utils = {
    formatDate(val) {
        if (!val) return '';
        try {
            const d = new Date(val);
            if (isNaN(d.getTime())) return val;
            return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
        } catch { return val; }
    },
    formatValue(val, type) {
        if (val === null || val === undefined) return '';
        if (type === 'date') return this.formatDate(val);
        if (type === 'zero') return val == 0 ? '' : val;
        return val;
    },
    escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    },
    parseForSort(val, type) {
        if (val === null || val === undefined || val === '') return type === 'number' ? -Infinity : '';
        if (type === 'date') {
            const d = new Date(val);
            return isNaN(d.getTime()) ? 0 : d.getTime();
        }
        if (type === 'number') return parseFloat(String(val).replace(/,/g, '')) || 0;
        return String(val).toLowerCase();
    },
    detectType(values) {
        let nums = 0, dates = 0, total = 0;
        for (const v of values.slice(0, 100)) {
            if (!v) continue;
            total++;
            if (!isNaN(new Date(v).getTime()) && String(v).includes('-')) dates++;
            else if (!isNaN(parseFloat(String(v).replace(/,/g, '')))) nums++;
        }
        if (dates / Math.max(total, 1) > 0.5) return 'date';
        if (nums / Math.max(total, 1) > 0.5) return 'number';
        return 'text';
    }
};

// Toast notifications
function showToast(message, type = 'info') {
    let toast = $('#toast-notification');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast-notification';
        toast.className = 'fixed top-4 right-4 z-[9999] max-w-sm w-full';
        document.body.appendChild(toast);
    }
    const colors = { success: 'bg-green-600', error: 'bg-red-600', warning: 'bg-yellow-600', info: 'bg-blue-600' };
    toast.innerHTML = `
        <div class="${colors[type] || colors.info} text-white px-4 py-3 rounded-lg shadow-lg">
            <div class="flex items-center justify-between gap-4">
                <span class="text-sm">${message}</span>
                <button onclick="this.closest('#toast-notification').remove()" class="opacity-80 hover:opacity-100">×</button>
            </div>
        </div>`;
    setTimeout(() => toast?.remove(), 3500);
}

// Cargar datos via fetch
async function loadData() {
    const loadingEl = $('#loading-overlay');
    const countEl = $('#loading-count');
    const progressEl = $('#loading-progress');

    try {
        progressEl.textContent = '';
        const startTime = performance.now();

        const response = await fetch(CONFIG.apiUrl, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        progressEl.textContent = 'Descargando datos...';
        const result = await response.json();

        if (!result.s) throw new Error(result.e || 'Error desconocido');

        // Convertir array indexado a objetos
        const columns = result.c;
        // Guardar columnas del API para usar en filtros
        CONFIG.apiColumns = columns;

        state.rawData = result.d.map(row => {
            const obj = {};
            columns.forEach((col, i) => obj[col] = row[i]);
            return obj;
        });

        state.filteredData = [...state.rawData];

        const elapsed = ((performance.now() - startTime) / 1000).toFixed(2);
        countEl.textContent = state.rawData.length;
        progressEl.textContent = `${state.rawData.length} registros en ${elapsed}s`;

        // Renderizar primera página
        setTimeout(() => {
            renderPage();
            loadingEl.classList.add('hidden');
            state.isLoading = false;
            showToast(`${state.rawData.length} registros cargados`, 'success');
        }, 100);

    } catch (error) {
        console.error('Error cargando datos:', error);
        loadingEl.innerHTML = `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                <p class="text-red-600 font-medium">Error al cargar datos</p>
                <p class="text-sm text-gray-500 mt-2">${error.message}</p>
                <button onclick="location.reload()" class="mt-4 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Reintentar</button>
            </div>`;
    }
}

// Mostrar/ocultar loading de tabla
function showTableLoading(show) {
    let overlay = $('#table-loading-overlay');

    if (show) {
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'table-loading-overlay';
            overlay.className = 'absolute inset-0 bg-white bg-opacity-80 flex items-center justify-center z-30';
            overlay.innerHTML = `
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-500 border-t-transparent mb-2"></div>
                    <p class="text-gray-600 text-sm">Procesando...</p>
                </div>`;
            $('#table-container').appendChild(overlay);
        }
        overlay.classList.remove('hidden');
    } else if (overlay) {
        overlay.classList.add('hidden');
    }
}

// Renderizar filas de la página actual
function renderPage() {
    const tbody = $('#codificacion-body');
    const totalCols = CONFIG.columnas.length;

    console.log(`renderPage() - filteredData: ${state.filteredData.length}, currentPage: ${state.currentPage}`);

    // Si no hay datos filtrados, mostrar mensaje
    if (!state.filteredData.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="${totalCols}" class="text-center py-16">
                    <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                    <p class="text-gray-500 font-medium">${state.filtrosDinamicos.length ? 'No se encontraron resultados' : 'No hay datos disponibles'}</p>
                    ${state.filtrosDinamicos.length ? '<p class="text-sm text-gray-400 mt-2">Intenta con otros filtros</p>' : ''}
                </td>
            </tr>`;
        updatePagination();
        return;
    }

    const start = (state.currentPage - 1) * state.itemsPerPage;
    const pageData = state.filteredData.slice(start, start + state.itemsPerPage);

    // Mostrar loading durante renderizado grande
    const showLoading = pageData.length > 200;
    if (showLoading) showTableLoading(true);

    // Usar setTimeout para permitir que el loading se muestre
    setTimeout(() => {
        // Usar DocumentFragment para mejor performance
        const fragment = document.createDocumentFragment();

        pageData.forEach(row => {
            const tr = document.createElement('tr');
            const isSelected = row.Id === state.selectedId;
            tr.className = `data-row cursor-pointer transition-colors ${isSelected ? 'bg-blue-500 text-white selected-row' : 'hover:bg-gray-50'}`;
            tr.dataset.id = row.Id;
            tr.onclick = () => selectRow(tr, row.Id);

            // Generar celdas
            CONFIG.camposModelo.forEach((campo, idx) => {
                const td = document.createElement('td');
                td.className = `column-${idx} px-3 py-2 text-sm whitespace-nowrap`;
                td.textContent = utils.formatValue(row[campo], CONFIG.tiposCampo[campo]);
                tr.appendChild(td);
            });

            fragment.appendChild(tr);
        });

        tbody.innerHTML = '';
        tbody.appendChild(fragment);
        updatePagination();

        if (showLoading) showTableLoading(false);
    }, showLoading ? 10 : 0);
}

// Actualizar paginación
function updatePagination() {
    const total = state.filteredData.length;
    const totalPages = Math.ceil(total / state.itemsPerPage) || 1;
    const start = total ? (state.currentPage - 1) * state.itemsPerPage + 1 : 0;
    const end = Math.min(state.currentPage * state.itemsPerPage, total);

    $('#pagination-from').textContent = start;
    $('#pagination-to').textContent = end;
    $('#pagination-total').textContent = total;
    $('#pagination-info').textContent = `Página ${state.currentPage} de ${totalPages}`;
    $('#pagination-prev').disabled = state.currentPage <= 1;
    $('#pagination-next').disabled = state.currentPage >= totalPages;
}

// Navegación de páginas
function goToPage(page) {
    const totalPages = Math.ceil(state.filteredData.length / state.itemsPerPage) || 1;
    if (page < 1 || page > totalPages) return;
    state.currentPage = page;
    renderPage();
    $('#table-container').scrollTop = 0;
}

// Ordenamiento optimizado (sobre datos en memoria)
function sortColumn(colIndex, dir) {
    // Mostrar loading si hay muchos datos
    const needsLoading = state.filteredData.length > 500;
    if (needsLoading) showTableLoading(true);

    // Actualizar botones visuales
    $$('#mainTable thead th .sort-btn').forEach(btn => {
        btn.classList.toggle('hidden', btn.classList.contains('sort-btn-desc'));
    });

    const th = $(`#mainTable thead th[data-index="${colIndex}"]`);
    if (th) {
        const ascBtn = th.querySelector('.sort-btn-asc');
        const descBtn = th.querySelector('.sort-btn-desc');
        if (dir === 'asc') {
            ascBtn?.classList.add('hidden');
            descBtn?.classList.remove('hidden');
        } else {
            descBtn?.classList.add('hidden');
            ascBtn?.classList.remove('hidden');
        }
    }

    setTimeout(() => {
        const campo = CONFIG.camposModelo[colIndex];
        const tipo = CONFIG.tiposCampo[campo];

        // Detectar tipo si no está definido
        const sortType = tipo === 'date' ? 'date' :
                         tipo === 'zero' ? 'number' :
                         utils.detectType(state.filteredData.map(r => r[campo]));

        state.filteredData.sort((a, b) => {
            const va = utils.parseForSort(a[campo], sortType);
            const vb = utils.parseForSort(b[campo], sortType);
            const cmp = va < vb ? -1 : va > vb ? 1 : 0;
            return dir === 'asc' ? cmp : -cmp;
        });

        state.currentSort = { col: colIndex, dir };
    state.currentPage = 1;
        renderPage();

        if (needsLoading) showTableLoading(false);
    }, needsLoading ? 10 : 0);
}

// Selección de fila
function selectRow(row, id) {
    // Remover selección anterior
    $$('#codificacion-body tr.selected-row').forEach(r => {
        r.classList.remove('bg-blue-500', 'selected-row');
        r.classList.add('hover:bg-gray-50');
        r.querySelectorAll('td').forEach(td => {
            td.classList.remove('text-white');
            td.classList.add('text-gray-700');
        });
    });

    // Aplicar selección nueva
    row.classList.remove('hover:bg-gray-50');
    row.classList.add('bg-blue-500', 'selected-row');
    row.querySelectorAll('td').forEach(td => {
        td.classList.remove('text-gray-700');
        td.classList.add('text-white');
    });

    state.selectedId = id;

    const editBtn = $('#btn-editar');
    const deleteBtn = $('#btn-eliminar');
    if (editBtn) editBtn.disabled = false;
    if (deleteBtn) deleteBtn.disabled = false;
}

// CRUD
function agregarCodificacion() {
    window.location.href = '/planeacion/catalogos/codificacion-modelos/create';
}

function editarCodificacion() {
    if (!state.selectedId) return showToast('Selecciona un registro', 'warning');
    window.location.href = `/planeacion/catalogos/codificacion-modelos/${state.selectedId}/edit`;
}

function eliminarCodificacion() {
    if (!state.selectedId) return showToast('Selecciona un registro', 'warning');

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
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').content }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                state.rawData = state.rawData.filter(r => r.Id !== state.selectedId);
                state.filteredData = state.filteredData.filter(r => r.Id !== state.selectedId);
                state.selectedId = null;
                renderPage();
                showToast('Registro eliminado', 'success');
            } else showToast('Error: ' + data.message, 'error');
        })
        .catch(e => showToast('Error: ' + e.message, 'error'));
    });
}

// ===== Sistema de Filtros Simplificado =====

// Aplicar filtros con lógica: OR entre misma columna, AND entre columnas
function applyFilters() {
    console.log('=== applyFilters() LLAMADO ===');
    console.log('Raw data length:', state.rawData.length);
    console.log('Filtros activos:', state.filtrosDinamicos.length);

    if (!state.rawData.length) {
        console.warn('No hay datos cargados aún');
        showToast('Espera a que carguen los datos', 'warning');
        return;
    }

    const needsLoading = state.rawData.length > 500;
    if (needsLoading) showTableLoading(true);

    setTimeout(() => {
        if (!state.filtrosDinamicos.length) {
            console.log('Sin filtros - restaurando todos los datos');
            state.filteredData = [...state.rawData];
            state.currentPage = 1;
            renderPage();
            updateActiveFiltersUI();
            if (needsLoading) showTableLoading(false);
            return;
        }

        console.log('Aplicando filtros:', state.filtrosDinamicos);

        // Aplicar filtros: AND entre filtros de diferentes columnas
        let filtered = [...state.rawData];

        state.filtrosDinamicos.forEach((filtro, filtroIdx) => {
            const campo = CONFIG.camposModelo[filtro.columna];

            if (!campo) {
                console.error(`Campo no encontrado para índice ${filtro.columna}`);
                return;
            }

            const valorBuscado = filtro.valor.toLowerCase().trim();
            const antes = filtered.length;

            filtered = filtered.filter((row, idx) => {
                const valorOriginal = row[campo];
                const valorCelda = String(valorOriginal ?? '').toLowerCase().trim();
                const coincide = valorCelda.includes(valorBuscado);

                // Debug para primer filtro y primeros registros
                if (filtroIdx === 0 && idx < 3) {
                    console.log(`[Filtro ${filtroIdx}] Campo "${campo}": "${valorCelda}" incluye "${valorBuscado}"? ${coincide}`);
                }

                return coincide;
            });

            console.log(`Filtro ${filtroIdx} (${campo}): ${antes} -> ${filtered.length} registros`);
        });

        state.filteredData = filtered;
        console.log(`✓ RESULTADO FINAL: ${state.filteredData.length} de ${state.rawData.length} registros`);

        state.currentPage = 1;
        renderPage();
        updateActiveFiltersUI();
        if (needsLoading) showTableLoading(false);

        if (state.filteredData.length === 0) {
            showToast('No se encontraron resultados', 'warning');
        } else {
            showToast(`${state.filteredData.length} de ${state.rawData.length} registros`, 'success');
        }

        console.log('=== FILTROS APLICADOS ===');
    }, needsLoading ? 10 : 0);
}

// UI de filtros activos
function updateActiveFiltersUI() {
    let container = $('#active-filters-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'active-filters-container';
        container.className = 'px-4 py-2 bg-gray-50 border-b flex flex-wrap gap-2 items-center';
        $('#table-container').parentElement.insertBefore(container, $('#table-container'));
    }

    if (!state.filtrosDinamicos.length) {
        container.classList.add('hidden');
        return;
    }

    container.classList.remove('hidden');
    container.innerHTML = `
        <span class="text-sm text-gray-600 font-medium mr-2">
            <i class="fas fa-filter mr-1"></i>Filtros (${state.filtrosDinamicos.length}):
        </span>
        ${state.filtrosDinamicos.map((f, i) => {
            const colName = CONFIG.columnas[f.columna]?.nombre || 'Columna';
            return `
                <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                    <strong>${colName}:</strong> "${f.valor}"
                    <button onclick="removeFilter(${i})" class="ml-1 hover:text-red-600 font-bold">×</button>
                </span>`;
        }).join('')}
        <button onclick="limpiarFiltrosCodificacion()" class="ml-2 px-2 py-1 text-xs text-red-600 hover:text-red-800 hover:bg-red-50 rounded">
            <i class="fas fa-times mr-1"></i>Limpiar
        </button>`;
}

function removeFilter(index) {
    state.filtrosDinamicos.splice(index, 1);
    applyFilters();
}

// Modal de filtros simplificado
function filtrarCodificacion() {
    Swal.fire({
        html: `
            <div class="relative">
                <!-- Header con X -->
                <div class="flex items-center justify-between mb-4 pb-3">
                    <h2 class="text-lg font-semibold text-gray-800">
                        Filtrar Datos
                    </h2>
                    <button type="button" id="btn-close-modal" class="text-gray-400 hover:text-red-600 text-2xl leading-none">&times;</button>
                </div>

                <!-- Formulario de filtro -->
                <div class="space-y-4">
                    <div class="flex gap-2">
                        <select id="filtro-columna" class="flex-1 px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Columna...</option>
                            ${CONFIG.columnas.map(c => `<option value="${c.index}">${c.nombre}</option>`).join('')}
                        </select>
                        <input type="text" id="filtro-valor" class="flex-1 px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Valor a buscar...">
                        <button type="button" id="btn-add-filter" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>

                    <!-- Filtros activos -->
                    <div id="modal-active-filters" class="${state.filtrosDinamicos.length ? '' : 'hidden'}">
                        <div class="text-sm font-medium text-gray-600 mb-2">
                            Filtros activos: <span id="filter-count" class="bg-blue-500 text-white px-2 py-0.5 rounded-full text-xs">${state.filtrosDinamicos.length}</span>
                        </div>
                        <div id="modal-filters-list" class="flex flex-wrap gap-2"></div>
                    </div>

                    <!-- Botón limpiar -->
                    <div id="btn-clear-container" class="${state.filtrosDinamicos.length ? '' : 'hidden'}">
                        <button type="button" id="btn-clear-filters" class="w-full px-4 py-2 text-red-600 border border-red-300 rounded-lg hover:bg-red-50 transition-colors text-sm">
                            <i class="fas fa-trash mr-2"></i>Limpiar todos los filtros
                        </button>
                    </div>
                </div>
            </div>`,
        width: '550px',
        showConfirmButton: false,
        showCancelButton: false,
        showCloseButton: false,
        didOpen: () => {
            renderModalFilters();

            // Cerrar con X
            $('#btn-close-modal').onclick = () => Swal.close();

            // Agregar filtro
            $('#btn-add-filter').onclick = addFilterFromModal;

            // Agregar con Enter
            $('#filtro-valor').onkeydown = e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addFilterFromModal();
                }
            };

            // Limpiar filtros
            $('#btn-clear-filters').onclick = () => {
                limpiarFiltrosCodificacion();
                Swal.close();
            };

            // Focus en columna
            $('#filtro-columna').focus();
        }
    });
}

function addFilterFromModal() {
    const colSelect = $('#filtro-columna');
    const valInput = $('#filtro-valor');

    const columnaIdx = parseInt(colSelect.value);
    const valor = valInput.value.trim();

    console.log('Agregando filtro - Columna índice:', columnaIdx, 'Valor:', valor);
    console.log('Campo correspondiente:', CONFIG.camposModelo[columnaIdx]);

    if (isNaN(columnaIdx) || columnaIdx < 0) {
        showToast('Selecciona una columna', 'warning');
        colSelect.focus();
        return;
    }

    if (!valor) {
        showToast('Ingresa un valor', 'warning');
        valInput.focus();
        return;
    }

    // Verificar duplicados
    if (state.filtrosDinamicos.some(f => f.columna === columnaIdx && f.valor.toLowerCase() === valor.toLowerCase())) {
        showToast('Este filtro ya existe', 'warning');
        return;
    }

    const nuevoFiltro = { columna: columnaIdx, valor: valor };
    console.log('Nuevo filtro:', nuevoFiltro);

    state.filtrosDinamicos.push(nuevoFiltro);

    // Limpiar inputs
    valInput.value = '';
    colSelect.selectedIndex = 0;
    valInput.focus();

    // Aplicar filtros automáticamente
    console.log('Llamando a applyFilters()...');
    try {
        applyFilters();
    } catch (error) {
        console.error('Error en applyFilters:', error);
    }
    renderModalFilters();
    showToast('Filtro agregado', 'success');
}

function renderModalFilters() {
    const container = $('#modal-active-filters');
    const list = $('#modal-filters-list');
    const counter = $('#filter-count');
    const clearContainer = $('#btn-clear-container');

    if (!container || !list) return;

    if (!state.filtrosDinamicos.length) {
        container.classList.add('hidden');
        if (clearContainer) clearContainer.classList.add('hidden');
        return;
    }

    container.classList.remove('hidden');
    if (clearContainer) clearContainer.classList.remove('hidden');
    if (counter) counter.textContent = state.filtrosDinamicos.length;

    list.innerHTML = state.filtrosDinamicos.map((f, i) => {
        const colName = CONFIG.columnas[f.columna]?.nombre || 'Columna';
        return `
            <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                <strong>${colName}:</strong> "${f.valor}"
                <button onclick="removeFilterFromModal(${i})" class="ml-1 hover:text-red-600 font-bold">&times;</button>
            </span>`;
    }).join('');
}

function removeFilterFromModal(index) {
    state.filtrosDinamicos.splice(index, 1);
    applyFilters();
    renderModalFilters();
}

function limpiarFiltrosCodificacion() {
    const needsLoading = state.rawData.length > 500;
    if (needsLoading) showTableLoading(true);

    setTimeout(() => {
        state.filtrosDinamicos = [];
        state.filteredData = [...state.rawData];
        state.currentPage = 1;
        renderPage();
        updateActiveFiltersUI();
        if (needsLoading) showTableLoading(false);
        showToast('Filtros limpiados', 'info');
    }, needsLoading ? 10 : 0);
}

// Excel upload
function subirExcelCodificacion() {
    const input = Object.assign(document.createElement('input'), { type: 'file', accept: '.xlsx,.xls' });
    input.onchange = e => {
        const file = e.target.files[0];
        if (!file) return;
        if (file.size > 10 * 1024 * 1024) return showToast('Máximo 10MB', 'error');

        Swal.fire({
            title: '¿Procesar Excel?',
            text: `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Procesar'
        }).then(r => r.isConfirmed && procesarExcel(file));
    };
    input.click();
}

function procesarExcel(file) {
    const formData = new FormData();
    formData.append('archivo_excel', file);

    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    fetch('/planeacion/catalogos/codificacion-modelos/excel', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').content },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.data.poll_url) pollImportProgress(data.data.poll_url);
        else Swal.fire({ title: 'Error', text: data.message || 'Error', icon: 'error' });
    })
    .catch(e => Swal.fire({ title: 'Error', text: e.message, icon: 'error' }));
}

function pollImportProgress(url, attempts = 0) {
    if (attempts > 600) return Swal.fire({ title: 'Timeout', icon: 'warning' });

    fetch(url).then(r => r.json()).then(result => {
            if (result.success && result.data) {
                const { status, processed_rows = 0, total_rows = '?', created = 0, updated = 0, errors = 0 } = result.data;
            Swal.update({ html: `${processed_rows}/${total_rows} (${result.percent || 0}%)` });

                if (status === 'done') {
                    Swal.close();
                Swal.fire({ title: '¡Éxito!', text: `Nuevos: ${created}, Actualizados: ${updated}`, icon: 'success' })
                    .then(() => location.reload());
            } else setTimeout(() => pollImportProgress(url, attempts + 1), 1000);
        } else setTimeout(() => pollImportProgress(url, attempts + 1), 1000);
    }).catch(() => setTimeout(() => pollImportProgress(url, attempts + 1), 1000));
}

// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    // Event listeners para botones de ordenamiento
    $$('#mainTable thead th .sort-btn-asc').forEach(btn => {
        btn.onclick = e => { e.stopPropagation(); sortColumn(+btn.dataset.column, 'asc'); };
    });
    $$('#mainTable thead th .sort-btn-desc').forEach(btn => {
        btn.onclick = e => { e.stopPropagation(); sortColumn(+btn.dataset.column, 'desc'); };
    });

    // Event listeners para paginación
    $('#pagination-prev').onclick = () => goToPage(state.currentPage - 1);
    $('#pagination-next').onclick = () => goToPage(state.currentPage + 1);

    // Cargar datos
    loadData();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection
