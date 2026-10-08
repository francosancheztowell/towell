{{-- Estado de un SYSMonError como punto discreto (listas). El texto va para el lector de pantalla y al pasar. --}}
@php
    $color = ['nuevo' => 'bg-(--adm-err)', 'visto' => 'bg-(--adm-warn)', 'resuelto' => 'bg-(--adm-ok)', 'ignorado' => 'bg-(--adm-ink-3)'][$estado] ?? 'bg-(--adm-ink-3)';
@endphp
<span class="inline-block size-2 shrink-0 rounded-full {{ $color }}" title="{{ ucfirst((string) $estado) }}"><span class="sr-only">{{ ucfirst((string) $estado) }}</span></span>
