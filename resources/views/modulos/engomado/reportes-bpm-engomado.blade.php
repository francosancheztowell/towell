@extends('layouts.app')

@section('page-title', 'BPM Engomado')

@section('navbar-right')
    <button type="button" data-ui-modal-open="modalReporteRango" aria-haspopup="dialog"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('engomado.reportes.bpm.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin, 'solo_finalizados' => ($soloFinalizados ?? true) ? '1' : '0']) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4" id="reportes-bpm-engomado-container">
        <div class="bg-white rounded-t-lg px-4 py-2 flex flex-wrap items-center gap-4">
            <span class="font-bold text-gray-800">BPM ENGOMADO</span>
            <span class="text-gray-600 text-sm">
                {{ $fechaIni ? \Carbon\Carbon::parse($fechaIni)->translatedFormat('d/m/Y') : '—' }}
                al
                {{ $fechaFin ? \Carbon\Carbon::parse($fechaFin)->translatedFormat('d/m/Y') : '—' }}
            </span>
            @if (!empty($filas))
                <span class="text-gray-500 text-sm">{{ count($filas) }} líneas</span>
            @endif
        </div>

        <div class="overflow-x-auto bg-white border border-t-0 border-gray-300 rounded-b-lg">
            <table class="w-full text-sm border-collapse">
                <thead class="sticky top-0 bg-blue-700 text-white z-10">
                    <tr>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">#</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Folio</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Status</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Fecha</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">ClaveEntrega</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">NombreEntrega</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Turno Entrega</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">ClaveRecibe</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">NombreRecibe</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Turno Recibe</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">ClaveAutoriza</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Nombre Autoriza</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Orden</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Actividad</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($filas ?? []) as $fila)
                        @php
                            $status = strtolower((string) ($fila->Status ?? ''));
                            $statusClass = 'bg-gray-100 text-gray-800';
                            $valorTxt = strtoupper((string) ($fila->ValorTexto ?? 'S/N'));
                            $valorClass = 'bg-gray-100 text-gray-700';
                            if ($status === 'creado') {
                                $statusClass = 'bg-blue-100 text-blue-800';
                            } elseif ($status === 'terminado') {
                                $statusClass = 'bg-yellow-100 text-yellow-800';
                            } elseif ($status === 'autorizado') {
                                $statusClass = 'bg-green-100 text-green-800';
                            }
                            if ($valorTxt === 'CORRECTO') {
                                $valorClass = 'bg-green-100 text-green-800';
                            } elseif ($valorTxt === 'INCORRECTO') {
                                $valorClass = 'bg-red-100 text-red-800';
                            }
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-2 py-0.5 border border-gray-300 text-center font-semibold">{{ $fila->InicioFolio ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $fila->Folio ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusClass }}">
                                    {{ $fila->Status ?? '' }}
                                </span>
                            </td>
                            <td class="px-2 py-0.5 border border-gray-300">
                                {{ !empty($fila->Fecha) ? \Carbon\Carbon::parse($fila->Fecha)->format('d/m/Y') : '' }}
                            </td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $fila->CveEmplEnt ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $fila->NombreEmplEnt ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $fila->TurnoEntrega ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $fila->CveEmplRec ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $fila->NombreEmplRec ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $fila->TurnoRecibe ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $fila->CveEmplAutoriza ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $fila->NombreEmplAutoriza ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $fila->Orden ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $fila->Actividad ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-center">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold {{ $valorClass }}">
                                    {{ $fila->ValorTexto ?? 'S/N' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="px-2 py-3 text-center text-gray-500 text-xs border border-gray-300">Sin datos</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('modulos.urdido.comun.reporte-rango', [
        'ruta' => route('engomado.reportes.bpm'),
        'checkbox' => 'Solo terminados/autorizados',
    ])
@endsection
