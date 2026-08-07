@extends('layouts.app')

@section('page-title', 'Actividades mecánicos')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create id="btn-nuevo" title="Nueva actividad" text="Nuevo" :module="$moduloPermiso" />
        <x-navbar.button-edit id="btn-editar" title="Editar actividad" text="Editar" :module="$moduloPermiso" :disabled="true" />
        <x-navbar.button-delete id="btn-eliminar" title="Eliminar actividad" text="Eliminar" :module="$moduloPermiso" :disabled="true" />
    </div>
@endsection

@section('content')
<div class="w-full px-4 py-4 space-y-4">

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-auto max-h-[calc(100vh-14rem)]">
            <table class="min-w-full text-base" id="tabla-actividades">
                <thead class="sticky top-0 z-10 bg-blue-500 text-white shadow-sm">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base w-24">Orden</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Actividad</th>
                    </tr>
                </thead>
                <tbody id="tbody-actividades">
                    @forelse($actividades as $actividad)
                        <tr class="actividad-row border-b border-gray-100 hover:bg-gray-50 cursor-pointer {{ $loop->even ? 'bg-gray-50/50' : '' }}"
                            data-id="{{ $actividad->Id }}"
                            data-orden="{{ $actividad->Orden }}"
                            data-actividad="{{ e($actividad->Actividad) }}">
                            <td class="px-4 py-3 text-gray-700 text-base">{{ $actividad->Orden }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 text-base">{{ $actividad->Actividad }}</td>
                        </tr>
                    @empty
                        <tr id="tr-empty">
                            <td colspan="2" class="px-4 py-8 text-center text-gray-500 text-base">No hay actividades registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Nuevo / Editar --}}
<div id="modal-form" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800" id="modal-title">Nueva actividad</h3>
            <button type="button" id="modal-cerrar" class="text-gray-400 hover:text-gray-600 text-2xl leading-none" aria-label="Cerrar">&times;</button>
        </div>
        <form id="form-actividad" method="POST">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">
            <div class="space-y-4">
                <div>
                    <label for="Orden" class="block text-sm font-medium text-gray-700 mb-1">Orden <span class="text-red-500">*</span></label>
                    <input type="number" name="Orden" id="Orden" required step="1"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="1, 2, 3…">
                </div>
                <div>
                    <label for="Actividad" class="block text-sm font-medium text-gray-700 mb-1">Actividad <span class="text-red-500">*</span></label>
                    <input type="text" name="Actividad" id="Actividad" required maxlength="100"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Nombre de la actividad">
                </div>
            </div>
            <div class="mt-6 flex gap-2 justify-end">
                <button type="submit" id="form-submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Guardar</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const modal = document.getElementById('modal-form');
    const form = document.getElementById('form-actividad');
    const modalTitle = document.getElementById('modal-title');
    const formMethod = document.getElementById('form-method');
    const tbody = document.getElementById('tbody-actividades');
    const storeUrl = @json(route('mecanicos.catalogos.actividades.store'));
    const updateUrlTpl = @json(route('mecanicos.catalogos.actividades.update', ['id' => '__ID__']));
    const destroyUrlTpl = @json(route('mecanicos.catalogos.actividades.destroy', ['id' => '__ID__']));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let selectedRow = null;

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function setActionsState(hasSelection) {
        const btnEdit = document.getElementById('btn-editar');
        const btnDelete = document.getElementById('btn-eliminar');
        if (btnEdit) btnEdit.disabled = !hasSelection;
        if (btnDelete) btnDelete.disabled = !hasSelection;
    }

    function clearSelection() {
        document.querySelectorAll('.actividad-row.selected').forEach(r => {
            r.classList.remove('selected', 'bg-blue-500', 'text-white', 'hover:bg-blue-600');
            r.classList.add('hover:bg-gray-50');
        });
        document.querySelectorAll('.actividad-row td').forEach(td => td.classList.remove('text-white'));
        selectedRow = null;
        setActionsState(false);
    }

    function selectRow(tr) {
        clearSelection();
        if (!tr || !tr.classList.contains('actividad-row')) return;
        tr.classList.remove('hover:bg-gray-50');
        tr.classList.add('selected', 'bg-blue-500', 'text-white', 'hover:bg-blue-600');
        tr.querySelectorAll('td').forEach(td => td.classList.add('text-white'));
        selectedRow = tr;
        setActionsState(true);
    }

    function ensureEmptyRow() {
        if (tbody.querySelectorAll('tr.actividad-row').length > 0) return;
        if (document.getElementById('tr-empty')) return;
        const empty = document.createElement('tr');
        empty.id = 'tr-empty';
        empty.innerHTML = '<td colspan="2" class="px-4 py-8 text-center text-gray-500 text-base">No hay actividades registradas.</td>';
        tbody.appendChild(empty);
    }

    function sortRowsByOrden() {
        const rows = Array.from(tbody.querySelectorAll('tr.actividad-row'));
        rows.sort((a, b) => {
            const ordenA = parseInt(a.dataset.orden || '0', 10);
            const ordenB = parseInt(b.dataset.orden || '0', 10);
            if (ordenA !== ordenB) return ordenA - ordenB;
            return parseInt(a.dataset.id || '0', 10) - parseInt(b.dataset.id || '0', 10);
        });
        rows.forEach(row => tbody.appendChild(row));
    }

    function toastSuccess(message) {
        if (window.notify?.success) {
            notify.success(message);
            return;
        }
        Swal.fire({ icon: 'success', title: message, toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
    }

    function toastError(message) {
        if (window.notify?.error) {
            notify.error(message);
            return;
        }
        Swal.fire({ icon: 'error', title: message });
    }

    async function confirmDelete(message) {
        if (window.notify?.confirm) {
            return notify.confirm({
                title: '¿Eliminar actividad?',
                text: message,
                confirmText: 'Sí, eliminar',
                cancelText: 'Cancelar',
                confirmColor: '#dc2626',
            });
        }
        const result = await Swal.fire({
            title: '¿Eliminar actividad?',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        });
        return result.isConfirmed;
    }

    tbody?.addEventListener('click', function (e) {
        const tr = e.target.closest('tr.actividad-row');
        if (tr) selectRow(tr);
    });

    document.getElementById('btn-nuevo')?.addEventListener('click', function () {
        modalTitle.textContent = 'Nueva actividad';
        form.action = storeUrl;
        formMethod.value = 'POST';
        form.reset();
        openModal();
        document.getElementById('Orden')?.focus();
    });

    document.getElementById('btn-editar')?.addEventListener('click', function () {
        if (!selectedRow) return;
        modalTitle.textContent = 'Editar actividad';
        form.action = updateUrlTpl.replace('__ID__', selectedRow.dataset.id);
        formMethod.value = 'PUT';
        document.getElementById('Orden').value = selectedRow.dataset.orden || '';
        document.getElementById('Actividad').value = selectedRow.dataset.actividad || '';
        openModal();
        document.getElementById('Orden')?.focus();
    });

    document.getElementById('btn-eliminar')?.addEventListener('click', async function () {
        if (!selectedRow) return;
        const id = selectedRow.dataset.id;
        const nombre = selectedRow.dataset.actividad || 'esta actividad';
        const confirmed = await confirmDelete('Se eliminará "' + nombre + '".');
        if (!confirmed) return;

        try {
            const fd = new FormData();
            fd.append('_token', csrf);
            fd.append('_method', 'DELETE');
            let data;
            try {
                data = await http.post(destroyUrlTpl.replace('__ID__', id), fd);
            } catch (e) {
                if (!e.status) throw e;
                data = e.data || {};
            }
            if (data.ok) {
                selectedRow.remove();
                selectedRow = null;
                setActionsState(false);
                ensureEmptyRow();
                toastSuccess(data.message || 'Eliminado');
            } else {
                toastError(data.message || 'No se pudo eliminar');
            }
        } catch (err) {
            toastError(err?.message || 'Error de conexión');
        }
    });

    document.getElementById('modal-cerrar')?.addEventListener('click', closeModal);

    modal?.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    form?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const isPut = formMethod.value === 'PUT';
        const url = form.action;
        const body = new FormData(form);
        if (isPut) body.append('_method', 'PUT');

        const submitBtn = document.getElementById('form-submit');
        submitBtn.disabled = true;

        try {
            let data;
            try {
                data = await http.post(url, body);
            } catch (err) {
                if (!err.status) throw err;
                data = err.data || {};
                if (err.status === 422 && window.notify?.validation && err.errors) {
                    notify.validation(err.errors);
                    submitBtn.disabled = false;
                    return;
                }
            }

            if (data.ok) {
                closeModal();
                const item = data.item || {};
                document.getElementById('tr-empty')?.remove();

                if (isPut && selectedRow) {
                    selectedRow.dataset.orden = item.Orden ?? '';
                    selectedRow.dataset.actividad = item.Actividad || '';
                    selectedRow.cells[0].textContent = item.Orden ?? '';
                    selectedRow.cells[1].textContent = item.Actividad || '';
                    sortRowsByOrden();
                } else {
                    const even = tbody.querySelectorAll('tr.actividad-row').length % 2 === 0;
                    const tr = document.createElement('tr');
                    tr.className = 'actividad-row border-b border-gray-100 hover:bg-gray-50 cursor-pointer ' + (even ? 'bg-gray-50/50' : '');
                    tr.dataset.id = item.Id;
                    tr.dataset.orden = item.Orden ?? '';
                    tr.dataset.actividad = item.Actividad || '';
                    tr.innerHTML =
                        '<td class="px-4 py-3 text-gray-700 text-base">' + (item.Orden ?? '') + '</td>' +
                        '<td class="px-4 py-3 font-medium text-gray-900 text-base">' + (item.Actividad || '') + '</td>';
                    tbody.appendChild(tr);
                    sortRowsByOrden();
                }
                toastSuccess(data.message || 'Guardado');
            } else {
                const msg = (data.errors && Object.values(data.errors).flat().length)
                    ? Object.values(data.errors).flat().join(' ')
                    : (data.message || 'No se pudo guardar');
                toastError(msg);
            }
        } catch (err) {
            toastError(err?.message || 'Error de conexión');
        }
        submitBtn.disabled = false;
    });

    setActionsState(false);
})();
</script>
@endpush
