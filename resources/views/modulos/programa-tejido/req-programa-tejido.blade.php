@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Programa de Tejido')



@section('content')
<div class="w-full ">
	<div class="bg-white shadow overflow-hidden w-full">

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
		// Si es 0, mostrar vacío
		if ($sv === '0' || $value === 0) return '';
		}

		// Si CambioHilo es 0, mostrar vacío
		if ($field === 'CambioHilo') {
		if ($value === '0' || $value === 0) return '';
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
							@php
								$rawValue = $registro->{$col['field']} ?? '';
								if ($rawValue instanceof \Carbon\Carbon) {
									$rawValue = $rawValue->format('Y-m-d H:i:s');
								}
							@endphp
							<td class="px-3 py-2 text-sm text-gray-700 {{ ($col['dateType'] ?? null) ? 'whitespace-normal' : 'whitespace-nowrap' }} column-{{ $colIndex }}"
								data-column="{{ $col['field'] }}"
								data-value="{{ e(is_scalar($rawValue) ? $rawValue : json_encode($rawValue)) }}">
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
		@include('components.programa-tejido.empty-state')
		@endif
	</div>
</div>

{{-- Componente para modal de líneas de detalle --}}
@include('components.programa-tejido.req-programa-tejido-line-table')
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

	.selectable-row.drag-over-warning {
		border-top: 3px solid #f59e0b;
		background-color: #fef3c7;
		box-shadow: 0 0 0 2px #f59e0b;
	}

	.selectable-row.drop-not-allowed {
		border-top: 3px solid #ef4444;
		background-color: #fee2e2;
		cursor: not-allowed !important;
	}

	/* Animación de actualización de celdas - deshabilitada durante drag para mejor rendimiento */
	td {
		transition: background-color 0.3s ease-in-out;
	}

	.selectable-row.dragging ~ tr td,
	.selectable-row.dragging td {
		transition: none !important;
	}

	td.bg-yellow-100 {
		background-color: #fef3c7 !important;
	}

	.inline-edit-mode .selectable-row {
		cursor: text !important;
	}

	.inline-edit-input {
		width: 100%;
		border: 1px solid #cbd5f5;
		border-radius: 0.375rem;
		padding: 0.25rem 0.5rem;
		font-size: 0.85rem;
		color: #1f2937;
		background-color: #f9fafb;
		transition: border 0.2s ease, box-shadow 0.2s ease;
	}

	.inline-edit-input:focus {
		outline: none;
		border-color: #2563eb;
		box-shadow: 0 0 0 1px #2563eb33;
		background-color: #fff;
	}

	.inline-edit-row.inline-saving {
		opacity: 0.7;
	}
</style>

<script>
	@include('modulos.programa-tejido.scripts.state')

	@include('modulos.programa-tejido.scripts.filters')

	@include('modulos.programa-tejido.scripts.columns')

	@include('modulos.programa-tejido.scripts.selection')

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

	// ===== Inline Editing =====
	function toggleInlineEditMode() {
		const btn = document.getElementById('btnInlineEdit');
		if (!btn) {
			console.error('btnInlineEdit no encontrado');
			return;
		}

		inlineEditMode = !inlineEditMode;
		console.log('toggleInlineEditMode:', inlineEditMode);

		if (inlineEditMode) {
			if (dragDropMode) {
				toggleDragDropMode(); // Desactivar drag&drop antes de editar
			}
			deselectRow();
			document.body.classList.add('inline-edit-mode');

			// Actualizar botón en navbar (solo icono, sin span)
			btn.classList.remove('bg-yellow-500', 'hover:bg-yellow-600');
			btn.classList.add('bg-yellow-600', 'ring-2', 'ring-yellow-300');
			btn.title = 'Desactivar edición en línea';

			applyInlineModeToRows();
			showToast('Modo edición activado. Los cambios se guardan al salir de cada campo.', 'info');
		} else {
			document.body.classList.remove('inline-edit-mode');
			restoreInlineEditing();

			// Restaurar botón en navbar
			btn.classList.remove('bg-yellow-600', 'ring-2', 'ring-yellow-300');
			btn.classList.add('bg-yellow-500', 'hover:bg-yellow-600');
			btn.title = 'Activar edición en línea';

			showToast('Modo edición desactivado', 'info');
		}
	}

	function applyInlineModeToRows() {
		if (!inlineEditMode) return;
		console.log('applyInlineModeToRows: activando modo inline');
		const rows = allRows.length ? allRows : $$('.selectable-row');
		console.log('Filas encontradas:', rows.length);
		rows.forEach(row => {
			// Limpiar estado previo si existe
			delete row.dataset.inlinePrepared;
			makeRowInlineEditable(row);
		});
	}

	function restoreInlineEditing() {
		console.log('restoreInlineEditing: restaurando modo normal');
		const rows = allRows.length ? allRows : $$('.selectable-row');
		rows.forEach(row => {
			row.classList.remove('inline-edit-row', 'inline-saving');
			Object.keys(inlineEditableFields).forEach(field => {
				const cell = row.querySelector(`[data-column="${field}"]`);
				if (!cell) return;

				// Restaurar HTML original si existe
				const original = cell.dataset.originalHtml;
				if (original !== undefined) {
					cell.innerHTML = original;
					delete cell.dataset.originalHtml;
				} else {
					// Si no hay HTML original, restaurar desde el valor guardado
					const savedValue = cell.dataset.value;
					if (savedValue !== undefined) {
						cell.textContent = formatInlineDisplay(field, savedValue);
					}
				}
				delete cell.dataset.inlineEditing;
			});
			delete row.dataset.inlinePrepared;
		});
		console.log('restoreInlineEditing: restauración completada');
	}

	function makeRowInlineEditable(row) {
		if (!row) {
			console.warn('makeRowInlineEditable: row es null');
			return;
		}

		if (row.dataset.inlinePrepared === 'true') {
			console.log('makeRowInlineEditable: fila ya preparada, omitiendo');
			return;
		}

		const rowId = row.getAttribute('data-id');
		if (!rowId) {
			console.warn('makeRowInlineEditable: fila sin data-id');
			return;
		}

		console.log('makeRowInlineEditable: preparando fila', rowId);
		row.classList.add('inline-edit-row');
		row.dataset.inlinePrepared = 'true';

		Object.keys(inlineEditableFields).forEach(field => {
			const cell = row.querySelector(`[data-column="${field}"]`);
			if (!cell) {
				console.warn(`makeRowInlineEditable: celda no encontrada para campo ${field}`);
				return;
			}

			// Guardar HTML original si no está guardado
			if (!cell.dataset.originalHtml) {
				cell.dataset.originalHtml = cell.innerHTML;
			}

			// Guardar valor original si no está guardado
			if (!cell.dataset.value) {
				const textValue = cell.textContent.trim();
				cell.dataset.value = textValue;
			}

			cell.dataset.inlineEditing = 'true';

			const cfg = inlineEditableFields[field] || {};
			const input = document.createElement('input');
			input.type = cfg.type || 'text';
			if (cfg.step) input.step = cfg.step;
			if (cfg.min !== undefined) input.min = cfg.min;
			if (cfg.max !== undefined) input.max = cfg.max;
			if (cfg.maxLength) input.maxLength = cfg.maxLength;
			input.className = 'inline-edit-input w-full px-2 py-1 border border-blue-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500';

			const rawValue = cell.dataset.value ?? cell.textContent.trim();
			const formattedValue = formatInlineValueForInput(field, rawValue);
			input.value = formattedValue;
			input.dataset.originalValue = rawValue ?? '';

			input.addEventListener('keydown', (e) => {
				if (e.key === 'Enter') {
					e.preventDefault();
					input.blur();
				}
				if (e.key === 'Escape') {
					e.preventDefault();
					input.value = formatInlineValueForInput(field, input.dataset.originalValue ?? '');
					input.blur();
				}
			});

			input.addEventListener('blur', () => handleInlineInputChange(row, field, input));
			input.addEventListener('click', (e) => e.stopPropagation());

			cell.innerHTML = '';
			cell.appendChild(input);
			console.log(`makeRowInlineEditable: input creado para ${field} con valor ${formattedValue}`);
		});
	}

	function formatInlineValueForInput(field, value) {
		if (value === null || value === undefined || value === 'null') return '';
		const cfg = inlineEditableFields[field];
		if (cfg?.inputFormatter) {
			try {
				return cfg.inputFormatter(value);
			} catch (error) {
				return value ?? '';
			}
		}
		return value ?? '';
	}

	function formatInlineDisplay(field, value) {
		if (value === null || value === undefined || value === '' || value === 'null') return '';
		const cfg = inlineEditableFields[field];
		if (cfg?.displayFormatter) {
			try {
				return cfg.displayFormatter(value);
			} catch (error) {
				return value;
			}
		}
		return value;
	}

	function convertInputForPayload(field, value) {
		const cfg = inlineEditableFields[field] || {};
		if (cfg.toPayload) {
			return cfg.toPayload(value);
		}
		if (cfg.type === 'number') {
			if (value === '') return null;
			const parsed = parseFloat(value);
			return isNaN(parsed) ? null : parsed;
		}
		return value === '' ? null : value;
	}

	function getComparableValue(field, value) {
		if (value === null || value === undefined || value === '' || value === 'null') return null;
		const cfg = inlineEditableFields[field] || {};
		if (cfg.compareFormatter) {
			try {
				return cfg.compareFormatter(value);
			} catch (error) {
				return value;
			}
		}
		if (cfg.type === 'number') {
			const parsed = parseFloat(value);
			return isNaN(parsed) ? null : parsed;
		}
		return value;
	}

	function isNearlyEqual(a, b, tolerance = 0.000001) {
		return Math.abs(a - b) <= tolerance;
	}

	function handleInlineInputChange(row, field, input) {
		const cfg = inlineEditableFields[field] || {};
		const cell = input.closest('td');
		if (!cell) return;

		let newValue = (input.value ?? '').trim();
		if (cfg.type === 'number' && newValue !== '') {
			newValue = newValue.replace(',', '.');
		}

		const originalValue = cell.dataset.value ?? '';
		const normalizedOriginal = getComparableValue(field, originalValue);
		const normalizedValue = convertInputForPayload(field, newValue);

		if (normalizedValue === null && newValue !== '') {
			input.classList.add('border-red-500');
			showToast('Valor no válido para el campo seleccionado', 'error');
			return;
		}
		input.classList.remove('border-red-500');

		const sameValue =
			(normalizedValue === null && normalizedOriginal === null) ||
			(typeof normalizedValue === 'number' && typeof normalizedOriginal === 'number'
				? isNearlyEqual(normalizedValue, normalizedOriginal)
				: normalizedValue === normalizedOriginal);

		if (sameValue) return;

		const payloadField = inlineFieldPayloadMap[field] || field;
		const payload = {};
		payload[payloadField] = normalizedValue;

		saveInlineField(row, field, payload, input, normalizedValue);
	}

	async function saveInlineField(row, field, payload, input, normalizedValue) {
		const rowId = row.getAttribute('data-id');
		if (!rowId) {
			showToast('No se pudo identificar el registro.', 'error');
			return;
		}

		row.classList.add('inline-saving');
		input.disabled = true;

		try {
			const response = await fetch(`/planeacion/programa-tejido/${rowId}`, {
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
					'Accept': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
				},
				body: JSON.stringify(payload)
			});

			const data = await response.json();

			if (!response.ok || !data.success) {
				throw new Error(data.message || 'No se pudo actualizar el registro');
			}

			updateRowWithResponse(row, data.data || {}, field, normalizedValue);
			showToast('Registro actualizado correctamente', 'success');
		} catch (error) {
			showToast(error.message || 'Error al actualizar el registro', 'error');
			const cell = input.closest('td');
			if (cell) {
				input.value = formatInlineValueForInput(field, cell.dataset.value ?? '');
			}
		} finally {
			input.disabled = false;
			row.classList.remove('inline-saving');
		}
	}

	function updateRowWithResponse(row, updatedData, fallbackField, fallbackValue) {
		const fields = new Set(Object.keys(updatedData || {}));
		if (!fields.size && fallbackField) fields.add(fallbackField);

		fields.forEach(field => {
			const cell = row.querySelector(`[data-column="${field}"]`);
			if (!cell) return;
			const newValue = field in updatedData ? updatedData[field] : fallbackValue;
			cell.dataset.value = newValue ?? '';

			if (inlineEditMode && inlineEditableFields[field]) {
				const input = cell.querySelector('input');
				if (input) {
					input.value = formatInlineValueForInput(field, newValue ?? '');
					input.dataset.originalValue = newValue ?? '';
				}
			} else {
				cell.textContent = formatInlineDisplay(field, newValue ?? '');
			}
			if (cell.dataset.originalHtml !== undefined) {
				cell.dataset.originalHtml = formatInlineDisplay(field, newValue ?? '');
			}

			cell.classList.add('bg-yellow-100');
			setTimeout(() => cell.classList.remove('bg-yellow-100'), 1500);
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

	// Función helper para obtener el telar de una fila (con cache)
	function getRowTelar(row) {
		if (!rowCache.has(row)) {
			const telarCell = row.querySelector('[data-column="NoTelarId"]');
			const salonCell = row.querySelector('[data-column="SalonTejidoId"]');
			const cambioHiloCell = row.querySelector('[data-column="CambioHilo"]');
			rowCache.set(row, {
				telar: telarCell ? telarCell.textContent.trim() : null,
				salon: salonCell ? salonCell.textContent.trim() : null,
				cambioHilo: cambioHiloCell ? cambioHiloCell.textContent.trim() : null
			});
		}
		return rowCache.get(row).telar;
	}

	// Función helper para obtener el salón de una fila (con cache)
	function getRowSalon(row) {
		if (!rowCache.has(row)) {
			getRowTelar(row); // Inicializa cache
		}
		return rowCache.get(row).salon;
	}

	// Función helper para obtener el cambio de hilo de una fila (con cache)
	function getRowCambioHilo(row) {
		if (!rowCache.has(row)) {
			getRowTelar(row); // Inicializa cache
		}
		return rowCache.get(row).cambioHilo;
	}

	// Limpiar cache cuando sea necesario
	function clearRowCache() {
		rowCache.clear();
	}

	function getRowsByTelar(telarId) {
		return allRows.filter(row => getRowTelar(row) === telarId);
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

			// Limpiar cache al activar drag and drop
			clearRowCache();

			const rows = allRows.length > 0 ? allRows : $$('.selectable-row', tb);
			for (let i = 0; i < rows.length; i++) {
				const row = rows[i];
				const enProceso = isRowEnProceso(row);
				row.draggable = !enProceso;
				row.onclick = null;

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
			}

			// Agregar listeners al tbody para permitir drops en espacios vacíos
			tb.addEventListener('dragover', handleDragOver);
			tb.addEventListener('drop', handleDrop);

			console.log('✅ Modo drag and drop activado. Filas:', rows.length);
			showToast('Modo arrastrar activado<br>Arrastra las filas para reorganizarlas', 'info');
		} else {
			// Desactivar modo drag and drop
			btn.classList.remove('bg-gray-400', 'hover:bg-gray-500', 'ring-2', 'ring-gray-300');
			btn.classList.add('bg-black', 'hover:bg-gray-800', 'focus:ring-gray-500');
			btn.title = 'Activar/Desactivar arrastrar filas';

			// IMPORTANTE: Primero actualizar allRows con el orden actual del DOM
			allRows = Array.from(tb.querySelectorAll('.selectable-row'));
			clearRowCache(); // Limpiar cache al desactivar

			const rows = allRows;
			for (let i = 0; i < rows.length; i++) {
				const row = rows[i];
				const realIndex = i;

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
				row.onclick = () => selectRow(row, realIndex);
			}

			// Remover listeners del tbody
			tb.removeEventListener('dragover', handleDragOver);
			tb.removeEventListener('drop', handleDrop);

			console.log('✅ Modo drag and drop desactivado');
			showToast('Modo arrastrar desactivado', 'info');
		}
	}

	// Manejador de inicio de drag - MEJORADO
	function handleDragStart(e) {
		console.log('🚀 handleDragStart iniciado');

		// Validación: no permitir drag si está en proceso
		if (isRowEnProceso(this)) {
			e.preventDefault();
			showToast('No se puede mover un registro en proceso', 'error');
			return false;
		}

		draggedRow = this;
		draggedRowTelar = getRowTelar(this);
		draggedRowSalon = getRowSalon(this);
		draggedRowCambioHilo = getRowCambioHilo(this);
		dragStartPosition = this.rowIndex;

		console.log('📦 Datos del registro arrastrado:', {
			id: draggedRow.getAttribute('data-id'),
			telar: draggedRowTelar,
			salon: draggedRowSalon,
			posicion: dragStartPosition
		});

		this.classList.add('dragging');
		this.style.opacity = '0.4';

		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/html', this.innerHTML);
		e.dataTransfer.setData('text/plain', draggedRow.getAttribute('data-id'));

		// Limpiar cache de otras filas para forzar recálculo si es necesario
		lastDragOverTime = 0;
	}

	// Manejador de drag over (feedback visual Y reordenamiento temporal) - MEJORADO
	function handleDragOver(e) {
		e.preventDefault();
		e.stopPropagation();

		// Si this es el tbody, buscar la fila más cercana
		let targetRow = this;
		if (this.tagName === 'TBODY') {
			const rows = Array.from(this.querySelectorAll('.selectable-row'));
			let closestRow = null;
			let closestDistance = Infinity;

			for (const row of rows) {
				if (row === draggedRow) continue;
				const rect = row.getBoundingClientRect();
				const distance = Math.abs(e.clientY - (rect.top + rect.height / 2));
				if (distance < closestDistance) {
					closestDistance = distance;
					closestRow = row;
				}
			}

			if (closestRow) {
				targetRow = closestRow;
			} else {
				return false;
			}
		}

		if (targetRow === draggedRow) return false;

		// Throttling para mejorar rendimiento (máximo 60fps)
		const now = performance.now();
		if (now - lastDragOverTime < 16) {
			return false;
		}
		lastDragOverTime = now;

		const targetTelar = getRowTelar(targetRow);

		// VALIDACIÓN: No permitir colocar antes de un registro en proceso
		if (draggedRowTelar !== targetTelar) {
			// Si es telar diferente, validar posición
			const tb = tbodyEl();
			if (tb) {
				const allRowsInDOM = Array.from(tb.querySelectorAll('.selectable-row'));
				const draggedRowIndex = allRowsInDOM.indexOf(draggedRow);

				if (draggedRowIndex !== -1) {
					// Verificar si hay registros en proceso antes de la posición objetivo
					const targetRowsInDOM = allRowsInDOM.filter(row => getRowTelar(row) === targetTelar);
					let posicionObjetivo = 0;
					for (let i = 0; i < draggedRowIndex; i++) {
						if (getRowTelar(allRowsInDOM[i]) === targetTelar) {
							posicionObjetivo++;
						}
					}

					// Encontrar último registro en proceso del telar destino
					let ultimoEnProcesoIndex = -1;
					for (let i = 0; i < targetRowsInDOM.length; i++) {
						if (isRowEnProceso(targetRowsInDOM[i])) {
							ultimoEnProcesoIndex = i;
						}
					}

					// Si se intenta colocar antes de un registro en proceso, mostrar error
					if (ultimoEnProcesoIndex !== -1 && posicionObjetivo <= ultimoEnProcesoIndex) {
						e.dataTransfer.dropEffect = 'none';
						if (targetRow.classList && !targetRow.classList.contains('drop-not-allowed')) {
							targetRow.classList.add('drop-not-allowed');
							targetRow.classList.remove('drag-over', 'drag-over-warning');
						}
						return false;
					}
				}
			}
		}

		// Visual feedback: mostrar advertencia si es telar diferente
		if (draggedRowTelar !== targetTelar) {
			e.dataTransfer.dropEffect = 'copy';
			if (targetRow.classList && !targetRow.classList.contains('drag-over-warning')) {
				targetRow.classList.add('drag-over-warning');
				targetRow.classList.remove('drag-over', 'drop-not-allowed');
			}
		} else {
			e.dataTransfer.dropEffect = 'move';
			if (targetRow.classList && !targetRow.classList.contains('drag-over')) {
				targetRow.classList.add('drag-over');
				targetRow.classList.remove('drag-over-warning', 'drop-not-allowed');
			}
		}

		// Reordenar visualmente en el DOM (solo si es necesario y si no hay error)
		if (!targetRow.classList.contains('drop-not-allowed')) {
			const tbody = targetRow.parentNode || tbodyEl();
			if (tbody) {
				const afterElement = getDragAfterElement(tbody, e.clientY);

				if (afterElement == null) {
					if (draggedRow.nextSibling !== null) {
						tbody.appendChild(draggedRow);
					}
				} else if (draggedRow.nextSibling !== afterElement) {
					tbody.insertBefore(draggedRow, afterElement);
				}
			}
		}

		return false;
	}

	// Helper para determinar después de qué elemento insertar - OPTIMIZADO
	function getDragAfterElement(container, y) {
		// Usar allRows filtradas en lugar de querySelectorAll para mejor rendimiento
		const draggableElements = allRows.filter(row => !row.classList.contains('dragging'));

		if (draggableElements.length === 0) return null;

		let closest = { offset: Number.NEGATIVE_INFINITY, element: null };

		for (let i = 0; i < draggableElements.length; i++) {
			const child = draggableElements[i];
			const box = child.getBoundingClientRect();
			const offset = y - box.top - box.height / 2;

			if (offset < 0 && offset > closest.offset) {
				closest = { offset: offset, element: child };
			}
		}

		return closest.element;
	}

	// Función helper para calcular posición objetivo y validar contra registros en proceso
	function calcularPosicionObjetivo(targetTelar) {
		const tb = tbodyEl();
		if (!tb) return 0;

		// Obtener todas las filas del telar destino del DOM (en orden visual actual)
		const allRowsInDOM = Array.from(tb.querySelectorAll('.selectable-row'));

		// Obtener las filas del telar destino ANTES de incluir el draggedRow
		// Esto es importante porque el draggedRow puede haber sido movido visualmente al telar destino
		const targetRowsOriginal = allRowsInDOM.filter(row => {
			const rowTelar = getRowTelar(row);
			return rowTelar === targetTelar && row !== draggedRow;
		});

		// Encontrar la posición del draggedRow en el DOM (después del reordenamiento visual)
		const draggedRowIndex = allRowsInDOM.indexOf(draggedRow);

		// Calcular posición objetivo basada en dónde está el draggedRow en el DOM
		let targetPosition = targetRowsOriginal.length; // Por defecto: al final

		if (draggedRowIndex !== -1) {
			// Contar cuántas filas del telar destino (excluyendo el draggedRow) están antes del draggedRow en el DOM
			let posicion = 0;
			for (let i = 0; i < draggedRowIndex; i++) {
				const row = allRowsInDOM[i];
				if (row !== draggedRow && getRowTelar(row) === targetTelar) {
					posicion++;
				}
			}
			targetPosition = posicion;
		}

		// VALIDACIÓN: No puede colocarse antes de un registro en proceso
		// Encontrar el último registro en proceso del telar destino (sin incluir el draggedRow)
		let ultimoEnProcesoIndex = -1;
		for (let i = 0; i < targetRowsOriginal.length; i++) {
			if (isRowEnProceso(targetRowsOriginal[i])) {
				ultimoEnProcesoIndex = i;
			}
		}

		// Si hay registros en proceso, la posición mínima debe ser después del último
		if (ultimoEnProcesoIndex !== -1) {
			const posicionMinima = ultimoEnProcesoIndex + 1;
			if (targetPosition < posicionMinima) {
				targetPosition = posicionMinima;
			}
		}

		return targetPosition;
	}

	// Manejador de drop (aquí se hace el movimiento real) - COMPLETAMENTE REESCRITO
	async function handleDrop(e) {
		e.stopPropagation();
		e.preventDefault();

		console.log('🎯 handleDrop iniciado');
		console.log('Evento:', e);
		console.log('this:', this);
		console.log('e.target:', e.target);

		if (!draggedRow) {
			console.error('❌ handleDrop: draggedRow es null');
			showToast('Error: No se encontró el registro arrastrado', 'error');
			return false;
		}

		// Obtener el ID del registro desde dataTransfer o desde draggedRow
		const registroId = e.dataTransfer.getData('text/plain') || draggedRow.getAttribute('data-id');

		if (!registroId) {
			console.error('❌ handleDrop: No se pudo obtener el ID del registro');
			showToast('Error: No se pudo obtener el ID del registro', 'error');
			return false;
		}

		// ESTRATEGIA MEJORADA: Determinar telar destino basándose en la posición visual del draggedRow
		// Después del reordenamiento visual durante drag over, el draggedRow ya está en su nueva posición
		const tb = tbodyEl();
		if (!tb) {
			console.error('❌ handleDrop: No se encontró tbody');
			return false;
		}

		// Obtener todas las filas en el orden actual del DOM
		const allRowsInDOM = Array.from(tb.querySelectorAll('.selectable-row'));
		const draggedIndex = allRowsInDOM.indexOf(draggedRow);

		console.log('📍 Posición del draggedRow en DOM:', draggedIndex, 'de', allRowsInDOM.length);

		if (draggedIndex === -1) {
			console.error('❌ handleDrop: draggedRow no encontrado en DOM');
			Swal.fire({
				icon: 'error',
				title: 'Error',
				text: 'No se pudo encontrar el registro en la tabla',
				confirmButtonColor: '#dc2626'
			});
			return false;
		}

		// Determinar el telar destino basándose en las filas adyacentes al draggedRow
		let targetTelar = null;
		let targetSalon = null;
		let targetRow = null;

		// Estrategia 1: Buscar la fila más cercana al punto donde se soltó (e.clientY)
		if (e.clientY) {
			let closestRow = null;
			let closestDistance = Infinity;

			for (const row of allRowsInDOM) {
				if (row === draggedRow) continue;
				const rect = row.getBoundingClientRect();
				const distance = Math.abs(e.clientY - (rect.top + rect.height / 2));
				if (distance < closestDistance) {
					closestDistance = distance;
					closestRow = row;
				}
			}

			if (closestRow) {
				targetRow = closestRow;
				targetTelar = getRowTelar(closestRow);
				targetSalon = getRowSalon(closestRow);
				console.log('✅ Telar destino detectado por fila más cercana al punto de drop:', targetTelar);
			}
		}

		// Estrategia 2: Si no se encontró, usar las filas adyacentes al draggedRow
		if (!targetTelar) {
			// Buscar fila anterior (si existe)
			if (draggedIndex > 0) {
				const prevRow = allRowsInDOM[draggedIndex - 1];
				targetRow = prevRow;
				targetTelar = getRowTelar(prevRow);
				targetSalon = getRowSalon(prevRow);
				console.log('✅ Telar destino detectado por fila anterior:', targetTelar);
			}
			// Si no hay anterior, buscar fila siguiente
			else if (draggedIndex < allRowsInDOM.length - 1) {
				const nextRow = allRowsInDOM[draggedIndex + 1];
				targetRow = nextRow;
				targetTelar = getRowTelar(nextRow);
				targetSalon = getRowSalon(nextRow);
				console.log('✅ Telar destino detectado por fila siguiente:', targetTelar);
			}
		}

		// Estrategia 3: Si aún no se encontró, buscar en un radio alrededor del draggedRow
		if (!targetTelar) {
			const radius = 3; // Buscar 3 filas arriba y abajo
			for (let i = Math.max(0, draggedIndex - radius); i <= Math.min(allRowsInDOM.length - 1, draggedIndex + radius); i++) {
				if (i === draggedIndex) continue;
				const row = allRowsInDOM[i];
				const rowTelar = getRowTelar(row);
				if (rowTelar && rowTelar !== draggedRowTelar) {
					targetRow = row;
					targetTelar = rowTelar;
					targetSalon = getRowSalon(row);
					console.log('✅ Telar destino detectado por búsqueda en radio:', targetTelar);
					break;
				}
			}
		}

		if (!targetTelar) {
			console.error('❌ handleDrop: No se pudo determinar el telar destino');
			Swal.fire({
				icon: 'error',
				title: 'Error',
				text: 'No se pudo determinar el telar destino. Por favor, intenta soltar sobre una fila específica.',
				confirmButtonColor: '#dc2626'
			});
			return false;
		}

		console.log('📍 Información del drop:', {
			registroId,
			draggedRowTelar,
			targetTelar,
			draggedRowSalon,
			targetSalon,
			targetRowId: targetRow ? targetRow.getAttribute('data-id') : 'N/A',
			draggedIndex,
			totalRows: allRowsInDOM.length
		});

		// Validación adicional: Si el telar destino es igual al origen, verificar si realmente es el mismo
		// o si el draggedRow se movió visualmente a otro telar
		if (draggedRowTelar === targetTelar) {
			// Verificar si hay filas de otros telares cerca del draggedRow
			const nearbyRows = [];
			const checkRadius = 5;
			for (let i = Math.max(0, draggedIndex - checkRadius); i <= Math.min(allRowsInDOM.length - 1, draggedIndex + checkRadius); i++) {
				if (i === draggedIndex) continue;
				const row = allRowsInDOM[i];
				const rowTelar = getRowTelar(row);
				if (rowTelar && rowTelar !== draggedRowTelar) {
					nearbyRows.push({ row, telar: rowTelar, index: i });
				}
			}

			// Si hay filas de otros telares cerca, usar el telar más cercano
			if (nearbyRows.length > 0) {
				// Encontrar la fila más cercana al draggedRow
				const closestNearby = nearbyRows.reduce((closest, current) => {
					const currentDist = Math.abs(current.index - draggedIndex);
					const closestDist = Math.abs(closest.index - draggedIndex);
					return currentDist < closestDist ? current : closest;
				});

				targetTelar = closestNearby.telar;
				targetRow = closestNearby.row;
				targetSalon = getRowSalon(closestNearby.row);
				console.log('🔄 Telar corregido basándose en filas cercanas:', draggedRowTelar, '->', targetTelar);
			}
		}

		// CASO 1: Mismo telar → Movimiento normal sin validación
		if (draggedRowTelar === targetTelar) {
			console.log('📌 Mismo telar - procesando movimiento normal');
			await procesarMovimientoMismoTelar(registroId);
			return false;
		}

		// CASO 2: Telar diferente → SIEMPRE mostrar alerta y validación
		console.log('🔄 Cambio de telar detectado:', draggedRowTelar, '->', targetTelar);

		// Calcular posición objetivo basada en el DOM visual
		let targetPosition = calcularPosicionObjetivo(targetTelar);
		console.log('📍 Posición objetivo calculada (inicial):', targetPosition);

		// Validación adicional: ajustar automáticamente para que nunca quede antes de un registro en proceso
		const targetRows = getRowsByTelar(targetTelar);
		if (targetRows.length) {
			let minAllowedPosition = 0;
			for (let i = 0; i < targetRows.length; i++) {
				if (isRowEnProceso(targetRows[i])) {
					minAllowedPosition = i + 1; // debe quedar después del último en proceso
				}
			}

			if (targetPosition < minAllowedPosition) {
				console.log('⚠️ targetPosition ajustado por registro en proceso:', targetPosition, '=>', minAllowedPosition);
				targetPosition = minAllowedPosition;
				showToast('Se colocó después del registro en proceso del telar destino', 'info');
			}
		}

		// No permitir posiciones negativas
		targetPosition = Math.max(0, targetPosition);
		console.log('📍 Posición objetivo final:', targetPosition);

		// Procesar movimiento a otro telar (siempre mostrará alerta)
		console.log('🚀 Iniciando procesamiento de cambio de telar...');
		await procesarMovimientoOtroTelar(registroId, targetSalon, targetTelar, targetPosition);
		return false;
	}

	// Función para procesar movimiento dentro del mismo telar
	async function procesarMovimientoMismoTelar(registroId) {
		const tb = tbodyEl();
		if (!tb) return;

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

		if (nuevaPosicion === -1) {
			showToast('Error al calcular la nueva posición', 'error');
			return;
		}

		// Enviar al backend
		showLoading();
		try {
			const response = await fetch(`/planeacion/programa-tejido/${registroId}/prioridad/mover`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
				},
				body: JSON.stringify({
					new_position: nuevaPosicion
				})
			});

			const data = await response.json();
			hideLoading();

			if (data.success) {
				updateTableAfterDragDrop(data.detalles, registroId);
				showToast(` Prioridad actualizada<br>${data.cascaded_records || 0} registro(s) recalculado(s)`, 'success');

				// Mantener seleccionado el registro movido
				const movedRow = document.querySelector(`.selectable-row[data-id="${registroId}"]`);
				if (movedRow) {
					const realIndex = allRows.indexOf(movedRow);
					if (realIndex !== -1) {
						setTimeout(() => {
							selectRow(movedRow, realIndex);
							movedRow.scrollIntoView({
								behavior: 'smooth',
								block: 'center'
							});
						}, 300);
					}
				}
			} else {
				showToast(data.message || 'No se pudo actualizar la prioridad', 'error');
			}
		} catch (error) {
			hideLoading();
			showToast('Ocurrió un error al procesar la solicitud', 'error');
		}
	}

	// Función para procesar movimiento a otro telar (con validación)
	async function procesarMovimientoOtroTelar(registroId, nuevoSalon, nuevoTelar, targetPosition) {
		console.log('procesarMovimientoOtroTelar iniciado:', {
			registroId,
			nuevoSalon,
			nuevoTelar,
			targetPosition
		});

		showLoading();

		try {
			const verificacionResponse = await fetch(`/planeacion/programa-tejido/${registroId}/verificar-cambio-telar`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
				},
				body: JSON.stringify({
					nuevo_salon: nuevoSalon,
					nuevo_telar: nuevoTelar
				})
			});

			if (!verificacionResponse.ok) {
				hideLoading();
				const errorText = await verificacionResponse.text();
				console.error('Error en verificación:', errorText);
				Swal.fire({
					icon: 'error',
					title: 'Error de validación',
					text: 'No se pudo validar el cambio de telar. Por favor, intenta de nuevo.',
					confirmButtonColor: '#dc2626'
				});
				return;
			}

			const verificacion = await verificacionResponse.json();
			console.log('Verificación recibida:', verificacion);
			hideLoading();

			if (!verificacion.puede_mover) {
				Swal.fire({
					icon: 'error',
					title: 'No se puede cambiar de telar',
					html: `
					<div class="text-left">
						<p class="mb-3">${verificacion.mensaje}</p>
						<div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm">
							<p class="font-semibold text-red-800 mb-2">Detalles:</p>
							<p><span class="font-medium">Clave Modelo:</span> ${verificacion.clave_modelo || 'N/A'}</p>
							<p><span class="font-medium">Telar Destino:</span> ${verificacion.telar_destino || nuevoTelar} (${verificacion.salon_destino || nuevoSalon})</p>
						</div>
					</div>
				`,
					confirmButtonText: 'Entendido',
					confirmButtonColor: '#dc2626',
					width: '500px'
				});
				return;
			}

			// SIEMPRE mostrar alerta con detalles del cambio
			console.log('Mostrando alerta de confirmación...');

			// Construir tabla de cambios
			let cambiosHTML = '';
			if (verificacion.cambios && Array.isArray(verificacion.cambios) && verificacion.cambios.length > 0) {
				cambiosHTML = `
					<div class="mt-4 max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
						<table class="min-w-full text-sm">
							<thead class="bg-gray-100 sticky top-0">
								<tr>
									<th class="px-3 py-2 text-left font-semibold text-gray-700">Campo</th>
									<th class="px-3 py-2 text-left font-semibold text-gray-700">Valor Actual</th>
									<th class="px-3 py-2 text-left font-semibold text-gray-700">Valor Nuevo</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-200">
								${verificacion.cambios.map(cambio => `
									<tr class="hover:bg-gray-50">
										<td class="px-3 py-2 font-medium text-gray-900">${cambio.campo || 'N/A'}</td>
										<td class="px-3 py-2 text-gray-600">${cambio.actual || 'N/A'}</td>
										<td class="px-3 py-2 text-blue-600 font-semibold">${cambio.nuevo || 'N/A'}</td>
									</tr>
								`).join('')}
							</tbody>
						</table>
					</div>
				`;
			} else {
				// Si no hay cambios específicos, mostrar información básica
				cambiosHTML = `
					<div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm">
						<p class="text-yellow-800 font-medium">Cambios que se aplicarán:</p>
						<ul class="list-disc list-inside mt-2 space-y-1 text-yellow-700">
							<li>El registro se moverá al telar ${nuevoTelar} (Salón ${nuevoSalon})</li>
							<li>Se recalcularán las fechas de inicio y fin</li>
							<li>Se actualizarán los valores de Eficiencia y Velocidad según el nuevo telar</li>
							<li>Se resetearán los campos "Último" y "Cambio Hilo"</li>
						</ul>
					</div>
				`;
			}

			const mensajeAlerta = verificacion.mensaje || `Se moverá el registro del telar ${draggedRowTelar} al telar ${nuevoTelar}`;
			const claveModelo = verificacion.clave_modelo || 'N/A';
			const telarOrigen = verificacion.telar_origen || draggedRowTelar;
			const salonOrigen = verificacion.salon_origen || draggedRowSalon;
			const telarDestino = verificacion.telar_destino || nuevoTelar;
			const salonDestino = verificacion.salon_destino || nuevoSalon;

			console.log('Datos de la alerta:', {
				mensajeAlerta,
				claveModelo,
				telarOrigen,
				salonOrigen,
				telarDestino,
				salonDestino,
				tieneCambios: verificacion.cambios && verificacion.cambios.length > 0
			});

			const confirmacion = await Swal.fire({
				icon: 'warning',
				title: 'Cambio de Telar/Salón',
				html: `
				<div class="text-left">
					<p class="mb-3 text-gray-700 font-medium">${mensajeAlerta}</p>
					<div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm space-y-1 mb-3">
						<p><span class="font-semibold text-blue-900">Clave Modelo:</span> <span class="text-blue-700">${claveModelo}</span></p>
						<p><span class="font-semibold text-blue-900">De:</span> <span class="text-blue-700">Telar ${telarOrigen} (Salón ${salonOrigen})</span></p>
						<p><span class="font-semibold text-blue-900">A:</span> <span class="text-blue-700">Telar ${telarDestino} (Salón ${salonDestino})</span></p>
					</div>
					${cambiosHTML}
					<p class="mt-4 text-sm text-red-600 font-semibold">Esta acción moverá el registro y aplicará los cambios mostrados arriba.</p>
				</div>
			`,
				showCancelButton: true,
				confirmButtonText: 'Sí, cambiar de telar',
				cancelButtonText: 'Cancelar',
				confirmButtonColor: '#3b82f6',
				cancelButtonColor: '#6b7280',
				width: '700px',
				customClass: {
					htmlContainer: 'text-left'
				},
				allowOutsideClick: false,
				allowEscapeKey: true
			});

			console.log('Confirmación recibida:', confirmacion.isConfirmed);

			if (!confirmacion.isConfirmed) {
				showToast('Operación cancelada', 'info');
				// Restaurar posición visual del elemento arrastrado
				const tb = tbodyEl();
				if (tb && draggedRow) {
					// Buscar la posición original del registro arrastrado
					const originalRows = allRows.filter(row => getRowTelar(row) === draggedRowTelar);
					if (originalRows.length > 0) {
						// Intentar restaurar la posición original
						const originalIndex = originalRows.indexOf(draggedRow);
						if (originalIndex !== -1 && originalIndex < originalRows.length - 1) {
							tb.insertBefore(draggedRow, originalRows[originalIndex + 1]);
						} else {
							// Si es el último, agregarlo al final
							const lastRow = originalRows[originalRows.length - 1];
							if (lastRow && lastRow.nextSibling) {
								tb.insertBefore(draggedRow, lastRow.nextSibling);
							} else {
								tb.appendChild(draggedRow);
							}
						}
					}
				}
				return;
			}

			showLoading();
			const cambioResponse = await fetch(`/planeacion/programa-tejido/${registroId}/cambiar-telar`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
				},
				body: JSON.stringify({
					nuevo_salon: nuevoSalon,
					nuevo_telar: nuevoTelar,
					target_position: targetPosition
				})
			});

			if (!cambioResponse.ok) {
				hideLoading();
				showToast('No se pudo cambiar de telar', 'error');
				return;
			}

			const cambio = await cambioResponse.json();
			console.log('Cambio realizado:', cambio);
			hideLoading();

			if (!cambio.success) {
				Swal.fire({
					icon: 'error',
					title: 'Error al cambiar de telar',
					text: cambio.message || 'No se pudo cambiar de telar',
					confirmButtonColor: '#dc2626'
				});
				// Restaurar posición visual
				const tb = tbodyEl();
				if (tb && draggedRow) {
					const originalRows = allRows.filter(row => getRowTelar(row) === draggedRowTelar);
					if (originalRows.length > 0) {
						const originalIndex = originalRows.indexOf(draggedRow);
						if (originalIndex !== -1 && originalIndex < originalRows.length - 1) {
							tb.insertBefore(draggedRow, originalRows[originalIndex + 1]);
						} else if (originalRows.length > 0) {
							const lastRow = originalRows[originalRows.length - 1];
							if (lastRow && lastRow.nextSibling) {
								tb.insertBefore(draggedRow, lastRow.nextSibling);
							} else {
								tb.appendChild(draggedRow);
							}
						}
					}
				}
				return;
			}

			// Éxito: mostrar mensaje y recargar página
			Swal.fire({
				icon: 'success',
				title: '¡Cambio realizado!',
				text: cambio.message || 'Telar actualizado correctamente',
				confirmButtonColor: '#3b82f6',
				timer: 2000,
				showConfirmButton: false
			});

			sessionStorage.setItem('priorityChangeMessage', cambio.message || 'Telar actualizado correctamente');
			sessionStorage.setItem('priorityChangeType', 'success');
			if (cambio.registro_id) {
				sessionStorage.setItem('scrollToRegistroId', cambio.registro_id);
				sessionStorage.setItem('selectRegistroId', cambio.registro_id);
			}

			// Recargar página después de un breve delay para mostrar el mensaje
			setTimeout(() => {
				window.location.href = '/planeacion/programa-tejido';
			}, 500);
		} catch (error) {
			console.error('Error en procesarMovimientoOtroTelar:', error);
			hideLoading();
			Swal.fire({
				icon: 'error',
				title: 'Error',
				text: 'Ocurrió un error al procesar el cambio de telar: ' + (error.message || 'Error desconocido'),
				confirmButtonColor: '#dc2626'
			});
			// Restaurar posición visual en caso de error
			const tb = tbodyEl();
			if (tb && draggedRow) {
				const originalRows = allRows.filter(row => getRowTelar(row) === draggedRowTelar);
				if (originalRows.length > 0) {
					const originalIndex = originalRows.indexOf(draggedRow);
					if (originalIndex !== -1 && originalIndex < originalRows.length - 1) {
						tb.insertBefore(draggedRow, originalRows[originalIndex + 1]);
					} else if (originalRows.length > 0) {
						const lastRow = originalRows[originalRows.length - 1];
						if (lastRow && lastRow.nextSibling) {
							tb.insertBefore(draggedRow, lastRow.nextSibling);
						} else {
							tb.appendChild(draggedRow);
						}
					}
				}
			}
		}
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
					return date.toLocaleDateString('es-ES', {
							day: '2-digit',
							month: '2-digit',
							year: 'numeric'
						}) + ' ' +
						date.toLocaleTimeString('es-ES', {
							hour: '2-digit',
							minute: '2-digit'
						});
				}
			} catch (e) {}
			return '';
		};

		// Obtener el telar de los registros afectados (todos deberían ser del mismo telar)
		const telarAfectado = detalles.length > 0 ? detalles[0].NoTelar : null;

		// PASO 1: Limpiar TODOS los "Ultimo" y "EnProceso" del telar afectado - OPTIMIZADO
		if (telarAfectado) {
			const rowsSameTelar = allRows.filter(row => getRowTelar(row) === telarAfectado);
			for (let i = 0; i < rowsSameTelar.length; i++) {
				const row = rowsSameTelar[i];
				// Limpiar Ultimo
				const ultimoCell = row.querySelector('[data-column="Ultimo"]');
				if (ultimoCell) ultimoCell.textContent = '';

				// Limpiar EnProceso
				const enProcesoCell = row.querySelector('[data-column="EnProceso"]');
				if (enProcesoCell) {
					const checkbox = enProcesoCell.querySelector('input[type="checkbox"]');
					if (checkbox) checkbox.checked = false;
				}
			}
		}

		// PASO 2: Actualizar cada registro afectado con los nuevos valores - OPTIMIZADO
		// Crear mapa de IDs para acceso rápido
		const rowMap = new Map();
		allRows.forEach(row => {
			const id = row.getAttribute('data-id');
			if (id) rowMap.set(id, row);
		});

		for (let i = 0; i < detalles.length; i++) {
			const detalle = detalles[i];
			const row = rowMap.get(String(detalle.Id));
			if (!row) continue;

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

			// Actualizar EnProceso (buscar tanto 'EnProceso' como 'EnProceso_nuevo')
			const enProcesoNuevo = detalle.EnProceso_nuevo !== undefined ? detalle.EnProceso_nuevo : detalle.EnProceso;
			if (enProcesoNuevo !== undefined) {
				const enProcesoCell = row.querySelector('[data-column="EnProceso"]');
				if (enProcesoCell) {
					const checkbox = enProcesoCell.querySelector('input[type="checkbox"]');
					if (checkbox) {
						checkbox.checked = (enProcesoNuevo == 1 || enProcesoNuevo === true);
					}
				}
			}

			// Actualizar Ultimo (buscar tanto 'Ultimo' como 'Ultimo_nuevo')
			const ultimoNuevo = detalle.Ultimo_nuevo !== undefined ? detalle.Ultimo_nuevo : detalle.Ultimo;
			if (ultimoNuevo !== undefined) {
				const ultimoCell = row.querySelector('[data-column="Ultimo"]');
				if (ultimoCell) {
					const valor = ultimoNuevo === '1' || ultimoNuevo === 'UL' || ultimoNuevo === 1 ? '1' : '';
					ultimoCell.textContent = valor;

					// Animación solo si se pone en "1" (para destacar el nuevo último)
					if (valor === '1') {
						ultimoCell.classList.add('bg-yellow-100');
						setTimeout(() => ultimoCell.classList.remove('bg-yellow-100'), 1500);
					}
				}
			}

			// Actualizar CambioHilo (buscar tanto 'CambioHilo' como 'CambioHilo_nuevo')
			const cambioHiloNuevo = detalle.CambioHilo_nuevo !== undefined ? detalle.CambioHilo_nuevo : detalle.CambioHilo;
			if (cambioHiloNuevo !== undefined) {
				const cambioHiloCell = row.querySelector('[data-column="CambioHilo"]');
				if (cambioHiloCell) {
					cambioHiloCell.textContent = cambioHiloNuevo;
					cambioHiloCell.classList.add('bg-yellow-100');
					setTimeout(() => cambioHiloCell.classList.remove('bg-yellow-100'), 1500);
				}
			}
		}

		// Actualizar allRows y limpiar cache
		const tb = tbodyEl();
		if (tb) {
			allRows = Array.from(tb.querySelectorAll('.selectable-row'));
			clearRowCache(); // Limpiar cache después de actualizar
		}
	}

	// Manejador de fin de drag - OPTIMIZADO
	function handleDragEnd(e) {
		this.classList.remove('dragging');
		this.style.opacity = '';

		// Limpiar estilos visuales de todas las filas (optimizado)
		const rows = allRows.length > 0 ? allRows : $$('.selectable-row');
		for (let i = 0; i < rows.length; i++) {
			const row = rows[i];
			row.classList.remove('drag-over', 'drag-over-warning', 'drop-not-allowed');
		}

		// Limpiar variables
		draggedRow = null;
		draggedRowTelar = null;
		draggedRowSalon = null;
		draggedRowCambioHilo = null;
		dragStartPosition = null;
		lastDragOverTime = 0;
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
			if (inlineEditMode) applyInlineModeToRows();
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
		const btnInlineEdit = document.getElementById('btnInlineEdit');
		if (btnInlineEdit) {
			// Remover onclick del HTML si existe para evitar doble ejecución
			btnInlineEdit.removeAttribute('onclick');
			btnInlineEdit.addEventListener('click', toggleInlineEditMode);
			console.log('✅ Event listener agregado a btnInlineEdit');
		} else {
			console.warn('⚠️ btnInlineEdit no encontrado en DOMContentLoaded');
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
	window.applyTableFilters = function(values) {
		try {
			const tb = tbodyEl();
			if (!tb) return;
			// Base: filas originales
			const rows = allRows.slice();
			const entries = Object.entries(values || {});
			let filtered = rows;
			if (entries.length) {
				filtered = rows.filter(tr => {
					return entries.every(([col, val]) => {
						const cell = tr.querySelector(`[data-column="${CSS.escape(col)}"]`);
						if (!cell) return false;
						return (cell.textContent || '').toLowerCase().includes(String(val).toLowerCase());
					});
				});
			}
			// Render con fragmento para mejor rendimiento
			tb.innerHTML = '';
			const fragment = document.createDocumentFragment();
			for (let i = 0; i < filtered.length; i++) {
				const r = filtered[i];
				const realIndex = allRows.indexOf(r);
				if (realIndex === -1) continue;

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
				fragment.appendChild(r);
			}
			tb.appendChild(fragment);

		// IMPORTANTE: Actualizar allRows después de manipular el DOM
		allRows = Array.from(tb.querySelectorAll('.selectable-row'));
		clearRowCache(); // Limpiar cache después de aplicar filtros
		} catch (e) {}
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
			const label = date.toLocaleDateString('es-ES', {
				month: 'long',
				year: 'numeric'
			});
			meses.push({
				value: `${year}-${month}`,
				label: label.charAt(0).toUpperCase() + label.slice(1)
			});
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
