{{--
    Barra de filtros (DS-10): buscador + filtros propios + limpiar + conteo.
    Receta: docs/cerebro-towell/Arquitectura/receta-componentes.md

    Modo cliente (tabla ya pintada en el HTML): pasar `target` = selector de la tabla. Filtra
    sus filas [data-filter-row] con el motor de filtros de la app
    (resources/js/componentes/filter-bar.ts → programa-tejido/filter-engine.ts).
    Los selects del slot llevan data-ui-filter-column="campo" y comparan con data-campo de la fila.

    Modo Livewire: sin `target`; el input usa wire:model (pasarlo en `model`) y no hay JS.

    @prop string $target       Selector de la tabla (modo cliente)
    @prop string $model        Propiedad Livewire del buscador (modo Livewire)
    @prop string $placeholder
    @prop bool   $count        Mostrar "N de M" (modo cliente, default true)

    <x-ui.filter-bar target="#tablaMaquinas" placeholder="Buscar máquina">
        <select data-ui-filter-column="tipo" class="ui-select">…</select>
    </x-ui.filter-bar>
--}}
@props(['target' => null, 'model' => null, 'placeholder' => 'Buscar…', 'count' => true])

<div {{ $attributes->class(['flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/60 px-3 py-2.5']) }}
     @if ($target) data-ui-filter-bar data-ui-filter-target="{{ $target }}" @endif role="search">
    <label class="relative min-w-0 flex-1 sm:max-w-xs">
        <span class="sr-only">{{ $placeholder }}</span>
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
        <input type="search" placeholder="{{ $placeholder }}" autocomplete="off"
               @if ($model) wire:model.live.debounce.300ms="{{ $model }}" @else data-ui-filter-text @endif
               class="min-h-touch w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
    </label>

    {{ $slot }}

    @if ($target)
        <button type="button" data-ui-filter-clear
                class="inline-flex min-h-touch items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i> Limpiar
        </button>
        @if ($count)
            <span class="ms-auto text-caption font-semibold text-ink-muted" data-ui-filter-count aria-live="polite"></span>
        @endif
    @endif
</div>
