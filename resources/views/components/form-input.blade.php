{{--
    Componente: Form Input

    Descripción:
        Input de formulario reutilizable con label, validación y diferentes tipos.
        Proporciona una interfaz consistente para campos de texto, números, email, etc.

    Props:
        @param string $name - Nombre del campo (requerido)
        @param string $label - Label del campo (opcional)
        @param string $type - Tipo de input: 'text', 'number', 'email', 'password', 'date', 'time' (default: 'text')
        @param string $value - Valor del campo (opcional)
        @param bool $required - Si el campo es requerido (default: false)
        @param string $placeholder - Placeholder del input (opcional)
        @param string $labelWidth - Ancho del label (default: 'w-28')
        @param bool $inline - Si el label debe estar en línea (default: true)
        @param string $help - Texto de ayuda debajo del input (opcional)

    Uso:
        <!-- Input de texto simple -->
        <x-form-input
            name="nombre"
            label="Nombre:"
            required
        />

        <!-- Input numérico con valor -->
        <x-form-input
            name="cantidad"
            label="Cantidad:"
            type="number"
            :value="old('cantidad', 10)"
        />

        <!-- Input con ayuda -->
        <x-form-input
            name="email"
            label="Email:"
            type="email"
            help="Ingrese un email válido"
        />

    Ejemplos:
        1. Campos de texto en formularios
        2. Campos numéricos para cantidades
        3. Campos de fecha y hora
--}}

@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'placeholder' => null,
    'labelWidth' => 'w-28',
    'inline' => true,
    'help' => null
])

@php
    $containerClass = $inline ? 'flex items-center gap-2' : 'space-y-2';
    $value = $value ?? old($name);
@endphp

<div class="{{ $containerClass }}">
    @if($label)
        <label for="{{ $name }}" class="{{ $labelWidth }} text-base font-semibold text-gray-800">
            {{ $label }}
        </label>
    @endif

    <div class="flex-1">
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $name }}"
            value="{{ $value }}"
            placeholder="{{ $placeholder }}"
            {{ $required ? 'required' : '' }}
            {{ $attributes->merge(['class' => 'w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors']) }}
        />

        @if($help)
            <p class="text-xs text-gray-500 mt-1">{{ $help }}</p>
        @endif

        <!-- Mostrar error de validación si existe -->
        @error($name)
            <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
        @enderror
    </div>
</div>







































