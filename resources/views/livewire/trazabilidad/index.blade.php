<div id="trazabilidad-livewire" class="space-y-4" data-trazabilidad-livewire>
    <flux:card size="sm" class="!p-2">
        <form wire:submit.prevent class="flex flex-col gap-2 min-[900px]:flex-row min-[900px]:items-center">
            <div class="grid flex-1 grid-cols-1 gap-2 sm:grid-cols-3">
                {{-- Desde 900 px, etiqueta a la izquierda y una sola fila. Más angosto (iPad vertical) no cabe:
                     la etiqueta va arriba y Restablecer baja a su propia fila. --}}
                <div class="flex min-w-0 flex-col gap-1 min-[900px]:flex-row min-[900px]:items-center min-[900px]:gap-2">
                    <flux:label for="filtro-flog" class="shrink-0">Flog</flux:label>
                    {{-- Solo viaja el Flog seleccionado: el resto se busca por AJAX. --}}
                    <select id="filtro-flog" class="filtro-select"
                            data-livewire-filter="flog"
                            data-remote-url="{{ route('trazabilidad.opciones.flog') }}">
                        <option value="">Todos</option>
                        @if ($flog !== '')
                            <option value="{{ $flog }}" selected>{{ $flog }}</option>
                        @endif
                    </select>
                </div>

                <div class="flex min-w-0 flex-col gap-1 min-[900px]:flex-row min-[900px]:items-center min-[900px]:gap-2">
                    <flux:label for="filtro-articulo" class="shrink-0">Artículo</flux:label>
                    <select id="filtro-articulo" class="filtro-select" data-livewire-filter="articulo">
                        <option value="">Todos</option>
                        @foreach ($opcionesArticulo as $option)
                            <option value="{{ $option['codigo'] }}" @selected($articulo === (string) $option['codigo'])>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex min-w-0 flex-col gap-1 min-[900px]:flex-row min-[900px]:items-center min-[900px]:gap-2">
                    <flux:label for="filtro-tamano" class="shrink-0">Tamaño</flux:label>
                    <select id="filtro-tamano" class="filtro-select" data-livewire-filter="tamano">
                        <option value="">Todos</option>
                        @foreach ($opcionesTamano as $option)
                            <option value="{{ $option }}" @selected($tamano === (string) $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <flux:button type="button" wire:click="restablecer" variant="ghost" icon="arrow-uturn-left"
                         class="min-h-touch self-end min-[900px]:self-auto" :disabled="! $hayFiltro">
                Restablecer
            </flux:button>
        </form>
    </flux:card>

    {{-- Sin target: los filtros llegan por evento, no por una acción con nombre. --}}
    <div class="relative">
        <div wire:loading.block class="traza-progreso hidden" role="status">
            <span class="sr-only">Actualizando trazabilidad…</span>
        </div>

        <div id="resultado-resumen-livewire" class="transition-opacity" wire:loading.class="opacity-50 pointer-events-none">
            @include('modulos.trazabilidad._resultado')
        </div>
    </div>
</div>
