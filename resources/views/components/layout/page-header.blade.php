{{--
    Componente: Page Header

    Descripción:
        Header de página con gradiente y estilos consistentes.
        Proporciona un título destacado para diferentes secciones de la aplicación.

    Props:
        @param string $title - Título principal del header (requerido)
        @param string $subtitle - Subtítulo opcional
        @param string $gradient - Tipo de gradiente: 'blue', 'yellow', 'green', 'red', 'purple' (default: 'blue')
        @param string $size - Tamaño del texto: 'sm', 'md', 'lg', 'xl' (default: 'lg')
        @param bool $centered - Si el contenido debe estar centrado (default: true)
        @param bool $rounded - Si debe tener bordes redondeados (default: true)

    Uso:
        <!-- Header simple -->
        <x-page-header title="PRODUCCIÓN EN PROCESO" />

        <!-- Header con subtítulo -->
        <x-page-header
            title="PROGRAMACIÓN DE REQUERIMIENTOS"
            subtitle="Seleccione los elementos a programar"
            gradient="yellow"
        />

        <!-- Header personalizado con slot -->
        <x-page-header title="Dashboard">
            <x-slot:actions>
                <button>Actualizar</button>
            </x-slot:actions>
        </x-page-header>

    Ejemplos:
        1. Header de módulo principal
        2. Header de formulario
        3. Header con acciones adicionales
--}}

@props([
    'title',
    'subtitle' => null,
    'badge' => null,
    'gradient' => 'blue',
    'size' => 'lg',
    'centered' => true,
    'rounded' => true,
    'containerClass' => null,
    'headerClass' => null
])

@php
    // Configuración de gradientes
    $gradients = [
        'blue' => 'from-blue-500 via-blue-400 to-blue-600',
        'yellow' => 'from-yellow-200 via-yellow-300 to-yellow-200',
        'green' => 'from-green-500 via-green-400 to-green-600',
        'red' => 'from-red-500 via-red-400 to-red-600',
        'purple' => 'from-purple-500 via-purple-400 to-purple-600',
        'indigo' => 'from-indigo-500 via-indigo-400 to-indigo-600',
    ];

    // Configuración de tamaños de texto
    $sizes = [
        'sm' => 'text-lg md:text-xl',
        'md' => 'text-xl md:text-2xl',
        'lg' => 'text-2xl md:text-3xl',
        'xl' => 'text-3xl md:text-4xl',
    ];

    $currentGradient = $gradients[$gradient] ?? $gradients['blue'];
    $currentSize = $sizes[$size] ?? $sizes['lg'];

    // Determinar color del texto según el gradiente o headerClass personalizado
    $hasCustomHeader = !empty($headerClass);
    if ($hasCustomHeader) {
        // Si hay headerClass personalizado, usar texto oscuro por defecto
        $textColor = 'text-gray-900';
    } else {
        // Si no hay headerClass, usar color según gradiente
        $textColor = in_array($gradient, ['yellow']) ? 'text-slate-800' : 'text-white';
    }

    // Clases adicionales
    $roundedClass = $rounded ? 'rounded-2xl' : '';
    $centerClass = $centered ? 'text-center' : '';

    // Clases personalizadas
    $containerClasses = $containerClass ?? '';
    $headerClasses = $headerClass ?? "bg-gradient-to-r {$currentGradient} {$roundedClass} shadow-lg";
@endphp

<div class="{{ $containerClasses }}">
<div {{ $attributes->merge(['class' => $headerClasses]) }}>
    <div class="p-2 md:p-4">
        <!-- Contenido principal -->
        <div class="flex items-center justify-between gap-4">
            <!-- Título y subtítulo -->
            <div class="flex-1 {{ $centerClass }}">
                <div class="flex items-center gap-3">
                    <h1 class="{{ $currentSize }} font-bold {{ $textColor }} tracking-wide">
                        {{ $title }}
                    </h1>
                    @if($badge)
                        <span class="px-3 py-1 text-sm font-medium {{ $hasCustomHeader ? 'bg-blue-100 text-blue-800' : $textColor . ' bg-white/20' }} rounded-full">
                            {{ $badge }}
                        </span>
                    @endif
                </div>

                @if($subtitle)
                    <p class="text-sm md:text-base {{ $textColor }} mt-1 opacity-90">
                        {{ $subtitle }}
                    </p>
                @endif

                @if($slot->isNotEmpty())
                    <div class="mt-2">
                        {{ $slot }}
                    </div>
                @endif
            </div>

            <!-- Slot para acciones adicionales (botones, etc.) -->
            @isset($actions)
                <div class="flex-shrink-0">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    </div>
</div>
</div>
