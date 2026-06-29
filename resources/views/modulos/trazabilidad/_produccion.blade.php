{{-- ============ Sección "Producción": tarjetas por orden ============
     Una tarjeta por orden (telar canónico en ReqProgramaTejido / CatCodificados).
     Switch JS (Todos / Activo / Terminado) filtra las cards sin recargar. --}}

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
    {{-- Barra superior: conteos + switch --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2 text-sm">
            <span class="font-semibold text-slate-700">{{ count($ordenCards) }} órdenes</span>
            @if (($resumen['activos'] ?? 0) > 0)
                <span class="text-[11px] bg-emerald-100 text-emerald-700 rounded-full px-2 py-0.5 font-bold">
                    {{ $resumen['activos'] }} activas
                </span>
            @endif
            @if (($resumen['terminados'] ?? 0) > 0)
                <span class="text-[11px] bg-slate-200 text-slate-600 rounded-full px-2 py-0.5 font-bold">
                    {{ $resumen['terminados'] }} terminadas
                </span>
            @endif
            @if (($resumen['alertas'] ?? 0) > 0)
                <span class="text-[11px] bg-amber-100 text-amber-700 rounded-full px-2 py-0.5 font-bold">
                    <i class="fa-solid fa-triangle-exclamation"></i> {{ $resumen['alertas'] }} alertas
                </span>
            @endif
        </div>

        {{-- Switch: Todos / Activo / Terminado --}}
        <div class="prod-switch inline-flex items-center rounded-full bg-slate-100 p-0.5 gap-0.5 select-none">
            <button type="button" data-filter="todos"
                    class="prod-filter-btn rounded-full px-3.5 py-1 text-xs font-semibold bg-white shadow text-slate-700 transition-all">
                Todos
            </button>
            <button type="button" data-filter="activo"
                    class="prod-filter-btn rounded-full px-3.5 py-1 text-xs font-semibold text-slate-500 hover:text-slate-700 transition-all">
                <i class="fa-solid fa-gears mr-0.5"></i> Activo
            </button>
            <button type="button" data-filter="terminado"
                    class="prod-filter-btn rounded-full px-3.5 py-1 text-xs font-semibold text-slate-500 hover:text-slate-700 transition-all">
                <i class="fa-solid fa-flag-checkered mr-0.5"></i> Terminado
            </button>
        </div>
    </div>

    {{-- Grid de tarjetas por orden --}}
    <div class="prod-cards-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
        @foreach ($ordenCards as $o)
            @php
                $avance = $o['avance'];
                $ancho  = min(100, max(0, $avance));
                if ($avance >= 50)      { $barra = '#f59e0b'; }
                elseif ($avance >= 25)  { $barra = '#fb923c'; }
                else                    { $barra = '#60a5fa'; }
            @endphp
            <div class="prod-card rounded-2xl border shadow-sm bg-white overflow-hidden flex flex-col
                        {{ $o['alerta'] ? 'border-amber-300 ring-1 ring-amber-200' : 'border-slate-200' }}"
                 data-estado="{{ $o['estado'] }}">

                {{-- Header: número de orden + badges de estado --}}
                <div class="px-4 pt-3 pb-2 border-b border-slate-100">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-wide leading-none mb-0.5">Orden</div>
                            <div class="text-2xl font-extrabold text-slate-800 font-mono leading-tight">{{ $o['orden'] }}</div>
                        </div>
                        <div class="flex flex-col items-end gap-1 pt-0.5">
                            @if ($o['alerta'])
                                @php
                                    $conflicto = implode(', ', $o['localidadesConflicto'] ?? []);
                                    $tituloAlerta = $conflicto !== ''
                                        ? 'Trazabilidad con producción en otro telar: '.$conflicto.'. Telar del programa: '.$o['telar'].'.'
                                        : 'El telar de la orden no coincide con la Localidad de trazabilidad.';
                                @endphp
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5"
                                      title="{{ $tituloAlerta }}">
                                    <i class="fa-solid fa-triangle-exclamation"></i> Revisar
                                </span>
                            @endif
                            @if ($o['fuente'] === 'programa')
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2 py-0.5"
                                      title="Encontrada en ReqProgramaTejido">
                                    <i class="fa-solid fa-gears"></i> Programado
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-200 text-slate-600 text-[10px] font-bold px-2 py-0.5"
                                      title="Encontrada en CatCodificados">
                                    <i class="fa-solid fa-flag-checkered"></i> Finalizado
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Telar + indicador En Producción --}}
                    <div class="flex items-center gap-1.5 mt-2">
                        <i class="fa-solid fa-industry text-slate-400 text-[11px]"></i>
                        <span class="text-sm font-bold text-slate-600">{{ $o['telar'] }}</span>
                        @if ($o['enProceso'])
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-semibold px-1.5 py-0.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> En Producción
                            </span>
                        @endif
                    </div>

                    {{-- Badges de mes --}}
                    @if (!empty($o['meses']))
                        <div class="flex flex-wrap gap-1 mt-1.5">
                            @foreach ($o['meses'] as $mes)
                                <span class="inline-flex items-center rounded-md bg-indigo-100 text-indigo-700 text-[10px] font-bold px-1.5 py-0.5">
                                    {{ $mes }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Stats 2×2 --}}
                <div class="grid grid-cols-2 gap-px bg-slate-100">
                    <div class="bg-white px-4 py-2.5">
                        <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Programadas</div>
                        <div class="text-base font-bold text-slate-700 tabular-nums">{{ number_format($o['programadas']) }}</div>
                    </div>
                    <div class="bg-white px-4 py-2.5">
                        <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Producidas</div>
                        <div class="text-base font-bold text-blue-700 tabular-nums">{{ number_format($o['producidas']) }}</div>
                    </div>
                    <div class="bg-white px-4 py-2.5">
                        <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">KG Prod.</div>
                        <div class="text-base font-bold text-teal-700 tabular-nums">{{ number_format($o['kg'], 2) }}</div>
                    </div>
                    <div class="bg-white px-4 py-2.5">
                        <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Pzas/Día</div>
                        <div class="text-base font-bold text-slate-700 tabular-nums">
                            {{ $o['pzasDia'] !== null ? number_format($o['pzasDia']) : '—' }}
                        </div>
                    </div>
                </div>

                {{-- Barra de avance --}}
                <div class="px-4 pt-2.5 pb-3 mt-auto">
                    <div class="flex items-center justify-between text-[11px] mb-1">
                        <span class="text-slate-500">Avance: <b class="text-slate-700">{{ number_format($avance, 1) }}%</b></span>
                        <span class="text-slate-400 tabular-nums">{{ number_format($o['producidas']) }} / {{ number_format($o['programadas']) }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full rounded-full" style="width: {{ $ancho }}%; background-color: {{ $barra }};"></div>
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

                // Actualizar botón activo
                btns.forEach(function (b) {
                    b.classList.remove('bg-white', 'shadow', 'text-slate-700');
                    b.classList.add('text-slate-500');
                });
                this.classList.add('bg-white', 'shadow', 'text-slate-700');
                this.classList.remove('text-slate-500');

                // Mostrar/ocultar tarjetas
                cards.forEach(function (card) {
                    var visible = filter === 'todos' || card.getAttribute('data-estado') === filter;
                    card.style.display = visible ? '' : 'none';
                });
            });
        });
    })();
    </script>
@endif
