@use('Carbon\Carbon')
@extends('layouts.app')

@section('page-title', 'Reportes OEE-URD-ENG')

@section('navbar-right')
    <button type="button" data-ui-modal-open="modalReporteRango" aria-haspopup="dialog"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('urdido.reportes.urdido.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin, 'solo_finalizados' => ($soloFinalizados ?? true) ? '1' : '0']) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4 max-w-[1600px] mx-auto" id="reportes-urdido-container">
        {{-- Encabezado: total por máquina, suma total y rango (blanco completo) --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-5 py-4 mb-4">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                @foreach ($porMaquina ?? [] as $maq)
                    <div class="flex items-center gap-2">
                        <span class="text-gray-600 text-sm font-medium">{{ $maq['label'] }}</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md bg-gray-50 text-gray-800 font-semibold text-sm">
                            {{ number_format($maq['totalKg'] ?? 0, 2) }} Kg
                        </span>
                    </div>
                @endforeach
                <div class="flex items-center gap-2 border-l border-gray-200 pl-5">
                    <span class="text-gray-600 text-sm font-medium">Total</span>
                    <span class="text-gray-900 font-bold text-base">{{ number_format($totalKg ?? 0, 2) }} Kg</span>
                </div>
                <div class="flex items-center gap-1.5 ml-auto text-gray-500 text-sm">
                    <i class="fas fa-calendar-alt text-gray-400"></i>
                    <span>
                        {{ $fechaIni ? Carbon::parse($fechaIni)->translatedFormat('d/m/Y') : '—' }}
                        al
                        {{ $fechaFin ? Carbon::parse($fechaFin)->translatedFormat('d/m/Y') : '—' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Secciones por máquina --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($porMaquina ?? [] as $maq)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col min-w-0">
                    <div class="bg-blue-500 px-4 py-3 border-b border-gray-100">
                        <h3 class="text-white text-center font-semibold text-base">{{ $maq['label'] }}</h3>
                    </div>

                    <div class="overflow-x-auto overflow-y-auto max-h-[420px] flex-1">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-white z-10 border-b-2 border-gray-200">
                                <tr>
                                    <th class="px-3 py-2.5 text-left font-semibold text-xs text-gray-600 uppercase tracking-wide">Orden</th>
                                    <th class="px-3 py-2.5 text-left font-semibold text-xs text-gray-600 uppercase tracking-wide">Julio</th>
                                    <th class="px-3 py-2.5 text-right font-semibold text-xs text-gray-600 uppercase tracking-wide">P. Neto</th>
                                    <th class="px-3 py-2.5 text-right font-semibold text-xs text-gray-600 uppercase tracking-wide">Metros</th>
                                    <th class="px-3 py-2.5 text-center font-semibold text-xs text-gray-600 uppercase tracking-wide">Operador</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse(($maq['filas'] ?? []) as $idx => $fila)
                                    <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50' }} hover:bg-blue-50/50 transition-colors">
                                        <td class="px-3 py-2 text-gray-800">{{ $fila['orden'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $fila['julio'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-800">{{ isset($fila['p_neto']) && $fila['p_neto'] !== '' ? number_format((float)$fila['p_neto'], 1) : '' }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-800">{{ (isset($fila['metros']) && $fila['metros'] != 0) ? $fila['metros'] : '' }}</td>
                                        <td class="px-3 py-2 text-center text-gray-700">{{ $fila['ope'] ?? '' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">Sin datos</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        @if (empty($porMaquina))
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-500">
                No hay datos de producción para el rango de fechas seleccionado.
            </div>
        @endif
    </div>

    @include('modulos.urdido.comun.reporte-rango', [
        'ruta' => route('urdido.reportes.urdido.03-oee'),
        'checkbox' => 'Solo finalizados',
    ])
@endsection
