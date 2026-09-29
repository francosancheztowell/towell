@extends('layouts.app')

@section('page-title', 'Producción Reenconado Cabezuela')

@section('navbar-right')
<div class="flex items-center gap-2">
    {{-- Botón de Filtros --}}
    <x-navbar.button-report
    id="btn-open-filters"
    title="Filtros"
    icon="fa-filter"
    module="Producción Reenconado Cabezuela"
    iconColor="text-purple-600"
    hoverBg="hover:bg-purple-100"
    class="text-sm" />

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

@push('styles')
<style>
    #tabla-registros tr.selected {
        background-color: #3b82f6 !important;
        color: white !important;
    }
    #tabla-registros tr.selected td {
        color: white !important;
    }
</style>
@endpush

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
<div class="w-full" id="pagina-reenconado" data-pagina='@json($configPagina)'>
    <div class="overflow-x-auto  bg-white w-full">
        <table class="min-w-full table-capture text-sm" id="tabla-registros">
            <thead class="text-white">
                <tr class="text-center align-middle">
                    <th class="w-[90px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Folio</th>
                    <th class="w-[90px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Fecha</th>
                    <th class="w-[72px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Turno</th>
                    <th class="min-w-[160px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Operador</th>
                    <th class="w-[72px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Calibre</th>
                    <th class="w-[90px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Fibra</th>
                    <th class="w-[90px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Cód. Color</th>
                    <th class="min-w-[160px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Color</th>
                    <th class="w-[90px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Cantidad</th>
                    <th class="w-[90px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Cabezuela</th>
                    <th class="w-[72px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Conos</th>
                    <th class="w-[90px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Hrs</th>
                    <th class="w-[90px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Eficiencia</th>
                    <th class="min-w-[160px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Observaciones</th>
                </tr>
            </thead>
            <tbody id="rows-body" class="text-gray-800">
                @forelse($registros as $r)
                    <tr class="table-row odd:bg-white even:bg-gray-50 hover:bg-blue-50 cursor-pointer"
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
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Folio }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Date ? $r->Date->format('Y-m-d') : '' }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Turno }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->nombreEmpl }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Calibre }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->FibraTrama }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->CodColor }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Color }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ is_null($r->Cantidad) ? '' : number_format($r->Cantidad, 2) }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ is_null($r->Cabezuela) ? '' : number_format($r->Cabezuela, 2) }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Conos }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ is_null($r->Horas) ? '' : number_format($r->Horas, 2) }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ is_null($r->Eficiencia) ? '' : number_format($r->Eficiencia, 2) }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Obs }}</td>
                    </tr>
                @empty
                    <tr class="odd:bg-white even:bg-gray-50">
                        <td colspan="14" class="text-center text-gray-500 py-4">Sin registros</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
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

{{-- Modal FILTROS --}}
<div id="modal-filters" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="modal-filters-titulo">
    <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-4 m-4">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800" id="modal-filters-titulo">
                <i class="fa-solid fa-filter text-purple-600 mr-2" aria-hidden="true"></i>Filtros
            </h2>
            <button type="button" data-accion="cerrar-filtros" aria-label="Cerrar filtros"
                    class="text-slate-500 hover:text-slate-700 text-5xl leading-none">&times;</button>
        </div>

        <div class="space-y-4 mb-4">
            {{-- Filtro por Operador --}}
            <div class="p-4 rounded-lg border-2 border-gray-300 bg-gray-50">
                <label for="filter-operador" class="block text-xs text-gray-600 mb-2">
                    <i class="fa-solid fa-user mr-1" aria-hidden="true"></i>Operador
                </label>
                <select id="filter-operador"
                        class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-500">
                    <option value="">Todos los operadores</option>
                </select>
            </div>

            {{-- Filtro por Calibre --}}
            <div class="p-4 rounded-lg border-2 border-gray-300 bg-gray-50">
                <label for="filter-calibre" class="block text-xs text-gray-600 mb-2">
                    <i class="fa-solid fa-ruler mr-1" aria-hidden="true"></i>Calibre
                </label>
                <select id="filter-calibre"
                        class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-500">
                    <option value="">Todos los calibres</option>
                </select>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" id="btn-clear-filters" data-accion="limpiar-filtros"
                    class="flex-1 px-3 py-2 rounded-lg border border-gray-300 bg-blue-500 text-white transition text-sm">
                <i class="fa-solid fa-eraser mr-1" aria-hidden="true"></i>Limpiar
            </button>
        </div>
    </div>
</div>

@endsection
