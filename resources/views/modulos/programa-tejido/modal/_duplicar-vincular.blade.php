{{-- Funciones específicas para duplicar y vincular telares --}}
{{-- NOTA: Este archivo se incluye dentro de un bloque <script>, NO agregar etiquetas <script> aquí --}}

// ===== Funciones de Duplicar/Vincular =====

// Función para actualizar todos los selects de telar en la tabla de destinos (solo modo duplicar)
function actualizarSelectsTelares(preseleccionarPrimero = false) {
	if (getModoActual() !== 'duplicar') {
		return;
	}
	const telarSelects = document.querySelectorAll('select[name="telar-destino[]"]');
	telarSelects.forEach((select, idx) => {
		const valorActual = select.value;
		const telarOriginal = select.dataset?.telarActual || '';
		// Para el primer select, preseleccionar el telar actual si se indica
		const valorPreseleccionar = (idx === 0 && preseleccionarPrimero)
			? (valorActual || telarOriginal || telarActual)
			: valorActual;

		// Solo reconstruir si hay telares disponibles
		if (telaresDisponibles.length > 0) {
			select.innerHTML = '<option value="">Seleccionar...</option>';
			telaresDisponibles.forEach(t => {
				const option = document.createElement('option');
				option.value = t;
				option.textContent = t;
				if (t == valorPreseleccionar) {
					option.selected = true;
				}
				select.appendChild(option);
			});
		}
	});
}

// Función para cargar telares por salón (modo duplicar)
function cargarTelaresPorSalon(salon, preseleccionarTelar = false) {
	if (!salon) {
		telaresDisponibles = [];
		actualizarSelectsTelares(false);
		if (typeof recomputeState === 'function') {
			recomputeState();
		}
		return;
	}

	fetch('/programa-tejido/telares-by-salon?salon_tejido_id=' + encodeURIComponent(salon))
		.then(response => response.json())
		.then(data => {
			telaresDisponibles = Array.isArray(data) ? data : [];
			// Actualizar los selects de la tabla de destinos solo en modo duplicar
			actualizarSelectsTelares(preseleccionarTelar);
			if (typeof recomputeState === 'function') {
				recomputeState();
			}
		})
		.catch(() => {
			telaresDisponibles = [];
			actualizarSelectsTelares(false);
			if (typeof recomputeState === 'function') {
				recomputeState();
			}
		});
}

// Función para agregar fila en modo duplicar
function agregarFilaDuplicar() {
	const tbody = document.getElementById('telar-pedido-body');
	if (!tbody) return;

	const selectSalon = document.getElementById('swal-salon');
	const salonActualLocal = selectSalon?.value || '';

	const newRow = document.createElement('tr');
	newRow.className = 'telar-row border-t border-gray-200';

	// Crear el select con las opciones de telares disponibles del salón seleccionado
	let telarOptionsHTML = '<option value="">Seleccionar...</option>';
	if (typeof telaresDisponibles !== 'undefined' && telaresDisponibles.length > 0) {
		telaresDisponibles.forEach(t => {
			telarOptionsHTML += '<option value="' + t + '">' + t + '</option>';
		});
	}

	const pedidoOriginal = document.getElementById('pedido-original')?.value || '';

	newRow.innerHTML =
		'<td class="p-2 border-r border-gray-200 hidden">' +
			'<input type="hidden" name="salon-destino[]" value="' + salonActualLocal + '">' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200">' +
			'<select name="telar-destino[]" class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 telar-destino-select">' +
				telarOptionsHTML +
			'</select>' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200 pedido-tempo-cell">' +
			'<input type="number" name="pedido-tempo-destino[]" value="' + pedidoOriginal + '" placeholder=""' +
				' class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200">' +
			'<input type="number" name="porcentaje-segundos-destino[]" value="0" placeholder="0.00" step="0.01" min="0"' +
				' class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200 produccion-cell">' +
			'<input type="number" name="pedido-destino[]" placeholder=""' +
				' class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200 saldo-total-cell hidden">' +
			'<input type="text" value="" readonly' +
				' class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200">' +
			'<input type="text" name="observaciones-destino[]" placeholder="Observaciones..."' +
				' class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">' +
		'</td>' +
		'<td class="p-2 text-center w-10">' +
			'<button type="button" class="btn-remove-row text-red-500 hover:text-red-700 transition-colors" title="Eliminar fila">' +
				'<i class="fas fa-times"></i>' +
			'</button>' +
		'</td>';
	tbody.appendChild(newRow);

	newRow.querySelector('.btn-remove-row').addEventListener('click', () => {
		newRow.remove();
		if (typeof recomputeState === 'function') {
			recomputeState();
		}
	});

	const telarSelect = newRow.querySelector('select[name="telar-destino[]"]');
	const pedidoInput = newRow.querySelector('input[name="pedido-destino[]"]');
	if (telarSelect && typeof recomputeState === 'function') {
		telarSelect.addEventListener('change', recomputeState);
	}
	if (pedidoInput && typeof recomputeState === 'function') {
		pedidoInput.addEventListener('input', recomputeState);
	}

	// Agregar listeners para cálculo automático
	if (typeof agregarListenersCalculoAutomatico === 'function') {
		agregarListenersCalculoAutomatico(newRow);
	}
	if (typeof aplicarVisibilidadColumnas === 'function') {
		aplicarVisibilidadColumnas(true);
	}
}
