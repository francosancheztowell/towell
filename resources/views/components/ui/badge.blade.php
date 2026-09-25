{{--
    Badge (DS-06): etiqueta de estado corta. Receta: docs/cerebro-towell/Arquitectura/receta-componentes.md

    @prop string $tone  'neutral' (default) | 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'accent'
    @prop string $icon  Clase Font Awesome opcional ('fa-check')
    @prop bool   $dot   Punto de color a la izquierda en vez de icono

    <x-ui.badge tone="success" icon="fa-check">Liberada</x-ui.badge>
--}}
@props(['tone' => 'neutral', 'icon' => null, 'dot' => false])

@php
    $tonos = [
        'neutral' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'primary' => 'bg-primary-soft text-blue-700 ring-blue-200',
        'success' => 'bg-success-soft text-green-700 ring-green-200',
        'warning' => 'bg-warning-soft text-amber-800 ring-amber-200',
        'danger' => 'bg-danger-soft text-red-700 ring-red-200',
        'info' => 'bg-info-soft text-sky-700 ring-sky-200',
        'accent' => 'bg-purple-50 text-purple-700 ring-purple-200',
    ];
    $puntos = [
        'neutral' => 'bg-slate-400', 'primary' => 'bg-primary', 'success' => 'bg-success',
        'warning' => 'bg-warning', 'danger' => 'bg-danger', 'info' => 'bg-info', 'accent' => 'bg-accent',
    ];
    $clase = $tonos[$tone] ?? $tonos['neutral'];
@endphp

<span {{ $attributes->class(["inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-caption font-semibold ring-1 ring-inset {$clase}"]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $puntos[$tone] ?? $puntos['neutral'] }}" aria-hidden="true"></span>
    @elseif ($icon)
        <i class="fa-solid {{ str_starts_with($icon, 'fa-') ? $icon : 'fa-'.$icon }}" aria-hidden="true"></i>
    @endif
    {{ $slot }}
</span>
