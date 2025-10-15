@props(['modulos', 'columns' => 'xl:grid-cols-5', 'filterConfig' => true, 'imageFolder' => 'fotos_modulos'])

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 {{ $columns }} gap-4 md:gap-6 max-w-8xl mx-auto">
    @foreach ($modulos as $modulo)
        @if(!$filterConfig || $modulo['nombre'] !== 'Configuración')
            <a href="{{ isset($modulo['ruta_tipo']) && $modulo['ruta_tipo'] === 'route' ? route($modulo['ruta'], $modulo['params'] ?? []) : url($modulo['ruta']) }}" class="block group tablet-optimized module-link">
                <div class=" p-4 md:p-6 flex flex-col items-center justify-center h-52 md:h-60 transition-all duration-300 transform group-hover:scale-105  ripple-effect">

                    <!-- Contenedor de imagen optimizado para tablet -->
                    <div class="flex-grow flex items-center justify-center mb-4">
                        <div class="relative tablet-optimized">
                            <img src="{{ $modulo['imagen'] ? asset('images/' . $imageFolder . '/' . $modulo['imagen']) : asset('images/fondosTowell/logo_towell2.png') }}"
                                alt="{{ $modulo['nombre'] }}"
                                class="w-28 h-28 md:w-32 md:h-32 lg:w-36 lg:h-36 object-cover rounded-2xl group-hover:shadow-xl transition-shadow duration-300"
                                loading="lazy"
                                decoding="async"
                                onerror="this.src='{{ asset('images/fotos_usuarios/TOWELLIN.png') }}'">
                        </div>
                    </div>

                    <!-- Texto del módulo optimizado -->
                    <div class="text-center">
                        <h2 class="module-title font-bold text-gray-800 leading-tight group-hover:text-blue-800 transition-colors duration-300 -mt-2">
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

