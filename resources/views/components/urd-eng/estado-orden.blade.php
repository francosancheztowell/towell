@php
    $color = match ($estado) {
        'Finalizado' => 'bg-green-100 text-green-800',
        'En Proceso' => 'bg-yellow-100 text-yellow-800',
        'Programado' => 'bg-blue-100 text-blue-800',
        'Parcial' => 'bg-amber-100 text-amber-800',
        'Cancelado' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-800',
    };
@endphp
<span class="inline-block whitespace-nowrap rounded px-2 py-1 text-xs font-semibold {{ $color }}">{{ $estado ?: '—' }}</span>
