@extends('layouts.app', ['ocultarBotones' => true])

@section('navbar-right')
<div class="flex items-center gap-2">
    <button type="button" data-accion="fijar-columnas" aria-label="Fijar columnas" title="Fijar columnas" class="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 flex items-center gap-2">
        <i class="fas fa-thumbtack" aria-hidden="true"></i>
    </button>
    <button type="button" data-accion="ocultar-columnas" aria-label="Ocultar columnas" title="Ocultar columnas" class="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600 flex items-center gap-2">
        <i class="fas fa-eye-slash" aria-hidden="true"></i>
    </button>
    <button type="button" data-accion="filtros" aria-label="Filtros" title="Filtros" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 flex items-center gap-2">
        <i class="fas fa-filter" aria-hidden="true"></i>
    </button>
    {{-- En Muestras el servidor exige crear del módulo Muestras (idrol 5, decisión del owner):
         el botón usa el mismo permiso para no ofrecer una acción que termina en 403. --}}
    {{-- Bloque, no @php(...): Blade empareja un @php( en línea con el siguiente @endphp y se come la vista hasta ahí. --}}
    @php
        $superficieLiberar = \App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface::actual();
    @endphp
    <x-navbar.button-create
        id="btn-liberar"
        data-accion="liberar"
        title="Liberar"
        text="Liberar"
        module="Programa Tejido"
        :moduleId="$superficieLiberar->esMuestras() ? $superficieLiberar->moduloPermiso() : null"
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
            ['field' => 'no_produccion', 'label' => 'No. Orden'],
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
            ['field' => 'PesoRollo', 'label' => 'Peso x Rollo'],
            ['field' => 'MtsRollo', 'label' => 'Metros x Rollo'],
            ['field' => 'PzasRollo', 'label' => 'Pzas x Rollo'],
            ['field' => 'TotalRollos', 'label' => 'Total Rollos'],
            ['field' => 'TotalPzas', 'label' => 'Total Pzas'],
            ['field' => 'Repeticiones', 'label' => 'Repeticiones'],
            ['field' => 'SaldoMarbete', 'label' => 'No Marbetes'],
            ['field' => 'NoTiras', 'label' => 'Tiras'],
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

            if ($field === 'no_produccion') {
                $id = $registro->Id ?? '';
                return '<input type="text"
                               class="no-produccion-input w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                               data-id="' . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . '"
                               placeholder="Auto (folio)"
                               style="min-width:160px;">';
            }

            if ($field === 'prioridad') {
                // Preferir valor guardado; si no hay, sugerir PrioridadAnterior. No usar empty(): "0" es válido.
                $prioridadAnterior = $registro->PrioridadAnterior ?? '';
                $prioridadActual = $registro->Prioridad;
                $prioridad = ($prioridadActual !== null && trim((string) $prioridadActual) !== '')
                    ? trim((string) $prioridadActual)
                    : (string) $prioridadAnterior;
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

            // Columna Flog. Editable en cualquier renglón. Toda orden lleva flog (AsignarFlogs = 1
            // por default en CatCodificados); si el artículo + tamaño está en el catálogo
            // TwArticulosFelpas el renglón decide: el check sale marcado y desmarcarlo guarda 0.
            if ($field === 'FlogsId') {
                $rowId = htmlspecialchars((string) ($registro->Id ?? ''), ENT_QUOTES, 'UTF-8');
                $decide = !empty($registro->RequiereDecisionFlog);
                // El flog sugerido de AX ya viene resuelto del controlador (una sola consulta
                // para todo el lote): el front ya no lo pide por renglón al cargar.
                $valorFlog = trim((string) ($registro->FlogsId ?? ''));
                if ($valorFlog === '' && $decide) {
                    $valorFlog = trim((string) ($registro->FlogSugerido ?? ''));
                }
                $valorEsc = htmlspecialchars($valorFlog, ENT_QUOTES, 'UTF-8');

                $check = !$decide ? '' :
                    '<div class="flex items-center gap-1 mb-1 whitespace-nowrap">
                        <label class="flog-toggle flex items-center gap-1 text-xs text-gray-600 cursor-pointer"
                               title="Este artículo está en el catálogo de flogs: desmarca si esta orden NO debe llevar flog.">
                            <input type="checkbox" class="flog-check" data-row-id="' . $rowId . '" checked>
                            <span>Asignar flogs</span>
                        </label>
                        <span class="px-1.5 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800"
                              title="Este artículo está en el catálogo de flogs: desmarca si esta orden NO debe llevar flog.">Decide flog</span>
                    </div>';

                return '<div class="flog-decision' . ($decide ? ' bg-amber-50 rounded p-1' : '') . '">
                            ' . $check . '
                            <input type="text"
                                   class="flog-input w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   style="min-width: 220px;"
                                   list="flog-options"
                                   autocomplete="off"
                                   data-row-id="' . $rowId . '"
                                   value="' . $valorEsc . '"
                                   maxlength="60"
                                   placeholder="Flog">
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
            if ($field === 'PesoRollo') {
                $rowId = $registro->Id ?? uniqid('row_');
                $rowId = htmlspecialchars((string) $rowId, ENT_QUOTES, 'UTF-8');
                $value = $registro->{$field} ?? null;
                $valueFormatted = $value !== null ? (is_numeric($value) ? number_format((float)$value, 2, '.', '') : '') : '';

                return '<input type="number"
                              step="0.01"
                              min="0"
                              class="editable-field peso-rollo-input w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                              value="' . htmlspecialchars($valueFormatted, ENT_QUOTES, 'UTF-8') . '"
                              data-field="' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '"
                              data-row-id="' . htmlspecialchars($rowId, ENT_QUOTES, 'UTF-8') . '">';
            }

            // Tiras: solo en Karl Mayer se capturan a mano; en el resto vienen del artículo.
            if ($field === 'NoTiras' && \App\Support\Planeacion\TelarSalonResolver::esKarlMayer($registro->SalonTejidoId ?? null, $registro->NoTelarId ?? null)) {
                $rowId = htmlspecialchars((string) ($registro->Id ?? uniqid('row_')), ENT_QUOTES, 'UTF-8');
                $value = $registro->NoTiras ?? null;
                $valueFormatted = is_numeric($value) ? (string) (int) $value : '';

                return '<input type="number"
                              step="1"
                              min="1"
                              class="editable-field no-tiras-input w-full px-2 py-1 text-sm border border-purple-300 rounded focus:outline-none focus:ring-2 focus:ring-purple-500"
                              value="' . htmlspecialchars($valueFormatted, ENT_QUOTES, 'UTF-8') . '"
                              data-field="NoTiras"
                              data-row-id="' . $rowId . '"
                              data-original-value="' . htmlspecialchars($valueFormatted, ENT_QUOTES, 'UTF-8') . '"
                              title="Karl Mayer: las tiras se capturan a mano y recalculan repeticiones, marbetes y rollos.">';
            }

            $camposNumericosSoloLectura = ['MtsRollo', 'PzasRollo', 'TotalPzas', 'Repeticiones', 'SaldoMarbete', 'NoTiras', 'Densidad'];
            if (in_array($field, $camposNumericosSoloLectura, true)) {
                $value = $registro->{$field} ?? null;

                // Formatear según el campo: Densidad (4 decimales), MtsRollo (decimales sin límite), otros (0 decimales)
                if ($field === 'Densidad') {
                    $valueFormatted = $value !== null ? (is_numeric($value) ? number_format((float)$value, 4, '.', ',') : '') : '';
                } elseif ($field === 'MtsRollo') {
                    // MtsRollo se muestra con decimales y separador de miles
                    $valueFormatted = $value !== null ? (is_numeric($value) ? number_format((float)$value, 2, '.', ',') : '') : '';
                } elseif ($field === 'SaldoMarbete') {
                    $valueFormatted = $value !== null ? (is_numeric($value) ? number_format(round((float) $value, 0), 0, '.', ',') : '') : '';
                } else {
                    // TotalPzas, PzasRollo, Repeticiones: números enteros con separador de miles
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
                return e((string) $value);
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

            // Texto libre (producto, descripción, flog…): va a {!! !!}, así que se escapa aquí.
            return e((string) $value);
        };
        @endphp
        @php
        $paginaLiberar = [
            'rutas' => [
                'procesar' => route('programa-tejido.liberar-ordenes.procesar'),
                'redirect' => route('catalogos.req-programa-tejido'),
                'tipoHilo' => route('programa-tejido.liberar-ordenes.tipo-hilo'),
                'bom' => route('programa-tejido.liberar-ordenes.bom'),
                'codigoDibujo' => route('programa-tejido.liberar-ordenes.codigo-dibujo'),
                'flog' => route('programa-tejido.liberar-ordenes.flog'),
                'flogs' => url('/programa-tejido/flogs-id-from-twflogs'),
            ],
            'columnas' => $columns,
            'pesoKarlMayer' => (string) \App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController::PESO_ROLLO_KG_KARL_MAYER,
        ];
        @endphp
        {{-- Valores del servidor para resources/js/modulos/programa-tejido/liberar-ordenes (receta 19-00 §2). --}}
        <div id="liberar-ordenes-config" hidden data-pagina='@json($paginaLiberar)'></div>


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
                                            data-accion="seleccionar-todo"
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
                            @php
                                $__tcFel = (string) ($registro->TamanoClave ?? '');
                                $__nomFel = (string) ($registro->NombreProducto ?? '');
                                // Karl Mayer tiene reglas propias: no aplica felpa y el renglón se pinta morado.
                                $__esKm = \App\Support\Planeacion\TelarSalonResolver::esKarlMayer(
                                    $registro->SalonTejidoId ?? null,
                                    $registro->NoTelarId ?? null
                                );
                                $__esFelpa = ! $__esKm && (
                                    ($__tcFel !== '' && stripos($__tcFel, 'FELPA') !== false)
                                    || ($__nomFel !== '' && stripos($__nomFel, 'FELPA') !== false)
                                );
                            @endphp
                            <tr class="transition-colors row-data cursor-pointer {{ $__esKm ? 'row-km' : '' }} {{ $loop->even ? 'bg-gray-100 row-even' : 'bg-white row-odd' }}"
                                data-es-km="{{ $__esKm ? '1' : '0' }}"
                                data-id="{{ $registro->Id ?? '' }}"
                                data-decision-flog="{{ !empty($registro->RequiereDecisionFlog) ? '1' : '0' }}"
                                data-item-id="{{ $registro->ItemId ?? '' }}"
                                data-salon-tejido-id="{{ $registro->SalonTejidoId ?? '' }}"
                                data-peso-crudo="{{ $registro->PesoCrudo ?? '' }}"
                                data-no-tiras="{{ $registro->NoTiras ?? '' }}"
                                data-largo-crudo="{{ $registro->LargoCrudo ?? '' }}"
                                data-saldo-pedido="{{ $registro->SaldoPedido ?? '' }}"
                                data-base-pedido="{{ $registro->BasePedido ?? '' }}"
                                data-invent-size-id="{{ $registro->InventSizeId ?? '' }}"
                                data-es-felpa="{{ $__esFelpa ? '1' : '0' }}"
                                title="Clic en la fila para marcar como referencia visual (azul)">
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

{{-- Flogs vigentes de AX para el autocompletado de la columna Flog (una sola lista para toda la grilla) --}}
<datalist id="flog-options"></datalist>

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

/* Karl Mayer: se pinta morado para que se vea de golpe que no sigue las mismas reglas
   (no aplica felpa y las tiras se capturan). Va antes de .row-selected para que la
   selección azul siga ganando. */
tr.row-data.row-km,
tr.row-data.row-km.row-odd,
tr.row-data.row-km.row-even {
    background-color: #f3e8ff !important;  /* purple-100 */
}
tr.row-data.row-km td {
    border-color: #e9d5ff;
}
tr.row-data.row-km td[data-column="Maquina"],
tr.row-data.row-km td[data-column="NoTiras"] {
    font-weight: 600;
    color: #6b21a8;                        /* purple-800 */
}
/* Más específico que el hover genérico de abajo, que va después en la hoja. */
tbody tr.row-data.row-km:not(.row-selected):hover,
tbody tr.row-data.row-km.row-odd:not(.row-selected):hover,
tbody tr.row-data.row-km.row-even:not(.row-selected):hover {
    background-color: #d8b4fe !important;  /* purple-300 */
}

/* Seleccionada en KM: morado oscuro en vez del azul, para no perder de vista que es Karl Mayer.
   El `tbody` está solo para ganarle en especificidad al bloque .row-selected de abajo. */
tbody tr.row-data.row-km.row-selected {
    background-color: #7e22ce !important;  /* purple-700 */
}
tbody tr.row-data.row-km.row-selected .hilo-ax-select option,
tbody tr.row-data.row-km.row-selected .cambio-repaso-select option {
    background: #581c87 !important;        /* purple-900 */
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
@push('scripts')
    @vite('resources/js/modulos/programa-tejido/liberar-ordenes/index.ts')
@endpush
@endsection
