@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Encabezado -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Catálogo de Máquinas</h1>
        <p class="text-gray-600 mt-2">Gestión de máquinas atadoras</p>
    </div>

    <!-- Barra de acciones -->
    <div class="flex justify-between items-center mb-6">
        <div class="flex gap-2">
            <!-- Botón Nuevo -->
            <button onclick="openCreateModal()" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg shadow-md transition duration-200 flex items-center gap-2">
                <i class="fas fa-plus"></i>
                Nueva Máquina
            </button>
            
            <!-- Botón Refrescar -->
            <button onclick="refreshTable()" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg shadow-md transition duration-200 flex items-center gap-2">
                <i class="fas fa-sync-alt"></i>
                Refrescar
            </button>
        </div>

        <!-- Buscador -->
        <div class="flex gap-2">
            <input type="text" id="searchInput" placeholder="Buscar máquina..." 
                class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-64">
            <button onclick="searchMaquinas()" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition duration-200">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>

    <!-- Tabla de máquinas -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" id="maquinasTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marca</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modelo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ubicación</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <!-- Datos de ejemplo - se llenarán dinámicamente con backend -->
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">1</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">MAQ-001</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Atadora A1</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">ITEMA</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">R9500</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Planta 1 - Sección A</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                Operativa
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">MAQ-002</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Atadora B2</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">PICANOL</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">OptiMax</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Planta 1 - Sección B</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                Mantenimiento
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

<!-- Modal Ver Máquina -->
<div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4">
        <div class="flex justify-between items-center border-b p-4">
            <h2 class="text-xl font-bold text-gray-800">Detalles de la Máquina</h2>
            <button onclick="closeViewModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ID</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_id">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_codigo">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_estado">-</p>
                </div>
                <div class="col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_nombre">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Marca</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_marca">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Modelo</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_modelo">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Año</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_anio">-</p>
                </div>
                <div class="col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ubicación</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded" id="view_ubicacion">-</p>
                </div>
                <div class="col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Especificaciones</label>
                    <p class="text-gray-900 bg-gray-50 p-2 rounded min-h-20" id="view_especificaciones">-</p>
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
<div id="formModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4 my-8">
        <div class="flex justify-between items-center border-b p-4">
            <h2 class="text-xl font-bold text-gray-800" id="formModalTitle">Nueva Máquina</h2>
            <button onclick="closeFormModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <form id="maquinaForm" onsubmit="handleSubmit(event)">
            <div class="p-6">
                <input type="hidden" id="maquina_id" name="id">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="codigo" class="block text-sm font-medium text-gray-700 mb-1">
                            Código <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="codigo" name="codigo" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Ej: MAQ-001">
                    </div>
                    
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="nombre" name="nombre" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Nombre de la máquina">
                    </div>
                    
                    <div>
                        <label for="marca" class="block text-sm font-medium text-gray-700 mb-1">
                            Marca <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="marca" name="marca" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Marca de la máquina">
                    </div>
                    
                    <div>
                        <label for="modelo" class="block text-sm font-medium text-gray-700 mb-1">
                            Modelo <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="modelo" name="modelo" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Modelo">
                    </div>
                    
                    <div>
                        <label for="anio" class="block text-sm font-medium text-gray-700 mb-1">
                            Año
                        </label>
                        <input type="number" id="anio" name="anio" min="1900" max="2100"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Año de fabricación">
                    </div>
                    
                    <div>
                        <label for="serie" class="block text-sm font-medium text-gray-700 mb-1">
                            Número de Serie
                        </label>
                        <input type="text" id="serie" name="serie"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Número de serie">
                    </div>
                    
                    <div class="col-span-2">
                        <label for="ubicacion" class="block text-sm font-medium text-gray-700 mb-1">
                            Ubicación <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="ubicacion" name="ubicacion" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Ubicación física de la máquina">
                    </div>
                    
                    <div class="col-span-2">
                        <label for="especificaciones" class="block text-sm font-medium text-gray-700 mb-1">
                            Especificaciones Técnicas
                        </label>
                        <textarea id="especificaciones" name="especificaciones" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Detalles técnicos de la máquina"></textarea>
                    </div>
                    
                    <div>
                        <label for="capacidad" class="block text-sm font-medium text-gray-700 mb-1">
                            Capacidad
                        </label>
                        <input type="text" id="capacidad" name="capacidad"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Capacidad de producción">
                    </div>
                    
                    <div>
                        <label for="estado_operativo" class="block text-sm font-medium text-gray-700 mb-1">
                            Estado Operativo <span class="text-red-500">*</span>
                        </label>
                        <select id="estado_operativo" name="estado_operativo" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="operativa">Operativa</option>
                            <option value="mantenimiento">Mantenimiento</option>
                            <option value="reparacion">En Reparación</option>
                            <option value="inactiva">Inactiva</option>
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

    // Abrir modal de creación
    function openCreateModal() {
        document.getElementById('formModalTitle').textContent = 'Nueva Máquina';
        document.getElementById('maquinaForm').reset();
        document.getElementById('maquina_id').value = '';
        document.getElementById('formModal').classList.remove('hidden');
        document.getElementById('formModal').classList.add('flex');
    }

    // Abrir modal de edición
    function openEditModal(id) {
        currentMaquinaId = id;
        document.getElementById('formModalTitle').textContent = 'Editar Máquina';
        
        // Aquí cargarías los datos desde el backend
        // Por ahora, datos de ejemplo
        document.getElementById('maquina_id').value = id;
        document.getElementById('codigo').value = 'MAQ-00' + id;
        document.getElementById('nombre').value = id === 1 ? 'Atadora A1' : 'Atadora B2';
        document.getElementById('marca').value = id === 1 ? 'ITEMA' : 'PICANOL';
        document.getElementById('modelo').value = id === 1 ? 'R9500' : 'OptiMax';
        document.getElementById('anio').value = id === 1 ? '2020' : '2019';
        document.getElementById('serie').value = 'SN' + (id === 1 ? '123456' : '789012');
        document.getElementById('ubicacion').value = id === 1 ? 'Planta 1 - Sección A' : 'Planta 1 - Sección B';
        document.getElementById('especificaciones').value = 'Especificaciones técnicas de la máquina';
        document.getElementById('capacidad').value = id === 1 ? '1000 u/h' : '1200 u/h';
        document.getElementById('estado_operativo').value = id === 1 ? 'operativa' : 'mantenimiento';
        
        document.getElementById('formModal').classList.remove('hidden');
        document.getElementById('formModal').classList.add('flex');
    }

    // Abrir modal de vista
    function openViewModal(id) {
        // Aquí cargarías los datos desde el backend
        // Por ahora, datos de ejemplo
        document.getElementById('view_id').textContent = id;
        document.getElementById('view_codigo').textContent = 'MAQ-00' + id;
        document.getElementById('view_nombre').textContent = id === 1 ? 'Atadora A1' : 'Atadora B2';
        document.getElementById('view_marca').textContent = id === 1 ? 'ITEMA' : 'PICANOL';
        document.getElementById('view_modelo').textContent = id === 1 ? 'R9500' : 'OptiMax';
        document.getElementById('view_anio').textContent = id === 1 ? '2020' : '2019';
        document.getElementById('view_ubicacion').textContent = id === 1 ? 'Planta 1 - Sección A' : 'Planta 1 - Sección B';
        document.getElementById('view_especificaciones').textContent = 'Especificaciones técnicas de la máquina';
        document.getElementById('view_estado').textContent = id === 1 ? 'Operativa' : 'Mantenimiento';
        
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
        currentMaquinaId = null;
    }

    // Confirmar eliminación
    function confirmDelete(id) {
        currentMaquinaId = id;
        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('deleteModal').classList.add('flex');
    }

    // Eliminar máquina
    function deleteMaquina() {
        console.log('Eliminando máquina:', currentMaquinaId);
        // Aquí implementarías la llamada al backend para eliminar
        // Por ahora solo cerramos el modal
        closeDeleteModal();
        // Mostrar mensaje de éxito (implementar con librería de notificaciones)
        alert('Máquina eliminada exitosamente');
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
        alert(data.id ? 'Máquina actualizada exitosamente' : 'Máquina creada exitosamente');
    }

    // Buscar máquinas
    function searchMaquinas() {
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
