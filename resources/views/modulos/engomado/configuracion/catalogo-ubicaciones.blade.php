@extends('layouts.app')

@section('page-title', 'Catálogo de Ubicaciones')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create
        onclick="openCreateModal()"
        title="Nueva Ubicación"

        />
        <x-navbar.button-edit
        id="btnEdit"
        onclick="editSelected()"
        title="Editar Ubicación"
        :disabled="true"

        />
        <x-navbar.button-delete
        id="btnDelete"
        onclick="deleteSelected()"
        title="Eliminar Ubicación"
        :disabled="true"

        hoverBg="hover:bg-red-600"/>
    </div>
@endsection

@section('content')
    <div class="w-full">
    @if ($noResults ?? false)
        <div class="alert alert-warning text-center">No se encontraron resultados con la información proporcionada.</div>
    @endif

    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-y-auto max-h-[70vh] relative scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
            <table class="min-w-full text-sm text-center table-sticky">
                <thead class="bg-blue-500 text-white">
                    <tr>
                        <th class="py-2 px-2 font-bold tracking-wider text-center">ID</th>
                        <th class="py-2 px-2 font-bold tracking-wider text-center">Código</th>
                    </tr>
                </thead>
                <tbody id="ubicaciones-body" class="bg-white">
                    @forelse ($ubicaciones as $ubicacion)
                        @php
                            $uid = $ubicacion->Id ?? uniqid();
                        @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer text-black"
                            onclick="selectRow(this)"
                            data-uid="{{ $uid }}"
                            data-codigo="{{ $ubicacion->Codigo }}"
                            data-id="{{ $ubicacion->Id }}">
                            <td class="py-2 px-4">{{ $ubicacion->Id }}</td>
                            <td class="py-2 px-4 font-semibold">{{ $ubicacion->Codigo }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="py-4 px-4 text-center text-gray-500">No hay ubicaciones registradas</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>

<style>
  .scrollbar-thin { scrollbar-width: thin; }
  .scrollbar-thin::-webkit-scrollbar { width: 8px; }
  .scrollbar-thumb-gray-400::-webkit-scrollbar-thumb { background-color: #9ca3af; border-radius: 4px; }
  .scrollbar-track-gray-100::-webkit-scrollbar-track { background-color: #f3f4f6; }
  .scrollbar-thin::-webkit-scrollbar-thumb:hover { background-color: #6b7280; }
  /* Encabezado fijo en scroll interno */
  .table-sticky { border-collapse: separate; border-spacing: 0; }
  .table-sticky thead {
    position: sticky;
    top: 0;
    z-index: 45;
  }
  .table-sticky thead th {
    position: sticky;
    top: 0;
    z-index: 50;
    background-clip: padding-box;
  }
</style>


    <script>
    // Variables globales
    let currentUbicacionId = null;
    let selectedRow = null;

    function getSelectedUbicacionData() {
        if (!selectedRow) {
            return null;
        }

        return {
            id: (selectedRow.dataset.id || '').trim(),
            codigo: selectedRow.dataset.codigo || ''
        };
    }

    // Seleccionar fila de la tabla
    window.selectRow = function(row) {
        // Deseleccionar fila anterior
        if (selectedRow) {
            selectedRow.classList.remove('bg-blue-500', 'text-white', 'hover:bg-blue-600');
            selectedRow.classList.add('text-black', 'hover:bg-blue-50');
        }

        // Si es la misma fila, deseleccionar
        if (selectedRow === row) {
            selectedRow = null;
            currentUbicacionId = null;
            row.classList.remove('bg-blue-500', 'text-white', 'hover:bg-blue-600');
            row.classList.add('text-black', 'hover:bg-blue-50');
            disableButtons();
            return;
        }

        // Seleccionar nueva fila
        selectedRow = row;
        currentUbicacionId = (row.dataset.id || '').trim() || null;
        row.classList.remove('text-black', 'hover:bg-blue-50');
        row.classList.add('bg-blue-500', 'text-white', 'hover:bg-blue-600');
        if (currentUbicacionId) {
            enableButtons();
        } else {
            disableButtons();
        }
    }

    // Habilitar botones del navbar
    window.enableButtons = function() {
        const btnEdit = document.getElementById('btnEdit');
        const btnDelete = document.getElementById('btnDelete');

        if (btnEdit) {
            btnEdit.disabled = false;
            btnEdit.removeAttribute('disabled');
            btnEdit.classList.remove('opacity-50', 'cursor-not-allowed');
            btnEdit.classList.add('cursor-pointer');
        }
        if (btnDelete) {
            btnDelete.disabled = false;
            btnDelete.removeAttribute('disabled');
            btnDelete.classList.remove('opacity-50', 'cursor-not-allowed');
            btnDelete.classList.add('cursor-pointer');
        }
    }

    // Deshabilitar botones del navbar
    window.disableButtons = function() {
        const btnEdit = document.getElementById('btnEdit');
        const btnDelete = document.getElementById('btnDelete');

        if (btnEdit) {
            btnEdit.disabled = true;
            btnEdit.setAttribute('disabled', 'disabled');
            btnEdit.classList.add('opacity-50', 'cursor-not-allowed');
            btnEdit.classList.remove('cursor-pointer');
        }
        if (btnDelete) {
            btnDelete.disabled = true;
            btnDelete.setAttribute('disabled', 'disabled');
            btnDelete.classList.add('opacity-50', 'cursor-not-allowed');
            btnDelete.classList.remove('cursor-pointer');
        }
    }

    function showSelectionWarning() {
        Swal.fire({
            icon: 'warning',
            title: 'Selecciona una ubicación',
            text: 'Debes seleccionar un registro para continuar.'
        });
    }

    // Editar registro seleccionado desde navbar
    window.editSelected = function() {
        const data = getSelectedUbicacionData();
        if (!data || !data.id) {
            showSelectionWarning();
            return;
        }

        openEditModal(data.id);
    }

    // Eliminar registro seleccionado desde navbar
    window.deleteSelected = function() {
        const data = getSelectedUbicacionData();
        if (!data || !data.id) {
            showSelectionWarning();
            return;
        }

        openDeleteModal(data.id);
    }

    // Abrir modal de creación
    window.openCreateModal = function() {
        openUbicacionModal('create');
    }

    // Abrir modal de edición
    window.openEditModal = function(ubicacionId) {
        const data = getSelectedUbicacionData() || {};
        const resolvedId = (ubicacionId || data.id || '').trim();

        if (!resolvedId) {
            showSelectionWarning();
            return;
        }

        openUbicacionModal('edit', {
            id: resolvedId,
            codigo: data.codigo || ''
        });
    }

    // Abrir modal de eliminación
    window.openDeleteModal = function(ubicacionId) {
        const resolvedId = (ubicacionId || currentUbicacionId || '').trim();
        if (!resolvedId) {
            showSelectionWarning();
            return;
        }

        confirmDeleteUbicacion(resolvedId);
    }

    function openUbicacionModal(mode, data = {}) {
        const isEdit = mode === 'edit';
        const title = isEdit ? 'Editar Ubicación' : 'Nueva Ubicación';
        const confirmText = isEdit ? 'Actualizar' : 'Guardar';

        Swal.fire({
            title: title,
            html: `
                <div class="grid grid-cols-1 gap-3 text-left text-sm">
                    <div>
                        <label class="block text-xs font-medium mb-1">
                            Código <span class="text-red-500">*</span>
                        </label>
                        <input id="swal-codigo" class="swal2-input" placeholder="Ej: A1, B1, C1" maxlength="10">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3b82f6',
            focusConfirm: false,
            didOpen: () => {
                document.getElementById('swal-codigo').value = data.codigo || '';
            },
            preConfirm: () => {
                const codigo = document.getElementById('swal-codigo').value.trim().toUpperCase();

                if (!codigo) {
                    Swal.showValidationMessage('El Código es requerido');
                    return false;
                }

                if (codigo.length > 10) {
                    Swal.showValidationMessage('El Código no puede tener más de 10 caracteres');
                    return false;
                }

                return {
                    Codigo: codigo
                };
            }
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            saveUbicacion(result.value, isEdit ? data.id : null);
        });
    }

    function saveUbicacion(data, ubicacionId) {
        const isEdit = !!ubicacionId;
        const url = isEdit
            ? `/engomado/configuracion/catalogo-ubicaciones/${encodeURIComponent(ubicacionId)}`
            : '/engomado/configuracion/catalogo-ubicaciones';
        const method = isEdit ? 'put' : 'post';

        fetch(url, {
            method: method,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(response => {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: isEdit ? '¡Actualizado!' : '¡Creado!',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    location.reload();
                });
                return;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: response.message || 'No se pudo guardar la ubicación'
            });
        })
        .catch(error => {
            console.error('Error al guardar:', error);
            let errorMessage = 'No se pudo guardar la ubicación';

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

    function confirmDeleteUbicacion(ubicacionId) {
        Swal.fire({
            title: 'Confirmar Eliminación',
            text: '¿Está seguro que desea eliminar esta ubicación? Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteUbicacion(ubicacionId);
            }
        });
    }

    function deleteUbicacion(ubicacionId) {
        fetch(`/engomado/configuracion/catalogo-ubicaciones/${encodeURIComponent(ubicacionId)}`, {
            method: 'delete',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(response => {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Eliminado!',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || 'No se pudo eliminar la ubicación'
                });
            }
        })
        .catch(error => {
            console.error('Error al eliminar:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo eliminar la ubicación'
            });
        });
    }
    </script>
@endsection
