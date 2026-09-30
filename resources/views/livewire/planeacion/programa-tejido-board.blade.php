{{-- Shell v2 (PT 03). La grilla es el mismo partial que la legacy; wire:ignore porque el DOM
     lo maneja resources/js/programa-tejido/index.js (selección, filtros, edición inline). --}}
<div class="bg-white overflow-hidden w-full pt-page-card" data-pt-shell="v2" data-superficie="{{ $superficie }}">
  <div wire:ignore>
    @include('modulos.programa-tejido.partials.grilla')
  </div>
</div>
