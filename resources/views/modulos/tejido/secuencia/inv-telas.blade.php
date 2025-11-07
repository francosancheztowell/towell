@extends('layouts.app')

@section('page-title', 'Secuencia de Telas')

@section('navbar-right')
    <x-botones-inventarios-secuencias
        modulo="Secuencia Inv Telas"
        onCreate="agregarSecuenciaInvTelas"
        onEdit="editarSecuenciaInvTelas"
        onDelete="eliminarSecuenciaInvTelas"
    />
@endsection

@section('content')
<div class="container-fluid px-4 py-6 -mt-6">
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto h-[600px]">
            <table id="mainTable" class="border-collapse w-full">
                <thead>
                    <tr class="border border-gray-300 px-2 py-2 text-center font-light text-white text-sm bg-blue-500">
                        <th class="py-2 px-4">NoTelar</th>
                        <th class="py-2 px-4">TipoTelar</th>
                        <th class="py-2 px-4">Secuencia</th>

                    </tr>
                </thead>
                <tbody id="secuencia-inv-telas-body" class="bg-white text-black">
                    @foreach ($registros as $item)
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer border-b"
                            onclick="selectRow(this, '{{ $item->Id }}')"
                            ondblclick="deselectRow(this)"
                            data-id="{{ $item->Id }}"
                            data-notelar="{{ $item->NoTelar }}"
                            data-tipotelar="{{ $item->TipoTelar }}"
                            data-secuencia="{{ $item->Secuencia }}"
                            data-observaciones="{{ $item->Observaciones ?? '' }}"
                        >
                            <td class="py-2 px-4 border-b">{{ $item->NoTelar }}</td>
                            <td class="py-2 px-4 border-b">{{ $item->TipoTelar }}</td>
                            <td class="py-2 px-4 border-b">{{ $item->Secuencia }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
/* ===========================
   Estado y datos
=========================== */
let selectedRow = null;
let selectedId = null;

let datosOriginales = @json($registros);
let datosActuales = datosOriginales;

/* ===========================
   Helpers UI
=========================== */
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
    document.querySelectorAll('#secuencia-inv-telas-body tr').forEach(r => {
        r.classList.remove('bg-blue-500','text-white');
        r.classList.add('hover:bg-blue-50');
    });
    row.classList.remove('hover:bg-blue-50');
    row.classList.add('bg-blue-500','text-white');

    selectedRow = row;
    selectedId = id;
    enableButtons();
}

function deselectRow(row) {
    if (!row.classList.contains('bg-blue-500')) return;
    row.classList.remove('bg-blue-500','text-white');
    row.classList.add('hover:bg-blue-50');
    selectedRow = null;
    selectedId = null;
    disableButtons();
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
});
</script>
@endsection

