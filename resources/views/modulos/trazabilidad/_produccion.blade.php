{{-- Tarjetas de producción: diseño compacto por orden/telar --}}

@php
    $prod       = $produccion ?? ['ordenes' => [], 'noEncontradas' => []];
    $ordenCards = $prod['ordenes'] ?? [];
    $resumen    = $prod['resumen'] ?? [];
@endphp

@if (empty($ordenCards))
    <div class="bg-white border border-dashed border-slate-300 rounded-2xl p-10 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mb-4">
            <i class="fa-solid fa-industry text-slate-400 text-lg"></i>
        </div>
        <p class="text-slate-700 font-semibold">Sin órdenes para los filtros actuales</p>
        <p class="text-slate-400 text-sm mt-1">No se encontraron órdenes en la trazabilidad de este filtro.</p>
    </div>
@else
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2 text-sm text-slate-600">
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
                    {{ $resumen['alertas'] }} alertas
                </span>
            @endif
        </div>

        <div class="prod-switch inline-flex items-center rounded-full bg-slate-100 p-0.5 gap-0.5 select-none shrink-0 self-start sm:self-auto">
            <button type="button" data-filter="todos"
                    class="prod-filter-btn rounded-full px-3.5 py-1.5 text-xs font-medium bg-white shadow text-slate-700 transition-all">
                todos
            </button>
            <button type="button" data-filter="activo"
                    class="prod-filter-btn rounded-full px-3.5 py-1.5 text-xs font-medium text-slate-500 hover:text-slate-700 transition-all">
                activo
            </button>
            <button type="button" data-filter="terminado"
                    class="prod-filter-btn rounded-full px-3.5 py-1.5 text-xs font-medium text-slate-500 hover:text-slate-700 transition-all">
                terminado
            </button>
        </div>
    </div>

    <p class="prod-sin-resultados hidden text-center text-sm text-slate-500 py-6 mb-2">
        Ninguna orden coincide con el filtro seleccionado.
    </p>

    <div class="prod-cards-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 items-start">
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

            <div class="prod-card prod-card-v2 {{ $o['alerta'] ? 'prod-card--alerta' : '' }}"
                 data-estado="{{ $o['estado'] }}">
                <div class="prod-card-v2__accent {{ $accentClass }}" aria-hidden="true"></div>

                <div class="flex flex-col flex-1 p-3.5 min-w-0">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="min-w-0">
                            <div class="text-xl sm:text-2xl font-extrabold text-slate-800 leading-tight">
                                {{ $o['telar'] }}
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5 font-mono truncate" title="orden {{ $o['orden'] }}">
                                orden {{ $o['orden'] }}
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1 shrink-0 max-w-[45%]">
                            @if ($o['enProceso'])
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-medium px-2 py-0.5 whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                    en producción
                                </span>
                            @elseif ($o['fuente'] === 'programa')
                                <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-medium px-2 py-0.5 whitespace-nowrap">
                                    programado
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 text-[11px] font-medium px-2 py-0.5 whitespace-nowrap">
                                    finalizado
                                </span>
                            @endif
                            @if ($o['alerta'])
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 text-[10px] font-medium px-2 py-0.5 whitespace-nowrap"
                                      title="{{ $tituloAlerta }}">
                                    revisar
                                </span>
                            @endif
                        </div>
                    </div>

                    @if (!empty($o['programa']['salonTejidoId']) || !empty($o['programa']['esOrdCompartida']) || !empty($o['meses']))
                        <div class="flex flex-wrap gap-1 mb-2">
                            @if (!empty($o['programa']['salonTejidoId']))
                                <span class="text-[10px] font-medium text-slate-600 bg-white border border-slate-200 rounded px-1.5 py-0.5">
                                    {{ $o['programa']['salonTejidoId'] }}
                                </span>
                            @endif
                            @if (!empty($o['programa']['esOrdCompartida']))
                                <span class="text-[10px] font-medium text-violet-700 bg-violet-50 border border-violet-100 rounded px-1.5 py-0.5">
                                    ord. compartida {{ $o['programa']['ordCompartida'] }}
                                    @if ($o['programa']['esLiderOrdCompartida'])
                                        · líder
                                    @endif
                                </span>
                            @endif
                            @foreach ($o['meses'] ?? [] as $mes)
                                <span class="text-[10px] font-medium text-indigo-700 bg-indigo-50 rounded px-1.5 py-0.5">{{ $mes }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($o['programa']['fechaInicio']) || !empty($o['programa']['fechaFinal']))
                        <div class="grid grid-cols-1 gap-0.5 text-[10px] text-slate-500 mb-2.5">
                            @if (!empty($o['programa']['fechaInicio']))
                                <span>inicio <span class="text-slate-700 font-medium">{{ $o['programa']['fechaInicio'] }}</span></span>
                            @endif
                            @if (!empty($o['programa']['fechaFinal']))
                                <span>final <span class="text-slate-700 font-medium">{{ $o['programa']['fechaFinal'] }}</span></span>
                            @endif
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-2 mb-2.5">
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
                            <div class="prod-stat-value">{{ $pzasDia !== null ? number_format($pzasDia) : '—' }}</div>
                        </div>
                    </div>

                    @if (!empty($o['programa']) || !empty($o['codificados']))
                        <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-500 mb-2.5 border-t border-slate-200/80 pt-2">
                            @if (!empty($o['programa']))
                                <span>
                                    saldo pedido
                                    <b class="tabular-nums {{ ($o['programa']['saldoPedido'] ?? 0) < 0 ? 'text-red-600' : 'text-slate-700' }}">
                                        {{ number_format($o['programa']['saldoPedido']) }}
                                    </b>
                                </span>
                            @elseif (!empty($o['codificados']))
                                <span>
                                    saldos
                                    <b class="tabular-nums {{ ($o['codificados']['saldos'] ?? 0) < 0 ? 'text-red-600' : 'text-slate-700' }}">
                                        {{ number_format($o['codificados']['saldos']) }}
                                    </b>
                                </span>
                            @endif
                            <span>
                                traza
                                <b class="text-slate-700 tabular-nums">{{ number_format($o['producidas']) }}</b>
                            </span>
                        </div>
                    @endif

                    <div class="mt-auto pt-1">
                        <div class="flex items-center justify-between gap-2 text-[11px] mb-1.5 min-w-0">
                            <span class="text-slate-600 shrink-0">
                                avance: <b class="text-slate-800">{{ number_format($avance, 1) }}%</b>
                            </span>
                            <span class="text-slate-400 tabular-nums truncate text-right" title="{{ $avanceRef }}">{{ $avanceRef }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-200/80 overflow-hidden">
                            <div class="h-full rounded-full bg-blue-500 transition-all" style="width: {{ $ancho }}%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
