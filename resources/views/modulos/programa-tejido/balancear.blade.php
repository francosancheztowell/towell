<script>
	let adjustingPedidos = false;
	let adjustingFromTotal = false;
	let lastEditedInput = null;
	let totalDisponibleBalanceo = null;
	let gruposDataCache = {};
let lineasCache = {}; // programaId => líneas originales
let currentGanttRegistros = []; // registros actuales en modal

	function formatearFecha(fecha) {
		if (!fecha) return '-';
		try {
			const d = new Date(fecha);
			if (d.getFullYear() <= 1970) return '-';
			return d.toLocaleDateString('es-MX', {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric'
			});
		} catch (e) {
			return '-';
		}
	}

	function getCurrentInputsMap() {
		const map = {};
		document.querySelectorAll('.pedido-input').forEach(inp => {
			const id = inp.dataset.id;
			if (!id) return;
			map[id] = {
				pedido: Number(inp.value) || 0,
				pedidoOriginal: Number(inp.dataset.original) || 0,
				fechaInicioMs: Number(inp.dataset.fechaInicio) || 0,
				duracionOriginalMs: Number(inp.dataset.duracionOriginal) || 0,
				fechaFinalCalcMs: Number(inp.dataset.fechaFinalCalculada) || 0,
			};
		});
		return map;
	}

	async function fetchRegistrosOrdCompartida(ordCompartida) {
		if (gruposDataCache[ordCompartida]) return gruposDataCache[ordCompartida];
		const resp = await fetch(`/planeacion/programa-tejido/registros-ord-compartida/${ordCompartida}`, {
			headers: {
				'Accept': 'application/json'
			}
		});
		const data = await resp.json();
		if (data?.success && Array.isArray(data.registros)) {
			gruposDataCache[ordCompartida] = data.registros;
			return data.registros;
		}
		throw new Error(data?.message || 'No se pudieron obtener los registros');
	}

	window.verDetallesGrupoBalanceo = async function(ordCompartida) {
		try {
			const registros = await fetchRegistrosOrdCompartida(ordCompartida);
		currentGanttRegistros = registros;
			if (!registros.length) {
				Swal.fire({
					icon: 'error',
					title: 'Sin registros',
					text: 'No se encontraron registros para esta orden compartida.',
					confirmButtonColor: '#3b82f6'
				});
				return;
			}

			const totalPedido = registros.reduce((sum, r) => sum + (Number(r.TotalPedido) || 0), 0);
			const totalProduccion = registros.reduce((sum, r) => sum + (Number(r.Produccion) || 0), 0);
			const totalSaldo = registros.reduce((sum, r) => sum + (Number(r.SaldoPedido) || 0), 0);
			totalDisponibleBalanceo = totalPedido;
			lineasCache = {}; // reset cache de líneas

			const filasHTML = registros.map(reg => {
				const fechaInicio = reg.FechaInicio ? new Date(reg.FechaInicio).getTime() : null;
				const fechaFinal = reg.FechaFinal ? new Date(reg.FechaFinal).getTime() : null;
				const duracionOriginalMs = (fechaInicio && fechaFinal) ? (fechaFinal - fechaInicio) : 0;

				return `
			<tr class="hover:bg-gray-50 border-b border-gray-200" data-registro-id="${reg.Id}">
				<td class="px-3 py-2 text-sm font-medium text-gray-900">${reg.NoTelarId || '-'}</td>
				<td class="px-3 py-2 text-sm text-gray-600">${reg.NombreProducto || '-'}</td>
				<td class="px-3 py-2 text-sm text-right text-gray-600">${Number(reg.StdDia || 0).toFixed(0)}</td>
				<td class="px-3 py-2 text-right">
					<input type="number"
						class="pedido-input w-24 px-2 py-1 text-sm text-right border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
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
				<style>
					#gantt-ord-container {
						overflow-x: auto;
						border: 1px solid #e5e7eb;
						border-radius: 8px;
						background: #fff;
					}
					.gantt-grid {
						display: grid;
						grid-auto-rows: minmax(34px, auto);
						width: max-content;
					}
					.gantt-cell {
						border: 1px solid #e5e7eb;
						padding: 6px 8px;
						font-size: 12px;
						line-height: 1.25;
						text-align: center;
						min-width: 80px;
						box-sizing: border-box;
					}
					.gantt-header {
						background: #f9fafb;
						font-weight: 600;
						color: #374151;
						position: sticky;
						top: 0;
						z-index: 1;
					}
					.gantt-label {
						font-weight: 600;
						background: #f3f4f6;
						text-align: left;
						position: sticky;
						left: 0;
						z-index: 2;
					}
					.gantt-row {
						display: contents;
					}
					.gantt-bar {
						background: #fef2f2;
						color: #991b1b;
						font-weight: 600;
					}
					.gantt-bar-alt {
						background: #ecfdf3;
						color: #166534;
						font-weight: 600;
					}
				</style>
				<div class="mb-3 flex justify-end">
					<button type="button" onclick="aplicarBalanceoAutomatico()"
						class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors flex items-center gap-2 text-sm font-medium">
						<i class="fa-solid fa-scale-balanced"></i>
						Balancear Fechas
					</button>
				</div>
				<div class="flex flex-row gap-4 items-stretch justify-between" style="flex-wrap: nowrap;">
					<div class="overflow-x-auto border border-gray-200 rounded-lg bg-white flex flex-col" style="flex: 0 0 42%; min-width: 370px;">
						<table class="w-full" id="tabla-detalles">
							<thead class="bg-blue-500 text-white">
								<tr>
									<th class="px-3 py-2 text-left text-xs">Telar</th>
									<th class="px-3 py-2 text-left text-xs">Producto</th>
									<th class="px-3 py-2 text-right text-xs">Std/Día</th>
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
									<td colspan="3" class="px-3 py-2 text-sm font-semibold text-gray-700 text-right">Totales:</td>
									<td class="px-3 py-2 text-right">
										<input type="number"
											id="total-pedido-input"
											class="w-24 px-2 py-1 text-sm text-right font-bold text-gray-900 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
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
						<div id="total-disponible" class="hidden">${totalPedido}</div>
					</div>

					<div class="border border-gray-200 rounded-lg p-1 bg-white flex flex-col" style="flex: 1 1 0; min-width: 460px;">
						<div id="gantt-ord-container" class="min-h-[150px] max-h-[70vh] overflow-auto">
							<div id="gantt-loading" class="p-3 text-sm text-gray-500">Cargando líneas...</div>
							<div id="gantt-ord"></div>
						</div>

					</div>
				</div>
			</div>
		`;

			Swal.fire({
				title: 'Balanceo de orden',
				html: htmlContent,
				width: '100%',
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
				},
				didOpen: () => {
					renderGanttOrd(registros, ordCompartida);
				}
			});
		} catch (error) {
			console.error('Error al cargar detalles:', error);
			Swal.fire({
				icon: 'error',
				title: 'Error',
				text: 'No se pudieron cargar los detalles de la orden compartida.',
				confirmButtonColor: '#3b82f6'
			});
		}
	};

	window.calcularTotalesYFechas = function(changedInput = null) {
		if (adjustingPedidos) return;
		lastEditedInput = changedInput || lastEditedInput;

		const inputs = Array.from(document.querySelectorAll('.pedido-input'));
		let totalPedido = 0;
		let totalSaldo = 0;

		const parseNumber = (val) => {
			if (val === null || val === undefined) return 0;
			const n = Number(String(val).replace(/[^0-9.\-]/g, ''));
			return isNaN(n) ? 0 : n;
		};

		inputs.forEach(input => {
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

			saldoCell.textContent = saldo.toLocaleString('es-MX');
			saldoCell.className = `px-3 py-2 text-sm text-right saldo-display ${saldo > 0 ? 'text-green-600 font-medium' : 'text-gray-500'}`;

			if (fechaInicioMs && duracionOriginalMs && pedidoOriginal > 0 && fechaFinalCell) {
				const factor = pedido / pedidoOriginal;
				const nuevaDuracionMs = duracionOriginalMs * factor;
				const nuevaFechaFinalMs = fechaInicioMs + nuevaDuracionMs;
				const nuevaFechaFinal = new Date(nuevaFechaFinalMs);

				fechaFinalCell.textContent = formatearFecha(nuevaFechaFinal.toISOString());
				input.dataset.fechaFinalCalculada = nuevaFechaFinalMs;

				if (factor !== 1) {
					fechaFinalCell.classList.add('text-blue-600', 'font-medium');
				} else {
					fechaFinalCell.classList.remove('text-blue-600', 'font-medium');
				}
			}
		});

		if (!adjustingFromTotal) {
			const totalDisponibleEl = document.getElementById('total-disponible');
			const totalDisponible = totalDisponibleEl ? parseNumber(totalDisponibleEl.textContent) : (totalDisponibleBalanceo || inputs.reduce((s, i) => s + (Number(i.dataset.original) || 0), 0));
			if (totalDisponible > 0 && inputs.length > 1) {
				const diff = totalDisponible - totalPedido;
				if (Math.abs(diff) > 0.0001) {
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
					return calcularTotalesYFechas(target);
				}
			}
		}

		const totalPedidoInput = document.getElementById('total-pedido-input');
		const totalSaldoEl = document.getElementById('total-saldo');

		if (totalPedidoInput && !adjustingPedidos && !adjustingFromTotal && document.activeElement !== totalPedidoInput) {
			totalPedidoInput.value = totalPedido;
		}

		if (totalSaldoEl) totalSaldoEl.textContent = totalSaldo.toLocaleString('es-MX');

		const totalDisponibleEl = document.getElementById('total-disponible');
		if (totalDisponibleEl) {
			totalDisponibleEl.textContent = totalPedido;
			if (totalDisponibleBalanceo !== null) {
				totalDisponibleBalanceo = totalPedido;
			}
		}

		// actualizar vista previa del gantt con los cambios locales
		updateGanttPreview();
	};

	window.actualizarPedidosDesdeTotal = function(totalInput) {
		if (adjustingPedidos || adjustingFromTotal) return;

		if (totalInput.value && totalInput.value.includes('.')) {
			totalInput.value = Math.round(Number(totalInput.value) || 0);
		}
		const nuevoTotal = Math.round(Number(totalInput.value) || 0);
		const inputs = Array.from(document.querySelectorAll('.pedido-input'));

		if (inputs.length === 0) return;

		let totalActual = 0;
		inputs.forEach(input => {
			totalActual += Number(input.value) || 0;
		});

		const diferencia = nuevoTotal - totalActual;

		if (Math.abs(diferencia) < 0.0001) {
			return;
		}

		adjustingFromTotal = true;

		if (inputs.length === 1) {
			const input = inputs[0];
			const valorActual = Number(input.value) || 0;
			input.value = Math.round(Math.max(0, valorActual + diferencia));
			calcularTotalesYFechas(input);
		} else {
			let diferenciaRestante = diferencia;

			inputs.forEach((input, index) => {
				const valorActual = Number(input.value) || 0;
				const proporcion = totalActual > 0 ? valorActual / totalActual : 1 / inputs.length;
				const diferenciaInput = diferencia * proporcion;

				if (index === inputs.length - 1) {
					input.value = Math.round(Math.max(0, valorActual + diferenciaRestante));
				} else {
					const nuevoValor = Math.round(Math.max(0, valorActual + diferenciaInput));
					input.value = nuevoValor;
					diferenciaRestante -= diferenciaInput;
				}
			});

			calcularTotalesYFechas();
			updateGanttPreview();
		}

		adjustingFromTotal = false;
	};

	// =================== GANTT simple (sin librería) ===================
	function parseDateISO(str) {
		if (!str) return null;
		const d = new Date(str);
		return isNaN(d.getTime()) ? null : d;
	}

	function formatShort(d) {
		return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
	}

	function buildDateRange(minDate, maxDate) {
		const res = [];
		const cur = new Date(minDate.getTime());
		while (cur <= maxDate) {
			res.push(new Date(cur.getTime()));
			cur.setDate(cur.getDate() + 1);
		}
		return res;
	}

	function mapWithScaledTimeline(reg, lineas, inputsMap) {
		const datos = inputsMap[String(reg.Id)] || {};
		const nuevoPedido = datos.pedido ?? Number(reg.TotalPedido || 0);
		const pedidoOriginal = datos.pedidoOriginal ?? Number(reg.TotalPedido || 0);
		const factor = pedidoOriginal > 0 ? (nuevoPedido / pedidoOriginal) : 1;

		const fechaInicioMs = datos.fechaInicioMs || (reg.FechaInicio ? new Date(reg.FechaInicio).getTime() : 0);
		const fechaFinalCalcMs = datos.fechaFinalCalcMs || 0;
		const duracionOriginalMs = datos.duracionOriginalMs || 0;
		const fechaFinOriginalMs = duracionOriginalMs > 0 && fechaInicioMs ? fechaInicioMs + duracionOriginalMs : (reg.FechaFinal ? new Date(reg.FechaFinal).getTime() : 0);
		const fechaFinDestinoMs = fechaFinalCalcMs || fechaFinOriginalMs;

		const map = {};

		if (!lineas || !lineas.length || !fechaInicioMs || !fechaFinDestinoMs) {
			const key = fechaInicioMs ? new Date(fechaInicioMs).toISOString().slice(0, 10) : 'N/A';
			map[key] = nuevoPedido;
			return { map, min: fechaInicioMs, max: fechaFinDestinoMs };
		}

		lineas.forEach(l => {
			const d = parseDateISO(l.Fecha);
			if (!d) return;
			const t = d.getTime();
			// si hay una nueva fecha fin calculada, descartamos líneas fuera del rango
			if (fechaInicioMs && t < fechaInicioMs) return;
			if (fechaFinDestinoMs && t > fechaFinDestinoMs) return;
			const key = d.toISOString().slice(0, 10);
			const qty = Number(l.Cantidad || 0) * factor;
			map[key] = (map[key] || 0) + Math.round((qty + Number.EPSILON) * 1000) / 1000;
		});

		const keys = Object.keys(map);
		let minKey = fechaInicioMs;
		let maxKey = fechaFinDestinoMs;
		if (keys.length) {
			const first = new Date(keys[0]);
			const last = new Date(keys[keys.length - 1]);
			minKey = first.getTime();
			maxKey = last.getTime();
		}

		return { map, min: minKey, max: maxKey };
	}

	function updateGanttPreview() {
		if (!currentGanttRegistros || !currentGanttRegistros.length) return;
		const inputsMap = getCurrentInputsMap();
		const rows = [];
		let minD = null;
		let maxD = null;

		currentGanttRegistros.forEach(reg => {
			const lineas = lineasCache[reg.Id] || [];
			const result = mapWithScaledTimeline(reg, lineas, inputsMap);
			const map = result.map || {};
			if (!Object.keys(map).length) return;
			const minLocal = result.min;
			const maxLocal = result.max;
			if (minLocal && (!minD || minLocal < minD)) minD = minLocal;
			if (maxLocal && (!maxD || maxLocal > maxD)) maxD = maxLocal;

			rows.push({
				label: `Telar ${reg.NoTelarId || '-'} · ${reg.NombreProducto || ''}`.trim(),
				map
			});
		});

		if (!rows.length || !minD || !maxD) return;
		const days = buildDateRange(new Date(minD), new Date(maxD));
		renderGanttGrid(days, rows);
	}

	async function fetchLineasPrograma(programaId) {
		if (lineasCache[programaId]) return lineasCache[programaId];
		const resp = await fetch(`/planeacion/req-programa-tejido-line?programa_id=${programaId}&per_page=5000&sort=Fecha&dir=asc`, {
			headers: { 'Accept': 'application/json' }
		});
		const json = await resp.json();
		if (!json?.success || !json.data?.data) return [];
		lineasCache[programaId] = json.data.data;
		return lineasCache[programaId];
	}

	function renderGanttGrid(dates, rows) {
		const cont = document.getElementById('gantt-ord');
		const loader = document.getElementById('gantt-loading');
		if (!cont) return;
		if (loader) loader.classList.add('hidden');

		if (!dates.length || !rows.length) {
			cont.innerHTML = '<div class="p-3 text-sm text-gray-500">Sin datos para mostrar.</div>';
			return;
		}

		// grid columns: 1 label + N dates
		const template = `180px repeat(${dates.length}, 70px)`;
		let html = `<div class="gantt-grid" style="grid-template-columns:${template}">`;

		// header
		html += `<div class="gantt-cell gantt-header gantt-label"></div>`;
		dates.forEach(d => {
			html += `<div class="gantt-cell gantt-header">${formatShort(d)}</div>`;
		});

		// rows
		rows.forEach((row, idx) => {
			html += `<div class="gantt-cell gantt-label">${row.label}</div>`;
			dates.forEach(d => {
				const key = d.toISOString().slice(0, 10);
				const qty = row.map[key] || 0;
				const cls = qty > 0 ? (idx % 2 === 0 ? 'gantt-bar' : 'gantt-bar-alt') : '';
				html += `<div class="gantt-cell ${cls}">${qty ? qty : ''}</div>`;
			});
		});

		html += `</div>`;
		cont.innerHTML = html;
	}

	async function renderGanttOrd(registros, ordCompartida) {
		const loader = document.getElementById('gantt-loading');
		if (loader) loader.classList.remove('hidden');
		const cont = document.getElementById('gantt-ord');
		if (cont) cont.innerHTML = '';

		try {
			const subset = registros.slice(0, 30); // limitar para no saturar
			const data = await Promise.all(subset.map(async reg => {
				const lineas = await fetchLineasPrograma(reg.Id);
				return { reg, lineas };
			}));
			currentGanttRegistros = registros;

			let minD = null;
			let maxD = null;
			const rows = [];

			data.forEach(({ reg, lineas }) => {
				const map = {};
				let localMin = parseDateISO(reg.FechaInicio);
				let localMax = parseDateISO(reg.FechaFinal);

				if (Array.isArray(lineas) && lineas.length) {
					lineas.forEach(l => {
						const d = parseDateISO(l.Fecha);
						if (!d) return;
						const key = d.toISOString().slice(0, 10);
						const qty = Number(l.Cantidad || 0);
						map[key] = (map[key] || 0) + Math.round((qty + Number.EPSILON) * 1000) / 1000;
						if (!localMin || d < localMin) localMin = d;
						if (!localMax || d > localMax) localMax = d;
					});
				}

				if (!localMin || !localMax) return;
				if (!minD || localMin < minD) minD = localMin;
				if (!maxD || localMax > maxD) maxD = localMax;

				const label = `Telar ${reg.NoTelarId || '-'} · ${reg.NombreProducto || ''}`.trim();
				rows.push({ label, map });
			});

			if (!minD || !maxD || rows.length === 0) {
				if (cont) cont.innerHTML = '<div class="p-3 text-sm text-gray-500">No hay líneas para mostrar.</div>';
				if (loader) loader.classList.add('hidden');
				return;
			}

			const days = buildDateRange(minD, maxD);
			renderGanttGrid(days, rows);
		} catch (e) {
			console.error('Error al renderizar gantt', e);
			if (cont) cont.innerHTML = '<div class="p-3 text-sm text-red-600">No se pudo cargar el gantt.</div>';
		} finally {
			if (loader) loader.classList.add('hidden');
		}
	}

	window.recargarGanttOrdCompartida = function(ordCompartida) {
		const registros = gruposDataCache[ordCompartida] || [];
		if (!registros.length) return;
		renderGanttOrd(registros, ordCompartida);
	};

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

		const telares = [];
		let cantidadTotal = 0;

		inputs.forEach(input => {
			const pedidoOriginal = Number(input.dataset.original) || 0;
			const fechaInicioMs = Number(input.dataset.fechaInicio) || 0;
			const duracionOriginalMs = Number(input.dataset.duracionOriginal) || 0;
			const row = input.closest('tr');
			const produccion = Number(row.querySelector('.saldo-display')?.dataset.produccion) || 0;

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

		function calcularFechaFinal(telar) {
			if (telar.tasaProduccion <= 0 || telar.cantidad <= 0) return telar.fechaInicioMs;
			const duracionMs = telar.cantidad / telar.tasaProduccion;
			return telar.fechaInicioMs + duracionMs;
		}

		function getDiferenciaMaximaDias() {
			const fechasFinales = telaresValidos.map(t => calcularFechaFinal(t));
			const maxFecha = Math.max(...fechasFinales);
			const minFecha = Math.min(...fechasFinales);
			return (maxFecha - minFecha) / (1000 * 60 * 60 * 24);
		}

		const maxIteraciones = 1000;
		const toleranciaDias = 0.5;
		const cantidadMinima = 100;
		let iteracion = 0;

		while (iteracion < maxIteraciones) {
			telaresValidos.forEach(t => {
				t.fechaFinalMs = calcularFechaFinal(t);
			});

			let telarTarde = telaresValidos[0];
			let telarTemprano = telaresValidos[0];

			telaresValidos.forEach(t => {
				if (t.fechaFinalMs > telarTarde.fechaFinalMs) telarTarde = t;
				if (t.fechaFinalMs < telarTemprano.fechaFinalMs) telarTemprano = t;
			});

			const diferenciaDias = (telarTarde.fechaFinalMs - telarTemprano.fechaFinalMs) / (1000 * 60 * 60 * 24);

			if (diferenciaDias <= toleranciaDias) {
				break;
			}

			const tiempoAMoverMs = (telarTarde.fechaFinalMs - telarTemprano.fechaFinalMs) / 2;
			let cantidadAMover = Math.floor(telarTarde.tasaProduccion * tiempoAMoverMs);

			const maxPuedeQuitar = telarTarde.cantidad - cantidadMinima;
			if (cantidadAMover > maxPuedeQuitar) {
				cantidadAMover = Math.max(0, maxPuedeQuitar);
			}

			if (cantidadAMover <= 0) {
				break;
			}

			telarTarde.cantidad -= cantidadAMover;
			telarTemprano.cantidad += cantidadAMover;

			iteracion++;
		}

		telaresValidos.forEach(telar => {
			telar.input.value = Math.round(telar.cantidad);
			telar.input.classList.add('bg-green-50', 'border-green-500');
		});

		calcularTotalesYFechas();

		const diferenciaFinalDias = getDiferenciaMaximaDias();

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
</script>
