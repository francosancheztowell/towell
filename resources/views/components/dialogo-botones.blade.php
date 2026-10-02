{{-- Pie de un <dialog> con formulario Livewire: Cancelar cierra, el submit muestra spinner
     (data-ocupado, app.css) mientras corre $accion. --}}
@props(['accion' => 'guardar', 'texto' => 'Guardar'])

<div class="ui-dialogo__botones">
    <button type="button" class="ui-dialogo__boton ui-dialogo__boton--secundario"
            x-on:click="$el.closest('dialog').close()">Cancelar</button>
    <button type="submit" class="ui-dialogo__boton ui-dialogo__boton--primario"
            wire:loading.attr="data-ocupado" wire:target="{{ $accion }}">{{ $texto }}</button>
</div>
