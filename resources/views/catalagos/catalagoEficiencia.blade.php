@extends('layouts.app')

@section('content')
    <div class="container">
        <!-- Los mensajes de éxito/error se muestran con SweetAlert2 -->

        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="overflow-y-auto h-[640px] scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
                <table class="table table-bordered table-sm w-full">
                    <thead class="sticky top-0 bg-blue-500 text-white z-10">
                        <tr>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Salón</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Telar</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Tipo de Hilo</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Eficiencia</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Densidad</th>
                </tr>
            </thead>
                    <tbody id="eficiencia-body" class="bg-white text-black">
                @foreach ($eficiencia as $item)
                        @php
                            // Crear un ID único combinando telar y fibra
                            $uniqueId = $item->NoTelarId . '_' . $item->FibraId;
                        @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            onclick="selectRow(this, '{{ $uniqueId }}', {{ $item->id ?? 'null' }})"
                                ondblclick="deselectRow(this)"
                            data-eficiencia="{{ $uniqueId }}"
                            data-eficiencia-id="{{ $item->id ?? 'null' }}">
                            <td class="py-1 px-4 border-b">{{ $item->SalonTejidoId }}</td>
                            <td class="py-1 px-4 border-b">{{ $item->NoTelarId }}</td>
                            <td class="py-1 px-4 border-b">{{ $item->FibraId }}</td>
                            <td class="py-1 px-4 border-b font-semibold">{{ number_format($item->Eficiencia * 100, 0) }}%</td>
                            <td class="py-1 px-4 border-b">{{ $item->Densidad ?? 'Normal' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
            </div>
        </div>
    </div>

    <!-- Los modales HTML han sido reemplazados por SweetAlert2 -->

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let selectedEficiencia = null;
        let selectedEficienciaId = null;

        // Variables para filtros
        let filtrosActuales = {
            salon: '',
            telar: '',
            fibra: '',
            densidad: '',
            eficiencia_min: '',
            eficiencia_max: ''
        };

        // Datos originales para filtrado
        let datosOriginales = @json($eficiencia);

        // Cache para optimización
        let cacheFiltros = new Map();
        let datosActuales = datosOriginales;

        // Helper para crear toasts
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

        function selectRow(row, uniqueId, eficienciaId) {
            console.log('selectRow llamado con uniqueId:', uniqueId, 'eficienciaId:', eficienciaId); // Debug

            // Remover selección anterior
            document.querySelectorAll('tbody tr').forEach(r => {
                r.classList.remove('bg-blue-500', 'text-white');
                r.classList.add('hover:bg-blue-50');
            });

            // Seleccionar fila actual
            row.classList.remove('hover:bg-blue-50');
            row.classList.add('bg-blue-500', 'text-white');

            // Guardar eficiencia seleccionada
            selectedEficiencia = uniqueId;
            selectedEficienciaId = eficienciaId;
            console.log('selectedEficiencia establecido a:', selectedEficiencia, 'selectedEficienciaId:', selectedEficienciaId); // Debug

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
                selectedEficiencia = null;
                selectedEficienciaId = null;

                // Deshabilitar botones
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

        function agregarEficiencia() {
            Swal.fire({
                title: 'Crear Nueva Eficiencia',
                html: `
                    <div class="text-left">
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telar *</label>
                            <input id="swal-telar" type="text" class="swal2-input" placeholder="Ej: JAC 201" maxlength="10" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Hilo *</label>
                            <input id="swal-fibra" type="text" class="swal2-input" placeholder="Ej: H, PAP, FIL370" maxlength="15" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Eficiencia * (0.00 a 1.00 o %)</label>
                            <input id="swal-eficiencia" type="text" class="swal2-input" placeholder="Ej: 0.78 o 78%" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Densidad</label>
                            <input id="swal-densidad" type="text" class="swal2-input" placeholder="Ej: Normal, Alta, Baja" maxlength="10" value="Normal">
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
                    const eficienciaStr = document.getElementById('swal-eficiencia').value.trim();
                    const densidad = document.getElementById('swal-densidad').value.trim();

                    if (!telar || !fibra || !eficienciaStr) {
                        Swal.showValidationMessage('Por favor completa los campos requeridos');
                        return false;
                    }

                    // Convertir eficiencia a decimal
                    let eficiencia = parseFloat(eficienciaStr.replace('%', ''));
                    if (eficienciaStr.includes('%')) {
                        eficiencia = eficiencia / 100;
                    }

                    if (isNaN(eficiencia) || eficiencia < 0 || eficiencia > 1) {
                        Swal.showValidationMessage('La eficiencia debe estar entre 0 y 1 (o 0% y 100%)');
                        return false;
                    }

                    return { telar, fibra, eficiencia, densidad };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const { telar, fibra, eficiencia, densidad } = result.value;

                    // Mostrar loader
                    Swal.fire({
                        title: 'Creando...',
                        text: 'Por favor espera',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Realizar petición AJAX para crear la eficiencia
                    fetch('/eficiencia', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            NoTelarId: telar,
                            FibraId: fibra,
                            Eficiencia: eficiencia,
                            Densidad: densidad
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡Eficiencia Creada!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al crear la eficiencia');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Error al crear la eficiencia',
                            icon: 'error'
                        });
                    });
                }
            });
        }

        function editarEficiencia() {
            console.log('editarEficiencia llamado, selectedEficiencia:', selectedEficiencia, 'selectedEficienciaId:', selectedEficienciaId); // Debug

            if (!selectedEficiencia || !selectedEficienciaId) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una eficiencia para editar',
                    icon: 'warning'
                });
                return;
            }

            // Obtener datos de la fila seleccionada
            const selectedRow = document.querySelector(`tr[data-eficiencia="${selectedEficiencia}"]`);
            if (!selectedRow) {
                Swal.fire({
                    title: 'Error',
                    text: 'No se encontraron los datos de la eficiencia seleccionada',
                    icon: 'error'
                });
                return;
            }

            const cells = selectedRow.querySelectorAll('td');
            const salonActual = cells[0].textContent.trim();
            const telarActual = cells[1].textContent.trim();
            const fibraActual = cells[2].textContent.trim();
            const eficienciaActual = parseFloat(cells[3].textContent.trim().replace('%', '')) / 100;
            const densidadActual = cells[4].textContent.trim();

            Swal.fire({
                title: 'Editar Eficiencia',
                html: `
                    <div class="text-left">
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telar *</label>
                            <input id="swal-telar-edit" type="text" class="swal2-input" placeholder="Ej: JAC 201" maxlength="10" required value="${telarActual}">
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Hilo *</label>
                            <input id="swal-fibra-edit" type="text" class="swal2-input" placeholder="Ej: H, PAP, FIL370" maxlength="15" required value="${fibraActual}">
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Eficiencia * (0.00 a 1.00 o %)</label>
                            <input id="swal-eficiencia-edit" type="text" class="swal2-input" placeholder="Ej: 0.78 o 78%" required value="${eficienciaActual}">
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Densidad</label>
                            <input id="swal-densidad-edit" type="text" class="swal2-input" placeholder="Ej: Normal, Alta, Baja" maxlength="10" value="${densidadActual}">
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
                    const eficienciaStr = document.getElementById('swal-eficiencia-edit').value.trim();
                    const densidad = document.getElementById('swal-densidad-edit').value.trim();

                    if (!telar || !fibra || !eficienciaStr) {
                        Swal.showValidationMessage('Por favor completa los campos requeridos');
                        return false;
                    }

                    // Convertir eficiencia a decimal
                    let eficiencia = parseFloat(eficienciaStr.replace('%', ''));
                    if (eficienciaStr.includes('%')) {
                        eficiencia = eficiencia / 100;
                    }

                    if (isNaN(eficiencia) || eficiencia < 0 || eficiencia > 1) {
                        Swal.showValidationMessage('La eficiencia debe estar entre 0 y 1 (o 0% y 100%)');
                        return false;
                    }

                    return { telar, fibra, eficiencia, densidad };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const { telar, fibra, eficiencia, densidad } = result.value;

                    // Mostrar loader
                    Swal.fire({
                        title: 'Actualizando...',
                        text: 'Por favor espera',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Realizar petición AJAX para actualizar la eficiencia
                    fetch(`/eficiencia/${selectedEficienciaId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            NoTelarId: telar,
                            FibraId: fibra,
                            Eficiencia: eficiencia,
                            Densidad: densidad
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡Eficiencia Actualizada!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al actualizar la eficiencia');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Error al actualizar la eficiencia',
                            icon: 'error'
                        });
                    });
                }
            });
        }

        function eliminarEficiencia() {
            console.log('eliminarEficiencia llamado, selectedEficiencia:', selectedEficiencia, 'selectedEficienciaId:', selectedEficienciaId); // Debug

            if (!selectedEficiencia || !selectedEficienciaId) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una eficiencia para eliminar',
                    icon: 'warning'
                });
                return;
            }

            const selectedRow = document.querySelector(`tr[data-eficiencia="${selectedEficiencia}"]`);
            if (!selectedRow) {
                Swal.fire({
                    title: 'Error',
                    text: 'No se encontraron los datos de la eficiencia seleccionada',
                    icon: 'error'
                });
                return;
            }

            const cells = selectedRow.querySelectorAll('td');
            const salon = cells[0].textContent.trim();
            const telar = cells[1].textContent.trim();
            const fibra = cells[2].textContent.trim();
            const eficiencia = cells[3].textContent.trim();

            Swal.fire({
                title: '¿Eliminar Eficiencia?',
                html: `
                    <div class="text-left">
                        <p><strong>Salón:</strong> ${salon}</p>
                        <p><strong>Telar:</strong> ${telar}</p>
                        <p><strong>Fibra:</strong> ${fibra}</p>
                        <p><strong>Eficiencia:</strong> ${eficiencia}</p>
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
                    // Mostrar loader
                    Swal.fire({
                        title: 'Eliminando...',
                        text: 'Por favor espera',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Realizar petición AJAX para eliminar la eficiencia
                    fetch(`/eficiencia/${selectedEficienciaId}`, {
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
                                title: '¡Eficiencia Eliminada!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al eliminar la eficiencia');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Error al eliminar la eficiencia',
                            icon: 'error'
                        });
                    });
                }
            });
        }

        function subirExcelEficiencia() {
            Swal.fire({
                title: 'Subir Excel - Eficiencia',
                html: `
                    <div class="text-left">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Seleccionar archivo Excel</label>
                            <input id="excel-file" type="file" accept=".xlsx,.xls" class="swal2-input">
                        </div>
                        <div class="text-sm text-gray-600 bg-blue-50 p-3 rounded">
                            <i class="fas fa-info-circle mr-1"></i>
                            Formatos soportados: .xlsx, .xls (máximo 10MB)
                        </div>
                    </div>
                `,
                width: '500px',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-upload me-2"></i>Subir',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                preConfirm: () => {
                    const fileInput = document.getElementById('excel-file');
                    if (!fileInput.files[0]) {
                        Swal.showValidationMessage('Por favor selecciona un archivo Excel');
                        return false;
                    }
                    return fileInput.files[0];
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const file = result.value;

                    // Mostrar loader
                    Swal.fire({
                        title: 'Procesando...',
                        text: 'Subiendo y procesando archivo Excel',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Crear FormData
                    const formData = new FormData();
                    formData.append('archivo_excel', file);
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                    // Enviar archivo
                    fetch('/eficiencia/excel', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡Excel Procesado!',
                                text: data.message,
                                icon: 'success',
                                timer: 3000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al procesar el archivo');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Error al subir el archivo Excel',
                            icon: 'error'
                        });
                    });
                }
            });
        }

        // Función global para que el botón "Subir Excel" del navbar pueda llamarla
        window.subirExcelEficiencia = function() {
            subirExcelEficiencia();
        };

        // Función global para que el botón "Filtrar" del navbar pueda llamarla
        window.filtrarEficiencia = function() {
            mostrarFiltros();
        };

        // Función global para limpiar filtros desde el navbar
        window.limpiarFiltrosEficiencia = function() {
            console.log('limpiarFiltrosEficiencia llamado desde navbar...');
            limpiarFiltros();
        };

        function mostrarFiltros() {
            Swal.fire({
                title: 'Filtrar Eficiencias',
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Hilo</label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">Eficiencia Mínima (%)</label>
                                <input id="swal-eficiencia-min" type="number" class="swal2-input" placeholder="Ej: 70" min="0" max="100" value="${filtrosActuales.eficiencia_min}">
                        </div>
                        <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Eficiencia Máxima (%)</label>
                                <input id="swal-eficiencia-max" type="number" class="swal2-input" placeholder="Ej: 90" min="0" max="100" value="${filtrosActuales.eficiencia_max}">
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
                confirmButtonText: '<i class="fas fa-search mr-2"></i>Filtrar',
                cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancelar',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                preConfirm: () => {
                    const salon = document.getElementById('swal-salon').value.trim();
                    const telar = document.getElementById('swal-telar').value.trim();
                    const fibra = document.getElementById('swal-fibra').value.trim();
                    const densidad = document.getElementById('swal-densidad').value;
                    const eficienciaMin = document.getElementById('swal-eficiencia-min').value.trim();
                    const eficienciaMax = document.getElementById('swal-eficiencia-max').value.trim();

                    // Validar rangos de eficiencia
                    if (eficienciaMin && eficienciaMax && parseFloat(eficienciaMin) > parseFloat(eficienciaMax)) {
                        Swal.showValidationMessage('La eficiencia mínima no puede ser mayor que la máxima');
                        return false;
                    }

                    return {
                        salon,
                        telar,
                        fibra,
                        densidad,
                        eficiencia_min: eficienciaMin,
                        eficiencia_max: eficienciaMax
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
            const cacheKey = JSON.stringify(filtros);

            // Verificar cache primero
            if (cacheFiltros.has(cacheKey)) {
                const datosFiltrados = cacheFiltros.get(cacheKey);
                actualizarTablaOptimizada(datosFiltrados);
                actualizarContador(datosFiltrados.length);

                const filtrosActivos = Object.values(filtros).filter(val => val !== '').length;
                if (filtrosActivos > 0) {
                    crearToast('success', `${datosFiltrados.length} de ${datosOriginales.length} registros mostrados`);
                }
                return;
            }

            // Filtrar datos de forma más eficiente
            let datosFiltrados = datosOriginales.filter(item => {
                // Optimización: salir temprano si no cumple un filtro
                if (filtros.salon && !item.SalonTejidoId.toLowerCase().includes(filtros.salon.toLowerCase())) return false;
                if (filtros.telar && !item.NoTelarId.toLowerCase().includes(filtros.telar.toLowerCase())) return false;
                if (filtros.fibra && !item.FibraId.toLowerCase().includes(filtros.fibra.toLowerCase())) return false;
                if (filtros.densidad && item.Densidad !== filtros.densidad) return false;

                // Filtros numéricos solo si hay valores
                if (filtros.eficiencia_min) {
                    const eficienciaMinDecimal = parseFloat(filtros.eficiencia_min) / 100;
                    if (item.Eficiencia < eficienciaMinDecimal) return false;
                }

                if (filtros.eficiencia_max) {
                    const eficienciaMaxDecimal = parseFloat(filtros.eficiencia_max) / 100;
                    if (item.Eficiencia > eficienciaMaxDecimal) return false;
                }

                return true;
            });

            // Guardar en cache (máximo 10 entradas para evitar uso excesivo de memoria)
            if (cacheFiltros.size >= 10) {
                const firstKey = cacheFiltros.keys().next().value;
                cacheFiltros.delete(firstKey);
            }
            cacheFiltros.set(cacheKey, datosFiltrados);

            // Actualizar datos actuales
            datosActuales = datosFiltrados;

            // Actualizar tabla de forma más rápida
            actualizarTablaOptimizada(datosFiltrados);

            // Actualizar contador y estado de filtros
            actualizarContador(datosFiltrados.length);

            // Mostrar mensaje de éxito solo si hay filtros
            const filtrosActivos = Object.values(filtros).filter(val => val !== '').length;
            if (filtrosActivos > 0) {
                crearToast('success', `${datosFiltrados.length} de ${datosOriginales.length} registros mostrados`);
            }
        }

        function limpiarFiltros() {
            console.log('limpiarFiltros llamado...');

            // Limpiar filtros
            filtrosActuales = {
                salon: '',
                telar: '',
                fibra: '',
                densidad: '',
                eficiencia_min: '',
                eficiencia_max: ''
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

        // Función optimizada para actualizar la tabla
        function actualizarTablaOptimizada(datos) {
            const tbody = document.getElementById('eficiencia-body');

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
                const eficienciaPorcentaje = Math.round(item.Eficiencia * 100);

                const row = document.createElement('tr');
                row.className = 'text-center hover:bg-blue-50 transition cursor-pointer';
                row.onclick = () => selectRow(row, uniqueId, item.id || null);
                row.ondblclick = () => deselectRow(row);
                row.setAttribute('data-eficiencia', uniqueId);
                row.setAttribute('data-eficiencia-id', item.id || 'null');

                row.innerHTML = `
                    <td class="py-1 px-4 border-b">${item.SalonTejidoId}</td>
                    <td class="py-1 px-4 border-b">${item.NoTelarId}</td>
                    <td class="py-1 px-4 border-b">${item.FibraId}</td>
                    <td class="py-1 px-4 border-b font-semibold">${eficienciaPorcentaje}%</td>
                    <td class="py-1 px-4 border-b">${item.Densidad || 'Normal'}</td>
                `;

                fragment.appendChild(row);
            });

            // Actualizar DOM de una sola vez
            tbody.innerHTML = '';
            tbody.appendChild(fragment);
        }

        // Función original mantenida para compatibilidad
        function actualizarTabla(datos) {
            actualizarTablaOptimizada(datos);
        }

        function actualizarContador(cantidad) {
            // Actualizar contador del navbar
            const filterCount = document.getElementById('filter-count');
            const filtrosActivos = Object.values(filtrosActuales).filter(val => val !== '').length;

            if (filterCount) {
                if (filtrosActivos > 0) {
                    filterCount.textContent = filtrosActivos;
                    filterCount.classList.remove('hidden');
            } else {
                    filterCount.classList.add('hidden');
                }
            }

            // Mostrar/ocultar indicador de filtros activos (solo si existe)
            const indicadorFiltros = document.getElementById('filtros-activos');
            if (indicadorFiltros) {
                const shouldShow = filtrosActivos > 0;
                if (shouldShow && indicadorFiltros.classList.contains('hidden')) {
                    indicadorFiltros.classList.remove('hidden');
                } else if (!shouldShow && !indicadorFiltros.classList.contains('hidden')) {
                    indicadorFiltros.classList.add('hidden');
                }
            }
        }

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            disableButtons();

            // Verificar si hay filtros activos desde la URL (para mantener estado después de recargar)
            const urlParams = new URLSearchParams(window.location.search);
            const hasFilters = urlParams.has('salon') || urlParams.has('telar') || urlParams.has('fibra') ||
                              urlParams.has('densidad') || urlParams.has('eficiencia_min') || urlParams.has('eficiencia_max');

            if (hasFilters) {
                const indicadorFiltros = document.getElementById('filtros-activos');
                if (indicadorFiltros) {
                    indicadorFiltros.classList.remove('hidden');
                }
            }
        });
    </script>

    <style>
        /* Estilos personalizados para el scrollbar */
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
