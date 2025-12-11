<script>
    (() => {
        'use strict';

        // ==========================
        // Estado global / caches
        // ==========================
	let adjustingPedidos = false;
	let adjustingFromTotal = false;
	let lastEditedInput = null;
	let totalDisponibleBalanceo = null;

        let gruposDataCache = {};       // ordCompartida => registros
        let lineasCache = {};           // programaId => líneas originales
        let currentGanttRegistros = []; // registros actuales en modal

        // ==========================
        // Helpers generales
        // ==========================

	function formatearFecha(fecha) {
		if (!fecha) return '-';
		try {
			const d = new Date(fecha);
                if (d.getFullYear() <= 1970 || isNaN(d.getTime())) return '-';
			return d.toLocaleDateString('es-MX', {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric'
			});
		} catch (e) {
			return '-';
		}
	}

        function parseNumber(val) {
            if (val === null || val === undefined) return 0;
            const n = Number(String(val).replace(/[^0-9.\-]/g, ''));
            return isNaN(n) ? 0 : n;
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
                    fechaFinalCalcMs: Number(inp.dataset.fechaFinalCalculada) || 0
                };
            });
            return map;
        }

        // ==========================
        // API / datos
        // ==========================

        async function fetchRegistrosOrdCompartida(ordCompartida) {
            if (gruposDataCache[ordCompartida]) {
                return gruposDataCache[ordCompartida];
            }

            const resp = await fetch(
                `/planeacion/programa-tejido/registros-ord-compartida/${ordCompartida}`,
                { headers: { Accept: 'application/json' } }
            );

		const data = await resp.json();

		if (data?.success && Array.isArray(data.registros)) {
			gruposDataCache[ordCompartida] = data.registros;
			return data.registros;
		}

		throw new Error(data?.message || 'No se pudieron obtener los registros');
	}

        async function fetchLineasPrograma(programaId) {
            if (lineasCache[programaId]) {
                return lineasCache[programaId];
            }

            const resp = await fetch(
                `/planeacion/req-programa-tejido-line?programa_id=${programaId}&per_page=5000&sort=Fecha&dir=asc`,
                { headers: { Accept: 'application/json' } }
            );

            const json = await resp.json();
            if (!json?.success || !json.data?.data) return [];

            lineasCache[programaId] = json.data.data;
            return lineasCache[programaId];
        }

        // Prefetch de líneas para un conjunto de registros (limita a 30)
        async function prefetchLineas(registros) {
            const subset = registros.slice(0, 30);
            await Promise.all(
                subset.map(r =>
                    fetchLineasPrograma(r.Id).catch(() => {
                        // silencio: si falla alguna no bloquea al resto
                        return [];
                    })
                )
            );
        }

        // ==========================
        // GANTT helpers
        // ==========================

        function parseDateISO(str) {
            if (!str) return null;
            const d = new Date(str);
            return isNaN(d.getTime()) ? null : d;
        }

        function formatShort(d) {
            return d.toLocaleDateString('es-MX', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
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

        /**
         * Recalcula distribución diaria emulando al observer (solo piezas):
         * - Usa rango [FechaInicio, FechaFinalDestino]
         * - Fracciones en primer/último día
         * - StdHrEfectivo = saldoNuevo / horasTotales
         * - piezasDia = StdHrEfectivo * horasDia
         */
        function mapWithScaledTimeline(reg, _lineasIgnorado, inputsMap) {
            const datos = inputsMap[String(reg.Id)] || {};

            const pedidoNuevo = datos.pedido ?? Number(reg.TotalPedido || 0);
            const produccion = Number(reg.Produccion || 0);

            // Saldo NUEVO (lo que realmente se producirá)
            const saldoNuevo = Math.max(0, pedidoNuevo - produccion);
            const totalPzas = saldoNuevo;
            if (totalPzas <= 0) return { map: {}, min: null, max: null };

            const fechaInicioMs =
                datos.fechaInicioMs ||
                (reg.FechaInicio ? new Date(reg.FechaInicio).getTime() : 0);

            // Usar SIEMPRE la nueva fecha fin calculada si existe
            const fechaFinalCalcMs = datos.fechaFinalCalcMs || 0;
            const duracionOriginalMs = datos.duracionOriginalMs || 0;
            const fechaFinOriginalMs =
                duracionOriginalMs > 0 && fechaInicioMs
                    ? fechaInicioMs + duracionOriginalMs
                    : reg.FechaFinal
                    ? new Date(reg.FechaFinal).getTime()
                    : 0;

            const fechaFinDestinoMs = fechaFinalCalcMs || fechaFinOriginalMs;

            if (!fechaInicioMs || !fechaFinDestinoMs || fechaFinDestinoMs <= fechaInicioMs) {
                const key = fechaInicioMs
                    ? new Date(fechaInicioMs).toISOString().slice(0, 10)
                    : 'N/A';
                return {
                    map: { [key]: totalPzas },
                    min: fechaInicioMs,
                    max: fechaFinDestinoMs
                };
            }

            const inicio = new Date(fechaInicioMs);
            const fin = new Date(fechaFinDestinoMs);

            const totalSegundos = Math.abs(fin.getTime() - inicio.getTime()) / 1000;
            const totalHoras = totalSegundos / 3600.0;
            if (totalHoras <= 0) {
                const key = new Date(fechaInicioMs).toISOString().slice(0, 10);
                return {
                    map: { [key]: totalPzas },
                    min: fechaInicioMs,
                    max: fechaFinDestinoMs
                };
            }

            // Fracciones por día (igual que en el observer)
            const startDay = new Date(
                inicio.getFullYear(),
                inicio.getMonth(),
                inicio.getDate()
            );
            const endDay = new Date(fin.getFullYear(), fin.getMonth(), fin.getDate());
            const dias = Math.round((endDay - startDay) / 86400000) + 1;
            const horasPorDia = {};

            for (let i = 0; i < dias; i++) {
                const dia = new Date(startDay.getTime() + i * 86400000);
                const esPrimerDia = i === 0;
                const esUltimoDia = dia.toDateString() === endDay.toDateString();
                let fraccion;

                if (esPrimerDia && esUltimoDia) {
                    const segundos = (fin.getTime() - inicio.getTime()) / 1000;
                    fraccion = segundos / 86400;
                } else if (esPrimerDia) {
                    const segundosDesdeMedianoche =
                        inicio.getHours() * 3600 +
                        inicio.getMinutes() * 60 +
                        inicio.getSeconds();
                    const segundosRestantes = 86400 - segundosDesdeMedianoche;
                    fraccion = segundosRestantes / 86400;
                } else if (esUltimoDia) {
                    const realInicio = new Date(
                        dia.getFullYear(),
                        dia.getMonth(),
                        dia.getDate()
                    );
                    const segundos = (fin.getTime() - realInicio.getTime()) / 1000;
                    fraccion = segundos / 86400;
                } else {
                    fraccion = 1;
                }

                if (fraccion < 0) fraccion = Math.abs(fraccion);
                const horasDia = fraccion * 24.0;
                horasPorDia[dia.toISOString().slice(0, 10)] = horasDia;
            }

            // Igual que en el observer:
            // StdHrEfectivo = totalPzas / totalHoras
            const stdHrEfectivo = totalPzas / totalHoras;
            const map = {};

            Object.entries(horasPorDia).forEach(([key, horasDia]) => {
                const piezas = stdHrEfectivo * horasDia;
                map[key] =
                    (map[key] || 0) +
                    Math.round((piezas + Number.EPSILON) * 1000) / 1000;
            });

            return { map, min: fechaInicioMs, max: fechaFinDestinoMs };
        }

        /**
         * Render del grid Gantt
         * - Diseño mejorado
         * - Altura dinámica según filas
         */
        function renderGanttGrid(dates, rows) {
            const cont = document.getElementById('gantt-ord');
            const loader = document.getElementById('gantt-loading');
            const wrapper = document.getElementById('gantt-ord-container');
            if (!cont) return;
            if (loader) loader.classList.add('hidden');

            if (!dates.length || !rows.length) {
                cont.innerHTML =
                    '<div class="p-3 text-sm text-gray-500">Sin datos para mostrar.</div>';
                if (wrapper) wrapper.style.height = '180px';
				return;
			}

            // Altura dinámica según número de registros
            if (wrapper) {
                const baseRowHeight = 40; // px
                const headerHeight = 42;
                const extraPadding = 32;
                const rowCount = rows.length;

                const neededHeight =
                    headerHeight + rowCount * baseRowHeight + extraPadding;
                const maxHeight = Math.round(window.innerHeight * 0.7); // 70% viewport

                wrapper.style.height = `${Math.min(neededHeight, maxHeight)}px`;
            }

            // 1 columna de etiqueta + N días
            const template = `200px repeat(${dates.length}, 70px)`;
            let html = `<div class="gantt-grid" style="grid-template-columns:${template}">`;

            // Header
            html += `<div class="gantt-cell gantt-header gantt-label"></div>`;
            dates.forEach(d => {
                html += `<div class="gantt-cell gantt-header">${formatShort(d)}</div>`;
            });

            // Filas
            rows.forEach((row, idx) => {
                html += `<div class="gantt-cell gantt-label">${row.label}</div>`;
                dates.forEach(d => {
                    const key = d.toISOString().slice(0, 10);
                    const qty = row.map[key] || 0;
                    const hasQty = qty && qty !== 0;
                    const cls = hasQty
                        ? idx % 2 === 0
                            ? 'gantt-bar'
                            : 'gantt-bar-alt'
                        : '';

                    const displayQty = hasQty
                        ? qty.toLocaleString('es-MX', {
                              minimumFractionDigits: 3,
                              maximumFractionDigits: 3
                          })
                        : '';

                    html += `<div class="gantt-cell ${cls}">${displayQty}</div>`;
                });
            });

            html += `</div>`;
            cont.innerHTML = html;

            // Auto-scroll a la fecha actual si existe; si no, al último día (con clamp)
            if (wrapper) {
                requestAnimationFrame(() => {
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    // Buscar índice de la fecha más cercana a hoy
                    let targetIndex = dates.findIndex(
                        d => d.getTime() >= today.getTime()
                    );
                    if (targetIndex === -1) {
                        targetIndex = dates.length - 1; // último día
                    }

                    const headers = cont.querySelectorAll('.gantt-header');
                    // headers[0] es la celda vacía de etiquetas; los días empiezan en 1
                    const targetHeader = headers[targetIndex + 1];
                    if (targetHeader) {
                        const targetLeft =
                            targetHeader.offsetLeft +
                            targetHeader.offsetWidth / 2 -
                            wrapper.clientWidth / 2;
                        const maxScroll =
                            Math.max(0, wrapper.scrollWidth - wrapper.clientWidth);
                        wrapper.scrollLeft = Math.min(
                            Math.max(0, targetLeft),
                            maxScroll
                        );
                    }
                });
            }
        }

        async function renderGanttOrd(registros) {
            const loader = document.getElementById('gantt-loading');
            if (loader) loader.classList.remove('hidden');

            const cont = document.getElementById('gantt-ord');
            if (cont) cont.innerHTML = '';

            try {
                const subset = registros.slice(0, 30); // limitar para no saturar
                const data = await Promise.all(
                    subset.map(async reg => {
                        const lineas = await fetchLineasPrograma(reg.Id);
                        return { reg, lineas };
                    })
                );

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
                            map[key] =
                                (map[key] || 0) +
                                Math.round((qty + Number.EPSILON) * 1000) / 1000;

                            if (!localMin || d < localMin) localMin = d;
                            if (!localMax || d > localMax) localMax = d;
                        });
                    }

                    if (!localMin || !localMax) return;
                    if (!minD || localMin < minD) minD = localMin;
                    if (!maxD || localMax > maxD) maxD = localMax;

                    const label = `Telar ${reg.NoTelarId || '-'} · ${
                        reg.NombreProducto || ''
                    }`.trim();
                    rows.push({ label, map });
                });

                if (!minD || !maxD || rows.length === 0) {
                    if (cont) {
                        cont.innerHTML =
                            '<div class="p-3 text-sm text-gray-500">No hay líneas para mostrar.</div>';
                    }
                    if (loader) loader.classList.add('hidden');
                    return;
                }

                const days = buildDateRange(minD, maxD);
                renderGanttGrid(days, rows);
            } catch (e) {
                console.error('Error al renderizar gantt', e);
                if (cont) {
                    cont.innerHTML =
                        '<div class="p-3 text-sm text-red-600">No se pudo cargar el gantt.</div>';
                }
            } finally {
                if (loader) loader.classList.add('hidden');
            }
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
                    label: `Telar ${reg.NoTelarId || '-'} · ${
                        reg.NombreProducto || ''
                    }`.trim(),
                    map
                });
            });

            if (!rows.length || !minD || !maxD) return;

            const days = buildDateRange(new Date(minD), new Date(maxD));
            renderGanttGrid(days, rows);
        }

        // ==========================
        // Lógica de totales / fechas
        // ==========================

        window.calcularTotalesYFechas = function (changedInput = null) {
		if (adjustingPedidos) return;
		lastEditedInput = changedInput || lastEditedInput;

		const inputs = Array.from(document.querySelectorAll('.pedido-input'));
		let totalPedido = 0;
		let totalSaldo = 0;

		inputs.forEach(input => {
                // Redondear si meten decimales
			if (input.value && input.value.includes('.')) {
				input.value = Math.round(Number(input.value) || 0);
			}

			const pedido = Math.round(Number(input.value) || 0);
			const pedidoOriginal = Number(input.dataset.original) || 0;
			const fechaInicioMs = Number(input.dataset.fechaInicio) || 0;
                const duracionOriginalMs =
                    Number(input.dataset.duracionOriginal) || 0;
                const stdDia = Number(input.dataset.stdDia) || 0;

			const row = input.closest('tr');
			const saldoCell = row.querySelector('.saldo-display');
			const fechaFinalCell = row.querySelector('.fecha-final-display');

			const produccion = Number(saldoCell.dataset.produccion) || 0;

                // saldo original de BD (por referencia)
                const saldoOriginalBD =
                    Number(saldoCell.dataset.saldoOriginal || 0) ||
                    Math.max(0, pedidoOriginal - produccion);

                // saldo NUEVO
			const saldo = pedido - produccion;

			totalPedido += pedido;
			totalSaldo += saldo;

                // actualizar saldo visual
			saldoCell.textContent = saldo.toLocaleString('es-MX');
                saldoCell.className =
                    'px-3 py-2 text-sm text-right saldo-display ' +
                    (saldo > 0 ? 'text-green-600 font-medium' : 'text-gray-500');

                // === NUEVA FECHA FIN: fórmula del observer ===
                // DiasJornada = saldoNuevo / StdDia
                if (fechaInicioMs && fechaFinalCell) {
                    let nuevaFechaFinalMs = null;

                    if (stdDia > 0) {
                        const saldoNuevo = Math.max(0, saldo);
                        const diasJornada =
                            saldoNuevo > 0 ? saldoNuevo / stdDia : 0;
                        const duracionMs =
                            diasJornada * 24 * 60 * 60 * 1000; // días -> ms
                        nuevaFechaFinalMs = fechaInicioMs + duracionMs;
                    } else if (duracionOriginalMs > 0 && saldoOriginalBD > 0) {
                        // Fallback si StdDia == 0: escalar por factor
                        const saldoNuevo = Math.max(0, saldo);
                        const factor = saldoNuevo / saldoOriginalBD;
                        const duracionMs =
                            duracionOriginalMs * (isFinite(factor) ? factor : 1);
                        nuevaFechaFinalMs = fechaInicioMs + duracionMs;
                    }

                    if (nuevaFechaFinalMs) {
                        const nuevaFechaFinal = new Date(nuevaFechaFinalMs);
                        fechaFinalCell.textContent = formatearFecha(
                            nuevaFechaFinal.toISOString()
                        );
				input.dataset.fechaFinalCalculada = nuevaFechaFinalMs;

                        // comparar contra fecha fin original (solo para resaltar)
                        const fechaFinOriginalMs =
                            fechaInicioMs && duracionOriginalMs
                                ? fechaInicioMs + duracionOriginalMs
                                : null;

                        if (
                            fechaFinOriginalMs &&
                            Math.abs(nuevaFechaFinalMs - fechaFinOriginalMs) >
                                60 * 60 * 1000 // >1h
                        ) {
                            fechaFinalCell.classList.add(
                                'text-blue-600',
                                'font-medium'
                            );
				} else {
                            fechaFinalCell.classList.remove(
                                'text-blue-600',
                                'font-medium'
                            );
                        }
				}
			}
		});

            // Ajuste automático para que la suma de pedidos se mantenga
		if (!adjustingFromTotal) {
                const totalDisponibleEl =
                    document.getElementById('total-disponible');
                const totalDisponible = totalDisponibleEl
                    ? parseNumber(totalDisponibleEl.textContent)
                    : totalDisponibleBalanceo ||
                      inputs.reduce(
                          (s, i) => s + (Number(i.dataset.original) || 0),
                          0
                      );

			if (totalDisponible > 0 && inputs.length > 1) {
				const diff = totalDisponible - totalPedido;

				if (Math.abs(diff) > 0.0001) {
                        const targets = inputs.filter(
                            inp => inp !== lastEditedInput
                        );
					const target = targets[0] || inputs[0];
					const valActual = Number(target.value) || 0;
					let nuevoValor = Math.round(valActual + diff);

                        if (nuevoValor < 0) nuevoValor = 0;

					adjustingPedidos = true;
					target.value = nuevoValor;
					adjustingPedidos = false;

                        return window.calcularTotalesYFechas(target);
				}
			}
		}

            // Totales
		const totalPedidoInput = document.getElementById('total-pedido-input');
		const totalSaldoEl = document.getElementById('total-saldo');

            if (
                totalPedidoInput &&
                !adjustingPedidos &&
                !adjustingFromTotal &&
                document.activeElement !== totalPedidoInput
            ) {
			totalPedidoInput.value = totalPedido;
		}

            if (totalSaldoEl) {
                totalSaldoEl.textContent = totalSaldo.toLocaleString('es-MX');
            }

		const totalDisponibleEl = document.getElementById('total-disponible');
		if (totalDisponibleEl) {
			totalDisponibleEl.textContent = totalPedido;
			if (totalDisponibleBalanceo !== null) {
				totalDisponibleBalanceo = totalPedido;
			}
		}

            // actualizar vista previa del gantt
            updateGanttPreview();
	};

        window.actualizarPedidosDesdeTotal = function (totalInput) {
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
            if (Math.abs(diferencia) < 0.0001) return;

		adjustingFromTotal = true;

		if (inputs.length === 1) {
			const input = inputs[0];
			const valorActual = Number(input.value) || 0;
			input.value = Math.round(Math.max(0, valorActual + diferencia));
                window.calcularTotalesYFechas(input);
		} else {
			let diferenciaRestante = diferencia;

			inputs.forEach((input, index) => {
				const valorActual = Number(input.value) || 0;
                    const proporcion =
                        totalActual > 0
                            ? valorActual / totalActual
                            : 1 / inputs.length;
				const diferenciaInput = diferencia * proporcion;

				if (index === inputs.length - 1) {
                        input.value = Math.round(
                            Math.max(0, valorActual + diferenciaRestante)
                        );
				} else {
                        const nuevoValor = Math.round(
                            Math.max(0, valorActual + diferenciaInput)
                        );
					input.value = nuevoValor;
					diferenciaRestante -= diferenciaInput;
				}
			});

                window.calcularTotalesYFechas();
                updateGanttPreview();
		}

		adjustingFromTotal = false;
	};

        // ==========================
        // Balanceo automático
        // ==========================

        window.aplicarBalanceoAutomatico = function () {
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

		inputs.forEach(input => {
			const pedidoOriginal = Number(input.dataset.original) || 0;
			const fechaInicioMs = Number(input.dataset.fechaInicio) || 0;
                const duracionOriginalMs =
                    Number(input.dataset.duracionOriginal) || 0;
			const row = input.closest('tr');
                const saldoCell = row.querySelector('.saldo-display');
                const produccion =
                    Number(saldoCell?.dataset.produccion || 0) || 0;
                const saldoOriginalBD =
                    Number(saldoCell?.dataset.saldoOriginal || 0) ||
                    Math.max(0, pedidoOriginal - produccion);

                // tasa basada en saldo original / duración
			let tasaProduccion = 0;
                if (duracionOriginalMs > 0 && saldoOriginalBD > 0) {
                    tasaProduccion = saldoOriginalBD / duracionOriginalMs;
			}

			telares.push({
                    input,
                    row,
                    pedidoOriginal,
				cantidad: Number(input.value) || 0,
                    fechaInicioMs,
                    duracionOriginalMs,
                    tasaProduccion,
                    produccion
                });
            });

            const telaresValidos = telares.filter(
                t => t.tasaProduccion > 0 && t.fechaInicioMs > 0
            );

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
                if (telar.tasaProduccion <= 0 || telar.cantidad <= 0)
                    return telar.fechaInicioMs;
			const duracionMs = telar.cantidad / telar.tasaProduccion;
			return telar.fechaInicioMs + duracionMs;
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
                    if (t.fechaFinalMs < telarTemprano.fechaFinalMs)
                        telarTemprano = t;
                });

                const diferenciaDias =
                    (telarTarde.fechaFinalMs - telarTemprano.fechaFinalMs) /
                    (1000 * 60 * 60 * 24);
                if (diferenciaDias <= toleranciaDias) break;

                const tiempoAMoverMs =
                    (telarTarde.fechaFinalMs - telarTemprano.fechaFinalMs) / 2;
                let cantidadAMover = Math.floor(
                    telarTarde.tasaProduccion * tiempoAMoverMs
                );

			const maxPuedeQuitar = telarTarde.cantidad - cantidadMinima;
			if (cantidadAMover > maxPuedeQuitar) {
				cantidadAMover = Math.max(0, maxPuedeQuitar);
			}

                if (cantidadAMover <= 0) break;

			telarTarde.cantidad -= cantidadAMover;
			telarTemprano.cantidad += cantidadAMover;

			iteracion++;
		}

		telaresValidos.forEach(telar => {
			telar.input.value = Math.round(telar.cantidad);
			telar.input.classList.add('bg-green-50', 'border-green-500');
		});

            window.calcularTotalesYFechas();
        };

        // ==========================
        // Guardar cambios
        // ==========================

	async function guardarCambiosPedido(ordCompartida) {
		const inputs = document.querySelectorAll('.pedido-input');
		const cambios = [];

		inputs.forEach(input => {
			const id = input.dataset.id;
			const valorOriginal = Number(input.dataset.original) || 0;
			const valorNuevo = Number(input.value) || 0;
                const fechaFinalCalculadaMs =
                    Number(input.dataset.fechaFinalCalculada) || 0;

			if (valorOriginal !== valorNuevo) {
				const cambio = {
                        id,
					total_pedido: valorNuevo
				};

				if (fechaFinalCalculadaMs > 0) {
					const fechaFinal = new Date(fechaFinalCalculadaMs);
                        cambio.fecha_final = fechaFinal
                            .toISOString()
                            .slice(0, 19)
                            .replace('T', ' ');
				}

				cambios.push(cambio);
			}
		});

		if (cambios.length === 0) {
			Swal.showValidationMessage('No hay cambios para guardar');
			return false;
		}

		try {
                const response = await fetch(
                    '/planeacion/programa-tejido/actualizar-pedidos-balanceo',
                    {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
                            'X-CSRF-TOKEN':
                                document.querySelector(
                                    'meta[name="csrf-token"]'
                                ).content
				},
				body: JSON.stringify({
                            cambios,
					ord_compartida: ordCompartida
				})
                    }
                );

			const data = await response.json();

			if (data.success) {
                    try {
                        // Refrescar cache y Gantt con datos reales regenerados por el observer
                        lineasCache = {};
                        const registrosRefrescados =
                            await fetchRegistrosOrdCompartida(ordCompartida);
                        gruposDataCache[ordCompartida] = registrosRefrescados;
                        currentGanttRegistros = registrosRefrescados;
                        renderGanttOrd(registrosRefrescados);
                    } catch (_) {
                        // si falla el refresco, no bloquear el guardado
                    }

				Swal.fire({
					icon: 'success',
					title: 'Guardado',
                        text:
                            data.message ||
                            'Los cambios se guardaron correctamente',
					confirmButtonColor: '#3b82f6',
					timer: 2000,
					showConfirmButton: false
				});

                    setTimeout(() => {
                        window.location.reload();
                    }, 600);

				return true;
			}

                Swal.showValidationMessage(
                    data.message || 'Error al guardar los cambios'
                );
                return false;
		} catch (error) {
                Swal.showValidationMessage(
                    'Error de conexión al guardar los cambios'
                );
			return false;
		}
	}

        // ==========================
        // Expuestos globales
        // ==========================

        window.recargarGanttOrdCompartida = async function (ordCompartida) {
            try {
                const registros = await fetchRegistrosOrdCompartida(ordCompartida);
                currentGanttRegistros = registros;
                lineasCache = {}; // forzar refetch de líneas
                await prefetchLineas(registros);
                renderGanttOrd(registros);
            } catch (e) {
                console.error('Error al recargar gantt', e);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo recargar el gantt. Intenta de nuevo.',
                    confirmButtonColor: '#3b82f6'
                });
            }
        };

        window.verDetallesGrupoBalanceo = async function (ordCompartida) {
            try {
                const registros = await fetchRegistrosOrdCompartida(
                    ordCompartida
                );
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

                const totalPedido = registros.reduce(
                    (sum, r) => sum + (Number(r.TotalPedido) || 0),
                    0
                );
                const totalProduccion = registros.reduce(
                    (sum, r) => sum + (Number(r.Produccion) || 0),
                    0
                );
                const totalSaldo = registros.reduce(
                    (sum, r) => sum + (Number(r.SaldoPedido) || 0),
                    0
                );

                totalDisponibleBalanceo = totalPedido;

                const filasHTML = registros
                    .map(reg => {
                        const fechaInicio = reg.FechaInicio
                            ? new Date(reg.FechaInicio).getTime()
                            : null;
                        const fechaFinal = reg.FechaFinal
                            ? new Date(reg.FechaFinal).getTime()
                            : null;
                        const duracionOriginalMs =
                            fechaInicio && fechaFinal
                                ? fechaFinal - fechaInicio
                                : 0;

                        const pedidoOriginal = Number(reg.TotalPedido || 0);
                        const produccion = Number(reg.Produccion || 0);
                        const saldoOriginal =
                            reg.SaldoPedido != null
                                ? Number(reg.SaldoPedido || 0)
                                : Math.max(0, pedidoOriginal - produccion);

                        const stdDia = Number(reg.StdDia || 0);

                        return `
                            <tr class="hover:bg-gray-50 border-b border-gray-200" data-registro-id="${
                                reg.Id
                            }">
                                <td class="px-3 py-2 text-xs sm:text-sm font-medium text-gray-900">
                                    ${reg.NoTelarId || '-'}
                                </td>
                                <td class="px-3 py-2 text-xs sm:text-sm text-gray-600">
                                    ${reg.NombreProducto || '-'}
                                </td>
                                <td class="px-3 py-2 text-xs sm:text-sm text-right text-gray-600">
                                    ${Math.round(stdDia).toLocaleString('es-MX')}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <input
                                        type="number"
                                        class="pedido-input w-20 sm:w-24 px-2 py-1 text-xs sm:text-sm text-right border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        data-id="${reg.Id}"
                                        data-original="${pedidoOriginal}"
                                        data-fecha-inicio="${fechaInicio || ''}"
                                        data-duracion-original="${duracionOriginalMs}"
                                        data-std-dia="${stdDia}"
                                        value="${pedidoOriginal}"
                                        min="0"
                                        step="1"
                                        oninput="calcularTotalesYFechas(this)"
                                    >
                                </td>
                                <td class="px-3 py-2 text-xs sm:text-sm text-right text-gray-600">
                                    ${produccion.toLocaleString('es-MX')}
                                </td>
                                <td
                                    class="px-3 py-2 text-xs sm:text-sm text-right saldo-display ${
                                        saldoOriginal > 0
                                            ? 'text-green-600 font-medium'
                                            : 'text-gray-500'
                                    }"
                                    data-produccion="${produccion}"
                                    data-saldo-original="${saldoOriginal}"
                                >
                                    ${saldoOriginal.toLocaleString('es-MX')}
                                </td>
                                <td class="px-3 py-2 text-xs sm:text-sm text-center text-gray-600">
                                    ${formatearFecha(reg.FechaInicio)}
                                </td>
                                <td class="px-3 py-2 text-xs sm:text-sm text-center text-gray-600 fecha-final-display">
                                    ${formatearFecha(reg.FechaFinal)}
                                </td>
                            </tr>
                        `;
                    })
                    .join('');

                const htmlContent = `
                    <div class="space-y-4 text-left">
                        <style>
                            #gantt-ord-container {
                                max-height: 75vh;
                            }
                            .gantt-grid {
                                display: grid;
                                grid-auto-rows: minmax(40px, auto);
                                width: max-content;
                            }
                            .gantt-cell {
                                border: 1px solid #e5e7eb;
                                padding: 6px 8px;
                                font-size: 11px;
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
                                z-index: 10;
                            }
                            .gantt-label {
                                font-weight: 600;
                                background: #f3f4f6;
                                text-align: left;
                                position: sticky;
                                left: 0;
                                z-index: 20;
                                white-space: nowrap;
                            }
                            .gantt-bar {
                                background: #fef2f2;
                                color: #b91c1c;
                                font-weight: 600;
                            }
                            .gantt-bar-alt {
                                background: #ecfdf3;
                                color: #166534;
                                font-weight: 600;
                            }
                            @media (max-width: 1024px) {
                                .gantt-cell {
                                    min-width: 64px;
                                    padding: 4px 6px;
                                    font-size: 10px;
                                }
                            }
                        </style>

                        <div class="flex flex-col sm:flex-row sm:justify-end gap-2">
                            <button
                                type="button"
                                id="btn-balancear-fechas"
                                onclick="aplicarBalanceoAutomatico()"
                                class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-lg bg-green-500 px-4 py-2 text-xs sm:text-sm font-medium text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400"
                            >
                                <i class="fa-solid fa-scale-balanced"></i>
                                <span>Balancear Fechas</span>
                            </button>
                            <button
                                type="button"
                                onclick="recargarGanttOrdCompartida(${ordCompartida})"
                                class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-lg bg-gray-700 px-4 py-2 text-xs sm:text-sm font-medium text-white shadow-sm hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500"
                            >
                                <i class="fa-solid fa-rotate-right"></i>
                                <span>Recargar Gantt</span>
                            </button>
                        </div>

                        <div class="flex flex-col lg:flex-row gap-4 items-stretch">
                            <!-- Tabla de detalles -->
                            <div class="w-full lg:w-5/12 min-w-0 flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white">
                                <div class="overflow-x-auto">
                                    <table id="tabla-detalles" class="min-w-full">
                                        <thead class="bg-blue-500 text-white">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-[11px] sm:text-xs">Telar</th>
                                                <th class="px-3 py-2 text-left text-[11px] sm:text-xs">Producto</th>
                                                <th class="px-3 py-2 text-right text-[11px] sm:text-xs">Std/Día</th>
                                                <th class="px-3 py-2 text-right text-[11px] sm:text-xs">Pedido</th>
                                                <th class="px-3 py-2 text-right text-[11px] sm:text-xs">Producción</th>
                                                <th class="px-3 py-2 text-right text-[11px] sm:text-xs">Saldo</th>
                                                <th class="px-3 py-2 text-center text-[11px] sm:text-xs">F.Inicio</th>
                                                <th class="px-3 py-2 text-center text-[11px] sm:text-xs">F.Final</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${filasHTML}
                                        </tbody>
                                        <tfoot class="bg-gray-100">
                                            <tr>
                                                <td colspan="3" class="px-3 py-2 text-xs sm:text-sm font-semibold text-gray-700 text-right">
                                                    Totales:
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <input
                                                        type="number"
                                                        id="total-pedido-input"
                                                        class="w-20 sm:w-24 px-2 py-1 text-xs sm:text-sm text-right font-bold text-gray-900 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                        value="${totalPedido}"
                                                        min="0"
                                                        step="1"
                                                        oninput="actualizarPedidosDesdeTotal(this)"
                                                    >
                                                </td>
                                                <td class="px-3 py-2 text-xs sm:text-sm text-right font-bold text-gray-900">
                                                    ${totalProduccion.toLocaleString('es-MX')}
                                                </td>
                                                <td
                                                    class="px-3 py-2 text-xs sm:text-sm text-right font-bold text-green-600"
                                                    id="total-saldo"
                                                >
                                                    ${totalSaldo.toLocaleString('es-MX')}
                                                </td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <div id="total-disponible" class="hidden">${totalPedido}</div>
                            </div>

                            <!-- Gantt -->
                            <div class="w-full lg:flex-1 min-w-0 flex flex-col rounded-lg border border-gray-200 bg-white p-2">
                                <div
                                    id="gantt-ord-container"
                                    class="relative min-h-[150px] overflow-auto"
                                >
                                    <div id="gantt-loading" class="p-3 text-sm text-gray-500">
                                        Cargando líneas...
                                    </div>
                                    <div id="gantt-ord"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                Swal.fire({
                    title: 'Balanceo de orden',
                    html: htmlContent,
                    width: '95%',
                    showCloseButton: true,
                    showConfirmButton: true,
                    confirmButtonText:
                        '<i class="fa-solid fa-save mr-2"></i> Guardar',
                    confirmButtonColor: '#3b82f6',
                    showCancelButton: true,
                    cancelButtonText: 'Cancelar',
                    cancelButtonColor: '#6b7280',
                    customClass: {
                        htmlContainer: 'text-left max-h-[70vh] overflow-y-auto'
                    },
                    preConfirm: () => guardarCambiosPedido(ordCompartida),
                    didOpen: () => {
                        renderGanttOrd(registros);
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
    })();
</script>
