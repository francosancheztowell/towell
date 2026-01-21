@extends('layouts.app')

@section('page-title', 'Pesos por Rollos')

@section('navbar-right')
<x-buttons.catalog-actions route="pesos-rollos" :showFilters="true" />
@endsection

@section('content')
    <div class="container-fluid">
        <!-- Tabla -->
        <div class="bg-white overflow-hidden">
            <div class="overflow-y-auto" style="max-height: calc(100vh - 70px); overflow-y: auto;">
                <table class="table w-full">
                    <thead class="sticky top-0 bg-blue-500 text-white z-10">
                        <tr>
                            <th class="py-1 px-2 font-bold tracking-wider text-center">Cod Artículo</th>
                            <th class="py-1 px-2 font-bold tracking-wider text-center">Nombre</th>
                            <th class="py-1 px-2 font-bold tracking-wider text-center">Tamaño</th>
                            <th class="py-1 px-2 font-bold tracking-wider text-center">Peso Rollo</th>
                        </tr>
                    </thead>
                    <tbody id="pesos-rollos-body" class="bg-white text-black">
                        @foreach ($pesosRollos as $item)
                            @php
                                $uniqueId = $item->ItemId . '_' . $item->InventSizeId;
                                $recordId = $item->Id;
                            @endphp
                            <tr class="text-center hover:bg-blue-50 transition cursor-pointer text-black"
                                onclick="selectRow(this, '{{ $uniqueId }}', '{{ $recordId }}')"
                                ondblclick="deselectRow(this)"
                                data-item-id="{{ $item->ItemId }}"
                                data-item-name="{{ $item->ItemName }}"
                                data-invent-size-id="{{ $item->InventSizeId }}"
                                data-peso-rollo="{{ $item->PesoRollo }}"
                                data-id="{{ $recordId }}">
                                <td class="py-1 px-4">{{ $item->ItemId }}</td>
                                <td class="py-1 px-4">{{ $item->ItemName }}</td>
                                <td class="py-1 px-4">{{ $item->InventSizeId }}</td>
                                <td class="py-1 px-4 font-semibold">{{ number_format($item->PesoRollo, 2) }} kg</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        /* ===========================
           Estado y datos
        ============================ */
        let selectedRow = null;
        let selectedKey = null;
        let selectedId = null;

        let filtrosActuales = { itemId: '', itemName: '', inventSizeId: '', pesoMin: '', pesoMax: '' };
        let datosOriginales = @json($pesosRollos);
        let datosActuales = datosOriginales;

        /* ===========================
           Helpers UI
        ============================ */
        function crearToast(icon, msg, ms = 1500) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: ms,
                timerProgressBar: true,
                didOpen: (t) => {
                    t.addEventListener('mouseenter', Swal.stopTimer);
                    t.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
            Toast.fire({ icon, title: msg });
        }

        // Función para actualizar botones (proporcionada por catalog-actions)
        // El componente genera: actualizarBotonesAccionPesos_rollos
        if (typeof window.actualizarBotonesAccionPesos_rollos === 'function') {
            // Ya está disponible del componente
        } else {
            window.actualizarBotonesAccionPesos_rollos = function(enabled) {
                // Función placeholder si no está disponible
            };
        }

        /* ===========================
           Selección de filas
        ============================ */
        function selectRow(row, uniqueId, id) {
            document.querySelectorAll('#pesos-rollos-body tr').forEach(r => {
                r.classList.remove('bg-blue-500', 'text-white', 'font-semibold');
                r.classList.add('text-black', 'hover:bg-blue-50');
            });
            row.classList.remove('text-black', 'hover:bg-blue-50');
            row.classList.add('bg-blue-500', 'text-white', 'font-semibold');

            selectedRow = row;
            selectedKey = uniqueId;
            selectedId = id ? String(id) : null;

            if (typeof window.actualizarBotonesAccionPesos_rollos === 'function') {
                window.actualizarBotonesAccionPesos_rollos(true);
            }
        }

        function deselectRow(row) {
            if (!row.classList.contains('bg-blue-500')) return;
            row.classList.remove('bg-blue-500', 'text-white', 'font-semibold');
            row.classList.add('text-black', 'hover:bg-blue-50');
            selectedRow = null;
            selectedKey = null;
            selectedId = null;

            if (typeof window.actualizarBotonesAccionPesos_rollos === 'function') {
                window.actualizarBotonesAccionPesos_rollos(false);
            }
        }

        /* ===========================
           Crear
        ============================ */
        function agregarPesoRollo() {
            Swal.fire({
                title: 'Crear Nuevo Peso por Rollo',
                html: `
                    <div class="grid grid-cols-1 gap-3 text-sm">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Cod Artículo *</label>
                            <input id="swal-item-id" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="Ej: ITEM001" maxlength="20" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre *</label>
                            <input id="swal-item-name" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="Ej: Tela Algodón" maxlength="60" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tamaño *</label>
                            <input id="swal-invent-size-id" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="Ej: S, M, L" maxlength="10" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Peso Rollo (kg) *</label>
                            <input id="swal-peso-rollo" type="number" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="0.00" min="0" step="0.01" required>
                        </div>
                    </div>
                `,
                width: '420px',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-save me-2"></i>Crear',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                preConfirm: () => {
                    const ItemId = document.getElementById('swal-item-id').value.trim();
                    const ItemName = document.getElementById('swal-item-name').value.trim();
                    const InventSizeId = document.getElementById('swal-invent-size-id').value.trim();
                    const PesoRollo = document.getElementById('swal-peso-rollo').value.trim();

                    if (!ItemId || !ItemName || !InventSizeId || !PesoRollo) {
                        Swal.showValidationMessage('Por favor completa todos los campos requeridos');
                        return false;
                    }

                    const peso = parseFloat(PesoRollo);
                    if (!Number.isFinite(peso) || peso < 0) {
                        Swal.showValidationMessage('El peso debe ser un número válido mayor o igual a 0');
                        return false;
                    }

                    return { ItemId, ItemName, InventSizeId, PesoRollo: peso };
                }
            }).then((res) => {
                if (!res.isConfirmed) return;
                Swal.fire({ title: 'Creando...', allowOutsideClick: false, showConfirmButton: false, didOpen: Swal.showLoading });

                fetch('/planeacion/catalogos/pesos-rollos', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(res.value)
                })
                    .then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t || `HTTP ${r.status}`); }))
                    .then(data => {
                        if (!data.success) throw new Error(data.message || 'Error al crear');
                        Swal.fire({ icon: 'success', title: '¡Peso por rollo creado!', timer: 2000, showConfirmButton: false })
                            .then(() => location.reload());
                    })
                    .catch(err => Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Error al crear el registro.' }));
            });
        }

        /* ===========================
           Editar
        ============================ */
        function editarPesoRollo() {
            if (!selectedRow || !selectedId) {
                Swal.fire({ title: 'Error', text: 'Selecciona un registro para editar', icon: 'warning' });
                return;
            }

            const itemIdActual = selectedRow.getAttribute('data-item-id');
            const itemNameActual = selectedRow.getAttribute('data-item-name');
            const inventSizeIdActual = selectedRow.getAttribute('data-invent-size-id');
            const pesoRolloActual = parseFloat(selectedRow.getAttribute('data-peso-rollo') || '0');

            Swal.fire({
                title: 'Editar Peso por Rollo',
                html: `
                    <div class="grid grid-cols-1 gap-3 text-sm">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Cod Artículo *</label>
                            <input id="swal-item-id-edit" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" maxlength="20" required value="${itemIdActual}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre *</label>
                            <input id="swal-item-name-edit" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" maxlength="60" required value="${itemNameActual}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tamaño *</label>
                            <input id="swal-invent-size-id-edit" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" maxlength="10" required value="${inventSizeIdActual}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Peso Rollo (kg) *</label>
                            <input id="swal-peso-rollo-edit" type="number" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" min="0" step="0.01" required value="${pesoRolloActual}">
                        </div>
                    </div>
                `,
                width: '420px',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-save me-2"></i>Guardar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6c757d',
                preConfirm: () => {
                    const ItemId = document.getElementById('swal-item-id-edit').value.trim();
                    const ItemName = document.getElementById('swal-item-name-edit').value.trim();
                    const InventSizeId = document.getElementById('swal-invent-size-id-edit').value.trim();
                    const PesoRollo = document.getElementById('swal-peso-rollo-edit').value.trim();

                    if (!ItemId || !ItemName || !InventSizeId || !PesoRollo) {
                        Swal.showValidationMessage('Por favor completa todos los campos requeridos');
                        return false;
                    }

                    const peso = parseFloat(PesoRollo);
                    if (!Number.isFinite(peso) || peso < 0) {
                        Swal.showValidationMessage('El peso debe ser un número válido mayor o igual a 0');
                        return false;
                    }

                    return { ItemId, ItemName, InventSizeId, PesoRollo: peso };
                }
            }).then((res) => {
                if (!res.isConfirmed) return;
                Swal.fire({ title: 'Actualizando...', allowOutsideClick: false, showConfirmButton: false, didOpen: Swal.showLoading });

                fetch(`/planeacion/catalogos/pesos-rollos/${selectedId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(res.value)
                })
                    .then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t || `HTTP ${r.status}`); }))
                    .then(data => {
                        if (!data.success) throw new Error(data.message || 'Error al actualizar');
                        Swal.fire({ icon: 'success', title: '¡Peso por rollo actualizado!', timer: 2000, showConfirmButton: false })
                            .then(() => location.reload());
                    })
                    .catch(err => Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Error al actualizar el registro.' }));
            });
        }

        /* ===========================
           Eliminar
        ============================ */
        function eliminarPesoRollo() {
            if (!selectedRow || !selectedId) {
                Swal.fire({ title: 'Error', text: 'Selecciona un registro para eliminar', icon: 'warning' });
                return;
            }

            Swal.fire({
                title: '¿Eliminar Peso por Rollo?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash me-2"></i>Sí, eliminar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
            }).then((result) => {
                if (!result.isConfirmed) return;

                Swal.fire({ title: 'Eliminando...', allowOutsideClick: false, showConfirmButton: false, didOpen: Swal.showLoading });

                fetch(`/planeacion/catalogos/pesos-rollos/${selectedId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                    .then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t || `HTTP ${r.status}`); }))
                    .then(data => {
                        if (!data.success) throw new Error(data.message || 'Error al eliminar');
                        Swal.fire({ icon: 'success', title: '¡Peso por rollo eliminado!', timer: 1800, showConfirmButton: false })
                            .then(() => location.reload());
                    })
                    .catch(err => Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'No se pudo eliminar el registro.' }));
            });
        }

        /* ===========================
           Filtros
        ============================ */
        function mostrarFiltros() {
            Swal.fire({
                title: 'Filtrar Pesos por Rollos',
                html: `
                    <div class="grid grid-cols-1 gap-3 text-sm">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Cod Artículo</label>
                            <input id="swal-item-id-filter" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="Buscar por Cod Artículo" value="${filtrosActuales.itemId || ''}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre</label>
                            <input id="swal-item-name-filter" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="Buscar por nombre" value="${filtrosActuales.itemName || ''}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tamaño</label>
                            <input id="swal-invent-size-id-filter" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="Buscar por tamaño" value="${filtrosActuales.inventSizeId || ''}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Peso Mínimo (kg)</label>
                            <input id="swal-peso-min-filter" type="number" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" min="0" step="0.01" value="${filtrosActuales.pesoMin || ''}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Peso Máximo (kg)</label>
                            <input id="swal-peso-max-filter" type="number" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" min="0" step="0.01" value="${filtrosActuales.pesoMax || ''}">
                        </div>
                    </div>
                    <div class="mt-3 text-xs text-gray-500 bg-blue-50 p-2 rounded">
                        <i class="fas fa-info-circle mr-1"></i>Deja campos vacíos para no aplicar filtro.
                    </div>
                `,
                width: '420px',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-filter mr-2"></i>Filtrar',
                cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancelar',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                preConfirm: () => {
                    return {
                        itemId: document.getElementById('swal-item-id-filter').value.trim(),
                        itemName: document.getElementById('swal-item-name-filter').value.trim(),
                        inventSizeId: document.getElementById('swal-invent-size-id-filter').value.trim(),
                        pesoMin: document.getElementById('swal-peso-min-filter').value.trim(),
                        pesoMax: document.getElementById('swal-peso-max-filter').value.trim()
                    };
                }
            }).then((res) => {
                if (!res.isConfirmed) return;
                filtrosActuales = res.value;
                aplicarFiltros();
            });
        }

        function aplicarFiltros() {
            datosActuales = datosOriginales.filter(item => {
                const itemId = String(item.ItemId || '').toLowerCase();
                const itemName = String(item.ItemName || '').toLowerCase();
                const inventSizeId = String(item.InventSizeId || '').toLowerCase();
                const pesoRollo = parseFloat(item.PesoRollo || 0);

                const matchItemId = !filtrosActuales.itemId || itemId.includes(filtrosActuales.itemId.toLowerCase());
                const matchItemName = !filtrosActuales.itemName || itemName.includes(filtrosActuales.itemName.toLowerCase());
                const matchInventSizeId = !filtrosActuales.inventSizeId || inventSizeId.includes(filtrosActuales.inventSizeId.toLowerCase());
                const matchPesoMin = !filtrosActuales.pesoMin || pesoRollo >= parseFloat(filtrosActuales.pesoMin);
                const matchPesoMax = !filtrosActuales.pesoMax || pesoRollo <= parseFloat(filtrosActuales.pesoMax);

                return matchItemId && matchItemName && matchInventSizeId && matchPesoMin && matchPesoMax;
            });

            renderizarTabla();
        }

        function limpiarFiltros() {
            filtrosActuales = { itemId: '', itemName: '', inventSizeId: '', pesoMin: '', pesoMax: '' };
            datosActuales = datosOriginales;
            renderizarTabla();
            crearToast('success', 'Filtros limpiados', 1500);
        }

        function renderizarTabla() {
            const tbody = document.getElementById('pesos-rollos-body');
            tbody.innerHTML = '';

            if (datosActuales.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500">No se encontraron registros</td></tr>';
                return;
            }

            datosActuales.forEach(item => {
                const uniqueId = `${item.ItemId}_${item.InventSizeId}`;
                const recordId = item.Id || item.id;
                const row = document.createElement('tr');
                row.className = 'text-center hover:bg-blue-50 transition cursor-pointer text-black';
                row.setAttribute('onclick', `selectRow(this, '${uniqueId}', '${recordId}')`);
                row.setAttribute('ondblclick', 'deselectRow(this)');
                row.setAttribute('data-item-id', item.ItemId || '');
                row.setAttribute('data-item-name', item.ItemName || '');
                row.setAttribute('data-invent-size-id', item.InventSizeId || '');
                row.setAttribute('data-peso-rollo', item.PesoRollo || '0');
                row.setAttribute('data-id', recordId);

                row.innerHTML = `
                    <td class="py-1 px-4">${item.ItemId || ''}</td>
                    <td class="py-1 px-4">${item.ItemName || ''}</td>
                    <td class="py-1 px-4">${item.InventSizeId || ''}</td>
                    <td class="py-1 px-4 font-semibold">${parseFloat(item.PesoRollo || 0).toFixed(2)} kg</td>
                `;

                tbody.appendChild(row);
            });
        }

        // Exponer funciones globalmente para el componente catalog-actions
        // El componente espera nombres basados en el route: "pesos-rollos" -> "Pesos_rollos"
        window.agregarPesos_rollos = agregarPesoRollo;
        window.editarPesos_rollos = editarPesoRollo;
        window.eliminarPesos_rollos = eliminarPesoRollo;
        window.filtrarPesos_rollos = mostrarFiltros;
        window.limpiarFiltrosPesos_rollos = limpiarFiltros;
    </script>
@endsection
