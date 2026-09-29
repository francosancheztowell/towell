@extends('layouts.app')

@section('page-title', 'Reporte Inv Telas')

@section('navbar-right')
    <button type="button" data-ui-modal-open="modalRangoTejido"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search" aria-hidden="true"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('tejido.reportes.inv-telas.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
        <a href="{{ route('tejido.reportes.inv-telas.pdf', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-pdf"></i> Descargar PDF
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4" data-reporte-inv-telas data-ruta-indice="{{ route('tejido.reportes.index') }}">
        @if (session('error'))
            <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                {{ session('error') }}
            </div>
        @endif
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-white">Reporte Inv Telas</h2>
                @if (!empty($fechaIni) && !empty($fechaFin))
                    <span class="text-white text-sm">
                        {{ \Carbon\Carbon::parse($fechaIni)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}
                    </span>
                @endif
            </div>

            <div class="p-6 overflow-x-auto">
                @if (empty($fechaIni) || empty($fechaFin))
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-alt text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg">Seleccione un rango de fechas (máximo 5 días) para consultar el reporte</p>
                        <button type="button" data-ui-modal-open="modalRangoTejido"
                            class="mt-4 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i> Seleccionar Fechas
                        </button>
                    </div>
                @else
                    @php
                        $leyendaColores = $leyendaColores ?? [];
                        $estilosColor = [
                            'blue'   => 'background-color:#3b82f6;color:#ffffff;font-weight:600;',
                            'orange' => 'background-color:#fb923c;color:#ffffff;font-weight:600;',
                            'yellow' => 'background-color:#fde047;color:#713f12;font-weight:600;',
                        ];
                        $swatchEstilos = [
                            'blue'   => 'background-color:#3b82f6;',
                            'orange' => 'background-color:#fb923c;',
                            'yellow' => 'background-color:#fde047;',
                        ];
                    @endphp
                    @if (!empty($leyendaColores))
                        <div class="mb-4 flex flex-wrap items-center gap-3 text-sm text-gray-700">
                            <span class="font-semibold text-gray-800">Leyenda:</span>
                            @foreach ($leyendaColores as $item)
                                <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 py-1">
                                    <span class="h-3 w-3 rounded-full" style="{{ $swatchEstilos[$item['color']] ?? 'background-color:#d1d5db;' }}"></span>
                                    <span>{{ $item['descripcion'] ?? ($item['label'] ?? '') }}</span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                    <table class="min-w-full border border-gray-300 text-sm">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800">No. Telar</th>
                                <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800">FIBRA</th>
                                <th class="border border-gray-300 px-3 py-2 text-center font-semibold text-gray-800">CALIBRE</th>
                                <th class="border border-gray-300 px-3 py-2 text-center font-semibold text-gray-800">CUENTA R/P</th>
                                @foreach ($dias as $dia)
                                    <th class="border border-gray-300 px-3 py-2 text-center font-semibold text-gray-800">{{ $dia['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($secciones as $seccion)
                                <tr style="background-color:#fef9c3;font-weight:600;">
                                    <td colspan="{{ 4 + count($dias) }}" class="border border-gray-300 px-3 py-2">
                                        {{ $seccion['nombre'] }}
                                    </td>
                                </tr>
                                @foreach ($seccion['filas'] as $fila)
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-300 px-3 py-2">{{ $fila['no_telar'] }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ $fila['fibra'] ?: '-' }}</td>
                                        <td class="border border-gray-300 px-3 py-2 text-center">{{ $fila['calibre'] ?: '-' }}</td>
                                        <td class="border border-gray-300 px-3 py-2 text-center">
                                            @php
                                                $cuentaRizo = trim((string) ($fila['cuenta_rizo'] ?? ''));
                                                $cuentaPie = trim((string) ($fila['cuenta_pie'] ?? ''));
                                            @endphp
                                            @if ($cuentaRizo !== '' && $cuentaPie !== '')
                                                {{ 'R: ' . $cuentaRizo . ' | P: ' . $cuentaPie }}
                                            @elseif ($cuentaRizo !== '')
                                                {{ 'R: ' . $cuentaRizo }}
                                            @elseif ($cuentaPie !== '')
                                                {{ 'P: ' . $cuentaPie }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        @foreach ($dias as $dia)
                                            @php
                                                $celdaDia = $fila['por_dia'][$dia['fecha']] ?? ['turnos' => []];
                                                $turnos = $celdaDia['turnos'] ?? [];
                                                $turnoAligns = [1 => 'left', 2 => 'center', 3 => 'right'];

                                                // Construir tooltip con desglose por turno
                                                $tooltipParts = [];
                                                $hayDatos = false;
                                                foreach ([1, 2, 3] as $t) {
                                                    $txt = trim((string) ($turnos[$t]['texto'] ?? ''));
                                                    if ($txt !== '') {
                                                        $hayDatos = true;
                                                        $tooltipParts[] = "T{$t}: {$txt}";
                                                    }
                                                }
                                                $tooltip = implode(' | ', $tooltipParts);
                                            @endphp
                                            <td class="border border-gray-300 px-0 py-0" @if($tooltip) title="{{ $tooltip }}" @endif>
                                                @if ($hayDatos)
                                                    <div style="display:flex;min-height:28px;">
                                                        @foreach ([1, 2, 3] as $t)
                                                            @php
                                                                $turnoTxt = trim((string) ($turnos[$t]['texto'] ?? ''));
                                                                $turnoColor = $turnos[$t]['color'] ?? null;
                                                                $turnoEstilo = $estilosColor[$turnoColor] ?? '';
                                                                $align = $turnoAligns[$t];
                                                            @endphp
                                                            <div style="flex:1;display:flex;align-items:center;justify-content:{{ $align }};padding:2px 3px;text-align:{{ $align }};font-size:0.8rem;{{ $turnoEstilo }}">
                                                                {{ $turnoTxt }}
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    &nbsp;
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    @include('modulos.tejido.reportes.partials.rango-fechas', [
        'ruta' => route('tejido.reportes.inv-telas'),
        'titulo' => 'Consultar en rango',
        'descripcion' => 'Seleccione un rango de máximo 5 días.',
        'maxDias' => 5,
        'abrirAlCargar' => empty($fechaIni) || empty($fechaFin),
    ])
@endsection

@push('scripts')
    @vite('resources/js/modulos/tejido/reportes/inv-telas/index.ts')
@endpush
