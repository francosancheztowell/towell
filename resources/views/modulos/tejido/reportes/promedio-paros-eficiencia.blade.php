@extends('layouts.app')

@section('page-title', 'Promedio Paros y Eficiencia')

@section('navbar-right')
    <button type="button" data-ui-modal-open="modalRangoTejido"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search" aria-hidden="true"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('tejido.reportes.promedio-paros-eficiencia.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4">
        @if (session('error'))
            <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-white">Promedio Paros y Eficiencia</h2>
                @if (!empty($fechaIni) && !empty($fechaFin))
                    <span class="text-white text-sm">
                        {{ \Carbon\Carbon::parse($fechaIni)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}
                    </span>
                @endif
            </div>

            <div class="p-6">
                <div class="border border-dashed border-gray-300 rounded-xl p-8 text-center">
                    <i class="fas fa-chart-line text-5xl text-blue-200 mb-4"></i>
                    @if (empty($fechaIni) || empty($fechaFin))
                        <p class="text-gray-600 text-lg">Seleccione un rango de fechas para generar el reporte en Excel (incluye gráficas de líneas en JACQ, JACQ-SULZ, SMIT e ITEMA).</p>
                        <button type="button" data-ui-modal-open="modalRangoTejido"
                            class="mt-4 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i> Seleccionar fechas
                        </button>
                    @else
                        <p class="text-gray-700 text-lg">El reporte Excel incluye las tablas de promedio y gráficas dinámicas por sala (misma fuente que las tablas dinámicas).</p>
                        <p class="text-sm text-gray-500 mt-2">Se combinan Marcas Finales y Cortes de Eficiencia por fecha, turno y telar.</p>
                        <a href="{{ route('tejido.reportes.promedio-paros-eficiencia.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
                            class="inline-flex mt-5 items-center gap-2 px-6 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-file-excel"></i> Descargar Excel
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('modulos.tejido.reportes.partials.rango-fechas', [
        'ruta' => route('tejido.reportes.promedio-paros-eficiencia'),
        'titulo' => 'Consultar rango',
        'descripcion' => 'Seleccione la fecha inicial y final del reporte.',
        'abrirAlCargar' => empty($fechaIni) || empty($fechaFin),
    ])
@endsection

@push('scripts')
    @vite('resources/js/modulos/tejido/reportes/promedio-paros/index.ts')
@endpush
