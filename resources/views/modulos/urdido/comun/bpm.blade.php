{{--
  Índice BPM (19-01): una vista para Urdido y Engomado. La incluyen
  modulos/urdido/BPM-Urdido/index y modulos/engomado/BPM-Engomado/index.
  @param string $variante  'urdido' | 'engomado'
  Datos del controller: $items, $usuarios, $maquinas, $esSupervisorBpm ($folioSugerido no se pinta).
  JS: resources/js/modulos/urdido/comun/bpm/index.ts
--}}
@php
    $cfgVariante = [
        'urdido' => [
            'titulo' => 'BPM Urdido',
            'modulo' => 'BPM (Buenas Practicas Manufactura) Urd',
            'rutas' => 'urd-bpm',
            'rutaLinea' => 'urd-bpm-line.index',
            'colAutoriza' => 'NombreEmplAutoriza',
            'badge' => ['Creado' => 'blue', 'Terminado' => 'yellow'],
            'tituloCrear' => 'Crear Nuevo Registro',
            'anchoCrear' => 'max-w-2xl',
            // El update de Urdido guarda solo el día; Engomado conserva la hora.
            'tipoFechaEdicion' => 'date',
            'formatoFechaEdicion' => 'Y-m-d',
        ],
        'engomado' => [
            'titulo' => 'BPM Engomado',
            'modulo' => 'BPM (Buenas Practicas Manufactura) Eng',
            'rutas' => 'eng-bpm',
            'rutaLinea' => 'eng-bpm-line.index',
            'colAutoriza' => 'NomEmplAutoriza',
            'badge' => ['Creado' => 'yellow', 'Terminado' => 'blue'],
            'tituloCrear' => 'Crear Nuevo Folio BPM Engomado',
            'anchoCrear' => 'max-w-4xl',
            'tipoFechaEdicion' => 'datetime-local',
            'formatoFechaEdicion' => 'Y-m-d\TH:i',
        ],
    ][$variante];

    $configBpm = [
        'variante' => $variante,
        'esSupervisor' => ! empty($esSupervisorBpm),
        'usuario' => auth()->user()->nombre ?? '',
        'rutas' => [
            'checklist' => route($cfgVariante['rutaLinea'], ['folio' => '__FOLIO__']),
            'actualizar' => route($cfgVariante['rutas'].'.update', ['id' => '__ID__']),
            'eliminar' => route($cfgVariante['rutas'].'.destroy', ['id' => '__ID__']),
        ],
    ];
@endphp
@extends('layouts.app')

@section('page-title', $cfgVariante['titulo'])

@section('navbar-right')
    <div class="flex items-center gap-2">
        {{-- Alcance (bpm/index.ts pone aria-pressed) y filtros por columna (tabla-columnas.ts).
             Turno: lo cubre el filtro de la columna "Turno Recibe". --}}
        <flux:button.group>
            <flux:button data-bpm-filtro="misFolios" aria-pressed="false" icon="user" class="min-h-touch">Mis folios</flux:button>
            <flux:button data-bpm-filtro="terminados" aria-pressed="false" icon="check-circle" class="min-h-touch">Finalizados</flux:button>
            <flux:button data-bpm-filtro="todos" aria-pressed="false" icon="list-bullet" class="min-h-touch">Todos</flux:button>
        </flux:button.group>
        <flux:button data-alternar-filtros="#bpmTable" aria-pressed="false" icon="funnel"
                     class="min-h-touch min-w-touch" title="Filtrar por columna" aria-label="Filtrar por columna" />
        <x-navbar.button-create
            data-bpm-accion="crear"
            :module="$cfgVariante['modulo']"
            title="Crear Registro" />
        <x-navbar.button-edit
            data-bpm-accion="checklist"
            id="btn-checklist"
            :module="$cfgVariante['modulo']"
            title="Abrir Checklist" />
    </div>
@endsection

@section('content')
<div id="bpm-pagina" data-bpm='@json($configBpm)'>
    {{-- flux:table. Alto: lo que deja el navbar; el scroll vive en el ui-table-scroll-area de Flux
         (min-h-0 para que encoja dentro del max-h y el encabezado sticky funcione).
         Cebra, selección y filtros por columna: piezas reutilizables de app.css / tabla-columnas.ts. --}}
    <div class="rounded-lg bg-white shadow-sm overflow-hidden mt-2 mx-2 sm:mt-4 sm:mx-4">
        <flux:table id="bpmTable"
                    container:class="max-h-[calc(100dvh-4.75rem)] sm:max-h-[calc(100dvh-5.5rem)] [&>ui-table-scroll-area]:min-h-0 [&>ui-table-scroll-area]:overscroll-contain"
                    data-filtros-columna
                    class="tabla-cebra tabla-seleccionable">
            <flux:table.columns sticky class="bg-white">
                <flux:table.column>Folio</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>No Recibe</flux:table.column>
                <flux:table.column>Nombre Recibe</flux:table.column>
                <flux:table.column align="center">Turno Recibe</flux:table.column>
                <flux:table.column>No Entrega</flux:table.column>
                <flux:table.column>Nombre Entrega</flux:table.column>
                <flux:table.column align="center">Turno Entrega</flux:table.column>
                <flux:table.column>Cve Autoriza</flux:table.column>
                <flux:table.column>Nombre Autoriza</flux:table.column>
            </flux:table.columns>
            <flux:table.rows id="tb-body">
                @forelse($items as $item)
                    <flux:table.row
                        data-bpm-fila
                        aria-selected="false"
                        data-id="{{ $item->Id }}"
                        data-folio="{{ $item->Folio }}"
                        data-status="{{ $item->Status }}"
                        data-fecha-edicion="{{ $item->Fecha ? $item->Fecha->format($cfgVariante['formatoFechaEdicion']) : '' }}"
                        data-cveemplrec="{{ $item->CveEmplRec }}"
                        data-nombreemplrec="{{ $item->NombreEmplRec }}"
                        data-turnorecibe="{{ $item->TurnoRecibe }}"
                        data-cveemplent="{{ $item->CveEmplEnt }}"
                        data-nombreemplent="{{ $item->NombreEmplEnt }}"
                        data-turnoentrega="{{ $item->TurnoEntrega }}">
                        <flux:table.cell variant="strong">{{ $item->Folio }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" inset="top bottom"
                                        :color="$cfgVariante['badge'][$item->Status] ?? ($item->Status === 'Autorizado' ? 'green' : 'zinc')">
                                {{ $item->Status }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->Fecha ? $item->Fecha->format('d/m/Y') : '' }}</flux:table.cell>
                        <flux:table.cell>{{ $item->CveEmplRec }}</flux:table.cell>
                        <flux:table.cell>{{ $item->NombreEmplRec }}</flux:table.cell>
                        <flux:table.cell align="center">{{ $item->TurnoRecibe }}</flux:table.cell>
                        <flux:table.cell>{{ $item->CveEmplEnt }}</flux:table.cell>
                        <flux:table.cell>{{ $item->NombreEmplEnt }}</flux:table.cell>
                        <flux:table.cell align="center">{{ $item->TurnoEntrega }}</flux:table.cell>
                        <flux:table.cell>{{ $item->CveEmplAutoriza }}</flux:table.cell>
                        <flux:table.cell>{{ $item->{$cfgVariante['colAutoriza']} }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="11" class="py-8 text-center">No hay registros disponibles</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <!-- Modal Crear -->
    <div id="createModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full {{ $cfgVariante['anchoCrear'] }} mx-4 max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-3 rounded-t-lg flex justify-between items-center">
                <h3 class="text-lg font-semibold">{{ $cfgVariante['tituloCrear'] }}</h3>
                <button type="button" data-bpm-cerrar="createModal" class="text-white hover:text-gray-200" aria-label="Cerrar">
                    <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
                </button>
            </div>
            <form action="{{ route($cfgVariante['rutas'].'.store') }}" method="POST" class="p-4">
                @csrf
                <!-- Status oculto, siempre será "Creado" -->
                <input type="hidden" name="Status" value="Creado">

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fecha *</label>
                        <input type="date" name="Fecha" value="{{ date('Y-m-d') }}" required readonly class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-blue-500 bg-gray-50">
                    </div>
                </div>

                <!-- Sección: Quien Recibe (autollenado con usuario actual) -->
                <div class="mb-3">
                    <h4 class="text-sm font-semibold text-green-700 mb-2">Quien Recibe</h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre</label>
                            <input type="text" id="create_NombreEmplRec" name="NombreEmplRec" value="{{ auth()->user()->nombre ?? '' }}" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">No. Empleado</label>
                            <input type="text" id="create_CveEmplRec" name="CveEmplRec" value="{{ auth()->user()->numero_empleado ?? '' }}" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Turno</label>
                            <input type="text" id="create_TurnoRecibe" name="TurnoRecibe" value="{{ auth()->user()->turno ?? '' }}" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                    </div>
                </div>

                <!-- Sección: Quien Entrega (selección y autocompletado) -->
                <div class="mb-3">
                    <h4 class="text-sm font-semibold text-blue-700 mb-2">Quien Entrega</h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label for="select_NombreEmplEnt" class="block text-xs font-medium text-gray-700 mb-1">Nombre <span class="text-red-600">*</span></label>
                            <select id="select_NombreEmplEnt" name="NombreEmplEnt" required
                                    data-bpm-autollenar data-llenar-numero="input_CveEmplEnt" data-llenar-turno="input_TurnoEntrega"
                                    class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-blue-500 @error('NombreEmplEnt') border-red-500 @enderror">
                                <option value="">Seleccione...</option>
                                @foreach($usuarios as $usuario)
                                    <option value="{{ $usuario->nombre }}"
                                            data-numero="{{ $usuario->numero_empleado }}"
                                            data-turno="{{ $usuario->turno }}">
                                        {{ $usuario->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            @error('NombreEmplEnt')
                                <div class="text-red-600 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">No. Empleado</label>
                            <input type="text" id="input_CveEmplEnt" name="CveEmplEnt" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Turno</label>
                            <input type="text" id="input_TurnoEntrega" name="TurnoEntrega" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="select_Maquina" class="block text-xs font-medium text-gray-700 mb-1">Máquina <span class="text-red-600">*</span></label>
                            <select id="select_Maquina" name="MaquinaId" required
                                    data-bpm-autollenar data-llenar-departamento="input_Departamento"
                                    class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-blue-500 @error('MaquinaId') border-red-500 @enderror">
                                <option value="">Seleccione...</option>
                                @foreach($maquinas as $maquina)
                                    <option value="{{ $maquina->MaquinaId }}"
                                            data-nombre="{{ $maquina->Nombre }}"
                                            data-departamento="{{ $maquina->Departamento }}">
                                        {{ $maquina->Nombre }}
                                    </option>
                                @endforeach
                            </select>
                            @error('MaquinaId')
                                <div class="text-red-600 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Departamento</label>
                            <input type="text" id="input_Departamento" name="Departamento" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" data-bpm-cerrar="createModal" class="px-3 py-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300 w-full">
                        Cancelar
                    </button>
                    <button type="submit" class="px-3 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700 w-full">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Editar: sin botón en el navbar desde ae3fde85 (se comentó el de editar); el JS lo abre con
         cualquier [data-bpm-accion="editar"] y toma los valores de los data-* de la fila. --}}
    <div id="editModal" class="hidden fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-4 py-3 rounded-t-lg flex justify-between items-center">
                <h3 class="text-lg font-semibold">Editar Registro</h3>
                <button type="button" data-bpm-cerrar="editModal" class="text-white hover:text-gray-200" aria-label="Cerrar">
                    <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
                </button>
            </div>
            <form id="editForm" method="POST" class="p-4">
                @csrf
                @method('PUT')
                <input type="hidden" id="edit_Status" name="Status">

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="col-span-2 bg-yellow-50 border-2 border-yellow-300 rounded-lg p-3">
                        <label class="block text-sm font-semibold text-yellow-800 mb-1">
                            <i class="fa-solid fa-hashtag mr-1" aria-hidden="true"></i>
                            No. Folio
                        </label>
                        <div class="text-2xl font-bold text-yellow-600" id="edit_FolioDisplay"></div>
                        <input type="hidden" id="edit_Folio" name="Folio">
                    </div>
                    <div class="col-span-2">
                        <label for="edit_Fecha" class="block text-xs font-medium text-gray-700 mb-1">Fecha *</label>
                        <input type="{{ $cfgVariante['tipoFechaEdicion'] }}" id="edit_Fecha" name="Fecha" required class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                    </div>
                </div>

                <!-- Sección: Quien Entrega -->
                <div class="mb-3">
                    <h4 class="text-sm font-semibold text-blue-700 mb-2">Quien Entrega</h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre</label>
                            <input type="text" id="edit_NombreEmplEnt" name="NombreEmplEnt" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">No. Empleado</label>
                            <input type="text" id="edit_CveEmplEnt" name="CveEmplEnt" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Turno</label>
                            <input type="text" id="edit_TurnoEntrega" name="TurnoEntrega" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                    </div>
                </div>

                <!-- Sección: Quien Recibe -->
                <div class="mb-3">
                    <h4 class="text-sm font-semibold text-green-700 mb-2">Quien Recibe</h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label for="edit_select_NombreEmplRec" class="block text-xs font-medium text-gray-700 mb-1">Nombre</label>
                            <select id="edit_select_NombreEmplRec" name="NombreEmplRec"
                                    data-bpm-autollenar data-llenar-numero="edit_input_CveEmplRec" data-llenar-turno="edit_input_TurnoRecibe"
                                    class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                                <option value="">Seleccione...</option>
                                @foreach($usuarios as $usuario)
                                    <option value="{{ $usuario->nombre }}"
                                            data-numero="{{ $usuario->numero_empleado }}"
                                            data-turno="{{ $usuario->turno }}">
                                        {{ $usuario->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">No. Empleado</label>
                            <input type="text" id="edit_input_CveEmplRec" name="CveEmplRec" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Turno</label>
                            <input type="text" id="edit_input_TurnoRecibe" name="TurnoRecibe" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" data-bpm-cerrar="editModal" class="px-3 py-1.5 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300 w-full">
                        Cancelar
                    </button>
                    <button type="submit" class="px-3 py-1.5 text-sm text-white bg-yellow-600 rounded hover:bg-yellow-700 w-full">
                        Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Borrado (data-bpm-accion="eliminar", también sin botón en el navbar): el JS pone la acción y lo envía. --}}
    <form id="deleteForm" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection

@push('scripts')
    @vite('resources/js/modulos/urdido/comun/bpm/index.ts')
@endpush
