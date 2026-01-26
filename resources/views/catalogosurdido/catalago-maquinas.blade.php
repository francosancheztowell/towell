@extends('layouts.app')

@section('page-title', 'Catálogo de Máquinas')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create
        onclick="openCreateModal()"
        title="Nueva Máquina"
        module="Catalogos Maquinas"
        />
        <x-navbar.button-edit
        id="btnEdit"
        onclick="editSelected()"
        title="Editar Máquina"
        :disabled="true"
        module="Catalogos Maquinas"
        />
        <x-navbar.button-delete
        id="btnDelete"
        onclick="deleteSelected()"
        title="Eliminar Máquina"
        :disabled="true"
        hoverBg="hover:bg-red-200"
        module="Catalogos Maquinas"
        />
        <x-buttons.catalog-actions route="maquinas" :showFilters="true" />
    </div>
@endsection

@section('content')
    <div class="container">
    @if ($noResults ?? false)
        <div class="alert alert-warning text-center">No se encontraron resultados con la información proporcionada.</div>
    @endif

    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-y-auto h-[640px] scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-blue-500 border-b-2 text-white z-20">
                    <tr>
                        <th class="py-1 px-2 font-bold tracking-wider text-center">Máquina ID</th>
                        <th class="py-1 px-2 font-bold tracking-wider text-center">Nombre</th>
                        <th class="py-1 px-2 font-bold tracking-wider text-center">Departamento</th>
                    </tr>
                </thead>
                <tbody id="maquinas-body" class="bg-white text-black">
                    @forelse ($maquinas as $maquina)
                        @php $uid = $maquina->MaquinaId ?? uniqid(); @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            onclick="selectRow(this, '{{ $maquina->MaquinaId }}')"
                            data-uid="{{ $uid }}"
                            data-maquina-id="{{ $maquina->MaquinaId }}"
                            data-nombre="{{ $maquina->Nombre ?? '' }}"
                            data-departamento="{{ $maquina->Departamento ?? '' }}"
                            data-id="{{ $maquina->MaquinaId }}">
                            <td class="py-2 px-4 ">{{ $maquina->MaquinaId }}</td>
                            <td class="py-2 px-4 ">{{ $maquina->Nombre ?? 'N/A' }}</td>
                            <td class="py-2 px-4 ">{{ $maquina->Departamento ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-4 px-4 text-center text-gray-500">No hay máquinas registradas</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>

    <!-- Modal Crear/Editar Máquina -->
    <div id="formModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
            <div class="flex justify-between items-center border-b p-4 bg-blue-500 rounded-t-lg">
                <h2 class="text-xl font-bold text-white" id="formModalTitle">Nueva Máquina</h2>
                <button onclick="closeFormModal()" class="text-white hover:text-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
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
                            <input type="text" id="MaquinaId" name="MaquinaId" required maxlength="50"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 transition-all duration-200"
                                placeholder="Ej: MC Coy 1, MC Coy 2">
                        </div>
                        <div>
                            <label for="Nombre" class="block text-sm font-medium text-gray-700 mb-1">
                                Nombre
                            </label>
                            <input type="text" id="Nombre" name="Nombre" maxlength="100"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 transition-all duration-200"
                                placeholder="Ej: MC Coy 1">
                        </div>
                        <div>
                            <label for="Departamento" class="block text-sm font-medium text-gray-700 mb-1">
                                Departamento
                            </label>
                            <input type="text" id="Departamento" name="Departamento" maxlength="50"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 transition-all duration-200"
                                placeholder="Ej: Urdido">
                        </div>
                    </div>
                </div>

                <div class="border-t p-4 flex justify-end gap-2 bg-gray-50 rounded-b-lg">
                    <button type="button" onclick="closeFormModal()"
                        class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Confirmar Eliminación -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="flex justify-between items-center border-b p-4 bg-red-500 rounded-t-lg">
                <h2 class="text-xl font-bold text-white">Confirmar Eliminación</h2>
                <button onclick="closeDeleteModal()" class="text-white hover:text-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6">
                <div class="flex items-center gap-4 mb-4">
                    <div class="shrink-0">
                        <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-gray-700 font-medium">¿Está seguro que desea eliminar esta máquina?</p>
                        <p class="text-gray-500 text-sm mt-1">Esta acción no se puede deshacer.</p>
                    </div>
                </div>
            </div>
            <div class="border-t p-4 flex justify-end gap-2 bg-gray-50 rounded-b-lg">
                <button onclick="closeDeleteModal()"
                    class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors">
                    Cancelar
                </button>
                <button onclick="deleteMaquina()"
                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Eliminar
                </button>
            </div>
        </div>
    </div>

<style>
  .scrollbar-thin { scrollbar-width: thin; }
  .scrollbar-thin::-webkit-scrollbar { width: 8px; }
  .scrollbar-thumb-gray-400::-webkit-scrollbar-thumb { background-color: #9ca3af; border-radius: 4px; }
  .scrollbar-track-gray-100::-webkit-scrollbar-track { background-color: #f3f4f6; }
  .scrollbar-thin::-webkit-scrollbar-thumb:hover { background-color: #6b7280; }
</style>

    <script>
    // Variables globales
    let currentMaquinaId = null;
    let selectedRow = null;

    // Seleccionar fila de la tabla
    function selectRow(row, maquinaId) {
        // Deseleccionar fila anterior
        if (selectedRow) {
            selectedRow.classList.remove('bg-blue-100', 'border-l-4', 'border-blue-500');
        }

        // Si es la misma fila, deseleccionar
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
        if (currentMaquinaId && selectedRow) {
            openEditModal(currentMaquinaId);
        }
    }

    // Eliminar registro seleccionado desde navbar
    function deleteSelected() {
        if (currentMaquinaId) {
            openDeleteModal();
        }
    }

    // Abrir modal de creación
    function openCreateModal() {
        document.getElementById('formModalTitle').textContent = 'Nueva Máquina';
        document.getElementById('maquinaForm').reset();
        document.getElementById('original_maquinaid').value = '';
        document.getElementById('formModal').classList.remove('hidden');
        document.getElementById('formModal').classList.add('flex');
        document.getElementById('MaquinaId').focus();
    }

    // Abrir modal de edición
    function openEditModal(maquinaId) {
        if (!selectedRow) return;

        document.getElementById('formModalTitle').textContent = 'Editar Máquina';
        document.getElementById('original_maquinaid').value = maquinaId;
        document.getElementById('MaquinaId').value = selectedRow.dataset.maquinaId || '';
        document.getElementById('Nombre').value = selectedRow.dataset.nombre || '';
        document.getElementById('Departamento').value = selectedRow.dataset.departamento || '';

        document.getElementById('formModal').classList.remove('hidden');
        document.getElementById('formModal').classList.add('flex');
    }

    // Cerrar modal de formulario
    function closeFormModal() {
        document.getElementById('formModal').classList.add('hidden');
        document.getElementById('formModal').classList.remove('flex');
    }

    // Abrir modal de eliminación
    function openDeleteModal() {
        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('deleteModal').classList.add('flex');
    }

    // Cerrar modal de eliminación
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        document.getElementById('deleteModal').classList.remove('flex');
    }

    // Manejar envío del formulario
    function handleSubmit(event) {
        event.preventDefault();

        const originalMaquinaId = document.getElementById('original_maquinaid').value;
        const isEdit = originalMaquinaId !== '';

        const data = {
            MaquinaId: document.getElementById('MaquinaId').value.trim(),
            Nombre: document.getElementById('Nombre').value.trim() || null,
            Departamento: document.getElementById('Departamento').value.trim() || null
        };

        // Validación básica
        if (!data.MaquinaId) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'El Máquina ID es requerido'
            });
            return;
        }

        const url = isEdit
            ? `/urdido/catalogo-maquinas/${encodeURIComponent(originalMaquinaId)}`
            : '/urdido/catalogo-maquinas';

        const method = isEdit ? 'put' : 'post';

        axios({
            method: method,
            url: url,
            data: data,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (response.data.success) {
                Swal.fire({
                    icon: 'success',
                    title: isEdit ? '¡Actualizado!' : '¡Creado!',
                    text: response.data.message,
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    location.reload();
                });
            }
        })
        .catch(error => {
            console.error('Error al guardar:', error);
            let errorMessage = 'No se pudo guardar la máquina';

            if (error.response?.data?.message) {
                if (typeof error.response.data.message === 'object') {
                    errorMessage = Object.values(error.response.data.message).flat().join(', ');
                } else {
                    errorMessage = error.response.data.message;
                }
            }

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage
            });
        });
    }

    // Eliminar máquina
    function deleteMaquina() {
        if (!currentMaquinaId) return;

        axios.delete(`/urdido/catalogo-maquinas/${encodeURIComponent(currentMaquinaId)}`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (response.data.success) {
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

    // Cerrar modales al hacer clic fuera
    window.onclick = function(event) {
        const formModal = document.getElementById('formModal');
        const deleteModal = document.getElementById('deleteModal');

        if (event.target === formModal) {
            closeFormModal();
        }
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
    }

    // Cerrar modales con tecla Escape
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeFormModal();
            closeDeleteModal();
        }
    });

    // Funciones globales para el componente de filtros
    window.filtrarMaquinas = function() {
        Swal.fire({
            title: 'Filtrar Máquinas',
            html: `
                <div class="grid grid-cols-1 gap-3 text-left text-sm">
                    <div>
                        <label class="block text-xs font-medium mb-1">Máquina ID</label>
                        <input id="filter-maquina-id" class="swal2-input" placeholder="Buscar por Máquina ID">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Nombre</label>
                        <input id="filter-nombre" class="swal2-input" placeholder="Buscar por Nombre">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Departamento</label>
                        <input id="filter-departamento" class="swal2-input" placeholder="Buscar por Departamento">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Filtrar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3b82f6',
            preConfirm: () => {
                const params = new URLSearchParams();
                const maquinaId = document.getElementById('filter-maquina-id').value.trim();
                const nombre = document.getElementById('filter-nombre').value.trim();
                const departamento = document.getElementById('filter-departamento').value.trim();

                if (maquinaId) params.append('maquina_id', maquinaId);
                if (nombre) params.append('nombre', nombre);
                if (departamento) params.append('departamento', departamento);

                window.location.href = `${window.location.pathname}?${params.toString()}`;
            }
        });
    };

    window.limpiarFiltrosMaquinas = function() {
        window.location.href = window.location.pathname;
    };

    // Inicializar botones como deshabilitados
    document.addEventListener('DOMContentLoaded', function() {
        disableButtons();
    });
    </script>
@endsection

