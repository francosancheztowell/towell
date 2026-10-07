{{-- Estado de un SYSMonError como badge de Flux: nuevo · visto · resuelto · ignorado. --}}
@php
    $color = ['nuevo' => 'red', 'visto' => 'amber', 'resuelto' => 'green', 'ignorado' => 'zinc'][$estado] ?? 'zinc';
@endphp
<flux:badge size="sm" :color="$color" inset="top bottom" class="shrink-0">{{ ucfirst((string) $estado) }}</flux:badge>
