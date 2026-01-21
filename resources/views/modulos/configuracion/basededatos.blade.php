@extends('layouts.app')

@section('page-title', 'Base de Datos')

@section('content')
<div class="w-full px-4 py-6">
    <div class="bg-white rounded-2xl  overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full" id="usuariosTable">
                <thead class="bg-blue-500 text-white">
                    <tr>
                        <th class="column-header px-6 py-4 text-left text-sm font-semibold text-white uppercase tracking-wider cursor-pointer hover:bg-blue-600 transition-colors relative" data-column="usuario" data-column-index="0">
                            Usuario
                            <i class="fas fa-filter ml-2 text-sm opacity-70"></i>
                        </th>
                        <th class="column-header px-6 py-4 text-left text-sm font-semibold text-white uppercase tracking-wider cursor-pointer hover:bg-blue-600 transition-colors relative" data-column="area" data-column-index="1">
                            Area
                            <i class="fas fa-filter ml-2 text-sm opacity-70"></i>
                        </th>
                        <th class="column-header px-6 py-4 text-left text-sm font-semibold text-white uppercase tracking-wider cursor-pointer hover:bg-blue-600 transition-colors relative" data-column="puesto" data-column-index="2">
                            Puesto
                            <i class="fas fa-filter ml-2 text-sm opacity-70"></i>
                        </th>
                        <th class="column-header px-6 py-4 text-center text-xs font-semibold text-white uppercase tracking-wider cursor-pointer hover:bg-blue-600 transition-colors relative" data-column="estado" data-column-index="3">
                            Estado
                            <i class="fas fa-filter ml-2 text-sm opacity-70"></i>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white ">
                    @forelse($usuarios as $usuario)
                        @php
                            $nombre = trim($usuario->nombre ?? '');
                            $inicial = $nombre !== '' ? strtoupper(substr($nombre, 0, 1)) : '?';
                        @endphp
                        <tr class="table-row" data-usuario="{{ strtolower($usuario->nombre ?? '') }}" data-area="{{ strtolower($usuario->area ?? '') }}" data-puesto="{{ strtolower($usuario->puesto ?? '') }}" data-estado="{{ ($usuario->Productivo ?? 1) == 2 ? 'productivo' : 'prueba' }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-blue-500 text-white flex items-center justify-center text-sm font-semibold shadow-sm">
                                        {{ $inicial }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900">{{ $usuario->nombre ?? 'Sin nombre' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-700">{{ $usuario->area ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-700">{{ $usuario->puesto ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <label class="switch-container">
                                    <input
                                        type="hidden"
                                        name="productivo_{{ $usuario->idusuario }}"
                                        value="{{ $usuario->Productivo ?? 1 }}"
                                        class="switch-value"
                                    >
                                    <input
                                        type="checkbox"
                                        class="switch-input"
                                        data-user-id="{{ $usuario->idusuario }}"
                                        {{ ($usuario->Productivo ?? 1) == 2 ? 'checked' : '' }}
                                        aria-label="Cambiar estado productivo"
                                    >
                                    <span class="switch-slider"></span>
                                    <span class="switch-label">
                                        <span class="switch-label-off">Prueba</span>
                                        <span class="switch-label-on">Productivo</span>
                                    </span>
                                </label>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                                        <i class="fas fa-users text-gray-400 text-xl"></i>
                                    </div>
                                    <p class="text-sm font-medium text-gray-900">No hay usuarios disponibles</p>
                                    <p class="text-xs text-gray-500 mt-1">Los usuarios aparecerán aquí cuando estén registrados</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Menú Contextual (Click Derecho) -->
<div id="contextMenu" class="hidden fixed bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-50 min-w-[200px]">
    <button class="context-menu-item w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors flex items-center gap-2" data-action="filter">
        <i class="fas fa-filter w-4"></i>
        <span>Filtrar columna</span>
    </button>
    <hr class="my-1 border-gray-200">
    <button class="context-menu-item w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors flex items-center gap-2" data-action="clear">
        <i class="fas fa-times-circle w-4"></i>
        <span>Limpiar filtros</span>
    </button>
</div>


<style>
    /* Switch estilo Apple mejorado */
    .switch-container {
        display: inline-flex;
        align-items: center;
        cursor: pointer;
        user-select: none;
        gap: 0.75rem;
    }

    .switch-input {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }

    .switch-slider {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 26px;
        background-color: #d1d5db;
        border-radius: 13px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .switch-slider::before {
        content: '';
        position: absolute;
        height: 22px;
        width: 22px;
        left: 2px;
        top: 2px;
        background-color: white;
        border-radius: 50%;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2), 0 1px 2px rgba(0, 0, 0, 0.15);
        transform: translateX(0);
    }

    .switch-input:checked + .switch-slider {
        background-color: #2563eb;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.15), 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .switch-input:checked + .switch-slider::before {
        transform: translateX(18px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25), 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    .switch-input:focus + .switch-slider {
        outline: 2px solid transparent;
        outline-offset: 2px;
    }

    .switch-input:focus:not(:checked) + .switch-slider {
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1), 0 0 0 3px rgba(156, 163, 175, 0.3);
    }

    .switch-input:focus:checked + .switch-slider {
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.15), 0 0 0 3px rgba(37, 99, 235, 0.3);
    }

    .switch-input:active + .switch-slider::before {
        width: 26px;
    }

    .switch-input:checked:active + .switch-slider::before {
        transform: translateX(16px);
    }

    .switch-label {
        font-size: 0.75rem;
        line-height: 1rem;
    }

    .switch-label-off,
    .switch-label-on {
        transition: all 0.2s ease-in-out;
    }

    .switch-label-off {
        color: #6b7280;
        display: inline;
    }

    .switch-label-on {
        color: #2563eb;
        font-weight: 500;
        display: none;
    }

    .switch-input:checked ~ .switch-label .switch-label-off {
        display: none;
    }

    .switch-input:checked ~ .switch-label .switch-label-on {
        display: inline;
    }

    .switch-container:hover .switch-label-off {
        color: #374151;
    }

    .switch-container:hover .switch-input:checked ~ .switch-label .switch-label-on {
        color: #1d4ed8;
    }

    .switch-container:hover .switch-slider {
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.12);
    }

    .switch-container:hover .switch-input:checked + .switch-slider {
        background-color: #1d4ed8;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.18), 0 0 0 4px rgba(37, 99, 235, 0.12);
    }

    /* Estilos para menú contextual y filtros */
    .table-row.hidden {
        display: none;
    }

    .context-menu-item {
        transition: all 0.15s ease;
    }

</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Variables globales
        let currentColumn = null;
        let filters = {
            usuario: { values: [], operator: 'contains', search: '' },
            area: { values: [], operator: 'contains', search: '' },
            puesto: { values: [], operator: 'contains', search: '' },
            estado: { values: [], operator: 'contains', search: '' }
        };

        // Switch functionality
        const switches = document.querySelectorAll('.switch-input');
        switches.forEach(switchInput => {
            switchInput.addEventListener('change', function() {
                const userId = this.dataset.userId;
                const value = this.checked ? 2 : 1;
                const hiddenInput = this.parentElement.querySelector('.switch-value');
                if (hiddenInput) {
                    hiddenInput.value = value;
                }
            });
        });

        // Menú contextual (click derecho)
        const contextMenu = document.getElementById('contextMenu');
        const columnHeaders = document.querySelectorAll('.column-header');

        columnHeaders.forEach(header => {
            header.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                currentColumn = this.dataset.column;

                // Posicionar menú
                contextMenu.style.left = e.pageX + 'px';
                contextMenu.style.top = e.pageY + 'px';
                contextMenu.classList.remove('hidden');
            });
        });

        // Cerrar menú contextual al hacer click fuera
        document.addEventListener('click', function(e) {
            if (!contextMenu.contains(e.target) && !e.target.closest('.column-header')) {
                contextMenu.classList.add('hidden');
            }
        });

        // Acciones del menú contextual
        document.querySelectorAll('.context-menu-item').forEach(item => {
            item.addEventListener('click', function() {
                const action = this.dataset.action;

                if (action === 'filter') {
                    openFilterModal();
                } else if (action === 'clear') {
                    clearFilters();
                }

                contextMenu.classList.add('hidden');
            });
        });

        function openFilterModal() {
            if (!currentColumn) return;

            const columnName = currentColumn.charAt(0).toUpperCase() + currentColumn.slice(1);
            const uniqueValues = getUniqueValues(currentColumn);
            const currentFilter = filters[currentColumn] || { values: [], operator: 'contains', search: '' };

            // Generar HTML para los checkboxeseee
            let checkboxesHTML = '';
            uniqueValues.forEach(value => {
                const isChecked = currentFilter.values.includes(value) ? 'checked' : '';
                checkboxesHTML += `
                    <label class="flex items-center gap-2 cursor-pointer p-2 hover:bg-gray-50 rounded" style="display: flex;">
                        <input type="checkbox" class="swal-filter-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500" value="${value}" ${isChecked}>
                        <span class="text-sm text-gray-700">${value}</span>
                    </label>
                `;
            });

            // HTML del modal
            const modalHTML = `
                <div style="text-align: left;">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
                        <input
                            type="text"
                            id="swal-filter-search"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Buscar en la columna..."
                            value="${currentFilter.search || ''}"
                        >
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Seleccionar valores</label>
                        <div id="swal-filter-checkboxes" class="max-h-64 overflow-y-auto border border-gray-200 rounded-md p-3 space-y-2" style="max-height: 16rem; overflow-y: auto;">
                            ${checkboxesHTML}
                        </div>
                    </div>

                    <div class="flex items-center gap-4 mb-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" id="swal-select-all" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span>Seleccionar todo</span>
                        </label>
                        <button type="button" id="swal-clear-selection" class="text-sm text-blue-600 hover:text-blue-800">
                            Limpiar selección
                        </button>
                    </div>

                    <div class="border-t pt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Filtros avanzados</label>
                        <select id="swal-filter-operator" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="contains" ${currentFilter.operator === 'contains' ? 'selected' : ''}>Contiene</option>
                            <option value="equals" ${currentFilter.operator === 'equals' ? 'selected' : ''}>Es igual a</option>
                            <option value="startsWith" ${currentFilter.operator === 'startsWith' ? 'selected' : ''}>Comienza con</option>
                            <option value="endsWith" ${currentFilter.operator === 'endsWith' ? 'selected' : ''}>Termina con</option>
                            <option value="notContains" ${currentFilter.operator === 'notContains' ? 'selected' : ''}>No contiene</option>
                        </select>
                    </div>
                </div>
            `;

            Swal.fire({
                title: `<i class="fas fa-filter"></i> Filtrar: ${columnName}`,
                html: modalHTML,
                width: '500px',
                showCancelButton: true,
                confirmButtonText: 'Aplicar filtro',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280',
                didOpen: () => {
                    const filterSearch = document.getElementById('swal-filter-search');
                    const filterCheckboxes = document.getElementById('swal-filter-checkboxes');
                    const selectAll = document.getElementById('swal-select-all');
                    const clearSelection = document.getElementById('swal-clear-selection');
                    const filterOperator = document.getElementById('swal-filter-operator');

                    // Funcionalidad de búsqueda
                    if (filterSearch) {
                        filterSearch.addEventListener('input', function() {
                            const searchTerm = this.value.toLowerCase();
                            const labels = filterCheckboxes.querySelectorAll('label');

                            labels.forEach(label => {
                                const text = label.textContent.toLowerCase();
                                label.style.display = text.includes(searchTerm) ? 'flex' : 'none';
                            });
                        });
                    }

                    // Seleccionar todo
                    if (selectAll) {
                        updateSelectAllState();

                        selectAll.addEventListener('change', function() {
                            const checkboxes = filterCheckboxes.querySelectorAll('.swal-filter-checkbox');
                            checkboxes.forEach(checkbox => {
                                checkbox.checked = this.checked;
                            });
                        });
                    }

                    // Limpiar selección
                    if (clearSelection) {
                        clearSelection.addEventListener('click', function() {
                            const checkboxes = filterCheckboxes.querySelectorAll('.swal-filter-checkbox');
                            checkboxes.forEach(checkbox => checkbox.checked = false);
                            if (selectAll) selectAll.checked = false;
                        });
                    }

                    // Actualizar estado de "Seleccionar todo" cuando cambian los checkboxes
                    if (filterCheckboxes) {
                        filterCheckboxes.addEventListener('change', function(e) {
                            if (e.target.classList.contains('swal-filter-checkbox')) {
                                updateSelectAllState();
                            }
                        });
                    }

                    function updateSelectAllState() {
                        const checkboxes = filterCheckboxes.querySelectorAll('.swal-filter-checkbox');
                        const checked = filterCheckboxes.querySelectorAll('.swal-filter-checkbox:checked');
                        if (selectAll) {
                            selectAll.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
                        }
                    }
                },
                preConfirm: () => {
                    const filterCheckboxes = document.getElementById('swal-filter-checkboxes');
                    const filterSearch = document.getElementById('swal-filter-search');
                    const filterOperator = document.getElementById('swal-filter-operator');

                    const selectedCheckboxes = filterCheckboxes.querySelectorAll('.swal-filter-checkbox:checked');
                    const selectedValues = Array.from(selectedCheckboxes).map(cb => cb.value);

                    filters[currentColumn] = {
                        values: selectedValues,
                        operator: filterOperator.value,
                        search: filterSearch.value
                    };

                    applyFilters();
                }
            });
        }

        function getUniqueValues(column) {
            const rows = document.querySelectorAll('.table-row');
            const values = new Set();

            rows.forEach(row => {
                const value = row.dataset[column] || '';
                if (value && value !== '-') {
                    values.add(value);
                }
            });

            return Array.from(values).sort();
        }

        function applyFilters() {
            const rows = document.querySelectorAll('.table-row');

            rows.forEach(row => {
                let show = true;

                Object.keys(filters).forEach(column => {
                    const filter = filters[column];
                    if (!filter || (!filter.values.length && !filter.search)) {
                        return;
                    }

                    const cellValue = (row.dataset[column] || '').toLowerCase();
                    let matches = true;

                    if (filter.search) {
                        const searchTerm = filter.search.toLowerCase();
                        switch(filter.operator) {
                            case 'contains':
                                matches = cellValue.includes(searchTerm);
                                break;
                            case 'equals':
                                matches = cellValue === searchTerm;
                                break;
                            case 'startsWith':
                                matches = cellValue.startsWith(searchTerm);
                                break;
                            case 'endsWith':
                                matches = cellValue.endsWith(searchTerm);
                                break;
                            case 'notContains':
                                matches = !cellValue.includes(searchTerm);
                                break;
                        }
                    }

                    if (filter.values.length > 0) {
                        matches = matches && filter.values.some(val =>
                            cellValue.includes(val.toLowerCase())
                        );
                    }

                    if (!matches) {
                        show = false;
                    }
                });

                if (show) {
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                }
            });
        }

        function clearFilters() {
            filters = {
                usuario: { values: [], operator: 'contains', search: '' },
                area: { values: [], operator: 'contains', search: '' },
                puesto: { values: [], operator: 'contains', search: '' },
                estado: { values: [], operator: 'contains', search: '' }
            };

            const rows = document.querySelectorAll('.table-row');
            rows.forEach(row => row.classList.remove('hidden'));
        }
    });
</script>

@endsection
