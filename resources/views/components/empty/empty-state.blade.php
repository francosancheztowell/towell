{{--
    Estado vacío (DS-08). Receta: docs/cerebro-towell/Arquitectura/receta-componentes.md

    @prop string $title
    @prop string $message
    @prop string $icon    'default' | 'config' (SVG de siempre) o una clase Font Awesome ('fa-inbox')
    @slot default         Acción opcional (p. ej. <x-ui.button variant="create">Crear</x-ui.button>)

    Dentro de una tabla usar x-ui.table-empty (es una fila <tr>).
--}}
@props(['title', 'message' => null, 'icon' => 'default'])

<div {{ $attributes->class(['text-center py-12']) }}>
    <div class="bg-gray-100 rounded-2xl p-8 max-w-md mx-auto">
        @if(str_starts_with($icon, 'fa-'))
            <i class="{{ str_starts_with($icon, 'fa-solid') ? $icon : 'fa-solid '.$icon }} block text-5xl text-gray-400 mx-auto mb-4" aria-hidden="true"></i>
        @elseif($icon === 'config')
            <!-- Icono de configuración -->
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        @else
            <!-- Icono por defecto -->
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709" />
            </svg>
        @endif

        <h3 class="text-lg font-semibold text-gray-600 mb-2">{{ $title }}</h3>
        @if (filled($message))
            <p class="text-gray-500">{{ $message }}</p>
        @endif
        @if ($slot->isNotEmpty())
            <div class="mt-4 flex justify-center">{{ $slot }}</div>
        @endif
    </div>
</div>
