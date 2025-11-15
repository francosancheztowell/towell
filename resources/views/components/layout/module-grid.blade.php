@props(['modulos', 'columns' => 'xl:grid-cols-5', 'filterConfig' => true, 'imageFolder' => 'fotos_modulos', 'isSubmodulos' => false])

@php
    $timestamp = time();
    $imagenFallback = asset('images/fondosTowell/TOWELLIN.png');

    // Contar módulos después de filtrar
    // Solo filtrar "Configuración" si NO es una vista de submódulos
    $modulosFiltrados = collect($modulos)->filter(function($modulo) use ($filterConfig, $isSubmodulos) {
        // Si es submódulos, NO filtrar "Configuración"
        if ($isSubmodulos) {
            return true;
        }
        // Si es módulos principales, filtrar "Configuración" si filterConfig es true
        return !$filterConfig || $modulo['nombre'] !== 'Configuración';
    });

    $cantidadModulos = $modulosFiltrados->count();
    $pocosModulos = $cantidadModulos <= 3;

    // Determinar clases según la cantidad de módulos
    if ($pocosModulos) {
        // Para 3 o menos módulos: centrar y ajustar espaciado
        if ($cantidadModulos === 1) {
            // 1 módulo: centrado
            $gridClasses = 'grid grid-cols-1 justify-items-center';
            $itemClasses = '';
            $gapClasses = '';
        } elseif ($cantidadModulos === 2) {
            // 2 módulos: grid de 2 columnas con espaciado grande
            $gridClasses = 'grid grid-cols-2 justify-items-center';
            $itemClasses = '';
            $gapClasses = 'gap-8 md:gap-12 lg:gap-16 xl:gap-20';
        } else { // 3 módulos
            // 3 módulos: 1 columna en móvil, 3 en desktop, con espaciado medio-grande
            $gridClasses = 'grid grid-cols-1 sm:grid-cols-3 justify-items-center space-x-10';
            $itemClasses = '';
            $gapClasses = 'gap-6 md:gap-8 lg:gap-10 xl:gap-12';
        }
    } else {
        // Para más de 3 módulos: grid normal con espaciado grande
        $gridClasses = 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 ' . $columns . ' justify-items-center';
        $itemClasses = '';
        $gapClasses = 'gap-3 md:gap-4 lg:gap-5 space-x-10';
    }
@endphp

<div class="w-full flex justify-center items-start px-6 py-8">
    <div class="{{ $gridClasses }} {{ $gapClasses }} max-w-7xl mx-auto">
    @foreach ($modulos as $modulo)
        @if($isSubmodulos || !$filterConfig || $modulo['nombre'] !== 'Configuración')
            @php
                $imagenUrl = !empty($modulo['imagen'])
                    ? asset('images/' . $imageFolder . '/' . $modulo['imagen']) . '?v=' . $timestamp
                    : $imagenFallback;
            @endphp
            <a href="{{ isset($modulo['ruta_tipo']) && $modulo['ruta_tipo'] === 'route' ? route($modulo['ruta'], $modulo['params'] ?? []) : url($modulo['ruta']) }}"
               class="block group relative overflow-visible min-h-[48px] min-w-[48px] touch-manipulation ripple-effect {{ $itemClasses }}">
                <div class="p-4 md:p-5 lg:p-6 flex flex-col items-center justify-center min-h-[10rem] md:min-h-[13rem] lg:min-h-[12rem] transition-all duration-300 transform hover:scale-105 active:scale-[0.98]">

                    <!-- Contenedor de imagen optimizado para tablet -->
                    <div class="flex-shrink-0 mb-3">
                        <div class="relative transform transition-transform duration-300 group-hover:-translate-y-0.5">

                            <img src="{{ $imagenUrl }}"
                                alt="{{ $modulo['nombre'] }}"
                                class="w-32 h-32 md:w-44 md:h-44 lg:w-36 lg:h-36 object-cover rounded-xl shadow-md group-hover:shadow-xl transition-shadow duration-300"
                                onerror="this.src='{{ $imagenFallback }}'; this.onerror=null;"
                                title="{{ $modulo['nombre'] }} - {{ $modulo['imagen'] ?? 'Sin imagen' }}"
                                loading="eager"
                                decoding="async">
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
</div>
