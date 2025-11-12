@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Encabezado -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Catálogo de Actividades</h1>
        <p class="text-gray-600 mt-2">Gestión de actividades de atadores</p>
    </div>

    <!-- Barra de acciones -->
    <div class="flex justify-between items-center mb-6">
        <div class="flex gap-2">
            <!-- Botón Nuevo -->
            <button onclick="openCreateModal()" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg shadow-md transition duration-200 flex items-center gap-2">
                <i class="fas fa-plus"></i>
                Nueva Actividad
            </button>
            
            <!-- Botón Refrescar -->
            <button onclick="refreshTable()" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg shadow-md transition duration-200 flex items-center gap-2">
                <i class="fas fa-sync-alt"></i>
                Refrescar
            </button>
        </div>

        <!-- Buscador -->
        <div class="flex gap-2">
            <input type="text" id="searchInput" placeholder="Buscar actividad..." 
                class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-64">
            <button onclick="searchActividades()" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition duration-200">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>

    <!-- Tabla de actividades -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" id="actividadesTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <!-- Datos de ejemplo - se llenarán dinámicamente con backend -->
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">1</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">ACT-001</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Montaje</td>
                        <td class="px-6 py-4 text-sm text-gray-500">Actividad de montaje de hilos</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                Activo
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                            <button onclick="openViewModal(1)" class="text-blue-600 hover:text-blue-900 mx-1" title="Ver">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="openEditModal(1)" class="text-yellow-600 hover:text-yellow-900 mx-1" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="confirmDelete(1)" class="text-red-600 hover:text-red-900 mx-1" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">ACT-002</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Anudado</td>
                        <td class="px-6 py-4 text-sm text-gray-500">Proceso de anudado</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                Activo
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                            <button onclick="openViewModal(2)" class="text-blue-600 hover:text-blue-900 mx-1" title="Ver">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="openEditModal(2)" class="text-yellow-600 hover:text-yellow-900 mx-1" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="confirmDelete(2)" class="text-red-600 hover:text-red-900 mx-1" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación -->
    <div class="mt-6 flex justify-between items-center">
        <div class="text-sm text-gray-700">
            Mostrando <span class="font-medium">1</span> a <span class="font-medium">2</span> de <span class="font-medium">2</span> resultados
        </div>
        <nav class="flex gap-1">
            <button class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 disabled:opacity-50" disabled>Anterior</button>
            <button class="px-3 py-1 bg-blue-500 text-white rounded">1</button>
            <button class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 disabled:opacity-50" disabled>Siguiente</button>
        </nav>
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
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ID</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_id">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_codigo">-</p>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_nombre">-</p>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_descripcion">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_estado">-</p>
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
                <input type="hidden" id="actividad_id" name="id">
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label for="codigo" class="block text-sm font-medium text-gray-700 mb-1">
                            Código <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="codigo" name="codigo" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Ej: ACT-001">
                    </div>
                    
                    <div class="col-span-2">
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="nombre" name="nombre" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Nombre de la actividad">
                    </div>
                    
                    <div class="col-span-2">
                        <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-1">
                            Descripción
                        </label>
                        <textarea id="descripcion" name="descripcion" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Descripción de la actividad"></textarea>
                    </div>
                    
                    <div class="col-span-2">
                        <label for="estado" class="block text-sm font-medium text-gray-700 mb-1">
                            Estado <span class="text-red-500">*</span>
                        </label>
                        <select id="estado" name="estado" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
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

    // Abrir modal de creación
    function openCreateModal() {
        document.getElementById('formModalTitle').textContent = 'Nueva Actividad';
        document.getElementById('actividadForm').reset();
        document.getElementById('actividad_id').value = '';
        document.getElementById('formModal').classList.remove('hidden');
        document.getElementById('formModal').classList.add('flex');
    }

    // Abrir modal de edición
    function openEditModal(id) {
        currentActividadId = id;
        document.getElementById('formModalTitle').textContent = 'Editar Actividad';
        
        // Aquí cargarías los datos desde el backend
        // Por ahora, datos de ejemplo
        document.getElementById('actividad_id').value = id;
        document.getElementById('codigo').value = 'ACT-00' + id;
        document.getElementById('nombre').value = id === 1 ? 'Montaje' : 'Anudado';
        document.getElementById('descripcion').value = id === 1 ? 'Actividad de montaje de hilos' : 'Proceso de anudado';
        document.getElementById('estado').value = '1';
        
        document.getElementById('formModal').classList.remove('hidden');
        document.getElementById('formModal').classList.add('flex');
    }

    // Abrir modal de vista
    function openViewModal(id) {
        // Aquí cargarías los datos desde el backend
        // Por ahora, datos de ejemplo
        document.getElementById('view_id').textContent = id;
        document.getElementById('view_codigo').textContent = 'ACT-00' + id;
        document.getElementById('view_nombre').textContent = id === 1 ? 'Montaje' : 'Anudado';
        document.getElementById('view_descripcion').textContent = id === 1 ? 'Actividad de montaje de hilos' : 'Proceso de anudado';
        document.getElementById('view_estado').textContent = 'Activo';
        
        document.getElementById('viewModal').classList.remove('hidden');
        document.getElementById('viewModal').classList.add('flex');
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
        currentActividadId = null;
    }

    // Confirmar eliminación
    function confirmDelete(id) {
        currentActividadId = id;
        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('deleteModal').classList.add('flex');
    }

    // Eliminar actividad
    function deleteActividad() {
        console.log('Eliminando actividad:', currentActividadId);
        // Aquí implementarías la llamada al backend para eliminar
        // Por ahora solo cerramos el modal
        closeDeleteModal();
        // Mostrar mensaje de éxito (implementar con librería de notificaciones)
        alert('Actividad eliminada exitosamente');
    }

    // Manejar envío del formulario
    function handleSubmit(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData);
        
        console.log('Datos a guardar:', data);
        
        // Aquí implementarías la llamada al backend para guardar
        // Por ahora solo cerramos el modal
        closeFormModal();
        
        // Mostrar mensaje de éxito
        alert(data.id ? 'Actividad actualizada exitosamente' : 'Actividad creada exitosamente');
    }

    // Buscar actividades
    function searchActividades() {
        const searchTerm = document.getElementById('searchInput').value;
        console.log('Buscando:', searchTerm);
        // Implementar búsqueda con backend
    }

    // Refrescar tabla
    function refreshTable() {
        console.log('Refrescando tabla...');
        // Implementar recarga de datos
        location.reload();
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
</script>
@endpush
