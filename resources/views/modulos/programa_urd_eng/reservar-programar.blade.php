@extends('layouts.app', ['ocultarBotones' => true])

@section('menu-planeacion')
<!-- Botones para Programa Urd/Eng -->
<div class="flex items-center gap-2">
	<button type="button" onclick="resetFilters(); return false;"
			class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
		<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
		</svg>
		Restablecer
	</button>

	<button type="button" onclick="openFilterModal(); return false;"
			class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
		<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
		</svg>
		Filtros
		<span id="filterCount" class="ml-2 px-2 py-0.5 bg-white text-purple-600 rounded-full text-xs font-bold hidden">0</span>
	</button>
</div>
@endsection

@section('content')
<div class="container mx-auto px-2 py-2 max-w-full">

    <!-- Sección: Tabla de Inventario de Telares -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="bg-blue-500 px-4 py-2 flex justify-between items-center">
            <h2 class="text-lg font-bold text-white text-center flex-1">
                Programación de requerimientos
            </h2>
            <button type="button" id="btnProgramar" onclick="programarTelar()" disabled
                    class="inline-flex items-center px-3 py-1.5 bg-gray-400 text-white text-sm rounded-lg transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed enabled:bg-green-600 enabled:hover:bg-green-700">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Programar
            </button>
        </div>

        @if(isset($inventarioTelares) && $inventarioTelares->count() > 0)
            <div class="overflow-hidden">


                <!-- Tabla con scroll -->
                <div class="overflow-x-auto">
                    <div class="overflow-y-auto max-h-[330px]">
                        <table id="telaresTable" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100 text-gray-900 sticky top-0" style="z-index: 20;">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                        No. Telar
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                        Tipo
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                        Cuenta
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                        Calibre
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                        Fecha
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                        Turno
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                        Hilo
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                        Metros
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                        No. Julio
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                        No. Orden
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                        Tipo Atado
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                        Salón
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach($inventarioTelares as $index => $telar)
                                    <tr class="hover:bg-blue-200 transition-colors cursor-pointer selectable-row {{ $index % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}"
                                        data-row-index="{{ $index }}"
                                        data-telar="{{ $telar->no_telar }}"
                                        onclick="selectRow(this, {{ $index }}, '{{ $telar->no_telar }}')">
                                        <td class="px-3 py-1.5 text-sm font-bold text-gray-900 whitespace-nowrap">
                                            {{ $telar->no_telar }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">
                                            @php
                                                $tipo = strtoupper(trim($telar->tipo ?? '-'));
                                                $tipoColor = match($tipo) {
                                                    'RIZO' => 'bg-rose-100 text-rose-700',
                                                    'PIE' => 'bg-teal-100 text-teal-700',
                                                    default => 'bg-gray-100 text-gray-700'
                                                };
                                            @endphp
                                            <span class="px-2 py-1 {{ $tipoColor }} rounded text-xs font-medium">
                                                {{ $telar->tipo ?? '-' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">
                                            {{ $telar->cuenta ?? '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">
                                            {{ $telar->calibre ? number_format($telar->calibre, 2) : '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">
                                            {{ $telar->fecha ? \Carbon\Carbon::parse($telar->fecha)->format('d-M-Y') : '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            @if($telar->turno)
                                                <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs font-medium">
                                                    {{ $telar->turno }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">
                                            {{ $telar->hilo ?? '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-right">
                                            {{ $telar->metros ? number_format($telar->metros, 2) : '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">
                                            {{ $telar->no_julio ?? '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">
                                            {{ $telar->no_orden ?? '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">
                                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs font-medium">
                                                {{ $telar->tipo_atado ?? 'Normal' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">
                                            @php
                                                $salon = $telar->salon ?? 'Jacquard';
                                                $salonColors = [
                                                    'Jacquard' => 'bg-pink-100 text-pink-700',
                                                    'JACQUARD' => 'bg-pink-100 text-pink-700',
                                                    'Itema' => 'bg-purple-100 text-purple-700',
                                                    'ITEMA' => 'bg-purple-100 text-purple-700',
                                                    'Smith' => 'bg-cyan-100 text-cyan-700',
                                                    'SMITH' => 'bg-cyan-100 text-cyan-700',
                                                    'Karl Mayer' => 'bg-amber-100 text-amber-700',
                                                    'KARL MAYER' => 'bg-amber-100 text-amber-700',
                                                    'Sulzer' => 'bg-lime-100 text-lime-700',
                                                    'SULZER' => 'bg-lime-100 text-lime-700',
                                                ];
                                                $colorClass = $salonColors[trim($salon)] ?? 'bg-indigo-100 text-indigo-700';
                                            @endphp
                                            <span class="px-2 py-1 {{ $colorClass }} rounded text-xs font-medium">
                                                {{ $salon }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">No hay inventario disponible</h3>
                <p class="mt-2 text-sm text-gray-500">No se han registrado telares en el inventario.</p>
                <div class="mt-6">
                    <button onclick="window.location.reload()"
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Recargar
                    </button>
                </div>
            </div>
        @endif
    </div>

    <!-- Segunda Tabla: Inventario Disponible -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden mt-2">
        <div class="bg-blue-500 px-4 py-2 flex justify-between items-center">
            <h2 class="text-lg font-bold text-white text-center flex-1">
                Inventario Disponible
            </h2>
            <button type="button" id="btnReservar" onclick="reservarInventario()" disabled
                    class="inline-flex items-center px-3 py-1.5 bg-gray-400 text-white text-sm rounded-lg transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed enabled:bg-orange-600 enabled:hover:bg-orange-700">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Reservar
            </button>
        </div>

        <div class="overflow-hidden">
            <!-- Tabla con scroll -->
            <div class="overflow-x-auto">
                <div class="overflow-y-auto max-h-[200px]">
                    <table id="inventarioTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100 text-gray-900 sticky top-0" style="z-index: 20;">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                    Artículo
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                    Tipo
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                    Cantidad
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                    Hilo
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                    Cuenta
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                    Color
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                    Almacén
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                    Orden
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                    Localidad
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                    No
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                    Julio
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                    Metros
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                    Fecha
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                    Telar
                                </th>
                                <th class="px-3 py-2 text-center text-xs font-medium uppercase tracking-wider whitespace-nowrap">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <!-- Los datos se cargarán dinámicamente aquí -->
                            <tr>
                                <td colspan="15" class="px-4 py-8 text-center text-sm text-gray-500">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    No hay datos de inventario disponible por el momento
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Filtros Avanzados -->
<div id="filterModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-3xl shadow-lg rounded-lg bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Filtrar Tablas</h3>
                <button onclick="closeFilterModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div id="filterFormContainer" class="space-y-4">
                <!-- Selector de tabla -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tabla a filtrar:</label>
                    <select id="tableSelector" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="telares">Programación de Requerimientos</option>
                        <option value="inventario">Inventario Disponible</option>
                    </select>
                </div>

                <!-- Contenedor de filtros dinámicos -->
                <div id="filtersInputContainer"></div>

                <button type="button" onclick="addFilterRow()"
                        class="w-full px-4 py-2 border-2 border-dashed border-gray-300 text-gray-600 rounded-lg hover:border-purple-500 hover:text-purple-600 transition-colors text-sm font-medium">
                    + Agregar otro filtro
                </button>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeFilterModal()"
                        class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    Cancelar
                </button>
                <button type="button" onclick="applyFilters(); return false;"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    Aplicar Filtros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 para notificaciones -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Estado global
let filters = [];
let allRowsTelares = [];
let allRowsInventario = [];
let selectedRow = null; // Solo una fila seleccionada
let selectedTelarData = null; // Solo un telar
let columnsDataTelares = [
    {field: 'no_telar', label: 'No. Telar'},
    {field: 'tipo', label: 'Tipo'},
    {field: 'cuenta', label: 'Cuenta'},
    {field: 'calibre', label: 'Calibre'},
    {field: 'fecha', label: 'Fecha'},
    {field: 'turno', label: 'Turno'},
    {field: 'hilo', label: 'Hilo'},
    {field: 'metros', label: 'Metros'},
    {field: 'no_julio', label: 'No. Julio'},
    {field: 'no_orden', label: 'No. Orden'},
    {field: 'tipo_atado', label: 'Tipo Atado'},
    {field: 'salon', label: 'Salón'}
];
let columnsDataInventario = [
    {field: 'articulo', label: 'Artículo'},
    {field: 'tipo', label: 'Tipo'},
    {field: 'cantidad', label: 'Cantidad'},
    {field: 'hilo', label: 'Hilo'},
    {field: 'cuenta', label: 'Cuenta'},
    {field: 'color', label: 'Color'},
    {field: 'almacen', label: 'Almacén'},
    {field: 'orden', label: 'Orden'},
    {field: 'localidad', label: 'Localidad'},
    {field: 'no', label: 'No'},
    {field: 'julio', label: 'Julio'},
    {field: 'metros', label: 'Metros'},
    {field: 'fecha', label: 'Fecha'},
    {field: 'telar', label: 'Telar'}
];

// ===== FUNCIONES DE MODAL =====
function openFilterModal() {
    document.getElementById('filterModal').classList.remove('hidden');
    if (filters.length === 0) {
        addFilterRow();
    }
}

function closeFilterModal() {
    document.getElementById('filterModal').classList.add('hidden');
}

// ===== FUNCIONES DE FILTROS =====
function addFilterRow() {
    const container = document.getElementById('filtersInputContainer');
    const filterIndex = filters.length;
    const selectedTable = document.getElementById('tableSelector').value;
    const columnsData = selectedTable === 'telares' ? columnsDataTelares : columnsDataInventario;

    const filterRow = document.createElement('div');
    filterRow.className = 'filter-row p-4 border border-gray-200 rounded-lg bg-gray-50';
    filterRow.dataset.filterIndex = filterIndex;
    filterRow.innerHTML = `
        <div class="flex items-center gap-3">
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-700 mb-1">Columna</label>
                <select class="filter-column w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Selecciona una columna...</option>
                    ${columnsData.map(col => `<option value="${col.field}">${col.label}</option>`).join('')}
                </select>
            </div>
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-700 mb-1">Valor</label>
                <input type="text" class="filter-value w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Escribe el valor...">
            </div>
            <div class="pt-6">
                <button type="button" onclick="removeFilter(${filterIndex})" class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors" title="Eliminar filtro">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    `;

    container.appendChild(filterRow);
    filters.push({ column: '', value: '', index: filterIndex, table: selectedTable });
}

function removeFilter(index) {
    const filterRow = document.querySelector(`[data-filter-index="${index}"]`);
    if (filterRow) {
        filterRow.remove();
    }
    filters = filters.filter(f => f.index !== index);
    updateFilterCount();
}

function applyFilters() {
    const activeFilters = [];
    const filterRows = document.querySelectorAll('.filter-row');
    const selectedTable = document.getElementById('tableSelector').value;

    filterRows.forEach(row => {
        const column = row.querySelector('.filter-column').value;
        const value = row.querySelector('.filter-value').value;

        if (column && value) {
            activeFilters.push({ column, value, table: selectedTable });
        }
    });

    if (activeFilters.length === 0) {
        Swal.fire('Sin filtros', 'Debes agregar al menos un filtro válido', 'warning');
        return;
    }

    // Aplicar filtros según la tabla seleccionada
    if (selectedTable === 'telares') {
        filterTable('telares', activeFilters, allRowsTelares);
    } else {
        filterTable('inventario', activeFilters, allRowsInventario);
    }

    filters = activeFilters;
    updateFilterCount();
    closeFilterModal();

    Swal.fire({
        title: 'Filtros aplicados',
        text: `Se aplicaron ${activeFilters.length} filtro(s)`,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

function filterTable(tableType, activeFilters, allRows) {
    let visibleRows = allRows;

    activeFilters.forEach(filter => {
        visibleRows = visibleRows.filter(row => {
            const cells = row.querySelectorAll('td');
            let found = false;
            cells.forEach(cell => {
                const cellValue = cell.textContent.toLowerCase();
                const filterValue = filter.value.toLowerCase();
                if (cellValue.includes(filterValue)) {
                    found = true;
                }
            });
            return found;
        });
    });

    // Actualizar tabla
    const tbody = document.querySelector(`#${tableType}Table tbody`);
    if (tbody) {
        tbody.innerHTML = '';
        visibleRows.forEach(row => tbody.appendChild(row.cloneNode(true)));
    }
}

function resetFilters() {
    // Restaurar todas las filas
    const tbodyTelares = document.querySelector('#telaresTable tbody');
    if (tbodyTelares && allRowsTelares.length > 0) {
        tbodyTelares.innerHTML = '';
        allRowsTelares.forEach(row => tbodyTelares.appendChild(row.cloneNode(true)));
    }

    const tbodyInventario = document.querySelector('#inventarioTable tbody');
    if (tbodyInventario && allRowsInventario.length > 0) {
        tbodyInventario.innerHTML = '';
        allRowsInventario.forEach(row => tbodyInventario.appendChild(row.cloneNode(true)));
    }

    // Limpiar estado
    filters = [];
    document.getElementById('filtersInputContainer').innerHTML = '';
    updateFilterCount();

    Swal.fire({
        title: 'Restablecido',
        text: 'Todos los filtros han sido eliminados',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

function updateFilterCount() {
    const badge = document.getElementById('filterCount');
    if (filters.length > 0) {
        badge.textContent = filters.length;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

// ===== FUNCIONES DE SELECCIÓN DE FILAS =====
function selectRow(rowElement, rowIndex, noTelar) {
    // Si la fila ya está seleccionada, deseleccionarla
    if (rowElement.classList.contains('bg-blue-500')) {
        // Deseleccionar esta fila específica
        rowElement.classList.remove('bg-blue-500', 'text-white');
        rowElement.classList.add('hover:bg-blue-200');

        // Restaurar color de texto de las celdas y sus elementos internos
        const cells = rowElement.querySelectorAll('td');
        cells.forEach(cell => {
            cell.classList.remove('text-white');
            cell.classList.add('text-gray-700');
            // Restaurar colores de los spans internos
            const spans = cell.querySelectorAll('span');
            spans.forEach(span => {
                span.classList.remove('badge-selected');
            });
        });

        // Limpiar selección
        selectedRow = null;
        selectedTelarData = null;

        // Deshabilitar botón
            const btnProgramar = document.getElementById('btnProgramar');
            if (btnProgramar) {
                btnProgramar.disabled = true;
        }

        console.log('Fila deseleccionada:', rowIndex, 'Telar:', noTelar);
        return;
    }

    // Deseleccionar cualquier fila previamente seleccionada
    const previouslySelected = document.querySelector('.selectable-row.bg-blue-500');
    if (previouslySelected) {
        previouslySelected.classList.remove('bg-blue-500', 'text-white');
        previouslySelected.classList.add('hover:bg-blue-200');

        const prevCells = previouslySelected.querySelectorAll('td');
        prevCells.forEach(cell => {
            cell.classList.remove('text-white');
            cell.classList.add('text-gray-700');
            const spans = cell.querySelectorAll('span');
            spans.forEach(span => {
                span.classList.remove('badge-selected');
            });
        });
    }

    // Seleccionar la nueva fila
    rowElement.classList.add('bg-blue-500', 'text-white');
    rowElement.classList.remove('hover:bg-blue-200', 'bg-white', 'bg-gray-50');

    // Cambiar color de texto de las celdas y sus elementos internos
    const cells = rowElement.querySelectorAll('td');
    cells.forEach(cell => {
        cell.classList.add('text-white');
        cell.classList.remove('text-gray-700', 'text-gray-900');
        // Agregar clase especial a los badges
        const spans = cell.querySelectorAll('span');
        spans.forEach(span => {
            span.classList.add('badge-selected');
        });
    });

    // Guardar selección (solo una fila)
    selectedRow = rowIndex;
    selectedTelarData = {
        no_telar: noTelar,
        rowIndex: rowIndex
    };

    // Habilitar el botón Programar
    const btnProgramar = document.getElementById('btnProgramar');
    if (btnProgramar) {
        btnProgramar.disabled = false;
    }

    console.log('Fila seleccionada:', rowIndex, 'Telar:', noTelar);
}

function deselectRow() {
    // Remover selección de la fila seleccionada
    const selectedRowElement = document.querySelector('.selectable-row.bg-blue-500');
    if (selectedRowElement) {
        selectedRowElement.classList.remove('bg-blue-500', 'text-white');
        selectedRowElement.classList.add('hover:bg-blue-200');

        // Restaurar color de texto de las celdas y sus elementos internos
        const cells = selectedRowElement.querySelectorAll('td');
        cells.forEach(cell => {
            cell.classList.remove('text-white');
            cell.classList.add('text-gray-700');
            // Restaurar colores de los spans internos
            const spans = cell.querySelectorAll('span');
            spans.forEach(span => {
                span.classList.remove('badge-selected');
            });
        });
    }

    // Limpiar selección
    selectedRow = null;
    selectedTelarData = null;

    // Deshabilitar el botón Programar
    const btnProgramar = document.getElementById('btnProgramar');
    if (btnProgramar) {
        btnProgramar.disabled = true;
    }

    console.log('Fila deseleccionada');
}

// Función para reservar inventario (placeholder)
function reservarInventario() {
    Swal.fire({
        title: 'Reservar inventario',
        text: 'Esta funcionalidad estará disponible próximamente',
        icon: 'info',
        confirmButtonColor: '#f97316',
        confirmButtonText: 'Entendido'
    });
}

// Función para programar el telar seleccionado
function programarTelar() {
    if (!selectedTelarData) {
        Swal.fire('Error', 'No hay ningún telar seleccionado', 'error');
        return;
    }

    const noTelar = selectedTelarData.no_telar;

    Swal.fire({
        title: '¿Programar telar?',
        html: `¿Deseas programar el telar <strong>${noTelar}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, programar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Aquí puedes agregar la lógica para programar el telar
            Swal.fire({
                title: '¡Programado!',
                text: `El telar ${noTelar} ha sido programado exitosamente.`,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });

            // Deseleccionar la fila después de programar
            deselectRow();
        }
    });
}

// Cerrar modal al hacer clic fuera
document.getElementById('filterModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeFilterModal();
    }
});

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    // Guardar filas originales de la tabla de telares
    const tbodyTelares = document.querySelector('#telaresTable tbody');
    if (tbodyTelares) {
        allRowsTelares = Array.from(tbodyTelares.querySelectorAll('tr'));
    }

    // Guardar filas originales de la tabla de inventario
    const tbodyInventario = document.querySelector('#inventarioTable tbody');
    if (tbodyInventario) {
        allRowsInventario = Array.from(tbodyInventario.querySelectorAll('tr'));
    }

    // Inicializar contador de filtros
    const badge = document.getElementById('filterCount');
    if (badge) {
        badge.classList.add('hidden');
        badge.textContent = '0';
    }

    console.log('Vista de Reservar y Programar cargada correctamente');
});
</script>

<style>
/* Estilo para badges cuando la fila está seleccionada */
.badge-selected {
    background-color: rgba(255, 255, 255, 0.25) !important;
    color: white !important;
    border: 1px solid rgba(255, 255, 255, 0.4);
}

/* Forzar color blanco en texto de filas seleccionadas */
tr.bg-blue-500 td {
    color: white !important;
}

/* Texto fuerte en filas seleccionadas */
tr.bg-blue-500 .font-bold {
    color: white !important;
}
</style>
@endsection

