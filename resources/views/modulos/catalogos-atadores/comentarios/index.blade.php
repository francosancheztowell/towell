@extends('layouts.app')
@section('page-title', 'Catálogo de Comentarios')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create onclick="openCreateModal()" title="Nuevo Comentario" module="Atadores"/>
        <x-navbar.button-edit id="btnEdit" onclick="editSelected()" title="Editar Comentario" module="Atadores" :disabled="true"/>
        <x-navbar.button-delete id="btnDelete" onclick="deleteSelected()" title="Eliminar Comentario" module="Atadores" :disabled="true"/>
    </div>
@endsection

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Tabla de actividades -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" id="actividadesTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nota 1</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nota 2</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($comentarios as $comentario)
                    <tr class="hover:bg-gray-50 cursor-pointer transition-colors duration-150"
                        onclick="selectRow(this, '{{ $comentario->Nota1 }}')"
                        data-id="{{ $comentario->Nota1 }}">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $comentario->Nota1 }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $comentario->Nota2 }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="px-6 py-4 text-center text-gray-500">
                            No hay comentarios registrados
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

<!-- Modal Ver Actividad -->
<div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
        <div class="flex justify-between items-center border-b p-4">
            <h2 class="text-xl font-bold text-gray-800">Detalles del comentario</h2>
            <button onclick="closeViewModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nota 1</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_nota1">-</p>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nota 2</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_nota2">-</p>
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

<!-- Modal Crear/Editar Actividad -->
<div id="formModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
        <div class="flex justify-between items-center border-b p-4">
            <h2 class="text-xl font-bold text-gray-800" id="formModalTitle">Nuevo Comentario</h2>
            <button onclick="closeFormModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <form id="comentarioForm" onsubmit="handleSubmit(event)">
            <div class="p-6">
                <input type="hidden" id="original_nota1" name="original_nota1">

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="Nota1" class="block text-sm font-medium text-gray-700 mb-1">
                            Nota 1 <span class="text-red-500">*</span>
                        </label>
                        <textarea id="Nota1" name="Nota1" required rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 hover:shadow-sm transition-all duration-200"
                            placeholder="Ingrese la primera nota"></textarea>
                    </div>

                    <div>
                        <label for="Nota2" class="block text-sm font-medium text-gray-700 mb-1">
                            Nota 2
                        </label>
                        <textarea id="Nota2" name="Nota2" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 hover:shadow-sm transition-all duration-200"
                            placeholder="Ingrese la segunda nota (opcional)"></textarea>
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
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl"></i>
                </div>
                <div>
                    <p class="text-gray-700">¿Está seguro que desea eliminar este comentario?</p>
                    <p class="text-gray-500 text-sm mt-1">Esta acción no se puede deshacer.</p>
                </div>
            </div>
        </div>
        <div class="border-t p-4 flex justify-end gap-2">
            <button onclick="closeDeleteModal()"
                class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg">
                Cancelar
            </button>
            <button onclick="deleteComentario()"
                class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg">
                <i class="fas fa-trash mr-1"></i> Eliminar
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    let currentComentarioNota1 = null;
    let selectedRow = null;

    // Seleccionar fila de la tabla
    function selectRow(row, nota1) {
        // Remover selección previa
        if (selectedRow) {
            selectedRow.classList.remove('bg-blue-500');
            selectedRow.querySelectorAll('td').forEach(td => {
                td.classList.remove('text-white');
                td.classList.add('text-gray-900');
            });
        }

        // Si se hace clic en la misma fila, deseleccionar
        if (selectedRow === row) {
            selectedRow = null;
            currentComentarioNota1 = null;
            disableButtons();
            return;
        }

        // Seleccionar nueva fila
        selectedRow = row;
        currentComentarioNota1 = nota1;
        row.classList.add('bg-blue-500');
        row.querySelectorAll('td').forEach(td => {
            td.classList.remove('text-gray-900');
            td.classList.add('text-white');
        });

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
        if (currentComentarioNota1) {
            openEditModal(currentComentarioNota1);
        }
    }

    // Eliminar registro seleccionado desde navbar
    function deleteSelected() {
        if (currentComentarioNota1) {
            confirmDelete(currentComentarioNota1);
        }
    }

    // Abrir modal de creación
    function openCreateModal() {
        document.getElementById('formModalTitle').textContent = 'Nuevo Comentario';
        document.getElementById('comentarioForm').reset();
        document.getElementById('original_nota1').value = '';
        document.getElementById('formModal').classList.remove('hidden');
        document.getElementById('formModal').classList.add('flex');
    }

    // Abrir modal de edición
    function openEditModal(nota1) {
        currentComentarioNota1 = nota1;
        document.getElementById('formModalTitle').textContent = 'Editar Comentario';

        // Cargar datos desde el backend
        axios.get(`/atadores/catalogos/comentarios/${encodeURIComponent(nota1)}`)
            .then(response => {
                if (response.data.success) {
                    const data = response.data.data;
                    document.getElementById('original_nota1').value = data.Nota1;
                    document.getElementById('Nota1').value = data.Nota1;
                    document.getElementById('Nota2').value = data.Nota2 || '';

                    document.getElementById('formModal').classList.remove('hidden');
                    document.getElementById('formModal').classList.add('flex');
                }
            })
            .catch(error => {
                console.error('Error al cargar comentario:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo cargar el comentario'
                });
            });
    }

    // Abrir modal de vista
    function openViewModal(nota1) {
        // Cargar datos desde el backend
        axios.get(`/atadores/catalogos/comentarios/${encodeURIComponent(nota1)}`)
            .then(response => {
                if (response.data.success) {
                    const data = response.data.data;
                    document.getElementById('view_nota1').textContent = data.Nota1;
                    document.getElementById('view_nota2').textContent = data.Nota2 || '-';

                    document.getElementById('viewModal').classList.remove('hidden');
                    document.getElementById('viewModal').classList.add('flex');
                }
            })
            .catch(error => {
                console.error('Error al cargar comentario:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo cargar el comentario'
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
    function confirmDelete(nota1) {
        currentComentarioNota1 = nota1;
        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('deleteModal').classList.add('flex');
    }

    // Eliminar comentario
    function deleteComentario() {
        axios.delete(`/atadores/catalogos/comentarios/${encodeURIComponent(currentComentarioNota1)}`)
            .then(response => {
                if (response.data.success) {
                    // Remover fila seleccionada
                    if (selectedRow) {
                        selectedRow.remove();
                        selectedRow = null;
                        currentComentarioNota1 = null;
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
                    text: error.response?.data?.message || 'No se pudo eliminar el comentario'
                });
            });
    }

    // Manejar envío del formulario
    function handleSubmit(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        const data = {
            Nota1: formData.get('Nota1'),
            Nota2: formData.get('Nota2')
        };

        const originalNota1 = formData.get('original_nota1');
        const isEdit = originalNota1 !== '';

        const url = isEdit
            ? `/atadores/catalogos/comentarios/${encodeURIComponent(originalNota1)}`
            : '/atadores/catalogos/comentarios';

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
                    text: error.response?.data?.message || 'No se pudo guardar el comentario'
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
