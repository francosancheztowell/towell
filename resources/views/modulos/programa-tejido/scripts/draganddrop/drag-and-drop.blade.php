// ==========================
// Drag & Drop - Config / Log
// ==========================

const DD_LOG_ENABLED = true;

function ddLog(step, payload) {
	if (!DD_LOG_ENABLED) return;
	if (payload !== undefined) {
		console.log('[DragDrop]', step, payload);
	} else {
		console.log('[DragDrop]', step);
	}
}

// ==========================
// Estado global Drag & Drop
// ==========================

let dragStartPosition = null;
let originalOrderIds = [];
let draggedRowOriginalTelarIndex = null; // índice original dentro del telar (DOM al iniciar drag)
let dragBlockedReason = null;           // Motivo de bloqueo (drop no permitido)
let dragDropPerformed = false;          // Si se ejecutó handleDrop

// ==========================
// Helpers de fila / cache
// ==========================

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

function getRowSalon(row) {
	if (!rowCache.has(row)) {
		getRowTelar(row);
	}
	return rowCache.get(row).salon;
}

function getRowCambioHilo(row) {
	if (!rowCache.has(row)) {
		getRowTelar(row);
	}
	return rowCache.get(row).cambioHilo;
}

function clearRowCache() {
	rowCache.clear();
}

// ==========================
// Helpers de selección visual
// ==========================

function clearSelectionStyles() {
	const tb = tbodyEl();
	if (!tb) return;

	const rows = tb.querySelectorAll('.selectable-row');
	rows.forEach(row => {
		row.classList.remove(
			'bg-blue-500',
			'text-white',
			'selected-row',
			'font-semibold'
		);
	});
}

// ==========================
// Helpers de telar / estado
// ==========================

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

function getRowsByTelar(telarId) {
	return allRows.filter(row => isSameTelar(getRowTelar(row), telarId));
}

function isRowEnProceso(row) {
	const enProcesoCell = row.querySelector('[data-column="EnProceso"]');
	if (!enProcesoCell) return false;

	const checkbox = enProcesoCell.querySelector('input[type="checkbox"]');
	return !!(checkbox && checkbox.checked);
}

// ==========================
// Restaurar orden original
// ==========================

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

		rowMap.forEach((row, id) => {
			if (!originalOrderIds.includes(id)) {
				fragment.appendChild(row);
			}
		});

		tb.innerHTML = '';
		tb.appendChild(fragment);

		allRows = Array.from(tb.querySelectorAll('.selectable-row'));
		clearRowCache();

		ddLog('restoreOriginalOrder: restaurado orden original', {
			totalFilas: allRows.length
		});
	} finally {
		originalOrderIds = [];
	}
}

// ==========================
// Activar / desactivar Drag & Drop
// ==========================

function toggleDragDropMode() {
	dragDropMode = !dragDropMode;

	const btn = $('#btnDragDrop');
	const tb = tbodyEl();
	if (!btn || !tb) return;

	if (dragDropMode) {
		ddLog('toggleDragDropMode: ACTIVADO');

		if (typeof deselectRow === 'function') {
			deselectRow();
		}
		clearSelectionStyles();

		btn.classList.remove('bg-black', 'hover:bg-gray-800', 'focus:ring-gray-500');
		btn.classList.add('bg-gray-400', 'hover:bg-gray-500', 'ring-2', 'ring-gray-300');
		btn.title = 'Desactivar arrastrar filas';

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

		tb.addEventListener('dragover', handleDragOver);
		tb.addEventListener('drop', handleDrop);

		showToast('Modo arrastrar activado<br>Arrastra las filas para reorganizarlas', 'info');
	} else {
		ddLog('toggleDragDropMode: DESACTIVADO');

		btn.classList.remove('bg-gray-400', 'hover:bg-gray-500', 'ring-2', 'ring-gray-300');
		btn.classList.add('bg-black', 'hover:bg-gray-800', 'focus:ring-gray-500');
		btn.title = 'Activar/Desactivar arrastrar filas';

		allRows = Array.from(tb.querySelectorAll('.selectable-row'));
		clearRowCache();

		const rows = allRows;

		for (let i = 0; i < rows.length; i++) {
			const row = rows[i];
			const realIndex = i;

			row.draggable = false;
			row.classList.remove('cursor-move', 'cursor-not-allowed');
			row.style.opacity = '';
			row.onclick = null;

			row.removeEventListener('dragstart', handleDragStart);
			row.removeEventListener('dragover', handleDragOver);
			row.removeEventListener('drop', handleDrop);
			row.removeEventListener('dragend', handleDragEnd);

			row.onclick = () => selectRow(row, realIndex);
		}

		tb.removeEventListener('dragover', handleDragOver);
		tb.removeEventListener('drop', handleDrop);

		showToast('Modo arrastrar desactivado', 'info');
	}
}

// ==========================
// Inicio de drag
// ==========================

function handleDragStart(e) {
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
	draggedRowOriginalTelarIndex = null; // reset
	dragBlockedReason = null;
	dragDropPerformed = false;

	const tbSnapshot = tbodyEl();

	if (tbSnapshot) {
		const snapshotRows = Array.from(tbSnapshot.querySelectorAll('.selectable-row'));
		originalOrderIds = snapshotRows.map(r => r.getAttribute('data-id') || '');

		// índice ORIGINAL dentro del telar (orden DOM antes de mover)
		const sameTelar = snapshotRows.filter(r =>
			isSameTelar(getRowTelar(r), draggedRowTelar)
		);
		draggedRowOriginalTelarIndex = sameTelar.indexOf(this);
	}

	if (typeof deselectRow === 'function') {
		deselectRow();
	}
	clearSelectionStyles();

	this.classList.add('dragging');
	this.style.opacity = '0.4';

	e.dataTransfer.effectAllowed = 'move';
	e.dataTransfer.setData('text/html', this.innerHTML);
	e.dataTransfer.setData('text/plain', draggedRow.getAttribute('data-id'));

	lastDragOverTime = 0;

	ddLog('handleDragStart', {
		registroId: this.getAttribute('data-id') || null,
		telar: draggedRowTelar,
		salon: draggedRowSalon,
		indexDOM: this.rowIndex,
		indexTelarOriginal: draggedRowOriginalTelarIndex
	});
}

// ==========================
// Drag over (feedback + DOM)
// ==========================

function handleDragOver(e) {
	e.preventDefault();
	e.stopPropagation();

	// reset del bloqueo en cada tick: sólo vale la posición actual
	dragBlockedReason = null;

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

		if (!closestRow) return false;
		targetRow = closestRow;
	}

	if (targetRow === draggedRow) return false;

	const now = performance.now();
	if (now - lastDragOverTime < 16) return false;
	lastDragOverTime = now;

	const targetTelar = getRowTelar(targetRow);

	ddLog('handleDragOver', {
		targetId: targetRow.getAttribute('data-id') || null,
		targetTelar,
		draggedTelar: draggedRowTelar
	});

	if (!isSameTelar(draggedRowTelar, targetTelar)) {
		const tb = tbodyEl();
		if (tb) {
			const allRowsInDOM = Array.from(tb.querySelectorAll('.selectable-row'));
			const draggedRowIndex = allRowsInDOM.indexOf(draggedRow);

			if (draggedRowIndex !== -1) {
				const targetRowsInDOM = allRowsInDOM.filter(row =>
					row !== draggedRow && isSameTelar(getRowTelar(row), targetTelar)
				);

				const targetRowRect = targetRow.getBoundingClientRect();
				const mouseY = e.clientY;
				const isBeforeTarget = mouseY < (targetRowRect.top + targetRowRect.height / 2);

				const targetRowIndexInTelar = targetRowsInDOM.indexOf(targetRow);
				let posicionObjetivo = 0;

				if (targetRowIndexInTelar !== -1) {
					posicionObjetivo = isBeforeTarget ? targetRowIndexInTelar : targetRowIndexInTelar + 1;
				} else {
					for (let i = 0; i < draggedRowIndex; i++) {
						if (
							allRowsInDOM[i] !== draggedRow &&
							isSameTelar(getRowTelar(allRowsInDOM[i]), targetTelar)
						) {
							posicionObjetivo++;
						}
					}
				}

				let ultimoEnProcesoIndex = -1;
				for (let i = 0; i < targetRowsInDOM.length; i++) {
					if (isRowEnProceso(targetRowsInDOM[i])) {
						ultimoEnProcesoIndex = i;
					}
				}

				if (ultimoEnProcesoIndex !== -1 && posicionObjetivo <= ultimoEnProcesoIndex) {
					dragBlockedReason = 'No se puede colocar antes de un registro en proceso en el telar destino.';
					e.dataTransfer.dropEffect = 'none';
					if (
						targetRow.classList &&
						!targetRow.classList.contains('drop-not-allowed')
					) {
						targetRow.classList.add('drop-not-allowed');
						targetRow.classList.remove('drag-over', 'drag-over-warning');
					}
					return false;
				}
			}
		}
	}

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

// ==========================
// Determinar elemento siguiente
// ==========================

function getDragAfterElement(container, y) {
	const draggableElements = allRows.filter(
		row => !row.classList.contains('dragging')
	);

	if (draggableElements.length === 0) return null;

	let closest = { offset: Number.NEGATIVE_INFINITY, element: null };

	for (let i = 0; i < draggableElements.length; i++) {
		const child = draggableElements[i];
		const box = child.getBoundingClientRect();
		const offset = y - box.top - box.height / 2;

		if (offset < 0 && offset > closest.offset) {
			closest = { offset, element: child };
		}
	}

	return closest.element;
}

// ==========================
// Bloques verticales por telar (no usado, pero disponible)
// ==========================

function computeTelarBlocks(allRowsInDOM) {
	const blocks = new Map();

	for (const row of allRowsInDOM) {
		const telar = getRowTelar(row);
		if (!telar) continue;

		const rect = row.getBoundingClientRect();
		const existing = blocks.get(telar);

		if (!existing) {
			blocks.set(telar, {
				top: rect.top,
				bottom: rect.bottom
			});
		} else {
			if (rect.top < existing.top) existing.top = rect.top;
			if (rect.bottom > existing.bottom) existing.bottom = rect.bottom;
		}
	}

	return blocks;
}

// ==========================
// Calcular posición objetivo en telar
// ==========================

function calcularPosicionObjetivo(targetTelar, targetRowElement = null) {
	const tb = tbodyEl();
	if (!tb) return 0;

	const allRowsInDOM = Array.from(tb.querySelectorAll('.selectable-row'));

	const targetRowsOriginal = allRowsInDOM.filter(row => {
		const rowTelar = getRowTelar(row);
		return isSameTelar(rowTelar, targetTelar) && row !== draggedRow;
	});

	const draggedRowIndex = allRowsInDOM.indexOf(draggedRow);

	let targetPosition = targetRowsOriginal.length;

	if (targetRowElement) {
		const targetRowIndex = allRowsInDOM.indexOf(targetRowElement);

		if (targetRowIndex !== -1) {
			let posicion = 0;
			for (let i = 0; i < targetRowIndex; i++) {
				const row = allRowsInDOM[i];
				if (row !== draggedRow && isSameTelar(getRowTelar(row), targetTelar)) {
					posicion++;
				}
			}

			if (draggedRowIndex < targetRowIndex) {
				targetPosition = posicion;
			} else {
				targetPosition = posicion + 1;
			}

			if (targetRowsOriginal.length === 1 && draggedRowIndex > targetRowIndex) {
				targetPosition = 1;
			}
		}
	} else if (draggedRowIndex !== -1) {
		let posicion = 0;
		for (let i = 0; i < draggedRowIndex; i++) {
			const row = allRowsInDOM[i];
			if (row !== draggedRow && isSameTelar(getRowTelar(row), targetTelar)) {
				posicion++;
			}
		}
		targetPosition = posicion;

		if (targetRowsOriginal.length === 1 && posicion === 1) {
			targetPosition = 1;
		}
	}

	let ultimoEnProcesoIndex = -1;
	for (let i = 0; i < targetRowsOriginal.length; i++) {
		if (isRowEnProceso(targetRowsOriginal[i])) {
			ultimoEnProcesoIndex = i;
		}
	}

	if (ultimoEnProcesoIndex !== -1) {
		const posicionMinima = ultimoEnProcesoIndex + 1;
		if (targetPosition < posicionMinima) {
			targetPosition = posicionMinima;
		}
	}

	targetPosition = Math.max(0, Math.min(targetPosition, targetRowsOriginal.length));

	return targetPosition;
}

// ==========================
// Drop: usando fila anterior/siguiente en el DOM
// ==========================

async function handleDrop(e) {
	e.stopPropagation();
	e.preventDefault();

	dragDropPerformed = true;

	const draggedId = draggedRow ? draggedRow.getAttribute('data-id') : null;

	ddLog('handleDrop_start', {
		draggedId,
		draggedTelar: draggedRowTelar,
		draggedSalon: draggedRowSalon
	});

	if (!draggedRow) {
		ddLog('handleDrop: draggedRow es null');
		showToast('Error: No se encontró el registro arrastrado', 'error');
		restoreOriginalOrder();
		return false;
	}

	const registroId =
		e.dataTransfer && e.dataTransfer.getData
			? (e.dataTransfer.getData('text/plain') || draggedRow.getAttribute('data-id'))
			: draggedRow.getAttribute('data-id');

	if (!registroId) {
		showToast('Error: No se pudo obtener el ID del registro', 'error');
		restoreOriginalOrder();
		return false;
	}

	const tb = tbodyEl();
	if (!tb) {
		restoreOriginalOrder();
		return false;
	}

	const allRowsInDOM = Array.from(tb.querySelectorAll('.selectable-row'));
	const draggedIndex = allRowsInDOM.indexOf(draggedRow);

	if (draggedIndex === -1) {
		Swal.fire({
			icon: 'error',
			title: 'Error',
			text: 'No se pudo encontrar el registro en la tabla',
			confirmButtonColor: '#dc2626'
		});
		restoreOriginalOrder();
		return false;
	}

	const prevRow = draggedIndex > 0 ? allRowsInDOM[draggedIndex - 1] : null;
	const nextRow =
		draggedIndex < allRowsInDOM.length - 1
			? allRowsInDOM[draggedIndex + 1]
			: null;

	const prevTelar = prevRow ? getRowTelar(prevRow) : null;
	const nextTelar = nextRow ? getRowTelar(nextRow) : null;
	const prevSalon = prevRow ? getRowSalon(prevRow) : null;
	const nextSalon = nextRow ? getRowSalon(nextRow) : null;

	ddLog('handleDrop_neighbors', {
		draggedIndex,
		draggedId,
		draggedTelar: draggedRowTelar,
		prevId: prevRow ? prevRow.getAttribute('data-id') : null,
		prevTelar,
		nextId: nextRow ? nextRow.getAttribute('data-id') : null,
		nextTelar
	});

	let targetTelar = draggedRowTelar;
	let targetSalon = draggedRowSalon;
	let targetRow = null;

	// ================================
	// DECISIÓN DE TELAR
	// Preferir permanecer en el mismo telar mientras el puntero esté dentro
	// del bloque visual del telar original (con un margen de tolerancia).
	// Solo cambiar de telar si el puntero cae fuera de ese bloque y la fila
	// adyacente más cercana es de otro telar.
	// ================================

	const yPointer = typeof e.clientY === 'number' ? e.clientY : null;
	let forceSameTelar = false;
	let nearestSameTelarRow = null;
	let blockBottom = null;
	let blockTop = null;
	let rowAbovePointer = null;
	let lastSameTelarRow = null;

	if (yPointer !== null) {
		const sameTelarRows = allRowsInDOM.filter(
			row => isSameTelar(getRowTelar(row), draggedRowTelar)
		);

		let minTop = Infinity;
		let maxBottom = -Infinity;

		sameTelarRows.forEach(row => {
			const rect = row.getBoundingClientRect();
			minTop = Math.min(minTop, rect.top);
			maxBottom = Math.max(maxBottom, rect.bottom);
		});

		// Si no hay más filas de ese telar, usar el propio draggedRow como referencia
		if (sameTelarRows.length === 0 && draggedRow) {
			const rect = draggedRow.getBoundingClientRect();
			minTop = rect.top;
			maxBottom = rect.bottom;
		}

		// Guardar referencia del último row del mismo telar para drops al final del bloque
		if (sameTelarRows.length > 0) {
			lastSameTelarRow = sameTelarRows[sameTelarRows.length - 1];
		}

		const MARGEN_MISMO_TELAR = 28; // px de tolerancia para permanecer en el mismo telar
		if (minTop !== Infinity && maxBottom !== -Infinity) {
			blockTop = minTop;
			blockBottom = maxBottom;
			if (yPointer >= minTop - MARGEN_MISMO_TELAR && yPointer <= maxBottom + MARGEN_MISMO_TELAR) {
				forceSameTelar = true;

				// Buscar la fila del mismo telar más cercana al puntero para ubicar posición
				let bestDist = Infinity;
				for (const row of sameTelarRows) {
					const rect = row.getBoundingClientRect();
					const centerY = rect.top + rect.height / 2;
					const dist = Math.abs(centerY - yPointer);
					if (dist < bestDist) {
						bestDist = dist;
						nearestSameTelarRow = row;
					}
				}
			}
		}

		ddLog('handleDrop_bounds', {
			yPointer,
			minTop,
			maxBottom,
			forceSameTelar,
			nearestSameTelarRowId: nearestSameTelarRow ? nearestSameTelarRow.getAttribute('data-id') : null
		});
	}

	// PRIORIDAD 0: si la fila más cercana por encima del puntero es del mismo telar, forzar mismo telar
	if (yPointer !== null) {
		let closestAbove = null;
		let closestAboveDist = Infinity;
		for (const row of allRowsInDOM) {
			if (row === draggedRow) continue;
			const rect = row.getBoundingClientRect();
			const centerY = rect.top + rect.height / 2;
			if (centerY <= yPointer) {
				const dist = yPointer - centerY;
				if (dist < closestAboveDist) {
					closestAboveDist = dist;
					closestAbove = row;
				}
			}
		}
		rowAbovePointer = closestAbove;

		if (rowAbovePointer && isSameTelar(getRowTelar(rowAbovePointer), draggedRowTelar)) {
			forceSameTelar = true;
			nearestSameTelarRow = rowAbovePointer;
			ddLog('handleDrop_decision', {
				tipo: 'mismo_telar_por_rowAbovePointer',
				targetRowId: rowAbovePointer.getAttribute('data-id'),
				yPointer
			});
		}
	}

	// PRIORIDAD 1: si el puntero está debajo del último row del mismo telar pero dentro de un margen, seguir en el mismo telar
	if (!forceSameTelar && yPointer !== null && lastSameTelarRow) {
		const rectLast = lastSameTelarRow.getBoundingClientRect();
		const MARGEN_FIN_BANDA = 40; // px adicionales debajo del último row del telar para seguir considerándolo
		if (yPointer <= rectLast.bottom + MARGEN_FIN_BANDA) {
			forceSameTelar = true;
			nearestSameTelarRow = lastSameTelarRow;
			ddLog('handleDrop_decision', {
				tipo: 'mismo_telar_por_fin_de_banda',
				targetRowId: lastSameTelarRow.getAttribute('data-id'),
				yPointer,
				rectBottom: rectLast.bottom
			});
		}
	}

	if (forceSameTelar) {
		targetTelar = draggedRowTelar;
		targetSalon = draggedRowSalon;
		targetRow = nearestSameTelarRow || prevRow || nextRow || null;
		dragBlockedReason = null; // se trata como movimiento interno

		ddLog('handleDrop_decision', {
			tipo: 'mismo_telar_por_banda',
			targetTelar,
			targetSalon,
			targetRowId: targetRow ? targetRow.getAttribute('data-id') : null
		});
	} else if (prevRow && isSameTelar(prevTelar, draggedRowTelar)) {
		//  Hay fila arriba y es del mismo telar → reordenar dentro del telar
		targetTelar = draggedRowTelar;
		targetSalon = draggedRowSalon;
		targetRow = prevRow;

		ddLog('handleDrop_decision', {
			tipo: 'mismo_telar_prevRow',
			baseRowId: prevRow.getAttribute('data-id'),
			targetTelar,
			targetSalon
		});
	} else if (!prevRow && nextRow && isSameTelar(nextTelar, draggedRowTelar)) {
		//  No hay fila arriba, pero la de abajo es del mismo telar → reordenar dentro del telar
		targetTelar = draggedRowTelar;
		targetSalon = draggedRowSalon;
		targetRow = nextRow;

		ddLog('handleDrop_decision', {
			tipo: 'mismo_telar_sinPrev_conNext',
			baseRowId: nextRow.getAttribute('data-id'),
			targetTelar,
			targetSalon
		});
	} else if (prevRow) {
		//  El de arriba es de OTRO telar → posible cambio de telar
		// Solo permitir cambio si el puntero está claramente FUERA del bloque del telar actual
		const fueraBloque =
			blockBottom !== null && blockTop !== null
				? (yPointer > blockBottom + 10 || yPointer < blockTop - 10)
				: true;
		if (!fueraBloque) {
			targetTelar = draggedRowTelar;
			targetSalon = draggedRowSalon;
			targetRow = prevRow; // seguirá reordenando dentro

			ddLog('handleDrop_decision', {
				tipo: 'mismo_telar_forzado_por_borde',
				targetTelar,
				targetSalon,
				targetRowId: prevRow.getAttribute('data-id'),
				yPointer,
				blockBottom,
				blockTop
			});
		} else {
		targetTelar = prevTelar;
		targetSalon = prevSalon || draggedRowSalon;
		targetRow = prevRow;

		ddLog('handleDrop_decision', {
			tipo: 'cambio_telar_prevRow',
			baseRowId: prevRow.getAttribute('data-id'),
			targetTelar,
			targetSalon,
			yPointer,
			blockBottom,
			blockTop
		});
		}
	} else if (nextRow) {
		// Caso: sin arriba pero con abajo, y abajo de otro telar → cambio de telar
		targetTelar = nextTelar;
		targetSalon = nextSalon || draggedRowSalon;
		targetRow = nextRow;

		ddLog('handleDrop_decision', {
			tipo: 'cambio_telar_nextRow',
			baseRowId: nextRow.getAttribute('data-id'),
			targetTelar,
			targetSalon
		});
	} else {
		// Solo hay una fila en la tabla (el arrastrado) → no hay cambio real
		targetTelar = draggedRowTelar;
		targetSalon = draggedRowSalon;
		targetRow = null;

		ddLog('handleDrop_decision', {
			tipo: 'sin_vecinos_fallback_mismo_telar',
			targetTelar,
			targetSalon
		});
	}

	const esMismoTelar = isSameTelar(draggedRowTelar, targetTelar);

	ddLog('handleDrop_finalContext', {
		registroId,
		draggedTelar: draggedRowTelar,
		targetTelar,
		targetSalon,
		esMismoTelar
	});

	// 1) Si el telar final es el mismo → reordenamos prioridad dentro del telar
	if (esMismoTelar) {
		if (dragBlockedReason) {
			ddLog('handleDrop_mismoTelar_blocked', { motivo: dragBlockedReason });
			showToast(dragBlockedReason, 'error');
			restoreOriginalOrder();
			return false;
		}

		await procesarMovimientoMismoTelar(registroId);
		return false;
	}

	// 2) Cambio real de telar: si había bloqueo (por EnProceso), lo respetamos
	if (dragBlockedReason) {
		ddLog('handleDrop_cambioTelar_blocked', { motivo: dragBlockedReason });
		showToast(dragBlockedReason, 'error');
		restoreOriginalOrder();
		return false;
	}

	// 3) Cambio de telar → calcular posición destino dentro del telar target
	let targetPosition = calcularPosicionObjetivo(targetTelar, targetRow);

	const targetRows = getRowsByTelar(targetTelar).filter(row => row !== draggedRow);

	if (targetRows.length) {
		let minAllowedPosition = 0;

		for (let i = 0; i < targetRows.length; i++) {
			if (isRowEnProceso(targetRows[i])) {
				minAllowedPosition = i + 1;
			}
		}

		if (targetPosition < minAllowedPosition) {
			targetPosition = minAllowedPosition;
			showToast('Se colocó después del registro en proceso del telar destino', 'info');
		}

		if (targetRows.length === 1) {
			if (isRowEnProceso(targetRows[0])) {
				targetPosition = 1;
			} else {
				targetPosition = Math.max(0, Math.min(targetPosition, 1));
			}
		}
	} else {
		targetPosition = 0;
	}

	targetPosition = Math.max(0, targetPosition);

	ddLog('handleDrop_beforeCambioTelar', {
		registroId,
		targetTelar,
		targetSalon,
		targetPosition
	});

	await procesarMovimientoOtroTelar(registroId, targetSalon, targetTelar, targetPosition);
	return false;
}

// ==========================
// Movimiento dentro del mismo telar
// ==========================

async function procesarMovimientoMismoTelar(registroId) {
	const tb = tbodyEl();
	if (!tb) {
		restoreOriginalOrder();
		return;
	}

	const allRowsSameTelar = allRows.filter(row =>
		isSameTelar(getRowTelar(row), draggedRowTelar)
	);
	const allRowsInDOM = Array.from(tb.querySelectorAll('.selectable-row'));

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
		.sort(
			(a, b) =>
				(positionMap.get(a) ?? Infinity) - (positionMap.get(b) ?? Infinity)
		);

	if (!positionMap.has(draggedRow)) {
		rowsOrdered.push(draggedRow);
	}

	const nuevaPosicion = rowsOrdered.indexOf(draggedRow);

	if (nuevaPosicion === -1) {
		showToast('Error al calcular la nueva posición', 'error');
		restoreOriginalOrder();
		return;
	}

	const posicionOriginal =
		typeof draggedRowOriginalTelarIndex === 'number' &&
		draggedRowOriginalTelarIndex >= 0
			? draggedRowOriginalTelarIndex
			: null;

	ddLog('procesarMovimientoMismoTelar: índices', {
		registroId,
		telar: draggedRowTelar,
		indexOriginalTelar: posicionOriginal,
		nuevaPosicion,
		totalFilasTelar: allRowsSameTelar.length
	});

	// Si tenemos índice original válido y coincide con la nueva posición, es NO-OP
	if (posicionOriginal !== null && posicionOriginal === nuevaPosicion) {
		showToast('El registro ya está en esa posición', 'info');
		restoreOriginalOrder();
		return;
	}

	showLoading();

	try {
		const response = await fetch(
			`/planeacion/programa-tejido/${registroId}/prioridad/mover`,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN':
						document.querySelector('meta[name="csrf-token"]').content
				},
				body: JSON.stringify({
					new_position: nuevaPosicion
				})
			}
		);

		const data = await response.json();
		hideLoading();

		ddLog('procesarMovimientoMismoTelar: respuesta backend', data);

		if (data.success) {
			originalOrderIds = [];
			updateTableAfterDragDrop(data.detalles, registroId);
			showToast(
				` Prioridad actualizada<br>${data.cascaded_records || 0} registro(s) recalculado(s)`,
				'success'
			);

			const movedRow = document.querySelector(
				`.selectable-row[data-id="${registroId}"]`
			);

			if (movedRow) {
				const realIndex = allRows.indexOf(movedRow);
				if (realIndex !== -1) {
					setTimeout(() => {
						// Mientras el modo drag&drop está activo, NO pintamos selección azul
						if (!dragDropMode && typeof selectRow === 'function') {
							selectRow(movedRow, realIndex);
						}
						movedRow.scrollIntoView({
							behavior: 'smooth',
							block: 'center'
						});
					}, 300);
				}
			}
		} else {
			showToast(data.message || 'No se pudo actualizar la prioridad', 'error');
			restoreOriginalOrder();
		}
	} catch (error) {
		hideLoading();
		showToast('Ocurrió un error al procesar la solicitud', 'error');
		restoreOriginalOrder();
	}
}

// ==========================
// Movimiento a otro telar / salón
// ==========================

async function procesarMovimientoOtroTelar(registroId, nuevoSalon, nuevoTelar, targetPosition) {
	showLoading();

	try {
		const verificacionResponse = await fetch(
			`/planeacion/programa-tejido/${registroId}/verificar-cambio-telar`,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN':
						document.querySelector('meta[name="csrf-token"]').content
				},
				body: JSON.stringify({
					nuevo_salon: nuevoSalon,
					nuevo_telar: nuevoTelar
				})
			}
		);

		if (!verificacionResponse.ok) {
			hideLoading();
			await verificacionResponse.text();
			Swal.fire({
				icon: 'error',
				title: 'Error de validación',
				text: 'No se pudo validar el cambio de telar. Por favor, intenta de nuevo.',
				confirmButtonColor: '#dc2626'
			});
			restoreOriginalOrder();
			return;
		}

		const verificacion = await verificacionResponse.json();
		hideLoading();

		ddLog('procesarMovimientoOtroTelar: verificación backend', verificacion);

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

		const cambioResponse = await fetch(
			`/planeacion/programa-tejido/${registroId}/cambiar-telar`,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN':
						document.querySelector('meta[name="csrf-token"]').content
				},
				body: JSON.stringify({
					nuevo_salon: nuevoSalon,
					nuevo_telar: nuevoTelar,
					target_position: targetPosition
				})
			}
		);

		if (!cambioResponse.ok) {
			hideLoading();
			showToast('No se pudo cambiar de telar', 'error');
			restoreOriginalOrder();
			return;
		}

		const cambio = await cambioResponse.json();
		hideLoading();

		ddLog('procesarMovimientoOtroTelar: respuesta backend', cambio);

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

		originalOrderIds = [];

		Swal.fire({
			icon: 'success',
			title: '¡Cambio realizado!',
			text: cambio.message || 'Telar actualizado correctamente',
			confirmButtonColor: '#3b82f6',
			timer: 2000,
			showConfirmButton: false
		});

		sessionStorage.setItem(
			'priorityChangeMessage',
			cambio.message || 'Telar actualizado correctamente'
		);
		sessionStorage.setItem('priorityChangeType', 'success');

		if (cambio.registro_id) {
			sessionStorage.setItem('scrollToRegistroId', cambio.registro_id);
			sessionStorage.setItem('selectRegistroId', cambio.registro_id);
		}

		setTimeout(() => {
			window.location.href = '/planeacion/programa-tejido';
		}, 500);
	} catch (error) {
		console.error('Error en procesarMovimientoOtroTelar:', error);
		hideLoading();
		Swal.fire({
			icon: 'error',
			title: 'Error',
			text:
				'Ocurrió un error al procesar el cambio de telar: ' +
				(error.message || 'Error desconocido'),
			confirmButtonColor: '#dc2626'
		});
		restoreOriginalOrder();
	}
}

// ==========================
// Actualizar tabla tras drag&drop
// ==========================

function updateTableAfterDragDrop(detalles, registroMovidoId) {
	if (!detalles || !Array.isArray(detalles)) return;

	ddLog('updateTableAfterDragDrop', {
		registros: detalles.length,
		registroMovidoId
	});

	const formatDate = dateStr => {
		if (!dateStr) return '';
		try {
			const date = new Date(dateStr);
			if (date.getFullYear() > 1970) {
				return (
					date.toLocaleDateString('es-ES', {
						day: '2-digit',
						month: '2-digit',
						year: 'numeric'
					}) +
					' ' +
					date.toLocaleTimeString('es-ES', {
						hour: '2-digit',
						minute: '2-digit'
					})
				);
			}
		} catch (e) {}
		return '';
	};

	const telarAfectado = detalles.length > 0 ? detalles[0].NoTelar : null;

	if (telarAfectado) {
		const rowsSameTelar = allRows.filter(
			row => getRowTelar(row) === telarAfectado
		);
		for (let i = 0; i < rowsSameTelar.length; i++) {
			const row = rowsSameTelar[i];

			const ultimoCell = row.querySelector('[data-column="Ultimo"]');
			if (ultimoCell) ultimoCell.textContent = '';

			const enProcesoCell = row.querySelector('[data-column="EnProceso"]');
			if (enProcesoCell) {
				const checkbox = enProcesoCell.querySelector('input[type="checkbox"]');
				if (checkbox) checkbox.checked = false;
			}
		}
	}

	const rowMap = new Map();
	allRows.forEach(row => {
		const id = row.getAttribute('data-id');
		if (id) rowMap.set(id, row);
	});

	for (let i = 0; i < detalles.length; i++) {
		const detalle = detalles[i];
		const row = rowMap.get(String(detalle.Id));
		if (!row) continue;

		const esRegistroMovido = detalle.Id == registroMovidoId;
		if (esRegistroMovido) {
			row.classList.add('bg-green-50');
			setTimeout(() => row.classList.remove('bg-green-50'), 2000);
		}

		if (detalle.FechaInicio_nueva) {
			const fechaInicioCell = row.querySelector('[data-column="FechaInicio"]');
			if (fechaInicioCell) {
				fechaInicioCell.textContent = formatDate(detalle.FechaInicio_nueva);
				fechaInicioCell.classList.add('bg-yellow-100');
				setTimeout(
					() => fechaInicioCell.classList.remove('bg-yellow-100'),
					1500
				);
			}
		}

		if (detalle.FechaFinal_nueva) {
			const fechaFinalCell = row.querySelector('[data-column="FechaFinal"]');
			if (fechaFinalCell) {
				fechaFinalCell.textContent = formatDate(detalle.FechaFinal_nueva);
				fechaFinalCell.classList.add('bg-yellow-100');
				setTimeout(
					() => fechaFinalCell.classList.remove('bg-yellow-100'),
					1500
				);
			}
		}

		const enProcesoNuevo =
			detalle.EnProceso_nuevo !== undefined
				? detalle.EnProceso_nuevo
				: detalle.EnProceso;

		if (enProcesoNuevo !== undefined) {
			const enProcesoCell = row.querySelector('[data-column="EnProceso"]');
			if (enProcesoCell) {
				const checkbox = enProcesoCell.querySelector('input[type="checkbox"]');
				if (checkbox) {
					checkbox.checked =
						enProcesoNuevo == 1 || enProcesoNuevo === true;
				}
			}
		}

		const ultimoNuevo =
			detalle.Ultimo_nuevo !== undefined
				? detalle.Ultimo_nuevo
				: detalle.Ultimo;

		if (ultimoNuevo !== undefined) {
			const ultimoCell = row.querySelector('[data-column="Ultimo"]');
			if (ultimoCell) {
				const valor =
					ultimoNuevo === '1' ||
					ultimoNuevo === 'UL' ||
					ultimoNuevo === 1
						? '1'
						: '';
				ultimoCell.textContent = valor;

				if (valor === '1') {
					ultimoCell.classList.add('bg-yellow-100');
					setTimeout(
						() => ultimoCell.classList.remove('bg-yellow-100'),
						1500
					);
				}
			}
		}

		const cambioHiloNuevo =
			detalle.CambioHilo_nuevo !== undefined
				? detalle.CambioHilo_nuevo
				: detalle.CambioHilo;

		if (cambioHiloNuevo !== undefined) {
			const cambioHiloCell = row.querySelector('[data-column="CambioHilo"]');
			if (cambioHiloCell) {
				cambioHiloCell.textContent = cambioHiloNuevo;
				cambioHiloCell.classList.add('bg-yellow-100');
				setTimeout(
					() => cambioHiloCell.classList.remove('bg-yellow-100'),
					1500
				);
			}
		}
	}

	const tb = tbodyEl();
	if (tb) {
		allRows = Array.from(tb.querySelectorAll('.selectable-row'));
		clearRowCache();
	}
}

// ==========================
// Fin de drag
// ==========================

function handleDragEnd() {
	const draggedId = this.getAttribute('data-id') || null;

	this.classList.remove('dragging');
	this.style.opacity = '';

	const rows = allRows.length > 0 ? allRows : $$('.selectable-row');
	for (let i = 0; i < rows.length; i++) {
		const row = rows[i];
		row.classList.remove('drag-over', 'drag-over-warning', 'drop-not-allowed');
	}

	draggedRow = null;
	draggedRowTelar = null;
	draggedRowSalon = null;
	draggedRowCambioHilo = null;
	dragStartPosition = null;
	lastDragOverTime = 0;
	draggedRowOriginalTelarIndex = null;
	dragDropPerformed = dragDropPerformed || false;

	// Si no hubo drop y había bloqueo, informar al usuario y restaurar
	if (!dragDropPerformed && dragBlockedReason) {
		showToast(dragBlockedReason, 'error');
		restoreOriginalOrder();
	}
	dragBlockedReason = null;

	ddLog('handleDragEnd: fin drag', { draggedRowId: draggedId });
}
