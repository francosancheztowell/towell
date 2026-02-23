@php
    $usuario = Auth::user();
    $fotoUrl = getFotoUsuarioUrl($usuario->foto ?? null);
    $usuarioInicial = strtoupper(substr($usuario->nombre, 0, 1));
@endphp

<div class="relative">
    <button id="btn-user-avatar" 
            class="w-10 h-10 md:w-12 md:h-12 rounded-full flex items-center justify-center transition-all duration-200 shadow-lg hover:shadow-xl hover:scale-105 overflow-hidden">
        @if($fotoUrl)
            <img src="{{ $fotoUrl }}" 
                 alt="Foto de {{ $usuario->nombre }}"
                 width="48"
                 height="48"
                 decoding="async"
                 class="w-full h-full object-cover">
        @else
            <div class="w-full h-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-sm md:text-base hover:from-blue-600 hover:to-blue-700">
                {{ $usuarioInicial }}
            </div>
        @endif
    </button>
</div>
