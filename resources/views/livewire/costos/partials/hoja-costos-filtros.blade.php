{{-- Filtros de la hoja: los usa el sidebar (escritorio) y el bottom sheet (tablet/móvil). Mismo wire:model en
     ambos; solo uno se ve a la vez. wire:model sin .live: no viaja al servidor hasta que haya cálculo.
     $compacto (sheet): rejilla de 2 columnas sin scroll; Modo y Período comparten fila y lo largo ocupa las dos
     (en tablet, md:, cada campo toma una). La clase va en un div: en flux:select/input cae en el control, no en la celda. --}}
@php($ancho = ($compacto ?? false) ? 'col-span-2 md:col-span-1' : '')
<flux:radio.group wire:model.live="modo" variant="segmented" aria-label="Modo" class="{{ ($compacto ?? false) ? 'self-end' : '' }}">
    <flux:radio value="linea" label="Línea" class="px-2!" />
    <flux:radio value="simular" label="Simular" class="px-2!" />
</flux:radio.group>

<flux:select wire:model="periodo" label="Período">
    @foreach (['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $i => $mes)
        <flux:select.option value="{{ $i + 1 }}">{{ $mes }}</flux:select.option>
    @endforeach
</flux:select>

<div class="{{ $ancho }}"><flux:input label="Código" value="7304 — FENDI BD III" readonly /></div>

<div class="{{ $ancho }}">
    <flux:select wire:model="tamano" label="Tamaño">
        <flux:select.option value="MB">MB · 877 órdenes · dominante</flux:select.option>
    </flux:select>
</div>

{{-- Los conteos van junto a la etiqueta: misma información, una fila menos. --}}
<div class="{{ $ancho }}"><flux:field>
    <div class="flex items-center gap-2 whitespace-nowrap">
        <flux:label class="shrink-0">Composición</flux:label>
        <flux:badge color="blue" variant="solid" size="sm">5 componentes</flux:badge>
        <flux:badge size="sm">877 órdenes</flux:badge>
    </div>
    <flux:select wire:model="composicion" aria-label="Composición">
        <flux:select.option value="1">Opción 1 · L.MAT TEJ MB FEN BD</flux:select.option>
    </flux:select>
</flux:field></div>

<div class="{{ $ancho }}">
    <flux:label>Materiales</flux:label>
    <details class="group mt-2 rounded-lg border border-zinc-200 bg-zinc-50">
        <summary class="flex min-h-10 cursor-pointer list-none items-center justify-between gap-2 px-3 py-1.5 text-sm">
            <span class="truncate"><span class="font-semibold">L.MAT</span> · TEJ MB FEN BD III-I</span>
            <flux:icon.chevron-down variant="micro" class="shrink-0 transition group-open:rotate-180" />
        </summary>
        <flux:text class="border-t border-zinc-200 px-3 py-2">Lista de materiales pendiente del cálculo.</flux:text>
    </details>
</div>

<div class="{{ $ancho }}">
    <flux:select wire:model="telar" label="Telar · óptimo por costo">
        <flux:select.option value="303">303 · $70.22/kg · óptimo · ITEMA</flux:select.option>
    </flux:select>
</div>
