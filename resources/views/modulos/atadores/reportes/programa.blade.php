@extends('layouts.app')

@section('page-title', 'Reporte Programa Atadores')

@section('navbar-right')
    <button type="button" data-ui-modal-open="modalRangoTejido"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('atadores.reportes.programa.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4" id="reporte-atadores" data-indice="{{ route('atadores.reportes.index') }}">
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-white">Reporte de Programa Atadores</h2>
                @if (!empty($fechaIni) && !empty($fechaFin))
                    <span class="text-white text-sm">
                        {{ \Carbon\Carbon::parse($fechaIni)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}
                    </span>
                @endif
            </div>

            <div class="p-6">
                @if (empty($fechaIni) || empty($fechaFin))
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-alt text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg">Seleccione un rango de fechas para consultar el reporte</p>
                        <button type="button" data-ui-modal-open="modalRangoTejido"
                            class="mt-4 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i> Seleccionar Fechas
                        </button>
                    </div>
                @else
                    <div class="text-center py-12">
                        <i class="fas fa-file-excel text-6xl text-green-500 mb-4"></i>
                        <p class="text-gray-700 text-lg mb-2">Reporte listo para descargar</p>
                        <p class="text-gray-500 text-sm mb-4">
                            El reporte incluye telas, máquinas y actividades del programa de atadores
                        </p>
                        <a href="{{ route('atadores.reportes.programa.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
                            class="inline-flex items-center gap-2 px-6 py-3 bg-green-500 hover:bg-green-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-download"></i> Descargar Excel
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @include('modulos.tejido.reportes.partials.rango-fechas', [
        'ruta' => route('atadores.reportes.programa'),
        'titulo' => 'Consultar en rango',
        'descripcion' => 'Seleccione la fecha inicial y final del reporte.',
        'abrirAlCargar' => empty($fechaIni) || empty($fechaFin),
    ])
@endsection

@push('scripts')
    @vite('resources/js/modulos/atadores/reportes/programa/index.ts')
@endpush
