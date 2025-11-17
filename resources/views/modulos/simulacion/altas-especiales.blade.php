@extends('layouts.app')

@section('page-title', 'Simulación - Altas de compras especiales')

@section('navbar-right')
    <button id="btnFiltros" type="button" class="inline-flex items-center justify-center w-9 h-9 text-base rounded-full text-white bg-stone-600 hover:bg-stone-700" title="Filtros">
        <i class="fa-solid fa-filter"></i>
    </button>
    <button id="btnRestablecer" type="button" class="inline-flex items-center justify-center w-9 h-9 text-base rounded-full text-white bg-gray-600 hover:bg-gray-700 ml-2" title="Restablecer">
        <i class="fa-solid fa-rotate"></i>
    </button>
    <button id="btnProgramar"
            class="hidden bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-sm ml-2">
        <i class="fa-solid fa-plus mr-1"></i> Programar
    </button>
@endsection

@section('content')
<div class="w-full px-0 py-0">
    <div class="bg-white shadow overflow-hidden w-full">
        @if(isset($errorMensaje))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 m-3 text-sm" role="alert">
                <p class="font-bold">Error</p>
                <p>{{ $errorMensaje }}</p>
            </div>
        @endif

        <div class="overflow-x-auto">
            <div class="overflow-y-auto" style="max-height: 600px;">
                <table class="min-w-full table-fixed divide-y divide-gray-200 text-xs leading-tight">
                    <thead class="bg-stone-500 text-white sticky top-0 z-10">
                        <tr>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-20 cursor-pointer hover:bg-stone-600" onclick="toggleSort('flog')">
                                Flog <i id="sortIcon-flog" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-36 cursor-pointer hover:bg-stone-600" onclick="toggleSort('proyecto')">
                                Proyecto <i id="sortIcon-proyecto" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-36 cursor-pointer hover:bg-stone-600" onclick="toggleSort('cliente')">
                                Cliente <i id="sortIcon-cliente" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-24 cursor-pointer hover:bg-stone-600" onclick="toggleSort('calidad')">
                                Calidad <i id="sortIcon-calidad" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-16 cursor-pointer hover:bg-stone-600" onclick="toggleSort('ancho')">
                                Ancho <i id="sortIcon-ancho" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-16 cursor-pointer hover:bg-stone-600" onclick="toggleSort('largo')">
                                Largo <i id="sortIcon-largo" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-28 cursor-pointer hover:bg-stone-600" onclick="toggleSort('articulo')">
                                Artículo <i id="sortIcon-articulo" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-44 cursor-pointer hover:bg-stone-600" onclick="toggleSort('nombre')">
                                Nombre <i id="sortIcon-nombre" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-20 cursor-pointer hover:bg-stone-600" onclick="toggleSort('tamano')">
                                Tamaño <i id="sortIcon-tamano" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-24 cursor-pointer hover:bg-stone-600" onclick="toggleSort('razurado')">
                                Rasurado <i id="sortIcon-razurado" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-20 cursor-pointer hover:bg-stone-600" onclick="toggleSort('hilo')">
                                Hilo <i id="sortIcon-hilo" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-24 cursor-pointer hover:bg-stone-600" onclick="toggleSort('valor')">
                                V. Agregado <i id="sortIcon-valor" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-28 cursor-pointer hover:bg-stone-600" onclick="toggleSort('cancelacion')">
                                Cancelación <i id="sortIcon-cancelacion" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                            <th class="px-2 py-2 text-right font-semibold whitespace-nowrap w-20 cursor-pointer hover:bg-stone-600" onclick="toggleSort('cantidad')">
                                Cantidad <i id="sortIcon-cantidad" class="fa-solid fa-sort ml-1 text-xs"></i>
                            </th>
                        </tr>
                    </thead>

                    <tbody id="tablaBody" class="bg-white divide-y divide-gray-200">
                        @php
                            $tieneDatos = isset($registros) && is_countable($registros) && count($registros) > 0;
                        @endphp

                        @if($tieneDatos)
                            @foreach($registros as $r)
                                <tr class="select-row cursor-pointer even:bg-gray-50 hover:bg-stone-50 transition-colors"
                                    data-id="{{ $r['FlogsId'] ?? '' }}"
                                    data-idflog="{{ $r['FlogsId'] ?? '' }}"
                                    data-itemid="{{ $r['ItemId'] ?? '' }}"
                                    data-inventsizeid="{{ $r['InventSizeId'] ?? '' }}"
                                    data-cantidad="{{ isset($r['Cantidad']) ? (float)$r['Cantidad'] : '' }}"
                                    data-tipohilo="{{ $r['TipoHilo'] ?? '' }}"
                                    data-flog="{{ strtolower($r['FlogsId'] ?? '') }}"
                                    data-proyecto="{{ strtolower($r['NombreProyecto'] ?? '') }}"
                                    data-cliente="{{ strtolower($r['CustName'] ?? '') }}"
                                    data-calidad="{{ strtolower($r['CategoriaCalidad'] ?? '') }}"
                                    data-ancho="{{ $r['Ancho'] ?? '' }}"
                                    data-largo="{{ $r['Largo'] ?? '' }}"
                                    data-articulo="{{ strtolower($r['ItemId'] ?? '') }}"
                                    data-nombre="{{ strtolower($r['ItemName'] ?? '') }}"
                                    data-tamano="{{ strtolower($r['InventSizeId'] ?? '') }}"
                                    data-hilo="{{ strtolower($r['TipoHilo'] ?? '') }}"
                                    data-valor="{{ strtolower($r['ValorAgregado'] ?? '') }}"
                                    data-razurado="{{ strtolower($r['Rasurado'] ?? $r['Razurado'] ?? '') }}">
                                    <td class="px-2 py-2 whitespace-nowrap truncate text-gray-700">{{ $r['FlogsId'] ?? '' }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate max-w-[10rem] text-gray-700">{{ $r['NombreProyecto'] ?? '' }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate max-w-[10rem] text-gray-700">{{ $r['CustName'] ?? '' }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate max-w-[8rem] text-gray-700">{{ $r['CategoriaCalidad'] ?? '' }}</td>

                                    <td class="px-2 py-2 whitespace-nowrap truncate text-gray-700">
                                        {{ isset($r['Ancho']) ? number_format((float)$r['Ancho'], 2) : '' }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate text-gray-700">
                                        {{ isset($r['Largo']) ? number_format((float)$r['Largo'], 2) : '' }}
                                    </td>

                                    <td class="px-2 py-2 whitespace-nowrap truncate max-w-[7rem] text-gray-700">{{ $r['ItemId'] ?? '' }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate max-w-[12rem] text-gray-700">{{ $r['ItemName'] ?? '' }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate text-gray-700">{{ $r['InventSizeId'] ?? '' }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate text-gray-700">
                                        {{ $r['Rasurado'] ?? $r['Razurado'] ?? '' }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate text-gray-700">{{ $r['TipoHilo'] ?? '' }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate max-w-[8rem] text-gray-700">{{ $r['ValorAgregado'] ?? '' }}</td>

                                    <td class="px-2 py-2 whitespace-nowrap truncate text-gray-700">
                                        @if(!empty($r['FechaCancelacion']))
                                            {{ \Carbon\Carbon::parse($r['FechaCancelacion'])->format('d/m/Y') }}
                                        @endif
                                    </td>

                                    <td class="px-2 py-2 text-right whitespace-nowrap truncate text-gray-700">
                                        {{ isset($r['Cantidad']) ? number_format((float)$r['Cantidad'], 2) : '0.00' }}
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="15" class="px-6 py-10 text-center">
                                    <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No hay registros</h3>
                                    <p class="mt-1 text-sm text-gray-500">No se encontraron registros de compras especiales.</p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modal de filtros --}}
<div id="modalFiltros" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50" style="display: none;">
    <div class="flex items-center justify-center h-full">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="p-4 border-b flex justify-between items-center">
            <h3 class="text-lg font-semibold">Filtrar registros</h3>
            <button id="cerrarModal" class="text-gray-500 hover:text-gray-700"><i class="fa-solid fa-times"></i></button>
        </div>
        <div class="p-4">
            <div id="filtrosContainer" class="space-y-3"></div>
            <button id="addFiltro" class="mt-3 w-full bg-stone-600 hover:bg-stone-700 text-white px-4 py-2 rounded">+ Agregar filtro</button>
        </div>
        <div class="p-4 border-t flex gap-2">
            <button id="aplicarFiltros" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Aplicar</button>
            <button id="cancelarFiltros" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded">Cancelar</button>
        </div>
    </div>
    </div>
</div>

<script>
// Configuración de columnas y mapeo
const CONFIG_COLUMNAS = {
    'flog': 'Flog',
    'proyecto': 'Proyecto',
    'cliente': 'Cliente',
    'calidad': 'Calidad',
    'articulo': 'Artículo',
    'nombre': 'Nombre',
    'tamano': 'Tamaño',
    'hilo': 'Hilo',
    'valor': 'V. Agregado',
    'razurado': 'Razurado'
};

const MAPEO_DATOS = {
    'FlogsId': 'flog',
    'NombreProyecto': 'proyecto',
    'CustName': 'cliente',
    'CategoriaCalidad': 'calidad',
    'ItemId': 'articulo',
    'ItemName': 'nombre',
    'InventSizeId': 'tamano',
    'TipoHilo': 'hilo',
    'ValorAgregado': 'valor',
    'Rasurado': 'razurado',
    'Razurado': 'razurado'
};

// Variables globales para ordenamiento
let sortColumn = null;
let sortOrder = 'asc'; // 'asc' o 'desc'
let currentData = [];

// Función para obtener el valor de una celda según la columna
function getCellValue(row, column) {
    const cells = row.querySelectorAll('td');
    const indexMap = {
        'flog': 0,
        'proyecto': 1,
        'cliente': 2,
        'calidad': 3,
        'ancho': 4,
        'largo': 5,
        'articulo': 6,
        'nombre': 7,
        'tamano': 8,
        'razurado': 9,
        'hilo': 10,
        'valor': 11,
        'cancelacion': 12,
        'cantidad': 13
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

    // Para fechas
    if (column === 'cancelacion') {
        if (!text) return '';
        // Convertir formato dd/mm/yyyy a timestamp
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
    const columns = ['flog', 'proyecto', 'cliente', 'calidad', 'ancho', 'largo', 'articulo', 'nombre', 'tamano', 'razurado', 'hilo', 'valor', 'cancelacion', 'cantidad'];

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

// Función para ordenar la tabla
function toggleSort(column) {
    const rows = Array.from(document.querySelectorAll('#tablaBody tr.select-row'));

    if (rows.length === 0) return;

    // Si se hace clic en la misma columna, cambiar el orden
    if (sortColumn === column) {
        sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn = column;
        sortOrder = 'asc';
    }

    // Actualizar iconos
    updateSortIcons(column);

    // Ordenar las filas
    const sorted = rows.sort((a, b) => {
        const valA = getCellValue(a, column);
        const valB = getCellValue(b, column);

        // Comparar valores
        let comparison = 0;
        if (typeof valA === 'number' && typeof valB === 'number') {
            comparison = valA - valB;
        } else {
            comparison = valA.localeCompare(valB);
        }

        return sortOrder === 'asc' ? comparison : -comparison;
    });

    // Reordenar en el DOM
    const tbody = document.getElementById('tablaBody');
    sorted.forEach(row => tbody.appendChild(row));

    // Deseleccionar fila al ordenar
    if (current) {
        setRowSelected(current, false);
        current = null;
        updateButtonVisibility();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    let filtros = [], allRows = [], modal = document.getElementById('modalFiltros'), container = document.getElementById('filtrosContainer');

    // Renderizar filtros en el modal
    function renderFiltros() {
        const filtrosHTML = filtros.length > 0
            ? filtros.map((f, i) => `
                <div class="flex gap-2 items-end">
                    <select class="flex-1 border rounded p-2" data-col>
                        <option value="">Columna...</option>
                        ${Object.entries(CONFIG_COLUMNAS).map(([k, v]) =>
                            `<option value="${k}" ${f.col === k ? 'selected' : ''}>${v}</option>`
                        ).join('')}
                    </select>
                    <input type="text" class="flex-1 border rounded p-2" value="${f.val || ''}" data-val placeholder="Valor...">
                    <button onclick="filtros.splice(${i}, 1); renderFiltros();" class="px-3 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            `).join('')
            : '<p class="text-gray-500 text-sm text-center py-2">No hay filtros activos</p>';

        container.innerHTML = filtrosHTML;
    }

    // Aplicar filtros a la tabla
    function aplicarFiltrosTabla() {
        const tbody = document.getElementById('tablaBody');

        // Mostrar todas las filas primero
        allRows.forEach(row => row.style.display = '');

        if (filtros.length === 0) {
            // Limpiar mensaje de "no resultados" si existe
            const emptyMsg = tbody.querySelector('td[colspan="15"]');
            if (emptyMsg) emptyMsg.closest('tr').remove();
            return;
        }

        // Filtrar filas
        let visibleCount = 0;
        allRows.forEach(row => {
            const match = filtros.every(f => {
                if (!f.col || f.val === '') return true;
                const val = (row.getAttribute(`data-${f.col}`) || '').toLowerCase();
                const filtroVal = f.val.toLowerCase().trim();
                return filtroVal === '' || val.includes(filtroVal);
            });

            row.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });

        // Mostrar mensaje si no hay resultados
        const existingEmptyMsg = tbody.querySelector('td[colspan="15"]');
        if (visibleCount === 0) {
            if (!existingEmptyMsg) {
                const emptyRow = document.createElement('tr');
                emptyRow.innerHTML = '<td colspan="15" class="px-6 py-10 text-center"><p class="text-gray-500">No hay resultados que coincidan con los filtros</p></td>';
                tbody.appendChild(emptyRow);
            }
        } else {
            if (existingEmptyMsg) {
                existingEmptyMsg.closest('tr').remove();
            }
        }
    }

    // Inicializar filas
    allRows = Array.from(document.querySelectorAll('tr.select-row'));

    // Configurar eventos de filtros
    const btnFiltros = document.getElementById('btnFiltros');
    const cerrarModal = document.getElementById('cerrarModal');
    const cancelarFiltros = document.getElementById('cancelarFiltros');
    const addFiltro = document.getElementById('addFiltro');
    const aplicarFiltros = document.getElementById('aplicarFiltros');
    const btnRestablecer = document.getElementById('btnRestablecer');

    if (btnFiltros) {
        btnFiltros.onclick = () => {
            filtros = [];
            renderFiltros();
            modal.style.display = 'block';
        };
    }

    if (cerrarModal) {
        cerrarModal.onclick = () => { modal.style.display = 'none'; };
    }

    if (cancelarFiltros) {
        cancelarFiltros.onclick = () => { modal.style.display = 'none'; };
    }

    if (addFiltro) {
        addFiltro.onclick = () => {
            filtros.push({ col: '', val: '' });
            renderFiltros();
        };
    }

    if (aplicarFiltros) {
        aplicarFiltros.onclick = () => {
            const selects = container.querySelectorAll('[data-col]');
            const inputs = container.querySelectorAll('[data-val]');

            filtros = Array.from(selects).map((select, i) => ({
                col: select.value,
                val: inputs[i] ? inputs[i].value : ''
            }));

            aplicarFiltrosTabla();
            modal.style.display = 'none';
        };
    }

    if (btnRestablecer) {
        btnRestablecer.onclick = () => {
            filtros = [];
            aplicarFiltrosTabla();
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

    const btnProgramar = document.getElementById('btnProgramar');
    let current = null;

    // helpers para chips (badges)
    function setChipSelected(chip, on) {
        if (!chip) return;

        // Guardar el estado original si es la primera vez
        if (!chip.dataset.originalClass) {
            // Si tiene bg-indigo-100, es bata (indigo), si no, es gris o no tiene color
            if (chip.classList.contains('bg-indigo-100')) {
                chip.dataset.originalClass = 'indigo';
            } else if (chip.classList.contains('bg-gray-100')) {
                chip.dataset.originalClass = 'gray';
            } else {
                // Si no tiene clase de color, asumir que es indigo (bata) ya que solo los bata tienen badge
                chip.dataset.originalClass = 'indigo';
            }
        }

        // Remover todas las clases de color
        chip.classList.remove('bg-indigo-100', 'text-indigo-800', 'bg-gray-100', 'text-gray-800', 'bg-stone-600', 'text-white');

        if (on) {
            // Seleccionado: azul
            chip.classList.add('bg-stone-600', 'text-white');
        } else {
            // Restaurar color original
            if (chip.dataset.originalClass === 'indigo') {
                chip.classList.add('bg-indigo-100', 'text-indigo-800');
            } else {
                chip.classList.add('bg-gray-100', 'text-gray-800');
            }
        }
    }

    function setRowSelected(row, selected) {
        const tds = row.querySelectorAll('td');
        const chip = row.querySelector('.chip');

        if (selected) {
            row.classList.add('bg-stone-500', 'text-white');
            row.classList.remove('even:bg-gray-50', 'hover:bg-stone-50');

            tds.forEach(td => {
                td.classList.remove('text-gray-700');
                td.classList.add('text-white');
            });

            setChipSelected(chip, true);
        } else {
            row.classList.remove('bg-stone-500', 'text-white');
            row.classList.add('hover:bg-stone-50');
            // restaurar texto
            tds.forEach(td => {
                td.classList.remove('text-white');
                td.classList.add('text-gray-700');
            });
            setChipSelected(chip, false);
        }
    }

    function updateButtonVisibility() {
        if (current) btnProgramar.classList.remove('hidden');
        else btnProgramar.classList.add('hidden');
    }

    btnProgramar.onclick = async () => {
        if (!current) return;
        const idflog       = current.dataset.idflog || '';
        const itemid       = current.dataset.itemid || '';
        const inventsizeid = current.dataset.inventsizeid || '';
        const cantidad     = current.dataset.cantidad || '';
        const tipohilo     = current.dataset.tipohilo || '';

        // HTML del modal (SweetAlert2)
        const html = `
            <div class="text-left text-sm">
                <div class="mb-4">
                    <div class="space-y-2">
                        <div>
                            <div class="text-xs text-gray-500 mb-1">Tamaño</div>
                            <div class="p-2 border rounded bg-gray-50">${inventsizeid || ''}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 mb-1">Artículo</div>
                            <div class="p-2 border rounded bg-gray-50">${itemid || ''}</div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Salón</label>
                    <select id="swal-salon" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-1 focus:ring-stone-500 focus:border-stone-500 text-sm">
                        <option value="">Seleccione salón...</option>
                        <option value="SMIT">SMIT</option>
                        <option value="JACQUARD">JACQUARD</option>
                        <option value="SULZER">SULZER</option>
                    </select>
                </div>

                <div class="mb-1 relative">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Clave modelo</label>
                    <input id="swal-clave" type="text" placeholder="Escriba la clave..." class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-1 focus:ring-stone-500 focus:border-stone-500 text-sm" autocomplete="off" />
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
                const claveInput = document.getElementById('swal-clave');
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
                        return `<div class="px-3 py-2 hover:bg-stone-50 cursor-pointer text-sm border-b border-gray-100 last:border-b-0" data-clave="${clave.replace(/"/g, '&quot;')}">
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

                    if (!salon || !inventsizeid || !itemid) {
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
                        url.searchParams.set('itemid', itemid);
                        url.searchParams.set('inventsizeid', inventsizeid);
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
                                const clave = ((data.InventSizeId || inventsizeid) + (data.ItemId || itemid)).toUpperCase().replace(/[\s\-_]+/g, '');
                                claveInput.value = clave;
                                // Ocultar error si existe
                                errorMsg.classList.add('hidden');
                            } else {
                                // No se encontró el modelo - mostrar mensaje debajo del input
                                errorMsg.textContent = `No se encontró un modelo para el artículo ${itemid}, tamaño ${inventsizeid} en el salón ${salon}. Por favor ingrese la clave modelo manualmente.`;
                                errorMsg.classList.remove('hidden');
                                claveInput.classList.add('border-red-500');
                            }
                        } else {
                            // Error en la respuesta o modelo no encontrado - mostrar mensaje debajo del input
                            errorMsg.textContent = `No se encontró un modelo para el artículo ${itemid}, tamaño ${inventsizeid} en el salón ${salon}. Por favor ingrese la clave modelo manualmente.`;
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
                const claveModelo = document.getElementById('swal-clave').value.trim();

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

                    if (itemid) {
                        searchUrl.searchParams.set('itemid', itemid);
                    }
                    if (inventsizeid) {
                        searchUrl.searchParams.set('inventsizeid', inventsizeid);
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
                        tamano: inventsizeid,
                        articulo: itemid,
                        modeloData: data
                    };
                } catch (error) {
                    Swal.hideLoading();
                    Swal.showValidationMessage('Error al validar la clave modelo');
                    return false;
                }
            }
        });

        if (!swalRes.isConfirmed) return;
        const { salon, claveModelo } = swalRes.value || { salon:'', claveModelo:'' };

        // Redirigir con parámetros (solo si llegamos aquí, significa que el modelo existe)
        const url = new URL('{{ route("programa-tejido.altas-especiales.nuevo") }}', window.location.origin);
        url.searchParams.set('idflog', idflog);
        if (itemid)       url.searchParams.set('itemid', itemid);
        if (inventsizeid) url.searchParams.set('inventsizeid', inventsizeid);
        if (cantidad)     url.searchParams.set('cantidad', cantidad);
        if (tipohilo)     url.searchParams.set('tipohilo', tipohilo);
        if (salon)        url.searchParams.set('salon', salon);
        if (claveModelo)  url.searchParams.set('clavemodelo', claveModelo);
        window.location.href = url.toString();
    };

    document.querySelectorAll('tr.select-row').forEach(row => {
        row.addEventListener('click', () => {
            if (current === row) {
                setRowSelected(row, false);
                current = null;
                updateButtonVisibility();
                return;
            }
            if (current) setRowSelected(current, false);
            current = row;
            setRowSelected(row, true);
            updateButtonVisibility();
        });
    });
});
</script>
@endsection
