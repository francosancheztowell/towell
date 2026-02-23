@extends('layouts.app')

@section('page-title', 'Secuencia de folios')

@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-create id="btn-nuevo" title="Nueva secuencia" text="Nuevo" />
    <x-navbar.button-edit id="btn-editar" title="Editar secuencia" text="Editar" :disabled="true" />
    <x-navbar.button-delete id="btn-eliminar" title="Eliminar secuencia" text="Eliminar" :disabled="true" />
</div>
@endsection

@section('content')
<div class="w-full px-4 py-4">
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-auto max-h-[calc(100vh-12rem)]">
            <table class="min-w-full text-base" id="tabla-secuencia">
                <thead class="sticky top-0 z-10 bg-blue-500 text-white shadow-sm">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Id</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Modulo</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Prefijo</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Consecutivo</th>
                    </tr>
                </thead>
                <tbody id="tbody-secuencia">
                    @forelse($items as $r)
                        @php
                            $modulo = $r->Modulo ?? $r->modulo ?? null;
                            $prefijo = $r->Prefijo ?? $r->prefijo ?? null;
                            $consecutivo = $r->Consecutivo ?? $r->consecutivo ?? null;
                        @endphp
                        <tr class="seq-row border-b border-gray-100 hover:bg-gray-50 cursor-pointer {{ $loop->even ? 'bg-gray-50/50' : '' }}"
                            data-id="{{ $r->Id }}"
                            data-modulo="{{ e($modulo) }}"
                            data-prefijo="{{ e($prefijo) }}"
                            data-consecutivo="{{ (int) $consecutivo }}">
                            <td class="px-4 py-3 text-gray-700 text-base">{{ $r->Id }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 text-base">{{ $modulo ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700 text-base">{{ $prefijo ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700 text-base">{{ $consecutivo !== null ? (int) $consecutivo : '—' }}</td>
                        </tr>
                    @empty
                        <tr id="tr-empty">
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500 text-base">No hay registros de secuencia.</td>
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
            <h3 class="text-lg font-semibold text-gray-800" id="modal-title">Nueva secuencia</h3>
            <button type="button" id="modal-cerrar" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <form id="form-secuencia" method="POST" action="">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">
            <div class="space-y-4">
                <div>
                    <label for="Modulo" class="block text-sm font-medium text-gray-700 mb-1">Modulo</label>
                    <input type="text" name="Modulo" id="Modulo" maxlength="100" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Ej. BPMTEjido">
                </div>
                <div>
                    <label for="Prefijo" class="block text-sm font-medium text-gray-700 mb-1">Prefijo</label>
                    <input type="text" name="Prefijo" id="Prefijo" maxlength="20" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Ej. BT">
                </div>
                <div>
                    <label for="Consecutivo" class="block text-sm font-medium text-gray-700 mb-1">Consecutivo <span class="text-red-500">*</span></label>
                    <input type="number" name="Consecutivo" id="Consecutivo" min="0" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="0">
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
    const form = document.getElementById('form-secuencia');
    const modalTitle = document.getElementById('modal-title');
    const formMethod = document.getElementById('form-method');
    const tbody = document.getElementById('tbody-secuencia');
    const trEmpty = document.getElementById('tr-empty');
    const storeUrl = @json(route('configuracion.secuencia-folios.store'));
    const updateUrlTpl = @json(route('configuracion.secuencia-folios.update', ['id' => ':id']));
    const destroyUrlTpl = @json(route('configuracion.secuencia-folios.destroy', ['id' => ':id']));
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
        document.querySelectorAll('.seq-row.selected').forEach(r => {
            r.classList.remove('selected', 'bg-blue-500', 'text-white', 'hover:bg-blue-600');
            r.classList.add('hover:bg-gray-50');
        });
        document.querySelectorAll('.seq-row td').forEach(td => td.classList.remove('text-white'));
        selectedRow = null;
        setActionsState(false);
    }

    function selectRow(tr) {
        clearSelection();
        if (!tr || !tr.classList.contains('seq-row')) return;
        tr.classList.remove('hover:bg-gray-50');
        tr.classList.add('selected', 'bg-blue-500', 'text-white', 'hover:bg-blue-600');
        tr.querySelectorAll('td').forEach(td => td.classList.add('text-white'));
        selectedRow = tr;
        setActionsState(true);
    }

    tbody?.addEventListener('click', function(e) {
        const tr = e.target.closest('tr.seq-row');
        if (tr) selectRow(tr);
    });

    document.getElementById('btn-nuevo').addEventListener('click', function(){
        modalTitle.textContent = 'Nueva secuencia';
        form.action = storeUrl;
        formMethod.value = 'POST';
        form.reset();
        document.getElementById('Consecutivo').value = '0';
        openModal();
    });

    document.getElementById('btn-editar').addEventListener('click', function(){
        if (!selectedRow) return;
        modalTitle.textContent = 'Editar secuencia';
        const id = selectedRow.dataset.id;
        form.action = updateUrlTpl.replace(':id', id);
        formMethod.value = 'PUT';
        document.getElementById('Modulo').value = selectedRow.dataset.modulo || '';
        document.getElementById('Prefijo').value = selectedRow.dataset.prefijo || '';
        document.getElementById('Consecutivo').value = selectedRow.dataset.consecutivo || '0';
        openModal();
    });

    document.getElementById('btn-eliminar').addEventListener('click', function(){
        if (!selectedRow) return;
        const id = selectedRow.dataset.id;
        const modulo = selectedRow.dataset.modulo || 'esta secuencia';
        Swal.fire({
            title: '¿Eliminar secuencia?',
            text: 'Se eliminará "' + modulo + '".',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(async function(r) {
            if (!r.isConfirmed) return;
            try {
                const fd = new FormData();
                fd.append('_token', csrf);
                fd.append('_method', 'DELETE');
                const res = await fetch(destroyUrlTpl.replace(':id', id), {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                });
                const data = await res.json().catch(function() { return {}; });
                if (res.ok && data.ok) {
                    selectedRow.remove();
                    selectedRow = null;
                    setActionsState(false);
                    if (tbody.querySelectorAll('tr.seq-row').length === 0) {
                        const empty = document.createElement('tr');
                        empty.id = 'tr-empty';
                        empty.innerHTML = '<td colspan="4" class="px-4 py-8 text-center text-gray-500 text-base">No hay registros de secuencia.</td>';
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
        const url = form.action;
        const isPut = formMethod.value === 'PUT';
        const body = new FormData(form);

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
                    selectedRow.dataset.modulo = item.Modulo ?? '';
                    selectedRow.dataset.prefijo = item.Prefijo ?? '';
                    selectedRow.dataset.consecutivo = String(item.Consecutivo ?? 0);
                    selectedRow.cells[0].textContent = item.Id ?? '';
                    selectedRow.cells[1].textContent = item.Modulo ?? '—';
                    selectedRow.cells[2].textContent = item.Prefijo ?? '—';
                    selectedRow.cells[3].textContent = item.Consecutivo ?? '—';
                } else {
                    if (trEmpty) trEmpty.remove();
                    const even = tbody.querySelectorAll('tr.seq-row').length % 2 === 0;
                    const tr = document.createElement('tr');
                    tr.className = 'seq-row border-b border-gray-100 hover:bg-gray-50 cursor-pointer ' + (even ? 'bg-gray-50/50' : '');
                    tr.dataset.id = item.Id;
                    tr.dataset.modulo = item.Modulo ?? '';
                    tr.dataset.prefijo = item.Prefijo ?? '';
                    tr.dataset.consecutivo = String(item.Consecutivo ?? 0);
                    tr.innerHTML = '<td class="px-4 py-3 text-gray-700 text-base">' + (item.Id || '') + '</td><td class="px-4 py-3 font-medium text-gray-900 text-base">' + (item.Modulo ?? '—') + '</td><td class="px-4 py-3 text-gray-700 text-base">' + (item.Prefijo ?? '—') + '</td><td class="px-4 py-3 text-gray-700 text-base">' + (item.Consecutivo ?? '—') + '</td>';
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
