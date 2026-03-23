@extends('layouts.app')

@section('page-title', 'Resumen Semanal Engomado')

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
            <span class="font-bold text-gray-800">RESUMEN SEMANAL ENGOMADO</span>
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
                    <table class="w-full text-sm border-collapse">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-2 py-1 text-left font-semibold text-xs border border-gray-300 text-red-600">Semana</th>
                                <th class="px-2 py-1 text-right font-semibold text-xs border border-gray-300 text-red-600">No. de ORDENES</th>
                                <th class="px-2 py-1 text-right font-semibold text-xs border border-gray-300 text-red-600">No. Julios</th>
                                <th class="px-2 py-1 text-right font-semibold text-xs border border-gray-300 text-red-600">KG</th>
                                <th class="px-2 py-1 text-right font-semibold text-xs border border-gray-300 text-red-600">Metros</th>
                                <th class="px-2 py-1 text-right font-semibold text-xs border border-gray-300 text-red-600">Cuentas</th>
                                <th class="px-2 py-1 text-right font-semibold text-xs border border-gray-300 text-red-600">Peso promedio por julio</th>
                                <th class="px-2 py-1 text-right font-semibold text-xs border border-gray-300 text-red-600">Metros promedio por julio</th>
                                <th class="px-2 py-1 text-right font-semibold text-xs border border-gray-300 text-red-600">Cuenta promedio por julio</th>
                                <th class="px-2 py-1 text-right font-semibold text-xs border border-gray-300 text-red-600">EFICIENCIA EN %</th>
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
                                    <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($semana['total_cuenta'] ?? 0, 2) }}</td>
                                    <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($semana['peso_promedio'] ?? 0, 2) }}</td>
                                    <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($semana['metros_promedio'] ?? 0, 2) }}</td>
                                    <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($semana['cuenta_promedio'] ?? 0, 2) }}</td>
                                    <td class="px-2 py-0.5 border border-gray-300 text-right font-bold">{{ number_format($semana['eficiencia'] ?? 0, 2) }}%</td>
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
                        <canvas id="resumenChart"></canvas>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <canvas id="eficienciaChart"></canvas>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal no ha cambiado --}}
    <div id="modalConsultarResumenEngomado" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-md mx-4">
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

    @if (!empty($datosSemanales))
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('resumenChart').getContext('2d');
        const datosSemanales = @json($datosSemanales);

        const labels = datosSemanales.map(item => item.semana_label);
        const pesoPromedioData = datosSemanales.map(item => item.peso_promedio);
        const metrosPromedioData = datosSemanales.map(item => item.metros_promedio);
        const cuentaPromedioData = datosSemanales.map(item => item.cuenta_promedio);

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Peso Promedio x Julio',
                    data: pesoPromedioData,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }, {
                    label: 'Metros Promedio x Julio',
                    data: metrosPromedioData,
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }, {
                    label: 'Cuenta Promedio x Julio',
                    data: cuentaPromedioData,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Promedios por Semana'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Gráfica de Eficiencia
        const ctxEficiencia = document.getElementById('eficienciaChart').getContext('2d');
        const eficienciaData = datosSemanales.map(item => item.eficiencia || 0);

        new Chart(ctxEficiencia, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Eficiencia Semanal (%)',
                    data: eficienciaData,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Eficiencia por Semana'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + "%"
                            }
                        }
                    }
                }
            }
        });
    });
    @endif
</script>
@endpush
