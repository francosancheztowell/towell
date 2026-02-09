@extends('layouts.app')

@section('page-title', 'Secuencia Corte de Eficiencia')

@section('navbar-right')
    <x-buttons.inventory-sequence-actions
        modulo="Secuencia Corte de Eficiencia"
        onCreate="agregarSecuenciaCorteEficiencia"
        onEdit="editarSecuenciaCorteEficiencia"
        onDelete="eliminarSecuenciaCorteEficiencia"
    />
@endsection

@section('content')
<div class="container-fluid px-4 py-6">
    <div class="rounded-xl shadow-sm overflow-hidden bg-white">
        <div class="overflow-x-auto overflow-y-auto max-h-[600px]">
            <table id="mainTable" class="w-full border-collapse">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-blue-500 text-white text-sm font-medium">
                        <th class="py-3 px-4 text-left w-12"></th>
                        <th class="py-3 px-4 text-center">NoTelarId</th>
                        <th class="py-3 px-4 text-center">SalonTejidoId</th>
                        <th class="py-3 px-4 text-center">Orden</th>
                    </tr>
                </thead>
                <tbody id="secuencia-corte-eficiencia-body">
                    @foreach ($registros as $index => $item)
                        <tr class="secuencia-row text-center transition cursor-pointer {{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-100' }} hover:opacity-90"
                            draggable="true"
                            ondragstart="handleDragStart(event)"
                            ondragend="handleDragEnd(event)"
                            ondragover="handleDragOver(event)"
                            ondragleave="handleDragLeave(event)"
                            ondrop="handleDrop(event)"
                            data-id="{{ $item->NoTelarId }}"
                            data-notelarid="{{ $item->NoTelarId }}"
                            data-salontejidoid="{{ $item->SalonTejidoId ?? '' }}"
                            data-orden="{{ $item->Orden }}"
                            data-row-index="{{ $index }}"
                            onclick="selectRow(this, '{{ $item->NoTelarId }}')"
                            ondblclick="deselectRow(this)"
                        >
                            <td class="py-3 px-2 text-gray-400 cursor-grab active:cursor-grabbing" title="Arrastrar para reordenar">
                                <i class="fas fa-grip-vertical"></i>
                            </td>
                            <td class="py-3 px-4">{{ $item->NoTelarId }}</td>
                            <td class="py-3 px-4">{{ $item->SalonTejidoId }}</td>
                            <td class="py-3 px-4">{{ $item->Orden }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* Animaciones drag and drop */
.secuencia-row {
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, opacity 0.2s ease;
}
.secuencia-row.dragging {
    opacity: 0.6;
    transform: scale(0.98);
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    background-color: #dbeafe !important;
    z-index: 10;
}
.secuencia-row.drag-over-top {
    box-shadow: 0 -4px 0 0 #3b82f6 inset;
    background-color: #eff6ff !important;
}
.secuencia-row.drag-over-bottom {
    box-shadow: 0 4px 0 0 #3b82f6 inset;
    background-color: #eff6ff !important;
}
.secuencia-row.drop-success {
    animation: dropSuccess 0.5s ease;
}
@keyframes dropSuccess {
    0% { background-color: #bbf7d0 !important; }
    100% { background-color: inherit; }
}
</style>

<script>
/* ===========================
   Estado y datos
=========================== */
let selectedRow = null;
let selectedId = null;
const updateUrlTemplate = @json(route('tejido.secuencia-corte-eficiencia.update', ['id' => '__ID__']));
const deleteUrlTemplate = @json(route('tejido.secuencia-corte-eficiencia.destroy', ['id' => '__ID__']));

/* ===========================
   Helpers UI
=========================== */
function enableButtons() {
    const e = document.getElementById('btn-editar');
    const d = document.getElementById('btn-eliminar');
    if (e) {
        e.disabled = false;
        e.className = 'p-2 text-yellow-600 hover:text-yellow-800 hover:bg-yellow-100 rounded-md transition-colors';
    }
    if (d) {
        d.disabled = false;
        d.className = 'p-2 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors';
    }
}

function disableButtons() {
    const e = document.getElementById('btn-editar');
    const d = document.getElementById('btn-eliminar');
    if (e) {
        e.disabled = true;
        e.className = 'p-2 text-gray-400 hover:text-gray-600 rounded-md transition-colors cursor-not-allowed';
    }
    if (d) {
        d.disabled = true;
        d.className = 'p-2 text-red-400 hover:text-red-600 rounded-md transition-colors cursor-not-allowed';
    }
}

/* ===========================
   Selección de filas
=========================== */
function selectRow(row, id) {
    if (isDragging) return;
    const tbody = document.getElementById('secuencia-corte-eficiencia-body');
    const rows = tbody ? tbody.querySelectorAll('tr.secuencia-row') : [];
    rows.forEach((r, idx) => {
        r.classList.remove('bg-blue-500', 'text-white');
        r.classList.add(idx % 2 === 0 ? 'bg-white' : 'bg-gray-100');
    });
    row.classList.remove('bg-white', 'bg-gray-100');
    row.classList.add('bg-blue-500', 'text-white');

    selectedRow = row;
    selectedId = id;
    enableButtons();
}

function deselectRow(row) {
    if (!row.classList.contains('bg-blue-500')) return;
    const tbody = document.getElementById('secuencia-corte-eficiencia-body');
    const rows = tbody ? tbody.querySelectorAll('tr.secuencia-row') : [];
    rows.forEach((r, idx) => {
        r.classList.remove('bg-blue-500', 'text-white');
        r.classList.add(idx % 2 === 0 ? 'bg-white' : 'bg-gray-100');
    });
    selectedRow = null;
    selectedId = null;
    disableButtons();
}

/* ===========================
   Crear (Agregar)
=========================== */
function agregarSecuenciaCorteEficiencia() {
    Swal.fire({
        title: 'Crear Secuencia Corte Eficiencia',
        html: `
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">NoTelarId *</label>
                    <input id="swal-notelarid" type="number" class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           placeholder="Ej: 201" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">SalonTejidoId *</label>
                    <input id="swal-salontejidoid" type="text" class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           placeholder="Ej: Jacquard" maxlength="100" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Orden</label>
                    <input id="swal-orden" type="number" step="1" min="1"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           placeholder="Auto">
                </div>
            </div>
        `,
        width: '500px',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save me-2"></i>Crear',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        confirmButtonColor: '#255be6',
        cancelButtonColor: '#6c757d',
        preConfirm: () => {
            const NoTelarId = document.getElementById('swal-notelarid').value;
            const SalonTejidoId = document.getElementById('swal-salontejidoid').value.trim();
            const Orden = document.getElementById('swal-orden').value;

            if (!NoTelarId) {
                Swal.showValidationMessage('El campo NoTelarId es requerido');
                return false;
            }
            if (!SalonTejidoId) {
                Swal.showValidationMessage('El campo SalonTejidoId es requerido');
                return false;
            }

            return {
                NoTelarId: parseInt(NoTelarId),
                SalonTejidoId: SalonTejidoId,
                Orden: Orden ? parseInt(Orden) : null
            };
        }
    }).then((res) => {
        if (!res.isConfirmed) return;
        Swal.fire({ title: 'Creando...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });

        fetch('{{ route("tejido.secuencia-corte-eficiencia.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(res.value)
        })
        .then(r => r.json().then(data => ({ ok: r.ok, status: r.status, data })))
        .then(({ ok, status, data }) => {
            if (!ok) {
                const msg = (data && data.errors && data.errors.NoTelarId) ? data.errors.NoTelarId[0] : (data && data.message) || 'Error al crear';
                throw new Error(msg);
            }
            if (!data.success) throw new Error(data.message || 'Error al crear');
            Swal.fire({ icon:'success', title:'¡Registro creado!', timer:2000, showConfirmButton:false })
                .then(() => location.reload());
        })
        .catch(err => Swal.fire({ icon:'error', title:'Error', text: err.message || 'Error al crear el registro.' }));
    });
}

/* ===========================
   Editar
=========================== */
function editarSecuenciaCorteEficiencia() {
    if (!selectedRow || selectedId === null) {
        Swal.fire({ title:'Error', text:'Por favor selecciona un registro para editar', icon:'warning' });
        return;
    }

    const notelaridActual = selectedRow.getAttribute('data-notelarid');
    const salontejidoidActual = selectedRow.getAttribute('data-salontejidoid') || '';
    const ordenActual = selectedRow.getAttribute('data-orden');

    Swal.fire({
        title: 'Editar Secuencia Corte Eficiencia',
        html: `
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">NoTelarId *</label>
                    <input id="swal-notelarid-edit" type="number" class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           required value="${notelaridActual}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">SalonTejidoId *</label>
                    <input id="swal-salontejidoid-edit" type="text" class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           maxlength="100" required value="${salontejidoidActual.replace(/"/g, '&quot;')}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Orden *</label>
                    <input id="swal-orden-edit" type="number" step="1" min="1"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           required value="${ordenActual}">
                </div>
            </div>
        `,
        width: '500px',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save me-2"></i>Actualizar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        preConfirm: () => {
            const NoTelarId = document.getElementById('swal-notelarid-edit').value;
            const SalonTejidoId = document.getElementById('swal-salontejidoid-edit').value.trim();
            const Orden = document.getElementById('swal-orden-edit').value;

            if (!NoTelarId) {
                Swal.showValidationMessage('El campo NoTelarId es requerido');
                return false;
            }
            if (!SalonTejidoId) {
                Swal.showValidationMessage('El campo SalonTejidoId es requerido');
                return false;
            }
            if (!Orden) {
                Swal.showValidationMessage('El campo Orden es requerido');
                return false;
            }

            return {
                NoTelarId: parseInt(NoTelarId),
                SalonTejidoId: SalonTejidoId,
                Orden: parseInt(Orden)
            };
        }
    }).then((res) => {
        if (!res.isConfirmed) return;
        Swal.fire({ title:'Actualizando...', allowOutsideClick:false, showConfirmButton:false, didOpen: () => Swal.showLoading() });

        const updateUrl = updateUrlTemplate.replace('__ID__', encodeURIComponent(String(selectedId)));
        fetch(updateUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(res.value)
        })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
            if (!ok) {
                const msg = (data && data.errors && data.errors.NoTelarId) ? data.errors.NoTelarId[0] : (data && data.message) || 'Error al actualizar';
                throw new Error(msg);
            }
            if (!data.success) throw new Error(data.message || 'Error al actualizar');
            Swal.fire({ icon:'success', title:'¡Registro actualizado!', timer:1800, showConfirmButton:false })
                .then(() => location.reload());
        })
        .catch(err => Swal.fire({ icon:'error', title:'Error', text: err.message || 'No se pudo actualizar el registro.' }));
    });
}

/* ===========================
   Eliminar
=========================== */
function eliminarSecuenciaCorteEficiencia() {
    if (!selectedRow || selectedId === null) {
        Swal.fire({ title:'Error', text:'Selecciona un registro para eliminar', icon:'warning' });
        return;
    }

    const notelarid = selectedRow.getAttribute('data-notelarid');
    const salon = selectedRow.getAttribute('data-salontejidoid');

    Swal.fire({
        title: '¿Eliminar Registro?',
        text: `¿Estás seguro de eliminar el telar ${notelarid} (${salon})?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash me-2"></i>Sí, eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
    }).then((result) => {
        if (!result.isConfirmed) return;

        Swal.fire({ title:'Eliminando...', allowOutsideClick:false, showConfirmButton:false, didOpen: () => Swal.showLoading() });

        const deleteUrl = deleteUrlTemplate.replace('__ID__', encodeURIComponent(String(selectedId)));
        fetch(deleteUrl, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
            if (!ok) throw new Error(data && data.message ? data.message : 'Error al eliminar');
            if (data && !data.success) throw new Error(data.message || 'Error al eliminar');
            Swal.fire({ icon:'success', title:'¡Registro eliminado!', timer:1800, showConfirmButton:false })
                .then(() => location.reload());
        })
        .catch(err => Swal.fire({ icon:'error', title:'Error', text: err.message || 'No se pudo eliminar el registro.' }));
    });
}

/* ===========================
   Drag and drop (reordenar)
=========================== */
let draggedRow = null;
let isDragging = false;

function handleDragStart(e) {
    const tr = e.target.closest('tr');
    if (!tr || !tr.classList.contains('secuencia-row')) return;
    draggedRow = tr;
    isDragging = true;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', tr.getAttribute('data-id'));
    tr.classList.add('dragging');
    e.dataTransfer.setDragImage(tr, 0, 0);
}

function handleDragEnd(e) {
    if (draggedRow) {
        draggedRow.classList.remove('dragging');
        draggedRow = null;
    }
    isDragging = false;
    document.querySelectorAll('#secuencia-corte-eficiencia-body tr.secuencia-row').forEach(r => {
        r.classList.remove('drag-over-top', 'drag-over-bottom');
    });
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    const tr = e.target.closest('tr');
    if (!tr || tr === draggedRow || !tr.classList.contains('secuencia-row')) return;
    document.querySelectorAll('#secuencia-corte-eficiencia-body tr.secuencia-row').forEach(r => {
        r.classList.remove('drag-over-top', 'drag-over-bottom');
    });
    const rect = tr.getBoundingClientRect();
    const mid = rect.top + rect.height / 2;
    if (e.clientY < mid) {
        tr.classList.add('drag-over-top');
    } else {
        tr.classList.add('drag-over-bottom');
    }
}

function handleDragLeave(e) {
    const tr = e.target.closest('tr');
    if (tr) tr.classList.remove('drag-over-top', 'drag-over-bottom');
}

function handleDrop(e) {
    e.preventDefault();
    const targetTr = e.target.closest('tr');
    if (!targetTr || !draggedRow || targetTr === draggedRow || !targetTr.classList.contains('secuencia-row')) return;
    targetTr.classList.remove('drag-over-top', 'drag-over-bottom');

    const tbody = document.getElementById('secuencia-corte-eficiencia-body');
    const rect = targetTr.getBoundingClientRect();
    const insertBefore = e.clientY < rect.top + rect.height / 2;

    if (insertBefore) {
        targetTr.parentNode.insertBefore(draggedRow, targetTr);
    } else {
        targetTr.parentNode.insertBefore(draggedRow, targetTr.nextSibling);
    }

    draggedRow.classList.remove('dragging');
    draggedRow.classList.add('drop-success');
    setTimeout(() => draggedRow.classList.remove('drop-success'), 500);

    renumberOrdenAndSave(tbody);
    draggedRow = null;
    isDragging = false;
}

function renumberOrdenAndSave(tbody) {
    const rows = tbody.querySelectorAll('tr.secuencia-row');
    const orden = [];
    rows.forEach((row, index) => {
        const num = index + 1;
        row.setAttribute('data-orden', num);
        row.querySelector('td:nth-child(4)').textContent = num;
        orden.push({ NoTelarId: parseInt(row.getAttribute('data-notelarid'), 10), Orden: num });
        row.classList.remove('bg-white', 'bg-gray-100');
        row.classList.add(index % 2 === 0 ? 'bg-white' : 'bg-gray-100');
    });
    if (selectedRow && selectedRow.parentNode === tbody) {
        selectedRow.classList.remove('bg-white', 'bg-gray-100');
        selectedRow.classList.add('bg-blue-500', 'text-white');
    }

    fetch('{{ route("tejido.secuencia-corte-eficiencia.orden") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ orden })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Orden guardado', showConfirmButton: false, timer: 1200 });
        } else {
            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'No se pudo guardar el orden' });
        }
    })
    .catch(() => {
        if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red al guardar el orden' });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    disableButtons();
    document.getElementById('secuencia-corte-eficiencia-body')?.addEventListener('click', (e) => {
        if (isDragging) e.stopPropagation();
    });
});
</script>
@endsection
