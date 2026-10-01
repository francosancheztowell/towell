{{--
    Tabla reutilizable para los CRUD de la app. Se usa dentro de un componente
    Livewire que aplique el trait App\Livewire\Concerns\ConTabla. Pinta con flux:table
    (+ x-ui.table-empty); aquí queda lo propio de Livewire (orden, selección, paginado).

    @prop array   $columnas   [['campo','titulo','orden'=>bool,'clase'=>string,'valor'=>Closure], ...]
    @prop mixed   $filas      LengthAwarePaginator con los registros
    @prop ?string $seleccionado  Id de la fila seleccionada
    @prop string  $ordenPor / $ordenDir
    @prop ?string $alEditar   Método Livewire a llamar con doble clic o Enter sobre la fila
    @prop string  $vacio      Texto del estado vacío
    @slot acciones  Botones de Crear/Editar/Eliminar. Se teletransportan al navbar,
                    así que la página debe incluir en @section('navbar-right'):
                        <div id="tabla-navbar-acciones" class="flex items-center gap-2"></div>
    @slot filtros   Selects propios de la pantalla; van junto al buscador.
    @prop ?string $fijarColumnas  Clave para recordar columnas fijas: clic derecho en un encabezado →
                    Fijar columna (componentes/tabla-fijar.ts). null = sin la opción.
    @prop bool    $mostrarPie   Pie con conteo, tamaño de página y paginación (default true).
    @prop bool    $filtroArriba  El botón del embudo (filtros por columna) va en el navbar, con las
                    acciones, en vez de en la barra de la tabla.
    Filtros por columna: las columnas con 'filtro' (ver ConTabla::columnas) llevan un input en
    una fila bajo el encabezado, oculta hasta pulsar el embudo; al ocultarla se limpian.
    Cebra y selección: .tabla-cebra / .tabla-seleccionable (app.css), las mismas de flux:table.

    Ejemplo:
      <x-tabla :columnas="$this->columnas()" :filas="$filas" :seleccionado="$seleccionado"
               :orden-por="$ordenPor" :orden-dir="$ordenDir" al-editar="abrirEdicion">
          <x-slot:acciones>…</x-slot:acciones>
          <x-slot:filtros>…</x-slot:filtros>
      </x-tabla>
--}}
@props([
    'columnas' => [],
    'filas',
    'seleccionado' => null,
    'ordenPor' => '',
    'ordenDir' => 'asc',
    'alEditar' => null,
    'vacio' => 'No hay registros',
    'vacioIcono' => 'fa-inbox',
    'buscarPlaceholder' => 'Buscar…',
    'acciones' => null,
    'filtros' => null,
    'seleccionInmediata' => false,
    'objetivosExtra' => '',
    'mostrarFiltros' => true,
    'mostrarTamanoPagina' => true,
    'mostrarPie' => true,
    'fijarColumnas' => null,
    'filtroArriba' => false,
])

@php
    // Un solo target para las acciones: mismo lugar en todas las pantallas.
    $objetivosCarga = 'buscar,filtrosColumna,ordenar,gotoPage,previousPage,nextPage,porPagina'.($objetivosExtra !== '' ? ','.$objetivosExtra : '');
    $conFiltro = fn (array $columna): bool => ! empty($columna['filtro']) && filled($columna['campo'] ?? null);
    $hayFiltrosColumna = collect($columnas)->contains($conFiltro);
@endphp

{{-- .tabla-pantalla: si la página es .pantalla-completa, la tabla ocupa el alto que sobra y el scroll
     vive dentro; si no, crece como antes. --}}
{{-- El x-data va aquí y no en el <tbody>: este nodo no cambia entre renders, así que el scope
     sobrevive al morph de Livewire (en el <tbody>, con la selección incrustada, se reemplazaba y
     las filas nuevas quedaban sin `visual`: "visual is not defined" y no se podía seleccionar).
     La selección inicial se lee de $wire, no de un literal que cambia en cada render. --}}
<div class="tabla-pantalla overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
     x-data="{
        verFiltros: false,
        visual: null,
        pending: 0,
        init() {
            this.visual = this.$wire.seleccionado;
            this.$watch('$wire.seleccionado', value => { if (!this.pending) this.visual = value; });
        },
        async elegir(id) {
            this.visual = this.visual === id ? null : id;
            window.dispatchEvent(new CustomEvent('tabla-seleccion-local', { detail: { component: this.$wire.$id, id: this.visual } }));
            this.pending++;
            try { await this.$wire.seleccionar(id); }
            finally {
                this.pending--;
                if (!this.pending) this.visual = this.$wire.seleccionado;
            }
        },
        mover(evento, paso) {
            const filas = [...this.$root.querySelectorAll('tr[data-fila]')];
            const actual = filas.indexOf(evento.target.closest('tr[data-fila]'));
            filas[Math.min(filas.length - 1, Math.max(0, actual + paso))]?.focus();
        }
     }"
     x-on:tabla-seleccion-local.window="if ($event.detail.component === $wire.$id) visual = $event.detail.id">
    {{-- Dentro del x-data: lo teletransportado conserva el scope (el embudo usa verFiltros). --}}
    @if (filled($acciones) || ($filtroArriba && $hayFiltrosColumna))
        @teleport('#tabla-navbar-acciones')
            <div class="flex items-center gap-2">
                @if ($filtroArriba && $hayFiltrosColumna)
                    <flux:button icon="funnel" class="min-h-touch"
                                 title="Filtrar por columna"
                                 x-bind:class="verFiltros && 'ring-2 ring-blue-500'"
                                 x-bind:aria-pressed="verFiltros.toString()"
                                 x-on:click="verFiltros = !verFiltros; if (!verFiltros) $wire.set('filtrosColumna', {})">Filtrar</flux:button>
                @endif
                {{ $acciones }}
            </div>
        @endteleport
    @endif
    @if ($mostrarFiltros)
    {{-- Barra: buscador + filtros de la pantalla --}}
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/60 px-3 py-2.5">
        <label class="relative min-w-0 flex-1 sm:max-w-xs">
            <span class="sr-only">{{ $buscarPlaceholder }}</span>
            <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
            <input type="search" wire:model.live.debounce.300ms="buscar"
                   placeholder="{{ $buscarPlaceholder }}"
                   class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
        </label>

        {{ $filtros }}

        @if ($hayFiltrosColumna && ! $filtroArriba)
            <flux:button icon="funnel" variant="filled" class="min-h-touch min-w-touch"
                         title="Filtrar por columna" aria-label="Filtrar por columna"
                         x-bind:aria-pressed="verFiltros.toString()"
                         x-on:click="verFiltros = !verFiltros; if (!verFiltros) $wire.set('filtrosColumna', {})" />
        @endif

        <span wire:loading.delay wire:target="{{ $objetivosCarga }}"
              class="ms-auto hidden items-center gap-1.5 text-xs font-semibold text-blue-600 sm:inline-flex">
            <i class="fa-solid fa-circle-notch fa-spin"></i> Actualizando
        </span>
    </div>

    @endif

    <div class="relative flex min-h-0 flex-1 flex-col">
        <div class="flex min-h-0 flex-1 flex-col" wire:loading.delay.class="opacity-50" wire:target="{{ $objetivosCarga }}">
            {{-- flux:table. El <thead> va a mano (no flux:table.columns) porque lleva dos filas:
                 títulos y filtros por columna; las celdas sí son de Flux. --}}
            <flux:table class="tabla-cebra tabla-seleccionable" :data-fijar-columnas="$fijarColumnas">
                <thead class="sticky top-0 z-20 bg-white">
                    <tr>
                        @foreach ($columnas as $indice => $columna)
                            @php
                                $campo = $columna['campo'] ?? '';
                                $ordenable = ($columna['orden'] ?? true) && $campo !== '';
                                $activa = $ordenable && $ordenPor === $campo;
                                $alOrdenar = $ordenable ? "ordenar('{$campo}')" : null;
                                $filtroActivo = $conFiltro($columna)
                                    ? "String(\$wire.filtrosColumna?.[{$indice}] ?? '').trim() !== '' ? '' : null"
                                    : null;
                            @endphp
                            <flux:table.column :sortable="$ordenable" :sorted="$activa" :direction="$ordenDir"
                                               :align="$columna['alinear'] ?? 'start'"
                                               class="{{ $columna['clase'] ?? '' }}" :wire:click="$alOrdenar"
                                               {{-- Verde (app.css) mientras su filtro tenga algo: se ve qué está filtrando. --}}
                                               :x-bind:data-filtro-activo="$filtroActivo"
                                               aria-sort="{{ $activa ? ($ordenDir === 'desc' ? 'descending' : 'ascending') : 'none' }}">
                                {{ $columna['titulo'] ?? $campo }}
                            </flux:table.column>
                        @endforeach
                    </tr>
                    @if ($hayFiltrosColumna)
                        <tr data-fila-filtros x-show="verFiltros" x-cloak>
                            @foreach ($columnas as $indice => $columna)
                                <th class="{{ $columna['clase'] ?? '' }} {{ ($columna['alinear'] ?? '') === 'center' ? 'text-center' : '' }}">
                                    @if ($conFiltro($columna))
                                        <input type="search" class="tabla-filtro-input" placeholder="{{ $columna['filtroAyuda'] ?? 'Filtrar' }}"
                                               title="{{ isset($columna['filtroAyuda']) ? 'Ej.: '.$columna['filtroAyuda'].' · comas = varios valores' : 'Varios valores separados por coma: 2229, 2039, 2049' }}"
                                               aria-label="Filtrar {{ $columna['titulo'] ?? $columna['campo'] }}"
                                               wire:model.live.debounce.400ms="filtrosColumna.{{ $indice }}">
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    @endif
                </thead>

                <flux:table.rows>
                    @forelse ($filas as $fila)
                        @php
                            $id = (string) $fila->getKey();
                            // Atributos según el modo; van armados aquí porque dentro de la etiqueta
                            // de un componente Blade no admite @if.
                            $atributosFila = array_filter([
                                'x-on:click' => $seleccionInmediata ? 'elegir('.\Illuminate\Support\Js::from($id).')' : null,
                                'wire:click' => $seleccionInmediata ? null : "seleccionar('{$id}')",
                                'wire:dblclick' => $alEditar ? "{$alEditar}('{$id}')" : null,
                                'x-bind:aria-selected' => $seleccionInmediata ? 'visual === '.\Illuminate\Support\Js::from($id)." ? 'true' : 'false'" : null,
                                'aria-selected' => $seleccionInmediata ? null : ($seleccionado === $id ? 'true' : 'false'),
                            ], fn ($valor) => $valor !== null);
                        @endphp
                        {{-- Color de cebra, hover y seleccionada: .tabla-cebra / .tabla-seleccionable por aria-selected. --}}
                        <flux:table.row data-fila tabindex="0"
                            wire:key="fila-{{ $filas->getPageName() }}-{{ $id }}"
                            x-on:keydown.enter.prevent="$wire.{{ $alEditar ?? 'seleccionar' }}('{{ $id }}')"
                            x-on:keydown.arrow-down.prevent="mover($event, 1)"
                            x-on:keydown.arrow-up.prevent="mover($event, -1)"
                            class="transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500"
                            :attributes="new \Illuminate\View\ComponentAttributeBag($atributosFila)">
                            @foreach ($columnas as $columna)
                                @php
                                    $campo = $columna['campo'] ?? '';
                                    $valor = isset($columna['valor'])
                                        ? ($columna['valor'])($fila)
                                        : data_get($fila, $campo);
                                @endphp
                                <flux:table.cell class="{{ $columna['clase'] ?? '' }}" :align="$columna['alinear'] ?? 'start'">
                                    {{ filled($valor) ? $valor : '—' }}
                                </flux:table.cell>
                            @endforeach
                        </flux:table.row>
                    @empty
                        <x-ui.table-empty :colspan="count($columnas)" :message="$vacio" :icon="$vacioIcono" />
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    {{-- Pie: a la izquierda qué se está viendo, a la derecha cómo moverse. Sin él (mostrarPie=false)
         el componente debe traer todas las filas en una página. --}}
    @if ($mostrarPie)
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-slate-100 bg-slate-50/60 px-3 py-2">
        <div class="flex items-center gap-3 text-xs text-slate-500">
            <span>
                @if ($filas->total() > 0)
                    <span class="font-bold text-slate-700">{{ number_format($filas->firstItem()) }}–{{ number_format($filas->lastItem()) }}</span>
                    de {{ number_format($filas->total()) }}
                @else
                    Sin registros
                @endif
            </span>

            @if ($mostrarTamanoPagina)
            <label class="flex items-center gap-1.5">
                <span class="hidden sm:inline">Mostrar</span>
                <select wire:model.live="porPagina"
                        class="rounded-lg border border-slate-300 bg-white py-1 pl-2 pr-7 text-xs font-semibold text-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    @foreach ([25, 50, 100] as $opcion)
                        <option value="{{ $opcion }}">{{ $opcion }}</option>
                    @endforeach
                </select>
            </label>
            @endif
        </div>

        {{ $filas->onEachSide(1)->links('components.tabla-paginacion') }}
    </div>
    @endif
</div>
