@extends('layouts.app')

@section('content')
    <div class="container">
        <!-- Contador oculto para las funciones de filtrado -->
        <span id="contador-registros" style="display: none;">{{ count($aplicaciones) }} registros</span>
        <div id="filtros-activos" class="hidden"></div>

        <!-- Tabla de aplicaciones -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="overflow-y-auto h-[640px] scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
                <table class="table table-bordered table-sm w-full">
                    <thead class="sticky top-0 bg-blue-500 text-white z-10">
                        <tr>
                            <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Clave</th>
                            <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Nombre</th>
                            <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Salón</th>
                            <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Telar</th>
                        </tr>
                    </thead>
                    <tbody id="aplicaciones-body" class="bg-white text-black">
                        @foreach ($aplicaciones as $item)
                            @php
                                $uniqueId = $item->AplicacionId;
                            @endphp
                            <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                                onclick="selectRow(this, '{{ $uniqueId }}', {{ $item->id ?? 'null' }})"
                                ondblclick="deselectRow(this)"
                                data-aplicacion="{{ $uniqueId }}"
                                data-aplicacion-id="{{ $item->id ?? 'null' }}">
                                <td class="py-1 px-4 border-b">{{ $item->AplicacionId }}</td>
                                <td class="py-1 px-4 border-b">{{ $item->Nombre }}</td>
                                <td class="py-1 px-4 border-b font-semibold">{{ $item->SalonTejidoId }}</td>
                                <td class="py-1 px-4 border-b">{{ $item->NoTelarId }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        console.log('Script de aplicaciones cargado');
        let selectedAplicacion = null;
        let selectedAplicacionId = null;

        // Variables para filtrado
        let filtrosActuales = {
            clave: '',
            nombre: '',
            salon: '',
            telar: ''
        };

        // Datos originales para filtrado
        let datosOriginales = @json($aplicaciones);
        let datosActuales = datosOriginales;

        // Cache para optimización de filtros
        const cacheFiltros = new Map();

        // Función global para que el botón "Filtrar" del navbar pueda llamarla
        window.filtrarAplicaciones = function() {
            console.log('filtrarAplicaciones llamado desde navbar');
            mostrarFiltros();
        };

        // Función global para limpiar filtros desde el navbar
        window.limpiarFiltrosAplicaciones = function() {
            console.log('limpiarFiltrosAplicaciones llamado desde navbar...');
            limpiarFiltros();
        };

        function selectRow(row, uniqueId, aplicacionId) {
            document.querySelectorAll('tbody tr').forEach(r => {
                r.classList.remove('bg-blue-500', 'text-white');
                r.classList.add('hover:bg-blue-50');
            });

            row.classList.remove('hover:bg-blue-50');
            row.classList.add('bg-blue-500', 'text-white');

            selectedAplicacion = uniqueId;
            selectedAplicacionId = aplicacionId;

            enableButtons();
        }

        function deselectRow(row) {
            if (row.classList.contains('bg-blue-500')) {
                row.classList.remove('bg-blue-500', 'text-white');
                row.classList.add('hover:bg-blue-50');

                selectedAplicacion = null;
                selectedAplicacionId = null;

                disableButtons();
            }
        }

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

            selectedAplicacion = null;
            selectedAplicacionId = null;
        }

        function agregarAplicacion() {
            Swal.fire({
                title: 'Agregar Nueva Aplicación',
                html: `
                    <div class="text-left space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Clave *</label>
                            <input id="swal-clave" type="text" class="swal2-input" placeholder="Ej: APP001" maxlength="50" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                            <input id="swal-nombre" type="text" class="swal2-input" placeholder="Ej: Sistema Control" maxlength="100" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Salón *</label>
                            <input id="swal-salon" type="text" class="swal2-input" placeholder="Ej: Salón A" maxlength="50" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telar *</label>
                            <input id="swal-telar" type="text" class="swal2-input" placeholder="Ej: T001" maxlength="50" required>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-plus me-2"></i>Agregar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                preConfirm: () => {
                    const clave = document.getElementById('swal-clave').value.trim();
                    const nombre = document.getElementById('swal-nombre').value.trim();
                    const salon = document.getElementById('swal-salon').value.trim();
                    const telar = document.getElementById('swal-telar').value.trim();

                    if (!clave || !nombre || !salon || !telar) {
                        Swal.showValidationMessage('Por favor completa los campos requeridos');
                        return false;
                    }

                    return { clave, nombre, salon, telar };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const formData = new FormData();
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                    formData.append('AplicacionId', result.value.clave);
                    formData.append('Nombre', result.value.nombre);
                    formData.append('SalonTejidoId', result.value.salon);
                    formData.append('NoTelarId', result.value.telar);

                    fetch('/aplicaciones-catalogo', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            crearToast('success', 'Aplicación creada exitosamente');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            crearToast('error', data.message || 'Error al crear la aplicación');
                        }
                    })
                    .catch(error => {
                        crearToast('error', 'Error al crear la aplicación');
                    });
                }
            });
        }

        function editarAplicacion() {
            if (!selectedAplicacion || !selectedAplicacionId) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una aplicación para editar',
                    icon: 'warning'
                });
                return;
            }

            const selectedRow = document.querySelector(`tr[data-aplicacion="${selectedAplicacion}"]`);
            if (!selectedRow) {
                Swal.fire({
                    title: 'Error',
                    text: 'No se encontraron los datos de la aplicación seleccionada',
                    icon: 'error'
                });
                return;
            }

            const cells = selectedRow.querySelectorAll('td');
            const claveActual = cells[0].textContent.trim();
            const nombreActual = cells[1].textContent.trim();
            const salonActual = cells[2].textContent.trim();
            const telarActual = cells[3].textContent.trim();

            Swal.fire({
                title: 'Editar Aplicación',
                html: `
                    <div class="text-left space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Clave *</label>
                            <input id="swal-clave-edit" type="text" class="swal2-input" placeholder="Ej: APP001" maxlength="50" required value="${claveActual}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                            <input id="swal-nombre-edit" type="text" class="swal2-input" placeholder="Ej: Sistema Control" maxlength="100" required value="${nombreActual}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Salón *</label>
                            <input id="swal-salon-edit" type="text" class="swal2-input" placeholder="Ej: Salón A" maxlength="50" required value="${salonActual}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telar *</label>
                            <input id="swal-telar-edit" type="text" class="swal2-input" placeholder="Ej: T001" maxlength="50" required value="${telarActual}">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-save me-2"></i>Guardar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                preConfirm: () => {
                    const clave = document.getElementById('swal-clave-edit').value.trim();
                    const nombre = document.getElementById('swal-nombre-edit').value.trim();
                    const salon = document.getElementById('swal-salon-edit').value.trim();
                    const telar = document.getElementById('swal-telar-edit').value.trim();

                    if (!clave || !nombre || !salon || !telar) {
                        Swal.showValidationMessage('Por favor completa los campos requeridos');
                        return false;
                    }

                    return { clave, nombre, salon, telar };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const formData = new FormData();
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                    formData.append('_method', 'PUT');
                    formData.append('AplicacionId', result.value.clave);
                    formData.append('Nombre', result.value.nombre);
                    formData.append('SalonTejidoId', result.value.salon);
                    formData.append('NoTelarId', result.value.telar);

                    fetch(`/aplicaciones-catalogo/${selectedAplicacionId}`, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            crearToast('success', 'Aplicación actualizada exitosamente');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            crearToast('error', data.message || 'Error al actualizar la aplicación');
                        }
                    })
                    .catch(error => {
                        crearToast('error', 'Error al actualizar la aplicación');
                    });
                }
            });
        }

        function eliminarAplicacion() {
            if (!selectedAplicacion || !selectedAplicacionId) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una aplicación para eliminar',
                    icon: 'warning'
                });
                return;
            }

            const selectedRow = document.querySelector(`tr[data-aplicacion="${selectedAplicacion}"]`);
            if (!selectedRow) {
                Swal.fire({
                    title: 'Error',
                    text: 'No se encontraron los datos de la aplicación seleccionada',
                    icon: 'error'
                });
                return;
            }

            const cells = selectedRow.querySelectorAll('td');
            const clave = cells[0].textContent.trim();
            const nombre = cells[1].textContent.trim();

            Swal.fire({
                title: '¿Eliminar Aplicación?',
                html: `
                    <div class="text-left">
                        <p><strong>Clave:</strong> ${clave}</p>
                        <p><strong>Nombre:</strong> ${nombre}</p>
                        <hr>
                        <p class="text-red-600 font-semibold">⚠️ Esta acción no se puede deshacer.</p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash me-2"></i>Sí, eliminar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`/aplicaciones-catalogo/${selectedAplicacionId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            crearToast('success', 'Aplicación eliminada exitosamente');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            crearToast('error', data.message || 'Error al eliminar la aplicación');
                        }
                    })
                    .catch(error => {
                        crearToast('error', 'Error al eliminar la aplicación');
                    });
                }
            });
        }

        function subirExcelAplicaciones() {
            // Esta función será llamada por el botón "Subir Excel" del componente action-buttons
            // El modal se maneja desde el componente
        }

        // Función global para que el botón "Subir Excel" del navbar pueda llamarla
        window.subirExcelAplicaciones = function() {
            subirExcelAplicaciones();
        };

        function mostrarFiltros() {
            console.log('mostrarFiltros ejecutándose...');
            Swal.fire({
                title: 'Filtrar Aplicaciones',
                html: `
                    <div class="text-left space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Clave</label>
                                <input id="swal-clave-filtro" type="text" class="swal2-input" placeholder="Ej: APP001" value="${filtrosActuales.clave}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                                <input id="swal-nombre-filtro" type="text" class="swal2-input" placeholder="Ej: Sistema" value="${filtrosActuales.nombre}">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Salón</label>
                                <input id="swal-salon-filtro" type="text" class="swal2-input" placeholder="Ej: Salón A" value="${filtrosActuales.salon}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Telar</label>
                                <input id="swal-telar-filtro" type="text" class="swal2-input" placeholder="Ej: T001" value="${filtrosActuales.telar}">
                            </div>
                        </div>

                        <div class="text-sm text-gray-600 bg-gray-50 p-3 rounded">
                            <i class="fas fa-info-circle mr-1"></i>
                            Deja los campos vacíos para no aplicar filtro en esa columna
                        </div>
                    </div>
                `,
                width: '600px',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-filter me-2"></i>Filtrar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                preConfirm: () => {
                    const clave = document.getElementById('swal-clave-filtro').value.trim();
                    const nombre = document.getElementById('swal-nombre-filtro').value.trim();
                    const salon = document.getElementById('swal-salon-filtro').value.trim();
                    const telar = document.getElementById('swal-telar-filtro').value.trim();

                    return {
                        clave,
                        nombre,
                        salon,
                        telar
                    };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    aplicarFiltros(result.value);
                }
            });
        }

        function aplicarFiltros(filtros) {
            filtrosActuales = { ...filtros };

            const cacheKey = JSON.stringify(filtrosActuales);

            if (cacheFiltros.has(cacheKey)) {
                const datosFiltrados = cacheFiltros.get(cacheKey);
                actualizarTablaOptimizada(datosFiltrados);
                actualizarContador(datosFiltrados.length);
                return;
            }

            let datosFiltrados = datosOriginales.filter(item => {
                const claveMatch = !filtros.clave || item.AplicacionId.toLowerCase().includes(filtros.clave.toLowerCase());
                const nombreMatch = !filtros.nombre || item.Nombre.toLowerCase().includes(filtros.nombre.toLowerCase());
                const salonMatch = !filtros.salon || item.SalonTejidoId.toLowerCase().includes(filtros.salon.toLowerCase());
                const telarMatch = !filtros.telar || item.NoTelarId.toLowerCase().includes(filtros.telar.toLowerCase());

                return claveMatch && nombreMatch && salonMatch && telarMatch;
            });

            cacheFiltros.set(cacheKey, datosFiltrados);

            datosActuales = datosFiltrados;

            actualizarTablaOptimizada(datosFiltrados);
            actualizarContador(datosFiltrados.length);

            mostrarIndicadorFiltros();
        }

        function limpiarFiltros() {
            console.log('limpiarFiltros llamado...');

            filtrosActuales = {
                clave: '',
                nombre: '',
                salon: '',
                telar: ''
            };

            cacheFiltros.clear();

            datosActuales = datosOriginales;

            actualizarTablaOptimizada(datosOriginales);

            actualizarContador(datosOriginales.length);

            const indicadorFiltros = document.getElementById('filtros-activos');
            if (indicadorFiltros) {
                indicadorFiltros.classList.add('hidden');
            }

            console.log('Mostrando toast de limpiar filtros...');
            crearToast('success', `Filtros limpiados - Mostrando ${datosOriginales.length} registros`, 2000);
        }

        function actualizarTablaOptimizada(datos) {
            const tbody = document.getElementById('aplicaciones-body');

            if (datos.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-8 text-gray-500">
                            <i class="fas fa-search text-4xl mb-2"></i>
                            <br>No se encontraron resultados con los filtros aplicados
                        </td>
                    </tr>
                `;
                return;
            }

            const fragment = document.createDocumentFragment();

            datos.forEach(item => {
                const uniqueId = item.AplicacionId;
                const row = document.createElement('tr');
                row.className = 'text-center hover:bg-blue-50 transition cursor-pointer';
                row.setAttribute('onclick', `selectRow(this, '${uniqueId}', ${item.id || 'null'})`);
                row.setAttribute('ondblclick', 'deselectRow(this)');
                row.setAttribute('data-aplicacion', uniqueId);
                row.setAttribute('data-aplicacion-id', item.id || 'null');

                row.innerHTML = `
                    <td class="py-1 px-4 border-b">${item.AplicacionId}</td>
                    <td class="py-1 px-4 border-b">${item.Nombre}</td>
                    <td class="py-1 px-4 border-b font-semibold">${item.SalonTejidoId}</td>
                    <td class="py-1 px-4 border-b">${item.NoTelarId}</td>
                `;

                fragment.appendChild(row);
            });

            tbody.innerHTML = '';
            tbody.appendChild(fragment);
        }

        function actualizarContador(total) {
            const contador = document.getElementById('contador-registros');
            if (contador) {
                contador.textContent = `${total} registros`;
            }
        }

        function mostrarIndicadorFiltros() {
            const indicador = document.getElementById('filtros-activos');
            if (indicador) {
                indicador.classList.remove('hidden');
            }
        }

        function crearToast(icono, mensaje, duracion = 1500) {
            console.log('crearToast llamado con:', icono, mensaje, duracion);

            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: duracion,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            Toast.fire({
                icon: icono,
                title: mensaje
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            disableButtons();

            actualizarContador(datosOriginales.length);

            console.log('Funciones globales disponibles:', {
                filtrarAplicaciones: typeof window.filtrarAplicaciones,
                limpiarFiltrosAplicaciones: typeof window.limpiarFiltrosAplicaciones
            });
        });
    </script>

    <style>
        .scrollbar-thin {
            scrollbar-width: thin;
        }

        .scrollbar-thumb-gray-400::-webkit-scrollbar-thumb {
            background-color: #9ca3af;
            border-radius: 4px;
        }

        .scrollbar-track-gray-100::-webkit-scrollbar-track {
            background-color: #f3f4f6;
        }

        .scrollbar-thin::-webkit-scrollbar {
            width: 8px;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background-color: #6b7280;
        }
    </style>

@endsection
