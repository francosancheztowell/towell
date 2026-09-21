@php
    $isProduccionIndex = Route::currentRouteName() === 'produccion.index';
    // Ruta del padre resuelta en el servidor: el boton es un <a> normal, sin fetch.
    $rutaPadre = $isProduccionIndex
        ? \App\Services\ModuloService::RUTA_INICIO
        : app(\App\Services\ModuloService::class)->rutaPadreDe(request()->path());
    $backButtonClasses = $isProduccionIndex
        ? 'bg-white text-white opacity-0 pointer-events-none'
        : 'bg-blue-200 hover:bg-blue-400 text-black opacity-100';
@endphp

<div class="flex items-center gap-2 md:gap-3 flex-shrink-0">
    <a id="btn-back"
       href="{{ $rutaPadre }}"
       class="w-12 h-12 md:w-14 md:h-14 flex items-center justify-center rounded-lg transition-all duration-200 shadow-md hover:shadow-lg active:scale-95 {{ $backButtonClasses }}"
       title="Volver atrás"
       aria-label="Volver atrás"
       @if($isProduccionIndex) tabindex="-1" aria-hidden="true" @endif>
        <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
    </a>

    <a href="{{ route('produccion.index') }}" class="flex items-center">
        <picture>
            <source srcset="{{ asset('images/fondosTowell/logo-sm.webp') }}" type="image/webp">
            <img src="{{ asset('images/fondosTowell/logo.png') }}"
                 alt="Logo Towell"
                 width="792"
                 height="227"
                 fetchpriority="high"
                 decoding="async"
                 class="h-10 md:h-12 w-auto">
        </picture>
    </a>
</div>
