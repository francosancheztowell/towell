@extends('layouts.app')

@section('page-title', 'Alta de Pronósticos')

@section('navbar-right')
    <button id="btnProgramar" type="button"
            class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-gray-400 hover:bg-gray-500 cursor-not-allowed mr-2 disabled:opacity-50 disabled:cursor-not-allowed"
            title="Programar" disabled>
        <i class="fa-solid fa-calendar-check mr-2"></i>
        Programar
    </button>
    <button id="btnFiltros" type="button"
            class="inline-flex items-center justify-center w-9 h-9 text-base rounded-full text-white bg-blue-600 hover:bg-blue-700"
            title="Filtros">
        <i class="fa-solid fa-filter"></i>
    </button>
    <button id="btnRestablecer" type="button"
            class="inline-flex items-center justify-center w-9 h-9 text-base rounded-full text-white bg-gray-600 hover:bg-gray-700 ml-2"
            title="Restablecer">
        <i class="fa-solid fa-rotate"></i>
    </button>
@endsection

@section('content')
<div class="w-full px-0 py-0">
    <div class="bg-white shadow overflow-hidden w-full">
        <!-- Loading State -->
        <div id="loadingState" class="flex flex-col items-center justify-center py-20 w-full">
            <div class="animate-spin rounded-full h-16 w-16 border-4 border-blue-200 border-t-blue-600 mb-4"></div>
            <p class="text-gray-600 text-base font-medium">Cargando pronósticos...</p>
        </div>

        <!-- Tabla Unificada -->
        <div id="tableContainer" class="overflow-x-auto hidden w-full">
            <div class="overflow-y-auto" style="max-height: 600px;">
                <table class="w-full divide-y divide-gray-200 text-xs leading-tight" id="tablaPronosticos" style="table-layout: fixed; width: 100%;">
                    <thead class="bg-blue-500 text-white sticky top-0 z-10">
                        <tr>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap cursor-pointer hover:bg-blue-600" style="width: 12%;" onclick="toggleSort('flog')">
                                Flog <i id="sortIcon-flog" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap cursor-pointer hover:bg-blue-600" style="width: 10%;" onclick="toggleSort('proyecto')">
                                Proyecto <i id="sortIcon-proyecto" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap cursor-pointer hover:bg-blue-600" style="width: 8%;" onclick="toggleSort('cliente')">
                                Cliente <i id="sortIcon-cliente" class="fa-solid fa-sort-up ml-1 text-xs"></i>
                            </th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap cursor-pointer hover:bg-blue-600" style="width: 6%;" onclick="toggleSort('calidad')">
                                Calidad <i id="sortIcon-calidad" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap cursor-pointer hover:bg-blue-600" style="width: 5%;" onclick="toggleSort('ancho')">
                                Ancho <i id="sortIcon-ancho" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap cursor-pointer hover:bg-blue-600" style="width: 5%;" onclick="toggleSort('largo')">
                                Largo <i id="sortIcon-largo" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap cursor-pointer hover:bg-blue-600" style="width: 6%;" onclick="toggleSort('articulo')">
                                Artículo <i id="sortIcon-articulo" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap cursor-pointer hover:bg-blue-600" style="width: 10%;" onclick="toggleSort('nombre')">
                                Nombre <i id="sortIcon-nombre" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap cursor-pointer hover:bg-blue-600" style="width: 5%;" onclick="toggleSort('tamano')">
                                Tamaño <i id="sortIcon-tamano" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap cursor-pointer hover:bg-blue-600" style="width: 6%;" onclick="toggleSort('razurado')">
                                Rasurado <i id="sortIcon-razurado" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap cursor-pointer hover:bg-blue-600" style="width: 6%;" onclick="toggleSort('tipohilo')">
                                Hilo <i id="sortIcon-tipohilo" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap cursor-pointer hover:bg-blue-600" style="width: 7%;" onclick="toggleSort('valoragregado')">
                                V.Agreg. <i id="sortIcon-valoragregado" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-1 py-2 text-right font-semibold whitespace-nowrap cursor-pointer hover:bg-blue-600" style="width: 6%;" onclick="toggleSort('cantidad')">
                                Cantidad <i id="sortIcon-cantidad" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap cursor-pointer hover:bg-blue-600" style="width: 7%;" onclick="toggleSort('cancelacion')">
                                Cancelación <i id="sortIcon-cancelacion" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="tablaBody" class="bg-white divide-y divide-gray-200"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // ---------- Estado UI ----------
    const tablaBody = document.getElementById('tablaBody');
    const loadingState = document.getElementById('loadingState');
    const tableContainer = document.getElementById('tableContainer');
    const btnProgramar = document.getElementById('btnProgramar');

    // ---------- Ordenamiento / Datos ----------
    let sortOrder = 'asc';
    let sortColumn = 'cliente';
    let currentData = [];
    let selectedRow = null;
    let selectedRowData = null;

    // Mapa único de columnas -> propiedades
    const columnMap = {
        flog: 'IDFLOG',
        proyecto: 'NOMBREPROYECTO',
        cliente: 'CUSTNAME',
        calidad: 'CATEGORIACALIDAD',
        ancho: 'ANCHO',
        largo: 'LARGO',
        articulo: 'ITEMID',
        nombre: 'ITEMNAME',
        tamano: 'INVENTSIZEID',
        razurado: 'RASURADOCRUDO', // si no hay, se usará RASURADO
        tipohilo: 'TIPOHILOID',
        valoragregado: 'VALORAGREGADO',
        cantidad: 'CANTIDAD',
        cancelacion: 'FECHACANCELACION'
    };

    // ---------- Utils ----------
    const fmt0 = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 0 });
    const fmt2 = new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function number_format(num, decimals) {
        if (typeof num !== 'number' || isNaN(num)) return '';
        return decimals === 0 ? fmt0.format(num) : fmt2.format(num);
    }

    function formatRazurado(valor) {
        if (valor === null || valor === undefined || valor === '') return '';
        const num = parseInt(valor);
        if (num === 0) return 'NA';
        if (num === 1) return 'Normal';
        if (num === 2) return 'Premium';
        return String(valor);
    }

    function showLoading() {
        loadingState.classList.remove('hidden');
        tableContainer.classList.add('hidden');
    }

    function hideLoading() {
        loadingState.classList.add('hidden');
        tableContainer.classList.remove('hidden');
    }

    function td(txt, { numeric = false, right = false, decimals = 2 } = {}) {
        const d = document.createElement('td');
        d.className = 'px-1 py-2 whitespace-nowrap truncate text-gray-700';
        if (right) d.classList.add('text-right');

        if (numeric && txt !== null && txt !== '' && txt !== undefined) {
            const num = Number(txt);
            d.textContent = isNaN(num) ? '' : number_format(num, decimals);
        } else {
            d.textContent = (txt ?? '').toString();
        }
        d.title = d.textContent;
        return d;
    }

    function getCantidad(item) {
        return item.CANTIDAD ?? 0;
    }

    function renderRows(data) {
        tablaBody.innerHTML = '';
        const frag = document.createDocumentFragment();

        data.forEach((x, index) => {
            const tr = document.createElement('tr');
            tr.className = 'select-row cursor-pointer even:bg-gray-50 hover:bg-blue-50 transition-colors';
            tr.dataset.index = index;
            tr.onclick = () => selectRow(tr, x, index);

            // 1. Flog
            frag.appendChild(
                tr.appendChild(td(x.IDFLOG ?? '')) && tr
            );

            // 2. Proyecto
            tr.appendChild(td(x.NOMBREPROYECTO ?? ''));

            // 3. Cliente
            tr.appendChild(td(x.CUSTNAME ?? ''));

            // 4. Calidad
            tr.appendChild(td(x.CATEGORIACALIDAD ?? ''));

            // 5. Ancho
            tr.appendChild(td(x.ANCHO, { numeric: true }));

            // 6. Largo
            tr.appendChild(td(x.LARGO, { numeric: true }));

            // 7. Artículo
            tr.appendChild(td(x.ITEMID ?? ''));

            // 8. Nombre
            tr.appendChild(td(x.ITEMNAME ?? ''));

            // 9. Tamaño
            tr.appendChild(td(x.INVENTSIZEID ?? ''));

            // 10. Rasurado (normales usan RASURADOCRUDO, batas usan RASURADO)
            const razuradoValor = formatRazurado(x.RASURADOCRUDO ?? x.RASURADO);
            tr.appendChild(td(razuradoValor));

            // 11. Tipo Hilo
            tr.appendChild(td(x.TIPOHILOID ?? ''));

            // 12. Valor Agregado
            tr.appendChild(td(x.VALORAGREGADO ?? ''));

            // 13. Cantidad (entero)
            tr.appendChild(td(getCantidad(x), { numeric: true, right: true, decimals: 0 }));

            // 14. Cancelación (fecha local)
            const fechaCancel = x.FECHACANCELACION ? new Date(x.FECHACANCELACION).toLocaleDateString('es-ES') : '';
            tr.appendChild(td(fechaCancel));

            frag.appendChild(tr);
        });

        tablaBody.appendChild(frag);
    }

    function updateSortIcons(activeColumn) {
        const columns = ['flog','proyecto','cliente','calidad','ancho','largo','articulo','nombre','tamano','razurado','tipohilo','valoragregado','cantidad','cancelacion'];
        columns.forEach(col => {
            const icon = document.getElementById(`sortIcon-${col}`);
            if (!icon) return;
            icon.className = (col === activeColumn)
                ? (sortOrder === 'asc' ? 'fa-solid fa-sort-up ml-1 text-xs' : 'fa-solid fa-sort-down ml-1 text-xs')
                : 'fa-solid fa-sort ml-1 text-xs';
        });
    }

    function toggleSort(column) {
        if (!columnMap[column]) return;

        if (sortColumn === column) {
            sortOrder = (sortOrder === 'asc') ? 'desc' : 'asc';
        } else {
            sortColumn = column;
            sortOrder = 'asc';
        }

        updateSortIcons(column);

        const sorted = [...currentData].sort((a, b) => {
            let valA, valB;

            // Mapeo de propiedad
            const prop = columnMap[column];

            if (column === 'razurado') {
                valA = formatRazurado(a.RASURADOCRUDO ?? a.RASURADO ?? '');
                valB = formatRazurado(b.RASURADOCRUDO ?? b.RASURADO ?? '');
            } else {
                valA = a[prop] ?? '';
                valB = b[prop] ?? '';
            }

            // Numéricos
            if (['ancho','largo','cantidad'].includes(column)) {
                const numA = parseFloat(valA) || 0;
                const numB = parseFloat(valB) || 0;
                return sortOrder === 'asc' ? (numA - numB) : (numB - numA);
            }

            // Fechas
            if (column === 'cancelacion') {
                const dateA = valA ? new Date(valA).getTime() : 0;
                const dateB = valB ? new Date(valB).getTime() : 0;
                return sortOrder === 'asc' ? (dateA - dateB) : (dateB - dateA);
            }

            // Texto
            const strA = String(valA || '').toUpperCase();
            const strB = String(valB || '').toUpperCase();
            const cmp = strA.localeCompare(strB);
            return sortOrder === 'asc' ? cmp : -cmp;
        });

        deselectRow();
        currentData = sorted;
        renderRows(sorted);
    }

    // Exponer toggleSort global para los onclick del thead
    window.toggleSort = toggleSort;

    function selectRow(rowElement, rowData) {
        if (selectedRow === rowElement) {
            deselectRow();
            return;
        }
        if (selectedRow) {
            selectedRow.classList.remove('bg-blue-500', 'text-white');
            selectedRow.classList.add('even:bg-gray-50');
            selectedRow.querySelectorAll('td').forEach(cell => {
                cell.classList.remove('text-white');
                cell.classList.add('text-gray-700');
            });
        }

        selectedRow = rowElement;
        selectedRowData = rowData;

        rowElement.classList.add('bg-blue-500', 'text-white');
        rowElement.classList.remove('even:bg-gray-50', 'hover:bg-blue-50');
        rowElement.querySelectorAll('td').forEach(cell => {
            cell.classList.remove('text-gray-700');
            cell.classList.add('text-white');
        });

        if (btnProgramar) {
            btnProgramar.disabled = false;
            btnProgramar.classList.remove('bg-gray-400','hover:bg-gray-500','cursor-not-allowed');
            btnProgramar.classList.add('bg-blue-600','hover:bg-blue-700','cursor-pointer');
        }
    }

    function deselectRow() {
        if (selectedRow) {
            selectedRow.classList.remove('bg-blue-500', 'text-white');
            selectedRow.classList.add('even:bg-gray-50');
            selectedRow.querySelectorAll('td').forEach(cell => {
                cell.classList.remove('text-white');
                cell.classList.add('text-gray-700');
            });
        }
        selectedRow = null;
        selectedRowData = null;

        if (btnProgramar) {
            btnProgramar.disabled = true;
            btnProgramar.classList.remove('bg-blue-600','hover:bg-blue-700','cursor-pointer');
            btnProgramar.classList.add('bg-gray-400','hover:bg-gray-500','cursor-not-allowed');
        }
    }

    // ---------- Filtros ----------
    let filtrosActivos = [];
    const columnasFiltros = {
        flog: 'Flog',
        proyecto: 'Proyecto',
        cliente: 'Cliente',
        calidad: 'Calidad',
        ancho: 'Ancho',
        largo: 'Largo',
        articulo: 'Artículo',
        nombre: 'Nombre',
        tamano: 'Tamaño',
        razurado: 'Razurado',
        tipohilo: 'Tipo Hilo',
        valoragregado: 'Valor Agregado',
        cancelacion: 'Cancelación',
        cantidad: 'Cantidad',
    };

    function aplicarFiltros() {
        if (filtrosActivos.length === 0) {
            renderRows(currentData);
            return;
        }
        const datosFiltrados = currentData.filter(item =>
            filtrosActivos.every(filtro => {
                const prop = columnMap[filtro.columna];
                if (!prop) return true;

                let valor;
                if (filtro.columna === 'razurado') {
                    valor = formatRazurado(item.RASURADOCRUDO ?? item.RASURADO ?? '');
                } else {
                    valor = item[prop] ?? '';
                }

                const valorTexto = String(valor).toLowerCase();
                const filtroTexto = filtro.valor.toLowerCase().trim();
                if (filtroTexto === '') return true;
                return valorTexto.includes(filtroTexto);
            })
        );
        renderRows(datosFiltrados);
    }

    function mostrarModalFiltros() {
        let filtrosHTML = filtrosActivos.map((filtro, index) => `
            <div class="flex gap-2 items-end mb-3" data-filtro-index="${index}">
                <select class="flex-1 border rounded px-2 py-1.5 text-sm" data-columna>
                    <option value="">Seleccione columna...</option>
                    ${Object.entries(columnasFiltros).map(([key, label]) =>
                        `<option value="${key}" ${filtro.columna === key ? 'selected' : ''}>${label}</option>`
                    ).join('')}
                </select>
                <input type="text" class="flex-1 border rounded px-2 py-1.5 text-sm"
                       value="${(filtro.valor || '').replace(/"/g, '&quot;')}"
                       data-valor
                       placeholder="Valor a buscar...">
                <button type="button" class="btn-eliminar-filtro px-3 py-1.5 bg-red-600 text-white rounded hover:bg-red-700 text-sm" data-index="${index}">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        `).join('');

        if (filtrosHTML === '') {
            filtrosHTML = '<p class="text-gray-500 text-sm text-center py-2">No hay filtros activos</p>';
        }

        const html = `
            <div class="text-left">
                <div id="filtrosContainer" class="space-y-2 max-h-64 overflow-y-auto mb-3">
                    ${filtrosHTML}
                </div>
                <button type="button" id="btnAgregarFiltro"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                    <i class="fa-solid fa-plus mr-1"></i> Agregar filtro
                </button>
            </div>
        `;

        Swal.fire({
            title: 'Filtros',
            html: html,
            width: 600,
            showCancelButton: true,
            confirmButtonText: 'Aplicar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6b7280',
            didOpen: () => {
                const container = document.getElementById('filtrosContainer');
                const btnAgregar = document.getElementById('btnAgregarFiltro');

                btnAgregar.addEventListener('click', () => {
                    const nuevoFiltro = document.createElement('div');
                    nuevoFiltro.className = 'flex gap-2 items-end mb-3';
                    const nuevoIndex = container.querySelectorAll('[data-filtro-index]').length;
                    nuevoFiltro.dataset.filtroIndex = nuevoIndex;
                    nuevoFiltro.innerHTML = `
                        <select class="flex-1 border rounded px-2 py-1.5 text-sm" data-columna>
                            <option value="">Seleccione columna...</option>
                            ${Object.entries(columnasFiltros).map(([key, label]) =>
                                `<option value="${key}">${label}</option>`
                            ).join('')}
                        </select>
                        <input type="text" class="flex-1 border rounded px-2 py-1.5 text-sm" data-valor placeholder="Valor a buscar...">
                        <button type="button" class="btn-eliminar-filtro px-3 py-1.5 bg-red-600 text-white rounded hover:bg-red-700 text-sm" data-index="${nuevoIndex}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    `;
                    container.appendChild(nuevoFiltro);

                    nuevoFiltro.querySelector('.btn-eliminar-filtro').addEventListener('click', function() {
                        nuevoFiltro.remove();
                        Array.from(container.querySelectorAll('[data-filtro-index]')).forEach((div, idx) => {
                            div.dataset.filtroIndex = idx;
                            const btn = div.querySelector('.btn-eliminar-filtro');
                            if (btn) btn.dataset.index = idx;
                        });
                    });
                });

                container.querySelectorAll('.btn-eliminar-filtro').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const filtroDiv = this.closest('[data-filtro-index]');
                        if (filtroDiv) {
                            filtroDiv.remove();
                            Array.from(container.querySelectorAll('[data-filtro-index]')).forEach((div, idx) => {
                                div.dataset.filtroIndex = idx;
                                const btn = div.querySelector('.btn-eliminar-filtro');
                                if (btn) btn.dataset.index = idx;
                            });
                        }
                    });
                });
            },
            preConfirm: () => {
                const container = document.getElementById('filtrosContainer');
                const filtrosDivs = container.querySelectorAll('[data-filtro-index]');
                filtrosActivos = Array.from(filtrosDivs).map(div => ({
                    columna: div.querySelector('[data-columna]').value,
                    valor: div.querySelector('[data-valor]').value
                })).filter(f => f.columna !== '' && f.valor.trim() !== '');
                return true;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                aplicarFiltros();
                Swal.fire({
                    icon: 'success',
                    title: 'Filtros aplicados',
                    text: `${filtrosActivos.length} filtro(s) activo(s)`,
                    toast: true,
                    position: 'top-end',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    }

    // ---------- Carga datos ----------
    async function cargarPronosticos() {
        showLoading();

        try {
            const params = new URLSearchParams();
            const urlParams = new URLSearchParams(window.location.search);

            const mesesPHP = @json($meses ?? []);
            const mesActualPHP = @json($mesActual ?? null);

            if (Array.isArray(mesesPHP) && mesesPHP.length > 0) {
                mesesPHP.forEach(m => params.append('meses[]', m));
            } else {
                const mesesDesdeUrl = urlParams.getAll('meses[]');
                const mesSimple = urlParams.get('meses');
                if (mesesDesdeUrl.length > 0) {
                    mesesDesdeUrl.forEach(m => params.append('meses[]', m));
                } else if (mesSimple) {
                    params.set('meses', mesSimple);
                } else if (mesActualPHP) {
                    params.set('meses', mesActualPHP);
                }
            }

            const res = await fetch(`{{ route('pronosticos.get') }}?` + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                method: 'GET',
            });

            if (!res.ok) {
                console.error('❌ Error en respuesta:', res.status, await res.text());
                pintar([], []);
                hideLoading();
                return;
            }

            const data = await res.json();
            pintar(data.otros ?? [], data.batas ?? []);
            // Orden inicial por cliente (ASC) e ícono actualizado
            updateSortIcons('cliente');

        } catch (err) {
            console.error('💥 Error al cargar pronósticos:', err);
            pintar([], []);
        } finally {
            hideLoading();
        }
    }

    function pintar(otros, batas) {
        tablaBody.innerHTML = '';

        const todos = [
            ...(Array.isArray(otros) ? otros.map(x => ({ ...x, esBata: false })) : []),
            ...(Array.isArray(batas) ? batas.map(x => ({ ...x, esBata: true })) : []),
        ];

        currentData = todos;

        if (todos.length === 0) {
            const tr = document.createElement('tr');
            const tdEmpty = document.createElement('td');
            tdEmpty.className = 'px-6 py-10 text-center';
            tdEmpty.colSpan = 15;
            tdEmpty.innerHTML = `
                <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay registros</h3>
                <p class="mt-1 text-sm text-gray-500">No se encontraron pronósticos.</p>
            `;
            tr.appendChild(tdEmpty);
            tablaBody.appendChild(tr);
            return;
        }

        // Orden inicial por cliente asc
        const sorted = [...todos].sort((a, b) => (a.CUSTNAME || '').localeCompare(b.CUSTNAME || ''));
        renderRows(sorted);
    }

    // ---------- Botones / Eventos ----------
    document.addEventListener('DOMContentLoaded', function() {
        // Botón Programar (lógica original conservada con pequeños ajustes)
        if (btnProgramar) {
            btnProgramar.onclick = async () => {
                if (!selectedRowData) {
                    Swal.fire({ icon: 'warning', title: 'Selecciona una fila', text: 'Por favor selecciona una fila para programar', confirmButtonText: 'OK' });
                    return;
                }

                const tamano = selectedRowData.INVENTSIZEID || '';
                const articulo = selectedRowData.ITEMID || '';

                const html = `
                    <div class="text-left text-sm">
                        <div class="mb-4">
                            <div class="space-y-2">
                                <div>
                                    <div class="text-xs text-gray-500 mb-1">Tamaño</div>
                                    <div class="p-2 border rounded bg-gray-50">${tamano}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 mb-1">Artículo</div>
                                    <div class="p-2 border rounded bg-gray-50">${articulo}</div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Salón</label>
                            <select id="swal-salon" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">Seleccione salón...</option>
                                <option value="SMIT">SMIT</option>
                                <option value="JACQUARD">JACQUARD</option>
                                <option value="SULZER">SULZER</option>
                            </select>
                        </div>
                        <div class="mb-1 relative">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Clave modelo</label>
                            <input id="swal-clave-modelo" type="text" placeholder="Escriba la clave..." class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" autocomplete="off" />
                            <div id="swal-clave-suggest" class="absolute left-0 right-0 mt-1 bg-white border border-gray-300 rounded shadow-lg hidden max-h-48 overflow-y-auto z-50"></div>
                            <div id="swal-clave-error" class="mt-1 text-xs text-red-600 hidden"></div>
                        </div>
                    </div>
                `;

                const swalRes = await Swal.fire({
                    title: 'Programar alta',
                    html,
                    width: 600,
                    focusConfirm: false,
                    showCancelButton: true,
                    confirmButtonText: 'Continuar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#6b7280',
                    didOpen: () => {
                        const salonSelect = document.getElementById('swal-salon');
                        const claveInput = document.getElementById('swal-clave-modelo');
                        const suggest = document.getElementById('swal-clave-suggest');
                        const errorMsg = document.getElementById('swal-clave-error');

                        const renderSuggest = (items) => {
                            if (!items || items.length === 0) {
                                suggest.classList.add('hidden');
                                suggest.innerHTML = '';
                                return;
                            }
                            suggest.innerHTML = items.map(it => {
                                const clave = (it.TamanoClave || (it.InventSizeId || '') + (it.ItemId || '')).toString();
                                const nombre = it.Nombre || it.ItemName || '';
                                return `<div class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-sm border-b border-gray-100 last:border-b-0" data-clave="${clave.replace(/"/g, '&quot;')}">
                                    <div class="font-medium text-gray-900">${clave}</div>
                                    <div class="text-xs text-gray-500">${nombre}</div>
                                </div>`;
                            }).join('');
                            suggest.classList.remove('hidden');
                            Array.from(suggest.children).forEach(div => {
                                div.addEventListener('click', () => {
                                    claveInput.value = div.getAttribute('data-clave') || '';
                                    suggest.classList.add('hidden');
                                    claveInput.focus();
                                });
                            });
                        };

                        const doFetch = async (q) => {
                            try {
                                if (!q || q.trim().length < 1) {
                                    renderSuggest([]);
                                    return;
                                }
                                const salon = salonSelect.value;
                                const url = new URL('{{ route("planeacion.buscar-modelos-sugerencias") }}', window.location.origin);
                                url.searchParams.set('q', q.trim());
                                if (salon) url.searchParams.set('salon_tejido_id', salon);
                                const response = await fetch(url.toString());
                                if (!response.ok) return renderSuggest([]);
                                const data = await response.json();
                                if (Array.isArray(data)) renderSuggest(data);
                                else if (data?.error) renderSuggest([]);
                                else renderSuggest([data]);
                            } catch { renderSuggest([]); }
                        };

                        let timer = null;
                        claveInput.addEventListener('input', () => {
                            clearTimeout(timer);
                            errorMsg.classList.add('hidden');
                            errorMsg.textContent = '';
                            claveInput.classList.remove('border-red-500');
                            const val = claveInput.value.trim();
                            if (val.length < 1) return renderSuggest([]);
                            timer = setTimeout(() => doFetch(val), 300);
                        });

                        claveInput.addEventListener('blur', () => setTimeout(() => suggest.classList.add('hidden'), 200));
                        claveInput.addEventListener('focus', () => {
                            if (claveInput.value.trim().length >= 1) doFetch(claveInput.value.trim());
                        });

                        const buscarClaveModelo = async () => {
                            const salon = salonSelect.value;
                            if (!salon || !tamano || !articulo) {
                                if (!salon) claveInput.value = '';
                                return;
                            }
                            claveInput.value = '';
                            suggest.classList.add('hidden');
                            errorMsg.classList.add('hidden');
                            errorMsg.textContent = '';
                            try {
                                const url = new URL('{{ route("planeacion.buscar-detalle-modelo") }}', window.location.origin);
                                url.searchParams.set('itemid', articulo);
                                url.searchParams.set('inventsizeid', tamano);
                                url.searchParams.set('salon_tejido_id', salon);
                                const response = await fetch(url.toString());
                                if (response.ok) {
                                    const data = await response.json();
                                    if (!data.error && data.TamanoClave) {
                                        claveInput.value = data.TamanoClave;
                                        errorMsg.classList.add('hidden');
                                    } else if (!data.error) {
                                        const clave = ((data.InventSizeId || tamano) + (data.ItemId || articulo))
                                            .toUpperCase().replace(/[\s\-_]+/g, '');
                                        claveInput.value = clave;
                                        errorMsg.classList.add('hidden');
                                    } else {
                                        errorMsg.textContent = `No se encontró un modelo para el artículo ${articulo}, tamaño ${tamano} en el salón ${salon}. Ingrese la clave manualmente.`;
                                        errorMsg.classList.remove('hidden');
                                        claveInput.classList.add('border-red-500');
                                    }
                                } else {
                                    errorMsg.textContent = `No se encontró un modelo para el artículo ${articulo}, tamaño ${tamano} en el salón ${salon}. Ingrese la clave manualmente.`;
                                    errorMsg.classList.remove('hidden');
                                    claveInput.classList.add('border-red-500');
                                }
                            } catch {
                                errorMsg.textContent = 'Ocurrió un error al buscar el modelo. Intente nuevamente.';
                                errorMsg.classList.remove('hidden');
                                claveInput.classList.add('border-red-500');
                            }
                        };

                        salonSelect.addEventListener('change', buscarClaveModelo);
                    },
                    preConfirm: async () => {
                        const salon = document.getElementById('swal-salon').value;
                        const claveModelo = document.getElementById('swal-clave-modelo').value.trim();

                        if (!salon) { Swal.showValidationMessage('Por favor seleccione un salón'); return false; }
                        if (!claveModelo) { Swal.showValidationMessage('Por favor ingrese una clave modelo'); return false; }

                        try {
                            Swal.showLoading();
                            const searchUrl = new URL('{{ route("planeacion.buscar-detalle-modelo") }}', window.location.origin);
                            searchUrl.searchParams.set('concatena', claveModelo);
                            searchUrl.searchParams.set('salon_tejido_id', salon);
                            if (articulo) searchUrl.searchParams.set('itemid', articulo);
                            if (tamano) searchUrl.searchParams.set('inventsizeid', tamano);
                            const response = await fetch(searchUrl.toString());
                            const data = await response.json();
                            if (response.status === 404 || data?.error) {
                                Swal.hideLoading();
                                Swal.showValidationMessage('La clave modelo no existe o no está disponible para este salón');
                                return false;
                            }
                            Swal.hideLoading();
                            return { salon, claveModelo, tamano, articulo, datos: selectedRowData, modeloData: data };
                        } catch {
                            Swal.hideLoading();
                            Swal.showValidationMessage('Error al validar la clave modelo');
                            return false;
                        }
                    }
                });

                if (swalRes.isConfirmed && swalRes.value) {
                    const { salon, claveModelo, tamano, articulo, datos } = swalRes.value;
                    const url = new URL('{{ route("programa-tejido.pronosticos.nuevo") }}', window.location.origin);
                    if (datos?.IDFLOG) url.searchParams.set('idflog', datos.IDFLOG);
                    if (articulo) url.searchParams.set('itemid', articulo);
                    if (tamano) url.searchParams.set('inventsizeid', tamano);
                    if (datos?.CANTIDAD) url.searchParams.set('cantidad', datos.CANTIDAD);
                    if (datos?.TIPOHILOID) url.searchParams.set('tipohilo', datos.TIPOHILOID);
                    if (salon) url.searchParams.set('salon', salon);
                    if (claveModelo) url.searchParams.set('clavemodelo', claveModelo);
                    if (datos?.CUSTNAME) url.searchParams.set('custname', datos.CUSTNAME);
                    if (datos?.ESTADO) url.searchParams.set('estado', datos.ESTADO);
                    if (datos?.NOMBREPROYECTO) url.searchParams.set('nombreproyecto', datos.NOMBREPROYECTO);
                    if (datos?.CATEGORIACALIDAD) url.searchParams.set('categoriacalidad', datos.CATEGORIACALIDAD);
                    window.location.href = url.toString();
                }
            };
        }

        const btnFiltros = document.getElementById('btnFiltros');
        if (btnFiltros) btnFiltros.onclick = () => mostrarModalFiltros();

        const btnRestablecer = document.getElementById('btnRestablecer');
        if (btnRestablecer) {
            btnRestablecer.onclick = () => {
                filtrosActivos = [];
                deselectRow();
                aplicarFiltros();
                Swal.fire({
                    icon: 'success',
                    title: 'Filtros restablecidos',
                    toast: true,
                    position: 'top-end',
                    timer: 2000,
                    showConfirmButton: false
                });
            };
        }

        cargarPronosticos();
    });
})();
</script>
@endsection
