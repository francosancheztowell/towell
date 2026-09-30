@extends('layouts.app')

@section('page-title', 'Reporte Atadores KM')

@section('navbar-right')
    <button type="button" data-ui-modal-open="modalRangoTejido"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
@endsection

@section('content')
    @php
        // Solo las filas que la gráfica dibuja (con efectividad); la tabla usa todas.
        $filasGrafica = array_values(array_filter($filas ?? [], fn ($f) => $f['efectividad'] !== null));
    @endphp
    <div class="w-full p-4 space-y-4" id="reporte-atadores" data-indice="{{ route('atadores.reportes.index') }}" data-filas='@json($filasGrafica)'>
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-white">Atadores KM (Karl Mayer)</h2>
                @if ($fechaIni)
                    <span class="text-white text-sm">
                        {{ \Carbon\Carbon::parse($fechaIni)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}
                    </span>
                @endif
            </div>

            @if (! $fechaIni)
                <div class="text-center py-12">
                    <i class="fas fa-calendar-alt text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">Seleccione un rango de fechas para consultar el reporte</p>
                </div>
            @elseif (empty($filas))
                <div class="text-center py-12 text-gray-500">No hay atados de barra en ese rango.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-center">
                        <thead class="bg-gray-100 text-gray-700 text-xs uppercase">
                            <tr>
                                <th rowspan="2" class="px-2 py-2 border">Turno</th>
                                <th rowspan="2" class="px-2 py-2 border">Clave montador</th>
                                <th rowspan="2" class="px-2 py-2 border">KM</th>
                                <th rowspan="2" class="px-2 py-2 border">Barra</th>
                                <th rowspan="2" class="px-2 py-2 border">No. julio</th>
                                <th colspan="3" class="px-2 py-1 border">Montado</th>
                                <th colspan="3" class="px-2 py-1 border">Enhebrado</th>
                                <th rowspan="2" class="px-2 py-2 border">Ideal (h)</th>
                                <th rowspan="2" class="px-2 py-2 border">Merma kg</th>
                                <th rowspan="2" class="px-2 py-2 border">Fecha</th>
                            </tr>
                            <tr>
                                <th class="px-2 py-1 border">Inicio</th>
                                <th class="px-2 py-1 border">Fin</th>
                                <th class="px-2 py-1 border">Total</th>
                                <th class="px-2 py-1 border">Inicio</th>
                                <th class="px-2 py-1 border">Fin</th>
                                <th class="px-2 py-1 border">Total</th>
                            </tr>
                        </thead>
                        <tbody class="tabular-nums">
                            @foreach ($filas as $f)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-2 py-1 border">{{ $f['turno'] }}</td>
                                    <td class="px-2 py-1 border">{{ $f['montador'] }}</td>
                                    <td class="px-2 py-1 border font-bold">{{ $f['km'] }}</td>
                                    <td class="px-2 py-1 border">{{ $f['barra'] }}</td>
                                    <td class="px-2 py-1 border">{{ $f['julio'] }}</td>
                                    <td class="px-2 py-1 border">{{ $f['montado_ini'] }}</td>
                                    <td class="px-2 py-1 border">{{ $f['montado_fin'] }}</td>
                                    <td class="px-2 py-1 border font-semibold">{{ $f['montado_total'] }}</td>
                                    <td class="px-2 py-1 border">{{ $f['enhebrado_ini'] }}</td>
                                    <td class="px-2 py-1 border">{{ $f['enhebrado_fin'] }}</td>
                                    <td class="px-2 py-1 border font-semibold">{{ $f['enhebrado_total'] }}</td>
                                    <td class="px-2 py-1 border">{{ number_format($f['ideal_min'] / 60, 2) }}</td>
                                    <td class="px-2 py-1 border">{{ $f['merma'] !== null ? number_format($f['merma'], 2) : '' }}</td>
                                    <td class="px-2 py-1 border">{{ $f['fecha'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if (! empty($filas))
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-x-auto">
                    <table class="min-w-full text-sm text-center tabular-nums">
                        <thead class="bg-gray-100 text-gray-700 text-xs uppercase">
                            <tr>
                                <th class="px-2 py-2 border">Fecha</th>
                                <th class="px-2 py-2 border">KM</th>
                                <th class="px-2 py-2 border">Barra</th>
                                <th class="px-2 py-2 border">Total enhebrado (min)</th>
                                <th class="px-2 py-2 border">Ideal (min)</th>
                                <th class="px-2 py-2 border bg-yellow-200">Efectividad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filas as $f)
                                @continue($f['efectividad'] === null)
                                <tr>
                                    <td class="px-2 py-1 border">{{ $f['etiqueta'] }}</td>
                                    <td class="px-2 py-1 border font-bold">{{ $f['km'] }}</td>
                                    <td class="px-2 py-1 border">{{ $f['barra'] }}</td>
                                    <td class="px-2 py-1 border">{{ $f['enhebrado_min'] }}</td>
                                    <td class="px-2 py-1 border">{{ $f['ideal_min'] }}</td>
                                    <td class="px-2 py-1 border bg-yellow-100 font-semibold">{{ number_format($f['efectividad'], 2) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="font-bold">
                            <tr>
                                <td class="px-2 py-1 border">TOTAL</td>
                                <td class="px-2 py-1 border">{{ $total['atados'] }}</td>
                                <td class="px-2 py-1 border"></td>
                                <td class="px-2 py-1 border">{{ $total['real'] }}</td>
                                <td class="px-2 py-1 border">{{ $total['ideal'] }}</td>
                                <td class="px-2 py-1 border bg-yellow-200">
                                    {{ $total['efectividad'] !== null ? number_format($total['efectividad'], 2).'%' : '' }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    <p class="px-3 py-2 text-xs text-gray-500">Efectividad = ideal / tiempo real de enhebrado. Solo cuenta enhebrados con inicio y fin.</p>
                </div>

                <div class="xl:col-span-2 bg-white rounded-lg shadow-lg border border-gray-200 p-4">
                    <div class="h-96"><canvas id="chartKm"></canvas></div>
                </div>
            </div>
        @endif
    </div>

    @include('modulos.tejido.reportes.partials.rango-fechas', [
        'ruta' => route('atadores.reportes.km'),
        'titulo' => 'Consultar en rango',
        'descripcion' => 'Seleccione la fecha inicial y final del reporte.',
        'abrirAlCargar' => ! $fechaIni,
    ])
@endsection

@push('scripts')
    @vite('resources/js/modulos/atadores/reportes/km/index.ts')
@endpush
