@extends('layouts.app')

@section('page-title', 'Órdenes de Trabajo Diarias')

@section('navbar-right')
    @if ($reporte)
        <button type="button" data-ot-descargar="excel"
            class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Excel
        </button>
        <button type="button" data-ot-descargar="pdf"
            class="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
        <button type="button" data-ot-imagen
            class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-image"></i> Imagen
        </button>
    @endif
@endsection

@section('content')
    <div class="w-full p-4" id="reporte-ot-diarias"
         data-excel-url="{{ route('mecanicos.reportes.ot-diarias.excel') }}"
         data-pdf-url="{{ route('mecanicos.reportes.ot-diarias.pdf') }}"
         data-imagen-url="{{ route('mecanicos.reportes.ot-diarias.telegram-imagen') }}"
         data-fecha="{{ $fecha }}"
         data-nombre-imagen="{{ $reporte ? 'ot-diarias_'.$reporte['desde'].'_'.$reporte['hasta'].'.png' : 'ot-diarias.png' }}"
         data-filas="{{ json_encode($reporte['mecanicos'] ?? []) }}">
        @if ($error)
            <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">{{ $error }}</div>
        @endif

        <form method="GET" action="{{ route('mecanicos.reportes.ot-diarias') }}"
              class="bg-white rounded-xl shadow border border-gray-200 p-4 mb-4 flex flex-wrap items-end gap-4">
            <div>
                <label for="fecha" class="block text-sm font-semibold text-gray-700 mb-1">Fecha de inicio</label>
                <input type="date" id="fecha" name="fecha" value="{{ $fecha }}" required
                       class="px-3 py-2 border border-gray-300 rounded-md text-sm">
            </div>
            <button type="submit" id="btn-consultar"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    {{ $fecha === '' ? 'disabled' : '' }}>
                <i class="fas fa-search mr-1"></i> Consultar
            </button>
        </form>

        @if (! $reporte)
            <div class="bg-white rounded-xl shadow border border-gray-200 px-6 py-16 text-center">
                <i class="fa-solid fa-calendar-week text-gray-300 text-4xl mb-3"></i>
                <p class="text-gray-600 font-medium">Pulsa Consultar para armar el reporte de 7 días</p>
                <p class="text-sm text-gray-500 mt-1">La fecha de inicio suele ser un lunes; el rango cubre ese día y los seis siguientes.</p>
            </div>
        @else
            <form id="form-export-ot-diarias" method="POST" class="hidden">
                @csrf
                <input type="hidden" name="fecha" value="{{ $reporte['desde'] }}">
                <div id="inputs-export-ot-diarias"></div>
            </form>

            <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
                <div id="hoja-ot-diarias" class="bg-white p-5">
                    <div class="flex flex-col items-start gap-1 mb-5">
                        <img src="{{ asset('images/fondosTowell/logo.png') }}" alt="Towell" class="h-14 mb-1">
                        <h1 class="text-lg font-bold text-gray-900 tracking-wide">ÓRDENES DE TRABAJO DIARIAS</h1>
                        <p class="text-sm text-gray-600">
                            Periodo: {{ \Carbon\Carbon::parse($reporte['desde'])->format('d/m/Y') }}
                            al {{ \Carbon\Carbon::parse($reporte['hasta'])->format('d/m/Y') }}
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        @include('modulos.mecanicos.reportes._ot-diarias-tabla', ['reporte' => $reporte, 'editable' => true])
                    </div>
                </div>
            </div>
        @endif
    </div>

    @vite('resources/js/modulos/mecanicos/reportes/ot-diarias/index.ts')
@endsection
