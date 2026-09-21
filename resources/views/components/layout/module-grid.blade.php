@props(['modulos', 'columns' => 'xl:grid-cols-5', 'filterConfig' => true, 'imageFolder' => 'fotos_modulos', 'isSubmodulos' => false])

@php
    $imagenFallbackWebp = asset('images/fondosTowell/TOWELLIN.webp');
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

    // El href se calcula una sola vez: lo usan la tarjeta y las speculation rules.
    $modulosFiltrados = $modulosFiltrados->map(function ($modulo) {
        $modulo['href'] = ($modulo['ruta_tipo'] ?? null) === 'route'
            ? route($modulo['ruta'], $modulo['params'] ?? [])
            : url($modulo['ruta']);

        return $modulo;
    });

    $cantidadModulos = $modulosFiltrados->count();

    // Prefetch nativo del navegador: al tocar/pasar sobre una tarjeta, Chrome pide
    // su HTML por adelantado, así el click ya no espera al servidor. Solo rutas
    // propias; las externas y el login quedan fuera.
    $origen = rtrim(url('/'), '/');
    $rutasPrefetch = $modulosFiltrados
        ->pluck('href')
        ->filter(fn ($href) => is_string($href) && str_starts_with($href, $origen.'/'))
        ->map(fn ($href) => substr($href, strlen($origen)))
        ->reject(fn ($ruta) => str_starts_with($ruta, '/login'))
        ->unique()
        ->values();

    // Con 3 o menos modulos se centran y se les da mas aire; con mas, grid normal.
    $gridClasses = match (true) {
        $cantidadModulos === 1 => 'grid grid-cols-1 justify-items-center',
        $cantidadModulos === 2 => 'grid grid-cols-2 justify-items-center',
        $cantidadModulos === 3 => 'grid grid-cols-1 sm:grid-cols-3 justify-items-center',
        default => 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-3 ' . $columns . ' justify-items-center',
    };

    $gapClasses = match (true) {
        $cantidadModulos === 1 => '',
        $cantidadModulos === 2 => 'gap-4 md:gap-6 lg:gap-8',
        $cantidadModulos === 3 => 'gap-4 md:gap-5 lg:gap-6',
        default => 'gap-2 md:gap-3 lg:gap-4',
    };
@endphp

<div class="w-full flex justify-center items-start px-3 py-4">
    <div class="{{ $gridClasses }} {{ $gapClasses }} max-w-5xl mx-auto">
    @foreach ($modulosFiltrados->values() as $index => $modulo)
            @php
                // Si el archivo no existe, usar fallback: un 404 de imagen bootea Laravel completo (~1s c/u)
                if (!empty($modulo['imagen']) && file_exists(public_path('images/' . $imageFolder . '/' . $modulo['imagen']))) {
                    $relativeImagePath = 'images/' . $imageFolder . '/' . $modulo['imagen'];
                    $absoluteImagePath = public_path($relativeImagePath);
                    $version = filemtime($absoluteImagePath);
                    $imagenUrl = asset($relativeImagePath) . '?v=' . $version;

                    $pathInfo = pathinfo($relativeImagePath);
                    $webpRelativeImagePath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
                    $webpAbsoluteImagePath = public_path($webpRelativeImagePath);
                    $webpVersion = file_exists($webpAbsoluteImagePath) ? filemtime($webpAbsoluteImagePath) : null;
                    $imagenWebpUrl = $webpVersion ? asset($webpRelativeImagePath) . '?v=' . $webpVersion : null;
                } else {
                    $imagenUrl = $imagenFallback;
                    $imagenWebpUrl = $imagenFallbackWebp;
                }

                $isLcpImage = $index === 0;
            @endphp

            <a href="{{ $modulo['href'] }}"
               class="block group relative overflow-visible min-h-[48px] min-w-[48px] touch-manipulation ripple-effect">
                <div class="p-4 md:p-5 lg:p-6 flex flex-col items-center justify-center min-h-[10rem] md:min-h-[13rem] lg:min-h-[13rem] transition-all duration-300 transform hover:scale-105 active:scale-[0.98]">

                    <!-- Contenedor de imagen optimizado para tablet -->
                    <div class="flex-shrink-0 mb-3">
                        <div class="relative transform transition-transform duration-300 group-hover:-translate-y-0.5">

                            <picture>
                                @if($imagenWebpUrl)
                                    <source srcset="{{ $imagenWebpUrl }}" type="image/webp">
                                @endif
                                <img src="{{ $imagenUrl }}"
                                    alt="{{ $modulo['nombre'] }}"
                                    width="176"
                                    height="176"
                                    class="w-32 h-32 md:w-44 md:h-44 lg:w-44 lg:h-44 object-cover rounded-xl shadow-md group-hover:shadow-xl transition-shadow duration-300"
                                    onerror="this.src='{{ $imagenFallback }}'; this.onerror=null;"
                                    title="{{ $modulo['nombre'] }} - {{ $modulo['imagen'] ?? 'Sin imagen' }}"
                                    loading="{{ $isLcpImage ? 'eager' : 'lazy' }}"
                                    decoding="async"
                                    fetchpriority="{{ $isLcpImage ? 'high' : 'low' }}">
                            </picture>
                        </div>
                    </div>

                    <!-- Texto del módulo optimizado -->
                    <div class="text-center px-2 -mt-3">
                        <h2 class="font-bold text-white leading-tight group-hover:text-white transition-colors duration-300 text-xs md:text-sm lg:text-sm break-words drop-shadow-lg bg-black/80 px-2 py-1 rounded-md backdrop-blur-sm max-w-full">
                            {{ $modulo['nombre'] }}
                        </h2>
                    </div>
                </div>
            </a>
    @endforeach
    </div>
</div>

@if ($rutasPrefetch->isNotEmpty())
    {{-- "moderate" = solo al pasar el cursor o tocar, no las 12 de golpe al cargar. --}}
    <script type="speculationrules">
        {"prefetch": [{"urls": @json($rutasPrefetch), "eagerness": "moderate"}]}
    </script>
@endif
