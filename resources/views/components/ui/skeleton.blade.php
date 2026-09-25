{{--
    Skeleton (DS-07): marcador gris mientras llega el contenido. Sin animación infinita si el
    usuario pidió menos movimiento.

    @prop int    $lines  Renglones de texto (default 3)
    @prop string $shape  'text' (default) | 'block' (usa h-*/w-* del class) | 'circle'

    <x-ui.skeleton :lines="2" />  <x-ui.skeleton shape="block" class="h-32" />
--}}
@props(['lines' => 3, 'shape' => 'text'])

@php $base = 'bg-slate-200 motion-safe:animate-pulse'; @endphp

@if ($shape === 'text')
    <div {{ $attributes->class(['space-y-2']) }} aria-hidden="true">
        @for ($i = 0; $i < max(1, (int) $lines); $i++)
            <div class="h-3 rounded {{ $base }} {{ $i === (int) $lines - 1 && $lines > 1 ? 'w-2/3' : 'w-full' }}"></div>
        @endfor
    </div>
@else
    <div {{ $attributes->class([$base, $shape === 'circle' ? 'rounded-full' : 'rounded-lg']) }} aria-hidden="true"></div>
@endif
