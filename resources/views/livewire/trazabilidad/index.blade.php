<div id="trazabilidad-livewire" class="space-y-4" data-trazabilidad-livewire>
    @php
        // Lo elegido que no viene en la lista (rangos, comodines, Flogs de la búsqueda remota)
        // va como <option> propia: Tom Select solo muestra lo que existe en el <select>.
        $valoresAx = static fn (string $texto): array => \App\Support\FiltroAx::valores($texto);
        $codigosArticulo = collect($opcionesArticulo)->pluck('codigo')->map(fn ($c) => mb_strtolower((string) $c))->all();
        $codigosTamano = collect($opcionesTamano)->map(fn ($t) => mb_strtolower((string) $t))->all();
        $enLista = static fn (string $valor, array $lista): bool => in_array(mb_strtolower($valor), $lista, true);
    @endphp
    <flux:card size="sm" class="!p-2">
        <form wire:submit.prevent class="flex flex-col gap-2 lg:flex-row lg:items-end">
            {{-- Una fila desde 1024 px (Flog, Cliente, Artículo más anchos; Tamaño, Año, Mes angostos).
                 Flog, Artículo, Tamaño y Cliente son múltiples; Flog, Artículo y Tamaño aceptan
                 la sintaxis de AX al escribir o pegar: 7166,7167 · 7160..7170 · 71* --}}
            <div class="grid min-w-0 flex-1 grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-[repeat(3,minmax(0,1.5fr))_repeat(3,minmax(0,1fr))]">
                <div class="flex min-w-0 flex-col gap-1">
                    <flux:label for="filtro-flog">Flog</flux:label>
                    {{-- Solo viajan los Flogs seleccionados: el resto se busca por AJAX. --}}
                    <select id="filtro-flog" class="filtro-select" multiple data-ax
                            data-livewire-filter="flog"
                            data-remote-url="{{ route('trazabilidad.opciones.flog') }}">
                        @foreach ($valoresAx($flog) as $valor)
                            <option value="{{ $valor }}" selected>{{ $valor }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex min-w-0 flex-col gap-1">
                    <flux:label for="filtro-cliente">Cliente</flux:label>
                    <select id="filtro-cliente" class="filtro-select" multiple data-livewire-filter="cliente">
                        @foreach ($opcionesCliente as $option)
                            <option value="{{ $option }}" @selected(in_array((string) $option, $cliente, true))>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex min-w-0 flex-col gap-1">
                    <flux:label for="filtro-articulo">Artículo</flux:label>
                    <select id="filtro-articulo" class="filtro-select" multiple data-ax data-livewire-filter="articulo">
                        @foreach ($valoresAx($articulo) as $valor)
                            @unless ($enLista($valor, $codigosArticulo))
                                <option value="{{ $valor }}" selected>{{ $valor }}</option>
                            @endunless
                        @endforeach
                        @foreach ($opcionesArticulo as $option)
                            <option value="{{ $option['codigo'] }}" @selected($enLista((string) $option['codigo'], array_map('mb_strtolower', $valoresAx($articulo))))>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex min-w-0 flex-col gap-1">
                    <flux:label for="filtro-tamano">Tamaño</flux:label>
                    <select id="filtro-tamano" class="filtro-select" multiple data-ax data-livewire-filter="tamano">
                        @foreach ($valoresAx($tamano) as $valor)
                            @unless ($enLista($valor, $codigosTamano))
                                <option value="{{ $valor }}" selected>{{ $valor }}</option>
                            @endunless
                        @endforeach
                        @foreach ($opcionesTamano as $option)
                            <option value="{{ $option }}" @selected($enLista((string) $option, array_map('mb_strtolower', $valoresAx($tamano))))>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex min-w-0 flex-col gap-1">
                    <flux:label for="filtro-anio">Año</flux:label>
                    <select id="filtro-anio" class="filtro-select" data-livewire-filter="anio">
                        <option value="">Todos</option>
                        @foreach ($opcionesAnio as $option)
                            <option value="{{ $option }}" @selected($anio === (string) $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex min-w-0 flex-col gap-1">
                    <flux:label for="filtro-mes">Mes</flux:label>
                    {{-- Mes solo con año: sin año no hay un periodo que acotar. --}}
                    <select id="filtro-mes" class="filtro-select" data-livewire-filter="mes" @if ($anio === '') data-bloqueado @endif
                            @if ($anio === '') title="Elige un año primero" @endif>
                        <option value="">{{ $anio === '' ? 'Elige año' : 'Todos' }}</option>
                        @foreach ($meses as $numero => $nombre)
                            <option value="{{ $numero + 1 }}" @selected($mes === (string) ($numero + 1))>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <flux:button type="button" wire:click="restablecer" variant="ghost" icon="arrow-uturn-left"
                         class="min-h-touch self-end lg:self-auto" :disabled="! $hayFiltro">
                Restablecer
            </flux:button>
        </form>
    </flux:card>

    {{-- Sin target: los filtros llegan por evento, no por una acción con nombre. --}}
    <div class="relative">
        <div wire:loading.delay.block class="traza-progreso hidden" role="status">
            <span class="sr-only">Actualizando trazabilidad…</span>
        </div>

        <div id="resultado-resumen-livewire" class="transition-opacity" wire:loading.delay.class="opacity-50 pointer-events-none">
            @include('modulos.trazabilidad._resultado')
        </div>
    </div>
</div>
