{{-- Panel /admin · Errores agrupados por huella (MON-25), al estilo de una lista de incidencias. --}}
<div class="space-y-4">
    {{-- Filtros --}}
    <div class="grid grid-cols-1 items-center gap-2 sm:flex sm:flex-wrap">
        <div class="sm:w-80">
            <flux:input wire:model.live.debounce.300ms="buscar" icon="magnifying-glass" size="sm" clearable
                        placeholder="Buscar clase, mensaje, ruta o archivo" aria-label="Buscar error" />
        </div>
        <flux:select wire:model.live="estado" size="sm" aria-label="Estado" class="sm:w-56">
            <flux:select.option value="abiertos">Abiertos (nuevo y visto)</flux:select.option>
            <flux:select.option value="todos">Todos los estados</flux:select.option>
            @foreach ($estados as $e)
                <flux:select.option :value="$e">{{ ucfirst($e) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="origen" size="sm" aria-label="Origen" class="sm:w-48">
            <flux:select.option value="">Todos los orígenes</flux:select.option>
            @foreach ($origenes as $o)
                <flux:select.option :value="$o">{{ $o }}</flux:select.option>
            @endforeach
        </flux:select>
        <span wire:loading.delay class="text-caption text-(--adm-ink-3) sm:ms-auto">Actualizando…</span>
    </div>

    <div class="tabla-pantalla overflow-hidden border">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Error</flux:table.column>
                <flux:table.column class="hidden w-36 md:table-cell">Últimas 24 h</flux:table.column>
                @foreach ([['Ocurrencias', 'Veces', 'hidden w-24 sm:table-cell'], ['UltimaVez', 'Última vez', 'w-20 sm:w-28']] as [$campo, $titulo, $clase])
                    <flux:table.column align="end" sortable :sorted="$ordenPor === $campo" :direction="$ordenDir"
                                       wire:click="ordenar('{{ $campo }}')" :class="$clase">{{ $titulo }}</flux:table.column>
                @endforeach
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($filas as $e)
                    <flux:table.row :key="$e->Id" class="relative transition-colors hover:bg-(--adm-hover)">
                        <flux:table.cell class="max-w-0 py-3!">
                            <a href="{{ route('admin.errores.show', $e->Id) }}" class="block min-w-0 after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:rounded-lg focus-visible:after:ring-2 focus-visible:after:ring-inset focus-visible:after:ring-(--adm-accent)">
                                <span class="flex min-w-0 items-center gap-2">
                                    @include('livewire.admin.partials.estado-punto', ['estado' => $e->Estado])
                                    <span class="truncate font-semibold text-(--adm-ink)">{{ \Illuminate\Support\Str::afterLast((string) $e->Clase, '\\') }}</span>
                                </span>
                                <span class="mt-0.5 block truncate text-(--adm-ink-2)">{{ $e->Mensaje }}</span>
                                <span class="mt-0.5 block truncate text-caption text-(--adm-ink-3)">
                                    <span class="adm-mono">{{ $e->Origen }}</span>@if ($e->Ruta) · <span class="adm-mono">{{ $e->Ruta }}</span>@endif
                                    @if ($e->Archivo) · <span class="adm-mono">{{ \Illuminate\Support\Str::afterLast(str_replace('\\', '/', (string) $e->Archivo), '/') }}:{{ $e->Linea }}</span>@endif
                                </span>
                            </a>
                        </flux:table.cell>
                        <flux:table.cell class="hidden md:table-cell">
                            <x-admin.barras :valores="$barras[$e->Id] ?? array_fill(0, 24, 0)" class="text-(--adm-err)" />
                        </flux:table.cell>
                        <flux:table.cell align="end" class="hidden font-semibold text-(--adm-ink)! sm:table-cell">{{ number_format((int) $e->Ocurrencias) }}</flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap" title="{{ $e->UltimaVez?->format('d/m/Y H:i') }}">
                            {{ $e->UltimaVez?->locale('es')->diffForHumans(short: true) }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-14! text-center">
                            <flux:icon.check-circle class="mx-auto size-6 text-(--adm-ok)" />
                            <p class="mt-2 text-sm text-(--adm-ink-2)">Sin errores con esos filtros.</p>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div data-tabla-pie class="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-2.5 text-caption">
            <span>
                @if ($filas->total() > 0)
                    <span class="font-bold">{{ number_format($filas->firstItem()) }}–{{ number_format($filas->lastItem()) }}</span> de {{ number_format($filas->total()) }}
                @else
                    Sin registros
                @endif
            </span>
            {{ $filas->onEachSide(1)->links('components.tabla-paginacion') }}
        </div>
    </div>
</div>
