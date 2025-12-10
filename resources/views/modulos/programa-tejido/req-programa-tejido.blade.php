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

{{-- Menú contextual (click derecho) --}}
<div id="contextMenu" class="hidden fixed bg-white border border-gray-300 rounded-lg shadow-lg z-50 py-1 min-w-[180px]">
	<button id="contextMenuCrear" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
		<i class="fas fa-plus-circle text-blue-500"></i>
		<span>Crear </span>
	</button>
	<button id="contextMenuEditar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
		<i class="fas fa-pen text-yellow-500"></i>
		<span>Editar fila</span>
	</button>
</div>

<style>
	/* Columnas fijadas */
	.pinned-column {
		position: sticky !important;
		background-color: #f3f8ff !important;
		color: #000000 !important;
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

	/* Estilos para menú contextual */
	#contextMenu {
		animation: contextMenuFadeIn 0.15s ease-out;
	}

	@keyframes contextMenuFadeIn {
		from {
			opacity: 0;
			transform: scale(0.95);
		}
		to {
			opacity: 1;
			transform: scale(1);
		}
	}

	#contextMenu button:active {
		background-color: #dbeafe;
	}

	#contextMenu button:disabled {
		opacity: 0.5;
		cursor: not-allowed;
	}
</style>

<script>
	@include('modulos.programa-tejido.scripts.state')

	@include('modulos.programa-tejido.scripts.filters')

	@include('modulos.programa-tejido.scripts.columns')

	@include('modulos.programa-tejido.scripts.selection')

	// ===== Edición puntual de una sola fila =====
	let inlineEditSingleRow = null;

	function editarFilaSeleccionada() {
		if (selectedRowIndex === null || selectedRowIndex === undefined || selectedRowIndex < 0) {
			showToast('Selecciona primero una fila para editar', 'info');
			return;
		}
		const row = allRows[selectedRowIndex] || $$('.selectable-row')[selectedRowIndex];
		if (!row) {
			showToast('No se pudo identificar la fila seleccionada', 'error');
			return;
		}

		// Activar modo inline solo para esa fila
		inlineEditMode = true;
		inlineEditSingleRow = row;
		document.body.classList.add('inline-edit-mode');

		// Restaurar y preparar únicamente la fila seleccionada
		restoreInlineEditing();
		makeRowInlineEditable(row);

		showToast('Edición activada solo para la fila seleccionada', 'info');
	}

	// Desactiva la edición de la fila anterior cuando se selecciona otra
	const _oldSelectRow = typeof selectRow === 'function' ? selectRow : null;
	selectRow = function(row, index) {
		// Si hay una fila en edición puntual y no es la misma, restaurar
		if (inlineEditMode && inlineEditSingleRow && inlineEditSingleRow !== row) {
			restoreInlineEditing();
			inlineEditMode = false;
			inlineEditSingleRow = null;
			document.body.classList.remove('inline-edit-mode');
		}
		if (_oldSelectRow) {
			_oldSelectRow(row, index);
		}
	};

	// También al deseleccionar (por botones o reset)
	const _oldDeselectRow = typeof deselectRow === 'function' ? deselectRow : null;
	deselectRow = function() {
		if (inlineEditMode && inlineEditSingleRow) {
			restoreInlineEditing();
			inlineEditMode = false;
			inlineEditSingleRow = null;
			document.body.classList.remove('inline-edit-mode');
		}
		if (_oldDeselectRow) {
			_oldDeselectRow();
		}
	};

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
			return;
		}

		inlineEditMode = !inlineEditMode;

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
		const rows = allRows.length ? allRows : $$('.selectable-row');
		rows.forEach(row => {
			// Limpiar estado previo si existe
			delete row.dataset.inlinePrepared;
			makeRowInlineEditable(row);
		});
	}

	function restoreInlineEditing() {
		const rows = allRows.length ? allRows : $$('.selectable-row');
		rows.forEach(row => {
			row.classList.remove('inline-edit-row', 'inline-saving');
			Object.keys(inlineEditableFields).forEach(field => {
				const cell = row.querySelector(`[data-column="${field}"]`);
				if (!cell) return;

				const savedValue = cell.dataset.value;
				const formatted = formatInlineDisplay(field, savedValue ?? '');
				cell.textContent = formatted;
				delete cell.dataset.originalHtml;
				delete cell.dataset.inlineEditing;
			});
			delete row.dataset.inlinePrepared;
		});
	}

	// Función para cargar catálogo de hilos
	async function cargarCatalogoHilos() {
		if (catalogosCache.hilos) {
			return catalogosCache.hilos;
		}

		try {
			const response = await fetch('/planeacion/catalogos/matriz-hilos/list', {
				headers: { 'Accept': 'application/json' }
			});
			const data = await response.json();
			if (data.success && data.data && Array.isArray(data.data)) {
				catalogosCache.hilos = data.data;
				return data.data;
			}
			return [];
		} catch (error) {
			console.error('Error al cargar catálogo de hilos:', error);
			return [];
		}
	}

	// Función para cargar catálogo de aplicaciones
	async function cargarCatalogoAplicaciones() {
		if (catalogosCache.aplicaciones) {
			return catalogosCache.aplicaciones;
		}

		try {
			const response = await fetch('/programa-tejido/aplicacion-id-options', {
				headers: { 'Accept': 'application/json' }
			});
			const data = await response.json();
			if (Array.isArray(data)) {
				catalogosCache.aplicaciones = data;
				return data;
			}
			return [];
		} catch (error) {
			console.error('Error al cargar catálogo de aplicaciones:', error);
			return [];
		}
	}

	// Función para cargar catálogo de calendarios (jornadas)
	async function cargarCatalogoCalendarios() {
		if (catalogosCache.calendarios) {
			return catalogosCache.calendarios;
		}

		try {
			const response = await fetch('/programa-tejido/calendario-id-options', {
				headers: { 'Accept': 'application/json' }
			});
			const data = await response.json();
			if (Array.isArray(data)) {
				catalogosCache.calendarios = data;
				return data;
			}
			return [];
		} catch (error) {
			console.error('Error al cargar catálogo de calendarios:', error);
			return [];
		}
	}

	async function makeRowInlineEditable(row) {
		if (!row) {
			console.warn('makeRowInlineEditable: row es null');
			return;
		}

		if (row.dataset.inlinePrepared === 'true') {
			return;
		}

		const rowId = row.getAttribute('data-id');
		if (!rowId) {
			return;
		}
		row.classList.add('inline-edit-row');
		row.dataset.inlinePrepared = 'true';

		// Cargar catálogos necesarios antes de crear los campos
		const hilos = await cargarCatalogoHilos();
		const aplicaciones = await cargarCatalogoAplicaciones();
		const calendarios = await cargarCatalogoCalendarios();

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
			const rawValue = cell.dataset.value ?? cell.textContent.trim();

			// Si es un select (campo con catálogo)
			if (cfg.type === 'select' && cfg.catalog) {
				const select = document.createElement('select');
				select.className = 'inline-edit-input w-full px-2 py-1 border border-blue-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500';

				// Agregar opción vacía
				const emptyOption = document.createElement('option');
				emptyOption.value = '';
				emptyOption.textContent = 'Seleccionar...';
				select.appendChild(emptyOption);

				// Cargar opciones según el catálogo
				if (cfg.catalog === 'hilos' && hilos.length > 0) {
					hilos.forEach(item => {
						const option = document.createElement('option');
						option.value = item.Hilo || item;
						option.textContent = item.Hilo + (item.Fibra ? ' - ' + item.Fibra : '');
						if (option.value === rawValue) {
							option.selected = true;
						}
						select.appendChild(option);
					});
				} else if (cfg.catalog === 'aplicaciones' && aplicaciones.length > 0) {
					aplicaciones.forEach(item => {
						const option = document.createElement('option');
						const valor = typeof item === 'string' ? item : (item.AplicacionId || item);
						option.value = valor;
						option.textContent = valor;
						if (option.value === rawValue) {
							option.selected = true;
						}
						select.appendChild(option);
					});
				} else if (cfg.catalog === 'calendarios' && calendarios.length > 0) {
					calendarios.forEach(item => {
						const option = document.createElement('option');
						const valor = typeof item === 'string' ? item : (item.CalendarioId || item);
						option.value = valor;
						option.textContent = valor;
						if (option.value === rawValue) {
							option.selected = true;
						}
						select.appendChild(option);
					});
				}

				select.dataset.originalValue = rawValue ?? '';
				select.addEventListener('change', () => handleInlineInputChange(row, field, select));
				select.addEventListener('click', (e) => e.stopPropagation());

				cell.innerHTML = '';
				cell.appendChild(select);
			} else {
				// Input normal (text, number, date, etc.)
				const input = document.createElement('input');
				input.type = cfg.type || 'text';
				if (cfg.step) input.step = cfg.step;
				if (cfg.min !== undefined) input.min = cfg.min;
				if (cfg.max !== undefined) input.max = cfg.max;
				if (cfg.maxLength) input.maxLength = cfg.maxLength;
				input.className = 'inline-edit-input w-full px-2 py-1 border border-blue-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500';

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
			}
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

		// Manejar select y input de la misma manera
		const isSelect = input.tagName === 'SELECT';
		let newValue = isSelect ? input.value : (input.value ?? '').trim();

		if (cfg.type === 'number' && newValue !== '' && !isSelect) {
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
	let originalOrderIds = []; // Guardar orden original para revertir en errores/cancel

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

	function normalizeTelarValue(value) {
		if (value === undefined || value === null) return '';
		const str = String(value).trim();
		if (!str) return '';
		const numericValue = Number(str);
		if (!Number.isNaN(numericValue)) {
			return numericValue.toString();
		}
		return str.toUpperCase();
	}

	function isSameTelar(a, b) {
		return normalizeTelarValue(a) === normalizeTelarValue(b);
	}

	// Restaurar orden original del tbody (si se canceló o hubo error)
	function restoreOriginalOrder() {
		try {
			if (!originalOrderIds || originalOrderIds.length === 0) return;
			const tb = tbodyEl();
			if (!tb) return;

			const rowMap = new Map();
			tb.querySelectorAll('.selectable-row').forEach(row => {
				const id = row.getAttribute('data-id') || '';
				rowMap.set(id, row);
			});

			const fragment = document.createDocumentFragment();
			originalOrderIds.forEach(id => {
				const row = rowMap.get(id);
				if (row) fragment.appendChild(row);
			});
			// Agregar filas no mapeadas al final
			rowMap.forEach((row, id) => {
				if (!originalOrderIds.includes(id)) {
					fragment.appendChild(row);
				}
			});

			tb.innerHTML = '';
			tb.appendChild(fragment);

			allRows = Array.from(tb.querySelectorAll('.selectable-row'));
			clearRowCache();
		} finally {
			originalOrderIds = [];
		}
	}

	function getRowsByTelar(telarId) {
		return allRows.filter(row => isSameTelar(getRowTelar(row), telarId));
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
			// Limpiar selección al activar drag and drop
			if (typeof deselectRow === 'function') {
				deselectRow();
			}

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

			showToast('Modo arrastrar desactivado', 'info');
		}
	}

	// Manejador de inicio de drag - MEJORADO
	function handleDragStart(e) {
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

		// Tomar snapshot del orden actual (para restaurar si se cancela/error)
		const tbSnapshot = tbodyEl();
		if (tbSnapshot) {
			originalOrderIds = Array.from(tbSnapshot.querySelectorAll('.selectable-row')).map(r => r.getAttribute('data-id') || '');
		}

		// Limpiar selección para evitar bloqueos visuales/botones
		if (typeof deselectRow === 'function') {
			deselectRow();
		}

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
		if (!isSameTelar(draggedRowTelar, targetTelar)) {
			// Si es telar diferente, validar posición
			const tb = tbodyEl();
			if (tb) {
				const allRowsInDOM = Array.from(tb.querySelectorAll('.selectable-row'));
				const draggedRowIndex = allRowsInDOM.indexOf(draggedRow);

				if (draggedRowIndex !== -1) {
					// Verificar si hay registros en proceso antes de la posición objetivo
					const targetRowsInDOM = allRowsInDOM.filter(row =>
						row !== draggedRow && isSameTelar(getRowTelar(row), targetTelar)
					);

					// Calcular posición objetivo considerando la posición del mouse
					const targetRowRect = targetRow.getBoundingClientRect();
					const mouseY = e.clientY;
					const isBeforeTarget = mouseY < (targetRowRect.top + targetRowRect.height / 2);

					// Encontrar el índice de la fila objetivo dentro de las filas del telar destino
					const targetRowIndexInTelar = targetRowsInDOM.indexOf(targetRow);
					let posicionObjetivo = 0;

					if (targetRowIndexInTelar !== -1) {
						// Si el mouse está antes de la fila objetivo, posición = índice
						// Si está después, posición = índice + 1
						posicionObjetivo = isBeforeTarget ? targetRowIndexInTelar : targetRowIndexInTelar + 1;
					} else {
						// Fallback: contar cuántas filas del telar destino están antes del draggedRow
					for (let i = 0; i < draggedRowIndex; i++) {
							if (allRowsInDOM[i] !== draggedRow && isSameTelar(getRowTelar(allRowsInDOM[i]), targetTelar)) {
							posicionObjetivo++;
							}
						}
					}

					// Encontrar último registro en proceso del telar destino
					let ultimoEnProcesoIndex = -1;
					for (let i = 0; i < targetRowsInDOM.length; i++) {
						if (isRowEnProceso(targetRowsInDOM[i])) {
							ultimoEnProcesoIndex = i;
						}
					}

					// Solo bloquear si se intenta colocar ANTES de un registro en proceso
					// Permitir siempre colocar DESPUÉS del último registro en proceso
					const cantidadFilasTelarDestino = targetRowsInDOM.length;

					// Si se intenta colocar antes de un registro en proceso, mostrar error
					// PERO si solo hay un registro y se quiere colocar después, permitirlo
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
		if (!isSameTelar(draggedRowTelar, targetTelar)) {
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
	function calcularPosicionObjetivo(targetTelar, targetRowElement = null) {
		const tb = tbodyEl();
		if (!tb) return 0;

		// Obtener todas las filas del telar destino del DOM (en orden visual actual)
		const allRowsInDOM = Array.from(tb.querySelectorAll('.selectable-row'));

		// Obtener las filas del telar destino ANTES de incluir el draggedRow
		// Esto es importante porque el draggedRow puede haber sido movido visualmente al telar destino
			const targetRowsOriginal = allRowsInDOM.filter(row => {
				const rowTelar = getRowTelar(row);
				return isSameTelar(rowTelar, targetTelar) && row !== draggedRow;
			});

		// Encontrar la posición del draggedRow en el DOM (después del reordenamiento visual)
		const draggedRowIndex = allRowsInDOM.indexOf(draggedRow);

		// Calcular posición objetivo basada en dónde está el draggedRow en el DOM
		let targetPosition = targetRowsOriginal.length; // Por defecto: al final

		// Si tenemos un targetRowElement específico, usar su posición como referencia
		if (targetRowElement) {
			const targetRowIndex = allRowsInDOM.indexOf(targetRowElement);
			const targetRowIndexInTelar = targetRowsOriginal.indexOf(targetRowElement);

			if (targetRowIndex !== -1) {
				// Contar cuántas filas del telar destino están antes del targetRow
				let posicion = 0;
				for (let i = 0; i < targetRowIndex; i++) {
					const row = allRowsInDOM[i];
					if (row !== draggedRow && isSameTelar(getRowTelar(row), targetTelar)) {
						posicion++;
					}
				}

				// Determinar si el draggedRow está antes o después del targetRow visualmente
				if (draggedRowIndex < targetRowIndex) {
					// draggedRow está ARRIBA del targetRow → colocar ANTES del target
					targetPosition = posicion;
				} else {
					// draggedRow está ABAJO del targetRow → colocar DESPUÉS del target
					targetPosition = posicion + 1;
				}

				// CASO ESPECIAL: Si hay un solo registro en el telar destino
				// y queremos colocar DESPUÉS de él
				if (targetRowsOriginal.length === 1 && draggedRowIndex > targetRowIndex) {
					targetPosition = 1; // Después del único registro
				}
			}
		} else if (draggedRowIndex !== -1) {
			// Contar cuántas filas del telar destino (excluyendo el draggedRow) están antes del draggedRow en el DOM
			let posicion = 0;
			for (let i = 0; i < draggedRowIndex; i++) {
				const row = allRowsInDOM[i];
				if (row !== draggedRow && isSameTelar(getRowTelar(row), targetTelar)) {
					posicion++;
				}
			}
			targetPosition = posicion;

			// CASO ESPECIAL: Si hay un solo registro y draggedRow está después de él
			// Forzar posición al final
			if (targetRowsOriginal.length === 1 && posicion === 1) {
				targetPosition = 1;
			}
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

		// Asegurar que la posición es válida (no negativa y no mayor que el total)
		targetPosition = Math.max(0, Math.min(targetPosition, targetRowsOriginal.length));

		return targetPosition;
	}

	// Manejador de drop (aquí se hace el movimiento real) - COMPLETAMENTE REESCRITO
	async function handleDrop(e) {
		e.stopPropagation();
		e.preventDefault();

		if (!draggedRow) {
			console.error('❌ handleDrop: draggedRow es null');
			showToast('Error: No se encontró el registro arrastrado', 'error');
			return false;
		}

		// Obtener el ID del registro desde dataTransfer o desde draggedRow
		const registroId = e.dataTransfer.getData('text/plain') || draggedRow.getAttribute('data-id');

		if (!registroId) {
			showToast('Error: No se pudo obtener el ID del registro', 'error');
			return false;
		}

		// ESTRATEGIA MEJORADA: Determinar telar destino basándose en la posición visual del draggedRow
		// Después del reordenamiento visual durante drag over, el draggedRow ya está en su nueva posición
		const tb = tbodyEl();
		if (!tb) {
			return false;
		}

		// Obtener todas las filas en el orden actual del DOM
		const allRowsInDOM = Array.from(tb.querySelectorAll('.selectable-row'));
		const draggedIndex = allRowsInDOM.indexOf(draggedRow);

		if (draggedIndex === -1) {
			Swal.fire({
				icon: 'error',
				title: 'Error',
				text: 'No se pudo encontrar el registro en la tabla',
				confirmButtonColor: '#dc2626'
			});
			return false;
		}

		// Determinar el telar destino basándose en la posición final del draggedRow
		let targetTelar = null;
		let targetSalon = null;
		let targetRow = null;

		// ESTRATEGIA MEJORADA: Detectar telar basándose en las filas adyacentes al punto de drop
		// Esto evita el problema de detectar el telar equivocado en los límites entre telares
		if (e.clientY) {
			// Obtener las filas adyacentes al draggedRow en su posición actual del DOM
		const prevRow = draggedIndex > 0 ? allRowsInDOM[draggedIndex - 1] : null;
		const nextRow = draggedIndex < allRowsInDOM.length - 1 ? allRowsInDOM[draggedIndex + 1] : null;

			const prevTelar = prevRow ? getRowTelar(prevRow) : null;
			const nextTelar = nextRow ? getRowTelar(nextRow) : null;

			// CASO 1: Si ambas filas adyacentes son del mismo telar → usar ese telar
			if (prevTelar && nextTelar && isSameTelar(prevTelar, nextTelar)) {
				targetTelar = prevTelar;
				targetRow = prevRow;
				targetSalon = getRowSalon(prevRow);
			}
			// CASO 2: Ambas filas son del telar origen → movimiento dentro del mismo telar
			else if (prevTelar && nextTelar &&
					 isSameTelar(prevTelar, draggedRowTelar) &&
					 isSameTelar(nextTelar, draggedRowTelar)) {
			targetTelar = draggedRowTelar;
				targetRow = prevRow;
				targetSalon = getRowSalon(prevRow);
			}
			// CASO 3: Fila anterior es de OTRO telar (quiere subir) → usar telar de arriba
			else if (prevTelar && !isSameTelar(prevTelar, draggedRowTelar)) {
				targetTelar = prevTelar;
				targetRow = prevRow;
				targetSalon = getRowSalon(prevRow);
			}
			// CASO 4: Fila siguiente es de OTRO telar (quiere bajar) → usar telar de abajo
			else if (nextTelar && !isSameTelar(nextTelar, draggedRowTelar)) {
				targetTelar = nextTelar;
				targetRow = nextRow;
				targetSalon = getRowSalon(nextRow);
			}
			// CASO 5: Solo hay fila anterior (del mismo telar origen)
			else if (prevRow && isSameTelar(prevTelar, draggedRowTelar)) {
				targetTelar = prevTelar;
				targetRow = prevRow;
				targetSalon = getRowSalon(prevRow);
			}
			// CASO 6: Solo hay fila siguiente (del mismo telar origen)
			else if (nextRow && isSameTelar(nextTelar, draggedRowTelar)) {
				targetTelar = nextTelar;
				targetRow = nextRow;
				targetSalon = getRowSalon(nextRow);
			}
			// CASO 7: Fallback - usar fila anterior si existe
			else if (prevRow) {
				targetTelar = prevTelar;
				targetRow = prevRow;
				targetSalon = getRowSalon(prevRow);
		}
			// CASO 8: Fallback - usar fila siguiente
			else if (nextRow) {
				targetTelar = nextTelar;
				targetRow = nextRow;
				targetSalon = getRowSalon(nextRow);
			}
		}

		// FALLBACK: Si no se detectó, buscar la fila más cercana al mouse
		if (!targetTelar && e.clientY) {
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
			}
		}

		if (!targetTelar) {
			Swal.fire({
				icon: 'error',
				title: 'Error',
				text: 'No se pudo determinar el telar destino. Por favor, intenta soltar sobre una fila específica.',
				confirmButtonColor: '#dc2626'
			});
			return false;
		}

		// CASO 1: Mismo telar → Movimiento normal sin validación
		// NOTA: Si el telar detectado es el mismo que el origen, respetar esa decisión
		// No forzar cambio a otro telar solo porque hay filas cercanas de otros telares
		if (isSameTelar(draggedRowTelar, targetTelar)) {
			await procesarMovimientoMismoTelar(registroId);
			return false;
		}

		// CASO 2: Telar diferente → SIEMPRE mostrar alerta y validación
		// Calcular posición objetivo basada en el DOM visual y el targetRow si está disponible
		let targetPosition = calcularPosicionObjetivo(targetTelar, targetRow);

		// Validación adicional: ajustar automáticamente para que nunca quede antes de un registro en proceso
		// Excluir el draggedRow de la lista de filas del telar destino
		const targetRows = getRowsByTelar(targetTelar).filter(row => row !== draggedRow);

		if (targetRows.length) {
			let minAllowedPosition = 0;
			for (let i = 0; i < targetRows.length; i++) {
				if (isRowEnProceso(targetRows[i])) {
					minAllowedPosition = i + 1; // debe quedar después del último en proceso
				}
			}

			if (targetPosition < minAllowedPosition) {
				targetPosition = minAllowedPosition;
				showToast('Se colocó después del registro en proceso del telar destino', 'info');
			}

			// Caso especial: Si hay un solo registro en el telar destino
			// Asegurar que la posición sea válida (0 o 1)
			if (targetRows.length === 1) {
				// Si el único registro está en proceso, forzar posición a 1 (después)
				if (isRowEnProceso(targetRows[0])) {
					targetPosition = 1;
				} else {
					// Si no está en proceso, permitir posición 0 o 1
					targetPosition = Math.max(0, Math.min(targetPosition, 1));
				}
			}
		} else {
			// Si no hay registros en el telar destino, posición = 0
			targetPosition = 0;
		}

		// No permitir posiciones negativas
		targetPosition = Math.max(0, targetPosition);

		// Procesar movimiento a otro telar (siempre mostrará alerta)
		await procesarMovimientoOtroTelar(registroId, targetSalon, targetTelar, targetPosition);
		return false;
	}

	// Función para procesar movimiento dentro del mismo telar
	async function procesarMovimientoMismoTelar(registroId) {
		const tb = tbodyEl();
		if (!tb) return;

		// Calcular nueva posición
		const allRowsSameTelar = allRows.filter(row => isSameTelar(getRowTelar(row), draggedRowTelar));
		const allRowsInDOM = Array.from(tb.querySelectorAll('.selectable-row'));

		// VALIDACIÓN: Si solo hay un registro en el telar, no tiene sentido moverlo
		if (allRowsSameTelar.length < 2) {
			showToast('Se requieren al menos dos registros para reordenar la prioridad', 'info');
			restoreOriginalOrder();
			return;
		}

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
			restoreOriginalOrder();
			return;
		}

		// VALIDACIÓN: Calcular posición original y comparar
		// Encontrar la posición original del registro en la secuencia ordenada por FechaInicio
		const originalRowsOrdered = [...allRowsSameTelar].sort((a, b) => {
			const fechaA = a.querySelector('[data-column="FechaInicio"]')?.getAttribute('data-value') || '';
			const fechaB = b.querySelector('[data-column="FechaInicio"]')?.getAttribute('data-value') || '';
			return fechaA.localeCompare(fechaB);
		});
		const posicionOriginal = originalRowsOrdered.indexOf(draggedRow);

		if (posicionOriginal === nuevaPosicion) {
			showToast('El registro ya está en esa posición', 'info');
			restoreOriginalOrder();
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
				originalOrderIds = []; // confirmamos nuevo orden
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
				Swal.fire({
					icon: 'error',
					title: 'Error de validación',
					text: 'No se pudo validar el cambio de telar. Por favor, intenta de nuevo.',
					confirmButtonColor: '#dc2626'
				});
				return;
			}

			const verificacion = await verificacionResponse.json();
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
				restoreOriginalOrder();
				return;
			}

			// SIEMPRE mostrar alerta con detalles del cambio
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

			const confirmacion = await Swal.fire({
				icon: 'warning',
				title: 'Cambio de Telar/Salón',
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

			if (!confirmacion.isConfirmed) {
				showToast('Operación cancelada', 'info');
				restoreOriginalOrder();
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
				restoreOriginalOrder();
				return;
			}

			const cambio = await cambioResponse.json();
			hideLoading();

			if (!cambio.success) {
				Swal.fire({
					icon: 'error',
					title: 'Error al cambiar de telar',
					text: cambio.message || 'No se pudo cambiar de telar',
					confirmButtonColor: '#dc2626'
				});
				restoreOriginalOrder();
				return;
			}

			// Éxito: mostrar mensaje y recargar página
			originalOrderIds = []; // confirmar nuevo orden
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
			restoreOriginalOrder();
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

	// ===== Menú Contextual (Click Derecho) =====
	let contextMenu = null;
	let contextMenuRow = null;
	let contextMenuTelar = null;
	let contextMenuSalon = null;

	function initContextMenu() {
		contextMenu = document.getElementById('contextMenu');
		if (!contextMenu) return;

		// Ocultar menú al hacer click fuera
		document.addEventListener('click', (e) => {
			if (contextMenu && !contextMenu.contains(e.target)) {
				hideContextMenu();
			}
		});

		// Ocultar menú con ESC
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' && contextMenu && !contextMenu.classList.contains('hidden')) {
				hideContextMenu();
			}
		});

		// Prevenir menú contextual por defecto del navegador en las filas
		const tb = tbodyEl();
		if (tb) {
			tb.addEventListener('contextmenu', (e) => {
				const row = e.target.closest('.selectable-row');
				if (row) {
					e.preventDefault();
					// Verificar si hay un registro seleccionado
					if (selectedRowIndex === -1 || selectedRowIndex === null || selectedRowIndex === undefined) {
						showToast('Por favor, selecciona un registro primero haciendo click en una fila', 'info');
						return;
					}
					// Usar la fila seleccionada en lugar de la fila donde se hizo click derecho
					const selectedRow = allRows[selectedRowIndex] || $$('.selectable-row')[selectedRowIndex];
					if (selectedRow) {
						showContextMenu(e, selectedRow);
					} else {
						showToast('No se pudo encontrar el registro seleccionado', 'error');
					}
				}
			});
		}

		// Event listener para la opción del menú (Crear = abrir modal duplicar/dividir)
		const btnCrear = document.getElementById('contextMenuCrear');
		const btnEditarFila = document.getElementById('contextMenuEditar');

		if (btnCrear) {
			btnCrear.addEventListener('click', () => {
				if (contextMenuRow) {
					// Usar siempre el mismo modal (duplicar/dividir) y dejar que el switch decida
					duplicarTelar(contextMenuRow);
				}
				hideContextMenu();
			});
		}

		if (btnEditarFila) {
			btnEditarFila.addEventListener('click', () => {
				hideContextMenu();
				editarFilaSeleccionada();
			});
		}
	}

	function showContextMenu(e, row) {
		if (!contextMenu || !row) return;

		// Verificar que hay un registro seleccionado
		if (selectedRowIndex === -1 || selectedRowIndex === null || selectedRowIndex === undefined) {
			showToast('Por favor, selecciona un registro primero haciendo click en una fila', 'info');
			return;
		}

		// Obtener información del telar y salón de la fila seleccionada
		contextMenuRow = row;
		contextMenuTelar = getRowTelar(row);
		contextMenuSalon = getRowSalon(row);

		// Validar que tenemos la información necesaria
		if (!contextMenuTelar || !contextMenuSalon) {
			showToast('No se pudo obtener la información del telar', 'error');
			return;
		}

		// Posicionar el menú
		const x = e.clientX;
		const y = e.clientY;
		contextMenu.style.left = x + 'px';
		contextMenu.style.top = y + 'px';

		// Ajustar posición si se sale de la pantalla
		const rect = contextMenu.getBoundingClientRect();
		const windowWidth = window.innerWidth;
		const windowHeight = window.innerHeight;

		if (rect.right > windowWidth) {
			contextMenu.style.left = (x - rect.width) + 'px';
		}
		if (rect.bottom > windowHeight) {
			contextMenu.style.top = (y - rect.height) + 'px';
		}

		// Mostrar el menú
		contextMenu.classList.remove('hidden');
	}

	function hideContextMenu() {
		if (contextMenu) {
			contextMenu.classList.add('hidden');
		}
		contextMenuRow = null;
		contextMenuTelar = null;
		contextMenuSalon = null;
	}

	// Modal Duplicar/Dividir Telar - Cargado desde componente separado
	@include('modulos.programa-tejido.modal.duplicar-dividir')

	// Función para dividir telar
	async function dividirTelar(row) {
		const telar = getRowTelar(row);
		const salon = getRowSalon(row);

		if (!telar || !salon) {
			showToast('No se pudo obtener la información del telar', 'error');
			return;
		}

		// Obtener todos los registros del telar
		const rowsSameTelar = getRowsByTelar(telar);
		if (rowsSameTelar.length < 2) {
			showToast('Se requieren al menos 2 registros para dividir un telar', 'error');
			return;
		}

		// Obtener el índice de la fila seleccionada dentro del telar
		const rowIndex = rowsSameTelar.indexOf(row);
		if (rowIndex === -1) {
			showToast('No se pudo determinar la posición del registro', 'error');
			return;
		}

		// Mostrar modal para seleccionar dónde dividir
		const { value: posicionDivision } = await Swal.fire({
			title: 'Dividir Telar',
			html: `
				<div class="text-left">
					<p class="mb-3">Selecciona dónde dividir el telar <strong>${telar}</strong> (Salón ${salon}):</p>
					<p class="text-sm text-gray-600 mb-4">Los registros desde la posición seleccionada en adelante se moverán a un nuevo telar.</p>
					<div class="mb-4">
						<label class="block text-sm font-medium text-gray-700 mb-2">Posición de división:</label>
						<select id="posicionDivision" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
							${rowsSameTelar.map((r, idx) => {
								const registroId = r.getAttribute('data-id');
								const fechaInicio = r.querySelector('[data-column="FechaInicio"]')?.textContent?.trim() || '';
								return `<option value="${idx}" ${idx === rowIndex ? 'selected' : ''}>Posición ${idx + 1}${fechaInicio ? ' - ' + fechaInicio : ''}</option>`;
							}).join('')}
						</select>
					</div>
					<div class="mb-4">
						<label class="block text-sm font-medium text-gray-700 mb-2">Nuevo telar:</label>
						<input type="text" id="nuevoTelar" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: ${telar}-2" required>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-2">Nuevo salón (opcional):</label>
						<input type="text" id="nuevoSalon" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="${salon}">
					</div>
				</div>
			`,
			icon: 'question',
			showCancelButton: true,
			confirmButtonText: 'Dividir',
			cancelButtonText: 'Cancelar',
			confirmButtonColor: '#f59e0b',
			cancelButtonColor: '#6b7280',
			width: '500px',
			preConfirm: () => {
				const posicion = document.getElementById('posicionDivision').value;
				const nuevoTelar = document.getElementById('nuevoTelar').value.trim();
				if (!nuevoTelar) {
					Swal.showValidationMessage('El nuevo telar es requerido');
					return false;
				}
				return {
					posicion: parseInt(posicion),
					nuevoTelar: nuevoTelar,
					nuevoSalon: document.getElementById('nuevoSalon').value.trim() || salon
				};
			}
		});

		if (!posicionDivision) {
			return;
		}

		showLoading();
		try {
			const response = await fetch('/planeacion/programa-tejido/dividir-telar', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
				},
				body: JSON.stringify({
					salon_tejido_id: salon,
					no_telar_id: telar,
					posicion_division: posicionDivision.posicion,
					nuevo_telar: posicionDivision.nuevoTelar,
					nuevo_salon: posicionDivision.nuevoSalon
				})
			});

			const data = await response.json();
			hideLoading();

			if (data.success) {
				showToast(data.message || 'Telar dividido correctamente', 'success');
				// Recargar la página después de un breve delay
				setTimeout(() => {
					window.location.reload();
				}, 1000);
			} else {
				showToast(data.message || 'Error al dividir el telar', 'error');
			}
		} catch (error) {
			hideLoading();
			showToast('Ocurrió un error al procesar la solicitud', 'error');
			console.error('Error al dividir telar:', error);
		}
	}

	// ===== Init =====
	document.addEventListener('DOMContentLoaded', function() {
		// Inicializar visibilidad de columnas según grupos
		if (typeof initializeColumnVisibility === 'function') {
			initializeColumnVisibility();
		}

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

		// ===== Botón Restablecer (Columnas + Filtros) =====
		const btnResetColumns = document.getElementById('btnResetColumns');
		const btnResetColumnsMobile = document.getElementById('btnResetColumnsMobile');

		const handleResetAll = function(e) {
			e.preventDefault();

			// Animación del icono
			const icon = this.querySelector('i') || document.getElementById('iconResetColumns');
			if (icon) {
				icon.classList.add('fa-spin');
				setTimeout(() => icon.classList.remove('fa-spin'), 500);
			}

			// 1. Limpiar FILTROS (igual que "Limpiar todo" del modal)
			filters = [];
			quickFilters = {
				ultimos: false,
				divididos: false,
				enProceso: false,
				salonJacquard: false,
				salonSmit: false,
				conCambioHilo: false,
			};
			dateRangeFilters = {
				fechaInicio: { desde: null, hasta: null },
				fechaFinal: { desde: null, hasta: null },
			};
			lastFilterState = null;

			// Mostrar todas las filas
			const tb = tbodyEl();
			if (tb) {
				tb.querySelectorAll('.selectable-row').forEach(row => {
					row.style.display = '';
					row.classList.remove('filter-hidden');
				});
			}

			// Actualizar badge de filtros
			if (typeof updateFilterUI === 'function') {
				updateFilterUI();
			}

			// 2. Restablecer COLUMNAS
			if (typeof resetColumnVisibility === 'function') {
				resetColumnVisibility();
			} else {
				// Fallback
				const table = document.getElementById('mainTable');
				if (table) {
					const headers = table.querySelectorAll('thead th');
					const totalCols = headers.length || 50;

					for (let i = 0; i < totalCols; i++) {
						document.querySelectorAll('.column-' + i).forEach(el => {
							el.style.display = '';
							el.style.visibility = '';
						});
					}

					hiddenColumns = [];
					pinnedColumns = [];

					if (typeof updatePinnedColumnsPositions === 'function') {
						updatePinnedColumnsPositions();
					}
				}
			}

			showToast('Vista restablecida (filtros y columnas)', 'success');
		};

		if (btnResetColumns) {
			btnResetColumns.addEventListener('click', handleResetAll);
		}
		if (btnResetColumnsMobile) {
			btnResetColumnsMobile.addEventListener('click', handleResetAll);
		}

		// Inicializar menú contextual
		initContextMenu();

		// ===== Seleccionar registro por parámetros de URL (después de duplicar) =====
		const urlParams = new URLSearchParams(window.location.search);
		const registroIdParam = urlParams.get('registro_id');
		const salonParam = urlParams.get('salon');
		const telarParam = urlParams.get('telar');

		if (registroIdParam || (salonParam && telarParam)) {
			// Buscar la fila que coincida con los parámetros
			setTimeout(() => {
				let filaEncontrada = null;
				let filaIndex = -1;

				allRows.forEach((row, idx) => {
					// Primero intentar por registro_id
					if (registroIdParam && row.getAttribute('data-id') == registroIdParam) {
						filaEncontrada = row;
						filaIndex = idx;
						return;
					}
					// Si no hay registro_id, buscar por salon y telar
					if (!filaEncontrada && salonParam && telarParam) {
						const salonCell = row.querySelector('[data-column="SalonTejidoId"]');
						const telarCell = row.querySelector('[data-column="NoTelarId"]');
						if (salonCell?.textContent?.trim() === salonParam &&
							telarCell?.textContent?.trim() === telarParam) {
							filaEncontrada = row;
							filaIndex = idx;
						}
					}
				});

				if (filaEncontrada && filaIndex >= 0) {
					// Seleccionar la fila
					selectRow(filaEncontrada, filaIndex);
					// Hacer scroll hacia la fila
					filaEncontrada.scrollIntoView({ behavior: 'smooth', block: 'center' });
					// Resaltar brevemente la fila
					filaEncontrada.classList.add('bg-yellow-100');
					setTimeout(() => {
						filaEncontrada.classList.remove('bg-yellow-100');
					}, 2000);
				}

				// Limpiar parámetros de la URL sin recargar
				if (registroIdParam || salonParam || telarParam) {
					const cleanUrl = new URL(window.location.href);
					cleanUrl.searchParams.delete('registro_id');
					cleanUrl.searchParams.delete('salon');
					cleanUrl.searchParams.delete('telar');
					window.history.replaceState({}, '', cleanUrl.toString());
				}
			}, 100);
		}

		// Inicializar botones del layout como deshabilitados
		const btnEditarLayout = document.getElementById('layoutBtnEditar');
		const btnEliminarLayout = document.getElementById('layoutBtnEliminar');
		const btnVerLineasLayout = document.getElementById('layoutBtnVerLineas');
		if (btnEditarLayout) {
			btnEditarLayout.disabled = true;
		}
		const btnInlineEdit = document.getElementById('btnInlineEdit');
		if (btnInlineEdit) {
			// Quitar el botón de editar de la navbar para esta vista
			btnInlineEdit.remove();
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
