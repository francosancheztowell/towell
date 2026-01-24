@php
    $isProduccionIndex = Route::currentRouteName() === 'produccion.index';
    $backButtonClasses = $isProduccionIndex 
        ? 'bg-white text-white opacity-0 pointer-events-none' 
        : 'bg-blue-200 hover:bg-blue-400 text-black opacity-100';
@endphp

<div class="flex items-center gap-2 md:gap-3 flex-shrink-0">
    <button id="btn-back" 
            class="w-8 h-8 md:w-10 md:h-10 flex items-center justify-center rounded-lg transition-all duration-200 shadow-md hover:shadow-lg active:scale-95 {{ $backButtonClasses }}"
            title="Volver atrás" 
            aria-label="Volver atrás" 
            {{ $isProduccionIndex ? 'disabled' : '' }}>
        <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
    </button>

    <a href="{{ route('produccion.index') }}" class="flex items-center">
        <img src="{{ asset('images/fondosTowell/logo.png') }}" 
             alt="Logo Towell" 
             fetchpriority="high" 
             class="h-10 md:h-12">
    </a>
</div>
