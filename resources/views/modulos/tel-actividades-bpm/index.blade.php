@extends('layouts.app')

@section('title', 'Actividades Tejedores BPM')
@section('page-title')
Actividades Tejedores · BPM
@endsection

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create
            onclick="openTelModal('createModal')"
            id="btn-nuevo"
            title="Nueva Actividad"
            module="Actividades Tejedores" />

        <x-navbar.button-edit
            onclick="handleTopEdit()"
            id="btn-top-edit"
            title="Editar Actividad"
            module="Actividades Tejedores" />

        <x-navbar.button-delete
            onclick="handleTopDelete()"
            id="btn-top-delete"
            title="Eliminar Actividad"
            module="Actividades Tejedores" />
    </div>
@endsection

@section('content')
<div class="w-full px-4 py-6">
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
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="overflow-auto max-h-[75vh]">
            <table class="w-full text-sm">
                <thead class="bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                    <tr>
                        <th class="sticky top-0 z-10 bg-blue-500 px-6 py-4 text-left font-semibold text-base w-32">Orden</th>
                        <th class="sticky top-0 z-10 bg-blue-500 px-6 py-4 text-left font-semibold text-base">Actividad</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr class="border-b border-gray-200 hover:bg-blue-50 transition-colors duration-150 cursor-pointer"
                            data-key="{{ $item->Orden }}"
                            data-actividad="{{ e($item->Actividad) }}"
                            onclick="selectRow(this)"
                            aria-selected="false">
                            <td class="px-6 py-4 align-middle font-semibold text-gray-700 text-base">
                                {{ $item->Orden }}
                            </td>
                            <td class="px-6 py-4 align-middle text-gray-800 text-base">
                                {{ $item->Actividad }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-6 py-12 text-center text-gray-500">
                                <i class="fa-solid fa-inbox text-5xl mb-3 text-gray-300 block"></i>
                                <p class="text-lg font-medium">No se encontraron actividades</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Formulario oculto para eliminación -->
    <form id="globalDeleteForm" action="#" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    <!-- Modal para Nueva Actividad -->
    <div id="createModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 items-center justify-center p-4" onclick="if(event.target === this) closeTelModal('createModal')">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl transform transition-all animate-modalFadeIn" onclick="event.stopPropagation()">
            <div class="bg-gradient-to-r from-green-600 via-green-500 to-green-600 text-white px-8 py-5 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold flex items-center gap-3">
                        <i class="fa-solid fa-plus-circle text-2xl"></i>
                        Nueva Actividad BPM
                    </h2>
                    <button type="button" onclick="closeTelModal('createModal')" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-2 transition-colors">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <form action="{{ route('tel-actividades-bpm.store') }}" method="POST" class="p-8">
                @csrf
                <div class="space-y-6">
                    <div>
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                            <i class="fa-solid fa-tasks text-green-600"></i>
                            Actividad <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="Actividad"
                            maxlength="100"
                            required
                            autofocus
                            class="w-full px-4 py-3 text-base border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all shadow-sm hover:shadow-md"
                            placeholder="Ingresa el nombre de la actividad">
                        <p class="mt-2 text-xs text-gray-500">
                            <i class="fa-solid fa-info-circle mr-1"></i>Máximo 100 caracteres
                        </p>
                    </div>
                </div>

                <div class="flex justify-end gap-4 mt-8 pt-6 border-t border-gray-200">
                    <button
                        type="button"
                        onclick="closeTelModal('createModal')"
                        class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-all font-semibold shadow-sm hover:shadow-md flex items-center gap-2">
                        <i class="fa-solid fa-times"></i> Cancelar
                    </button>
                    <button
                        type="submit"
                        class="px-6 py-3 bg-gradient-to-r from-green-600 to-green-500 hover:from-green-700 hover:to-green-600 text-white rounded-lg transition-all font-semibold shadow-md hover:shadow-lg flex items-center gap-2">
                        <i class="fa-solid fa-check"></i> Guardar Actividad
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Editar Actividad -->
    <div id="editModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 items-center justify-center p-4" onclick="if(event.target === this) closeTelModal('editModal')">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl transform transition-all animate-modalFadeIn" onclick="event.stopPropagation()">
            <div class="bg-gradient-to-r from-yellow-600 via-yellow-500 to-yellow-600 text-white px-8 py-5 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold flex items-center gap-3">
                        <i class="fa-solid fa-edit text-2xl"></i>
                        Editar Actividad BPM
                    </h2>
                    <button type="button" onclick="closeTelModal('editModal')" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-2 transition-colors">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <form id="editForm" action="" method="POST" class="p-8">
                @csrf
                @method('PUT')
                <div class="space-y-6">
                    <div>
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                            <i class="fa-solid fa-tasks text-yellow-600"></i>
                            Actividad <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="editActividad"
                            name="Actividad"
                            maxlength="100"
                            required
                            autofocus
                            class="w-full px-4 py-3 text-base border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-all shadow-sm hover:shadow-md"
                            placeholder="Ingresa el nombre de la actividad">
                        <p class="mt-2 text-xs text-gray-500">
                            <i class="fa-solid fa-info-circle mr-1"></i>Máximo 100 caracteres
                        </p>
                    </div>
                </div>

                <div class="flex justify-end gap-4 mt-8 pt-6 border-t border-gray-200">
                    <button
                        type="button"
                        onclick="closeTelModal('editModal')"
                        class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-all font-semibold shadow-sm hover:shadow-md flex items-center gap-2">
                        <i class="fa-solid fa-times"></i> Cancelar
                    </button>
                    <button
                        type="submit"
                        class="px-6 py-3 bg-gradient-to-r from-yellow-600 to-yellow-500 hover:from-yellow-700 hover:to-yellow-600 text-white rounded-lg transition-all font-semibold shadow-md hover:shadow-lg flex items-center gap-2">
                        <i class="fa-solid fa-save"></i> Actualizar Actividad
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
            transform: scale(0.9) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .animate-modalFadeIn {
        animation: modalFadeIn 0.3s ease-out;
    }

    /* Mejorar scrollbar de la tabla */
    .overflow-auto::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .overflow-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .overflow-auto::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    .overflow-auto::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
</style>

<script>
    const updateUrl = '{{ route("tel-actividades-bpm.update", ["telActividadesBPM" => "PLACEHOLDER"]) }}';
    const destroyUrl = '{{ route("tel-actividades-bpm.destroy", ["telActividadesBPM" => "PLACEHOLDER"]) }}';

    let selectedRow = null;
    let selectedKey = null;

    // Actualizar estado de botones superiores
    function updateTopButtonsState() {
        const btnEdit = document.getElementById('btn-top-edit');
        const btnDelete = document.getElementById('btn-top-delete');
        const hasSelection = !!selectedKey;

        [btnEdit, btnDelete].forEach(btn => {
            if (!btn) return;
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
        const actividad = selectedRow.dataset.actividad || '';
        openEditModal(selectedKey, actividad);
    }

    // Eliminar desde botón superior
    function handleTopDelete() {
        if (!selectedKey) return;
        deleteActivity(selectedKey);
    }

    // Abrir modal
    function openTelModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
            // Focus en el primer input si existe
            setTimeout(() => {
                const firstInput = modal.querySelector('input[type="text"], input[type="number"], textarea, select');
                if (firstInput) firstInput.focus();
            }, 100);
        }
    }

    // Cerrar modal
    function closeTelModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
            // Reset form si es createModal
            if (modalId === 'createModal') {
                const form = modal.querySelector('form');
                if (form) form.reset();
            }
        }
    }

    // Abrir modal de edición
    function openEditModal(key, actividad) {
        document.getElementById('editActividad').value = actividad || '';
        document.getElementById('editForm').action = updateUrl.replace('PLACEHOLDER', encodeURIComponent(key));
        openTelModal('editModal');
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
            closeTelModal(event.target.id);
        }
    }

    // Cerrar modal con tecla ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeTelModal('createModal');
            closeTelModal('editModal');
        }
    });

    // Inicialización
    document.addEventListener('DOMContentLoaded', function() {
        updateTopButtonsState();
    });
</script>
@endsection
