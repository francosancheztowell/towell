{{-- Detalle de producción: Crudo (por orden) + Rollos Teñido (por máquina). --}}
@php
    $crudo = $produccion['crudo'] ?? ['ordenes' => [], 'resumen' => []];
    $rollos = $produccion['rollosTenido'] ?? ['maquinas' => [], 'resumen' => ['maquinas' => 0, 'ordenes' => 0]];

    $ordenesCrudo = $crudo['ordenes'] ?? [];
    $resumenCrudo = $crudo['resumen'] ?? [];
    $maquinasRollos = $rollos['maquinas'] ?? [];
    $resumenRollos = $rollos['resumen'] ?? ['maquinas' => 0, 'ordenes' => 0];
    $filtrosCrudo = [
        ['todos', 'Todas', $resumenCrudo['ordenes'] ?? count($ordenesCrudo)],
        ['activo', 'Activas', $resumenCrudo['activos'] ?? 0],
        ['terminado', 'Terminadas', $resumenCrudo['terminados'] ?? 0],
    ];
@endphp

<div id="produccion-contenido" class="space-y-8">
    <section class="space-y-4" data-prod-crudo aria-labelledby="prod-titulo-crudo">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <flux:heading id="prod-titulo-crudo" size="lg" level="3">Crudo</flux:heading>
                @if (($resumenCrudo['alertas'] ?? 0) > 0)
                    <flux:badge size="sm" color="amber" icon="exclamation-triangle"
                                title="La trazabilidad registra piezas de esa orden en un telar distinto al del programa">
                        {{ $resumenCrudo['alertas'] }} con producción en otro telar
                    </flux:badge>
                @endif
            </div>

            @if (! empty($ordenesCrudo))
                <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar órdenes de crudo">
                    @foreach ($filtrosCrudo as [$valor, $etiqueta, $total])
                        <flux:button size="sm" variant="outline" class="min-h-touch" data-prod-filtro="{{ $valor }}"
                                     aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
                            {{ $etiqueta }} <span class="tabular-nums opacity-70">{{ $total }}</span>
                        </flux:button>
                    @endforeach
                </div>
            @endif
        </div>

        @if (empty($ordenesCrudo))
            <x-trazabilidad.vacio icono="cube" titulo="Sin órdenes de crudo" texto="No hay órdenes en la trazabilidad para estos filtros." />
        @else
            @include('modulos.trazabilidad._produccion_resumen_crudo', ['resumenCrudo' => $resumenCrudo])

            <p data-prod-sin-resultados class="hidden py-6 text-center text-sm text-zinc-500" role="status">
                Ninguna orden coincide con el filtro.
            </p>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                @foreach ($ordenesCrudo as $o)
                    @include('modulos.trazabilidad._produccion_crudo_card', ['o' => $o])
                @endforeach
            </div>
        @endif
    </section>

    <section class="space-y-4" aria-labelledby="prod-titulo-rollos-tenido">
        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <flux:heading id="prod-titulo-rollos-tenido" size="lg" level="3">Rollos Teñido</flux:heading>
            @if (! empty($maquinasRollos))
                <flux:text class="tabular-nums">
                    {{ $resumenRollos['maquinas'] ?? count($maquinasRollos) }} máquinas · {{ $resumenRollos['ordenes'] ?? 0 }} órdenes
                </flux:text>
            @endif
        </div>

        @if (empty($maquinasRollos))
            <x-trazabilidad.vacio icono="swatch" titulo="Sin rollos teñidos" texto="Ninguna máquina de teñido registra rollos para estos filtros." />
        @else
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($maquinasRollos as $m)
                    @include('modulos.trazabilidad._produccion_rollos_maquina_card', ['m' => $m])
                @endforeach
            </div>
        @endif
    </section>
</div>
