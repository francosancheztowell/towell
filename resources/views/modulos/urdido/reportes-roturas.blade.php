@extends('layouts.app')

@section('page-title', 'Roturas x Millón')

@section('navbar-right')
    <button type="button" data-ui-modal-open="modalReporteRango" aria-haspopup="dialog"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('urdido.reportes.urdido.roturas.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin, 'solo_finalizados' => ($soloFinalizados ?? true) ? '1' : '0']) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4" id="reportes-roturas-container">
        <div class="bg-white rounded-t-lg px-4 py-2 flex flex-wrap items-center gap-4">
            <span class="font-bold text-gray-800">ROTURAS X MILLÓN</span>
            <span class="text-gray-600 text-sm">
                {{ $fechaIni ? \Carbon\Carbon::parse($fechaIni)->translatedFormat('d/m/Y') : '—' }}
                al
                {{ $fechaFin ? \Carbon\Carbon::parse($fechaFin)->translatedFormat('d/m/Y') : '—' }}
            </span>
            @if (!empty($filas))
                <span class="text-gray-500 text-sm">{{ count($filas) }} órdenes</span>
            @endif
        </div>

        <div class="overflow-x-auto bg-white border border-t-0 border-gray-300 rounded-b-lg">
            <table class="w-full text-sm border-collapse">
                <thead class="sticky top-0 bg-blue-700 text-white z-10">
                    <tr>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">MAQ</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">FECHA</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">ORDEN</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">PROVEEDOR</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">CUENTA</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">CALIBRE</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">TIPO</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">MTS X JULIO</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">TOTAL JULIOS</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">HILOS X JULIO</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">MILLÓN</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">MTS ORDEN</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">MILLÓN MTS ANALIZADOS</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">ROT. HILATURA</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">ROT. MÁQUINA</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">ROT. OPERACIÓN</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">TRANSFER.</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">TOTAL ROT.</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($filas ?? []) as $f)
                        <tr class="hover:bg-gray-50">
                            <td class="px-2 py-0.5 border border-gray-300">{{ $f['maq'] ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ isset($f['fecha']) && $f['fecha'] ? \Carbon\Carbon::parse($f['fecha'])->translatedFormat('d-M') : '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $f['orden'] ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $f['proveedor'] ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $f['cuenta'] ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $f['calibre'] ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $f['tipo'] ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($f['metros_julio'] ?? 0) }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $f['total_julios'] ?? 0 }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($f['hilos_julio'] ?? 0) }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">1,000,000</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($f['metros_orden'] ?? 0) }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($f['millon_metros'] ?? 0, 2) }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $f['rot_hilatura'] ?? 0 }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $f['rot_maquina'] ?? 0 }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $f['rot_operacion'] ?? 0 }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $f['transferencia'] ?? 0 }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right font-semibold">{{ $f['total_roturas'] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="18" class="px-2 py-3 text-center text-gray-500 text-xs border border-gray-300">Sin datos</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('modulos.urdido.comun.reporte-rango', [
        'ruta' => route('urdido.reportes.urdido.roturas'),
        'checkbox' => 'Solo finalizados',
    ])
@endsection
