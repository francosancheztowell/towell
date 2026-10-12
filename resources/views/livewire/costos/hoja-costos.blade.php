@php
    // Clases completas por tono: Tailwind solo ve literales en Blade/JS (no escanea app/).
    $tonos = [
        'blue' => ['titulo' => 'text-blue-700', 'barra' => 'bg-blue-700', 'borde' => 'border-blue-300', 'fondo' => 'bg-blue-50', 'encabezado' => 'bg-blue-100! text-blue-800! border-blue-200!', 'acento' => 'bg-blue-200! text-blue-800! border-blue-200!'],
        'amber' => ['titulo' => 'text-amber-800', 'barra' => 'bg-amber-700', 'borde' => 'border-amber-300', 'fondo' => 'bg-amber-50', 'encabezado' => 'bg-amber-100! text-amber-900! border-amber-200!', 'acento' => 'bg-amber-200! text-amber-900! border-amber-200!'],
        'emerald' => ['titulo' => 'text-emerald-800', 'barra' => 'bg-emerald-700', 'borde' => 'border-emerald-300', 'fondo' => 'bg-emerald-50', 'encabezado' => 'bg-emerald-100! text-emerald-900! border-emerald-200!', 'acento' => 'bg-emerald-200! text-emerald-900! border-emerald-200!'],
        'rose' => ['titulo' => 'text-rose-800', 'barra' => 'bg-rose-700', 'borde' => 'border-rose-300', 'fondo' => 'bg-rose-50', 'encabezado' => 'bg-rose-100! text-rose-900! border-rose-200!', 'acento' => 'bg-rose-200! text-rose-900! border-rose-200!'],
    ];
@endphp
{{-- filtros: sidebar visible u oculto en escritorio (lg+); en tablet/móvil el mismo botón abre el bottom sheet.
     fijo: indicadores pegados arriba al hacer scroll; se recuerda por navegador (si localStorage falla,
     solo arranca suelto). abrirFiltros() decide por ancho. Nada de esto viaja al servidor. --}}
<div class="flex min-h-full flex-col bg-zinc-50"
     x-data="{ filtros: true,
               abrirFiltros() { window.matchMedia('(min-width: 1024px)').matches ? this.filtros = !this.filtros : this.$flux.modal('hoja-filtros').show() },
               fijo: (() => { try { return localStorage.getItem('hoja-costos-fijo') === '1' } catch (e) { return false } })() }"
     x-effect="(() => { try { localStorage.setItem('hoja-costos-fijo', fijo ? '1' : '0') } catch (e) {} })()">
    {{-- Costeo en el navbar (en móvil, la fila de acciones que el navbar pone debajo). --}}
    @teleport('#hoja-navbar-acciones')
    <div class="-mt-1 flex items-center gap-2">
        <flux:radio.group wire:model.live="costeo" variant="segmented" aria-label="Método de costeo" class="h-11!">
            <flux:radio value="directo" label="Directo" title="Sin costos fijos" class="min-h-touch data-checked:bg-blue-600! data-checked:text-white!" />
            <flux:radio value="absorbente" label="Absorbente" title="Todos los costos" class="min-h-touch data-checked:bg-blue-600! data-checked:text-white!" />
        </flux:radio.group>
    </div>
    @endteleport

    {{-- Filtros + pestañas. --}}
    <div class="sticky top-0 z-10 flex h-15 items-center gap-2 bg-slate-800 px-2 md:px-6">
        <flux:tooltip position="bottom">
            <flux:button size="sm" icon="adjustments-horizontal" variant="ghost" x-on:click="abrirFiltros()"
                         aria-label="Filtros" class="min-h-touch shrink-0 text-white! hover:bg-white/10!"><span class="lg:hidden">Filtros</span></flux:button>
            <flux:tooltip.content>
                <span class="hidden lg:inline" x-text="filtros ? 'Ocultar filtros' : 'Mostrar filtros'">Mostrar u ocultar filtros</span>
                <span class="lg:hidden">Abrir filtros</span>
                <span class="block text-xs opacity-75">Período, código, tamaño, composición y telar</span>
            </flux:tooltip.content>
        </flux:tooltip>
        {{-- Carril de pestañas: en móvil se desliza de lado en vez de envolver (la barra mide siempre h-15); el
             desvanecido de la derecha avisa que hay más. Desglose/Comparativo/Listado aún no existen: se ven
             desactivadas con "Pronto". Cuotas es otra pantalla: va aparte y con ↗. --}}
        <nav aria-label="Vistas de la hoja" class="flex min-w-0 flex-1 items-center gap-1 overflow-x-auto pe-6 [scrollbar-width:none] max-lg:[mask-image:linear-gradient(to_right,black_calc(100%-2rem),transparent)]">
            <flux:button size="sm" variant="filled" aria-current="page" class="min-h-touch shrink-0 rounded-full! bg-white! px-4! text-slate-900!">Artículo</flux:button>
            @foreach (['Desglose', 'Comparativo', 'Listado'] as $texto)
                <flux:button size="sm" variant="ghost" disabled class="min-h-touch shrink-0 rounded-full! px-3! text-white/60! opacity-100!">
                    {{ $texto }} <flux:badge size="sm" class="ms-1 bg-white/15! text-white/80!">Pronto</flux:badge>
                </flux:button>
            @endforeach
            <flux:separator vertical class="mx-1 my-3 bg-white/25!" />
            <flux:button :href="route('costos.cuotas')" variant="ghost" size="sm" icon:trailing="arrow-up-right"
                         title="Abre la pantalla de cuotas" class="min-h-touch shrink-0 rounded-full! px-4! text-white! hover:bg-white/10!">Cuotas</flux:button>
        </nav>

        {{-- Pin: inclinado = suelto, derecho y azul = indicadores fijos. --}}
        <flux:tooltip position="bottom" class="shrink-0">
            <flux:button size="sm" square x-on:click="fijo = !fijo" x-bind:aria-pressed="fijo" aria-label="Fijar indicadores"
                         x-bind:class="fijo ? 'bg-blue-600! text-white!' : 'text-white! hover:bg-white/10!'"
                         variant="ghost" class="size-touch! rounded-full!">
                <i class="fa-solid fa-thumbtack transition-transform" x-bind:class="fijo || 'rotate-45'" aria-hidden="true"></i>
            </flux:button>
            <flux:tooltip.content>
                <span x-text="fijo ? 'Soltar indicadores' : 'Fijar indicadores'">Fijar indicadores</span>
                <span class="block text-xs opacity-75">Mantiene los costos y el lote arriba al hacer scroll</span>
            </flux:tooltip.content>
        </flux:tooltip>
    </div>

    <div class="flex flex-1 flex-col lg:flex-row" wire:loading.class="opacity-60" wire:target="costeo">
        {{-- Filtros en escritorio (lg+): sidebar pegado con su propio scroll. Abajo de lg van en el bottom sheet. --}}
        <aside id="hoja-filtros" x-show="filtros" x-transition.opacity
               class="relative z-[6] hidden space-y-5 lg:block border-blue-200 bg-gradient-to-b from-blue-100/80 via-blue-50/40 to-blue-100/60 p-4 shadow-[6px_0_18px_-6px_rgb(30_64_175/0.35)] lg:sticky lg:top-15 lg:h-[calc(100dvh-var(--pt-navbar-height)-3.75rem)] lg:w-80 lg:shrink-0 lg:self-start lg:border-e-2 lg:overflow-y-auto">
            @include('livewire.costos.partials.hoja-costos-filtros')
        </aside>

        {{-- @container: las rejillas se acomodan al ancho del contenido (cambia al abrir/cerrar el sidebar), no al de la pantalla. --}}
        <div class="@container min-w-0 flex-1 space-y-6 p-4 md:p-6">
            {{-- Indicadores. Con el pin quedan pegados bajo la barra de pestañas (top-15 = su alto) y
                 compactos: solo título y valor, para no comerse la pantalla. --}}
            @php
                $k = $kpi;
                $operacion = $k['totalKg'] - $k['ventaKg'];
                $merma = 1 - $k['terminado'] / $k['crudo'];
            @endphp
            <h2 class="sr-only">Indicadores</h2>
            <div class="grid grid-cols-2 gap-2 sm:gap-3 @4xl:grid-cols-4"
                 x-bind:class="fijo && 'sticky top-15 z-[5] -mx-4 bg-zinc-50/95 px-4 py-2 shadow-md backdrop-blur md:-mx-6 md:px-6'">
                @foreach ([
                    ['Costo total', 'border-t-slate-800', '$'.number_format($k['totalKg'], 2), '/ kg', '$'.number_format($k['totalPza'], 2), '/ pza',
                        '$'.number_format($k['ventaKg'], 2).' venta + $'.number_format($operacion, 2).'/kg operación y financieros'],
                    ['Costo de venta', 'border-t-sky-600', '$'.number_format($k['ventaKg'], 2), '/ kg', '$'.number_format($k['ventaPza'], 2), '/ pza', 'Hasta PT / Almacén'],
                    ['Peso por pieza', 'border-t-orange-600', number_format($k['crudo'], 3), 'kg crudo', number_format($k['terminado'], 3), 'kg terminado',
                        'Merma '.number_format($merma * 100, 1).' % de crudo a terminado'],
                ] as [$titulo, $borde, $valor, $unidad, $sub, $subUnidad, $nota])
                    {{-- Fijas en móvil solo quedan Costo total y el lote (una fila); venta y peso se ocultan. --}}
                    <flux:card class="border-t-4 {{ $borde }}" x-bind:class="fijo ? 'px-3! py-2! sm:px-4!{{ $loop->first ? '' : ' max-sm:hidden' }}' : 'p-3! sm:p-4!'">
                        <flux:heading level="3">{{ $titulo }}</flux:heading>
                        <p class="tabular-nums"><span class="font-bold text-slate-900" x-bind:class="fijo ? 'text-lg sm:text-xl' : 'text-2xl sm:text-3xl'">{{ $valor }}</span> <span class="text-sm text-zinc-500">{{ $unidad }}</span></p>
                        <div x-show="!fijo">
                            <p class="text-sm tabular-nums"><span class="font-semibold">{{ $sub }}</span> {{ $subUnidad }}</p>
                            <flux:text size="sm" class="mt-2 tabular-nums">{{ $nota }}</flux:text>
                        </div>
                    </flux:card>
                @endforeach

                {{-- Lote: piezas → costo y peso del lote, sin ida al servidor. --}}
                <flux:card class="border-t-4 border-t-blue-600 bg-blue-50/50!" x-bind:class="fijo ? 'px-3! py-2! sm:px-4!' : 'p-3! sm:p-4!'" x-data="{ pzas: 1 }">
                    <flux:heading level="3">Costo del lote</flux:heading>
                    {{-- Mismo orden que las otras tarjetas: el valor grande arriba y el detalle abajo (en móvil cabe sin partirse). --}}
                    <p class="font-bold tabular-nums text-blue-700" x-bind:class="fijo ? 'text-lg sm:text-xl' : 'text-2xl sm:text-3xl'"
                       x-text="'$' + ((pzas || 0) * {{ $k['totalPza'] }}).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })">${{ number_format($k['totalPza'], 2) }}</p>
                    <div class="flex min-w-0 items-center gap-2 text-sm tabular-nums">
                        {{-- Solo enteros ≥ 0: lo que no sea dígito se borra al teclear o pegar (sin "-", "." ni "e"). --}}
                        <flux:input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="7" size="sm" value="1"
                                    x-on:input="$el.value = $el.value.replace(/\D/g, ''); pzas = Number($el.value)" aria-label="Piezas del lote" class="max-w-20 shrink-0" input:class="font-bold tabular-nums" />
                        <span class="text-zinc-500">pzas</span>
                        <span x-show="!fijo" class="min-w-0 truncate">· <span class="font-semibold" x-text="((pzas || 0) * {{ $k['crudo'] }}).toLocaleString('es-MX', { maximumFractionDigits: 3 })">{{ $k['crudo'] }}</span> kg</span>
                    </div>
                    <flux:text x-show="!fijo" size="sm" class="mt-2">Escribe las piezas · a costo total</flux:text>
                </flux:card>
            </div>

            {{-- Etapas: Urdido arriba, Engomado abajo; Rizo a la izquierda, Pie a la derecha. --}}
            {{-- Dos tablas por fila solo si a cada una le tocan ≥ ~560 px (sin scroll ni $ Total cortado): @6xl = 72rem. --}}
            <div class="grid gap-x-6 gap-y-4 @6xl:grid-cols-2">
                @foreach ($etapas as $i => $e)
                    @php($c = $tonos[$e['tono']])
                    <section class="min-w-0" aria-labelledby="etapa-{{ $i }}">
                        <h2 id="etapa-{{ $i }}" class="mb-3 flex items-center gap-2 text-2xl font-bold {{ $c['titulo'] }}">
                            <span class="h-7 w-1.5 rounded {{ $c['barra'] }}"></span>{{ $e['titulo'] }}
                        </h2>
                        <div class="overflow-hidden rounded-xl border-2 {{ $c['borde'] }} {{ $c['fondo'] }}">
                            <div class="flex gap-2 overflow-x-auto p-3 [scrollbar-width:none] sm:flex-wrap sm:justify-end">
                                @foreach ($e['chips'] as $chip)
                                    <span class="shrink-0 rounded-full border {{ $c['borde'] }} bg-white px-3 py-0.5 text-sm font-semibold tabular-nums text-slate-800">{{ $chip }}</span>
                                @endforeach
                            </div>
                            <flux:table class="bg-white tabular-nums">
                                <flux:table.columns>
                                    <flux:table.column class="text-xs! {{ $c['encabezado'] }}">Cuenta</flux:table.column>
                                    <flux:table.column align="end" class="text-xs! {{ $c['encabezado'] }}">Cuota/min (ref.)</flux:table.column>
                                    <flux:table.column align="end" class="text-xs! {{ $c['encabezado'] }}">$/kg</flux:table.column>
                                    <flux:table.column align="end" class="text-xs! {{ $c['encabezado'] }}">kg base</flux:table.column>
                                    <flux:table.column align="end" class="text-xs! {{ $c['acento'] }}">$ Total</flux:table.column>
                                </flux:table.columns>
                                <flux:table.rows>
                                    @isset($e['mp'])
                                        <flux:table.row>
                                            <flux:table.cell colspan="5" class="{{ $c['fondo'] }} text-xs! text-zinc-600">MP = costo {{ $e['mp'][0] }}: ${{ number_format($e['mp'][1], 3) }}/kg</flux:table.cell>
                                        </flux:table.row>
                                    @endisset
                                    @foreach ($e['filas'] as [$cuenta, $cuota, $porKg])
                                        <flux:table.row>
                                            <flux:table.cell class="font-medium text-zinc-900!">{{ $cuenta }}</flux:table.cell>
                                            <flux:table.cell align="end" class="text-zinc-600">{{ $cuota }}</flux:table.cell>
                                            <flux:table.cell align="end">${{ number_format($porKg, 3) }}</flux:table.cell>
                                            <flux:table.cell align="end" class="font-semibold text-emerald-600!">{{ number_format($e['kg'], 3) }}</flux:table.cell>
                                            <flux:table.cell align="end" class="font-bold text-blue-700!">${{ number_format($porKg * $e['kg'], 3) }}</flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                    <flux:table.row class="bg-slate-800 font-bold text-white">
                                        <flux:table.cell colspan="2" class="text-white!">{{ $e['total'] }} · $/kg hilo</flux:table.cell>
                                        <flux:table.cell align="end" class="text-white!">${{ number_format($e['porKg'], 3) }}</flux:table.cell>
                                        <flux:table.cell align="end" class="text-slate-300!">{{ number_format($e['kg'], 3) }}</flux:table.cell>
                                        <flux:table.cell align="end" class="bg-slate-900 text-white!">${{ number_format($e['importe'], 3) }}</flux:table.cell>
                                    </flux:table.row>
                                </flux:table.rows>
                            </flux:table>
                        </div>
                    </section>

                @endforeach
            </div>
        </div>
    </div>

    {{-- Filtros en tablet/móvil: bottom sheet de Flux (flyout desde abajo), compacto para que quepa sin scroll.
         Sin título visible ni ✕: se cierra con "Ver costos", tocando fuera o con Esc. --}}
    <flux:modal name="hoja-filtros" variant="flyout" position="bottom" :closable="false" aria-label="Filtros"
                class="max-h-[90dvh] rounded-t-2xl px-4! pt-2! pb-0! md:px-6! lg:hidden">
        <div class="mx-auto mb-3 h-1.5 w-12 rounded-full bg-zinc-300" aria-hidden="true"></div>
        <div class="grid grid-cols-2 gap-x-3 gap-y-2 md:gap-x-6 md:gap-y-4">
            @include('livewire.costos.partials.hoja-costos-filtros', ['compacto' => true])
        </div>
        <div class="sticky bottom-0 -mx-4 mt-3 border-t border-zinc-200 bg-white px-4 py-3 md:-mx-6 md:px-6">
            <flux:modal.close>
                <flux:button variant="primary" color="blue" class="min-h-touch w-full">Ver costos</flux:button>
            </flux:modal.close>
        </div>
    </flux:modal>
</div>
