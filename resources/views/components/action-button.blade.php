{{--
    Componente: Action Button

    Descripción:
        Botón de acción reutilizable con diferentes variantes y estilos.
        Soporta iconos, estados de carga y diferentes tamaños.

    Props:
        @param string $variant - Variante del botón: 'primary', 'success', 'danger', 'warning', 'secondary' (default: 'primary')
        @param string $size - Tamaño: 'sm', 'md', 'lg' (default: 'md')
        @param string $type - Tipo de botón: 'button', 'submit', 'reset' (default: 'button')
        @param string $icon - Icono SVG path (opcional)
        @param bool $loading - Si el botón está en estado de carga (default: false)
        @param bool $fullWidth - Si el botón debe ocupar todo el ancho (default: false)

    Uso:
        <!-- Botón primario simple -->
        <x-action-button>
            Guardar
        </x-action-button>

        <!-- Botón de éxito con icono -->
        <x-action-button variant="success" type="submit" icon="check">
            Confirmar
        </x-action-button>

        <!-- Botón en estado de carga -->
        <x-action-button :loading="true">
            Procesando...
        </x-action-button>

    Ejemplos:
        1. Botón de submit en formularios
        2. Botón de acción principal
        3. Botón de cancelar o eliminar
--}}

@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'icon' => null,
    'loading' => false,
    'fullWidth' => false
])

@php
    // Configuración de variantes
    $variants = [
        'primary' => 'bg-gradient-to-r from-blue-500 via-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white ring-blue-300/40',
        'success' => 'bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white ring-green-300/40',
        'danger' => 'bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white ring-red-300/40',
        'warning' => 'bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-white ring-yellow-300/40',
        'secondary' => 'bg-gray-500 hover:bg-gray-600 text-white ring-gray-300/40',
    ];

    // Configuración de tamaños
    $sizes = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-base',
        'lg' => 'px-6 py-3 text-lg',
    ];

    $currentVariant = $variants[$variant] ?? $variants['primary'];
    $currentSize = $sizes[$size] ?? $sizes['md'];

    // Iconos predefinidos
    $icons = [
        'check' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />',
        'plus' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />',
        'trash' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />',
        'edit' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />',
        'save' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />',
    ];

    $widthClass = $fullWidth ? 'w-full' : '';
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => "inline-flex items-center justify-center {$currentSize} {$widthClass} rounded-xl font-semibold {$currentVariant} transition-all duration-200 shadow-lg ring-1 disabled:opacity-50 disabled:cursor-not-allowed"]) }}
    {{ $loading ? 'disabled' : '' }}
>
    @if($loading)
        <!-- Spinner de carga -->
        <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    @elseif($icon && isset($icons[$icon]))
        <!-- Icono predefinido -->
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            {!! $icons[$icon] !!}
        </svg>
    @endif

    <span>{{ $slot }}</span>
</button>











