{{-- Estado vacío de los detalles de Trazabilidad. --}}
@props(['icono' => 'inbox', 'titulo', 'texto' => null])

<flux:card {{ $attributes->class('flex flex-col items-center gap-2 border-dashed px-6 py-10 text-center') }}>
    <flux:icon :icon="$icono" class="size-7 text-zinc-400" />
    <flux:heading size="lg" level="4">{{ $titulo }}</flux:heading>
    @if ($texto)
        <flux:text class="max-w-md">{{ $texto }}</flux:text>
    @endif
    {{ $slot }}
</flux:card>
