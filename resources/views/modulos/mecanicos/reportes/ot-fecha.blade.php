@extends('layouts.app')

@section('page-title', 'OT x Fecha')

@php
    use App\Services\Mecanicos\ReporteOtFechaService;
    $hayDatos = $reporte && $reporte['mecanicos'] !== [];
@endphp

@section('navbar-right')
    @if ($hayDatos)
        <button type="button" data-ot-fecha-descargar="excel"
            class="flex items-center gap-2 min-h-touch px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel" aria-hidden="true"></i> Excel
        </button>
        <button type="button" data-ot-fecha-descargar="pdf"
            class="flex items-center gap-2 min-h-touch px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-pdf" aria-hidden="true"></i> PDF
        </button>
        <button type="button" data-ot-fecha-imagen
            class="flex items-center gap-2 min-h-touch px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-image" aria-hidden="true"></i> Imagen
        </button>
    @endif
@endsection

@section('content')
    <div class="w-full p-4" id="reporte-ot-fecha"
         data-excel-url="{{ route('mecanicos.reportes.ot-fecha.excel') }}"
         data-pdf-url="{{ route('mecanicos.reportes.ot-fecha.pdf') }}"
         data-imagen-url="{{ route('mecanicos.reportes.ot-fecha.telegram-imagen') }}"
         data-nombre-imagen="{{ $reporte ? 'ot-fecha_'.$reporte['desde'].'_'.$reporte['hasta'].'.png' : 'ot-fecha.png' }}">
        @if ($error)
            <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg" role="alert">{{ $error }}</div>
        @endif

        <form method="GET" action="{{ route('mecanicos.reportes.ot-fecha') }}"
              class="bg-white rounded-xl shadow border border-gray-200 p-4 mb-4 grid grid-cols-1 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-end gap-4">
            <div>
                <label for="desde" class="block text-sm font-semibold text-gray-700 mb-1">Fecha inicial</label>
                <input type="date" id="desde" name="desde" value="{{ $desde }}" required
                       class="w-full min-h-touch px-3 py-2 border border-gray-300 rounded-md text-base">
            </div>
            <div>
                <label for="hasta" class="block text-sm font-semibold text-gray-700 mb-1">Fecha final</label>
                <input type="date" id="hasta" name="hasta" value="{{ $hasta }}" min="{{ $desde }}" required
                       class="w-full min-h-touch px-3 py-2 border border-gray-300 rounded-md text-base">
            </div>
            <div class="sm:col-span-2 lg:min-w-72">
                <label for="mecanico" class="block text-sm font-semibold text-gray-700 mb-1">Mecánico</label>
                <select id="mecanico" name="mecanico"
                        class="w-full min-h-touch px-3 py-2 border border-gray-300 rounded-md text-base bg-white">
                    <option value="">Todos los mecánicos</option>
                    @foreach ($mecanicos as $opcion)
                        <option value="{{ $opcion['cve'] }}" @selected($opcion['cve'] === $mecanico)>
                            {{ $opcion['nombre'] }} ({{ $opcion['cve'] }})
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" id="btn-consultar"
                    class="sm:col-span-2 lg:col-span-1 min-h-touch px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-base font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-search mr-1" aria-hidden="true"></i> Consultar
            </button>
        </form>

        @if (! $reporte)
            <div class="bg-white rounded-xl shadow border border-gray-200 px-6 py-16 text-center">
                <i class="fa-solid fa-calendar-days text-gray-300 text-4xl mb-3" aria-hidden="true"></i>
                <p class="text-gray-600 font-medium">Elige el rango de fechas y pulsa Consultar</p>
                <p class="text-sm text-gray-500 mt-1">Sin mecánico seleccionado se muestran todos los que hicieron alguna OT en el rango.</p>
            </div>
        @elseif (! $hayDatos)
            <div class="bg-white rounded-xl shadow border border-gray-200 px-6 py-16 text-center">
                <i class="fa-solid fa-screwdriver-wrench text-gray-300 text-4xl mb-3" aria-hidden="true"></i>
                <p class="text-gray-600 font-medium">No hay órdenes de trabajo en el rango seleccionado.</p>
            </div>
        @else
            <form id="form-export-ot-fecha" method="POST" class="hidden">
                @csrf
                <input type="hidden" name="desde" value="{{ $reporte['desde'] }}">
                <input type="hidden" name="hasta" value="{{ $reporte['hasta'] }}">
                <input type="hidden" name="mecanico" value="{{ $reporte['mecanico'] }}">
            </form>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-4">
                @foreach ($reporte['mecanicos'] as $resumen)
                    <div class="bg-white rounded-xl shadow border border-gray-200 p-4">
                        <p class="font-semibold text-gray-900 truncate" title="{{ $resumen['nombre'] }}">{{ $resumen['nombre'] }}</p>
                        <p class="text-caption text-gray-500 mb-2">Clave {{ $resumen['cve'] }}</p>
                        <div class="flex justify-between text-sm text-gray-700">
                            <span>{{ $resumen['total_ots'] }} OT · {{ $resumen['total_trabajos'] }} trabajos</span>
                            <span class="font-semibold">{{ ReporteOtFechaService::duracion($resumen['total_minutos']) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
                <div id="hoja-ot-fecha" class="bg-white p-5">
                    <div class="flex flex-col items-start gap-1 mb-5">
                        <img src="{{ asset('images/fondosTowell/logo.png') }}" alt="Towell" class="h-14 mb-1">
                        <h2 class="text-lg font-bold text-gray-900 tracking-wide">ÓRDENES DE TRABAJO POR FECHA</h2>
                        <p class="text-sm text-gray-600">
                            Periodo: {{ \Carbon\Carbon::parse($reporte['desde'])->format('d/m/Y') }}
                            al {{ \Carbon\Carbon::parse($reporte['hasta'])->format('d/m/Y') }}
                            · {{ $reporte['mecanico_nombre'] !== '' ? $reporte['mecanico_nombre'] : 'Todos los mecánicos' }}
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        @include('modulos.mecanicos.reportes._ot-fecha-tabla', ['reporte' => $reporte])
                    </div>
                </div>
            </div>
        @endif
    </div>

    @vite('resources/js/modulos/mecanicos/reportes/ot-fecha/index.ts')
@endsection
