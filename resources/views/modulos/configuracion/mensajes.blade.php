@extends('layouts.app')

@section('page-title', 'Mensajes')

@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-create id="btn-nuevo" title="Nuevo mensaje" text="Nuevo" />
    <x-navbar.button-edit id="btn-editar" title="Editar mensaje" text="Editar" :disabled="true" />
    <button type="button" id="btn-obtener-chat-id" title="Obtener Chat ID de Telegram" disabled class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">Obtener Chat ID</button>
    <x-navbar.button-delete id="btn-eliminar" title="Eliminar mensaje" text="Eliminar" :disabled="true" />
</div>
@endsection

@section('content')
<div class="w-full px-4 py-4">
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-auto max-h-[calc(100vh-12rem)]">
            <table class="min-w-full text-base" id="tabla-mensajes">
                <thead class="sticky top-0 z-10 bg-blue-500 text-white shadow-sm">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Id</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Departamento</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Teléfono</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Token</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Nombre</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Desarrolladores</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Notif. atado julio</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Corte SEF</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Marcas finales</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Rep. eléctrico</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Rep. mecánico</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Rep. tiempo muerto</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Atadores</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Inv. trama</th>
                    </tr>
                </thead>
                <tbody id="tbody-mensajes">
                    @forelse($mensajes as $m)
                        @php
                            $depto = $m->departamento;
                            $deptoNombre = $depto ? ($depto->Depto ?? $depto->Descripcion ?? '') : '';
                            $activo = (bool) ($m->Activo ?? true);
                        @endphp
                        <tr class="msg-row border-b border-gray-100 hover:bg-gray-50 cursor-pointer {{ $loop->even ? 'bg-gray-50/50' : '' }}"
                            data-id="{{ $m->Id }}"
                            data-departamento-id="{{ $m->DepartamentoId }}"
                            data-telefono="{{ e($m->Telefono) }}"
                            data-token="{{ e($m->Token) }}"
                            data-activo="{{ $activo ? '1' : '0' }}"
                            data-nombre="{{ e($m->Nombre ?? '') }}"
                            data-desarrolladores="{{ ($m->Desarrolladores ?? false) ? '1' : '0' }}"
                            data-notificar-atado-julio="{{ ($m->NotificarAtadoJulio ?? false) ? '1' : '0' }}"
                            data-corte-sef="{{ ($m->CorteSEF ?? false) ? '1' : '0' }}"
                            data-marcas-finales="{{ ($m->MarcasFinales ?? false) ? '1' : '0' }}"
                            data-reporte-electrico="{{ ($m->ReporteElectrico ?? false) ? '1' : '0' }}"
                            data-reporte-mecanico="{{ ($m->ReporteMecanico ?? false) ? '1' : '0' }}"
                            data-reporte-tiempo-muerto="{{ ($m->ReporteTiempoMuerto ?? false) ? '1' : '0' }}"
                            data-atadores="{{ ($m->Atadores ?? false) ? '1' : '0' }}"
                            data-inv-trama="{{ ($m->InvTrama ?? false) ? '1' : '0' }}">
                            <td class="px-4 py-3 text-gray-700 text-base">{{ $m->Id }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 text-base">{{ $deptoNombre }}</td>
                            <td class="px-4 py-3 text-gray-700 text-base">{{ $m->Telefono }}</td>
                            <td class="px-4 py-3 text-gray-600 text-base max-w-[140px] truncate font-mono" title="{{ $m->Token }}">{{ $m->Token }}</td>
                            <td class="px-4 py-3 text-gray-700 text-base">{{ $m->Nombre ?? '' }}</td>
                            <td class="px-4 py-3 text-center text-base">{{ ($m->Desarrolladores ?? false) ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-center text-base">{{ ($m->NotificarAtadoJulio ?? false) ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-center text-base">{{ ($m->CorteSEF ?? false) ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-center text-base">{{ ($m->MarcasFinales ?? false) ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-center text-base">{{ ($m->ReporteElectrico ?? false) ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-center text-base">{{ ($m->ReporteMecanico ?? false) ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-center text-base">{{ ($m->ReporteTiempoMuerto ?? false) ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-center text-base">{{ ($m->Atadores ?? false) ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-center text-base">{{ ($m->InvTrama ?? false) ? 'Sí' : 'No' }}</td>
                        </tr>
                    @empty
                        <tr id="tr-empty">
                            <td colspan="14" class="px-4 py-8 text-center text-gray-500 text-base">No hay mensajes registrados.</td>
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
            <h3 class="text-lg font-semibold text-gray-800" id="modal-title">Nuevo mensaje</h3>
            <button type="button" id="modal-cerrar" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <form id="form-mensaje" method="POST">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">
            <div class="space-y-4">
                <div>
                    <label for="DepartamentoId" class="block text-sm font-medium text-gray-700 mb-1">Departamento <span class="text-red-500">*</span></label>
                    <select name="DepartamentoId" id="DepartamentoId" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Seleccione departamento</option>
                        @foreach($departamentos as $d)
                            <option value="{{ $d->id }}">{{ $d->Depto }}{{ $d->Descripcion ? ' — ' . $d->Descripcion : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="Telefono" class="block text-sm font-medium text-gray-700 mb-1">Teléfono <span class="text-red-500">*</span></label>
                    <input type="text" name="Telefono" id="Telefono" required maxlength="20" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Ej. 521234567890">
                </div>
                <div>
                    <label for="Token" class="block text-sm font-medium text-gray-700 mb-1">Token <span class="text-red-500">*</span></label>
                    <input type="text" name="Token" id="Token" required maxlength="255" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Token de notificación">
                </div>
                <div>
                    <label for="Nombre" class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <input type="text" name="Nombre" id="Nombre" maxlength="150" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Nombre opcional">
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="Activo" value="0">
                    <input type="checkbox" name="Activo" id="Activo" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <label for="Activo" class="text-sm font-medium text-gray-700">Activo</label>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <label class="flex items-center gap-2"><input type="hidden" name="Desarrolladores" value="0"><input type="checkbox" name="Desarrolladores" id="Desarrolladores" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"> Desarrolladores</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="NotificarAtadoJulio" value="0"><input type="checkbox" name="NotificarAtadoJulio" id="NotificarAtadoJulio" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"> Notif. atado julio</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="CorteSEF" value="0"><input type="checkbox" name="CorteSEF" id="CorteSEF" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"> Corte SEF</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="MarcasFinales" value="0"><input type="checkbox" name="MarcasFinales" id="MarcasFinales" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"> Marcas finales</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="ReporteElectrico" value="0"><input type="checkbox" name="ReporteElectrico" id="ReporteElectrico" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"> Rep. eléctrico</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="ReporteMecanico" value="0"><input type="checkbox" name="ReporteMecanico" id="ReporteMecanico" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"> Rep. mecánico</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="ReporteTiempoMuerto" value="0"><input type="checkbox" name="ReporteTiempoMuerto" id="ReporteTiempoMuerto" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"> Rep. tiempo muerto</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="Atadores" value="0"><input type="checkbox" name="Atadores" id="Atadores" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"> Atadores</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="InvTrama" value="0"><input type="checkbox" name="InvTrama" id="InvTrama" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"> Inv. trama</label>
                </div>
            </div>
            <div class="mt-6 flex gap-2 justify-end">
                <button type="button" id="form-cancelar" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" id="form-submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Guardar</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Obtener Chat ID --}}
<div id="modal-chat-id" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Obtener Chat ID de Telegram</h3>
            <button type="button" id="modal-chat-id-cerrar" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <p class="text-sm text-gray-600 mb-3">Pide al usuario que envíe un mensaje al bot y haz clic en "Actualizar lista". Luego elige un chat_id para asignar a este registro.</p>
        <div class="mb-4">
            <button type="button" id="btn-refrescar-chat-ids" class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">Actualizar lista</button>
        </div>
        <div id="chat-ids-list" class="max-h-64 overflow-auto space-y-2 mb-4"></div>
        <div class="flex gap-2 justify-end">
            <button type="button" id="modal-chat-id-cancelar" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cerrar</button>
        </div>
    </div>
</div>


<script>
(function(){
    const modal = document.getElementById('modal-form');
    const form = document.getElementById('form-mensaje');
    const modalTitle = document.getElementById('modal-title');
    const formMethod = document.getElementById('form-method');
    const tbody = document.getElementById('tbody-mensajes');
    const trEmpty = document.getElementById('tr-empty');
    const storeUrl = @json(route('configuracion.mensajes.store'));
    const updateUrlTpl = @json(route('configuracion.mensajes.update', ['id' => ':id']));
    const destroyUrlTpl = @json(route('configuracion.mensajes.destroy', ['id' => ':id']));
    const obtenerChatIdsUrlTpl = @json(route('configuracion.mensajes.obtener-chat-ids', ['id' => ':id']));
    const actualizarChatIdUrlTpl = @json(route('configuracion.mensajes.actualizar-chat-id', ['id' => ':id']));
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
        document.getElementById('btn-obtener-chat-id').disabled = !hasSelection;
    }

    function clearSelection() {
        document.querySelectorAll('.msg-row.selected').forEach(r => {
            r.classList.remove('selected', 'bg-blue-500', 'text-white', 'hover:bg-blue-600');
            r.classList.add('hover:bg-gray-50');
        });
        document.querySelectorAll('.msg-row td').forEach(td => td.classList.remove('text-white'));
        selectedRow = null;
        setActionsState(false);
    }

    function selectRow(tr) {
        clearSelection();
        if (!tr || !tr.classList.contains('msg-row')) return;
        tr.classList.remove('hover:bg-gray-50');
        tr.classList.add('selected', 'bg-blue-500', 'text-white', 'hover:bg-blue-600');
        tr.querySelectorAll('td').forEach(td => td.classList.add('text-white'));
        selectedRow = tr;
        setActionsState(true);
    }

    tbody?.addEventListener('click', function(e) {
        const tr = e.target.closest('tr.msg-row');
        if (tr) selectRow(tr);
    });

    document.getElementById('btn-nuevo').addEventListener('click', function(){
        modalTitle.textContent = 'Nuevo mensaje';
        form.action = storeUrl;
        formMethod.value = 'POST';
        form.reset();
        document.getElementById('Activo').checked = true;
        document.querySelectorAll('#modal-form input[type="hidden"][name]').forEach(function(h){ if(h.name!=='_token'&&h.name!=='_method') h.value = '0'; });
        openModal();
    });

    document.getElementById('btn-editar').addEventListener('click', function(){
        if (!selectedRow) return;
        const id = selectedRow.dataset.id;
        const depId = selectedRow.dataset.departamentoId || '';
        const telefono = selectedRow.dataset.telefono || '';
        const token = selectedRow.dataset.token || '';
        const activo = selectedRow.dataset.activo === '1';
        const nombre = selectedRow.dataset.nombre || '';
        modalTitle.textContent = 'Editar mensaje';
        form.action = updateUrlTpl.replace(':id', id);
        formMethod.value = 'PUT';
        document.getElementById('DepartamentoId').value = depId;
        document.getElementById('Telefono').value = telefono;
        document.getElementById('Token').value = token;
        document.getElementById('Activo').checked = activo;
        document.getElementById('Nombre').value = nombre;
        document.getElementById('Desarrolladores').checked = selectedRow.dataset.desarrolladores === '1';
        document.getElementById('NotificarAtadoJulio').checked = selectedRow.dataset.notificarAtadoJulio === '1';
        document.getElementById('CorteSEF').checked = selectedRow.dataset.corteSef === '1';
        document.getElementById('MarcasFinales').checked = selectedRow.dataset.marcasFinales === '1';
        document.getElementById('ReporteElectrico').checked = selectedRow.dataset.reporteElectrico === '1';
        document.getElementById('ReporteMecanico').checked = selectedRow.dataset.reporteMecanico === '1';
        document.getElementById('ReporteTiempoMuerto').checked = selectedRow.dataset.reporteTiempoMuerto === '1';
        document.getElementById('Atadores').checked = selectedRow.dataset.atadores === '1';
        document.getElementById('InvTrama').checked = selectedRow.dataset.invTrama === '1';
        document.querySelector('input[name="Activo"][type="hidden"]').value = activo ? '0' : '0';
        openModal();
    });

    document.getElementById('btn-eliminar').addEventListener('click', function(){
        if (!selectedRow) return;
        const id = selectedRow.dataset.id;
        const telefono = selectedRow.dataset.telefono || 'este registro';
        Swal.fire({
            title: '¿Eliminar mensaje?',
            text: 'Se eliminará el teléfono "' + telefono + '".',
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
                    if (tbody.querySelectorAll('tr.msg-row').length === 0) {
                        const empty = document.createElement('tr');
                        empty.id = 'tr-empty';
                        empty.innerHTML = '<td colspan="14" class="px-4 py-8 text-center text-gray-500 text-base">No hay mensajes registrados.</td>';
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

    const modalChatId = document.getElementById('modal-chat-id');
    const chatIdsList = document.getElementById('chat-ids-list');
    function openModalChatId() {
        modalChatId.classList.remove('hidden');
        modalChatId.classList.add('flex');
        if (selectedRow) cargarChatIds(selectedRow.dataset.id);
    }
    function closeModalChatId() {
        modalChatId.classList.add('hidden');
        modalChatId.classList.remove('flex');
    }
    async function cargarChatIds(id) {
        chatIdsList.innerHTML = '<p class="text-sm text-gray-500">Cargando...</p>';
        try {
            const res = await fetch(obtenerChatIdsUrlTpl.replace(':id', id), { headers: { 'Accept': 'application/json' } });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                chatIdsList.innerHTML = '<p class="text-sm text-red-600">No se pudo obtener la lista.</p>';
                return;
            }
            const list = data.chat_ids || [];
            if (list.length === 0) {
                chatIdsList.innerHTML = '<p class="text-sm text-gray-500">No hay mensajes recientes. Pide al usuario que envíe un mensaje al bot y haz clic en "Actualizar lista".</p>';
                return;
            }
            chatIdsList.innerHTML = list.map(function(c) {
                const label = (c.first_name || '') + (c.username ? ' (@' + c.username + ')' : '') + ' — ' + c.chat_id;
                return '<div class="flex items-center justify-between gap-2 p-2 border border-gray-200 rounded-lg"><span class="text-sm font-mono">' + (c.chat_id || '') + '</span><span class="text-sm text-gray-600 truncate flex-1">' + (c.first_name || '') + (c.username ? ' @' + c.username : '') + '</span><button type="button" class="btn-asignar-chat-id px-2 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700" data-chat-id="' + (c.chat_id || '') + '">Asignar</button></div>';
            }).join('');
            chatIdsList.querySelectorAll('.btn-asignar-chat-id').forEach(function(btn) {
                btn.addEventListener('click', async function() {
                    const chatId = btn.getAttribute('data-chat-id');
                    if (!selectedRow || !chatId) return;
                    const id = selectedRow.dataset.id;
                    try {
                        const fd = new FormData();
                        fd.append('_token', csrf);
                        fd.append('_method', 'PUT');
                        fd.append('ChatId', chatId);
                        const r = await fetch(actualizarChatIdUrlTpl.replace(':id', id), {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                            body: fd
                        });
                        const d = await r.json().catch(() => ({}));
                        if (r.ok && d.ok && d.item) {
                            const item = d.item;
                            selectedRow.dataset.token = item.Token || '';
                            selectedRow.cells[3].textContent = item.Token || '';
                            selectedRow.cells[3].title = item.Token || '';
                            closeModalChatId();
                            Swal.fire({ icon: 'success', title: d.message || 'Chat ID asignado', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
                        } else {
                            Swal.fire({ icon: 'error', title: d.message || 'No se pudo asignar' });
                        }
                    } catch (err) {
                        Swal.fire({ icon: 'error', title: 'Error de conexión' });
                    }
                });
            });
        } catch (err) {
            chatIdsList.innerHTML = '<p class="text-sm text-red-600">Error de conexión.</p>';
        }
    }
    document.getElementById('btn-obtener-chat-id').addEventListener('click', function() {
        if (!selectedRow) return;
        openModalChatId();
    });
    document.getElementById('btn-refrescar-chat-ids').addEventListener('click', function() {
        if (selectedRow) cargarChatIds(selectedRow.dataset.id);
    });
    document.getElementById('modal-chat-id-cerrar').addEventListener('click', closeModalChatId);
    document.getElementById('modal-chat-id-cancelar').addEventListener('click', closeModalChatId);

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const isPut = formMethod.value === 'PUT';
        const url = form.action;
        const body = new FormData(form);
        if (isPut) body.append('_method', 'PUT');
        if (!document.getElementById('Activo').checked) body.set('Activo', '0'); else body.set('Activo', '1');
        ['Desarrolladores','NotificarAtadoJulio','CorteSEF','MarcasFinales','ReporteElectrico','ReporteMecanico','ReporteTiempoMuerto','Atadores','InvTrama'].forEach(function(name){
            var el = document.getElementById(name);
            body.set(name, el && el.checked ? '1' : '0');
        });

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

            function siNo(v) { return v ? 'Sí' : 'No'; }
                if (res.ok && data.ok) {
                closeModal();
                const item = data.item || {};
                if (isPut && selectedRow) {
                    selectedRow.dataset.departamentoId = item.DepartamentoId || '';
                    selectedRow.dataset.telefono = item.Telefono || '';
                    selectedRow.dataset.token = item.Token || '';
                    selectedRow.dataset.activo = item.Activo ? '1' : '0';
                    selectedRow.dataset.nombre = item.Nombre || '';
                    selectedRow.dataset.desarrolladores = item.Desarrolladores ? '1' : '0';
                    selectedRow.dataset.notificarAtadoJulio = item.NotificarAtadoJulio ? '1' : '0';
                    selectedRow.dataset.corteSef = item.CorteSEF ? '1' : '0';
                    selectedRow.dataset.marcasFinales = item.MarcasFinales ? '1' : '0';
                    selectedRow.dataset.reporteElectrico = item.ReporteElectrico ? '1' : '0';
                    selectedRow.dataset.reporteMecanico = item.ReporteMecanico ? '1' : '0';
                    selectedRow.dataset.reporteTiempoMuerto = item.ReporteTiempoMuerto ? '1' : '0';
                    selectedRow.dataset.atadores = item.Atadores ? '1' : '0';
                    selectedRow.dataset.invTrama = item.InvTrama ? '1' : '0';
                    selectedRow.cells[0].textContent = item.Id;
                    selectedRow.cells[1].textContent = item.DepartamentoNombre || '';
                    selectedRow.cells[2].textContent = item.Telefono || '';
                    selectedRow.cells[3].textContent = item.Token || '';
                    selectedRow.cells[3].title = item.Token || '';
                    selectedRow.cells[4].textContent = item.Nombre || '';
                    selectedRow.cells[5].textContent = siNo(item.Desarrolladores);
                    selectedRow.cells[6].textContent = siNo(item.NotificarAtadoJulio);
                    selectedRow.cells[7].textContent = siNo(item.CorteSEF);
                    selectedRow.cells[8].textContent = siNo(item.MarcasFinales);
                    selectedRow.cells[9].textContent = siNo(item.ReporteElectrico);
                    selectedRow.cells[10].textContent = siNo(item.ReporteMecanico);
                    selectedRow.cells[11].textContent = siNo(item.ReporteTiempoMuerto);
                    selectedRow.cells[12].textContent = siNo(item.Atadores);
                    selectedRow.cells[13].textContent = siNo(item.InvTrama);
                } else {
                    if (trEmpty) trEmpty.remove();
                    const even = tbody.querySelectorAll('tr.msg-row').length % 2 === 0;
                    const tr = document.createElement('tr');
                    tr.className = 'msg-row border-b border-gray-100 hover:bg-gray-50 cursor-pointer ' + (even ? 'bg-gray-50/50' : '');
                    tr.dataset.id = item.Id;
                    tr.dataset.departamentoId = item.DepartamentoId || '';
                    tr.dataset.telefono = item.Telefono || '';
                    tr.dataset.token = item.Token || '';
                    tr.dataset.activo = item.Activo ? '1' : '0';
                    tr.dataset.nombre = item.Nombre || '';
                    tr.dataset.desarrolladores = item.Desarrolladores ? '1' : '0';
                    tr.dataset.notificarAtadoJulio = item.NotificarAtadoJulio ? '1' : '0';
                    tr.dataset.corteSef = item.CorteSEF ? '1' : '0';
                    tr.dataset.marcasFinales = item.MarcasFinales ? '1' : '0';
                    tr.dataset.reporteElectrico = item.ReporteElectrico ? '1' : '0';
                    tr.dataset.reporteMecanico = item.ReporteMecanico ? '1' : '0';
                    tr.dataset.reporteTiempoMuerto = item.ReporteTiempoMuerto ? '1' : '0';
                    tr.dataset.atadores = item.Atadores ? '1' : '0';
                    tr.dataset.invTrama = item.InvTrama ? '1' : '0';
                    tr.innerHTML = '<td class="px-4 py-3 text-gray-700 text-base">' + (item.Id || '') + '</td><td class="px-4 py-3 font-medium text-gray-900 text-base">' + (item.DepartamentoNombre || '') + '</td><td class="px-4 py-3 text-gray-700 text-base">' + (item.Telefono || '') + '</td><td class="px-4 py-3 text-gray-600 text-base max-w-[140px] truncate font-mono" title="' + (item.Token || '') + '">' + (item.Token || '') + '</td><td class="px-4 py-3 text-gray-700 text-base">' + (item.Nombre || '') + '</td><td class="px-4 py-3 text-center text-base">' + siNo(item.Desarrolladores) + '</td><td class="px-4 py-3 text-center text-base">' + siNo(item.NotificarAtadoJulio) + '</td><td class="px-4 py-3 text-center text-base">' + siNo(item.CorteSEF) + '</td><td class="px-4 py-3 text-center text-base">' + siNo(item.MarcasFinales) + '</td><td class="px-4 py-3 text-center text-base">' + siNo(item.ReporteElectrico) + '</td><td class="px-4 py-3 text-center text-base">' + siNo(item.ReporteMecanico) + '</td><td class="px-4 py-3 text-center text-base">' + siNo(item.ReporteTiempoMuerto) + '</td><td class="px-4 py-3 text-center text-base">' + siNo(item.Atadores) + '</td><td class="px-4 py-3 text-center text-base">' + siNo(item.InvTrama) + '</td>';
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
