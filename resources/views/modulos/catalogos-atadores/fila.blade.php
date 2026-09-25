{{--
    Fila de un catálogo de atadores. Con $valores = null es la plantilla vacía que usa el JS.
    La selección se pinta con aria-selected (lo alterna resources/js/catalogos/catalog-base.ts).
--}}
@php $llave = $valores ? (string) data_get($valores, $catalogo['llave']) : ''; @endphp
<tr data-fila data-id="{{ $llave }}" tabindex="0" aria-selected="false"
    class="cursor-pointer transition-colors duration-150 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500 aria-selected:bg-blue-100 aria-selected:shadow-[inset_4px_0_0_0_#3b82f6] aria-selected:hover:bg-blue-100">
    @foreach ($catalogo['columnas'] as $columna)
        @php $valor = $valores ? data_get($valores, $columna['campo']) : null; @endphp
        <td class="{{ $columna['clase'] ?? '' }}" data-campo="{{ $columna['campo'] }}" @isset($columna['sufijo']) data-sufijo="{{ $columna['sufijo'] }}" @endisset>@if (filled($valor)){{ $valor }}{{ $columna['sufijo'] ?? '' }}@endif</td>
    @endforeach
</tr>
