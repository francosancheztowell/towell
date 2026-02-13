@extends('layouts.app', ['ocultarBotones' => true])

@section('navbar-right')
<div class="flex items-center gap-2">
    <button onclick="openPinColumnsModal()" class="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 flex items-center gap-2">
        <i class="fas fa-thumbtack"></i>
    </button>
    <button onclick="openHideColumnsModal()" class="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600 flex items-center gap-2">
        <i class="fas fa-eye-slash"></i>
    </button>
    <button onclick="toggleFilters()" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 flex items-center gap-2">
        <i class="fas fa-filter"></i>
    </button>
    <x-navbar.button-create
        onclick="liberarOrdenes()"
        title="Liberar"
        text="Liberar"
        module="Programa Tejido"
        icon="fa-unlock"
        bg="bg-green-500"
        iconColor="text-white"
        hoverBg="hover:bg-green-600"
    />
</div>
@endsection

@section('page-title', 'Liberar Órdenes')

@section('content')
<div class="w-full" style="height: calc(100vh - 70px); display: flex; flex-direction: column;">
    <div class="bg-white shadow overflow-hidden w-full h-full rounded-lg flex flex-col" style="flex: 1; min-height: 0;">
        @php
        // Opciones de hilos para el select (pasadas desde el controlador)
        $hilosOptions = $hilosOptions ?? [];

        // Columnas según la lista del usuario
        $columns = [
            ['field' => 'select', 'label' => 'Seleccionar'],
            ['field' => 'prioridad', 'label' => 'Prioridad'],
            ['field' => 'Maquina', 'label' => 'Maq'],
            ['field' => 'Ancho', 'label' => 'Ancho'],
            ['field' => 'EficienciaSTD', 'label' => 'Eficiencia'],
            ['field' => 'VelocidadSTD', 'label' => 'Velocidad'],
            ['field' => 'FibraRizo', 'label' => 'Hilo'],
            ['field' => 'CalibrePie2', 'label' => 'Calibre Pie'],
            ['field' => 'ItemId', 'label' => 'Clave AX'],
            ['field' => 'NombreProducto', 'label' => 'Producto'],
            ['field' => 'CodigoDibujo', 'label' => 'Codigo Dibujo'],
            ['field' => 'InventSizeId', 'label' => 'Tamaño AX'],
            ['field' => 'TotalPedido', 'label' => 'Pedido'],
            ['field' => 'ProgramarProd', 'label' => 'Day Shedulling'],
            ['field' => 'Programado', 'label' => 'INN'],
            ['field' => 'FlogsId', 'label' => 'Flog'],
            ['field' => 'NombreProyecto', 'label' => 'Descripción'],
            ['field' => 'AplicacionId', 'label' => 'Aplic'],
            ['field' => 'TipoPedido', 'label' => 'Tipo Ped'],
            ['field' => 'FechaInicio', 'label' => 'Inicio'],
            ['field' => 'FechaFinal', 'label' => 'Fin'],
            ['field' => 'EntregaProduc', 'label' => 'Fecha Compromiso'],
            ['field' => 'EntregaPT', 'label' => 'Fecha Compromiso'],
            ['field' => 'EntregaCte', 'label' => 'Entrega'],
            ['field' => 'PTvsCte', 'label' => 'Dif vs Compromiso'],
            ['field' => 'MtsRollo', 'label' => 'Metros x Rollo'],
            ['field' => 'PzasRollo', 'label' => 'Pzas x Rollo'],
            ['field' => 'TotalRollos', 'label' => 'Total Rollos'],
            ['field' => 'TotalPzas', 'label' => 'Total Pzas'],
            ['field' => 'Repeticiones', 'label' => 'Repeticiones'],
            ['field' => 'SaldoMarbete', 'label' => 'No Marbetes'],
            ['field' => 'Densidad', 'label' => 'Densidad'],
            ['field' => 'Observaciones', 'label' => 'Observaciones'],
            ['field' => 'CambioRepaso', 'label' => 'Cambio Repaso'],
            ['field' => 'CombinaTrama', 'label' => 'Comb Trama'],
            ['field' => 'BomId', 'label' => 'L.Mat'],
            ['field' => 'BomName', 'label' => 'Nombre L.Mat'],
            ['field' => 'HiloAX', 'label' => 'Hilo AX'],
        ];

        $formatValue = function($registro, $field) use ($hilosOptions) {
            if ($field === 'select') {
                $checked = 'checked'; // Todos marcados por defecto
                return '<input type="checkbox" class="row-checkbox w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 mx-auto block" data-id="' . ($registro->Id ?? '') . '" ' . $checked . '>';
            }

            if ($field === 'prioridad') {
                // Usar PrioridadAnterior si existe, sino usar Prioridad actual, sino vacío
                $prioridadAnterior = $registro->PrioridadAnterior ?? '';
                $prioridadActual = $registro->Prioridad ?? '';
                $prioridad = !empty($prioridadActual) ? $prioridadActual : $prioridadAnterior;
                $id = $registro->Id ?? '';

                return '<input type="text"
                        class="prioridad-input w-full px-3 py-2 text-base border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                        value="' . htmlspecialchars($prioridad, ENT_QUOTES, 'UTF-8') . '"
                        data-id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"
                        data-prioridad-anterior="' . htmlspecialchars($prioridadAnterior, ENT_QUOTES, 'UTF-8') . '"
                        placeholder="Prioridad"
                        style="min-width: 280px;">';
            }

            if ($field === 'BomId') {
                $rowId = $registro->Id ?? uniqid('row_');
                $rowId = htmlspecialchars((string) $rowId, ENT_QUOTES, 'UTF-8');
                $value = htmlspecialchars((string) ($registro->BomId ?? ''), ENT_QUOTES, 'UTF-8');

                return '<div class="relative">
                            <input type="text"
                                  id="bom-id-input-' . $rowId . '"
                                  class="bom-id-input w-full min-w-[100px] px-3 py-2 text-sm border border-gray-300 rounded"
                                value="' . $value . '"
                                data-row-id="' . $rowId . '"
                                list="bom-id-options-' . $rowId . '"
                                placeholder="L.Mat">
                            <datalist id="bom-id-options-' . $rowId . '"></datalist>
                            <div id="bom-id-message-' . $rowId . '" class="bom-no-results-message hidden text-xs text-red-500 mt-1"></div>
                        </div>';
            }

            if ($field === 'BomName') {
                $rowId = $registro->Id ?? uniqid('row_');
                $rowId = htmlspecialchars((string) $rowId, ENT_QUOTES, 'UTF-8');
                $value = htmlspecialchars((string) ($registro->BomName ?? ''), ENT_QUOTES, 'UTF-8');

                return '<div class="relative">
                            <input type="text"
                                id="bom-name-input-' . $rowId . '"
                                class="bom-name-input w-full min-w-[150px] px-3 py-2 text-sm border border-gray-300 rounded"
                                value="' . $value . '"
                                data-row-id="' . $rowId . '"
                                list="bom-name-options-' . $rowId . '"
                                placeholder="Nombre L.Mat">
                            <datalist id="bom-name-options-' . $rowId . '"></datalist>
                            <div id="bom-name-message-' . $rowId . '" class="bom-no-results-message hidden text-xs text-red-500 mt-1"></div>
                        </div>';
            }

            // Columna INN (Programado) - Usar el valor calculado del controlador
            if ($field === 'Programado') {
                $programadoCalculado = $registro->ProgramadoCalculado ?? null;
                if ($programadoCalculado && $programadoCalculado instanceof \Carbon\Carbon) {
                    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
                    $mes = $meses[$programadoCalculado->month - 1] ?? strtolower($programadoCalculado->format('M'));
                    return $programadoCalculado->format('d') . '-' . $mes . '-' . $programadoCalculado->format('Y');
                }
                return '';
            }

            // Campo HiloAX - mostrar como select siempre, incluso si es null
            if ($field === 'HiloAX') {
                $value = $registro->{$field} ?? null;
                $rowId = $registro->Id ?? uniqid('row_');
                $rowId = htmlspecialchars((string) $rowId, ENT_QUOTES, 'UTF-8');
                $valueStr = $value !== null ? htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') : '';

                // Construir opciones del select
                $optionsHtml = '<option value="">Seleccionar...</option>';
                if (!empty($hilosOptions) && is_array($hilosOptions)) {
                    foreach ($hilosOptions as $hilo) {
                        $hiloValue = htmlspecialchars((string) ($hilo ?? ''), ENT_QUOTES, 'UTF-8');
                        $selected = ($valueStr && $valueStr === $hiloValue) ? 'selected' : '';
                        $optionsHtml .= '<option value="' . $hiloValue . '" ' . $selected . '>' . $hiloValue . '</option>';
                    }
                }

                // Si el valor no está en las opciones pero existe, agregarlo
                if ($valueStr && !empty($hilosOptions) && !in_array($valueStr, $hilosOptions)) {
                    $optionsHtml = '<option value="' . $valueStr . '" selected>' . $valueStr . '</option>' . $optionsHtml;
                }

                return '<select class="hilo-ax-select w-full px-3 py-2 text-base border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                                data-row-id="' . $rowId . '"
                                data-id="' . $rowId . '"
                                style="min-width: 200px; width: 100%;">' . $optionsHtml . '</select>';
            }

            // Solo TotalRollos es editable, los demás son texto plano
            $camposNumericosSoloLectura = ['MtsRollo', 'PzasRollo', 'TotalPzas', 'Repeticiones', 'SaldoMarbete', 'Densidad'];
            if (in_array($field, $camposNumericosSoloLectura, true)) {
                $value = $registro->{$field} ?? null;

                // Formatear según el campo: Densidad (4 decimales), MtsRollo (decimales sin límite), otros (0 decimales)
                if ($field === 'Densidad') {
                    $valueFormatted = $value !== null ? (is_numeric($value) ? number_format((float)$value, 4, '.', ',') : '') : '';
                } elseif ($field === 'MtsRollo') {
                    // MtsRollo se mantiene como decimal sin redondear, con separador de miles
                    $valueFormatted = $value !== null ? (is_numeric($value) ? number_format((float)$value, 2, '.', ',') : '') : '';
                } else {
                    // TotalPzas, PzasRollo, Repeticiones, SaldoMarbete: números enteros con separador de miles
                    $valueFormatted = $value !== null ? (is_numeric($value) ? number_format((float)$value, 0, '.', ',') : '') : '';
                }

                // Mostrar como texto plano (solo lectura) con atributo data-field para facilitar búsqueda
                return '<span class="text-sm text-gray-700" data-field="' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($valueFormatted, ENT_QUOTES, 'UTF-8') . '</span>';
            }

            // TotalRollos es el único editable
            if ($field === 'TotalRollos') {
                $rowId = $registro->Id ?? uniqid('row_');
                $rowId = htmlspecialchars((string) $rowId, ENT_QUOTES, 'UTF-8');
                $value = $registro->{$field} ?? null;
                $valueFormatted = $value !== null ? (is_numeric($value) ? number_format((float)$value, 0, '.', '') : '') : '';

                // Guardar PzasRollo como atributo para el cálculo
                $pzasRollo = $registro->PzasRollo ?? 0;
                $pzasRolloFormatted = $pzasRollo !== null ? (is_numeric($pzasRollo) ? number_format((float)$pzasRollo, 0, '.', '') : '0') : '0';

                return '<input type="number"
                              step="1"
                              class="editable-field w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                              value="' . htmlspecialchars($valueFormatted, ENT_QUOTES, 'UTF-8') . '"
                              data-field="' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '"
                              data-row-id="' . htmlspecialchars($rowId, ENT_QUOTES, 'UTF-8') . '"
                              data-pzas-rollo="' . htmlspecialchars($pzasRolloFormatted, ENT_QUOTES, 'UTF-8') . '"
                              data-original-value="' . htmlspecialchars($valueFormatted, ENT_QUOTES, 'UTF-8') . '">';
            }

            // Campo CombinaTrama (string editable)
            if ($field === 'CombinaTrama') {
                $rowId = $registro->Id ?? uniqid('row_');
                $rowId = htmlspecialchars((string) $rowId, ENT_QUOTES, 'UTF-8');
                $value = $registro->{$field} ?? null;
                $valueFormatted = $value !== null ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') : '';

                return '<input type="text"
                              class="editable-field w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                              value="' . $valueFormatted . '"
                              data-field="' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '"
                              data-row-id="' . $rowId . '"
                              data-original-value="' . $valueFormatted . '">';
            }

            // Campo Observaciones (string editable)
            if ($field === 'Observaciones') {
                $rowId = $registro->Id ?? uniqid('row_');
                $rowId = htmlspecialchars((string) $rowId, ENT_QUOTES, 'UTF-8');
                $value = $registro->{$field} ?? null;
                $valueFormatted = $value !== null ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') : '';

                return '<input type="text"
                              class="editable-field w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 observaciones-input"
                              value="' . $valueFormatted . '"
                              data-field="Observaciones"
                              data-row-id="' . $rowId . '"
                              data-original-value="' . $valueFormatted . '"
                              placeholder="Observaciones">';
            }

            // Campo CambioRepaso (select SI/NO)
            if ($field === 'CambioRepaso') {
                $rowId = $registro->Id ?? uniqid('row_');
                $rowId = htmlspecialchars((string) $rowId, ENT_QUOTES, 'UTF-8');

                $valorActual = strtoupper(trim((string) ($registro->CambioHilo ?? 'NO')));
                if (!in_array($valorActual, ['SI', 'NO'], true)) {
                    $valorActual = 'NO';
                }

                return '<select class="cambio-repaso-select w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                              data-field="CambioRepaso"
                              data-row-id="' . $rowId . '">
                            <option value="SI" ' . ($valorActual === 'SI' ? 'selected' : '') . '>SI</option>
                            <option value="NO" ' . ($valorActual === 'NO' ? 'selected' : '') . '>NO</option>
                        </select>';
            }

            $value = $registro->{$field} ?? null;
            if ($value === null || $value === '') return '';

            // Formato de porcentaje para EficienciaSTD
            if ($field === 'EficienciaSTD' && is_numeric($value)) {
                $porcentaje = (float)$value * 100;
                return round($porcentaje) . '%';
            }

            // Formato PTvsCte (Dif vs Compromiso) como entero con redondeo
            if ($field === 'PTvsCte' && is_numeric($value)) {
                $valorFloat = (float)$value;
                $parteEntera = (int)$valorFloat;
                $parteDecimal = abs($valorFloat - $parteEntera);
                $valorFormateado = '';
                if ($parteDecimal > 0.50) {
                    if ($valorFloat >= 0) {
                        $valorFormateado = (string)(int)ceil($valorFloat);
                    } else {
                        $valorFormateado = (string)(int)floor($valorFloat);
                    }
                } else {
                    $valorFormateado = (string)$parteEntera;
                }
                // Si es negativo, aplicar clase CSS para mostrarlo en rojo
                if ($valorFloat < 0) {
                    return '<span class="valor-negativo">' . htmlspecialchars($valorFormateado, ENT_QUOTES, 'UTF-8') . '</span>';
                }
                return $valorFormateado;
            }

            // Formato de fechas (día-mes-año abreviado)
            $fechaCampos = ['ProgramarProd', 'FechaInicio', 'FechaFinal', 'EntregaProduc', 'EntregaPT'];
            if (in_array($field, $fechaCampos, true)) {
                try {
                    if ($value instanceof \Carbon\Carbon) {
                        if ($value->year > 1970) {
                            $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
                            $mes = $meses[$value->month - 1] ?? strtolower($value->format('M'));
                            return $value->format('d') . '-' . $mes . '-' . $value->format('Y');
                        }
                        return '';
                    }
                    $dt = \Carbon\Carbon::parse($value);
                    if ($dt->year > 1970) {
                        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
                        $mes = $meses[$dt->month - 1] ?? strtolower($dt->format('M'));
                        return $dt->format('d') . '-' . $mes . '-' . $dt->format('Y');
                    }
                    return '';
                } catch (\Exception $e) {
                    return '';
                }
            }

            // Formato para EntregaCte (datetime)
            if ($field === 'EntregaCte') {
                try {
                    if ($value instanceof \Carbon\Carbon) {
                        if ($value->year > 1970) {
                            $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
                            $mes = $meses[$value->month - 1] ?? strtolower($value->format('M'));
                            return $value->format('d') . '-' . $mes . '-' . $value->format('Y') . ' ' . $value->format('H:i');
                        }
                        return '';
                    }
                    $dt = \Carbon\Carbon::parse($value);
                    if ($dt->year > 1970) {
                        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
                        $mes = $meses[$dt->month - 1] ?? strtolower($dt->format('M'));
                        return $dt->format('d') . '-' . $mes . '-' . $dt->format('Y') . ' ' . $dt->format('H:i');
                    }
                    return '';
                } catch (\Exception $e) {
                    return '';
                }
            }

            if ($field === 'ItemId') {
                return (string) $value;
            }

            // Números con formato
            if (is_numeric($value)) {
                $floatValue = (float)$value;
                if ($floatValue == floor($floatValue)) {
                    return number_format($floatValue, 0, '.', ',');
                } else {
                    return number_format($floatValue, 2, '.', ',');
                }
            }

            return $value;
        };
        @endphp


        @if(isset($error))
            <div class="px-6 py-4 bg-red-100 border-l-4 border-red-500 text-red-700">
                <p class="font-bold">Error</p>
                <p>{{ $error }}</p>
            </div>
        @endif

        @if(isset($registros) && is_countable($registros) && count($registros) > 0)
            <div class="overflow-x-auto flex-1" style="min-height: 0; flex: 1;">
                <div class="overflow-y-auto" style="height: 100%; position: relative;">
                    <table id="mainTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-blue-500 text-white liberar-header-context" style="position: sticky; top: 0; z-index: 10;">
                            <tr>
                                @foreach($columns as $index => $col)
                                <th class="px-2 py-2 text-left text-sm font-semibold text-white whitespace-nowrap column-{{ $index }}"
                                    style="position: sticky; top: 0; background-color: #3b82f6; min-width: {{ $col['field'] === 'prioridad' ? '300px' : ($col['field'] === 'HiloAX' ? '220px' : ($col['field'] === 'BomId' ? '180px' : ($col['field'] === 'BomName' ? '300px' : ($col['field'] === 'Observaciones' ? '260px' : ($col['field'] === 'CambioRepaso' ? '170px' : '80px'))))) }}; z-index: 10;"
                                    data-index="{{ $index }}"
                                    data-field="{{ $col['field'] }}">
                                    @if($col['field'] === 'select')
                                        <input type="checkbox"
                                            id="selectAllCheckbox"
                                            class="w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 mx-auto block"
                                            onclick="toggleSeleccionarTodo()"
                                            aria-label="Seleccionar todo">
                                    @else
                                        <div class="flex items-center gap-1">
                                            <span>{{ $col['label'] }}</span>
                                            <span class="liberar-header-badges inline-flex items-center gap-0.5 ml-0.5" data-index="{{ $index }}" data-field="{{ $col['field'] }}"></span>
                                        </div>
                                    @endif
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach($registros as $index => $registro)
                            <tr class="transition-colors row-data cursor-pointer {{ $loop->even ? 'bg-gray-100 row-even' : 'bg-white row-odd' }}" data-id="{{ $registro->Id ?? '' }}" title="Clic en la fila para marcar como referencia visual (azul)">
                                @foreach($columns as $colIndex => $col)
                                <td class="px-3 py-2 text-sm text-gray-700 whitespace-nowrap column-{{ $colIndex }} {{ $col['field'] === 'select' ? 'text-center' : '' }} {{ $col['field'] === 'prioridad' ? 'px-4 py-3' : '' }}"
                                    data-column="{{ $col['field'] }}">
                                    {!! $formatValue($registro, $col['field']) !!}
                                </td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay registros</h3>
                <p class="mt-1 text-sm text-gray-500">No se encontraron registros sin orden de producción.</p>
            </div>
        @endif
    </div>
</div>

{{-- Menú contextual en encabezados (clic derecho): Filtrar, Fijar, Ocultar --}}
<div id="liberar-context-menu-header" class="hidden fixed bg-white border border-gray-300 rounded-lg shadow-lg py-1 min-w-[180px]" style="z-index: 99999;">
    <button type="button" id="liberar-context-filtrar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
        <i class="fas fa-filter text-blue-500"></i>
        <span>Filtrar</span>
    </button>
    <button type="button" id="liberar-context-fijar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-yellow-50 hover:text-yellow-700 flex items-center gap-2">
        <i class="fas fa-thumbtack text-yellow-600"></i>
        <span>Fijar / Desfijar</span>
    </button>
    <button type="button" id="liberar-context-ocultar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 flex items-center gap-2">
        <i class="fas fa-eye-slash text-red-500"></i>
        <span>Ocultar</span>
    </button>
</div>

<script>
const liberarOrdenesUrl = '{{ route('programa-tejido.liberar-ordenes.procesar') }}';
const redirectAfterLiberar = '{{ route('catalogos.req-programa-tejido') }}';
const tipoHiloUrl = '{{ route('programa-tejido.liberar-ordenes.tipo-hilo') }}';
const bomAutocompleteUrl = '{{ route('programa-tejido.liberar-ordenes.bom') }}';
const codigoDibujoUrl = '{{ route('programa-tejido.liberar-ordenes.codigo-dibujo') }}';

// Variables globales para columnas
const liberarOrdenesColumnsList = @json($columns ?? []);
let pinnedColumns = [2, 6, 9, 12];
let hiddenColumns = [];
let filtersActive = false;
/** Filtros tipo Excel por columna: { [columnField]: string[] } valores seleccionados para mostrar */
let liberarOrdenesColumnFilters = {};

document.addEventListener('DOMContentLoaded', function() {
    // Marcar todos los checkboxes por defecto
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
        checkbox.addEventListener('change', updateSelectAllCheckbox);
    });

    // Marcar el checkbox del encabezado ya que todos están seleccionados
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = true;
    }

    // Rellenar automáticamente los inputs de prioridad con el valor del registro anterior
    const prioridadInputs = document.querySelectorAll('.prioridad-input');
    prioridadInputs.forEach((input, index) => {
        const prioridadAnterior = input.getAttribute('data-prioridad-anterior') || '';
        const valorActual = input.value.trim();

        // Si el input está vacío, intentar rellenarlo
        if (!valorActual) {
            // Primero intentar con data-prioridad-anterior
            if (prioridadAnterior) {
                input.value = prioridadAnterior;
            }
            // Si no hay prioridad anterior y no es el primero, buscar el valor del input anterior
            else if (index > 0) {
                const inputAnterior = prioridadInputs[index - 1];
                if (inputAnterior && inputAnterior.value.trim()) {
                    input.value = inputAnterior.value.trim();
                }
            }
        }
    });

    // Marcar fila como referencia al hacer clic en la fila (no en casilla ni inputs)
    setupRowReferenceClick();

    // Menú contextual en encabezados (clic derecho): Filtrar, Fijar, Ocultar
    initLiberarContextMenuHeader();

    // Iconos en encabezados: fijado / filtrado (clic quita fijado o filtro)
    updateLiberarHeaderBadges();
    document.querySelector('#mainTable thead')?.addEventListener('click', (e) => {
        const pinBtn = e.target.closest('.liberar-header-badge-pin');
        const filterBtn = e.target.closest('.liberar-header-badge-filter');
        if (pinBtn) {
            e.preventDefault();
            e.stopPropagation();
            const idx = parseInt(pinBtn.dataset.index, 10);
            if (!Number.isNaN(idx)) { unpinColumn(idx); updateLiberarHeaderBadges(); updatePinnedColumnsPositions(); }
        }
        if (filterBtn) {
            e.preventDefault();
            e.stopPropagation();
            const field = filterBtn.dataset.field;
            if (field) { delete liberarOrdenesColumnFilters[field]; applyFiltersSilent(); updateLiberarHeaderBadges(); }
        }
    });

    // Inicializar posiciones de columnas fijadas (con delay para asegurar que la tabla esté renderizada)
    setTimeout(() => {
        updatePinnedColumnsPositions();
    }, 100);

    // Recalcular posiciones cuando cambie el tamaño de la ventana
    window.addEventListener('resize', () => {
        updatePinnedColumnsPositions();
    });

    // Event listeners para campos editables
    // IMPORTANTE: Ningún campo se guarda automáticamente, solo se guardan al presionar "Liberar"
    const editableFields = document.querySelectorAll('.editable-field');
    editableFields.forEach(field => {
        const fieldName = field.getAttribute('data-field');

        // Event listeners para TotalRollos - calcular TotalPzas automáticamente (sin guardar)
        if (fieldName === 'TotalRollos') {
            field.addEventListener('input', function() {
                calcularTotalPzas(this);
            });
            field.addEventListener('blur', function() {
                calcularTotalPzas(this);
                // No guardar automáticamente
            });
        }
        // Los demás campos no tienen cálculos automáticos ni guardado automático
    });

    // Rellenar automáticamente el campo Hilo AX al cargar
    autoFillAllHiloAX();

    // Rellenar automáticamente los campos L.Mat y Nombre L.Mat al cargar
    autoFillAllBomFields();

    // Rellenar automáticamente el campo Codigo Dibujo al cargar
    autoFillAllCodigoDibujo();

    // Habilitar autocompletado para L.Mat y Nombre L.Mat
    setupBomAutocomplete();
});

function autoFillAllHiloAX() {
    const rows = document.querySelectorAll('.row-data');

    const itemIdsSet = new Set();
    const rowsByItemId = new Map();

    rows.forEach((row) => {
        const itemIdCell = row.querySelector('[data-column="ItemId"]');
        const hiloAXCell = row.querySelector('[data-column="HiloAX"]');

        if (!itemIdCell || !hiloAXCell) return;

        const itemId = (itemIdCell.textContent || '').trim();
        const hiloAXSelect = hiloAXCell.querySelector('select');
        const currentHiloAX = hiloAXSelect ? hiloAXSelect.value : (hiloAXCell.textContent || '').trim();

        // Procesar si no tiene valor (o tiene valor vacío) y tiene itemId
        if ((!currentHiloAX || currentHiloAX === '') && itemId) {
            itemIdsSet.add(itemId);
            if (!rowsByItemId.has(itemId)) {
                rowsByItemId.set(itemId, []);
            }
            rowsByItemId.get(itemId).push(row);
        }
    });

    if (itemIdsSet.size === 0) {
        return;
    }

    // Consultar INVENTTABLE para obtener TwTipoHiloId (TipoHilo)
    const itemIdsArray = Array.from(itemIdsSet);
    const urlInvent = `${tipoHiloUrl}?itemIds=${encodeURIComponent(itemIdsArray.join(','))}`;

    fetch(urlInvent, { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(payload => {
            if (!payload || !payload.success || !payload.data) {
                return;
            }

            const data = payload.data;
            itemIdsArray.forEach(itemId => {
                if (data[itemId]) {
                    const rows = rowsByItemId.get(itemId) || [];
                    rows.forEach(row => {
                        // Actualizar el select con el valor de INVENTTABLE
                        actualizarHiloAXSelect(row, data[itemId]);
                    });
                }
            });
        })
        .catch(() => {});
}

// Función para actualizar el select de HiloAX con un valor
function actualizarHiloAXSelect(row, valorHiloAX) {
    const hiloAXCell = row.querySelector('[data-column="HiloAX"]');
    if (!hiloAXCell) return;

    const select = hiloAXCell.querySelector('select');
    if (!select) return;

    if (valorHiloAX && valorHiloAX.trim() !== '') {
        const valor = valorHiloAX.trim();
        select.value = valor;

        // Si el valor no está en las opciones, agregarlo
        if (!Array.from(select.options).some(opt => opt.value === valor)) {
            const option = document.createElement('option');
            option.value = valor;
            option.textContent = valor;
            option.selected = true;
            select.insertBefore(option, select.firstChild);
        }
    }
}

function autoFillAllBomFields() {
    const rows = document.querySelectorAll('.row-data');

    const combinations = [];
    const cellsByKey = new Map();

    rows.forEach((row) => {
        const itemIdCell = row.querySelector('[data-column="ItemId"]');
        const inventSizeIdCell = row.querySelector('[data-column="InventSizeId"]');
        const bomIdInput = row.querySelector('.bom-id-input');
        const bomNameInput = row.querySelector('.bom-name-input');

        if (!itemIdCell || !inventSizeIdCell || !bomIdInput || !bomNameInput) return;

        const itemId = (itemIdCell.textContent || '').trim();
        const inventSizeId = (inventSizeIdCell.textContent || '').trim();
        const currentBomId = (bomIdInput.value || '').trim();

        // No autocompletar si el usuario ya editó el campo manualmente
        const userEdited = bomIdInput.dataset.userEdited === 'true' || bomNameInput.dataset.userEdited === 'true';

        if (!currentBomId && itemId && inventSizeId && !userEdited) {
            const cacheKey = `${itemId}|${inventSizeId}`;

            if (!cellsByKey.has(cacheKey)) {
                cellsByKey.set(cacheKey, []);
                combinations.push(`${itemId}:${inventSizeId}`);
            }
            cellsByKey.get(cacheKey).push({ bomIdInput, bomNameInput });
        }
    });

    if (combinations.length === 0) {
        return;
    }

    // UNA SOLA petición con todas las combinaciones
    const url = `${bomAutocompleteUrl}?combinations=${encodeURIComponent(combinations.join(','))}`;

    fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(payload => {
            if (!payload || !payload.success || !payload.data) {
                return;
            }

            const data = payload.data || {};

            Object.keys(data).forEach(cacheKey => {
                const options = data[cacheKey] || [];

                if (options.length === 1) {
                    const option = options[0];
                    const cells = cellsByKey.get(cacheKey) || [];

                    cells.forEach(({ bomIdInput, bomNameInput }) => {
                        bomIdInput.value = option.bomId || '';
                        bomNameInput.value = option.bomName || '';
                    });
                }
            });
        })
        .catch(() => {});
}

function autoFillAllCodigoDibujo() {
    const rows = document.querySelectorAll('.row-data');

    const combinations = [];
    const cellsByKey = new Map();
    const rowsByKey = new Map();

    rows.forEach((row) => {
        const itemIdCell = row.querySelector('[data-column="ItemId"]');
        const inventSizeIdCell = row.querySelector('[data-column="InventSizeId"]');
        const codigoDibujoCell = row.querySelector('[data-column="CodigoDibujo"]');

        if (!itemIdCell || !inventSizeIdCell || !codigoDibujoCell) return;

        const itemId = (itemIdCell.textContent || '').trim();
        const inventSizeId = (inventSizeIdCell.textContent || '').trim();
        const currentCodigoDibujo = (codigoDibujoCell.textContent || '').trim();

        if (!currentCodigoDibujo && itemId && inventSizeId) {
            const cacheKey = `${itemId}|${inventSizeId}`;

            if (!cellsByKey.has(cacheKey)) {
                cellsByKey.set(cacheKey, []);
                rowsByKey.set(cacheKey, []);
                combinations.push(`${itemId}:${inventSizeId}`);
            }
            cellsByKey.get(cacheKey).push(codigoDibujoCell);
            rowsByKey.get(cacheKey).push(row);
        }
    });

    if (combinations.length === 0) {
        return;
    }

    // UNA SOLA petición con todas las combinaciones
    const url = `${codigoDibujoUrl}?combinations=${encodeURIComponent(combinations.join(','))}`;

    fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(payload => {
            if (!payload || !payload.success || !payload.data) {
                return;
            }

            const data = payload.data || {};

            Object.keys(data).forEach(cacheKey => {
                const codigoDibujo = data[cacheKey];
                const cells = cellsByKey.get(cacheKey) || [];

                cells.forEach(cell => {
                    cell.textContent = codigoDibujo || '';
                });
            });
        })
        .catch(() => {});
}

function convertirHiloAXaSelect(row, valorHiloAX = null) {
    const hiloAXCell = row.querySelector('[data-column="HiloAX"]');
    if (!hiloAXCell) return;

    // Verificar si ya es un select
    if (hiloAXCell.querySelector('select')) {
        // Si ya es select y se pasó un valor, actualizarlo
        if (valorHiloAX !== null) {
            const select = hiloAXCell.querySelector('select');
            if (select) {
                select.value = valorHiloAX;
            }
        }
        return;
    }

    const currentValue = valorHiloAX !== null ? valorHiloAX : (hiloAXCell.textContent || '').trim();
    const rowId = row.getAttribute('data-id') || '';
    const hilosOptions = @json($hilosOptions ?? []);

    let optionsHtml = '<option value="">Seleccionar...</option>';
    if (hilosOptions && hilosOptions.length > 0) {
        hilosOptions.forEach(hilo => {
            const hiloValue = String(hilo || '').trim();
            const selected = (currentValue && currentValue === hiloValue) ? 'selected' : '';
            optionsHtml += `<option value="${hiloValue}" ${selected}>${hiloValue}</option>`;
        });
    }

    const select = document.createElement('select');
    select.className = 'hilo-ax-select w-full px-3 py-2 text-base border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white';
    select.style.minWidth = '200px';
    select.style.width = '100%';
    select.setAttribute('data-row-id', rowId);
    select.setAttribute('data-id', rowId);
    select.innerHTML = optionsHtml;

    hiloAXCell.innerHTML = '';
    hiloAXCell.appendChild(select);

    // Asegurar que el select tenga el valor seleccionado correctamente después de agregarlo al DOM
    setTimeout(() => {
        if (currentValue && currentValue.trim() !== '') {
            select.value = currentValue.trim();
            // Si el valor no está en las opciones, agregarlo
            if (!Array.from(select.options).some(opt => opt.value === currentValue.trim())) {
                const option = document.createElement('option');
                option.value = currentValue.trim();
                option.textContent = currentValue.trim();
                option.selected = true;
                select.appendChild(option);
            }
        }
    }, 0);
}

const bomOptionsByRow = new Map();

function setupBomAutocomplete() {
    const rows = document.querySelectorAll('.row-data');

    rows.forEach(row => {
        const bomIdInput = row.querySelector('.bom-id-input');
        const bomNameInput = row.querySelector('.bom-name-input');

        if (!bomIdInput || !bomNameInput) return;

        const itemId = (row.querySelector('[data-column="ItemId"]')?.textContent || '').trim();
        const inventSizeId = (row.querySelector('[data-column="InventSizeId"]')?.textContent || '').trim();
        const rowId = row.getAttribute('data-id') || bomIdInput.dataset.rowId || '';

        bomIdInput.dataset.itemId = itemId;
        bomIdInput.dataset.inventSizeId = inventSizeId;
        bomNameInput.dataset.itemId = itemId;
        bomNameInput.dataset.inventSizeId = inventSizeId;

        // Función para cargar opciones disponibles
        // Si el usuario editó el campo, usar modo libre (fallback) para mostrar más opciones
        const loadAllOptions = async (sourceInput) => {
            const bomIdMessage = document.getElementById(`bom-id-message-${rowId}`);
            const bomNameMessage = document.getElementById(`bom-name-message-${rowId}`);
            if (bomIdMessage) bomIdMessage.classList.add('hidden');
            if (bomNameMessage) bomNameMessage.classList.add('hidden');

            // Si el usuario editó el campo, usar modo libre para mostrar todas las opciones posibles
            const userEdited = bomIdInput.dataset.userEdited === 'true' || bomNameInput.dataset.userEdited === 'true';
            const freeMode = userEdited;

            const options = await fetchBomOptions(
                sourceInput.dataset.itemId,
                sourceInput.dataset.inventSizeId,
                '',
                true,
                freeMode
            );
            bomOptionsByRow.set(rowId, options);
            updateBomDatalists(rowId, options);
        };

        const debouncedFetch = debounce(async (sourceInput) => {
            const term = (sourceInput.value || '').trim();
            const bomIdMessage = document.getElementById(`bom-id-message-${rowId}`);
            const bomNameMessage = document.getElementById(`bom-name-message-${rowId}`);

            // Si el usuario editó el campo, usar modo libre
            const userEdited = bomIdInput.dataset.userEdited === 'true' || bomNameInput.dataset.userEdited === 'true';
            const freeMode = userEdited;

            if (!term) {
                // Si el término está vacío, cargar opciones (en modo libre si editó)
                if (bomIdMessage) bomIdMessage.classList.add('hidden');
                if (bomNameMessage) bomNameMessage.classList.add('hidden');
                await loadAllOptions(sourceInput);
                return;
            }

            const options = await fetchBomOptions(
                sourceInput.dataset.itemId,
                sourceInput.dataset.inventSizeId,
                term,
                true,
                freeMode
            );
            bomOptionsByRow.set(rowId, options);
            updateBomDatalists(rowId, options);

            // NO autocompletar automáticamente aunque haya solo una opción
            // El usuario debe elegir manualmente del datalist o escribir el valor completo
        }, 300);

        // Al hacer focus, cargar opciones disponibles para poder elegir
        bomIdInput.addEventListener('focus', () => loadAllOptions(bomIdInput));
        bomNameInput.addEventListener('focus', () => loadAllOptions(bomNameInput));

        // Marcar que el usuario ha editado el campo manualmente
        bomIdInput.addEventListener('input', () => {
            bomIdInput.dataset.userEdited = 'true';

            // Verificar si el usuario seleccionó una opción del datalist
            const value = (bomIdInput.value || '').trim();
            const datalist = document.getElementById(`bom-id-options-${rowId}`);
            const selectedOption = datalist ? Array.from(datalist.options).find(opt => opt.value === value) : null;

            if (selectedOption) {
                // El usuario seleccionó una opción, sincronizar el otro campo
                const bomName = selectedOption.label || '';
                if (bomName) {
                    bomNameInput.value = bomName;
                }
            } else {
                // El usuario está escribiendo, buscar opciones
                debouncedFetch(bomIdInput);
            }
        });

        bomNameInput.addEventListener('input', () => {
            bomNameInput.dataset.userEdited = 'true';

            // Verificar si el usuario seleccionó una opción del datalist
            const value = (bomNameInput.value || '').trim();
            const datalist = document.getElementById(`bom-name-options-${rowId}`);
            const selectedOption = datalist ? Array.from(datalist.options).find(opt => opt.value === value) : null;

            if (selectedOption) {
                // El usuario seleccionó una opción, sincronizar el otro campo
                const bomId = selectedOption.label || '';
                if (bomId) {
                    bomIdInput.value = bomId;
                }
            } else {
                // El usuario está escribiendo, buscar opciones
                debouncedFetch(bomNameInput);
            }
        });

        bomIdInput.addEventListener('change', () => syncBomFromInput(row, 'bomId'));
        bomNameInput.addEventListener('change', () => syncBomFromInput(row, 'bomName'));
    });
}

function debounce(fn, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn.apply(this, args), wait);
    };
}

async function fetchBomOptions(itemId, inventSizeId, term, allowFallback, freeMode = false) {
    const params = new URLSearchParams();

    // Si freeMode está activo, NO enviar itemId ni inventSizeId - búsqueda completamente libre
    if (!freeMode) {
        if (itemId) params.set('itemId', itemId);
        if (inventSizeId) params.set('inventSizeId', inventSizeId);
    }
    if (term) params.set('term', term);
    if (allowFallback || freeMode) params.set('fallback', '1');
    if (freeMode) params.set('freeMode', '1');

    const url = `${bomAutocompleteUrl}?${params.toString()}`;

    try {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const payload = await res.json();
        if (!payload || !payload.success || !payload.data) {
            return [];
        }
        return payload.data;
    } catch {
        return [];
    }
}

function updateBomDatalists(rowId, options) {
    const bomIdList = document.getElementById(`bom-id-options-${rowId}`);
    const bomNameList = document.getElementById(`bom-name-options-${rowId}`);
    const bomIdMessage = document.getElementById(`bom-id-message-${rowId}`);
    const bomNameMessage = document.getElementById(`bom-name-message-${rowId}`);

    if (bomIdList) {
        bomIdList.innerHTML = '';
        if (options.length === 0) {
            // Mostrar mensaje "sin resultados" debajo del input
            if (bomIdMessage) {
                bomIdMessage.textContent = 'sin resultados';
                bomIdMessage.classList.remove('hidden');
            }
        } else {
            // Ocultar mensaje si hay resultados
            if (bomIdMessage) {
                bomIdMessage.classList.add('hidden');
            }
            options.forEach(option => {
                const opt = document.createElement('option');
                opt.value = option.bomId || '';
                opt.label = option.bomName || '';
                bomIdList.appendChild(opt);
            });
        }
    }

    if (bomNameList) {
        bomNameList.innerHTML = '';
        if (options.length === 0) {
            // Mostrar mensaje "sin resultados" debajo del input
            if (bomNameMessage) {
                bomNameMessage.textContent = 'sin resultados';
                bomNameMessage.classList.remove('hidden');
            }
        } else {
            // Ocultar mensaje si hay resultados
            if (bomNameMessage) {
                bomNameMessage.classList.add('hidden');
            }
            options.forEach(option => {
                const opt = document.createElement('option');
                opt.value = option.bomName || '';
                opt.label = option.bomId || '';
                bomNameList.appendChild(opt);
            });
        }
    }
}

function syncBomFromInput(row, sourceKey) {
    const bomIdInput = row.querySelector('.bom-id-input');
    const bomNameInput = row.querySelector('.bom-name-input');
    const rowId = row.getAttribute('data-id') || bomIdInput?.dataset.rowId || '';
    const options = bomOptionsByRow.get(rowId) || [];

    if (!bomIdInput || !bomNameInput) return;

    if (sourceKey === 'bomId') {
        const value = (bomIdInput.value || '').trim();
        if (!value) return;

        // Buscar en las opciones cargadas
        const match = options.find(option => (option.bomId || '') === value);
        if (match) {
            bomNameInput.value = match.bomName || '';
        }
    }

    if (sourceKey === 'bomName') {
        const value = (bomNameInput.value || '').trim();
        if (!value) return;

        // Buscar en las opciones cargadas
        const match = options.find(option => (option.bomName || '') === value);
        if (match) {
            bomIdInput.value = match.bomId || '';
        }
    }
}

// Función para sincronizar inmediatamente cuando se selecciona del datalist
function syncBomOnSelect(row, sourceKey) {
    const bomIdInput = row.querySelector('.bom-id-input');
    const bomNameInput = row.querySelector('.bom-name-input');
    const rowId = row.getAttribute('data-id') || bomIdInput?.dataset.rowId || '';

    if (!bomIdInput || !bomNameInput) return;

    // Obtener el datalist correspondiente
    const datalist = sourceKey === 'bomId'
        ? document.getElementById(`bom-id-options-${rowId}`)
        : document.getElementById(`bom-name-options-${rowId}`);

    if (!datalist) return;

    const inputValue = sourceKey === 'bomId'
        ? (bomIdInput.value || '').trim()
        : (bomNameInput.value || '').trim();

    if (!inputValue) return;

    // Buscar en las opciones del datalist
    const optionElement = Array.from(datalist.options).find(opt => opt.value === inputValue);

    if (optionElement) {
        // El label contiene el valor del otro campo
        const otherValue = optionElement.label || '';
        if (sourceKey === 'bomId' && otherValue) {
            bomNameInput.value = otherValue;
        } else if (sourceKey === 'bomName' && otherValue) {
            bomIdInput.value = otherValue;
        }
    }
}

function applyBomOption(row, option) {
    const bomIdInput = row.querySelector('.bom-id-input');
    const bomNameInput = row.querySelector('.bom-name-input');

    if (!bomIdInput || !bomNameInput) return;

    bomIdInput.value = option.bomId || '';
    bomNameInput.value = option.bomName || '';
}

function toggleSeleccionarTodo() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (!checkboxes.length) {
        return;
    }

    const todosSeleccionados = Array.from(checkboxes).every(cb => cb.checked);
    const nuevoEstado = !todosSeleccionados;

    checkboxes.forEach(cb => {
        cb.checked = nuevoEstado;
    });

    // Actualizar el checkbox del encabezado
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = nuevoEstado;
    }
}

function updateSelectAllCheckbox() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');

    if (!checkboxes.length || !selectAllCheckbox) {
        return;
    }

    const todosSeleccionados = Array.from(checkboxes).every(cb => cb.checked);
    selectAllCheckbox.checked = todosSeleccionados;
}

/** Clic en la fila (sin tocar casilla/inputs) marca la fila como referencia visual (azul). No afecta qué se libera. */
/** Referencia visual: solo una fila puede estar marcada (azul). Clic en otra fila mueve la referencia; clic en la misma la quita. */
function setupRowReferenceClick() {
    document.querySelectorAll('tr.row-data').forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.closest('input, select, button, a, .row-checkbox')) return;
            if (this.classList.contains('row-selected')) {
                this.classList.remove('row-selected');
                return;
            }
            document.querySelectorAll('tr.row-data.row-selected').forEach(r => r.classList.remove('row-selected'));
            this.classList.add('row-selected');
        });
    });
}

/** Actualiza los iconos en encabezados: fijado (chincheta) y filtrado (filtro). Clic en el icono quita fijado o filtro. */
function updateLiberarHeaderBadges() {
    document.querySelectorAll('.liberar-header-badges').forEach(span => {
        const index = parseInt(span.dataset.index, 10);
        const field = span.dataset.field;
        if (Number.isNaN(index) || !field) return;
        const isPinned = pinnedColumns.includes(index);
        const hasFilter = liberarOrdenesColumnFilters[field] != null;
        const parts = [];
        if (isPinned) {
            parts.push(`<button type="button" class="liberar-header-badge-pin inline-flex items-center justify-center w-5 h-5 rounded bg-amber-400/90 text-white hover:bg-amber-500 text-[10px]" data-index="${index}" title="Quitar fijado"><i class="fas fa-thumbtack"></i></button>`);
        }
        if (hasFilter) {
            parts.push(`<button type="button" class="liberar-header-badge-filter inline-flex items-center justify-center w-5 h-5 rounded bg-blue-400/90 text-white hover:bg-blue-500 text-[10px]" data-field="${field}" title="Quitar filtro"><i class="fas fa-filter"></i></button>`);
        }
        span.innerHTML = parts.join('');
    });
}

/** Menú contextual en encabezados (clic derecho): Filtrar, Fijar, Ocultar — como req-programa-tejido. */
function initLiberarContextMenuHeader() {
    const menu = document.getElementById('liberar-context-menu-header');
    if (!menu) return;

    let menuColumnIndex = null;
    let menuColumnField = null;

    function hide() {
        menu.classList.add('hidden');
        menuColumnIndex = null;
        menuColumnField = null;
    }

    function show(e, columnIndex, columnField) {
        menuColumnIndex = columnIndex;
        menuColumnField = columnField;
        menu.style.left = e.clientX + 'px';
        menu.style.top = e.clientY + 'px';
        const rect = menu.getBoundingClientRect();
        if (rect.right > window.innerWidth) menu.style.left = (e.clientX - rect.width) + 'px';
        if (rect.bottom > window.innerHeight) menu.style.top = (e.clientY - rect.height) + 'px';
        menu.classList.remove('hidden');
    }

    document.addEventListener('click', (e) => {
        if (!menu.classList.contains('hidden') && !menu.contains(e.target)) hide();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !menu.classList.contains('hidden')) hide();
    });

    const thead = document.querySelector('#mainTable thead');
    if (thead) {
        thead.addEventListener('contextmenu', (e) => {
            const th = e.target.closest('th');
            if (!th) return;
            e.preventDefault();
            e.stopPropagation();
            let columnIndex = parseInt(th.dataset.index, 10);
            if (Number.isNaN(columnIndex)) {
                const classMatch = th.className.match(/column-(\d+)/);
                if (classMatch) columnIndex = parseInt(classMatch[1], 10);
            }
            const columnField = th.dataset.field || th.getAttribute('data-field');
            if (Number.isNaN(columnIndex) || columnField == null) return;
            show(e, columnIndex, columnField);
        });
    }

    document.getElementById('liberar-context-filtrar')?.addEventListener('click', () => {
        const idx = menuColumnIndex;
        const field = menuColumnField;
        hide();
        if (idx != null && idx >= 0 && field) {
            const col = liberarOrdenesColumnsList.find(c => c.field === field);
            openFilterExcelModal(field, col ? col.label : field);
        }
    });
    document.getElementById('liberar-context-fijar')?.addEventListener('click', () => {
        const idx = menuColumnIndex;
        hide();
        if (idx != null && idx >= 0) {
            if (pinnedColumns.includes(idx)) unpinColumn(idx);
            else pinColumn(idx);
            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'info', title: 'Columna fijada/desfijada', toast: true, position: 'top-end', timer: 1500, showConfirmButton: false });
        }
    });
    document.getElementById('liberar-context-ocultar')?.addEventListener('click', () => {
        const idx = menuColumnIndex;
        hide();
        if (idx != null && idx >= 0) {
            hideColumn(idx);
            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'info', title: 'Columna oculta', toast: true, position: 'top-end', timer: 1500, showConfirmButton: false });
        }
    });
}

// Funciones para fijar columnas
function openPinColumnsModal() {
    const columns = @json($columns);
    const pinned = pinnedColumns;

    const columnOptions = columns.map((col, index) => {
        if (col.field === 'select' || col.field === 'prioridad') return '';
        const isPinned = pinned.includes(index);
        return `
            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                <span class="text-sm font-medium text-gray-700">${col.label}</span>
                <input type="checkbox" ${isPinned ? 'checked' : ''}
                    class="w-4 h-4 text-yellow-600 bg-gray-100 border-gray-300 rounded focus:ring-yellow-500"
                    data-column-index="${index}">
            </div>
        `;
    }).filter(html => html !== '').join('');

    Swal.fire({
        title: 'Fijar Columnas',
        html: `
            <div class="text-left">
                <p class="text-sm text-gray-600 mb-4">
                    Selecciona las columnas que deseas fijar a la izquierda de la tabla:
                </p>
                <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg">
                    ${columnOptions}
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Aplicar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6b7280',
        width: '500px',
        didOpen: () => {
            const checkboxes = document.querySelectorAll('#swal2-html-container input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    const columnIndex = parseInt(this.dataset.columnIndex, 10);
                    if (Number.isNaN(columnIndex)) return;

                    if (this.checked) {
                        pinColumn(columnIndex);
                    } else {
                        unpinColumn(columnIndex);
                    }
                });
            });
        }
    });
}

function pinColumn(index) {
    if (!pinnedColumns.includes(index)) {
        pinnedColumns.push(index);
        pinnedColumns.sort((a, b) => a - b);
        updatePinnedColumnsPositions();
        updateLiberarHeaderBadges();
    }
}

function unpinColumn(index) {
    pinnedColumns = pinnedColumns.filter(i => i !== index);
    updatePinnedColumnsPositions();
    updateLiberarHeaderBadges();
}

function updatePinnedColumnsPositions() {
    // Limpiar estilos de todas las columnas fijadas
    document.querySelectorAll('[class*="column-"]').forEach(el => {
        if (el.classList.contains('pinned-column')) {
            el.style.left = '';
            el.classList.remove('pinned-column');
            if (el.tagName === 'TH') {
                el.style.backgroundColor = '#3b82f6';
                el.style.color = '#fff';
                el.style.zIndex = '10';
                el.style.top = '0';
            } else {
                el.style.backgroundColor = '';
            }
        }
    });

    // Asegurar que el thead esté fijo (igual que req-programa-tejido)
    const thead = document.querySelector('#mainTable thead');
    if (thead) {
        thead.style.position = 'sticky';
        thead.style.top = '0';
        thead.style.zIndex = '10';
    }

    // Calcular altura del thead para fijar celdas del tbody debajo del encabezado
    const theadElement = document.querySelector('#mainTable thead');
    const theadHeight = theadElement ? theadElement.offsetHeight : 0;

    // Aplicar estilos a columnas fijadas (solo las que no están ocultas)
    let left = 0;
    pinnedColumns.forEach((idx) => {
        // Saltar si la columna está oculta
        if (hiddenColumns.includes(idx)) return;

        const th = document.querySelector(`th.column-${idx}`);
        if (!th || th.style.display === 'none') return;

        const width = th.offsetWidth || th.getBoundingClientRect().width;
        document.querySelectorAll(`.column-${idx}`).forEach(el => {
            el.classList.add('pinned-column');
            el.style.position = 'sticky';
            el.style.left = left + 'px';
            if (el.tagName === 'TH') {
                el.style.backgroundColor = '#f59e0b';
                el.style.color = '#fff';
                el.style.zIndex = '20';
                el.style.top = '0';
            } else {
                el.style.backgroundColor = '#fffbeb';
                el.style.zIndex = '10';
                el.style.top = theadHeight + 'px';
            }
        });
        left += width;
    });
}

// Funciones para ocultar columnas
function openHideColumnsModal() {
    const columns = @json($columns);
    const hidden = hiddenColumns;

    const columnOptions = columns.map((col, index) => {
        if (col.field === 'select' || col.field === 'prioridad') return '';
        const isHidden = hidden.includes(index);
        return `
            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                <span class="text-sm font-medium text-gray-700">${col.label}</span>
                <input type="checkbox" ${isHidden ? 'checked' : ''}
                    class="w-4 h-4 text-red-600 bg-gray-100 border-gray-300 rounded focus:ring-red-500"
                    data-column-index="${index}">
            </div>
        `;
    }).filter(html => html !== '').join('');

    Swal.fire({
        title: 'Ocultar Columnas',
        html: `
            <div class="text-left">
                <p class="text-sm text-gray-600 mb-4">Selecciona las columnas que deseas ocultar:</p>
                <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg">
                    ${columnOptions}
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Aplicar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        width: '500px',
        didOpen: () => {
            const checkboxes = document.querySelectorAll('#swal2-html-container input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    const columnIndex = parseInt(this.dataset.columnIndex, 10);
                    if (Number.isNaN(columnIndex)) return;

                    if (this.checked) {
                        // Ocultar columna
                        hideColumn(columnIndex);
        } else {
                        // Mostrar columna
                        showColumn(columnIndex);
                    }
                });
            });
        }
    });
}

function hideColumn(index) {
    document.querySelectorAll(`.column-${index}`).forEach(el => {
        el.style.display = 'none';
    });
    if (!hiddenColumns.includes(index)) {
        hiddenColumns.push(index);
    }
    // Si la columna estaba fijada, actualizar posiciones (para que las demás se ajusten)
    if (pinnedColumns.includes(index)) {
        updatePinnedColumnsPositions();
    }
}

function showColumn(index) {
    document.querySelectorAll(`.column-${index}`).forEach(el => {
        el.style.display = '';
    });
    // Remover del array de columnas ocultas
    hiddenColumns = hiddenColumns.filter(i => i !== index);
    updatePinnedColumnsPositions();
}

// Variables para filtros (usar nombre único para evitar conflicto con app-filters.js)
let liberarOrdenesFilters = [];

// Funciones para filtros
function toggleFilters() {
    openFiltersModal();
}

/** Abre el modal de filtros. Opcional: preSelectColumnField = campo de columna a preseleccionar (desde menú contextual). */
function openFiltersModal(preSelectColumnField) {
    const columns = @json($columns);
    const filteredColumns = columns.filter(c => c.field !== 'select' && c.field !== 'prioridad');

    // Construir HTML de filtros activos
    const activeFiltersHtml = liberarOrdenesFilters.length > 0 ? `
        <div class="space-y-2 pt-3 border-t border-gray-100">
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-semibold uppercase text-gray-400">Filtros activos</span>
                <span class="inline-flex items-center justify-center rounded-full bg-blue-100 px-1.5 text-[10px] font-bold text-blue-600">
                    ${liberarOrdenesFilters.length}
                </span>
            </div>
            <div class="flex flex-wrap gap-1.5">
                ${liberarOrdenesFilters.map((f, i) => {
                    const colLabel = columns.find(c => c.field === f.column)?.label || f.column;
                    return `
                        <div class="inline-flex items-center gap-1.5 pl-2 pr-1 py-0.5 bg-blue-50 rounded-full text-[11px] text-blue-800">
                            <span class="font-medium">${colLabel}:</span>
                            <span class="text-blue-600">${f.value}</span>
                            <button onclick="removeFilter(${i})"
                                    class="flex h-4 w-4 items-center justify-center rounded-full hover:bg-blue-100 text-blue-500 transition-colors">
                                <i class="fa-solid fa-xmark text-[9px]"></i>
                            </button>
                        </div>
                    `;
                }).join('')}
            </div>
        </div>
    ` : '';

    const html = `
        <div class="w-full max-h-[80vh] overflow-hidden flex flex-col">
            <section class="flex-1 overflow-y-auto bg-white px-5 py-4 space-y-4">
                ${activeFiltersHtml}

                <!-- Buscar en columna -->
                <div class="space-y-2 ${liberarOrdenesFilters.length > 0 ? 'pt-3 border-t border-gray-100' : ''}">
                    <span class="text-[11px] font-semibold uppercase text-gray-400">Buscar en columna</span>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <select id="filtro-columna"
                                class="flex-1 rounded-lg bg-gray-100 px-3 py-2 text-xs text-gray-700
                                       focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                            <option value="">Selecciona columna...</option>
                            ${filteredColumns.map(c => `<option value="${c.field}">${c.label}</option>`).join('')}
                        </select>
                        <div id="filtro-valor-container" class="flex-[2]">
                            <input type="text" id="filtro-valor" placeholder="Valor a buscar..."
                                   class="w-full rounded-lg bg-gray-100 px-3 py-2 text-xs text-gray-700
                                          focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                   onkeypress="if(event.key==='Enter')addCustomFilter()">
                        </div>
                        <button type="button" onclick="addCustomFilter()"
                                class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700 transition-colors">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Footer con botones -->
            <footer class="flex items-center justify-between px-5 py-3 bg-gray-50 border-t border-gray-200">
                <button type="button" onclick="clearAllFilters()"
                        class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Limpiar Todo
                </button>
                <button type="button" onclick="Swal.close()"
                        class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Cerrar
                </button>
            </footer>
        </div>
    `;

    Swal.fire({
        title: 'Filtros',
        html: html,
        width: '580px',
        padding: 0,
        showConfirmButton: false,
        showCloseButton: true,
        customClass: {
            popup: 'rounded-xl overflow-hidden p-0 shadow-xl',
            htmlContainer: 'p-0 m-0'
        },
        backdrop: 'rgba(0,0,0,0.4)',
        didOpen: () => {
            setTimeout(() => {
                const colSelect = document.getElementById('filtro-columna');
                const valInput = document.getElementById('filtro-valor');
                if (preSelectColumnField && colSelect) {
                    colSelect.value = preSelectColumnField;
                    if (valInput) valInput.focus();
                } else if (colSelect) colSelect.focus();
            }, 50);
        }
    });
}

function addCustomFilter() {
    const colSelect = document.getElementById('filtro-columna');
    const valEl = document.getElementById('filtro-valor');

    const column = colSelect?.value;
    const value = valEl?.value?.trim();

    if (!column || !value) {
        Swal.fire({
            icon: 'warning',
            title: 'Campos incompletos',
            text: 'Por favor selecciona una columna e ingresa un valor.',
            toast: true,
            position: 'top-end',
            timer: 2000,
            showConfirmButton: false
        });
        return;
    }

    // Verificar si ya existe el filtro
    if (liberarOrdenesFilters.some(f => f.column === column && f.value === value)) {
        Swal.fire({
            icon: 'info',
            title: 'Filtro duplicado',
            text: 'Este filtro ya está activo.',
            toast: true,
            position: 'top-end',
            timer: 2000,
            showConfirmButton: false
        });
        return;
    }

    liberarOrdenesFilters.push({ column, value });

    // Limpiar inputs
    colSelect.value = '';
    valEl.value = '';

    // Aplicar filtros automáticamente
    applyFiltersSilent();

    // Reabrir modal con filtros actualizados
    Swal.close();
    setTimeout(() => openFiltersModal(), 100);
}

function removeFilter(index) {
    liberarOrdenesFilters.splice(index, 1);
    // Aplicar filtros automáticamente
    applyFiltersSilent();
    Swal.close();
    setTimeout(() => {
        openFiltersModal();
    }, 100);
}

function clearAllFilters() {
    liberarOrdenesFilters = [];
    liberarOrdenesColumnFilters = {};
    applyFiltersSilent();
    updateLiberarHeaderBadges();
    Swal.close();
    setTimeout(() => {
        openFiltersModal();
    }, 100);
}

/** Obtiene el valor de celda de una fila para una columna (para filtro tipo Excel). */
function getCellValueForColumn(row, columnField) {
    const cell = row.querySelector(`td[data-column="${columnField}"]`);
    if (!cell) return '';
    const select = cell.querySelector('select');
    if (select) return (select.value || '').trim();
    const input = cell.querySelector('input');
    if (input) return (input.value != null ? String(input.value) : '').trim();
    if (columnField === 'TotalPzas') {
        const span = cell.querySelector('span[data-calculated-value]');
        if (span) return (span.getAttribute('data-calculated-value') || '').trim();
    }
    return (cell.textContent || '').trim();
}

function escapeHtmlExcel(s) {
    if (s == null) return '';
    const div = document.createElement('div');
    div.textContent = String(s);
    return div.innerHTML;
}

/** Filtro tipo Excel: abre modal con valores únicos de la columna y checkboxes para elegir qué mostrar. */
function openFilterExcelModal(columnField, columnLabel) {
    const rows = document.querySelectorAll('.row-data');
    const valueCounts = new Map();
    rows.forEach(row => {
        const val = getCellValueForColumn(row, columnField);
        const key = val === '' ? '(vacío)' : val;
        valueCounts.set(key, (valueCounts.get(key) || 0) + 1);
    });
    const uniqueValues = Array.from(valueCounts.entries()).sort((a, b) => String(a[0]).localeCompare(String(b[0]), undefined, { sensitivity: 'base' }));
    const currentSelected = liberarOrdenesColumnFilters[columnField];
    const selectedSet = currentSelected ? new Set(currentSelected) : null;

    const checkboxesHtml = uniqueValues.map(([val, count]) => {
        const checked = selectedSet === null ? true : selectedSet.has(val);
        const safeVal = String(val).replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const id = 'excel-filter-' + columnField.replace(/\W/g, '_') + '-' + String(val).replace(/\W/g, '_').slice(0, 30);
        return `
            <label class="flex items-center gap-2 py-1.5 px-2 hover:bg-gray-50 rounded cursor-pointer">
                <input type="checkbox" class="excel-filter-cb w-4 h-4 text-blue-600 rounded border-gray-300" data-value="${safeVal}" ${checked ? 'checked' : ''} id="${id}">
                <span class="text-sm text-gray-700 truncate flex-1" title="${safeVal}">${escapeHtmlExcel(val)}</span>
                <span class="text-xs text-gray-400">(${count})</span>
            </label>`;
    }).join('');

    const html = `
        <div class="w-full max-h-[70vh] flex flex-col">
            <p class="text-sm text-gray-600 mb-2">Mostrar filas donde <strong>${escapeHtmlExcel(columnLabel)}</strong> sea uno de:</p>
            <div class="flex gap-2 mb-2">
                <button type="button" id="excel-filter-select-all" class="px-3 py-1.5 text-xs font-medium bg-blue-100 text-blue-700 rounded hover:bg-blue-200">Seleccionar todo</button>
                <button type="button" id="excel-filter-deselect-all" class="px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 rounded hover:bg-gray-200">Quitar selección</button>
            </div>
            <div class="border border-gray-200 rounded-lg overflow-y-auto flex-1 min-h-0" style="max-height: 320px;">
                ${checkboxesHtml || '<p class="p-3 text-sm text-gray-500">No hay valores en esta columna.</p>'}
            </div>
            <footer class="flex justify-between gap-2 mt-3 pt-3 border-t border-gray-200">
                <button type="button" id="excel-filter-clear" class="px-3 py-2 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Limpiar filtro de columna</button>
                <button type="button" id="excel-filter-apply" class="px-4 py-2 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700">Aplicar</button>
            </footer>
        </div>`;

    Swal.fire({
        title: 'Filtrar: ' + (columnLabel || columnField),
        html: html,
        width: '420px',
        padding: '1rem',
        showConfirmButton: false,
        showCloseButton: true,
        customClass: { popup: 'rounded-xl', htmlContainer: 'p-0 text-left' },
        didOpen: () => {
            const container = document.querySelector('.excel-filter-cb')?.closest('.swal2-html-container');
            if (!container) return;
            container.querySelector('#excel-filter-select-all')?.addEventListener('click', () => {
                container.querySelectorAll('.excel-filter-cb').forEach(cb => { cb.checked = true; });
            });
            container.querySelector('#excel-filter-deselect-all')?.addEventListener('click', () => {
                container.querySelectorAll('.excel-filter-cb').forEach(cb => { cb.checked = false; });
            });
            container.querySelector('#excel-filter-apply')?.addEventListener('click', () => {
                const selected = Array.from(container.querySelectorAll('.excel-filter-cb:checked')).map(cb => cb.dataset.value);
                liberarOrdenesColumnFilters[columnField] = selected.length === uniqueValues.length ? null : selected;
                if (liberarOrdenesColumnFilters[columnField] === null) delete liberarOrdenesColumnFilters[columnField];
                applyFiltersSilent();
                updateLiberarHeaderBadges();
                Swal.close();
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: 'Filtro aplicado', toast: true, position: 'top-end', timer: 1200, showConfirmButton: false });
            });
            container.querySelector('#excel-filter-clear')?.addEventListener('click', () => {
                delete liberarOrdenesColumnFilters[columnField];
                applyFiltersSilent();
                updateLiberarHeaderBadges();
                Swal.close();
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'info', title: 'Filtro de columna quitado', toast: true, position: 'top-end', timer: 1200, showConfirmButton: false });
            });
        }
    });
}

// Función para aplicar filtros sin mostrar notificación
function applyFiltersSilent() {
    const rows = document.querySelectorAll('.row-data');

    rows.forEach(row => {
        let showRow = true;

        if (liberarOrdenesFilters.length > 0) {
            liberarOrdenesFilters.forEach(filter => {
                const cell = row.querySelector(`td[data-column="${filter.column}"]`);
                const cellText = cell ? (getCellValueForColumn(row, filter.column) || '').toLowerCase() : '';
                const filterValue = filter.value.toLowerCase().trim();
                if (!cellText.includes(filterValue)) showRow = false;
            });
        }

        Object.keys(liberarOrdenesColumnFilters).forEach(columnField => {
            const allowed = liberarOrdenesColumnFilters[columnField];
            if (!allowed) return;
            if (allowed.length === 0) { showRow = false; return; }
            const cellVal = getCellValueForColumn(row, columnField);
            const key = cellVal === '' ? '(vacío)' : cellVal;
            if (!allowed.includes(key)) showRow = false;
        });

        row.style.display = showRow ? '' : 'none';
    });
}

// Función para aplicar filtros con notificación (usada por el botón)
function applyFilters() {
    applyFiltersSilent();

    Swal.fire({
        icon: 'success',
        title: 'Filtros aplicados',
        text: `${liberarOrdenesFilters.length} filtro(s) activo(s)`,
        toast: true,
        position: 'top-end',
        timer: 1000,
        showConfirmButton: false
    });
}

// Funciones para liberar órdenes
function obtenerRegistrosSeleccionados() {
    return Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => {
        const row = cb.closest('tr');
        const prioridadInput = row ? row.querySelector('.prioridad-input') : null;

        // Capturar campos desde las celdas de la tabla
        const getCellValue = (columnName) => {
            const cell = row ? row.querySelector(`[data-column="${columnName}"]`) : null;
            if (!cell) return null;

            // Buscar select primero (para HiloAX)
            const select = cell.querySelector('select');
            if (select) {
                const value = select.value ? select.value.trim() : '';
                return value === '' ? null : value;
            }

            // Buscar input
            const input = cell.querySelector('input');
            if (input) {
                const value = input.value ? input.value.trim() : '';
                return value === '' ? null : value;
            }

            // Para TotalPzas, priorizar el valor calculado si existe
            if (columnName === 'TotalPzas') {
                const span = cell.querySelector('span');
                if (span && span.hasAttribute('data-calculated-value')) {
                    return span.getAttribute('data-calculated-value');
                }
            }

            // Si no hay input ni select, usar textContent
            const text = cell.textContent ? cell.textContent.trim() : '';
            return text === '' ? null : text;
        };

        // Capturar valores numéricos (remover comas de formato)
        const getNumericValue = (columnName) => {
            const value = getCellValue(columnName);
            if (!value) return null;
            const cleaned = value.replace(/,/g, '');
            return cleaned === '' ? null : cleaned;
        };

        return {
            id: cb.getAttribute('data-id'),
            prioridad: prioridadInput ? prioridadInput.value.trim() : '',
            bomId: getCellValue('BomId'),
            bomName: getCellValue('BomName'),
            hiloAX: getCellValue('HiloAX'),
            mtsRollo: getNumericValue('MtsRollo'),
            pzasRollo: getNumericValue('PzasRollo'),
            totalRollos: getNumericValue('TotalRollos'),
            totalPzas: getNumericValue('TotalPzas'),
            repeticiones: getNumericValue('Repeticiones'),
            saldoMarbete: getNumericValue('SaldoMarbete'),
            densidad: getNumericValue('Densidad'),
            observaciones: getCellValue('Observaciones'),
            cambioRepaso: getCellValue('CambioRepaso'),
            combinaTram: getCellValue('CombinaTrama')
        };
    });
}

function descargarExcelBase64(data, fileName) {
    const byteCharacters = atob(data);
    const byteNumbers = new Array(byteCharacters.length);
    for (let i = 0; i < byteCharacters.length; i++) {
        byteNumbers[i] = byteCharacters.charCodeAt(i);
    }
    const byteArray = new Uint8Array(byteNumbers);
    const blob = new Blob([byteArray], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const link = document.createElement('a');
    const blobUrl = window.URL.createObjectURL(blob);
    link.href = blobUrl;
    link.download = fileName || 'liberar-ordenes.xlsx';
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();

    setTimeout(() => {
        document.body.removeChild(link);
        window.URL.revokeObjectURL(blobUrl);
    }, 100);
}

function liberarOrdenes() {
    const registros = obtenerRegistrosSeleccionados().filter(r => r.id);

    if (!registros.length) {
        Swal.fire({
            icon: 'warning',
            title: 'Sin registros',
            text: 'Selecciona al menos un registro para liberar.',
        });
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    Swal.fire({
        title: 'Liberar órdenes',
        html: `Se actualizarán <strong>${registros.length}</strong> registros seleccionados.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Liberar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#6b7280',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: () => {
            return fetch(liberarOrdenesUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ registros }),
            })
            .then(async response => {
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Error al liberar las órdenes.');
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(error.message);
            });
        }
    }).then(result => {
        if (result.isConfirmed && result.value) {
            const payload = result.value;
            if (payload.fileData) {
                descargarExcelBase64(payload.fileData, payload.fileName);
            }
            Swal.fire({
                icon: 'success',
                title: 'Órdenes liberadas',
                text: payload.message || 'Se actualizaron los registros seleccionados.',
                confirmButtonText: 'Aceptar',
            }).then(() => {
                window.location.href = payload.redirectUrl || redirectAfterLiberar;
            });
        }
    });
}

// Los checkboxes no cambian el estilo visual de las filas

// Función para calcular TotalPzas automáticamente cuando cambia TotalRollos
function calcularTotalPzas(changedInput) {
    const rowId = changedInput.getAttribute('data-row-id');
    if (!rowId) return;

    // Buscar la fila que contiene este input
    const row = changedInput.closest('.row-data');
    if (!row) return;

    // Obtener los valores de TotalRollos
    const totalRollosInput = row.querySelector('input[data-field="TotalRollos"]');

    // Buscar la celda de TotalPzas y el span dentro
    const totalPzasCell = row.querySelector('td[data-column="TotalPzas"]');
    const totalPzasSpan = totalPzasCell ? totalPzasCell.querySelector('span') : null;

    // Obtener PzasRollo del atributo data-pzas-rollo del input TotalRollos
    const pzasRollo = parseFloat(changedInput.getAttribute('data-pzas-rollo')) || 0;

    if (!totalRollosInput || !totalPzasSpan) return;

    const totalRollos = parseFloat(totalRollosInput.value) || 0;

    // Calcular TotalPzas = TotalRollos * PzasRollo
    const totalPzas = totalRollos * pzasRollo;

    // Actualizar el valor de TotalPzas (ahora es un span, no un input)
    if (totalPzas > 0) {
        const newTotalPzas = Math.round(totalPzas);
        totalPzasSpan.textContent = newTotalPzas.toLocaleString('es-MX');
        totalPzasSpan.setAttribute('data-calculated-value', newTotalPzas.toString());
    } else {
        totalPzasSpan.textContent = '';
        totalPzasSpan.removeAttribute('data-calculated-value');
    }
}

</script>

<style>
/* Menú contextual en encabezados */
.liberar-header-context th { cursor: context-menu; }
#liberar-context-menu-header {
    z-index: 99999 !important;
}
#liberar-context-menu-header:not(.hidden) {
    display: block;
}
#liberar-context-menu-header button {
    border: none;
    background: none;
    width: 100%;
    cursor: pointer;
}

/* Filas alternas: gris / blanco; seleccionada: blue-500 y texto blanco (solo visual) */
tr.row-data.row-selected {
    background-color: #3b82f6 !important;
    color: #fff;
}
tr.row-data.row-selected td {
    color: #fff !important;
}
tr.row-data.row-selected .prioridad-input,
tr.row-data.row-selected .bom-id-input,
tr.row-data.row-selected .bom-name-input,
tr.row-data.row-selected .editable-field,
tr.row-data.row-selected .hilo-ax-select {
    color: #fff !important;
    background-color: rgba(255,255,255,0.2) !important;
    border-color: rgba(255,255,255,0.5) !important;
}
tr.row-data.row-selected .hilo-ax-select option {
    background: #1e40af;
    color: #fff;
}
tr.row-data.row-selected .cambio-repaso-select {
    color: #111827 !important;
    background-color: #ffffff !important;
    border-color: #93c5fd !important;
}
tr.row-data.row-selected .cambio-repaso-select option {
    background: #ffffff;
    color: #111827;
}
tr.row-data.row-selected span[data-field] {
    color: #fff !important;
}
tr.row-data.row-selected td.pinned-column {
    background-color: #3b82f6 !important;
}
/* Hover en fila no seleccionada */
tr.row-data:not(.row-selected).row-odd:hover { background-color: #eff6ff !important; }
tr.row-data:not(.row-selected).row-even:hover { background-color: #dbeafe !important; }

.pinned-column {
    position: sticky !important;
    background-color: #fffbeb !important;
}

/* Asegurar que el thead completo se mantenga visible */
thead {
    z-index: 10 !important; /* Base para todos los encabezados */
}

/* Asegurar que los encabezados de columnas fijadas se mantengan visibles al hacer scroll */
thead th.pinned-column {
    position: sticky !important;
    top: 0 !important;
    background-color: #f59e0b !important;
    color: #fff !important;
    z-index: 20 !important; /* Mayor que las celdas pero menor que modales (z-50) */
}

/* Asegurar que las celdas de columnas fijadas también tengan z-index apropiado */
tbody td.pinned-column {
    position: sticky !important;
    background-color: #fffbeb !important;
    z-index: 10 !important;
    /* El top se establece dinámicamente en JavaScript según la altura del thead */
}

.valor-negativo {
    color: #dc2626;
    font-weight: bold;
}

/* Ocultar el icono del datalist */
.bom-id-input::-webkit-calendar-picker-indicator,
.bom-name-input::-webkit-calendar-picker-indicator {
    display: none !important;
    opacity: 0 !important;
    width: 0 !important;
    height: 0 !important;
}

.bom-id-input::-webkit-list-button,
.bom-name-input::-webkit-list-button {
    display: none !important;
}

.bom-id-input::-webkit-input-placeholder,
.bom-name-input::-webkit-input-placeholder {
    color: #9ca3af;
}

/* Asegurar que el datalist se muestre automáticamente */
.bom-id-input:focus,
.bom-name-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
}

/* Estilos para el select de HiloAX */
.hilo-ax-select {
    min-width: 200px !important;
    width: 100% !important;
    padding: 0.5rem 0.75rem !important;
    font-size: 1rem !important;
    line-height: 1.5 !important;
}

/* Asegurar que la columna HiloAX tenga suficiente espacio */
td[data-column="HiloAX"] {
    min-width: 220px;
    width: 220px;
}

th[data-field="HiloAX"] {
    min-width: 220px !important;
    width: 220px !important;
}

/* Estilos para los inputs de L.Mat y anchos de columna */
td[data-column="BomId"],
th[data-field="BomId"] {
    min-width: 180px !important;
    width: 180px;
}
td[data-column="BomName"],
th[data-field="BomName"] {
    min-width: 300px !important;
    width: 300px;
}
td[data-column="Observaciones"],
th[data-field="Observaciones"] {
    min-width: 260px !important;
    width: 260px;
}
td[data-column="CambioRepaso"],
th[data-field="CambioRepaso"] {
    min-width: 170px !important;
    width: 170px;
}
.bom-id-input {
    min-width: 160px !important;
    width: 100% !important;
}

.bom-name-input {
    min-width: 280px !important;
    width: 100% !important;
}

.observaciones-input {
    min-width: 240px !important;
    width: 100% !important;
}

.cambio-repaso-select {
    min-width: 150px !important;
    width: 100% !important;
}

/* Estilos para el input de densidad */
.densidad-input {
    min-width: 150px !important;
    width: 100% !important;
}

/* Estilos para el input de Total Pzas - más ancho para 5 dígitos */
.total-pzas-input {
    min-width: 80px !important;
    width: 100% !important;
}
</style>
@endsection
