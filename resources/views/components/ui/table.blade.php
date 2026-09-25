{{--
    Tabla shell (DS-03): encabezado fijo, variantes, cebra, vacío y cargando.
    x-tabla (CRUD Livewire) se construye encima. Receta: docs/cerebro-towell/Arquitectura/receta-componentes.md

    @prop string $variant  'primary' (encabezado azul, como x-tabla) | 'subtle' (encabezado gris)
    @prop bool   $sticky   Encabezado fijo al hacer scroll (default true)
    @prop bool   $zebra    Filas alternas (default false)
    @prop bool   $loading  Pinta $loadingRows filas skeleton en vez del cuerpo
    @prop int    $columns  Columnas (para el colspan de cargando)
    @slot head             Filas del <thead> (<tr><th>…</th></tr>)
    @slot default          Filas del cuerpo. Si el slot ya trae su propio <tbody> (p. ej. con
                           x-data de Alpine), se usa tal cual en vez de envolverlo.

    <x-ui.table variant="subtle" id="maquinasTable">
        <x-slot:head><tr><th scope="col">Máquina</th></tr></x-slot:head>
        @forelse ($filas as $f) <tr>…</tr> @empty <x-ui.table-empty :colspan="1" message="Sin máquinas" /> @endforelse
    </x-ui.table>
--}}
@props(['variant' => 'primary', 'sticky' => true, 'zebra' => false, 'loading' => false, 'loadingRows' => 5, 'columns' => 1, 'head' => null])

@php
    $cuerpo = $slot->toHtml();
    $traeTbody = str_starts_with(ltrim($cuerpo), '<tbody');
@endphp

<table {{ $attributes->class([
    'min-w-full text-sm ui-table',
    'ui-table--subtle' => $variant === 'subtle',
    'ui-table--primary' => $variant !== 'subtle',
    'ui-table--zebra' => $zebra,
]) }} @if ($loading) aria-busy="true" @endif>
    @if (filled($head))
        <thead @class(['sticky top-0 z-10' => $sticky])>
            {{ $head }}
        </thead>
    @endif

    @if ($loading)
        <tbody>
            @for ($i = 0; $i < $loadingRows; $i++)
                <tr>
                    @for ($c = 0; $c < max(1, (int) $columns); $c++)
                        <td><x-ui.skeleton :lines="1" /></td>
                    @endfor
                </tr>
            @endfor
        </tbody>
    @elseif ($traeTbody)
        {!! $cuerpo !!}
    @else
        <tbody>{!! $cuerpo !!}</tbody>
    @endif
</table>
