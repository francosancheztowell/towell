{{-- Panel /admin · Rendimiento por ruta (MON-26): p50/p95, últimos 7 días vs los 7 previos. --}}
@php
    $celda = function (array $metrica, int $umbral): array {
        $actual = $metrica['actual'] ?? null;
        $previa = $metrica['previa'] ?? null;
        $delta = $actual && $previa && $previa['p95'] > 0 ? (int) round(100 * ($actual['p95'] - $previa['p95']) / $previa['p95']) : null;

        return [$actual, $previa, $delta, $actual && $actual['p95'] > $umbral];
    };
    $grupos = ['ServidorMs' => 'Servidor', 'CargaMs' => 'Carga en navegador'];
@endphp
<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.300ms="buscar" icon="magnifying-glass" size="sm" clearable placeholder="Buscar ruta" aria-label="Buscar ruta" />
        </div>
        <flux:switch wire:model.live="soloLentas" label="Solo lentas" align="left" />
        <span class="text-caption text-(--adm-ink-3)">p95 sobre {{ number_format($umbrales['ServidorMs']) }} ms en servidor o {{ number_format($umbrales['CargaMs']) }} ms en carga</span>
        <span class="ms-auto text-caption text-(--adm-ink-3)">Calculado {{ $calculadoEn }}</span>
        <flux:button size="sm" icon="arrow-path" wire:click="recalcular" wire:target="recalcular">Recalcular</flux:button>
        @if ($pulse)
            <flux:button size="sm" variant="ghost" icon="heart" :href="url(config('pulse.path'))">Pulse</flux:button>
        @endif
    </div>

    <div class="tabla-pantalla overflow-x-auto border">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Ruta</flux:table.column>
                @foreach ($grupos as $metrica => $titulo)
                    <flux:table.column class="w-56">{{ $titulo }} · p95</flux:table.column>
                    <flux:table.column align="end" class="hidden w-20 lg:table-cell">p50</flux:table.column>
                @endforeach
                <flux:table.column align="end" class="w-16">Vistas</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($filas as $fila)
                    <flux:table.row :key="'ruta-'.md5($fila['ruta'])">
                        <flux:table.cell class="max-w-0">
                            <span class="flex min-w-0 items-center gap-2">
                                @if ($fila['lenta'])
                                    <flux:icon.exclamation-triangle variant="micro" class="size-4 shrink-0 text-(--adm-err)" aria-label="Lenta" />
                                @endif
                                <span class="adm-mono truncate text-(--adm-ink)" title="{{ $fila['ruta'] }}">{{ $fila['ruta'] }}</span>
                            </span>
                        </flux:table.cell>
                        @foreach ($grupos as $metrica => $titulo)
                            @php
                                [$actual, $previa, $delta, $excede] = $celda($fila[$metrica], $umbrales[$metrica]);
                                $ancho = $actual ? min(100, round(50 * $actual['p95'] / max(1, $umbrales[$metrica]))) : 0;
                            @endphp
                            <flux:table.cell>
                                @if ($actual)
                                    <span class="flex items-baseline justify-between gap-2">
                                        <span @class(['font-semibold tabular-nums', 'text-(--adm-err)' => $excede, 'text-(--adm-ink)' => ! $excede])>{{ number_format($actual['p95']) }} ms</span>
                                        @if ($delta !== null)
                                            <span @class(['text-caption tabular-nums', 'text-(--adm-err)' => $delta > 10, 'text-(--adm-ok)' => $delta < -10, 'text-(--adm-ink-3)' => abs($delta) <= 10])>{{ $delta > 0 ? '+' : '' }}{{ $delta }}%</span>
                                        @else
                                            <span class="text-caption text-(--adm-ink-3)">nuevo</span>
                                        @endif
                                    </span>
                                    {{-- Barra contra el umbral: la marca a la mitad es el umbral. --}}
                                    <span class="relative mt-1.5 block h-1 rounded-full bg-(--adm-hover)" aria-hidden="true">
                                        <span @class(['absolute inset-y-0 start-0 rounded-full', 'bg-(--adm-err)' => $excede, 'bg-(--adm-ink-3)' => ! $excede]) style="width: {{ $ancho }}%"></span>
                                        <span class="absolute -inset-y-0.5 start-1/2 w-px bg-(--adm-line-strong)"></span>
                                    </span>
                                @else
                                    <span class="text-(--adm-ink-3)">—</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end" class="hidden tabular-nums lg:table-cell">{{ $actual ? number_format($actual['p50']) : '—' }}</flux:table.cell>
                        @endforeach
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format($fila['ServidorMs']['actual']['n'] ?? $fila['CargaMs']['actual']['n'] ?? 0) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-14! text-center text-sm text-(--adm-ink-2)">Sin vistas medidas en los últimos 7 días.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    <p class="text-caption text-(--adm-ink-3)">Percentil de rango más cercano sobre SYSMonVista, últimos 7 días contra los 7 previos. La marca de cada barra es el umbral.</p>
</div>
