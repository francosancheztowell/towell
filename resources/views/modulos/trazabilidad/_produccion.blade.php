{{-- ============ Sección "Producción": telares del flog ============
     Lista los telares presentes en el filtro actual (Localidad) con su orden,
     tamaño y color. El telar de la Orden se busca en ReqProgramaTejido /
     CatCodificados y se compara con la Localidad: si no coincide o la orden no se
     localiza, se marca una alerta en la fila. --}}

@php
    $prod = $produccion ?? ['telares' => [], 'resumen' => []];
    $telaresProd = $prod['telares'] ?? [];
    $resumen = $prod['resumen'] ?? ['telares' => 0, 'ordenes' => 0, 'cantidad' => 0, 'peso' => 0, 'alertas' => 0];
@endphp

@if (!empty($telaresProd))
    <div class="mb-6">
        {{-- Encabezado de sección --}}
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 mb-3">
            <h2 class="text-xs md:text-sm font-bold text-slate-600 whitespace-nowrap flex items-center gap-1.5">
                <span>🧶</span> Producción
                @if ($hayFlog)
                    <span class="ml-2 normal-case font-semibold text-blue-600">· {{ $filtros['flog'] }}</span>
                @endif
            </h2>
            <span class="flex-1 h-px bg-slate-200"></span>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-5 gap-2 md:gap-3 mb-3">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-4 py-3">
                <div class="text-[11px] font-semibold text-slate-400 flex items-center gap-1">
                    <i class="fa-solid fa-industry"></i> Telares
                </div>
                <div class="text-xl font-extrabold text-slate-700 tabular-nums">{{ number_format($resumen['telares']) }}</div>
            </div>
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-4 py-3">
                <div class="text-[11px] font-semibold text-slate-400 flex items-center gap-1">
                    <i class="fa-solid fa-list-ol"></i> Órdenes
                </div>
                <div class="text-xl font-extrabold text-slate-700 tabular-nums">{{ number_format($resumen['ordenes']) }}</div>
            </div>
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-4 py-3">
                <div class="text-[11px] font-semibold text-slate-400 flex items-center gap-1">
                    <i class="fa-solid fa-boxes-stacked"></i> Crudo (pzas)
                </div>
                <div class="text-xl font-extrabold text-blue-700 tabular-nums">{{ number_format($resumen['cantidad']) }}</div>
            </div>
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-4 py-3">
                <div class="text-[11px] font-semibold text-slate-400 flex items-center gap-1">
                    <i class="fa-solid fa-weight-hanging"></i> Crudo (kg)
                </div>
                <div class="text-xl font-extrabold text-teal-700 tabular-nums">{{ number_format($resumen['peso'], 1) }}</div>
            </div>
            <div class="bg-white border rounded-2xl shadow-sm px-4 py-3 {{ $resumen['alertas'] > 0 ? 'border-amber-300 bg-amber-50' : 'border-slate-200' }}">
                <div class="text-[11px] font-semibold flex items-center gap-1 {{ $resumen['alertas'] > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                    <i class="fa-solid fa-triangle-exclamation"></i> Alertas
                </div>
                <div class="text-xl font-extrabold tabular-nums {{ $resumen['alertas'] > 0 ? 'text-amber-700' : 'text-slate-700' }}">
                    {{ number_format($resumen['alertas']) }}
                </div>
            </div>
        </div>

        {{-- Banner de alerta global: telares de orden que no coinciden con la Localidad --}}
        @if ($resumen['alertas'] > 0)
            <div class="flex items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 mb-3">
                <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5"></i>
                <p class="text-xs text-amber-800 leading-relaxed">
                    <strong>{{ $resumen['alertas'] }}</strong>
                    {{ $resumen['alertas'] === 1 ? 'orden' : 'órdenes' }} con discrepancia: el telar de la orden
                    (ReqProgramaTejido / CatCodificados) no coincide con la <strong>Localidad</strong> de
                    trazabilidad, o la orden no se localizó. Revisa las filas marcadas.
                </p>
            </div>
        @endif

        {{-- Tabla de telares --}}
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="bg-slate-50/80 text-slate-500">
                            <th class="text-left font-bold px-3 py-2 border-b border-slate-200 whitespace-nowrap">Telar</th>
                            <th class="text-left font-bold px-3 py-2 border-b border-slate-200 whitespace-nowrap">Orden</th>
                            <th class="text-left font-bold px-3 py-2 border-b border-slate-200 whitespace-nowrap">Artículo</th>
                            <th class="text-left font-bold px-3 py-2 border-b border-slate-200 whitespace-nowrap">Tamaño</th>
                            <th class="text-left font-bold px-3 py-2 border-b border-slate-200 whitespace-nowrap">Color</th>
                            <th class="text-right font-bold px-3 py-2 border-b border-slate-200 whitespace-nowrap">Crudo (pzas)</th>
                            <th class="text-right font-bold px-3 py-2 border-b border-slate-200 whitespace-nowrap">Crudo (kg)</th>
                            <th class="text-center font-bold px-3 py-2 border-b border-slate-200 whitespace-nowrap">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($telaresProd as $t)
                            @php
                                $hayAlerta = !empty($t['alerta']);
                                $filaCls = $hayAlerta ? 'bg-amber-50/60 hover:bg-amber-50' : 'hover:bg-slate-50/60';
                            @endphp
                            <tr class="transition-colors {{ $filaCls }}">
                                {{-- Telar (Localidad) --}}
                                <td class="px-3 py-2.5 border-b border-slate-100 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 font-bold text-slate-700">
                                        <span class="inline-block w-2 h-2 rounded-full {{ $hayAlerta ? 'bg-amber-400' : 'bg-emerald-400' }}"></span>
                                        {{ $t['localidad'] !== '' ? $t['localidad'] : '—' }}
                                    </span>
                                </td>
                                {{-- Orden --}}
                                <td class="px-3 py-2.5 border-b border-slate-100 whitespace-nowrap font-mono text-[12px] text-slate-600">
                                    {{ $t['orden'] }}
                                </td>
                                {{-- Artículo --}}
                                <td class="px-3 py-2.5 border-b border-slate-100 text-slate-600">
                                    {{ $t['articulo'] !== '' ? $t['articulo'] : '—' }}
                                </td>
                                {{-- Tamaño --}}
                                <td class="px-3 py-2.5 border-b border-slate-100 whitespace-nowrap text-slate-600">
                                    {{ $t['tamano'] !== '' ? $t['tamano'] : '—' }}
                                </td>
                                {{-- Color --}}
                                <td class="px-3 py-2.5 border-b border-slate-100 text-slate-600">
                                    <span class="inline-flex items-center gap-1.5">
                                        <i class="fa-solid fa-palette text-slate-300"></i>
                                        {{ $t['color'] !== '' ? $t['color'] : 'Sin color' }}
                                    </span>
                                </td>
                                {{-- Crudo (pzas) --}}
                                <td class="px-3 py-2.5 border-b border-slate-100 text-right tabular-nums font-semibold text-slate-700">
                                    {{ $t['cantidad'] > 0 ? number_format($t['cantidad']) : '—' }}
                                </td>
                                {{-- Crudo (kg) --}}
                                <td class="px-3 py-2.5 border-b border-slate-100 text-right tabular-nums font-semibold text-slate-700">
                                    {{ $t['peso'] > 0 ? number_format($t['peso'], 1) : '—' }}
                                </td>
                                {{-- Estado / alerta --}}
                                <td class="px-3 py-2.5 border-b border-slate-100 text-center whitespace-nowrap">
                                    @if ($t['alerta'] === 'no_coincide')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-800 text-[11px] font-semibold px-2.5 py-1"
                                              title="La Localidad ({{ $t['localidad'] ?: '—' }}) no coincide con el telar de la orden ({{ $t['telarOrden'] ?: '—' }})">
                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                            No coincide · orden T-{{ $t['telarOrden'] ?: '?' }}
                                        </span>
                                    @elseif ($t['alerta'] === 'no_encontrado')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 text-slate-500 text-[11px] font-semibold px-2.5 py-1"
                                              title="La orden {{ $t['orden'] }} no se encontró en ReqProgramaTejido ni en CatCodificados">
                                            <i class="fa-solid fa-circle-question"></i>
                                            Orden no localizada
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 text-emerald-700 text-[11px] font-semibold px-2.5 py-1"
                                              title="Telar confirmado vía {{ $t['fuente'] === 'programa' ? 'ReqProgramaTejido' : 'CatCodificados' }}">
                                            <i class="fa-solid fa-check"></i>
                                            OK
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
