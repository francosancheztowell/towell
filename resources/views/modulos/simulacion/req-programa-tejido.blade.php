@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Simulación - Programa de Tejido')

@section('content')
<div class="w-full px-0 py-0">
    <div class="bg-white shadow overflow-hidden w-full" style="max-width: 100%;">
        @php
            $columns = [
                ['field' => 'EnProceso', 'label' => 'Estado'],
                ['field' => 'CuentaRizo', 'label' => 'Cuenta'],
                ['field' => 'CalibreRizo2', 'label' => 'Calibre Rizo'],
                ['field' => 'SalonTejidoId', 'label' => 'Salón'],
                ['field' => 'NoTelarId', 'label' => 'Telar'],
                ['field' => 'Ultimo', 'label' => 'Último'],
                ['field' => 'CambioHilo', 'label' => 'Cambios Hilo'],
                ['field' => 'Maquina', 'label' => 'Maq'],
                ['field' => 'Ancho', 'label' => 'Ancho'],
                ['field' => 'EficienciaSTD', 'label' => 'Ef Std'],
                ['field' => 'VelocidadSTD', 'label' => 'Vel'],
                ['field' => 'FibraRizo', 'label' => 'Hilo'],
                ['field' => 'CalibrePie2', 'label' => 'Calibre Pie'],
                ['field' => 'CalendarioId', 'label' => 'Jornada'],
                ['field' => 'TamanoClave', 'label' => 'Clave Mod.'],
                ['field' => 'NoExisteBase', 'label' => 'Usar cuando no existe en base'],
                ['field' => 'ItemId', 'label' => 'Clave AX'],
                ['field' => 'InventSizeId', 'label' => 'Tamaño AX'],
                ['field' => 'Rasurado', 'label' => 'Rasurado'],
                ['field' => 'NombreProducto', 'label' => 'Producto'],
                ['field' => 'TotalPedido', 'label' => 'Pedido'],
                ['field' => 'Produccion', 'label' => 'Producción'],
                ['field' => 'SaldoPedido', 'label' => 'Saldos'],
                ['field' => 'SaldoMarbete', 'label' => 'Saldo Marbetes'],
                ['field' => 'ProgramarProd', 'label' => 'Día Scheduling'],
                ['field' => 'NoProduccion', 'label' => 'Orden Prod.'],
                ['field' => 'Programado', 'label' => 'INN'],
                ['field' => 'FlogsId', 'label' => 'Id Flog'],
                ['field' => 'NombreProyecto', 'label' => 'Descrip.'],
                ['field' => 'CustName', 'label' => 'Nombre Cliente'],
                ['field' => 'AplicacionId', 'label' => 'Aplic.'],
                ['field' => 'Observaciones', 'label' => 'Obs'],
                ['field' => 'TipoPedido', 'label' => 'Tipo Ped.'],
                ['field' => 'NoTiras', 'label' => 'Tiras'],
                ['field' => 'Peine', 'label' => 'Pei.'],
                ['field' => 'Luchaje', 'label' => 'Lcr'],
                ['field' => 'PesoCrudo', 'label' => 'Pcr'],
                ['field' => 'CalibreTrama2', 'label' => 'Calibre Tra'],
                ['field' => 'FibraTrama', 'label' => 'Fibra Trama'],
                ['field' => 'DobladilloId', 'label' => 'Dob'],
                ['field' => 'PasadasTrama', 'label' => 'Pasadas Tra'],
                ['field' => 'PasadasComb1', 'label' => 'Pasadas C1'],
                ['field' => 'PasadasComb2', 'label' => 'Pasadas C2'],
                ['field' => 'PasadasComb3', 'label' => 'Pasadas C3'],
                ['field' => 'PasadasComb4', 'label' => 'Pasadas C4'],
                ['field' => 'PasadasComb5', 'label' => 'Pasadas C5'],
                ['field' => 'AnchoToalla', 'label' => 'Ancho por Toalla'],
                ['field' => 'CodColorTrama', 'label' => 'Código Color Tra'],
                ['field' => 'ColorTrama', 'label' => 'Color Tra'],
                ['field' => 'CalibreComb1', 'label' => 'Calibre C1'],
                ['field' => 'FibraComb1', 'label' => 'Fibra C1'],
                ['field' => 'CodColorComb1', 'label' => 'Código Color C1'],
                ['field' => 'NombreCC1', 'label' => 'Color C1'],
                ['field' => 'CalibreComb2', 'label' => 'Calibre C2'],
                ['field' => 'FibraComb2', 'label' => 'Fibra C2'],
                ['field' => 'CodColorComb2', 'label' => 'Código Color C2'],
                ['field' => 'NombreCC2', 'label' => 'Color C2'],
                ['field' => 'CalibreComb3', 'label' => 'Calibre C3'],
                ['field' => 'FibraComb3', 'label' => 'Fibra C3'],
                ['field' => 'CodColorComb3', 'label' => 'Código Color C3'],
                ['field' => 'NombreCC3', 'label' => 'Color C3'],
                ['field' => 'CalibreComb4', 'label' => 'Calibre C4'],
                ['field' => 'FibraComb4', 'label' => 'Fibra C4'],
                ['field' => 'CodColorComb4', 'label' => 'Código Color C4'],
                ['field' => 'NombreCC4', 'label' => 'Color C4'],
                ['field' => 'CalibreComb5', 'label' => 'Calibre C5'],
                ['field' => 'FibraComb5', 'label' => 'Fibra C5'],
                ['field' => 'CodColorComb5', 'label' => 'Código Color C5'],
                ['field' => 'NombreCC5', 'label' => 'Color C5'],
                ['field' => 'MedidaPlano', 'label' => 'Plano'],
                ['field' => 'CuentaPie', 'label' => 'Cuenta Pie'],
                ['field' => 'CodColorCtaPie', 'label' => 'Código Color Pie'],
                ['field' => 'NombreCPie', 'label' => 'Color Pie'],
                ['field' => 'PesoGRM2', 'label' => 'Peso (gr/m²)'],
                ['field' => 'DiasEficiencia', 'label' => 'Días Ef.'],
                ['field' => 'ProdKgDia', 'label' => 'Prod (Kg)/Día'],
                ['field' => 'StdDia', 'label' => 'Std/Día'],
                ['field' => 'ProdKgDia2', 'label' => 'Prod (Kg)/Día 2'],
                ['field' => 'StdToaHra', 'label' => 'Std (Toa/Hr) 100%'],
                ['field' => 'DiasJornada', 'label' => 'Días Jornada'],
                ['field' => 'HorasProd', 'label' => 'Horas'],
                ['field' => 'StdHrsEfect', 'label' => 'Std/Hr Efectivo'],
                ['field' => 'FechaInicio', 'label' => 'Inicio'],
                ['field' => 'Calc4', 'label' => 'Calc4'],
                ['field' => 'Calc5', 'label' => 'Calc5'],
                ['field' => 'Calc6', 'label' => 'Calc6'],
                ['field' => 'FechaFinal', 'label' => 'Fin'],
                ['field' => 'EntregaProduc', 'label' => 'Fecha Compromiso Prod.'],
                ['field' => 'EntregaPT', 'label' => 'Fecha Compromiso PT'],
                ['field' => 'EntregaCte', 'label' => 'Entrega'],
                ['field' => 'PTvsCte', 'label' => 'Dif vs Compromiso'],
            ];

            $formatValue = function ($registro, $field) {
                $value = $registro->{$field} ?? null;
                if ($value === null || $value === '') {
                    return '';
                }

                // Checkbox para EnProceso
                if ($field === 'EnProceso') {
                    $checked = ($value == 1 || $value === true) ? 'checked' : '';
                    return '<input type="checkbox" ' . $checked . ' disabled class="w-4 h-4 text-stone-600 bg-gray-100 border-gray-300 rounded focus:ring-stone-500">';
                }

                // Mapear 'UL' como 1 para la columna 'Ultimo'
                if ($field === 'Ultimo') {
                    $sv = strtoupper(trim((string) $value));
                    if ($sv === 'UL') {
                        return '1';
                    }
                }

                // EficienciaSTD en porcentaje
                if ($field === 'EficienciaSTD' && is_numeric($value)) {
                    $porcentaje = (float) $value * 100;
                    return round($porcentaje) . '%';
                }

                // Campos fecha conocidos
                $fechaCampos = [
                    'Programado',
                    'ProgramarProd',
                    'FechaInicio',
                    'FechaFinal',
                    'EntregaProduc',
                    'EntregaPT',
                    'EntregaCte',
                ];

                // Campos que son solo fecha (sin hora)
                $fechaSoloCampos = ['EntregaProduc', 'EntregaPT'];

                if (in_array($field, $fechaCampos, true)) {
                    try {
                        if ($value instanceof \Carbon\Carbon) {
                            $dt = $value;
                        } else {
                            $dt = \Carbon\Carbon::parse($value);
                        }

                        if ($dt->year <= 1970) {
                            return '';
                        }

                        if (in_array($field, $fechaSoloCampos, true)) {
                            return $dt->format('d/m/Y');
                        }

                        return $dt->format('d/m/Y H:i');
                    } catch (\Exception $e) {
                        return '';
                    }
                }

                // Números con decimales (no enteros puros)
                if (is_numeric($value) && !preg_match('/^\d+$/', (string) $value)) {
                    return number_format((float) $value, 2);
                }

                return $value;
            };
        @endphp

        @if ($registros->count() > 0)
            <div class="overflow-x-auto">
                <div class="overflow-y-auto" style="max-height: 320px;">
                    <table id="mainTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-stone-700 text-white">
                            <tr>
                                @foreach ($columns as $index => $col)
                                    <th
                                        class="px-2 py-1 text-left text-xs font-semibold bg-stone-700 text-white whitespace-nowrap column-{{ $index }}"
                                        style="position: sticky; top: 0; z-index: 30;  min-width: 80px;"
                                        data-column="{{ $col['field'] }}"
                                        data-index="{{ $index }}"
                                    >
                                        {{ $col['label'] }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach ($registros as $rowIndex => $registro)
                                <tr
                                    class="hover:bg-stone-50 cursor-pointer selectable-row"
                                    data-row-index="{{ $rowIndex }}"
                                    data-id="{{ $registro->Id ?? $registro->id ?? '' }}"
                                >
                                    @foreach ($columns as $colIndex => $col)
                                        @php
                                            $isFecha = in_array($col['field'], [
                                                'Programado',
                                                'ProgramarProd',
                                                'FechaInicio',
                                                'FechaFinal',
                                                'EntregaProduc',
                                                'EntregaPT',
                                                'EntregaCte',
                                            ], true);
                                        @endphp
                                        <td
                                            class="px-3 py-2 text-sm text-gray-700 {{ $isFecha ? 'whitespace-normal' : 'whitespace-nowrap' }} column-{{ $colIndex }}"
                                            data-column="{{ $col['field'] }}"
                                        >
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
                <i class="fas fa-database text-stone-500 text-4xl mb-4"></i>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay registros de simulación</h3>
                <p class="mt-1 text-sm text-gray-500 mb-2">
                    La tabla de simulación está vacía.
                </p>
                <p class="mt-1 text-xs text-gray-600 mb-4">
                    Puedes llenar la simulación con los datos actuales de Programa de Tejido.
                </p>
                <div class="mt-6">
                    <button
                        id="btnLlenarSimulacion"
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-stone-700 hover:bg-stone-800 transition-colors"
                    >
                        <i class="fas fa-copy mr-2"></i>
                        Llenar Simulación
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Tabla detalle SimulacionProgramaTejidoLine --}}
@include('components.tables.simulacion.simulacion-programa-tejido-line-table')


<script>
// ===== Config global desde Blade =====
const COLUMN_OPTIONS = @json(
    array_map(function ($c) {
        return ['field' => $c['field'], 'label' => $c['label']];
    }, $columns)
);

// ===== Estado =====
let filters = [];
let hiddenColumns = [];
let pinnedColumns = [];
let allRows = [];
let selectedRowIndex = -1;
let selectedRowId = null;

// ===== Helpers DOM =====
const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
const tbodyEl = () => $('#mainTable tbody');

// ===================== Filtros =====================
function renderFilterModalContent() {
    const options = COLUMN_OPTIONS;

    const filtrosHTML = filters.length
        ? `<div class="mb-4 p-3 bg-gray-50 rounded-lg">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Filtros Activos:</h4>
                <div class="space-y-1">
                    ${filters.map((f, i) => `
                        <div class="flex items-center justify-between bg-white p-2 rounded border">
                            <span class="text-xs">${f.column}: ${f.value}</span>
                            <button onclick="removeFilter(${i})" class="text-red-500 hover:text-red-700 text-xs">×</button>
                        </div>
                    `).join('')}
                </div>
           </div>`
        : '';

    return `
        ${filtrosHTML}
        <div class="text-left space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Columna</label>
                <select id="filtro-columna" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-stone-500">
                    <option value="">Selecciona una columna...</option>
                    ${options.map(col => `<option value="${col.field}">${col.label}</option>`).join('')}
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Valor a buscar</label>
                <input type="text" id="filtro-valor" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-stone-500" placeholder="Ingresa el valor a buscar">
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" id="btn-agregar-otro" class="flex-1 px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm">
                    + Agregar Otro Filtro
                </button>
            </div>
        </div>
    `;
}

function openFilterModal() {
    Swal.fire({
        title: 'Filtrar por Columna',
        html: renderFilterModalContent(),
        showCancelButton: true,
        confirmButtonText: 'Agregar Filtro',
        cancelButtonText: 'Cerrar',
        confirmButtonColor: '#44403c',
        cancelButtonColor: '#6b7280',
        width: '450px',
        didOpen: () => {
            const btnAgregarOtro = $('#btn-agregar-otro');
            if (!btnAgregarOtro) return;

            btnAgregarOtro.addEventListener('click', () => {
                const col = $('#filtro-columna').value;
                const val = $('#filtro-valor').value;
                if (!col || !val) {
                    Swal.showValidationMessage('Selecciona columna y valor');
                    return;
                }
                if (filters.some(f => f.column === col && f.value === val)) {
                    Swal.showValidationMessage('Este filtro ya está activo');
                    return;
                }

                filters.push({ column: col, value: val });
                applyFilters();
                showToast('Filtro agregado correctamente', 'success');
                Swal.update({ html: renderFilterModalContent() });
            });
        },
        preConfirm: () => {
            const col = $('#filtro-columna').value;
            const val = $('#filtro-valor').value;
            if (!col || !val) {
                Swal.showValidationMessage('Selecciona columna y valor');
                return false;
            }
            if (filters.some(f => f.column === col && f.value === val)) {
                Swal.showValidationMessage('Este filtro ya está activo');
                return false;
            }
            return { column: col, value: val };
        }
    }).then(res => {
        if (res.isConfirmed && res.value) {
            filters.push(res.value);
            applyFilters();
            showToast('Filtro agregado correctamente', 'success');
        }
    });
}

function removeFilter(index) {
    filters.splice(index, 1);
    applyFilters();
    showToast('Filtro eliminado', 'info');
    Swal.update({ html: renderFilterModalContent() });
}

function applyFilters() {
    const tb = tbodyEl();
    if (!tb) return;

    let rows = allRows.slice();

    if (filters.length) {
        rows = rows.filter(tr =>
            filters.every(f => {
                const cell = tr.querySelector(`[data-column="${f.column}"]`);
                return cell
                    ? (cell.textContent || '').toLowerCase().includes(f.value.toLowerCase())
                    : false;
            })
        );
    }

    tb.innerHTML = '';
    let newSelectedIndex = -1;
    rows.forEach((r, i) => {
        r.onclick = () => selectRow(r, i);
        tb.appendChild(r);
        // Si esta fila tiene el ID seleccionado, actualizar el índice
        if (selectedRowId && r.getAttribute('data-id') === selectedRowId) {
            newSelectedIndex = i;
        }
    });

    // Actualizar el índice seleccionado si la fila sigue visible
    if (newSelectedIndex >= 0) {
        selectedRowIndex = newSelectedIndex;
        // Reseleccionar visualmente la fila
        const selectedRow = rows[newSelectedIndex];
        if (selectedRow) {
            selectedRow.classList.add('bg-stone-500', 'text-white');
            selectedRow.classList.remove('hover:bg-stone-50');
            $$('td', selectedRow).forEach(td => {
                td.classList.add('text-white');
                td.classList.remove('text-gray-700');
            });
        }
    } else if (selectedRowId) {
        // Si la fila seleccionada no está en los resultados filtrados, deseleccionar
        selectedRowIndex = -1;
        selectedRowId = null;
        const rpc = $('#rowPriorityControls');
        if (rpc) rpc.classList.add('hidden');
        const btnEditarLayout = document.getElementById('layoutBtnEditar');
        const btnEliminarLayout = document.getElementById('layoutBtnEliminar');
        if (btnEditarLayout) {
            btnEditarLayout.disabled = true;
            btnEditarLayout.classList.add('opacity-50', 'cursor-not-allowed');
        }
        if (btnEliminarLayout) {
            btnEliminarLayout.disabled = true;
            btnEliminarLayout.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }

    updateFilterCount();

    if (filters.length) {
        showToast(
            `Filtros aplicados<br>${filters.length} filtro(s) · ${rows.length} resultado(s)`,
            'success'
        );
    }
}

function updateFilterCount() {
    const badge = $('#filterCount');
    if (!badge) return;
    if (filters.length > 0) {
        badge.textContent = filters.length;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

function resetFilters() {
    const tb = tbodyEl();
    if (!tb) return;

    // Restaurar filas originales
    tb.innerHTML = '';
    allRows.forEach((r, i) => {
        r.classList.remove('bg-stone-500', 'text-white', 'hover:bg-stone-50');
        r.classList.add('hover:bg-stone-50');
        $$('td', r).forEach(td => {
            td.classList.remove('text-white');
            td.classList.add('text-gray-700');
        });
        r.onclick = () => selectRow(r, i);
        tb.appendChild(r);
    });

    // Mostrar columnas ocultas
    hiddenColumns.forEach(idx => {
        showColumn(idx);
        const hideBtn = $(`th.column-${idx} .hide-btn`);
        if (hideBtn) {
            hideBtn.classList.remove('bg-red-600');
            hideBtn.classList.add('bg-red-500');
            hideBtn.title = 'Ocultar columna';
        }
    });
    hiddenColumns = [];

    // Desfijar columnas
    pinnedColumns = [];
    updatePinnedColumnsPositions();

    filters = [];
    updateFilterCount();

    const rpc = $('#rowPriorityControls');
    if (rpc) rpc.classList.add('hidden');

    selectedRowIndex = -1;
    selectedRowId = null;

    // Deshabilitar botón Programar
    const btnProgramar = document.getElementById('btnProgramar');
    if (btnProgramar) {
        btnProgramar.disabled = true;
        btnProgramar.classList.remove('bg-stone-700', 'hover:bg-stone-800', 'cursor-pointer');
        btnProgramar.classList.add('bg-gray-400', 'hover:bg-gray-500', 'cursor-not-allowed');
    }

    const btnEditar = document.getElementById('btn-editar-programa');
    if (btnEditar) btnEditar.disabled = true;
    const btnEliminar = document.getElementById('btn-eliminar-programa');
    if (btnEliminar) btnEliminar.disabled = true;

    showToast('Restablecido<br>Se limpiaron filtros, fijados y columnas ocultas', 'success');
}

// ===================== Columnas: fijar / ocultar =====================
function getColumnsData() {
    // Deriva columnas directamente del DOM, para evitar desincronización con Blade
    return $$('#mainTable thead th').map(th => ({
        label: (th.textContent || '').trim(),
        field: th.dataset.column,
        index: parseInt(th.dataset.index, 10)
    }));
}

function getPinnedColumns() {
    return pinnedColumns || [];
}

function getHiddenColumns() {
    return hiddenColumns || [];
}

function openPinColumnsModal() {
    const columns = getColumnsData();
    const pinned = getPinnedColumns();

    const columnOptions = columns.map(col => {
        const columnIndex = col.index;
        const isPinned = pinned.includes(columnIndex);
        return `
            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                <span class="text-sm font-medium text-gray-700">${col.label}</span>
                <input type="checkbox" ${isPinned ? 'checked' : ''}
                    class="w-4 h-4 text-yellow-600 bg-gray-100 border-gray-300 rounded focus:ring-yellow-500"
                    data-column-index="${columnIndex}">
            </div>
        `;
    }).join('');

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

function openHideColumnsModal() {
    const columns = getColumnsData();
    const hidden = getHiddenColumns();

    const columnOptions = columns.map(col => {
        const columnIndex = col.index;
        const isHidden = hidden.includes(columnIndex);
        return `
            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                <span class="text-sm font-medium text-gray-700">${col.label}</span>
                <input type="checkbox" ${isHidden ? 'checked' : ''}
                    class="w-4 h-4 text-red-600 bg-gray-100 border-gray-300 rounded focus:ring-red-500"
                    data-column-index="${columnIndex}">
            </div>
        `;
    }).join('');

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
                        hideColumn(columnIndex);
                    } else {
                        showColumn(columnIndex);
                        hiddenColumns = hiddenColumns.filter(i => i !== columnIndex);
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

function resetColumnVisibility() {
    // Mostrar todas las columnas
    const allColumns = $$('th[class*="column-"]');
    allColumns.forEach(th => {
        const idx = parseInt(th.dataset.index, 10);
        if (!Number.isNaN(idx)) {
            showColumn(idx);
        }
    });

    pinnedColumns = [];
    hiddenColumns = [];
    updatePinnedColumnsPositions();

    Swal.fire({
        title: 'Columnas Restablecidas',
        text: 'Todas las columnas han sido mostradas y desfijadas',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    });
}

function showColumn(index) {
    $$(`.column-${index}`).forEach(el => {
        el.style.display = '';
    });
}

function hideColumn(index) {
    $$(`.column-${index}`).forEach(el => {
        el.style.display = 'none';
    });

    const hideBtn = $(`th.column-${index} .hide-btn`);
    if (hideBtn) {
        hideBtn.classList.remove('bg-red-500');
        hideBtn.classList.add('bg-red-600');
        hideBtn.title = 'Columna oculta';
    }

    if (!hiddenColumns.includes(index)) {
        hiddenColumns.push(index);
    }

    showToast('Columna oculta', 'info');
}

function togglePinColumn(index) {
    const exists = pinnedColumns.includes(index);
    if (exists) {
        pinnedColumns = pinnedColumns.filter(i => i !== index);
    } else {
        pinnedColumns.push(index);
    }
    pinnedColumns.sort((a, b) => a - b);

    const pinBtn = $(`th.column-${index} .pin-btn`);
    if (pinBtn) {
        pinBtn.classList.toggle('bg-yellow-600', !exists);
        pinBtn.classList.toggle('bg-yellow-500', exists);
        pinBtn.title = exists ? 'Fijar columna' : 'Desfijar columna';
    }

    updatePinnedColumnsPositions();
}

function updatePinnedColumnsPositions() {
    const headerThs = $$('th[class*="column-"]');
    const allIdx = [...new Set(headerThs.map(th => +th.dataset.index))];

    // Limpia estilos
    allIdx.forEach(idx => {
        $$(`.column-${idx}`).forEach(el => {
            if (el.tagName === 'TH') {
                el.style.top = '0';
                el.style.position = 'sticky';
                el.style.zIndex = '30';
                el.classList.add('bg-stone-700', 'text-white');
            } else {
                el.style.position = '';
                el.style.top = '';
                el.style.zIndex = '';
                el.classList.remove('bg-stone-700', 'text-white');
            }
            el.style.left = '';
            el.classList.remove('pinned-column');
        });
    });

    // Aplica fijados
    let left = 0;
    pinnedColumns.forEach((idx, order) => {
        const th = $(`th.column-${idx}`);
        if (!th || th.style.display === 'none') return;

        const width = th.offsetWidth;
        $$(`.column-${idx}`).forEach(el => {
            el.classList.add('pinned-column', 'bg-stone-700', 'text-white');
            el.style.left = `${left}px`;

            if (el.tagName === 'TH') {
                el.style.top = '0';
                el.style.zIndex = String(40 + order);
                el.style.position = 'sticky';
            } else {
                el.style.zIndex = String(35 + order);
                el.style.position = 'sticky';
            }
        });
        left += width;
    });
}

// ===================== Selección de filas / prioridad =====================
function selectRow(rowElement, rowIndex) {
    try {
        // Si ya está seleccionada, deseleccionar
        if (selectedRowIndex === rowIndex && rowElement.classList.contains('bg-stone-500')) {
            deselectRow();
            return;
        }

        // Limpiar selección previa
        $$('.selectable-row').forEach(row => {
            row.classList.remove('bg-stone-500', 'text-white');
            row.classList.add('hover:bg-stone-50');
            $$('td', row).forEach(td => {
                td.classList.remove('text-white');
                td.classList.add('text-gray-700');
            });
        });

        // Seleccionar actual
        rowElement.classList.add('bg-stone-500', 'text-white');
        rowElement.classList.remove('hover:bg-stone-50');
        $$('td', rowElement).forEach(td => {
            td.classList.add('text-white');
            td.classList.remove('text-gray-700');
        });

        selectedRowIndex = rowIndex;
        selectedRowId = rowElement.getAttribute('data-id');

        const rpc = $('#rowPriorityControls');
        if (rpc) rpc.classList.remove('hidden');

        // Cargar detalle líneas
        if (window.loadSimulacionProgramaTejidoLines) {
            const id = rowElement.getAttribute('data-id');
            window.loadSimulacionProgramaTejidoLines({ programa_id: id });
        }

        // Habilitar botón Programar
        const btnProgramar = document.getElementById('btnProgramar');
        if (btnProgramar) {
            btnProgramar.disabled = false;
            btnProgramar.classList.remove('bg-gray-400', 'hover:bg-gray-500', 'cursor-not-allowed');
            btnProgramar.classList.add('bg-stone-700', 'hover:bg-stone-800', 'cursor-pointer');
        }

        const btnEditar = document.getElementById('btn-editar-programa');
        const btnEditarLayout = document.getElementById('layoutBtnEditar');
        if (btnEditar) btnEditar.disabled = false;
        if (btnEditarLayout) {
            btnEditarLayout.disabled = false;
            btnEditarLayout.classList.remove('opacity-50', 'cursor-not-allowed');
        }

        const btnEliminar = document.getElementById('btn-eliminar-programa');
        const btnEliminarLayout = document.getElementById('layoutBtnEliminar');

        const enProcesoCell = rowElement.querySelector('[data-column="EnProceso"]');
        const estaEnProceso = enProcesoCell && enProcesoCell.querySelector('input[type="checkbox"]')?.checked;

        if (btnEliminar) {
            btnEliminar.disabled = !!estaEnProceso;
        }
        if (btnEliminarLayout) {
            btnEliminarLayout.disabled = !!estaEnProceso;
            btnEliminarLayout.classList.toggle('opacity-50', !!estaEnProceso);
            btnEliminarLayout.classList.toggle('cursor-not-allowed', !!estaEnProceso);
        }
    } catch (e) {
        console.error('Error en selectRow:', e);
    }
}

function deselectRow() {
    try {
        $$('.selectable-row').forEach(row => {
            row.classList.remove('bg-stone-500', 'text-white');
            row.classList.add('hover:bg-stone-50');
            $$('td', row).forEach(td => {
                td.classList.remove('text-white');
                td.classList.add('text-gray-700');
            });
        });

        selectedRowIndex = -1;
        selectedRowId = null;

        const rpc = $('#rowPriorityControls');
        if (rpc) rpc.classList.add('hidden');

        // Deshabilitar botón Programar
        const btnProgramar = document.getElementById('btnProgramar');
        if (btnProgramar) {
            btnProgramar.disabled = true;
            btnProgramar.classList.remove('bg-stone-700', 'hover:bg-stone-800', 'cursor-pointer');
            btnProgramar.classList.add('bg-gray-400', 'hover:bg-gray-500', 'cursor-not-allowed');
        }

        const btnEditar = document.getElementById('btn-editar-programa');
        const btnEditarLayout = document.getElementById('layoutBtnEditar');
        if (btnEditar) btnEditar.disabled = true;
        if (btnEditarLayout) {
            btnEditarLayout.disabled = true;
            btnEditarLayout.classList.add('opacity-50', 'cursor-not-allowed');
        }

        const btnEliminar = document.getElementById('btn-eliminar-programa');
        const btnEliminarLayout = document.getElementById('layoutBtnEliminar');
        if (btnEliminar) btnEliminar.disabled = true;
        if (btnEliminarLayout) {
            btnEliminarLayout.disabled = true;
            btnEliminarLayout.classList.add('opacity-50', 'cursor-not-allowed');
        }
    } catch (e) {
        console.error('Error en deselectRow:', e);
    }
}

// ===================== Loader =====================
function showLoading() {
    let loader = document.getElementById('priority-loader');
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'priority-loader';
        loader.className = 'fixed inset-0 bg-black bg-opacity-30 z-[9999] flex items-center justify-center';
        loader.style.display = 'flex';
        loader.style.alignItems = 'center';
        loader.style.justifyContent = 'center';
        loader.innerHTML = `
            <div class="bg-white p-5 rounded-lg shadow-lg flex items-center justify-center">
                <div class="w-10 h-10 border-4 border-gray-200 border-t-stone-700 rounded-full animate-spin"></div>
            </div>
        `;
        document.body.appendChild(loader);
    } else {
        loader.style.display = 'flex';
        loader.style.alignItems = 'center';
        loader.style.justifyContent = 'center';
    }
}

function hideLoading() {
    const loader = document.getElementById('priority-loader');
    if (loader) loader.style.display = 'none';
}

// ===================== Prioridad (subir / bajar) =====================
function moveRowUp() {
    const tb = tbodyEl();
    if (!tb) return;

    if (selectedRowIndex <= 0) {
        showToast('No se puede subir<br>El registro ya es el primero', 'error');
        return;
    }

    const rows = $$('.selectable-row', tb);
    const selectedRow = rows[selectedRowIndex];
    const id = selectedRow?.getAttribute('data-id');

    if (!id) {
        showToast('Error<br>No se pudo obtener el ID del registro', 'error');
        return;
    }

    showLoading();

    fetch(`/simulacion/${id}/prioridad/subir`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                if (data.registro_id) {
                    sessionStorage.setItem('scrollToRegistroId', data.registro_id);
                    sessionStorage.setItem('selectRegistroId', data.registro_id);
                }
                sessionStorage.setItem('priorityChangeMessage', 'Prioridad actualizada correctamente');
                sessionStorage.setItem('priorityChangeType', 'success');
                window.location.href = '/simulacion';
            } else {
                showToast(data.message || 'No se pudo actualizar la prioridad', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showToast('Ocurrió un error al procesar la solicitud', 'error');
        });
}

function moveRowDown() {
    const tb = tbodyEl();
    if (!tb) return;

    const rows = $$('.selectable-row', tb);
    if (selectedRowIndex < 0 || selectedRowIndex >= rows.length - 1) {
        showToast('No se puede bajar<br>El registro ya es el último', 'error');
        return;
    }

    const selectedRow = rows[selectedRowIndex];
    const id = selectedRow?.getAttribute('data-id');

    if (!id) {
        showToast('Error<br>No se pudo obtener el ID del registro', 'error');
        return;
    }

    showLoading();

    fetch(`/simulacion/${id}/prioridad/bajar`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                if (data.registro_id) {
                    sessionStorage.setItem('scrollToRegistroId', data.registro_id);
                    sessionStorage.setItem('selectRegistroId', data.registro_id);
                }
                sessionStorage.setItem('priorityChangeMessage', 'Prioridad actualizada correctamente');
                sessionStorage.setItem('priorityChangeType', 'success');
                window.location.href = '/simulacion';
            } else {
                showToast(data.message || 'No se pudo actualizar la prioridad', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showToast('Ocurrió un error al procesar la solicitud', 'error');
        });
}

// ===================== Nuevo / Eliminar =====================
function abrirNuevo() {
    window.location.href = '/simulacion/nuevo';
}

function eliminarRegistro(id) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '¿Eliminar registro?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
        }).then(result => {
            if (!result.isConfirmed) return;

            showLoading();

            fetch(`/simulacion/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        sessionStorage.setItem('priorityChangeMessage', 'Registro eliminado correctamente');
                        sessionStorage.setItem('priorityChangeType', 'success');
                        window.location.href = '/simulacion';
                    } else {
                        showToast(data.message || 'No se pudo eliminar el registro', 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    showToast('Ocurrió un error al procesar la solicitud', 'error');
                });
        });
    } else {
        if (!confirm('¿Eliminar registro? Esta acción no se puede deshacer.')) {
            return;
        }
        showLoading();
        fetch(`/simulacion/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    sessionStorage.setItem('priorityChangeMessage', 'Registro eliminado correctamente');
                    sessionStorage.setItem('priorityChangeType', 'success');
                    window.location.href = '/simulacion';
                } else {
                    showToast(data.message || 'No se pudo eliminar el registro', 'error');
                }
            });
    }
}

// ===================== Init =====================
document.addEventListener('DOMContentLoaded', () => {
    const tb = tbodyEl();
    if (tb) {
        allRows = $$('.selectable-row', tb);
        allRows.forEach((row, i) => row.addEventListener('click', () => selectRow(row, i)));
    }

    updateFilterCount();
    window.addEventListener('resize', updatePinnedColumnsPositions);

    // Conectar botón de filtros del navbar
    const btnFilters = document.getElementById('btnFilters');
    if (btnFilters) {
        btnFilters.addEventListener('click', openFilterModal);
    }

    const btnEditarLayout = document.getElementById('layoutBtnEditar');
    const btnEliminarLayout = document.getElementById('layoutBtnEliminar');

    if (btnEditarLayout) {
        btnEditarLayout.disabled = true;
        btnEditarLayout.classList.add('opacity-50', 'cursor-not-allowed');
        btnEditarLayout.addEventListener('click', () => {
            if (!selectedRowId) return;
            // Buscar la fila por data-id en lugar de usar el índice
            const selected = document.querySelector(`.selectable-row[data-id="${selectedRowId}"]`);
            if (!selected) {
                // Si no se encuentra, intentar con el índice como fallback
                const rows = $$('.selectable-row');
                if (selectedRowIndex >= 0 && selectedRowIndex < rows.length) {
                    const id = rows[selectedRowIndex]?.getAttribute('data-id');
                    if (id) {
                        window.location.href = `/simulacion/${encodeURIComponent(id)}/editar`;
                    }
                }
                return;
            }
            const id = selected.getAttribute('data-id');
            if (id) {
                window.location.href = `/simulacion/${encodeURIComponent(id)}/editar`;
            }
        });
    }

    if (btnEliminarLayout) {
        btnEliminarLayout.disabled = true;
        btnEliminarLayout.classList.add('opacity-50', 'cursor-not-allowed');
        btnEliminarLayout.addEventListener('click', () => {
            if (!selectedRowId) return;
            // Buscar la fila por data-id en lugar de usar el índice
            const selected = document.querySelector(`.selectable-row[data-id="${selectedRowId}"]`);
            if (!selected) {
                // Si no se encuentra, intentar con el índice como fallback
                const rows = $$('.selectable-row');
                if (selectedRowIndex >= 0 && selectedRowIndex < rows.length) {
                    const id = rows[selectedRowIndex]?.getAttribute('data-id');
                    if (id) {
                        eliminarRegistro(id);
                    }
                }
                return;
            }
            const id = selected.getAttribute('data-id');
            if (id) {
                eliminarRegistro(id);
            }
        });
    }

    const btnEditar = document.getElementById('btn-editar-programa');
    if (btnEditar) {
        btnEditar.addEventListener('click', () => {
            if (!selectedRowId) return;
            // Buscar la fila por data-id en lugar de usar el índice
            const selected = document.querySelector(`.selectable-row[data-id="${selectedRowId}"]`);
            if (!selected) {
                // Si no se encuentra, intentar con el índice como fallback
                const rows = $$('.selectable-row');
                if (selectedRowIndex >= 0 && selectedRowIndex < rows.length) {
                    const id = rows[selectedRowIndex]?.getAttribute('data-id');
                    if (id) {
                        window.location.href = `/simulacion/${encodeURIComponent(id)}/editar`;
                    }
                }
                return;
            }
            const id = selected.getAttribute('data-id');
            if (id) {
                window.location.href = `/simulacion/${encodeURIComponent(id)}/editar`;
            }
        });
    }

    const btnEliminar = document.getElementById('btn-eliminar-programa');
    if (btnEliminar) {
        btnEliminar.addEventListener('click', () => {
            if (!selectedRowId) return;
            // Buscar la fila por data-id en lugar de usar el índice
            const selected = document.querySelector(`.selectable-row[data-id="${selectedRowId}"]`);
            if (!selected) {
                // Si no se encuentra, intentar con el índice como fallback
                const rows = $$('.selectable-row');
                if (selectedRowIndex >= 0 && selectedRowIndex < rows.length) {
                    const id = rows[selectedRowIndex]?.getAttribute('data-id');
                    if (id) {
                        eliminarRegistro(id);
                    }
                }
                return;
            }
            const id = selected.getAttribute('data-id');
            if (id) {
                eliminarRegistro(id);
            }
        });
    }

    // Restaurar selección después de recargar
    const registroIdToSelect = sessionStorage.getItem('selectRegistroId');
    const registroIdToScroll = sessionStorage.getItem('scrollToRegistroId');
    const priorityChangeMessage = sessionStorage.getItem('priorityChangeMessage');
    const priorityChangeType = sessionStorage.getItem('priorityChangeType');

    if ((registroIdToSelect || registroIdToScroll) && tb) {
        setTimeout(() => {
            const rows = $$('.selectable-row', tb);
            let targetRow = null;
            let targetIndex = -1;

            rows.forEach((row, index) => {
                const rowId = row.getAttribute('data-id');
                if (rowId && (rowId === registroIdToSelect || rowId === registroIdToScroll)) {
                    targetRow = row;
                    targetIndex = index;
                }
            });

            if (targetRow && targetIndex >= 0) {
                selectRow(targetRow, targetIndex);
                // Asegurar que selectedRowId también se actualice
                selectedRowId = targetRow.getAttribute('data-id');
                targetRow.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                    inline: 'nearest'
                });
                sessionStorage.removeItem('selectRegistroId');
                sessionStorage.removeItem('scrollToRegistroId');
            }
        }, 300);
    }

    if (priorityChangeMessage) {
        setTimeout(() => {
            showToast(priorityChangeMessage, priorityChangeType || 'success');
            sessionStorage.removeItem('priorityChangeMessage');
            sessionStorage.removeItem('priorityChangeType');
        }, 500);
    }
});

// === Filtros compactos desde layout: values = { columna: valor, ... } ===
window.applyTableFilters = function (values) {
    try {
        const tb = tbodyEl();
        if (!tb) return;

        const rows = allRows.slice();
        const entries = Object.entries(values || {});
        let filtered = rows;

        if (entries.length) {
            filtered = rows.filter(tr =>
                entries.every(([col, val]) => {
                    const cell = tr.querySelector(`[data-column="${CSS.escape(col)}"]`);
                    if (!cell) return false;
                    return (cell.textContent || '')
                        .toLowerCase()
                        .includes(String(val).toLowerCase());
                })
            );
        }

        tb.innerHTML = '';
        filtered.forEach((r, i) => {
            r.onclick = () => selectRow(r, i);
            tb.appendChild(r);
        });
    } catch (e) {
        console.error('applyTableFilters error:', e);
    }
};

// ===================== Llenar Simulación =====================
document.getElementById('btnLlenarSimulacion')?.addEventListener('click', function () {
    Swal.fire({
        title: '¿Duplicar datos de Programa de Tejido?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, duplicar datos',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#44403c',
        cancelButtonColor: '#6b7280',
        width: '500px',
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar loader
            showLoading();

            fetch('{{ route("simulacion.duplicar-datos") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        Swal.fire({
                            title: '¡Éxito!',
                            html: `
                                <p class="text-sm text-gray-700 mb-2">${data.message}</p>
                                <p class="text-xs text-gray-500">
                                    Se crearon <strong>${data.data.registros}</strong> registro(s) y <strong>${data.data.lineas}</strong> línea(s).
                                </p>
                            `,
                            icon: 'success',
                            confirmButtonColor: '#44403c',
                            confirmButtonText: 'Recargar página'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'No se pudieron duplicar los datos',
                            icon: 'error',
                            confirmButtonColor: '#dc2626'
                        });
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error',
                        text: 'Ocurrió un error al duplicar los datos. Por favor intenta nuevamente.',
                        icon: 'error',
                        confirmButtonColor: '#dc2626'
                    });
                });
        }
    });
});

// ===================== Alta de Pronósticos (modal rango de meses) =====================
document.getElementById('btnAltaPronosticos')?.addEventListener('click', function () {
    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1;

    const meses = [];
    for (let i = -12; i <= 3; i++) {
        const date = new Date(currentYear, currentMonth + i - 1, 1);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const label = date.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });
        meses.push({
            value: `${year}-${month}`,
            label: label.charAt(0).toUpperCase() + label.slice(1)
        });
    }

    const mesesHTML = meses.map(m => `<option value="${m.value}">${m.label}</option>`).join('');

    const html = `
        <div class="text-left">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Mes Inicial:</label>
                <select id="mesInicial" class="w-full border rounded px-3 py-2 text-sm">
                    ${mesesHTML}
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Mes Final:</label>
                <select id="mesFinal" class="w-full border rounded px-3 py-2 text-sm">
                    ${mesesHTML}
                </select>
            </div>
            <p class="text-xs text-gray-500 mt-3">
                <i class="fa-solid fa-info-circle mr-1"></i>
                Se mostrarán los pronósticos del rango seleccionado (inclusive).
            </p>
        </div>
    `;

    Swal.fire({
        title: 'Seleccionar Rango de Meses',
        html: html,
        width: 500,
        showCancelButton: true,
        confirmButtonText: 'Continuar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#6b7280',
        didOpen: () => {
            const mesActual = `${currentYear}-${String(currentMonth).padStart(2, '0')}`;
            document.getElementById('mesInicial').value = mesActual;
            document.getElementById('mesFinal').value = mesActual;
        },
        preConfirm: () => {
            const mesInicial = document.getElementById('mesInicial').value;
            const mesFinal = document.getElementById('mesFinal').value;

            if (!mesInicial || !mesFinal) {
                Swal.showValidationMessage('Por favor seleccione ambos meses');
                return false;
            }

            if (mesInicial > mesFinal) {
                Swal.showValidationMessage('El mes inicial debe ser menor o igual al mes final');
                return false;
            }

            const mesesSeleccionados = [];
            let [yearIni, monthIni] = mesInicial.split('-').map(Number);
            const [yearFin, monthFin] = mesFinal.split('-').map(Number);

            while (yearIni < yearFin || (yearIni === yearFin && monthIni <= monthFin)) {
                mesesSeleccionados.push(`${yearIni}-${String(monthIni).padStart(2, '0')}`);
                monthIni++;
                if (monthIni > 12) {
                    monthIni = 1;
                    yearIni++;
                }
            }

            return mesesSeleccionados;
        }
    }).then(result => {
        if (result.isConfirmed && result.value) {
            const mesesSeleccionados = result.value;
            const url = new URL('{{ route("simulacion.alta-pronosticos") }}', window.location.origin);
            mesesSeleccionados.forEach(mes => url.searchParams.append('meses[]', mes));
            window.location.href = url.toString();
        }
    });
});
</script>

@include('components.ui.toast-notification')
@endsection
