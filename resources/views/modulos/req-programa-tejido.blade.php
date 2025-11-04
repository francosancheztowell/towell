@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Programa de Tejido')

@section('navbar-right')
<a href="{{ route('programa-tejido.altas-especiales') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 mr-2" title="Altas Especiales">
    Altas Especiales
</a>
<a href="{{ route('programa-tejido.alta-pronosticos') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 mr-2" title="Alta de Pronósticos">
    <i class="fa-solid fa-chart-line mr-2"></i>
    Alta de Pronósticos
</a>
<button id="btn-editar-programa" type="button" class="inline-flex items-center justify-center w-9 h-9 text-base rounded-full text-white bg-yellow-500 hover:bg-yellow-600 disabled:opacity-50 disabled:cursor-not-allowed" title="Editar" aria-label="Editar" disabled>
    <i class="fa-solid fa-pen-to-square"></i>
</button>
<button id="btn-eliminar-programa" type="button" class="inline-flex items-center justify-center w-9 h-9 text-base rounded-full text-white bg-red-500 hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed ml-2" title="Eliminar" aria-label="Eliminar" disabled>
    <i class="fa-solid fa-trash"></i>
</button>
@endsection

@section('menu-planeacion')
@endsection

@section('content')
<div class="w-full px-0 py-0 ">
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

		$formatValue = function($registro, $field) {
			$value = $registro->{$field} ?? null;
			if ($value === null || $value === '') return '';

			// Checkbox para EnProceso
			if ($field === 'EnProceso') {
				$checked = ($value == 1 || $value === true) ? 'checked' : '';
				return '<input type="checkbox" ' . $checked . ' disabled class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">';
			}

			// Mapear 'UL' como 1 para la columna 'Ultimo'
			if ($field === 'Ultimo') {
				$sv = strtoupper(trim((string)$value));
				if ($sv === 'UL') return '1';
			}

			if ($field === 'EficienciaSTD' && is_numeric($value)) {
				// Convertir a porcentaje: 0.8 = 80%, 0.78 = 78%
				$porcentaje = (float)$value * 100;
				// Redondear a entero para mostrar sin decimales
				return round($porcentaje) . '%';
			}

			// Campos fecha conocidos (usa claves, no labels)
			$fechaCampos = [
				'Programado','ProgramarProd','FechaInicio','FechaFinal',
				'EntregaProduc','EntregaPT','EntregaCte'
			];

			// Campos que son solo fecha (sin hora)
			$fechaSoloCampos = ['EntregaProduc','EntregaPT'];

			if (in_array($field, $fechaCampos, true)) {
				try {
					if ($value instanceof \Carbon\Carbon) {
						if ($value->year > 1970) {
							// Si es campo de solo fecha, mostrar sin hora
							if (in_array($field, $fechaSoloCampos, true)) {
								return $value->format('d/m/Y');
							}
							// Si es campo datetime, mostrar con hora
							return $value->format('d/m/Y H:i');
						}
						return '';
					}
					$dt = \Carbon\Carbon::parse($value);
					if ($dt->year > 1970) {
						// Si es campo de solo fecha, mostrar sin hora
						if (in_array($field, $fechaSoloCampos, true)) {
							return $dt->format('d/m/Y');
						}
						// Si es campo datetime, mostrar con hora
						return $dt->format('d/m/Y H:i');
					}
					return '';
				} catch (\Exception $e) {
					return '';
				}
			}

			// Números con decimales
			if (is_numeric($value) && !preg_match('/^\d+$/', (string)$value)) {
				return number_format((float)$value, 2);
			}

			return $value;
		};
		@endphp

		@if($registros->count() > 0)
			<div class="overflow-x-auto">
					<div class="overflow-y-auto" style="max-height: 320px;">
						<table id="mainTable" class="min-w-full divide-y divide-gray-200">
							<thead class="bg-blue-500 text-white">
								<tr>
									@foreach($columns as $index => $col)
									<th class="px-2 py-1 text-left text-xs font-semibold text-white whitespace-nowrap column-{{ $index }}"
										style="position: sticky; top: 0; z-index: 30; background-color: #3b82f6; min-width: 80px;"
										data-column="{{ $col['field'] }}" data-index="{{ $index }}">
										{{ $col['label'] }}
									</th>
							@endforeach
						</tr>
					</thead>
					<tbody class="bg-white divide-y divide-gray-100">
								@foreach($registros as $index => $registro)
                                <tr class="hover:bg-blue-50 cursor-pointer selectable-row" data-row-index="{{ $index }}" data-id="{{ $registro->Id ?? $registro->id ?? '' }}">
										@foreach($columns as $colIndex => $col)
										<td class="px-3 py-2 text-sm text-gray-700 {{ in_array($col['field'], ['Programado','ProgramarProd','FechaInicio','FechaFinal','EntregaProduc','EntregaPT','EntregaCte']) ? 'whitespace-normal' : 'whitespace-nowrap' }} column-{{ $colIndex }}"
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
				<p class="mt-1 text-sm text-gray-500">No se han importado registros aún. Carga un archivo Excel para comenzar.</p>
				<div class="mt-6">
					<a href="{{ route('configuracion.utileria.cargar.catalogos') }}"
					   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
						<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
						</svg>
						Cargar Archivo Excel
					</a>
				</div>
			</div>
		@endif
	</div>
</div>

{{-- Tabla detalle ReqProgramaTejidoLine (fuera del contenedor blanco para ver el fondo) --}}
@include('components.req-programa-tejido-line-table')

<style>
	/* Igual que tu diseño, con apoyo para “pinned” */
	.pinned-column { position: sticky !important; background-color: #3b82f6 !important; color: #fff !important; }
</style>

<script>
// ===== Estado =====
let filters = [];
let hiddenColumns = [];
let pinnedColumns = [];
let allRows = [];
let selectedRowIndex = -1;

// ===== Helpers DOM =====
const $ = (sel, ctx=document) => ctx.querySelector(sel);
const $$ = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));
const tbodyEl = () => $('#mainTable tbody');

// ===== Filtros =====
function renderFilterModalContent() {
	const options = @json(array_map(fn($c)=>['field'=>$c['field'],'label'=>$c['label']], $columns));
	const filtrosHTML = filters.length
		? `<div class="mb-4 p-3 bg-gray-50 rounded-lg">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Filtros Activos:</h4>
                <div class="space-y-1">
					${filters.map((f,i)=>`
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
                    <select id="filtro-columna" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Selecciona una columna...</option>
					${options.map(col=>`<option value="${col.field}">${col.label}</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Valor a buscar</label>
                    <input type="text" id="filtro-valor" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ingresa el valor a buscar">
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
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        width: '450px',
        didOpen: () => {
			$('#btn-agregar-otro').addEventListener('click', () => {
				const col = $('#filtro-columna').value;
				const val = $('#filtro-valor').value;
				if (!col || !val) return Swal.showValidationMessage('Selecciona columna y valor');
				if (filters.some(f => f.column===col && f.value===val)) return Swal.showValidationMessage('Este filtro ya está activo');

				filters.push({ column: col, value: val });
                applyFilters();
                showToast('Filtro agregado correctamente', 'success');
				Swal.update({ html: renderFilterModalContent() });
				openFilterModal(); // re-abre para re-inyectar listeners
			});
		},
		preConfirm: () => {
			const col = $('#filtro-columna').value;
			const val = $('#filtro-valor').value;
			if (!col || !val) { Swal.showValidationMessage('Selecciona columna y valor'); return false; }
			if (filters.some(f => f.column===col && f.value===val)) { Swal.showValidationMessage('Este filtro ya está activo'); return false; }
			return { column: col, value: val };
		}
	}).then(res => {
		if (res.isConfirmed) {
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
	let rows = allRows.slice();
	if (filters.length) {
		rows = rows.filter(tr => {
			return filters.every(f => {
				const cell = tr.querySelector(`[data-column="${f.column}"]`);
				return cell ? cell.textContent.toLowerCase().includes(f.value.toLowerCase()) : false;
			});
		});
	}
	const tb = tbodyEl();
	tb.innerHTML = '';
	rows.forEach((r,i) => {
		r.onclick = () => selectRow(r, i);
		tb.appendChild(r);
	});
	updateFilterCount();
	if (filters.length) showToast(`Filtros aplicados<br>${filters.length} filtro(s) · ${rows.length} resultado(s)`, 'success');
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
	// Filas originales
	const tb = tbodyEl();
	tb.innerHTML = '';
	allRows.forEach((r,i) => {
		r.classList.remove('bg-blue-500','text-white','hover:bg-blue-50');
		r.classList.add('hover:bg-blue-50');
		$$('td', r).forEach(td => td.classList.remove('text-white','text-gray-700'));
		r.onclick = () => selectRow(r, i);
		tb.appendChild(r);
	});

	// Mostrar columnas ocultas
	hiddenColumns.forEach(idx => {
		$$(`.column-${idx}`).forEach(el => el.style.display = '');
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
	updatePinnedColumnsPositions(); // limpiará estilos

	// UI filtros
	filters = [];
    updateFilterCount();

	// Ocultar controles de prioridad
	const rpc = $('#rowPriorityControls');
	if (rpc) rpc.classList.add('hidden');

	selectedRowIndex = -1;

	// Deshabilitar botones
	const btnEditar = document.getElementById('btn-editar-programa');
	if (btnEditar) btnEditar.disabled = true;
	const btnEliminar = document.getElementById('btn-eliminar-programa');
	if (btnEliminar) btnEliminar.disabled = true;

	showToast('Restablecido<br>Se limpiaron filtros, fijados y columnas ocultas', 'success');
}

// ===== Controles de columnas desde navbar =====
function openPinColumnsModal() {
	const columns = getColumnsData();
	const pinnedColumns = getPinnedColumns();

	const columnOptions = columns.map((col, index) => {
		const isPinned = pinnedColumns.includes(index);
		return `
			<div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
				<span class="text-sm font-medium text-gray-700">${col.label}</span>
				<input type="checkbox" ${isPinned ? 'checked' : ''}
					   class="w-4 h-4 text-yellow-600 bg-gray-100 border-gray-300 rounded focus:ring-yellow-500"
					   data-column-index="${index}">
			</div>
		`;
	}).join('');

	Swal.fire({
		title: 'Fijar Columnas',
		html: `
			<div class="text-left">
				<p class="text-sm text-gray-600 mb-4">Selecciona las columnas que deseas fijar a la izquierda de la tabla:</p>
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
			// Agregar event listeners a los checkboxes
			const checkboxes = document.querySelectorAll('#swal2-html-container input[type="checkbox"]');
			checkboxes.forEach(checkbox => {
				checkbox.addEventListener('change', function() {
					const columnIndex = parseInt(this.dataset.columnIndex);
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
	const hiddenColumns = getHiddenColumns();

	const columnOptions = columns.map((col, index) => {
		const isHidden = hiddenColumns.includes(index);
		return `
			<div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
				<span class="text-sm font-medium text-gray-700">${col.label}</span>
				<input type="checkbox" ${isHidden ? 'checked' : ''}
					   class="w-4 h-4 text-red-600 bg-gray-100 border-gray-300 rounded focus:ring-red-500"
					   data-column-index="${index}">
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
			// Agregar event listeners a los checkboxes
			const checkboxes = document.querySelectorAll('#swal2-html-container input[type="checkbox"]');
			checkboxes.forEach(checkbox => {
				checkbox.addEventListener('change', function() {
					const columnIndex = parseInt(this.dataset.columnIndex);
					if (this.checked) {
						hideColumn(columnIndex);
					} else {
						showColumn(columnIndex);
					}
				});
			});
		}
	});
}

function getColumnsData() {
	return [
		{label: 'Estado', field: 'EnProceso'},
		{label: 'Cuenta', field: 'CuentaRizo'},
		{label: 'Calibre Rizo', field: 'CalibreRizo'},
		{label: 'Salón', field: 'SalonTejidoId'},
		{label: 'Telar', field: 'NoTelarId'},
		{label: 'Último', field: 'Ultimo'},
		{label: 'Cambios Hilo', field: 'CambioHilo'},
		{label: 'Maq', field: 'Maquina'},
		{label: 'Ancho', field: 'Ancho'},
		{label: 'Ef Std', field: 'EficienciaSTD'},
		{label: 'Vel', field: 'VelocidadSTD'},
		{label: 'Hilo', field: 'FibraRizo'},
		{label: 'Calibre Pie', field: 'CalibrePie'},
		{label: 'Jornada', field: 'CalendarioId'},
		{label: 'Clave Mod.', field: 'TamanoClave'},
		{label: 'Usar cuando no existe en base', field: 'NoExisteBase'},
		{label: 'Clave AX', field: 'ItemId'},
		{label: 'Tamaño AX', field: 'InventSizeId'},
		{label: 'Rasurado', field: 'Rasurado'},
		{label: 'Producto', field: 'NombreProducto'},
		{label: 'Pedido', field: 'TotalPedido'},
		{label: 'Producción', field: 'Produccion'},
		{label: 'Saldos', field: 'SaldoPedido'},
		{label: 'Saldo Marbetes', field: 'SaldoMarbete'},
		{label: 'Día Scheduling', field: 'ProgramarProd'},
		{label: 'Orden Prod.', field: 'NoProduccion'},
		{label: 'INN', field: 'Programado'},
		{label: 'Id Flog', field: 'FlogsId'},
		{label: 'Nombre Proyecto', field: 'NombreProyecto'},
		{label: 'Clave', field: 'Clave'},
		{label: 'Pedido', field: 'Pedido'},
		{label: 'Peine', field: 'Peine'},
		{label: 'Ancho Toalla', field: 'AnchoToalla'},
		{label: 'Largo Toalla', field: 'LargoToalla'},
		{label: 'Peso Crudo', field: 'PesoCrudo'},
		{label: 'Luchaje', field: 'Luchaje'},
		{label: 'No Tiras', field: 'NoTiras'},
		{label: 'Repeticiones', field: 'Repeticiones'},
		{label: 'Total Marbetes', field: 'TotalMarbetes'},
		{label: 'Cambio Repaso', field: 'CambioRepaso'},
		{label: 'Vendedor', field: 'Vendedor'},
		{label: 'Cat Calidad', field: 'CatCalidad'},
		{label: 'Obs', field: 'Obs'},
		{label: 'Ancho Peine Trama', field: 'AnchoPeineTrama'},
		{label: 'Log Lucha Total', field: 'LogLuchaTotal'},
		{label: 'Cal Trama Fondo C1', field: 'CalTramaFondoC1'},
		{label: 'Cal Trama Fondo C12', field: 'CalTramaFondoC12'},
		{label: 'Fibra Trama Fondo C1', field: 'FibraTramaFondoC1'},
		{label: 'Pasadas Trama Fondo C1', field: 'PasadasTramaFondoC1'},
		{label: 'Calibre Comb1', field: 'CalibreComb1'},
		{label: 'Calibre Comb12', field: 'CalibreComb12'},
		{label: 'Fibra Comb1', field: 'FibraComb1'},
		{label: 'Cod Color C1', field: 'CodColorC1'},
		{label: 'Nom Color C1', field: 'NomColorC1'},
		{label: 'Pasadas Comb1', field: 'PasadasComb1'},
		{label: 'Calibre Comb2', field: 'CalibreComb2'},
		{label: 'Calibre Comb22', field: 'CalibreComb22'},
		{label: 'Fibra Comb2', field: 'FibraComb2'},
		{label: 'Cod Color C2', field: 'CodColorC2'},
		{label: 'Nom Color C2', field: 'NomColorC2'},
		{label: 'Pasadas Comb2', field: 'PasadasComb2'},
		{label: 'Calibre Comb3', field: 'CalibreComb3'},
		{label: 'Calibre Comb32', field: 'CalibreComb32'},
		{label: 'Fibra Comb3', field: 'FibraComb3'},
		{label: 'Cod Color C3', field: 'CodColorC3'},
		{label: 'Nom Color C3', field: 'NomColorC3'},
		{label: 'Pasadas Comb3', field: 'PasadasComb3'},
		{label: 'Calibre Comb4', field: 'CalibreComb4'},
		{label: 'Calibre Comb42', field: 'CalibreComb42'},
		{label: 'Fibra Comb4', field: 'FibraComb4'},
		{label: 'Cod Color C4', field: 'CodColorC4'},
		{label: 'Nom Color C4', field: 'NomColorC4'},
		{label: 'Pasadas Comb4', field: 'PasadasComb4'},
		{label: 'Calibre Comb5', field: 'CalibreComb5'},
		{label: 'Calibre Comb52', field: 'CalibreComb52'},
		{label: 'Fibra Comb5', field: 'FibraComb5'},
		{label: 'Cod Color C5', field: 'CodColorC5'},
		{label: 'Nom Color C5', field: 'NomColorC5'},
		{label: 'Pasadas Comb5', field: 'PasadasComb5'},
		{label: 'Total', field: 'Total'},
		{label: 'Pasadas Dibujo', field: 'PasadasDibujo'},
		{label: 'Contracción', field: 'Contraccion'},
		{label: 'Tramas CM Tejido', field: 'TramasCMTejido'},
		{label: 'Contrac Rizo', field: 'ContracRizo'},
		{label: 'Clasificación KG', field: 'ClasificacionKG'},
		{label: 'KG Dia', field: 'KGDia'},
		{label: 'Densidad', field: 'Densidad'},
		{label: 'Pzas Dia Pasadas', field: 'PzasDiaPasadas'},
		{label: 'Pzas Dia Formula', field: 'PzasDiaFormula'},
		{label: 'DIF', field: 'DIF'},
		{label: 'EFIC', field: 'EFIC'},
		{label: 'Rev', field: 'Rev'},
		{label: 'TIRAS', field: 'TIRAS'},
		{label: 'PASADAS', field: 'PASADAS'},
		{label: 'Colum CT', field: 'ColumCT'},
		{label: 'Colum CU', field: 'ColumCU'},
		{label: 'Colum CV', field: 'ColumCV'},
		{label: 'Comprobar Mod Dup', field: 'ComprobarModDup'},
		{label: 'Fecha Inicio', field: 'FechaInicio'},
		{label: 'Calc4', field: 'Calc4'},
		{label: 'Calc5', field: 'Calc5'},
		{label: 'Calc6', field: 'Calc6'},
		{label: 'Fecha Final', field: 'FechaFinal'},
		{label: 'Fecha Compromiso Prod.', field: 'EntregaProduc'},
		{label: 'Fecha Compromiso PT', field: 'EntregaPT'},
		{label: 'Entrega', field: 'EntregaCte'},
		{label: 'Dif vs Compromiso', field: 'PTvsCte'}
	];
}

function getPinnedColumns() {
	return pinnedColumns || [];
}

function getHiddenColumns() {
	return hiddenColumns || [];
}

function pinColumn(index) {
	if (!pinnedColumns.includes(index)) {
		pinnedColumns.push(index);
		pinnedColumns.sort((a,b) => a-b);
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
	allColumns.forEach((th, index) => {
		showColumn(index);
	});
	// Desfijar todas las columnas
	pinnedColumns = [];
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
	$$(`.column-${index}`).forEach(el => el.style.display = '');
}

// ===== Columnas: ocultar / fijar =====
function hideColumn(index) {
	$$(`.column-${index}`).forEach(el => el.style.display = 'none');
	const hideBtn = $(`th.column-${index} .hide-btn`);
    if (hideBtn) {
        hideBtn.classList.remove('bg-red-500');
        hideBtn.classList.add('bg-red-600');
        hideBtn.title = 'Columna oculta';
    }
	if (!hiddenColumns.includes(index)) hiddenColumns.push(index);
	showToast(`Columna oculta`, 'info');
}

function togglePinColumn(index) {
	const exists = pinnedColumns.includes(index);
	if (exists) pinnedColumns = pinnedColumns.filter(i => i !== index);
	else pinnedColumns.push(index);
	pinnedColumns.sort((a,b)=>a-b);

	// Botón estado
	const pinBtn = $(`th.column-${index} .pin-btn`);
	if (pinBtn) {
		pinBtn.classList.toggle('bg-yellow-600', !exists);
		pinBtn.classList.toggle('bg-yellow-500', exists);
		pinBtn.title = exists ? 'Fijar columna' : 'Desfijar columna';
	}

	updatePinnedColumnsPositions();
}

function updatePinnedColumnsPositions() {
	// Limpia estilos de todas primero
	const allIdx = [...new Set($$('th[class*="column-"]').map(th => +th.dataset.index))];
	allIdx.forEach(idx => {
		$$(`.column-${idx}`).forEach(el => {
			// Mantén sticky top en TH, pero quita left/zIndex/background si no está fijada
			if (el.tagName === 'TH') {
				el.style.top = '0';
                el.style.position = 'sticky';
                el.style.zIndex = '30';
                el.style.backgroundColor = '#3b82f6';
				el.style.color = '#fff';
            } else {
                el.style.position = '';
                el.style.top = '';
                el.style.zIndex = '';
                el.style.backgroundColor = '';
                el.style.color = '';
            }
			el.style.left = '';
			el.classList.remove('pinned-column');
		});
	});

	// Aplica fijados en orden
	let left = 0;
	pinnedColumns.forEach((idx, order) => {
		const th = $(`th.column-${idx}`);
		if (!th || th.style.display === 'none') return;

		const width = th.offsetWidth;
		$$(`.column-${idx}`).forEach(el => {
            el.classList.add('pinned-column');
			el.style.left = left + 'px';
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

// ===== Selección de filas / prioridad =====
function selectRow(rowElement, rowIndex) {
	// Toggle si ya estaba seleccionada
    if (selectedRowIndex === rowIndex && rowElement.classList.contains('bg-blue-500')) {
		return deselectRow();
	}

	// Limpiar selección previa
	$$('.selectable-row').forEach(row => {
		row.classList.remove('bg-blue-500','text-white');
        row.classList.add('hover:bg-blue-50');
		$$('td', row).forEach(td => {
			td.classList.remove('text-white');
			td.classList.add('text-gray-700');
        });
    });

	// Seleccionar actual
	rowElement.classList.add('bg-blue-500','text-white');
    rowElement.classList.remove('hover:bg-blue-50');
	$$('td', rowElement).forEach(td => {
		td.classList.add('text-white');
		td.classList.remove('text-gray-700');
	});

    selectedRowIndex = rowIndex;

	// Mostrar controles
	const rpc = $('#rowPriorityControls');
	if (rpc) rpc.classList.remove('hidden');

    // Cargar detalle de líneas filtradas por ProgramaId
    if (window.loadReqProgramaTejidoLines) {
        const id = rowElement.getAttribute('data-id');
        window.loadReqProgramaTejidoLines({ programa_id: id });
    }

    // Habilitar botones editar y eliminar
    const btnEditar = document.getElementById('btn-editar-programa');
    if (btnEditar) btnEditar.disabled = false;

    const btnEliminar = document.getElementById('btn-eliminar-programa');
    if (btnEliminar) {
        // Verificar si el registro está en proceso
        const enProceso = rowElement.querySelector('[data-column="EnProceso"]');
        const estaEnProceso = enProceso && enProceso.querySelector('input[type="checkbox"]')?.checked;
        btnEliminar.disabled = estaEnProceso;
    }
}

function deselectRow() {
	$$('.selectable-row').forEach(row => {
		row.classList.remove('bg-blue-500','text-white');
        row.classList.add('hover:bg-blue-50');
		$$('td', row).forEach(td => {
			td.classList.remove('text-white');
			td.classList.add('text-gray-700');
        });
    });
    selectedRowIndex = -1;
	const rpc = $('#rowPriorityControls');
	if (rpc) rpc.classList.add('hidden');
}

// Función para mostrar/ocultar loading rápido
function showLoading() {
	let loader = document.getElementById('priority-loader');
	if (!loader) {
		loader = document.createElement('div');
		loader.id = 'priority-loader';
		loader.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.3);z-index:9999;display:flex;align-items:center;justify-content:center;';
		loader.innerHTML = '<div style="background:white;padding:20px;border-radius:8px;box-shadow:0 4px 6px rgba(0,0,0,0.1);"><div style="width:40px;height:40px;border:4px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:spin 0.6s linear infinite;"></div><style>@keyframes spin{to{transform:rotate(360deg);}}</style></div>';
		document.body.appendChild(loader);
	} else {
		loader.style.display = 'flex';
	}
}

function hideLoading() {
	const loader = document.getElementById('priority-loader');
	if (loader) loader.style.display = 'none';
}

function moveRowUp() {
	const tb = tbodyEl();
	if (selectedRowIndex <= 0) {
		showToast('No se puede subir<br>El registro ya es el primero', 'error');
		return;
	}
	const rows = $$('.selectable-row', tb);
	const selectedRow = rows[selectedRowIndex];
	const id = selectedRow.getAttribute('data-id');

	if (!id) {
		showToast('Error<br>No se pudo obtener el ID del registro', 'error');
		return;
	}

	// Mostrar loading rápido
	showLoading();

	// Ejecutar directamente sin confirmación
	fetch(`/planeacion/programa-tejido/${id}/prioridad/subir`, {
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
			// Guardar el ID del registro movido en sessionStorage para después de recargar
			if (data.registro_id) {
				sessionStorage.setItem('scrollToRegistroId', data.registro_id);
				sessionStorage.setItem('selectRegistroId', data.registro_id);
			}
			// Guardar mensaje de éxito para mostrar después de recargar
			sessionStorage.setItem('priorityChangeMessage', 'Prioridad actualizada correctamente');
			sessionStorage.setItem('priorityChangeType', 'success');
			// Recargar la página para mostrar los cambios
			window.location.href = '/planeacion/programa-tejido';
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
	const rows = $$('.selectable-row', tb);
	if (selectedRowIndex < 0 || selectedRowIndex >= rows.length - 1) {
		showToast('No se puede bajar<br>El registro ya es el último', 'error');
		return;
	}
	const selectedRow = rows[selectedRowIndex];
	const id = selectedRow.getAttribute('data-id');

	if (!id) {
		showToast('Error<br>No se pudo obtener el ID del registro', 'error');
		return;
	}

	// Mostrar loading rápido
	showLoading();

	// Ejecutar directamente sin confirmación
	fetch(`/planeacion/programa-tejido/${id}/prioridad/bajar`, {
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
			// Guardar el ID del registro movido en sessionStorage para después de recargar
			if (data.registro_id) {
				sessionStorage.setItem('scrollToRegistroId', data.registro_id);
				sessionStorage.setItem('selectRegistroId', data.registro_id);
			}
			// Guardar mensaje de éxito para mostrar después de recargar
			sessionStorage.setItem('priorityChangeMessage', 'Prioridad actualizada correctamente');
			sessionStorage.setItem('priorityChangeType', 'success');
			// Recargar la página para mostrar los cambios
			window.location.href = '/planeacion/programa-tejido';
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

// ===== Función para abrir nuevo registro =====
function abrirNuevo() {
	window.location.href = '/planeacion/programa-tejido/nuevo';
}

// ===== Función para eliminar registro =====
function eliminarRegistro(id) {
	// Confirmar eliminación
	if (typeof Swal !== 'undefined') {
		Swal.fire({
			title: '¿Eliminar registro?',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonText: 'Sí, eliminar',
			cancelButtonText: 'Cancelar',
			confirmButtonColor: '#dc2626',
			cancelButtonColor: '#6b7280',
		}).then((result) => {
			if (result.isConfirmed) {
				// Mostrar loading
				showLoading();

				fetch(`/planeacion/programa-tejido/${id}`, {
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
						// Guardar mensaje de éxito para mostrar después de recargar
						sessionStorage.setItem('priorityChangeMessage', 'Registro eliminado correctamente');
						sessionStorage.setItem('priorityChangeType', 'success');
						// Recargar la página para mostrar los cambios
						window.location.href = '/planeacion/programa-tejido';
					} else {
						showToast(data.message || 'No se pudo eliminar el registro', 'error');
					}
				})
				.catch(error => {
					hideLoading();
					console.error('Error:', error);
					showToast('Ocurrió un error al procesar la solicitud', 'error');
				});
			}
		});
	} else {
		// Fallback si no hay SweetAlert
		if (!confirm('¿Eliminar registro? Esta acción no se puede deshacer.')) {
			return;
		}
		showLoading();
		fetch(`/planeacion/programa-tejido/${id}`, {
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
				window.location.href = '/planeacion/programa-tejido';
			} else {
				showToast(data.message || 'No se pudo eliminar el registro', 'error');
			}
		});
	}
}

// ===== Init =====
document.addEventListener('DOMContentLoaded', function() {
	const tb = tbodyEl();
	if (tb) {
		allRows = $$('.selectable-row', tb);
		allRows.forEach((row, i) => row.addEventListener('click', () => selectRow(row, i)));
	}
	updateFilterCount();
	window.addEventListener('resize', () => updatePinnedColumnsPositions());

    const btnEditar = document.getElementById('btn-editar-programa');
    if (btnEditar) {
        btnEditar.addEventListener('click', () => {
            const selected = $$('.selectable-row')[selectedRowIndex];
            const id = selected ? selected.getAttribute('data-id') : null;
            if (!id) return;
            window.location.href = `/planeacion/programa-tejido/${encodeURIComponent(id)}/editar`;
        });
    }

    const btnEliminar = document.getElementById('btn-eliminar-programa');
    if (btnEliminar) {
        btnEliminar.addEventListener('click', () => {
            const selected = $$('.selectable-row')[selectedRowIndex];
            const id = selected ? selected.getAttribute('data-id') : null;
            if (!id) return;
            eliminarRegistro(id);
        });
    }

	// ===== Restaurar selección después de recargar (después de mover prioridad) =====
	const registroIdToSelect = sessionStorage.getItem('selectRegistroId');
	const registroIdToScroll = sessionStorage.getItem('scrollToRegistroId');
	const priorityChangeMessage = sessionStorage.getItem('priorityChangeMessage');
	const priorityChangeType = sessionStorage.getItem('priorityChangeType');

	if (registroIdToSelect || registroIdToScroll) {
		// Esperar un poco para que el DOM esté completamente cargado
		setTimeout(() => {
			const rows = $$('.selectable-row', tb);
			let targetRow = null;
			let targetIndex = -1;

			// Buscar el registro por ID
			rows.forEach((row, index) => {
				const rowId = row.getAttribute('data-id');
				if (rowId && (rowId === registroIdToSelect || rowId === registroIdToScroll)) {
					targetRow = row;
					targetIndex = index;
				}
			});

			if (targetRow && targetIndex >= 0) {
				// Seleccionar la fila
				selectRow(targetRow, targetIndex);

				// Desplazar la vista hacia la fila
				targetRow.scrollIntoView({
					behavior: 'smooth',
					block: 'center',
					inline: 'nearest'
				});

				// Limpiar sessionStorage
				sessionStorage.removeItem('selectRegistroId');
				sessionStorage.removeItem('scrollToRegistroId');
			}
		}, 300); // Pequeño delay para asegurar que todo esté renderizado
	}

	// Mostrar notificación después de recargar si hay un mensaje guardado
	if (priorityChangeMessage) {
		setTimeout(() => {
			showToast(priorityChangeMessage, priorityChangeType || 'success');
			sessionStorage.removeItem('priorityChangeMessage');
			sessionStorage.removeItem('priorityChangeType');
		}, 500); // Pequeño delay para que el DOM esté listo
	}
});

// === Integración con modal de filtros compacto del layout ===
// values: { columna: valor, ... }
window.applyTableFilters = function(values){
    try{
        const tb = tbodyEl(); if(!tb) return;
        // Base: filas originales
        const rows = allRows.slice();
        const entries = Object.entries(values || {});
        let filtered = rows;
        if(entries.length){
            filtered = rows.filter(tr => {
                return entries.every(([col, val]) => {
                    const cell = tr.querySelector(`[data-column="${CSS.escape(col)}"]`);
                    if(!cell) return false;
                    return (cell.textContent || '').toLowerCase().includes(String(val).toLowerCase());
                });
            });
        }
        // Render
        tb.innerHTML = '';
        filtered.forEach((r,i) => { r.onclick = () => selectRow(r, i); tb.appendChild(r); });
    }catch(e){}
}
</script>

@include('components.toast-notification')
@endsection
