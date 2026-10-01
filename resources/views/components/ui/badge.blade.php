{{--
    Badge (DS-06): etiqueta de estado corta. Envuelve flux:badge: aquí solo se traduce el tono
    semántico a un color de Flux. Receta: docs/cerebro-towell/Arquitectura/receta-componentes.md

    @prop string $tone  'neutral' (default) | 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'accent'
    @prop string $icon  Clase Font Awesome opcional ('fa-check')
    @prop bool   $dot   Punto de color a la izquierda en vez de icono

    <x-ui.badge tone="success" icon="fa-check">Liberada</x-ui.badge>
    En vistas nuevas también vale <flux:badge color="green">…</flux:badge> directo.
--}}
@props(['tone' => 'neutral', 'icon' => null, 'dot' => false])

@php
    $color = [
        'neutral' => 'zinc', 'primary' => 'blue', 'success' => 'green', 'warning' => 'amber',
        'danger' => 'red', 'info' => 'sky', 'accent' => 'purple',
    ][$tone] ?? 'zinc';
@endphp

<flux:badge size="sm" rounded :color="$color" {{ $attributes }}>
    @if ($dot)
        <span class="me-1.5 size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    @elseif ($icon)
        <i class="fa-solid {{ str_starts_with($icon, 'fa-') ? $icon : 'fa-'.$icon }} me-1" aria-hidden="true"></i>
    @endif
    {{ $slot }}
</flux:badge>
