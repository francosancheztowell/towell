{{-- Detalle: matriz de piezas por área, agrupada por mes → semana → día.
     Solo se pintan las columnas de mes: las de semana/día las arma matrix-detail.ts
     al expandir, con los datos compactos de `meta` (filas, totales, columnas). --}}
@php
    $columnasPeriodos = $columnasPeriodos ?? [];
    $hayPeriodos = ! empty($columnasPeriodos);
    $columnasMes = array_values(array_filter($columnasPeriodos, static fn (array $periodo): bool => $periodo['nivel'] === 'mes'));
    $sumarPeriodo = static function (array $valores, array $indices, int $precision): ?float {
        $tieneValor = false;
        $suma = 0.0;

        foreach ($indices as $indice) {
            if (array_key_exists($indice, $valores) && ! is_null($valores[$indice])) {
                $tieneValor = true;
                $suma += (float) $valores[$indice];
            }
        }

        return $tieneValor ? round($suma, $precision) : null;
    };
@endphp

<div id="trazabilidad-matriz-detalle" class="space-y-3">
    <div class="flex flex-wrap items-center gap-2">
        <flux:heading size="lg" level="3">
            Piezas por día y área
            @if ($hayFlog)
                <span class="text-blue-600">· {{ $filtros['flog'] }}</span>
            @endif
        </flux:heading>

        @if ($hayFlog && $info)
            @if (filled($info->Tipo))
                <flux:badge size="sm" color="amber" icon="tag">{{ $info->Tipo }}</flux:badge>
            @endif
            @if (filled($info->Cliente))
                <flux:badge size="sm" color="blue" icon="building-office">{{ $info->Cliente }}</flux:badge>
            @endif
            @if (filled($info->Agente))
                <flux:badge size="sm" color="emerald" icon="user">{{ $info->Agente }}</flux:badge>
            @endif
        @endif

        @if ($hayPeriodos)
            <flux:button size="sm" variant="outline" icon="arrows-pointing-out" class="ms-auto min-h-touch"
                         data-expandir-periodos aria-expanded="false">
                <span data-expandir-periodos-label>Expandir todo</span>
            </flux:button>
        @endif
    </div>

    <flux:card class="overflow-hidden !p-0">
        <div class="traza-scroll overflow-x-auto">
            <table class="traza-matriz-periodos border-separate border-spacing-0 text-sm">
                <thead>
                    <tr>
                        <th scope="col" class="traza-col-area text-left">Área</th>
                        <th scope="col" class="traza-col-total">Total</th>

                        @foreach ($columnasMes as $periodo)
                            <th scope="col" class="traza-periodo-col traza-periodo-col--mes"
                                data-periodo-nivel="mes" data-mes-key="{{ $periodo['mesClave'] }}">
                                <button type="button"
                                        data-periodo-toggle="mes"
                                        data-periodo-key="{{ $periodo['clave'] }}"
                                        aria-expanded="false"
                                        class="traza-periodo-toggle"
                                        title="Mostrar semanas del mes">
                                    <flux:icon.chevron-right variant="micro" class="periodo-caret shrink-0 transition-transform" />
                                    <span class="flex flex-col leading-tight">
                                        <span>{{ $periodo['label'] }}</span>
                                        <small>{{ $periodo['subLabel'] }}</small>
                                        <span class="traza-periodo-subtotal">Subtotal mes</span>
                                    </span>
                                </button>
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach ($areas as $idx => $area)
                        @php
                            $expandible = ! empty($area['detalles']);
                            $totalArea = array_sum(array_map(fn ($v) => (float) ($v ?? 0), $area['valores']));
                        @endphp
                        <tr @class(['area-fila' => $expandible]) data-area-index="{{ $idx }}"
                            @if ($expandible) data-area-key="{{ $idx }}" data-area-dot="{{ $area['dot'] }}" @endif>
                            <th scope="row" class="traza-col-area" style="--area-dot: {{ $area['dot'] }}">
                                @if ($expandible)
                                    <button type="button" class="traza-area-toggle" aria-expanded="false"
                                            aria-label="Desglose de {{ $area['label'] ?? $area['nombre'] }} por artículo y color">
                                        <flux:icon.chevron-right variant="micro" class="area-caret shrink-0 text-zinc-400 transition-transform" />
                                        <span class="traza-area-nombre" style="color: {{ $area['text'] }}">{{ $area['label'] ?? $area['nombre'] }}</span>
                                        <span class="text-xs font-normal text-zinc-500">({{ count($area['detalles']) }})</span>
                                    </button>
                                @else
                                    <span class="traza-area-nombre" style="color: {{ $area['text'] }}">{{ $area['label'] ?? $area['nombre'] }}</span>
                                @endif
                            </th>

                            <td class="traza-col-total" style="color: {{ $area['text'] }}">
                                {{ $totalArea ? number_format($totalArea, $decimales) : '—' }}
                            </td>

                            @foreach ($columnasMes as $periodo)
                                @php $valor = $sumarPeriodo($area['valores'], $periodo['indices'], $decimales); @endphp
                                <td @class([
                                        'traza-periodo-col traza-periodo-col--mes',
                                        'traza-vacio' => is_null($valor),
                                    ])
                                    data-periodo-nivel="mes" data-mes-key="{{ $periodo['mesClave'] }}"
                                    @unless (is_null($valor)) style="color: {{ $area['text'] }}; background-color: {{ $area['tint'] }}" @endunless>
                                    {{ is_null($valor) ? '—' : number_format($valor, $decimales) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        @php $granTotal = array_sum(array_map(fn ($v) => (float) ($v ?? 0), $totales)); @endphp
                        <th scope="row" class="traza-col-area text-left">Total</th>
                        <td class="traza-col-total">{{ $granTotal ? number_format($granTotal, $decimales) : '—' }}</td>

                        @foreach ($columnasMes as $periodo)
                            @php $totalPeriodo = $sumarPeriodo($totales, $periodo['indices'], $decimales); @endphp
                            <td class="traza-periodo-col traza-periodo-col--mes"
                                data-periodo-nivel="mes" data-mes-key="{{ $periodo['mesClave'] }}">
                                {{ is_null($totalPeriodo) ? '—' : number_format($totalPeriodo, $decimales) }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
    </flux:card>
</div>
