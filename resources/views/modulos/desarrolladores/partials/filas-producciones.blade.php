{{-- Filas de producciones (renderizadas por el servidor) --}}
@if($hasData)
    @foreach($producciones as $p)
        @php
            $ordenVacio = empty($p['NoProduccion'] ?? null) || trim($p['NoProduccion'] ?? '') === '';
            $fechaFormateada = !empty($p['FechaInicio'])
                ? \Carbon\Carbon::parse($p['FechaInicio'])->format('d/m/Y')
                : 'N/A';
        @endphp
        <tr class="hover:bg-gray-100 transition-colors">
            <td class="px-3 py-3 whitespace-nowrap text-sm font-medium text-gray-900 bg-white">
                @if($ordenVacio)
                    <input type="number" min="1" class="orden-input w-full px-2 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Escribe orden" data-original="">
                @else
                    <span class="orden-value">{{ $p['NoProduccion'] }}</span>
                @endif
            </td>
            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-600 bg-blue-50">{{ $fechaFormateada }}</td>
            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-600 bg-white">{{ $p['TamanoClave'] ?? 'N/A' }}</td>
            <td class="px-3 py-3 text-sm text-gray-600 break-words bg-blue-50">{{ $p['NombreProducto'] ?? 'N/A' }}</td>
            <td class="px-3 py-3 whitespace-nowrap bg-white">
                <select class="telar-destino-select w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-green-50 cursor-pointer">
                    <option value="">--</option>
                    @foreach($telares as $t)
                        @if($t !== $telarId)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endif
                    @endforeach
                </select>
            </td>
            <td class="px-3 py-3 whitespace-nowrap text-center bg-blue-50">
                <input type="checkbox" class="checkbox-produccion w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 cursor-pointer"
                       data-telar="{{ $telarId }}"
                       data-salon="{{ $p['SalonTejidoId'] ?? '' }}"
                       data-tamano="{{ $p['TamanoClave'] ?? '' }}"
                       data-produccion="{{ $p['NoProduccion'] ?? '' }}"
                       data-modelo="{{ $p['NombreProducto'] ?? '' }}"
                       onchange="seleccionarProduccion(this)">
            </td>
        </tr>
    @endforeach
@endif
