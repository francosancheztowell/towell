{{-- Switch Directo / Absorbente de Costos, en el navbar (en móvil, la fila de acciones que el navbar pone
     debajo). Va dentro de un componente Livewire con la propiedad $costeo; $destino es el contenedor del
     navbar que pone la página en @section('navbar-right'). Lo usan la Hoja de costos y Cuotas. --}}
@props(['destino'])

@teleport($destino)
<div class="-mt-1 flex items-center gap-2">
    <flux:radio.group wire:model.live="costeo" variant="segmented" aria-label="Método de costeo" class="h-11!">
        <flux:radio value="directo" label="Directo" title="Sin costos fijos" class="min-h-touch data-checked:bg-blue-600! data-checked:text-white!" />
        <flux:radio value="absorbente" label="Absorbente" title="Todos los costos" class="min-h-touch data-checked:bg-blue-600! data-checked:text-white!" />
    </flux:radio.group>
</div>
@endteleport
