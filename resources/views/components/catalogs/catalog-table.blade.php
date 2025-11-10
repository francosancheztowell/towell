{{--
    Componente: Catalog Table
    Tabla reutilizable para catálogos con selección de filas y estilos consistentes

    Props:
        @param array $items - Array de items a mostrar
        @param array $columns - Array de columnas con formato ['name' => 'Nombre Campo', 'field' => 'campo_db']
        @param string $tableBodyId - ID del tbody (default: 'catalog-body')
        @param string $idField - Campo que contiene el ID único (default: 'id')
        @param bool $selectable - Si las filas son seleccionables (default: true)
        @param string $height - Altura máxima de la tabla (default: '640px')
--}}

@props([
    'items' => [],
    'columns' => [],
    'tableBodyId' => 'catalog-body',
    'idField' => 'id',
    'selectable' => true,
    'height' => '640px'
])

<div class="bg-white overflow-hidden shadow-sm rounded-lg">
    <div class="overflow-y-auto scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100" style="max-height: {{ $height }};">
        <table class="table table-bordered table-sm w-full">
            <thead class="sticky top-0 bg-blue-500 text-white z-10">
                <tr>
                    @foreach($columns as $column)
                        <th class="py-1 px-2 font-bold tracking-wider text-center">
                            {{ $column['label'] ?? $column['name'] }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody id="{{ $tableBodyId }}" class="bg-white text-black">
                @foreach($items as $item)
                    @php
                        $uniqueId = $item->{$idField} ?? $item->Id ?? $item->id ?? null;
                        $recordId = $item->Id ?? $item->id ?? $uniqueId;
                    @endphp
                    <tr class="text-center hover:bg-blue-50 transition {{ $selectable ? 'cursor-pointer' : '' }}"
                        @if($selectable)
                            onclick="window.catalogManager?.selectRow(this, '{{ $uniqueId }}', '{{ $recordId }}')"
                            ondblclick="window.catalogManager?.deselectRow(this)"
                        @endif
                        data-id="{{ $recordId }}"
                        @foreach($columns as $column)
                            @php $field = $column['field'] ?? $column['name']; @endphp
                            data-{{ strtolower($field) }}="{{ $item->$field ?? '' }}"
                        @endforeach
                    >
                        @foreach($columns as $column)
                            @php
                                $field = $column['field'] ?? $column['name'];
                                $value = $item->$field ?? '';
                                $format = $column['format'] ?? null;
                            @endphp
                            <td class="py-1 px-4 border-b {{ $column['class'] ?? '' }}">
                                @if($format === 'number' && is_numeric($value))
                                    {{ number_format($value, $column['decimals'] ?? 0) }}
                                @elseif($format === 'date' && $value)
                                    {{ \Carbon\Carbon::parse($value)->format($column['dateFormat'] ?? 'd/m/Y') }}
                                @elseif($format === 'percentage' && is_numeric($value))
                                    {{ number_format($value * 100, $column['decimals'] ?? 0) }}%
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<style>
    .scrollbar-thin { scrollbar-width: thin; }
    .scrollbar-thin::-webkit-scrollbar { width: 8px; }
    .scrollbar-thumb-gray-400::-webkit-scrollbar-thumb { background-color: #9ca3af; border-radius: 4px; }
    .scrollbar-track-gray-100::-webkit-scrollbar-track { background-color: #f3f4f6; }
    .scrollbar-thin::-webkit-scrollbar-thumb:hover { background-color: #6b7280; }
</style>

