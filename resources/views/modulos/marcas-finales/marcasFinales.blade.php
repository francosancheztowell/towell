@extends('layouts.app')

@section('page-title', 'Marcas Finales')

@php
    use Carbon\Carbon;
    $configPagina = [
        'ultimoFolio' => isset($ultimoFolio) ? $ultimoFolio->Folio : null,
        'esSupervisor' => $esSupervisor ?? false,
        'rutas' => [
            'nuevo' => route('marcas.nuevo'),
            'show' => route('marcas.show', ['folio' => '__FOLIO__']),
            'visualizar' => route('marcas.visualizar', ['folio' => '__FOLIO__']),
            'finalizar' => route('marcas.finalizar', ['folio' => '__FOLIO__']),
            'actualizarRegistro' => route('marcas.actualizar.registro', ['folio' => '__FOLIO__']),
            'reporte' => route('marcas.reporte'),
        ],
    ];
@endphp

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create
            id="btn-nuevo"
            data-accion="nuevo"
            title="Nuevo"
            module="Marcas Finales"

         />

        <x-navbar.button-edit
            id="btn-editar"
            data-accion="editar"
            title="Editar"
            module="Marcas Finales"

         />
        <x-navbar.button-report
            id="btn-visualizar"
            data-accion="visualizar"
            title="Visualizar"
            module="Marcas Finales"

            icon="fa-eye"
            iconColor="text-gray-700"
            hoverBg="hover:bg-gray-100" />

        <x-navbar.button-report
            id="btn-finalizar"
            data-accion="finalizar"
            title="Finalizar"
            module="Marcas Finales"

            icon="fa-check"
            iconColor="text-orange-600"
            hoverBg="hover:bg-orange-100" />

        <x-navbar.button-create
            id="btn-fechas"
            data-accion="fechas"
            title="Fechas"
            module="Marcas Finales"
            icon="fa-calendar"
            iconColor="text-indigo-600"
            hoverBg="hover:bg-indigo-100"
            text=""
            bg=""
            />

        @if($esSupervisor ?? false)
        <x-navbar.button-report
            id="btn-editar-supervisor"
            data-accion="editar-supervisor"
            title="Editar (Supervisor)"
            module="Marcas Finales"
            :disabled="true"
            icon="fa-unlock"
            iconColor="text-red-600"
            hoverBg="hover:bg-red-100" />
        @endif
    </div>
@endsection

@section('content')
{{-- JS: resources/js/modulos/tejido/marcas-finales/consultar/index.ts --}}
<div id="pagina-marcas-consultar" class="w-full max-w-7xl mx-auto h-full overflow-hidden flex flex-col px-4 md:px-8 lg:px-12 xl:px-16 py-4" data-pagina='@json($configPagina)'>
    <div class="flex flex-col flex-1 bg-white rounded-lg shadow-md overflow-hidden">
    @if(isset($marcas) && $marcas->count() > 0)
        <!-- Header fijo (sticky) dentro del contenedor -->
        <div class="bg-blue-600 text-white sticky top-0 z-10">
            <table class="w-full text-sm">
                <colgroup>
                    <col style="width: 20%">
                    <col style="width: 20%">
                    <col style="width: 15%">
                    <col style="width: 25%">
                    <col style="width: 20%">
                </colgroup>
                <thead>
                    <tr>
                        <th class="px-2 md:px-4 py-2 md:py-3 text-left uppercase text-xs md:text-sm font-semibold">Folio</th>
                        <th class="px-2 md:px-4 py-2 md:py-3 text-left uppercase text-xs md:text-sm font-semibold">Fecha</th>
                        <th class="px-2 md:px-4 py-2 md:py-3 text-left uppercase text-xs md:text-sm font-semibold">Turno</th>
                        <th class="px-2 md:px-4 py-2 md:py-3 text-left uppercase text-xs md:text-sm font-semibold">Empleado</th>
                        <th class="px-2 md:px-4 py-2 md:py-3 text-left uppercase text-xs md:text-sm font-semibold">Status</th>
                    </tr>
                </thead>
            </table>
        </div>
        <!-- Solo el contenido con scroll -->
        <div class="flex-1 overflow-auto">
            <table class="w-full text-sm">
                <colgroup>
                    <col style="width: 20%">
                    <col style="width: 20%">
                    <col style="width: 15%">
                    <col style="width: 25%">
                    <col style="width: 20%">
                </colgroup>
                <tbody class="divide-y divide-gray-100">
              @foreach($marcas as $marca)
                            <tr class="hover:bg-blue-500 hover:text-white cursor-pointer transition-colors marca-row {{ isset($ultimoFolio) && $ultimoFolio->Folio == $marca->Folio ? 'bg-blue-100 border-l-4 border-blue-600' : '' }}"
                  id="row-{{ $marca->Folio }}"
                  data-folio="{{ $marca->Folio }}"
                  data-status="{{ $marca->Status }}"
                  tabindex="0"
                  aria-selected="false">
                <td class="px-2 md:px-4 py-2 md:py-3 font-semibold text-gray-900 text-sm md:text-base truncate hover:text-white">{{ $marca->Folio }}</td>
                                <td class="px-2 md:px-4 py-2 md:py-3 text-gray-900 text-sm md:text-base truncate hover:text-white">
                                    @if($marca->Date)
                                        {{ Carbon::parse($marca->Date)->format('d/m/Y') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-2 md:px-4 py-2 md:py-3 text-gray-900 text-sm md:text-base truncate hover:text-white">
                                    {{ $marca->Turno }}@if((int) ($marca->turno_capturista ?? 0) === 4)<span class="text-amber-600 font-medium" title="Las marcas son del turno {{ $marca->Turno }}; las capturo personal de turno 4 (cubre descansos)"> (turno 4)</span>@endif
                                </td>
                <td class="px-2 md:px-4 py-2 md:py-3 text-gray-900 text-sm md:text-base truncate hover:text-white">{{ $marca->numero_empleado ?? 'N/A' }}</td>
                <td class="px-2 md:px-4 py-2 md:py-3">
                  @if($marca->Status === 'Finalizado')
                    <span class="px-2 md:px-3 py-1 md:py-1.5 rounded-full text-xs md:text-sm font-semibold bg-green-100 text-green-700">Finalizado</span>
                  @elseif($marca->Status === 'En Proceso')
                    <span class="px-2 md:px-3 py-1 md:py-1.5 rounded-full text-xs md:text-sm font-semibold bg-blue-100 text-blue-700">En Proceso</span>
                  @else
                    <span class="px-2 md:px-3 py-1 md:py-1.5 rounded-full text-xs md:text-sm font-semibold bg-yellow-100 text-yellow-700">{{ $marca->Status }}</span>
                  @endif
                </td>
              </tr>
              @endforeach
                </tbody>
            </table>
        </div>
    @else
            <!-- Sin Registros -->
        <div class="flex flex-col items-center justify-center flex-1 p-8 text-center">
            <h2 class="text-2xl font-semibold text-gray-700 mb-3">No hay marcas registradas</h2>
            <p class="text-gray-500 text-lg mb-6">Toca "Nueva Marca" para crear el primer registro.</p>
        <x-navbar.button-create
                  id="btn-nuevo-empty"
            data-accion="nuevo"
          title="Nuevo"
          module="Marcas Finales"
                    :checkPermission="false"
          :disabled="false"
          icon="fa-plus"
          iconColor="text-green-600"
          hoverBg="hover:bg-green-100" />
        </div>
    @endif
    </div>
</div>

    @php
        // Preparar fechas únicas de los folios para el modal
        $fechasUnicas = (isset($marcas) && $marcas->count() > 0)
            ? $marcas->pluck('Date')
                ->filter()
                ->map(function ($d) { try { return Carbon::parse($d)->format('Y-m-d'); } catch (\Exception $e) { return null; } })
                ->filter()
                ->unique()
                ->sort()
            : collect();
    @endphp

    <!-- Modal Fechas -->
    <div id="modal-fechas" class="hidden fixed inset-0 z-50 flex items-center justify-center" role="dialog" aria-modal="true" aria-labelledby="modal-fechas-titulo">
        <div class="absolute inset-0 bg-black/40" data-accion="cerrar-fechas"></div>
        <div class="relative w-full max-w-md rounded-lg bg-white shadow-lg">
            <div class="px-4 py-3 border-b flex items-center justify-between">
                <h3 id="modal-fechas-titulo" class="text-lg font-semibold text-gray-800">Selecciona una fecha</h3>
                <button type="button" id="modal-fechas-close" data-accion="cerrar-fechas" class="text-gray-500 hover:text-gray-700" aria-label="Cerrar">
                    <i class="fa fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="p-4">
                <label for="input-fechas" class="block text-sm font-medium text-gray-700 mb-1">Fecha de folios</label>
                <input type="date" id="input-fechas" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="{{ Carbon::now()->format('Y-m-d') }}">
            </div>
            <div class="px-4 py-3 border-t flex justify-end gap-2">
                <button type="button" id="modal-fechas-ok" class="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700" data-accion="generar-reporte">Generar Reporte</button>
            </div>
        </div>
    </div>

    {{-- Modal Editar Registro (Supervisor) --}}
    @if($esSupervisor ?? false)
    <div id="modal-editar-registro" class="hidden fixed inset-0 z-50 flex items-center justify-center" role="dialog" aria-modal="true" aria-labelledby="modal-editar-titulo">
        <div class="absolute inset-0 bg-black/40" data-accion="cerrar-edicion"></div>
        <div class="relative w-full max-w-lg rounded-lg bg-white shadow-lg">
            <div class="px-4 py-3 border-b flex items-center justify-between bg-red-50">
                <h3 id="modal-editar-titulo" class="text-lg font-semibold text-gray-800">
                    <i class="fa-solid fa-unlock text-red-600 mr-2" aria-hidden="true"></i>Editar Registro
                    <span id="edit-folio-title" class="text-red-600 font-bold"></span>
                </h3>
                <button type="button" id="modal-editar-close" data-accion="cerrar-edicion" class="text-gray-500 hover:text-gray-700" aria-label="Cerrar">
                    <i class="fa fa-times text-xl" aria-hidden="true"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label for="edit-fecha" class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                    <input type="date" id="edit-fecha" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="edit-turno" class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                    <select id="edit-turno" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="1">Turno 1</option>
                        <option value="2">Turno 2</option>
                        <option value="3">Turno 3</option>
                    </select>
                    <p id="edit-turno-capturista" class="mt-1 text-xs text-amber-600 hidden">Capturado por personal de turno 4 (cubre descansos)</p>
                </div>
                <div>
                    <label for="edit-empleado" class="block text-sm font-medium text-gray-700 mb-1">No. Empleado</label>
                    <input type="text" id="edit-empleado" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="edit-nombre" class="block text-sm font-medium text-gray-700 mb-1">Nombre Empleado</label>
                    <input type="text" id="edit-nombre" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="edit-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="edit-status" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="En Proceso">En Proceso</option>
                        <option value="Finalizado">Finalizado</option>
                    </select>
                </div>
            </div>
            <div class="px-4 py-3 border-t flex justify-end gap-2">
                <button type="button" id="modal-editar-cancel" data-accion="cerrar-edicion" class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="button" id="modal-editar-save" data-accion="guardar-registro" class="px-4 py-2 rounded-md bg-red-600 text-white hover:bg-red-700">
                    <i class="fa-solid fa-save mr-1" aria-hidden="true"></i>Guardar Cambios
                </button>
            </div>
        </div>
    </div>
    @endif


@push('scripts')
    @vite('resources/js/modulos/tejido/marcas-finales/consultar/index.ts')
@endpush
@endsection
