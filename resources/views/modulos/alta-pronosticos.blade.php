@extends('layouts.app')

@section('page-title', 'Alta de Pronósticos')

@section('navbar-right')
    <button id="btnProgramar" type="button" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-gray-400 hover:bg-gray-500 cursor-not-allowed mr-2 disabled:opacity-50 disabled:cursor-not-allowed" title="Programar" disabled>
        <i class="fa-solid fa-calendar-check mr-2"></i>
        Programar
    </button>
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
        <div id="tableContainer" class="overflow-x-auto hidden">
            <div class="overflow-y-auto" style="max-height: 600px;">
                <table class="min-w-full divide-y divide-gray-200 text-xs leading-tight" id="tablaPronosticos">
                    <thead class="bg-blue-500 text-white sticky top-0 z-10">
                        <tr>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap min-w-[80px]">ID FLOG</th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap min-w-[120px] cursor-pointer hover:bg-blue-600" id="thCliente" onclick="toggleSort('cliente')">
                                NOMBRE DEL CLIENTE
                                <i id="sortIconCliente" class="fa-solid fa-sort-up ml-1 text-xs"></i>
                            </th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap min-w-[90px]">Cod. Artículo</th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap min-w-[100px]">Artículo</th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap min-w-[80px]">Tipo Hilo</th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap min-w-[60px]">Talla</th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap min-w-[70px]">Rasurado</th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap min-w-[90px]">Valor Agregado</th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap min-w-[60px]">Ancho</th>
                            <th class="px-1 py-2 text-right font-semibold whitespace-nowrap min-w-[80px]">Por Entregar</th>
                            <th class="px-1 py-2 text-left font-semibold whitespace-nowrap min-w-[90px]">Tipo</th>
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

        const mesActual = '{{ $mesActual }}';
        const params = new URLSearchParams();

        if (mesActual) {
            params.set('meses', mesActual);
        }

        try {
            const res = await fetch(`{{ route('pronosticos.get') }}?` + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                method: 'GET',
            });

            const data = await res.json();

            if (!res.ok) {
                pintar([], []);
                hideLoading();
                return;
            }

            pintar(data.otros ?? [], data.batas ?? []);
            hideLoading();

        } catch (err) {
            pintar([], []);
            hideLoading();
            console.error(err);
        }
    }

    function td(txt, isNumeric = false, isRight = false) {
        const d = document.createElement('td');
        d.className = 'px-1 py-2 whitespace-nowrap truncate text-gray-700';
        if (isRight) {
            d.classList.add('text-right');
        }
        if (isNumeric && txt !== null && txt !== '' && txt !== undefined) {
            const num = parseFloat(txt);
            if (!isNaN(num)) {
                d.textContent = number_format(num, 2);
            } else {
                d.textContent = '';
            }
        } else {
            d.textContent = txt ?? '';
        }
        d.title = d.textContent; // Tooltip para texto truncado
        return d;
    }

    function getTipoArticulo(itemTypeId) {
        if (!itemTypeId) return '';
        const tipo = parseInt(itemTypeId);
        if (tipo >= 10 && tipo <= 19) {
            return 'Bata';
        }
        return 'Otro';
    }

    function tdTipo(item) {
        const d = document.createElement('td');
        d.className = 'px-1 py-2 whitespace-nowrap';

        const tipo = item.ITEMTYPEID ? parseInt(item.ITEMTYPEID) : null;
        const esBata = tipo >= 10 && tipo <= 19;

        if (esBata) {
            d.innerHTML = `
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-800">
                    Bata
                </span>
            `;
        } else {
            d.textContent = 'Otro';
            d.className += ' text-gray-700';
        }

        return d;
    }

    function getCantidad(item) {
        // Para batas usar TOTAL_RESULTADO o TOTAL_INVENTQTY, para otros usar PORENTREGAR
        if (item.esBata) {
            return item.TOTAL_RESULTADO ?? item.TOTAL_INVENTQTY ?? 0;
        } else {
            return item.PORENTREGAR ?? 0;
        }
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

        // Guardar datos para ordenamiento
        currentData = todos;

        if (todos.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.className = 'px-6 py-10 text-center';
            td.colSpan = 11;
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

            // 2. NOMBRE DEL CLIENTE
            tr.appendChild(td(x.CUSTNAME ?? ''));

            // 3. CÓDIGO DEL ARTÍCULO
            tr.appendChild(td(x.ITEMID ?? ''));

            // 4. NOMBRE DEL ARTÍCULO
            tr.appendChild(td(x.ITEMNAME ?? ''));

            // 5. TIPO DE HILO
            tr.appendChild(td(x.TIPOHILOID ?? ''));

            // 6. TAMAÑO
            tr.appendChild(td(x.INVENTSIZEID ?? ''));

            // 7. RASURADO
            tr.appendChild(td(x.RASURADOCRUDO ?? ''));

            // 8. VALOR AGREGADO
            tr.appendChild(td(x.VALORAGREGADO ?? ''));

            // 9. ANCHO
            tr.appendChild(td(x.ANCHO, true));

            // 10. CANTIDAD
            const cantidad = getCantidad(x);
            tr.appendChild(td(cantidad, true, true));

            // 11. TIPO DE ARTÍCULO
            tr.appendChild(tdTipo(x));

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
            // No cambiar el color del badge, solo el texto normal
            const badge = cell.querySelector('span.bg-blue-100');
            if (!badge) {
                cell.classList.remove('text-gray-700');
                cell.classList.add('text-white');
            }
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

    function toggleSort(column) {
        if (column === 'cliente') {
            sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
            const icon = document.getElementById('sortIconCliente');
            if (icon) {
                icon.className = sortOrder === 'asc'
                    ? 'fa-solid fa-sort-up ml-1 text-xs'
                    : 'fa-solid fa-sort-down ml-1 text-xs';
            }

            const sorted = [...currentData].sort((a, b) => {
                const nameA = (a.CUSTNAME || '').toUpperCase();
                const nameB = (b.CUSTNAME || '').toUpperCase();
                if (sortOrder === 'asc') {
                    return nameA.localeCompare(nameB);
                } else {
                    return nameB.localeCompare(nameA);
                }
            });
            currentData = sorted; // Actualizar datos actuales
            // Deseleccionar fila al ordenar
            deselectRow();
            renderRows(sorted);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Botón Programar
        if (btnProgramar) {
            btnProgramar.onclick = () => {
                if (!selectedRowData) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Selecciona una fila',
                        text: 'Por favor selecciona una fila para programar',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Aquí puedes agregar la lógica para programar
                Swal.fire({
                    icon: 'info',
                    title: 'Programar Pronóstico',
                    html: `
                        <div class="text-left">
                            <p class="mb-2"><strong>Cliente:</strong> ${selectedRowData.CUSTNAME || 'N/A'}</p>
                            <p class="mb-2"><strong>Artículo:</strong> ${selectedRowData.ITEMNAME || 'N/A'}</p>
                            <p class="mb-2"><strong>Código:</strong> ${selectedRowData.ITEMID || 'N/A'}</p>
                            <p><strong>Cantidad:</strong> ${number_format(getCantidad(selectedRowData), 2)}</p>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Confirmar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#6b7280'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Aquí puedes hacer la llamada al backend para programar
                        Swal.fire({
                            icon: 'success',
                            title: 'Programado',
                            text: 'El pronóstico ha sido programado correctamente',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                });
            };
        }

        // Botón filtros
        const btnFiltros = document.getElementById('btnFiltros');
        if (btnFiltros) {
            btnFiltros.onclick = () => {
                Swal.fire({
                    title: 'Filtros',
                    text: 'Funcionalidad de filtros próximamente',
                    icon: 'info',
                    confirmButtonText: 'OK'
                });
            };
        }

        // Botón restablecer
        const btnRestablecer = document.getElementById('btnRestablecer');
        if (btnRestablecer) {
            btnRestablecer.onclick = () => {
                deselectRow(); // Deseleccionar fila al restablecer
                cargarPronosticos();
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
