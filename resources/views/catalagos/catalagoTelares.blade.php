@extends('layouts.app')

@section('content')
    <div class="container">

        <!-- Mensaje si no hay resultados -->
        @if ($noResults)
            <div class="alert alert-warning text-center" role="alert">
                No se encontraron resultados con la información proporcionada.
            </div>
        @endif

        <!-- Tabla de telares -->
        <div class=" overflow-y-auto ">
            <table class="min-w-full text-sm ">
                <thead class="sticky top-0 bg-blue-500 border-b-2 text-white z-10">
                    <tr>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Salón</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Telar</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Nombre</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Grupo</th>
                    </tr>
                </thead>
                <tbody id="telares-body" class="bg-white text-black">
                    @foreach ($telares as $telar)
                        @php
                            // Crear un ID único combinando salón y telar
                            $uniqueId = $telar->SalonTejidoId . '_' . $telar->NoTelarId;
                        @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            onclick="selectRow(this, '{{ $uniqueId }}', {{ $telar->id ?? 'null' }})"
                            ondblclick="deselectRow(this)"
                            data-telar="{{ $uniqueId }}"
                            data-telar-id="{{ $telar->id ?? 'null' }}">
                            <td class="py-2 px-4 border-b">{{ $telar->SalonTejidoId }}</td>
                            <td class="py-2 px-4 border-b">{{ $telar->NoTelarId }}</td>
                            <td class="py-2 px-4 border-b">{{ $telar->Nombre }}</td>
                            <td class="py-2 px-4 border-b">{{ $telar->Grupo ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>


    </div>

    <!-- Los modales HTML han sido reemplazados por SweetAlert2 -->

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let selectedTelar = null;
        let selectedTelarId = null;

        function selectRow(row, uniqueId, telarId) {
            console.log('selectRow llamado con uniqueId:', uniqueId, 'telarId:', telarId); // Debug

            // Remover selección anterior
            document.querySelectorAll('tbody tr').forEach(r => {
                r.classList.remove('bg-blue-500', 'text-white');
                r.classList.add('hover:bg-blue-50');
            });

            // Seleccionar fila actual
            row.classList.remove('hover:bg-blue-50');
            row.classList.add('bg-blue-500', 'text-white');

            // Guardar telar seleccionado
            selectedTelar = uniqueId;
            selectedTelarId = telarId;
            console.log('selectedTelar establecido a:', selectedTelar, 'selectedTelarId:', selectedTelarId); // Debug

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
                selectedTelar = null;
                selectedTelarId = null;

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

            selectedTelar = null;
        }

        // Las funciones de modales HTML han sido reemplazadas por SweetAlert2

        function agregarTelar() {
            Swal.fire({
                title: 'Crear Nuevo Telar',
                html: `
                    <div class="text-left">
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Salón de Tejido *</label>
                            <input id="swal-salon" type="text" class="swal2-input" placeholder="Ej: Jacquard, Smith" maxlength="20" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Número de Telar *</label>
                            <input id="swal-telar" type="text" class="swal2-input" placeholder="Ej: 201, 202, 300" maxlength="10" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Grupo</label>
                            <input id="swal-grupo" type="text" class="swal2-input" placeholder="Ej: Jacquard Smith, Itema Nuevo" maxlength="30">
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
                    const salon = document.getElementById('swal-salon').value.trim();
                    const telar = document.getElementById('swal-telar').value.trim();
                    const grupo = document.getElementById('swal-grupo').value.trim();

                    if (!salon || !telar) {
                        Swal.showValidationMessage('Por favor completa los campos requeridos (Salón y Telar)');
                        return false;
                    }

                    return { salon, telar, grupo };
                },
                didOpen: () => {
                    const salonInput = document.getElementById('swal-salon');
                    const telarInput = document.getElementById('swal-telar');
                    const previewDiv = document.getElementById('preview-nombre');

                    function updatePreview() {
                        const salon = salonInput.value.trim();
                        const telar = telarInput.value.trim();

                        if (salon && telar) {
                            const salonUpper = salon.toUpperCase();
                            let prefijo;

                            if (salonUpper.includes('JACQUARD')) {
                                prefijo = 'JAC';
                            } else if (salonUpper.includes('SMITH')) {
                                prefijo = 'Smith';
                            } else {
                                prefijo = salon.substring(0, 3).toUpperCase();
                            }

                            previewDiv.textContent = prefijo + ' ' + telar;
                        } else {
                            previewDiv.textContent = '-';
                        }
                    }

                    salonInput.addEventListener('input', updatePreview);
                    telarInput.addEventListener('input', updatePreview);
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const { salon, telar, grupo } = result.value;

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

                    // Realizar petición AJAX para crear el telar
                    fetch('/telares', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            SalonTejidoId: salon,
                            NoTelarId: telar,
                            Grupo: grupo
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡Telar Creado!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al crear el telar');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Error al crear el telar',
                            icon: 'error'
                        });
                    });
                }
            });
        }

        function editarTelar() {
            console.log('editarTelar llamado, selectedTelar:', selectedTelar, 'selectedTelarId:', selectedTelarId); // Debug

            if (!selectedTelar || !selectedTelarId) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona un telar para editar',
                    icon: 'warning'
                });
                return;
            }

            // Obtener datos de la fila seleccionada
            const selectedRow = document.querySelector(`tr[data-telar="${selectedTelar}"]`);
            if (!selectedRow) {
                Swal.fire({
                    title: 'Error',
                    text: 'No se encontraron los datos del telar seleccionado',
                    icon: 'error'
                });
                return;
            }

            const cells = selectedRow.querySelectorAll('td');
            const salonActual = cells[0].textContent.trim();
            const telarActual = cells[1].textContent.trim();
            const nombreActual = cells[2].textContent.trim();
            const grupoActual = cells[3].textContent.trim();

            Swal.fire({
                title: 'Editar Telar',
                html: `
                    <div class="text-left">
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Salón de Tejido *</label>
                            <input id="swal-salon-edit" type="text" class="swal2-input" placeholder="Ej: Jacquard, Smith" maxlength="20" required value="${salonActual}">
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Número de Telar *</label>
                            <input id="swal-telar-edit" type="text" class="swal2-input" placeholder="Ej: 201, 202, 300" maxlength="10" required value="${telarActual}">
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Grupo</label>
                            <input id="swal-grupo-edit" type="text" class="swal2-input" placeholder="Ej: Jacquard Smith, Itema Nuevo" maxlength="30" value="${grupoActual}">
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
                    const salon = document.getElementById('swal-salon-edit').value.trim();
                    const telar = document.getElementById('swal-telar-edit').value.trim();
                    const grupo = document.getElementById('swal-grupo-edit').value.trim();

                    if (!salon || !telar) {
                        Swal.showValidationMessage('Por favor completa los campos requeridos (Salón y Telar)');
                        return false;
                    }

                    return { salon, telar, grupo };
                },
                didOpen: () => {
                    const salonInput = document.getElementById('swal-salon-edit');
                    const telarInput = document.getElementById('swal-telar-edit');
                    const previewDiv = document.getElementById('preview-nombre-edit');

                    function updatePreview() {
                        const salon = salonInput.value.trim();
                        const telar = telarInput.value.trim();

                        if (salon && telar) {
                            const salonUpper = salon.toUpperCase();
                            let prefijo;

                            if (salonUpper.includes('JACQUARD')) {
                                prefijo = 'JAC';
                            } else if (salonUpper.includes('SMITH')) {
                                prefijo = 'Smith';
                            } else {
                                prefijo = salon.substring(0, 3).toUpperCase();
                            }

                            previewDiv.textContent = prefijo + ' ' + telar;
                        } else {
                            previewDiv.textContent = '-';
                        }
                    }

                    salonInput.addEventListener('input', updatePreview);
                    telarInput.addEventListener('input', updatePreview);
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const { salon, telar, grupo } = result.value;

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

                    // Realizar petición AJAX para actualizar el telar
                    fetch(`/telares/${selectedTelarId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            SalonTejidoId: salon,
                            NoTelarId: telar,
                            Grupo: grupo
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡Telar Actualizado!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al actualizar el telar');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Error al actualizar el telar',
                            icon: 'error'
                        });
                    });
                }
            });
        }

        function eliminarTelar() {
            console.log('eliminarTelar llamado, selectedTelar:', selectedTelar, 'selectedTelarId:', selectedTelarId); // Debug

            if (!selectedTelar || !selectedTelarId) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona un telar para eliminar',
                    icon: 'warning'
                });
                return;
            }

            const selectedRow = document.querySelector(`tr[data-telar="${selectedTelar}"]`);
            if (!selectedRow) {
                Swal.fire({
                    title: 'Error',
                    text: 'No se encontraron los datos del telar seleccionado',
                    icon: 'error'
                });
                return;
            }

            const cells = selectedRow.querySelectorAll('td');
            const salon = cells[0].textContent.trim();
            const telar = cells[1].textContent.trim();
            const nombre = cells[2].textContent.trim();

            Swal.fire({
                title: '¿Eliminar Telar?',
                html: `
                    <div class="text-left">
                        <p><strong>Salón:</strong> ${salon}</p>
                        <p><strong>Telar:</strong> ${telar}</p>
                        <p><strong>Nombre:</strong> ${nombre}</p>
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

                    // Realizar petición AJAX para eliminar el telar
                    fetch(`/telares/${selectedTelarId}`, {
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
                                title: '¡Telar Eliminado!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al eliminar el telar');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Error al eliminar el telar',
                            icon: 'error'
                        });
                    });
                }
            });
        }

        function subirExcelTelar() {
            // Esta función será llamada por el botón "Subir Excel" del componente action-buttons
            // El modal se maneja desde el componente
            console.log('Subir Excel para Telares - función llamada desde action-buttons');
        }

        // Inicializar botones como deshabilitados
        document.addEventListener('DOMContentLoaded', function() {
            disableButtons();
        });

    </script>

    @include('components.toast-notification')

@endsection
