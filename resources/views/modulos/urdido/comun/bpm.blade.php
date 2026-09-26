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
            'badge' => ['Creado' => 'bg-blue-100 text-blue-800', 'Terminado' => 'bg-yellow-100 text-yellow-800'],
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
            'badge' => ['Creado' => 'bg-yellow-100 text-yellow-800', 'Terminado' => 'bg-blue-100 text-blue-800'],
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
        <x-navbar.button-report
            id="btn-open-filters"
            data-bpm-accion="filtros"
            title="Filtros"
            icon="fa-filter"
            text="Filtrar"
            :module="$cfgVariante['modulo']"
            iconColor="text-white"
            hoverBg="hover:bg-green-600"
            class="text-white"
            bg="bg-green-600" />
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
    <div class="overflow-x-auto overflow-y-auto rounded-lg border bg-white shadow-sm mt-4 mx-4" style="max-height: 70vh;">
        <table id="bpmTable" class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Folio</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Status</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Fecha</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">No Recibe</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Nombre Recibe</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Turno Recibe</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">No Entrega</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Nombre Entrega</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Turno Entrega</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Cve Autoriza</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Nombre Autoriza</th>
                </tr>
            </thead>
            <tbody id="tb-body">
                @forelse($items as $item)
                    @php
                        $statusClass = $cfgVariante['badge'][$item->Status]
                            ?? ($item->Status === 'Autorizado' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800');
                    @endphp
                    <tr class="table-row hover:bg-blue-50 cursor-pointer transition-colors"
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
                        <td class="px-4 py-3 whitespace-nowrap font-medium">{{ $item->Folio }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold {{ $statusClass }}">
                                {{ $item->Status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->Fecha ? $item->Fecha->format('d/m/Y') : '' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->CveEmplRec }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->NombreEmplRec }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-center">{{ $item->TurnoRecibe }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->CveEmplEnt }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->NombreEmplEnt }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-center">{{ $item->TurnoEntrega }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->CveEmplAutoriza }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->{$cfgVariante['colAutoriza']} }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-8 text-center text-gray-500">
                            No hay registros disponibles
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal FILTROS --}}
    <div id="modal-filters" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-4 m-4">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800">
                    <i class="fa-solid fa-filter text-purple-600 mr-2" aria-hidden="true"></i>Filtros
                </h2>
                <button type="button" data-bpm-cerrar="modal-filters" aria-label="Cerrar"
                        class="text-slate-500 hover:text-slate-700 text-5xl leading-none">&times;</button>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-4">
                <button type="button" id="btn-filter-finished" data-bpm-filtro="terminados"
                        class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                    <i class="fa-solid fa-check-circle text-2xl mb-2 block" aria-hidden="true"></i>
                    <div class="font-semibold text-sm">Finalizados</div>
                </button>

                <button type="button" id="btn-filter-my-folios" data-bpm-filtro="misFolios"
                        class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-blue-100 border-blue-400 text-blue-800">
                    <i class="fa-solid fa-user text-2xl mb-2 block" aria-hidden="true"></i>
                    <div class="font-semibold text-sm">Mis Folios</div>
                </button>

                <button type="button" id="btn-filter-all" data-bpm-filtro="todos"
                        class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                    <i class="fa-solid fa-list text-2xl mb-2 block" aria-hidden="true"></i>
                    <div class="font-semibold text-sm">Todos</div>
                </button>

                <div class="p-4 rounded-lg border-2 border-gray-300 bg-gray-50">
                    <label for="filter-turno" class="block text-xs text-gray-600 mb-2 text-center">
                        <i class="fa-solid fa-clock mr-1" aria-hidden="true"></i>Turno
                    </label>
                    <select id="filter-turno"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-500">
                        <option value="">Todos</option>
                        <option value="1">Turno 1</option>
                        <option value="2">Turno 2</option>
                        <option value="3">Turno 3</option>
                        <option value="4">Turno 4</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" id="btn-clear-filters" data-bpm-accion="limpiar-filtros"
                        class="flex-1 px-3 py-2 rounded-lg border border-gray-300 bg-blue-500 text-white transition text-sm">
                    <i class="fa-solid fa-eraser mr-1" aria-hidden="true"></i>Limpiar
                </button>
            </div>
        </div>
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
