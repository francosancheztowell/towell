@props(['modulos', 'columns' => 'xl:grid-cols-5', 'filterConfig' => true, 'imageFolder' => 'fotos_modulos'])

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4 gap-3 md:gap-4 lg:gap-5 max-w-6xl mx-auto px-6 py-8">
    @foreach ($modulos as $modulo)
        @if(!$filterConfig || $modulo['nombre'] !== 'Configuración')
            <a href="{{ isset($modulo['ruta_tipo']) && $modulo['ruta_tipo'] === 'route' ? route($modulo['ruta'], $modulo['params'] ?? []) : url($modulo['ruta']) }}"
               class="block group relative overflow-visible min-h-[48px] min-w-[48px] touch-manipulation ripple-effect">
                <div class="p-4 md:p-5 lg:p-6 flex flex-col items-center justify-center min-h-[10rem] md:min-h-[13rem] lg:min-h-[12rem] transition-all duration-300 transform hover:scale-105 active:scale-[0.98]">

                    <!-- Contenedor de imagen optimizado para tablet -->
                    <div class="flex-shrink-0 mb-3">
                        <div class="relative transform transition-transform duration-300 group-hover:-translate-y-0.5">
                            @php
                                // FORZAR ACTUALIZACIÓN CON TIMESTAMP PARA EVITAR CACHÉ
                                $timestamp = time();
                                if (!empty($modulo['imagen'])) {
                                    $imagenUrl = asset('images/' . $imageFolder . '/' . $modulo['imagen']) . '?v=' . $timestamp;
                                } else {
                                    $imagenUrl = asset('images/fondosTowell/TOWELLIN.png') . '?v=' . $timestamp;
                                }
                                $imagenFallback = asset('images/fondosTowell/TOWELLIN.png') . '?v=' . $timestamp;
                            @endphp

                            <img src="{{ $imagenUrl }}"
                                alt="{{ $modulo['nombre'] }}"
                                class="w-32 h-32 md:w-44 md:h-44 lg:w-36 lg:h-36 object-cover rounded-xl shadow-md group-hover:shadow-xl transition-shadow duration-300"
                                onerror="this.src='{{ $imagenFallback }}'"
                                title="{{ $modulo['nombre'] }} - {{ $modulo['imagen'] ?? 'Sin imagen' }}"
                                loading="eager"
                                decoding="sync">
                        </div>
                    </div>

                    <!-- Texto del módulo optimizado -->
                    <div class="text-center px-2 -mt-3">
                        <h2 class="font-bold text-white leading-tight group-hover:text-blue-100 transition-colors duration-300 text-xs md:text-sm lg:text-sm break-words drop-shadow-lg bg-black/50 px-2 py-1 rounded-md backdrop-blur-sm max-w-full">
                            {{ $modulo['nombre'] }}
                        </h2>
                    </div>
                </div>
            </a>
        @endif
    @endforeach
</div>
