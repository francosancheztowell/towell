@extends('layouts.app')
@section('page-title', 'Máquinas Atadores')
@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create onclick="openCreateModal()" title="Nueva Máquina" module="Maquinas"/>
        <x-navbar.button-edit id="btnEdit" onclick="editSelected()" title="Editar Máquina" module="Maquinas" />
        <x-navbar.button-delete id="btnDelete" onclick="deleteSelected()" title="Eliminar Máquina" module="Maquinas" />
    </div>
@endsection

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Tabla de máquinas -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" id="maquinasTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Máquina ID</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($maquinas as $maquina)
                    <tr class="hover:bg-gray-50 cursor-pointer transition-colors duration-150"
                        onclick="selectRow(this, '{{ $maquina->MaquinaId }}')"
                        data-id="{{ $maquina->MaquinaId }}">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $maquina->MaquinaId }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="1" class="px-6 py-4 text-center text-gray-500">
                            No hay máquinas registradas
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

<!-- Modal Ver Máquina -->
<div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
        <div class="flex justify-between items-center border-b p-4">
            <h2 class="text-xl font-bold text-gray-800">Detalles de la Máquina</h2>
            <button onclick="closeViewModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Máquina ID</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_maquinaid">-</p>
                </div>
            </div>
        </div>
        <div class="border-t p-4 flex justify-end">
            <button onclick="closeViewModal()" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg">
                Cerrar
            </button>
        </div>
    </div>
</div>

<!-- Modal Crear/Editar Máquina -->
<div id="formModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
        <div class="flex justify-between items-center border-b p-4">
            <h2 class="text-xl font-bold text-gray-800" id="formModalTitle">Nueva Máquina</h2>
            <button onclick="closeFormModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <form id="maquinaForm" onsubmit="handleSubmit(event)">
            <div class="p-6">
                <input type="hidden" id="original_maquinaid" name="original_maquinaid">

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="MaquinaId" class="block text-sm font-medium text-gray-700 mb-1">
                            Máquina ID <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="MaquinaId" name="MaquinaId" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 hover:shadow-sm transition-all duration-200"
                            placeholder="Ingrese el ID de la máquina">
                    </div>
                </div>
            </div>

            <div class="border-t p-4 flex justify-end gap-2">
                <button type="button" onclick="closeFormModal()"
                    class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg">
                    Cancelar
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg">
                    <i class="fas fa-save mr-1"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Confirmar Eliminación -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="flex justify-between items-center border-b p-4 bg-red-50">
            <h2 class="text-xl font-bold text-red-700">Confirmar Eliminación</h2>
            <button onclick="closeDeleteModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-4 mb-4">
                <div class="shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl"></i>
                </div>
                <div>
                    <p class="text-gray-700">¿Está seguro que desea eliminar esta máquina?</p>
                    <p class="text-gray-500 text-sm mt-1">Esta acción no se puede deshacer.</p>
                </div>
            </div>
        </div>
        <div class="border-t p-4 flex justify-end gap-2">
            <button onclick="closeDeleteModal()"
                class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg">
                Cancelar
            </button>
            <button onclick="deleteMaquina()"
                class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg">
                <i class="fas fa-trash mr-1"></i> Eliminar
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    let currentMaquinaId = null;
    let selectedRow = null;

    // Seleccionar fila de la tabla
    function selectRow(row, maquinaId) {
        // Remover selección previa
        if (selectedRow) {
            selectedRow.classList.remove('bg-blue-100', 'border-l-4', 'border-blue-500');
        }

        // Si se hace clic en la misma fila, deseleccionar
        if (selectedRow === row) {
            selectedRow = null;
            currentMaquinaId = null;
            disableButtons();
            return;
        }

        // Seleccionar nueva fila
        selectedRow = row;
        currentMaquinaId = maquinaId;
        row.classList.add('bg-blue-100', 'border-l-4', 'border-blue-500');

        // Habilitar botones
        enableButtons();
    }

    // Habilitar botones del navbar
    function enableButtons() {
        const btnEdit = document.getElementById('btnEdit');
        const btnDelete = document.getElementById('btnDelete');

        if (btnEdit) {
            btnEdit.disabled = false;
            btnEdit.classList.remove('opacity-50', 'cursor-not-allowed');
            btnEdit.classList.add('cursor-pointer');
        }

        if (btnDelete) {
            btnDelete.disabled = false;
            btnDelete.classList.remove('opacity-50', 'cursor-not-allowed');
            btnDelete.classList.add('cursor-pointer');
        }
    }

    // Deshabilitar botones del navbar
    function disableButtons() {
        const btnEdit = document.getElementById('btnEdit');
        const btnDelete = document.getElementById('btnDelete');

        if (btnEdit) {
            btnEdit.disabled = true;
            btnEdit.classList.add('opacity-50', 'cursor-not-allowed');
            btnEdit.classList.remove('cursor-pointer');
        }

        if (btnDelete) {
            btnDelete.disabled = true;
            btnDelete.classList.add('opacity-50', 'cursor-not-allowed');
            btnDelete.classList.remove('cursor-pointer');
        }
    }

    // Editar registro seleccionado desde navbar
    function editSelected() {
        if (currentMaquinaId) {
            openEditModal(currentMaquinaId);
        }
    }

    // Eliminar registro seleccionado desde navbar
    function deleteSelected() {
        if (currentMaquinaId) {
            confirmDelete(currentMaquinaId);
        }
    }

    // Abrir modal de creación
    function openCreateModal() {
        document.getElementById('formModalTitle').textContent = 'Nueva Máquina';
        document.getElementById('maquinaForm').reset();
        document.getElementById('original_maquinaid').value = '';
        document.getElementById('formModal').classList.remove('hidden');
        document.getElementById('formModal').classList.add('flex');
    }

    // Abrir modal de edición
    function openEditModal(maquinaId) {
        currentMaquinaId = maquinaId;
        document.getElementById('formModalTitle').textContent = 'Editar Máquina';

        // Cargar datos desde el backend
        axios.get(`/atadores/catalogos/maquinas/${encodeURIComponent(maquinaId)}`)
            .then(response => {
                if (response.data.success) {
                    const data = response.data.data;
                    document.getElementById('original_maquinaid').value = data.MaquinaId;
                    document.getElementById('MaquinaId').value = data.MaquinaId;

                    document.getElementById('formModal').classList.remove('hidden');
                    document.getElementById('formModal').classList.add('flex');
                }
            })
            .catch(error => {
                console.error('Error al cargar máquina:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo cargar la máquina'
                });
            });
    }

    // Abrir modal de vista
    function openViewModal(maquinaId) {
        // Cargar datos desde el backend
        axios.get(`/atadores/catalogos/maquinas/${encodeURIComponent(maquinaId)}`)
            .then(response => {
                if (response.data.success) {
                    const data = response.data.data;
                    document.getElementById('view_maquinaid').textContent = data.MaquinaId;

                    document.getElementById('viewModal').classList.remove('hidden');
                    document.getElementById('viewModal').classList.add('flex');
                }
            })
            .catch(error => {
                console.error('Error al cargar máquina:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo cargar la máquina'
                });
            });
    }

    // Cerrar modales
    function closeFormModal() {
        document.getElementById('formModal').classList.add('hidden');
        document.getElementById('formModal').classList.remove('flex');
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.add('hidden');
        document.getElementById('viewModal').classList.remove('flex');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        document.getElementById('deleteModal').classList.remove('flex');
    }

    // Confirmar eliminación
    function confirmDelete(maquinaId) {
        currentMaquinaId = maquinaId;
        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('deleteModal').classList.add('flex');
    }

    // Eliminar máquina
    function deleteMaquina() {
        axios.delete(`/atadores/catalogos/maquinas/${encodeURIComponent(currentMaquinaId)}`)
            .then(response => {
                if (response.data.success) {
                    // Remover fila seleccionada
                    if (selectedRow) {
                        selectedRow.remove();
                        selectedRow = null;
                        currentMaquinaId = null;
                        disableButtons();
                    }

                    closeDeleteModal();

                    Swal.fire({
                        icon: 'success',
                        title: '¡Eliminado!',
                        text: response.data.message,
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            })
            .catch(error => {
                console.error('Error al eliminar:', error);
                closeDeleteModal();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.response?.data?.message || 'No se pudo eliminar la máquina'
                });
            });
    }

    // Manejar envío del formulario
    function handleSubmit(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        const data = {
            MaquinaId: formData.get('MaquinaId')
        };

        const originalMaquinaId = formData.get('original_maquinaid');
        const isEdit = originalMaquinaId !== '';

        const url = isEdit
            ? `/atadores/catalogos/maquinas/${encodeURIComponent(originalMaquinaId)}`
            : '/atadores/catalogos/maquinas';

        const method = isEdit ? 'put' : 'post';

        axios[method](url, data)
            .then(response => {
                if (response.data.success) {
                    closeFormModal();

                    Swal.fire({
                        icon: 'success',
                        title: isEdit ? '¡Actualizado!' : '¡Creado!',
                        text: response.data.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        // Recargar la página para ver los cambios
                        location.reload();
                    });
                }
            })
            .catch(error => {
                console.error('Error al guardar:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.response?.data?.message || 'No se pudo guardar la máquina'
                });
            });
    }

    // Cerrar modales al hacer clic fuera
    window.onclick = function(event) {
        const formModal = document.getElementById('formModal');
        const viewModal = document.getElementById('viewModal');
        const deleteModal = document.getElementById('deleteModal');

        if (event.target === formModal) {
            closeFormModal();
        }
        if (event.target === viewModal) {
            closeViewModal();
        }
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
    }

    // Inicializar botones como deshabilitados
    document.addEventListener('DOMContentLoaded', function() {
        disableButtons();
    });
</script>
@endpush
