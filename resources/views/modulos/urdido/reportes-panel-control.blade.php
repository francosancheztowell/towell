@extends('layouts.app')

@section('page-title', 'Panel de Control KM')

@php
    // El controlador expande el contrato del servicio: llaves snake_case + $filtros.
    $umbralVerde = $umbral_verde ?? 0.9;
    $umbralAmarillo = $umbral_amarillo ?? 0.75;
    $kpis = $kpis ?? [];
    $semanasDetalle = $semanas_detalle ?? [];
    $categorias = $categorias ?? [];
    $hallazgos = $hallazgos ?? [];

    // Mismo formato fijo 0.0 que el Excel (decimales() recorta el .0).
    $n1 = fn ($v) => $v === null ? '—' : number_format((float) $v, 1);
    $n0 = fn ($v) => $v === null ? '—' : number_format((float) $v, 0);
    $signo1 = fn ($v) => $v === null ? '—' : ($v > 0 ? '+' : '') . number_format((float) $v, 1);
    $fecha = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/y') : '—';

    $queryParams = [
        'telar' => $telar,
        'anio' => $anio,
        'desde' => $desde,
        'hasta' => $hasta,
        'umbral_verde' => $umbralVerde,
        'umbral_amarillo' => $umbralAmarillo,
    ];

    $badgeEstado = [
        'En meta' => 'background:#C6EFCE;color:#006100',
        'Atención' => 'background:#FFEB9C;color:#9C6500',
        'Crítico' => 'background:#FFC7CE;color:#9C0006',
        'Sin dato' => 'background:#E7E6E6;color:#595959',
    ];

    $brecha = $kpis['brecha'] ?? null;
    $maxPct = 0;
    foreach ($categorias as $c) { $maxPct = max($maxPct, ($c['porcentaje'] ?? 0)); }
@endphp

@section('navbar-right')
    <a href="{{ route('urdido.reportes.urdido.panel-control.excel', $queryParams) }}"
        class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-file-excel"></i> Exportar Excel
    </a>
@endsection

@section('content')
<div class="w-full p-3" id="panel-control-km-container" style="background:#EEF1F7">
    <div class="space-y-3">

        {{-- 1. BANNER (réplica del encabezado navy del Excel) --}}
        <div class="rounded-lg shadow-sm overflow-hidden" style="background:#1F3864">
            <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                <h1 class="text-white font-bold tracking-wide" style="font-size:1.35rem">
                    PANEL DE CONTROL — TELARES KM {{ $anio }}
                </h1>
                <p class="text-sm" style="color:#BFD3F2">Eficiencia · RPM · Eventos por semana</p>
            </div>

            {{-- 2. FILTROS integrados en la franja inferior del banner --}}
            <form method="GET" action="{{ route('urdido.reportes.urdido.panel-control') }}"
                class="flex flex-wrap items-end gap-x-3 gap-y-2 px-5 py-2.5" style="background:#F5F7FB">
                @php $lbl = 'block text-[10px] font-bold uppercase tracking-wider mb-0.5'; @endphp
                @php $inp = 'border border-gray-300 rounded-md text-sm px-2 py-1 bg-white focus:outline-none focus:ring-2 focus:border-transparent'; @endphp

                <div>
                    <label for="telar" class="{{ $lbl }}" style="color:#1F3864">Telar</label>
                    <select id="telar" name="telar" class="{{ $inp }}" style="--tw-ring-color:#1F3864">
                        <option value="ambos" @selected($telar === 'ambos')>Ambos</option>
                        <option value="401" @selected($telar === '401')>401</option>
                        <option value="402" @selected($telar === '402')>402</option>
                    </select>
                </div>
                <div>
                    <label for="anio" class="{{ $lbl }}" style="color:#1F3864">Año</label>
                    <select id="anio" name="anio" class="{{ $inp }}" style="--tw-ring-color:#1F3864">
                        @for ($a = now()->year + 1; $a >= now()->year - 5; $a--)
                            <option value="{{ $a }}" @selected($anio === $a)>{{ $a }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label for="desde" class="{{ $lbl }}" style="color:#1F3864">Desde</label>
                    <input type="date" id="desde" name="desde" value="{{ $desde }}" class="{{ $inp }}" style="--tw-ring-color:#1F3864">
                </div>
                <div>
                    <label for="hasta" class="{{ $lbl }}" style="color:#1F3864">Hasta</label>
                    <input type="date" id="hasta" name="hasta" value="{{ $hasta }}" class="{{ $inp }}" style="--tw-ring-color:#1F3864">
                </div>
                <div>
                    <label for="umbral_verde" class="{{ $lbl }}" style="color:#1F3864">Umbral verde</label>
                    <input type="number" id="umbral_verde" name="umbral_verde" step="0.01" min="0" max="2"
                        value="{{ $umbralVerde }}" class="{{ $inp }} w-20 text-center" style="background:#FFF2CC;--tw-ring-color:#1F3864">
                </div>
                <div>
                    <label for="umbral_amarillo" class="{{ $lbl }}" style="color:#1F3864">Umbral amarillo</label>
                    <input type="number" id="umbral_amarillo" name="umbral_amarillo" step="0.01" min="0" max="2"
                        value="{{ $umbralAmarillo }}" class="{{ $inp }} w-20 text-center" style="background:#FFF2CC;--tw-ring-color:#1F3864">
                </div>
                <button type="submit"
                    class="flex items-center gap-1.5 px-4 py-1.5 text-white rounded-md text-sm font-semibold transition-opacity hover:opacity-90"
                    style="background:#1F3864">
                    <i class="fas fa-search text-xs"></i> Consultar
                </button>
                <a href="{{ route('urdido.reportes.urdido.panel-control.excel', $queryParams) }}"
                    class="flex items-center gap-1.5 px-4 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm font-semibold transition-colors">
                    <i class="fas fa-file-excel text-xs"></i> Excel
                </a>
            </form>
        </div>

        {{-- 3. FRANJA DE KPIs (una sola pieza, separada por líneas, colores del Excel) --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 divide-x divide-y xl:divide-y-0 divide-gray-200">
            @php
                $kpiDefs = [
                    ['EFICIENCIA PROM.', ($kpis['eficiencia_prom'] ?? null) === null ? '—' : $n1($kpis['eficiencia_prom']) . '%', '#1F3864', '% real de operación'],
                    ['ESTÁNDAR PROM.', ($kpis['estandar_prom'] ?? null) === null ? '—' : $n1($kpis['estandar_prom']) . '%', '#2E75B6', 'meta de eficiencia'],
                    ['BRECHA VS EST.', $signo1($brecha), $brecha === null ? '#9CA3AF' : ($brecha < 0 ? '#C00000' : '#1E7145'), $brecha === null ? 'puntos vs estándar' : ($brecha < 0 ? 'puntos por debajo' : 'puntos por encima')],
                    ['RPM PROM.', $n0($kpis['rpm_prom'] ?? null), '#548235', 'revoluciones por minuto'],
                    ['SEMANAS', $kpis['semanas'] ?? 0, '#7030A0', 'semanas con registro'],
                    ['EVENTOS REGISTRADOS', number_format($kpis['eventos'] ?? 0), '#BF8F00', 'paros y observaciones'],
                ];
            @endphp
            @foreach ($kpiDefs as [$label, $valor, $color, $leyenda])
                <div class="px-3 py-2.5 text-center">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">{{ $label }}</p>
                    <p class="font-bold leading-tight mt-0.5" style="font-size:1.7rem;color:{{ $color }};font-variant-numeric:tabular-nums">{{ $valor }}</p>
                    <p class="text-[10px] text-gray-500">{{ $leyenda }}</p>
                </div>
            @endforeach
        </div>

        {{-- 4. DETALLE SEMANAL (izq) + ANÁLISIS DE OBSERVACIONES (der), como el Excel --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-3 items-start">

            <div class="xl:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="flex items-baseline gap-2 px-4 py-2" style="background:#F5F7FB">
                    <h2 class="font-bold text-sm" style="color:#1F3864">DETALLE SEMANAL</h2>
                    <span class="text-xs text-gray-500">{{ count($semanasDetalle) }} semanas</span>
                </div>
                <div class="overflow-x-auto" style="max-height:29rem">
                    <table class="w-full text-xs border-collapse" style="font-variant-numeric:tabular-nums">
                        <thead>
                            <tr class="text-white" style="background:#1F3864">
                                <th class="sticky top-0 px-2 py-1.5 font-semibold text-center" style="background:#1F3864">Semana</th>
                                <th class="sticky top-0 px-2 py-1.5 font-semibold text-right" style="background:#1F3864">Efic. %</th>
                                <th class="sticky top-0 px-2 py-1.5 font-semibold text-right" style="background:#1F3864">Est. %</th>
                                <th class="sticky top-0 px-2 py-1.5 font-semibold text-right" style="background:#1F3864">RPM</th>
                                <th class="sticky top-0 px-2 py-1.5 font-semibold text-right" style="background:#1F3864">RPM Est.</th>
                                <th class="sticky top-0 px-2 py-1.5 font-semibold text-right" style="background:#1F3864">Dif. (pp)</th>
                                <th class="sticky top-0 px-2 py-1.5 font-semibold text-right" style="background:#1F3864">Días</th>
                                <th class="sticky top-0 px-2 py-1.5 font-semibold text-right" style="background:#1F3864">Eventos</th>
                                <th class="sticky top-0 px-2 py-1.5 font-semibold text-center" style="background:#1F3864">Desde</th>
                                <th class="sticky top-0 px-2 py-1.5 font-semibold text-center" style="background:#1F3864">Hasta</th>
                                <th class="sticky top-0 px-2 py-1.5 font-semibold text-center" style="background:#1F3864">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($semanasDetalle as $s)
                                @php $dif = $s['dif'] ?? null; @endphp
                                <tr class="hover:bg-blue-50/50 {{ $loop->even ? 'bg-gray-50/60' : '' }}">
                                    <td class="px-2 py-1 text-center font-semibold" style="color:#1F3864">S{{ $s['semana'] ?? '' }}</td>
                                    <td class="px-2 py-1 text-right">{{ $n1($s['efic'] ?? null) }}</td>
                                    <td class="px-2 py-1 text-right text-gray-500">{{ $n1($s['est'] ?? null) }}</td>
                                    <td class="px-2 py-1 text-right">{{ $n0($s['rpm'] ?? null) }}</td>
                                    <td class="px-2 py-1 text-right text-gray-500">{{ $n0($s['rpm_est'] ?? null) }}</td>
                                    <td class="px-2 py-1 text-right font-bold" style="color:{{ $dif === null ? '#9CA3AF' : ($dif < 0 ? '#C00000' : '#1E7145') }}">
                                        {{ $signo1($dif) }}
                                    </td>
                                    <td class="px-2 py-1 text-right">{{ $s['dias'] ?? 0 }}</td>
                                    <td class="px-2 py-1 text-right">{{ $s['eventos'] ?? 0 }}</td>
                                    <td class="px-2 py-1 text-center text-gray-500">{{ $fecha($s['desde'] ?? null) }}</td>
                                    <td class="px-2 py-1 text-center text-gray-500">{{ $fecha($s['hasta'] ?? null) }}</td>
                                    <td class="px-1.5 py-1 text-center">
                                        <span class="inline-block w-full max-w-[5.5rem] px-1.5 py-0.5 rounded text-[11px] font-bold"
                                            style="{{ $badgeEstado[$s['estado'] ?? 'Sin dato'] ?? $badgeEstado['Sin dato'] }}">
                                            {{ $s['estado'] ?? 'Sin dato' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="px-2 py-6 text-center text-gray-500">Sin datos para el periodo seleccionado</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-4 py-2" style="background:#F5F7FB">
                    <h2 class="font-bold text-sm" style="color:#1F3864">ANÁLISIS DE OBSERVACIONES</h2>
                </div>
                <table class="w-full text-xs border-collapse" style="font-variant-numeric:tabular-nums">
                    <thead>
                        <tr class="text-white" style="background:#1F3864">
                            <th class="px-3 py-1.5 font-semibold text-left">Categoría</th>
                            <th class="px-2 py-1.5 font-semibold text-right">Menciones</th>
                            <th class="px-3 py-1.5 font-semibold text-right">% del total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($categorias as $c)
                            @php
                                $pct = ($c['porcentaje'] ?? 0) * 100;
                                // Barra de datos estilo Excel dentro de la celda, relativa al máximo
                                $barra = $maxPct > 0 ? min(100, ($c['porcentaje'] ?? 0) / $maxPct * 100) : 0;
                            @endphp
                            <tr class="hover:bg-blue-50/50">
                                <td class="px-3 py-1 font-medium"
                                    style="background:linear-gradient(90deg,#DCE6F5 {{ $barra }}%,transparent {{ $barra }}%)">
                                    {{ $c['categoria'] ?? '' }}
                                </td>
                                <td class="px-2 py-1 text-right font-semibold">{{ $c['menciones'] ?? 0 }}</td>
                                <td class="px-3 py-1 text-right text-gray-500">{{ $n1($pct) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-2 py-6 text-center text-gray-500">Sin observaciones en el periodo</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-3 pb-3 pt-2 border-t border-gray-100">
                    <div style="height:13.5rem"><canvas id="panelKmMencionesChart"></canvas></div>
                </div>
            </div>
        </div>

        {{-- 5. GRÁFICAS DE SEMANA (misma fila de gráficos del Excel) --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-3">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3">
                <div style="height:15rem"><canvas id="panelKmEficienciaChart"></canvas></div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3">
                <div style="height:15rem"><canvas id="panelKmRpmChart"></canvas></div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3">
                <div style="height:15rem"><canvas id="panelKmEventosChart"></canvas></div>
            </div>
        </div>

        {{-- 6. HALLAZGOS AUTOMÁTICOS (encabezado ámbar como el Excel) --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-4 py-1.5" style="background:#FFC000">
                <h2 class="font-bold text-sm" style="color:#1F3864">HALLAZGOS AUTOMÁTICOS</h2>
            </div>
            <ul class="px-4 py-2 divide-y divide-gray-100">
                @forelse($hallazgos as $h)
                    <li class="py-1.5 text-sm text-gray-700">{{ $h }}</li>
                @empty
                    <li class="py-1.5 text-sm text-gray-500">Sin hallazgos para el periodo seleccionado.</li>
                @endforelse
            </ul>
        </div>

        {{-- 7. NOTA --}}
        <p class="text-[11px] italic text-gray-500 px-1 pb-2">
            Notas: los promedios de estándar (Est. % y RPM Est.) excluyen registros en cero.
            'Eventos' cuenta una observación por cada turno con comentario capturado.
            Todo el tablero responde al filtro de telar y a los umbrales seleccionados.
        </p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const NAVY = '#1F3864', AZUL = '#2E75B6', AMBAR = '#BF8F00', GRISNAVY = '#A6B8D4';
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.color = '#4B5563';

        const semanas = @json($semanasDetalle);
        const categorias = @json($categorias);

        // null corta la línea (equivale al NA() del Excel)
        const valor = (v) => (v === null || v === undefined ? null : Number(v));
        const labels = semanas.map(s => 'S' + s.semana);

        const base = (titulo) => ({
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, font: { size: 10 } } },
                title: { display: true, text: titulo, color: NAVY, font: { size: 12, weight: 'bold' } }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 9 } } },
                y: { beginAtZero: true, grid: { color: '#EEF1F7' }, ticks: { font: { size: 9 } } }
            }
        });

        if (semanas.length) {
            new Chart(document.getElementById('panelKmEficienciaChart'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Eficiencia real',
                        data: semanas.map(s => valor(s.efic)),
                        borderColor: NAVY, backgroundColor: NAVY,
                        borderWidth: 2, pointRadius: 2, spanGaps: false, tension: 0.25
                    }, {
                        label: 'Estándar',
                        data: semanas.map(s => valor(s.est)),
                        borderColor: AZUL, backgroundColor: AZUL,
                        borderWidth: 2, borderDash: [6, 4], pointRadius: 0, spanGaps: false, tension: 0.25
                    }]
                },
                options: base('Eficiencia real vs estándar por semana (%)')
            });

            new Chart(document.getElementById('panelKmRpmChart'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'RPM real',
                        data: semanas.map(s => valor(s.rpm)),
                        backgroundColor: NAVY, borderRadius: 2
                    }, {
                        label: 'RPM estándar',
                        data: semanas.map(s => valor(s.rpm_est)),
                        backgroundColor: GRISNAVY, borderRadius: 2
                    }]
                },
                options: base('RPM real vs estándar por semana')
            });

            new Chart(document.getElementById('panelKmEventosChart'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Eventos',
                        data: semanas.map(s => Number(s.eventos || 0)),
                        backgroundColor: AMBAR, borderRadius: 2
                    }]
                },
                options: (() => {
                    const o = base('Eventos registrados por semana');
                    o.scales.y.ticks.precision = 0;
                    return o;
                })()
            });
        }

        if (categorias.length && categorias.some(c => c.menciones > 0)) {
            new Chart(document.getElementById('panelKmMencionesChart'), {
                type: 'bar',
                data: {
                    labels: categorias.map(c => c.categoria),
                    datasets: [{
                        label: 'Menciones',
                        data: categorias.map(c => Number(c.menciones || 0)),
                        backgroundColor: NAVY, borderRadius: 2
                    }]
                },
                options: (() => {
                    const o = base('Menciones por tipo de observación');
                    o.indexAxis = 'y';
                    o.plugins.legend.display = false;
                    o.scales = {
                        x: { beginAtZero: true, grid: { color: '#EEF1F7' }, ticks: { font: { size: 9 }, precision: 0 } },
                        y: { grid: { display: false }, ticks: { font: { size: 10 } } }
                    };
                    return o;
                })()
            });
        }
    });
</script>
@endpush
