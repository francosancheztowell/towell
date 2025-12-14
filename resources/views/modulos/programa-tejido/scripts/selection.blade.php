// ===== Selección de filas - OPTIMIZADO =====
function selectRow(rowElement, rowIndex) {
	try {
		// Toggle si ya estaba seleccionada
		if (selectedRowIndex === rowIndex && rowElement.classList.contains('bg-blue-500')) {
			return deselectRow();
		}

		// Limpiar selección previa (optimizado con allRows)
		const rows = allRows.length > 0 ? allRows : $$('.selectable-row');
		for (let i = 0; i < rows.length; i++) {
			const row = rows[i];
			row.classList.remove('bg-blue-500', 'text-white');
			row.classList.add('hover:bg-blue-50');
			const tds = row.querySelectorAll('td');
			for (let j = 0; j < tds.length; j++) {
				const td = tds[j];
				td.classList.remove('text-white');
				td.classList.add('text-gray-700');
			}
		}

		// Seleccionar actual
		rowElement.classList.add('bg-blue-500', 'text-white');
		rowElement.classList.remove('hover:bg-blue-50');
		const tds = rowElement.querySelectorAll('td');
		for (let i = 0; i < tds.length; i++) {
			const td = tds[i];
			td.classList.add('text-white');
			td.classList.remove('text-gray-700');
		}

		selectedRowIndex = rowIndex;
		window.selectedRowIndex = rowIndex; // Sincronizar con window

		// Disparar evento personalizado para notificar cambio de selección
		document.dispatchEvent(new CustomEvent('pt:selection-changed', {
			detail: { rowIndex, rowElement }
		}));

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
	} catch (e) {
		// Error silencioso para mejor rendimiento
	}
}

function deselectRow() {
	try {
		// Optimizado con allRows
		const rows = allRows.length > 0 ? allRows : $$('.selectable-row');
		for (let i = 0; i < rows.length; i++) {
			const row = rows[i];
			row.classList.remove('bg-blue-500', 'text-white');
			row.classList.add('hover:bg-blue-50');
			const tds = row.querySelectorAll('td');
			for (let j = 0; j < tds.length; j++) {
				const td = tds[j];
				td.classList.remove('text-white');
				td.classList.add('text-gray-700');
			}
		}
		selectedRowIndex = -1;
		window.selectedRowIndex = -1; // Sincronizar con window

		// Disparar evento personalizado para notificar cambio de selección
		document.dispatchEvent(new CustomEvent('pt:selection-changed', {
			detail: { rowIndex: -1, rowElement: null }
		}));

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
	} catch (e) {
		// Error silencioso para mejor rendimiento
	}
}

// Exponer funciones globalmente
window.selectRow = selectRow;
window.deselectRow = deselectRow;










