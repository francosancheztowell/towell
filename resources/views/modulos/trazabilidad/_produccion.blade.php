{{-- Tarjetas de producción: diseño compacto por orden/telar --}}

@php
    $prod       = $produccion ?? ['ordenes' => [], 'noEncontradas' => []];
    $ordenCards = $prod['ordenes'] ?? [];
    $resumen    = $prod['resumen'] ?? [];
@endphp

<style>
    .prod-card-v2 {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        min-height: 100%;
    }
    .prod-card-v2.prod-card--alerta {
        border-color: #fcd34d;
        box-shadow: 0 0 0 1px rgba(251, 191, 36, 0.25);
    }
    .prod-card-v2__accent {
        width: 5px;
        flex-shrink: 0;
        align-self: stretch;
        border-radius: 1rem 0 0 1rem;
    }
    .prod-card-v2__accent--activo { background: #22c55e; }
    .prod-card-v2__accent--terminado { background: #94a3b8; }
    .prod-card-v2__accent--alerta { background: #f59e0b; }
    .prod-stat-box {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 0.65rem;
        padding: 0.65rem 0.75rem;
    }
    .prod-stat-label {
        font-size: 0.7rem;
        color: #94a3b8;
        font-weight: 500;
        line-height: 1.2;
    }
    .prod-stat-value {
        font-size: 1.05rem;
        font-weight: 700;
        color: #1e293b;
        font-variant-numeric: tabular-nums;
        line-height: 1.25;
        margin-top: 0.15rem;
    }
</style>

@if (empty($ordenCards))
    <div class="bg-white border border-dashed border-slate-300 rounded-2xl p-10 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mb-4">
            <i class="fa-solid fa-industry text-slate-400 text-lg"></i>
        </div>
        <p class="text-slate-700 font-semibold">Sin órdenes para los filtros actuales</p>
        <p class="text-slate-400 text-sm mt-1">No se encontraron órdenes en la trazabilidad de este filtro.</p>
    </div>
@else
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2 text-sm text-slate-600">
            <span class="font-semibold text-slate-700">{{ count($ordenCards) }} órdenes</span>
            @if (($resumen['activos'] ?? 0) > 0)
                <span class="text-[11px] bg-emerald-50 text-emerald-700 rounded-full px-2 py-0.5 font-medium">
                    {{ $resumen['activos'] }} activas
                </span>
            @endif
            @if (($resumen['terminados'] ?? 0) > 0)
                <span class="text-[11px] bg-slate-100 text-slate-600 rounded-full px-2 py-0.5 font-medium">
                    {{ $resumen['terminados'] }} terminadas
                </span>
            @endif
            @if (($resumen['alertas'] ?? 0) > 0)
                <span class="text-[11px] bg-amber-50 text-amber-700 rounded-full px-2 py-0.5 font-medium">
                    <i class="fa-solid fa-triangle-exclamation"></i> {{ $resumen['alertas'] }} alertas
                </span>
            @endif
        </div>

        <div class="prod-switch inline-flex items-center rounded-full bg-slate-100 p-0.5 gap-0.5 select-none">
            <button type="button" data-filter="todos"
                    class="prod-filter-btn rounded-full px-3.5 py-1 text-xs font-medium bg-white shadow text-slate-700 transition-all">
                todos
            </button>
            <button type="button" data-filter="activo"
                    class="prod-filter-btn rounded-full px-3.5 py-1 text-xs font-medium text-slate-500 hover:text-slate-700 transition-all">
                activo
            </button>
            <button type="button" data-filter="terminado"
                    class="prod-filter-btn rounded-full px-3.5 py-1 text-xs font-medium text-slate-500 hover:text-slate-700 transition-all">
                terminado
            </button>
        </div>
    </div>

    <div class="prod-cards-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
        @foreach ($ordenCards as $o)
            @php
                if (!empty($o['programa'])) {
                    $programadas = (float) ($o['programa']['totalPedido'] ?? 0);
                    $producidasBd = (float) ($o['programa']['produccion'] ?? 0);
                    $pzasDia = $o['programa']['stdDia'] ?? null;
                    $avanceRef = number_format($producidasBd).' / '.number_format($programadas);
                } elseif (!empty($o['codificados'])) {
                    $programadas = (float) ($o['codificados']['pedido'] ?? 0);
                    $producidasBd = (float) ($o['codificados']['produccion'] ?? 0);
                    $pzasDia = null;
                    $avanceRef = number_format($producidasBd).' / '.number_format($programadas);
                } else {
                    $programadas = (float) ($o['programadas'] ?? 0);
                    $producidasBd = (float) ($o['producidas'] ?? 0);
                    $pzasDia = $o['pzasDia'] ?? null;
                    $avanceRef = number_format($producidasBd).' / '.number_format($programadas);
                }

                $avance = $o['avance'];
                $ancho  = min(100, max(0, $avance));

                $accentClass = $o['alerta']
                    ? 'prod-card-v2__accent--alerta'
                    : ($o['estado'] === 'activo' ? 'prod-card-v2__accent--activo' : 'prod-card-v2__accent--terminado');

                $conflicto = implode(', ', $o['localidadesConflicto'] ?? []);
                $tituloAlerta = $conflicto !== ''
                    ? 'Trazabilidad con producción en otro telar: '.$conflicto.'. Telar del programa: '.$o['telar'].'.'
                    : 'El telar de la orden no coincide con la localidad de trazabilidad.';
            @endphp

            <div class="prod-card prod-card-v2 {{ $o['alerta'] ? 'prod-card--alerta' : '' }} flex flex-row"
                 data-estado="{{ $o['estado'] }}">
                <div class="prod-card-v2__accent {{ $accentClass }}"></div>

                <div class="flex flex-col flex-1 p-3.5 min-w-0">
                    {{-- Encabezado: telar + estado --}}
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <div class="min-w-0">
                            <div class="text-2xl font-extrabold text-slate-800 leading-tight tracking-tight">
                                {{ $o['telar'] }}
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5 font-mono">
                                orden {{ $o['orden'] }}
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1 shrink-0">
                            @if ($o['enProceso'])
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-medium px-2.5 py-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    en producción
                                </span>
                            @elseif ($o['fuente'] === 'programa')
                                <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-medium px-2.5 py-1">
                                    programado
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 text-[11px] font-medium px-2.5 py-1">
                                    finalizado
                                </span>
                            @endif
                            @if ($o['alerta'])
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 text-[10px] font-medium px-2 py-0.5"
                                      title="{{ $tituloAlerta }}">
                                    <i class="fa-solid fa-triangle-exclamation"></i> revisar
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Meta compacta --}}
                    @php
                        $meta = [];
                        if (!empty($o['programa']['salonTejidoId'])) {
                            $meta[] = $o['programa']['salonTejidoId'];
                        }
                        if (!empty($o['programa']['fechaInicio'])) {
                            $meta[] = 'inicio '.$o['programa']['fechaInicio'];
                        }
                        if (!empty($o['programa']['fechaFinal'])) {
                            $meta[] = 'final '.$o['programa']['fechaFinal'];
                        }
                        if (!empty($o['programa']['esOrdCompartida'])) {
                            $oc = 'ord. compartida '.$o['programa']['ordCompartida'];
                            if ($o['programa']['esLiderOrdCompartida']) {
                                $oc .= ' · líder';
                            }
                            $meta[] = $oc;
                        }
                        if (!empty($o['meses'])) {
                            $meta[] = implode(', ', $o['meses']);
                        }
                    @endphp
                    @if (!empty($meta))
                        <p class="text-[10px] text-slate-400 mb-2.5 leading-relaxed">{{ implode(' · ', $meta) }}</p>
                    @endif

                    {{-- Cuadrícula 2×2 --}}
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <div class="prod-stat-box">
                            <div class="prod-stat-label">programadas</div>
                            <div class="prod-stat-value">{{ number_format($programadas) }}</div>
                        </div>
                        <div class="prod-stat-box">
                            <div class="prod-stat-label">producidas</div>
                            <div class="prod-stat-value">{{ number_format($producidasBd) }}</div>
                        </div>
                        <div class="prod-stat-box">
                            <div class="prod-stat-label">kg prod.</div>
                            <div class="prod-stat-value">{{ number_format($o['kg'], 2) }}</div>
                        </div>
                        <div class="prod-stat-box">
                            <div class="prod-stat-label">pzas/día</div>
                            <div class="prod-stat-value">
                                {{ $pzasDia !== null ? number_format($pzasDia) : '—' }}
                            </div>
                        </div>
                    </div>

                    {{-- Saldos (programa o cat) --}}
                    @if (!empty($o['programa']) || !empty($o['codificados']))
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-500 mb-2.5">
                            @if (!empty($o['programa']))
                                <span>
                                    saldo pedido
                                    <b class="text-slate-700 {{ ($o['programa']['saldoPedido'] ?? 0) < 0 ? 'text-red-600' : '' }}">
                                        {{ number_format($o['programa']['saldoPedido']) }}
                                    </b>
                                </span>
                                <span>
                                    traza
                                    <b class="text-slate-700">{{ number_format($o['producidas']) }}</b>
                                </span>
                            @elseif (!empty($o['codificados']))
                                <span>
                                    saldos
                                    <b class="text-slate-700 {{ ($o['codificados']['saldos'] ?? 0) < 0 ? 'text-red-600' : '' }}">
                                        {{ number_format($o['codificados']['saldos']) }}
                                    </b>
                                </span>
                                <span>
                                    traza
                                    <b class="text-slate-700">{{ number_format($o['producidas']) }}</b>
                                </span>
                            @endif
                        </div>
                    @endif

                    {{-- Avance --}}
                    <div class="mt-auto pt-1">
                        <div class="flex items-center justify-between text-[11px] mb-1.5">
                            <span class="text-slate-600">
                                avance: <b class="text-slate-800">{{ number_format($avance, 1) }}%</b>
                            </span>
                            <span class="text-slate-400 tabular-nums">{{ $avanceRef }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-200/80 overflow-hidden">
                            <div class="h-full rounded-full bg-blue-500 transition-all"
                                 style="width: {{ $ancho }}%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <script>
    (function () {
        var pane = document.querySelector('[data-pane="produccion"]');
        if (!pane) return;
        var btns  = pane.querySelectorAll('.prod-filter-btn');
        var cards = pane.querySelectorAll('.prod-card');

        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var filter = this.getAttribute('data-filter');

                btns.forEach(function (b) {
                    b.classList.remove('bg-white', 'shadow', 'text-slate-700');
                    b.classList.add('text-slate-500');
                });
                this.classList.add('bg-white', 'shadow', 'text-slate-700');
                this.classList.remove('text-slate-500');

                cards.forEach(function (card) {
                    var visible = filter === 'todos' || card.getAttribute('data-estado') === filter;
                    card.style.display = visible ? '' : 'none';
                });
            });
        });
    })();
    </script>
@endif
