{{--
  Componente modal base reutilizable (sin Alpine.js — toggle con classList).
  @prop string $id       ID único del div modal
  @prop string $title    Título en el header
  @prop string $size     'sm'|'md'|'lg'|'xl' (default: 'md')
  @prop string $onclose  JS a ejecutar al cerrar (default: ocultar + restaurar scroll)
--}}
@props(['id', 'title', 'size' => 'md', 'onclose' => null])

@php
$maxWidth = match($size) {
    'sm' => 'max-w-sm',
    'lg' => 'max-w-2xl',
    'xl' => 'max-w-4xl',
    default => 'max-w-lg',
};
$closeJs = $onclose ?? "document.getElementById('{$id}').classList.add('hidden'); document.body.style.overflow = '';";
@endphp

<div id="{{ $id }}"
     class="hidden fixed left-0 right-0 bottom-0 z-50 overflow-y-auto"
     style="top: var(--pt-navbar-height, 64px); background-color: rgba(0,0,0,0.4);"
     aria-modal="true" role="dialog">
  <div class="min-h-full flex items-center justify-center p-4">
    <div class="relative bg-white rounded-lg shadow-xl border border-gray-200 w-full {{ $maxWidth }} overflow-hidden">

      {{-- Header --}}
      <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-50">
        <h3 class="text-base font-semibold text-gray-800 uppercase tracking-wide">{{ $title }}</h3>
        <button type="button"
                onclick="{{ $closeJs }}"
                class="p-1 text-gray-500 hover:text-gray-700 hover:bg-gray-200 rounded focus:outline-none focus:ring-2 focus:ring-gray-300"
                aria-label="Cerrar">
          <i class="fas fa-times"></i>
        </button>
      </div>

      {{-- Body --}}
      <div class="p-4">
        {{ $slot }}
      </div>

    </div>
  </div>
</div>
