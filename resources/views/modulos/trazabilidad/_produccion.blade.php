{{-- Tarjetas producción: diseño compacto (telar + orden + métricas + avance) --}}

@php
    $prod       = $produccion ?? ['ordenes' => [], 'noEncontradas' => []];
    $ordenCards = $prod['ordenes'] ?? [];
    $resumen    = $prod['resumen'] ?? [];
    $soloFecha = function (?string $fecha): ?string {
        if (blank($fecha)) {
            return null;
        }
        $partes = preg_split('/\s+/', trim($fecha), 2);

        return $partes[0] ?? trim($fecha);
    };
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
                <span class="text-[11px] bg-amber-50 text-amber-700 rounded-full px-2 py-0.5 font-medium"
                      title="La trazabilidad registra piezas de esa orden en un telar distinto al del programa">
                    {{ $resumen['alertas'] }} con prod. en otro telar
                </span>
            @endif
        </div>

        <div class="prod-segment select-none shrink-0 self-start sm:self-auto" role="group" aria-label="Filtrar órdenes">
            <button type="button" data-filter="todos"
                    class="prod-filter-btn prod-segment__btn is-active">
                <span>todos</span>
                <span class="prod-segment__count">{{ count($ordenCards) }}</span>
            </button>
            <button type="button" data-filter="activo"
                    class="prod-filter-btn prod-segment__btn prod-segment__btn--activo">
                <span>activo</span>
                <span class="prod-segment__count">{{ $resumen['activos'] ?? 0 }}</span>
            </button>
            <button type="button" data-filter="terminado"
                    class="prod-filter-btn prod-segment__btn prod-segment__btn--terminado">
                <span>terminado</span>
                <span class="prod-segment__count">{{ $resumen['terminados'] ?? 0 }}</span>
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
                } elseif (!empty($o['codificados'])) {
                    $programadas = (float) ($o['codificados']['pedido'] ?? 0);
                    $producidasBd = (float) ($o['codificados']['produccion'] ?? 0);
                    $pzasDia = null;
                } else {
                    $programadas = (float) ($o['programadas'] ?? 0);
                    $producidasBd = (float) ($o['producidas'] ?? 0);
                    $pzasDia = $o['pzasDia'] ?? null;
                }

                $avanceRef = number_format($producidasBd).' / '.number_format($programadas);
                $avance = $o['avance'];
                $ancho  = min(100, max(0, $avance));

                $accentClass = $o['alerta']
                    ? 'prod-card-v2__accent--alerta'
                    : ($o['estado'] === 'activo' ? 'prod-card-v2__accent--activo' : 'prod-card-v2__accent--terminado');

                $conflicto = $o['localidadesConflicto'] ?? [];
                $conflictoTexto = implode(', ', $conflicto);
                $tituloAlerta = $conflictoTexto !== ''
                    ? 'En trazabilidad hay producción de esta orden también en: '.$conflictoTexto.'. En programa/catálogo está en '.$o['telar'].'.'
                    : 'La localidad en trazabilidad no coincide con el telar del programa.';
                $etiquetaAlerta = $conflictoTexto !== ''
                    ? 'prod. en '.$conflicto[0]
                    : 'otro telar';
            @endphp

            <div class="prod-card prod-card-v2 {{ $o['alerta'] ? 'prod-card--alerta' : '' }}"
                 data-estado="{{ $o['estado'] }}">
                <div class="prod-card-v2__accent {{ $accentClass }}" aria-hidden="true"></div>

                <div class="flex flex-col flex-1 p-4 min-w-0">
                    <div class="flex items-start justify-between gap-2 mb-2.5">
                        <div class="min-w-0">
                            <div class="text-2xl font-extrabold text-slate-800 leading-none tracking-tight">
                                {{ $o['telar'] }}
                            </div>
                            <div class="text-xs text-slate-500 mt-1 font-mono">
                                orden {{ $o['orden'] }}
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1 shrink-0 max-w-[48%]">
                            @if ($o['enProceso'])
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-medium px-2.5 py-1 whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    en producción
                                </span>
                            @elseif ($o['fuente'] === 'programa')
                                <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-medium px-2.5 py-1 whitespace-nowrap">
                                    programado
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 text-[11px] font-medium px-2.5 py-1 whitespace-nowrap">
                                    finalizado
                                </span>
                            @endif
                            @if ($o['alerta'])
                                <span class="inline-flex items-center rounded-full bg-amber-50 text-amber-800 text-[10px] font-medium px-2 py-0.5 whitespace-nowrap"
                                      title="{{ $tituloAlerta }}">
                                    {{ $etiquetaAlerta }}
                                </span>
                            @endif
                        </div>
                    </div>

                    @php
                        $fechaInicio = $soloFecha($o['programa']['fechaInicio'] ?? null);
                        $fechaFinal = $soloFecha($o['programa']['fechaFinal'] ?? null);
                        $hayBadges = !empty($o['programa']['esOrdCompartida'])
                            || !empty($o['meses'])
                            || filled($fechaInicio)
                            || filled($fechaFinal);
                    @endphp
                    @if ($hayBadges)
                        <div class="flex flex-wrap gap-1 mb-2.5">
                            @if (!empty($o['programa']['esOrdCompartida']))
                                <span class="prod-badge prod-badge--compartida">
                                    ord. compartida {{ $o['programa']['ordCompartida'] }}
                                    @if ($o['programa']['esLiderOrdCompartida'])
                                        · líder
                                    @endif
                                </span>
                            @endif
                            @foreach ($o['meses'] ?? [] as $mes)
                                <span class="prod-badge prod-badge--mes">{{ $mes }}</span>
                            @endforeach
                            @if (filled($fechaInicio))
                                <span class="prod-badge prod-badge--fecha">inicio {{ $fechaInicio }}</span>
                            @endif
                            @if (filled($fechaFinal))
                                <span class="prod-badge prod-badge--fecha">final {{ $fechaFinal }}</span>
                            @endif
                        </div>
                    @endif

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
                            <div class="prod-stat-value">{{ $pzasDia !== null ? number_format($pzasDia) : '—' }}</div>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <div class="flex items-center justify-between text-[11px] mb-1.5 gap-2">
                            <span class="text-slate-600">
                                avance: <b class="text-slate-900">{{ number_format($avance, 1) }}%</b>
                            </span>
                            <span class="text-slate-400 tabular-nums shrink-0">{{ $avanceRef }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-200 overflow-hidden">
                            <div class="h-full rounded-full bg-blue-500" style="width: {{ $ancho }}%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
