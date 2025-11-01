{{--
    Componente: Form Select

    Descripción:
        Select/dropdown reutilizable con label y estilos consistentes.
        Soporta múltiples opciones, valores predeterminados y validación.

    Props:
        @param string $name - Nombre del campo (requerido)
        @param string $label - Label del campo (opcional)
        @param array $options - Array de opciones en formato ['value' => 'label'] (requerido)
        @param string $selected - Valor seleccionado por defecto (opcional)
        @param bool $required - Si el campo es requerido (default: false)
        @param string $placeholder - Texto del placeholder (default: 'Seleccione una opción')
        @param string $labelWidth - Ancho del label (default: 'w-28')
        @param bool $inline - Si el label debe estar en línea con el select (default: true)

    Uso:
        <!-- Select básico -->
        <x-form-select
            name="telar"
            label="Telar:"
            :options="$telares"
            required
        />

        <!-- Select con valor seleccionado -->
        <x-form-select
            name="tipo"
            label="Tipo:"
            :options="['mecanica' => 'Mecánica', 'electrica' => 'Eléctrica']"
            selected="mecanica"
        />

        <!-- Select con rango numérico -->
        <x-form-select
            name="numero"
            label="Número:"
            :options="range(1, 100)"
        />

    Ejemplos:
        1. Select de telares
        2. Select de tipos de falla
        3. Select de categorías
--}}

@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'required' => false,
    'placeholder' => 'Seleccione una opción',
    'labelWidth' => 'w-28',
    'inline' => true
])

@php
    // Convertir array de opciones a formato consistente
    $formattedOptions = [];
    foreach ($options as $key => $value) {
        if (is_array($value)) {
            // Si el valor es un array, asumir formato ['value' => 'label']
            $formattedOptions[$key] = $value;
        } elseif (is_numeric($key)) {
            // Si la clave es numérica, usar el valor como clave y label
            $formattedOptions[$value] = $value;
        } else {
            // Formato normal ['key' => 'value']
            $formattedOptions[$key] = $value;
        }
    }

    // Clases del contenedor
    $containerClass = $inline ? 'flex items-center gap-2' : 'space-y-2';
@endphp

<div class="{{ $containerClass }}">
    @if($label)
        <label for="{{ $name }}" class="{{ $labelWidth }} text-base font-semibold text-gray-800">
            {{ $label }}
        </label>
    @endif

    <select
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $required ? 'required' : '' }}
        {{ $attributes->merge(['class' => 'flex-1 p-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors']) }}
    >
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach($formattedOptions as $value => $label)
            <option
                value="{{ $value }}"
                {{ (string)$selected === (string)$value ? 'selected' : '' }}
            >
                {{ $label }}
            </option>
        @endforeach
    </select>

    <!-- Mostrar error de validación si existe -->
    @error($name)
        <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
    @enderror
</div>

































