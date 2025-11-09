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
        <button id="btn-open-create" class="rounded-lg px-4 py-2 bg-blue-600 text-white hover:bg-blue-700">
            + Nuevo folio
        </button>
    </div>


    {{-- Tabla --}}
    <div class="overflow-x-auto rounded-lg border">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-100">
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
                    <th class="text-right px-3 py-2 w-64">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $row)
                    <tr class="border-t">
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
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <div class="flex justify-end items-center gap-2">
                                <a href="{{ route('tel-bpm-line.index', $row->Folio) }}"
                                   class="rounded-lg px-3 py-2 bg-blue-600 text-white hover:bg-blue-700">
                                    Consultar
                                </a>

                                @if($row->Status === 'Creado')
                                    <button type="button"
                                            class="rounded-lg px-3 py-2 bg-amber-500 text-white hover:bg-amber-600 btn-edit"
                                            data-folio="{{ $row->Folio }}"
                                            data-cve="{{ $row->CveEmplEnt }}"
                                            data-nombre="{{ $row->NombreEmplEnt }}"
                                            data-turno="{{ $row->TurnoEntrega }}">
                                        Editar
                                    </button>

                                    <form action="{{ route('tel-bpm.destroy', $row->Folio) }}" method="POST" class="inline delete-form">
                                        @csrf @method('DELETE')
                                        <button type="button" class="rounded-lg px-3 py-2 bg-red-600 text-white hover:bg-red-700 btn-delete">
                                            Eliminar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
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

        <div class="grid md:grid-cols-3 gap-4">
            <div class="md:col-span-3">
                <div class="text-xs text-slate-500">Los datos de <b>Recibe</b> se llenan automáticamente con el usuario actual.</div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">No Entrega <span class="text-red-600">*</span></label>
                <input type="text" name="CveEmplEnt" maxlength="30" value="{{ old('CveEmplEnt') }}"
                       class="w-full rounded-lg border px-3 py-2 @error('CveEmplEnt') border-red-500 @enderror" required>
                @error('CveEmplEnt')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Nombre Entrega <span class="text-red-600">*</span></label>
                <input type="text" name="NombreEmplEnt" maxlength="150" value="{{ old('NombreEmplEnt') }}"
                       class="w-full rounded-lg border px-3 py-2 @error('NombreEmplEnt') border-red-500 @enderror" required>
                @error('NombreEmplEnt')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Turno Entrega <span class="text-red-600">*</span></label>
                <input type="text" name="TurnoEntrega" maxlength="10" value="{{ old('TurnoEntrega') }}"
                       class="w-full rounded-lg border px-3 py-2 @error('TurnoEntrega') border-red-500 @enderror" required>
                @error('TurnoEntrega')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mt-6 flex items-center gap-2">
            <button class="rounded-lg px-4 py-2 bg-blue-600 text-white hover:bg-blue-700">
                Crear y abrir checklist
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

    // Crear
    qs('#btn-open-create')?.addEventListener('click', ()=> open('#modal-create'));

    // Editar
    const formEdit  = qs('#form-edit');
    const pkEdit    = qs('#pk-edit');
    const editCve   = qs('#edit-cve');
    const editNom   = qs('#edit-nombre');
    const editTurno = qs('#edit-turno');
    const updateUrlTpl = @json(route('tel-bpm.update', ':FOLIO'));

    qsa('.btn-edit').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            const folio  = btn.dataset.folio;
            pkEdit.value = folio;
            editCve.value   = btn.dataset.cve || '';
            editNom.value   = btn.dataset.nombre || '';
            editTurno.value = btn.dataset.turno || '';
            formEdit.action = updateUrlTpl.replace(':FOLIO', folio);
            open('#modal-edit');
        });
    });

    // Eliminar (SweetAlert modal)
    qsa('.btn-delete').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            const form = btn.closest('form');
            Swal.fire({
                title: '¿Eliminar?',
                text: 'Sólo se permite eliminar en estado Creado.',
                icon: 'warning', showCancelButton:true,
                confirmButtonText: 'Sí, eliminar', cancelButtonText:'Cancelar'
            }).then(r => { if (r.isConfirmed) form.submit(); });
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
@endsection
