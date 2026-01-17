const columnGroups = {
	1: {
		name: 'Grupo 1',
		fields: [
			'CuentaRizo','CalibreRizo2','SalonTejidoId','NoTelarId','Ultimo','CambioHilo',
			'CalendarioId','NoExisteBase','ItemId','InventSizeId','Rasurado'
		],
		defaultVisible: true
	},
	2: {
		name: 'Grupo 2',
		fields: [
			'PasadasTrama','PasadasComb1','PasadasComb2','PasadasComb3','PasadasComb4','PasadasComb5',
			'AnchoToalla','CodColorTrama','ColorTrama',
			'CalibreComb1','FibraComb1','CodColorComb1','NombreCC1',
			'CalibreComb2','FibraComb2','CodColorComb2','NombreCC2',
			'CalibreComb3','FibraComb3','CodColorComb3','NombreCC3',
			'CalibreComb4','FibraComb4','CodColorComb4','NombreCC4',
			'CalibreComb5','FibraComb5','CodColorComb5','NombreCC5',
			'MedidaPlano','CuentaPie','CodColorCtaPie','NombreCPie','PesoGRM2'
		],
		defaultVisible: true
	},
	3: {
		name: 'Grupo 3',
		fields: ['ProdKgDia','StdToaHra','DiasJornada','HorasProd','StdHrsEfect'],
		defaultVisible: true
	}
};
const REQUIRED_PINNED_FIELDS = ['Ultimo', 'CambioHilo', 'Maquina', 'Ancho', 'NombreProducto'];
// ===== Cache de columnas para lookups rapidos =====
const columnCache = {
	fieldToIndex: null,
	fieldToGroup: null,
	groupToIndices: null,
	columnCount: 0
};
let columnIndicesCache = null;
let hiddenColumnsSet = new Set();
let pinnedColumnsSet = new Set();
let defaultPinsApplied = false;
function buildFieldToGroupMap() {
	columnCache.fieldToGroup = new Map();
	Object.entries(columnGroups).forEach(([groupId, group]) => {
		group.fields.forEach(field => {
			columnCache.fieldToGroup.set(field, parseInt(groupId));
		});
	});
}
function ensureColumnCache() {
	if (!columnCache.fieldToGroup || columnCache.fieldToGroup.size === 0) {
		buildFieldToGroupMap();
	}
	if (!Array.isArray(columnsData) || columnsData.length === 0) return false;
	if (columnCache.fieldToIndex && columnCache.columnCount === columnsData.length) return true;
	columnCache.fieldToIndex = new Map();
	columnsData.forEach((col, idx) => {
		if (col?.field) columnCache.fieldToIndex.set(col.field, idx);
	});
	columnCache.columnCount = columnsData.length;
	columnIndicesCache = columnsData.map((_, idx) => idx);
	columnCache.groupToIndices = new Map();
	Object.entries(columnGroups).forEach(([groupId, group]) => {
		const indices = [];
		group.fields.forEach(field => {
			const idx = columnCache.fieldToIndex.get(field);
			if (idx !== undefined) indices.push(idx);
		});
		columnCache.groupToIndices.set(parseInt(groupId), indices);
	});
	return true;
}
function getAllColumnIndices() {
	if (Array.isArray(columnIndicesCache) && columnIndicesCache.length > 0) return columnIndicesCache;
	if (Array.isArray(columnsData) && columnsData.length > 0) {
		columnIndicesCache = columnsData.map((_, idx) => idx);
		return columnIndicesCache;
	}
	const nodes = document.querySelectorAll('th[class*="column-"]');
	const indices = [];
	nodes.forEach(th => {
		const idx = parseInt(th.dataset.index);
		if (!Number.isNaN(idx)) indices.push(idx);
	});
	return [...new Set(indices)];
}
function syncColumnStateSets(force = false) {
	if (!Array.isArray(hiddenColumns)) hiddenColumns = [];
	if (!Array.isArray(pinnedColumns)) pinnedColumns = [];
	if (force || hiddenColumnsSet.size !== hiddenColumns.length) {
		hiddenColumnsSet = new Set(hiddenColumns);
	}
	if (force || pinnedColumnsSet.size !== pinnedColumns.length) {
		pinnedColumnsSet = new Set(pinnedColumns);
	}
}
const columnElementsCache = new Map();
let columnElementsCacheRowCount = 0;
function getColumnElements(index) {
	const table = document.getElementById('mainTable');
	if (!table) {
		columnElementsCache.clear();
		columnElementsCacheRowCount = 0;
		return [];
	}
	const rowCount = table.rows.length;
	if (rowCount !== columnElementsCacheRowCount) {
		columnElementsCache.clear();
		columnElementsCacheRowCount = rowCount;
	}
	let cached = columnElementsCache.get(index);
	if (cached && cached.length > 0 && cached[0].isConnected) {
		const valid = cached.every(el => el.closest('table') === table);
		if (valid) return cached;
	}
	cached = Array.from(table.getElementsByClassName(`column-${index}`));
	columnElementsCache.set(index, cached);
	return cached;
}
function forEachColumnElement(index, cb) {
	const elements = getColumnElements(index);
	for (let i = 0; i < elements.length; i++) cb(elements[i]);
}
function ensureColumnsReady() {
	if (!Array.isArray(columnsData) || columnsData.length === 0) {
		if (typeof showToast === 'function') {
			showToast('Las columnas aun no estan disponibles. Por favor, espera un momento e intenta de nuevo.', 'warning');
		} else {
			alert('Las columnas aun no estan disponibles. Por favor, espera un momento e intenta de nuevo.');
		}
		return false;
	}
	ensureColumnCache();
	syncColumnStateSets();
	return true;
}
function buildGroupedColumns() {
	const groupedColumns = {};
	const ungroupedColumns = [];
	columnsData.forEach((col, realIndex) => {
		if (!col || !col.field) return;
		const groupId = getColumnGroup(col.field);
		const colData = {
			label: col.label || col.field || '',
			field: col.field
		};
		if (groupId) {
			if (!groupedColumns[groupId]) {
				groupedColumns[groupId] = {
					group: columnGroups[groupId],
					columns: []
				};
			}
			groupedColumns[groupId].columns.push({ col: colData, index: realIndex });
		} else {
			ungroupedColumns.push({ col: colData, index: realIndex });
		}
	});
	return { groupedColumns, ungroupedColumns };
}
// ===== Persistencia de columnas ocultas (usa hiddenColumns global de state.blade) =====
const COLUMN_STATE_ENDPOINT = '/programa-tejido/columnas';
const CURRENT_USER_ID = (window?.App?.user?.id) ?? (window?.authUserId) ?? null;
const COLUMN_STATE_CACHE_KEY = 'pt_hidden_columns_' + (CURRENT_USER_ID || 'guest');
const PINNED_COLUMNS_CACHE_KEY = 'pt_pinned_columns_' + (CURRENT_USER_ID || 'guest');

function getCachedHiddenFields() {
	try {
		const raw = localStorage.getItem(COLUMN_STATE_CACHE_KEY);
		if (!raw) return null;
		const data = JSON.parse(raw);
		return Array.isArray(data) ? data : null;
	} catch (e) {
		return null;
	}
}

function setCachedHiddenFields(fields) {
	try {
		localStorage.setItem(COLUMN_STATE_CACHE_KEY, JSON.stringify(fields || []));
	} catch (e) {
		// ignore cache errors
	}
}

function getCachedPinnedFields() {
	try {
		const raw = localStorage.getItem(PINNED_COLUMNS_CACHE_KEY);
		if (!raw) return null;
		const data = JSON.parse(raw);
		return Array.isArray(data) ? data : null;
	} catch (e) {
		return null;
	}
}

function setCachedPinnedFields(fields) {
	try {
		localStorage.setItem(PINNED_COLUMNS_CACHE_KEY, JSON.stringify(fields || []));
	} catch (e) {
		// ignore cache errors
	}
}

let saveHiddenColumnsTimer = null;
let isInitializingColumns = false; // evita guardar mientras cargamos estados
let pendingHiddenFields = null; // campos que deben ocultarse cuando columnsData estÃ© listo
let initialColumnsApplyScheduled = false;
let initialColumnsApplyAttempts = 0;
const MAX_INITIAL_COLUMNS_APPLY_ATTEMPTS = 30;
async function loadPersistedHiddenColumns() {
	// ⚡ OPTIMIZACIÓN: Aplicar caché inmediatamente sin esperar al servidor
	const cachedFields = getCachedHiddenFields();
	const hasCached = Array.isArray(cachedFields);
	if (hasCached) {
		pendingHiddenFields = cachedFields.slice();
		// Aplicar inmediatamente desde caché
		tryApplyHiddenFields();
	}

	// ⚡ OPTIMIZACIÓN: Cargar desde servidor en segundo plano sin bloquear
	// No esperar a que termine para aplicar el estado
	try {
		isInitializingColumns = true;
		const res = await fetch(COLUMN_STATE_ENDPOINT, {
			method: 'GET',
			headers: { 'Accept': 'application/json' },
			credentials: 'same-origin'
		});
		if (res.ok) {
			const data = await res.json();
			if (data.success && data.data) {
				// Guardar pendientes y aplicar cuando columnsData este listo
				const hiddenFields = Object.entries(data.data || {})
					.filter(([, hidden]) => hidden)
					.map(([field]) => field);
				setCachedHiddenFields(hiddenFields);
				// Solo actualizar si hay diferencias con el caché
				if (!hasCached || JSON.stringify(cachedFields.sort()) !== JSON.stringify(hiddenFields.sort())) {
					pendingHiddenFields = hiddenFields;
					tryApplyHiddenFields();
				}
			}
		}
	} catch (e) {
		console.warn('No se pudo cargar estado de columnas desde servidor', e);
	} finally {
		isInitializingColumns = false;

		// ⚡ OPTIMIZACIÓN: Si no había estados guardados, inicializar con defaults
		// Esto asegura que siempre haya una inicialización, pero solo si no hay estados guardados
		if (!hasCached && typeof window.initializeColumnVisibility === 'function') {
			// Esperar un momento para que columnsData esté listo
			setTimeout(() => {
				window.initializeColumnVisibility();
			}, 50);
		}
	}

	// Si no había caché, intentar aplicar de todas formas
	if (!hasCached) {
		tryApplyHiddenFields();
	}
}
function scheduleSaveHiddenColumns() {
	if (isInitializingColumns) return;
	if (saveHiddenColumnsTimer) clearTimeout(saveHiddenColumnsTimer);
	saveHiddenColumnsTimer = setTimeout(saveHiddenColumns, 400);
}
async function saveHiddenColumns() {
	try {
		syncColumnStateSets();
		const columnas = {};
		// Enviar estado completo: true = oculta, false = visible
		const hiddenFields = [];
		columnsData.forEach((col, idx) => {
			if (!col?.field) return;
			const hidden = hiddenColumnsSet.has(idx);
			columnas[col.field] = hidden;
			if (hidden) hiddenFields.push(col.field);
		});
		setCachedHiddenFields(hiddenFields);
		const body = { columnas };
		if (CURRENT_USER_ID) body.usuario_id = CURRENT_USER_ID;
		const res = await fetch(COLUMN_STATE_ENDPOINT, {
			method: 'POST',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
			},
			credentials: 'same-origin',
			body: JSON.stringify(body)
		});
		if (!res.ok) {
			console.warn('No se pudo guardar estado de columnas (respuesta no OK)', res.status);
		}
	} catch (e) {
		console.warn('No se pudo guardar estado de columnas', e);
	}
}
// FunciÃ³n para obtener el Ã­ndice de una columna por su campo
function getColumnIndexByField(field) {
	if (ensureColumnCache()) {
		const idx = columnCache.fieldToIndex.get(field);
		return idx === undefined ? -1 : idx;
	}
	return columnsData.findIndex(col => col.field === field);
}
function isRequiredPinnedField(field) {
	return REQUIRED_PINNED_FIELDS.includes(field);
}
function isRequiredPinnedIndex(index) {
	const field = columnsData[index]?.field;
	return field ? isRequiredPinnedField(field) : false;
}
// FunciÃ³n para obtener el grupo de una columna
function getColumnGroup(field) {
	if (!columnCache.fieldToGroup || columnCache.fieldToGroup.size === 0) {
		buildFieldToGroupMap();
	}
	return columnCache.fieldToGroup.get(field) || null;
}
// FunciÃ³n para obtener todas las columnas de un grupo
function getGroupColumns(groupId) {
	if (ensureColumnCache() && columnCache.groupToIndices) {
		return columnCache.groupToIndices.get(groupId) || [];
	}
	const group = columnGroups[groupId];
	if (!group) return [];
	return group.fields.map(field => getColumnIndexByField(field)).filter(idx => idx !== -1);
}
function tryApplyHiddenFields() {
	if (initialColumnsApplyScheduled) return;
	initialColumnsApplyScheduled = true;
	// Aplicar inmediatamente si columnsData está disponible, sino usar requestAnimationFrame
	if (Array.isArray(columnsData) && columnsData.length > 0) {
		applyInitialColumnState();
	} else {
		requestAnimationFrame(applyInitialColumnState);
	}
}
function applyInitialColumnState() {
	initialColumnsApplyScheduled = false;
	try {
		if (!Array.isArray(columnsData) || columnsData.length === 0) {
			if (initialColumnsApplyAttempts < MAX_INITIAL_COLUMNS_APPLY_ATTEMPTS) {
				initialColumnsApplyAttempts += 1;
				// Usar setTimeout con delay mínimo en lugar de requestAnimationFrame para reintentos
				setTimeout(tryApplyHiddenFields, 10);
				return;
			}
			return;
		}
		ensureColumnCache();
		syncColumnStateSets();

		// ⚡ OPTIMIZACIÓN: Aplicar estados de oculto de forma más eficiente
		if (pendingHiddenFields && pendingHiddenFields.length > 0) {
			// Batch DOM updates: aplicar todos los cambios de una vez
			const indicesToHide = [];
			pendingHiddenFields.forEach(field => {
				const idx = getColumnIndexByField(field);
				if (idx !== -1 && !hiddenColumnsSet.has(idx)) {
					indicesToHide.push(idx);
					hiddenColumnsSet.add(idx);
					hiddenColumns.push(idx);
				}
			});
			// Aplicar todos los cambios de visibilidad de una vez
			indicesToHide.forEach(idx => {
				forEachColumnElement(idx, el => {
					el.style.display = 'none';
				});
			});
		}

		// ⚡ OPTIMIZACIÓN: Aplicar columnas fijadas inmediatamente
		applyDefaultPinsOnce();

		// ⚡ OPTIMIZACIÓN: Actualizar posiciones de columnas fijadas inmediatamente
		updatePinnedColumnsPositions();

		pendingHiddenFields = null;
		initialColumnsApplyAttempts = 0;
	} catch (e) {
		console.warn('No se pudo aplicar columnas ocultas', e);
	}
}

// FunciÃ³n para verificar si un grupo estÃ¡ visible
function isGroupVisible(groupId) {
	syncColumnStateSets();
	const groupColumns = getGroupColumns(groupId);
	if (groupColumns.length === 0) return false;
	// Un grupo estÃ¡ visible si al menos una columna estÃ¡ visible
	return groupColumns.some(idx => !hiddenColumnsSet.has(idx));
}
// FunciÃ³n para mostrar/ocultar un grupo completo
function toggleGroupVisibility(groupId, visible, silent = false) {
	const groupColumns = getGroupColumns(groupId);
	if (groupColumns.length === 0) return;
	groupColumns.forEach(index => {
		if (visible) showColumn(index, true);
		else hideColumn(index, true);
	});
	if (!silent) {
		if (typeof showToast === 'function') {
			showToast(visible ? 'Grupo visible' : 'Grupo oculto', 'info');
		}
		scheduleSaveHiddenColumns();
	}
}
// FunciÃ³n para fijar/desfijar un grupo completo
function toggleGroupPin(groupId, pin) {
	const groupColumns = getGroupColumns(groupId);
	if (groupColumns.length === 0) return;
	syncColumnStateSets();
	let changed = false;
	if (pin) {
		groupColumns.forEach(index => {
			if (!pinnedColumnsSet.has(index)) {
				pinnedColumnsSet.add(index);
				pinnedColumns.push(index);
				changed = true;
			}
		});
		if (changed) pinnedColumns.sort((a, b) => a - b);
	} else {
		groupColumns.forEach(index => {
			if (pinnedColumnsSet.has(index) && !isRequiredPinnedIndex(index)) {
				pinnedColumnsSet.delete(index);
				changed = true;
			}
		});
		if (changed) pinnedColumns = pinnedColumns.filter(i => pinnedColumnsSet.has(i));
	}
	if (changed) {
		// ⚡ OPTIMIZACIÓN: Guardar en caché cuando se cambia un grupo
		if (ensureColumnCache()) {
			const fields = [];
			groupColumns.forEach(index => {
				if (pin && pinnedColumnsSet.has(index)) {
					const field = columnsData[index]?.field;
					if (field) fields.push(field);
				}
			});
			if (pin) {
				const cached = getCachedPinnedFields() || [];
				fields.forEach(field => {
					if (!cached.includes(field)) cached.push(field);
				});
				setCachedPinnedFields(cached);
			} else {
				const cached = getCachedPinnedFields() || [];
				const filtered = cached.filter(f => !fields.includes(f) || isRequiredPinnedField(f));
				setCachedPinnedFields(filtered);
			}
		}
		updatePinnedColumnsPositions();
	}
}
// ===== Controles de columnas desde navbar =====
const COLUMN_MODAL_CONFIG = {
	pin: {
		title: 'Fijar Columnas',
		description: 'Selecciona las columnas o grupos que deseas fijar a la izquierda de la tabla:',
		groupLabel: 'Fijar grupo',
		columnClass: 'column-toggle-pin',
		groupClass: 'group-toggle-pin',
		checkboxClass: 'text-yellow-700',
		focusClass: 'focus:ring-yellow-600',
		confirmColor: '#d97706',
		columnAction: (index, checked) => (checked ? pinColumn(index) : unpinColumn(index)),
		groupAction: (groupId, checked) => toggleGroupPin(groupId, checked)
	},
	hide: {
		title: 'Ocultar Columnas',
		description: 'Selecciona las columnas o grupos que deseas ocultar:',
		groupLabel: 'Ocultar grupo',
		columnClass: 'column-toggle-hide',
		groupClass: 'group-toggle-hide',
		checkboxClass: 'text-red-600',
		focusClass: 'focus:ring-red-500',
		confirmColor: '#ef4444',
		columnAction: (index, checked) => (checked ? hideColumn(index) : showColumn(index)),
		groupAction: (groupId, checked) => toggleGroupVisibility(groupId, !checked)
	}
};
function getModalStateSet(mode) {
	syncColumnStateSets();
	return mode === 'pin' ? pinnedColumnsSet : hiddenColumnsSet;
}
function buildColumnsModalHtml({ mode, groupedColumns, ungroupedColumns }) {
	const config = COLUMN_MODAL_CONFIG[mode];
	const set = getModalStateSet(mode);
	let html = `
		<div class="text-left">
			<p class="text-sm text-gray-600 mb-4">${config.description}</p>
			<div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-4">
	`;
	Object.keys(groupedColumns).sort().forEach(groupId => {
		const groupData = groupedColumns[groupId];
		const groupColumns = getGroupColumns(parseInt(groupId, 10));
		const allChecked = groupColumns.length > 0 && groupColumns.every(idx => set.has(idx) || (mode === 'pin' && isRequiredPinnedIndex(idx)));
		const someChecked = groupColumns.some(idx => set.has(idx) || (mode === 'pin' && isRequiredPinnedIndex(idx)));
		const indeterminateAttr = someChecked && !allChecked ? 'data-indeterminate="1"' : '';
		html += `
			<div class="border border-gray-200 rounded-lg p-2">
				<div class="flex items-center justify-between mb-2 pb-2 border-b border-gray-200">
					<span class="text-sm font-semibold text-gray-800">${groupData.group.name}</span>
					<label class="flex items-center gap-2 cursor-pointer">
						<input type="checkbox" ${allChecked ? 'checked' : ''}
							   class="w-4 h-4 ${config.checkboxClass} bg-gray-100 border-gray-300 rounded ${config.focusClass} ${config.groupClass}"
							   data-group-id="${groupId}" ${indeterminateAttr}>
						<span class="text-xs text-gray-600">${config.groupLabel}</span>
					</label>
				</div>
				<div class="pl-4 space-y-1">
		`;
		groupData.columns.forEach(({ col, index }) => {
			const isRequired = mode === 'pin' && isRequiredPinnedField(col.field);
			const isChecked = isRequired || set.has(index);
			const disabledAttr = isRequired ? 'disabled' : '';
			html += `
				<div class="flex items-center justify-between p-1 hover:bg-gray-50 rounded">
					<span class="text-xs text-gray-700 ml-2">${col.label}</span>
					<input type="checkbox" ${isChecked ? 'checked' : ''}
						   class="w-4 h-4 ${config.checkboxClass} bg-gray-100 border-gray-300 rounded ${config.focusClass} ${config.columnClass}"
						   data-column-index="${index}"
						   data-group-id="${groupId}" ${disabledAttr}>
				</div>
			`;
		});
		html += `
				</div>
			</div>
		`;
	});
	if (ungroupedColumns.length > 0) {
		html += `
			<div class="border border-gray-200 rounded-lg p-2">
				<div class="text-sm font-semibold text-gray-800 mb-2 pb-2 border-b border-gray-200">Otras columnas</div>
				<div class="pl-4 space-y-1">
		`;
		ungroupedColumns.forEach(({ col, index }) => {
			const isRequired = mode === 'pin' && isRequiredPinnedField(col.field);
			const isChecked = isRequired || set.has(index);
			const disabledAttr = isRequired ? 'disabled' : '';
			html += `
				<div class="flex items-center justify-between p-1 hover:bg-gray-50 rounded">
					<span class="text-xs text-gray-700 ml-2">${col.label}</span>
					<input type="checkbox" ${isChecked ? 'checked' : ''}
						   class="w-4 h-4 ${config.checkboxClass} bg-gray-100 border-gray-300 rounded ${config.focusClass} ${config.columnClass}"
						   data-column-index="${index}" ${disabledAttr}>
				</div>
			`;
		});
		html += `
				</div>
			</div>
		`;
	}
	html += `
			</div>
		</div>
	`;
	return html;
}
function bindColumnsModalEvents(mode) {
	const config = COLUMN_MODAL_CONFIG[mode];
	const container = document.getElementById('swal2-html-container');
	if (!config || !container) return;
	container.querySelectorAll(`.${config.groupClass}[data-indeterminate="1"]`).forEach(cb => {
		cb.indeterminate = true;
	});
	container.querySelectorAll(`.${config.columnClass}`).forEach(checkbox => {
		checkbox.addEventListener('change', function() {
			const columnIndex = parseInt(this.dataset.columnIndex, 10);
			if (Number.isNaN(columnIndex)) return;
			config.columnAction(columnIndex, this.checked);
			const groupId = this.dataset.groupId;
			if (groupId) updateGroupCheckboxState(parseInt(groupId, 10), mode);
		});
	});
	container.querySelectorAll(`.${config.groupClass}`).forEach(checkbox => {
		checkbox.addEventListener('change', function() {
			const groupId = parseInt(this.dataset.groupId, 10);
			if (Number.isNaN(groupId)) return;
			config.groupAction(groupId, this.checked);
			this.indeterminate = false;
			container.querySelectorAll(`.${config.columnClass}[data-group-id="${groupId}"]`).forEach(cb => {
				cb.checked = this.checked;
			});
		});
	});
}
function openColumnsModal(mode) {
	if (!ensureColumnsReady()) return;
	if (!COLUMN_MODAL_CONFIG[mode]) return;
	const { groupedColumns, ungroupedColumns } = buildGroupedColumns();
	const html = buildColumnsModalHtml({ mode, groupedColumns, ungroupedColumns });
	const config = COLUMN_MODAL_CONFIG[mode];
	Swal.fire({
		title: config.title,
		html: html,
		showCancelButton: true,
		confirmButtonText: 'Aplicar',
		cancelButtonText: 'Cancelar',
		confirmButtonColor: config.confirmColor,
		cancelButtonColor: '#6b7280',
		width: '600px',
		didOpen: () => bindColumnsModalEvents(mode)
	});
}
function openPinColumnsModal() {
	openColumnsModal('pin');
}
function openHideColumnsModal() {
	openColumnsModal('hide');
}
function updateGroupCheckboxState(groupId, type) {
	const groupColumns = getGroupColumns(groupId);
	const prefix = type === 'pin' ? 'pin' : 'hide';
	syncColumnStateSets();
	const set = type === 'pin' ? pinnedColumnsSet : hiddenColumnsSet;
	const allChecked = groupColumns.every(idx => set.has(idx) || (type === 'pin' && isRequiredPinnedIndex(idx)));
	const someChecked = groupColumns.some(idx => set.has(idx) || (type === 'pin' && isRequiredPinnedIndex(idx)));
	const groupCheckbox = document.querySelector(`.group-toggle-${prefix}[data-group-id="${groupId}"]`);
	if (groupCheckbox) {
		groupCheckbox.checked = allChecked;
		groupCheckbox.indeterminate = someChecked && !allChecked;
	}
}
function getColumnsData() {
	if (!Array.isArray(columnsData) || columnsData.length === 0) {
		console.warn('getColumnsData: columnsData no estÃ¡ disponible o estÃ¡ vacÃ­o');
		return [];
	}
	return columnsData.map(c => ({
		label: c.label || c.field || '',
		field: c.field || ''
	})).filter(c => c.field); // Filtrar columnas sin field
}
function getPinnedColumns() {
	syncColumnStateSets();
	return pinnedColumns || [];
}
function getHiddenColumns() {
	syncColumnStateSets();
	return hiddenColumns || [];
}
function pinColumn(index) {
	syncColumnStateSets();
	if (!pinnedColumnsSet.has(index)) {
		pinnedColumnsSet.add(index);
		pinnedColumns.push(index);
		pinnedColumns.sort((a, b) => a - b);

		// ⚡ OPTIMIZACIÓN: Guardar en caché inmediatamente
		if (ensureColumnCache()) {
			const field = columnsData[index]?.field;
			if (field) {
				const cached = getCachedPinnedFields() || [];
				if (!cached.includes(field)) {
					cached.push(field);
					setCachedPinnedFields(cached);
				}
			}
		}

		updatePinnedColumnsPositions();
	}
}
function unpinColumn(index) {
	syncColumnStateSets();
	// Permitir desfijar incluso columnas requeridas
	if (pinnedColumnsSet.has(index)) {
		pinnedColumnsSet.delete(index);
		const idx = pinnedColumns.indexOf(index);
		if (idx > -1) pinnedColumns.splice(idx, 1);

		// ⚡ OPTIMIZACIÓN: Guardar en caché inmediatamente
		if (ensureColumnCache()) {
			const field = columnsData[index]?.field;
			if (field) {
				const cached = getCachedPinnedFields() || [];
				const filtered = cached.filter(f => f !== field);
				setCachedPinnedFields(filtered);
			}
		}

		updatePinnedColumnsPositions();
	}
}
function resetColumnVisibility() {
	try {
		const table = document.getElementById('mainTable');
		if (!table) {
			console.warn('resetColumnVisibility: tabla no encontrada');
			return;
		}
		// Limpiar array de columnas ocultas
		hiddenColumns = [];
		hiddenColumnsSet.clear();
		// Aplicar visibilidad segÃºn grupos por defecto
		Object.keys(columnGroups).forEach(groupId => {
			const group = columnGroups[groupId];
			const groupIdNum = parseInt(groupId);
			toggleGroupVisibility(groupIdNum, group.defaultVisible, true);
		});
		// Mostrar columnas sin grupo (por defecto visibles)
		columnsData.forEach((col, index) => {
			const groupId = getColumnGroup(col.field);
			if (!groupId) {
				showColumn(index, true);
			}
		});
		// Desfijar todas las columnas
		pinnedColumns = [];
		pinnedColumnsSet.clear();
		// Fijar columnas por defecto
		pinDefaultColumns();
		// Actualizar posiciones
		updatePinnedColumnsPositions();
		// Mostrar notificaciÃ³n
		if (typeof showToast === 'function') {
			showToast('Columnas restablecidas a valores por defecto', 'success');
		}
	} catch (error) {
		console.error('Error en resetColumnVisibility:', error);
		if (typeof showToast === 'function') {
			showToast('Error al restablecer columnas', 'error');
		}
	}
}
// FunciÃ³n para inicializar la visibilidad de columnas segÃºn grupos
function initializeColumnVisibility() {
	try {
		isInitializingColumns = true;

		// ⚡ OPTIMIZACIÓN: Verificar si hay estados guardados antes de aplicar defaults
		// Solo aplicar defaults si no hay estados guardados (evita conflictos)
		const cachedFields = getCachedHiddenFields();
		const hasCached = Array.isArray(cachedFields) && cachedFields.length > 0;

		// Si hay estados guardados, no aplicar defaults (ya se aplicaron en loadPersistedHiddenColumns)
		if (hasCached) {
			// Solo asegurar que los estados se apliquen si columnsData está listo
			if (Array.isArray(columnsData) && columnsData.length > 0) {
				tryApplyHiddenFields();
			}
			return;
		}

		// Solo aplicar defaults si no hay estados guardados
		Object.keys(columnGroups).forEach(groupId => {
			const group = columnGroups[groupId];
			const groupIdNum = parseInt(groupId);
			toggleGroupVisibility(groupIdNum, group.defaultVisible, true);
		});
	} catch (error) {
		console.error('Error al inicializar visibilidad de columnas:', error);
	} finally {
		isInitializingColumns = false;
	}
}
function showColumn(index, silent = false) {
	forEachColumnElement(index, el => {
		el.style.display = '';
		el.style.visibility = '';
	});
	// Remover del array de columnas ocultas
	syncColumnStateSets();
	if (hiddenColumnsSet.has(index)) {
		hiddenColumnsSet.delete(index);
		const idx = hiddenColumns.indexOf(index);
		if (idx > -1) hiddenColumns.splice(idx, 1);
	}
	if (!silent && typeof showToast === 'function') {
		showToast(`Columna visible`, 'info');
	}
	if (!silent) scheduleSaveHiddenColumns();
	if (pinnedColumns.length > 0) updatePinnedColumnsPositions();
}
function hideColumn(index, silent = false) {
	forEachColumnElement(index, el => {
		el.style.display = 'none';
	});
	const hideBtn = $(`th.column-${index} .hide-btn`);
	if (hideBtn) {
		hideBtn.classList.remove('bg-red-500');
		hideBtn.classList.add('bg-red-600');
		hideBtn.title = 'Columna oculta';
	}
	syncColumnStateSets();
	if (!hiddenColumnsSet.has(index)) {
		hiddenColumnsSet.add(index);
		hiddenColumns.push(index);
	}
	if (!silent && typeof showToast === 'function') {
		showToast(`Columna oculta`, 'info');
	}
	if (!silent) scheduleSaveHiddenColumns();
	if (pinnedColumns.length > 0) updatePinnedColumnsPositions();
}
function togglePinColumn(index) {
	syncColumnStateSets();
	const exists = pinnedColumnsSet.has(index);
	// Permitir desfijar incluso columnas requeridas
	if (exists) {
		pinnedColumnsSet.delete(index);
		const idx = pinnedColumns.indexOf(index);
		if (idx > -1) pinnedColumns.splice(idx, 1);
	} else {
		pinnedColumnsSet.add(index);
		pinnedColumns.push(index);
	}
	pinnedColumns.sort((a, b) => a - b);

	// ⚡ OPTIMIZACIÓN: Guardar en caché inmediatamente
	if (ensureColumnCache()) {
		const field = columnsData[index]?.field;
		if (field) {
			const cached = getCachedPinnedFields() || [];
			if (exists) {
				const filtered = cached.filter(f => f !== field);
				setCachedPinnedFields(filtered);
			} else {
				if (!cached.includes(field)) {
					cached.push(field);
					setCachedPinnedFields(cached);
				}
			}
		}
	}

	// BotÃ³n estado
	const pinBtn = $(`th.column-${index} .pin-btn`);
	if (pinBtn) {
		pinBtn.classList.toggle('bg-yellow-700', !exists);
		pinBtn.classList.toggle('bg-yellow-600', exists);
		pinBtn.title = exists ? 'Fijar columna' : 'Desfijar columna';
	}
	updatePinnedColumnsPositions();
}
let lastPinnedColumnsSet = new Set();
function clearPinnedStyles(index) {
	forEachColumnElement(index, el => {
		if (el.tagName === 'TH') {
			el.style.top = '0';
			el.style.position = 'sticky';
		} else {
			el.style.position = '';
			el.style.top = '';
		}
		el.style.left = '';
		el.classList.remove('pinned-column');
	});
}
function applyPinnedStyles(index, left) {
	forEachColumnElement(index, el => {
		el.classList.add('pinned-column');
		el.style.left = left + 'px';
		if (el.tagName === 'TH') {
			el.style.top = '0';
			el.style.position = 'sticky';
		} else {
			el.style.position = 'sticky';
		}
	});
}
function updatePinnedColumnsPositions() {
	syncColumnStateSets();
	const currentPinnedSet = new Set(pinnedColumns);
	lastPinnedColumnsSet.forEach(idx => {
		if (!currentPinnedSet.has(idx)) clearPinnedStyles(idx);
	});
	// Asegurar que el thead sea sticky cuando hay columnas fijadas
	const thead = $('thead');
	if (thead && pinnedColumns.length > 0) {
		thead.style.position = 'sticky';
		thead.style.top = '0';
	}
	// Aplica fijados en orden
	let left = 0;
	pinnedColumns.forEach(idx => {
		const th = $(`th.column-${idx}`);
		if (!th || th.style.display === 'none') {
			clearPinnedStyles(idx);
			return;
		}
		const width = th.getBoundingClientRect().width;
		applyPinnedStyles(idx, left);
		left += width;
	});
	lastPinnedColumnsSet = currentPinnedSet;
}

// ===== Exponer funciones globalmente =====
window.resetColumnVisibility = resetColumnVisibility;
window.openPinColumnsModal = openPinColumnsModal;
window.openHideColumnsModal = openHideColumnsModal;
window.pinColumn = pinColumn;
window.unpinColumn = unpinColumn;
window.hideColumn = hideColumn;
window.showColumn = showColumn;
window.togglePinColumn = togglePinColumn;
window.updatePinnedColumnsPositions = updatePinnedColumnsPositions;
window.initializeColumnVisibility = initializeColumnVisibility;
window.toggleGroupVisibility = toggleGroupVisibility;
window.toggleGroupPin = toggleGroupPin;
window.getColumnGroup = getColumnGroup;
window.getGroupColumns = getGroupColumns;
window.pinDefaultColumns = pinDefaultColumns;
window.loadPersistedHiddenColumns = loadPersistedHiddenColumns;
window.applyDefaultPinsOnce = applyDefaultPinsOnce;
// FunciÃ³n para fijar columnas por defecto
function applyDefaultPinsOnce() {
	if (defaultPinsApplied) return;
	defaultPinsApplied = true;
	pinDefaultColumns();
}
function pinDefaultColumns() {
	ensureColumnCache();
	syncColumnStateSets();

	// ⚡ OPTIMIZACIÓN: Cargar columnas fijadas desde caché primero
	const cachedPinnedFields = getCachedPinnedFields();
	const fieldsToPin = new Set();
	if (cachedPinnedFields && cachedPinnedFields.length > 0) {
		cachedPinnedFields.forEach(field => fieldsToPin.add(field));
	}
	REQUIRED_PINNED_FIELDS.forEach(field => fieldsToPin.add(field));
	fieldsToPin.forEach(field => {
		const index = getColumnIndexByField(field);
		if (index !== -1 && !pinnedColumnsSet.has(index)) {
			pinnedColumnsSet.add(index);
			pinnedColumns.push(index);
		}
	});

	// Ordenar las columnas fijadas
	pinnedColumns.sort((a, b) => a - b);

	// ⚡ OPTIMIZACIÓN: Actualizar posiciones inmediatamente si el DOM está listo
	// Solo usar requestAnimationFrame si es necesario
	if (document.readyState === 'complete' || document.readyState === 'interactive') {
		updatePinnedColumnsPositions();
	} else {
		requestAnimationFrame(updatePinnedColumnsPositions);
	}
}
// ⚡ OPTIMIZACIÓN: loadPersistedHiddenColumns() ahora se llama desde main.blade.php
// después de DOMContentLoaded para evitar conflictos con initializeColumnVisibility()
// No se ejecuta aquí para evitar duplicados





