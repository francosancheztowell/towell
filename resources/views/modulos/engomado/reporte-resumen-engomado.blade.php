@extends('layouts.app')

@section('page-title', 'Resumen Engomado')

@section('navbar-right')
    <button type="button" onclick="mostrarModalConsultarResumenEngomado()"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('engomado.reportes.resumen-engomado.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4" id="reporte-resumen-engomado-container">
        <div class="bg-white rounded-t-lg px-4 py-2 flex flex-wrap items-center gap-4">
            <span class="font-bold text-gray-800">RESUMEN ENGOMADO</span>
            <span class="text-gray-600 text-sm">
                {{ $fechaIni ? \Carbon\Carbon::parse($fechaIni)->translatedFormat('d/m/Y') : '—' }}
                al
                {{ $fechaFin ? \Carbon\Carbon::parse($fechaFin)->translatedFormat('d/m/Y') : '—' }}
            </span>
            @if (!empty($porFecha))
                <span class="text-gray-500 text-sm">{{ count($porFecha) }} días</span>
            @endif
        </div>

        <div class="overflow-x-auto bg-white border border-t-0 border-gray-300 rounded-b-lg">
            @forelse($porFecha as $fecha => $datos)
                @php
                    $date = \Carbon\Carbon::parse($fecha);
                    $diaMap = ['DO', 'LU', 'MA', 'MI', 'JU', 'VI', 'SA'];
                    $diaSemana = $diaMap[$date->dayOfWeek] ?? '';
                    $totalKg = $datos['totalKg'] ?? 0;
                @endphp

                <div class="mb-6 border-b border-gray-300 pb-4">
                    <div class="bg-blue-100 px-4 py-2 mb-2">
                        <div class="flex items-center gap-4">
                            <span class="font-bold text-blue-900">{{ $diaSemana }}</span>
                            <span class="text-blue-800">{{ ucfirst($date->locale('es')->translatedFormat('l, d \d\e F \d\e Y')) }}</span>
                            <span class="ml-auto font-semibold text-blue-900">Total: {{ number_format($totalKg, 1) }} Kg</span>
                        </div>
                    </div>

                    @foreach($datos['porMaquina'] ?? [] as $maquina)
                        @php
                            $label = $maquina['label'] ?? '';
                            $filas = $maquina['filas'] ?? [];
                            $kgMaquina = array_sum(array_column($filas, 'p_neto'));
                            $metrosMaquina = array_sum(array_column($filas, 'metros'));
                        @endphp

                        @if(!empty($filas))
                            <div class="px-4 py-2">
                                <div class="bg-gray-100 px-3 py-1.5 mb-2 rounded">
                                    <div class="flex items-center gap-4">
                                        <span class="font-bold text-gray-800">{{ $label }}</span>
                                        <span class="text-gray-700">{{ number_format($kgMaquina, 1) }} Kg</span>
                                        <span class="text-gray-700">{{ number_format($metrosMaquina, 0) }} mts</span>
                                    </div>
                                </div>

                                <table class="w-full text-sm border-collapse mb-3">
                                    <thead class="bg-gray-200">
                                        <tr>
                                            <th class="px-2 py-1 text-left font-semibold text-xs border border-gray-300">Orden</th>
                                            <th class="px-2 py-1 text-left font-semibold text-xs border border-gray-300">Julio</th>
                                            <th class="px-2 py-1 text-right font-semibold text-xs border border-gray-300">Kg Neto</th>
                                            <th class="px-2 py-1 text-right font-semibold text-xs border border-gray-300">Metros</th>
                                            <th class="px-2 py-1 text-left font-semibold text-xs border border-gray-300">Operadores</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($filas as $fila)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-2 py-0.5 border border-gray-300">{{ $fila['orden'] ?? '' }}</td>
                                                <td class="px-2 py-0.5 border border-gray-300">{{ $fila['julio'] ?? '' }}</td>
                                                <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($fila['p_neto'] ?? 0, 2) }}</td>
                                                <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $fila['metros'] ? number_format($fila['metros'], 0) : '' }}</td>
                                                <td class="px-2 py-0.5 border border-gray-300">{{ $fila['ope'] ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endforeach
                </div>
            @empty
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-info-circle text-4xl mb-2"></i>
                    <p>No hay datos para el rango de fechas seleccionado.</p>
                    <p class="text-sm mt-1">Seleccione un rango de fechas para generar el reporte.</p>
                </div>
            @endforelse
        </div>
    </div>

    <div id="modalConsultarResumenEngomado" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="bg-blue-600 text-white px-6 py-4 rounded-t-lg flex items-center justify-between">
                <h3 class="text-lg font-semibold">Consultar Resumen Engomado</h3>
                <button type="button" onclick="cerrarModalConsultarResumenEngomado()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="GET" action="{{ route('engomado.reportes.resumen-engomado') }}" class="p-6">
                <div class="space-y-4">
                    <div>
                        <label for="fecha_ini" class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicial</label>
                        <input type="date" id="fecha_ini" name="fecha_ini" required
                            value="{{ $fechaIni ?? '' }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="fecha_fin" class="block text-sm font-medium text-gray-700 mb-1">Fecha Final</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" required
                            value="{{ $fechaFin ?? '' }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="cerrarModalConsultarResumenEngomado()"
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
<script>
    function mostrarModalConsultarResumenEngomado() {
        document.getElementById('modalConsultarResumenEngomado').classList.remove('hidden');
    }

    function cerrarModalConsultarResumenEngomado() {
        document.getElementById('modalConsultarResumenEngomado').classList.add('hidden');
    }

    document.getElementById('modalConsultarResumenEngomado').addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModalConsultarResumenEngomado();
        }
    });
</script>
@endpush
