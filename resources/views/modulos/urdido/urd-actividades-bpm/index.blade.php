@extends('layouts.app')

@section('title', 'Actividades BPM Urdido')
@section('page-title')
Actividades BPM Urdido
@endsection

@section('navbar-right')
    <div class="flex items-center gap-2">

            <x-navbar.button-create
            module="Actividades BPM Urdido"
            onclick="openUrdModal('createModal')"
            id="btn-nuevo"
            title="Nueva Actividad"
             />

        <!-- Botón Editar -->
        <x-navbar.button-edit
        module="Actividades BPM Urdido"
        onclick="handleTopEdit()"
            id="btn-top-edit"
            title="Editar Actividad"
         />

        <!-- Botón Eliminar -->
        <x-navbar.button-delete
        module="Actividades BPM Urdido"
            onclick="handleTopDelete()"
            id="btn-top-delete"
            title="Eliminar Actividad"
         />
    </div>
@endsection

@section('content')
<div class="container mx-auto px-4 py-6">
    @if($errors->any())
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                html: '<ul class="text-left list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>',
                confirmButtonText: 'Aceptar'
            });
        </script>
    @endif
    @if(session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: '{{ session('success') }}',
                confirmButtonText: 'Aceptar',
                timer: 3000
            });
        </script>
    @endif

    <!-- Tabla de Actividades -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-blue-600 text-white sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold w-24">Orden</th>
                        <th class="px-4 py-3 text-left font-semibold">Actividad</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr class="border-b border-gray-200 hover:bg-blue-50 transition-colors duration-150 cursor-pointer"
                            data-key="{{ $item->Id }}"
                            data-orden="{{ $item->Orden ?? '' }}"
                            data-actividad="{{ e($item->Actividad) }}"
                            onclick="selectRow(this)"
                            aria-selected="false">
                            <td class="px-4 py-3 align-middle font-medium text-gray-700">
                                {{ $item->Orden ?? '-' }}
                            </td>
                            <td class="px-4 py-3 align-middle text-gray-800">
                                {{ $item->Actividad }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-8 text-center text-gray-500">
                                <i class="fa-solid fa-inbox text-4xl mb-2 text-gray-300"></i>
                                <p class="text-lg">No se encontraron actividades</p>
                                @if($q)
                                    <p class="text-sm mt-2">Intenta con otro término de búsqueda</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        @if($items->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
                {{ $items->links() }}
            </div>
        @endif
    </div>

    <!-- Formulario oculto para eliminación -->
    <form id="globalDeleteForm" action="#" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    <!-- Modal para Nueva Actividad -->
    <div id="createModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg mx-4 transform transition-all">
            <div class="bg-green-600 text-white px-6 py-4 rounded-t-lg">
                <h2 class="text-xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-plus-circle"></i>
                    Nueva Actividad BPM
                </h2>
            </div>

            <form action="{{ route('urd-actividades-bpm.store') }}" method="POST" class="p-6">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Orden <span class="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <input
                            type="number"
                            name="Orden"
                            min="1"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                            placeholder="Ej: 1, 2, 3...">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Actividad <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="Actividad"
                            maxlength="100"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                            placeholder="Nombre de la actividad">
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                    <button
                        type="button"
                        onclick="closeUrdModal('createModal')"
                        class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition font-medium">
                        <i class="fa-solid fa-times mr-1"></i> Cancelar
                    </button>
                    <button
                        type="submit"
                        class="px-5 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                        <i class="fa-solid fa-check mr-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Editar Actividad -->
    <div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg mx-4 transform transition-all">
            <div class="bg-yellow-600 text-white px-6 py-4 rounded-t-lg">
                <h2 class="text-xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-edit"></i>
                    Editar Actividad BPM
                </h2>
            </div>

            <form id="editForm" action="" method="POST" class="p-6">
                @csrf
                @method('PUT')
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Orden <span class="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <input
                            type="number"
                            id="editOrden"
                            name="Orden"
                            min="1"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition"
                            placeholder="Ej: 1, 2, 3...">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Actividad <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="editActividad"
                            name="Actividad"
                            maxlength="100"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition"
                            placeholder="Nombre de la actividad">
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                    <button
                        type="button"
                        onclick="closeUrdModal('editModal')"
                        class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition font-medium">
                        <i class="fa-solid fa-times mr-1"></i> Cancelar
                    </button>
                    <button
                        type="submit"
                        class="px-5 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition font-medium">
                        <i class="fa-solid fa-save mr-1"></i> Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Estilos para filas seleccionadas */
    tbody tr {
        transition: all 0.15s ease;
    }

    tbody tr:hover {
        background-color: #eff6ff !important;
    }

    tbody tr[aria-selected="true"] {
        background-color: #dbeafe !important;
        box-shadow: inset 0 0 0 2px rgba(59, 130, 246, 0.5);
    }

    tbody tr[aria-selected="true"] td:first-child {
        border-left: 4px solid #3b82f6;
    }

    /* Animación para modales */
    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    #createModal > div,
    #editModal > div {
        animation: modalFadeIn 0.2s ease-out;
    }
</style>
<script>
    const updateUrl = '{{ route("urd-actividades-bpm.update", ["urdActividadesBpm" => "PLACEHOLDER"]) }}';
    const destroyUrl = '{{ route("urd-actividades-bpm.destroy", ["urdActividadesBpm" => "PLACEHOLDER"]) }}';

    let selectedRow = null;
    let selectedKey = null;

    // Actualizar estado de botones superiores
    function updateTopButtonsState() {
        const btnEdit = document.getElementById('btn-top-edit');
        const btnDelete = document.getElementById('btn-top-delete');
        const hasSelection = !!selectedKey;

        [btnEdit, btnDelete].forEach(btn => {
            if (!btn) return;
            // Usar la propiedad disabled para mayor consistencia
            btn.disabled = !hasSelection;
        });
    }

    // Limpiar selección
    function clearSelection() {
        if (selectedRow) {
            selectedRow.setAttribute('aria-selected', 'false');
        }
        selectedRow = null;
        selectedKey = null;
        updateTopButtonsState();
    }

    // Seleccionar fila
    function selectRow(row) {
        if (selectedRow === row) {
            clearSelection();
            return;
        }

        if (selectedRow) {
            selectedRow.setAttribute('aria-selected', 'false');
        }

        selectedRow = row;
        selectedKey = row.dataset.key || null;
        row.setAttribute('aria-selected', 'true');
        updateTopButtonsState();
    }

    // Editar desde botón superior
    function handleTopEdit() {
        if (!selectedRow || !selectedKey) return;
        const orden = selectedRow.dataset.orden || '';
        const actividad = selectedRow.dataset.actividad || '';
        openEditModal(selectedKey, orden, actividad);
    }

    // Eliminar desde botón superior
    function handleTopDelete() {
        if (!selectedKey) return;
        deleteActivity(selectedKey);
    }

    // Abrir modal
    function openUrdModal(modalId) {
        document.getElementById(modalId).classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    // Cerrar modal
    function closeUrdModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    // Abrir modal de edición
    function openEditModal(key, orden, actividad) {
        document.getElementById('editOrden').value = orden || '';
        document.getElementById('editActividad').value = actividad || '';
        document.getElementById('editForm').action = updateUrl.replace('PLACEHOLDER', encodeURIComponent(key));
        openUrdModal('editModal');
    }

    // Abrir modal de edición directamente (desde botón de fila)
    function openEditModalDirect(key, orden, actividad) {
        openEditModal(key, orden || '', actividad);
    }

    // Eliminar actividad
    function deleteActivity(key) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fa-solid fa-trash mr-1"></i> Sí, eliminar',
            cancelButtonText: '<i class="fa-solid fa-times mr-1"></i> Cancelar',
            reverseButtons: true,
            customClass: {
                confirmButton: 'px-4 py-2 rounded-lg font-medium',
                cancelButton: 'px-4 py-2 rounded-lg font-medium'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('globalDeleteForm');
                form.action = destroyUrl.replace('PLACEHOLDER', encodeURIComponent(key));
                form.submit();
            }
        });
    }

    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        if (event.target.id === 'createModal' || event.target.id === 'editModal') {
            closeUrdModal(event.target.id);
        }
    }

    // Cerrar modal con tecla ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeUrdModal('createModal');
            closeUrdModal('editModal');
        }
    });

    // Inicialización: vincular eventos a los botones del navbar
    document.addEventListener('DOMContentLoaded', function() {
        // Botón Nuevo/Crear
        const btnNuevo = document.getElementById('btn-nuevo');
        if (btnNuevo) {
            btnNuevo.addEventListener('click', function(e) {
                e.preventDefault();
                openUrdModal('createModal');
            });
        }

        // Botón Editar
        const btnEdit = document.getElementById('btn-top-edit');
        if (btnEdit) {
            btnEdit.addEventListener('click', function(e) {
                e.preventDefault();
                handleTopEdit();
            });
        }

        // Botón Eliminar
        const btnDelete = document.getElementById('btn-top-delete');
        if (btnDelete) {
            btnDelete.addEventListener('click', function(e) {
                e.preventDefault();
                handleTopDelete();
            });
        }

        updateTopButtonsState();
    });
</script>
@endsection
