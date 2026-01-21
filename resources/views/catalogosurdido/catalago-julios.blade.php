@extends('layouts.app')

@section('page-title', 'Catálogo de Julios')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create onclick="openCreateModal()" title="Nuevo Julio"/>
        <x-navbar.button-edit id="btnEdit" onclick="editSelected()" title="Editar Julio" :disabled="true"/>
        <x-navbar.button-delete id="btnDelete" onclick="deleteSelected()" title="Eliminar Julio" :disabled="true" hoverBg="hover:bg-red-600"/>
        <x-buttons.catalog-actions route="julios" :showFilters="true" />
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
                        <th class="py-2 px-2 font-bold tracking-wider text-center">No. Julio</th>
                        <th class="py-2 px-2 font-bold tracking-wider text-center">Tara</th>
                        <th class="py-2 px-2 font-bold tracking-wider text-center">Departamento</th>
                    </tr>
                </thead>
                <tbody id="julios-body" class="bg-white">
                    @forelse ($julios as $julio)
                        @php
                            $uid = $julio->Id ?? uniqid();
                            $dep = strtoupper($julio->Departamento ?? '');
                            $depBadge = $dep === 'URDIDO'
                                ? 'bg-indigo-100 text-indigo-700'
                                : ($dep === 'ENGOMADO'
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-gray-100 text-gray-700');
                        @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer text-black"
                            onclick="selectRow(this)"
                            data-uid="{{ $uid }}"
                            data-no-julio="{{ $julio->NoJulio }}"
                            data-tara="{{ $julio->Tara ?? 0 }}"
                            data-departamento="{{ $julio->Departamento ?? '' }}"
                            data-id="{{ $julio->Id ?? $julio->NoJulio }}">
                            <td class="py-2 px-4">{{ $julio->NoJulio }}</td>
                            <td class="py-2 px-4">{{ number_format($julio->Tara ?? 0, 2) }}</td>
                            <td class="py-2 px-4">
                                <span class="px-2 py-1 rounded text-xs font-semibold {{ $depBadge }}">
                                    {{ $julio->Departamento ?? 'N/A' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-4 px-4 text-center text-gray-500">No hay julios registrados</td>
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
    let currentJulioId = null;
    let selectedRow = null;

    function getSelectedJulioData() {
        if (!selectedRow) {
            return null;
        }

        return {
            id: (selectedRow.dataset.id || selectedRow.dataset.noJulio || '').trim(),
            noJulio: selectedRow.dataset.noJulio || '',
            tara: selectedRow.dataset.tara || '',
            departamento: selectedRow.dataset.departamento || ''
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
            currentJulioId = null;
            row.classList.remove('bg-blue-500', 'text-white', 'hover:bg-blue-600');
            row.classList.add('text-black', 'hover:bg-blue-50');
            disableButtons();
            return;
        }

        // Seleccionar nueva fila
        selectedRow = row;
        currentJulioId = (row.dataset.id || row.dataset.noJulio || '').trim() || null;
        row.classList.remove('text-black', 'hover:bg-blue-50');
        row.classList.add('bg-blue-500', 'text-white', 'hover:bg-blue-600');
        if (currentJulioId) {
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

        if (typeof window.actualizarBotonesAccionJulios === 'function') {
            window.actualizarBotonesAccionJulios(true);
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

        if (typeof window.actualizarBotonesAccionJulios === 'function') {
            window.actualizarBotonesAccionJulios(false);
        }
    }

    function showSelectionWarning() {
        Swal.fire({
            icon: 'warning',
            title: 'Selecciona un julio',
            text: 'Debes seleccionar un registro para continuar.'
        });
    }

    // Editar registro seleccionado desde navbar
    window.editSelected = function() {
        const data = getSelectedJulioData();
        if (!data || !data.id) {
            showSelectionWarning();
            return;
        }

        openEditModal(data.id);
    }

    // Eliminar registro seleccionado desde navbar
    window.deleteSelected = function() {
        const data = getSelectedJulioData();
        if (!data || !data.id) {
            showSelectionWarning();
            return;
        }

        openDeleteModal(data.id);
    }

    // Abrir modal de creación
    window.openCreateModal = function() {
        openJulioModal('create');
    }

    window.agregarJulios = function() {
        openCreateModal();
    }

    // Abrir modal de edición
    window.openEditModal = function(julioId) {
        const data = getSelectedJulioData() || {};
        const resolvedId = (julioId || data.id || '').trim();

        if (!resolvedId) {
            showSelectionWarning();
            return;
        }

        openJulioModal('edit', {
            id: resolvedId,
            noJulio: data.noJulio || '',
            tara: data.tara || '',
            departamento: data.departamento || ''
        });
    }

    // Abrir modal de eliminación
    window.openDeleteModal = function(julioId) {
        const resolvedId = (julioId || currentJulioId || '').trim();
        if (!resolvedId) {
            showSelectionWarning();
            return;
        }

        confirmDeleteJulio(resolvedId);
    }

    window.editarJulios = function() {
        editSelected();
    }

    window.eliminarJulios = function() {
        deleteSelected();
    }

    function openJulioModal(mode, data = {}) {
        const isEdit = mode === 'edit';
        const title = isEdit ? 'Editar Julio' : 'Nuevo Julio';
        const confirmText = isEdit ? 'Actualizar' : 'Guardar';

        Swal.fire({
            title: title,
            html: `
                <div class="grid grid-cols-1 gap-3 text-left text-sm">
                    <div>
                        <label class="block text-xs font-medium mb-1">
                            No. Julio <span class="text-red-500">*</span>
                        </label>
                        <input id="swal-no-julio" class="swal2-input" placeholder="Ej: J001, J002">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Tara</label>
                        <input id="swal-tara" type="number" step="0.01" min="0" class="swal2-input" placeholder="Ej: 10.50">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Departamento</label>
                        <select id="swal-departamento" class="swal2-input">
                            <option value="">-- Seleccione --</option>
                            <option value="Urdido">Urdido</option>
                            <option value="Engomado">Engomado</option>
                        </select>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3b82f6',
            focusConfirm: false,
            didOpen: () => {
                document.getElementById('swal-no-julio').value = data.noJulio || '';
                document.getElementById('swal-tara').value = data.tara ?? '';
                document.getElementById('swal-departamento').value = data.departamento || '';
            },
            preConfirm: () => {
                const noJulio = document.getElementById('swal-no-julio').value.trim();
                const taraValue = document.getElementById('swal-tara').value;
                const departamento = document.getElementById('swal-departamento').value;

                if (!noJulio) {
                    Swal.showValidationMessage('El No. Julio es requerido');
                    return false;
                }

                const tara = taraValue === '' ? 0 : Number(taraValue);
                if (taraValue !== '' && Number.isNaN(tara)) {
                    Swal.showValidationMessage('La Tara debe ser un número válido');
                    return false;
                }

                return {
                    NoJulio: noJulio,
                    Tara: tara,
                    Departamento: departamento || null
                };
            }
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            saveJulio(result.value, isEdit ? data.id : null);
        });
    }

    function saveJulio(data, julioId) {
        const isEdit = !!julioId;
        const url = isEdit
            ? `/urdido/catalogos-julios/${encodeURIComponent(julioId)}`
            : '/urdido/catalogos-julios';
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
                return;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: response.data.message || 'No se pudo guardar el julio'
            });
        })
        .catch(error => {
            console.error('Error al guardar:', error);
            let errorMessage = 'No se pudo guardar el julio';

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

    function confirmDeleteJulio(julioId) {
        Swal.fire({
            title: 'Confirmar Eliminación',
            text: '¿Está seguro que desea eliminar este julio? Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteJulio(julioId);
            }
        });
    }

    function deleteJulio(julioId) {
        const resolvedId = (julioId || currentJulioId || '').trim();
        if (!resolvedId) {
            return;
        }

        axios.delete(`/urdido/catalogos-julios/${encodeURIComponent(resolvedId)}`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (response.data.success) {
                if (selectedRow && selectedRow.dataset.id === resolvedId) {
                    selectedRow.remove();
                    selectedRow = null;
                    currentJulioId = null;
                    disableButtons();
                }

                Swal.fire({
                    icon: 'success',
                    title: '¡Eliminado!',
                    text: response.data.message,
                    showConfirmButton: false,
                    timer: 1500
                });
                return;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: response.data.message || 'No se pudo eliminar el julio'
            });
        })
        .catch(error => {
            console.error('Error al eliminar:', error);

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.response?.data?.message || 'No se pudo eliminar el julio'
            });
        });
    }

    // Funciones globales para el componente de filtros
    window.filtrarJulios = function() {
        Swal.fire({
            title: 'Filtrar Julios',
            html: `
                <div class="grid grid-cols-1 gap-3 text-left text-sm">
                    <div>
                        <label class="block text-xs font-medium mb-1">No. Julio</label>
                        <input id="filter-no-julio" class="swal2-input" placeholder="Buscar por No. Julio">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Departamento</label>
                        <select id="filter-departamento" class="swal2-input">
                            <option value="">-- Todos --</option>
                            <option value="Urdido">Urdido</option>
                            <option value="Engomado">Engomado</option>
                        </select>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Filtrar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3b82f6',
            preConfirm: () => {
                const params = new URLSearchParams();
                const noJulio = document.getElementById('filter-no-julio').value.trim();
                const departamento = document.getElementById('filter-departamento').value;

                if (noJulio) params.append('no_julio', noJulio);
                if (departamento) params.append('departamento', departamento);

                window.location.href = `${window.location.pathname}?${params.toString()}`;
            }
        });
    };

    window.limpiarFiltrosJulios = function() {
        window.location.href = window.location.pathname;
    };

    // Inicializar botones como deshabilitados
    document.addEventListener('DOMContentLoaded', function() {
        disableButtons();
    });
    </script>
@endsection
