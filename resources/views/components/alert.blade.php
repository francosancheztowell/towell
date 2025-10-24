{{--
    Componente: Alert

    Descripción:
        Muestra mensajes de alerta con diferentes tipos (error, success, warning, info).
        Proporciona feedback visual consistente al usuario sobre el estado de las operaciones.

    Props:
        @param string $type - Tipo de alerta: 'error', 'success', 'warning', 'info' (default: 'info')
        @param string $title - Título de la alerta (opcional)
        @param string $message - Mensaje principal de la alerta (opcional)
        @param array $items - Lista de mensajes para mostrar como bullets (opcional)
        @param bool $dismissible - Si la alerta puede cerrarse (default: true)

    Uso:
        <!-- Alerta simple -->
        <x-alert type="success" title="¡Éxito!" message="Operación completada correctamente" />

        <!-- Alerta con lista de errores -->
        <x-alert type="error" title="Errores encontrados" :items="$errors->all()" />

        <!-- Alerta con slot personalizado -->
        <x-alert type="warning">
            <p>Tu contenido personalizado aquí</p>
        </x-alert>

    Ejemplos:
        1. Mensaje de éxito simple
        2. Lista de errores de validación
        3. Advertencias con iconos
        4. Mensajes informativos
--}}

@props([
    'type' => 'info',
    'title' => null,
    'message' => null,
    'items' => [],
    'dismissible' => true
])

@php
    // Configuración de colores y estilos según el tipo
    $config = [
        'error' => [
            'bg' => 'bg-red-100',
            'border' => 'border-red-400',
            'text' => 'text-red-700',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />'
        ],
        'success' => [
            'bg' => 'bg-green-100',
            'border' => 'border-green-400',
            'text' => 'text-green-700',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />'
        ],
        'warning' => [
            'bg' => 'bg-yellow-100',
            'border' => 'border-yellow-400',
            'text' => 'text-yellow-700',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-1.964-1.333-2.732 0L3.732 16c-.77 1.333.192 3 1.732 3z" />'
        ],
        'info' => [
            'bg' => 'bg-blue-100',
            'border' => 'border-blue-400',
            'text' => 'text-blue-700',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />'
        ]
    ];

    $currentConfig = $config[$type] ?? $config['info'];
@endphp

<div {{ $attributes->merge(['class' => "{$currentConfig['bg']} border {$currentConfig['border']} {$currentConfig['text']} px-4 py-3 rounded relative mb-4 shadow-sm"]) }} role="alert">
    <div class="flex items-start">
        <!-- Icono -->
        <div class="flex-shrink-0">
            <svg class="w-5 h-5 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {!! $currentConfig['icon'] !!}
            </svg>
        </div>

        <!-- Contenido -->
        <div class="flex-1">
            @if($title)
                <strong class="font-bold block mb-1">{{ $title }}</strong>
            @endif

            @if($message)
                <span class="block">{{ $message }}</span>
            @endif

            @if(!empty($items))
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    @foreach($items as $item)
                        <li class="text-sm">{{ $item }}</li>
                    @endforeach
                </ul>
            @endif

            @if($slot->isNotEmpty())
                <div class="mt-1">
                    {{ $slot }}
                </div>
            @endif
        </div>

        <!-- Botón de cerrar (opcional) -->
        @if($dismissible)
            <button type="button" class="ml-3 flex-shrink-0 inline-flex text-current opacity-75 hover:opacity-100 transition-opacity"
                    onclick="this.parentElement.parentElement.remove()">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        @endif
    </div>
</div>




















