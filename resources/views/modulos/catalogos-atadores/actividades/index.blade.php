@extends('layouts.app')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create onclick="openCreateModal()" title="Nueva Actividad" module="Atadores"/>
        <x-navbar.button-edit id="btnEdit" onclick="editSelected()" title="Editar Actividad" module="Atadores" :disabled="true"/>
        <x-navbar.button-delete id="btnDelete" onclick="deleteSelected()" title="Eliminar Actividad" module="Atadores" :disabled="true"/>
    </div>
@endsection

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Encabezado -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Catálogo de Actividades</h1>
    </div>

    <!-- Tabla de actividades -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" id="actividadesTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actividad ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Porcentaje</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($actividades as $actividad)
                    <tr class="hover:bg-gray-50 cursor-pointer transition-colors duration-150" 
                        onclick="selectRow(this, '{{ $actividad->ActividadId }}')" 
                        data-id="{{ $actividad->ActividadId }}">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $actividad->ActividadId }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $actividad->Porcentaje }}%</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="px-6 py-4 text-center text-gray-500">
                            No hay actividades registradas
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
            <h2 class="text-xl font-bold text-gray-800">Detalles de la Actividad</h2>
            <button onclick="closeViewModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Actividad ID</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_actividadid">-</p>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Porcentaje</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_porcentaje">-</p>
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
            <h2 class="text-xl font-bold text-gray-800" id="formModalTitle">Nueva Actividad</h2>
            <button onclick="closeFormModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <form id="actividadForm" onsubmit="handleSubmit(event)">
            <div class="p-6">
                <input type="hidden" id="original_actividadid" name="original_id">
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label for="ActividadId" class="block text-sm font-medium text-gray-700 mb-1">
                            Actividad ID <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="ActividadId" name="ActividadId" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 hover:shadow-sm transition-all duration-200"
                            placeholder="Ej: MONTAJE">
                    </div>
                    
                    <div class="col-span-2">
                        <label for="Porcentaje" class="block text-sm font-medium text-gray-700 mb-1">
                            Porcentaje <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="Porcentaje" name="Porcentaje" required min="0" max="100" step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 hover:shadow-sm transition-all duration-200"
                            placeholder="0-100">
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
                    <p class="text-gray-700">¿Está seguro que desea eliminar esta actividad?</p>
                    <p class="text-gray-500 text-sm mt-1">Esta acción no se puede deshacer.</p>
                </div>
            </div>
        </div>
        <div class="border-t p-4 flex justify-end gap-2">
            <button onclick="closeDeleteModal()" 
                class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg">
                Cancelar
            </button>
            <button onclick="deleteActividad()" 
                class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg">
                <i class="fas fa-trash mr-1"></i> Eliminar
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    let currentActividadId = null;
    let selectedRow = null;

    // Seleccionar fila de la tabla
    function selectRow(row, id) {
        // Remover selección previa
        if (selectedRow) {
            selectedRow.classList.remove('bg-blue-100', 'border-l-4', 'border-blue-500');
        }
        
        // Si se hace clic en la misma fila, deseleccionar
        if (selectedRow === row) {
            selectedRow = null;
            currentActividadId = null;
            disableButtons();
            return;
        }
        
        // Seleccionar nueva fila
        selectedRow = row;
        currentActividadId = id;
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
        if (currentActividadId) {
            openEditModal(currentActividadId);
        }
    }

    // Eliminar registro seleccionado desde navbar
    function deleteSelected() {
        if (currentActividadId) {
            confirmDelete(currentActividadId);
        }
    }

    // Abrir modal de creación
    function openCreateModal() {
        document.getElementById('formModalTitle').textContent = 'Nueva Actividad';
        document.getElementById('actividadForm').reset();
        document.getElementById('original_actividadid').value = '';
        document.getElementById('formModal').classList.remove('hidden');
        document.getElementById('formModal').classList.add('flex');
    }

    // Abrir modal de edición
    function openEditModal(id) {
        currentActividadId = id;
        document.getElementById('formModalTitle').textContent = 'Editar Actividad';
        
        // Cargar datos desde el backend
        axios.get(`/atadores/catalogos/actividades/${id}`)
            .then(response => {
                if (response.data.success) {
                    const data = response.data.data;
                    document.getElementById('original_actividadid').value = data.ActividadId;
                    document.getElementById('ActividadId').value = data.ActividadId;
                    document.getElementById('Porcentaje').value = data.Porcentaje;
                    
                    document.getElementById('formModal').classList.remove('hidden');
                    document.getElementById('formModal').classList.add('flex');
                }
            })
            .catch(error => {
                console.error('Error al cargar actividad:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo cargar la actividad'
                });
            });
    }

    // Abrir modal de vista
    function openViewModal(id) {
        // Cargar datos desde el backend
        axios.get(`/atadores/catalogos/actividades/${id}`)
            .then(response => {
                if (response.data.success) {
                    const data = response.data.data;
                    document.getElementById('view_actividadid').textContent = data.ActividadId;
                    document.getElementById('view_porcentaje').textContent = data.Porcentaje + '%';
                    
                    document.getElementById('viewModal').classList.remove('hidden');
                    document.getElementById('viewModal').classList.add('flex');
                }
            })
            .catch(error => {
                console.error('Error al cargar actividad:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo cargar la actividad'
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
    function confirmDelete(id) {
        currentActividadId = id;
        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('deleteModal').classList.add('flex');
    }

    // Eliminar actividad
    function deleteActividad() {
        axios.delete(`/atadores/catalogos/actividades/${currentActividadId}`)
            .then(response => {
                if (response.data.success) {
                    // Remover fila seleccionada
                    if (selectedRow) {
                        selectedRow.remove();
                        selectedRow = null;
                        currentActividadId = null;
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
                    text: error.response?.data?.message || 'No se pudo eliminar la actividad'
                });
            });
    }

    // Manejar envío del formulario
    function handleSubmit(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const data = {
            ActividadId: formData.get('ActividadId'),
            Porcentaje: formData.get('Porcentaje')
        };
        
        const originalId = formData.get('original_id');
        const isEdit = originalId !== '';
        
        const url = isEdit 
            ? `/atadores/catalogos/actividades/${originalId}`
            : '/atadores/catalogos/actividades';
        
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
                    text: error.response?.data?.message || 'No se pudo guardar la actividad'
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
