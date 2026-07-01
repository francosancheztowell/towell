{{-- Tarjetas producción: Crudo + Rollos Teñido --}}

@php
    $crudo = $produccion['crudo'] ?? ($produccion ?? ['ordenes' => [], 'noEncontradas' => [], 'resumen' => []]);
    $rollos = $produccion['rollosTenido'] ?? ['ordenes' => [], 'resumen' => ['ordenes' => 0]];

    $ordenCardsCrudo = $crudo['ordenes'] ?? [];
    $resumenCrudo = $crudo['resumen'] ?? [];
    $ordenCardsRollos = $rollos['ordenes'] ?? [];
    $resumenRollos = $rollos['resumen'] ?? ['ordenes' => 0];

    $soloFecha = function (?string $fecha): ?string {
        if (blank($fecha)) {
            return null;
        }
        $partes = preg_split('/\s+/', trim($fecha), 2);

        return $partes[0] ?? trim($fecha);
    };
@endphp

{{-- ===== Crudo ===== --}}
<section class="prod-area prod-area--crudo mb-10" aria-labelledby="prod-titulo-crudo">
    <h3 id="prod-titulo-crudo" class="text-lg md:text-xl font-bold text-slate-800 mb-4">
        Crudo
    </h3>

    @if (empty($ordenCardsCrudo))
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
                <span class="font-semibold text-slate-700">{{ $resumenCrudo['ordenes'] ?? count($ordenCardsCrudo) }} órdenes</span>
                @if (($resumenCrudo['activos'] ?? 0) > 0)
                    <span class="text-[11px] bg-emerald-50 text-emerald-700 rounded-full px-2 py-0.5 font-medium">
                        {{ $resumenCrudo['activos'] }} Activas
                    </span>
                @endif
                @if (($resumenCrudo['terminados'] ?? 0) > 0)
                    <span class="text-[11px] bg-slate-100 text-slate-600 rounded-full px-2 py-0.5 font-medium">
                        {{ $resumenCrudo['terminados'] }} Terminadas
                    </span>
                @endif
                @if (($resumenCrudo['alertas'] ?? 0) > 0)
                    <span class="text-[11px] bg-amber-50 text-amber-700 rounded-full px-2 py-0.5 font-medium"
                          title="La trazabilidad registra piezas de esa orden en un telar distinto al del programa">
                        {{ $resumenCrudo['alertas'] }} Con prod. en otro telar
                    </span>
                @endif
            </div>

            <div class="prod-segment select-none shrink-0 self-start sm:self-auto" role="group" aria-label="Filtrar órdenes de crudo">
                <button type="button" data-filter="todos"
                        class="prod-filter-btn prod-segment__btn is-active">
                    <span>Todos</span>
                    <span class="prod-segment__count">{{ $resumenCrudo['ordenes'] ?? count($ordenCardsCrudo) }}</span>
                </button>
                <button type="button" data-filter="activo"
                        class="prod-filter-btn prod-segment__btn prod-segment__btn--activo">
                    <span>Activo</span>
                    <span class="prod-segment__count">{{ $resumenCrudo['activos'] ?? 0 }}</span>
                </button>
                <button type="button" data-filter="terminado"
                        class="prod-filter-btn prod-segment__btn prod-segment__btn--terminado">
                    <span>Terminado</span>
                    <span class="prod-segment__count">{{ $resumenCrudo['terminados'] ?? 0 }}</span>
                </button>
            </div>
        </div>

        <p class="prod-sin-resultados hidden text-center text-sm text-slate-500 py-6 mb-2">
            Ninguna orden coincide con el filtro seleccionado.
        </p>

        <div class="prod-cards-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 items-start">
            @foreach (collect($ordenCardsCrudo)->groupBy('grupoKey') as $grupo)
                @if ($grupo->count() > 1)
                    @php $estadoGrupo = $grupo->firstWhere('esOtroTelar', false)['estado'] ?? $grupo->first()['estado']; @endphp
                    <div class="prod-card-grupo sm:col-span-2 xl:col-span-2" data-estado="{{ $estadoGrupo }}">
                        @foreach ($grupo->sortBy(fn ($c) => ($c['esOtroTelar'] ?? false) ? 1 : 0) as $o)
                            @include('modulos.trazabilidad._produccion_card', ['o' => $o, 'modo' => 'crudo', 'soloFecha' => $soloFecha])
                        @endforeach
                    </div>
                @else
                    @include('modulos.trazabilidad._produccion_card', ['o' => $grupo->first(), 'modo' => 'crudo', 'soloFecha' => $soloFecha])
                @endif
            @endforeach
        </div>
    @endif
</section>

{{-- ===== Rollos Teñido ===== --}}
<section class="prod-area prod-area--rollos-tenido" aria-labelledby="prod-titulo-rollos-tenido">
    <h3 id="prod-titulo-rollos-tenido" class="text-lg md:text-xl font-bold text-slate-800 mb-3">
        Rollos Teñido
    </h3>

    @include('modulos.trazabilidad._produccion_filtro_tenido', [
        'opcionesNombrecolorTenido' => $opcionesNombrecolorTenido ?? collect(),
        'filtros' => $filtros ?? [],
    ])

    @php
        $nombresColorSel = collect(explode('|', (string) ($filtros['nombrecolor'] ?? '')))
            ->map(fn ($v) => trim($v))->filter()->values()->all();
    @endphp

    @if (empty($ordenCardsRollos))
        <div class="bg-white border border-dashed border-slate-300 rounded-2xl p-10 text-center">
            <div class="mx-auto w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center mb-4">
                <i class="fa-solid fa-scroll text-blue-400 text-lg"></i>
            </div>
            @if (! empty($nombresColorSel))
                <p class="text-slate-700 font-semibold">Sin rollos para los colores seleccionados</p>
                <p class="text-slate-400 text-sm mt-1">Prueba quitando colores del filtro o elige otros.</p>
            @else
                <p class="text-slate-700 font-semibold">Sin rollos teñidos para los filtros actuales</p>
                <p class="text-slate-400 text-sm mt-1">No hay registros en TrazaProduccion con área Rollos Teñido.</p>
            @endif
        </div>
    @else
        <div class="flex flex-wrap items-center gap-2 text-sm text-slate-600 mb-4">
            <span class="font-semibold text-slate-700">{{ count($ordenCardsRollos) }} registros</span>
        </div>

        <div class="prod-cards-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 items-start">
            @foreach ($ordenCardsRollos as $o)
                @include('modulos.trazabilidad._produccion_card', ['o' => $o, 'modo' => 'rollos', 'soloFecha' => $soloFecha])
            @endforeach
        </div>
    @endif
</section>
