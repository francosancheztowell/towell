{{-- Tarjeta de producción (Crudo o Rollos Teñido) --}}
@php
    $modo = $modo ?? 'crudo';
    $soloFecha = $soloFecha ?? function (?string $fecha): ?string {
        if (blank($fecha)) {
            return null;
        }
        $partes = preg_split('/\s+/', trim($fecha), 2);

        return $partes[0] ?? trim($fecha);
    };

    if ($modo === 'rollos') {
        $tituloPrincipal = $o['titulo'] ?? ($o['localidad'] ?? 'Sin localidad');
        $cantidadTraza = (float) ($o['cantidad'] ?? 0);
        $pesoTraza = (float) ($o['peso'] ?? 0);
        $accentClass = 'prod-card-v2__accent--rollos';
    } else {
        if (!empty($o['programa'])) {
            $programadas = (float) ($o['programa']['totalPedido'] ?? 0);
            $producidasBd = (float) ($o['programa']['produccion'] ?? 0);
            $pzasDia = $o['programa']['stdDia'] ?? null;
            $prodKgDia = $o['programa']['prodKgDia'] ?? null;
        } elseif (!empty($o['codificados'])) {
            $programadas = (float) ($o['codificados']['pedido'] ?? 0);
            $producidasBd = (float) ($o['codificados']['produccion'] ?? 0);
            $pzasDia = null;
            $prodKgDia = null;
        } else {
            $programadas = (float) ($o['programadas'] ?? 0);
            $producidasBd = (float) ($o['producidas'] ?? 0);
            $pzasDia = $o['pzasDia'] ?? null;
            $prodKgDia = null;
        }

        $kgProducidos = (float) ($o['kg'] ?? 0);
        $avanceRef = number_format($producidasBd).' / '.number_format($programadas);
        $avance = $o['avance'];
        $ancho = min(100, max(0, $avance));

        $accentClass = $o['alerta']
            ? 'prod-card-v2__accent--alerta'
            : ($o['estado'] === 'activo' ? 'prod-card-v2__accent--activo' : 'prod-card-v2__accent--terminado');

        $conflicto = $o['localidadesConflicto'] ?? [];
        $conflictoTexto = implode(', ', $conflicto);
        $tituloAlerta = $conflictoTexto !== ''
            ? 'En trazabilidad hay producción de esta orden también en: '.$conflictoTexto.'. En programa/catálogo está en '.$o['telar'].'.'
            : 'La localidad en trazabilidad no coincide con el telar del programa.';
        $etiquetaAlerta = $conflictoTexto !== ''
            ? 'Prod. en '.$conflicto[0]
            : 'Otro telar';

        $tituloPrincipal = $o['telar'];
    }
@endphp

<div class="prod-card prod-card-v2 {{ $modo === 'crudo' && ($o['alerta'] ?? false) ? 'prod-card--alerta' : '' }}"
     @if ($modo === 'crudo') data-estado="{{ $o['estado'] }}" @endif>
    <div class="prod-card-v2__accent {{ $accentClass }}" aria-hidden="true"></div>

    <div class="flex flex-col flex-1 p-4 min-w-0">
        <div class="flex items-start justify-between gap-2 mb-2.5">
            <div class="min-w-0">
                <div class="text-2xl font-extrabold text-slate-800 leading-none tracking-tight">
                    {{ $tituloPrincipal }}
                </div>
                <div class="text-2xl font-extrabold text-slate-800 leading-none tracking-tight mt-1 font-mono">
                    Orden {{ $o['orden'] }}
                </div>
            </div>
            @if ($modo === 'crudo')
                <div class="flex flex-col items-end gap-1 shrink-0 max-w-[48%]">
                    @if ($o['enProceso'])
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-medium px-2.5 py-1 whitespace-nowrap">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            En producción
                        </span>
                    @elseif ($o['fuente'] === 'programa')
                        <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-medium px-2.5 py-1 whitespace-nowrap">
                            Programado
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 text-[11px] font-medium px-2.5 py-1 whitespace-nowrap">
                            Finalizado
                        </span>
                    @endif
                    @if ($o['alerta'])
                        <span class="inline-flex items-center rounded-full bg-amber-50 text-amber-800 text-[10px] font-medium px-2 py-0.5 whitespace-nowrap"
                              title="{{ $tituloAlerta }}">
                            {{ $etiquetaAlerta }}
                        </span>
                    @endif
                </div>
            @elseif (filled($o['tipo'] ?? null))
                <span class="inline-flex items-center rounded-full bg-blue-50 text-blue-700 text-[10px] font-medium px-2 py-0.5 whitespace-nowrap shrink-0 max-w-[48%] truncate"
                      title="{{ $o['tipo'] }}">
                    {{ $o['tipo'] }}
                </span>
            @endif
        </div>

        @php
            if ($modo === 'rollos') {
                $hayBadges = filled($o['articulo'] ?? null)
                    || filled($o['color'] ?? null)
                    || !empty($o['meses']);
            } else {
                $fechaInicio = $soloFecha($o['programa']['fechaInicio'] ?? null);
                $fechaFinal = $soloFecha($o['programa']['fechaFinal'] ?? null);
                $hayBadges = !empty($o['programa']['esOrdCompartida'])
                    || !empty($o['meses'])
                    || filled($fechaInicio)
                    || filled($fechaFinal);
            }
        @endphp
        @if ($hayBadges)
            <div class="flex flex-wrap gap-1 mb-2.5">
                @if ($modo === 'rollos')
                    @if (filled($o['articulo'] ?? null))
                        <span class="prod-badge prod-badge--articulo" title="{{ $o['nombreArticulo'] ?? '' }}">
                            {{ trim(($o['articulo'] ?? '').(filled($o['nombreArticulo'] ?? null) ? ' · '.$o['nombreArticulo'] : '')) }}
                        </span>
                    @endif
                    @if (filled($o['color'] ?? null))
                        <span class="prod-badge prod-badge--color" title="{{ $o['nombreColor'] ?? '' }}">
                            {{ trim(($o['color'] ?? '').(filled($o['nombreColor'] ?? null) ? ' · '.$o['nombreColor'] : '')) }}
                        </span>
                    @endif
                    @foreach ($o['meses'] ?? [] as $mes)
                        <span class="prod-badge prod-badge--mes">{{ $mes }}</span>
                    @endforeach
                @else
                    @if (!empty($o['programa']['esOrdCompartida']))
                        <span class="prod-badge prod-badge--compartida">
                            Ord. compartida {{ $o['programa']['ordCompartida'] }}
                            @if ($o['programa']['esLiderOrdCompartida'])
                                · Líder
                            @endif
                        </span>
                    @endif
                    @foreach ($o['meses'] ?? [] as $mes)
                        <span class="prod-badge prod-badge--mes">{{ $mes }}</span>
                    @endforeach
                    @if (filled($fechaInicio))
                        <span class="prod-badge prod-badge--fecha">Inicio {{ $fechaInicio }}</span>
                    @endif
                    @if (filled($fechaFinal))
                        <span class="prod-badge prod-badge--fecha">Final {{ $fechaFinal }}</span>
                    @endif
                @endif
            </div>
        @endif

        @if ($modo === 'rollos')
            <div class="grid grid-cols-2 gap-2 mb-3">
                <div class="prod-stat-box col-span-2"
                     title="Suma de Cantidad y Peso en TrazaProduccion (área Rollos Teñido) para esta orden y localidad.">
                    <div class="prod-stat-label">Producido</div>
                    <div class="prod-stat-value prod-stat-value--sm">{{ number_format($cantidadTraza) }} Pzas</div>
                    <div class="prod-stat-value prod-stat-value--sm text-blue-800">{{ number_format($pesoTraza, 2) }} Kg</div>
                </div>
            </div>
        @else
            <div class="grid grid-cols-2 gap-2 mb-3">
                <div class="prod-stat-box">
                    <div class="prod-stat-label">Programadas</div>
                    <div class="prod-stat-value">{{ number_format($programadas) }}</div>
                </div>
                <div class="prod-stat-box"
                     title="Piezas: ReqProgramaTejido.Produccion (o CatCodificados.Produccion). Kilos: suma de Peso en TrazaProduccion, área Crudo, en el telar del programa.">
                    <div class="prod-stat-label">Producido</div>
                    <div class="prod-stat-value prod-stat-value--sm">{{ number_format($producidasBd) }} Pzas</div>
                    <div class="prod-stat-value prod-stat-value--sm text-teal-800">{{ number_format($kgProducidos, 2) }} Kg</div>
                </div>
                <div class="prod-stat-box col-span-2"
                     title="StdDia y ProdKgDia de ReqProgramaTejido (piezas y kg estándar por día).">
                    <div class="prod-stat-label">ProdKgDia</div>
                    @if ($pzasDia !== null || $prodKgDia !== null)
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 mt-0.5">
                            @if ($pzasDia !== null)
                                <span class="prod-stat-value text-base">{{ number_format($pzasDia) }} <span class="text-xs font-semibold text-slate-400">Pzas/día</span></span>
                            @endif
                            @if ($prodKgDia !== null)
                                <span class="prod-stat-value text-base">{{ number_format($prodKgDia, 2) }} <span class="text-xs font-semibold text-slate-400">Kg/día</span></span>
                            @endif
                        </div>
                    @else
                        <div class="prod-stat-value">—</div>
                    @endif
                </div>
            </div>

            <div class="mt-auto">
                <div class="flex items-center justify-between text-[11px] mb-1.5 gap-2">
                    <span class="text-slate-600">
                        Avance: <b class="text-slate-900">{{ number_format($avance, 1) }}%</b>
                    </span>
                    <span class="text-slate-400 tabular-nums shrink-0">{{ $avanceRef }}</span>
                </div>
                <div class="h-2 rounded-full bg-slate-200 overflow-hidden">
                    <div class="h-full rounded-full bg-blue-500" style="width: {{ $ancho }}%;"></div>
                </div>
            </div>
        @endif
    </div>
</div>
