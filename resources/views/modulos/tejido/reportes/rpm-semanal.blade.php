@extends('layouts.app')

@section('page-title', 'Reporte RPM semanal')

@section('navbar-right')
    <button type="button" onclick="mostrarModalSemanaRpm()"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Elegir semana
    </button>
    @if (!empty($lunes) && !empty($domingo))
        <a href="{{ route('tejido.reportes.inv-trama.excel', ['semana' => $lunes]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4">
        @if (session('error'))
            <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                {{ session('error') }}
            </div>
        @endif
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between">
                <h1 class="text-xl font-bold text-white">Reporte RPM semanal</h1>
                @if (!empty($lunes) && !empty($domingo))
                    <span class="text-white text-sm">
                        Semana: {{ \Carbon\Carbon::parse($lunes)->locale('es')->translatedFormat('D j M') }}
                        al {{ \Carbon\Carbon::parse($domingo)->locale('es')->translatedFormat('D j M Y') }}
                    </span>
                @endif
            </div>



            <div class="p-6 overflow-x-auto">
                @if (empty($lunes) || ! isset($filasOrdenTelar) || count($filasOrdenTelar) === 0)
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-week text-6xl text-gray-300 mb-4"></i>
                        <button type="button" onclick="mostrarModalSemanaRpm()"
                            class="mt-4 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i> Seleccionar semana
                        </button>
                    </div>
                @else
                    @php
                        $chartLabels = array_map(static fn (array $r) => (string) $r['no_telar'], $filasOrdenTelar);
                        $chartDataReal = array_map(static fn (array $r) => $r['rpm_real'], $filasOrdenTelar);
                        $chartDataIdeal = array_map(static fn (array $r) => $r['rpm_ideal'], $filasOrdenTelar);
                    @endphp

                    <div class="rounded-lg border border-slate-300 overflow-hidden bg-white">
                        <table class="w-full min-w-[640px] border-collapse text-sm tabular-nums">
                            <caption class="sr-only">
                                Reporte RPM semana del {{ \Carbon\Carbon::parse($lunes)->format('Y-m-d') }} al {{ \Carbon\Carbon::parse($domingo)->format('Y-m-d') }}.
                            </caption>
                            <thead>
                                <tr class="bg-slate-200 text-slate-900">
                                    <th scope="col" class="border border-slate-400 px-3 py-2.5 text-left text-xs font-bold tracking-wide">{{ \App\Exports\ReporteRpmSemanalExport::COL_GRUPO }}</th>
                                    <th scope="col" class="border border-slate-400 px-3 py-2.5 text-center text-xs font-bold tracking-wide w-24">{{ \App\Exports\ReporteRpmSemanalExport::COL_TELAR }}</th>
                                    <th scope="col" class="border border-slate-400 px-3 py-2.5 text-right text-xs font-bold tracking-wide">{{ \App\Exports\ReporteRpmSemanalExport::COL_RPM_REAL }}</th>
                                    <th scope="col" class="border border-slate-400 px-3 py-2.5 text-right text-xs font-bold tracking-wide">{{ \App\Exports\ReporteRpmSemanalExport::COL_RPM_IDEAL }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                @php $rowNum = 0; @endphp
                                @foreach ($filasOrdenTelar as $fila)
                                    @php $rowNum++; @endphp
                                    <tr class="hover:bg-blue-50/40 {{ $rowNum % 2 === 0 ? 'bg-slate-50/60' : 'bg-white' }}">
                                        <td class="border border-slate-300 px-3 py-2 text-slate-800">{{ $fila['grupo'] }}</td>
                                        <td class="border border-slate-300 px-3 py-2 text-center font-mono font-medium text-slate-900">{{ $fila['no_telar'] }}</td>
                                        <td class="border border-slate-300 px-3 py-2 text-right">
                                            @if ($fila['rpm_real'] !== null)
                                                {{ number_format($fila['rpm_real'], 0, '.', ',') }}
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                        <td class="border border-slate-300 px-3 py-2 text-right font-medium text-slate-900">
                                            {{ number_format($fila['rpm_ideal'], 0, '.', ',') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            @if (isset($totalGeneral))
                                <tfoot>
                                    <tr class="bg-slate-200 font-bold text-slate-900 border-t-2 border-slate-500">
                                        <td class="border border-slate-400 px-3 py-2.5 text-left">{{ $totalGeneral['grupo'] }}</td>
                                        <td class="border border-slate-400 px-3 py-2.5 text-center text-slate-500">—</td>
                                        <td class="border border-slate-400 px-3 py-2.5 text-right tabular-nums">
                                            @if ($totalGeneral['rpm_real'] !== null)
                                                {{ number_format($totalGeneral['rpm_real'], 0, '.', ',') }}
                                            @else
                                                <span class="text-slate-500 font-normal">—</span>
                                            @endif
                                        </td>
                                        <td class="border border-slate-400 px-3 py-2.5 text-right tabular-nums">
                                            {{ number_format($totalGeneral['rpm_ideal'], 0, '.', ',') }}
                                        </td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function mostrarModalSemanaRpm() {
        const hoy = new Date();
        const iso = hoy.toISOString().slice(0, 10);
        const actual = @json($semanaParam) || iso;

        Swal.fire({
            title: 'Semana a consultar',
            html: `
                <p class="text-left text-sm text-gray-600 mb-3">Indique <strong>una fecha</strong> de la semana (se toma lunes a domingo de esa semana, zona horario del sistema).</p>
                <div class="text-left">
                    <label for="swal_semana" class="block text-sm font-medium text-gray-700 mb-1">Fecha de referencia</label>
                    <input type="date" id="swal_semana" value="${actual}" class="swal2-input w-full" style="margin:0;width:100%;">
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Consultar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            focusConfirm: false,
            preConfirm: () => {
                const v = document.getElementById('swal_semana')?.value;
                if (!v) {
                    Swal.showValidationMessage('Seleccione una fecha');
                    return false;
                }
                return { semana: v };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const params = new URLSearchParams(result.value);
                window.location.href = '{{ route("tejido.reportes.inv-trama") }}?' + params.toString();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        @if (empty($lunes) || ! isset($filasOrdenTelar) || count($filasOrdenTelar) === 0)
        mostrarModalSemanaRpm();
        @else
        (function() {
            if (typeof Chart === 'undefined') return;
            const canvas = document.getElementById('chartRpmSemanal');
            if (!canvas) return;
            const labels = @json($chartLabels ?? []);
            const dataReal = @json($chartDataReal ?? []);
            const dataIdeal = @json($chartDataIdeal ?? []);
            const numeric = [].concat(
                (dataReal || []).filter((v) => v !== null && v !== undefined).map((v) => Number(v)),
                (dataIdeal || []).filter((v) => v !== null && v !== undefined).map((v) => Number(v))
            );
            const maxVal = numeric.length ? Math.max.apply(null, numeric) : 0;
            const yMax = Math.max(100, Math.ceil(maxVal / 50) * 50);

            new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: @json(\App\Exports\ReporteRpmSemanalExport::COL_RPM_REAL),
                            data: dataReal,
                            backgroundColor: 'rgb(30 64 175)',
                            borderColor: 'rgb(30 64 175)',
                            borderWidth: 1
                        },
                        {
                            label: @json(\App\Exports\ReporteRpmSemanalExport::COL_RPM_IDEAL),
                            data: dataIdeal,
                            backgroundColor: 'rgb(234 88 12)',
                            borderColor: 'rgb(234 88 12)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            title: { display: true, text: 'Valores' }
                        },
                        tooltip: {
                            filter: (ctx) => ctx.parsed.y !== null
                        }
                    },
                    scales: {
                        x: {
                            title: { display: true, text: 'TELAR' },
                            ticks: { maxRotation: 60, minRotation: 0, autoSkip: true }
                        },
                        y: {
                            beginAtZero: true,
                            suggestedMax: yMax,
                            ticks: { stepSize: 50, precision: 0 }
                        }
                    }
                }
            });
        })();
        @endif
    });
</script>
@endpush
