@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Balancear Telares')

@section('content')
<div class="w-full flex">
	<!-- Tabla principal -->
	<div class="flex-1 overflow-hidden">
		@if(isset($gruposCompartidos) && count($gruposCompartidos) > 0)
		<div class="bg-white">
			<div class="overflow-x-auto" style="max-height: calc(100vh - 70px);">
				<table class="w-full">
					<thead class="bg-blue-500 text-white sticky top-0 z-10">
						<tr>
							<th class="px-4 py-3 text-left text-xs tracking-wider">Orden</th>
							<th class="px-4 py-3 text-left text-xs tracking-wider">Telar</th>
							<th class="px-4 py-3 text-left text-xs tracking-wider">Nombre Producto</th>
							<th class="px-4 py-3 text-right text-xs tracking-wider">Cantidad</th>
							<th class="px-4 py-3 text-right text-xs tracking-wider">Saldo</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-200">
						@foreach($gruposCompartidos as $ordCompartida => $registros)
						@php
							$totalCantidad = $registros->sum('TotalPedido');
							$totalSaldo = $registros->sum('SaldoPedido');
							$primerRegistro = $registros->first();
							$telaresLista = $registros->pluck('NoTelarId')->implode(', ');
							$productosUnicos = $registros->pluck('NombreProducto')->filter()->unique()->values();
							$productosDisplay = $productosUnicos->take(3)->implode(', ');
							if ($productosUnicos->count() > 3) {
								$productosDisplay .= ' +' . ($productosUnicos->count() - 3) . ' más';
							}
							$productosTitulo = $productosUnicos->implode(', ');
						@endphp
						<tr class="transition-colors border-b border-gray-200 cursor-pointer selectable-row"
							data-ord-compartida="{{ $ordCompartida }}"
							onclick="seleccionarFila(this, {{ $ordCompartida }})">
							<!-- Orden Compartida -->
							<td class="px-4 py-3 text-sm font-bold">
								{{ $ordCompartida }}
							</td>
							<!-- Telares (lista separada por comas) -->
							<td class="px-4 py-3 text-sm">
								{{ $telaresLista }}
							</td>
							<!-- Producto (únicos; muestra primeros y resume si hay muchos) -->
							<td class="px-4 py-3 text-sm" title="{{ $productosTitulo ?: ($primerRegistro->NombreProducto ?? '') }}">
								{{ $productosDisplay ?: ($primerRegistro->NombreProducto ?? '') }}
							</td>
							<!-- Cantidad Total -->
							<td class="px-4 py-3 text-sm text-right font-medium">
								{{ number_format($totalCantidad, 0) }}
							</td>
							<!-- Saldo Total -->
							<td class="px-4 py-3 text-sm text-right font-medium saldo-cell {{ $totalSaldo > 0 ? 'text-green-600' : 'text-gray-500' }}">
								{{ number_format($totalSaldo, 0) }}
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
		@else
		<!-- Estado vacío -->
		<div class="p-12 bg-white text-center">
			<div class="w-20 h-20 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
				<i class="fa-solid fa-scale-balanced text-3xl text-gray-400"></i>
			</div>
			<h3 class="text-lg font-medium text-gray-900 mb-2">No hay registros compartidos</h3>
			<p class="text-gray-500 mb-6">
				Cuando dividas un registro entre varios telares, aparecerán aquí para poder balancearlos.
			</p>
			<a href="{{ route('catalogos.req-programa-tejido') }}"
				class="inline-flex items-center gap-2 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
				<i class="fa-solid fa-arrow-left"></i>
				Volver a Programa Tejido
			</a>
		</div>
		@endif
	</div>

	<!-- Panel lateral con botón Detalles -->
	@if(isset($gruposCompartidos) && count($gruposCompartidos) > 0)
	<div class="w-32 bg-white flex flex-col items-center justify-start">
		<button type="button" id="btnVerDetalles"
			onclick="verDetallesSeleccionado()"
			class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 text-sm font-medium"
			title="Ver detalles" disabled>
			<i class="fa-solid fa-eye"></i>
			Detalles
		</button>
	</div>
	@endif
</div>

<style>
	.selectable-row {
		color: #1f2937; /* text-gray-800 */
	}
	.selectable-row.selected {
		background-color: #3b82f6 !important; /* bg-blue-500 */
		color: white !important;
	}
	.selectable-row.selected td {
		color: white !important;
	}
	.selectable-row.selected .saldo-cell {
		color: white !important;
	}
	.selectable-row:not(.selected):hover {
		background-color: #eff6ff;
	}
</style>

<script>
// Datos de los grupos para el modal (inyectados desde PHP)
const gruposData = @json($gruposCompartidos ?? []);
let filaSeleccionada = null;
let ordCompartidaSeleccionada = null;
let adjustingPedidos = false;
let adjustingFromTotal = false;
let lastEditedInput = null;
let totalDisponibleBalanceo = null;

// Asegurar que la función esté disponible globalmente
window.seleccionarFila = function(fila, ordCompartida) {
	// Deseleccionar fila anterior
	document.querySelectorAll('.selectable-row.selected').forEach(row => {
		row.classList.remove('selected');
	});

	// Seleccionar nueva fila
	fila.classList.add('selected');
	filaSeleccionada = fila;
	ordCompartidaSeleccionada = ordCompartida;

	// Habilitar botón de detalles
	const btnDetalles = document.getElementById('btnVerDetalles');
	if (btnDetalles) {
		btnDetalles.disabled = false;
	}
};

window.verDetallesSeleccionado = function() {
	if (!ordCompartidaSeleccionada) {
		Swal.fire({
			icon: 'info',
			title: 'Selecciona una fila',
			text: 'Por favor, selecciona una fila de la tabla para ver los detalles.',
			confirmButtonColor: '#3b82f6'
		});
		return;
	}

	verDetallesGrupo(ordCompartidaSeleccionada);
};

function formatearFecha(fecha) {
	if (!fecha) return '-';
	try {
		const d = new Date(fecha);
		if (d.getFullYear() <= 1970) return '-';
		return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
	} catch (e) {
		return '-';
	}
}

function verDetallesGrupo(ordCompartida) {
	// Buscar los registros del grupo
	const registros = gruposData[ordCompartida] || [];

	if (registros.length === 0) {
		Swal.fire({
			icon: 'error',
			title: 'Sin registros',
			text: 'No se encontraron registros para esta orden compartida.',
			confirmButtonColor: '#3b82f6'
		});
		return;
	}

	// Calcular totales
	const totalPedido = registros.reduce((sum, r) => sum + (Number(r.TotalPedido) || 0), 0);
	const totalProduccion = registros.reduce((sum, r) => sum + (Number(r.Produccion) || 0), 0);
	const totalSaldo = registros.reduce((sum, r) => sum + (Number(r.SaldoPedido) || 0), 0);
	totalDisponibleBalanceo = totalPedido; // guardar target para ajustes
	const primerRegistro = registros[0];

	// Generar filas de la tabla con input para Pedido
	// Guardamos datos originales para calcular la fecha final proporcionalmente
	let filasHTML = registros.map((reg, index) => {
		const fechaInicio = reg.FechaInicio ? new Date(reg.FechaInicio).getTime() : null;
		const fechaFinal = reg.FechaFinal ? new Date(reg.FechaFinal).getTime() : null;
		const duracionOriginalMs = (fechaInicio && fechaFinal) ? (fechaFinal - fechaInicio) : 0;

		return `
		<tr class="hover:bg-gray-50 border-b border-gray-200" data-registro-id="${reg.Id}">
			<td class="px-3 py-2 text-sm font-medium text-gray-900">${reg.NoTelarId || '-'}</td>
			<td class="px-3 py-2 text-sm text-gray-600">${reg.NombreProducto || '-'}</td>
			<td class="px-3 py-2">
				<input type="number"
					class="pedido-input w-full px-1 py-0.5 text-xs text-right border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
					data-id="${reg.Id}"
					data-original="${reg.TotalPedido || 0}"
					data-fecha-inicio="${fechaInicio || ''}"
					data-duracion-original="${duracionOriginalMs}"
					value="${reg.TotalPedido || 0}"
					min="0"
					step="1"
					oninput="calcularTotalesYFechas(this)">
			</td>
			<td class="px-3 py-2 text-sm text-right">${Number(reg.Produccion || 0).toLocaleString('es-MX')}</td>
			<td class="px-3 py-2 text-sm text-right saldo-display ${(reg.SaldoPedido || 0) > 0 ? 'text-green-600 font-medium' : 'text-gray-500'}" data-produccion="${reg.Produccion || 0}">${Number(reg.SaldoPedido || 0).toLocaleString('es-MX')}</td>
			<td class="px-3 py-2 text-sm text-center text-gray-600">${formatearFecha(reg.FechaInicio)}</td>
			<td class="px-3 py-2 text-sm text-center text-gray-600 fecha-final-display">${formatearFecha(reg.FechaFinal)}</td>
		</tr>
		`;
	}).join('');

	const htmlContent = `
		<div class="text-left">
			<!-- Botón de balanceo automático -->
			<div class="mb-3 flex justify-end">
				<button type="button" onclick="aplicarBalanceoAutomatico()"
					class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors flex items-center gap-2 text-sm font-medium">
					<i class="fa-solid fa-scale-balanced"></i>
					Balancear Fechas
				</button>
			</div>
			<div class="overflow-x-auto border border-gray-200 rounded-lg">
				<table class="w-full" id="tabla-detalles">
					<thead class="bg-blue-500 text-white">
						<tr>
							<th class="px-3 py-2 text-left text-xs">Telar</th>
							<th class="px-3 py-2 text-left text-xs">Producto</th>
							<th class="px-3 py-2 text-right text-xs">Pedido</th>
							<th class="px-3 py-2 text-right text-xs">Producción</th>
							<th class="px-3 py-2 text-right text-xs">Saldo</th>
							<th class="px-3 py-2 text-center text-xs">F.Inicio</th>
							<th class="px-3 py-2 text-center text-xs">F.Final</th>
						</tr>
					</thead>
					<tbody>
						${filasHTML}
					</tbody>
					<tfoot class="bg-gray-100">
						<tr>
							<td colspan="2" class="px-3 py-2 text-sm font-semibold text-gray-700 text-right">Totales:</td>
							<td class="px-3 py-2 text-sm text-right">
								<input type="number"
									id="total-pedido-input"
									class="w-full px-1 py-0.5 text-xs text-right font-bold text-gray-900 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
									value="${totalPedido}"
									min="0"
									step="1"
									oninput="actualizarPedidosDesdeTotal(this)">
							</td>
							<td class="px-3 py-2 text-sm text-right font-bold text-gray-900">${totalProduccion.toLocaleString('es-MX')}</td>
							<td class="px-3 py-2 text-sm text-right font-bold text-green-600" id="total-saldo">${totalSaldo.toLocaleString('es-MX')}</td>
							<td colspan="2"></td>
						</tr>
					</tfoot>
				</table>
				<!-- Total disponible (hidden) para conservar suma de pedido -->
				<div id="total-disponible" class="hidden">{{ $gruposCompartidos ? '' : '' }}{{ $gruposCompartidos ? '' : '' }}${totalPedido}</div>
			</div>
		</div>
	`;

	Swal.fire({
		title: 'Detalles de orden',
		html: htmlContent,
		width: '850px',
		showCloseButton: true,
		showConfirmButton: true,
		confirmButtonText: '<i class="fa-solid fa-save mr-2"></i> Guardar',
		confirmButtonColor: '#3b82f6',
		showCancelButton: true,
		cancelButtonText: 'Cancelar',
		cancelButtonColor: '#6b7280',
		customClass: {
			htmlContainer: 'text-left'
		},
		preConfirm: () => {
			return guardarCambiosPedido(ordCompartida);
		}
	});
}

window.calcularTotalesYFechas = function(changedInput = null) {
	if (adjustingPedidos) return;
	lastEditedInput = changedInput || lastEditedInput;

	const inputs = Array.from(document.querySelectorAll('.pedido-input'));
	let totalPedido = 0;
	let totalSaldo = 0;

	// Parsing helper
	const parseNumber = (val) => {
		if (val === null || val === undefined) return 0;
		const n = Number(String(val).replace(/[^0-9.\-]/g, ''));
		return isNaN(n) ? 0 : n;
	};

	inputs.forEach(input => {
		// Redondear el valor del input a entero si tiene decimales
		if (input.value && input.value.includes('.')) {
			input.value = Math.round(Number(input.value) || 0);
		}
		const pedido = Math.round(Number(input.value) || 0);
		const pedidoOriginal = Number(input.dataset.original) || 0;
		const fechaInicioMs = Number(input.dataset.fechaInicio) || 0;
		const duracionOriginalMs = Number(input.dataset.duracionOriginal) || 0;

		const row = input.closest('tr');
		const saldoCell = row.querySelector('.saldo-display');
		const fechaFinalCell = row.querySelector('.fecha-final-display');
		const produccion = Number(saldoCell.dataset.produccion) || 0;
		const saldo = pedido - produccion;

		totalPedido += pedido;
		totalSaldo += saldo;

		// Actualizar saldo en la celda
		saldoCell.textContent = saldo.toLocaleString('es-MX');
		saldoCell.className = `px-3 py-2 text-sm text-right saldo-display ${saldo > 0 ? 'text-green-600 font-medium' : 'text-gray-500'}`;

		// Calcular nueva fecha final proporcionalmente
		if (fechaInicioMs && duracionOriginalMs && pedidoOriginal > 0 && fechaFinalCell) {
			const factor = pedido / pedidoOriginal; // Factor = nueva cantidad / cantidad original
			const nuevaDuracionMs = duracionOriginalMs * factor;
			const nuevaFechaFinalMs = fechaInicioMs + nuevaDuracionMs;
			const nuevaFechaFinal = new Date(nuevaFechaFinalMs);

			fechaFinalCell.textContent = formatearFecha(nuevaFechaFinal.toISOString());
			input.dataset.fechaFinalCalculada = nuevaFechaFinalMs; // Guardar timestamp calculado

			// Resaltar si cambió
			if (factor !== 1) {
				fechaFinalCell.classList.add('text-blue-600', 'font-medium');
			} else {
				fechaFinalCell.classList.remove('text-blue-600', 'font-medium');
			}
		}
	});

	// Ajustar automáticamente para conservar el total original (lógica original restaurada)
	// PERO solo si NO estamos ajustando desde el total
	if (!adjustingFromTotal) {
		const totalDisponibleEl = document.getElementById('total-disponible');
		const totalDisponible = totalDisponibleEl ? parseNumber(totalDisponibleEl.textContent) : (totalDisponibleBalanceo || inputs.reduce((s, i) => s + (Number(i.dataset.original) || 0), 0));
		if (totalDisponible > 0 && inputs.length > 1) {
			const diff = totalDisponible - totalPedido;
			if (Math.abs(diff) > 0.0001) {
				// Elegir un input distinto al que se editó para compensar
				const targets = inputs.filter(inp => inp !== lastEditedInput);
				const target = targets[0] || inputs[0];
				const valActual = Number(target.value) || 0;
				let nuevoValor = Math.round(valActual + diff);
				if (nuevoValor < 0) {
					nuevoValor = 0;
				}
				adjustingPedidos = true;
				target.value = nuevoValor;
				adjustingPedidos = false;
				// Recalcular con el ajuste aplicado
				return calcularTotalesYFechas(target);
			}
		}
	}

	// Actualizar totales
	const totalPedidoInput = document.getElementById('total-pedido-input');
	const totalSaldoEl = document.getElementById('total-saldo');

	// Solo actualizar el input del total si no estamos ajustando desde el total
	// y si no está enfocado (para evitar conflictos cuando el usuario lo edita)
	if (totalPedidoInput && !adjustingPedidos && !adjustingFromTotal && document.activeElement !== totalPedidoInput) {
		totalPedidoInput.value = totalPedido;
	}

	if (totalSaldoEl) totalSaldoEl.textContent = totalSaldo.toLocaleString('es-MX');

	// Actualizar la referencia del total disponible
	const totalDisponibleEl = document.getElementById('total-disponible');
	if (totalDisponibleEl) {
		totalDisponibleEl.textContent = totalPedido;
		if (totalDisponibleBalanceo !== null) {
			totalDisponibleBalanceo = totalPedido;
		}
	}
};

// Alias para compatibilidad
window.calcularTotales = function() {
	calcularTotalesYFechas();
};

// Función para actualizar los inputs individuales cuando se cambia el total
window.actualizarPedidosDesdeTotal = function(totalInput) {
	if (adjustingPedidos || adjustingFromTotal) return;

	// Redondear el valor del total a entero si tiene decimales
	if (totalInput.value && totalInput.value.includes('.')) {
		totalInput.value = Math.round(Number(totalInput.value) || 0);
	}
	const nuevoTotal = Math.round(Number(totalInput.value) || 0);
	const inputs = Array.from(document.querySelectorAll('.pedido-input'));

	if (inputs.length === 0) return;

	// Calcular el total actual
	let totalActual = 0;
	inputs.forEach(input => {
		totalActual += Number(input.value) || 0;
	});

	const diferencia = nuevoTotal - totalActual;

	if (Math.abs(diferencia) < 0.0001) {
		// No hay diferencia, no hacer nada
		return;
	}

	// Marcar que estamos ajustando desde el total
	adjustingFromTotal = true;

	// Distribuir la diferencia proporcionalmente entre los inputs
	// O si solo hay un input, agregar toda la diferencia a ese
	if (inputs.length === 1) {
		const input = inputs[0];
		const valorActual = Number(input.value) || 0;
		input.value = Math.round(Math.max(0, valorActual + diferencia));
		calcularTotalesYFechas(input);
	} else {
		// Distribuir proporcionalmente según el valor actual de cada input
		let diferenciaRestante = diferencia;

		inputs.forEach((input, index) => {
			const valorActual = Number(input.value) || 0;
			const proporcion = totalActual > 0 ? valorActual / totalActual : 1 / inputs.length;
			const diferenciaInput = diferencia * proporcion;

			if (index === inputs.length - 1) {
				// El último input recibe la diferencia restante para evitar errores de redondeo
				input.value = Math.round(Math.max(0, valorActual + diferenciaRestante));
			} else {
				const nuevoValor = Math.round(Math.max(0, valorActual + diferenciaInput));
				input.value = nuevoValor;
				diferenciaRestante -= diferenciaInput;
			}
		});

		calcularTotalesYFechas();
	}

	adjustingFromTotal = false;
};

/**
 * Aplica balanceo automático tipo "bubble sort" iterativo
 * Mueve cantidades del telar que termina más tarde al que termina más temprano
 * hasta que las fechas finales converjan lo más posible
 */
window.aplicarBalanceoAutomatico = function() {
	const inputs = document.querySelectorAll('.pedido-input');

	if (inputs.length < 2) {
		Swal.fire({
			icon: 'info',
			title: 'No se puede balancear',
			text: 'Se necesitan al menos 2 telares para balancear.',
			confirmButtonColor: '#3b82f6'
		});
		return;
	}

	// Recopilar datos de cada telar
	const telares = [];
	let cantidadTotal = 0;

	inputs.forEach(input => {
		const pedidoOriginal = Number(input.dataset.original) || 0;
		const fechaInicioMs = Number(input.dataset.fechaInicio) || 0;
		const duracionOriginalMs = Number(input.dataset.duracionOriginal) || 0;
		const row = input.closest('tr');
		const produccion = Number(row.querySelector('.saldo-display')?.dataset.produccion) || 0;

		// Calcular tasa de producción (cantidad por milisegundo)
		let tasaProduccion = 0;
		if (duracionOriginalMs > 0 && pedidoOriginal > 0) {
			tasaProduccion = pedidoOriginal / duracionOriginalMs;
		}

		telares.push({
			input: input,
			row: row,
			pedidoOriginal: pedidoOriginal,
			cantidad: Number(input.value) || 0,
			fechaInicioMs: fechaInicioMs,
			duracionOriginalMs: duracionOriginalMs,
			tasaProduccion: tasaProduccion,
			produccion: produccion
		});

		cantidadTotal += Number(input.value) || 0;
	});

	// Verificar que todos los telares tengan datos válidos
	const telaresValidos = telares.filter(t => t.tasaProduccion > 0 && t.fechaInicioMs > 0);

	if (telaresValidos.length < 2) {
		Swal.fire({
			icon: 'warning',
			title: 'Datos insuficientes',
			text: 'No hay suficientes telares con datos válidos para balancear.',
			confirmButtonColor: '#3b82f6'
		});
		return;
	}

	// Función para calcular la fecha final de un telar dado su cantidad actual
	function calcularFechaFinal(telar) {
		if (telar.tasaProduccion <= 0 || telar.cantidad <= 0) return telar.fechaInicioMs;
		const duracionMs = telar.cantidad / telar.tasaProduccion;
		return telar.fechaInicioMs + duracionMs;
	}

	// Función para obtener la diferencia máxima entre fechas finales (en días)
	function getDiferenciaMaximaDias() {
		const fechasFinales = telaresValidos.map(t => calcularFechaFinal(t));
		const maxFecha = Math.max(...fechasFinales);
		const minFecha = Math.min(...fechasFinales);
		return (maxFecha - minFecha) / (1000 * 60 * 60 * 24);
	}

	// Algoritmo iterativo tipo bubble sort
	const maxIteraciones = 1000;
	const toleranciaDias = 0.5; // Tolerancia de medio día
	const cantidadMinima = 100; // Cantidad mínima que debe tener cada telar
	let iteracion = 0;

	console.log('=== INICIO BALANCEO ===');
	console.log('Cantidad total:', cantidadTotal);
	console.log('Telares válidos:', telaresValidos.length);

	while (iteracion < maxIteraciones) {
		// Calcular fechas finales actuales
		telaresValidos.forEach(t => {
			t.fechaFinalMs = calcularFechaFinal(t);
		});

		// Encontrar el telar que termina más tarde y el que termina más temprano
		let telarTarde = telaresValidos[0];
		let telarTemprano = telaresValidos[0];

		telaresValidos.forEach(t => {
			if (t.fechaFinalMs > telarTarde.fechaFinalMs) telarTarde = t;
			if (t.fechaFinalMs < telarTemprano.fechaFinalMs) telarTemprano = t;
		});

		// Calcular diferencia en días
		const diferenciaDias = (telarTarde.fechaFinalMs - telarTemprano.fechaFinalMs) / (1000 * 60 * 60 * 24);

		// Si la diferencia es menor a la tolerancia, terminamos
		if (diferenciaDias <= toleranciaDias) {
			console.log(`Convergió en iteración ${iteracion}, diferencia: ${diferenciaDias.toFixed(2)} días`);
			break;
		}

		// Calcular cuánta cantidad mover
		// Queremos que ambos terminen en una fecha intermedia
		// Diferencia de tiempo = diferenciaDias / 2 (en ms)
		const tiempoAMoverMs = (telarTarde.fechaFinalMs - telarTemprano.fechaFinalMs) / 2;

		// Cantidad a mover del telar tarde = tiempo * su tasa de producción
		let cantidadAMover = Math.floor(telarTarde.tasaProduccion * tiempoAMoverMs);

		// Limitar la cantidad a mover para no dejar al telar tarde con menos del mínimo
		const maxPuedeQuitar = telarTarde.cantidad - cantidadMinima;
		if (cantidadAMover > maxPuedeQuitar) {
			cantidadAMover = Math.max(0, maxPuedeQuitar);
		}

		// Si no podemos mover nada, terminamos
		if (cantidadAMover <= 0) {
			console.log(`No se puede mover más en iteración ${iteracion}`);
			break;
		}

		// Mover la cantidad
		telarTarde.cantidad -= cantidadAMover;
		telarTemprano.cantidad += cantidadAMover;

		iteracion++;
	}

	console.log(`Terminado en ${iteracion} iteraciones`);
	console.log('Diferencia final:', getDiferenciaMaximaDias().toFixed(2), 'días');

	// Aplicar las nuevas cantidades a los inputs
	telaresValidos.forEach(telar => {
		telar.input.value = Math.round(telar.cantidad);
		telar.input.classList.add('bg-green-50', 'border-green-500');
	});

	// Recalcular totales y fechas visualmente
	calcularTotalesYFechas();

	// Calcular fechas finales para mostrar en el banner
	const fechasFinales = telaresValidos.map(t => calcularFechaFinal(t));
	const fechaFinalPromedio = new Date(fechasFinales.reduce((a, b) => a + b, 0) / fechasFinales.length);
	const diferenciaFinalDias = getDiferenciaMaximaDias();

	// Crear banner informativo
	const tablaDetalles = document.getElementById('tabla-detalles');
	if (tablaDetalles) {
		const bannerAnterior = document.getElementById('banner-balanceo');
		if (bannerAnterior) bannerAnterior.remove();

		const banner = document.createElement('div');
		banner.id = 'banner-balanceo';
		banner.className = 'mb-2 p-2 bg-green-100 border border-green-300 rounded-lg text-sm text-green-800 flex items-center gap-2';
		banner.innerHTML = `
			<i class="fa-solid fa-check-circle"></i>
			<span><strong>Balanceo aplicado.</strong> Diferencia entre fechas: <strong>${diferenciaFinalDias.toFixed(1)} días</strong>. Presiona "Guardar" para confirmar.</span>
		`;
		tablaDetalles.parentElement.insertBefore(banner, tablaDetalles);
	}
};

async function guardarCambiosPedido(ordCompartida) {
	const inputs = document.querySelectorAll('.pedido-input');
	const cambios = [];

	inputs.forEach(input => {
		const id = input.dataset.id;
		const valorOriginal = Number(input.dataset.original) || 0;
		const valorNuevo = Number(input.value) || 0;
		const fechaFinalCalculadaMs = Number(input.dataset.fechaFinalCalculada) || 0;

		if (valorOriginal !== valorNuevo) {
			const cambio = {
				id: id,
				total_pedido: valorNuevo
			};

			// Incluir fecha final calculada si existe
			if (fechaFinalCalculadaMs > 0) {
				const fechaFinal = new Date(fechaFinalCalculadaMs);
				cambio.fecha_final = fechaFinal.toISOString().slice(0, 19).replace('T', ' ');
			}

			cambios.push(cambio);
		}
	});

	if (cambios.length === 0) {
		Swal.showValidationMessage('No hay cambios para guardar');
		return false;
	}

	try {
		const response = await fetch('/planeacion/programa-tejido/actualizar-pedidos-balanceo', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
			},
			body: JSON.stringify({
				cambios: cambios,
				ord_compartida: ordCompartida
			})
		});

		const data = await response.json();

		if (data.success) {
			// Actualizar datos locales
			cambios.forEach(cambio => {
				const registros = gruposData[ordCompartida] || [];
				const registro = registros.find(r => r.Id == cambio.id);
				if (registro) {
					registro.TotalPedido = cambio.total_pedido;
					registro.SaldoPedido = cambio.total_pedido - (registro.Produccion || 0);
					if (cambio.fecha_final) {
						registro.FechaFinal = cambio.fecha_final;
					}
				}
			});

			// Actualizar la tabla principal
			actualizarFilaPrincipal(ordCompartida);

			Swal.fire({
				icon: 'success',
				title: 'Guardado',
				text: data.message || 'Los cambios se guardaron correctamente',
				confirmButtonColor: '#3b82f6',
				timer: 2000,
				showConfirmButton: false
			});
			return true;
		} else {
			Swal.showValidationMessage(data.message || 'Error al guardar los cambios');
			return false;
		}
	} catch (error) {
		Swal.showValidationMessage('Error de conexión al guardar los cambios');
		return false;
	}
}

function actualizarFilaPrincipal(ordCompartida) {
	const registros = gruposData[ordCompartida] || [];
	const totalCantidad = registros.reduce((sum, r) => sum + (Number(r.TotalPedido) || 0), 0);
	const totalSaldo = registros.reduce((sum, r) => sum + (Number(r.SaldoPedido) || 0), 0);

	const fila = document.querySelector(`tr[data-ord-compartida="${ordCompartida}"]`);
	if (fila) {
		const celdas = fila.querySelectorAll('td');
		if (celdas[3]) celdas[3].textContent = totalCantidad.toLocaleString('es-MX');
		if (celdas[4]) {
			celdas[4].textContent = totalSaldo.toLocaleString('es-MX');
			celdas[4].className = `px-4 py-3 text-sm text-right font-medium saldo-cell ${totalSaldo > 0 ? 'text-green-600' : 'text-gray-500'}`;
		}
	}
}

// Doble clic para ver detalles
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.selectable-row').forEach(row => {
		row.addEventListener('dblclick', function() {
			const ordCompartida = this.dataset.ordCompartida;
			if (ordCompartida) {
				verDetallesGrupo(parseInt(ordCompartida));
			}
		});
	});
});
</script>

@include('components.ui.toast-notification')
@endsection

