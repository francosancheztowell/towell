{{--
    Componente: Button (DS-05). Receta: docs/cerebro-towell/Arquitectura/receta-componentes.md

    Props:
        @param string $variant  Planos (paleta de x-navbar.button-*, preferidos en código nuevo):
                                'create' azul, 'edit' morado, 'delete' rojo, 'report' fantasma morado,
                                'neutral' gris, 'ghost' fantasma gris.
                                Con degradado (los de siempre, sin cambios):
                                'primary' (default), 'success', 'danger', 'warning', 'secondary'.
        @param string $size     Planos: 'touch' (default, alto >= 44 px), 'nav' (como los botones del
                                navbar), 'icon' (circular 44 px, requiere `label`).
                                Con degradado: 'sm', 'md' (default), 'lg'.
        @param string $type     'button' | 'submit' | 'reset' (default 'button')
        @param string $href     Si viene, se pinta un <a> con el mismo estilo
        @param string $icon     Clase Font Awesome ('fa-plus', 'fa-solid fa-plus') o uno de los SVG
                                predefinidos: check, plus, trash, edit, save
        @param string $label    Texto accesible (obligatorio si no hay texto visible)
        @param bool   $loading  Deshabilita y muestra un spinner (default false)
        @param bool   $fullWidth

    Uso:
        <x-ui.button>Guardar</x-ui.button>
        <x-ui.button variant="create" icon="fa-plus">Crear</x-ui.button>
        <x-ui.button variant="delete" size="icon" icon="fa-trash" label="Eliminar" />
        <x-ui.button variant="ghost" href="{{ url('/') }}">Volver</x-ui.button>
--}}

@props([
    'variant' => 'primary',
    'size' => null,
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'label' => null,
    'loading' => false,
    'fullWidth' => false
])

@php
    // Con degradado: se conservan tal cual (los usa el panel /admin).
    $variantsDegradado = [
        'primary' => 'bg-gradient-to-r from-blue-500 via-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white ring-blue-300/40',
        'success' => 'bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white ring-green-300/40',
        'danger' => 'bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white ring-red-300/40',
        'warning' => 'bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-white ring-yellow-300/40',
        'secondary' => 'bg-gray-500 hover:bg-gray-600 text-white ring-gray-300/40',
    ];

    // Planos: misma paleta que x-navbar.button-create/edit/delete/report.
    $variantsPlanos = [
        'create' => 'bg-primary hover:bg-primary-hover text-white',
        'edit' => 'bg-accent hover:bg-accent-hover text-white',
        'delete' => 'bg-danger hover:bg-danger-hover text-white',
        'report' => 'text-purple-600 hover:bg-purple-100',
        'neutral' => 'bg-gray-500 hover:bg-gray-600 text-white',
        'ghost' => 'text-slate-600 hover:bg-slate-100',
    ];

    $plano = isset($variantsPlanos[$variant]);

    if ($plano) {
        $sizes = [
            'touch' => 'min-h-touch px-4 py-2 text-sm gap-2 rounded-lg',
            'nav' => 'px-3 py-2 text-sm gap-2 rounded-lg',
            'icon' => 'size-touch rounded-full',
        ];
        $currentSize = $sizes[$size ?? 'touch'] ?? $sizes['touch'];
        $clases = "inline-flex items-center justify-center {$currentSize} font-medium {$variantsPlanos[$variant]} transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed aria-disabled:opacity-50 aria-disabled:pointer-events-none";
    } else {
        $sizes = [
            'sm' => 'px-3 py-1.5 text-sm',
            'md' => 'px-4 py-2 text-base',
            'lg' => 'px-6 py-3 text-lg',
        ];
        $currentVariant = $variantsDegradado[$variant] ?? $variantsDegradado['primary'];
        $currentSize = $sizes[$size ?? 'md'] ?? $sizes['md'];
        $clases = "inline-flex items-center justify-center {$currentSize} rounded-xl font-semibold {$currentVariant} transition-all duration-200 shadow-lg ring-1 disabled:opacity-50 disabled:cursor-not-allowed";
    }

    if ($fullWidth) {
        $clases = str_replace('inline-flex', 'inline-flex w-full', $clases);
    }

    // Iconos predefinidos (SVG)
    $icons = [
        'check' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />',
        'plus' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />',
        'trash' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />',
        'edit' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />',
        'save' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />',
    ];

    $iconoFa = $icon && str_contains($icon, 'fa-') && ! isset($icons[$icon])
        ? (str_starts_with($icon, 'fa-') ? 'fa-solid '.$icon : $icon)
        : null;
    $conTexto = $slot->isNotEmpty();
    $separacion = $plano || ! $conTexto ? '' : 'mr-2';

    $tag = $href ? 'a' : 'button';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @if ($loading) aria-disabled="true" @endif
    @else type="{{ $type }}" @endif
    @if ($label) aria-label="{{ $label }}" @if (! $conTexto) title="{{ $label }}" @endif @endif
    @if ($loading) aria-busy="true" @endif
    {{ $attributes->merge(['class' => $clases]) }}
    @if ($loading && ! $href) disabled @endif
>
    @if($loading)
        <!-- Spinner de carga -->
        <svg class="animate-spin {{ $plano ? '' : '-ml-1 mr-2' }} h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    @elseif($iconoFa)
        <i class="{{ $iconoFa }} {{ $separacion }}" aria-hidden="true"></i>
    @elseif($icon && isset($icons[$icon]))
        <!-- Icono predefinido -->
        <svg class="w-5 h-5 {{ $plano ? '' : 'mr-2' }}" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            {!! $icons[$icon] !!}
        </svg>
    @endif

    @if ($conTexto || ! $plano)
        <span>{{ $slot }}</span>
    @endif
</{{ $tag }}>
