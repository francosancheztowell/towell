@extends('layouts.app')

@section('page-title', 'Matriz de Hilos')

@section('navbar-right')
<x-action-buttons route="matriz-hilos" :showFilters="false" />
@endsection

@section('content')
<div class="container-fluid px-4 py-6 -mt-6">

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto h-[600px]">
            <table id="mainTable" class="border-collapse w-full">
                <thead>
                    <tr class="border border-gray-300 px-2 py-2 text-center font-light text-white text-sm bg-blue-500">
                        <th class="py-2 px-4">Hilo</th>
                        <th class="py-2 px-4">Calibre</th>
                        <th class="py-2 px-4">Calibre2</th>
                        <th class="py-2 px-4">CalibreAX</th>
                        <th class="py-2 px-4">Fibra</th>
                        <th class="py-2 px-4">CodColor</th>
                        <th class="py-2 px-4">NombreColor</th>
                    </tr>
                </thead>
                <tbody id="matriz-hilos-body" class="bg-white text-black">
                    @foreach ($matrizHilos as $item)
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer border-b"
                            onclick="selectRow(this, '{{ $item->id }}')"
                            ondblclick="deselectRow(this)"
                            data-id="{{ $item->id }}"
                            data-hilo="{{ $item->Hilo }}"
                            data-calibre="{{ $item->Calibre }}"
                            data-calibre2="{{ $item->Calibre2 }}"
                            data-calibreax="{{ $item->CalibreAX }}"
                            data-fibra="{{ $item->Fibra }}"
                            data-codcolor="{{ $item->CodColor }}"
                            data-nombrecolor="{{ $item->NombreColor }}"
                        >
                            <td class="py-2 px-4 border-b">{{ $item->Hilo }}</td>
                            <td class="py-2 px-4 border-b">{{ $item->Calibre ? number_format($item->Calibre, 4) : '' }}</td>
                            <td class="py-2 px-4 border-b">{{ $item->Calibre2 ? number_format($item->Calibre2, 4) : '' }}</td>
                            <td class="py-2 px-4 border-b">{{ $item->CalibreAX }}</td>
                            <td class="py-2 px-4 border-b">{{ $item->Fibra }}</td>
                            <td class="py-2 px-4 border-b">{{ $item->CodColor }}</td>
                            <td class="py-2 px-4 border-b">{{ $item->NombreColor }}</td>
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

let datosOriginales = @json($matrizHilos);
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
        e.className = 'inline-flex items-center px-3 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg text-sm font-medium';
    }
    if (d) {
        d.disabled = false;
        d.className = 'inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium';
    }
}

function disableButtons() {
    const e = document.getElementById('btn-editar');
    const d = document.getElementById('btn-eliminar');
    if (e) {
        e.disabled = true;
        e.className = 'inline-flex items-center px-3 py-2 bg-gray-400 text-gray-200 rounded-lg text-sm font-medium cursor-not-allowed';
    }
    if (d) {
        d.disabled = true;
        d.className = 'inline-flex items-center px-3 py-2 bg-gray-400 text-gray-200 rounded-lg text-sm font-medium cursor-not-allowed';
    }
}

/* ===========================
   Selección de filas
=========================== */
function selectRow(row, id) {
    document.querySelectorAll('#matriz-hilos-body tr').forEach(r => {
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
function agregarMatrizHilo() {
    Swal.fire({
        title: 'Crear Nueva Matriz de Hilos',
        html: `
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Hilo *</label>
                    <input id="swal-hilo" type="text" class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           placeholder="Ej: H001" maxlength="30" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Calibre</label>
                    <input id="swal-calibre" type="number" step="0.0001"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           placeholder="0.0000">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Calibre2</label>
                    <input id="swal-calibre2" type="number" step="0.0001"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           placeholder="0.0000">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">CalibreAX</label>
                    <input id="swal-calibreax" type="text" maxlength="20"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           placeholder="Calibre AX">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Fibra</label>
                    <input id="swal-fibra" type="text" maxlength="30"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           placeholder="Tipo de fibra">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">CodColor</label>
                    <input id="swal-codcolor" type="text" maxlength="10"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           placeholder="Código color">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">NombreColor</label>
                    <input id="swal-nombrecolor" type="text" maxlength="60"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           placeholder="Nombre del color">
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
            const Hilo = document.getElementById('swal-hilo').value.trim();
            const Calibre = document.getElementById('swal-calibre').value;
            const Calibre2 = document.getElementById('swal-calibre2').value;
            const CalibreAX = document.getElementById('swal-calibreax').value.trim();
            const Fibra = document.getElementById('swal-fibra').value.trim();
            const CodColor = document.getElementById('swal-codcolor').value.trim();
            const NombreColor = document.getElementById('swal-nombrecolor').value.trim();

            if (!Hilo) {
                Swal.showValidationMessage('El campo Hilo es requerido');
                return false;
            }

            return {
                Hilo,
                Calibre: Calibre ? parseFloat(Calibre) : null,
                Calibre2: Calibre2 ? parseFloat(Calibre2) : null,
                CalibreAX: CalibreAX || null,
                Fibra: Fibra || null,
                CodColor: CodColor || null,
                NombreColor: NombreColor || null
            };
        }
    }).then((res) => {
        if (!res.isConfirmed) return;
        Swal.fire({ title: 'Creando...', allowOutsideClick: false, showConfirmButton: false, didOpen: Swal.showLoading });

        fetch('/planeacion/catalogos/matriz-hilos', {
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
function editarMatrizHilo() {
    if (!selectedRow || !selectedId) {
        Swal.fire({ title:'Error', text:'Por favor selecciona un registro para editar', icon:'warning' });
        return;
    }

    const hiloActual = selectedRow.getAttribute('data-hilo');
    const calibreActual = selectedRow.getAttribute('data-calibre');
    const calibre2Actual = selectedRow.getAttribute('data-calibre2');
    const calibreaxActual = selectedRow.getAttribute('data-calibreax');
    const fibraActual = selectedRow.getAttribute('data-fibra');
    const codcolorActual = selectedRow.getAttribute('data-codcolor');
    const nombrecolorActual = selectedRow.getAttribute('data-nombrecolor');

    Swal.fire({
        title: 'Editar Matriz de Hilos',
        html: `
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Hilo *</label>
                    <input id="swal-hilo-edit" type="text" class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           maxlength="30" required value="${hiloActual}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Calibre</label>
                    <input id="swal-calibre-edit" type="number" step="0.0001"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           value="${calibreActual || ''}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Calibre2</label>
                    <input id="swal-calibre2-edit" type="number" step="0.0001"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           value="${calibre2Actual || ''}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">CalibreAX</label>
                    <input id="swal-calibreax-edit" type="text" maxlength="20"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           value="${calibreaxActual || ''}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Fibra</label>
                    <input id="swal-fibra-edit" type="text" maxlength="30"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           value="${fibraActual || ''}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">CodColor</label>
                    <input id="swal-codcolor-edit" type="text" maxlength="10"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           value="${codcolorActual || ''}">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">NombreColor</label>
                    <input id="swal-nombrecolor-edit" type="text" maxlength="60"
                           class="w-full px-2 py-2 border border-gray-300 rounded text-center"
                           value="${nombrecolorActual || ''}">
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
            const Hilo = document.getElementById('swal-hilo-edit').value.trim();
            const Calibre = document.getElementById('swal-calibre-edit').value;
            const Calibre2 = document.getElementById('swal-calibre2-edit').value;
            const CalibreAX = document.getElementById('swal-calibreax-edit').value.trim();
            const Fibra = document.getElementById('swal-fibra-edit').value.trim();
            const CodColor = document.getElementById('swal-codcolor-edit').value.trim();
            const NombreColor = document.getElementById('swal-nombrecolor-edit').value.trim();

            if (!Hilo) {
                Swal.showValidationMessage('El campo Hilo es requerido');
                return false;
            }

            return {
                Hilo,
                Calibre: Calibre ? parseFloat(Calibre) : null,
                Calibre2: Calibre2 ? parseFloat(Calibre2) : null,
                CalibreAX: CalibreAX || null,
                Fibra: Fibra || null,
                CodColor: CodColor || null,
                NombreColor: NombreColor || null
            };
        }
    }).then((res) => {
        if (!res.isConfirmed) return;
        Swal.fire({ title:'Actualizando...', allowOutsideClick:false, showConfirmButton:false, didOpen:Swal.showLoading });

        fetch(`/planeacion/catalogos/matriz-hilos/${selectedId}`, {
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
function eliminarMatrizHilo() {
    if (!selectedRow || !selectedId) {
        Swal.fire({ title:'Error', text:'Selecciona un registro para eliminar', icon:'warning' });
        return;
    }

    Swal.fire({
        title: '¿Eliminar Registro?',
        text: `¿Estás seguro de eliminar el hilo "${selectedRow.getAttribute('data-hilo')}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash me-2"></i>Sí, eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
    }).then((result) => {
        if (!result.isConfirmed) return;

        Swal.fire({ title:'Eliminando...', allowOutsideClick:false, showConfirmButton:false, didOpen:Swal.showLoading });

        fetch(`/planeacion/catalogos/matriz-hilos/${selectedId}`, {
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

// Aliases globales para coincidir con los handlers generados por action-buttons (matriz_hilos)
window.agregarMatriz_hilos = agregarMatrizHilo;
window.editarMatriz_hilos = editarMatrizHilo;
window.eliminarMatriz_hilos = eliminarMatrizHilo;
</script>
@endsection
