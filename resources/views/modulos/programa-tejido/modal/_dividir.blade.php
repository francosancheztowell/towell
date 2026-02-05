{{-- Funciones específicas para dividir telares --}}
{{-- NOTA: Este archivo se incluye dentro de un bloque <script>, NO agregar etiquetas <script> aquí --}}

// Función para calcular el Saldo Total usando el controller
async function calcularSaldoTotal(row) {
	if (!row) return;

	const modoActual = getModoActual();
	if (modoActual !== 'dividir') return;

	// Obtener los inputs usando la función compartida
	const { pedidoTempoInput, porcentajeSegundosInput, totalInput } = getRowInputs(row);

	// Obtener el input de producción
	const produccionInput = getProduccionInputFromRow(row);

	// Obtener el input de saldo total
	const saldoTotalInput = row.querySelector('.saldo-total-cell input');

	if (!pedidoTempoInput || !produccionInput || !saldoTotalInput) {
		return;
	}

	// Obtener valores
	const pedido = (typeof parseNumeroConMiles === 'function' ? parseNumeroConMiles(pedidoTempoInput.value) : parseFloat(pedidoTempoInput.value)) || 0;
	// En modo dividir, el porcentaje de segundas siempre es 0 (campo oculto)
	const porcentajeSegundos = 0;
	const produccion = parseFloat(produccionInput.value) || 0;

	try {
		const csrfToken = getCsrfToken();

		const response = await fetch('/programa-tejido/calcular-totales-dividir', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': csrfToken,
				'Accept': 'application/json'
			},
			body: JSON.stringify({
				pedido: pedido,
				porcentaje_segundos: porcentajeSegundos,
				produccion: produccion
			})
		});

		if (!response.ok) {
			return;
		}

		const data = await response.json();

		if (data.success) {
			// Actualizar el campo de saldo total
			saldoTotalInput.value = data.saldo_total.toString();

			// Actualizar el campo total (hidden) si existe
			if (totalInput) {
				totalInput.value = data.total_pedido.toString();
			}

			// Forzar actualización visual del DOM
			saldoTotalInput.dispatchEvent(new Event('input', { bubbles: true }));
			if (totalInput) {
				totalInput.dispatchEvent(new Event('input', { bubbles: true }));
			}
		}
	} catch (error) {
		// Error silencioso
	}
}

// Hacer la función global para que se pueda llamar desde otros módulos
window.calcularSaldoTotal = calcularSaldoTotal;

// Función para sincronizar PedidoTempo y Total bidireccionalmente en modo dividir
function sincronizarPedidoTempoYTotal(row, desdeTotal = false) {
	if (!row) return;

	const modoActual = getModoActual();
	const esDividir = modoActual === 'dividir';

	if (!esDividir) return;

	const { pedidoTempoInput, porcentajeSegundosInput, totalInput } = getRowInputs(row);

	if (!pedidoTempoInput || !totalInput) return;

	// En modo dividir, el porcentaje de segundas siempre es 0
	const porcentajeSegundos = 0;
	const total = (typeof parseNumeroConMiles === 'function' ? parseNumeroConMiles(totalInput.value) : parseFloat(totalInput.value)) || 0;

	// En modo dividir, sincronizar bidireccionalmente
	if (desdeTotal) {
		// Si cambió el total, actualizar pedido tempo
		// Como porcentaje siempre es 0, total = pedido tempo
		if (total > 0) {
			if (total % 1 === 0) {
				pedidoTempoInput.value = total.toString();
			} else {
				pedidoTempoInput.value = total.toFixed(2);
			}
		} else {
			pedidoTempoInput.value = '';
		}
		// Actualizar todo después de sincronizar
		calcularSaldoTotal(row);
	} else {
		// Si cambió el pedido tempo, calcular todo
		calcularSaldoTotal(row);
	}
}

// Función para redistribuir el pedido total entre los telares en modo dividir
function redistribuirPedidoTotalEntreTelares() {
	const modoActual = getModoActual();
	if (modoActual !== 'dividir') return;

	const inputPedidoTotal = document.getElementById('swal-pedido');
	if (!inputPedidoTotal) return;

	const pedidoTotal = (typeof parseNumeroConMiles === 'function' ? parseNumeroConMiles(inputPedidoTotal.value) : parseFloat(inputPedidoTotal.value)) || 0;
	if (pedidoTotal <= 0) {
		// Si el pedido total es 0 o vacío, limpiar todos los totales (incluyendo origen)
		const filas = document.querySelectorAll('#telar-pedido-body tr');
		filas.forEach((fila) => {
			const totalInput = fila.querySelector('input[name="pedido-destino[]"]');
			const pedidoTempoInput = fila.querySelector('input[name="pedido-tempo-destino[]"]');
			if (totalInput) totalInput.value = '';
			if (pedidoTempoInput) pedidoTempoInput.value = '';
		});
		if (typeof recomputeState === 'function') {
			recomputeState();
		}
		return;
	}

	// Obtener todas las filas de telares (incluyendo origen y destinos)
	const filas = document.querySelectorAll('#telar-pedido-body tr');
	if (filas.length === 0) return;

	// Dividir equitativamente entre TODOS los telares (origen + destinos)
	const cantidadPorTelar = pedidoTotal / filas.length;

	filas.forEach((fila) => {
		const pedidoTempoInput = fila.querySelector('input[name="pedido-tempo-destino[]"]');
		if (pedidoTempoInput) {
			// Actualizar el pedido tempo con la cantidad distribuida
			if (cantidadPorTelar % 1 === 0) {
				pedidoTempoInput.value = cantidadPorTelar.toString();
			} else {
				pedidoTempoInput.value = cantidadPorTelar.toFixed(2);
			}
			// Calcular automáticamente total y saldo total
			if (typeof calcularSaldoTotal === 'function') {
				calcularSaldoTotal(fila);
			}
		}
	});

	// Actualizar resumen de cantidades
	if (typeof actualizarResumenCantidades === 'function') {
		actualizarResumenCantidades();
	}
	if (typeof recomputeState === 'function') {
		recomputeState();
	}
}

// Función para actualizar el resumen de cantidades
function actualizarResumenCantidades() {
	const pedidoInputs = document.querySelectorAll('input[name="pedido-destino[]"]');
	const sumaCantidades = document.getElementById('suma-cantidades');

	if (!sumaCantidades) return;

	let suma = 0;
	pedidoInputs.forEach(input => {
		const val = parseFloat(input.value) || 0;
		suma += val;
	});

	sumaCantidades.textContent = suma.toLocaleString('es-MX');
}

// Hacer la función global para que se pueda llamar desde oninput
window.actualizarResumenCantidades = actualizarResumenCantidades;

// Cache y carga de telares por salon (para filas en modo dividir)
const telaresPorSalonCache = new Map();

function obtenerTelaresPorSalonCache(salon) {
	const key = String(salon || '');
	if (telaresPorSalonCache.has(key)) {
		return Promise.resolve(telaresPorSalonCache.get(key));
	}
	return fetch('/programa-tejido/telares-by-salon?salon_tejido_id=' + encodeURIComponent(key), {
		headers: { 'Accept': 'application/json' }
	})
		.then(r => r.json())
		.then(data => {
			const lista = Array.isArray(data) ? data : [];
			telaresPorSalonCache.set(key, lista);
			return lista;
		})
		.catch(() => {
			telaresPorSalonCache.set(key, []);
			return [];
		});
}

function actualizarSelectTelaresParaFila(selectTelar, telares, preseleccionar = '') {
	if (!selectTelar) return;
	const valorActual = preseleccionar || selectTelar.value;
	selectTelar.innerHTML = '<option value="">Seleccionar...</option>';
	telares.forEach(t => {
		const option = document.createElement('option');
		option.value = t;
		option.textContent = t;
		if (t == valorActual) {
			option.selected = true;
		}
		selectTelar.appendChild(option);
	});
}

function actualizarSelectSalonesParaFila(selectSalonDestino, valorPreseleccionar = '') {
	if (!selectSalonDestino) return;
	// Usar window.salonesDisponibles como fuente principal o fallback
	const salones = window.salonesDisponibles || (typeof salonesDisponibles !== 'undefined' ? salonesDisponibles : []);
	if (!salones || salones.length === 0) {
		return;
	}
	const valorActual = valorPreseleccionar || selectSalonDestino.value;
	selectSalonDestino.innerHTML = '<option value="">Seleccionar...</option>';
	salones.forEach(item => {
		const option = document.createElement('option');
		option.value = item;
		option.textContent = item;
		if (item === valorActual) {
			option.selected = true;
		}
		selectSalonDestino.appendChild(option);
	});
}

async function actualizarTelaresPorSalonEnFila(selectSalonDestino, selectTelarDestino, preseleccionarTelar = '') {
	const salonSeleccionado = selectSalonDestino?.value || '';
	if (!salonSeleccionado) {
		if (selectTelarDestino) {
			selectTelarDestino.innerHTML = '<option value="">Seleccionar...</option>';
			selectTelarDestino.disabled = true;
		}
		return;
	}
	if (selectTelarDestino) {
		selectTelarDestino.disabled = false;
	}
	const telares = await obtenerTelaresPorSalonCache(salonSeleccionado);
	actualizarSelectTelaresParaFila(selectTelarDestino, telares, preseleccionarTelar);
}

// Función para guardar los valores originales de una fila existente
function guardarValoresOriginales(fila) {
	const registroId = fila.dataset.registroId || fila.id || 'sin-id';
	const saldoTotalInput = fila.querySelector('.saldo-total-cell input');
	const pedidoTempoInput = fila.querySelector('input[name="pedido-tempo-destino[]"]');
	const porcentajeSegundosInput = fila.querySelector('input[name="porcentaje-segundos-destino[]"]');
	const pedidoDestinoInput = fila.querySelector('input[name="pedido-destino[]"]');
	const produccionInput = getProduccionInputFromRow(fila);

	if (saldoTotalInput && pedidoTempoInput) {
		// Solo guardar si no existe ya (para no sobrescribir valores originales)
		if (!window.valoresOriginalesFilas.has(registroId)) {
			window.valoresOriginalesFilas.set(registroId, {
				saldoTotal: (typeof parseNumeroConMiles === 'function' ? parseNumeroConMiles(saldoTotalInput.value) : parseFloat(saldoTotalInput.value)) || 0,
				pedidoTempo: (typeof parseNumeroConMiles === 'function' ? parseNumeroConMiles(pedidoTempoInput.value) : parseFloat(pedidoTempoInput.value)) || 0,
				porcentajeSegundos: parseFloat(porcentajeSegundosInput?.value) || 0,
				totalPedido: parseFloat(pedidoDestinoInput?.value) || 0,
				produccion: parseFloat(produccionInput?.value) || 0
			});
		}
	}
}

// ⚡ MEJORA: Guardar valores originales de la fila principal al inicio
function guardarValoresOriginalesFilaPrincipal() {
	const filaPrincipal = document.getElementById('fila-principal');
	if (filaPrincipal) {
		const registroId = filaPrincipal.dataset.registroId || filaPrincipal.id || 'fila-principal';
		const saldoTotalInput = filaPrincipal.querySelector('.saldo-total-cell input');
		const pedidoTempoInput = filaPrincipal.querySelector('input[name="pedido-tempo-destino[]"]');
		const porcentajeSegundosInput = filaPrincipal.querySelector('input[name="porcentaje-segundos-destino[]"]');
		const pedidoDestinoInput = filaPrincipal.querySelector('input[name="pedido-destino[]"]');
		const produccionInput = getProduccionInputFromRow && typeof getProduccionInputFromRow === 'function'
			? getProduccionInputFromRow(filaPrincipal)
			: null;

		if (saldoTotalInput && pedidoTempoInput) {
			// Guardar valores originales de la fila principal si no existen
			if (!window.valoresOriginalesFilas.has(registroId)) {
				window.valoresOriginalesFilas.set(registroId, {
					saldoTotal: (typeof parseNumeroConMiles === 'function' ? parseNumeroConMiles(saldoTotalInput.value) : parseFloat(saldoTotalInput.value)) || 0,
					pedidoTempo: (typeof parseNumeroConMiles === 'function' ? parseNumeroConMiles(pedidoTempoInput.value) : parseFloat(pedidoTempoInput.value)) || 0,
					porcentajeSegundos: parseFloat(porcentajeSegundosInput?.value) || 0,
					totalPedido: parseFloat(pedidoDestinoInput?.value) || 0,
					produccion: parseFloat(produccionInput?.value) || 0
				});
			}
		}
	}
}

// OPTIMIZACIÓN: Función para restaurar los valores originales de todas las filas existentes (más rápida)
function restaurarValoresOriginales() {
	window.redistribuyendo = true;

	// MEJORA: Restaurar también la fila principal si tiene valores originales guardados
	const filaPrincipal = document.getElementById('fila-principal');
	if (filaPrincipal) {
		const registroId = filaPrincipal.dataset.registroId || filaPrincipal.id || 'fila-principal';
		const valoresOriginales = window.valoresOriginalesFilas.get(registroId);

		if (valoresOriginales) {
			const saldoTotalInput = filaPrincipal.querySelector('.saldo-total-cell input');
			const pedidoTempoInput = filaPrincipal.querySelector('input[name="pedido-tempo-destino[]"]');
			const porcentajeSegundosInput = filaPrincipal.querySelector('input[name="porcentaje-segundos-destino[]"]');
			const pedidoDestinoInput = filaPrincipal.querySelector('input[name="pedido-destino[]"]');

			// Actualizar directamente sin setTimeout para mayor velocidad
			if (saldoTotalInput) {
				saldoTotalInput.value = valoresOriginales.saldoTotal.toString();
				saldoTotalInput.setAttribute('value', valoresOriginales.saldoTotal.toString());
				saldoTotalInput.dispatchEvent(new Event('input', { bubbles: true }));
			}
			if (pedidoTempoInput) {
				pedidoTempoInput.value = valoresOriginales.pedidoTempo.toString();
				pedidoTempoInput.setAttribute('value', valoresOriginales.pedidoTempo.toString());
				pedidoTempoInput.dispatchEvent(new Event('input', { bubbles: true }));
			}
			if (porcentajeSegundosInput && valoresOriginales.porcentajeSegundos !== undefined) {
				porcentajeSegundosInput.value = valoresOriginales.porcentajeSegundos.toString();
				porcentajeSegundosInput.setAttribute('value', valoresOriginales.porcentajeSegundos.toString());
			}
			if (pedidoDestinoInput) {
				pedidoDestinoInput.value = valoresOriginales.totalPedido.toString();
				pedidoDestinoInput.setAttribute('value', valoresOriginales.totalPedido.toString());
			}
		}
	}

	const filas = document.querySelectorAll('#telar-pedido-body tr.telar-row[data-es-existente="true"]');

	// Restaurar otras filas existentes
	filas.forEach(fila => {
		const registroId = fila.dataset.registroId || fila.id || 'sin-id';
		const valoresOriginales = window.valoresOriginalesFilas.get(registroId);

		if (valoresOriginales) {
			const saldoTotalInput = fila.querySelector('.saldo-total-cell input');
			const pedidoTempoInput = fila.querySelector('input[name="pedido-tempo-destino[]"]');
			const porcentajeSegundosInput = fila.querySelector('input[name="porcentaje-segundos-destino[]"]');
			const pedidoDestinoInput = fila.querySelector('input[name="pedido-destino[]"]');

			// Actualizar directamente sin setTimeout para mayor velocidad
			if (saldoTotalInput) {
				saldoTotalInput.value = valoresOriginales.saldoTotal.toString();
				saldoTotalInput.setAttribute('value', valoresOriginales.saldoTotal.toString());
				saldoTotalInput.dispatchEvent(new Event('input', { bubbles: true }));
			}
			if (pedidoTempoInput) {
				pedidoTempoInput.value = valoresOriginales.pedidoTempo.toString();
				pedidoTempoInput.setAttribute('value', valoresOriginales.pedidoTempo.toString());
				pedidoTempoInput.dispatchEvent(new Event('input', { bubbles: true }));
			}
			if (porcentajeSegundosInput && valoresOriginales.porcentajeSegundos !== undefined) {
				porcentajeSegundosInput.value = valoresOriginales.porcentajeSegundos.toString();
				porcentajeSegundosInput.setAttribute('value', valoresOriginales.porcentajeSegundos.toString());
			}
			if (pedidoDestinoInput) {
				pedidoDestinoInput.value = valoresOriginales.totalPedido.toString();
				pedidoDestinoInput.setAttribute('value', valoresOriginales.totalPedido.toString());
			}
		}
	});

	// Desactivar bandera inmediatamente (sin setTimeout para mayor velocidad)
	window.redistribuyendo = false;
}

// Función para calcular el saldo total disponible de todos los registros existentes usando valores ORIGINALES
function calcularSaldoTotalDisponibleOriginal() {
	let saldoTotalDisponible = 0;

	window.valoresOriginalesFilas.forEach((valores, registroId) => {
		saldoTotalDisponible += valores.saldoTotal;
	});

	// Si no hay valores originales (primera vez que se divide), usar el saldo de la fila principal o el pedido total
	if (saldoTotalDisponible === 0) {
		// Intentar obtener el saldo de la fila principal
		const filaPrincipal = document.getElementById('fila-principal');
		if (filaPrincipal) {
			const saldoTotalInput = filaPrincipal.querySelector('.saldo-total-cell input');
			if (saldoTotalInput) {
				saldoTotalDisponible = (typeof parseNumeroConMiles === 'function' ? parseNumeroConMiles(saldoTotalInput.value) : parseFloat(saldoTotalInput.value)) || 0;
			}
		}

		// Si aún no hay saldo, usar el campo "Pedido Total" como fallback
		if (saldoTotalDisponible === 0) {
			const inputPedidoTotal = document.getElementById('swal-pedido');
			if (inputPedidoTotal) {
				saldoTotalDisponible = (typeof parseNumeroConMiles === 'function' ? parseNumeroConMiles(inputPedidoTotal.value) : parseFloat(inputPedidoTotal.value)) || 0;
			}
		}
	}

	return Math.round(saldoTotalDisponible);
}

// Bandera global para evitar recálculos durante la redistribución
window.redistribuyendo = false;

// Almacenar valores originales de las filas existentes para poder restaurarlos
window.valoresOriginalesFilas = new Map();

// Función para redistribuir proporcionalmente los saldos cuando se añade o elimina un registro
function redistribuirSaldosProporcionalmente(cambioPedido) {
	// Activar bandera para evitar recálculos
	window.redistribuyendo = true;

	const filas = document.querySelectorAll('#telar-pedido-body tr.telar-row');
	const filasExistentes = [];

	// Obtener solo las filas existentes usando valores ORIGINALES
	filas.forEach(fila => {
		const esExistente = fila.dataset.esExistente === 'true';
		if (esExistente) {
			const registroId = fila.dataset.registroId || fila.id || 'sin-id';
			const valoresOriginales = window.valoresOriginalesFilas.get(registroId);

			if (valoresOriginales) {
				const saldoTotalInput = fila.querySelector('.saldo-total-cell input');
				const pedidoTempoInput = fila.querySelector('input[name="pedido-tempo-destino[]"]');
				const porcentajeSegundosInput = fila.querySelector('input[name="porcentaje-segundos-destino[]"]');

				if (saldoTotalInput && pedidoTempoInput && valoresOriginales.saldoTotal > 0) {
					filasExistentes.push({
						fila: fila,
						saldoActual: valoresOriginales.saldoTotal, // Usar valor ORIGINAL
						pedidoTempoActual: valoresOriginales.pedidoTempo, // Usar valor ORIGINAL
						porcentajeSegundosOriginal: valoresOriginales.porcentajeSegundos, // Usar valor ORIGINAL
						produccionOriginal: valoresOriginales.produccion, // Usar valor ORIGINAL
						pedidoTempoInput: pedidoTempoInput,
						porcentajeSegundosInput: porcentajeSegundosInput,
						saldoTotalInput: saldoTotalInput
					});
				}
			}
		}
	});

	if (filasExistentes.length === 0) {
		window.redistribuyendo = false;
		return;
	}

	// Calcular el saldo total disponible usando valores ORIGINALES
	let saldoTotalDisponible = 0;
	filasExistentes.forEach(item => {
		saldoTotalDisponible += item.saldoActual; // Ya es el valor original
	});

	if (saldoTotalDisponible <= 0 && cambioPedido < 0) {
		return;
	}

	// Calcular el nuevo saldo total después del cambio
	const nuevoSaldoTotal = saldoTotalDisponible - cambioPedido;

	// Si el nuevo saldo es negativo o cero, no redistribuir
	if (nuevoSaldoTotal <= 0) {
		return;
	}

	// Calcular el factor de redistribución: nuevo_saldo_total / saldo_total_actual
	const factor = nuevoSaldoTotal / saldoTotalDisponible;

	// Redistribuir proporcionalmente sin decimales (sumar decimales al primer registro)
	const nuevosSaldos = [];
	let sumaDecimales = 0;
	let sumaEnteros = 0;

	filasExistentes.forEach((item, index) => {
		const nuevoSaldo = item.saldoActual * factor;
		const nuevoSaldoEntero = Math.floor(nuevoSaldo);
		const decimal = nuevoSaldo - nuevoSaldoEntero;

		nuevosSaldos.push({
			...item,
			nuevoSaldoEntero,
			decimal
		});

		sumaDecimales += decimal;
		sumaEnteros += nuevoSaldoEntero;
	});

	// Calcular la diferencia para asegurar que la suma sea exacta
	// nuevoSaldoTotal debe ser igual a sumaEnteros + decimales redondeados + diferencia
	const decimalesRedondeados = Math.round(sumaDecimales);
	const sumaActual = sumaEnteros + decimalesRedondeados;
	const diferencia = nuevoSaldoTotal - sumaActual;

	// Agregar todos los decimales al primer registro + la diferencia para cuadrar exactamente
	if (nuevosSaldos.length > 0) {
		nuevosSaldos[0].nuevoSaldoEntero += decimalesRedondeados + diferencia;
	}
	// Actualizar cada fila con su nuevo saldo
	nuevosSaldos.forEach((item, index) => {
		const nuevoSaldo = item.nuevoSaldoEntero;

		// Usar la producción original guardada
		const produccion = item.produccionOriginal || 0;

		// TotalPedido = SaldoTotal + Produccion (redondear a entero)
		const nuevoTotalPedido = Math.round(nuevoSaldo + produccion);

		// En modo dividir, el porcentaje de segundas siempre es 0
		const porcentajeSegundos = 0;

		// TotalPedido = PedidoTempo * (1 + PorcentajeSegundos / 100)
		// Como porcentaje es 0, TotalPedido = PedidoTempo
		const nuevoPedidoTempo = nuevoTotalPedido;

		// Actualizar el pedido tempo como entero (sin decimales)
		// Usar setAttribute para evitar disparar eventos durante la redistribución
		item.pedidoTempoInput.setAttribute('value', Math.round(nuevoPedidoTempo).toString());
		item.pedidoTempoInput.value = Math.round(nuevoPedidoTempo).toString();

		// Actualizar el hidden pedido-destino[] con el nuevo TotalPedido (entero)
		const pedidoDestinoInput = item.fila.querySelector('input[name="pedido-destino[]"]');
		if (pedidoDestinoInput) {
			pedidoDestinoInput.setAttribute('value', nuevoTotalPedido.toString());
			pedidoDestinoInput.value = nuevoTotalPedido.toString();
		}

		// Actualizar el saldo total en la UI como entero (sin decimales)
		item.saldoTotalInput.setAttribute('value', nuevoSaldo.toString());
		item.saldoTotalInput.value = nuevoSaldo.toString();
	});

	// Desactivar bandera después de actualizar todos los valores
	setTimeout(() => {
		window.redistribuyendo = false;
	}, 100);
}

	// Función para validar y redistribuir cuando se añade un nuevo registro
function validarYRedistribuirNuevoRegistro(nuevaFila) {
	const pedidoTempoInput = nuevaFila.querySelector('input[name="pedido-tempo-destino[]"]');
	if (!pedidoTempoInput) return;
	const obtenerTotalPedidoNuevos = (excluirFila = null) => {
		const filasNuevas = document.querySelectorAll('#telar-pedido-body tr.telar-row[data-es-nuevo="true"]');
		let total = 0;
		filasNuevas.forEach((fila) => {
			if (excluirFila && fila === excluirFila) return;
			const input = fila.querySelector('input[name="pedido-tempo-destino[]"]');
			const valor = parseFloat(input?.value) || 0;
			if (valor > 0) {
				total += Math.round(valor);
			}
		});
		return total;
	};

	const obtenerSaldoOriginalPrincipal = () => {
		const filaPrincipal = document.getElementById('fila-principal');
		if (!filaPrincipal) return 0;
		const registroId = filaPrincipal.dataset.registroId || filaPrincipal.id || 'fila-principal';
		const valoresOriginales = window.valoresOriginalesFilas.get(registroId);
		let saldoOriginal = 0;
		if (valoresOriginales) {
			saldoOriginal = valoresOriginales.saldoTotal || 0;
		}
		if (!saldoOriginal) {
			const saldoTotalPrincipal = filaPrincipal.querySelector('.saldo-total-cell input');
			saldoOriginal = (typeof parseNumeroConMiles === 'function' ? parseNumeroConMiles(saldoTotalPrincipal?.value) : parseFloat(saldoTotalPrincipal?.value)) || 0;
		}
		return Math.round(saldoOriginal);
	};

	const obtenerProduccionOriginalPrincipal = () => {
		const filaPrincipal = document.getElementById('fila-principal');
		if (!filaPrincipal) return 0;
		const registroId = filaPrincipal.dataset.registroId || filaPrincipal.id || 'fila-principal';
		const valoresOriginales = window.valoresOriginalesFilas.get(registroId);
		let produccionOriginal = 0;
		if (valoresOriginales) {
			produccionOriginal = valoresOriginales.produccion || 0;
		}
		if (!produccionOriginal) {
			const produccionInput = typeof getProduccionInputFromRow === 'function'
				? getProduccionInputFromRow(filaPrincipal)
				: filaPrincipal.querySelector('.produccion-cell input');
			produccionOriginal = parseFloat(produccionInput?.value) || 0;
		}
		return Math.round(produccionOriginal);
	};
	// Función para validar y redistribuir
	const validarYRedistribuir = () => {
		const pedidoTempo = (typeof parseNumeroConMiles === 'function' ? parseNumeroConMiles(pedidoTempoInput.value) : parseFloat(pedidoTempoInput.value)) || 0;
		// En modo dividir, el porcentaje de segundas siempre es 0
		const porcentajeSegundos = 0;
		// Calcular el TotalPedido (con %segundas aplicado, redondear a entero)
		const totalPedidoActual = Math.round(pedidoTempo * (1 + porcentajeSegundos / 100));
		const totalOtrosPedidos = obtenerTotalPedidoNuevos(nuevaFila);
		let totalPedidoNuevos = totalOtrosPedidos + totalPedidoActual;

		// Si el pedido actual es 0, solo restaurar si no hay otros pedidos nuevos
		if (totalPedidoActual <= 0 || pedidoTempo === 0) {
			if (window.valoresOriginalesFilas.size === 0) {
				guardarValoresOriginalesFilaPrincipal();
			}
			if (totalOtrosPedidos === 0) {
				restaurarValoresOriginales();
				return;
			}
			totalPedidoNuevos = totalOtrosPedidos;
		}
		// ⚡ MEJORA: Guardar valores originales de la fila principal si aún no están guardados
		if (window.valoresOriginalesFilas.size === 0) {
			guardarValoresOriginalesFilaPrincipal();
		}

		// ⚡ CORRECCIÓN: Verificar si es la primera división (no hay valores originales guardados de otras filas)
		const esPrimeraDivision = window.valoresOriginalesFilas.size === 1; // Solo la fila principal

		// Calcular saldo total disponible usando valores ORIGINALES
		const saldoTotalDisponible = calcularSaldoTotalDisponibleOriginal();

		// ⚡ CORRECCIÓN: Si es la primera división, siempre restar directamente del registro principal
		// Si NO es la primera división, validar y usar redistribución proporcional
		if (esPrimeraDivision) {
			// Primera división: restar directamente del registro principal
			// Obtener el Pedido actual de la fila principal (este es el valor que se debe reducir)
			const filaPrincipal = document.getElementById('fila-principal');
			if (filaPrincipal) {
				const pedidoTempoPrincipal = filaPrincipal.querySelector('input[name="pedido-tempo-destino[]"]');
				const saldoDisponiblePrincipal = obtenerSaldoOriginalPrincipal();
				const produccionPrincipal = obtenerProduccionOriginalPrincipal();
				const saldoTotalPrincipal = filaPrincipal.querySelector('.saldo-total-cell input');

				if (saldoDisponiblePrincipal > 0) {
					// Saldo nuevo = saldo disponible - pedidos nuevos
					const nuevoSaldoPedidoPrincipal = saldoDisponiblePrincipal - totalPedidoNuevos;
					// TotalPedido = Saldo + Produccion original
					const nuevoTotalPedidoPrincipal = nuevoSaldoPedidoPrincipal + produccionPrincipal;

					if (nuevoSaldoPedidoPrincipal >= 0) {
						const nuevoSaldoRedondeado = Math.round(nuevoSaldoPedidoPrincipal);
						const nuevoTotalRedondeado = Math.round(nuevoTotalPedidoPrincipal);

						if (pedidoTempoPrincipal) {
							pedidoTempoPrincipal.value = nuevoTotalRedondeado.toString();
							pedidoTempoPrincipal.setAttribute('value', nuevoTotalRedondeado.toString());
							pedidoTempoPrincipal.style.display = 'none';
							pedidoTempoPrincipal.offsetHeight;
							pedidoTempoPrincipal.style.display = '';
							pedidoTempoPrincipal.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
							pedidoTempoPrincipal.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
						}

						const pedidoDestinoHidden = filaPrincipal.querySelector('input[name="pedido-destino[]"]');
						if (pedidoDestinoHidden) {
							pedidoDestinoHidden.value = nuevoTotalRedondeado.toString();
							pedidoDestinoHidden.setAttribute('value', nuevoTotalRedondeado.toString());
						}

						if (saldoTotalPrincipal) {
							saldoTotalPrincipal.value = nuevoSaldoRedondeado.toString();
							saldoTotalPrincipal.setAttribute('value', nuevoSaldoRedondeado.toString());
							saldoTotalPrincipal.style.display = 'none';
							saldoTotalPrincipal.offsetHeight;
							saldoTotalPrincipal.style.display = '';
							saldoTotalPrincipal.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
							saldoTotalPrincipal.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
						}

						requestAnimationFrame(() => {
							if (pedidoTempoPrincipal) {
								pedidoTempoPrincipal.blur();
								pedidoTempoPrincipal.focus();
								pedidoTempoPrincipal.blur();
							}
							if (saldoTotalPrincipal) {
								saldoTotalPrincipal.blur();
								saldoTotalPrincipal.focus();
								saldoTotalPrincipal.blur();
							}
						});

					} else {
						if (typeof showToast === 'function') {
							showToast(`El pedido total (${totalPedidoNuevos}) no puede ser mayor al saldo disponible (${Math.round(saldoDisponiblePrincipal)})`, 'error');
						}
						const maxPermitido = Math.max(0, saldoDisponiblePrincipal - totalOtrosPedidos);
						pedidoTempoInput.value = maxPermitido > 0 ? maxPermitido.toString() : '0';
						if (typeof calcularSaldoTotal === 'function') {
							calcularSaldoTotal(nuevaFila);
						}
						pedidoTempoInput.focus();
						return;
					}
				}
			}

			// Calcular el saldo total de la nueva fila (que será igual al totalPedido ya que no tiene producción)
			// Sin setTimeout para mayor velocidad - ejecutar inmediatamente
			if (typeof calcularSaldoTotal === 'function') {
				calcularSaldoTotal(nuevaFila);
			}
			return;
		}

		// ⚡ CORRECCIÓN: Si NO es la primera división, validar y usar redistribución proporcional
		// Solo validar si hay saldo disponible (evitar validación en primera división hasta tener valores)
		// Si el saldo disponible es mayor a 0, validar que no exceda
		if (saldoTotalDisponible > 0 && totalPedidoNuevos > saldoTotalDisponible) {
			if (typeof showToast === 'function') {
				showToast(`El pedido total (${totalPedidoNuevos}) no puede ser mayor al saldo total disponible (${Math.round(saldoTotalDisponible)})`, 'error');
			}
			const maxPermitido = Math.max(0, saldoTotalDisponible - totalOtrosPedidos);
			pedidoTempoInput.value = maxPermitido > 0 ? maxPermitido.toString() : '0';
			if (typeof calcularSaldoTotal === 'function') {
				calcularSaldoTotal(nuevaFila);
			}
			pedidoTempoInput.focus();
			return;
		}

		// Redistribuir proporcionalmente usando el TotalPedido (cuando ya hay 2+ registros)
		redistribuirSaldosProporcionalmente(totalPedidoNuevos);

		// Recalcular el saldo total de la nueva fila después de redistribuir
		// Sin setTimeout para mayor velocidad - ejecutar inmediatamente
		if (typeof calcularSaldoTotal === 'function') {
			calcularSaldoTotal(nuevaFila);
		}
	};

	// ⚡ MEJORA: Listener para cuando se ingresa el pedido tempo - ejecutar inmediatamente
	// Usar 'input' para capturar cambios en tiempo real mientras se escribe
	// Input rapido: calcular solo la fila actual mientras se escribe
	const handleInputRapido = () => {
		if (typeof calcularSaldoTotal === 'function') {
			calcularSaldoTotal(nuevaFila);
		}
	};
	const runValidacion = () => {
		validarYRedistribuir();
	};

	// Usar 'input' para no frenar el tecleo
	pedidoTempoInput.addEventListener('input', handleInputRapido, { passive: true });
	// Validar/redistribuir cuando el usuario termina
	pedidoTempoInput.addEventListener('change', runValidacion, { passive: true });
	pedidoTempoInput.addEventListener('blur', runValidacion, { passive: true });
	pedidoTempoInput.addEventListener('keyup', (e) => {
		if (e.key === 'Enter' || e.key === 'Tab') {
			runValidacion();
		}
	}, { passive: true });
}

// Función para agregar fila en modo dividir
function agregarFilaDividir() {
	const tbody = document.getElementById('telar-pedido-body');
	if (!tbody) return;

	const newRow = document.createElement('tr');
	newRow.className = 'telar-row border-t border-gray-200';
	newRow.dataset.esExistente = 'false';
	newRow.dataset.esNuevo = 'true';

	let telarOptionsHTML = '<option value="">Seleccionar destino...</option>';

	const salonActualLocal = document.getElementById('swal-salon')?.value || '';
	const claveModelo = document.getElementById('swal-claveModelo')?.value || '';
	const producto = document.getElementById('swal-producto')?.value || '';
	const flog = document.getElementById('swal-flog')?.value || '';
	const descripcion = document.getElementById('swal-descripcion')?.value || '';
	const aplicacion = document.getElementById('swal-aplicacion')?.value || '';

	// Obtener opciones de aplicación disponibles
	let aplicacionOptionsHTML = '<option value="">Seleccionar...</option>';
	const selectAplicacionGlobal = document.getElementById('swal-aplicacion');
	if (selectAplicacionGlobal && selectAplicacionGlobal.options) {
		Array.from(selectAplicacionGlobal.options).forEach(option => {
			if (option.value) {
				aplicacionOptionsHTML += `<option value="${option.value}"${option.value === aplicacion ? ' selected' : ''}>${option.textContent}</option>`;
			}
		});
	}

	newRow.innerHTML = `
		<td class="p-2 border-r border-gray-200 clave-modelo-cell">
			<input type="text" value="${claveModelo || ''}"
				class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-green-500">
		</td>
		<td class="p-2 border-r border-gray-200 producto-cell">
			<textarea rows="2" readonly
				class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed resize-none">${producto || ''}</textarea>
		</td>
		<td class="p-2 border-r border-gray-200 flogs-cell" style="min-width: 120px;">
			<textarea rows="2"
				class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-green-500 resize-none">${flog || ''}</textarea>
		</td>
		<td class="p-2 border-r border-gray-200 descripcion-cell" style="min-width: 130px;">
			<textarea rows="2"
				class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-green-500 resize-none">${descripcion || ''}</textarea>
		</td>
		<td class="p-2 border-r border-gray-200 aplicacion-cell" style="min-width: 5rem; width: 5rem;">
			<select name="aplicacion-destino[]" class="w-full min-w-0 px-1 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-green-500">
				${aplicacionOptionsHTML}
			</select>
		</td>
		<td class="p-2 border-r border-gray-200" style="min-width: 100px;">
			<div class="flex items-center gap-2">
				<select name="telar-destino[]" class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-green-500 telar-destino-select">
					${telarOptionsHTML}
				</select>
			</div>
		</td>
		<td class="p-2 border-r border-gray-200 pedido-tempo-cell" style="width: 5rem; min-width: 5rem;">
			<input type="text" name="pedido-tempo-destino[]" value="" data-pedido-total="true" inputmode="decimal"
				class="w-full min-w-0 px-1 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-green-500">
		</td>
		<td class="p-2 border-r border-gray-200 porcentaje-segundos-cell" style="width: 3.25rem; min-width: 3.25rem;">
			<input type="number" name="porcentaje-segundos-destino[]" value="0" step="0.01" min="0" readonly disabled
				class="w-full min-w-0 px-0.5 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed" style="max-width: 3rem;">
		</td>
		<td class="p-2 border-r border-gray-200 produccion-cell" style="width: 5rem; min-width: 5rem;">
			<input type="text" value="" readonly
				class="w-full min-w-0 px-1 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
			<input type="hidden" name="pedido-destino[]" value="">
		</td>
		<td class="p-2 border-r border-gray-200 saldo-total-cell" style="width: 5rem; min-width: 5rem;">
			<input type="text" value="" readonly
				class="w-full min-w-0 px-1 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
		</td>
		<td class="p-2 border-r border-gray-200">
			<textarea rows="2" name="observaciones-destino[]" placeholder="Observaciones..."
				class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-green-500 resize-none"></textarea>
		</td>
		<td class="py-1 px-0 text-center acciones-cell" style="width: 2rem; min-width: 2rem;">
			<button type="button" class="btn-remove-row p-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded text-sm inline-flex items-center justify-center" style="min-width: 1.5rem; min-height: 1.5rem;" title="Eliminar fila">
				<i class="fas fa-times"></i>
			</button>
		</td>
	`;
	tbody.appendChild(newRow);
	const hiddenSalon = document.createElement('input');
	hiddenSalon.type = 'hidden';
	hiddenSalon.name = 'salon-destino[]';
	hiddenSalon.value = '';
	newRow.appendChild(hiddenSalon);

	// ⚡ FIX: Configurar autocompletadores independientes para esta fila
	// Cada fila debe funcionar de forma independiente
	if (typeof window.setupRowAutocompletadores === 'function') {
		window.setupRowAutocompletadores(newRow);
	}

	// Eventos
	const btnRemove = newRow.querySelector('.btn-remove-row');
	if (btnRemove) {
		btnRemove.addEventListener('click', () => {
			// Antes de eliminar, restaurar los valores originales de las filas existentes
			if (typeof restaurarValoresOriginales === 'function') {
				restaurarValoresOriginales();
			}

			newRow.remove();

			// ⚡ FIX: Actualizar visibilidad de la columna de acciones si no quedan filas agregadas
			const filasAgregadas = document.querySelectorAll('tr.telar-row:not(#fila-principal)');
			const thAcciones = document.getElementById('th-acciones');
			if (filasAgregadas.length === 0 && thAcciones) {
				thAcciones.classList.add('hidden');
			}

			if (typeof actualizarResumenCantidades === 'function') {
				actualizarResumenCantidades();
			}
			if (typeof recomputeState === 'function') {
				recomputeState();
			}
		});
	}

	// NOTA: Ya no sincronizamos descripción entre filas
	// Cada fila es independiente y maneja sus propios valores
	// Los autocompletadores se configuran en setupRowAutocompletadores

	const salonHiddenInput = newRow.querySelector('input[name="salon-destino[]"]');
	const telarSelect = newRow.querySelector('select[name="telar-destino[]"]');
	const pedidoInput = newRow.querySelector('input[name="pedido-destino[]"]');

	// Obtener el salón del select global (ya no hay select por fila)
	const selectSalonGlobal = document.getElementById('swal-salon');
	if (salonHiddenInput && selectSalonGlobal) {
		salonHiddenInput.value = selectSalonGlobal.value || salonActualLocal;
	}

	if (telarSelect) {
		// Inicializar telares si hay un salón preseleccionado
		if (salonActualLocal && typeof actualizarTelaresPorSalonEnFila === 'function') {
			// Usar el select global de salón para obtener telares
			if (selectSalonGlobal) {
				actualizarTelaresPorSalonEnFila(selectSalonGlobal, telarSelect);
			}
		}

		// Listener para cuando cambia el telar
		if (typeof recomputeState === 'function') {
			telarSelect.addEventListener('change', recomputeState);
			telarSelect.addEventListener('change', () => {
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
		}

		// ⚡ MEJORA: Listener para cuando cambia la clave modelo en la nueva fila
		const claveModeloInput = newRow.querySelector('.clave-modelo-cell input');
		if (claveModeloInput) {
			// Guardar clave modelo en dataset cuando cambia
			const actualizarClaveModelo = () => {
				const nuevaClaveModelo = claveModeloInput.value.trim();
				// Guardar en dataset para que se envíe al backend
				newRow.dataset.claveModelo = nuevaClaveModelo;
				if (nuevaClaveModelo && typeof window.cargarDatosRelacionadosRow === 'function') {
					// Cargar datos relacionados cuando se cambia la clave modelo
					window.cargarDatosRelacionadosRow(newRow, nuevaClaveModelo);
				}
			};

			claveModeloInput.addEventListener('change', actualizarClaveModelo);
			claveModeloInput.addEventListener('blur', actualizarClaveModelo);
			claveModeloInput.addEventListener('input', () => {
				// Actualizar dataset en tiempo real mientras se escribe
				newRow.dataset.claveModelo = claveModeloInput.value.trim();
			});
		}
	}

	// Listener para cuando cambia el salón global (actualizar telares de todas las filas nuevas)
	if (selectSalonGlobal && telarSelect) {
		const updateTelares = async () => {
			const nuevoSalon = selectSalonGlobal.value;
			if (salonHiddenInput) {
				salonHiddenInput.value = nuevoSalon;
			}
			if (typeof actualizarTelaresPorSalonEnFila === 'function') {
				actualizarTelaresPorSalonEnFila(selectSalonGlobal, telarSelect);
			}

			// Validar clave modelo en el nuevo salón
			const inputClaveModelo = document.getElementById('swal-claveModelo');
			const claveModeloActual = inputClaveModelo?.value?.trim();
			if (claveModeloActual && nuevoSalon && typeof validarClaveModeloEnSalonDestino === 'function') {
				const existe = await validarClaveModeloEnSalonDestino(nuevoSalon, claveModeloActual);
				if (!existe) {
					if (typeof showToast === 'function') {
						showToast('La clave modelo no existe en el salon seleccionado', 'error');
					}
				}
			}

			if (typeof recomputeState === 'function') {
				recomputeState();
			}
		};

		// Agregar listener solo una vez usando una bandera
		if (!selectSalonGlobal.dataset.listenerAgregado) {
			selectSalonGlobal.addEventListener('change', updateTelares);
			selectSalonGlobal.dataset.listenerAgregado = 'true';
		}
	}

	if (pedidoInput) {
		pedidoInput.addEventListener('input', () => {
			if (typeof actualizarResumenCantidades === 'function') {
				actualizarResumenCantidades();
			}
			if (typeof recomputeState === 'function') {
				recomputeState();
			}
		});
	}

	// Agregar listeners para cálculo automático
	if (typeof agregarListenersCalculoAutomatico === 'function') {
		agregarListenersCalculoAutomatico(newRow);
	}

	// Validar y redistribuir cuando se ingresa el pedido
	validarYRedistribuirNuevoRegistro(newRow);

	// ⚡ OPTIMIZACIÓN: Calcular automáticamente los totales iniciales inmediatamente (sin setTimeout)
	if (typeof calcularSaldoTotal === 'function') {
		calcularSaldoTotal(newRow);
	}

	// Aplicar formateo de miles a inputs de pedido y saldo de la nueva fila
	if (typeof aplicarFormatoMilesEnContenedor === 'function') {
		aplicarFormatoMilesEnContenedor(newRow);
	}
}

// Función para cargar registros existentes de OrdCompartida
async function cargarRegistrosOrdCompartida(ordCompartida) {
	// Validar que ordCompartida sea válido
	if (!ordCompartida) {
		return;
	}

	// Convertir a número y validar
	const ordCompartidaNum = parseInt(ordCompartida, 10);
	if (isNaN(ordCompartidaNum) || ordCompartidaNum <= 0) {
		return;
	}

	const tbody = document.getElementById('telar-pedido-body');
	if (!tbody) return;

	try {
		// Construir la URL de forma más robusta
		const baseUrl = window.location.origin;
		const url = `${baseUrl}/planeacion/programa-tejido/registros-ord-compartida/${ordCompartidaNum}`;

		const csrfToken = getCsrfToken();
		if (!csrfToken) {
			return;
		}

		const response = await fetch(url, {
			method: 'GET',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': csrfToken,
				'X-Requested-With': 'XMLHttpRequest'
			},
			credentials: 'same-origin'
		});

		// Validar que la respuesta sea exitosa
		if (!response.ok) {
			throw new Error(`Error HTTP: ${response.status} ${response.statusText}`);
		}

		// Validar que la respuesta sea JSON
		const contentType = response.headers.get('content-type');
		if (!contentType || !contentType.includes('application/json')) {
			throw new Error('La respuesta no es JSON válido');
		}

		const data = await response.json();

		if (data.success && data.registros && data.registros.length > 0) {
			registrosOrdCompartidaExistentes = data.registros;
			const totalOriginal = data.total_original || 0;

			// Actualizar el total disponible (para el resumen)
			const totalDisponible = document.getElementById('total-disponible');
			if (totalDisponible) {
				totalDisponible.textContent = totalOriginal;
			}

			// Actualizar el campo "Pedido Total" con la suma de las cantidades de los telares divididos
			const inputPedidoTotal = document.getElementById('swal-pedido');
			if (inputPedidoTotal) {
				inputPedidoTotal.value = totalOriginal;
			}

			// Limpiar tabla
			tbody.innerHTML = '';

			const selectSalon = document.getElementById('swal-salon');
			const salonActualLocal = selectSalon?.value || '';
			const claveModelo = document.getElementById('swal-claveModelo')?.value || '';
			const producto = document.getElementById('swal-producto')?.value || '';
			const flog = document.getElementById('swal-flog')?.value || '';
			const descripcion = document.getElementById('swal-descripcion')?.value || '';
			const aplicacion = document.getElementById('swal-aplicacion')?.value || '';

			// Obtener opciones de aplicaci?n disponibles
			const selectAplicacionGlobal = document.getElementById('swal-aplicacion');
			// Crear filas para cada registro existente
			data.registros.forEach((reg, index) => {
				// El candado se muestra en el registro que tiene OrdCompartidaLider = 1
				const esLider = reg.OrdCompartidaLider === 1 || reg.OrdCompartidaLider === true || reg.OrdCompartidaLider === '1';
				const puedeEliminar = !esLider && !reg.EnProceso;

				// Reconstruir opciones de aplicación para cada registro
				let aplicacionOptionsHTMLReg = '<option value="">Seleccionar...</option>';
				if (selectAplicacionGlobal && selectAplicacionGlobal.options) {
					Array.from(selectAplicacionGlobal.options).forEach(option => {
						if (option.value) {
							const selected = (reg.AplicacionId && option.value === reg.AplicacionId) || (!reg.AplicacionId && option.value === aplicacion) ? ' selected' : '';
							aplicacionOptionsHTMLReg += `<option value="${option.value}"${selected}>${option.textContent}</option>`;
						}
					});
				}

				const newRow = document.createElement('tr');
				newRow.className = 'telar-row border-t border-gray-200';
				newRow.id = esLider ? 'fila-principal' : '';
				newRow.dataset.registroId = reg.Id;
				newRow.dataset.esExistente = 'true';

				newRow.innerHTML = `
					<td class="p-2 border-r border-gray-200 clave-modelo-cell">
						<input type="text" value="${claveModelo || ''}" readonly
							class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
					</td>
					<td class="p-2 border-r border-gray-200 producto-cell">
						<textarea rows="2" readonly
							class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed resize-none">${producto || ''}</textarea>
					</td>
					<td class="p-2 border-r border-gray-200 flogs-cell" style="min-width: 120px;">
						<textarea rows="2" readonly
							class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed resize-none">${flog || ''}</textarea>
					</td>
					<td class="p-2 border-r border-gray-200 descripcion-cell" style="min-width: 130px;">
						<textarea rows="2" readonly
							class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed resize-none">${descripcion || ''}</textarea>
					</td>
					<td class="p-2 border-r border-gray-200 aplicacion-cell" style="min-width: 5rem; width: 5rem;">
						<select name="aplicacion-destino[]" class="w-full min-w-0 px-1 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed" data-registro-id="${reg.Id}" disabled>
							${aplicacionOptionsHTMLReg}
						</select>
					</td>
					<td class="p-2 border-r border-gray-200" style="min-width: 100px;">
						<div class="flex items-center gap-2">
							<input type="text" name="telar-destino[]" value="${reg.NoTelarId}" readonly
								data-registro-id="${reg.Id}"
								class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
						</div>
					</td>
					<td class="p-2 border-r border-gray-200 pedido-tempo-cell" style="width: 5rem; min-width: 5rem;">
						<input type="text" name="pedido-tempo-destino[]" value="${reg.TotalPedido != null ? reg.TotalPedido : 0}" data-pedido-total="true" readonly data-registro-id="${reg.Id}"
							class="w-full min-w-0 px-1 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
					</td>
					<td class="p-2 border-r border-gray-200 porcentaje-segundos-cell" style="width: 3.25rem; min-width: 3.25rem;">
						<input type="number" name="porcentaje-segundos-destino[]" value="${reg.PorcentajeSegundos !== null && reg.PorcentajeSegundos !== undefined ? reg.PorcentajeSegundos : '0'}" step="0.01" min="0" readonly data-registro-id="${reg.Id}"
							class="w-full min-w-0 px-0.5 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed" style="max-width: 3rem;">
					</td>
					<td class="p-2 border-r border-gray-200 produccion-cell" style="width: 5rem; min-width: 5rem;">
						<input type="text" value="${reg.Produccion !== null && reg.Produccion !== undefined ? reg.Produccion : 0}" readonly
							class="w-full min-w-0 px-1 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
						<input type="hidden" name="pedido-destino[]" value="${reg.TotalPedido || 0}" data-registro-id="${reg.Id}">
					</td>
					<td class="p-2 border-r border-gray-200 saldo-total-cell" style="width: 5rem; min-width: 5rem;">
						<input type="text" value="${reg.SaldoPedido !== null && reg.SaldoPedido !== undefined ? reg.SaldoPedido : 0}" readonly
							data-registro-id="${reg.Id}"
							class="w-full min-w-0 px-1 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
					</td>
					<td class="p-2 border-r border-gray-200">
						<textarea rows="2" name="observaciones-destino[]" placeholder="Observaciones..."
							data-registro-id="${reg.Id}"
							class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-green-500 resize-none">${reg.Observaciones || ''}</textarea>
					</td>
					<td class="py-1 px-0 text-center acciones-cell" style="width: 2rem; min-width: 2rem;">
						${esLider
							? '<div class="w-3 h-3 rounded-full bg-green-500 mx-auto" title="Líder"></div>'
							: '<div class="w-3 h-3 rounded-full bg-gray-400 mx-auto" title="En proceso"></div>'}
					</td>
				`;
				tbody.appendChild(newRow);
				const hiddenSalon = document.createElement('input');
				hiddenSalon.type = 'hidden';
				hiddenSalon.name = 'salon-destino[]';
				hiddenSalon.value = reg.SalonTejidoId || salonActualLocal;
				hiddenSalon.setAttribute('data-registro-id', reg.Id);
				newRow.appendChild(hiddenSalon);

				// Guardar valores originales de esta fila existente
				guardarValoresOriginales(newRow);

				// Evento para actualizar al cambiar cantidad
				const pedidoInput = newRow.querySelector('input[name="pedido-destino[]"]');
				if (pedidoInput) {
					pedidoInput.addEventListener('input', () => {
						if (typeof actualizarResumenCantidades === 'function') {
							actualizarResumenCantidades();
						}
						if (typeof recomputeState === 'function') {
							recomputeState();
						}
					});
				}

				// Agregar listeners para cálculo automático
				if (typeof agregarListenersCalculoAutomatico === 'function') {
					agregarListenersCalculoAutomatico(newRow);
				}

				// Calcular automáticamente los totales
				if (typeof calcularSaldoTotal === 'function') {
					calcularSaldoTotal(newRow);
				}
			});

			// Actualizar resumen
			if (typeof actualizarResumenCantidades === 'function') {
				actualizarResumenCantidades();
			}

			// Calcular y mostrar saldo total de todos los registros vinculados
			let saldoTotalAcumulado = 0;
			data.registros.forEach(reg => {
				const saldo = parseFloat(reg.SaldoPedido) || 0;
				saldoTotalAcumulado += saldo;
			});

			// Crear o actualizar fila de totales
			let filaTotales = tbody.querySelector('tr.saldo-total-row');
			if (!filaTotales) {
				filaTotales = document.createElement('tr');
				filaTotales.className = 'saldo-total-row bg-blue-50 font-semibold';
				tbody.appendChild(filaTotales);
			}

			// Obtener número de columnas (basado en el header)
			const thead = tbody.closest('table')?.querySelector('thead tr');
			const numColumns = thead ? thead.children.length : 13; // Aproximadamente 13 columnas

			// Crear HTML de la fila de totales
			// Columnas: Clave Modelo, Producto, Flogs, Descripcion, Aplicación, Telar, Pedido, % Segundas (hidden), Produccion, Saldo Total, Obs, Acciones (hidden)
			// La columna "Saldo Total" está en la posición 10 (índice 9)
			filaTotales.innerHTML = `
				<td class="p-2 border-r border-gray-300"></td>
				<td class="p-2 border-r border-gray-300 text-right text-sm font-semibold text-gray-700" colspan="8">Saldo Total:</td>
				<td class="p-2 border-r border-gray-300 text-right text-sm font-bold text-blue-700">
					<span id="saldo-total-vinculados">${saldoTotalAcumulado.toFixed(2)}</span>
				</td>
				<td class="p-2 border-r border-gray-300"></td>
				<td class="p-2"></td>
			`;
		}
	} catch (error) {
		// Mostrar mensaje de error al usuario si existe la función showToast
		if (typeof showToast === 'function') {
			let mensaje = 'Error al cargar los registros vinculados';
			if (error.message) {
				mensaje += `: ${error.message}`;
			}
			showToast(mensaje, 'error');
		}

		// Limpiar registros existentes en caso de error
		registrosOrdCompartidaExistentes = [];

		// Limpiar la tabla si existe
		if (tbody) {
			tbody.innerHTML = '';
		}
	}
}
