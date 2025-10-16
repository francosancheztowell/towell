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
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            onclick="selectRow(this, '{{ $telar->telar }}')"
                            ondblclick="deselectRow(this)"
                            data-telar="{{ $telar->telar }}">
                            <td class="py-2 px-4 border-b">{{ $telar->salon }}</td>
                            <td class="py-2 px-4 border-b">{{ $telar->telar }}</td>
                            <td class="py-2 px-4 border-b">{{ $telar->nombre }}</td>
                            <td class="py-2 px-4 border-b">{{ $telar->grupo ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>


    </div>

    <!-- Modal Añadir Telar -->
    <div id="modal-agregar" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Agregar Nuevo Telar</h3>
                <button onclick="cerrarModal('modal-agregar')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="form-agregar" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Salón</label>
                    <input type="text" id="agregar-salon" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: Salón A">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telar</label>
                    <input type="text" id="agregar-telar" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: T001">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <input type="text" id="agregar-nombre" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: Telar Sulzer 1">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Grupo</label>
                    <input type="text" id="agregar-grupo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: Grupo 1">
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="cerrarModal('modal-agregar')" class="px-4 py-2 text-gray-600 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                        Agregar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Telar -->
    <div id="modal-editar" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Editar Telar</h3>
                <button onclick="cerrarModal('modal-editar')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="form-editar" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Salón</label>
                    <input type="text" id="editar-salon" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telar</label>
                    <input type="text" id="editar-telar" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" readonly>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <input type="text" id="editar-nombre" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Grupo</label>
                    <input type="text" id="editar-grupo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="cerrarModal('modal-editar')" class="px-4 py-2 text-gray-600 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Confirmar Eliminar (reemplazado por SweetAlert2) -->
    <div id="modal-eliminar" class="hidden"></div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let selectedTelar = null;

        function selectRow(row, telarId) {
            // Remover selección anterior
            document.querySelectorAll('tbody tr').forEach(r => {
                r.classList.remove('bg-blue-500', 'text-white');
                r.classList.add('hover:bg-blue-50');
            });

            // Seleccionar fila actual
            row.classList.remove('hover:bg-blue-50');
            row.classList.add('bg-blue-500', 'text-white');

            // Guardar telar seleccionado
            selectedTelar = telarId;

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

        // Funciones para manejar modales
        function abrirModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function cerrarModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function agregarTelar() {
            // Limpiar formulario
            document.getElementById('agregar-salon').value = '';
            document.getElementById('agregar-telar').value = '';
            document.getElementById('agregar-nombre').value = '';
            document.getElementById('agregar-grupo').value = '';

            // Abrir modal
            abrirModal('modal-agregar');
        }

        function editarTelar() {
            if (!selectedTelar) {
                alert('Por favor selecciona un telar para editar');
                return;
            }

            // Obtener datos de la fila seleccionada
            const selectedRow = document.querySelector(`tr[data-telar="${selectedTelar}"]`);
            if (selectedRow) {
                const cells = selectedRow.querySelectorAll('td');
                document.getElementById('editar-salon').value = cells[0].textContent;
                document.getElementById('editar-telar').value = cells[1].textContent;
                document.getElementById('editar-nombre').value = cells[2].textContent;
                document.getElementById('editar-grupo').value = cells[3].textContent;
            }

            // Abrir modal
            abrirModal('modal-editar');
        }

        function eliminarTelar() {
            if (!selectedTelar) {
                alert('Por favor selecciona un telar para eliminar');
                return;
            }

            const selectedRow = document.querySelector(`tr[data-telar="${selectedTelar}"]`);
            const nombre = selectedRow ? selectedRow.querySelectorAll('td')[2].textContent : selectedTelar;

            Swal.fire({
                title: '¿Eliminar telar?',
                html: `Vas a eliminar <b>${nombre}</b>. Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Aquí puedes realizar la petición DELETE
                    // fetch('/telares/' + selectedTelar, { method: 'DELETE' })

                    showToast('El telar fue eliminado correctamente', 'success');

                    // Reiniciar selección/botones
                    disableButtons();
                }
            });
        }

        function subirExcelTelar() {
            // Esta función será llamada por el botón "Subir Excel" del componente action-buttons
            // El modal se maneja desde el componente
            console.log('Subir Excel para Telares - función llamada desde action-buttons');
        }

        // Inicializar botones como deshabilitados y listeners de formularios
        document.addEventListener('DOMContentLoaded', function() {
            disableButtons();

            // Event listeners para los formularios de modales
            document.getElementById('form-agregar').addEventListener('submit', function(e) {
                e.preventDefault();

                const salon = document.getElementById('agregar-salon').value;
                const telar = document.getElementById('agregar-telar').value;
                const nombre = document.getElementById('agregar-nombre').value;
                const grupo = document.getElementById('agregar-grupo').value;

                if (!salon || !telar || !nombre || !grupo) {
                    alert('Por favor completa todos los campos');
                    return;
                }

                // Simulación de guardado
                showToast(`${nombre} (${telar}) agregado correctamente`, 'success');
                cerrarModal('modal-agregar');
            });

            document.getElementById('form-editar').addEventListener('submit', function(e) {
                e.preventDefault();

                const salon = document.getElementById('editar-salon').value;
                const telar = document.getElementById('editar-telar').value;
                const nombre = document.getElementById('editar-nombre').value;
                const grupo = document.getElementById('editar-grupo').value;

                if (!salon || !telar || !nombre || !grupo) {
                    alert('Por favor completa todos los campos');
                    return;
                }

                // Simulación de actualización
                showToast(`${nombre} (${telar}) actualizado correctamente`, 'success');
                cerrarModal('modal-editar');
            });
        });

    </script>

    @include('components.toast-notification')

@endsection
