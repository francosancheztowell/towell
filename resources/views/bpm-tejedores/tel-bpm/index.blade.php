@extends('layouts.app')

@section('page-title', 'BPM - Folios')

@section('content')
<div class="max-w-7xl mx-auto p-4">
    {{-- Flash messages --}}
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-600/10 border border-green-600/30 text-green-800 px-4 py-3">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-600/10 border border-red-600/30 text-red-800 px-4 py-3">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex items-center justify-between gap-2 mb-4">
        <h1 class="text-xl font-semibold">Folios BPM</h1>
        <div class="flex items-center gap-2">
            <button id="btn-consult" class="rounded-lg px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                Consultar folio
            </button>
            <button id="btn-edit" class="rounded-lg px-4 py-2 bg-amber-600 text-white hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                Editar folio
            </button>
            <button id="btn-delete" class="rounded-lg px-4 py-2 bg-red-600 text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                Eliminar folio
            </button>
            <button id="btn-open-create" class="rounded-lg px-4 py-2 bg-green-600 text-white hover:bg-green-700">
                + Nuevo folio
            </button>
        </div>
    </div>


    {{-- Tabla --}}
    <div class="overflow-x-auto rounded-lg border bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-blue-600 text-white">
                <tr>
                    <th class="text-left px-3 py-2">Folio</th>
                    <th class="text-left px-3 py-2">Status</th>
                    <th class="text-left px-3 py-2">Fecha</th>
                    <th class="text-left px-3 py-2">No Recibe</th>
                    <th class="text-left px-3 py-2">Nombre Recibe</th>
                    <th class="text-left px-3 py-2">Turno Recibe</th>
                    <th class="text-left px-3 py-2">No Entrega</th>
                    <th class="text-left px-3 py-2">Nombre Entrega</th>
                    <th class="text-left px-3 py-2">Turno Entrega</th>
                    <th class="text-left px-3 py-2">Cve Autoriza</th>
                    <th class="text-left px-3 py-2">Nombre Autoriza</th>
                </tr>
            </thead>
            <tbody id="tb-body">
                @forelse($items as $row)
                    <tr class="border-t hover:bg-blue-50 cursor-pointer"
                        data-folio="{{ $row->Folio }}"
                        data-status="{{ $row->Status }}"
                        data-cveent="{{ $row->CveEmplEnt }}"
                        data-noment="{{ $row->NombreEmplEnt }}"
                        data-turnoent="{{ $row->TurnoEntrega }}">
                        <td class="px-3 py-2 font-semibold">
                            <a class="text-blue-700 hover:underline" href="{{ route('tel-bpm-line.index', $row->Folio) }}">{{ $row->Folio }}</a>
                        </td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs
                                {{ $row->Status==='Autorizado' ? 'bg-green-100 text-green-800' :
                                   ($row->Status==='Terminado' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-800') }}">
                                {{ $row->Status }}
                            </span>
                        </td>
                        <td class="px-3 py-2">{{ optional($row->Fecha)->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2 font-mono">{{ $row->CveEmplRec }}</td>
                        <td class="px-3 py-2">{{ $row->NombreEmplRec }}</td>
                        <td class="px-3 py-2">{{ $row->TurnoRecibe }}</td>
                        <td class="px-3 py-2 font-mono">{{ $row->CveEmplEnt }}</td>
                        <td class="px-3 py-2">{{ $row->NombreEmplEnt }}</td>
                        <td class="px-3 py-2">{{ $row->TurnoEntrega }}</td>
                        <td class="px-3 py-2 font-mono">{{ $row->CveEmplAutoriza }}</td>
                        <td class="px-3 py-2">{{ $row->NomEmplAutoriza }}</td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="px-3 py-6 text-center text-slate-500">Sin resultados</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $items->links() }}
    </div>
</div>

{{-- Modal CREAR --}}
<div id="modal-create" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-5">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">Nuevo folio</h2>
        <button data-close="#modal-create" class="text-slate-500 hover:text-slate-700">&times;</button>
    </div>

    <form id="form-create" method="POST" action="{{ route('tel-bpm.store') }}">
        @csrf
        <input type="hidden" name="_mode" value="create">

        <div class="grid md:grid-cols-3 gap-4 bg-white">
            <!-- Fila 1: Fecha/Hora actual -->
            <div>
                <label class="block text-sm font-medium mb-1">Fecha y hora</label>
                <input type="text" readonly class="w-full rounded-lg border px-3 py-2 bg-slate-50"
                       value="{{ optional($fechaActual)->format('d/m/Y H:i') }}">
            </div>

            <!-- Fila 2: Recibe (usuario actual) -->
            <div>
                <label class="block text-sm font-medium mb-1">No. Operador (Recibe)</label>
                <input type="text" name="CveEmplRec" readonly class="w-full rounded-lg border px-3 py-2 bg-slate-50"
                       value="{{ $operadorUsuario->numero_empleado ?? '' }}">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Nombre (Recibe)</label>
                <input type="text" name="NombreEmplRec" readonly class="w-full rounded-lg border px-3 py-2 bg-slate-50"
                       value="{{ $operadorUsuario->nombreEmpl ?? '' }}">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Turno (Recibe)</label>
                <input type="text" name="TurnoRecibe" readonly class="w-full rounded-lg border px-3 py-2 bg-slate-50"
                       value="{{ $operadorUsuario->Turno ?? '' }}">
            </div>

            <!-- Fila 3: Entrega (select de operador) -->
            <div>
                <label class="block text-sm font-medium mb-1">No. Operador (Entrega) <span class="text-red-600">*</span></label>
                <select name="CveEmplEnt" id="sel-entrega" class="w-full rounded-lg border px-3 py-2 @error('CveEmplEnt') border-red-500 @enderror" required>
                    <option value="">Seleccione…</option>
                    @php $noRecibe = $operadorUsuario->numero_empleado ?? ''; @endphp
                    @foreach(($operadoresEntrega ?? collect()) as $op)
                        @if($op->numero_empleado !== $noRecibe)
                        <option value="{{ $op->numero_empleado }}" data-nombre="{{ $op->nombreEmpl }}" data-turno="{{ $op->Turno }}"
                            {{ old('CveEmplEnt') == $op->numero_empleado ? 'selected' : '' }}>{{ $op->numero_empleado }}</option>
                        @endif
                    @endforeach
                </select>
                @error('CveEmplEnt')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Nombre (Entrega) <span class="text-red-600">*</span></label>
                <input type="text" name="NombreEmplEnt" id="inp-nombre-ent" maxlength="150" value="{{ old('NombreEmplEnt') }}"
                       class="w-full rounded-lg border px-3 py-2 bg-slate-50 @error('NombreEmplEnt') border-red-500 @enderror" required readonly>
                @error('NombreEmplEnt')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Turno (Entrega) <span class="text-red-600">*</span></label>
                <input type="text" name="TurnoEntrega" id="inp-turno-ent" maxlength="10" value="{{ old('TurnoEntrega') }}"
                       class="w-full rounded-lg border px-3 py-2 bg-slate-50 @error('TurnoEntrega') border-red-500 @enderror" required readonly>
                @error('TurnoEntrega')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mt-6 flex items-center gap-2">
            <button class="rounded-lg px-4 py-2 bg-blue-600 text-white hover:bg-blue-700">
                Crear folio
            </button>
            <button type="button" data-close="#modal-create" class="rounded-lg px-4 py-2 border hover:bg-slate-50">
                Cancelar
            </button>
        </div>
    </form>
  </div>
</div>

{{-- Modal EDITAR --}}
<div id="modal-edit" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-5">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">Editar folio</h2>
        <button data-close="#modal-edit" class="text-slate-500 hover:text-slate-700">&times;</button>
    </div>

    <form id="form-edit" method="POST" action="#">
        @csrf
        @method('PUT')
        <input type="hidden" name="pk" id="pk-edit" value="{{ old('pk') }}">
        <input type="hidden" name="_mode" value="edit">

        <div class="grid md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">No Entrega <span class="text-red-600">*</span></label>
                <input type="text" name="CveEmplEnt" id="edit-cve" maxlength="30" value="{{ old('CveEmplEnt') }}"
                       class="w-full rounded-lg border px-3 py-2 @error('CveEmplEnt') border-red-500 @enderror" required>
                @error('CveEmplEnt')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Nombre Entrega <span class="text-red-600">*</span></label>
                <input type="text" name="NombreEmplEnt" id="edit-nombre" maxlength="150" value="{{ old('NombreEmplEnt') }}"
                       class="w-full rounded-lg border px-3 py-2 @error('NombreEmplEnt') border-red-500 @enderror" required>
                @error('NombreEmplEnt')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Turno Entrega <span class="text-red-600">*</span></label>
                <input type="text" name="TurnoEntrega" id="edit-turno" maxlength="10" value="{{ old('TurnoEntrega') }}"
                       class="w-full rounded-lg border px-3 py-2 @error('TurnoEntrega') border-red-500 @enderror" required>
                @error('TurnoEntrega')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mt-6 flex items-center gap-2">
            <button class="rounded-lg px-4 py-2 bg-blue-600 text-white hover:bg-blue-700">
                Guardar cambios
            </button>
            <button type="button" data-close="#modal-edit" class="rounded-lg px-4 py-2 border hover:bg-slate-50">
                Cancelar
            </button>
        </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
    const qs  = s => document.querySelector(s);
    const qsa = s => [...document.querySelectorAll(s)];

    // Abrir/Cerrar modales
    const open = sel => { const m = qs(sel); if(!m) return; m.classList.remove('hidden'); m.classList.add('flex'); }
    const close = sel => { const m = qs(sel); if(!m) return; m.classList.add('hidden'); m.classList.remove('flex'); }
    qsa('[data-close]').forEach(b => b.addEventListener('click', () => close(b.dataset.close)));

    // Crear: validar que el usuario exista como operador
    const usuarioEsOperador = @json($usuarioEsOperador ?? false);
    const noRecibeInput = document.querySelector('#form-create input[name="CveEmplRec"]');
    qs('#btn-open-create')?.addEventListener('click', ()=> {
        if (!usuarioEsOperador) {
            Swal.fire({
                icon: 'error',
                title: 'No eres operador registrado',
                text: 'No es posible crear el folio porque tu usuario no existe en la tabla de operadores.',
                confirmButtonText: 'Entendido'
            });
            return;
        }
        open('#modal-create')
    });

    // Seleccionar fila y accionar desde barra superior
    const tbody = qs('#tb-body');
    let selected = null;
    const btnConsult = qs('#btn-consult');
    const btnEditTop = qs('#btn-edit');
    const btnDeleteTop = qs('#btn-delete');
    const consultUrlTpl = @json(route('tel-bpm-line.index', ':FOLIO'));
    const updateUrlTpl = @json(route('tel-bpm.update', ':FOLIO'));
    const deleteUrlTpl = @json(route('tel-bpm.destroy', ':FOLIO'));

    function setDisabled(btn, val) {
        if (!btn) return;
        btn.disabled = !!val;
        btn.classList.toggle('opacity-50', !!val);
        btn.classList.toggle('cursor-not-allowed', !!val);
    }

    function updateActions() {
        const hasSel = !!selected;
        setDisabled(btnConsult, !hasSel);
        const editable = hasSel && selected.dataset.status === 'Creado';
        setDisabled(btnEditTop, !editable);
        setDisabled(btnDeleteTop, !editable);
    }

    function clearSelection() {
        (tbody?.querySelectorAll('tr.selected') || []).forEach(tr => tr.classList.remove('selected','bg-blue-100'));
    }

    tbody?.addEventListener('click', (e)=>{
        const tr = e.target.closest('tr');
        if (!tr) return;
        clearSelection();
        tr.classList.add('selected','bg-blue-100');
        selected = tr;
        updateActions();
    });

    btnConsult?.addEventListener('click', ()=>{
        if (!selected) return Swal.fire('Selecciona un folio','Debes seleccionar un folio para consultar','info');
        const folio = selected.dataset.folio;
        window.location.href = consultUrlTpl.replace(':FOLIO', folio);
    });

    // Autocompletar entrega según selección y validar distinto a recibe
    const selEntrega   = qs('#sel-entrega');
    const inpNomEnt    = qs('#inp-nombre-ent');
    const inpTurnoEnt  = qs('#inp-turno-ent');
    selEntrega?.addEventListener('change', ()=>{
        const opt = selEntrega.options[selEntrega.selectedIndex];
        const nom = opt?.dataset?.nombre || '';
        const tur = opt?.dataset?.turno || '';
        // Validar que no sea mismo operador que Recibe
        const noRecibe = noRecibeInput?.value || '';
        if (selEntrega.value && noRecibe && selEntrega.value === noRecibe) {
            Swal.fire({
                icon: 'warning',
                title: 'Operador duplicado',
                text: 'Entrega y Recibe no pueden ser el mismo operador.'
            });
            selEntrega.value = '';
            inpNomEnt.value = '';
            inpTurnoEnt.value = '';
            return;
        }
        inpNomEnt.value = nom;
        inpTurnoEnt.value = tur;
    });

    // Si hay un valor previo (old) en el select, disparemos el cambio para autocompletar
    if (selEntrega && selEntrega.value) {
        const event = new Event('change');
        selEntrega.dispatchEvent(event);
    }

    // Editar (desde barra superior)
    const formEdit  = qs('#form-edit');
    const pkEdit    = qs('#pk-edit');
    const editCve   = qs('#edit-cve');
    const editNom   = qs('#edit-nombre');
    const editTurno = qs('#edit-turno');
    btnEditTop?.addEventListener('click', ()=>{
        if (!selected) return Swal.fire('Selecciona un folio','Debes seleccionar un folio para editar','info');
        if (selected.dataset.status !== 'Creado') return Swal.fire('No editable','Sólo se puede editar en estado Creado','warning');
        const folio  = selected.dataset.folio;
        pkEdit.value = folio;
        editCve.value   = selected.dataset.cveent || '';
        editNom.value   = selected.dataset.noment || '';
        editTurno.value = selected.dataset.turnoent || '';
        formEdit.action = updateUrlTpl.replace(':FOLIO', folio);
        open('#modal-edit');
    });

    // Eliminar (SweetAlert modal)
    const deleteForm = document.createElement('form');
    deleteForm.method = 'POST';
    deleteForm.className = 'hidden';
    deleteForm.innerHTML = `@csrf @method('DELETE')`;
    document.body.appendChild(deleteForm);

    btnDeleteTop?.addEventListener('click', ()=>{
        if (!selected) return Swal.fire('Selecciona un folio','Debes seleccionar un folio para eliminar','info');
        if (selected.dataset.status !== 'Creado') return Swal.fire('No permitido','Sólo se puede eliminar en estado Creado','warning');
        const folio = selected.dataset.folio;
        Swal.fire({
            title: '¿Eliminar?',
            text: `Se eliminará el folio ${folio}.`,
            icon: 'warning', showCancelButton:true,
            confirmButtonText: 'Sí, eliminar', cancelButtonText:'Cancelar'
        }).then(r => {
            if (r.isConfirmed) {
                deleteForm.action = deleteUrlTpl.replace(':FOLIO', folio);
                deleteForm.submit();
            }
        });
    });

    // Reabrir modal si hubo errores de validación
    @if($errors->any())
        @if(old('_mode') === 'edit')
            open('#modal-edit');
        @else
            open('#modal-create');
        @endif
    @endif

    // Inicializa estado de acciones según selección
    updateActions();
})();
</script>
@if(session('error'))
<script>
  (function(){
    Swal.fire({
      icon: 'warning',
      title: 'Acción no permitida',
      text: @json(session('error')),
      confirmButtonText: 'Entendido'
    });
  })();
</script>
@endif

@if(session('success'))
<script>
  (function(){
    const message = @json(session('success'));
    // Auto-refrescar cuando se crea, termina, autoriza o rechaza un folio
    if (message.includes('creado') || message.includes('Terminado') || message.includes('Autorizado') || message.includes('Creado')) {
        // Pequeño delay para que el usuario vea el mensaje de éxito antes del refresh
        setTimeout(() => {
            window.location.reload();
        }, 1500);
    }
  })();
</script>
@endif
@endsection
