@props(['modulos', 'columns' => 'xl:grid-cols-5', 'filterConfig' => true, 'imageFolder' => 'fotos_modulos', 'isSubmodulos' => false])

@php
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
            // 2 módulos: grid de 2 columnas
            $gridClasses = 'grid grid-cols-2 justify-items-center';
            $itemClasses = '';
            $gapClasses = 'gap-4 md:gap-6 lg:gap-8';
        } else { // 3 módulos
            // 3 módulos: 1 columna en móvil, 3 en desktop
            $gridClasses = 'grid grid-cols-1 sm:grid-cols-3 justify-items-center';
            $itemClasses = '';
            $gapClasses = 'gap-4 md:gap-5 lg:gap-6';
        }
    } else {
        // Para más de 3 módulos: grid normal
        $gridClasses = 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-3 ' . $columns . ' justify-items-center';
        $itemClasses = '';
        $gapClasses = 'gap-2 md:gap-3 lg:gap-4';
    }
@endphp

<div class="w-full flex justify-center items-start px-3 py-4">
    <div class="{{ $gridClasses }} {{ $gapClasses }} max-w-5xl mx-auto">
    @foreach ($modulosFiltrados as $modulo)
            @php
                if (!empty($modulo['imagen'])) {
                    $relativeImagePath = 'images/' . $imageFolder . '/' . $modulo['imagen'];
                    $absoluteImagePath = public_path($relativeImagePath);
                    $version = file_exists($absoluteImagePath) ? filemtime($absoluteImagePath) : null;
                    $imagenUrl = asset($relativeImagePath) . ($version ? '?v=' . $version : '');
                } else {
                    $imagenUrl = $imagenFallback;
                }
                
                // Verificar si es el módulo de Atado de Julio / Cortado de Rollo
                $esNotificarMontado = in_array($modulo['nombre'], ['Atado de Julio', 'Atado de Julio (Tej.)', 'Notificar Montado de Julio', 'Notificar Montado de Julio (Tej.)']);
                $esNotificarCortado = in_array($modulo['nombre'], ['Cortado de Rollo', 'Cortado de Rollo (Tej.)', 'Notificar Cortado de rollo', 'Notificar Cortado de Rollo', 'Notificar Cortado de Rollo (Tej.)']);
            @endphp
            
            @if($esNotificarMontado)
                <a href="javascript:void(0)" onclick="abrirModalTelares()"
                   class="block group relative overflow-visible min-h-[48px] min-w-[48px] touch-manipulation ripple-effect {{ $itemClasses }}">
            @elseif($esNotificarCortado)
                <a href="javascript:void(0)" onclick="abrirModalCortadoRollos()"
                   class="block group relative overflow-visible min-h-[48px] min-w-[48px] touch-manipulation ripple-effect {{ $itemClasses }}">
            @else
                <a href="{{ isset($modulo['ruta_tipo']) && $modulo['ruta_tipo'] === 'route' ? route($modulo['ruta'], $modulo['params'] ?? []) : url($modulo['ruta']) }}"
                   class="block group relative overflow-visible min-h-[48px] min-w-[48px] touch-manipulation ripple-effect {{ $itemClasses }}">
            @endif
                <div class="p-2 md:p-3 lg:p-4 flex flex-col items-center justify-center min-h-[7rem] md:min-h-[8rem] lg:min-h-[9rem] transition-all duration-300 transform hover:scale-105 active:scale-[0.98]">

                    <!-- Contenedor de imagen -->
                    <div class="flex-shrink-0 mb-1.5">
                        <div class="relative transform transition-transform duration-300 group-hover:-translate-y-0.5">

                            <img src="{{ $imagenUrl }}"
                                alt="{{ $modulo['nombre'] }}"
                                class="w-20 h-20 md:w-28 md:h-28 lg:w-32 lg:h-32 object-cover rounded-lg shadow-md group-hover:shadow-xl transition-shadow duration-300"
                                onerror="this.src='{{ $imagenFallback }}'; this.onerror=null;"
                                title="{{ $modulo['nombre'] }} - {{ $modulo['imagen'] ?? 'Sin imagen' }}"
                                loading="lazy"
                                decoding="async"
                                fetchpriority="low">
                        </div>
                    </div>

                    <!-- Texto del módulo -->
                    <div class="text-center px-1 -mt-1">
                        <h2 class="font-bold text-white leading-tight group-hover:text-blue-100 transition-colors duration-300 text-xs md:text-xs lg:text-sm break-words drop-shadow-lg bg-black/50 px-1.5 py-0.5 rounded backdrop-blur-sm max-w-full">
                            {{ $modulo['nombre'] }}
                        </h2>
                    </div>
                </div>
            </a>
    @endforeach
    </div>
</div>
