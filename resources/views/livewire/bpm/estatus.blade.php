{{-- Status de un folio BPM; mismos colores en la lista y en el checklist. --}}
<flux:badge size="sm" inset="top bottom" :color="['Creado' => 'blue', 'Terminado' => 'amber', 'Autorizado' => 'green'][$status] ?? 'zinc'">{{ $status }}</flux:badge>
