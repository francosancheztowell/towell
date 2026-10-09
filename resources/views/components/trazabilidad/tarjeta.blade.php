{{--
    Tarjeta del resumen de Trazabilidad: flux:card con encabezado fijo y cuerpo que llena el alto.
    @prop string      $titulo
    @prop string|null $detalle  Destino de "Ver detalle" (data-resumen-detalle); null = sin botón.
--}}
@props(['titulo', 'detalle' => null])

<flux:card {{ $attributes->class('flex min-h-72 flex-col overflow-hidden !p-0') }}>
    <header class="flex min-h-14 items-center justify-between gap-3 border-b border-zinc-200 px-4 py-2">
        <flux:heading size="lg" level="3">{{ $titulo }}</flux:heading>
        @if ($detalle)
            <flux:button size="sm" variant="ghost" icon:trailing="arrow-right" class="min-h-touch"
                         data-resumen-detalle="{{ $detalle }}">
                Ver detalle
            </flux:button>
        @endif
    </header>
    <div class="flex min-h-0 flex-1 flex-col">
        {{ $slot }}
    </div>
</flux:card>
