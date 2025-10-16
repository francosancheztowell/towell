@extends('layouts.app')

@section('content')
    <div class="container overflow-y-auto h-[600px]">

        <!-- Tabla 1: ReqCalendarioTab -->
        <div class="mb-6">

            <div class="bg-white overflow-hidden">
                <div class="overflow-y-auto max-h-[250px]">
                    <table class="table table-bordered table-sm w-full">
                        <thead class="sticky top-0 bg-blue-500 text-white z-10">
                            <tr>
                                <th style="width: 20%;" class="px-4  text-center font-semibold">No Calendario</th>
                                <th style="width: 80%;" class="px-4  text-center font-semibold">Nombre</th>
                    </tr>
                </thead>
                        <tbody id="calendario-tab-body" class="bg-white text-black">
                            @foreach ($calendarioTab as $item)
                                <tr class="text-center hover:bg-blue-50 transition cursor-pointer border-b border-gray-200"
                                    onclick="selectRowTab(this, '{{ $item->Calendariold }}')"
                                    ondblclick="deselectRowTab(this)"
                                    data-calendario="{{ $item->Calendariold }}">
                                    <td class="px-4  font-medium">{{ $item->Calendariold }}</td>
                                    <td class="px-4 ">{{ $item->Nombre }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
                </div>
            </div>
        </div>

        <!-- Tabla 2: ReqCalendarioLine -->
        <div class="mb-6">


            <div class="bg-white  overflow-hidden">
                <div class="overflow-y-auto max-h-[300px]">
                    <table class="table table-bordered table-sm w-full">
                        <thead class="sticky top-0 bg-blue-500 text-white z-10">
                            <tr>
                                <th style="width: 15%;" class="px-4  text-center font-semibold">No Calendario</th>
                                <th style="width: 25%;" class="px-4  text-center font-semibold">Inicio (Fecha Hora)</th>
                                <th style="width: 25%;" class="px-4  text-center font-semibold">Fin (Fecha Hora)</th>
                                <th style="width: 15%;" class="px-4  text-center font-semibold">Horas</th>
                                <th style="width: 20%;" class="px-4  text-center font-semibold">Turno</th>
                            </tr>
                        </thead>
                        <tbody id="calendario-line-body" class="bg-white text-black">
                            @foreach ($calendarioLine as $item)
                                <tr class="text-center hover:bg-green-50 transition cursor-pointer border-b border-gray-200"
                                    onclick="selectRowLine(this, '{{ $item->Calendariold }}-{{ $item->Turno }}')"
                                    ondblclick="deselectRowLine(this)"
                                    data-calendario-line="{{ $item->Calendariold }}-{{ $item->Turno }}">
                                    <td class="px-4  font-medium">{{ $item->Calendariold }}</td>
                                    <td class="px-4 ">{{ date('d/m/Y H:i', strtotime($item->Fechalnicio)) }}</td>
                                    <td class="px-4 ">{{ date('d/m/Y H:i', strtotime($item->FechaFin)) }}</td>
                                    <td class="px-4  font-semibold">{{ $item->HorasTurno }}</td>
                                    <td class="px-4  font-semibold">{{ $item->Turno }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let selectedCalendarioTab = null;
        let selectedCalendarioLine = null;
        let activeFiltersTab = [];
        let activeFiltersLine = [];
        let originalDataTab = [];
        let originalDataLine = [];

        // Funciones de selección para Tabla 1 (ReqCalendarioTab)
        function selectRowTab(row, calendarioId) {
            // Remover selección anterior de AMBAS tablas
            document.querySelectorAll('#calendario-tab-body tr').forEach(r => {
                r.classList.remove('bg-blue-500', 'text-white');
                r.classList.add('hover:bg-blue-50');
            });
            document.querySelectorAll('#calendario-line-body tr').forEach(r => {
                r.classList.remove('bg-green-500', 'text-white');
                r.classList.add('hover:bg-green-50');
            });

            // Seleccionar fila actual
            row.classList.remove('hover:bg-blue-50');
            row.classList.add('bg-blue-500', 'text-white');

            // Limpiar selecciones de la otra tabla
            selectedCalendarioLine = null;

            // Guardar calendario seleccionado
            selectedCalendarioTab = calendarioId;

            // Habilitar botones de editar y eliminar
            enableButtons();
        }

        function deselectRowTab(row) {
            if (row.classList.contains('bg-blue-500')) {
                row.classList.remove('bg-blue-500', 'text-white');
                row.classList.add('hover:bg-blue-50');
                selectedCalendarioTab = null;
                disableButtons();
            }
        }

        // Funciones de selección para Tabla 2 Calendario
        function selectRowLine(row, calendarioLineId) {
            // Remover selección anterior de AMBAS tablas
            document.querySelectorAll('#calendario-tab-body tr').forEach(r => {
                r.classList.remove('bg-blue-500', 'text-white');
                r.classList.add('hover:bg-blue-50');
            });
            document.querySelectorAll('#calendario-line-body tr').forEach(r => {
                r.classList.remove('bg-green-500', 'text-white');
                r.classList.add('hover:bg-green-50');
            });

            // Seleccionar fila actual
            row.classList.remove('hover:bg-green-50');
            row.classList.add('bg-green-500', 'text-white');

            // Limpiar selecciones de la otra tabla
            selectedCalendarioTab = null;

            // Guardar calendario line seleccionado
            selectedCalendarioLine = calendarioLineId;

            // Habilitar botones de editar y eliminar
            enableButtons();
        }

        function deselectRowLine(row) {
            if (row.classList.contains('bg-green-500')) {
                row.classList.remove('bg-green-500', 'text-white');
                row.classList.add('hover:bg-green-50');
                selectedCalendarioLine = null;
                disableButtons();
            }
        }

        // Funciones de botones unificadas
        function enableButtons() {
            const btnEditar = document.getElementById('btn-editar');
            const btnEliminar = document.getElementById('btn-eliminar');

            if (btnEditar && btnEliminar) {
                btnEditar.disabled = false;
                btnEliminar.disabled = false;
                btnEditar.className = 'inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm font-medium';
                btnEliminar.className = 'inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors text-sm font-medium';
            }
        }

        function disableButtons() {
            const btnEditar = document.getElementById('btn-editar');
            const btnEliminar = document.getElementById('btn-eliminar');

            if (btnEditar && btnEliminar) {
                btnEditar.disabled = true;
                btnEliminar.disabled = true;
                btnEditar.className = 'inline-flex items-center px-3 py-2 bg-gray-400 text-gray-200 rounded-lg transition-colors text-sm font-medium cursor-not-allowed';
                btnEliminar.className = 'inline-flex items-center px-3 py-2 bg-gray-400 text-gray-200 rounded-lg transition-colors text-sm font-medium cursor-not-allowed';
            }
        }

        // Funciones de modales SweetAlert para Calendarios
        function agregarCalendario() {
            Swal.fire({
                title: 'Agregar Nuevo Calendario',
                html: `
                    <div class="text-left space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">No Calendario</label>
                            <input type="text" id="agregar-calendario-id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: CAL011">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                            <input type="text" id="agregar-nombre" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: Calendario Noviembre">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Agregar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                width: '500px',
                preConfirm: () => {
                    const calendarioId = document.getElementById('agregar-calendario-id').value;
                    const nombre = document.getElementById('agregar-nombre').value;

                    if (!calendarioId || !nombre) {
                        Swal.showValidationMessage('Por favor completa todos los campos');
                        return false;
                    }
                    return { calendarioId, nombre };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    showToast(`Calendario ${result.value.nombre} agregado correctamente`, 'success');
                }
            });
        }

        function editarCalendario() {
            if (!selectedCalendarioTab && !selectedCalendarioLine) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una fila para editar',
                    icon: 'warning'
                });
                return;
            }

            if (selectedCalendarioTab) {
                // Editar calendario de la tabla 1
                const selectedRow = document.querySelector(`tr[data-calendario="${selectedCalendarioTab}"]`);
                if (!selectedRow) return;

                const cells = selectedRow.querySelectorAll('td');
                const calendarioId = cells[0].textContent;
                const nombre = cells[1].textContent;

                Swal.fire({
                    title: 'Editar Calendario',
                    html: `
                        <div class="text-left space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">No Calendario</label>
                                <input type="text" id="editar-calendario-id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="${calendarioId}" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                                <input type="text" id="editar-nombre" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="${nombre}">
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#6b7280',
                    width: '500px',
                    preConfirm: () => {
                        const calendarioId = document.getElementById('editar-calendario-id').value;
                        const nombre = document.getElementById('editar-nombre').value;

                        if (!calendarioId || !nombre) {
                            Swal.showValidationMessage('Por favor completa todos los campos');
                            return false;
                        }
                        return { calendarioId, nombre };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        showToast(`Calendario ${result.value.nombre} actualizado correctamente`, 'success');
                    }
                });
            } else if (selectedCalendarioLine) {
                // Editar línea de calendario de la tabla 2
                const selectedRow = document.querySelector(`tr[data-calendario-line="${selectedCalendarioLine}"]`);
                if (!selectedRow) return;

                const cells = selectedRow.querySelectorAll('td');
                const calendarioId = cells[0].textContent;
                const fechaInicio = cells[1].textContent;
                const fechaFin = cells[2].textContent;
                const horas = cells[3].textContent;
                const turno = cells[4].textContent;

                Swal.fire({
                    title: 'Editar Línea de Calendario',
                    html: `
                        <div class="text-left space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">No Calendario</label>
                                <input type="text" id="editar-line-calendario-id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="${calendarioId}" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Inicio (Fecha Hora)</label>
                                <input type="datetime-local" id="editar-fecha-inicio" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="2024-01-01T06:00">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fin (Fecha Hora)</label>
                                <input type="datetime-local" id="editar-fecha-fin" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="2024-01-01T14:00">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Horas</label>
                                <input type="number" step="0.1" id="editar-horas" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="${horas}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                                <input type="number" id="editar-turno" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="${turno}">
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#6b7280',
                    width: '500px',
                    preConfirm: () => {
                        const calendarioId = document.getElementById('editar-line-calendario-id').value;
                        const fechaInicio = document.getElementById('editar-fecha-inicio').value;
                        const fechaFin = document.getElementById('editar-fecha-fin').value;
                        const horas = document.getElementById('editar-horas').value;
                        const turno = document.getElementById('editar-turno').value;

                        if (!calendarioId || !fechaInicio || !fechaFin || !horas || !turno) {
                            Swal.showValidationMessage('Por favor completa todos los campos');
                            return false;
                        }
                        return { calendarioId, fechaInicio, fechaFin, horas, turno };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        showToast(`Línea de calendario actualizada correctamente`, 'success');
                    }
                });
            }
        }

        function eliminarCalendario() {
            if (!selectedCalendarioTab && !selectedCalendarioLine) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una fila para eliminar',
                    icon: 'warning'
                });
                return;
            }

            let title, html;

            if (selectedCalendarioTab) {
                const selectedRow = document.querySelector(`tr[data-calendario="${selectedCalendarioTab}"]`);
                if (!selectedRow) return;

                const cells = selectedRow.querySelectorAll('td');
                const calendarioId = cells[0].textContent;
                const nombre = cells[1].textContent;

                title = '¿Eliminar calendario?';
                html = `Vas a eliminar el calendario <b>${calendarioId}</b> - ${nombre}.`;
            } else if (selectedCalendarioLine) {
                const selectedRow = document.querySelector(`tr[data-calendario-line="${selectedCalendarioLine}"]`);
                if (!selectedRow) return;

                const cells = selectedRow.querySelectorAll('td');
                const calendarioId = cells[0].textContent;
                const turno = cells[4].textContent;

                title = '¿Eliminar línea de calendario?';
                html = `Vas a eliminar la línea del calendario <b>${calendarioId}</b> turno <b>${turno}</b>.`;
            }

            Swal.fire({
                title: title,
                html: html,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    showToast('El registro fue eliminado correctamente', 'success');
                    disableButtons();
                }
            });
        }

        function filtrarPorColumna() {
            // Generar lista de filtros activos
            let filtrosActivosHTML = '';
            const totalFiltros = activeFiltersTab.length + activeFiltersLine.length;

            if (totalFiltros > 0) {
                filtrosActivosHTML = `
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Filtros Activos:</h4>
                        <div class="space-y-1">
                            ${[...activeFiltersTab, ...activeFiltersLine].map((filtro, index) => `
                                <div class="flex items-center justify-between bg-white p-2 rounded border">
                                    <span class="text-xs">${filtro.columna}: ${filtro.valor}</span>
                                    <button onclick="removeFilter(${index}, '${filtro.tabla}')" class="text-red-500 hover:text-red-700 text-xs">×</button>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            Swal.fire({
                title: 'Filtrar por Columna',
                html: `
                    ${filtrosActivosHTML}
                    <div class="text-left space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tabla</label>
                            <select id="filtro-tabla" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="updateFilterColumns()">
                                <option value="tab">Calendarios (ReqCalendarioTab)</option>
                                <option value="line">Líneas de Calendario (ReqCalendarioLine)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Columna</label>
                            <select id="filtro-columna" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="Calendariold">No Calendario</option>
                                <option value="Nombre">Nombre</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valor a buscar</label>
                            <input type="text" id="filtro-valor" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ingresa el valor a buscar">
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="button" id="btn-agregar-otro" class="flex-1 px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm">
                                + Agregar Otro Filtro
                            </button>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Agregar Filtro',
                cancelButtonText: 'Cerrar',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                width: '450px',
                preConfirm: () => {
                    const tabla = document.getElementById('filtro-tabla').value;
                    const columna = document.getElementById('filtro-columna').value;
                    const valor = document.getElementById('filtro-valor').value;

                    if (!valor) {
                        Swal.showValidationMessage('Por favor ingresa un valor para filtrar');
                        return false;
                    }

                    const filtros = tabla === 'tab' ? activeFiltersTab : activeFiltersLine;
                    const existeFiltro = filtros.some(f => f.columna === columna && f.valor === valor);
                    if (existeFiltro) {
                        Swal.showValidationMessage('Este filtro ya está activo');
                        return false;
                    }

                    return { tabla, columna, valor };
                },
                didOpen: () => {
                    updateFilterColumns();
                    document.getElementById('btn-agregar-otro').addEventListener('click', () => {
                        const tabla = document.getElementById('filtro-tabla').value;
                        const columna = document.getElementById('filtro-columna').value;
                        const valor = document.getElementById('filtro-valor').value;

                        if (!valor) {
                            Swal.showValidationMessage('Por favor ingresa un valor para filtrar');
                            return;
                        }

                        const filtros = tabla === 'tab' ? activeFiltersTab : activeFiltersLine;
                        const existeFiltro = filtros.some(f => f.columna === columna && f.valor === valor);
                        if (existeFiltro) {
                            Swal.showValidationMessage('Este filtro ya está activo');
                            return;
                        }

                        const filtro = { columna, valor, tabla };
                        if (tabla === 'tab') {
                            activeFiltersTab.push(filtro);
                        } else {
                            activeFiltersLine.push(filtro);
                        }

                        applyFilters();
                        showToast('Filtro agregado correctamente', 'success');
                        document.getElementById('filtro-valor').value = '';
                        updateFilterModal();
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const filtro = { ...result.value };
                    if (result.value.tabla === 'tab') {
                        activeFiltersTab.push(filtro);
                    } else {
                        activeFiltersLine.push(filtro);
                    }
                    applyFilters();
                    showToast('Filtro agregado correctamente', 'success');
                }
            });
        }

        function updateFilterColumns() {
            const tabla = document.getElementById('filtro-tabla').value;
            const columnaSelect = document.getElementById('filtro-columna');

            if (tabla === 'tab') {
                columnaSelect.innerHTML = `
                    <option value="Calendariold">No Calendario</option>
                    <option value="Nombre">Nombre</option>
                `;
            } else {
                columnaSelect.innerHTML = `
                    <option value="Calendariold">No Calendario</option>
                    <option value="Fechalnicio">Inicio (Fecha Hora)</option>
                    <option value="FechaFin">Fin (Fecha Hora)</option>
                    <option value="HorasTurno">Horas</option>
                    <option value="Turno">Turno</option>
                `;
            }
        }

        function removeFilter(index, tabla) {
            if (tabla === 'tab') {
                activeFiltersTab.splice(index, 1);
            } else {
                activeFiltersLine.splice(index, 1);
            }
            applyFilters();
            showToast('Filtro eliminado', 'info');
            updateFilterModal();
        }

        function updateFilterModal() {
            // Regenerar modal con filtros actualizados
            filtrarPorColumna();
        }

        function applyFilters() {
            // Aplicar filtros a tabla 1
            if (!originalDataTab.length) {
                const rows = document.querySelectorAll('#calendario-tab-body tr');
                originalDataTab = Array.from(rows).map(row => ({
                    element: row,
                    Calendariold: row.cells[0].textContent.trim(),
                    Nombre: row.cells[1].textContent.trim()
                }));
            }

            originalDataTab.forEach(item => {
                item.element.style.display = '';
                if (activeFiltersTab.length > 0) {
                    let matches = true;
                    activeFiltersTab.forEach(filter => {
                        const value = item[filter.columna].toLowerCase();
                        const filterValue = filter.valor.toLowerCase();
                        if (!value.includes(filterValue)) {
                            matches = false;
                        }
                    });
                    item.element.style.display = matches ? '' : 'none';
                }
            });

            // Aplicar filtros a tabla 2
            if (!originalDataLine.length) {
                const rows = document.querySelectorAll('#calendario-line-body tr');
                originalDataLine = Array.from(rows).map(row => ({
                    element: row,
                    Calendariold: row.cells[0].textContent.trim(),
                    Fechalnicio: row.cells[1].textContent.trim(),
                    FechaFin: row.cells[2].textContent.trim(),
                    HorasTurno: row.cells[3].textContent.trim(),
                    Turno: row.cells[4].textContent.trim()
                }));
            }

            originalDataLine.forEach(item => {
                item.element.style.display = '';
                if (activeFiltersLine.length > 0) {
                    let matches = true;
                    activeFiltersLine.forEach(filter => {
                        const value = item[filter.columna].toLowerCase();
                        const filterValue = filter.valor.toLowerCase();
                        if (!value.includes(filterValue)) {
                            matches = false;
                        }
                    });
                    item.element.style.display = matches ? '' : 'none';
                }
            });

            updateFilterCount();
        }

        function updateFilterCount() {
            const filterCount = document.getElementById('filter-count');
            if (filterCount) {
                const totalFiltros = activeFiltersTab.length + activeFiltersLine.length;
                if (totalFiltros > 0) {
                    filterCount.textContent = totalFiltros;
                    filterCount.classList.remove('hidden');
                } else {
                    filterCount.classList.add('hidden');
                }
            }
        }

        function restablecerFiltros() {
            activeFiltersTab = [];
            activeFiltersLine = [];

            if (originalDataTab.length > 0) {
                originalDataTab.forEach(item => {
                    item.element.style.display = '';
                });
            }

            if (originalDataLine.length > 0) {
                originalDataLine.forEach(item => {
                    item.element.style.display = '';
                });
            }

            updateFilterCount();
            showToast('Restablecido<br>Todos los filtros y configuraciones han sido eliminados', 'success');
        }

        // Inicializar botones como deshabilitados
        document.addEventListener('DOMContentLoaded', function() {
            disableButtons();
        });
    </script>

    @include('components.toast-notification')

@endsection
