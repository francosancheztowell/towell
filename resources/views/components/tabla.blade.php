{{--
    Tabla reutilizable para los CRUD de la app. Se usa dentro de un componente
    Livewire que aplique el trait App\Livewire\Concerns\ConTabla. Compone x-ui.table
    (el shell) y x-ui.table-empty; aquí queda lo propio de Livewire (orden, selección, paginado).

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
])

@php
    // Un solo target para las acciones: mismo lugar en todas las pantallas.
    $objetivosCarga = 'buscar,ordenar,gotoPage,previousPage,nextPage,porPagina'.($objetivosExtra !== '' ? ','.$objetivosExtra : '');
@endphp

@if (filled($acciones))
    @teleport('#tabla-navbar-acciones')
        <div class="flex items-center gap-2">{{ $acciones }}</div>
    @endteleport
@endif

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
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

        <span wire:loading.delay wire:target="{{ $objetivosCarga }}"
              class="ms-auto hidden items-center gap-1.5 text-xs font-semibold text-blue-600 sm:inline-flex">
            <i class="fa-solid fa-circle-notch fa-spin"></i> Actualizando
        </span>
    </div>

    @endif

    <div class="relative overflow-x-auto">
        <div wire:loading.delay.class="opacity-50" wire:target="{{ $objetivosCarga }}">
            <x-ui.table>
                <x-slot:head>
                    <tr>
                        @foreach ($columnas as $columna)
                            @php
                                $campo = $columna['campo'] ?? '';
                                $ordenable = ($columna['orden'] ?? true) && $campo !== '';
                                $activa = $ordenable && $ordenPor === $campo;
                            @endphp
                            <th scope="col"
                                class="whitespace-nowrap px-4 py-2.5 text-left font-semibold {{ $columna['clase'] ?? '' }}"
                                @if ($activa) aria-sort="{{ $ordenDir === 'desc' ? 'descending' : 'ascending' }}" @endif>
                                @if ($ordenable)
                                    <button type="button" wire:click="ordenar('{{ $campo }}')"
                                            class="inline-flex items-center gap-1.5 rounded transition hover:text-blue-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70">
                                        {{ $columna['titulo'] ?? $campo }}
                                        <i @class([
                                            'fa-solid text-[11px]',
                                            'fa-sort opacity-50' => ! $activa,
                                            'fa-sort-up' => $activa && $ordenDir === 'asc',
                                            'fa-sort-down' => $activa && $ordenDir === 'desc',
                                        ])></i>
                                    </button>
                                @else
                                    {{ $columna['titulo'] ?? $campo }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </x-slot:head>

                <tbody x-data="{
                    visual: @js($seleccionado),
                    pending: 0,
                    init() {
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
                        const filas = [...$el.querySelectorAll('tr[data-fila]')];
                        const actual = filas.indexOf(evento.target.closest('tr[data-fila]'));
                        filas[Math.min(filas.length - 1, Math.max(0, actual + paso))]?.focus();
                    }
                }" x-on:tabla-seleccion-local.window="if ($event.detail.component === $wire.$id) visual = $event.detail.id">
                    @forelse ($filas as $fila)
                        @php $id = (string) $fila->getKey(); @endphp
                        <tr data-fila tabindex="0"
                            wire:key="fila-{{ $filas->getPageName() }}-{{ $id }}"
                            @if ($seleccionInmediata)
                                x-on:click="elegir(@js($id))"
                                x-bind:style="visual === @js($id) ? 'background-color: #dbeafe; box-shadow: inset 4px 0 0 #3b82f6' : ''"
                            @else
                                wire:click="seleccionar('{{ $id }}')"
                            @endif
                            @if ($alEditar) wire:dblclick="{{ $alEditar }}('{{ $id }}')" @endif
                            @keydown.enter.prevent="$wire.{{ $alEditar ?? 'seleccionar' }}('{{ $id }}')"
                            @keydown.arrow-down.prevent="mover($event, 1)"
                            @keydown.arrow-up.prevent="mover($event, -1)"
                            @class([
                                'cursor-pointer border-b border-slate-100 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500',
                                'bg-blue-100 shadow-[inset_4px_0_0_0_#3b82f6]' => ! $seleccionInmediata && $seleccionado === $id,
                                'odd:bg-white even:bg-slate-50/60 hover:bg-blue-50' => $seleccionInmediata || $seleccionado !== $id,
                            ])
                            @if ($seleccionInmediata) x-bind:aria-selected="visual === @js($id) ? 'true' : 'false'"
                            @else aria-selected="{{ $seleccionado === $id ? 'true' : 'false' }}" @endif>
                            @foreach ($columnas as $columna)
                                @php
                                    $campo = $columna['campo'] ?? '';
                                    $valor = isset($columna['valor'])
                                        ? ($columna['valor'])($fila)
                                        : data_get($fila, $campo);
                                @endphp
                                <td class="px-4 py-2.5 align-middle text-slate-700 {{ $columna['clase'] ?? '' }}">
                                    {{ filled($valor) ? $valor : '—' }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <x-ui.table-empty :colspan="count($columnas)" :message="$vacio" :icon="$vacioIcono" />
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>
    </div>

    {{-- Pie: a la izquierda qué se está viendo, a la derecha cómo moverse. --}}
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
</div>
