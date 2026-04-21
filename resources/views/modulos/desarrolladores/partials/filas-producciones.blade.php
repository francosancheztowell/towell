{{-- Filas de producciones (renderizadas por el servidor) --}}
@if($hasData)
    @foreach($producciones as $p)
        @php
            $fechaFormateada = !empty($p['FechaInicio'])
                ? \Carbon\Carbon::parse($p['FechaInicio'])->format('d/m/Y')
                : 'N/A';
            $telarOrigenValue = '';
            foreach($telaresDestino as $t) {
                $tp = trim(explode('|', $t['value'] ?? '', 2)[1] ?? '');
                if ($tp === (string)$telarId) { $telarOrigenValue = $t['value']; break; }
            }
        @endphp
        <tr class="hover:bg-gray-100 transition-colors">
            <td class="px-3 py-3 whitespace-nowrap text-sm font-medium text-gray-900 bg-white">
                <span class="orden-value">{{ $p['NoProduccion'] }}</span>
            </td>
            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-600 bg-blue-50">{{ $fechaFormateada }}</td>
            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-600 bg-white">{{ $p['TamanoClave'] ?? 'N/A' }}</td>
            <td class="px-3 py-3 text-sm text-gray-600 break-words bg-blue-50">{{ $p['NombreProducto'] ?? 'N/A' }}</td>
            <td class="px-3 py-3 whitespace-nowrap bg-white">
                <select class="telar-destino-select w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-green-50 cursor-pointer">
                    @if($telarOrigenValue)
                        <option value="{{ $telarOrigenValue }}" data-es-origen="true">{{ $telarId }}</option>
                    @endif
                    @foreach($telaresDestino as $t)
                        @php
                            $partes = explode('|', $t['value'] ?? '', 2);
                            $telarParte = trim($partes[1] ?? '');
                        @endphp
                        @if($telarParte !== (string)$telarId)
                            <option value="{{ $t['value'] }}">{{ $t['label'] }}</option>
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
                       data-id="{{ $p['Id'] ?? '' }}"
                       onchange="seleccionarProduccion(this)">
            </td>
        </tr>
    @endforeach
@endif
