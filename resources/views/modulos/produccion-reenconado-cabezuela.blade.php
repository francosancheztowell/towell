@extends('layouts.app')

@section('page-title', 'Producción Reenconado Cabezuela')

@section('navbar-right')
<div class="flex items-center gap-2">
    {{-- "Mis registros" (reenconado/index.ts pone aria-pressed; arranca encendido) y filtros por
         columna (tabla-columnas.ts): reemplazan el modal de operador/calibre. --}}
    <flux:button data-accion="mis-registros" aria-pressed="true" icon="user" class="min-h-touch">Mis registros</flux:button>
    <flux:button data-alternar-filtros="#tabla-registros" aria-pressed="false" icon="funnel"
                 class="min-h-touch min-w-touch" title="Filtrar por columna" aria-label="Filtrar por columna" />

    <x-navbar.button-create
        id="btn-nuevo"
        title="Nuevo"
        module="Producción Reenconado Cabezuela"
    />
    <x-navbar.button-edit
        id="btn-editar"
        title="Editar"
        module="Producción Reenconado Cabezuela"
    />
    <x-navbar.button-delete
        id="btn-eliminar"
        title="Eliminar"
        module="Producción Reenconado Cabezuela"


    />
</div>
@endsection

@push('scripts')
    @vite('resources/js/modulos/tejido/reenconado/index.ts')
@endpush

@section('content')
@php
    $configPagina = [
        'usuario' => auth()->user()->nombre ?? '',
        'rutas' => [
            'calibres' => route('tejido.produccion.reenconado.calibres'),
            'fibras' => route('tejido.produccion.reenconado.fibras'),
            'colores' => route('tejido.produccion.reenconado.colores'),
            'store' => route('tejido.produccion.reenconado.store'),
            'generarFolio' => route('tejido.produccion.reenconado.generar-folio'),
            'update' => route('tejido.produccion.reenconado.update', ['folio' => '__F__']),
            'destroy' => route('tejido.produccion.reenconado.destroy', ['folio' => '__F__']),
        ],
    ];
@endphp
<div class="pantalla-completa" id="pagina-reenconado" data-pagina='@json($configPagina)'>
    {{-- flux:table + .tabla-cebra / .tabla-seleccionable / data-filtros-columna (app.css, tabla-columnas.ts).
         reenconado/index.ts solo pone hidden ("Mis registros") y aria-selected. --}}
    <div class="tabla-pantalla bg-white">
        <flux:table id="tabla-registros" data-filtros-columna class="tabla-cebra tabla-seleccionable">
            <flux:table.columns sticky class="bg-white">
                <flux:table.column align="center">Folio</flux:table.column>
                <flux:table.column align="center">Fecha</flux:table.column>
                <flux:table.column align="center">Turno</flux:table.column>
                <flux:table.column align="center" class="min-w-[160px]">Operador</flux:table.column>
                <flux:table.column align="center">Calibre</flux:table.column>
                <flux:table.column align="center">Fibra</flux:table.column>
                <flux:table.column align="center">Cód. Color</flux:table.column>
                <flux:table.column align="center" class="min-w-[160px]">Color</flux:table.column>
                <flux:table.column align="center">Cantidad</flux:table.column>
                <flux:table.column align="center">Cabezuela</flux:table.column>
                <flux:table.column align="center">Conos</flux:table.column>
                <flux:table.column align="center">Hrs</flux:table.column>
                <flux:table.column align="center">Eficiencia</flux:table.column>
                <flux:table.column align="center" class="min-w-[160px]">Observaciones</flux:table.column>
            </flux:table.columns>
            <flux:table.rows id="rows-body">
                @forelse($registros as $r)
                    <flux:table.row class="table-row" aria-selected="false"
                        data-folio="{{ $r->Folio }}"
                        data-date="{{ $r->Date ? $r->Date->format('Y-m-d') : '' }}"
                        data-turno="{{ $r->Turno }}"
                        data-numero-empleado="{{ $r->numero_empleado }}"
                        data-nombreempl="{{ $r->nombreEmpl }}"
                        data-calibre="{{ $r->Calibre ?? '' }}"
                        data-fibratrama="{{ $r->FibraTrama }}"
                        data-codcolor="{{ $r->CodColor }}"
                        data-color="{{ $r->Color }}"
                        data-cantidad="{{ is_null($r->Cantidad) ? '' : number_format($r->Cantidad, 2, '.', '') }}"
                        data-cabezuela="{{ is_null($r->Cabezuela) ? '' : number_format($r->Cabezuela, 2, '.', '') }}"
                        data-conos="{{ $r->Conos }}"
                        data-horas="{{ is_null($r->Horas) ? '' : number_format($r->Horas, 2, '.', '') }}"
                        data-eficiencia="{{ is_null($r->Eficiencia) ? '' : number_format($r->Eficiencia, 2, '.', '') }}"
                        data-obs="{{ $r->Obs }}">
                        <flux:table.cell align="center">{{ $r->Folio }}</flux:table.cell>
                        <flux:table.cell align="center">{{ $r->Date ? $r->Date->format('Y-m-d') : '' }}</flux:table.cell>
                        <flux:table.cell align="center">{{ $r->Turno }}</flux:table.cell>
                        <flux:table.cell align="center">{{ $r->nombreEmpl }}</flux:table.cell>
                        <flux:table.cell align="center">{{ $r->Calibre }}</flux:table.cell>
                        <flux:table.cell align="center">{{ $r->FibraTrama }}</flux:table.cell>
                        <flux:table.cell align="center">{{ $r->CodColor }}</flux:table.cell>
                        <flux:table.cell align="center">{{ $r->Color }}</flux:table.cell>
                        <flux:table.cell align="center">{{ is_null($r->Cantidad) ? '' : number_format($r->Cantidad, 2) }}</flux:table.cell>
                        <flux:table.cell align="center">{{ is_null($r->Cabezuela) ? '' : number_format($r->Cabezuela, 2) }}</flux:table.cell>
                        <flux:table.cell align="center">{{ $r->Conos }}</flux:table.cell>
                        <flux:table.cell align="center">{{ is_null($r->Horas) ? '' : number_format($r->Horas, 2) }}</flux:table.cell>
                        <flux:table.cell align="center">{{ is_null($r->Eficiencia) ? '' : number_format($r->Eficiencia, 2) }}</flux:table.cell>
                        <flux:table.cell align="center">{{ $r->Obs }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="14" class="py-4 text-center">Sin registros</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    @if($limitado ?? false)
        <p class="mt-2 text-caption text-gray-600">
            Se muestran los {{ $registros->count() }} registros más recientes.
            <a href="{{ request()->fullUrlWithQuery(['todos' => 1]) }}" class="text-primary underline">Ver todo</a>
        </p>
    @endif
</div>

<div id="modalNuevo" class="fixed inset-0 z-50 hidden items-center justify-center" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="fixed inset-0 bg-black/50" data-accion="cerrar-modal"></div>
    <div class="relative bg-white w-[95vw] max-w-5xl rounded shadow-lg">
        <div class="modal-header bg-blue-500 text-white flex items-center justify-between p-2.5">
            <h5 class="text-base md:text-lg font-semibold flex items-center gap-2" id="modal-title">
                Nuevo Registro de Producción
            </h5>
            <div class="flex items-center gap-2">
                <button type="button" class="px-6 py-2.5 rounded bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium transition text-md min-w-[120px]" id="btn-cancelar-modal" data-accion="cerrar-modal">
                    Cancelar
                </button>
                <button type="button" class="px-6 py-2.5 rounded bg-green-600 text-white hover:bg-green-700 font-medium transition shadow-md text-md min-w-[120px]" id="btn-guardar-nuevo" data-accion="guardar">
                    Guardar
                </button>
            </div>
        </div>
        <div class="p-3 modal-scroll max-h-[85vh] overflow-y-auto">
            <div class="grid grid-cols-12 gap-3">
                <div class="col-span-12">
                    <h6 class="text-sm font-semibold text-gray-700 border-b border-gray-200">
                        <i class="fa fa-info-circle mr-2 text-blue-500" aria-hidden="true"></i>Información General
                    </h6>
                </div>

                <div class="col-span-6 md:col-span-2">
                    <label for="f_Folio" class="block text-sm font-medium text-gray-700">Folio</label>
                    <input type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50" id="f_Folio" readonly>
                </div>
                <div class="col-span-6 md:col-span-2">
                    <label for="f_Date" class="block text-sm font-medium text-gray-700">Fecha</label>
                    <input type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Date">
                </div>
                <div class="col-span-6 md:col-span-2">
                    <label for="f_Turno" class="block text-sm font-medium text-gray-700">Turno</label>
                    <select class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Turno">
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                    </select>
                </div>
                <div class="col-span-6 md:col-span-2">
                    <label for="f_numero_empleado" class="block text-sm font-medium text-gray-700">No. Empleado</label>
                    <input type="text" disabled class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_numero_empleado">
                </div>
                <div class="col-span-12 md:col-span-4">
                    <label for="f_nombreEmpl" class="block text-sm font-medium text-gray-700">Nombre del Operador</label>
                    <input type="text" disabled class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_nombreEmpl">
                </div>

                <div class="col-span-12">
                    <h6 class="text-sm font-semibold text-gray-700  border-b border-gray-200">
                        <i class="fa fa-box mr-2 text-blue-500" aria-hidden="true"></i>Detalles del Material
                    </h6>
                </div>

                <div class="col-span-6 md:col-span-3">
                    <label for="f_Calibre" class="block text-sm font-medium text-gray-700">Calibre</label>
                    <select class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Calibre">
                        <option value="">Cargando...</option>
                    </select>
                </div>
                <div class="col-span-6 md:col-span-3">
                    <label for="f_FibraTrama" class="block text-sm font-medium text-gray-700">Fibra</label>
                    <select class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_FibraTrama" disabled>
                        <option value="">Selecciona calibre</option>
                    </select>
                </div>
                <div class="col-span-6 md:col-span-3">
                    <label for="f_CodColor" class="block text-sm font-medium text-gray-700">Cód. Color</label>
                    <select class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_CodColor" disabled>
                        <option value="">Selecciona calibre</option>
                    </select>
                </div>
                <div class="col-span-6 md:col-span-3">
                    <label for="f_Color" class="block text-sm font-medium text-gray-700">Nombre del Color</label>
                    <input type="text" class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50" id="f_Color" readonly>
                </div>

                <div class="col-span-12">
                    <h6 class="text-sm font-semibold text-gray-700 border-b border-gray-200">
                        <i class="fa fa-chart-line mr-2 text-blue-500" aria-hidden="true"></i>Datos de Producción
                    </h6>
                </div>

                <div class="col-span-12 grid grid-cols-5 gap-3">
                    <div>
                        <label for="f_Cantidad" class="block text-sm font-medium text-gray-700">Cantidad (kg)</label>
                        <input type="number" step="0.01" class="w-full min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Cantidad">
                    </div>
                    <div>
                        <label for="f_Cabezuela" class="block text-sm font-medium text-gray-700">Cabezuela</label>
                        <input type="number" step="0.01" class="w-full min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Cabezuela">
                    </div>
                    <div>
                        <label for="f_Conos" class="block text-sm font-medium text-gray-700">Conos</label>
                        <input type="number" step="1" class="w-full min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Conos">
                    </div>
                    <div>
                        <label for="f_Horas" class="block text-sm font-medium text-gray-700">Tiempo (hrs)</label>
                        <input type="number" step="0.01" class="w-full min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Horas">
                    </div>
                    <div>
                        <label for="f_Eficiencia" class="block text-sm font-medium text-gray-700">Eficiencia (%)</label>
                        <input type="number" step="0.01" class="w-full min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Eficiencia">
                    </div>
                </div>
                <div class="col-span-12">
                    <label for="f_Obs" class="block text-sm font-medium text-gray-700">Observaciones</label>
                    <textarea class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-4" id="f_Obs" rows="2"></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
