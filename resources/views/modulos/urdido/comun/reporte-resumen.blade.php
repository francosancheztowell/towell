{{--
  Resumen semanal de Urdido y Engomado (19-01): una vista para las dos variantes.
  @param string $variante  'urdido' | 'engomado'. Usa del controller: $fechaIni, $fechaFin, $datosSemanales.
  La vista original hace @extends('layouts.app') y @include de esta con la variante.
  JS: resources/js/modulos/urdido/comun/reporte-resumen/index.ts (Chart.js dentro del bundle).
--}}
@php
    $v = [
        'urdido' => [
            'titulo' => 'Resumen Semanal Urdido',
            'nombre' => 'Resumen Urdido',
            'encabezado' => 'URDIDO',
            'ruta' => 'urdido.reportes.urdido.resumen',
            'rutaExcel' => 'urdido.reportes.urdido.resumen.excel',
            'modal' => 'modalConsultarResumenUrdido',
            'canvasResumen' => 'resumenChartUrdido',
            'canvasEficiencia' => 'eficienciaChartUrdido',
            'eficienciaNegra' => true,
        ],
        'engomado' => [
            'titulo' => 'Resumen Semanal Engomado',
            'nombre' => 'Resumen Engomado',
            'encabezado' => 'ENGOMADO',
            'ruta' => 'engomado.reportes.resumen-engomado',
            'rutaExcel' => 'engomado.reportes.resumen-engomado.excel',
            'modal' => 'modalConsultarResumenEngomado',
            'canvasResumen' => 'resumenChart',
            'canvasEficiencia' => 'eficienciaChart',
            'eficienciaNegra' => false,
        ],
    ][$variante];
    $configResumen = ['datos' => array_values($datosSemanales ?? [])];
@endphp

@section('page-title', $v['titulo'])

@section('navbar-right')
    <button type="button" data-accion-resumen="abrir" aria-haspopup="dialog"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route($v['rutaExcel'], ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4" id="reporte-resumen-{{ $variante }}-container"
         data-reporte-resumen='@json($configResumen)'>
        <div class="bg-white rounded-t-lg px-4 py-2 flex flex-wrap items-center gap-4">
            <span class="font-bold text-gray-800">{{ mb_strtoupper($v['titulo']) }}</span>
            <span class="text-gray-600 text-sm">
                {{ $fechaIni ? \Carbon\Carbon::parse($fechaIni)->translatedFormat('d/m/Y') : '—' }}
                al
                {{ $fechaFin ? \Carbon\Carbon::parse($fechaFin)->translatedFormat('d/m/Y') : '—' }}
            </span>
            @if (!empty($datosSemanales))
                <span class="text-gray-500 text-sm">{{ count($datosSemanales) }} semanas</span>
            @endif
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 bg-white border border-t-0 border-gray-300 rounded-b-lg p-4">
            <!-- Tabla de Datos -->
            <div class="overflow-x-auto">
                @if (!empty($datosSemanales))
                    <h2 class="text-2xl font-bold text-center mb-4">{{ $v['encabezado'] }}</h2>
                    <table class="w-full text-sm border-collapse">
                        <thead class="bg-red-600">
                            <tr>
                                <th class="px-2 py-1 text-center font-semibold text-xs border border-gray-300 text-white">Semana</th>
                                <th class="px-2 py-1 text-center font-semibold text-xs border border-gray-300 text-white">No. de ORDENES</th>
                                <th class="px-2 py-1 text-center font-semibold text-xs border border-gray-300 text-white">No. de julios</th>
                                <th class="px-2 py-1 text-center font-semibold text-xs border border-gray-300 text-white">KG</th>
                                <th class="px-2 py-1 text-center font-semibold text-xs border border-gray-300 text-white">Metros</th>
                                <th class="px-2 py-1 text-center font-semibold text-xs border border-gray-300 text-white">Peso promedio por julio</th>
                                <th class="px-2 py-1 text-center font-semibold text-xs border border-gray-300 text-white">Metros promedio por julio</th>
                                <th class="px-2 py-1 text-center font-semibold text-xs border border-gray-300 text-white">Cuenta promedio por julio</th>
                                <th class="px-2 py-1 text-center font-semibold text-xs border border-gray-300 text-white bg-yellow-400{{ $v['eficienciaNegra'] ? ' text-black' : '' }}">EFICIENCIA EN %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($datosSemanales as $semana)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-2 py-0.5 border border-gray-300 font-medium">{{ $semana['semana_label'] ?? '' }}</td>
                                    <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($semana['total_ordenes'] ?? 0, 0) }}</td>
                                    <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($semana['total_julios'] ?? 0, 0) }}</td>
                                    <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($semana['total_kg'] ?? 0, 2) }}</td>
                                    <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($semana['total_metros'] ?? 0, 2) }}</td>
                                    <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($semana['peso_promedio'] ?? 0, 2) }}</td>
                                    <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($semana['metros_promedio'] ?? 0, 2) }}</td>
                                    <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($semana['cuenta_promedio'] ?? 0, 2) }}</td>
                                    <td class="px-2 py-0.5 border border-gray-300 text-right font-bold bg-yellow-100">{{ number_format($semana['eficiencia'] ?? 0, 2) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-info-circle text-4xl mb-2"></i>
                        <p>No hay datos para el rango de fechas seleccionado.</p>
                        <p class="text-sm mt-1">Seleccione un rango de fechas para generar el reporte.</p>
                    </div>
                @endif
            </div>

            <!-- Gráfica -->
            <div class="w-full space-y-6">
                @if (!empty($datosSemanales))
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <canvas id="{{ $v['canvasResumen'] }}" data-grafica-resumen="promedios"></canvas>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <canvas id="{{ $v['canvasEficiencia'] }}" data-grafica-resumen="eficiencia"></canvas>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal no ha cambiado --}}
    <div id="{{ $v['modal'] }}" data-modal-resumen class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="bg-blue-600 text-white px-6 py-4 rounded-t-lg flex items-center justify-between">
                <h3 class="text-lg font-semibold">Consultar {{ $v['nombre'] }}</h3>
                <button type="button" data-accion-resumen="cerrar" class="text-white hover:text-gray-200" aria-label="Cerrar">
                    <i class="fas fa-times text-xl" aria-hidden="true"></i>
                </button>
            </div>

            <form method="GET" action="{{ route($v['ruta']) }}" class="p-6">
                <div class="space-y-4">
                    <div>
                        <label for="fecha_ini" class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicial</label>
                        <input type="date" id="fecha_ini" name="fecha_ini" required
                            value="{{ !empty($fechaIni) ? $fechaIni : \Carbon\Carbon::now()->format('Y-m-d') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="fecha_fin" class="block text-sm font-medium text-gray-700 mb-1">Fecha Final</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" required
                            value="{{ !empty($fechaFin) ? $fechaFin : \Carbon\Carbon::now()->format('Y-m-d') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="button" data-accion-resumen="cerrar"
                        class="flex-1 px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg font-medium transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                        class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                        Consultar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/modulos/urdido/comun/reporte-resumen/index.ts')
@endpush
