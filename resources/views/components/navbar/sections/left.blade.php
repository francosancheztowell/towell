@php
    $isProduccionIndex = Route::currentRouteName() === 'produccion.index';
    // Ruta del padre resuelta en el servidor: el boton es un <a> normal, sin fetch.
    $rutaPadre = $isProduccionIndex
        ? \App\Services\ModuloService::RUTA_INICIO
        : app(\App\Services\ModuloService::class)->rutaPadreDe(request()->path());
@endphp

<div class="flex h-14 items-center gap-1 sm:gap-2 md:gap-3 shrink-0">
    {{-- En el inicio no hay padre: invisible para no mover el logo; en móvil se quita para dejarle sitio al título. --}}
    <flux:button id="btn-back" :href="$rutaPadre" variant="filled" color="blue" icon="chevron-left"
                 class="min-h-touch min-w-touch {{ $isProduccionIndex ? 'invisible max-sm:hidden' : '' }}"
                 title="Volver atrás" aria-label="Volver atrás"
                 :tabindex="$isProduccionIndex ? -1 : null" :aria-hidden="$isProduccionIndex ? 'true' : null" />

    <a href="{{ route('produccion.index') }}" class="flex items-center">
        <picture>
            <source srcset="{{ asset('images/fondosTowell/logo-sm.webp') }}" type="image/webp">
            <img src="{{ asset('images/fondosTowell/logo.png') }}"
                 alt="Logo Towell"
                 width="792"
                 height="227"
                 fetchpriority="high"
                 decoding="async"
                 class="h-7 sm:h-9 md:h-10 w-auto">
        </picture>
    </a>
</div>
