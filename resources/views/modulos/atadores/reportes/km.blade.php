@extends('layouts.app')

@section('page-title', 'Reporte Atadores KM')

@section('navbar-right')
    <button type="button" onclick="mostrarModalFechasKm()"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
@endsection

@section('content')
    <div class="w-full p-4 space-y-4">
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between">
                <h1 class="text-xl font-bold text-white">Atadores KM (Karl Mayer)</h1>
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
@endsection

@push('scripts')
@vite('resources/js/charts.js')
<script>
    window.volverAlIndice = function() {
        window.location.href = '{{ route("atadores.reportes.index") }}';
    };

    function mostrarModalFechasKm() {
        const hoy = new Date().toISOString().split('T')[0];
        Swal.fire({
            title: 'Consultar en rango',
            html: `
                <div class="text-left space-y-4">
                    <div>
                        <label for="swal_fecha_ini" class="block text-sm font-medium text-gray-700 mb-1">Fecha inicial</label>
                        <input type="date" id="swal_fecha_ini" value="{{ $fechaIni }}" class="swal2-input w-full" style="margin: 0; width: 100%;">
                    </div>
                    <div>
                        <label for="swal_fecha_fin" class="block text-sm font-medium text-gray-700 mb-1">Fecha final</label>
                        <input type="date" id="swal_fecha_fin" value="{{ $fechaFin }}" class="swal2-input w-full" style="margin: 0; width: 100%;">
                    </div>
                </div>
            `,
            didOpen: () => {
                document.getElementById('swal_fecha_ini').value ||= hoy;
                document.getElementById('swal_fecha_fin').value ||= hoy;
            },
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Consultar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            focusConfirm: false,
            preConfirm: () => {
                const fi = document.getElementById('swal_fecha_ini').value;
                const ff = document.getElementById('swal_fecha_fin').value;
                if (!fi || !ff) {
                    Swal.showValidationMessage('Seleccione fecha inicial y final');
                    return false;
                }
                if (fi > ff) {
                    Swal.showValidationMessage('La fecha inicial no puede ser mayor que la final');
                    return false;
                }
                return { fecha_ini: fi, fecha_fin: ff };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                window.location.href = '{{ route("atadores.reportes.km") }}?' + new URLSearchParams(result.value);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        @if (! $fechaIni)
        mostrarModalFechasKm();
        @elseif (! empty($filas))
        const filas = @json(array_values(array_filter($filas, fn ($f) => $f['efectividad'] !== null)));
        const canvas = document.getElementById('chartKm');
        if (typeof Chart === 'undefined' || !canvas) return;

        new Chart(canvas, {
            data: {
                labels: filas.map(f => `${f.etiqueta} · ${f.km} B${f.barra}`),
                datasets: [
                    { type: 'line', label: 'Efectividad %', data: filas.map(f => f.efectividad), yAxisID: 'pct', borderColor: 'rgb(132 204 22)', backgroundColor: 'rgb(132 204 22)', tension: 0 },
                    { type: 'bar', label: 'Total enhebrado (min)', data: filas.map(f => f.enhebrado_min), backgroundColor: 'rgb(59 130 246)' },
                    { type: 'bar', label: 'Ideal (min)', data: filas.map(f => f.ideal_min), backgroundColor: 'rgb(220 38 38)' },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Minutos' } },
                    pct: { position: 'right', beginAtZero: true, suggestedMax: 100, grid: { drawOnChartArea: false }, ticks: { callback: v => v + '%' } }
                }
            }
        });
        @endif
    });
</script>
@endpush
