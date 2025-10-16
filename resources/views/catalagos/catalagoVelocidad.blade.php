@extends('layouts.app')

@section('content')
    <div class="container">
        <!-- Contador oculto para las funciones de filtrado -->
        <span id="contador-registros" style="display: none;">{{ count($velocidad) }} registros</span>
        <div id="filtros-activos" class="hidden"></div>

        <!-- Tabla de velocidad -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="overflow-y-auto h-[640px] scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
                <table class="table table-bordered table-sm w-full">
                    <thead class="sticky top-0 bg-blue-500 text-white z-10">
                        <tr>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Salón</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Telar</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Fibra</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">RPM</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Densidad</th>
                </tr>
            </thead>
                    <tbody id="velocidad-body" class="bg-white text-black">
                @foreach ($velocidad as $item)
                        @php
                            $uniqueId = $item->NoTelarId . '_' . $item->FibraId;
                        @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            onclick="selectRow(this, '{{ $uniqueId }}', {{ $item->id ?? 'null' }})"
                                ondblclick="deselectRow(this)"
                            data-velocidad="{{ $uniqueId }}"
                            data-velocidad-id="{{ $item->id ?? 'null' }}">
                            <td class="py-1 px-4 border-b">{{ $item->SalonTejidoId }}</td>
                            <td class="py-1 px-4 border-b">{{ $item->NoTelarId }}</td>
                            <td class="py-1 px-4 border-b">{{ $item->FibraId }}</td>
                            <td class="py-1 px-4 border-b font-semibold">{{ $item->Velocidad }} RPM</td>
                            <td class="py-1 px-4 border-b">{{ $item->Densidad ?? 'Normal' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        console.log('Script de velocidad cargado');
        let selectedVelocidad = null;
        let selectedVelocidadId = null;

        // Variables para filtrado
        let filtrosActuales = {
            salon: '',
            telar: '',
            fibra: '',
            velocidad_min: '',
            velocidad_max: '',
            densidad: ''
        };

        // Datos originales para filtrado
        let datosOriginales = @json($velocidad);
        let datosActuales = datosOriginales;

        // Cache para optimización de filtros
        const cacheFiltros = new Map();

        // Helper para crear toasts
        function crearToast(icono, mensaje, duracion = 1500) {
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

        function selectRow(row, uniqueId, velocidadId) {
            document.querySelectorAll('tbody tr').forEach(r => {
                r.classList.remove('bg-blue-500', 'text-white');
                r.classList.add('hover:bg-blue-50');
            });

            row.classList.remove('hover:bg-blue-50');
            row.classList.add('bg-blue-500', 'text-white');

            selectedVelocidad = uniqueId;
            selectedVelocidadId = velocidadId;

            enableButtons();
        }

        function deselectRow(row) {
            if (row.classList.contains('bg-blue-500')) {
                row.classList.remove('bg-blue-500', 'text-white');
                row.classList.add('hover:bg-blue-50');

                selectedVelocidad = null;
                selectedVelocidadId = null;

                disableButtons();
            }
        }

        function enableButtons() {
            const btnEditar = document.getElementById('btn-editar');
            const btnEliminar = document.getElementById('btn-eliminar');
            if (btnEditar) btnEditar.disabled = false;
            if (btnEliminar) btnEliminar.disabled = false;
        }

        function disableButtons() {
            const btnEditar = document.getElementById('btn-editar');
            const btnEliminar = document.getElementById('btn-eliminar');
            if (btnEditar) btnEditar.disabled = true;
            if (btnEliminar) btnEliminar.disabled = true;
        }

        function agregarVelocidad() {
            Swal.fire({
                title: 'Crear Nueva Velocidad',
                html: `
                    <div class="text-left">
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telar *</label>
                            <input id="swal-telar" type="text" class="swal2-input" placeholder="Ej: JAC 201" maxlength="50" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fibra *</label>
                            <input id="swal-fibra" type="text" class="swal2-input" placeholder="Ej: H, PAP" maxlength="50" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Velocidad (RPM) *</label>
                            <input id="swal-velocidad" type="number" class="swal2-input" placeholder="Ej: 850.5" min="0" step="0.1" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Densidad</label>
                            <input id="swal-densidad" type="text" class="swal2-input" placeholder="Ej: Normal, Alta" maxlength="50" value="Normal">
                        </div>
                    </div>
                `,
                width: '500px',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-save me-2"></i>Crear',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                preConfirm: () => {
                    const telar = document.getElementById('swal-telar').value.trim();
                    const fibra = document.getElementById('swal-fibra').value.trim();
                    const velocidad = document.getElementById('swal-velocidad').value.trim();
                    const densidad = document.getElementById('swal-densidad').value.trim();

                    if (!telar || !fibra || !velocidad) {
                        Swal.showValidationMessage('Por favor completa los campos requeridos');
                        return false;
                    }

                    return { telar, fibra, velocidad: parseFloat(velocidad), densidad };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const { telar, fibra, rpm, densidad } = result.value;

                    Swal.fire({
                        title: 'Creando...',
                        text: 'Por favor espera',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch('/velocidad', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            NoTelarId: telar,
                            FibraId: fibra,
                            RPM: rpm,
                            Densidad: densidad
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡Velocidad Creada!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al crear la velocidad');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Error al crear la velocidad',
                            icon: 'error'
                        });
                    });
                }
            });
        }

        function editarVelocidad() {
            if (!selectedVelocidad || !selectedVelocidadId) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una velocidad para editar',
                    icon: 'warning'
                });
                return;
            }

            const selectedRow = document.querySelector(`tr[data-velocidad="${selectedVelocidad}"]`);
            if (!selectedRow) {
                Swal.fire({
                    title: 'Error',
                    text: 'No se encontraron los datos de la velocidad seleccionada',
                    icon: 'error'
                });
                return;
            }

            const cells = selectedRow.querySelectorAll('td');
            const telarActual = cells[1].textContent.trim();
            const fibraActual = cells[2].textContent.trim();
            const velocidadActual = parseFloat(cells[3].textContent.trim().replace(' RPM', ''));
            const densidadActual = cells[4].textContent.trim();

            Swal.fire({
                title: 'Editar Velocidad',
                html: `
                    <div class="text-left">
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telar *</label>
                            <input id="swal-telar-edit" type="text" class="swal2-input" placeholder="Ej: JAC 201" maxlength="50" required value="${telarActual}">
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fibra *</label>
                            <input id="swal-fibra-edit" type="text" class="swal2-input" placeholder="Ej: H, PAP" maxlength="50" required value="${fibraActual}">
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Velocidad (RPM) *</label>
                            <input id="swal-velocidad-edit" type="number" class="swal2-input" placeholder="Ej: 850.5" min="0" step="0.1" required value="${velocidadActual}">
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Densidad</label>
                            <input id="swal-densidad-edit" type="text" class="swal2-input" placeholder="Ej: Normal, Alta" maxlength="50" value="${densidadActual}">
                        </div>
                    </div>
                `,
                width: '500px',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-save me-2"></i>Actualizar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                preConfirm: () => {
                    const telar = document.getElementById('swal-telar-edit').value.trim();
                    const fibra = document.getElementById('swal-fibra-edit').value.trim();
                    const velocidad = document.getElementById('swal-velocidad-edit').value.trim();
                    const densidad = document.getElementById('swal-densidad-edit').value.trim();

                    if (!telar || !fibra || !velocidad) {
                        Swal.showValidationMessage('Por favor completa los campos requeridos');
                        return false;
                    }

                    return { telar, fibra, velocidad: parseFloat(velocidad), densidad };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const { telar, fibra, rpm, densidad } = result.value;

                    Swal.fire({
                        title: 'Actualizando...',
                        text: 'Por favor espera',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch(`/velocidad/${selectedVelocidadId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            NoTelarId: telar,
                            FibraId: fibra,
                            RPM: rpm,
                            Densidad: densidad
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡Velocidad Actualizada!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al actualizar la velocidad');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Error al actualizar la velocidad',
                            icon: 'error'
                        });
                    });
                }
            });
        }

        function eliminarVelocidad() {
            if (!selectedVelocidad || !selectedVelocidadId) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una velocidad para eliminar',
                    icon: 'warning'
                });
                return;
            }

            const selectedRow = document.querySelector(`tr[data-velocidad="${selectedVelocidad}"]`);
            if (!selectedRow) {
                Swal.fire({
                    title: 'Error',
                    text: 'No se encontraron los datos de la velocidad seleccionada',
                    icon: 'error'
                });
                return;
            }

            const cells = selectedRow.querySelectorAll('td');
            const telar = cells[1].textContent.trim();
            const fibra = cells[2].textContent.trim();
            const rpm = cells[3].textContent.trim();

            Swal.fire({
                title: '¿Eliminar Velocidad?',
                html: `
                    <div class="text-left">
                        <p><strong>Telar:</strong> ${telar}</p>
                        <p><strong>Fibra:</strong> ${fibra}</p>
                        <p><strong>RPM:</strong> ${rpm}</p>
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
                    Swal.fire({
                        title: 'Eliminando...',
                        text: 'Por favor espera',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch(`/velocidad/${selectedVelocidadId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡Velocidad Eliminada!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al eliminar la velocidad');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Error al eliminar la velocidad',
                            icon: 'error'
                        });
                    });
                }
            });
        }

        function subirExcelVelocidad() {
            // Esta función será llamada por el botón "Subir Excel" del componente action-buttons
            // El modal se maneja desde el componente
        }

        // Función global para que el botón "Subir Excel" del navbar pueda llamarla
        window.subirExcelVelocidad = function() {
            subirExcelVelocidad();
        };


        // Función global para que el botón "Filtrar" del navbar pueda llamarla
        window.filtrarVelocidad = function() {
            console.log('filtrarVelocidad llamado desde navbar');
            mostrarFiltros();
        };

        // Función global para limpiar filtros desde el navbar
        window.limpiarFiltrosVelocidad = function() {
            console.log('limpiarFiltrosVelocidad llamado desde navbar...');
            limpiarFiltros();
        };

        function mostrarFiltros() {
            console.log('mostrarFiltros ejecutándose...');
            Swal.fire({
                title: 'Filtrar Velocidades',
                html: `
                    <div class="text-left space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Salón</label>
                                <input id="swal-salon" type="text" class="swal2-input" placeholder="Ej: JACQUARD" value="${filtrosActuales.salon}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Telar</label>
                                <input id="swal-telar" type="text" class="swal2-input" placeholder="Ej: JAC 201" value="${filtrosActuales.telar}">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fibra</label>
                                <input id="swal-fibra" type="text" class="swal2-input" placeholder="Ej: H, PAP, FIL370" value="${filtrosActuales.fibra}">
                            </div>
                        <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Densidad</label>
                                <select id="swal-densidad" class="swal2-input">
                                    <option value="">Todas</option>
                                    <option value="Normal" ${filtrosActuales.densidad === 'Normal' ? 'selected' : ''}>Normal</option>
                                    <option value="Alta" ${filtrosActuales.densidad === 'Alta' ? 'selected' : ''}>Alta</option>
                                    <option value="Baja" ${filtrosActuales.densidad === 'Baja' ? 'selected' : ''}>Baja</option>
                            </select>
                        </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Velocidad Mínima (RPM)</label>
                                <input id="swal-velocidad-min" type="number" class="swal2-input" placeholder="Ej: 200" min="0" value="${filtrosActuales.velocidad_min}">
                            </div>
                        <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Velocidad Máxima (RPM)</label>
                                <input id="swal-velocidad-max" type="number" class="swal2-input" placeholder="Ej: 1000" min="0" value="${filtrosActuales.velocidad_max}">
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
                    const salon = document.getElementById('swal-salon').value.trim();
                    const telar = document.getElementById('swal-telar').value.trim();
                    const fibra = document.getElementById('swal-fibra').value.trim();
                    const densidad = document.getElementById('swal-densidad').value;
                    const velocidadMin = document.getElementById('swal-velocidad-min').value.trim();
                    const velocidadMax = document.getElementById('swal-velocidad-max').value.trim();

                    return {
                        salon,
                        telar,
                        fibra,
                        densidad,
                        velocidad_min: velocidadMin,
                        velocidad_max: velocidadMax
                    };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    aplicarFiltros(result.value);
                }
            });
        }

        function aplicarFiltros(filtros) {
            // Actualizar filtros actuales
            filtrosActuales = { ...filtros };

            // Crear clave de cache
            const cacheKey = JSON.stringify(filtrosActuales);

            // Verificar cache
            if (cacheFiltros.has(cacheKey)) {
                const datosFiltrados = cacheFiltros.get(cacheKey);
                actualizarTablaOptimizada(datosFiltrados);
                actualizarContador(datosFiltrados.length);
                return;
            }

            // Aplicar filtros
            let datosFiltrados = datosOriginales.filter(item => {
                const salonMatch = !filtros.salon || item.SalonTejidoId.toLowerCase().includes(filtros.salon.toLowerCase());
                const telarMatch = !filtros.telar || item.NoTelarId.toLowerCase().includes(filtros.telar.toLowerCase());
                const fibraMatch = !filtros.fibra || item.FibraId.toLowerCase().includes(filtros.fibra.toLowerCase());
                const densidadMatch = !filtros.densidad || item.Densidad === filtros.densidad;
                const velocidadMinMatch = !filtros.velocidad_min || item.Velocidad >= parseFloat(filtros.velocidad_min);
                const velocidadMaxMatch = !filtros.velocidad_max || item.Velocidad <= parseFloat(filtros.velocidad_max);

                return salonMatch && telarMatch && fibraMatch && densidadMatch && velocidadMinMatch && velocidadMaxMatch;
            });

            // Guardar en cache
            cacheFiltros.set(cacheKey, datosFiltrados);

            // Actualizar datos actuales
            datosActuales = datosFiltrados;

            // Actualizar tabla
            actualizarTablaOptimizada(datosFiltrados);
            actualizarContador(datosFiltrados.length);

            // Mostrar indicador de filtros activos
            mostrarIndicadorFiltros();
        }

        function limpiarFiltros() {
            console.log('limpiarFiltros llamado...');

            // Limpiar filtros
            filtrosActuales = {
                salon: '',
                telar: '',
                fibra: '',
                velocidad_min: '',
                velocidad_max: '',
                densidad: ''
            };

            // Limpiar cache
            cacheFiltros.clear();

            // Restaurar datos actuales
            datosActuales = datosOriginales;

            // Restaurar tabla original de forma rápida
            actualizarTablaOptimizada(datosOriginales);

            // Actualizar contador
            actualizarContador(datosOriginales.length);

            // Ocultar indicador de filtros activos (solo si existe)
            const indicadorFiltros = document.getElementById('filtros-activos');
            if (indicadorFiltros) {
                indicadorFiltros.classList.add('hidden');
            }

            // Toast de confirmación
            console.log('Mostrando toast de limpiar filtros...');
            crearToast('success', `Filtros limpiados - Mostrando ${datosOriginales.length} registros`, 2000);
        }

        function actualizarTablaOptimizada(datos) {
            const tbody = document.getElementById('velocidad-body');

            if (datos.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-8 text-gray-500">
                            <i class="fas fa-search text-4xl mb-2"></i>
                            <br>No se encontraron resultados con los filtros aplicados
                        </td>
                    </tr>
                `;
                return;
            }

            // Usar DocumentFragment para mejor rendimiento
            const fragment = document.createDocumentFragment();

            datos.forEach(item => {
                const uniqueId = item.NoTelarId + '_' + item.FibraId;
                const row = document.createElement('tr');
                row.className = 'text-center hover:bg-blue-50 transition cursor-pointer';
                row.setAttribute('onclick', `selectRow(this, '${uniqueId}', ${item.id || 'null'})`);
                row.setAttribute('ondblclick', 'deselectRow(this)');
                row.setAttribute('data-velocidad', uniqueId);
                row.setAttribute('data-velocidad-id', item.id || 'null');

                row.innerHTML = `
                    <td class="py-1 px-4 border-b">${item.SalonTejidoId}</td>
                    <td class="py-1 px-4 border-b">${item.NoTelarId}</td>
                    <td class="py-1 px-4 border-b">${item.FibraId}</td>
                    <td class="py-1 px-4 border-b font-semibold">${item.Velocidad} RPM</td>
                    <td class="py-1 px-4 border-b">${item.Densidad || 'Normal'}</td>
                `;

                fragment.appendChild(row);
            });

            // Limpiar y actualizar tbody
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

            // Inicializar contador
            actualizarContador(datosOriginales.length);

            // Verificar que las funciones globales estén disponibles
            console.log('Funciones globales disponibles:', {
                filtrarVelocidad: typeof window.filtrarVelocidad,
                limpiarFiltrosVelocidad: typeof window.limpiarFiltrosVelocidad
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
