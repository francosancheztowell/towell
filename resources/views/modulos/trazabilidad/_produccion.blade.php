{{-- ============ Sección "Producción": tarjetas por telar ============
     Una tarjeta por telar (Localidad) del filtro actual. Programadas/Pzas-Día
     vienen del programa de tejido (ReqProgramaTejido / CatCodificados); Producidas
     y KG vienen de la trazabilidad (área "Crudo"). Si el telar de la orden no
     coincide con la Localidad —o la orden no se localiza— se marca la tarjeta. --}}

@php
    $prod = $produccion ?? ['telares' => [], 'resumen' => []];
    $telaresProd = $prod['telares'] ?? [];
    $resumen = $prod['resumen'] ?? ['telares' => 0, 'activos' => 0, 'programadas' => 0, 'producidas' => 0, 'kg' => 0, 'avance' => 0, 'alertas' => 0];
@endphp

@if (empty($telaresProd))
    <div class="bg-white border border-dashed border-slate-300 rounded-2xl p-10 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mb-4">
            <i class="fa-solid fa-industry text-slate-400 text-lg"></i>
        </div>
        <p class="text-slate-700 font-semibold">Sin telares para los filtros actuales</p>
        <p class="text-slate-400 text-sm mt-1">No se encontraron órdenes con telar en la trazabilidad de este filtro.</p>
    </div>
@else
    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-2 md:gap-3 mb-4">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-4 py-3">
            <div class="text-[11px] font-semibold text-slate-400 flex items-center gap-1"><i class="fa-solid fa-industry"></i> Telares</div>
            <div class="text-xl font-extrabold text-slate-700 tabular-nums">{{ number_format($resumen['telares']) }}</div>
            <div class="text-[10px] text-slate-400">{{ number_format($resumen['activos']) }} en producción</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-4 py-3">
            <div class="text-[11px] font-semibold text-slate-400 flex items-center gap-1"><i class="fa-solid fa-clipboard-list"></i> Programadas</div>
            <div class="text-xl font-extrabold text-slate-700 tabular-nums">{{ number_format($resumen['programadas']) }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-4 py-3">
            <div class="text-[11px] font-semibold text-slate-400 flex items-center gap-1"><i class="fa-solid fa-boxes-stacked"></i> Producidas</div>
            <div class="text-xl font-extrabold text-blue-700 tabular-nums">{{ number_format($resumen['producidas']) }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-4 py-3">
            <div class="text-[11px] font-semibold text-slate-400 flex items-center gap-1"><i class="fa-solid fa-weight-hanging"></i> KG Prod.</div>
            <div class="text-xl font-extrabold text-teal-700 tabular-nums">{{ number_format($resumen['kg'], 1) }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-4 py-3">
            <div class="text-[11px] font-semibold text-slate-400 flex items-center gap-1"><i class="fa-solid fa-gauge-high"></i> Avance Global</div>
            <div class="text-xl font-extrabold text-emerald-600 tabular-nums">{{ number_format($resumen['avance'], 1) }}%</div>
        </div>
        <div class="bg-white border rounded-2xl shadow-sm px-4 py-3 {{ $resumen['alertas'] > 0 ? 'border-amber-300 bg-amber-50' : 'border-slate-200' }}">
            <div class="text-[11px] font-semibold flex items-center gap-1 {{ $resumen['alertas'] > 0 ? 'text-amber-600' : 'text-slate-400' }}"><i class="fa-solid fa-triangle-exclamation"></i> Alertas</div>
            <div class="text-xl font-extrabold tabular-nums {{ $resumen['alertas'] > 0 ? 'text-amber-700' : 'text-slate-700' }}">{{ number_format($resumen['alertas']) }}</div>
        </div>
    </div>

    {{-- Banner de alerta global --}}
    @if ($resumen['alertas'] > 0)
        <div class="flex items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 mb-4">
            <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5"></i>
            <p class="text-xs text-amber-800 leading-relaxed">
                <strong>{{ $resumen['alertas'] }}</strong>
                {{ $resumen['alertas'] === 1 ? 'telar' : 'telares' }} con discrepancia: el telar de la orden
                (ReqProgramaTejido / CatCodificados) no coincide con la <strong>Localidad</strong> de
                trazabilidad, o la orden no se localizó. Revisa las tarjetas marcadas.
            </p>
        </div>
    @endif

    {{-- Tarjetas por telar --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
        @foreach ($telaresProd as $t)
            @php
                $avance = $t['avance'];
                $ancho = min(100, max(0, $avance));
                // Color de la barra según avance.
                if ($avance >= 50) { $barra = '#f59e0b'; }      // gold/amber alto
                elseif ($avance >= 25) { $barra = '#fb923c'; }  // naranja
                else { $barra = '#60a5fa'; }                    // azul (arranque)
                $hayAlerta = $t['alerta'];
            @endphp
            <div class="rounded-2xl border shadow-sm bg-white overflow-hidden flex flex-col
                        {{ $hayAlerta ? 'border-amber-300 ring-1 ring-amber-200' : 'border-slate-200' }}">
                {{-- Encabezado: telar + estado --}}
                <div class="flex items-center justify-between gap-2 px-4 pt-3 pb-2 border-b border-slate-100">
                    <span class="text-lg font-extrabold text-slate-800">{{ $t['telar'] }}</span>
                    <span class="flex items-center gap-1.5">
                        @if ($hayAlerta)
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5"
                                  title="El telar de la orden no coincide con la Localidad, o la orden no se localizó.">
                                <i class="fa-solid fa-triangle-exclamation"></i> Revisar
                            </span>
                        @endif
                        @if ($t['enProceso'])
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 text-[11px] font-semibold px-2.5 py-0.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> En Producción
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 text-slate-500 text-[11px] font-semibold px-2.5 py-0.5">
                                Programado
                            </span>
                        @endif
                    </span>
                </div>

                {{-- Stats 2x2 --}}
                <div class="grid grid-cols-2 gap-px bg-slate-100">
                    <div class="bg-white px-4 py-2.5">
                        <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Programadas</div>
                        <div class="text-base font-bold text-slate-700 tabular-nums">{{ number_format($t['programadas']) }}</div>
                    </div>
                    <div class="bg-white px-4 py-2.5">
                        <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Producidas</div>
                        <div class="text-base font-bold text-blue-700 tabular-nums">{{ number_format($t['producidas']) }}</div>
                    </div>
                    <div class="bg-white px-4 py-2.5">
                        <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">KG Prod.</div>
                        <div class="text-base font-bold text-teal-700 tabular-nums">{{ number_format($t['kg'], 2) }}</div>
                    </div>
                    <div class="bg-white px-4 py-2.5">
                        <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Pzas/Día</div>
                        <div class="text-base font-bold text-slate-700 tabular-nums">
                            {{ $t['pzasDia'] !== null ? number_format($t['pzasDia']) : '—' }}
                        </div>
                    </div>
                </div>

                {{-- Avance --}}
                <div class="px-4 pt-2.5 pb-3 mt-auto">
                    <div class="flex items-center justify-between text-[11px] mb-1">
                        <span class="text-slate-500">Avance: <b class="text-slate-700">{{ number_format($avance, 1) }}%</b></span>
                        <span class="text-slate-400 tabular-nums">{{ number_format($t['producidas']) }} / {{ number_format($t['programadas']) }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full rounded-full" style="width: {{ $ancho }}%; background-color: {{ $barra }};"></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
