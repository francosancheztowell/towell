@extends('layouts.app')

@section('page-title', 'BPM Tejedores')

@section('navbar-right')
<div class="flex items-center gap-2">
    {{-- Alcance (tel-bpm/index.ts pone aria-pressed) y filtros por columna (tabla-columnas.ts).
         Turno: lo cubre el filtro de la columna "Turno Recibe". --}}
    <flux:button.group>
        <flux:button data-tel-filtro="misFolios" aria-pressed="false" icon="user" class="min-h-touch">Mis folios</flux:button>
        <flux:button data-tel-filtro="autorizados" aria-pressed="false" icon="check-circle" class="min-h-touch">Autorizados</flux:button>
        <flux:button data-tel-filtro="todos" aria-pressed="false" icon="list-bullet" class="min-h-touch">Todos</flux:button>
    </flux:button.group>
    <flux:button data-alternar-filtros="#telBpmTable" aria-pressed="false" icon="funnel"
                 class="min-h-touch min-w-touch" title="Filtrar por columna" aria-label="Filtrar por columna" />
    <x-navbar.button-edit
    id="btn-consult"
    title="Consultar folio"
    module="BPM Tejedores"/>
    <x-navbar.button-delete module="BPM Tejedores" id="btn-delete" title="Eliminar folio"/>
    <x-navbar.button-create module="BPM Tejedores" id="btn-open-create" title="Nuevo folio"/>
</div>
@endsection

@section('content')
@php
    $configTelBpm = [
        'usuario' => auth()->user()->nombre ?? '',
        'esSupervisor' => (bool) ($esSupervisor ?? false),
        'usuarioEsOperador' => (bool) ($usuarioEsOperador ?? false),
        'reabrir' => $errors->any() ? (old('_mode') === 'edit' ? 'edit' : 'create') : null,
        'rutas' => [
            'consultar' => route('tel-bpm-line.index', '__FOLIO__'),
            'actualizar' => route('tel-bpm.update', '__FOLIO__'),
            'eliminar' => route('tel-bpm.destroy', '__FOLIO__'),
        ],
    ];
    $colorStatus = ['Autorizado' => 'green', 'Terminado' => 'amber'];
@endphp
<div id="tel-bpm-pagina" class="max-w-7xl mx-auto p-4 pb-8" data-tel-bpm='@json($configTelBpm)'>
    {{-- flux:table con las piezas reutilizables (cebra, selección y filtros por columna, app.css /
         tabla-columnas.ts). El alcance oculta filas con hidden desde tel-bpm/index.ts. --}}
    <div class="rounded-lg bg-white shadow-sm overflow-hidden">
        <flux:table id="telBpmTable" data-filtros-columna class="tabla-cebra tabla-seleccionable"
                    container:class="max-h-[70vh] [&>ui-table-scroll-area]:min-h-0">
            <flux:table.columns sticky class="bg-white">
                @foreach (['Folio', 'Status', 'Fecha', 'No Recibe', 'Nombre Recibe', 'Turno Recibe', 'No Entrega', 'Nombre Entrega', 'Turno Entrega', 'Cve Autoriza', 'Nombre Autoriza'] as $titulo)
                    <flux:table.column :align="str_starts_with($titulo, 'Turno') ? 'center' : 'start'">{{ $titulo }}</flux:table.column>
                @endforeach
                <flux:table.column class="min-w-[200px]">Comentarios</flux:table.column>
            </flux:table.columns>
            <flux:table.rows id="tb-body">
                @forelse($items as $row)
                    <flux:table.row data-tel-fila aria-selected="false"
                        data-folio="{{ $row->Folio }}"
                        data-status="{{ $row->Status }}"
                        data-cveent="{{ $row->CveEmplEnt }}"
                        data-noment="{{ $row->NombreEmplEnt }}"
                        data-turnoent="{{ $row->TurnoEntrega }}"
                        data-cverec="{{ $row->CveEmplRec }}"
                        data-nomrec="{{ $row->NombreEmplRec }}"
                        data-turnorec="{{ $row->TurnoRecibe }}">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('tel-bpm-line.index', $row->Folio)">{{ $row->Folio }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" inset="top bottom" :color="$colorStatus[$row->Status] ?? 'zinc'">{{ $row->Status }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ optional($row->Fecha)->format('d/m/Y H:i') }}</flux:table.cell>
                        <flux:table.cell class="font-mono">{{ $row->CveEmplRec }}</flux:table.cell>
                        <flux:table.cell>{{ $row->NombreEmplRec }}</flux:table.cell>
                        <flux:table.cell align="center">{{ $row->TurnoRecibe }}</flux:table.cell>
                        <flux:table.cell class="font-mono">{{ $row->CveEmplEnt }}</flux:table.cell>
                        <flux:table.cell>{{ $row->NombreEmplEnt }}</flux:table.cell>
                        <flux:table.cell align="center">{{ $row->TurnoEntrega }}</flux:table.cell>
                        <flux:table.cell class="font-mono">{{ $row->CveEmplAutoriza }}</flux:table.cell>
                        <flux:table.cell>{{ $row->NomEmplAutoriza }}</flux:table.cell>
                        <flux:table.cell class="max-w-[200px]">
                            @if($row->Comentarios)
                                <div class="truncate" title="{{ $row->Comentarios }}">{{ $row->Comentarios }}</div>
                            @else
                                <span class="italic">Sin comentarios</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="12" class="py-6 text-center">Sin resultados</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>

{{-- Eliminar: el folio lo pone tel-bpm/index.ts en action. --}}
<form id="form-delete" method="POST" class="hidden">@csrf @method('DELETE')</form>

{{-- Modal CREAR --}}
<div id="modal-create" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-5">
    <div class="flex items-center justify-center mb-4 relative">
        <h2 class="text-lg font-semibold text-center">Nuevo Folio</h2>
        <button data-close="#modal-create" class="absolute right-0 text-slate-500 hover:text-slate-700">&times;</button>
    </div>

    <form id="form-create" method="POST" action="{{ route('tel-bpm.store') }}">
        @csrf
        <input type="hidden" name="_mode" value="create">

        <div class="space-y-6">
            <!-- Fecha y Hora -->
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Fecha y hora</label>
                    <input type="text" readonly class="w-full rounded-lg border px-3 py-2 bg-slate-50"
                           value="{{ optional($fechaActual)->format('d/m/Y H:i') }}">
                </div>
            </div>

            <!-- Sección RECIBE -->
            <div>
                <h3 class="text-md font-semibold text-gray-700 mb-3 pb-2 border-b border-gray-200">
                    <i class="fa-solid fa-arrow-down text-green-600 mr-2"></i>RECIBE
                </h3>
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Nombre</label>
                        <input type="text" name="NombreEmplRec" readonly class="w-full rounded-lg border px-3 py-2 bg-slate-50"
                               value="{{ $operadorUsuario->nombreEmpl ?? '' }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">No. Operador</label>
                        <input type="text" name="CveEmplRec" readonly class="w-full rounded-lg border px-3 py-2 bg-slate-50"
                               value="{{ $operadorUsuario->numero_empleado ?? '' }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Turno</label>
                        <input type="text" name="TurnoRecibe" readonly class="w-full rounded-lg border px-3 py-2 bg-slate-50"
                               value="{{ $operadorUsuario->Turno ?? '' }}">
                    </div>
                </div>
            </div>

            <!-- Sección ENTREGA -->
            <div>
                <h3 class="text-md font-semibold text-gray-700 mb-3 pb-2 border-b border-gray-200">
                    <i class="fa-solid fa-arrow-up text-blue-600 mr-2"></i>ENTREGA
                </h3>
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Nombre <span class="text-red-600">*</span></label>
                        <select name="CveEmplEnt" id="sel-entrega" class="w-full rounded-lg border px-3 py-2 @error('CveEmplEnt') border-red-500 @enderror @error('NombreEmplEnt') border-red-500 @enderror" required>
                            <option value="">Seleccione…</option>
                            @php
                                $noRecibe = $operadorUsuario->numero_empleado ?? '';
                                // Los operadores ya vienen filtrados del controlador (sin supervisores)
                                $operadoresUnicos = ($operadoresEntrega ?? collect())
                                    ->unique('numero_empleado')
                                    ->filter(function($op) use ($noRecibe) {
                                        // Excluir el operador que recibe
                                        return $op->numero_empleado !== $noRecibe;
                                    });
                            @endphp
                            @foreach($operadoresUnicos as $op)
                                <option value="{{ $op->numero_empleado }}" data-nombre="{{ $op->nombreEmpl }}" data-turno="{{ $op->Turno }}"
                                    {{ old('CveEmplEnt') == $op->numero_empleado ? 'selected' : '' }}>{{ $op->nombreEmpl }}</option>
                            @endforeach
                        </select>
                        @error('CveEmplEnt')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
                        @error('NombreEmplEnt')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
                        <input type="hidden" name="NombreEmplEnt" id="inp-nombre-ent" value="{{ old('NombreEmplEnt') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">No. Operador <span class="text-red-600">*</span></label>
                        <input type="text" name="CveEmplEntTxt" id="inp-cve-ent" maxlength="30" value="{{ old('CveEmplEnt') }}"
                               class="w-full rounded-lg border px-3 py-2 bg-slate-50" required readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Turno <span class="text-red-600">*</span></label>
                        <input type="text" name="TurnoEntrega" id="inp-turno-ent" maxlength="10" value="{{ old('TurnoEntrega') }}"
                               class="w-full rounded-lg border px-3 py-2 bg-slate-50 @error('TurnoEntrega') border-red-500 @enderror" required readonly>
                        @error('TurnoEntrega')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-2">
            <button type="submit" class="rounded-lg px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 w-full p-2">
                Crear folio
            </button>
            <button type="button" data-close="#modal-create" class="rounded-lg px-4 py-2 text-black border-black border w-full transition">
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

@push('scripts')
    {{-- Avisos de sesión y errores de validación: los pinta x-ui.flash del layout. --}}
    @vite('resources/js/modulos/tejedores/tel-bpm/index.ts')
@endpush
@endsection
