@extends('layouts.app', ['ocultarBotones' => true])

@section('navbar-right')
<div class="flex items-center gap-2">
    <button onclick="openPinColumnsModal()" class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600 flex items-center gap-2">
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
        // Columnas según la lista del usuario
        $columns = [
            ['field' => 'select', 'label' => 'Seleccionar'],
            ['field' => 'prioridad', 'label' => 'Prioridad'],
            ['field' => 'Maquina', 'label' => 'Maq'],
            ['field' => 'Ancho', 'label' => 'Ancho'],
            ['field' => 'EficienciaSTD', 'label' => 'Ef Std'],
            ['field' => 'VelocidadSTD', 'label' => 'Velocidad'],
            ['field' => 'FibraRizo', 'label' => 'Hilo'],
            ['field' => 'CalibrePie2', 'label' => 'Calibre Pie'],
            ['field' => 'ItemId', 'label' => 'Clave AX'],
            ['field' => 'NombreProducto', 'label' => 'Producto'],
            ['field' => 'InventSizeId', 'label' => 'Tamaño AX'],
            ['field' => 'TotalPedido', 'label' => 'Pedido'],
            ['field' => 'NoProduccion', 'label' => 'Producción'],
            ['field' => 'SaldoPedido', 'label' => 'Saldos'],
            ['field' => 'ProgramarProd', 'label' => 'Day Shedulling'],
            ['field' => 'NoProduccion', 'label' => 'Orden Prod'],
            ['field' => 'Programado', 'label' => 'INN'],
            ['field' => 'NombreProyecto', 'label' => 'Descripción'],
            ['field' => 'AplicacionId', 'label' => 'Aplic'],
            ['field' => 'Observaciones', 'label' => 'Obs'],
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
            ['field' => 'CombinaTrama', 'label' => 'Comb Trama'],
            ['field' => 'BomId', 'label' => 'L.Mat'],
            ['field' => 'BomName', 'label' => 'Nombre L.Mat'],
            ['field' => 'HiloAX', 'label' => 'Hilo AX'],
        ];

        $formatValue = function($registro, $field) {
            if ($field === 'select') {
                $checked = 'checked'; // Todos marcados por defecto
                return '<input type="checkbox" class="row-checkbox w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 mx-auto block" data-id="' . ($registro->Id ?? '') . '" ' . $checked . '>';
            }

            if ($field === 'prioridad') {
                // Usar PrioridadAnterior si existe, sino usar Prioridad actual, sino vacío
                $prioridadAnterior = $registro->PrioridadAnterior ?? '';
                $prioridadActual = $registro->Prioridad ?? '';
                $prioridad = !empty($prioridadAnterior) ? $prioridadAnterior : $prioridadActual;
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
                $id = $registro->Id ?? '';
                $valor = $registro->BomId ?? '';
                return '<input type="text"
                              class="bom-id-input w-full px-3 py-2 text-base border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                              value="' . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . '"
                              data-id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"
                              placeholder="L.Mat"
                              maxlength="20"
                              style="min-width: 200px;">';
            }

            if ($field === 'BomName') {
                $id = $registro->Id ?? '';
                $valor = $registro->BomName ?? '';
                return '<input type="text"
                              class="bom-name-input w-full px-3 py-2 text-base border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                              value="' . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . '"
                              data-id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"
                              placeholder="Nombre L.Mat"
                              maxlength="60"
                              style="min-width: 240px;">';
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
                if ($parteDecimal > 0.50) {
                    if ($valorFloat >= 0) {
                        return (string)(int)ceil($valorFloat);
                    } else {
                        return (string)(int)floor($valorFloat);
                    }
                } else {
                    return (string)$parteEntera;
                }
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
                        <thead class="bg-blue-500 text-white" style="position: sticky; top: 0; z-index: 10;">
                            <tr>
                                @foreach($columns as $index => $col)
                                <th class="px-2 py-2 text-left text-sm font-semibold text-white whitespace-nowrap column-{{ $index }}"
                                    style="position: sticky; top: 0; background-color: #3b82f6; min-width: {{ $col['field'] === 'prioridad' ? '300px' : '80px' }}; z-index: 10;"
                                    data-index="{{ $index }}"
                                    data-field="{{ $col['field'] }}">
                                    {{ $col['label'] }}
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach($registros as $index => $registro)
                            <tr class="hover:bg-blue-50 transition-colors row-data" data-id="{{ $registro->Id ?? '' }}">
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


<script>
const liberarOrdenesUrl = '{{ route('programa-tejido.liberar-ordenes.procesar') }}';
const redirectAfterLiberar = '{{ route('catalogos.req-programa-tejido') }}';

// Variables globales para columnas
let pinnedColumns = [];
let hiddenColumns = [];
let filtersActive = false;

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded - Iniciando relleno de prioridades');

    // Marcar todos los checkboxes por defecto
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });

    // Rellenar automáticamente los inputs de prioridad con el valor del registro anterior
    const prioridadInputs = document.querySelectorAll('.prioridad-input');
    console.log('Inputs de prioridad encontrados:', prioridadInputs.length);

    prioridadInputs.forEach((input, index) => {
        const prioridadAnterior = input.getAttribute('data-prioridad-anterior') || '';
        const valorActual = input.value.trim();
        const id = input.getAttribute('data-id');

        console.log(`Input ${index}:`, {
            id: id,
            valorActual: valorActual,
            prioridadAnterior: prioridadAnterior,
            tieneValor: !!valorActual,
            tienePrioridadAnterior: !!prioridadAnterior
        });

        // Si el input está vacío, intentar rellenarlo
        if (!valorActual) {
            // Primero intentar con data-prioridad-anterior
            if (prioridadAnterior) {
                input.value = prioridadAnterior;
                console.log(`Input ${index} rellenado con prioridad anterior:`, prioridadAnterior);
            }
            // Si no hay prioridad anterior y no es el primero, buscar el valor del input anterior
            else if (index > 0) {
                const inputAnterior = prioridadInputs[index - 1];
                if (inputAnterior && inputAnterior.value.trim()) {
                    input.value = inputAnterior.value.trim();
                    console.log(`Input ${index} rellenado con valor del input anterior:`, inputAnterior.value.trim());
                }
            }
        }
    });

    // Inicializar posiciones de columnas fijadas
    updatePinnedColumnsPositions();

    console.log('DOMContentLoaded - Finalizado relleno de prioridades');
});

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
    }
}

function unpinColumn(index) {
    pinnedColumns = pinnedColumns.filter(i => i !== index);
    updatePinnedColumnsPositions();
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

function openFiltersModal() {
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
                if (colSelect) colSelect.focus();
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
    // Aplicar filtros automáticamente (mostrar todas las filas)
    applyFiltersSilent();
    Swal.close();
    setTimeout(() => {
        openFiltersModal();
    }, 100);
}

// Función para aplicar filtros sin mostrar notificación
function applyFiltersSilent() {
    const rows = document.querySelectorAll('.row-data');

    rows.forEach(row => {
        let showRow = true;

        if (liberarOrdenesFilters.length > 0) {
            liberarOrdenesFilters.forEach(filter => {
                const cell = row.querySelector(`td[data-column="${filter.column}"]`);
                const cellText = cell ? cell.textContent.toLowerCase().trim() : '';
                const filterValue = filter.value.toLowerCase().trim();

                if (!cellText.includes(filterValue)) {
                    showRow = false;
                }
            });
        }

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
        timer: 2000,
        showConfirmButton: false
    });
}

// Funciones para liberar órdenes
function obtenerRegistrosSeleccionados() {
    return Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => {
        const row = cb.closest('tr');
        const prioridadInput = row ? row.querySelector('.prioridad-input') : null;
        return {
            id: cb.getAttribute('data-id'),
            prioridad: prioridadInput ? prioridadInput.value.trim() : ''
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
</script>

<style>
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
}

.valor-negativo {
    color: #dc2626;
    font-weight: bold;
}
</style>
@endsection
