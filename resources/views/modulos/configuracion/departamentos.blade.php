@extends('layouts.app')

@section('page-title', 'Departamentos')

@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-create id="btn-nuevo" title="Nuevo departamento" text="Nuevo" />
    <x-navbar.button-edit id="btn-editar" title="Editar departamento" text="Editar" :disabled="true" />
    <x-navbar.button-delete id="btn-eliminar" title="Eliminar departamento" text="Eliminar" :disabled="true" />
</div>
@endsection

@section('content')
<div class="w-full px-4 py-4">
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-auto max-h-[calc(100vh-12rem)]">
            <table class="min-w-full text-base" id="tabla-departamentos">
                <thead class="sticky top-0 z-10 bg-blue-500 text-white shadow-sm">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Id</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Departamento</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Descripción</th>
                    </tr>
                </thead>
                <tbody id="tbody-departamentos">
                    @forelse($departamentos as $d)
                        <tr class="depto-row border-b border-gray-100 hover:bg-gray-50 cursor-pointer {{ $loop->even ? 'bg-gray-50/50' : '' }}"
                            data-id="{{ $d->id }}"
                            data-depto="{{ e($d->Depto) }}"
                            data-descripcion="{{ e($d->Descripcion ?? '') }}">
                            <td class="px-4 py-3 text-gray-700 text-base">{{ $d->id }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 text-base">{{ $d->Depto }}</td>
                            <td class="px-4 py-3 text-gray-600 text-base">{{ $d->Descripcion ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr id="tr-empty">
                            <td colspan="3" class="px-4 py-8 text-center text-gray-500 text-base">No hay departamentos registrados.</td>
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
            <h3 class="text-lg font-semibold text-gray-800" id="modal-title">Nuevo departamento</h3>
            <button type="button" id="modal-cerrar" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <form id="form-depto" method="POST">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">
            <div class="space-y-4">
                <div>
                    <label for="Depto" class="block text-sm font-medium text-gray-700 mb-1">Depto <span class="text-red-500">*</span></label>
                    <input type="text" name="Depto" id="Depto" required maxlength="100" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Ej. Atadores">
                </div>
                <div>
                    <label for="Descripcion" class="block text-sm font-medium text-gray-700 mb-1">Descripcion</label>
                    <input type="text" name="Descripcion" id="Descripcion" maxlength="255" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Opcional">
                </div>
            </div>
            <div class="mt-6 flex gap-2 justify-end">
                <button type="button" id="form-cancelar" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" id="form-submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
    const modal = document.getElementById('modal-form');
    const form = document.getElementById('form-depto');
    const modalTitle = document.getElementById('modal-title');
    const formMethod = document.getElementById('form-method');
    const tbody = document.getElementById('tbody-departamentos');
    const trEmpty = document.getElementById('tr-empty');
    const storeUrl = @json(route('configuracion.departamentos.store'));
    const updateUrlTpl = @json(route('configuracion.departamentos.update', ['id' => ':id']));
    const destroyUrlTpl = @json(route('configuracion.departamentos.destroy', ['id' => ':id']));
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
        document.getElementById('btn-editar').disabled = !hasSelection;
        document.getElementById('btn-eliminar').disabled = !hasSelection;
    }

    function clearSelection() {
        document.querySelectorAll('.depto-row.selected').forEach(r => {
            r.classList.remove('selected', 'bg-blue-500', 'text-white', 'hover:bg-blue-600');
            r.classList.add('hover:bg-gray-50');
        });
        document.querySelectorAll('.depto-row td').forEach(td => td.classList.remove('text-white'));
        selectedRow = null;
        setActionsState(false);
    }

    function selectRow(tr) {
        clearSelection();
        if (!tr || !tr.classList.contains('depto-row')) return;
        tr.classList.remove('hover:bg-gray-50');
        tr.classList.add('selected', 'bg-blue-500', 'text-white', 'hover:bg-blue-600');
        tr.querySelectorAll('td').forEach(td => td.classList.add('text-white'));
        selectedRow = tr;
        setActionsState(true);
    }

    tbody?.addEventListener('click', function(e) {
        const tr = e.target.closest('tr.depto-row');
        if (tr) selectRow(tr);
    });

    document.getElementById('btn-nuevo').addEventListener('click', function(){
        modalTitle.textContent = 'Nuevo departamento';
        form.action = storeUrl;
        formMethod.value = 'POST';
        form.reset();
        openModal();
    });

    document.getElementById('btn-editar').addEventListener('click', function(){
        if (!selectedRow) return;
        const id = selectedRow.dataset.id;
        const depto = selectedRow.dataset.depto || '';
        const descripcion = selectedRow.dataset.descripcion || '';
        modalTitle.textContent = 'Editar departamento';
        form.action = updateUrlTpl.replace(':id', id);
        formMethod.value = 'PUT';
        document.getElementById('Depto').value = depto;
        document.getElementById('Descripcion').value = descripcion;
        openModal();
    });

    document.getElementById('btn-eliminar').addEventListener('click', function(){
        if (!selectedRow) return;
        const id = selectedRow.dataset.id;
        const depto = selectedRow.dataset.depto || 'este registro';
        Swal.fire({
            title: '¿Eliminar departamento?',
            text: 'Se eliminará "' + depto + '".',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(async r => {
            if (!r.isConfirmed) return;
            try {
                const fd = new FormData();
                fd.append('_token', csrf);
                fd.append('_method', 'DELETE');
                const res = await fetch(destroyUrlTpl.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: fd
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.ok) {
                    selectedRow.remove();
                    selectedRow = null;
                    setActionsState(false);
                    if (tbody.querySelectorAll('tr.depto-row').length === 0) {
                        const empty = document.createElement('tr');
                        empty.id = 'tr-empty';
                        empty.innerHTML = '<td colspan="3" class="px-4 py-8 text-center text-gray-500">No hay departamentos registrados.</td>';
                        tbody.appendChild(empty);
                    }
                    Swal.fire({ icon: 'success', title: data.message || 'Eliminado', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'error', title: data.message || 'No se pudo eliminar' });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error de conexión' });
            }
        });
    });

    document.getElementById('modal-cerrar').addEventListener('click', closeModal);
    document.getElementById('form-cancelar').addEventListener('click', closeModal);

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const isPut = formMethod.value === 'PUT';
        const url = form.action;
        const body = new FormData(form);
        if (isPut) body.append('_method', 'PUT');

        const submitBtn = document.getElementById('form-submit');
        submitBtn.disabled = true;

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf
                },
                body: body
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok && data.ok) {
                closeModal();
                const item = data.item || {};
                if (isPut && selectedRow) {
                    selectedRow.dataset.depto = item.Depto || '';
                    selectedRow.dataset.descripcion = item.Descripcion || '';
                    selectedRow.cells[0].textContent = item.id;
                    selectedRow.cells[1].textContent = item.Depto;
                    selectedRow.cells[2].textContent = item.Descripcion || '—';
                } else {
                    if (trEmpty) trEmpty.remove();
                    const even = tbody.querySelectorAll('tr.depto-row').length % 2 === 0;
                    const tr = document.createElement('tr');
                    tr.className = 'depto-row border-b border-gray-100 hover:bg-gray-50 cursor-pointer ' + (even ? 'bg-gray-50/50' : '');
                    tr.dataset.id = item.id;
                    tr.dataset.depto = item.Depto || '';
                    tr.dataset.descripcion = item.Descripcion || '';
                    tr.innerHTML = '<td class="px-4 py-3 text-gray-700 text-base">' + (item.id || '') + '</td><td class="px-4 py-3 font-medium text-gray-900 text-base">' + (item.Depto || '') + '</td><td class="px-4 py-3 text-gray-600 text-base">' + (item.Descripcion || '—') + '</td>';
                    tbody.appendChild(tr);
                }
                Swal.fire({ icon: 'success', title: data.message || 'Guardado', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
            } else {
                const msg = (data.errors && Object.values(data.errors).flat().length) ? Object.values(data.errors).flat().join(' ') : (data.message || 'No se pudo guardar');
                Swal.fire({ icon: 'error', title: msg });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error de conexión' });
        }
        submitBtn.disabled = false;
    });

    setActionsState(false);
})();
</script>
@endsection
