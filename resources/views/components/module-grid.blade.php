@props(['modulos', 'columns' => 'xl:grid-cols-5', 'filterConfig' => true, 'imageFolder' => 'fotos_modulos'])

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4 gap-3 md:gap-4 lg:gap-5 max-w-7xl mx-auto px-4">
    @foreach ($modulos as $modulo)
        @if(!$filterConfig || $modulo['nombre'] !== 'Configuración')
            <a href="{{ isset($modulo['ruta_tipo']) && $modulo['ruta_tipo'] === 'route' ? route($modulo['ruta'], $modulo['params'] ?? []) : url($modulo['ruta']) }}" class="block group tablet-optimized module-link">
                <div class="p-4 md:p-5 lg:p-6 flex flex-col items-center justify-center h-40 md:h-44 lg:h-48 transition-all duration-300 transform group-hover:scale-105 ripple-effect">

                    <!-- Contenedor de imagen optimizado para tablet -->
                    <div class="flex-grow flex items-center justify-center mb-3">
                        <div class="relative tablet-optimized">
                            @php
                                $imagenUrl = $modulo['imagen'] ? asset('images/' . $imageFolder . '/' . $modulo['imagen']) : asset('images/fondosTowell/TOWELLIN.png');
                                $imagenFallback = asset('images/fondosTowell/TOWELLIN.png');
                            @endphp
                            <img src="{{ $imagenUrl }}"
                                alt="{{ $modulo['nombre'] }}"
                                class="w-32 h-32 md:w-32 md:h-32 lg:w-32 lg:h-32 object-cover rounded-xl group-hover:shadow-xl transition-shadow duration-300"
                                loading="lazy"
                                decoding="async"
                                onerror="this.src='{{ $imagenFallback }}'"
                                title="{{ $modulo['nombre'] }} - {{ $modulo['imagen'] ?? 'Sin imagen' }}">
                        </div>
                    </div>

                    <!-- Texto del módulo optimizado -->
                    <div class="text-center px-2">
                        <h2 class="module-title font-bold text-gray-800 leading-tight group-hover:text-blue-800 transition-colors duration-300 text-sm md:text-base lg:text-lg">
                            {{ $modulo['nombre'] }}
                        </h2>
                    </div>
                </div>
            </a>
        @endif
    @endforeach
</div>

@push('styles')
    <style>
        /* Estilos optimizados para tablet */
        .tablet-optimized {
            -webkit-tap-highlight-color: rgba(59, 130, 246, 0.1);
            touch-action: manipulation;
        }

        .group:hover .tablet-optimized {
            transform: translateY(-2px);
        }

        .module-card {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border: 1px solid #e2e8f0;
        }

        .module-card:hover {
            background: linear-gradient(145deg, #f8fafc, #ffffff);
            border-color: #3b82f6;
        }

        .module-link {
            min-height: 48px;
            min-width: 48px;
        }

        .ripple-effect {
            position: relative;
            overflow: hidden;
        }

        .ripple-effect::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .ripple-effect:active::before {
            width: 300px;
            height: 300px;
        }
    </style>
@endpush

