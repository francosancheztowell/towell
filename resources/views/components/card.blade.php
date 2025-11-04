{{--
    Componente: Card

    Descripción:
        Tarjeta/contenedor reutilizable con estilos consistentes.
        Proporciona un contenedor visual para agrupar contenido relacionado.

    Props:
        @param string $title - Título de la tarjeta (opcional)
        @param string $subtitle - Subtítulo de la tarjeta (opcional)
        @param bool $shadow - Si debe tener sombra (default: true)
        @param bool $border - Si debe tener borde (default: true)
        @param bool $rounded - Si debe tener bordes redondeados (default: true)
        @param string $padding - Padding interno: 'none', 'sm', 'md', 'lg' (default: 'md')
        @param string $bg - Color de fondo: 'white', 'gray', 'blue' (default: 'white')

    Uso:
        <!-- Card simple -->
        <x-card title="Información">
            <p>Contenido de la tarjeta</p>
        </x-card>

        <!-- Card con header y footer slots -->
        <x-card>
            <x-slot:header>
                <h3>Header personalizado</h3>
            </x-slot:header>

            <p>Contenido principal</p>

            <x-slot:footer>
                <button>Acción</button>
            </x-slot:footer>
        </x-card>

        <!-- Card sin padding -->
        <x-card padding="none">
            <img src="..." class="w-full">
        </x-card>

    Ejemplos:
        1. Contenedor de formularios
        2. Panel de información
        3. Tarjeta de resultados
--}}

@props([
    'title' => null,
    'subtitle' => null,
    'shadow' => true,
    'border' => true,
    'rounded' => true,
    'padding' => 'md',
    'bg' => 'white'
])

@php
    // Configuración de colores de fondo
    $backgrounds = [
        'white' => 'bg-white',
        'gray' => 'bg-gray-50',
        'blue' => 'bg-blue-50',
    ];

    // Configuración de padding
    $paddings = [
        'none' => 'p-0',
        'sm' => 'p-3',
        'md' => 'p-4 md:p-6',
        'lg' => 'p-6 md:p-8',
    ];

    $currentBg = $backgrounds[$bg] ?? $backgrounds['white'];
    $currentPadding = $paddings[$padding] ?? $paddings['md'];

    // Clases condicionales
    $shadowClass = $shadow ? 'shadow-xl' : '';
    $borderClass = $border ? 'border border-gray-200' : '';
    $roundedClass = $rounded ? 'rounded-2xl' : '';
@endphp

<div {{ $attributes->merge(['class' => "{$currentBg} {$shadowClass} {$borderClass} {$roundedClass} overflow-hidden"]) }}>
    <!-- Header (título predefinido o slot personalizado) -->
    @if($title || isset($header))
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200 px-4 md:px-6 py-3">
            @if(isset($header))
                {{ $header }}
            @else
                <h3 class="text-lg md:text-xl font-bold text-gray-800">
                    {{ $title }}
                </h3>
                @if($subtitle)
                    <p class="text-sm text-gray-600 mt-1">{{ $subtitle }}</p>
                @endif
            @endif
        </div>
    @endif

    <!-- Contenido principal -->
    <div class="{{ $currentPadding }}">
        {{ $slot }}
    </div>

    <!-- Footer (opcional) -->
    @isset($footer)
        <div class="bg-gray-50 border-t border-gray-200 px-4 md:px-6 py-3">
            {{ $footer }}
        </div>
    @endisset
</div>








































