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

