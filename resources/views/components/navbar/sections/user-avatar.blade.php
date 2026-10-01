@php
    $usuario = Auth::user();
@endphp

{{-- Disparador del flux:dropdown del navbar: Flux le pone aria-expanded y maneja el foco. --}}
<button type="button" aria-label="Menú de usuario: {{ $usuario->nombre }}"
        class="size-touch rounded-full shadow-md hover:shadow-lg transition-shadow">
    <flux:avatar circle color="blue" initials:single class="size-full"
                 :name="$usuario->nombre" :src="getFotoUsuarioUrl($usuario->foto ?? null)"
                 :alt="'Foto de '.$usuario->nombre" />
</button>
