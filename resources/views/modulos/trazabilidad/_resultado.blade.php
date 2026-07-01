{{-- Contenido del módulo de Trazabilidad. Se renderiza tanto en la carga inicial
     como en las respuestas AJAX (sin recargar la página). Dos pestañas comparten
     los mismos filtros:
       · Trazabilidad → matriz "Producción por día y área".
       · Producción   → tarjetas por telar (Orden/Localidad vs ReqProgramaTejido). --}}

@unless ($hayFiltro)
    {{-- Estado inicial: aún no se aplica ningún filtro --}}
    <div class="bg-white border border-dashed border-slate-300 rounded-2xl p-10 md:p-14 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center mb-4">
            <i class="fa-solid fa-magnifying-glass text-blue-500 text-lg"></i>
        </div>
        <p class="text-slate-700 font-semibold">Selecciona al menos un filtro para ver la trazabilidad</p>
        <p class="text-slate-400 text-sm mt-1">
            Puedes empezar por Artículo, Tamaño, Color o Mes, y luego afinar con un Flog.
        </p>
    </div>
@else
    {{-- ===== Pestañas (Trazabilidad / Producción) ===== --}}
    <div class="flex items-center gap-1 mb-4 border-b border-slate-200" id="traza-tabs">
        <button type="button" data-tab="trazabilidad"
                class="traza-tab inline-flex items-center gap-1.5 px-4 py-2 text-sm font-bold border-b-2 -mb-px transition-colors">
            <i class="fa-solid fa-table-cells-large"></i> Trazabilidad
        </button>
        <button type="button" data-tab="produccion"
                class="traza-tab inline-flex items-center gap-1.5 px-4 py-2 text-sm font-bold border-b-2 -mb-px transition-colors">
            <i class="fa-solid fa-industry"></i> Producción
            @php $prodIssues = (int) ($produccion['resumen']['alertas'] ?? 0); @endphp
            @if ($prodIssues > 0)
                <span class="prod-alert-badge inline-flex items-center justify-center rounded-full bg-amber-500 text-white text-[10px] font-bold min-w-4 h-4 px-1"
                      title="{{ $prodIssues }} orden(es) con producción en otro telar">
                    {{ $prodIssues }}
                </span>
            @endif
        </button>
    </div>

    {{-- ===== Pestaña: Trazabilidad (matriz por día y área) ===== --}}
    <div data-pane="trazabilidad">
        {{-- Encabezado de sección --}}
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 mb-3">
            <h2 class="text-xs md:text-sm font-bold text-slate-600 whitespace-nowrap">
                Producción por día y área
                @if ($hayFlog)
                    <span class="ml-2 normal-case font-semibold text-blue-600">· {{ $filtros['flog'] }}</span>
                @endif
            </h2>

            {{-- Tipo / Cliente / Agente del Flog seleccionado --}}
            @if ($hayFlog && $info)
                <span class="flex flex-wrap items-center gap-2 ml-3 md:ml-5">
                    @if (filled($info->Tipo))
                        <span class="inline-flex items-center gap-1.5 rounded-full text-xs font-semibold px-3 py-1"
                              style="background-color:#fef3c7;color:#92400e;">
                            <i class="fa-solid fa-tag"></i>{{ $info->Tipo }}
                        </span>
                    @endif
                    @if (filled($info->Cliente))
                        <span class="inline-flex items-center gap-1.5 rounded-full text-xs font-semibold px-3 py-1"
                              style="background-color:#dbeafe;color:#1e40af;">
                            <i class="fa-solid fa-user-tie"></i>{{ $info->Cliente }}
                        </span>
                    @endif
                    @if (filled($info->Agente))
                        <span class="inline-flex items-center gap-1.5 rounded-full text-xs font-semibold px-3 py-1"
                              style="background-color:#dcfce7;color:#166534;">
                            <i class="fa-solid fa-id-badge"></i>{{ $info->Agente }}
                        </span>
                    @endif
                </span>
            @endif

            <span class="flex-1 h-px bg-slate-200"></span>
        </div>

        {{-- Tarjeta con la matriz --}}
        @php
            // Cuando hay UN solo mes seleccionado, la tabla ocupa todo el ancho (w-full).
            // Con varios meses (o ninguno) se ajusta al contenido para no estirarse de más.
            $unSoloMes = count(array_filter(array_map('trim', explode(',', (string) ($filtros['mes'] ?? ''))))) === 1;
        @endphp
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="{{ $unSoloMes ? 'w-full ' : '' }}border-collapse text-[13px]">
                    {{-- Cabecera: fechas --}}
                    <thead>
                        <tr class="bg-slate-50/80">
                            <th class="sticky left-0 z-20 bg-slate-50 text-left font-bold text-slate-500
                                       px-3 py-1.5 w-[350px] whitespace-nowrap border-b border-slate-200"
                                style="border-right:2px solid #cbd5e1;">
                                Área
                            </th>
                            @foreach ($fechas as $fecha)
                                <th class="px-2 py-1.5 text-center border-b border-r border-slate-200 min-w-[56px]
                                           {{ $fecha['destacada'] ? 'font-extrabold text-blue-600' : 'font-semibold text-slate-500' }}"
                                    @if (!empty($fecha['nuevoMes'])) style="border-left:3px solid #94a3b8;" @endif>
                                    {{ $fecha['label'] }}
                                </th>
                            @endforeach
                            <th class="px-2 py-1.5 text-center font-bold text-blue-800
                                       bg-blue-50 border-b border-l border-slate-200 min-w-[72px]">
                                Total
                            </th>
                        </tr>
                    </thead>

                    {{-- Cuerpo: áreas --}}
                    <tbody>
                        @foreach ($areas as $idx => $area)
                            @php
                                // Área expandible: solo cuando hay Flog con +2 artículos ($dropdown)
                                // y el área tiene desglose por artículo/color.
                                $expandible = ($dropdown ?? false) && !empty($area['detalles']);
                            @endphp
                            <tr class="group hover:bg-slate-50/40 transition-colors {{ $expandible ? 'area-fila cursor-pointer select-none' : '' }}"
                                @if ($expandible) data-area-key="{{ $idx }}" @endif>
                                {{-- Columna de área (sticky) --}}
                                <td class="sticky left-0 z-10 bg-white group-hover:bg-slate-50 px-3 py-3 w-[350px]
                                           border-b border-slate-100"
                                    style="box-shadow: inset 4px 0 0 0 {{ $area['dot'] }}; border-right:2px solid #cbd5e1;">
                                    <span class="flex items-center gap-2">
                                        @if ($expandible)
                                            <i class="area-caret fa-solid fa-chevron-right text-[10px] text-slate-400 transition-transform flex-shrink-0"></i>
                                        @endif
                                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0"
                                              style="background-color: {{ $area['dot'] }};"></span>
                                        <span class="font-semibold whitespace-nowrap text-xs"
                                              style="color: {{ $area['text'] }};">{{ $area['label'] ?? $area['nombre'] }}</span>
                                        @if ($expandible)
                                            <span class="text-[10px] font-medium text-slate-400 whitespace-nowrap">
                                                ({{ count($area['detalles']) }})
                                            </span>
                                        @endif
                                    </span>
                                </td>

                                {{-- Celdas de valores --}}
                                @foreach ($fechas as $i => $fecha)
                                    @php
                                        $valor = $area['valores'][$i] ?? null;
                                        $bordeMes = !empty($fecha['nuevoMes']) ? 'border-left:3px solid #94a3b8;' : '';
                                    @endphp
                                    @if (!is_null($valor))
                                        <td class="px-2 py-3 text-center font-semibold border-b border-r border-slate-200 tabular-nums"
                                            style="background-color: {{ $area['bgs'][$i] ?? $area['tint'] }}; color: {{ $area['text'] }};{{ $bordeMes }}">
                                            {{ number_format($valor, $decimales) }}
                                        </td>
                                    @else
                                        <td class="px-2 py-3 text-center text-slate-300 border-b border-r border-slate-200 select-none"
                                            style="{{ $bordeMes }}">
                                            —
                                        </td>
                                    @endif
                                @endforeach

                                {{-- Total por área --}}
                                @php $totalArea = array_sum(array_map(fn ($v) => (float) ($v ?? 0), $area['valores'])); @endphp
                                <td class="px-2 py-3 text-center font-bold border-b border-l border-slate-200 bg-blue-50/60 tabular-nums"
                                    style="color: {{ $area['text'] }};">
                                    {{ $totalArea ? number_format($totalArea, $decimales) : '—' }}
                                </td>
                            </tr>

                            {{-- Sub-filas: desglose por artículo + color (ocultas hasta hacer click) --}}
                            @if ($expandible)
                                @foreach ($area['detalles'] as $det)
                                    <tr class="detalle-fila hidden bg-slate-100" data-area-key="{{ $idx }}">
                                        {{-- Artículo / color (sticky) --}}
                                        <td class="sticky left-0 z-10 bg-slate-200 px-3 py-1.5 w-[350px] border-b border-slate-300"
                                            style="box-shadow: inset 4px 0 0 0 {{ $area['dot'] }}; border-right:2px solid #cbd5e1;">
                                            <span class="flex flex-col pl-6 leading-tight">
                                                <span class="text-[11px] font-semibold text-slate-600 whitespace-nowrap">
                                                    {{ $det['articulo'] ?: '—' }}
                                                </span>
                                                <span class="text-[10px] text-slate-400 whitespace-nowrap">
                                                    <i class="fa-solid fa-palette mr-0.5"></i>{{ $det['color'] ?: 'Sin color' }}
                                                </span>
                                            </span>
                                        </td>

                                        {{-- Valores por fecha del artículo/color --}}
                                        @foreach ($fechas as $i => $fecha)
                                            @php
                                                $v = $det['valores'][$i] ?? null;
                                                $bordeMes = !empty($fecha['nuevoMes']) ? 'border-left:3px solid #94a3b8;' : '';
                                            @endphp
                                            @if (!is_null($v) && (float) $v != 0.0)
                                                <td class="px-2 py-1.5 text-center text-[12px] text-slate-600 border-b border-r border-slate-200 tabular-nums"
                                                    style="{{ $bordeMes }}">
                                                    {{ number_format($v, $decimales) }}
                                                </td>
                                            @else
                                                <td class="px-2 py-1.5 text-center text-slate-300 border-b border-r border-slate-200 select-none"
                                                    style="{{ $bordeMes }}">·</td>
                                            @endif
                                        @endforeach

                                        {{-- Total de la sub-fila --}}
                                        <td class="px-2 py-1.5 text-center text-[12px] font-semibold text-slate-700 border-b border-l border-slate-200 bg-blue-50/40 tabular-nums">
                                            {{ $det['total'] ? number_format($det['total'], $decimales) : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>

                    {{-- Pie: totales --}}
                    <tfoot>
                        <tr class="bg-blue-50/70">
                            <td class="sticky left-0 z-10 bg-blue-50 px-3 py-1.5 font-bold text-blue-800"
                                style="border-right:2px solid #cbd5e1;">
                                Total
                            </td>
                            @foreach ($fechas as $i => $fecha)
                                <td class="px-2 py-1.5 text-center font-bold text-blue-800 border-r border-slate-200 tabular-nums"
                                    @if (!empty($fecha['nuevoMes'])) style="border-left:3px solid #94a3b8;" @endif>
                                    {{ !is_null($totales[$i]) ? number_format($totales[$i], $decimales) : '—' }}
                                </td>
                            @endforeach
                            @php $granTotal = array_sum(array_map(fn ($v) => (float) ($v ?? 0), $totales)); @endphp
                            <td class="px-2 py-1.5 text-center font-extrabold text-blue-900 bg-blue-100/70 border-l border-slate-200 tabular-nums">
                                {{ $granTotal ? number_format($granTotal, $decimales) : '—' }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- ===== Pestaña: Producción (Crudo + Rollos Teñido) ===== --}}
    <div data-pane="produccion" class="hidden">
        <div id="produccion-contenido">
            @if ($produccionCargando ?? false)
                @include('modulos.trazabilidad._produccion_loading')
            @else
                @include('modulos.trazabilidad._produccion')
            @endif
        </div>
    </div>
@endunless
