@extends('layouts.app')

@section('page-title', 'Alta de Pronósticos')

@section('navbar-right')
    <x-navbar.button-create id="btnProgramar" onclick="handleProgramarPronosticos()" title="Programar" module="Programa Tejido" icon="fa-calendar-check" bg="bg-gray-400" iconColor="text-white" hoverBg="hover:bg-gray-500" disabled />
    <button id="btnFiltros" type="button" class="inline-flex items-center justify-center w-9 h-9 text-base rounded-full text-white bg-blue-600 hover:bg-blue-700" title="Filtros">
        <i class="fa-solid fa-filter"></i>
    </button>
    <button id="btnRestablecer" type="button" class="inline-flex items-center justify-center w-9 h-9 text-base rounded-full text-white bg-gray-600 hover:bg-gray-700 ml-2" title="Restablecer">
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
                    <tbody id="tablaBody" class="bg-white divide-y divide-gray-200">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const tablaBody = document.getElementById('tablaBody');
    const loadingState = document.getElementById('loadingState');
    const tableContainer = document.getElementById('tableContainer');
    const btnProgramar = document.getElementById('btnProgramar');
    let sortOrder = 'asc'; // 'asc' o 'desc'
    let sortColumn = 'cliente'; // Columna actualmente ordenada
    let currentData = [];
    let selectedRow = null;
    let selectedRowData = null;

    function showLoading() {
        loadingState.classList.remove('hidden');
        tableContainer.classList.add('hidden');
    }

    function hideLoading() {
        loadingState.classList.add('hidden');
        tableContainer.classList.remove('hidden');
    }

    async function cargarPronosticos() {
        showLoading();

        const params = new URLSearchParams();

        // Si hay meses en la URL, usarlos
        const urlParams = new URLSearchParams(window.location.search);
        const mesesFromUrl = urlParams.getAll('meses[]');

        // Si hay meses pasados desde PHP (de la vista), usarlos
        @if(isset($meses) && !empty($meses))
            const mesesPHP = @json($meses);
            mesesPHP.forEach(mes => params.append('meses[]', mes));
        @elseif(!empty($mesesFromUrl))
            // Si vienen de la URL, usarlos
            mesesFromUrl.forEach(mes => params.append('meses[]', mes));
        @else
            // Fallback: usar mes actual
            const mesActual = '{{ $mesActual }}';
            if (mesActual) {
                params.set('meses', mesActual);
            }
        @endif

        try {
            console.log('🔍 Iniciando carga de pronósticos...', params.toString());
            const res = await fetch(`{{ route('pronosticos.get') }}?` + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                method: 'GET',
            });

            console.log('📡 Respuesta recibida:', res.status, res.statusText);

            if (!res.ok) {
                const errorText = await res.text();
                console.error('❌ Error en respuesta:', res.status, errorText);
                pintar([], []);
                hideLoading();
                return;
            }

            const data = await res.json();
            console.log('✅ Datos recibidos:', {
                otros: data.otros?.length || 0,
                batas: data.batas?.length || 0
            });

            pintar(data.otros ?? [], data.batas ?? []);
            hideLoading();

        } catch (err) {
            console.error('💥 Error al cargar pronósticos:', err);
            pintar([], []);
            hideLoading();
        }
    }

    function td(txt, isNumeric = false, isRight = false, decimals = 2) {
        const d = document.createElement('td');
        d.className = 'px-1 py-2 whitespace-nowrap truncate text-gray-700';
        if (isRight) {
            d.classList.add('text-right');
        }
        if (isNumeric && txt !== null && txt !== '' && txt !== undefined) {
            const num = parseFloat(txt);
            if (!isNaN(num)) {
                d.textContent = number_format(num, decimals);
            } else {
                d.textContent = '';
            }
        } else {
            d.textContent = txt ?? '';
        }
        d.title = d.textContent; // Tooltip para texto truncado
        return d;
    }


    function getCantidad(item) {
        // Ambas consultas (batas y otros) ahora retornan CANTIDAD
        return item.CANTIDAD ?? 0;
    }

    function formatRazurado(valor) {
        if (valor === null || valor === undefined || valor === '') return '';
        const num = parseInt(valor);
        if (num === 0) return 'NA';
        if (num === 1) return 'Normal';
        if (num === 2) return 'Premium';
        return String(valor);
    }

    function number_format(num, decimals) {
        return num.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    function pintar(otros, batas) {
        tablaBody.innerHTML = '';

        // Combinar todos los registros: primero otros, luego batas
        const todos = [
            ...otros.map(x => ({...x, esBata: false})),
            ...batas.map(x => ({...x, esBata: true}))
        ];

        // Debug: verificar campos RASURADO en batas
        const batasConRasurado = batas.filter(x => x.RASURADO !== null && x.RASURADO !== undefined);
        if (batasConRasurado.length > 0) {
            console.log('Batas con RASURADO:', batasConRasurado.slice(0, 3).map(x => ({
                ITEMID: x.ITEMID,
                RASURADO: x.RASURADO,
                RASURADOCRUDO: x.RASURADOCRUDO
            })));
        } else if (batas.length > 0) {
            console.warn('Batas encontradas pero sin RASURADO:', batas.length, batas.slice(0, 2));
        }

        // Guardar datos para ordenamiento
        currentData = todos;

        if (todos.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.className = 'px-6 py-10 text-center';
            td.colSpan = 17;
            td.innerHTML = `
                <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay registros</h3>
                <p class="mt-1 text-sm text-gray-500">No se encontraron pronósticos.</p>
            `;
            tr.appendChild(td);
            tablaBody.appendChild(tr);
        } else {
            // Ordenar inicialmente por cliente ascendente
            const sorted = [...todos].sort((a, b) => {
                const nameA = (a.CUSTNAME || '').toUpperCase();
                const nameB = (b.CUSTNAME || '').toUpperCase();
                return nameA.localeCompare(nameB);
            });
            renderRows(sorted);
        }
    }

    function renderRows(data) {
        tablaBody.innerHTML = '';
        data.forEach((x, index) => {
            const tr = document.createElement('tr');
            tr.className = 'select-row cursor-pointer even:bg-gray-50 hover:bg-blue-50 transition-colors';
            tr.dataset.index = index;
            tr.onclick = () => selectRow(tr, x, index);

            // 1. ID FLOG
            tr.appendChild(td(x.IDFLOG ?? ''));

            // 3. Proyecto
            tr.appendChild(td(x.NOMBREPROYECTO ?? ''));

            // 4. Cliente
            tr.appendChild(td(x.CUSTNAME ?? ''));

            // 5. Calidad
            tr.appendChild(td(x.CATEGORIACALIDAD ?? ''));

            // 6. Ancho
            tr.appendChild(td(x.ANCHO, true));

            // 7. Largo
            tr.appendChild(td(x.LARGO, true));

            // 8. Artículo
            tr.appendChild(td(x.ITEMID ?? ''));

            // 9. Nombre
            tr.appendChild(td(x.ITEMNAME ?? ''));

            // 10. Tamaño
            tr.appendChild(td(x.INVENTSIZEID ?? ''));

            // 11. Razurado
            // Para registros normales: usar RASURADOCRUDO, para batas: usar RASURADO
            const razuradoValor = formatRazurado(x.RASURADOCRUDO ?? x.RASURADO);
            tr.appendChild(td(razuradoValor));

            // 12. Tipo Hilo
            tr.appendChild(td(x.TIPOHILOID ?? ''));

            // 13. Valor Agregado
            tr.appendChild(td(x.VALORAGREGADO ?? ''));

            // 14. Cantidad (sin decimales)
            const cantidad = getCantidad(x);
            tr.appendChild(td(cantidad, true, true, 0));

            // 15. Fecha Cancel
            const fechaCancel = x.FECHACANCELACION ? new Date(x.FECHACANCELACION).toLocaleDateString('es-ES') : '';
            tr.appendChild(td(fechaCancel));

            tablaBody.appendChild(tr);
        });
    }

    function selectRow(rowElement, rowData, index) {
        // Si la fila ya está seleccionada, deseleccionarla
        if (selectedRow === rowElement) {
            deselectRow();
            return;
        }

        // Deseleccionar la fila anterior si existe
        if (selectedRow) {
            selectedRow.classList.remove('bg-blue-500', 'text-white');
            selectedRow.classList.add('even:bg-gray-50');
            // Restaurar texto gris en las celdas
            const cells = selectedRow.querySelectorAll('td');
            cells.forEach(cell => {
                cell.classList.remove('text-white');
                cell.classList.add('text-gray-700');
            });
        }

        // Seleccionar la nueva fila
        selectedRow = rowElement;
        selectedRowData = rowData;
        rowElement.classList.add('bg-blue-500', 'text-white');
        rowElement.classList.remove('even:bg-gray-50', 'hover:bg-blue-50');

        // Cambiar texto a blanco en todas las celdas
        const cells = rowElement.querySelectorAll('td');
        cells.forEach(cell => {
            cell.classList.remove('text-gray-700');
            cell.classList.add('text-white');
        });

        // Habilitar botón Programar
        if (btnProgramar) {
            btnProgramar.disabled = false;
            btnProgramar.classList.remove('bg-gray-400', 'hover:bg-gray-500', 'cursor-not-allowed');
            btnProgramar.classList.add('bg-blue-600', 'hover:bg-blue-700', 'cursor-pointer');
        }
    }

    function deselectRow() {
        if (selectedRow) {
            selectedRow.classList.remove('bg-blue-500', 'text-white');
            selectedRow.classList.add('even:bg-gray-50');
            // Restaurar texto gris en las celdas
            const cells = selectedRow.querySelectorAll('td');
            cells.forEach(cell => {
                cell.classList.remove('text-white');
                cell.classList.add('text-gray-700');
            });
        }
        selectedRow = null;
        selectedRowData = null;

        // Deshabilitar botón Programar
        if (btnProgramar) {
            btnProgramar.disabled = true;
            btnProgramar.classList.remove('bg-blue-600', 'hover:bg-blue-700', 'cursor-pointer');
            btnProgramar.classList.add('bg-gray-400', 'hover:bg-gray-500', 'cursor-not-allowed');
        }
    }

    // Función para obtener el valor de una celda según la columna
    function getCellValue(row, column) {
        const cells = row.querySelectorAll('td');
        const indexMap = {
            'flog': 0,
            'estado': 1,
            'proyecto': 2,
            'cliente': 3,
            'calidad': 4,
            'ancho': 5,
            'largo': 6,
            'articulo': 7,
            'nombre': 8,
            'tamano': 9,
            'razurado': 10,
            'tipohilo': 11,
            'valoragregado': 12,
            'cantidad': 13,
            'cancelacion': 14,
        };

        const index = indexMap[column];
        if (index === undefined || !cells[index]) return '';

        const cell = cells[index];
        const text = cell.textContent.trim();

        // Para columnas numéricas
        if (['ancho', 'largo', 'cantidad'].includes(column)) {
            const num = parseFloat(text.replace(/,/g, ''));
            return isNaN(num) ? 0 : num;
        }

        // Para fechas (formato dd/mm/yyyy)
        if (column === 'cancelacion') {
            if (!text) return '';
            const parts = text.split('/');
            if (parts.length === 3) {
                const date = new Date(parts[2], parts[1] - 1, parts[0]);
                return isNaN(date.getTime()) ? 0 : date.getTime();
            }
            return 0;
        }

        // Para texto
        return text.toLowerCase();
    }

    // Función para actualizar los iconos de ordenamiento
    function updateSortIcons(activeColumn) {
        const columns = ['flog', 'estado', 'proyecto', 'cliente', 'calidad', 'ancho', 'largo', 'articulo', 'nombre', 'tamano', 'razurado', 'tipohilo', 'valoragregado', 'cantidad', 'cancelacion'];

        columns.forEach(col => {
            const icon = document.getElementById(`sortIcon-${col}`);
            if (!icon) return;

            if (col === activeColumn) {
                icon.className = sortOrder === 'asc'
                    ? 'fa-solid fa-sort-up ml-1 text-xs'
                    : 'fa-solid fa-sort-down ml-1 text-xs';
            } else {
                icon.className = 'fa-solid fa-sort ml-1 text-xs';
            }
        });
    }

    function toggleSort(column) {
        // Si se hace clic en la misma columna, cambiar el orden
        if (sortColumn === column) {
            sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            sortColumn = column;
            sortOrder = 'asc';
        }

        // Actualizar iconos
        updateSortIcons(column);

        // Ordenar los datos
            const sorted = [...currentData].sort((a, b) => {
            let valA, valB;

            // Mapear columnas a propiedades del objeto
            const columnMap = {
                'flog': 'IDFLOG',
                'estado': 'ESTADO',
                'proyecto': 'NOMBREPROYECTO',
                'cliente': 'CUSTNAME',
                'calidad': 'CATEGORIACALIDAD',
                'ancho': 'ANCHO',
                'largo': 'LARGO',
                'articulo': 'ITEMID',
                'nombre': 'ITEMNAME',
                'tamano': 'INVENTSIZEID',
                'razurado': 'RASURADOCRUDO',
                'tipohilo': 'TIPOHILOID',
                'valoragregado': 'VALORAGREGADO',
                'cancelacion': 'FECHACANCELACION',
                'cantidad': 'CANTIDAD',
            };

            const prop = columnMap[column];
            if (prop) {
                // Para razurado, usar el valor formateado (priorizar RASURADOCRUDO para normales, RASURADO para batas)
                if (column === 'razurado') {
                    valA = formatRazurado(a.RASURADOCRUDO ?? a.RASURADO ?? '');
                    valB = formatRazurado(b.RASURADOCRUDO ?? b.RASURADO ?? '');
                } else {
                    valA = a[prop] ?? '';
                    valB = b[prop] ?? '';
                }
                } else {
                valA = '';
                valB = '';
            }

            // Para columnas numéricas
            if (['ancho', 'largo', 'cantidad'].includes(column)) {
                const numA = parseFloat(valA) || 0;
                const numB = parseFloat(valB) || 0;
                return sortOrder === 'asc' ? numA - numB : numB - numA;
            }

            // Para fechas
            if (column === 'cancelacion') {
                const dateA = valA ? new Date(valA).getTime() : 0;
                const dateB = valB ? new Date(valB).getTime() : 0;
                return sortOrder === 'asc' ? dateA - dateB : dateB - dateA;
            }

            // Para texto
            const strA = String(valA || '').toUpperCase();
            const strB = String(valB || '').toUpperCase();
            const comparison = strA.localeCompare(strB);
            return sortOrder === 'asc' ? comparison : -comparison;
        });

            currentData = sorted; // Actualizar datos actuales
            // Deseleccionar fila al ordenar
            deselectRow();
            renderRows(sorted);
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Botón Programar
        if (btnProgramar) {
            btnProgramar.onclick = async () => {
                console.log('Botón programar clickeado, selectedRowData:', selectedRowData);

                if (!selectedRowData) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Selecciona una fila',
                        text: 'Por favor selecciona una fila para programar',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                const tamano = selectedRowData.INVENTSIZEID || '';
                const articulo = selectedRowData.ITEMID || '';

                console.log('Datos extraídos:', { tamano, articulo, selectedRowData });

                // HTML del modal
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

                        // Función para renderizar sugerencias
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

                            // Agregar eventos a las sugerencias
                            Array.from(suggest.children).forEach(div => {
                                div.addEventListener('click', () => {
                                    claveInput.value = div.getAttribute('data-clave') || '';
                                    suggest.classList.add('hidden');
                                    claveInput.focus();
                                });
                            });
                        };

                        // Función para buscar modelos
                        const doFetch = async (q) => {
                            try {
                                if (!q || q.trim().length < 1) {
                                    renderSuggest([]);
                                    return;
                                }

                                const salon = salonSelect.value;
                                const url = new URL('{{ route("planeacion.buscar-modelos-sugerencias") }}', window.location.origin);
                                url.searchParams.set('q', q.trim());

                                if (salon) {
                                    url.searchParams.set('salon_tejido_id', salon);
                                }

                                const response = await fetch(url.toString());

                                if (!response.ok) {
                                    renderSuggest([]);
                                    return;
                                }

                                const data = await response.json();

                                // Si es un array, mostrar todas las sugerencias
                                if (Array.isArray(data)) {
                                    renderSuggest(data);
                                } else if (data.error) {
                                    renderSuggest([]);
                                } else {
                                    renderSuggest([data]);
                                }
                            } catch (e) {
                                console.error('Error en autocompletado:', e);
                                renderSuggest([]);
                            }
                        };

                        let timer = null;

                        // Evento de escritura en el input
                        claveInput.addEventListener('input', () => {
                            clearTimeout(timer);
                            const val = claveInput.value.trim();
                            // Limpiar error y borde rojo cuando el usuario empieza a escribir
                            errorMsg.classList.add('hidden');
                            errorMsg.textContent = '';
                            claveInput.classList.remove('border-red-500');

                            if (val.length < 1) {
                                renderSuggest([]);
                                return;
                            }
                            timer = setTimeout(() => doFetch(val), 300);
                        });

                        // Ocultar sugerencias al hacer blur
                        claveInput.addEventListener('blur', () => {
                            setTimeout(() => suggest.classList.add('hidden'), 200);
                        });

                        // Mostrar sugerencias al hacer focus si hay texto
                        claveInput.addEventListener('focus', () => {
                            if (claveInput.value.trim().length >= 1) {
                                doFetch(claveInput.value.trim());
                            }
                        });

                        // Función para buscar y completar clave modelo automáticamente
                        const buscarClaveModelo = async () => {
                            const salon = salonSelect.value;

                            if (!salon || !tamano || !articulo) {
                                // Si no hay salón, limpiar el input
                                if (!salon) {
                                    claveInput.value = '';
                                }
                                return;
                            }

                            // Reiniciar el input antes de buscar
                            claveInput.value = '';
                            // Ocultar sugerencias si están visibles
                            suggest.classList.add('hidden');
                            // Ocultar mensaje de error anterior
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
                                        // Ocultar error si existe
                                        errorMsg.classList.add('hidden');
                                    } else if (!data.error) {
                                        // Si no hay TamanoClave, construir con InventSizeId + ItemId
                                        const clave = ((data.InventSizeId || tamano) + (data.ItemId || articulo)).toUpperCase().replace(/[\s\-_]+/g, '');
                                        claveInput.value = clave;
                                        // Ocultar error si existe
                                        errorMsg.classList.add('hidden');
                                    } else {
                                        // No se encontró el modelo - mostrar mensaje debajo del input
                                        errorMsg.textContent = `No se encontró un modelo para el artículo ${articulo}, tamaño ${tamano} en el salón ${salon}. Por favor ingrese la clave modelo manualmente.`;
                                        errorMsg.classList.remove('hidden');
                                        claveInput.classList.add('border-red-500');
                                    }
                                } else {
                                    // Error en la respuesta o modelo no encontrado - mostrar mensaje debajo del input
                                    errorMsg.textContent = `No se encontró un modelo para el artículo ${articulo}, tamaño ${tamano} en el salón ${salon}. Por favor ingrese la clave modelo manualmente.`;
                                    errorMsg.classList.remove('hidden');
                                    claveInput.classList.add('border-red-500');
                                }
                            } catch (e) {
                                console.error('Error al buscar clave modelo:', e);
                                errorMsg.textContent = 'Ocurrió un error al buscar el modelo. Por favor intente nuevamente.';
                                errorMsg.classList.remove('hidden');
                                claveInput.classList.add('border-red-500');
                            }
                        };

                        // Buscar clave modelo cuando se selecciona un salón
                        salonSelect.addEventListener('change', () => {
                            buscarClaveModelo();
                        });
                    },
                    preConfirm: async () => {
                        const salon = document.getElementById('swal-salon').value;
                        const claveModelo = document.getElementById('swal-clave-modelo').value.trim();

                        if (!salon) {
                            Swal.showValidationMessage('Por favor seleccione un salón');
                            return false;
                        }

                        if (!claveModelo) {
                            Swal.showValidationMessage('Por favor ingrese una clave modelo');
                            return false;
                        }

                        // Validar que el modelo existe
                        try {
                            Swal.showLoading();

                            const searchUrl = new URL('{{ route("planeacion.buscar-detalle-modelo") }}', window.location.origin);
                            searchUrl.searchParams.set('concatena', claveModelo);
                            searchUrl.searchParams.set('salon_tejido_id', salon);

                            if (articulo) {
                                searchUrl.searchParams.set('itemid', articulo);
                            }
                            if (tamano) {
                                searchUrl.searchParams.set('inventsizeid', tamano);
                            }

                            const response = await fetch(searchUrl.toString());
                            const data = await response.json();

                            if (response.status === 404 || (data && data.error)) {
                                Swal.hideLoading();
                                Swal.showValidationMessage('La clave modelo no existe o no está disponible para este salón');
                                return false;
                            }

                            Swal.hideLoading();

                            return {
                                salon: salon,
                                claveModelo: claveModelo,
                                tamano: tamano,
                                articulo: articulo,
                                datos: selectedRowData,
                                modeloData: data
                            };
                        } catch (error) {
                            Swal.hideLoading();
                            Swal.showValidationMessage('Error al validar la clave modelo');
                            return false;
                        }
                    }
                });

                console.log('swalRes:', swalRes);

                if (swalRes.isConfirmed && swalRes.value) {
                    const { salon, claveModelo, tamano, articulo, datos } = swalRes.value;

                    console.log('Redirigiendo con datos:', { salon, claveModelo, tamano, articulo, datos });

                    // Redirigir con parámetros
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
                } else {
                    console.log('No se confirmó o no hay valor:', {
                        isConfirmed: swalRes.isConfirmed,
                        value: swalRes.value,
                        dismiss: swalRes.dismiss
                    });
                }
            };
        }

        // Variables para filtros
        let filtrosActivos = [];

        // Mapeo de columnas para filtros
        const columnasFiltros = {
            'flog': 'Flog',
            'estado': 'Estado',
            'proyecto': 'Proyecto',
            'cliente': 'Cliente',
            'calidad': 'Calidad',
            'ancho': 'Ancho',
            'largo': 'Largo',
            'articulo': 'Artículo',
            'nombre': 'Nombre',
            'tamano': 'Tamaño',
            'razurado': 'Razurado',
            'tipohilo': 'Tipo Hilo',
            'valoragregado': 'Valor Agregado',
            'cancelacion': 'Cancelación',
            'cantidad': 'Cantidad',
        };

        // Mapeo de columnas a propiedades del objeto
        const columnMap = {
            'flog': 'IDFLOG',
            'estado': 'ESTADO',
            'proyecto': 'NOMBREPROYECTO',
            'cliente': 'CUSTNAME',
            'calidad': 'CATEGORIACALIDAD',
            'ancho': 'ANCHO',
            'largo': 'LARGO',
            'articulo': 'ITEMID',
            'nombre': 'ITEMNAME',
            'tamano': 'INVENTSIZEID',
            'razurado': 'RASURADOCRUDO',
            'tipohilo': 'TIPOHILOID',
            'valoragregado': 'VALORAGREGADO',
            'cancelacion': 'FECHACANCELACION',
            'cantidad': 'CANTIDAD',
        };

        // Función para aplicar filtros
        function aplicarFiltros() {
            if (filtrosActivos.length === 0) {
                // Si no hay filtros, mostrar todos los datos
                renderRows(currentData);
                return;
            }

            const datosFiltrados = currentData.filter(item => {
                return filtrosActivos.every(filtro => {
                    const prop = columnMap[filtro.columna];
                    if (!prop) return true;

                    let valor;
                    // Para razurado, usar el valor formateado (priorizar RASURADOCRUDO para normales, RASURADO para batas)
                    if (filtro.columna === 'razurado') {
                        valor = formatRazurado(item.RASURADOCRUDO ?? item.RASURADO ?? '');
                    } else {
                        valor = item[prop] ?? '';
                    }

                    const valorTexto = String(valor).toLowerCase();
                    const filtroTexto = filtro.valor.toLowerCase().trim();

                    // Si el filtro está vacío, no filtrar
                    if (filtroTexto === '') return true;

                    // Buscar coincidencia parcial
                    return valorTexto.includes(filtroTexto);
                });
            });

            renderRows(datosFiltrados);
        }

        // Función para mostrar modal de filtros
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

                    // Función para agregar nuevo filtro
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
                            <input type="text" class="flex-1 border rounded px-2 py-1.5 text-sm"
                                   data-valor
                                   placeholder="Valor a buscar...">
                            <button type="button" class="btn-eliminar-filtro px-3 py-1.5 bg-red-600 text-white rounded hover:bg-red-700 text-sm" data-index="${nuevoIndex}">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        `;
                        container.appendChild(nuevoFiltro);

                        // Agregar evento al botón de eliminar
                        nuevoFiltro.querySelector('.btn-eliminar-filtro').addEventListener('click', function() {
                            nuevoFiltro.remove();
                            // Reindexar los filtros restantes
                            Array.from(container.querySelectorAll('[data-filtro-index]')).forEach((div, idx) => {
                                div.dataset.filtroIndex = idx;
                                const btn = div.querySelector('.btn-eliminar-filtro');
                                if (btn) btn.dataset.index = idx;
                            });
                        });
                    });

                    // Agregar eventos a los botones de eliminar existentes
                    container.querySelectorAll('.btn-eliminar-filtro').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const index = parseInt(this.dataset.index);
                            const filtroDiv = this.closest('[data-filtro-index]');
                            if (filtroDiv) {
                                filtroDiv.remove();
                                // Reindexar los filtros restantes
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

        // Botón filtros
        const btnFiltros = document.getElementById('btnFiltros');
        if (btnFiltros) {
            btnFiltros.onclick = () => {
                mostrarModalFiltros();
            };
        }

        // Botón restablecer
        const btnRestablecer = document.getElementById('btnRestablecer');
        if (btnRestablecer) {
            btnRestablecer.onclick = () => {
                filtrosActivos = [];
                deselectRow(); // Deseleccionar fila al restablecer
                aplicarFiltros(); // Aplicar filtros (vacío = mostrar todo)
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

        // Cargar pronósticos automáticamente al cargar la página
        cargarPronosticos();
    });
</script>
@endsection
