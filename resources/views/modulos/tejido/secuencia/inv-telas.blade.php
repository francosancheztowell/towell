@extends('layouts.app')

@section('page-title', 'Secuencia de Telas')

@section('navbar-right')
    <x-buttons.inventory-sequence-actions
        modulo="Secuencia Inv Telas"
        onCreate="agregarSecuenciaInvTelas"
        onEdit="editarSecuenciaInvTelas"
        onDelete="eliminarSecuenciaInvTelas"
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
                        <th class="py-3 px-4 text-center">Telar</th>
                        <th class="py-3 px-4 text-center">Tipo telar</th>
                        <th class="py-3 px-4 text-center">Secuencia</th>
                    </tr>
                </thead>
                <tbody id="secuencia-inv-telas-body">
                    @foreach ($registros as $index => $item)
                        <tr class="secuencia-row text-center transition cursor-pointer {{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-100' }} hover:opacity-90"
                            draggable="true"
                            ondragstart="handleDragStart(event)"
                            ondragend="handleDragEnd(event)"
                            ondragover="handleDragOver(event)"
                            ondragleave="handleDragLeave(event)"
                            ondrop="handleDrop(event)"
                            data-id="{{ $item->Id }}"
                            data-notelar="{{ $item->NoTelar }}"
                            data-tipotelar="{{ $item->TipoTelar }}"
                            data-secuencia="{{ $item->Secuencia }}"
                            data-observaciones="{{ $item->Observaciones ?? '' }}"
                            onclick="selectRow(this, '{{ $item->Id }}')"
                            ondblclick="deselectRow(this)"
                        >
                            <td class="py-3 px-2 text-gray-400 cursor-grab active:cursor-grabbing" title="Arrastrar para reordenar">
                                <i class="fas fa-grip-vertical"></i>
                            </td>
                            <td class="py-3 px-4">{{ $item->NoTelar }}</td>
                            <td class="py-3 px-4">{{ $item->TipoTelar }}</td>
                            <td class="py-3 px-4">{{ $item->Secuencia }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.secuencia-row { transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, opacity 0.2s ease; }
.secuencia-row.dragging { opacity: 0.6; transform: scale(0.98); box-shadow: 0 8px 24px rgba(0,0,0,0.15); background-color: #dbeafe !important; z-index: 10; }
.secuencia-row.drag-over-top { box-shadow: 0 -4px 0 0 #3b82f6 inset; background-color: #eff6ff !important; }
.secuencia-row.drag-over-bottom { box-shadow: 0 4px 0 0 #3b82f6 inset; background-color: #eff6ff !important; }
.secuencia-row.drop-success { animation: dropSuccess 0.5s ease; }
@keyframes dropSuccess { 0% { background-color: #bbf7d0 !important; } 100% { background-color: inherit; } }
</style>

<script>
/* ===========================
   Estado y datos
=========================== */
let selectedRow = null;
let selectedId = null;
let draggedRow = null;
let isDragging = false;

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
    const tbody = document.getElementById('secuencia-inv-telas-body');
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
    const tbody = document.getElementById('secuencia-inv-telas-body');
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
   Drag and drop (reordenar)
=========================== */
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
    document.querySelectorAll('#secuencia-inv-telas-body tr.secuencia-row').forEach(r => {
        r.classList.remove('drag-over-top', 'drag-over-bottom');
    });
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    const tr = e.target.closest('tr');
    if (!tr || tr === draggedRow || !tr.classList.contains('secuencia-row')) return;
    document.querySelectorAll('#secuencia-inv-telas-body tr.secuencia-row').forEach(r => {
        r.classList.remove('drag-over-top', 'drag-over-bottom');
    });
    const rect = tr.getBoundingClientRect();
    if (e.clientY < rect.top + rect.height / 2) tr.classList.add('drag-over-top');
    else tr.classList.add('drag-over-bottom');
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
    const tbody = document.getElementById('secuencia-inv-telas-body');
    const rect = targetTr.getBoundingClientRect();
    const insertBefore = e.clientY < rect.top + rect.height / 2;
    if (insertBefore) targetTr.parentNode.insertBefore(draggedRow, targetTr);
    else targetTr.parentNode.insertBefore(draggedRow, targetTr.nextSibling);
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
        row.setAttribute('data-secuencia', num);
        row.querySelector('td:nth-child(4)').textContent = num;
        orden.push({ Id: parseInt(row.getAttribute('data-id'), 10), Secuencia: num });
        row.classList.remove('bg-white', 'bg-gray-100');
        row.classList.add(index % 2 === 0 ? 'bg-white' : 'bg-gray-100');
    });
    fetch('{{ route("tejido.secuencia-inv-telas.orden") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ orden })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && typeof Swal !== 'undefined')
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Orden guardado', showConfirmButton: false, timer: 1200 });
        else if (data && !data.success && typeof Swal !== 'undefined')
            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'No se pudo guardar el orden' });
    })
    .catch(() => { if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red al guardar el orden' }); });
}

/* ===========================
   Crear (Agregar)
=========================== */
function agregarSecuenciaInvTelas() {
    Swal.fire({
        title: 'Crear Nueva Secuencia Inv Telas',
        html: `
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">NoTelar *</label>
                    <input id="swal-notelar" type="number" class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           placeholder="Ej: 201" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">TipoTelar *</label>
                    <select id="swal-tipotelar" class="w-full px-2 py-2 border border-gray-300 rounded text-center" required>
                        <option value="">Seleccione...</option>
                        <option value="JACQUARD">JACQUARD</option>
                        <option value="ITEMA">ITEMA</option>
                        <option value="SULZER">SULZER</option>
                        <option value="SMIT">SMIT</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Secuencia *</label>
                    <input id="swal-secuencia" type="number" step="1"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           placeholder="Ej: 1" required>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Observaciones</label>
                    <textarea id="swal-observaciones" rows="3" maxlength="500"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           placeholder="Observaciones (opcional)"></textarea>
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
            const NoTelar = document.getElementById('swal-notelar').value;
            const TipoTelar = document.getElementById('swal-tipotelar').value.trim();
            const Secuencia = document.getElementById('swal-secuencia').value;
            const Observaciones = document.getElementById('swal-observaciones').value.trim();

            if (!NoTelar) {
                Swal.showValidationMessage('El campo NoTelar es requerido');
                return false;
            }
            if (!TipoTelar) {
                Swal.showValidationMessage('El campo TipoTelar es requerido');
                return false;
            }
            if (!Secuencia) {
                Swal.showValidationMessage('El campo Secuencia es requerido');
                return false;
            }

            return {
                NoTelar: parseInt(NoTelar),
                TipoTelar,
                Secuencia: parseInt(Secuencia),
                Observaciones: Observaciones || null
            };
        }
    }).then((res) => {
        if (!res.isConfirmed) return;
        Swal.fire({ title: 'Creando...', allowOutsideClick: false, showConfirmButton: false, didOpen: Swal.showLoading });

        fetch('{{ route("tejido.secuencia-inv-telas.store") }}', {
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
            Swal.fire({ icon:'success', title:'¡Registro creado!', timer:2000, showConfirmButton:false })
                .then(() => location.reload());
        })
        .catch(err => Swal.fire({ icon:'error', title:'Error', text: err.message || 'Error al crear el registro.' }));
    });
}

/* ===========================
   Editar
=========================== */
function editarSecuenciaInvTelas() {
    if (!selectedRow || !selectedId) {
        Swal.fire({ title:'Error', text:'Por favor selecciona un registro para editar', icon:'warning' });
        return;
    }

    const notelarActual = selectedRow.getAttribute('data-notelar');
    const tipotelarActual = selectedRow.getAttribute('data-tipotelar');
    const secuenciaActual = selectedRow.getAttribute('data-secuencia');
    const observacionesActual = selectedRow.getAttribute('data-observaciones') || '';

    Swal.fire({
        title: 'Editar Secuencia Inv Telas',
        html: `
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">NoTelar *</label>
                    <input id="swal-notelar-edit" type="number" class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           required value="${notelarActual}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">TipoTelar *</label>
                    <select id="swal-tipotelar-edit" class="w-full px-2 py-2 border border-gray-300 rounded text-center" required>
                        <option value="">Seleccione...</option>
                        <option value="JACQUARD" ${tipotelarActual === 'JACQUARD' ? 'selected' : ''}>JACQUARD</option>
                        <option value="ITEMA" ${tipotelarActual === 'ITEMA' ? 'selected' : ''}>ITEMA</option>
                        <option value="SULZER" ${tipotelarActual === 'SULZER' ? 'selected' : ''}>SULZER</option>
                        <option value="SMIT" ${tipotelarActual === 'SMIT' ? 'selected' : ''}>SMIT</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Secuencia *</label>
                    <input id="swal-secuencia-edit" type="number" step="1"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           required value="${secuenciaActual}">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Observaciones</label>
                    <textarea id="swal-observaciones-edit" rows="3" maxlength="500"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           placeholder="Observaciones (opcional)">${observacionesActual}</textarea>
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
            const NoTelar = document.getElementById('swal-notelar-edit').value;
            const TipoTelar = document.getElementById('swal-tipotelar-edit').value.trim();
            const Secuencia = document.getElementById('swal-secuencia-edit').value;
            const Observaciones = document.getElementById('swal-observaciones-edit').value.trim();

            if (!NoTelar) {
                Swal.showValidationMessage('El campo NoTelar es requerido');
                return false;
            }
            if (!TipoTelar) {
                Swal.showValidationMessage('El campo TipoTelar es requerido');
                return false;
            }
            if (!Secuencia) {
                Swal.showValidationMessage('El campo Secuencia es requerido');
                return false;
            }

            return {
                NoTelar: parseInt(NoTelar),
                TipoTelar,
                Secuencia: parseInt(Secuencia),
                Observaciones: Observaciones || null
            };
        }
    }).then((res) => {
        if (!res.isConfirmed) return;
        Swal.fire({ title:'Actualizando...', allowOutsideClick:false, showConfirmButton:false, didOpen:Swal.showLoading });

        fetch(`{{ route("tejido.secuencia-inv-telas.update", ":id") }}`.replace(':id', selectedId), {
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
            Swal.fire({ icon:'success', title:'¡Registro actualizado!', timer:1800, showConfirmButton:false })
                .then(() => location.reload());
        })
        .catch(err => Swal.fire({ icon:'error', title:'Error', text: err.message || 'No se pudo actualizar el registro.' }));
    });
}

/* ===========================
   Eliminar
=========================== */
function eliminarSecuenciaInvTelas() {
    if (!selectedRow || !selectedId) {
        Swal.fire({ title:'Error', text:'Selecciona un registro para eliminar', icon:'warning' });
        return;
    }

    const notelar = selectedRow.getAttribute('data-notelar');
    const tipotelar = selectedRow.getAttribute('data-tipotelar');

    Swal.fire({
        title: '¿Eliminar Registro?',
        text: `¿Estás seguro de eliminar el telar ${notelar} (${tipotelar})?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash me-2"></i>Sí, eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
    }).then((result) => {
        if (!result.isConfirmed) return;

        Swal.fire({ title:'Eliminando...', allowOutsideClick:false, showConfirmButton:false, didOpen:Swal.showLoading });

        fetch(`{{ route("tejido.secuencia-inv-telas.destroy", ":id") }}`.replace(':id', selectedId), {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t || `HTTP ${r.status}`); }))
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Error al eliminar');
            Swal.fire({ icon:'success', title:'¡Registro eliminado!', timer:1800, showConfirmButton:false })
                .then(() => location.reload());
        })
        .catch(err => Swal.fire({ icon:'error', title:'Error', text: err.message || 'No se pudo eliminar el registro.' }));
    });
}

/* ===========================
   Bootstrap
=========================== */
document.addEventListener('DOMContentLoaded', () => {
    disableButtons();
    document.getElementById('secuencia-inv-telas-body')?.addEventListener('click', (e) => {
        if (isDragging) e.stopPropagation();
    });
});
</script>
@endsection

