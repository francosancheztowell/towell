@extends('layouts.app')

@section('title', 'Catálogo de Fallas')
@section('page-title')
Catálogo de Fallas
@endsection

@section('navbar-right')
    <div class="flex items-center gap-2 flex-wrap">
        <!-- Botón Filtro -->
        <button
            onclick="openFilterModal()"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors flex items-center gap-2"
            title="Filtrar">
            <i class="fa-solid fa-filter"></i>
            <span>Filtrar</span>
            @if($tipoFallaFilter || $departamentoFilter)
                <span class="bg-white text-blue-600 rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">
                    !
                </span>
            @endif
        </button>

        <div class="flex items-center gap-2">
            <x-navbar.button-create
                module="Catálogo de Fallas"
                onclick="openModal('createModal')"
                id="btn-nuevo"
                title="Nueva Falla"
                icon="fa-plus"
                iconColor="text-green-600"
                hoverBg="hover:bg-green-100" />

            <!-- Botón Editar -->
            <x-navbar.button-edit
                module="Catálogo de Fallas"
                onclick="handleTopEdit()"
                id="btn-top-edit"
                title="Editar Falla"
                :disabled="true"
                iconColor="text-yellow-500"
                hoverBg="hover:bg-yellow-100" />

            <!-- Botón Eliminar -->
            <x-navbar.button-delete
                module="Catálogo de Fallas"
                onclick="handleTopDelete()"
                id="btn-top-delete"
                title="Eliminar Falla"
                :disabled="true"
                iconColor="text-red-600"
                hoverBg="hover:bg-red-100" />
        </div>
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



    <!-- Tabla de Fallas -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-blue-600 text-white sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Tipo Falla</th>
                        <th class="px-4 py-3 text-left font-semibold">Departamento</th>
                        <th class="px-4 py-3 text-left font-semibold">Falla</th>
                        <th class="px-4 py-3 text-left font-semibold">Descripción</th>
                        <th class="px-4 py-3 text-left font-semibold">Abreviado</th>
                        <th class="px-4 py-3 text-left font-semibold">Sección</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr class="border-b border-gray-200 hover:bg-blue-50 transition-colors duration-150 cursor-pointer"
                            data-key="{{ $item->Id }}"
                            data-tipo-falla="{{ e($item->TipoFallaId) }}"
                            data-departamento="{{ e($item->Departamento) }}"
                            data-falla="{{ e($item->Falla) }}"
                            data-descripcion="{{ e($item->Descripcion ?? '') }}"
                            data-abreviado="{{ e($item->Abreviado ?? '') }}"
                            data-seccion="{{ e($item->Seccion ?? '') }}"
                            onclick="selectRow(this)"
                            aria-selected="false">
                            <td class="px-4 py-3 align-middle text-gray-800">
                                {{ $item->TipoFallaId }}
                            </td>
                            <td class="px-4 py-3 align-middle text-gray-800">
                                {{ $item->Departamento }}
                            </td>
                            <td class="px-4 py-3 align-middle text-gray-800 font-medium">
                                {{ $item->Falla }}
                            </td>
                            <td class="px-4 py-3 align-middle text-gray-600">
                                {{ $item->Descripcion ?? '-' }}
                            </td>
                            <td class="px-4 py-3 align-middle text-gray-600">
                                {{ $item->Abreviado ?? '-' }}
                            </td>
                            <td class="px-4 py-3 align-middle text-gray-600">
                                {{ $item->Seccion ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                <i class="fa-solid fa-inbox text-4xl mb-2 text-gray-300"></i>
                                <p class="text-lg">No se encontraron fallas</p>
                                @if($tipoFallaFilter || $departamentoFilter)
                                    <p class="text-sm mt-2">Intenta con otros filtros</p>
                                @endif
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

    <!-- Modal para Nueva Falla -->
    <div id="createModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-2xl mx-4 transform transition-all max-h-[90vh] overflow-y-auto">
            <div class="bg-green-600 text-white px-6 py-4 rounded-t-lg sticky top-0">
                <h2 class="text-xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-plus-circle"></i>
                    Nueva Falla
                </h2>
            </div>

            <form action="{{ route('mantenimiento.catalogos-fallas.store') }}" method="POST" class="p-6">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Tipo de Falla <span class="text-red-500">*</span>
                        </label>
                        <select
                            name="TipoFallaId"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            <option value="">Seleccione un tipo</option>
                            @foreach($tiposFalla as $tipo)
                                <option value="{{ $tipo }}" {{ old('TipoFallaId') === $tipo ? 'selected' : '' }}>
                                    {{ $tipo }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Departamento <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="Departamento"
                            value="{{ old('Departamento') }}"
                            required
                            maxlength="50"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                            placeholder="Ej: URDIDO, ENGOMADO, Tejido">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Falla <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="Falla"
                            value="{{ old('Falla') }}"
                            required
                            maxlength="100"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                            placeholder="Código o nombre de la falla">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Descripción
                        </label>
                        <textarea
                            name="Descripcion"
                            rows="3"
                            maxlength="255"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition resize-none"
                            placeholder="Descripción detallada de la falla">{{ old('Descripcion') }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Abreviado
                        </label>
                        <input
                            type="text"
                            name="Abreviado"
                            value="{{ old('Abreviado') }}"
                            maxlength="50"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                            placeholder="Ej: AMA TRA, F JUL">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Sección
                        </label>
                        <input
                            type="text"
                            name="Seccion"
                            value="{{ old('Seccion') }}"
                            maxlength="50"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                            placeholder="Ej: FILETA, CABEZAL">
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                    <button
                        type="button"
                        onclick="closeModal('createModal')"
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

    <!-- Modal para Editar Falla -->
    <div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-2xl mx-4 transform transition-all max-h-[90vh] overflow-y-auto">
            <div class="bg-yellow-600 text-white px-6 py-4 rounded-t-lg sticky top-0">
                <h2 class="text-xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-edit"></i>
                    Editar Falla
                </h2>
            </div>

            <form id="editForm" action="" method="POST" class="p-6">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Tipo de Falla <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="editTipoFallaId"
                            name="TipoFallaId"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                            <option value="">Seleccione un tipo</option>
                            @foreach($tiposFalla as $tipo)
                                <option value="{{ $tipo }}">{{ $tipo }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Departamento <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="editDepartamento"
                            name="Departamento"
                            required
                            maxlength="50"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Falla <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="editFalla"
                            name="Falla"
                            required
                            maxlength="100"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Descripción
                        </label>
                        <textarea
                            id="editDescripcion"
                            name="Descripcion"
                            rows="3"
                            maxlength="255"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition resize-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Abreviado
                        </label>
                        <input
                            type="text"
                            id="editAbreviado"
                            name="Abreviado"
                            maxlength="50"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Sección
                        </label>
                        <input
                            type="text"
                            id="editSeccion"
                            name="Seccion"
                            maxlength="50"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                    <button
                        type="button"
                        onclick="closeModal('editModal')"
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
    const updateUrl = '{{ route("mantenimiento.catalogos-fallas.update", ["catalogosFalla" => "PLACEHOLDER"]) }}';
    const destroyUrl = '{{ route("mantenimiento.catalogos-fallas.destroy", ["catalogosFalla" => "PLACEHOLDER"]) }}';

    let selectedRow = null;
    let selectedKey = null;

    // Asegurar que la función esté disponible globalmente
    window.openFilterModal = function() {

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
        const tipoFalla = selectedRow.dataset.tipoFalla || '';
        const departamento = selectedRow.dataset.departamento || '';
        const falla = selectedRow.dataset.falla || '';
        const descripcion = selectedRow.dataset.descripcion || '';
        const abreviado = selectedRow.dataset.abreviado || '';
        const seccion = selectedRow.dataset.seccion || '';
        openEditModal(selectedKey, tipoFalla, departamento, falla, descripcion, abreviado, seccion);
    }

    // Eliminar desde botón superior
    function handleTopDelete() {
        if (!selectedKey) return;
        deleteFalla(selectedKey);
    }

    // Abrir modal
    function openModal(modalId) {
        document.getElementById(modalId).classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    // Cerrar modal
    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    // Abrir modal de edición
    function openEditModal(key, tipoFalla, departamento, falla, descripcion, abreviado, seccion) {
        document.getElementById('editTipoFallaId').value = tipoFalla || '';
        document.getElementById('editDepartamento').value = departamento || '';
        document.getElementById('editFalla').value = falla || '';
        document.getElementById('editDescripcion').value = descripcion || '';
        document.getElementById('editAbreviado').value = abreviado || '';
        document.getElementById('editSeccion').value = seccion || '';
        document.getElementById('editForm').action = updateUrl.replace('PLACEHOLDER', encodeURIComponent(key));
        openModal('editModal');
    }

        try {
            const tiposFalla = @json($tiposFalla ?? []);
            const departamentos = @json($departamentos ?? []);
            const tipoFallaActual = '{{ $tipoFallaFilter ?? '' }}';
            const departamentoActual = '{{ $departamentoFilter ?? '' }}';

            let tipoFallaOptions = '<option value="">Todos</option>';
            if (Array.isArray(tiposFalla)) {
                tiposFalla.forEach(tipo => {
                    const tipoEscaped = String(tipo).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    const selected = tipoFallaActual === tipo ? 'selected' : '';
                    tipoFallaOptions += `<option value="${tipoEscaped}" ${selected}>${tipoEscaped}</option>`;
                });
            }

            let departamentoOptions = '<option value="">Todos</option>';
            if (Array.isArray(departamentos)) {
                departamentos.forEach(depto => {
                    const deptoEscaped = String(depto).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    const selected = departamentoActual === depto ? 'selected' : '';
                    departamentoOptions += `<option value="${deptoEscaped}" ${selected}>${deptoEscaped}</option>`;
                });
            }

            Swal.fire({
                title: '<i class="fa-solid fa-filter text-blue-600"></i> Filtrar Fallas',
                html: `
                    <div class="text-left space-y-4 mt-4" style="min-width: 300px;">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo de Falla</label>
                            <select id="swal-tipo-falla" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" style="width: 100%;">
                                ${tipoFallaOptions}
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Departamento</label>
                            <select id="swal-departamento" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" style="width: 100%;">
                                ${departamentoOptions}
                            </select>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: '<i class="fa-solid fa-filter mr-1"></i> Aplicar Filtros',
                denyButtonText: '<i class="fa-solid fa-times mr-1"></i> Limpiar',
                cancelButtonText: '<i class="fa-solid fa-xmark mr-1"></i> Cancelar',
                confirmButtonColor: '#2563eb',
                denyButtonColor: '#6b7280',
                cancelButtonColor: '#dc2626',
                reverseButtons: true,
                width: '500px',
                customClass: {
                    confirmButton: 'px-4 py-2 rounded-lg font-medium',
                    denyButton: 'px-4 py-2 rounded-lg font-medium',
                    cancelButton: 'px-4 py-2 rounded-lg font-medium',
                    popup: 'text-left'
                },
                didOpen: () => {
                    // Asegurar que los selects funcionen correctamente
                    const tipoFallaSelect = document.getElementById('swal-tipo-falla');
                    const departamentoSelect = document.getElementById('swal-departamento');
                    if (tipoFallaSelect) {
                        tipoFallaSelect.style.width = '100%';
                        tipoFallaSelect.focus();
                    }
                    if (departamentoSelect) {
                        departamentoSelect.style.width = '100%';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Aplicar filtros
                    const tipoFallaSelect = document.getElementById('swal-tipo-falla');
                    const departamentoSelect = document.getElementById('swal-departamento');
                    
                    if (!tipoFallaSelect || !departamentoSelect) {
                        console.error('No se encontraron los elementos del formulario');
                        return;
                    }
                    
                    const tipoFalla = tipoFallaSelect.value;
                    const departamento = departamentoSelect.value;
                    
                    const params = new URLSearchParams();
                    if (tipoFalla) params.append('tipo_falla', tipoFalla);
                    if (departamento) params.append('departamento', departamento);
                    
                    const url = '{{ route("mantenimiento.catalogos-fallas.index") }}' + (params.toString() ? '?' + params.toString() : '');
                    window.location.href = url;
                } else if (result.isDenied) {
                    // Limpiar filtros
                    window.location.href = '{{ route("mantenimiento.catalogos-fallas.index") }}';
                }
            });
        } catch (error) {
            console.error('Error al abrir modal de filtros:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo abrir el modal de filtros. Por favor, recarga la página.',
                confirmButtonText: 'Aceptar'
            });
        }
    };

    // Eliminar falla
    function deleteFalla(key) {
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
            closeModal(event.target.id);
        }
    }

    // Cerrar modal con tecla ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal('createModal');
            closeModal('editModal');
        }
    });

    // Inicialización: vincular eventos a los botones del navbar
    document.addEventListener('DOMContentLoaded', function() {
        // Botón Nuevo/Crear
        const btnNuevo = document.getElementById('btn-nuevo');
        if (btnNuevo) {
            btnNuevo.addEventListener('click', function(e) {
                e.preventDefault();
                openModal('createModal');
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
