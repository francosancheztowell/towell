@extends('layouts.app')

@section('title', 'Catálogo de Núcleos')
@section('page-title')
Catálogo de Núcleos
@endsection

@section('navbar-right')
    <div class="flex items-center gap-2">

        <!-- Botón Agregar -->
        <x-navbar.button-create
        onclick="openCreateModal()"
        title="Nuevo Núcleo"
        module="Catálogo de Núcleos"
        />

        <!-- Botón Editar -->
        <x-navbar.button-edit
        id="btn-top-edit"
        onclick="handleTopEdit()"
        module="Catálogo de Núcleos"
        />

        <!-- Botón Eliminar -->
        <x-navbar.button-delete
        id="btn-top-delete"
        onclick="handleTopDelete()"
        module="Catálogo de Núcleos"
        />
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
    @if(session('error'))
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '{{ session('error') }}',
                confirmButtonText: 'Aceptar'
            });
        </script>
    @endif


    <!-- Tabla de Núcleos -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden w-full">
        <div class="overflow-x-auto w-full">
            <table class="w-full text-sm">
                <thead class=" bg-blue-500 text-white sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Salón</th>
                        <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr class="border-b border-gray-200 hover:bg-blue-50 transition-colors duration-150 cursor-pointer"
                            data-key="{{ $item->Id }}"
                            data-salon="{{ e($item->Salon) }}"
                            data-nombre="{{ e($item->Nombre) }}"
                            onclick="selectRow(this)"
                            aria-selected="false">
                            <td class="px-4 py-3 align-middle font-medium text-gray-700 select-row-text">
                                {{ $item->Salon }}
                            </td>
                            <td class="px-4 py-3 align-middle text-gray-800 select-row-text">
                                {{ $item->Nombre }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-8 text-center text-gray-500">
                                <i class="fa-solid fa-inbox text-4xl mb-2 text-gray-300"></i>
                                <p class="text-lg">No se encontraron núcleos</p>
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

    <!-- Formulario oculto para crear -->
    <form id="createForm" action="{{ route('urd-eng-nucleos.store') }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" name="Salon" id="createSalon">
        <input type="hidden" name="Nombre" id="createNombre">
    </form>

    <!-- Formulario oculto para editar -->
    <form id="editForm" action="#" method="POST" class="hidden">
        @csrf
        @method('PUT')
        <input type="hidden" name="Salon" id="editSalon">
        <input type="hidden" name="Nombre" id="editNombre">
    </form>
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
        background-color: #3b82f6 !important;
    }

    tbody tr[aria-selected="true"] .select-row-text {
        color: white !important;
    }


    /* Estilos para inputs de SweetAlert */
    .swal2-input {
        width: 100%;
        margin: 0.5rem 0;
    }

    .swal2-label {
        display: block;
        text-align: left;
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.25rem;
    }
</style>

<script>
    const updateUrl = '{{ route("urd-eng-nucleos.update", ["urdEngNucleo" => "PLACEHOLDER"]) }}';
    const destroyUrl = '{{ route("urd-eng-nucleos.destroy", ["urdEngNucleo" => "PLACEHOLDER"]) }}';

    let selectedRow = null;
    let selectedKey = null;

    // Actualizar estado de botones superiores
    function updateTopButtonsState() {
        const btnEdit = document.getElementById('btn-top-edit');
        const btnDelete = document.getElementById('btn-top-delete');
        const hasSelection = !!selectedKey;

        [btnEdit, btnDelete].forEach(btn => {
            if (!btn) return;
            if (hasSelection) {
                btn.removeAttribute('disabled');
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                btn.setAttribute('disabled', 'disabled');
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            }
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

    // Abrir modal de creación con SweetAlert
    function openCreateModal() {
        Swal.fire({
            title: 'Nuevo Núcleo',
            html: `
                <div class="text-left">
                    <label class="swal2-label">
                        Salón <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="swal-salon"
                        class="swal2-input"
                        required
                    >
                        <option value="">-- Seleccione un salón --</option>
                        <option value="JACQUARD">JACQUARD</option>
                        <option value="SMIT">SMIT</option>
                        <option value="KARL MAYER">KARL MAYER</option>
                    </select>
                    <label class="swal2-label" style="margin-top: 1rem;">
                        Nombre <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="swal-nombre"
                        class="swal2-input"
                        placeholder="Nombre del núcleo"
                        maxlength="120"
                        required
                    >
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-check mr-1"></i> Guardar',
            cancelButtonText: '<i class="fa-solid fa-times mr-1"></i> Cancelar',
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            focusConfirm: false,
            allowOutsideClick: false,
            preConfirm: () => {
                const salon = document.getElementById('swal-salon').value.trim();
                const nombre = document.getElementById('swal-nombre').value.trim();

                if (!salon || !nombre) {
                    Swal.showValidationMessage('Todos los campos son requeridos');
                    return false;
                }

                if (nombre.length > 120) {
                    Swal.showValidationMessage('El nombre no puede tener más de 120 caracteres');
                    return false;
                }

                return { salon, nombre };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                // Enviar formulario
                document.getElementById('createSalon').value = result.value.salon;
                document.getElementById('createNombre').value = result.value.nombre;
                document.getElementById('createForm').submit();
            }
        });
    }

    // Abrir modal de edición con SweetAlert
    function openEditModal(key, salon, nombre) {
        Swal.fire({
            title: '<i class="fa-solid fa-edit text-yellow-600"></i> Editar Núcleo',
            html: `
                <div class="text-left">
                    <label class="swal2-label">
                        Salón <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="swal-edit-salon"
                        class="swal2-input"
                        required
                    >
                        <option value="">-- Seleccione un salón --</option>
                        <option value="JACQUARD" ${salon === 'JACQUARD' ? 'selected' : ''}>JACQUARD</option>
                        <option value="SMIT" ${salon === 'SMIT' ? 'selected' : ''}>SMIT</option>
                        <option value="KARL MAYER" ${salon === 'KARL MAYER' ? 'selected' : ''}>KARL MAYER</option>
                    </select>
                    <label class="swal2-label" style="margin-top: 1rem;">
                        Nombre <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="swal-edit-nombre"
                        class="swal2-input"
                        placeholder="Nombre del núcleo"
                        maxlength="120"
                        required
                        value="${nombre || ''}"
                    >
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-save mr-1"></i> Actualizar',
            cancelButtonText: '<i class="fa-solid fa-times mr-1"></i> Cancelar',
            confirmButtonColor: '#eab308',
            cancelButtonColor: '#6b7280',
            focusConfirm: false,
            allowOutsideClick: false,
            preConfirm: () => {
                const salon = document.getElementById('swal-edit-salon').value.trim();
                const nombre = document.getElementById('swal-edit-nombre').value.trim();

                if (!salon || !nombre) {
                    Swal.showValidationMessage('Todos los campos son requeridos');
                    return false;
                }

                if (nombre.length > 120) {
                    Swal.showValidationMessage('El nombre no puede tener más de 120 caracteres');
                    return false;
                }

                return { salon, nombre };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                // Enviar formulario
                document.getElementById('editForm').action = updateUrl.replace('PLACEHOLDER', encodeURIComponent(key));
                document.getElementById('editSalon').value = result.value.salon;
                document.getElementById('editNombre').value = result.value.nombre;
                document.getElementById('editForm').submit();
            }
        });
    }

    // Editar desde botón superior
    function handleTopEdit() {
        if (!selectedRow || !selectedKey) return;
        const salon = selectedRow.dataset.salon || '';
        const nombre = selectedRow.dataset.nombre || '';
        openEditModal(selectedKey, salon, nombre);
    }

    // Eliminar desde botón superior
    function handleTopDelete() {
        if (!selectedKey) return;
        deleteNucleo(selectedKey);
    }

    // Eliminar núcleo
    function deleteNucleo(key) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fa-solid fa-trash mr-1"></i> Sí, eliminar',
            cancelButtonText: '<i class="fa-solid fa-times mr-1"></i> Cancelar',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('globalDeleteForm');
                form.action = destroyUrl.replace('PLACEHOLDER', encodeURIComponent(key));
                form.submit();
            }
        });
    }

    // Estado inicial
    updateTopButtonsState();
</script>
@endsection
