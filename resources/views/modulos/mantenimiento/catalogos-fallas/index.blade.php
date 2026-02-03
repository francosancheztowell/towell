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

                onclick="openCreateModal()"
                id="btn-nuevo"/>

            <!-- Botón Editar -->
            <x-navbar.button-edit

                onclick="handleTopEdit()"
                id="btn-top-edit"
                :disabled="true"/>

            <!-- Botón Eliminar -->
            <x-navbar.button-delete

                onclick="handleTopDelete()"
                id="btn-top-delete"
                :disabled="true"/>
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
</style>
<script>
    const updateUrl = '{{ route("mantenimiento.catalogos-fallas.update", ["catalogosFalla" => "PLACEHOLDER"]) }}';
    const destroyUrl = '{{ route("mantenimiento.catalogos-fallas.destroy", ["catalogosFalla" => "PLACEHOLDER"]) }}';
    const storeUrl = '{{ route("mantenimiento.catalogos-fallas.store") }}';

    // Opciones fijas para los selects
    const departamentos = ['ENGOMADO', 'Tejido', 'Atadores', 'URDIDO'];
    const tiposFalla = ['Electrico', 'Mecanico', 'Tiempo Muerto'];

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

    // Abrir modal de creación con SweetAlert
    function openCreateModal() {
        let tipoFallaOptions = '<option value="">Seleccione un tipo</option>';
        tiposFalla.forEach(tipo => {
            tipoFallaOptions += `<option value="${tipo}">${tipo}</option>`;
        });

        let departamentoOptions = '<option value="">Seleccione un departamento</option>';
        departamentos.forEach(depto => {
            departamentoOptions += `<option value="${depto}">${depto}</option>`;
        });

        Swal.fire({
            title: '<i class="fa-solid fa-plus-circle text-green-600"></i> Nueva Falla',
            html: `
                <form id="createForm" class="text-left space-y-4 mt-4" style="min-width: 500px;">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Tipo de Falla <span class="text-red-500">*</span>
                        </label>
                        <select id="swal-tipo-falla" name="TipoFallaId" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            ${tipoFallaOptions}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Departamento <span class="text-red-500">*</span>
                        </label>
                        <select id="swal-departamento" name="Departamento" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            ${departamentoOptions}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Falla <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="swal-falla" name="Falla" required maxlength="100"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                            placeholder="Código o nombre de la falla">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Descripción
                        </label>
                        <textarea id="swal-descripcion" name="Descripcion" rows="3" maxlength="255"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"
                            placeholder="Descripción detallada de la falla"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Abreviado
                            </label>
                            <input type="text" id="swal-abreviado" name="Abreviado" maxlength="50"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="Ej: AMA TRA, F JUL">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Sección
                            </label>
                            <input type="text" id="swal-seccion" name="Seccion" maxlength="50"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="Ej: FILETA, CABEZAL">
                        </div>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-check mr-1"></i> Guardar',
            cancelButtonText: '<i class="fa-solid fa-times mr-1"></i> Cancelar',
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            width: '600px',
            customClass: {
                confirmButton: 'px-4 py-2 rounded-lg font-medium',
                cancelButton: 'px-4 py-2 rounded-lg font-medium',
                popup: 'text-left'
            },
            didOpen: () => {
                document.getElementById('swal-tipo-falla').focus();
            },
            preConfirm: () => {
                const tipoFalla = document.getElementById('swal-tipo-falla').value;
                const departamento = document.getElementById('swal-departamento').value;
                const falla = document.getElementById('swal-falla').value.trim();
                const descripcion = document.getElementById('swal-descripcion').value.trim();
                const abreviado = document.getElementById('swal-abreviado').value.trim();
                const seccion = document.getElementById('swal-seccion').value.trim();

                if (!tipoFalla || !departamento || !falla) {
                    Swal.showValidationMessage('Por favor complete todos los campos requeridos');
                    return false;
                }

                return {
                    TipoFallaId: tipoFalla,
                    Departamento: departamento,
                    Falla: falla,
                    Descripcion: descripcion || null,
                    Abreviado: abreviado || null,
                    Seccion: seccion || null
                };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                submitCreateForm(result.value);
            }
        });
    }

    // Enviar formulario de creación
    function submitCreateForm(data) {
        const formData = new FormData();
        Object.keys(data).forEach(key => {
            if (data[key] !== null) {
                formData.append(key, data[key]);
            }
        });

        fetch(storeUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => Promise.reject(err));
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: result.message || 'Falla creada correctamente',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                throw new Error(result.message || 'Error al crear la falla');
            }
        })
        .catch(error => {
            let errorMessage = 'Error al crear la falla';
            if (error.message) {
                errorMessage = error.message;
            } else if (error.errors) {
                errorMessage = Object.values(error.errors).flat().join(', ');
            }
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage,
                confirmButtonText: 'Aceptar'
            });
        });
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

    // Abrir modal de edición con SweetAlert
    function openEditModal(key, tipoFalla, departamento, falla, descripcion, abreviado, seccion) {
        let tipoFallaOptions = '<option value="">Seleccione un tipo</option>';
        tiposFalla.forEach(tipo => {
            const selected = tipoFalla === tipo ? 'selected' : '';
            tipoFallaOptions += `<option value="${tipo}" ${selected}>${tipo}</option>`;
        });

        let departamentoOptions = '<option value="">Seleccione un departamento</option>';
        departamentos.forEach(depto => {
            const selected = departamento === depto ? 'selected' : '';
            departamentoOptions += `<option value="${depto}" ${selected}>${depto}</option>`;
        });

        Swal.fire({
            title: '<i class="fa-solid fa-edit text-yellow-600"></i> Editar Falla',
            html: `
                <form id="editForm" class="text-left space-y-4 mt-4" style="min-width: 500px;">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Tipo de Falla <span class="text-red-500">*</span>
                        </label>
                        <select id="swal-edit-tipo-falla" name="TipoFallaId" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
                            ${tipoFallaOptions}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Departamento <span class="text-red-500">*</span>
                        </label>
                        <select id="swal-edit-departamento" name="Departamento" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
                            ${departamentoOptions}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Falla <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="swal-edit-falla" name="Falla" required maxlength="100"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500"
                            value="${(falla || '').replace(/"/g, '&quot;')}">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Descripción
                        </label>
                        <textarea id="swal-edit-descripcion" name="Descripcion" rows="3" maxlength="255"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 resize-none">${(descripcion || '').replace(/"/g, '&quot;')}</textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Abreviado
                            </label>
                            <input type="text" id="swal-edit-abreviado" name="Abreviado" maxlength="50"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500"
                                value="${(abreviado || '').replace(/"/g, '&quot;')}">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Sección
                            </label>
                            <input type="text" id="swal-edit-seccion" name="Seccion" maxlength="50"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500"
                                value="${(seccion || '').replace(/"/g, '&quot;')}">
                        </div>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-save mr-1"></i> Actualizar',
            cancelButtonText: '<i class="fa-solid fa-times mr-1"></i> Cancelar',
            confirmButtonColor: '#ca8a04',
            cancelButtonColor: '#6b7280',
            width: '600px',
            customClass: {
                confirmButton: 'px-4 py-2 rounded-lg font-medium',
                cancelButton: 'px-4 py-2 rounded-lg font-medium',
                popup: 'text-left'
            },
            didOpen: () => {
                document.getElementById('swal-edit-tipo-falla').focus();
            },
            preConfirm: () => {
                const tipoFalla = document.getElementById('swal-edit-tipo-falla').value;
                const departamento = document.getElementById('swal-edit-departamento').value;
                const falla = document.getElementById('swal-edit-falla').value.trim();
                const descripcion = document.getElementById('swal-edit-descripcion').value.trim();
                const abreviado = document.getElementById('swal-edit-abreviado').value.trim();
                const seccion = document.getElementById('swal-edit-seccion').value.trim();

                if (!tipoFalla || !departamento || !falla) {
                    Swal.showValidationMessage('Por favor complete todos los campos requeridos');
                    return false;
                }

                return {
                    TipoFallaId: tipoFalla,
                    Departamento: departamento,
                    Falla: falla,
                    Descripcion: descripcion || null,
                    Abreviado: abreviado || null,
                    Seccion: seccion || null
                };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                submitUpdateForm(key, result.value);
            }
        });
    }

    // Enviar formulario de actualización
    function submitUpdateForm(key, data) {
        const url = updateUrl.replace('PLACEHOLDER', encodeURIComponent(key));
        const formData = new FormData();
        formData.append('_method', 'PUT');
        Object.keys(data).forEach(key => {
            if (data[key] !== null) {
                formData.append(key, data[key]);
            }
        });

        fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => Promise.reject(err));
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: result.message || 'Falla actualizada correctamente',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                throw new Error(result.message || 'Error al actualizar la falla');
            }
        })
        .catch(error => {
            let errorMessage = 'Error al actualizar la falla';
            if (error.message) {
                errorMessage = error.message;
            } else if (error.errors) {
                errorMessage = Object.values(error.errors).flat().join(', ');
            }
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage,
                confirmButtonText: 'Aceptar'
            });
        });
    }

    // Eliminar desde botón superior
    function handleTopDelete() {
        if (!selectedKey) return;
        deleteFalla(selectedKey);
    }

    // Asegurar que la función esté disponible globalmente
    window.openFilterModal = function() {
        try {
            const tipoFallaActual = '{{ $tipoFallaFilter ?? '' }}';
            const departamentoActual = '{{ $departamentoFilter ?? '' }}';

            let tipoFallaOptions = '<option value="">Todos</option>';
            tiposFalla.forEach(tipo => {
                const selected = tipoFallaActual === tipo ? 'selected' : '';
                tipoFallaOptions += `<option value="${tipo}" ${selected}>${tipo}</option>`;
            });

            let departamentoOptions = '<option value="">Todos</option>';
            departamentos.forEach(depto => {
                const selected = departamentoActual === depto ? 'selected' : '';
                departamentoOptions += `<option value="${depto}" ${selected}>${depto}</option>`;
            });

            Swal.fire({
                title: '<i class="fa-solid fa-filter text-blue-600"></i> Filtrar Fallas',
                html: `
                    <div class="text-left space-y-4 mt-4" style="min-width: 300px;">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo de Falla</label>
                            <select id="swal-filter-tipo-falla" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" style="width: 100%;">
                                ${tipoFallaOptions}
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Departamento</label>
                            <select id="swal-filter-departamento" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" style="width: 100%;">
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
                    const tipoFallaSelect = document.getElementById('swal-filter-tipo-falla');
                    const departamentoSelect = document.getElementById('swal-filter-departamento');
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
                    const tipoFallaSelect = document.getElementById('swal-filter-tipo-falla');
                    const departamentoSelect = document.getElementById('swal-filter-departamento');

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
                const url = destroyUrl.replace('PLACEHOLDER', encodeURIComponent(key));
                const formData = new FormData();
                formData.append('_method', 'DELETE');

                fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => Promise.reject(err));
                    }
                    return response.json();
                })
                .then(result => {
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: result.message || 'Falla eliminada correctamente',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        throw new Error(result.message || 'Error al eliminar la falla');
                    }
                })
                .catch(error => {
                    let errorMessage = 'Error al eliminar la falla';
                    if (error.message) {
                        errorMessage = error.message;
                    } else if (error.errors) {
                        errorMessage = Object.values(error.errors).flat().join(', ');
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: errorMessage,
                        confirmButtonText: 'Aceptar'
                    });
                });
            }
        });
    }

    // Inicialización: vincular eventos a los botones del navbar
    document.addEventListener('DOMContentLoaded', function() {
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
