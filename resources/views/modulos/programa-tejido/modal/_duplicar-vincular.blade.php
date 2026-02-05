{{-- Funciones específicas para duplicar y vincular telares --}}
{{-- NOTA: Este archivo se incluye dentro de un bloque <script>, NO agregar etiquetas <script> aquí --}}

// ===== Funciones de Duplicar/Vincular =====
const telaresPorSalonCacheDuplicar = new Map();

function obtenerTelaresPorSalonCacheDuplicar(salon) {
	const key = String(salon || '');
	if (telaresPorSalonCacheDuplicar.has(key)) {
		return Promise.resolve(telaresPorSalonCacheDuplicar.get(key));
	}
	return fetch('/programa-tejido/telares-by-salon?salon_tejido_id=' + encodeURIComponent(key), {
		headers: { 'Accept': 'application/json' }
	})
		.then(r => r.json())
		.then(data => {
			const lista = Array.isArray(data) ? data : [];
			telaresPorSalonCacheDuplicar.set(key, lista);
			return lista;
		})
		.catch(() => {
			telaresPorSalonCacheDuplicar.set(key, []);
			return [];
		});
}


// Función para actualizar todos los selects de telar en la tabla de destinos (solo modo duplicar)
function actualizarSelectsTelares(preseleccionarPrimero = false) {
	if (getModoActual() !== 'duplicar') {
		return;
	}
	const telarSelects = document.querySelectorAll('select[name="telar-destino[]"]');
	const salonActual = document.getElementById('swal-salon')?.value || '';
	const buildValue = (typeof window.buildTelarValue === 'function')
		? window.buildTelarValue
		: (salon, telar) => telar;

	telarSelects.forEach((select, idx) => {
		const valorActual = select.value;
		const telarOriginal = select.dataset?.telarActual || '';
		// Para el primer select, preseleccionar el telar actual si se indica
		const valorPreseleccionar = (idx === 0 && preseleccionarPrimero)
			? (valorActual || buildValue(salonActual, (telarOriginal || telarActual)))
			: valorActual;

		// Solo reconstruir si hay telares disponibles
		if (telaresDisponibles.length > 0) {
			select.innerHTML = '<option value="">Seleccionar...</option>';
			telaresDisponibles.forEach(t => {
				const option = document.createElement('option');
				const isObj = t && typeof t === 'object';
				const optionValue = isObj ? (t.value || '') : t;
				// Solo mostrar el número del telar, sin el salón
				const optionLabel = isObj ? (t.telar || t.value || '') : t;
				const optionSalon = isObj ? (t.salon || '') : '';
				option.value = optionValue;
				option.textContent = optionLabel;
				if (optionSalon) option.dataset.salon = optionSalon;
				if (optionValue == valorPreseleccionar) {
					option.selected = true;
				}
				select.appendChild(option);
			});
		}
		if (typeof window.parseTelarValue === 'function') {
			const parsed = window.parseTelarValue(select.value);
			const hiddenSalon = select.closest('tr')?.querySelector('input[name="salon-destino[]"]');
			if (parsed.salon && hiddenSalon) hiddenSalon.value = parsed.salon;
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

	obtenerTelaresPorSalonCacheDuplicar(salon)
		.then(lista => {
			telaresDisponibles = lista.map(t => ({
				salon,
				telar: t,
				value: (typeof window.buildTelarValue === 'function') ? window.buildTelarValue(salon, t) : t,
				label: t // Solo mostrar el numero del telar, sin el salon
			}));
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
			const isObj = t && typeof t === 'object';
			const optionValue = isObj ? (t.value || '') : t;
			// Solo mostrar el número del telar, sin el salón
			const optionLabel = isObj ? (t.telar || t.value || '') : t;
			telarOptionsHTML += '<option value="' + optionValue + '">' + optionLabel + '</option>';
		});
	}

	const pedidoOriginal = document.getElementById('pedido-original')?.value || '';
	const claveModelo = document.getElementById('swal-claveModelo')?.value || '';
	const producto = document.getElementById('swal-producto')?.value || '';
	const flog = document.getElementById('swal-flog')?.value || '';
	const descripcion = document.getElementById('swal-descripcion')?.value || '';
	const aplicacion = document.getElementById('swal-aplicacion')?.value || '';

	// Obtener opciones de aplicación disponibles
	let aplicacionOptionsHTML = '<option value="">Seleccionar...</option>';
	const selectAplicacionGlobal = document.getElementById('swal-aplicacion');
	if (selectAplicacionGlobal && selectAplicacionGlobal.options && selectAplicacionGlobal.options.length > 0) {
		Array.from(selectAplicacionGlobal.options).forEach(option => {
			if (option.value) {
				aplicacionOptionsHTML += '<option value="' + option.value + '"' + (option.value === aplicacion ? ' selected' : '') + '>' + option.textContent + '</option>';
			}
		});
	} else if (Array.isArray(window.aplicacionesDisponibles)) {
		window.aplicacionesDisponibles.forEach(item => {
			aplicacionOptionsHTML += '<option value="' + item + '"' + (item === aplicacion ? ' selected' : '') + '>' + item + '</option>';
		});
	}
	if (!aplicacion && aplicacionOptionsHTML.indexOf('value="NA"') !== -1) {
		aplicacionOptionsHTML = aplicacionOptionsHTML.replace('value="NA"', 'value="NA" selected');
	}

	newRow.innerHTML =
		'<td class="p-2 border-r border-gray-200 clave-modelo-cell">' +
			'<input type="text" value="' + (claveModelo || '') + '"' +
				' class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200 producto-cell">' +
			'<textarea rows="2" readonly' +
				' class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed resize-none">' +
				(producto || '') +
			'</textarea>' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200 flogs-cell" style="min-width: 120px;">' +
			'<textarea rows="2"' +
				' class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 resize-none">' +
				(flog || '') +
			'</textarea>' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200 descripcion-cell" style="min-width: 130px;">' +
			'<textarea rows="2"' +
				' class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 resize-none">' +
				(descripcion || '') +
			'</textarea>' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200 aplicacion-cell" style="min-width: 5rem; width: 5rem;">' +
			'<select name="aplicacion-destino[]" class="w-full min-w-0 px-1 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">' +
				aplicacionOptionsHTML +
			'</select>' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200" style="min-width: 100px;">' +
			'<select name="telar-destino[]" class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 telar-destino-select">' +
				telarOptionsHTML +
			'</select>' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200 pedido-tempo-cell" style="width: 5rem; min-width: 5rem;">' +
			'<input type="text" name="pedido-tempo-destino[]" value="' + pedidoOriginal + '" inputmode="decimal"' +
				' class="w-full min-w-0 px-1 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200 porcentaje-segundos-cell" style="width: 3.25rem; min-width: 3.25rem;">' +
			'<input type="number" name="porcentaje-segundos-destino[]" value="0" placeholder="0" step="0.01" min="0"' +
				' class="w-full min-w-0 px-0.5 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" style="max-width: 3rem;">' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200 produccion-cell hidden">' +
			'<input type="hidden" name="pedido-destino[]" value="">' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200 saldo-total-cell hidden" style="width: 5rem; min-width: 5rem;">' +
			'<input type="text" value="" readonly' +
				' class="w-full min-w-0 px-1 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200 saldo-cell" style="width: 5rem; min-width: 5rem;">' +
			'<input type="text" name="saldo-destino[]" value="' + pedidoOriginal + '" inputmode="decimal"' +
				' class="w-full min-w-0 px-1 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">' +
		'</td>' +
		'<td class="p-2 border-r border-gray-200">' +
			'<textarea rows="2" name="observaciones-destino[]" placeholder="Observaciones..."' +
				' class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 resize-none"></textarea>' +
		'</td>' +
		'<td class="py-1 px-0 text-center acciones-cell" style="width: 2rem; min-width: 2rem;">' +
			'<button type="button" class="btn-remove-row p-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded text-sm inline-flex items-center justify-center" style="min-width: 1.5rem; min-height: 1.5rem;" title="Eliminar fila">' +
				'<i class="fas fa-times"></i>' +
			'</button>' +
		'</td>';
	tbody.appendChild(newRow);
	const hiddenSalon = document.createElement('input');
	hiddenSalon.type = 'hidden';
	hiddenSalon.name = 'salon-destino[]';
	hiddenSalon.value = salonActualLocal;
	newRow.appendChild(hiddenSalon);

	// ⚡ FIX: Agregar event listener para el botón de eliminar
	const btnRemove = newRow.querySelector('.btn-remove-row');
	if (btnRemove) {
		btnRemove.addEventListener('click', () => {
			newRow.remove();
			
			// ⚡ FIX: Actualizar visibilidad de la columna de acciones si no quedan filas agregadas
			const filasAgregadas = document.querySelectorAll('tr.telar-row:not(#fila-principal)');
			const thAcciones = document.getElementById('th-acciones');
			if (filasAgregadas.length === 0 && thAcciones) {
				thAcciones.classList.add('hidden');
			}
			
			if (typeof recomputeState === 'function') {
				recomputeState();
			}
		});
	}

	const telarSelect = newRow.querySelector('select[name="telar-destino[]"]');
	const pedidoInput = newRow.querySelector('input[name="pedido-destino[]"]');
	if (telarSelect && typeof recomputeState === 'function') {
		telarSelect.addEventListener('change', recomputeState);
		telarSelect.addEventListener('change', () => {
			if (typeof window.parseTelarValue !== 'function') return;
			const parsed = window.parseTelarValue(telarSelect.value);
			if (parsed.salon) hiddenSalon.value = parsed.salon;
			
			// Recalcular eficiencia, velocidad y maquina cuando cambie el telar
			const claveModeloInput = newRow.querySelector('.clave-modelo-cell input');
			if (claveModeloInput && claveModeloInput.value && typeof window.cargarDatosRelacionadosRow === 'function') {
				// Si hay una clave modelo, recargar los datos para obtener eficiencia y velocidad con el nuevo telar
				window.cargarDatosRelacionadosRow(newRow, claveModeloInput.value);
			} else if (typeof window.construirMaquinaRow === 'function') {
				// Si no hay clave modelo, solo construir la máquina
				window.construirMaquinaRow(newRow);
			}
		});
		if (typeof window.parseTelarValue === 'function') {
			const parsed = window.parseTelarValue(telarSelect.value);
			if (parsed.salon) hiddenSalon.value = parsed.salon;
		}
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

	// ⚡ FIX: Configurar autocompletadores independientes para esta fila
	// Cada fila debe funcionar de forma independiente
	setupRowAutocompletadores(newRow);

	// Calcular saldo inicial para la nueva fila
	if (typeof calcularSaldoDuplicar === 'function') {
		calcularSaldoDuplicar(newRow);
	}

	// Aplicar formateo de miles a inputs de pedido y saldo de la nueva fila
	if (typeof aplicarFormatoMilesEnContenedor === 'function') {
		aplicarFormatoMilesEnContenedor(newRow);
	}
}
