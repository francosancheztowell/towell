@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Programa de Tejido')



@section('content')
<div class="w-full px-0 py-0 ">
	<div class="bg-white shadow overflow-hidden w-full" >

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
					<div class="overflow-y-auto" style="max-height: calc(100vh - 70px); position: relative;">
						<table id="mainTable" class="min-w-full divide-y divide-gray-200">
							<thead class="bg-blue-500 text-white" style="position: sticky; top: 0; z-index: 10;">
								<tr>
									@foreach($columns as $index => $col)
									<th class="px-2 py-1 text-left text-xs font-semibold text-white whitespace-nowrap column-{{ $index }}"
										style="position: sticky; top: 0; z-index: 10; background-color: #3b82f6; min-width: 80px;"
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
				<i class="fas fa-database text-gray-500 text-4xl mb-4"></i>
				<h3 class="mt-2 text-sm font-medium text-gray-900">No hay registros</h3>
				<p class="mt-1 text-sm text-gray-500">No se han importado registros aún. Carga un archivo Excel para comenzar.</p>
				<div class="mt-6">
					<a href="{{ route('configuracion.cargar.planeacion') }}"
					   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
						<i class="fas fa-file-excel mr-2"></i>
						Cargar Archivo Excel
					</a>
				</div>
			</div>
		@endif
	</div>
</div>

{{-- Componente para modal de líneas de detalle --}}
@include('components.tables.req-programa-tejido-line-table')
        <style>
            /* Columnas fijadas */
            .pinned-column {
                position: sticky !important;
                background-color: #3b82f6 !important;
                color: #fff !important;
            }

            /* Estilos para drag and drop */
            .cursor-move {
                cursor: move !important;
            }

            .cursor-not-allowed {
                cursor: not-allowed !important;
                opacity: 0.6;
            }

            .selectable-row.dragging {
                opacity: 0.4;
                background-color: #e0e7ff !important;
            }

            .selectable-row.drag-over {
                border-top: 3px solid #3b82f6;
                background-color: #dbeafe;
            }

            .selectable-row.drop-not-allowed {
                border-top: 3px solid #ef4444;
                background-color: #fee2e2;
                cursor: not-allowed !important;
            }

            /* Animación de actualización de celdas */
            td {
                transition: background-color 0.5s ease-in-out;
            }

            td.bg-yellow-100 {
                background-color: #fef3c7 !important;
            }

</style>

<script>
// ===== Estado =====
let filters = [];
let hiddenColumns = [];
let pinnedColumns = [];
let allRows = [];
let selectedRowIndex = -1;
let dragDropMode = false;
let draggedRow = null;
let draggedRowIndex = -1;
let draggedRowTelar = null;

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
	rows.forEach((r) => {
		const realIndex = allRows.indexOf(r);
		if (realIndex === -1) return;

		if (dragDropMode) {
			const enProceso = isRowEnProceso(r);
			r.draggable = !enProceso;
			r.onclick = null;

			if (!enProceso) {
				r.classList.add('cursor-move');
				r.addEventListener('dragstart', handleDragStart);
				r.addEventListener('dragover', handleDragOver);
				r.addEventListener('drop', handleDrop);
				r.addEventListener('dragend', handleDragEnd);
			} else {
				r.classList.add('cursor-not-allowed');
				r.style.opacity = '0.6';
			}
		} else {
			r.onclick = () => selectRow(r, realIndex);
		}
		tb.appendChild(r);
	});

	// IMPORTANTE: Actualizar allRows después de manipular el DOM
	allRows = Array.from(tb.querySelectorAll('.selectable-row'));

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
	const tb = tbodyEl();
	tb.innerHTML = '';
	allRows.forEach((r) => {
		const realIndex = allRows.indexOf(r);

		r.classList.remove('bg-blue-500','text-white','hover:bg-blue-50');
		r.classList.add('hover:bg-blue-50');
		$$('td', r).forEach(td => td.classList.remove('text-white','text-gray-700'));

		if (dragDropMode) {
			const enProceso = isRowEnProceso(r);
			r.draggable = !enProceso;
			r.onclick = null;

			if (!enProceso) {
				r.classList.add('cursor-move');
				r.addEventListener('dragstart', handleDragStart);
				r.addEventListener('dragover', handleDragOver);
				r.addEventListener('drop', handleDrop);
				r.addEventListener('dragend', handleDragEnd);
			} else {
				r.classList.add('cursor-not-allowed');
				r.style.opacity = '0.6';
			}
		} else {
			r.onclick = () => selectRow(r, realIndex);
		}
		tb.appendChild(r);
	});

	// IMPORTANTE: Actualizar allRows después de manipular el DOM
	allRows = Array.from(tb.querySelectorAll('.selectable-row'));

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

	selectedRowIndex = -1;

	// Deshabilitar botones
	const btnEditar = document.getElementById('btn-editar-programa');
	if (btnEditar) btnEditar.disabled = true;
	const btnEliminar = document.getElementById('btn-eliminar-programa');
	if (btnEliminar) btnEliminar.disabled = true;
	const btnVerLineas = document.getElementById('btn-ver-lineas');
	if (btnVerLineas) btnVerLineas.disabled = true;
	const btnVerLineasLayoutReset = document.getElementById('layoutBtnVerLineas');
	if (btnVerLineasLayoutReset) btnVerLineasLayoutReset.disabled = true;

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
                el.style.zIndex = '10';
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
				el.style.zIndex = String(20 + order);
            el.style.position = 'sticky';
            } else {
				el.style.zIndex = String(15 + order);
				el.style.position = 'sticky';
			}
		});
		left += width;
	});
}

// ===== Selección de filas =====
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

		// Habilitar botones editar, eliminar y ver líneas (local y layout)
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

		// Habilitar botón de ver líneas de detalle
		const btnVerLineas = document.getElementById('btn-ver-lineas');
		const btnVerLineasLayoutSelect = document.getElementById('layoutBtnVerLineas');
		if (btnVerLineas) btnVerLineas.disabled = false;
		if (btnVerLineasLayoutSelect) btnVerLineasLayoutSelect.disabled = false;
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

		// Deshabilitar botones editar y eliminar (local y layout)
		const btnEditar = document.getElementById('btn-editar-programa');
		const btnEditarLayout = document.getElementById('layoutBtnEditar');
		if (btnEditar) btnEditar.disabled = true;
		if (btnEditarLayout) btnEditarLayout.disabled = true;

		const btnEliminar = document.getElementById('btn-eliminar-programa');
		const btnEliminarLayout = document.getElementById('layoutBtnEliminar');
		if (btnEliminar) btnEliminar.disabled = true;
		if (btnEliminarLayout) btnEliminarLayout.disabled = true;

		// Deshabilitar botón de ver líneas de detalle
		const btnVerLineas = document.getElementById('btn-ver-lineas');
		const btnVerLineasLayoutDeselect = document.getElementById('layoutBtnVerLineas');
		if (btnVerLineas) btnVerLineas.disabled = true;
		if (btnVerLineasLayoutDeselect) btnVerLineasLayoutDeselect.disabled = true;
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

// ===== Drag and Drop =====
// Variables globales para drag and drop
let dragStartPosition = null; // Guardar posición inicial del drag

// Función helper para obtener el telar de una fila
function getRowTelar(row) {
	const telarCell = row.querySelector('[data-column="NoTelarId"]');
	return telarCell ? telarCell.textContent.trim() : null;
}

// Función helper para verificar si una fila está en proceso
function isRowEnProceso(row) {
	const enProcesoCell = row.querySelector('[data-column="EnProceso"]');
	if (enProcesoCell) {
		const checkbox = enProcesoCell.querySelector('input[type="checkbox"]');
		return checkbox && checkbox.checked;
	}
	return false;
}

// Activar/Desactivar modo drag and drop
function toggleDragDropMode() {
	dragDropMode = !dragDropMode;
	const btn = $('#btnDragDrop');
	const tb = tbodyEl();

	if (!btn || !tb) return;

	if (dragDropMode) {
		// Activar modo drag and drop
		btn.classList.remove('bg-black', 'hover:bg-gray-800', 'focus:ring-gray-500');
		btn.classList.add('bg-gray-400', 'hover:bg-gray-500', 'ring-2', 'ring-gray-300');
		btn.title = 'Desactivar arrastrar filas';

		const rows = $$('.selectable-row', tb);
		rows.forEach(row => {
			const enProceso = isRowEnProceso(row);
			row.draggable = !enProceso;
			row.onclick = null; // Remover click mientras está en modo drag

			if (!enProceso) {
				row.classList.add('cursor-move');
				row.addEventListener('dragstart', handleDragStart);
				row.addEventListener('dragover', handleDragOver);
				row.addEventListener('drop', handleDrop);
				row.addEventListener('dragend', handleDragEnd);
			} else {
				row.classList.add('cursor-not-allowed');
				row.style.opacity = '0.6';
			}
		});

		showToast('Modo arrastrar activado<br>Arrastra las filas para reorganizarlas', 'info');
	} else {
		// Desactivar modo drag and drop
		btn.classList.remove('bg-gray-400', 'hover:bg-gray-500', 'ring-2', 'ring-gray-300');
		btn.classList.add('bg-black', 'hover:bg-gray-800', 'focus:ring-gray-500');
		btn.title = 'Activar/Desactivar arrastrar filas';

		// IMPORTANTE: Primero actualizar allRows con el orden actual del DOM
		allRows = Array.from(tb.querySelectorAll('.selectable-row'));

		const rows = $$('.selectable-row', tb);
		rows.forEach(row => {
			// Usar el índice actualizado de allRows
			const realIndex = allRows.indexOf(row);

			// Limpiar atributos y estilos de drag
			row.draggable = false;
			row.classList.remove('cursor-move', 'cursor-not-allowed');
			row.style.opacity = '';
			row.onclick = null;

			// Remover todos los event listeners de drag
			row.removeEventListener('dragstart', handleDragStart);
			row.removeEventListener('dragover', handleDragOver);
			row.removeEventListener('drop', handleDrop);
			row.removeEventListener('dragend', handleDragEnd);

			// IMPORTANTE: Restaurar el onclick para selección de TODAS las filas
			if (realIndex !== -1) {
				row.onclick = () => selectRow(row, realIndex);
			}
		});

		showToast('Modo arrastrar desactivado', 'info');
	}
}

// Manejador de inicio de drag
function handleDragStart(e) {
	// Validación: no permitir drag si está en proceso
	if (isRowEnProceso(this)) {
		e.preventDefault();
		showToast('No se puede mover un registro en proceso', 'error');
		return false;
	}

	draggedRow = this;
	draggedRowTelar = getRowTelar(this);
	dragStartPosition = this.rowIndex; // Guardar posición inicial

	this.classList.add('dragging');
	this.style.opacity = '0.4';

	e.dataTransfer.effectAllowed = 'move';
	e.dataTransfer.setData('text/html', this.innerHTML);
}

// Manejador de drag over (feedback visual Y reordenamiento temporal)
function handleDragOver(e) {
	e.preventDefault();

	if (this === draggedRow) return false;

	const targetTelar = getRowTelar(this);

	// Validación: telares diferentes
	if (draggedRowTelar !== targetTelar) {
		e.dataTransfer.dropEffect = 'none';
		this.classList.add('drop-not-allowed');
		this.classList.remove('drag-over');
		return false;
	}

	// Validación pasada: permitir movimiento
	e.dataTransfer.dropEffect = 'move';
	this.classList.remove('drop-not-allowed');
	this.classList.add('drag-over');

	// Reordenar visualmente en el DOM
	const tbody = this.parentNode;
	const afterElement = getDragAfterElement(tbody, e.clientY);

	if (afterElement == null) {
		tbody.appendChild(draggedRow);
	} else {
		tbody.insertBefore(draggedRow, afterElement);
	}

	return false;
}

// Helper para determinar después de qué elemento insertar
function getDragAfterElement(container, y) {
	const draggableElements = [...container.querySelectorAll('.selectable-row:not(.dragging)')];

	return draggableElements.reduce((closest, child) => {
		const box = child.getBoundingClientRect();
		const offset = y - box.top - box.height / 2;

		if (offset < 0 && offset > closest.offset) {
			return { offset: offset, element: child };
		} else {
			return closest;
		}
	}, { offset: Number.NEGATIVE_INFINITY }).element;
}

// Manejador de drop (aquí se hace el movimiento real)
function handleDrop(e) {
	e.stopPropagation();
	e.preventDefault();

	if (!draggedRow) return false;

	const targetTelar = getRowTelar(this);

	// Validación final: verificar telar
	if (draggedRowTelar !== targetTelar) {
		showToast('No se puede mover entre diferentes telares', 'error');
		return false;
	}

	const tb = tbodyEl();
	if (!tb) return false;

	// Calcular nueva posición
	const allRowsSameTelar = allRows.filter(row => getRowTelar(row) === draggedRowTelar);
	const allRowsInDOM = Array.from(tb.querySelectorAll('.selectable-row'));

	const positionMap = new Map();
	allRowsInDOM.forEach((row, idx) => {
		if (allRowsSameTelar.includes(row)) {
			positionMap.set(row, idx);
		}
	});

	const rowsOrdered = allRowsSameTelar
		.filter(row => positionMap.has(row))
		.sort((a, b) => (positionMap.get(a) ?? Infinity) - (positionMap.get(b) ?? Infinity));

	if (!positionMap.has(draggedRow)) {
		rowsOrdered.push(draggedRow);
	}

	const nuevaPosicion = rowsOrdered.indexOf(draggedRow);
	const registroId = draggedRow.getAttribute('data-id');

	if (nuevaPosicion === -1 || !registroId) {
		showToast('Error al procesar el movimiento', 'error');
		return false;
	}

	// Enviar al backend
	showLoading();
	fetch(`/planeacion/programa-tejido/${registroId}/prioridad/mover`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
		},
		body: JSON.stringify({ new_position: nuevaPosicion })
	})
	.then(response => response.json())
	.then(data => {
		hideLoading();
		if (data.success) {
			// Actualizar el DOM sin recargar la página
			updateTableAfterDragDrop(data.detalles, registroId);

			// Mostrar mensaje de éxito
			showToast(`Prioridad actualizada<br>${data.cascaded_records || 0} registro(s) recalculado(s)`, 'success');

			// Mantener seleccionado el registro movido
			const movedRow = document.querySelector(`.selectable-row[data-id="${registroId}"]`);
			if (movedRow) {
				const realIndex = allRows.indexOf(movedRow);
				if (realIndex !== -1) {
					// Pequeño delay para que se vea la animación
					setTimeout(() => {
						selectRow(movedRow, realIndex);
						movedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
					}, 300);
				}
			}
		} else {
			showToast(data.message || 'No se pudo actualizar la prioridad', 'error');
		}
	})
	.catch(error => {
		hideLoading();
		console.error('Error:', error);
		showToast('Ocurrió un error al procesar la solicitud', 'error');
	});

	return false;
}

// Actualizar tabla después de drag and drop exitoso (sin recargar página)
function updateTableAfterDragDrop(detalles, registroMovidoId) {
	if (!detalles || !Array.isArray(detalles)) return;

	// Función helper para formatear fechas
	const formatDate = (dateStr) => {
		if (!dateStr) return '';
		try {
			const date = new Date(dateStr);
			if (date.getFullYear() > 1970) {
				return date.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' +
				       date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
			}
		} catch (e) {}
		return '';
	};

	// Actualizar cada registro afectado
	detalles.forEach(detalle => {
		const row = document.querySelector(`.selectable-row[data-id="${detalle.Id}"]`);
		if (!row) return;

		// Destacar visualmente el registro que fue movido
		const esRegistroMovido = (detalle.Id == registroMovidoId);
		if (esRegistroMovido) {
			row.classList.add('bg-green-50');
			setTimeout(() => row.classList.remove('bg-green-50'), 2000);
		}

		// Actualizar FechaInicio
		if (detalle.FechaInicio_nueva) {
			const fechaInicioCell = row.querySelector('[data-column="FechaInicio"]');
			if (fechaInicioCell) {
				fechaInicioCell.textContent = formatDate(detalle.FechaInicio_nueva);
				fechaInicioCell.classList.add('bg-yellow-100');
				setTimeout(() => fechaInicioCell.classList.remove('bg-yellow-100'), 1500);
			}
		}

		// Actualizar FechaFinal
		if (detalle.FechaFinal_nueva) {
			const fechaFinalCell = row.querySelector('[data-column="FechaFinal"]');
			if (fechaFinalCell) {
				fechaFinalCell.textContent = formatDate(detalle.FechaFinal_nueva);
				fechaFinalCell.classList.add('bg-yellow-100');
				setTimeout(() => fechaFinalCell.classList.remove('bg-yellow-100'), 1500);
			}
		}

		// Actualizar EnProceso
		if (detalle.hasOwnProperty('EnProceso')) {
			const enProcesoCell = row.querySelector('[data-column="EnProceso"]');
			if (enProcesoCell) {
				const checkbox = enProcesoCell.querySelector('input[type="checkbox"]');
				if (checkbox) {
					checkbox.checked = (detalle.EnProceso == 1 || detalle.EnProceso === true);
				}
			}
		}

		// Actualizar Ultimo
		if (detalle.hasOwnProperty('Ultimo')) {
			const ultimoCell = row.querySelector('[data-column="Ultimo"]');
			if (ultimoCell) {
				ultimoCell.textContent = detalle.Ultimo === '1' || detalle.Ultimo === 'UL' ? '1' : '';
			}
		}
	});

	// Actualizar allRows para reflejar el orden actual del DOM
	const tb = tbodyEl();
	if (tb) {
		allRows = Array.from(tb.querySelectorAll('.selectable-row'));
	}
}

// Manejador de fin de drag
function handleDragEnd(e) {
	this.classList.remove('dragging');
	this.style.opacity = '';

	// Limpiar estilos visuales de todas las filas
	$$('.selectable-row').forEach(row => {
		row.classList.remove('drag-over', 'drop-not-allowed');
	});

	// Limpiar variables
	draggedRow = null;
	draggedRowTelar = null;
	dragStartPosition = null;
}

// ===== Init =====
document.addEventListener('DOMContentLoaded', function() {
	const tb = tbodyEl();
	if (tb) {
		allRows = $$('.selectable-row', tb);
		// Usar onclick para consistencia con el resto del código
		allRows.forEach((row, i) => {
			row.onclick = () => selectRow(row, i);
		});
	}
	updateFilterCount();
	window.addEventListener('resize', () => updatePinnedColumnsPositions());

	// Inicializar botones del layout como deshabilitados
	const btnEditarLayout = document.getElementById('layoutBtnEditar');
	const btnEliminarLayout = document.getElementById('layoutBtnEliminar');
	const btnVerLineasLayout = document.getElementById('layoutBtnVerLineas');
	if (btnEditarLayout) {
		btnEditarLayout.disabled = true;
	}
	if (btnEliminarLayout) {
		btnEliminarLayout.disabled = true;
	}
	if (btnVerLineasLayout) btnVerLineasLayout.disabled = true;

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

    const btnVerLineas = document.getElementById('btn-ver-lineas');
    // btnVerLineasLayout ya está declarado arriba en la línea 1396
    if (btnVerLineas) {
        btnVerLineas.addEventListener('click', () => {
            const selected = $$('.selectable-row')[selectedRowIndex];
            const id = selected ? selected.getAttribute('data-id') : null;
            if (!id) return;
            if (typeof window.openLinesModal === 'function') {
                window.openLinesModal(id);
            } else {
                console.error('openLinesModal no está disponible. Asegúrate de que el componente req-programa-tejido-line-table esté incluido.');
                showToast('Error: No se pudo abrir el modal. Por favor recarga la página.', 'error');
            }
        });
    }
    if (btnVerLineasLayout) {
        btnVerLineasLayout.addEventListener('click', () => {
            const selected = $$('.selectable-row')[selectedRowIndex];
            const id = selected ? selected.getAttribute('data-id') : null;
            if (!id) return;
            if (typeof window.openLinesModal === 'function') {
                window.openLinesModal(id);
            } else {
                console.error('openLinesModal no está disponible. Asegúrate de que el componente req-programa-tejido-line-table esté incluido.');
                showToast('Error: No se pudo abrir el modal. Por favor recarga la página.', 'error');
            }
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
        filtered.forEach((r) => {
            const realIndex = allRows.indexOf(r);
            if (realIndex === -1) return;

            if (dragDropMode) {
                const enProceso = isRowEnProceso(r);
                r.draggable = !enProceso;
                r.onclick = null;

                if (!enProceso) {
                    r.classList.add('cursor-move');
                    r.addEventListener('dragstart', handleDragStart);
                    r.addEventListener('dragover', handleDragOver);
                    r.addEventListener('drop', handleDrop);
                    r.addEventListener('dragend', handleDragEnd);
                } else {
                    r.classList.add('cursor-not-allowed');
                    r.style.opacity = '0.6';
                }
            } else {
                r.onclick = () => selectRow(r, realIndex);
            }
            tb.appendChild(r);
        });

        // IMPORTANTE: Actualizar allRows después de manipular el DOM
        allRows = Array.from(tb.querySelectorAll('.selectable-row'));
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
