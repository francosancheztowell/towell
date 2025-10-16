@extends('layouts.app')

@section('content')
    <div class="container overflow-y-auto h-[600px]">



        <!-- Tabla de aplicaciones con scroll -->
        <div class="bg-white overflow-hidden">
            <div class="overflow-y-auto max-h-[600px]">
                <table class="table table-bordered table-sm w-full">
                    <thead class="sticky top-0 bg-blue-500 text-white z-10">
                        <tr>
                            <th style="width: 20%;" class="px-4 py-1 text-center font-semibold">Clave</th>
                            <th style="width: 40%;" class="px-4 py-1 text-center font-semibold">Nombre</th>
                            <th style="width: 20%;" class="px-4 py-1 text-center font-semibold">Salón</th>
                            <th style="width: 20%;" class="px-4 py-1 text-center font-semibold">Telar</th>
                        </tr>
                    </thead>
                    <tbody id="aplicaciones-body" class="bg-white text-black">
                        @foreach ($aplicaciones as $item)
                            <tr class="text-center hover:bg-blue-50 transition cursor-pointer border-b border-gray-200"
                                onclick="selectRow(this, '{{ $item->AplicacionId }}')"
                                ondblclick="deselectRow(this)"
                                data-aplicacion="{{ $item->AplicacionId }}">
                                <td class="px-4 py-1 font-medium">{{ $item->AplicacionId }}</td>
                                <td class="px-4 py-1">{{ $item->Nombre }}</td>
                                <td class="px-4 py-1 font-semibold">{{ $item->SalonTejidold }}</td>
                                <td class="px-4 py-1 font-semibold">{{ $item->NoTelarId }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let selectedAplicacion = null;
        let activeFilters = [];
        let originalData = [];

        // Funciones de selección de filas
        function selectRow(row, aplicacionId) {
            // Remover selección anterior
            document.querySelectorAll('tbody tr').forEach(r => {
                r.classList.remove('bg-blue-500', 'text-white');
                r.classList.add('hover:bg-blue-50');
            });

            // Seleccionar fila actual
            row.classList.remove('hover:bg-blue-50');
            row.classList.add('bg-blue-500', 'text-white');

            // Guardar aplicación seleccionada
            selectedAplicacion = aplicacionId;

            // Habilitar botones de editar y eliminar
            enableButtons();
        }

        function deselectRow(row) {
            // Solo deseleccionar si la fila está seleccionada
            if (row.classList.contains('bg-blue-500')) {
                // Deseleccionar fila
                row.classList.remove('bg-blue-500', 'text-white');
                row.classList.add('hover:bg-blue-50');

                // Limpiar selección
                selectedAplicacion = null;

                // Deshabilitar botones
                disableButtons();
            }
        }

        function enableButtons() {
            const btnEditar = document.getElementById('btn-editar');
            const btnEliminar = document.getElementById('btn-eliminar');

            if (btnEditar && btnEliminar) {
                // Habilitar botones
                btnEditar.disabled = false;
                btnEliminar.disabled = false;

                // Cambiar estilos a habilitado
                btnEditar.className = 'inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm font-medium';
                btnEliminar.className = 'inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors text-sm font-medium';
            }
        }

        function disableButtons() {
            const btnEditar = document.getElementById('btn-editar');
            const btnEliminar = document.getElementById('btn-eliminar');

            if (btnEditar && btnEliminar) {
                // Deshabilitar botones
                btnEditar.disabled = true;
                btnEliminar.disabled = true;

                // Cambiar estilos a deshabilitado
                btnEditar.className = 'inline-flex items-center px-3 py-2 bg-gray-400 text-gray-200 rounded-lg transition-colors text-sm font-medium cursor-not-allowed';
                btnEliminar.className = 'inline-flex items-center px-3 py-2 bg-gray-400 text-gray-200 rounded-lg transition-colors text-sm font-medium cursor-not-allowed';
            }

            selectedAplicacion = null;
        }

        // Funciones de modales SweetAlert
        function agregarAplicacion() {
            Swal.fire({
                title: 'Agregar Nueva Aplicación',
                html: `
                    <div class="text-left space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Clave</label>
                            <input type="text" id="agregar-clave" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: APP016">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                            <input type="text" id="agregar-nombre" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: Sistema de Control Avanzado">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Salón</label>
                            <input type="text" id="agregar-salon" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: Salón F">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telar</label>
                            <input type="text" id="agregar-telar" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: T016">
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
                    const clave = document.getElementById('agregar-clave').value;
                    const nombre = document.getElementById('agregar-nombre').value;
                    const salon = document.getElementById('agregar-salon').value;
                    const telar = document.getElementById('agregar-telar').value;

                    if (!clave || !nombre || !salon || !telar) {
                        Swal.showValidationMessage('Por favor completa todos los campos');
                        return false;
                    }
                    return { clave, nombre, salon, telar };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    showToast(`Aplicación ${result.value.nombre} agregada correctamente`, 'success');
                }
            });
        }

        function editarAplicacion() {
            if (!selectedAplicacion) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una fila para editar',
                    icon: 'warning'
                });
                return;
            }

            // Obtener datos de la fila seleccionada
            const selectedRow = document.querySelector(`tr[data-aplicacion="${selectedAplicacion}"]`);
            if (!selectedRow) return;

            const cells = selectedRow.querySelectorAll('td');
            const clave = cells[0].textContent;
            const nombre = cells[1].textContent;
            const salon = cells[2].textContent;
            const telar = cells[3].textContent;

            Swal.fire({
                title: 'Editar Aplicación',
                html: `
                    <div class="text-left space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Clave</label>
                            <input type="text" id="editar-clave" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                            <input type="text" id="editar-nombre" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="${nombre}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Salón</label>
                            <input type="text" id="editar-salon" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="${salon}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telar</label>
                            <input type="text" id="editar-telar" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="${telar}">
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
                    const clave = document.getElementById('editar-clave').value;
                    const nombre = document.getElementById('editar-nombre').value;
                    const salon = document.getElementById('editar-salon').value;
                    const telar = document.getElementById('editar-telar').value;

                    if (!clave || !nombre || !salon || !telar) {
                        Swal.showValidationMessage('Por favor completa todos los campos');
                        return false;
                    }
                    return { clave, nombre, salon, telar };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    showToast(`Aplicación ${result.value.nombre} actualizada correctamente`, 'success');
                }
            });
        }

        function eliminarAplicacion() {
            if (!selectedAplicacion) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una fila para eliminar',
                    icon: 'warning'
                });
                return;
            }

            // Obtener datos de la fila seleccionada
            const selectedRow = document.querySelector(`tr[data-aplicacion="${selectedAplicacion}"]`);
            if (!selectedRow) return;

            const cells = selectedRow.querySelectorAll('td');
            const clave = cells[0].textContent;
            const nombre = cells[1].textContent;

            Swal.fire({
                title: '¿Eliminar aplicación?',
                html: `Vas a eliminar la aplicación <b>${clave}</b> - ${nombre}.`,
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
            if (activeFilters.length > 0) {
                filtrosActivosHTML = `
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Filtros Activos:</h4>
                        <div class="space-y-1">
                            ${activeFilters.map((filtro, index) => `
                                <div class="flex items-center justify-between bg-white p-2 rounded border">
                                    <span class="text-xs">${filtro.columna}: ${filtro.valor}</span>
                                    <button onclick="removeFilter(${index})" class="text-red-500 hover:text-red-700 text-xs">×</button>
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Columna</label>
                            <select id="filtro-columna" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="AplicacionId">Clave</option>
                                <option value="Nombre">Nombre</option>
                                <option value="SalonTejidold">Salón</option>
                                <option value="NoTelarId">Telar</option>
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
                    const columna = document.getElementById('filtro-columna').value;
                    const valor = document.getElementById('filtro-valor').value;

                    if (!valor) {
                        Swal.showValidationMessage('Por favor ingresa un valor para filtrar');
                        return false;
                    }

                    // Verificar si ya existe este filtro
                    const existeFiltro = activeFilters.some(f => f.columna === columna && f.valor === valor);
                    if (existeFiltro) {
                        Swal.showValidationMessage('Este filtro ya está activo');
                        return false;
                    }

                    return { columna, valor };
                },
                didOpen: () => {
                    // Agregar event listener al botón "Agregar Otro Filtro"
                    document.getElementById('btn-agregar-otro').addEventListener('click', () => {
                        const columna = document.getElementById('filtro-columna').value;
                        const valor = document.getElementById('filtro-valor').value;

                        if (!valor) {
                            Swal.showValidationMessage('Por favor ingresa un valor para filtrar');
                            return;
                        }

                        // Verificar si ya existe este filtro
                        const existeFiltro = activeFilters.some(f => f.columna === columna && f.valor === valor);
                        if (existeFiltro) {
                            Swal.showValidationMessage('Este filtro ya está activo');
                            return;
                        }

                        // Agregar filtro y limpiar campos
                        activeFilters.push({ columna, valor });
                        applyFilters();
                        showToast('Filtro agregado correctamente', 'success');

                        // Limpiar campos para el siguiente filtro
                        document.getElementById('filtro-valor').value = '';

                        // Actualizar la vista del modal con los nuevos filtros activos
                        updateFilterModal();
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Agregar nuevo filtro
                    activeFilters.push(result.value);

                    // Aplicar filtros
                    applyFilters();

                    showToast('Filtro agregado correctamente', 'success');
                }
            });
        }

        function removeFilter(index) {
            activeFilters.splice(index, 1);
            applyFilters();
            showToast('Filtro eliminado', 'info');
            updateFilterModal();
        }

        function updateFilterModal() {
            // Generar nueva lista de filtros activos
            let filtrosActivosHTML = '';
            if (activeFilters.length > 0) {
                filtrosActivosHTML = `
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Filtros Activos:</h4>
                        <div class="space-y-1">
                            ${activeFilters.map((filtro, index) => `
                                <div class="flex items-center justify-between bg-white p-2 rounded border">
                                    <span class="text-xs">${filtro.columna}: ${filtro.valor}</span>
                                    <button onclick="removeFilter(${index})" class="text-red-500 hover:text-red-700 text-xs">×</button>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            // Actualizar el contenido del modal
            const modalContent = document.querySelector('.swal2-html-container');
            if (modalContent) {
                modalContent.innerHTML = `
                    ${filtrosActivosHTML}
                    <div class="text-left space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Columna</label>
                            <select id="filtro-columna" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="AplicacionId">Clave</option>
                                <option value="Nombre">Nombre</option>
                                <option value="SalonTejidold">Salón</option>
                                <option value="NoTelarId">Telar</option>
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
                `;

                // Reagregar event listener al botón
                document.getElementById('btn-agregar-otro').addEventListener('click', () => {
                    const columna = document.getElementById('filtro-columna').value;
                    const valor = document.getElementById('filtro-valor').value;

                    if (!valor) {
                        Swal.showValidationMessage('Por favor ingresa un valor para filtrar');
                        return;
                    }

                    // Verificar si ya existe este filtro
                    const existeFiltro = activeFilters.some(f => f.columna === columna && f.valor === valor);
                    if (existeFiltro) {
                        Swal.showValidationMessage('Este filtro ya está activo');
                        return;
                    }

                    // Agregar filtro y limpiar campos
                    activeFilters.push({ columna, valor });
                    applyFilters();
                    showToast('Filtro agregado correctamente', 'success');

                    // Limpiar campos para el siguiente filtro
                    document.getElementById('filtro-valor').value = '';

                    // Actualizar la vista del modal con los nuevos filtros activos
                    updateFilterModal();
                });
            }
        }

        function applyFilters() {
            if (!originalData.length) {
                // Guardar datos originales la primera vez
                const rows = document.querySelectorAll('#aplicaciones-body tr');
                originalData = Array.from(rows).map(row => ({
                    element: row,
                    AplicacionId: row.cells[0].textContent.trim(),
                    Nombre: row.cells[1].textContent.trim(),
                    SalonTejidold: row.cells[2].textContent.trim(),
                    NoTelarId: row.cells[3].textContent.trim()
                }));
            }

            // Mostrar todas las filas primero
            originalData.forEach(item => {
                item.element.style.display = '';
            });

            // Aplicar filtros
            if (activeFilters.length > 0) {
                originalData.forEach(item => {
                    let matches = true;

                    activeFilters.forEach(filter => {
                        const value = item[filter.columna].toLowerCase();
                        const filterValue = filter.valor.toLowerCase();

                        if (!value.includes(filterValue)) {
                            matches = false;
                        }
                    });

                    item.element.style.display = matches ? '' : 'none';
                });
            }

            // Actualizar contador de filtros
            updateFilterCount();
        }

        function updateFilterCount() {
            const filterCount = document.getElementById('filter-count');
            if (filterCount) {
                if (activeFilters.length > 0) {
                    filterCount.textContent = activeFilters.length;
                    filterCount.classList.remove('hidden');
                } else {
                    filterCount.classList.add('hidden');
                }
            }
        }

        function restablecerFiltros() {
            // Limpiar filtros activos
            activeFilters = [];

            // Restaurar datos originales
            if (originalData.length > 0) {
                originalData.forEach(item => {
                    item.element.style.display = '';
                });
            }

            // Actualizar contador de filtros
            updateFilterCount();

            // Mostrar toast personalizado
            showToast('Restablecido<br>Todos los filtros y configuraciones han sido eliminados', 'success');
        }

        // Inicializar botones como deshabilitados
        document.addEventListener('DOMContentLoaded', function() {
            disableButtons();
        });
    </script>

    @include('components.toast-notification')

@endsection
