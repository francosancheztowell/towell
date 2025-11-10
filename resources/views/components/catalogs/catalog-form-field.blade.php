{{--
    Componente: Catalog Form Field
    Campo de formulario reutilizable para modales de catálogo

    Props:
        @param string $name - Nombre del campo
        @param string $label - Etiqueta del campo
        @param string $type - Tipo de input (text, number, select, etc.)
        @param mixed $value - Valor por defecto
        @param bool $required - Si el campo es requerido
        @param string $placeholder - Placeholder del input
        @param array $options - Opciones para select
        @param string $prefix - Prefijo para el ID del input (swal- o swal-edit-)
--}}

@props([
    'name',
    'label',
    'type' => 'text',
    'value' => '',
    'required' => false,
    'placeholder' => '',
    'options' => [],
    'prefix' => 'swal-',
    'maxlength' => null,
    'step' => null,
    'min' => null,
    'max' => null
])

@php
    $inputId = $prefix . $name;
    $inputName = $name;
    $inputValue = $value;
    $classes = 'w-full px-2 py-2 border border-gray-300 rounded text-center';
    if ($type === 'number') {
        $classes .= ' text-sm';
    }
@endphp

<div class="{{ $props['colSpan'] ?? 'col-span-1' }}">
    <label class="block text-xs font-medium text-gray-600 mb-1">
        {{ $label }}
        @if($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    
    @if($type === 'select')
        <select id="{{ $inputId }}"
                name="{{ $inputName }}"
                class="{{ $classes }}"
                {{ $required ? 'required' : '' }}>
            <option value="">Seleccionar</option>
            @foreach($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" {{ $inputValue == $optionValue ? 'selected' : '' }}>
                    {{ $optionLabel }}
                </option>
            @endforeach
        </select>
    @elseif($type === 'textarea')
        <textarea id="{{ $inputId }}"
                  name="{{ $inputName }}"
                  class="{{ $classes }}"
                  placeholder="{{ $placeholder }}"
                  {{ $required ? 'required' : '' }}
                  {{ $maxlength ? "maxlength={$maxlength}" : '' }}>{{ $inputValue }}</textarea>
    @else
        <input type="{{ $type }}"
               id="{{ $inputId }}"
               name="{{ $inputName }}"
               value="{{ $inputValue }}"
               placeholder="{{ $placeholder }}"
               class="{{ $classes }}"
               {{ $required ? 'required' : '' }}
               {{ $maxlength ? "maxlength={$maxlength}" : '' }}
               {{ $step ? "step={$step}" : '' }}
               {{ $min !== null ? "min={$min}" : '' }}
               {{ $max !== null ? "max={$max}" : '' }}>
    @endif
</div>

