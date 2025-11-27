// ===== Definición de grupos de columnas =====
// Orden: Estado (sin grupo) -> Grupo 1 -> Grupo 2 -> Grupo 3 -> Grupo 4 -> Grupo 5 -> Grupo 6 -> Grupo 7
const columnGroups = {
	1: {
		name: 'Grupo 1 - Cuenta/Salón/Telar',
		fields: ['CuentaRizo', 'SalonTejidoId', 'NoTelarId', 'Ultimo', 'CambioHilo'],
		defaultVisible: false // OCULTO por defecto
	},
	2: {
		name: 'Grupo 2 - Máquina/Hilo',
		fields: ['Maquina', 'Ancho', 'EficienciaSTD', 'VelocidadSTD', 'FibraRizo', 'CalibrePie2'],
		defaultVisible: true // VISIBLE por defecto
	},
	3: {
		name: 'Grupo 3 - Jornada/Clave',
		fields: ['CalendarioId', 'TamanoClave', 'NoExisteBase'],
		defaultVisible: false // OCULTO por defecto
	},
	4: {
		name: 'Grupo 4 - Producto/Pedido',
		fields: ['NombreProducto', 'SaldoPedido', 'ProgramarProd', 'NoProduccion', 'Programado', 'NombreProyecto', 'AplicacionId', 'Observaciones', 'TipoPedido'],
		defaultVisible: true // VISIBLE por defecto
	},
	5: {
		name: 'Grupo 5 - Producción',
		fields: ['NoTiras', 'Peine', 'LargoCrudo', 'PesoCrudo', 'Luchaje', 'CalibreTrama2', 'DobladilloId', 'DiasEficiencia', 'ProdKgDia', 'StdDia'],
		defaultVisible: true // VISIBLE por defecto
	},
	6: {
		name: 'Grupo 6 - Pasadas/Colores',
		fields: ['PasadasTrama', 'PasadasComb1', 'PasadasComb2', 'PasadasComb3', 'PasadasComb4', 'PasadasComb5', 'AnchoToalla', 'ColorTrama', 'CalibreComb1', 'NombreCC1', 'CalibreComb2', 'NombreCC2', 'CalibreComb3', 'NombreCC3', 'CalibreComb4', 'NombreCC4', 'CalibreComb5', 'NombreCC5', 'MedidaPlano', 'CuentaPie', 'NombreCPie', 'PesoGRM2'],
		defaultVisible: false // OCULTO por defecto
	},
	7: {
		name: 'Grupo 7 - Fechas',
		fields: ['FechaInicio', 'FechaFinal', 'EntregaProduc', 'EntregaPT', 'EntregaCte', 'PTvsCte'],
		defaultVisible: true // VISIBLE por defecto
	},
	8: {
		name: 'Grupo 8 - Otras columnas',
		fields: ['CalibreRizo2', 'ItemId', 'InventSizeId', 'Rasurado', 'TotalPedido', 'Produccion', 'SaldoMarbete', 'OrdCompartida', 'FlogsId', 'CustName', 'FibraTrama', 'CodColorTrama', 'FibraComb1', 'CodColorComb1', 'FibraComb2', 'CodColorComb2', 'FibraComb3', 'CodColorComb3', 'FibraComb4', 'CodColorComb4', 'FibraComb5', 'CodColorComb5', 'CodColorCtaPie', 'ProdKgDia2', 'StdToaHra', 'DiasJornada', 'HorasProd', 'StdHrsEfect'],
		defaultVisible: false // OCULTO por defecto
	}
};

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
		Object.keys(columnGroups).forEach(groupId => {
			const group = columnGroups[groupId];
			const groupIdNum = parseInt(groupId);
			toggleGroupVisibility(groupIdNum, group.defaultVisible, true);
		});
	} catch (error) {
		console.error('Error al inicializar visibilidad de columnas:', error);
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

