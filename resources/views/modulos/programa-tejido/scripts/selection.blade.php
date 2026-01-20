// ===== Seleccion de filas - OPTIMIZADO =====
const getSelectableRows = () => (allRows.length > 0 ? allRows : $$('.selectable-row'));
const isInlineEditActive = () => inlineEditMode || window.inlineEditMode;

function updateRowSelectionStyles(row, isSelected, inlineActive) {
	const esRepaso = row.dataset.esRepaso === '1';
	row.classList.toggle('bg-blue-700', isSelected && !esRepaso);
	row.classList.toggle('bg-blue-400', isSelected && esRepaso);
	row.classList.toggle('text-white', isSelected);
	row.classList.toggle('hover:bg-blue-50', !isSelected);

	if (!inlineActive && !row.querySelector('.inline-edit-input')) {
		row.classList.remove('bg-yellow-100');
	}

	const tds = row.querySelectorAll('td');
	for (let i = 0; i < tds.length; i++) {
		const td = tds[i];
		td.classList.toggle('text-white', isSelected);
		td.classList.toggle('text-gray-700', !isSelected);
	}
}

function setButtonDisabled(id, disabled) {
	const el = document.getElementById(id);
	if (el) el.disabled = disabled;
}

function updateSelectionButtons({ canEdit, canDelete, canViewLines }) {
	setButtonDisabled('btn-editar-programa', !canEdit);
	setButtonDisabled('layoutBtnEditar', !canEdit);
	setButtonDisabled('btn-eliminar-programa', !canDelete);
	setButtonDisabled('layoutBtnEliminar', !canDelete);
	setButtonDisabled('btn-ver-lineas', !canViewLines);
	setButtonDisabled('layoutBtnVerLineas', !canViewLines);
}

function clearSelectionStyles() {
	const rows = getSelectableRows();
	const inlineActive = isInlineEditActive();
	for (let i = 0; i < rows.length; i++) {
		updateRowSelectionStyles(rows[i], false, inlineActive);
	}
}

function selectRow(rowElement, rowIndex) {
	try {
		// Cancelar cualquier timeout de amarillo temporal
		if (typeof window.yellowHighlightTimeout !== 'undefined' && window.yellowHighlightTimeout) {
			clearTimeout(window.yellowHighlightTimeout);
			window.yellowHighlightTimeout = null;
		}

		// Toggle si ya estaba seleccionada
		if (selectedRowIndex === rowIndex && rowElement.classList.contains('bg-blue-700')) {
			return deselectRow();
		}

		// Cerrar edicion inline de la fila anterior si existe y desactivar modo inline
		if (selectedRowIndex >= 0 && selectedRowIndex !== rowIndex) {
			const rows = getSelectableRows();
			const previousRow = rows[selectedRowIndex];
			if (previousRow && typeof window.closeInlineEditForRow === 'function') {
				window.closeInlineEditForRow(previousRow);
			}
			// Desactivar modo inline cuando se selecciona otra fila
			if (typeof window.toggleInlineEditMode === 'function' && isInlineEditActive()) {
				window.toggleInlineEditMode();
			}
		}

		clearSelectionStyles();

		updateRowSelectionStyles(rowElement, true, isInlineEditActive());

		selectedRowIndex = rowIndex;
		window.selectedRowIndex = rowIndex; // Sincronizar con window

		// Disparar evento personalizado para notificar cambio de seleccion
		document.dispatchEvent(new CustomEvent('pt:selection-changed', {
			detail: { rowIndex, rowElement }
		}));

		// Verificar si el registro esta en proceso (una sola vez)
		const enProceso = rowElement.querySelector('[data-column="EnProceso"]');
		const estaEnProceso = enProceso && enProceso.querySelector('input[type="checkbox"]')?.checked;

		updateSelectionButtons({
			canEdit: true,
			canDelete: !estaEnProceso,
			canViewLines: true
		});
	} catch (e) {
		// Error silencioso para mejor rendimiento
	}
}

function deselectRow() {
	try {
		// Cerrar edicion inline de la fila seleccionada si existe
		if (selectedRowIndex >= 0) {
			const rows = getSelectableRows();
			const currentRow = rows[selectedRowIndex];
			if (currentRow && typeof window.closeInlineEditForRow === 'function') {
				window.closeInlineEditForRow(currentRow);
			}
		}

		clearSelectionStyles();

		selectedRowIndex = -1;
		window.selectedRowIndex = -1; // Sincronizar con window

		// Disparar evento personalizado para notificar cambio de seleccion
		document.dispatchEvent(new CustomEvent('pt:selection-changed', {
			detail: { rowIndex: -1, rowElement: null }
		}));

		updateSelectionButtons({
			canEdit: false,
			canDelete: false,
			canViewLines: false
		});
	} catch (e) {
		// Error silencioso para mejor rendimiento
	}
}

// Exponer funciones globalmente
window.selectRow = selectRow;
window.deselectRow = deselectRow;
