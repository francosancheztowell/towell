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

// ===== Persistencia de columnas ocultas (usa hiddenColumns global de state.blade) =====
const COLUMN_STATE_ENDPOINT = '/programa-tejido/columnas';
const CURRENT_USER_ID = (window?.App?.user?.id) ?? (window?.authUserId) ?? null;
let saveHiddenColumnsTimer = null;
let isInitializingColumns = false; // evita guardar mientras cargamos estados
let tableLoadingContainer = null;
let pendingHiddenFields = null; // campos que deben ocultarse cuando columnsData esté listo

async function loadPersistedHiddenColumns() {
	try {
		setTableLoading(true);

		isInitializingColumns = true;
		const res = await fetch(COLUMN_STATE_ENDPOINT, {
			method: 'GET',
			headers: { 'Accept': 'application/json' },
			credentials: 'same-origin'
		});
		if (!res.ok) return;
		const data = await res.json();
		if (!data.success || !data.data) return;

		// Guardar pendientes y aplicar cuando columnsData esté listo
		pendingHiddenFields = Object.entries(data.data || {})
			.filter(([, hidden]) => hidden)
			.map(([field]) => field);
		tryApplyHiddenFields();
	} catch (e) {
		console.warn('No se pudo cargar estado de columnas', e);
	} finally {
		isInitializingColumns = false;
		// si no se aplicó aún, se liberará en tryApplyHiddenFields cuando columnsData esté listo
	}
}

function scheduleSaveHiddenColumns() {
	if (isInitializingColumns) return;
	if (saveHiddenColumnsTimer) clearTimeout(saveHiddenColumnsTimer);
	saveHiddenColumnsTimer = setTimeout(saveHiddenColumns, 400);
}

async function saveHiddenColumns() {
	try {
		const columnas = {};
		// Enviar estado completo: true = oculta, false = visible
		columnsData.forEach((col, idx) => {
			if (col?.field) columnas[col.field] = hiddenColumns.includes(idx);
		});

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

// Función para obtener el índice de una columna por su campo
function getColumnIndexByField(field) {
	return columnsData.findIndex(col => col.field === field);
}

// Función para obtener el grupo de una columna
function getColumnGroup(field) {
	for (const [groupId, group] of Object.entries(columnGroups)) {
		if (group.fields.includes(field)) {
			return parseInt(groupId);
		}
	}
	return null;
}

// Función para obtener todas las columnas de un grupo
function getGroupColumns(groupId) {
	const group = columnGroups[groupId];
	if (!group) return [];
	return group.fields.map(field => getColumnIndexByField(field)).filter(idx => idx !== -1);
}

function tryApplyHiddenFields() {
	try {
		if (!Array.isArray(columnsData) || columnsData.length === 0) {
			// reintentar hasta que exista columnsData
			return setTimeout(tryApplyHiddenFields, 80);
		}
		if (!Array.isArray(hiddenColumns)) hiddenColumns = [];
		if (!pendingHiddenFields || pendingHiddenFields.length === 0) {
			pinDefaultColumns();
			updatePinnedColumnsPositions();
			setTableLoading(false);
			return;
		}

		// Aplicar estados de oculto según pendientes
		pendingHiddenFields.forEach(field => {
			const idx = getColumnIndexByField(field);
			if (idx !== -1 && !hiddenColumns.includes(idx)) {
				hideColumn(idx, true);
			}
		});

		// Fijar columnas por defecto después de aplicar estados
		pinDefaultColumns();

		updatePinnedColumnsPositions();
		pendingHiddenFields = null;
		setTableLoading(false);
	} catch (e) {
		console.warn('No se pudo aplicar columnas ocultas', e);
		setTableLoading(false);
	}
}

function setTableLoading(isLoading) {
	try {
		const table = document.getElementById('mainTable');
		if (!table) return;
		if (!tableLoadingContainer) {
			tableLoadingContainer = table.closest('.table-responsive') || table;
		}
		const c = tableLoadingContainer;
		if (isLoading) {
			if (!c.querySelector('.pt-columns-loading')) {
				const overlay = document.createElement('div');
				overlay.className = 'pt-columns-loading';
				overlay.style.position = 'absolute';
				overlay.style.inset = '0';
			overlay.style.display = 'flex';
			overlay.style.alignItems = 'center';
			overlay.style.justifyContent = 'center';
			overlay.style.backgroundColor = 'transparent'; // no cubras la tabla
			overlay.style.zIndex = '999';
			overlay.style.pointerEvents = 'none'; // no bloquees clicks
			overlay.innerHTML = `
				<div class="flex items-center gap-2 px-3 py-1.5 bg-white rounded shadow text-gray-700 text-sm">
					<svg class="animate-spin h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
					  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
					  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
					</svg>
					<span class="font-medium whitespace-nowrap">Cargando columnas...</span>
				</div>
			`;
				// asegurar contenedor relativo
				if (getComputedStyle(c).position === 'static') {
					c.style.position = 'relative';
				}
				c.appendChild(overlay);
			}
		} else {
			const overlay = c.querySelector('.pt-columns-loading');
			if (overlay) overlay.remove();
		}
	} catch (e) {
		console.warn('No se pudo ajustar visibilidad de tabla', e);
	}
}

// Función para verificar si un grupo está visible
function isGroupVisible(groupId) {
	const groupColumns = getGroupColumns(groupId);
	if (groupColumns.length === 0) return false;
	// Un grupo está visible si al menos una columna está visible
	return groupColumns.some(idx => !hiddenColumns.includes(idx));
}

// Función para mostrar/ocultar un grupo completo
function toggleGroupVisibility(groupId, visible, silent = false) {
	const groupColumns = getGroupColumns(groupId);
	groupColumns.forEach(index => {
		if (visible) {
			showColumn(index, silent);
		} else {
			hideColumn(index, silent);
		}
	});
	if (!silent) scheduleSaveHiddenColumns();
}

// Función para fijar/desfijar un grupo completo
function toggleGroupPin(groupId, pin) {
	const groupColumns = getGroupColumns(groupId);
	groupColumns.forEach(index => {
		if (pin) {
			pinColumn(index);
		} else {
			unpinColumn(index);
		}
	});
}

// ===== Controles de columnas desde navbar =====
function openPinColumnsModal() {
	const columns = getColumnsData();
	const pinnedColumns = getPinnedColumns();

	// Agrupar columnas por grupo
	const groupedColumns = {};
	const ungroupedColumns = [];

	columns.forEach((col, index) => {
		const groupId = getColumnGroup(col.field);
		if (groupId) {
			if (!groupedColumns[groupId]) {
				groupedColumns[groupId] = {
					group: columnGroups[groupId],
					columns: []
				};
			}
			groupedColumns[groupId].columns.push({ col, index });
		} else {
			ungroupedColumns.push({ col, index });
		}
	});

	let html = `
		<div class="text-left">
			<p class="text-sm text-gray-600 mb-4">Selecciona las columnas o grupos que deseas fijar a la izquierda de la tabla:</p>
			<div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-4">
	`;

	// Mostrar grupos primero
	Object.keys(groupedColumns).sort().forEach(groupId => {
		const groupData = groupedColumns[groupId];
		const groupColumns = getGroupColumns(parseInt(groupId));
		const allPinned = groupColumns.every(idx => pinnedColumns.includes(idx));
		const somePinned = groupColumns.some(idx => pinnedColumns.includes(idx));

		html += `
			<div class="border border-gray-200 rounded-lg p-2">
				<div class="flex items-center justify-between mb-2 pb-2 border-b border-gray-200">
					<span class="text-sm font-semibold text-gray-800">${groupData.group.name}</span>
					<label class="flex items-center gap-2 cursor-pointer">
						<input type="checkbox" ${allPinned ? 'checked' : ''}
							   class="w-4 h-4 text-yellow-600 bg-gray-100 border-gray-300 rounded focus:ring-yellow-500 group-toggle-pin"
							   data-group-id="${groupId}"
							   ${somePinned && !allPinned ? 'indeterminate' : ''}>
						<span class="text-xs text-gray-600">Fijar grupo</span>
					</label>
				</div>
				<div class="pl-4 space-y-1">
		`;

		groupData.columns.forEach(({ col, index }) => {
			const isPinned = pinnedColumns.includes(index);
			html += `
				<div class="flex items-center justify-between p-1 hover:bg-gray-50 rounded">
					<span class="text-xs text-gray-700 ml-2">${col.label}</span>
					<input type="checkbox" ${isPinned ? 'checked' : ''}
						   class="w-4 h-4 text-yellow-600 bg-gray-100 border-gray-300 rounded focus:ring-yellow-500 column-toggle-pin"
						   data-column-index="${index}"
						   data-group-id="${groupId}">
				</div>
			`;
		});

		html += `
				</div>
			</div>
		`;
	});

	// Mostrar columnas sin grupo
	if (ungroupedColumns.length > 0) {
		html += `
			<div class="border border-gray-200 rounded-lg p-2">
				<div class="text-sm font-semibold text-gray-800 mb-2 pb-2 border-b border-gray-200">Otras columnas</div>
				<div class="pl-4 space-y-1">
		`;
		ungroupedColumns.forEach(({ col, index }) => {
			const isPinned = pinnedColumns.includes(index);
			html += `
				<div class="flex items-center justify-between p-1 hover:bg-gray-50 rounded">
					<span class="text-xs text-gray-700 ml-2">${col.label}</span>
					<input type="checkbox" ${isPinned ? 'checked' : ''}
						   class="w-4 h-4 text-yellow-600 bg-gray-100 border-gray-300 rounded focus:ring-yellow-500 column-toggle-pin"
						   data-column-index="${index}">
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

	Swal.fire({
		title: 'Fijar Columnas',
		html: html,
		showCancelButton: true,
		confirmButtonText: 'Aplicar',
		cancelButtonText: 'Cancelar',
		confirmButtonColor: '#f59e0b',
		cancelButtonColor: '#6b7280',
		width: '600px',
		didOpen: () => {
			// Event listeners para checkboxes individuales
			document.querySelectorAll('#swal2-html-container .column-toggle-pin').forEach(checkbox => {
				checkbox.addEventListener('change', function() {
					const columnIndex = parseInt(this.dataset.columnIndex);
					if (this.checked) {
						pinColumn(columnIndex);
					} else {
						unpinColumn(columnIndex);
					}
					// Actualizar estado del grupo
					const groupId = this.dataset.groupId;
					if (groupId) {
						updateGroupCheckboxState(groupId, 'pin');
					}
				});
			});

			// Event listeners para checkboxes de grupo
			document.querySelectorAll('#swal2-html-container .group-toggle-pin').forEach(checkbox => {
				checkbox.addEventListener('change', function() {
					const groupId = parseInt(this.dataset.groupId);
					toggleGroupPin(groupId, this.checked);
					// Actualizar todos los checkboxes individuales del grupo
					document.querySelectorAll(`#swal2-html-container .column-toggle-pin[data-group-id="${groupId}"]`).forEach(cb => {
						cb.checked = this.checked;
					});
				});
			});
		}
	});
}

function openHideColumnsModal() {
	const columns = getColumnsData();
	const hiddenColumns = getHiddenColumns();

	// Agrupar columnas por grupo
	const groupedColumns = {};
	const ungroupedColumns = [];

	columns.forEach((col, index) => {
		const groupId = getColumnGroup(col.field);
		if (groupId) {
			if (!groupedColumns[groupId]) {
				groupedColumns[groupId] = {
					group: columnGroups[groupId],
					columns: []
				};
			}
			groupedColumns[groupId].columns.push({ col, index });
		} else {
			ungroupedColumns.push({ col, index });
		}
	});

	let html = `
		<div class="text-left">
			<p class="text-sm text-gray-600 mb-4">Selecciona las columnas o grupos que deseas ocultar:</p>
			<div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-4">
	`;

	// Mostrar grupos primero
	Object.keys(groupedColumns).sort().forEach(groupId => {
		const groupData = groupedColumns[groupId];
		const groupColumns = getGroupColumns(parseInt(groupId));
		const allHidden = groupColumns.every(idx => hiddenColumns.includes(idx));
		const someHidden = groupColumns.some(idx => hiddenColumns.includes(idx));

		html += `
			<div class="border border-gray-200 rounded-lg p-2">
				<div class="flex items-center justify-between mb-2 pb-2 border-b border-gray-200">
					<span class="text-sm font-semibold text-gray-800">${groupData.group.name}</span>
					<label class="flex items-center gap-2 cursor-pointer">
						<input type="checkbox" ${allHidden ? 'checked' : ''}
							   class="w-4 h-4 text-red-600 bg-gray-100 border-gray-300 rounded focus:ring-red-500 group-toggle-hide"
							   data-group-id="${groupId}"
							   ${someHidden && !allHidden ? 'indeterminate' : ''}>
						<span class="text-xs text-gray-600">Ocultar grupo</span>
					</label>
				</div>
				<div class="pl-4 space-y-1">
		`;

		groupData.columns.forEach(({ col, index }) => {
			const isHidden = hiddenColumns.includes(index);
			html += `
				<div class="flex items-center justify-between p-1 hover:bg-gray-50 rounded">
					<span class="text-xs text-gray-700 ml-2">${col.label}</span>
					<input type="checkbox" ${isHidden ? 'checked' : ''}
						   class="w-4 h-4 text-red-600 bg-gray-100 border-gray-300 rounded focus:ring-red-500 column-toggle-hide"
						   data-column-index="${index}"
						   data-group-id="${groupId}">
				</div>
			`;
		});

		html += `
				</div>
			</div>
		`;
	});

	// Mostrar columnas sin grupo
	if (ungroupedColumns.length > 0) {
		html += `
			<div class="border border-gray-200 rounded-lg p-2">
				<div class="text-sm font-semibold text-gray-800 mb-2 pb-2 border-b border-gray-200">Otras columnas</div>
				<div class="pl-4 space-y-1">
		`;
		ungroupedColumns.forEach(({ col, index }) => {
			const isHidden = hiddenColumns.includes(index);
			html += `
				<div class="flex items-center justify-between p-1 hover:bg-gray-50 rounded">
					<span class="text-xs text-gray-700 ml-2">${col.label}</span>
					<input type="checkbox" ${isHidden ? 'checked' : ''}
						   class="w-4 h-4 text-red-600 bg-gray-100 border-gray-300 rounded focus:ring-red-500 column-toggle-hide"
						   data-column-index="${index}">
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

	Swal.fire({
		title: 'Ocultar Columnas',
		html: html,
		showCancelButton: true,
		confirmButtonText: 'Aplicar',
		cancelButtonText: 'Cancelar',
		confirmButtonColor: '#ef4444',
		cancelButtonColor: '#6b7280',
		width: '600px',
		didOpen: () => {
			// Event listeners para checkboxes individuales
			document.querySelectorAll('#swal2-html-container .column-toggle-hide').forEach(checkbox => {
				checkbox.addEventListener('change', function() {
					const columnIndex = parseInt(this.dataset.columnIndex);
					if (this.checked) {
						hideColumn(columnIndex);
					} else {
						showColumn(columnIndex);
					}
					// Actualizar estado del grupo
					const groupId = this.dataset.groupId;
					if (groupId) {
						updateGroupCheckboxState(groupId, 'hide');
					}
				});
			});

			// Event listeners para checkboxes de grupo
			document.querySelectorAll('#swal2-html-container .group-toggle-hide').forEach(checkbox => {
				checkbox.addEventListener('change', function() {
					const groupId = parseInt(this.dataset.groupId);
					toggleGroupVisibility(groupId, !this.checked);
					// Actualizar todos los checkboxes individuales del grupo
					document.querySelectorAll(`#swal2-html-container .column-toggle-hide[data-group-id="${groupId}"]`).forEach(cb => {
						cb.checked = this.checked;
					});
				});
			});
		}
	});
}

// Función auxiliar para actualizar el estado del checkbox de grupo
function updateGroupCheckboxState(groupId, type) {
	const groupColumns = getGroupColumns(groupId);
	const prefix = type === 'pin' ? 'pin' : 'hide';
	const array = type === 'pin' ? pinnedColumns : hiddenColumns;

	const allChecked = groupColumns.every(idx => array.includes(idx));
	const someChecked = groupColumns.some(idx => array.includes(idx));

	const groupCheckbox = document.querySelector(`.group-toggle-${prefix}[data-group-id="${groupId}"]`);
	if (groupCheckbox) {
		groupCheckbox.checked = allChecked;
		groupCheckbox.indeterminate = someChecked && !allChecked;
	}
}

function getColumnsData() {
	return columnsData.map(c => ({
		label: c.label,
		field: c.field
	}));
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
		pinnedColumns.sort((a, b) => a - b);
		updatePinnedColumnsPositions();
	}
}

function unpinColumn(index) {
	pinnedColumns = pinnedColumns.filter(i => i !== index);
	updatePinnedColumnsPositions();
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

		// Aplicar visibilidad según grupos por defecto
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

		// Fijar columnas por defecto
		pinDefaultColumns();

		// Actualizar posiciones
		updatePinnedColumnsPositions();

		// Mostrar notificación
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

// Función para inicializar la visibilidad de columnas según grupos
function initializeColumnVisibility() {
	try {
		isInitializingColumns = true;
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
	$$(`.column-${index}`).forEach(el => {
		el.style.display = '';
		el.style.visibility = '';
	});
	// Remover del array de columnas ocultas
	if (Array.isArray(hiddenColumns)) {
		const idx = hiddenColumns.indexOf(index);
		if (idx > -1) {
			hiddenColumns.splice(idx, 1);
		}
	}
	if (!silent && typeof showToast === 'function') {
		showToast(`Columna visible`, 'info');
	}
	if (!silent) scheduleSaveHiddenColumns();
}

function hideColumn(index, silent = false) {
	$$(`.column-${index}`).forEach(el => el.style.display = 'none');
	const hideBtn = $(`th.column-${index} .hide-btn`);
	if (hideBtn) {
		hideBtn.classList.remove('bg-red-500');
		hideBtn.classList.add('bg-red-600');
		hideBtn.title = 'Columna oculta';
	}
	if (!hiddenColumns.includes(index)) hiddenColumns.push(index);
	if (!silent && typeof showToast === 'function') {
		showToast(`Columna oculta`, 'info');
	}
	if (!silent) scheduleSaveHiddenColumns();
}

function togglePinColumn(index) {
	const exists = pinnedColumns.includes(index);
	if (exists) pinnedColumns = pinnedColumns.filter(i => i !== index);
	else pinnedColumns.push(index);
	pinnedColumns.sort((a, b) => a - b);

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

// Función para fijar columnas por defecto
function pinDefaultColumns() {
	const defaultPinnedFields = ['Ultimo', 'CambioHilo', 'Maquina', 'NombreProducto','Ancho'];

	defaultPinnedFields.forEach(field => {
		const index = getColumnIndexByField(field);
		if (index !== -1 && !pinnedColumns.includes(index)) {
			pinnedColumns.push(index);
		}
	});

	// Ordenar las columnas fijadas
	pinnedColumns.sort((a, b) => a - b);

	// Actualizar posiciones después de un breve delay para asegurar que el DOM esté listo
	setTimeout(() => {
		updatePinnedColumnsPositions();
	}, 100);
}

// Cargar estados persistidos al inicio
setTableLoading(true);
loadPersistedHiddenColumns();

// Fijar columnas por defecto después de que se carguen los datos
document.addEventListener('DOMContentLoaded', function() {
	// Esperar a que columnsData esté disponible
	const checkAndPin = () => {
		if (Array.isArray(columnsData) && columnsData.length > 0) {
			pinDefaultColumns();
		} else {
			setTimeout(checkAndPin, 50);
		}
	};
	checkAndPin();
});

