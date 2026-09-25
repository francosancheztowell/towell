{{--
    Field (DS-04): label ligado, marca de obligatorio, ayuda y error de validación.
    Receta: docs/cerebro-towell/Arquitectura/receta-componentes.md

    @prop string $name      Nombre del campo; el error se toma de $errors->first($name)
    @prop string $label
    @prop string $id        Default: $name. El control del slot debe usar este id.
    @prop bool   $required
    @prop string $hint      Texto de ayuda bajo el control
    @prop string $error     Error explícito (p. ej. desde JS/Livewire); gana sobre $errors
    @prop string $as        Si viene ('input'|'textarea'|'select'), el componente pinta el control
                            con las clases estándar y pasa el resto de atributos a él.

    Con control propio (slot):
        <x-ui.field name="Porcentaje" label="Porcentaje" required hint="0 a 100">
            <input id="Porcentaje" name="Porcentaje" type="number" class="ui-input" ...>
        </x-ui.field>
    Con control generado:
        <x-ui.field as="input" name="MaquinaId" label="Máquina ID" required placeholder="KM-01" />
--}}
@props(['name', 'label', 'id' => null, 'required' => false, 'hint' => null, 'error' => null, 'as' => null, 'value' => null])

@php
    $id ??= $name;
    $mensaje = $error ?? (isset($errors) ? $errors->first($name) : null);
    $describe = collect([$hint ? $id.'-ayuda' : null, $mensaje ? $id.'-error' : null])->filter()->implode(' ');
    $control = 'w-full min-h-touch rounded-lg border bg-white px-3 py-2 text-sm text-ink transition-all duration-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 hover:shadow-sm '
        .($mensaje ? 'border-red-400' : 'border-gray-300');
@endphp

<div class="ui-field">
    <label for="{{ $id }}" class="mb-1 block text-sm font-medium text-gray-700">
        {{ $label }} @if ($required)<span class="text-red-500" aria-hidden="true">*</span>@endif
    </label>

    @if ($as === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif
            @if ($describe) aria-describedby="{{ $describe }}" @endif @if ($mensaje) aria-invalid="true" @endif
            {{ $attributes->class([$control]) }}>{{ $value }}</textarea>
    @elseif ($as === 'select')
        <select id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif
            @if ($describe) aria-describedby="{{ $describe }}" @endif @if ($mensaje) aria-invalid="true" @endif
            {{ $attributes->class([$control]) }}>{{ $slot }}</select>
    @elseif ($as)
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $as === 'input' ? 'text' : $as }}" @if ($required) required @endif
            @if (! is_null($value)) value="{{ $value }}" @endif
            @if ($describe) aria-describedby="{{ $describe }}" @endif @if ($mensaje) aria-invalid="true" @endif
            {{ $attributes->class([$control]) }}>
    @else
        {{ $slot }}
    @endif

    @if ($hint)
        <p id="{{ $id }}-ayuda" class="mt-1 text-caption text-ink-muted">{{ $hint }}</p>
    @endif
    <p id="{{ $id }}-error" class="mt-1 text-caption font-medium text-red-600" data-ui-field-error @if (! $mensaje) hidden @endif>{{ $mensaje }}</p>
</div>
