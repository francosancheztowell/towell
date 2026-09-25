{{--
  Modal base: <dialog> nativo (DS-02). Receta: docs/cerebro-towell/Arquitectura/receta-componentes.md

  @prop string $id              ID del <dialog> (el JS lo busca con getElementById)
  @prop string $title           Título del header (queda ligado con aria-labelledby)
  @prop string $size            'sm'|'md'|'lg'|'xl' (default 'md')
  @prop string $onclose         JS que cierra el modal (botón ×, y Esc vía el runtime).
                                Default: poner `hidden` y restaurar el scroll del body.
  @prop bool   $closeOnBackdrop Cerrar al hacer clic fuera del panel (default false)
  @prop string $tone            'default'|'danger' (header rojo para confirmaciones)
  @slot footer                  Botones de acción alineados a la derecha (opcional)

  Abrir/cerrar: quitar/poner la clase `hidden` en el <dialog>, igual que el <div> anterior
  (así lo hace Programa Tejido). resources/js/componentes/dialog.ts sincroniza `open`, lleva
  el foco al primer control, lo devuelve al cerrar y hace que Esc ejecute $onclose.
  No se usa showModal(): el top layer dejaría a SweetAlert2 y a los toasts debajo.
--}}
@props(['id', 'title', 'size' => 'md', 'onclose' => null, 'closeOnBackdrop' => false, 'tone' => 'default', 'footer' => null])

@php
$maxWidth = match($size) {
    'sm' => 'max-w-sm',
    'lg' => 'max-w-2xl',
    'xl' => 'max-w-4xl',
    default => 'max-w-lg',
};
$closeJs = $onclose ?? "document.getElementById('{$id}').classList.add('hidden'); document.body.style.overflow = '';";
$tituloId = $id.'-titulo';
$header = $tone === 'danger' ? 'bg-red-50 border-red-100' : 'bg-gray-50 border-gray-200';
$tituloColor = $tone === 'danger' ? 'text-red-700' : 'text-gray-800';
@endphp

<dialog id="{{ $id }}"
        {{ $attributes->class(['ui-modal hidden']) }}
        aria-modal="true" aria-labelledby="{{ $tituloId }}"
        data-ui-modal
        @if ($closeOnBackdrop) data-ui-modal-backdrop-close @endif>
  <div class="min-h-full flex items-center justify-center p-4" data-ui-modal-backdrop>
    <div class="ui-modal-panel relative bg-white rounded-lg shadow-xl border border-gray-200 w-full {{ $maxWidth }} overflow-hidden">

      {{-- Header --}}
      <div class="flex items-center justify-between px-4 py-3 border-b {{ $header }}">
        <h3 id="{{ $tituloId }}" class="text-base font-semibold {{ $tituloColor }} uppercase tracking-wide">{{ $title }}</h3>
        <button type="button"
                onclick="{{ $closeJs }}"
                data-ui-modal-close
                class="p-1 text-gray-500 hover:text-gray-700 hover:bg-gray-200 rounded focus:outline-none focus:ring-2 focus:ring-gray-300"
                aria-label="Cerrar">
          <i class="fas fa-times" aria-hidden="true"></i>
        </button>
      </div>

      {{-- Body --}}
      <div class="p-4">
        {{ $slot }}
      </div>

      @if (filled($footer))
        <div class="flex flex-wrap justify-end gap-2 px-4 py-3 border-t border-gray-200 bg-gray-50">
          {{ $footer }}
        </div>
      @endif

    </div>
  </div>
</dialog>
