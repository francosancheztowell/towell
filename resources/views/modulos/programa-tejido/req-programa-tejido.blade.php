@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Programa de Tejido')



@section('content')
<div class="w-full px-0 py-0 ">
	<div class="bg-white shadow overflow-hidden w-full" style="max-width: 100%;">

		@php
		// Asegurar que $columns esté definido
		$columns = $columns ?? [];
		$registros = $registros ?? collect();

		// Las columnas vienen del controller con información de tipo de fecha
		$formatValue = function($registro, $field, $dateType = null) {
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

			// Formatear fechas según su tipo (DATE o DATETIME)
			if ($dateType === 'date' || $dateType === 'datetime') {
				try {
					if ($value instanceof \Carbon\Carbon) {
						if ($value->year > 1970) {
							// DATE: solo fecha sin hora
							if ($dateType === 'date') {
								return $value->format('d/m/Y');
							}
							// DATETIME: fecha con hora
							return $value->format('d/m/Y H:i');
						}
						return '';
					}
					$dt = \Carbon\Carbon::parse($value);
					if ($dt->year > 1970) {
						// DATE: solo fecha sin hora
						if ($dateType === 'date') {
							return $dt->format('d/m/Y');
						}
						// DATETIME: fecha con hora
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

		@if(isset($registros) && $registros->count() > 0)
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
										<td class="px-3 py-2 text-sm text-gray-700 {{ ($col['dateType'] ?? null) ? 'whitespace-normal' : 'whitespace-nowrap' }} column-{{ $colIndex }}"
											data-column="{{ $col['field'] }}">
											{!! $formatValue($registro, $col['field'], $col['dateType'] ?? null) !!}
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
					<a href="{{ route('configuracion.cargar.planeacion') }}"
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
@include('components.tables.req-programa-tejido-line-table')

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

// ===== Columnas desde PHP =====
const columnsData = @json($columns ?? []);

// ===== Filtros =====
function renderFilterModalContent() {
	const options = columnsData.map(c => ({field: c.field, label: c.label}));
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
	return columnsData.map(c => ({label: c.label, field: c.field}));
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
	try {
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

		// Habilitar botones editar y eliminar (local y layout)
		const btnEditar = document.getElementById('btn-editar-programa');
		const btnEditarLayout = document.getElementById('layoutBtnEditar');
		if (btnEditar) btnEditar.disabled = false;
		if (btnEditarLayout) btnEditarLayout.disabled = false;

		const btnEliminar = document.getElementById('btn-eliminar-programa');
		const btnEliminarLayout = document.getElementById('layoutBtnEliminar');

		// Verificar si el registro está en proceso (una sola vez)
		const enProceso = rowElement.querySelector('[data-column="EnProceso"]');
		const estaEnProceso = enProceso && enProceso.querySelector('input[type="checkbox"]')?.checked;

		if (btnEliminar) btnEliminar.disabled = estaEnProceso;
		if (btnEliminarLayout) btnEliminarLayout.disabled = estaEnProceso;
	} catch(e) {
		console.error('Error en selectRow:', e);
	}
}

function deselectRow() {
	try {
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

		// Deshabilitar botones editar y eliminar (local y layout)
		const btnEditar = document.getElementById('btn-editar-programa');
		const btnEditarLayout = document.getElementById('layoutBtnEditar');
		if (btnEditar) btnEditar.disabled = true;
		if (btnEditarLayout) btnEditarLayout.disabled = true;

		const btnEliminar = document.getElementById('btn-eliminar-programa');
		const btnEliminarLayout = document.getElementById('layoutBtnEliminar');
		if (btnEliminar) btnEliminar.disabled = true;
		if (btnEliminarLayout) btnEliminarLayout.disabled = true;
	} catch(e) {
		console.error('Error en deselectRow:', e);
	}
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

// ===== Función para descargar programa =====
function descargarPrograma() {
	Swal.fire({
		title: 'Descargar Programa',
		html: `
			<div class="text-left">
				<label class="block text-sm font-medium text-gray-700 mb-2">Fecha Inicial:</label>
				<input
					type="date"
					id="fechaInicial"
					class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
					required
				>
			</div>
		`,
		icon: 'question',
		showCancelButton: true,
		confirmButtonText: 'Descargar',
		cancelButtonText: 'Cancelar',
		confirmButtonColor: '#3b82f6',
		cancelButtonColor: '#6b7280',
		didOpen: () => {
			// Preseleccionar fecha actual
			const hoy = new Date().toISOString().split('T')[0];
			document.getElementById('fechaInicial').value = hoy;
			document.getElementById('fechaInicial').focus();
		},
		preConfirm: () => {
			const fechaInicial = document.getElementById('fechaInicial').value;
			if (!fechaInicial) {
				Swal.showValidationMessage('Por favor seleccione una fecha inicial');
				return false;
			}
			return fechaInicial;
		}
	}).then((result) => {
		if (result.isConfirmed) {
			const fechaInicial = result.value;

			// Mostrar loading
			showLoading();

			// Hacer petición al servidor
			fetch('/planeacion/programa-tejido/descargar-programa', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
				},
				body: JSON.stringify({
					fecha_inicial: fechaInicial
				})
			})
			.then(response => response.json())
			.then(data => {
				hideLoading();
				if (data.success) {
					showToast('Programa descargado correctamente', 'success');
				} else {
					showToast(data.message || 'Error al descargar el programa', 'error');
				}
			})
			.catch(error => {
				hideLoading();
				console.error('Error:', error);
				showToast('Ocurrió un error al procesar la solicitud', 'error');
			});
		}
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

	// Inicializar botones del layout como deshabilitados
	const btnEditarLayout = document.getElementById('layoutBtnEditar');
	const btnEliminarLayout = document.getElementById('layoutBtnEliminar');
	if (btnEditarLayout) {
		btnEditarLayout.disabled = true;
	}
	if (btnEliminarLayout) {
		btnEliminarLayout.disabled = true;
	}

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

// Modal para seleccionar rango de meses para Alta de Pronósticos
document.getElementById('btnAltaPronosticos')?.addEventListener('click', function() {
    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1;

    // Generar opciones de meses (últimos 12 meses + próximos 3 meses)
    const meses = [];
    for (let i = -12; i <= 3; i++) {
        const date = new Date(currentYear, currentMonth + i - 1, 1);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const label = date.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });
        meses.push({ value: `${year}-${month}`, label: label.charAt(0).toUpperCase() + label.slice(1) });
    }

    const mesesHTML = meses.map(m =>
        `<option value="${m.value}">${m.label}</option>`
    ).join('');

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
            // Preseleccionar mes actual
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

            // Validar que mes inicial <= mes final
            if (mesInicial > mesFinal) {
                Swal.showValidationMessage('El mes inicial debe ser menor o igual al mes final');
                return false;
            }

            // Generar lista de meses entre inicial y final
            const mesesSeleccionados = [];
            const [yearIni, monthIni] = mesInicial.split('-').map(Number);
            const [yearFin, monthFin] = mesFinal.split('-').map(Number);

            let currentYear = yearIni;
            let currentMonth = monthIni;

            while (currentYear < yearFin || (currentYear === yearFin && currentMonth <= monthFin)) {
                mesesSeleccionados.push(`${currentYear}-${String(currentMonth).padStart(2, '0')}`);

                currentMonth++;
                if (currentMonth > 12) {
                    currentMonth = 1;
                    currentYear++;
                }
            }

            return mesesSeleccionados;
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const meses = result.value;
            // Construir URL con los meses como parámetros
            const url = new URL('{{ route("programa-tejido.alta-pronosticos") }}', window.location.origin);
            meses.forEach(mes => {
                url.searchParams.append('meses[]', mes);
            });
            // Redirigir a la página de alta de pronósticos
            window.location.href = url.toString();
        }
    });
});
</script>

@include('components.ui.toast-notification')
@endsection
