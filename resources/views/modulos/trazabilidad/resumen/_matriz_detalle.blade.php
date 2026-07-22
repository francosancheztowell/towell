{{-- ===== Pestaña: Trazabilidad (matriz agrupada por mes, semana y día) ===== --}}
@php
    $columnasPeriodos = $columnasPeriodos ?? [];
    $sumarPeriodo = static function (array $valores, array $indices, int $precision): ?float {
        $tieneValor = false;
        $suma = 0.0;

        foreach ($indices as $indice) {
            if (array_key_exists($indice, $valores) && !is_null($valores[$indice])) {
                $tieneValor = true;
                $suma += (float) $valores[$indice];
            }
        }

        return $tieneValor ? round($suma, $precision) : null;
    };
@endphp

<div data-pane="trazabilidad">
    <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-2">
        <h2 class="whitespace-nowrap text-xs font-bold text-slate-600 md:text-sm">
            Producción por día y área
            @if ($hayFlog)
                <span class="ml-2 font-semibold normal-case text-blue-600">· {{ $filtros['flog'] }}</span>
            @endif
        </h2>

        @if ($hayFlog && $info)
            <span class="ml-3 flex flex-wrap items-center gap-2 md:ml-5">
                @if (filled($info->Tipo))
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                          style="background-color:#fef3c7;color:#92400e;">
                        <i class="fa-solid fa-tag"></i>{{ $info->Tipo }}
                    </span>
                @endif
                @if (filled($info->Cliente))
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                          style="background-color:#dbeafe;color:#1e40af;">
                        <i class="fa-solid fa-user-tie"></i>{{ $info->Cliente }}
                    </span>
                @endif
                @if (filled($info->Agente))
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                          style="background-color:#dcfce7;color:#166534;">
                        <i class="fa-solid fa-id-badge"></i>{{ $info->Agente }}
                    </span>
                @endif
            </span>
        @endif

        <span class="h-px flex-1 bg-slate-200"></span>

        @if (!empty($columnasPeriodos))
            <button type="button" data-expandir-periodos aria-expanded="false"
                    class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 transition hover:border-blue-300 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-300">
                <i class="fa-solid fa-expand"></i>
                <span data-expandir-periodos-label>Expandir todo</span>
            </button>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="traza-matriz-periodos border-collapse text-[13px]">
                <thead>
                    <tr class="bg-slate-50/80">
                        <th class="traza-col-area sticky left-0 z-30 w-[350px] whitespace-nowrap border-b border-slate-200 bg-slate-50 px-3 py-1.5 text-left font-bold text-slate-500"
                            style="border-right:2px solid #cbd5e1;">
                            Área
                        </th>
                        <th class="traza-col-total sticky left-[350px] z-[29] min-w-[72px] border-b border-r border-slate-200 bg-blue-50 px-2 py-1.5 text-center font-bold text-blue-800">
                            Total
                        </th>

                        @foreach ($columnasPeriodos as $periodo)
                            <th @class([
                                    'traza-periodo-col border-b border-r border-slate-200 text-center',
                                    'traza-periodo-col--mes' => $periodo['nivel'] === 'mes',
                                    'traza-periodo-col--semana' => $periodo['nivel'] === 'semana',
                                    'traza-periodo-col--dia' => $periodo['nivel'] === 'dia',
                                    'hidden' => $periodo['nivel'] !== 'mes',
                                ])
                                data-periodo-nivel="{{ $periodo['nivel'] }}"
                                data-mes-key="{{ $periodo['mesClave'] }}"
                                @if ($periodo['semanaClave']) data-semana-key="{{ $periodo['semanaClave'] }}" @endif>
                                @if (in_array($periodo['nivel'], ['mes', 'semana'], true))
                                    <button type="button"
                                            data-periodo-toggle="{{ $periodo['nivel'] }}"
                                            data-periodo-key="{{ $periodo['clave'] }}"
                                            aria-expanded="false"
                                            class="traza-periodo-toggle"
                                            title="{{ $periodo['nivel'] === 'mes' ? 'Mostrar semanas del mes' : 'Mostrar días de la semana' }}">
                                        <i class="periodo-caret fa-solid fa-chevron-right"></i>
                                        <span class="flex flex-col leading-tight">
                                            <span>{{ $periodo['label'] }}</span>
                                            <small>{{ $periodo['subLabel'] }}</small>
                                            <span class="traza-periodo-subtotal">
                                                {{ $periodo['nivel'] === 'mes' ? 'Subtotal mes' : 'Subtotal semana' }}
                                            </span>
                                        </span>
                                    </button>
                                @else
                                    <span class="flex flex-col leading-tight {{ $periodo['destacada'] ? 'font-extrabold text-blue-600' : 'font-semibold text-slate-500' }}">
                                        <span>{{ $periodo['label'] }}</span>
                                        <small class="font-medium text-slate-400">{{ $periodo['subLabel'] }}</small>
                                    </span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach ($areas as $idx => $area)
                        @php $expandible = !empty($area['detalles']); @endphp
                        <tr class="group transition-colors hover:bg-slate-50/40 {{ $expandible ? 'area-fila cursor-pointer select-none' : '' }}"
                            @if ($expandible) data-area-key="{{ $idx }}" @endif>
                            <td class="traza-col-area sticky left-0 z-20 w-[350px] border-b border-slate-100 bg-white px-3 py-3 group-hover:bg-slate-50"
                                style="box-shadow: inset 4px 0 0 0 {{ $area['dot'] }}; border-right:2px solid #cbd5e1;">
                                <span class="flex items-center gap-2">
                                    @if ($expandible)
                                        <i class="area-caret fa-solid fa-chevron-right flex-shrink-0 text-[10px] text-slate-400 transition-transform"></i>
                                    @endif
                                    <span class="inline-block h-2 w-2 flex-shrink-0 rounded-full"
                                          style="background-color: {{ $area['dot'] }};"></span>
                                    <span class="whitespace-nowrap text-xs font-semibold"
                                          style="color: {{ $area['text'] }};">{{ $area['label'] ?? $area['nombre'] }}</span>
                                    @if ($expandible)
                                        <span class="whitespace-nowrap text-[10px] font-medium text-slate-400">
                                            ({{ count($area['detalles']) }})
                                        </span>
                                    @endif
                                </span>
                            </td>

                            @php $totalArea = array_sum(array_map(fn ($v) => (float) ($v ?? 0), $area['valores'])); @endphp
                            <td class="traza-col-total sticky left-[350px] z-[19] min-w-[72px] border-b border-r border-slate-200 bg-blue-50 px-2 py-3 text-center font-bold tabular-nums"
                                style="color: {{ $area['text'] }};">
                                {{ $totalArea ? number_format($totalArea, $decimales) : '—' }}
                            </td>

                            @foreach ($columnasPeriodos as $periodo)
                                @php
                                    $valor = $sumarPeriodo($area['valores'], $periodo['indices'], $decimales);
                                    $indiceDia = $periodo['nivel'] === 'dia' ? $periodo['indices'][0] : null;
                                    $fondo = $periodo['nivel'] === 'dia'
                                        ? ($area['bgs'][$indiceDia] ?? $area['tint'])
                                        : $area['tint'];
                                @endphp
                                <td @class([
                                        'traza-periodo-col border-b border-r border-slate-200 px-2 py-3 text-center tabular-nums',
                                        'traza-periodo-col--mes font-extrabold' => $periodo['nivel'] === 'mes',
                                        'traza-periodo-col--semana font-bold' => $periodo['nivel'] === 'semana',
                                        'traza-periodo-col--dia font-semibold' => $periodo['nivel'] === 'dia',
                                        'hidden' => $periodo['nivel'] !== 'mes',
                                        'text-slate-300 select-none' => is_null($valor),
                                    ])
                                    data-periodo-nivel="{{ $periodo['nivel'] }}"
                                    data-mes-key="{{ $periodo['mesClave'] }}"
                                    @if ($periodo['semanaClave']) data-semana-key="{{ $periodo['semanaClave'] }}" @endif
                                    style="color: {{ is_null($valor) ? '#cbd5e1' : $area['text'] }}; background-color: {{ is_null($valor) ? '#ffffff' : $fondo }};">
                                    {{ !is_null($valor) ? number_format($valor, $decimales) : '—' }}
                                </td>
                            @endforeach
                        </tr>

                        @if ($expandible)
                            @foreach ($area['detalles'] as $det)
                                <tr class="detalle-fila hidden bg-slate-100" data-area-key="{{ $idx }}">
                                    <td class="traza-col-area sticky left-0 z-20 w-[350px] border-b border-slate-300 bg-slate-200 px-3 py-1.5"
                                        style="box-shadow: inset 4px 0 0 0 {{ $area['dot'] }}; border-right:2px solid #cbd5e1;">
                                        <span class="flex flex-col pl-6 leading-tight">
                                            <span class="whitespace-nowrap text-[11px] font-semibold text-slate-600">
                                                {{ $det['articulo'] ?: '—' }}
                                            </span>
                                            <span class="whitespace-nowrap text-[10px] text-slate-400">
                                                <i class="fa-solid fa-palette mr-0.5"></i>{{ $det['color'] ?: 'Sin color' }}
                                            </span>
                                        </span>
                                    </td>

                                    <td class="traza-col-total sticky left-[350px] z-[19] min-w-[72px] border-b border-r border-slate-200 bg-blue-50 px-2 py-1.5 text-center text-[12px] font-semibold text-slate-700 tabular-nums">
                                        {{ $det['total'] ? number_format($det['total'], $decimales) : '—' }}
                                    </td>

                                    @foreach ($columnasPeriodos as $periodo)
                                        @php $valorDetalle = $sumarPeriodo($det['valores'], $periodo['indices'], $decimales); @endphp
                                        <td @class([
                                                'traza-periodo-col border-b border-r border-slate-200 px-2 py-1.5 text-center text-[12px] tabular-nums',
                                                'traza-periodo-col--mes font-bold text-slate-700' => $periodo['nivel'] === 'mes',
                                                'traza-periodo-col--semana font-semibold text-slate-600' => $periodo['nivel'] === 'semana',
                                                'traza-periodo-col--dia text-slate-600' => $periodo['nivel'] === 'dia',
                                                'hidden' => $periodo['nivel'] !== 'mes',
                                                'text-slate-300 select-none' => is_null($valorDetalle) || (float) $valorDetalle === 0.0,
                                            ])
                                            data-periodo-nivel="{{ $periodo['nivel'] }}"
                                            data-mes-key="{{ $periodo['mesClave'] }}"
                                            @if ($periodo['semanaClave']) data-semana-key="{{ $periodo['semanaClave'] }}" @endif>
                                            {{ !is_null($valorDetalle) && (float) $valorDetalle !== 0.0 ? number_format($valorDetalle, $decimales) : '·' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                </tbody>

                <tfoot>
                    <tr class="bg-blue-50/70">
                        <td class="traza-col-area sticky left-0 z-20 bg-blue-50 px-3 py-1.5 font-bold text-blue-800"
                            style="border-right:2px solid #cbd5e1;">
                            Total
                        </td>
                        @php $granTotal = array_sum(array_map(fn ($v) => (float) ($v ?? 0), $totales)); @endphp
                        <td class="traza-col-total sticky left-[350px] z-[19] min-w-[72px] border-r border-slate-200 bg-blue-100 px-2 py-1.5 text-center font-extrabold text-blue-900 tabular-nums">
                            {{ $granTotal ? number_format($granTotal, $decimales) : '—' }}
                        </td>

                        @foreach ($columnasPeriodos as $periodo)
                            @php $totalPeriodo = $sumarPeriodo($totales, $periodo['indices'], $decimales); @endphp
                            <td @class([
                                    'traza-periodo-col border-r border-slate-200 px-2 py-1.5 text-center font-bold text-blue-800 tabular-nums',
                                    'traza-periodo-col--mes' => $periodo['nivel'] === 'mes',
                                    'traza-periodo-col--semana' => $periodo['nivel'] === 'semana',
                                    'traza-periodo-col--dia' => $periodo['nivel'] === 'dia',
                                    'hidden' => $periodo['nivel'] !== 'mes',
                                ])
                                data-periodo-nivel="{{ $periodo['nivel'] }}"
                                data-mes-key="{{ $periodo['mesClave'] }}"
                                @if ($periodo['semanaClave']) data-semana-key="{{ $periodo['semanaClave'] }}" @endif>
                                {{ !is_null($totalPeriodo) ? number_format($totalPeriodo, $decimales) : '—' }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
