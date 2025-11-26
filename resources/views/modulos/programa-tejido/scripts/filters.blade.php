// ===== Filtros =====
function renderFilterModalContent() {
	const options = columnsData.map(c => ({
		field: c.field,
		label: c.label
	}));
	const filtrosHTML = filters.length ?
		`<div class="mb-4 p-3 bg-gray-50 rounded-lg">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Filtros Activos:</h4>
                <div class="space-y-1">
					${filters.map((f,i)=>`
                        <div class="flex items-center justify-between bg-white p-2 rounded border">
							<span class="text-xs">${f.column}: ${f.value}</span>
							<button onclick="removeFilter(${i})" class="text-red-500 hover:text-red-700 text-xs">×</button>
                        </div>
                    `).join('')}
                </div>
		   </div>` :
		'';

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
                        Agregar Otro Filtro
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
				if (filters.some(f => f.column === col && f.value === val)) return Swal.showValidationMessage('Este filtro ya está activo');

				filters.push({
					column: col,
					value: val
				});
				applyFilters();
				showToast('Filtro agregado correctamente', 'success');
				Swal.update({
					html: renderFilterModalContent()
				});
				openFilterModal(); // re-abre para re-inyectar listeners
			});
		},
		preConfirm: () => {
			const col = $('#filtro-columna').value;
			const val = $('#filtro-valor').value;
			if (!col || !val) {
				Swal.showValidationMessage('Selecciona columna y valor');
				return false;
			}
			if (filters.some(f => f.column === col && f.value === val)) {
				Swal.showValidationMessage('Este filtro ya está activo');
				return false;
			}
			return {
				column: col,
				value: val
			};
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
	Swal.update({
		html: renderFilterModalContent()
	});
}

function applyFilters() {
	let rows = allRows.slice();
	if (filters.length) {
		// Pre-calcular valores en minúsculas para mejor rendimiento
		const filterValues = filters.map(f => ({
			column: f.column,
			value: f.value.toLowerCase()
		}));

		rows = rows.filter(tr => {
			return filterValues.every(f => {
				const cell = tr.querySelector(`[data-column="${f.column}"]`);
				return cell ? cell.textContent.toLowerCase().includes(f.value) : false;
			});
		});
	}
	const tb = tbodyEl();
	tb.innerHTML = '';

	// Crear fragmento para mejor rendimiento en inserción masiva
	const fragment = document.createDocumentFragment();
	for (let i = 0; i < rows.length; i++) {
		const r = rows[i];
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
	clearRowCache(); // Limpiar cache después de filtrar
	if (inlineEditMode) applyInlineModeToRows();

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

	// Usar fragmento para mejor rendimiento
	const fragment = document.createDocumentFragment();
	for (let i = 0; i < allRows.length; i++) {
		const r = allRows[i];
		const realIndex = i;

		r.classList.remove('bg-blue-500', 'text-white', 'hover:bg-blue-50');
		r.classList.add('hover:bg-blue-50');
		const tds = r.querySelectorAll('td');
		for (let j = 0; j < tds.length; j++) {
			const td = tds[j];
			td.classList.remove('text-white', 'text-gray-700');
		}

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
	clearRowCache(); // Limpiar cache después de reset
	if (inlineEditMode) applyInlineModeToRows();

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









