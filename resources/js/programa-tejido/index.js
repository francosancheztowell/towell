// Primero boot: los módulos de abajo leen PT_BOOT al evaluarse.
import { PT_BOOT } from './boot.ts';
import {
    checkFilterMatch as ptCheckFilterMatch,
    dateInRange as ptDateInRange,
    groupFiltersByColumn as ptGroupFiltersByColumn,
    rowMatchesCustomFilters as ptRowMatchesCustomFilters,
} from './filter-engine.ts';
import { instalarIndiceSeleccion as ptInstalarIndiceSeleccion } from './seleccion.ts';
import { accionesTactiles as ptAccionesTactiles } from '../utils/acciones-tactiles.ts';
import { enlazarDiasLiberar as ptEnlazarDiasLiberar } from './acciones.ts';
import { abrirDuplicar as ptAbrirDuplicar, escucharGuardado as ptEscucharDuplicarGuardado } from './duplicar.ts';
// Scripts que vivían inline en la vista (04-perf, corte 5). Se evalúan antes que este
// archivo y solo publican funciones en window, como hacían sus <script>.
import './balancear.ts';
import './recalcular-fechas.ts';
import './modales/act-calendarios.ts';
import './modales/repaso.ts';
import './modales/marbetes.ts';
import './lineas.ts';

// Bundle JS de Programa Tejido. Antes iba inline en el HTML (527 KB que el
// navegador volvia a descargar y a recompilar en cada recarga); ahora lo sirve
// Vite con hash, asi que sale de cache y V8 reusa el bytecode.
//
// Todo comparte un solo scope, igual que cuando era un unico <script>: no
// convertir esto en modulos con import/export sin repasar las dependencias
// cruzadas (p. ej. selection usa $$ de state).
//
// Los valores que dependian de Blade llegan en PT_BOOT (boot.ts), que la vista
// imprime como <script type="application/json" id="pt-boot">.


  const PT_BASE_PATH = PT_BOOT.basePath || '/planeacion/programa-tejido';
  const PT_API_PATH = PT_BOOT.apiPath || '/programa-tejido';
  const PT_LINE_PATH = PT_BOOT.linePath || '/req-programa-tejido-line';

  (function () {
    if (window.PT_FETCH_PATCHED) return;
    window.PT_FETCH_PATCHED = true;

    const originalFetch = window.fetch.bind(window);
    const rewriteUrl = (url) => {
      if (typeof url !== 'string') return url;
      let next = url;

      if (PT_BASE_PATH && next.includes('/planeacion/programa-tejido')) {
        next = next.replace('/planeacion/programa-tejido', PT_BASE_PATH);
      }
      if (PT_API_PATH && next.includes('/programa-tejido')) {
        next = next.replace('/programa-tejido', PT_API_PATH);
      }
      if (PT_LINE_PATH && next.includes('/planeacion/req-programa-tejido-line')) {
        next = next.replace('/planeacion/req-programa-tejido-line', PT_LINE_PATH);
      }

      return next;
    };

    window.fetch = function (input, init) {
      if (typeof input === 'string') {
        return originalFetch(rewriteUrl(input), init);
      }
      if (input instanceof Request) {
        const url = rewriteUrl(input.url);
        if (url === input.url) return originalFetch(input, init);
        return originalFetch(new Request(url, input), init);
      }
      return originalFetch(input, init);
    };
  })();


function getCsrfToken() {
	return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

// Depuración modal programa-tejido: en consola, window.__PT_DEBUG = true antes de usar el modal
window.__PT_DEBUG = window.__PT_DEBUG === true;
function ptDebugLog(...args) {
	if (window.__PT_DEBUG) {
		console.log.apply(console, args);
	}
}

window.escapeHtmlPtModal = escapeHtmlPtModal;
function escapeHtmlPtModal(s) {
	if (s === null || s === undefined) return '';
	return String(s)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
}
window.escapeHtmlPtModal = escapeHtmlPtModal;

function normalizeSqlDateValue(value) {
	if (value === null || value === undefined) return null;
	const raw = String(value).trim();
	if (!raw) return null;
	const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
	const dt = new Date(normalized);
	if (Number.isNaN(dt.getTime()) || dt.getFullYear() <= 1970) return null;
	return dt;
}

function formatDateCellValue(value, type) {
	const dt = normalizeSqlDateValue(value);
	if (!dt) return '';
	const day = String(dt.getDate()).padStart(2, '0');
	const month = String(dt.getMonth() + 1).padStart(2, '0');
	const year = dt.getFullYear();
	if (type === 'datetime') {
		const hours = String(dt.getHours()).padStart(2, '0');
		const minutes = String(dt.getMinutes()).padStart(2, '0');
		return `${day}/${month}/${year} ${hours}:${minutes}`;
	}
	return `${day}/${month}/${year}`;
}

function updateDateCell(cell, value, type) {
	if (!cell || value === undefined) return;
	cell.textContent = formatDateCellValue(value, type);
	cell.setAttribute('data-value', value || '');
}

async function actualizarRegistrosPorIds(registrosIds) {
	const ids = Array.from(new Set(registrosIds || [])).filter(Boolean);
	if (ids.length === 0) return;

	if (typeof actualizarRegistrosVinculados === 'function') {
		await actualizarRegistrosVinculados(ids, null);
		return;
	}

	const tb = document.querySelector('#mainTable tbody');
	if (!tb) return;

	const columns = (typeof columnsData !== 'undefined' && columnsData && columnsData.length > 0)
		? columnsData
		: (window.columns || Array.from(document.querySelectorAll('#mainTable thead th[data-column]')).map(th => ({
			field: th.getAttribute('data-column'),
			label: th.textContent.trim(),
			dateType: null
		})));

	if (!columns || columns.length === 0) return;

	const getDateType = (field) => {
		const col = columns.find(c => c.field === field);
		return col?.dateType || null;
	};

	const formatearValor = (registro, field, value) => {
		if (typeof formatearValorCelda === 'function') {
			return formatearValorCelda(registro, field, value, getDateType(field));
		}
		if (value === null || value === undefined || value === '') return '';
		const dateType = getDateType(field);
		if (dateType === 'date' || dateType === 'datetime') {
			return formatDateCellValue(value, dateType);
		}
		if (!isNaN(value) && !Number.isInteger(parseFloat(value))) {
			return parseFloat(value).toFixed(2);
		}
		return String(value);
	};

	const fetchDetalle = async (registroId) => {
		try {
			const response = await fetch(`/planeacion/programa-tejido/${registroId}/detalles-balanceo?t=${Date.now()}`, {
				headers: {
					'Accept': 'application/json',
					'X-CSRF-TOKEN': getCsrfToken(),
					'Cache-Control': 'no-cache'
				}
			});
			if (!response.ok) return null;
			const result = await response.json();
			if (!result.success || !result.registro) return null;
			return { registroId, registro: result.registro };
		} catch (error) {
			console.warn(`Error al actualizar registro ${registroId}:`, error);
			return null;
		}
	};

	const concurrenciaDetalleBalanceo = 12;
	const resultados = [];
	for (let i = 0; i < ids.length; i += concurrenciaDetalleBalanceo) {
		const chunk = ids.slice(i, i + concurrenciaDetalleBalanceo);
		const part = await Promise.all(chunk.map((id) => fetchDetalle(id)));
		resultados.push(...part);
	}

	for (const item of resultados) {
		if (!item) continue;
		const { registroId, registro } = item;
		const fila = tb.querySelector(`tr.selectable-row[data-id="${registroId}"]`);
		if (!fila) continue;

		if (registro.OrdCompartida) {
			fila.setAttribute('data-ord-compartida', registro.OrdCompartida);
		} else {
			fila.removeAttribute('data-ord-compartida');
		}

		columns.forEach(col => {
			const field = col.field;
			const value = registro[field] !== undefined ? registro[field] : null;
			const celda = fila.querySelector(`td[data-column="${field}"]`);
			if (!celda) return;
			celda.setAttribute('data-value', value !== null && value !== undefined ? String(value) : '');
			celda.innerHTML = formatearValor(registro, field, value);
		});
	}
}

async function actualizarRegistroOriginalDividir(data, tableBody) {
	if (!data?.registro_id_original) return;
	const tb = tableBody || document.querySelector('#mainTable tbody');
	if (!tb) return;

	const aplicarActualizacion = (filaOriginal, regOriginal) => {
		const totalPedidoCell = filaOriginal.querySelector('[data-column="TotalPedido"]');
		const saldoPedidoCell = filaOriginal.querySelector('[data-column="SaldoPedido"]');
		const fechaFinalCell = filaOriginal.querySelector('[data-column="FechaFinal"]');
		const fechaInicioCell = filaOriginal.querySelector('[data-column="FechaInicio"]');
		const entregaProducCell = filaOriginal.querySelector('[data-column="EntregaProduc"]');
		const entregaPTCell = filaOriginal.querySelector('[data-column="EntregaPT"]');
		const entregaCteCell = filaOriginal.querySelector('[data-column="EntregaCte"]');
		const programarProdCell = filaOriginal.querySelector('[data-column="ProgramarProd"]');
		const horasProdCell = filaOriginal.querySelector('[data-column="HorasProd"]');
		const diasJornadaCell = filaOriginal.querySelector('[data-column="DiasJornada"]');
		const stdDiaCell = filaOriginal.querySelector('[data-column="StdDia"]');
		const prodKgDiaCell = filaOriginal.querySelector('[data-column="ProdKgDia"]');
		const horasNecesariasCell = filaOriginal.querySelector('[data-column="HorasNecesarias"]');
		const eficienciaCell = filaOriginal.querySelector('[data-column="Eficiencia"]');

		if (totalPedidoCell && regOriginal.TotalPedido !== undefined) {
			totalPedidoCell.textContent = regOriginal.TotalPedido || '0';
			totalPedidoCell.setAttribute('data-value', regOriginal.TotalPedido || '0');
		}
		if (saldoPedidoCell && regOriginal.SaldoPedido !== undefined) {
			saldoPedidoCell.textContent = regOriginal.SaldoPedido || '0';
			saldoPedidoCell.setAttribute('data-value', regOriginal.SaldoPedido || '0');
		}

		updateDateCell(fechaFinalCell, regOriginal.FechaFinal, 'datetime');
		updateDateCell(fechaInicioCell, regOriginal.FechaInicio, 'datetime');
		updateDateCell(entregaProducCell, regOriginal.EntregaProduc, 'date');
		updateDateCell(entregaPTCell, regOriginal.EntregaPT, 'date');
		updateDateCell(entregaCteCell, regOriginal.EntregaCte, 'datetime');
		updateDateCell(programarProdCell, regOriginal.ProgramarProd, 'date');

		if (horasProdCell && regOriginal.HorasProd !== undefined) {
			horasProdCell.textContent = regOriginal.HorasProd ? parseFloat(regOriginal.HorasProd).toFixed(2) : '0';
			horasProdCell.setAttribute('data-value', regOriginal.HorasProd || '0');
		}
		if (diasJornadaCell && regOriginal.DiasJornada !== undefined) {
			diasJornadaCell.textContent = regOriginal.DiasJornada ? parseFloat(regOriginal.DiasJornada).toFixed(2) : '0';
			diasJornadaCell.setAttribute('data-value', regOriginal.DiasJornada || '0');
		}
		if (stdDiaCell && regOriginal.StdDia !== undefined) {
			stdDiaCell.textContent = regOriginal.StdDia ? parseFloat(regOriginal.StdDia).toFixed(2) : '0';
			stdDiaCell.setAttribute('data-value', regOriginal.StdDia || '0');
		}
		if (prodKgDiaCell && regOriginal.ProdKgDia !== undefined) {
			prodKgDiaCell.textContent = regOriginal.ProdKgDia ? parseFloat(regOriginal.ProdKgDia).toFixed(2) : '0';
			prodKgDiaCell.setAttribute('data-value', regOriginal.ProdKgDia || '0');
		}
		if (horasNecesariasCell && regOriginal.HorasNecesarias !== undefined) {
			horasNecesariasCell.textContent = regOriginal.HorasNecesarias ? parseFloat(regOriginal.HorasNecesarias).toFixed(2) : '0';
			horasNecesariasCell.setAttribute('data-value', regOriginal.HorasNecesarias || '0');
		}
		if (eficienciaCell && regOriginal.Eficiencia !== undefined) {
			eficienciaCell.textContent = regOriginal.Eficiencia ? parseFloat(regOriginal.Eficiencia).toFixed(2) + '%' : '0%';
			eficienciaCell.setAttribute('data-value', regOriginal.Eficiencia || '0');
		}
	};

	try {
		if (data?.registro_original) {
			const filaOriginal = tb.querySelector(`tr.selectable-row[data-id="${data.registro_id_original}"]`);
			if (filaOriginal) {
				aplicarActualizacion(filaOriginal, data.registro_original);
			}
			return;
		}

		const responseOriginal = await fetch(`/planeacion/programa-tejido/${data.registro_id_original}/detalles-balanceo?t=${Date.now()}`, {
			headers: {
				'Accept': 'application/json',
				'X-CSRF-TOKEN': getCsrfToken(),
				'Cache-Control': 'no-cache'
			}
		});

		if (responseOriginal.ok) {
			const resultOriginal = await responseOriginal.json();
			if (resultOriginal.success && resultOriginal.registro) {
				const filaOriginal = tb.querySelector(`tr.selectable-row[data-id="${data.registro_id_original}"]`);
				if (filaOriginal) {
					aplicarActualizacion(filaOriginal, resultOriginal.registro);
				}
			}
		}
	} catch (e) {
		console.warn('[DEBUG] Error al actualizar registro original:', e);
	}
}

function buildCalendarWarningHtml(message, advertencias) {
	const detalles = Array.isArray(advertencias?.detalles) ? advertencias.detalles : [];
	const detallesHtml = detalles.length > 0
		? `<ul class="list-disc list-inside text-xs text-yellow-700 space-y-1">${detalles.slice(0, 5).map(detalle => (
			`<li>Calendario '<strong>${escapeHtmlPtModal(detalle.calendario_id)}</strong>': ${escapeHtmlPtModal(detalle.mensaje)}</li>`
		)).join('')}${detalles.length > 5 ? `<li>... y ${detalles.length - 5} mas</li>` : ''}</ul>`
		: '';

	return `
		<div class="text-left">
			<p class="mb-3 text-sm text-gray-700">${escapeHtmlPtModal(message)}</p>
			<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-3">
				<p class="font-semibold text-yellow-800 mb-2">Advertencia: Problemas con calendarios</p>
				<p class="text-sm text-yellow-700 mb-2">${escapeHtmlPtModal(advertencias.total_errores)} programa(s) no pudieron generar lineas diarias porque no hay fechas disponibles en el calendario.</p>
				${detallesHtml}
			</div>
			<p class="text-xs text-gray-600">Los programas se crearon correctamente, pero necesitas agregar fechas al calendario para generar las lineas diarias.</p>
		</div>
	`;
}

// Modal Duplicar/Dividir (Livewire): al guardar se pintan las filas sin recargar.
ptEscucharDuplicarGuardado({
	insertar: redirectToRegistro,
	refrescar: actualizarRegistrosPorIds,
	advertenciaHtml: buildCalendarWarningHtml,
});

async function redirectToRegistro(data) {
	// Evitar location.href / reload en flujos que ya actualizan la tabla sin recargar (alinear dividir con duplicar/vincular)
	const evitarRecargaProgramaTejido = (d) =>
		d?.modo === 'dividir' ||
		d?.modo === 'duplicar' ||
		(d?.registro_id_original != null && d?.registros_divididos != null);

	ptDebugLog('[DEBUG] redirectToRegistro llamado', {
		modo: data?.modo,
		registros_ids: data?.registros_ids,
		registros_ids_length: data?.registros_ids?.length,
		registros_duplicados: data?.registros_duplicados,
		registros_vinculados: data?.registros_vinculados,
		tiene_registros_datos: !!data?.registros_datos,
		registros_datos_keys: data?.registros_datos ? Object.keys(data.registros_datos) : 'N/A',
		data_keys: Object.keys(data || {}),
	});

	// Si hay múltiples registros duplicados/vinculados O tenemos registros_ids (duplicar/dividir): agregar sin recargar
	// El backend a veces no envía "modo", pero si envía registros_ids debemos agregar filas sin recargar
	const tieneMultiplesRegistros = (data?.registros_duplicados && data.registros_duplicados > 1) ||
	                                (data?.registros_vinculados && data.registros_vinculados > 1) ||
	                                (data?.registros_ids && Array.isArray(data.registros_ids) && data.registros_ids.length > 1) ||
	                                (data?.registros_ids && Array.isArray(data.registros_ids) && data.registros_ids.length >= 1);

	if (tieneMultiplesRegistros) {
		// Preferir usar registros_ids si está disponible (más confiable)
		// En dividir: registros_ids = solo los nuevos; el original se actualiza con actualizarRegistroOriginalDividir
		if (data?.registros_ids && Array.isArray(data.registros_ids) && data.registros_ids.length > 0) {
			try {
				const datosPrecargadosCompletos = data.registros_datos &&
					data.registros_ids.every((id) => data.registros_datos[String(id)] != null);
				// Sin datos precargados, breve espera por visibilidad en BD tras commit; con datos del servidor no hace falta 800ms
				const delayInicialMs = datosPrecargadosCompletos ? 0 : 120;
				if (delayInicialMs > 0) {
					await new Promise(resolve => setTimeout(resolve, delayInicialMs));
				}

				const tb = document.querySelector('#mainTable tbody');
				if (!tb) {
					console.error('[DEBUG] No se encontró el tbody');
					if (evitarRecargaProgramaTejido(data)) {
						if (typeof showToast === 'function') {
							showToast('No se encontró la tabla principal. Recargue la página si no ve los cambios.', 'warning');
						}
						return;
					}
					window.location.reload();
					return;
				}

				// Obtener IDs de filas existentes en el DOM
				const filasExistentes = Array.from(tb.querySelectorAll('.selectable-row')).map(f => f.getAttribute('data-id'));
				const registrosAgregados = [];

				ptDebugLog(`[DEBUG] 📋 IDs de registros a agregar:`, data.registros_ids);
				ptDebugLog(`[DEBUG] 📋 registros_datos disponible:`, !!data.registros_datos, data.registros_datos ? Object.keys(data.registros_datos) : 'N/A');
				ptDebugLog(`[DEBUG] 📋 IDs de filas existentes en DOM:`, filasExistentes);

				// Agregar todos los registros usando los IDs devueltos por el backend
				// Preferir data.registros_datos cuando el backend lo envíe (evita 404 en detalles-balanceo)
				for (const registroId of data.registros_ids) {
					const idStr = String(registroId);

					// Verificar si ya existe en el DOM
					if (filasExistentes.includes(idStr)) {
						ptDebugLog(`[DEBUG] ⏭️ Registro ${idStr} ya existe en el DOM, saltando`);
						registrosAgregados.push(parseInt(idStr));
						continue;
					}

					try {
						const registroPrecargado = data.registros_datos && data.registros_datos[idStr];
						let payload = { registro_id: registroId, message: '' };

						if (registroPrecargado) {
							payload.registro = registroPrecargado;
							payload.registros_datos = data.registros_datos;
							// Validar telar destino igual que cuando viene del fetch
							const esVincular = data?.registros_vinculados > 0 || data?.ord_compartida;
							const esDividir = data?.modo === 'dividir';
							const esDuplicar = data?.modo === 'duplicar';
							const telarValido = esVincular || esDividir || esDuplicar ||
								(registroPrecargado.SalonTejidoId === data.salon_destino &&
								 registroPrecargado.NoTelarId === data.telar_destino);
							if (!telarValido) {
								console.warn(`[DEBUG] ⚠️ Registro ${registroId} (precargado) no pertenece al telar destino`, {
									salon_registro: registroPrecargado.SalonTejidoId,
									salon_destino: data.salon_destino,
									telar_registro: registroPrecargado.NoTelarId,
									telar_destino: data.telar_destino
								});
								continue;
							}
							ptDebugLog(`[DEBUG] ➕ Agregando registro ID: ${registroId} (desde registros_datos)`, {
								TamanoClave: registroPrecargado.TamanoClave,
								ItemId: registroPrecargado.ItemId,
								InventSizeId: registroPrecargado.InventSizeId,
								SalonTejidoId: registroPrecargado.SalonTejidoId,
								NoTelarId: registroPrecargado.NoTelarId
							});
							await agregarRegistroSinRecargar(payload, { preventReload: true });
							registrosAgregados.push(parseInt(idStr));
							filasExistentes.push(idStr);
							continue;
						}

						// Fallback: obtener por detalles-balanceo (sin delay extra: ya hubo espera inicial si hacía falta)
						const response = await fetch(`/planeacion/programa-tejido/${registroId}/detalles-balanceo?t=${Date.now()}`, {
							headers: {
								'Accept': 'application/json',
								'X-CSRF-TOKEN': getCsrfToken(),
								'Cache-Control': 'no-cache'
							}
						});

						if (response.ok) {
							const result = await response.json();
							if (result.success && result.registro) {
								const esVincular = data?.registros_vinculados > 0 || data?.ord_compartida;
								const esDividir = data?.modo === 'dividir';
								const esDuplicar = data?.modo === 'duplicar';
								const telarValido = esVincular || esDividir || esDuplicar ||
									(result.registro.SalonTejidoId === data.salon_destino &&
									 result.registro.NoTelarId === data.telar_destino);

								if (telarValido) {
									ptDebugLog(`[DEBUG] ➕ Agregando registro ID: ${registroId}`, {
										TamanoClave: result.registro.TamanoClave,
										ItemId: result.registro.ItemId,
										InventSizeId: result.registro.InventSizeId,
										SalonTejidoId: result.registro.SalonTejidoId,
										NoTelarId: result.registro.NoTelarId
									});
									await agregarRegistroSinRecargar({ registro_id: registroId, message: '', registro: result.registro }, { preventReload: true });
									registrosAgregados.push(parseInt(idStr));
									filasExistentes.push(idStr);
								} else {
									console.warn(`[DEBUG] ⚠️ Registro ${registroId} no pertenece al telar destino`, {
										salon_registro: result.registro.SalonTejidoId,
										salon_destino: data.salon_destino,
										telar_registro: result.registro.NoTelarId,
										telar_destino: data.telar_destino
									});
								}
							} else {
								console.warn(`[DEBUG] ⚠️ No se pudo obtener el registro ${registroId}:`, result);
							}
						} else {
							console.warn(`[DEBUG] ⚠️ Error al obtener el registro ${registroId}:`, response.status);
						}
					} catch (e) {
						console.warn(`[DEBUG] ⚠️ Excepción al obtener el registro ${registroId}:`, e);
					}
				}

				if (data?.modo === 'dividir' && data?.registro_id_original) {
					await actualizarRegistroOriginalDividir(data, tb);
				}

				// Verificar si se agregaron todos los registros
				if (registrosAgregados.length < data.registros_ids.length) {
					console.warn(`[DEBUG] ⚠️ Solo se agregaron ${registrosAgregados.length} de ${data.registros_ids.length} registros esperados`);

					// Intentar agregar los registros faltantes
					const idsFaltantes = data.registros_ids.filter(id => !registrosAgregados.includes(parseInt(id)));
					ptDebugLog(`[DEBUG] 🔄 Intentando agregar registros faltantes:`, idsFaltantes);

					for (const idFaltante of idsFaltantes) {
						try {
							const idStrFaltante = String(idFaltante);
							const registroPrecargado = data.registros_datos && data.registros_datos[idStrFaltante];
							if (registroPrecargado) {
								await agregarRegistroSinRecargar({ registro_id: idFaltante, message: '', registro: registroPrecargado }, { preventReload: true });
								registrosAgregados.push(parseInt(idFaltante));
								continue;
							}
							const response = await fetch(`/planeacion/programa-tejido/${idFaltante}/detalles-balanceo?t=${Date.now()}`, {
								headers: {
									'Accept': 'application/json',
									'X-CSRF-TOKEN': getCsrfToken(),
									'Cache-Control': 'no-cache'
								}
							});

							if (response.ok) {
								const result = await response.json();
								if (result.success && result.registro) {
									await agregarRegistroSinRecargar({ registro_id: idFaltante, message: '', registro: result.registro }, { preventReload: true });
									registrosAgregados.push(parseInt(idFaltante));
								}
							}
						} catch (e) {
							console.warn(`[DEBUG] ⚠️ Error al agregar registro faltante ${idFaltante}:`, e);
						}
					}
				}

				// Mostrar resumen final
				const totalFilasFinal = tb.querySelectorAll('.selectable-row').length;
				ptDebugLog(`[DEBUG] ✅ RESUMEN FINAL: Se agregaron ${registrosAgregados.length} de ${data.registros_ids.length} registros esperados. Total filas en tabla: ${totalFilasFinal}`);

				// Verificar que todos los registros agregados estén visibles con sus datos correctos
				registrosAgregados.forEach((id, index) => {
					const fila = tb.querySelector(`tr.selectable-row[data-id="${id}"]`);
					if (fila) {
						const itemId = fila.querySelector('[data-column="ItemId"]')?.textContent?.trim() || fila.querySelector('[data-column="ItemId"]')?.getAttribute('data-value') || 'NO ENCONTRADO';
						const inventSizeId = fila.querySelector('[data-column="InventSizeId"]')?.textContent?.trim() || fila.querySelector('[data-column="InventSizeId"]')?.getAttribute('data-value') || 'NO ENCONTRADO';
						const tamanoClave = fila.querySelector('[data-column="TamanoClave"]')?.textContent?.trim() || fila.querySelector('[data-column="TamanoClave"]')?.getAttribute('data-value') || 'NO ENCONTRADO';
						ptDebugLog(`[DEBUG] 📋 Registro ${index + 1} (ID: ${id}):`, {
							TamanoClave: tamanoClave,
							ItemId: itemId,
							InventSizeId: inventSizeId,
							visible: true
						});
					} else {
						console.warn(`[DEBUG] ⚠️ Registro ${index + 1} (ID: ${id}) NO está visible en el DOM`);
					}
				});

				if (typeof showToast === 'function') {
					const totalEsperado = data.registros_ids.length;
					const mensajeExito = data?.modo === 'dividir'
						? (data.message || `Se dividió en ${totalEsperado} registro(s) correctamente`)
						: data?.modo === 'duplicar'
							? (data.message || `Se duplicaron ${totalEsperado} registro(s) correctamente`)
							: data?.registros_vinculados
								? `Se vincularon ${data.registros_vinculados} registro(s) correctamente`
								: `Se procesaron ${data.registros_duplicados || totalEsperado} registro(s) correctamente`;

					if (registrosAgregados.length === totalEsperado) {
						showToast(data.message || mensajeExito, 'success');
					} else {
						showToast(`Se agregaron ${registrosAgregados.length} de ${totalEsperado} registro(s). Algunos pueden no estar visibles.`, 'warning');
					}
				}

				ptDebugLog('[DEBUG] ✅ Registros agregados sin recargar, saliendo');
				return; // IMPORTANTE: Salir aquí para NO recargar
			} catch (error) {
				console.error('[DEBUG] ❌ Error al agregar múltiples registros con registros_ids:', error);
				// NO continuar con fallback que recarga - mostrar error y cerrar modal
				if (typeof showToast === 'function') {
					showToast('Error al agregar registros. Algunos pueden no estar visibles.', 'warning');
				}
				ptDebugLog('[DEBUG] ⚠️ Error pero NO recargando para modo dividir/duplicar');
				return; // Salir sin recargar
			}
		}

		// Fallback: Si registros_ids no está disponible, usar método anterior con IDs secuenciales
		// SOLO para duplicar/vincular con múltiples registros (NO para dividir).
		// Cada fila insertada usa registro.Id devuelto por detalles-balanceo (construirFilaRegistro); no se asigna data-id por heurística sin respuesta del API.
		if (data?.modo !== 'dividir' && data?.modo !== 'duplicar' && data?.registro_id && (data?.salon_destino || data?.registros_vinculados)) {
		const totalRegistrosFallback = data?.registros_duplicados || data?.registros_vinculados || 1;
		if (data?.registro_id && (data?.salon_destino || data?.registros_vinculados)) {
			try {
				ptDebugLog('[DEBUG] 🔄 Fallback: Usando método de IDs secuenciales');
				await new Promise(resolve => setTimeout(resolve, 800));

				await agregarRegistroSinRecargar({ registro_id: data.registro_id, message: data.message });
				await new Promise(resolve => setTimeout(resolve, 300));

				const tb = document.querySelector('#mainTable tbody');
				if (tb) {
					let filasExistentes = Array.from(tb.querySelectorAll('.selectable-row')).map(f => f.getAttribute('data-id'));
					const primerId = parseInt(data.registro_id);
					const registrosAgregados = [primerId];
					const esVincular = data?.registros_vinculados > 0 || data?.ord_compartida;

					for (let i = 1; i < totalRegistrosFallback; i++) {
						const siguienteId = primerId + i;
						const tbActualizado = document.querySelector('#mainTable tbody');
						if (tbActualizado) {
							filasExistentes = Array.from(tbActualizado.querySelectorAll('.selectable-row')).map(f => f.getAttribute('data-id'));
						}

						if (!filasExistentes.includes(String(siguienteId))) {
							try {
								await new Promise(resolve => setTimeout(resolve, 300));
								const response = await fetch(`/planeacion/programa-tejido/${siguienteId}/detalles-balanceo?t=${Date.now()}`, {
									headers: {
										'Accept': 'application/json',
										'X-CSRF-TOKEN': getCsrfToken(),
										'Cache-Control': 'no-cache'
									}
								});

								if (response.ok) {
									const result = await response.json();
									if (result.success && result.registro) {
										// Para vincular puede haber múltiples telares destino, así que la validación es más flexible
										const telarValido = esVincular ||
											(result.registro.SalonTejidoId === data.salon_destino &&
											 result.registro.NoTelarId === data.telar_destino);

										if (telarValido) {
											await agregarRegistroSinRecargar({ registro_id: siguienteId, message: '' });
											registrosAgregados.push(siguienteId);
											await new Promise(resolve => setTimeout(resolve, 200));
										}
									}
								}
							} catch (e) {
								console.warn(`[DEBUG] ⚠️ No se pudo obtener el registro ${siguienteId}:`, e);
							}
						}
					}

					if (typeof showToast === 'function') {
						const totalEsperadoFallback = data?.registros_duplicados || data?.registros_vinculados || registrosAgregados.length;
						const mensajeExitoFallback = data?.registros_vinculados
							? `Se vincularon ${data.registros_vinculados} registro(s) correctamente`
							: `Se duplicaron ${data.registros_duplicados || totalEsperadoFallback} registro(s) correctamente`;

						if (registrosAgregados.length === totalEsperadoFallback) {
							showToast(data.message || mensajeExitoFallback, 'success');
						} else {
							showToast(`Se agregaron ${registrosAgregados.length} de ${totalEsperadoFallback} registro(s).`, 'warning');
						}
					}
				}
				return;
			} catch (error) {
				console.error('[DEBUG] ❌ Error en fallback:', error);
			}
		}
		}

	// Último fallback: si no se pueden obtener los IDs o hay error, intentar agregar al menos el primer registro
	// PERO NO para dividir/duplicar - esos deben tener registros_ids
	if (data?.modo !== 'dividir' && data?.modo !== 'duplicar' && data?.registro_id) {
		ptDebugLog('[DEBUG] 🔄 Fallback: Agregando solo el primer registro');
		await agregarRegistroSinRecargar(data);
		if (typeof showToast === 'function') {
			showToast(data.message || `Se procesaron los registros. Algunos pueden no estar visibles.`, 'warning');
		}
		return;
	} else if (data?.modo === 'dividir' || data?.modo === 'duplicar') {
		// Para dividir/duplicar, si llegamos aquí es porque algo falló pero NO recargamos
		console.warn('[DEBUG] ⚠️ Modo dividir/duplicar sin registros_ids - NO recargando');
		if (typeof showToast === 'function') {
			showToast('Los registros se crearon pero no se pudieron mostrar. Por favor recarga manualmente.', 'warning');
		}
		return;
	} else if (data?.salon_destino && data?.telar_destino) {
		if (evitarRecargaProgramaTejido(data)) {
			if (typeof showToast === 'function') {
				showToast(data.message || 'Operación completada. Actualice la vista si no ve los cambios.', 'info');
			}
			return;
		}
		const url = new URL(window.location.href);
		url.searchParams.set('salon', data.salon_destino);
		url.searchParams.set('telar', data.telar_destino);
		window.location.href = url.toString();
		return;
	} else {
		if (evitarRecargaProgramaTejido(data)) {
			if (typeof showToast === 'function') {
				showToast(data.message || 'Operación completada. Actualice la vista si no ve los cambios.', 'warning');
			}
			return;
		}
		ptDebugLog('[DEBUG] 🔄 Último fallback: recargando página');
		window.location.reload();
		return;
	}
	} // cierra if (tieneMultiplesRegistros)

	// Si solo hay un registro, agregarlo sin recargar
	if (data?.registro_id) {
		await agregarRegistroSinRecargar(data);
		if (data?.modo === 'dividir' && data?.registro_id_original) {
			await actualizarRegistroOriginalDividir(data);
		}
		return;
	}

	if (evitarRecargaProgramaTejido(data)) {
		if (typeof showToast === 'function') {
			showToast(data.message || 'Operación completada. Actualice la vista si no ve los cambios.', 'info');
		}
		return;
	}

	if (data?.salon_destino && data?.telar_destino) {
		// Si no hay registro_id pero hay destino, recargar con filtros
		const url = new URL(window.location.href);
		url.searchParams.set('salon', data.salon_destino);
		url.searchParams.set('telar', data.telar_destino);
		window.location.href = url.toString();
		return;
	}

	ptDebugLog('[DEBUG] 🔄 Fallback final: recargando página');
	window.location.reload();
}

async function agregarRegistroSinRecargar(data, { preventReload = false } = {}) {
	if (!data?.registro_id) return;

	let registro = null;

	// Usar datos pre-cargados del backend (registros_datos) cuando estén disponibles para evitar 404 en detalles-balanceo
	if (data.registro && typeof data.registro === 'object') {
		registro = data.registro;
	} else if (data.registros_datos && data.registros_datos[String(data.registro_id)]) {
		registro = data.registros_datos[String(data.registro_id)];
	}

	if (!registro) {
		try {
			const response = await fetch(`/planeacion/programa-tejido/${data.registro_id}/detalles-balanceo?t=${Date.now()}`, {
				headers: {
					'Accept': 'application/json',
					'X-CSRF-TOKEN': getCsrfToken(),
					'Cache-Control': 'no-cache'
				}
			});

			if (!response.ok) {
				throw new Error('No se pudo obtener el registro');
			}

			const result = await response.json();
			if (!result.success || !result.registro) {
				throw new Error('Registro no encontrado');
			}

			registro = result.registro;
		} catch (err) {
			console.warn(`[DEBUG] ⚠️ Error al obtener el registro ${data.registro_id}:`, err);
			throw err;
		}
	}

	if (registro && (registro.Id === undefined || registro.Id === null || registro.Id === '') && registro.id != null) {
		registro.Id = registro.id;
	}

	try {
		// Verificar que los campos clave estén presentes y actualizados
		ptDebugLog(`[DEBUG] 📥 Datos recibidos del endpoint para registro ${registro.Id}:`, {
			TamanoClave: registro.TamanoClave,
			ItemId: registro.ItemId,
			InventSizeId: registro.InventSizeId,
			CustName: registro.CustName,
			FlogsId: registro.FlogsId,
			NoTelarId: registro.NoTelarId,
			SalonTejidoId: registro.SalonTejidoId
		});

		// Verificar si ya existe en la tabla
		const tb = document.querySelector('#mainTable tbody');
		if (!tb) {
			if (preventReload) throw new Error('No se encontró la tabla principal (#mainTable tbody)');
			window.location.reload();
			return;
		}

		const existe = tb.querySelector(`tr.selectable-row[data-id="${registro.Id}"]`);
		if (existe) {
			// El registro ya existe, solo mostrar mensaje
			if (typeof showToast === 'function') {
				showToast(data.message || 'Registro duplicado correctamente', 'success');
			}
			return;
		}

		// Obtener las columnas desde columnsData, window.columns o desde el DOM
		const columns = (typeof columnsData !== 'undefined' && columnsData && columnsData.length > 0)
			? columnsData
			: (window.columns || Array.from(document.querySelectorAll('#mainTable thead th[data-column]')).map(th => ({
				field: th.getAttribute('data-column'),
				label: th.textContent.trim(),
				dateType: null // Por ahora no detectamos el tipo de fecha desde JS
			})));

		if (!columns || columns.length === 0) {
			if (preventReload) throw new Error('No se encontraron columnas para la tabla');
			window.location.reload();
			return;
		}

		// Construir la fila HTML
		const row = construirFilaRegistro(registro, columns);

		// Verificar que la fila se construyó correctamente con los datos
		const itemIdCell = row.querySelector('[data-column="ItemId"]');
		const inventSizeIdCell = row.querySelector('[data-column="InventSizeId"]');
		const tamanoClaveCell = row.querySelector('[data-column="TamanoClave"]');
		const custNameCell = row.querySelector('[data-column="CustName"]');

		ptDebugLog(`[DEBUG] ✅ Fila construida para registro ${registro.Id}:`, {
			ItemId_celda: itemIdCell?.textContent?.trim() || itemIdCell?.innerHTML?.trim() || 'NO ENCONTRADO',
			InventSizeId_celda: inventSizeIdCell?.textContent?.trim() || inventSizeIdCell?.innerHTML?.trim() || 'NO ENCONTRADO',
			TamanoClave_celda: tamanoClaveCell?.textContent?.trim() || tamanoClaveCell?.innerHTML?.trim() || 'NO ENCONTRADO',
			CustName_celda: custNameCell?.textContent?.trim() || custNameCell?.innerHTML?.trim() || 'NO ENCONTRADO',
			ItemId_data_value: itemIdCell?.getAttribute('data-value') || 'NO ENCONTRADO',
			InventSizeId_data_value: inventSizeIdCell?.getAttribute('data-value') || 'NO ENCONTRADO'
		});

		// Encontrar la posición correcta para insertar (ordenado por NoTelar, luego por FechaInicio)
		const filasExistentes = Array.from(tb.querySelectorAll('.selectable-row'));
		const noTelarNuevo = registro.NoTelarId ? String(registro.NoTelarId).trim() : '';
		// Local, no UTC: se compara contra parsearFecha() de abajo, que construye la fecha
		// por componentes. Mezclar ambos criterios desplazaba la insercion un dia.
		const fechaInicio = registro.FechaInicio ? parseSqlDateTimeLocal(registro.FechaInicio) : null;
		let insertarAntes = null;

		// Función auxiliar para parsear fecha desde texto
		const parsearFecha = (fechaTexto) => {
			if (!fechaTexto) return null;
			try {
				const partes = fechaTexto.split('/');
				if (partes.length === 3) {
					return new Date(partes[2], partes[1] - 1, partes[0]);
				}
			} catch (e) {
				// Si hay error parseando, retornar null
			}
			return null;
		};

		// Función auxiliar para obtener NoTelar de una fila
		const obtenerNoTelar = (fila) => {
			const telarCell = fila.querySelector('[data-column="NoTelarId"]');
			return telarCell ? String(telarCell.textContent.trim()) : '';
		};

		// Función auxiliar para obtener FechaInicio de una fila
		const obtenerFechaInicio = (fila) => {
			const fechaCell = fila.querySelector('[data-column="FechaInicio"]');
			if (fechaCell) {
				const fechaTexto = fechaCell.textContent.trim();
				return parsearFecha(fechaTexto);
			}
			return null;
		};

		// Buscar la posición correcta: primero por NoTelar, luego por FechaInicio
		for (const filaExistente of filasExistentes) {
			const noTelarExistente = obtenerNoTelar(filaExistente);
			const fechaExistente = obtenerFechaInicio(filaExistente);

			// Comparar primero por NoTelar (orden numérico si son números, alfabético si no)
			const compararTelar = (telar1, telar2) => {
				const num1 = parseInt(telar1);
				const num2 = parseInt(telar2);
				if (!isNaN(num1) && !isNaN(num2)) {
					return num1 - num2; // Comparación numérica
				}
				return telar1.localeCompare(telar2); // Comparación alfabética
			};

			const comparacionTelar = compararTelar(noTelarNuevo, noTelarExistente);

			if (comparacionTelar < 0) {
				// El telar nuevo es menor, insertar antes de esta fila
				insertarAntes = filaExistente;
				break;
			} else if (comparacionTelar === 0) {
				// Mismo telar, comparar por FechaInicio
				if (fechaInicio && fechaExistente) {
					if (fechaExistente > fechaInicio) {
						insertarAntes = filaExistente;
						break;
					}
				} else if (fechaInicio && !fechaExistente) {
					// Si la fila existente no tiene fecha pero el nuevo sí, insertar después
					continue;
				} else if (!fechaInicio && fechaExistente) {
					// Si el nuevo no tiene fecha pero el existente sí, insertar antes
					insertarAntes = filaExistente;
					break;
				}
				// Si ambos tienen o no tienen fecha, mantener el orden actual
			}
			// Si el telar nuevo es mayor, continuar buscando
		}

		// Antes de insertar, actualizar el campo "Ultimo" del registro anterior del mismo telar
		// Solo si el nuevo registro tiene Ultimo=1
		if (registro.Ultimo == 1 || registro.Ultimo === '1' || registro.Ultimo === 1) {
			const salonId = registro.SalonTejidoId;
			const telarId = registro.NoTelarId;

			const filasMismoTelar = Array.from(tb.querySelectorAll('.selectable-row')).filter(f => {
				const rowId = f.getAttribute('data-id');
				const fSalon = f.querySelector('[data-column="SalonTejidoId"]')?.textContent?.trim();
				const fTelar = f.querySelector('[data-column="NoTelarId"]')?.textContent?.trim();
				return rowId !== String(registro.Id) &&
					fSalon === String(salonId) &&
					fTelar === String(telarId);
			});

			// Encontrar el registro anterior que tenga Ultimo=1 y actualizarlo
			for (const fila of filasMismoTelar) {
				const ultimoCell = fila.querySelector('[data-column="Ultimo"]');
				if (ultimoCell && (ultimoCell.textContent.includes('ULTIMO') || ultimoCell.querySelector('strong'))) {
					// Actualizar visualmente el campo Ultimo del registro anterior
					ultimoCell.innerHTML = '';
					ultimoCell.setAttribute('data-value', '0');
					break;
				}
			}
		}

		// Insertar la fila
		ptDebugLog(`[DEBUG] 📍 Insertando fila ${registro.Id}`, {
			insertarAntes: insertarAntes ? insertarAntes.getAttribute('data-id') : 'null (al final)',
			totalFilasAntes: tb.querySelectorAll('.selectable-row').length
		});

		if (insertarAntes) {
			tb.insertBefore(row, insertarAntes);
		} else {
			tb.appendChild(row);
		}
		window.PT?.filterIndex?.updateRow(row);

		// Hacer scroll para que la nueva fila sea visible
		if (row.scrollIntoView) {
			row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		}

		ptDebugLog(`[DEBUG] ✅ Fila ${registro.Id} insertada. Total filas después:`, tb.querySelectorAll('.selectable-row').length);

		// Actualizar window.allRows manualmente y actualizar índices
		window.allRows = Array.from(tb.querySelectorAll('.selectable-row'));

		// Actualizar data-row-index de todas las filas
		window.allRows.forEach((fila, index) => {
			fila.setAttribute('data-row-index', index);
		});

		// Limpiar cache de filas para que se recalcule correctamente
		if (typeof clearRowCache === 'function') {
			clearRowCache();
		} else if (window.PT && window.PT.rowCache) {
			window.PT.rowCache = new WeakMap();
		}

		// Aplicar columnas ocultas a la nueva fila basándonos en el estado del header
		// Obtener todas las columnas del header y aplicar el mismo estado de visibilidad a la nueva fila
		const headerCells = document.querySelectorAll('#mainTable thead th[data-column]');
		headerCells.forEach((th) => {
			// Extraer el índice de la columna desde las clases column-X
			const classList = Array.from(th.classList);
			const columnClass = classList.find(cls => cls.startsWith('column-'));
			if (columnClass) {
				const colIndex = parseInt(columnClass.replace('column-', ''));
				if (!isNaN(colIndex)) {
					// Verificar si la columna está oculta en el header
					const isHidden = th.style.display === 'none' ||
					                th.classList.contains('hidden') ||
					                window.getComputedStyle(th).display === 'none';

					if (isHidden) {
						// Aplicar el mismo estado a la celda correspondiente en la nueva fila
						const cell = row.querySelector(`td.column-${colIndex}`);
						if (cell) {
							cell.style.display = 'none';
						}
					}
				}
			}
		});

		// Actualizar posiciones de columnas fijadas para que la nueva fila tenga los estilos correctos
		if (typeof window.updatePinnedColumnsPositions === 'function') {
			window.updatePinnedColumnsPositions();
		}

		// Asegurar que los campos editables (TamanoClave, FlogsId, etc.) no tengan inputs bloqueados
		// Verificar que las celdas editables estén listas para el modo inline edit
		row.querySelectorAll('td[data-column]').forEach(cell => {
			const columnName = cell.getAttribute('data-column');
			// Si el campo es editable según uiInlineEditableFields, asegurar que no tenga inputs readonly/disabled
			if (columnName && typeof uiInlineEditableFields !== 'undefined' && uiInlineEditableFields[columnName]) {
				// Remover cualquier input readonly o disabled que pueda existir en la celda
				const inputs = cell.querySelectorAll('input[readonly], input[disabled], textarea[readonly], textarea[disabled]');
				inputs.forEach(input => {
					// Si es un campo editable como TamanoClave o FlogsId, eliminar el input y dejar solo el texto
					if (columnName === 'TamanoClave' || columnName === 'FlogsId') {
						const textValue = input.value || input.textContent || '';
						input.remove();
						cell.innerHTML = textValue || '';
						cell.setAttribute('data-value', textValue);
					} else {
						// Para otros campos editables, solo remover los atributos bloqueados
						input.removeAttribute('readonly');
						input.removeAttribute('disabled');
						input.classList.remove('bg-gray-100', 'cursor-not-allowed');
					}
				});
			}
		});

		// Hacer la nueva fila seleccionable - asignar event listener
		// La seleccion la maneja el listener delegado en tbody (bindRowSelectionOnce);
		// las filas nuevas quedan clicables sin enganchar nada.

		// Actualizar totales si existe la función
		if (typeof window.updateTotales === 'function') {
			window.updateTotales();
		}

		// Mostrar mensaje de éxito
		if (typeof showToast === 'function') {
			showToast(data.message || 'Registro duplicado correctamente', 'success');
		}

	} catch (error) {
		console.error('Error al agregar registro:', error);
		if (preventReload) throw error;
		if (data?.salon_destino && data?.telar_destino) {
			const url = new URL(window.location.href);
			url.searchParams.set('salon', data.salon_destino);
			url.searchParams.set('telar', data.telar_destino);
			if (data.registro_id) {
				url.searchParams.set('registro_id', data.registro_id);
			}
			window.location.href = url.toString();
		} else {
			window.location.reload();
		}
	}
}

function construirFilaRegistro(registro, columns) {
	if (registro.id != null && (registro.Id === undefined || registro.Id === null || registro.Id === '')) {
		registro.Id = registro.id;
	}
	const tr = document.createElement('tr');
	tr.className = 'hover:bg-blue-50 cursor-pointer selectable-row';
	const rowPk = registro.Id ?? registro.id;
	if (rowPk === undefined || rowPk === null || rowPk === '') {
		console.error('construirFilaRegistro: registro sin Id', registro);
	}
	tr.setAttribute('data-id', String(rowPk ?? ''));

	const producto = registro.NombreProducto || '';
	const esRepaso = producto && producto.toUpperCase().substring(0, 6) === 'REPASO';
	if (esRepaso) {
		tr.setAttribute('data-es-repaso', '1');
	}
	if (registro.OrdCompartida) {
		tr.setAttribute('data-ord-compartida', registro.OrdCompartida);
	}

	// Obtener índice actual de filas para data-row-index
	const tb = document.querySelector('#mainTable tbody');
	const totalFilas = tb ? tb.querySelectorAll('.selectable-row').length : 0;
	tr.setAttribute('data-row-index', totalFilas);

	columns.forEach((col, colIndex) => {
		const td = document.createElement('td');
		const field = col.field;
		const value = registro[field] !== undefined ? registro[field] : null;
		const rawValue = value;

		// Determinar clases CSS
		// Mismo markup que el SSR de req-programa-tejido.blade.php: los estilos
		// vienen de #mainTable tbody td en main.css.
		let clases = 'column-' + colIndex;
		if (col.dateType) clases += ' pt-wrap';

		// Detectar valores negativos en PTvsCte
		let esNegativo = false;
		if (field === 'PTvsCte' && value !== null && value !== '') {
			const valorNumerico = isNaN(value) ? 0 : parseFloat(value);
			esNegativo = valorNumerico < 0;
			if (esNegativo) {
				clases += ' valor-negativo';
				td.setAttribute('data-es-negativo', '1');
			}
		}

		td.className = clases;
		td.setAttribute('data-column', field);
		td.setAttribute('data-value', rawValue !== null && rawValue !== undefined ? String(rawValue) : '');

		// Formatear el valor según el tipo de campo
		let contenidoHTML = formatearValorCelda(registro, field, value, col.dateType);
		td.innerHTML = contenidoHTML;

		tr.appendChild(td);
	});

		// Asegurar que los eventos se propaguen correctamente
		// Los event listeners globales se aplicarán automáticamente si están configurados con delegación de eventos

		return tr;
	}

function formatearValorCelda(registro, field, value, dateType) {
	if (value === null || value === undefined || value === '') {
		if (field === 'Reprogramar' || field === 'EnProceso') {
			return '<input type="checkbox" disabled class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">';
		}
		return '';
	}

	// Campo Reprogramar
	if (field === 'Reprogramar') {
		const valorActual = value || '';
		const checked = (valorActual == '1' || valorActual == '2') ? 'checked' : '';
		let textoMostrar = '';
		if (valorActual == '1') {
			textoMostrar = 'P. Siguiente';
		} else if (valorActual == '2') {
			textoMostrar = 'P. Ultima';
		}
		const enProceso = registro.EnProceso ?? 0;
		const estaEnProceso = (enProceso == 1 || enProceso === true);
		const disabled = estaEnProceso ? '' : 'disabled';
		const cursorClass = estaEnProceso ? 'cursor-pointer' : 'cursor-not-allowed opacity-50';
		const dataEnProceso = estaEnProceso ? 'data-en-proceso="1"' : 'data-en-proceso="0"';
		return `<div class="relative inline-flex items-center reprogramar-container" data-registro-id="${registro.Id}" ${dataEnProceso}>
			<input type="checkbox" ${checked} ${disabled} class="reprogramar-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 ${cursorClass}" data-registro-id="${registro.Id}" data-valor-actual="${valorActual}">
			<span class="reprogramar-texto ml-2 text-xs text-gray-600 font-medium">${textoMostrar}</span>
		</div>`;
	}

	// Campo EnProceso
	if (field === 'EnProceso') {
		const checked = (value == 1 || value === true) ? 'checked' : '';
		return `<input type="checkbox" ${checked} disabled class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">`;
	}

	// Campo Ultimo
	if (field === 'Ultimo') {
		const sv = String(value).toUpperCase().trim();
		if (sv === 'UL' || sv === '1') return '<strong>ULTIMO</strong>';
		if (sv === '0') return '';
	}

	// Campo CambioHilo
	if (field === 'CambioHilo' && (value === '0' || value === 0)) {
		return '';
	}

	// Campo EficienciaSTD
	if (field === 'EficienciaSTD' && !isNaN(value)) {
		return (parseFloat(value) * 100).toFixed(0) + '%';
	}

	// Campo PTvsCte (Dif vs Compromiso)
	if (field === 'PTvsCte' && !isNaN(value)) {
		const valorFloat = parseFloat(value);
		const parteEntera = parseInt(valorFloat);
		const parteDecimal = Math.abs(valorFloat - parteEntera);

		if (parteDecimal > 0.50) {
			return valorFloat >= 0 ? String(Math.ceil(valorFloat)) : String(Math.floor(valorFloat));
		} else {
			return String(parteEntera);
		}
	}

	// Campo AnchoToalla - siempre 2 decimales
	if (field === 'AnchoToalla' && !isNaN(value)) {
		return parseFloat(value).toFixed(2);
	}

	// Campo CuentaPie
	if (field === 'CuentaPie') {
		return String(value);
	}

	// Campo PesoGRM2
	if (field === 'PesoGRM2' && !isNaN(value)) {
		return parseFloat(value).toFixed(2);
	}

	// Campo MedidaPlano
	if (field === 'MedidaPlano') {
		return value !== null && value !== undefined ? String(value) : '';
	}

	// Campo CodColorCtaPie
	if (field === 'CodColorCtaPie') {
		return value !== null && value !== undefined ? String(value) : '';
	}

	// Campo NombreCPie (Color Pie)
	if (field === 'NombreCPie') {
		return value !== null && value !== undefined ? String(value) : '';
	}

	// Campo DiasEficiencia
	if (field === 'DiasEficiencia' && !isNaN(value)) {
		return parseFloat(value).toFixed(2);
	}

	// Campo ProdKgDia
	if (field === 'ProdKgDia' && !isNaN(value)) {
		return parseFloat(value).toFixed(2);
	}

	// Campo ProdKgDia2
	if (field === 'ProdKgDia2' && !isNaN(value)) {
		return parseFloat(value).toFixed(2);
	}

	// Campo StdToaHra
	if (field === 'StdToaHra' && !isNaN(value)) {
		return parseFloat(value).toFixed(2);
	}

	// Campo DiasJornada
	if (field === 'DiasJornada' && !isNaN(value)) {
		return parseFloat(value).toFixed(2);
	}

	// Campo HorasProd
	if (field === 'HorasProd' && !isNaN(value)) {
		return parseFloat(value).toFixed(2);
	}

	// Campo StdHrsEfect
	if (field === 'StdHrsEfect' && !isNaN(value)) {
		return parseFloat(value).toFixed(2);
	}

	// Campo TamanoClave (Clave Modelo) - texto simple, no input
	if (field === 'TamanoClave') {
		return value !== null && value !== undefined ? String(value) : '';
	}

	// Campo FlogsId (Flogs) - texto simple, no input
	if (field === 'FlogsId') {
		return value !== null && value !== undefined ? String(value) : '';
	}

	// Campo EntregaCte (datetime)
	if (field === 'EntregaCte') {
		if (!value || value === null || value === '') return '';
		try {
			// parseSqlDateTimeLocal parsea por componentes en hora local (esta declarado
			// en inline-edit.blade.php, mismo scope de script). new Date('2026-01-05')
			// se interpreta como UTC y en America/Mexico_City devolvia el dia anterior.
			const dt = parseSqlDateTimeLocal(value);
			if (!dt || dt.getFullYear() <= 1970) return '';
			const day = String(dt.getDate()).padStart(2, '0');
			const month = String(dt.getMonth() + 1).padStart(2, '0');
			const year = dt.getFullYear();
			const hours = String(dt.getHours()).padStart(2, '0');
			const minutes = String(dt.getMinutes()).padStart(2, '0');
			return `${day}/${month}/${year} ${hours}:${minutes}`;
		} catch (e) {
			return '';
		}
	}

	// Fechas
	if (dateType === 'date' || dateType === 'datetime') {
		try {
			// parseSqlDateTimeLocal parsea por componentes en hora local (esta declarado
			// en inline-edit.blade.php, mismo scope de script). new Date('2026-01-05')
			// se interpreta como UTC y en America/Mexico_City devolvia el dia anterior.
			const dt = parseSqlDateTimeLocal(value);
			if (!dt || dt.getFullYear() <= 1970) return '';
			if (dateType === 'date') {
				const day = String(dt.getDate()).padStart(2, '0');
				const month = String(dt.getMonth() + 1).padStart(2, '0');
				const year = dt.getFullYear();
				return `${day}/${month}/${year}`;
			} else {
				const day = String(dt.getDate()).padStart(2, '0');
				const month = String(dt.getMonth() + 1).padStart(2, '0');
				const year = dt.getFullYear();
				const hours = String(dt.getHours()).padStart(2, '0');
				const minutes = String(dt.getMinutes()).padStart(2, '0');
				return `${day}/${month}/${year} ${hours}:${minutes}`;
			}
		} catch (e) {
			return '';
		}
	}

	// Números con decimales (para campos numéricos generales)
	if (!isNaN(value) && isNaN(parseInt(value))) {
		return parseFloat(value).toFixed(2);
	}

	// Valor por defecto. El resultado se asigna con innerHTML y las ramas de arriba
	// devuelven HTML, así que esta rama (texto libre de BD) debe ir escapada.
	return escapeHtmlPtModal(value);
}

  // ===== Estado =====
let filters = [];
let hiddenColumns = [];
let pinnedColumns = [];
// Fuente unica en window: main.blade.php y _shared-helpers.blade.php escriben
// estos tres desde otro punto del script. Tenerlos ademas como binding lexico
// creaba DOS estados: filters/selection usaban uno y el resto el otro, asi que
// tras filtrar+borrar la seleccion operaba sobre filas ya desconectadas del DOM
// y revertia valores de un registro que el usuario no habia tocado.
window.allRows = [];
// Accesor sobre la fila, no un número congelado (seleccion.ts).
ptInstalarIndiceSeleccion(window, () => (window.allRows.length > 0 ? window.allRows : document.querySelectorAll('.selectable-row')));
// "Liberar órdenes" del navbar: data-accion en vez de onclick (HANDOFF 17-02 B1). Import
// dinámico: el modal trae SweetAlert, que no debe evaluarse al cargar el bundle.
ptEnlazarDiasLiberar(document, () => import('../componentes/dias-liberar.ts').then((m) => m.mostrarModalDiasLiberar()));
window.inlineEditMode = false;

const normalizeInputValue = (value) => {
	if (value === undefined || value === null) return '';
	const str = String(value).trim();
	if (!str || str.toLowerCase() === 'null') return '';
	return str;
};

const normalizeDateValue = (value) => {
	const str = normalizeInputValue(value);
	if (!str) return '';
	if (str.includes('T')) return str.split('T')[0];
	if (str.includes(' ')) return str.split(' ')[0];
	return str;
};

const normalizeDateTimeValue = (value) => {
	let str = normalizeInputValue(value);
	if (!str) return '';
	str = str.replace('T', ' ').replace('Z', '');
	if (str.includes('.')) str = str.split('.')[0];
	const [datePart, rawTime = ''] = str.split(' ');
	if (!rawTime) return `${datePart} 00:00:00`.trim();
	const [hour = '00', minute = '00', second = '00'] = rawTime.split(':');
	return `${datePart} ${hour.padStart(2, '0')}:${minute.padStart(2, '0')}:${second.padStart(2, '0')}`;
};

const formatDateInputValue = (value) => normalizeDateValue(value);

const formatDateDisplayValue = (value) => {
	const normalized = normalizeDateValue(value);
	if (!normalized) return '';
	const [year, month, day] = normalized.split('-');
	if (!year || !month || !day) return normalized;
	return `${day.padStart(2, '0')}/${month.padStart(2, '0')}/${year}`;
};

const formatDateTimeInputValue = (value) => {
	const normalized = normalizeDateTimeValue(value);
	if (!normalized) return '';
	const [datePart, timePart = ''] = normalized.split(' ');
	if (!datePart) return '';
	const [hour = '00', minute = '00'] = timePart.split(':');
	return `${datePart}T${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
};

const formatDateTimeDisplayValue = (value) => {
	const normalized = normalizeDateTimeValue(value);
	if (!normalized) return '';
	const [datePart, timePart = ''] = normalized.split(' ');
	if (!datePart) return normalized;
	const [year, month, day] = datePart.split('-');
	if (!year || !month || !day) return normalized;
	if (!timePart) return `${day.padStart(2, '0')}/${month.padStart(2, '0')}/${year}`;
	const [hour = '00', minute = '00'] = timePart.split(':');
	return `${day.padStart(2, '0')}/${month.padStart(2, '0')}/${year} ${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
};

const datetimeLocalToSql = (value) => {
	if (!value) return null;
	const [datePart, timePart = ''] = value.split('T');
	if (!datePart) return null;
	const [hour = '00', minute = '00'] = timePart.split(':');
	return `${datePart} ${hour.padStart(2, '0')}:${minute.padStart(2, '0')}:00`;
};

const dateCompareValue = (value) => {
	const normalized = formatDateInputValue(value);
	return normalized === '' ? null : normalized;
};

const dateTimeCompareValue = (value) => {
	const normalized = normalizeDateTimeValue(value);
	return normalized === '' ? null : normalized;
};

const createDateFieldConfig = () => ({
	type: 'date',
	inputFormatter: formatDateInputValue,
	displayFormatter: formatDateDisplayValue,
	toPayload: (value) => (value === '' ? null : value),
	compareFormatter: dateCompareValue
});

const createDateTimeFieldConfig = () => ({
	type: 'datetime-local',
	inputFormatter: formatDateTimeInputValue,
	displayFormatter: formatDateTimeDisplayValue,
	toPayload: (value) => (value ? datetimeLocalToSql(value) : null),
	compareFormatter: dateTimeCompareValue
});

// Cache de catÃ¡logos cargados
let catalogosCache = {
	hilos: null,
	aplicaciones: null,
	calendarios: null
};

// Mapeo de campos editables a nombres de payload para el backend
const inlineFieldPayloadMap = {
	FibraRizo: 'hilo',
	CalendarioId: 'calendario_id',
	TamanoClave: 'tamano_clave',
	Rasurado: 'rasurado',
	TotalPedido: 'pedido',
	ProgramarProd: 'programar_prod',
	FlogsId: 'idflog',
	NombreProyecto: 'descripcion',
	AplicacionId: 'aplicacion_id',
	NoTiras: 'no_tiras',
	Peine: 'peine',
	LargoCrudo: 'largo_crudo',
	Luchaje: 'luchaje',
	PesoCrudo: 'peso_crudo',
	Ancho: 'ancho',
	AnchoToalla: 'ancho_toalla',
	EntregaProduc: 'entrega_produc',
	EntregaPT: 'entrega_pt',
	EntregaCte: 'entrega_cte',
	PTvsCte: 'pt_vs_cte',
	NoProduccion: 'no_produccion'
};

// ===== Helpers DOM =====
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
const tbodyEl = () => $('#mainTable tbody');

// ===== Columnas desde PHP =====
const columnsData = PT_BOOT.columns || [];


  // ===== Sistema de Filtros Avanzado – Programa Tejido =====

// Estado de filtros
// NOTE: `filters` se declara globalmente en state.blade.php
let quickFilters = {
    ultimos: false,
    divididos: false,
    enProceso: false,
    salonJacquard: false,
    salonSmit: false,
    conCambioHilo: false,
};

let dateRangeFilters = {
    fechaInicio: { desde: null, hasta: null },
    fechaFinal: { desde: null, hasta: null },
};

let lastFilterState = null;
let debounceTimer = null;

// Columnas excluidas del selector (se manejan como quickfilters o fechas)
const excludedColumns = ['Estado', 'Salon', 'SalonTejidoId', 'CambioHilo', 'FechaInicio', 'FechaFinal'];

// ===== Filtros Rápidos =====
const quickFilterConfig = {
    ultimos: {
        label: 'Últimos',
        icon: 'fa-flag-checkered',
        description: 'Últimos registros de cada telar',
        check: (row) => {
            const cell = row.querySelector('[data-column="Ultimo"]');
            if (!cell) return false;
            const val = (cell.dataset.value || cell.textContent || '').toString().trim();
            return val === '1' || val.toUpperCase() === 'UL';
        },
    },
    divididos: {
        label: 'Telares divididos',
        icon: 'fa-code-branch',
        description: 'Telares con orden compartida',
        check: (row) => {
            // Usar el atributo data-ord-compartida del <tr> en lugar de buscar una celda
            const ordCompartida = row.dataset.ordCompartida;
            if (!ordCompartida) return false;
            const val = ordCompartida.toString().trim();
            return val !== '' && val !== '0' && val.toLowerCase() !== 'null' && val.toLowerCase() !== 'undefined';
        },
    },
    enProceso: {
        label: 'En proceso',
        icon: 'fa-spinner',
        description: 'Registros actualmente en proceso',
        check: (row) => {
            const cell = row.querySelector('[data-column="EnProceso"]');
            if (!cell) return false;
            const checkbox = cell.querySelector('input[type="checkbox"]');
            if (checkbox) return checkbox.checked;
            const val = (cell.dataset.value || cell.textContent || '').toString().trim();
            return val === '1' || val.toLowerCase() === 'true';
        },
    },
    salonJacquard: {
        label: 'JACQUARD',
        icon: 'fa-industry',
        description: 'Solo salón Jacquard',
        check: (row) => {
            const cell = row.querySelector('[data-column="Salon"]') || row.querySelector('[data-column="SalonTejidoId"]');
            if (!cell) return false;
            const val = (cell.dataset.value || cell.textContent || '').toString().trim().toUpperCase();
            return val.includes('JACQUARD');
        },
    },
    salonSmit: {
        label: 'SMIT',
        icon: 'fa-industry',
        description: 'Solo salón Smit',
        check: (row) => {
            const cell = row.querySelector('[data-column="Salon"]') || row.querySelector('[data-column="SalonTejidoId"]');
            if (!cell) return false;
            const val = (cell.dataset.value || cell.textContent || '').toString().trim().toUpperCase();
            return val.includes('SMIT');
        },
    },
    conCambioHilo: {
        label: 'Cambio de hilo',
        icon: 'fa-exchange-alt',
        description: 'Con cambio de hilo',
        check: (row) => {
            const cell = row.querySelector('[data-column="CambioHilo"]');
            if (!cell) return false;
            const val = (cell.dataset.value || cell.textContent || '').toString().trim();
            return val === '1' || val === 'true' || val === 'Sí';
        },
    },
};

// ===== Aplicar filtros (quick + personalizados + fechas) =====
window.applyProgramaTejidoFilters = applyProgramaTejidoFilters;
function applyProgramaTejidoFilters() {
    const tb = tbodyEl();
    if (!tb) return;

    const currentState = JSON.stringify({ filters, quickFilters, dateRangeFilters });
    if (currentState === lastFilterState) {
        return;
    }

    const rows = Array.from(tb.querySelectorAll('.selectable-row'));
    const hasQuickFilters = Object.values(quickFilters).some(Boolean);
    const hasCustomFilters = filters.length > 0;
    const hasDateFilters = Object.values(dateRangeFilters).some(d => d.desde || d.hasta);

    // Filtros rápidos activos (excepto salones que son mutuamente excluyentes)
    const activeQuickChecks = hasQuickFilters
        ? Object.entries(quickFilters)
            .filter(([, active]) => active)
            .map(([key]) => quickFilterConfig[key].check)
        : [];

    // Agrupar filtros por columna: OR entre valores de la misma columna, AND entre columnas.
    const filtersByColumn = hasCustomFilters ? ptGroupFiltersByColumn(filters) : {};
    const checkFilterMatch = ptCheckFilterMatch;

    let visibleRows = 0;

    rows.forEach(row => {
        const rowId = row.dataset.id;
        const rowData = window.PT_FILTER_INDEX?.get(rowId) ?? null;

        // Quick filters siguen usando DOM (necesitan estado de checkboxes, etc.)
        const matchesQuick = !hasQuickFilters || activeQuickChecks.every(check => check(row));

        // Filtros custom usan índice en memoria si disponible, fallback al DOM
        let matchesCustom = true;
        if (hasCustomFilters) {
            matchesCustom = rowData
                ? ptRowMatchesCustomFilters(rowData, filtersByColumn)
                : Object.entries(filtersByColumn).every(([column, columnFilters]) => {
                    const cell = row.querySelector(`[data-column="${column}"]`);
                    if (!cell) return false;
                    const cellValue = String(cell.dataset.value || cell.textContent || '').trim().toLowerCase();
                    return columnFilters.some(filter => checkFilterMatch(cellValue, filter));
                });
        }

        const matchesDates = !hasDateFilters || checkDateFilters(row);
        const shouldShow = matchesQuick && matchesCustom && matchesDates;
        if (shouldShow) {
            row.style.display = '';
            row.classList.remove('filter-hidden');
            visibleRows++;
        } else {
            row.style.display = 'none';
            row.classList.add('filter-hidden');
        }
    });

    window.allRows = rows;
    // clearRowCache puede no estar disponible en este scope, verificar antes de llamar
    if (typeof clearRowCache === 'function') {
        clearRowCache();
    } else if (typeof window.clearRowCache === 'function') {
        window.clearRowCache();
    } else if (window.PT && typeof window.PT.clearRowCache === 'function') {
        window.PT.clearRowCache();
    }
    if (window.inlineEditMode) applyInlineModeToRows();

    lastFilterState = currentState;
    updateFilterUI();

    // Actualizar totales después de aplicar filtros (con delay para asegurar que los estilos se aplicaron)
    // Usar requestAnimationFrame para asegurar que el DOM se actualizó
    requestAnimationFrame(() => {
        setTimeout(() => {
            if (typeof window.updateTotales === 'function') {
                window.updateTotales();
            } else {
                console.warn('updateTotales no está disponible');
            }
        }, 50);
    });

    const totalFilters = filters.length + Object.values(quickFilters).filter(Boolean).length +
                        (hasDateFilters ? 1 : 0);
    if (typeof showToast === 'function') {
        if (totalFilters > 0) {
            showToast(`${visibleRows} resultado(s) encontrado(s)`, visibleRows > 0 ? 'success' : 'warning');
        } else {
            showToast('Mostrando todas las filas', 'info');
        }
    }

    if (typeof window.onFiltersApplied === 'function') {
        window.onFiltersApplied(filters);
    }
}

// ===== Verificar filtros de fecha =====
// data-value de las columnas de fecha viene como 'Y-m-d H:i:s' y los <input type="date">
// entregan 'Y-m-d', asi que dateInRange compara strings y no toca
// zonas horarias: new Date('2026-09-15') era UTC medianoche, o sea el dia 14 en
// America/Mexico_City, y el rango se corria un dia dejando fuera el 'hasta'.
function checkDateFilters(row) {
    for (const [field, range] of Object.entries(dateRangeFilters)) {
        if (!range.desde && !range.hasta) continue;

        const columnName = field === 'fechaInicio' ? 'FechaInicio' : 'FechaFinal';
        const cell = row.querySelector(`[data-column="${columnName}"]`);
        if (!cell) return false;

        const cellValue = (cell.dataset.value || '').trim();
        if (!ptDateInRange(cellValue, range.desde, range.hasta)) return false;
    }
    return true;
}

// ===== Quick filters: toggle + UI =====
window.toggleQuickFilter = toggleQuickFilter;
function toggleQuickFilter(filterKey) {
    // Si es un filtro de salón, desactivar el otro
    if (filterKey === 'salonJacquard' && !quickFilters.salonJacquard) {
        quickFilters.salonSmit = false;
    } else if (filterKey === 'salonSmit' && !quickFilters.salonSmit) {
        quickFilters.salonJacquard = false;
    }

    quickFilters[filterKey] = !quickFilters[filterKey];
    lastFilterState = null;
    applyProgramaTejidoFilters();

    // Actualizar UI de todos los botones de salón
    ['salonJacquard', 'salonSmit'].forEach(key => {
        const btn = document.querySelector(`[data-quick-filter="${key}"]`);
        if (btn) updateQuickFilterButton(btn, key);
    });

    const btn = document.querySelector(`[data-quick-filter="${filterKey}"]`);
    if (btn) {
        updateQuickFilterButton(btn, filterKey);
    }
}

function renderQuickFilterButtons() {
    return Object.entries(quickFilterConfig)
        .map(([key, config]) => {
            const isActive = quickFilters[key];
            return `
                <button
                    type="button"
                    data-quick-filter="${key}"
                    class="relative flex items-center gap-2 px-3 py-2 rounded-lg text-xs
                           ${isActive ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}
                           transition-all">
                    <i class="fa-solid ${config.icon} ${isActive ? 'text-white' : 'text-gray-400'}"></i>
                    <div class="text-left">
                        <div class="font-medium text-xs">${config.label}</div>
                    </div>
                    ${isActive ? `<i class="fa-solid fa-check text-[10px] ml-auto"></i>` : ''}
                </button>
            `;
        })
        .join('');
}

function updateQuickFilterButton(btn, filterKey) {
    const config = quickFilterConfig[filterKey];
    const isActive = quickFilters[filterKey];

    btn.className =
        `relative flex items-center gap-2 px-3 py-2 rounded-lg text-xs transition-all ` +
        (isActive ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200');

    const icon = btn.querySelector('i:first-child');
    if (icon) {
        icon.className = `fa-solid ${config.icon} ${isActive ? 'text-white' : 'text-gray-400'}`;
    }

    // Actualizar o agregar check
    const existingCheck = btn.querySelector('.fa-check');
    if (isActive && !existingCheck) {
        btn.insertAdjacentHTML('beforeend', `<i class="fa-solid fa-check text-[10px] ml-auto"></i>`);
    } else if (!isActive && existingCheck) {
        existingCheck.remove();
    }
}

// ===== Modal Filtros – Programa Tejido (SweetAlert2) =====
window.openProgramaTejidoFilterModal = openProgramaTejidoFilterModal;
function openProgramaTejidoFilterModal() {
    if (typeof window.notify === 'undefined') {
        console.warn('notify no está disponible');
        return;
    }

    // Filtrar columnas excluidas
    const filteredColumns = columnsData.filter(c => !excludedColumns.includes(c.field));

    const html = `
        <div class="w-full max-h-[80vh] overflow-hidden flex flex-col">
            <section class="flex-1 overflow-y-auto bg-white px-5 py-4 space-y-4">
                <!-- Filtros rápidos -->
                <div class="space-y-2">
                    <span class="text-[11px] font-semibold uppercase text-gray-400">Filtros rápidos</span>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        ${renderQuickFilterButtons()}
                    </div>
                </div>

                <!-- Filtros de fecha -->
                <div class="space-y-2 pt-3 border-t border-gray-100">
                    <span class="text-[11px] font-semibold uppercase text-gray-400">Rango de fechas</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="text-[11px] text-gray-500">Fecha Inicio</label>
                            <div class="flex gap-2">
                                <input type="date" id="fecha-inicio-desde"
                                       value="${dateRangeFilters.fechaInicio.desde || ''}"
                                       class="flex-1 rounded-lg bg-gray-100 px-2 py-1.5 text-xs text-gray-700
                                              focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                       placeholder="Desde">
                                <input type="date" id="fecha-inicio-hasta"
                                       value="${dateRangeFilters.fechaInicio.hasta || ''}"
                                       class="flex-1 rounded-lg bg-gray-100 px-2 py-1.5 text-xs text-gray-700
                                              focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                       placeholder="Hasta">
                            </div>
                        </div>
                <div class="space-y-1">
                            <label class="text-[11px] text-gray-500">Fecha Final</label>
                            <div class="flex gap-2">
                                <input type="date" id="fecha-final-desde"
                                       value="${dateRangeFilters.fechaFinal.desde || ''}"
                                       class="flex-1 rounded-lg bg-gray-100 px-2 py-1.5 text-xs text-gray-700
                                              focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                       placeholder="Desde">
                                <input type="date" id="fecha-final-hasta"
                                       value="${dateRangeFilters.fechaFinal.hasta || ''}"
                                       class="flex-1 rounded-lg bg-gray-100 px-2 py-1.5 text-xs text-gray-700
                                              focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                       placeholder="Hasta">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros activos -->
                <div id="activeFiltersSection" class="${filters.length === 0 ? 'hidden' : ''} space-y-2 pt-3 border-t border-gray-100">
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-semibold uppercase text-gray-400">Filtros activos</span>
                        <span class="inline-flex items-center justify-center rounded-full bg-blue-100 px-1.5 text-[10px] font-bold text-blue-600">
                            ${filters.length}
                        </span>
                    </div>
                    <div id="activeFiltersList" class="flex flex-wrap gap-1.5">
                        ${renderActiveFilters()}
                        </div>
                </div>

                <!-- Buscar en columna -->
                <div class="space-y-2 pt-3 border-t border-gray-100">
                    <span class="text-[11px] font-semibold uppercase text-gray-400">Buscar en columna</span>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <select id="filtro-columna"
                                class="flex-1 rounded-lg bg-gray-100 px-3 py-2 text-xs text-gray-700
                                       focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                            <option value="">Columna...</option>
                            ${filteredColumns.map(c => `<option value="${c.field}">${c.label}</option>`).join('')}
                    </select>
                        <div id="filtro-valor-container" class="flex-[2]">
                            <input type="text" id="filtro-valor" placeholder="Valor a buscar..."
                                   class="w-full rounded-lg bg-gray-100 px-3 py-2 text-xs text-gray-700
                                          focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                   onkeypress="if(event.key==='Enter')addCustomFilter()">
                </div>
                        <button type="button" data-action="add"
                                class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700 transition-colors">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                    </button>
                </div>
                </div>
            </section>


            </div>
	`;

	window.notify.dialog({
        titulo: 'Filtros',
        html,
        tono: null,
        formulario: true,
        ancho: 'lg',
        botones: [{ texto: 'Cerrar', valor: 'cerrar', variante: 'secundario' }],
        alAbrir: (modalEl) => {
            // Quick filters - ya aplican automáticamente
            modalEl.querySelectorAll('[data-quick-filter]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const key = btn.dataset.quickFilter;
                    toggleQuickFilter(key);
                    // Actualizar todos los botones (para el caso de salones mutuamente excluyentes)
                    modalEl.querySelectorAll('[data-quick-filter]').forEach(b => {
                        const k = b.dataset.quickFilter;
                        updateQuickFilterButton(b, k);
                    });
                });
            });

            // Agregar filtro - aplica automáticamente
            modalEl.querySelector('[data-action="add"]')?.addEventListener('click', (e) => {
                e.preventDefault();
                addCustomFilter();
            });

            // Cerrar modal
            modalEl.querySelector('[data-action="close"]')?.addEventListener('click', (e) => {
                e.preventDefault();
                closeProgramaTejidoFilterModal();
            });

            // Limpiar todo
            modalEl.querySelector('[data-action="reset"]')?.addEventListener('click', (e) => {
                e.preventDefault();
                resetAllFiltersInModal(modalEl);
            });

            // Filtros de fecha - aplicar automáticamente con debounce
            const dateInputs = modalEl.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                input.addEventListener('change', () => {
                    saveDateRangeFilters();
                    // Debounce para evitar muchas llamadas seguidas
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        lastFilterState = null;
                        applyProgramaTejidoFilters();
                    }, 300);
                });
            });

            setTimeout(() => modalEl.querySelector('#filtro-columna')?.focus(), 50);
		}
	});
}

window.saveDateRangeFilters = saveDateRangeFilters;
function saveDateRangeFilters() {
    dateRangeFilters.fechaInicio.desde = document.getElementById('fecha-inicio-desde')?.value || null;
    dateRangeFilters.fechaInicio.hasta = document.getElementById('fecha-inicio-hasta')?.value || null;
    dateRangeFilters.fechaFinal.desde = document.getElementById('fecha-final-desde')?.value || null;
    dateRangeFilters.fechaFinal.hasta = document.getElementById('fecha-final-hasta')?.value || null;
    lastFilterState = null;
}

window.closeProgramaTejidoFilterModal = closeProgramaTejidoFilterModal;
function closeProgramaTejidoFilterModal() {
    if (typeof window.notify !== 'undefined') {
        window.notify.close();
    }
}

// ===== Filtros personalizados =====
function renderActiveFilters() {
    return filters
        .map((f, i) => {
            const colLabel = columnsData.find(c => c.field === f.column)?.label || f.column;
            // ⚡ FIX: Escapar correctamente el valor para HTML sin convertir espacios en barras invertidas
            const escapedValue = String(f.value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

            return `
                <div class="inline-flex items-center gap-1.5 pl-2 pr-1 py-0.5 bg-blue-50 rounded-full text-[11px] text-blue-800">
                    <span class="font-medium">${colLabel}:</span>
                    <span class="text-blue-600">${escapedValue}</span>
                    <button onclick="removeFilter(${i})"
                            class="flex h-4 w-4 items-center justify-center rounded-full hover:bg-blue-100 text-blue-500 transition-colors">
                        <i class="fa-solid fa-xmark text-[9px]"></i>
                    </button>
                </div>
            `;
        })
        .join('');
}

window.addCustomFilter = addCustomFilter;
function addCustomFilter() {
    const colSelect = document.getElementById('filtro-columna');
    const valEl = document.getElementById('filtro-valor');

    const column = colSelect?.value;
    const operator = 'contains';
    // ⚡ FIX: Guardar el valor tal cual, sin procesamiento adicional que pueda convertir espacios
    const value = valEl?.value?.trim() || '';

    if (!column) {
        showToast('Selecciona una columna', 'warning');
        colSelect?.focus();
        return;
    }

    if (!value) {
        showToast('Ingresa un valor a buscar', 'warning');
        valEl?.focus();
        return;
    }

    // ⚡ FIX: Comparar valores normalizados para evitar duplicados
    const normalizedValue = value.toLowerCase().trim();
    if (filters.some(f => f.column === column && String(f.value || '').toLowerCase().trim() === normalizedValue)) {
        showToast('Este filtro ya existe', 'warning');
        return;
    }

    // ⚡ FIX: Guardar el valor original (con espacios) tal cual se ingresó
    filters.push({ column, operator, value });
    lastFilterState = null;

    const section = document.getElementById('activeFiltersSection');
    const list = document.getElementById('activeFiltersList');
    if (section && list) {
        section.classList.remove('hidden');
        list.innerHTML = renderActiveFilters();
        const counter = section.querySelector('span.bg-blue-100');
        if (counter) counter.textContent = filters.length;
    }

    if (valEl.tagName === 'SELECT') {
        valEl.selectedIndex = 0;
	} else {
        valEl.value = '';
    }
    colSelect.value = '';
    valEl?.focus();

    // Aplicar filtros automáticamente
    applyProgramaTejidoFilters();
}

window.removeFilter = removeFilter;
function removeFilter(index) {
    filters.splice(index, 1);
    lastFilterState = null;

    const section = document.getElementById('activeFiltersSection');
    const list = document.getElementById('activeFiltersList');
    if (section && list) {
        if (filters.length === 0) {
            section.classList.add('hidden');
			} else {
            list.innerHTML = renderActiveFilters();
            const counter = section.querySelector('span.bg-blue-100');
            if (counter) counter.textContent = filters.length;
        }
    }

    applyProgramaTejidoFilters();
}

// Limpiar filtros sin cerrar el modal
window.resetAllFiltersInModal = resetAllFiltersInModal;
function resetAllFiltersInModal(modalEl) {
    // Limpiar arrays
    filters = [];
    quickFilters = {
        ultimos: false,
        divididos: false,
        enProceso: false,
        salonJacquard: false,
        salonSmit: false,
        conCambioHilo: false,
    };
    dateRangeFilters = {
        fechaInicio: { desde: null, hasta: null },
        fechaFinal: { desde: null, hasta: null },
    };
    lastFilterState = null;

    // Actualizar UI de quick filters
    if (modalEl) {
        modalEl.querySelectorAll('[data-quick-filter]').forEach(btn => {
            const key = btn.dataset.quickFilter;
            updateQuickFilterButton(btn, key);
        });

        // Limpiar inputs de fecha
        modalEl.querySelectorAll('input[type="date"]').forEach(input => {
            input.value = '';
        });

        // Limpiar inputs de texto
        const colSelect = modalEl.querySelector('#filtro-columna');
        const valInput = modalEl.querySelector('#filtro-valor');
        if (colSelect) colSelect.value = '';
        if (valInput) valInput.value = '';
    }

    // Ocultar sección de filtros activos
    const section = document.getElementById('activeFiltersSection');
    if (section) section.classList.add('hidden');

    // Aplicar (mostrar todas las filas)
    applyProgramaTejidoFilters();
    showToast('Filtros limpiados', 'info');
}

window.applyAndCloseProgramaTejidoFilterModal = applyAndCloseProgramaTejidoFilterModal;
function applyAndCloseProgramaTejidoFilterModal() {
    applyProgramaTejidoFilters();
    closeProgramaTejidoFilterModal();
}

// ===== Reset de filtros (Programa Tejido) =====
window.resetAllFilters = resetAllFilters;
function resetAllFilters() {
    // Limpiar arrays
    filters = [];
    quickFilters = {
        ultimos: false,
        divididos: false,
        enProceso: false,
        salonJacquard: false,
        salonSmit: false,
        conCambioHilo: false,
    };
    dateRangeFilters = {
        fechaInicio: { desde: null, hasta: null },
        fechaFinal: { desde: null, hasta: null },
    };
    lastFilterState = null;

    // Mostrar todas las filas
    const tb = tbodyEl();
    if (tb) {
        const rows = tb.querySelectorAll('.selectable-row');
        rows.forEach((row, i) => {
            row.style.display = '';
            row.classList.remove('filter-hidden');
        });
    }

    // Actualizar UI
    updateFilterUI();

    // Cerrar modal si está abierto
    closeProgramaTejidoFilterModal();
    showToast('Filtros restablecidos', 'success');
}

// ===== Badge de filtros en navbar =====
window.updateFilterUI = updateFilterUI;
function updateFilterUI() {
    const badge = document.getElementById('filterCount');
    if (!badge) return;

    const hasDateFilters = Object.values(dateRangeFilters).some(d => d.desde || d.hasta);
    const totalFilters = filters.length +
                        Object.values(quickFilters).filter(v => v).length +
                        (hasDateFilters ? 1 : 0);

    if (totalFilters > 0) {
        badge.textContent = totalFilters;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

// ===== Exponer funciones globalmente =====
window.openProgramaTejidoFilterModal = openProgramaTejidoFilterModal;
window.closeProgramaTejidoFilterModal = closeProgramaTejidoFilterModal;
window.toggleQuickFilter = toggleQuickFilter;
window.addCustomFilter = addCustomFilter;
window.removeFilter = removeFilter;
window.resetAllFilters = resetAllFilters;
window.resetAllFiltersInModal = resetAllFiltersInModal;
window.resetFilters = resetAllFilters;
window.applyProgramaTejidoFilters = applyProgramaTejidoFilters;
window.applyAndCloseProgramaTejidoFilterModal = applyAndCloseProgramaTejidoFilterModal;
window.saveDateRangeFilters = saveDateRangeFilters;

// El boton #btnFilters vive en components/navbar/sections/programa-tejido.blade.php y no
// trae onclick. Antes lo enganchaba app-filters.js; al desconectarlo, el modulo engancha
// el suyo aqui, que es donde vive openProgramaTejidoFilterModal.
document.addEventListener('DOMContentLoaded', function () {
    const btnFilters = document.getElementById('btnFilters');
    if (!btnFilters) return;

    btnFilters.addEventListener('click', function (e) {
        e.preventDefault();
        openProgramaTejidoFilterModal();
    });
});

  const columnGroups = {
	1: {
		name: 'Grupo 1',
		fields: [
			'CuentaRizo','CalibreRizo2','SalonTejidoId','NoTelarId','Ultimo','CambioHilo',
			'CalendarioId','NoExisteBase','ItemId','InventSizeId','Rasurado'
		],
		defaultVisible: true
	},
	2: {
		name: 'Grupo 2',
		fields: [
			'PasadasTrama','PasadasComb1','PasadasComb2','PasadasComb3','PasadasComb4','PasadasComb5',
			'AnchoToalla','CodColorTrama','ColorTrama',
			'CalibreComb1','FibraComb1','CodColorComb1','NombreCC1',
			'CalibreComb2','FibraComb2','CodColorComb2','NombreCC2',
			'CalibreComb3','FibraComb3','CodColorComb3','NombreCC3',
			'CalibreComb4','FibraComb4','CodColorComb4','NombreCC4',
			'CalibreComb5','FibraComb5','CodColorComb5','NombreCC5',
			'MedidaPlano','CuentaPie','CodColorCtaPie','NombreCPie','PesoGRM2'
		],
		defaultVisible: true
	},
	3: {
		name: 'Grupo 3',
		fields: ['ProdKgDia','StdToaHra','DiasJornada','HorasProd','StdHrsEfect'],
		defaultVisible: true
	}
};
const REQUIRED_PINNED_FIELDS = ['Ultimo', 'CambioHilo', 'Maquina', 'Ancho', 'NombreProducto'];
// ===== Cache de columnas para lookups rapidos =====
const columnCache = {
	fieldToIndex: null,
	fieldToGroup: null,
	groupToIndices: null,
	columnCount: 0
};
let columnIndicesCache = null;
let hiddenColumnsSet = new Set();
let pinnedColumnsSet = new Set();
let defaultPinsApplied = false;
function buildFieldToGroupMap() {
	columnCache.fieldToGroup = new Map();
	Object.entries(columnGroups).forEach(([groupId, group]) => {
		group.fields.forEach(field => {
			columnCache.fieldToGroup.set(field, parseInt(groupId));
		});
	});
}
function ensureColumnCache() {
	if (!columnCache.fieldToGroup || columnCache.fieldToGroup.size === 0) {
		buildFieldToGroupMap();
	}
	if (!Array.isArray(columnsData) || columnsData.length === 0) return false;
	if (columnCache.fieldToIndex && columnCache.columnCount === columnsData.length) return true;
	columnCache.fieldToIndex = new Map();
	columnsData.forEach((col, idx) => {
		if (col?.field) columnCache.fieldToIndex.set(col.field, idx);
	});
	columnCache.columnCount = columnsData.length;
	columnIndicesCache = columnsData.map((_, idx) => idx);
	columnCache.groupToIndices = new Map();
	Object.entries(columnGroups).forEach(([groupId, group]) => {
		const indices = [];
		group.fields.forEach(field => {
			const idx = columnCache.fieldToIndex.get(field);
			if (idx !== undefined) indices.push(idx);
		});
		columnCache.groupToIndices.set(parseInt(groupId), indices);
	});
	return true;
}
function syncColumnStateSets(force = false) {
	if (!Array.isArray(hiddenColumns)) hiddenColumns = [];
	if (!Array.isArray(pinnedColumns)) pinnedColumns = [];
	if (force || hiddenColumnsSet.size !== hiddenColumns.length) {
		hiddenColumnsSet = new Set(hiddenColumns);
	}
	if (force || pinnedColumnsSet.size !== pinnedColumns.length) {
		pinnedColumnsSet = new Set(pinnedColumns);
	}
}
const columnElementsCache = new Map();
let columnElementsCacheRowCount = 0;
function getColumnElements(index) {
	const table = document.getElementById('mainTable');
	if (!table) {
		columnElementsCache.clear();
		columnElementsCacheRowCount = 0;
		return [];
	}
	const rowCount = table.rows.length;
	if (rowCount !== columnElementsCacheRowCount) {
		columnElementsCache.clear();
		columnElementsCacheRowCount = rowCount;
	}
	let cached = columnElementsCache.get(index);
	if (cached && cached.length > 0 && cached[0].isConnected) {
		const valid = cached.every(el => el.closest('table') === table);
		if (valid) return cached;
	}
	cached = Array.from(table.getElementsByClassName(`column-${index}`));
	columnElementsCache.set(index, cached);
	return cached;
}
function forEachColumnElement(index, cb) {
	const elements = getColumnElements(index);
	for (let i = 0; i < elements.length; i++) cb(elements[i]);
}
function ensureColumnsReady() {
	if (!Array.isArray(columnsData) || columnsData.length === 0) {
		if (typeof showToast === 'function') {
			showToast('Las columnas aun no estan disponibles. Por favor, espera un momento e intenta de nuevo.', 'warning');
		} else {
			alert('Las columnas aun no estan disponibles. Por favor, espera un momento e intenta de nuevo.');
		}
		return false;
	}
	ensureColumnCache();
	syncColumnStateSets();
	return true;
}
function buildGroupedColumns() {
	const groupedColumns = {};
	const ungroupedColumns = [];
	columnsData.forEach((col, realIndex) => {
		if (!col || !col.field) return;
		const groupId = getColumnGroup(col.field);
		const colData = {
			label: col.label || col.field || '',
			field: col.field
		};
		if (groupId) {
			if (!groupedColumns[groupId]) {
				groupedColumns[groupId] = {
					group: columnGroups[groupId],
					columns: []
				};
			}
			groupedColumns[groupId].columns.push({ col: colData, index: realIndex });
		} else {
			ungroupedColumns.push({ col: colData, index: realIndex });
		}
	});
	return { groupedColumns, ungroupedColumns };
}
// ===== Persistencia de columnas ocultas (usa hiddenColumns global de state.blade) =====
const COLUMN_STATE_ENDPOINT = '/programa-tejido/columnas';
const CURRENT_USER_ID = (window?.App?.user?.id) ?? (window?.authUserId) ?? null;
const COLUMN_STATE_CACHE_KEY = 'pt_hidden_columns_' + (CURRENT_USER_ID || 'guest');
const PINNED_COLUMNS_CACHE_KEY = 'pt_pinned_columns_' + (CURRENT_USER_ID || 'guest');

function getCachedHiddenFields() {
	try {
		const raw = localStorage.getItem(COLUMN_STATE_CACHE_KEY);
		if (!raw) return null;
		const data = JSON.parse(raw);
		return Array.isArray(data) ? data : null;
	} catch (e) {
		return null;
	}
}

function setCachedHiddenFields(fields) {
	try {
		localStorage.setItem(COLUMN_STATE_CACHE_KEY, JSON.stringify(fields || []));
	} catch (e) {
		// ignore cache errors
	}
}

function getCachedPinnedFields() {
	try {
		const raw = localStorage.getItem(PINNED_COLUMNS_CACHE_KEY);
		if (!raw) return null;
		const data = JSON.parse(raw);
		return Array.isArray(data) ? data : null;
	} catch (e) {
		return null;
	}
}

function setCachedPinnedFields(fields) {
	try {
		localStorage.setItem(PINNED_COLUMNS_CACHE_KEY, JSON.stringify(fields || []));
	} catch (e) {
		// ignore cache errors
	}
}

let saveHiddenColumnsTimer = null;
let isInitializingColumns = false; // evita guardar mientras cargamos estados
let pendingHiddenFields = null; // campos que deben ocultarse cuando columnsData estÃ© listo
let initialColumnsApplyScheduled = false;
let initialColumnsApplyAttempts = 0;
const MAX_INITIAL_COLUMNS_APPLY_ATTEMPTS = 30;
const SSR_HIDDEN_FIELDS = PT_BOOT.hiddenFields || [];
window.loadPersistedHiddenColumns = loadPersistedHiddenColumns;
async function loadPersistedHiddenColumns() {
	// El HTML ya salio con display:none en las columnas ocultas (lo resuelve
	// ProgramaTejidoController), asi que aqui solo se sincroniza el estado:
	// ni GET, ni escrituras en el DOM, ni salto de layout.
	if (Array.isArray(SSR_HIDDEN_FIELDS) && SSR_HIDDEN_FIELDS.length > 0) {
		ensureColumnCache();
		syncColumnStateSets();
		SSR_HIDDEN_FIELDS.forEach(field => {
			const idx = getColumnIndexByField(field);
			if (idx !== -1 && !hiddenColumnsSet.has(idx)) {
				hiddenColumnsSet.add(idx);
				hiddenColumns.push(idx);
			}
		});
		setCachedHiddenFields(SSR_HIDDEN_FIELDS.slice());
		applyDefaultPinsOnce();
		updatePinnedColumnsPositions();
		return;
	}

	// ⚡ OPTIMIZACIÓN: Aplicar caché inmediatamente sin esperar al servidor
	const cachedFields = getCachedHiddenFields();
	const hasCached = Array.isArray(cachedFields);
	if (hasCached) {
		pendingHiddenFields = cachedFields.slice();
		// Aplicar inmediatamente desde caché
		tryApplyHiddenFields();
	}

	// ⚡ OPTIMIZACIÓN: Cargar desde servidor en segundo plano sin bloquear
	// No esperar a que termine para aplicar el estado
	try {
		isInitializingColumns = true;
		const res = await fetch(COLUMN_STATE_ENDPOINT, {
			method: 'GET',
			headers: { 'Accept': 'application/json' },
			credentials: 'same-origin'
		});
		if (res.ok) {
			const data = await res.json();
			if (data.success && data.data) {
				// Guardar pendientes y aplicar cuando columnsData este listo
				const hiddenFields = Object.entries(data.data || {})
					.filter(([, hidden]) => hidden)
					.map(([field]) => field);
				setCachedHiddenFields(hiddenFields);
				// Solo actualizar si hay diferencias con el caché
				if (!hasCached || JSON.stringify(cachedFields.sort()) !== JSON.stringify(hiddenFields.sort())) {
					pendingHiddenFields = hiddenFields;
					tryApplyHiddenFields();
				}
			}
		}
	} catch (e) {
		console.warn('No se pudo cargar estado de columnas desde servidor', e);
	} finally {
		isInitializingColumns = false;

		// ⚡ OPTIMIZACIÓN: Si no había estados guardados, inicializar con defaults
		// Esto asegura que siempre haya una inicialización, pero solo si no hay estados guardados
		if (!hasCached && typeof window.initializeColumnVisibility === 'function') {
			// Esperar un momento para que columnsData esté listo
			setTimeout(() => {
				window.initializeColumnVisibility();
			}, 50);
		}
	}

	// Si no había caché, intentar aplicar de todas formas
	if (!hasCached) {
		tryApplyHiddenFields();
	}
}
function scheduleSaveHiddenColumns() {
	if (isInitializingColumns) return;
	if (saveHiddenColumnsTimer) clearTimeout(saveHiddenColumnsTimer);
	saveHiddenColumnsTimer = setTimeout(saveHiddenColumns, 400);
}
async function saveHiddenColumns() {
	try {
		syncColumnStateSets();
		const columnas = {};
		// Enviar estado completo: true = oculta, false = visible
		const hiddenFields = [];
		columnsData.forEach((col, idx) => {
			if (!col?.field) return;
			const hidden = hiddenColumnsSet.has(idx);
			columnas[col.field] = hidden;
			if (hidden) hiddenFields.push(col.field);
		});
		setCachedHiddenFields(hiddenFields);
		const body = { columnas };
		if (CURRENT_USER_ID) body.usuario_id = CURRENT_USER_ID;
		const res = await fetch(COLUMN_STATE_ENDPOINT, {
			method: 'POST',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
			},
			credentials: 'same-origin',
			body: JSON.stringify(body)
		});
		if (!res.ok) {
			console.warn('No se pudo guardar estado de columnas (respuesta no OK)', res.status);
		}
	} catch (e) {
		console.warn('No se pudo guardar estado de columnas', e);
	}
}
// FunciÃ³n para obtener el Ã­ndice de una columna por su campo
function getColumnIndexByField(field) {
	if (ensureColumnCache()) {
		const idx = columnCache.fieldToIndex.get(field);
		return idx === undefined ? -1 : idx;
	}
	return columnsData.findIndex(col => col.field === field);
}
function isRequiredPinnedField(field) {
	return REQUIRED_PINNED_FIELDS.includes(field);
}
function isRequiredPinnedIndex(index) {
	const field = columnsData[index]?.field;
	return field ? isRequiredPinnedField(field) : false;
}
// FunciÃ³n para obtener el grupo de una columna
window.getColumnGroup = getColumnGroup;
function getColumnGroup(field) {
	if (!columnCache.fieldToGroup || columnCache.fieldToGroup.size === 0) {
		buildFieldToGroupMap();
	}
	return columnCache.fieldToGroup.get(field) || null;
}
// FunciÃ³n para obtener todas las columnas de un grupo
window.getGroupColumns = getGroupColumns;
function getGroupColumns(groupId) {
	if (ensureColumnCache() && columnCache.groupToIndices) {
		return columnCache.groupToIndices.get(groupId) || [];
	}
	const group = columnGroups[groupId];
	if (!group) return [];
	return group.fields.map(field => getColumnIndexByField(field)).filter(idx => idx !== -1);
}
function tryApplyHiddenFields() {
	if (initialColumnsApplyScheduled) return;
	initialColumnsApplyScheduled = true;
	// Aplicar inmediatamente si columnsData está disponible, sino usar requestAnimationFrame
	if (Array.isArray(columnsData) && columnsData.length > 0) {
		applyInitialColumnState();
	} else {
		requestAnimationFrame(applyInitialColumnState);
	}
}
function applyInitialColumnState() {
	initialColumnsApplyScheduled = false;
	try {
		if (!Array.isArray(columnsData) || columnsData.length === 0) {
			if (initialColumnsApplyAttempts < MAX_INITIAL_COLUMNS_APPLY_ATTEMPTS) {
				initialColumnsApplyAttempts += 1;
				// Usar setTimeout con delay mínimo en lugar de requestAnimationFrame para reintentos
				setTimeout(tryApplyHiddenFields, 10);
				return;
			}
			return;
		}
		ensureColumnCache();
		syncColumnStateSets();

		// ⚡ OPTIMIZACIÓN: Aplicar estados de oculto de forma más eficiente
		if (pendingHiddenFields && pendingHiddenFields.length > 0) {
			// Batch DOM updates: aplicar todos los cambios de una vez
			const indicesToHide = [];
			pendingHiddenFields.forEach(field => {
				const idx = getColumnIndexByField(field);
				if (idx !== -1 && !hiddenColumnsSet.has(idx)) {
					indicesToHide.push(idx);
					hiddenColumnsSet.add(idx);
					hiddenColumns.push(idx);
				}
			});
			// Aplicar todos los cambios de visibilidad de una vez
			indicesToHide.forEach(idx => {
				forEachColumnElement(idx, el => {
					el.style.display = 'none';
				});
			});
		}

		// ⚡ OPTIMIZACIÓN: Aplicar columnas fijadas inmediatamente
		applyDefaultPinsOnce();

		// ⚡ OPTIMIZACIÓN: Actualizar posiciones de columnas fijadas inmediatamente
		updatePinnedColumnsPositions();

		pendingHiddenFields = null;
		initialColumnsApplyAttempts = 0;
	} catch (e) {
		console.warn('No se pudo aplicar columnas ocultas', e);
	}
}

// FunciÃ³n para mostrar/ocultar un grupo completo
window.toggleGroupVisibility = toggleGroupVisibility;
function toggleGroupVisibility(groupId, visible, silent = false) {
	const groupColumns = getGroupColumns(groupId);
	if (groupColumns.length === 0) return;
	groupColumns.forEach(index => {
		if (visible) showColumn(index, true);
		else hideColumn(index, true);
	});
	if (!silent) {
		if (typeof showToast === 'function') {
			showToast(visible ? 'Grupo visible' : 'Grupo oculto', 'info');
		}
		scheduleSaveHiddenColumns();
	}
}
// FunciÃ³n para fijar/desfijar un grupo completo
window.toggleGroupPin = toggleGroupPin;
function toggleGroupPin(groupId, pin) {
	const groupColumns = getGroupColumns(groupId);
	if (groupColumns.length === 0) return;
	syncColumnStateSets();
	let changed = false;
	if (pin) {
		groupColumns.forEach(index => {
			if (!pinnedColumnsSet.has(index)) {
				pinnedColumnsSet.add(index);
				pinnedColumns.push(index);
				changed = true;
			}
		});
		if (changed) pinnedColumns.sort((a, b) => a - b);
	} else {
		groupColumns.forEach(index => {
			if (pinnedColumnsSet.has(index) && !isRequiredPinnedIndex(index)) {
				pinnedColumnsSet.delete(index);
				changed = true;
			}
		});
		if (changed) pinnedColumns = pinnedColumns.filter(i => pinnedColumnsSet.has(i));
	}
	if (changed) {
		// ⚡ OPTIMIZACIÓN: Guardar en caché cuando se cambia un grupo
		if (ensureColumnCache()) {
			const fields = [];
			groupColumns.forEach(index => {
				if (pin && pinnedColumnsSet.has(index)) {
					const field = columnsData[index]?.field;
					if (field) fields.push(field);
				}
			});
			if (pin) {
				const cached = getCachedPinnedFields() || [];
				fields.forEach(field => {
					if (!cached.includes(field)) cached.push(field);
				});
				setCachedPinnedFields(cached);
			} else {
				const cached = getCachedPinnedFields() || [];
				const filtered = cached.filter(f => !fields.includes(f) || isRequiredPinnedField(f));
				setCachedPinnedFields(filtered);
			}
		}
		updatePinnedColumnsPositions();
	}
}
// ===== Controles de columnas desde navbar =====
const COLUMN_MODAL_CONFIG = {
	pin: {
		title: 'Fijar Columnas',
		description: 'Selecciona las columnas o grupos que deseas fijar a la izquierda de la tabla:',
		groupLabel: 'Fijar grupo',
		columnClass: 'column-toggle-pin',
		groupClass: 'group-toggle-pin',
		checkboxClass: 'text-yellow-700',
		focusClass: 'focus:ring-yellow-600',
		confirmColor: '#d97706',
		columnAction: (index, checked) => (checked ? pinColumn(index) : unpinColumn(index)),
		groupAction: (groupId, checked) => toggleGroupPin(groupId, checked)
	},
	hide: {
		title: 'Ocultar Columnas',
		description: 'Selecciona las columnas o grupos que deseas ocultar:',
		groupLabel: 'Ocultar grupo',
		columnClass: 'column-toggle-hide',
		groupClass: 'group-toggle-hide',
		checkboxClass: 'text-red-600',
		focusClass: 'focus:ring-red-500',
		confirmColor: '#ef4444',
		columnAction: (index, checked) => (checked ? hideColumn(index) : showColumn(index)),
		groupAction: (groupId, checked) => toggleGroupVisibility(groupId, !checked)
	}
};
function getModalStateSet(mode) {
	syncColumnStateSets();
	return mode === 'pin' ? pinnedColumnsSet : hiddenColumnsSet;
}
function buildColumnsModalHtml({ mode, groupedColumns, ungroupedColumns }) {
	const config = COLUMN_MODAL_CONFIG[mode];
	const set = getModalStateSet(mode);
	let html = `
		<div class="text-left">
			<p class="text-sm text-gray-600 mb-4">${config.description}</p>
			<div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-4">
	`;
	Object.keys(groupedColumns).sort().forEach(groupId => {
		const groupData = groupedColumns[groupId];
		const groupColumns = getGroupColumns(parseInt(groupId, 10));
		const allChecked = groupColumns.length > 0 && groupColumns.every(idx => set.has(idx) || (mode === 'pin' && isRequiredPinnedIndex(idx)));
		const someChecked = groupColumns.some(idx => set.has(idx) || (mode === 'pin' && isRequiredPinnedIndex(idx)));
		const indeterminateAttr = someChecked && !allChecked ? 'data-indeterminate="1"' : '';
		html += `
			<div class="border border-gray-200 rounded-lg p-2">
				<div class="flex items-center justify-between mb-2 pb-2 border-b border-gray-200">
					<span class="text-sm font-semibold text-gray-800">${groupData.group.name}</span>
					<label class="flex items-center gap-2 cursor-pointer">
						<input type="checkbox" ${allChecked ? 'checked' : ''}
							   class="w-4 h-4 ${config.checkboxClass} bg-gray-100 border-gray-300 rounded ${config.focusClass} ${config.groupClass}"
							   data-group-id="${groupId}" ${indeterminateAttr}>
						<span class="text-xs text-gray-600">${config.groupLabel}</span>
					</label>
				</div>
				<div class="pl-4 space-y-1">
		`;
		groupData.columns.forEach(({ col, index }) => {
			const isRequired = mode === 'pin' && isRequiredPinnedField(col.field);
			const isChecked = isRequired || set.has(index);
			const disabledAttr = isRequired ? 'disabled' : '';
			html += `
				<div class="flex items-center justify-between p-1 hover:bg-gray-50 rounded">
					<span class="text-xs text-gray-700 ml-2">${col.label}</span>
					<input type="checkbox" ${isChecked ? 'checked' : ''}
						   class="w-4 h-4 ${config.checkboxClass} bg-gray-100 border-gray-300 rounded ${config.focusClass} ${config.columnClass}"
						   data-column-index="${index}"
						   data-group-id="${groupId}" ${disabledAttr}>
				</div>
			`;
		});
		html += `
				</div>
			</div>
		`;
	});
	if (ungroupedColumns.length > 0) {
		html += `
			<div class="border border-gray-200 rounded-lg p-2">
				<div class="text-sm font-semibold text-gray-800 mb-2 pb-2 border-b border-gray-200">Otras columnas</div>
				<div class="pl-4 space-y-1">
		`;
		ungroupedColumns.forEach(({ col, index }) => {
			const isRequired = mode === 'pin' && isRequiredPinnedField(col.field);
			const isChecked = isRequired || set.has(index);
			const disabledAttr = isRequired ? 'disabled' : '';
			html += `
				<div class="flex items-center justify-between p-1 hover:bg-gray-50 rounded">
					<span class="text-xs text-gray-700 ml-2">${col.label}</span>
					<input type="checkbox" ${isChecked ? 'checked' : ''}
						   class="w-4 h-4 ${config.checkboxClass} bg-gray-100 border-gray-300 rounded ${config.focusClass} ${config.columnClass}"
						   data-column-index="${index}" ${disabledAttr}>
				</div>
			`;
		});
		html += `
				</div>
			</div>
		`;
	}
	html += `
			</div>
		</div>
	`;
	return html;
}
function bindColumnsModalEvents(mode, container) {
	const config = COLUMN_MODAL_CONFIG[mode];
	if (!config || !container) return;
	container.querySelectorAll(`.${config.groupClass}[data-indeterminate="1"]`).forEach(cb => {
		cb.indeterminate = true;
	});
	container.querySelectorAll(`.${config.columnClass}`).forEach(checkbox => {
		checkbox.addEventListener('change', function() {
			const columnIndex = parseInt(this.dataset.columnIndex, 10);
			if (Number.isNaN(columnIndex)) return;
			config.columnAction(columnIndex, this.checked);
			const groupId = this.dataset.groupId;
			if (groupId) updateGroupCheckboxState(parseInt(groupId, 10), mode);
		});
	});
	container.querySelectorAll(`.${config.groupClass}`).forEach(checkbox => {
		checkbox.addEventListener('change', function() {
			const groupId = parseInt(this.dataset.groupId, 10);
			if (Number.isNaN(groupId)) return;
			config.groupAction(groupId, this.checked);
			this.indeterminate = false;
			container.querySelectorAll(`.${config.columnClass}[data-group-id="${groupId}"]`).forEach(cb => {
				cb.checked = this.checked;
			});
		});
	});
}
function openColumnsModal(mode) {
	if (!ensureColumnsReady()) return;
	if (!COLUMN_MODAL_CONFIG[mode]) return;
	const { groupedColumns, ungroupedColumns } = buildGroupedColumns();
	const html = buildColumnsModalHtml({ mode, groupedColumns, ungroupedColumns });
	const config = COLUMN_MODAL_CONFIG[mode];
	window.notify.form({
		title: config.title,
		html: html,
		confirmText: 'Aplicar',
		cancelText: 'Cancelar',
		danger: mode === 'hide',
		width: 'lg',
		didOpen: (root) => bindColumnsModalEvents(mode, root)
	});
}
window.openPinColumnsModal = openPinColumnsModal;
function openPinColumnsModal() {
	openColumnsModal('pin');
}
window.openHideColumnsModal = openHideColumnsModal;
function openHideColumnsModal() {
	openColumnsModal('hide');
}
function updateGroupCheckboxState(groupId, type) {
	const groupColumns = getGroupColumns(groupId);
	const prefix = type === 'pin' ? 'pin' : 'hide';
	syncColumnStateSets();
	const set = type === 'pin' ? pinnedColumnsSet : hiddenColumnsSet;
	const allChecked = groupColumns.every(idx => set.has(idx) || (type === 'pin' && isRequiredPinnedIndex(idx)));
	const someChecked = groupColumns.some(idx => set.has(idx) || (type === 'pin' && isRequiredPinnedIndex(idx)));
	const groupCheckbox = document.querySelector(`.group-toggle-${prefix}[data-group-id="${groupId}"]`);
	if (groupCheckbox) {
		groupCheckbox.checked = allChecked;
		groupCheckbox.indeterminate = someChecked && !allChecked;
	}
}
function getColumnsData() {
	if (!Array.isArray(columnsData) || columnsData.length === 0) {
		console.warn('getColumnsData: columnsData no estÃ¡ disponible o estÃ¡ vacÃ­o');
		return [];
	}
	return columnsData.map(c => ({
		label: c.label || c.field || '',
		field: c.field || ''
	})).filter(c => c.field); // Filtrar columnas sin field
}
window.getPinnedColumns = getPinnedColumns;
function getPinnedColumns() {
	syncColumnStateSets();
	return pinnedColumns || [];
}
window.pinColumn = pinColumn;
function pinColumn(index) {
	syncColumnStateSets();
	if (!pinnedColumnsSet.has(index)) {
		pinnedColumnsSet.add(index);
		pinnedColumns.push(index);
		pinnedColumns.sort((a, b) => a - b);

		// ⚡ OPTIMIZACIÓN: Guardar en caché inmediatamente
		if (ensureColumnCache()) {
			const field = columnsData[index]?.field;
			if (field) {
				const cached = getCachedPinnedFields() || [];
				if (!cached.includes(field)) {
					cached.push(field);
					setCachedPinnedFields(cached);
				}
			}
		}

		updatePinnedColumnsPositions();
	}
}
window.unpinColumn = unpinColumn;
function unpinColumn(index) {
	syncColumnStateSets();
	// Permitir desfijar incluso columnas requeridas
	if (pinnedColumnsSet.has(index)) {
		pinnedColumnsSet.delete(index);
		const idx = pinnedColumns.indexOf(index);
		if (idx > -1) pinnedColumns.splice(idx, 1);

		// ⚡ OPTIMIZACIÓN: Guardar en caché inmediatamente
		if (ensureColumnCache()) {
			const field = columnsData[index]?.field;
			if (field) {
				const cached = getCachedPinnedFields() || [];
				const filtered = cached.filter(f => f !== field);
				setCachedPinnedFields(filtered);
			}
		}

		updatePinnedColumnsPositions();
	}
}
window.resetColumnVisibility = resetColumnVisibility;
function resetColumnVisibility() {
	try {
		const table = document.getElementById('mainTable');
		if (!table) {
			console.warn('resetColumnVisibility: tabla no encontrada');
			return;
		}
		// Limpiar array de columnas ocultas
		hiddenColumns = [];
		hiddenColumnsSet.clear();
		// Aplicar visibilidad segÃºn grupos por defecto
		Object.keys(columnGroups).forEach(groupId => {
			const group = columnGroups[groupId];
			const groupIdNum = parseInt(groupId);
			toggleGroupVisibility(groupIdNum, group.defaultVisible, true);
		});
		// Mostrar columnas sin grupo (por defecto visibles)
		columnsData.forEach((col, index) => {
			const groupId = getColumnGroup(col.field);
			if (!groupId) {
				showColumn(index, true);
			}
		});
		// Desfijar todas las columnas
		pinnedColumns = [];
		pinnedColumnsSet.clear();
		// Fijar columnas por defecto
		pinDefaultColumns();
		// Actualizar posiciones
		updatePinnedColumnsPositions();
		// Mostrar notificaciÃ³n
		if (typeof showToast === 'function') {
			showToast('Columnas restablecidas a valores por defecto', 'success');
		}
	} catch (error) {
		console.error('Error en resetColumnVisibility:', error);
		if (typeof showToast === 'function') {
			showToast('Error al restablecer columnas', 'error');
		}
	}
}
// FunciÃ³n para inicializar la visibilidad de columnas segÃºn grupos
window.initializeColumnVisibility = initializeColumnVisibility;
function initializeColumnVisibility() {
	try {
		isInitializingColumns = true;

		// ⚡ OPTIMIZACIÓN: Verificar si hay estados guardados antes de aplicar defaults
		// Solo aplicar defaults si no hay estados guardados (evita conflictos)
		const cachedFields = getCachedHiddenFields();
		const hasCached = Array.isArray(cachedFields) && cachedFields.length > 0;

		// Si hay estados guardados, no aplicar defaults (ya se aplicaron en loadPersistedHiddenColumns)
		if (hasCached) {
			// Solo asegurar que los estados se apliquen si columnsData está listo
			if (Array.isArray(columnsData) && columnsData.length > 0) {
				tryApplyHiddenFields();
			}
			return;
		}

		// Solo aplicar defaults si no hay estados guardados
		Object.keys(columnGroups).forEach(groupId => {
			const group = columnGroups[groupId];
			const groupIdNum = parseInt(groupId);
			toggleGroupVisibility(groupIdNum, group.defaultVisible, true);
		});
	} catch (error) {
		console.error('Error al inicializar visibilidad de columnas:', error);
	} finally {
		isInitializingColumns = false;
	}
}
window.showColumn = showColumn;
function showColumn(index, silent = false) {
	forEachColumnElement(index, el => {
		el.style.display = '';
		el.style.visibility = '';
	});
	// Remover del array de columnas ocultas
	syncColumnStateSets();
	if (hiddenColumnsSet.has(index)) {
		hiddenColumnsSet.delete(index);
		const idx = hiddenColumns.indexOf(index);
		if (idx > -1) hiddenColumns.splice(idx, 1);
	}
	if (!silent && typeof showToast === 'function') {
		showToast(`Columna visible`, 'info');
	}
	if (!silent) scheduleSaveHiddenColumns();
	if (pinnedColumns.length > 0) updatePinnedColumnsPositions();
}
window.hideColumn = hideColumn;
function hideColumn(index, silent = false) {
	forEachColumnElement(index, el => {
		el.style.display = 'none';
	});
	const hideBtn = $(`th.column-${index} .hide-btn`);
	if (hideBtn) {
		hideBtn.classList.remove('bg-red-500');
		hideBtn.classList.add('bg-red-600');
		hideBtn.title = 'Columna oculta';
	}
	syncColumnStateSets();
	if (!hiddenColumnsSet.has(index)) {
		hiddenColumnsSet.add(index);
		hiddenColumns.push(index);
	}
	if (!silent && typeof showToast === 'function') {
		showToast(`Columna oculta`, 'info');
	}
	if (!silent) scheduleSaveHiddenColumns();
	if (pinnedColumns.length > 0) updatePinnedColumnsPositions();
}
window.togglePinColumn = togglePinColumn;
function togglePinColumn(index) {
	syncColumnStateSets();
	const exists = pinnedColumnsSet.has(index);
	// Permitir desfijar incluso columnas requeridas
	if (exists) {
		pinnedColumnsSet.delete(index);
		const idx = pinnedColumns.indexOf(index);
		if (idx > -1) pinnedColumns.splice(idx, 1);
	} else {
		pinnedColumnsSet.add(index);
		pinnedColumns.push(index);
	}
	pinnedColumns.sort((a, b) => a - b);

	// ⚡ OPTIMIZACIÓN: Guardar en caché inmediatamente
	if (ensureColumnCache()) {
		const field = columnsData[index]?.field;
		if (field) {
			const cached = getCachedPinnedFields() || [];
			if (exists) {
				const filtered = cached.filter(f => f !== field);
				setCachedPinnedFields(filtered);
			} else {
				if (!cached.includes(field)) {
					cached.push(field);
					setCachedPinnedFields(cached);
				}
			}
		}
	}

	// BotÃ³n estado
	const pinBtn = $(`th.column-${index} .pin-btn`);
	if (pinBtn) {
		pinBtn.classList.toggle('bg-yellow-700', !exists);
		pinBtn.classList.toggle('bg-yellow-600', exists);
		pinBtn.title = exists ? 'Fijar columna' : 'Desfijar columna';
	}
	updatePinnedColumnsPositions();
}
let lastPinnedColumnsSet = new Set();
function clearPinnedStyles(index) {
	forEachColumnElement(index, el => {
		// Remover clase pinned-column primero
		el.classList.remove('pinned-column');
		
		// Limpiar TODOS los estilos inline relacionados con pinned
		// Usar removeProperty para asegurar que se eliminen completamente
		el.style.removeProperty('left');
		el.style.removeProperty('position');
		el.style.removeProperty('top');
		el.style.removeProperty('background-color');
		el.style.removeProperty('background');
		el.style.removeProperty('color');
		el.style.removeProperty('z-index');
		el.style.removeProperty('zIndex');
		// Sin `void el.offsetHeight` aqui: era un reflow sincronico por elemento, o sea
		// 86 reflows por columna despinneada. Quitar la clase y los estilos inline ya
		// deja que :not(.pinned-column) gane; el navegador repinta en el frame.
	});
}
function applyPinnedStyles(index, left) {
	forEachColumnElement(index, el => {
		el.classList.add('pinned-column');
		el.style.left = left + 'px';
		if (el.tagName === 'TH') {
			el.style.top = '0';
			el.style.position = 'sticky';
		} else {
			el.style.position = 'sticky';
		}
	});
}
window.updatePinnedColumnsPositions = updatePinnedColumnsPositions;
function updatePinnedColumnsPositions() {
	syncColumnStateSets();
	const currentPinnedSet = new Set(pinnedColumns);
	lastPinnedColumnsSet.forEach(idx => {
		if (!currentPinnedSet.has(idx)) clearPinnedStyles(idx);
	});
	// Asegurar que el thead sea sticky cuando hay columnas fijadas
	const thead = $('thead');
	if (thead && pinnedColumns.length > 0) {
		thead.style.position = 'sticky';
		thead.style.top = '0';
	}
	// Aplica fijados en orden
	let left = 0;
	pinnedColumns.forEach(idx => {
		const th = $(`th.column-${idx}`);
		if (!th || th.style.display === 'none') {
			clearPinnedStyles(idx);
			return;
		}
		const width = th.getBoundingClientRect().width;
		applyPinnedStyles(idx, left);
		left += width;
	});
	lastPinnedColumnsSet = currentPinnedSet;
}

// ===== Exponer funciones globalmente =====
window.resetColumnVisibility = resetColumnVisibility;
window.openPinColumnsModal = openPinColumnsModal;
window.openHideColumnsModal = openHideColumnsModal;
window.pinColumn = pinColumn;
window.unpinColumn = unpinColumn;
window.hideColumn = hideColumn;
window.showColumn = showColumn;
window.togglePinColumn = togglePinColumn;
window.updatePinnedColumnsPositions = updatePinnedColumnsPositions;
window.initializeColumnVisibility = initializeColumnVisibility;
window.toggleGroupVisibility = toggleGroupVisibility;
window.toggleGroupPin = toggleGroupPin;
window.getColumnGroup = getColumnGroup;
window.getGroupColumns = getGroupColumns;
window.pinDefaultColumns = pinDefaultColumns;
window.loadPersistedHiddenColumns = loadPersistedHiddenColumns;
window.applyDefaultPinsOnce = applyDefaultPinsOnce;
// FunciÃ³n para fijar columnas por defecto
window.applyDefaultPinsOnce = applyDefaultPinsOnce;
function applyDefaultPinsOnce() {
	if (defaultPinsApplied) return;
	defaultPinsApplied = true;
	pinDefaultColumns();
}
window.pinDefaultColumns = pinDefaultColumns;
function pinDefaultColumns() {
	ensureColumnCache();
	syncColumnStateSets();

	// ⚡ OPTIMIZACIÓN: Cargar columnas fijadas desde caché primero
	const cachedPinnedFields = getCachedPinnedFields();
	const fieldsToPin = new Set();
	if (cachedPinnedFields && cachedPinnedFields.length > 0) {
		cachedPinnedFields.forEach(field => fieldsToPin.add(field));
	}
	REQUIRED_PINNED_FIELDS.forEach(field => fieldsToPin.add(field));
	fieldsToPin.forEach(field => {
		const index = getColumnIndexByField(field);
		if (index !== -1 && !pinnedColumnsSet.has(index)) {
			pinnedColumnsSet.add(index);
			pinnedColumns.push(index);
		}
	});

	// Ordenar las columnas fijadas
	pinnedColumns.sort((a, b) => a - b);

	// ⚡ OPTIMIZACIÓN: Actualizar posiciones inmediatamente si el DOM está listo
	// Solo usar requestAnimationFrame si es necesario
	if (document.readyState === 'complete' || document.readyState === 'interactive') {
		updatePinnedColumnsPositions();
	} else {
		requestAnimationFrame(updatePinnedColumnsPositions);
	}
}
// ⚡ OPTIMIZACIÓN: loadPersistedHiddenColumns() ahora se llama desde main.blade.php
// después de DOMContentLoaded para evitar conflictos con initializeColumnVisibility()
// No se ejecuta aquí para evitar duplicados

// ===== VISTAS GUARDADAS DE COLUMNAS =====
async function promptSavePreset() {
    await saveHiddenColumns();
    if (typeof showToast === 'function') showToast('Vista de columnas guardada', 'success');
}

async function promptLoadPreset() {
    await loadPersistedHiddenColumns();
    if (typeof showToast === 'function') showToast('Vista de columnas restaurada', 'info');
}

window.PT = window.PT || {};
window.PT.presets = { save: promptSavePreset, load: promptLoadPreset };






  // ===== Seleccion de filas - OPTIMIZADO =====
const getSelectableRows = () => (window.allRows.length > 0 ? window.allRows : $$('.selectable-row'));
const isInlineEditActive = () => !!window.inlineEditMode;

function updateRowSelectionStyles(row, isSelected, inlineActive) {
	const esRepaso = row.dataset.esRepaso === '1';
	row.classList.toggle('bg-blue-700', isSelected && !esRepaso);
	row.classList.toggle('bg-blue-400', isSelected && esRepaso);
	row.classList.toggle('row-selected', isSelected);
	row.classList.toggle('text-white', isSelected);
	row.classList.toggle('hover:bg-blue-50', !isSelected);

	if (!inlineActive && !row.querySelector('.inline-edit-input')) {
		row.classList.remove('bg-yellow-100');
	}
	// El color del texto lo decide main.css desde la clase de la fila
	// (.selectable-row.bg-blue-700 td / tbody td:not(.pinned-column), ambas
	// !important). Togglear text-white por celda eran 92 ops por fila x 85
	// filas por clic sin efecto visual.
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

window.selectRow = selectRow;
function selectRow(rowElement, rowIndex) {
	try {
		// Cancelar cualquier timeout de amarillo temporal
		if (typeof window.yellowHighlightTimeout !== 'undefined' && window.yellowHighlightTimeout) {
			clearTimeout(window.yellowHighlightTimeout);
			window.yellowHighlightTimeout = null;
		}

		// Toggle si ya estaba seleccionada
		if (window.selectedRowIndex === rowIndex && rowElement.classList.contains('bg-blue-700')) {
			return deselectRow();
		}

		// Cerrar edicion inline de la fila anterior si existe y desactivar modo inline
		if (window.selectedRowIndex >= 0 && window.selectedRowIndex !== rowIndex) {
			const rows = getSelectableRows();
			const previousRow = rows[window.selectedRowIndex];
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

		window.selectedRowIndex = rowIndex;

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
		// No se traga: los bugs de seleccion se manifiestan como "hago clic y no
		// pasa nada" y sin esto no dejan rastro en consola.
		console.error('[PT] fallo en la seleccion de fila:', e);
	}
}

window.deselectRow = deselectRow;
function deselectRow() {
	try {
		// Cerrar edicion inline de la fila seleccionada si existe
		if (window.selectedRowIndex >= 0) {
			const rows = getSelectableRows();
			const currentRow = rows[window.selectedRowIndex];
			if (currentRow && typeof window.closeInlineEditForRow === 'function') {
				window.closeInlineEditForRow(currentRow);
			}
		}

		clearSelectionStyles();

		window.selectedRowIndex = -1;

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
		// No se traga: los bugs de seleccion se manifiestan como "hago clic y no
		// pasa nada" y sin esto no dejan rastro en consola.
		console.error('[PT] fallo en la seleccion de fila:', e);
	}
}

// Exponer funciones globalmente
window.selectRow = selectRow;
window.deselectRow = deselectRow;

  // =========================
// Inline Edit - Estado
// =========================
// inlineEditMode vive en window (state.blade.php); catalogosCache tambien se declara alli
// inlineFieldPayloadMap también está en state.blade.php y usa nombres de BD
// uiInlineEditableFields ahora usa directamente los nombres de BD que coinciden con data-column

// Helpers de formateo mejorados
function parseSqlDateTimeLocal(raw) {
  if (!raw) return null;
  const s = String(raw).trim().replace('T', ' ').replace('Z', '');
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/);
  if (m) {
    const Y = +m[1], Mo = +m[2] - 1, D = +m[3];
    const hh = +(m[4] || 0), mm = +(m[5] || 0), ss = +(m[6] || 0);
    const d = new Date(Y, Mo, D, hh, mm, ss, 0);
    return isNaN(d.getTime()) ? null : d;
  }
  const d = new Date(raw);
  return isNaN(d.getTime()) ? null : d;
}

function formatDateOnlyDisplay(raw) {
  const d = parseSqlDateTimeLocal(raw);
  if (!d) return '';
  return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatDateTimeDisplay(raw) {
  const d = parseSqlDateTimeLocal(raw);
  if (!d) return '';
  const date = d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
  const hh = String(d.getHours()).padStart(2, '0');
  const mm = String(d.getMinutes()).padStart(2, '0');
  // Separador de espacio, igual que el servidor (d/m/Y H:i), _shared-helpers y state.
  // Con <br> las filas tocadas por arrastre o edicion inline se veian a dos lineas
  // y el resto a una; ademas checkDateFilters cae a textContent cuando la celda no
  // trae data-value, y ahi el <br> producia "05/01/202614:30", sin separador.
  return `${date} ${hh}:${mm}`;
}

function formatNumber2(raw) {
  if (raw == null || raw === '') return '';
  const n = Number(raw);
  if (!isFinite(n)) return String(raw);
  return n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Mantener formatDateDisplay para compatibilidad con código existente
function formatDateDisplay(isoOrDate) {
  return formatDateOnlyDisplay(isoOrDate);
}

function toInputDate(val) {
  // acepta dd/mm/yyyy, yyyy-mm-dd, dd-mm-yyyy, etc.
  if (!val) return '';
  const s = String(val).trim();

  // si ya viene YYYY-MM-DD
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;

  // dd/mm/yyyy o dd-mm-yyyy
  const m = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
  if (m) {
    const dd = String(m[1]).padStart(2, '0');
    const mm = String(m[2]).padStart(2, '0');
    let yy = String(m[3]);
    if (yy.length === 2) yy = '20' + yy;
    return `${yy}-${mm}-${dd}`;
  }

  // intenta parsear fecha
  const d = new Date(s);
  if (!isNaN(d.getTime())) {
    const yy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yy}-${mm}-${dd}`;
  }

  return '';
}

/**
 * Config de ediciones (usando nombres de campos de BD que coinciden con data-column):
 * - type: text|number|date|select
 * - catalog: hilos|calendarios|aplicaciones (si type=select)
 */
const uiInlineEditableFields = {
  // Usar los nombres de campos de BD que coinciden con data-column
  FibraRizo: {
    type: 'select',
    catalog: 'hilos',
    displayFormatter: (_val, inputEl) => inputEl?.selectedOptions?.[0]?.textContent || ''
  },
  CalendarioId: {
    type: 'select',
    catalog: 'calendarios',
    displayFormatter: (_val, inputEl) => inputEl?.selectedOptions?.[0]?.textContent || ''
  },
  TotalPedido: {
    type: 'number',
    min: 0,
    step: 0.01,
    displayFormatter: (v) => (v == null || v === '') ? '' : Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  },
  FlogsId: {
    type: 'text',
    maxLength: 40
  },
  NombreProyecto: {
    type: 'text',
    maxLength: 150
  },
  AplicacionId: {
    type: 'select',
    catalog: 'aplicaciones',
    toPayload: (v) => (v === '' || v === 'NA') ? null : v,
    displayFormatter: (_val, inputEl) => inputEl?.selectedOptions?.[0]?.textContent || ''
  },
  EntregaProduc: {
    type: 'date',
    inputFormatter: (v) => toInputDate(v),
    toPayload: (v) => v === '' ? null : v,
    displayFormatter: (v) => v ? formatDateDisplay(v) : ''
  },
  TamanoClave: {
    type: 'text',
    maxLength: 40
  },
  Rasurado: {
    type: 'text',
    maxLength: 2
  },
  ProgramarProd: {
    type: 'date',
    inputFormatter: (v) => toInputDate(v),
    toPayload: (v) => v === '' ? null : v,
    displayFormatter: (v) => v ? formatDateDisplay(v) : ''
  },
  NoTiras: {
    type: 'number',
    min: 0,
    step: 1,
    displayFormatter: (v) => (v == null || v === '') ? '' : String(v)
  },
  Peine: {
    type: 'number',
    min: 0,
    step: 1,
    displayFormatter: (v) => (v == null || v === '') ? '' : String(v)
  },
  LargoCrudo: {
    type: 'number',
    min: 0,
    step: 0.01,
    displayFormatter: (v) => (v == null || v === '') ? '' : Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  },
  Luchaje: {
    type: 'number',
    min: 0,
    step: 0.01,
    displayFormatter: (v) => (v == null || v === '') ? '' : Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  },
  PesoCrudo: {
    type: 'number',
    min: 0,
    step: 0.01,
    displayFormatter: (v) => (v == null || v === '') ? '' : Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  },
  Ancho: {
    type: 'number',
    min: 0,
    step: 0.01,
    displayFormatter: (v) => (v == null || v === '') ? '' : Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  },
  AnchoToalla: {
    type: 'number',
    min: 0,
    step: 0.01,
    displayFormatter: (v) => (v == null || v === '') ? '' : Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  },
  EntregaPT: {
    type: 'date',
    inputFormatter: (v) => toInputDate(v),
    toPayload: (v) => v === '' ? null : v,
    displayFormatter: (v) => v ? formatDateDisplay(v) : ''
  },
  EntregaCte: {
    type: 'datetime-local',
    inputFormatter: (v) => {
      if (!v) return '';
      const d = new Date(v);
      if (isNaN(d.getTime())) return '';
      const year = d.getFullYear();
      const month = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      const hours = String(d.getHours()).padStart(2, '0');
      const minutes = String(d.getMinutes()).padStart(2, '0');
      return `${year}-${month}-${day}T${hours}:${minutes}`;
    },
    toPayload: (v) => v === '' ? null : v,
    displayFormatter: (v) => v ? formatDateDisplay(v) : ''
  },
  PTvsCte: {
    type: 'number',
    step: 1,
    displayFormatter: (v) => (v == null || v === '') ? '' : String(v)
  },
  NoProduccion: {
    type: 'text',
    maxLength: 80,
    toPayload: (v) => (v === '' ? null : String(v).trim()),
    displayFormatter: (v) => (v == null || v === '') ? '' : String(v)
  },
  // NOTA: Las siguientes columnas NO son editables (calculadas automáticamente):
  // - DiasEficiencia (Dias Ef)
  // - ProdKgDia
  // - StdDia
  // - ProdKgDia2
  // - StdToaHra
  // - HorasProd
  // - StdHrsEfect
  // - FechaInicio (Inicio)
  // - FechaFinal (Fin)
};

// ==========================
// Inline Edit - Lógica
// ==========================
(function() {
  'use strict';

  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
  const tbodyEl = () => $('#mainTable tbody');

  function getCellValue(cell) {
    // data-value tiene prioridad (valor real)
    const v = cell?.dataset?.value;
    if (v !== undefined && v !== null) return String(v).trim();
    return (cell?.textContent || '').trim();
  }

  function setCellValue(cell, display, rawValue) {
    cell.innerHTML = (display ?? '');
    if (rawValue === null || rawValue === undefined) {
      delete cell.dataset.value;
    } else {
      cell.dataset.value = String(rawValue);
    }
    cell.dataset.originalValue = cell.innerHTML;
  }

  // Cargar catálogo
  async function loadCatalog(catalogName) {
    if (catalogosCache[catalogName]) return catalogosCache[catalogName];

    let url = '';
    if (catalogName === 'hilos') url = '/programa-tejido/hilos-options';
    else if (catalogName === 'aplicaciones') url = '/programa-tejido/aplicacion-id-options';
    else if (catalogName === 'calendarios') url = '/programa-tejido/calendario-id-options';
    else return [];

    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const res = await fetch(url, {
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf
        },
        credentials: 'same-origin'
      });

      if (!res.ok) return [];

      const data = await res.json();

      let normalized = [];
      if (Array.isArray(data)) {
        normalized = data.map(x => ({ value: x, label: x }));
      } else if (data?.data && Array.isArray(data.data)) {
        if (catalogName === 'hilos') {
          normalized = data.data.map(item => ({
            value: item.Hilo || item.value || item.id || item,
            label: item.Hilo
              ? (item.Hilo + (item.Fibra ? ' - ' + item.Fibra : ''))
              : (item.label || item.name || item.value || item.id || String(item))
          }));
        } else {
          normalized = data.data.map(item => ({
            value: item.value || item.id || item,
            label: item.label || item.name || item.text || item.value || item.id || String(item)
          }));
        }
      } else if (data?.success && Array.isArray(data.data)) {
        normalized = data.data.map(item => ({
          value: item.value || item.id || item,
          label: item.label || item.name || item.text || item.value || item.id || String(item)
        }));
      }

      catalogosCache[catalogName] = normalized;
      return normalized;
    } catch (e) {
      console.error('loadCatalog error', catalogName, e);
      return [];
    }
  }

  // ====== Editar SOLO la celda clickeada ======
  window.enableInlineEditForCell = async function enableInlineEditForCell(cell) {
    if (!cell) {
      return;
    }

    const row = cell.closest('.selectable-row');
    if (!row) {
      return;
    }

    const rowId = row.getAttribute('data-id');
    if (!rowId) {
      return;
    }

    // Asegurar que solo esta fila específica tenga el amarillo (limpiar otras filas)
    const allRows = window.allRows?.length ? window.allRows : Array.from(document.querySelectorAll('.selectable-row'));
    allRows.forEach(r => {
      if (r !== row && r.classList.contains('bg-yellow-100') && !r.querySelector('.inline-edit-input')) {
        // Solo quitar amarillo de filas que no están en edición
        r.classList.remove('bg-yellow-100');
      }
    });

    const columnName = cell.getAttribute('data-column');
    if (!columnName) {
      return;
    }

    if (!uiInlineEditableFields[columnName]) {
      return;
    }

    // si ya está editando esa celda
    if (cell.querySelector('.inline-edit-input')) {
      return;
    }


    const cfg = uiInlineEditableFields[columnName];
    const currentValue = getCellValue(cell);
    const originalDisplay = cell.dataset.originalValue ?? cell.innerHTML;
    const originalDataValue = cell.dataset.value;

    // Guardar valores originales para poder restaurarlos
    cell.dataset.originalValue = originalDisplay;
    if (originalDataValue !== undefined) {
      cell.dataset.originalDataValue = originalDataValue;
    }

    // NO agregar amarillo al inicio, solo cuando hay cambios

    const wrap = document.createElement('div');
    wrap.className = 'inline-edit-input-container';
    wrap.style.width = '100%';

    let input;

    if (cfg.type === 'select' && cfg.catalog) {
      input = document.createElement('select');
      // Agregar clase especial para TotalPedido si es select (aunque no debería serlo)
      const baseClass = columnName === 'TotalPedido'
        ? 'inline-edit-input inline-edit-input-wide w-full'
        : 'inline-edit-input w-full';
      input.className = baseClass;
      input.dataset.field = columnName;
      input.dataset.rowId = rowId;

      // opción vacía
      const empty = document.createElement('option');
      empty.value = '';
      empty.textContent = '-- Seleccionar --';
      input.appendChild(empty);

      // opcional: NA para aplicaciones
      if (columnName === 'AplicacionId') {
        const na = document.createElement('option');
        na.value = 'NA';
        na.textContent = 'NA';
        input.appendChild(na);
      }

      const catalog = await loadCatalog(cfg.catalog);
      catalog.forEach(item => {
        const opt = document.createElement('option');
        opt.value = String(item.value ?? '');
        opt.textContent = String(item.label ?? item.value ?? '');
        input.appendChild(opt);
      });

      // seleccionar por value (data-value) o por texto
      const byValue = Array.from(input.options).find(o => o.value == currentValue);
      if (byValue) byValue.selected = true;
      else {
        const byText = Array.from(input.options).find(o => o.textContent.trim() === currentValue);
        if (byText) byText.selected = true;
      }

    } else if (cfg.type === 'date' || cfg.type === 'datetime-local') {
      input = document.createElement('input');
      input.type = cfg.type;
      // Agregar clase especial para TotalPedido si es fecha (aunque no debería serlo)
      const baseClass = columnName === 'TotalPedido'
        ? 'inline-edit-input inline-edit-input-wide w-full'
        : 'inline-edit-input w-full';
      input.className = baseClass;
      input.dataset.field = columnName;
      input.dataset.rowId = rowId;

      // Para datetime-local, usar el data-value original si existe para preservar segundos
      let dateValue = currentValue;
      if (cfg.type === 'datetime-local' && originalDataValue) {
        // Usar el valor original del data-value que tiene la fecha completa con segundos
        dateValue = originalDataValue;
      }

      input.value = cfg.inputFormatter ? cfg.inputFormatter(dateValue) : dateValue;

    } else {
      input = document.createElement('input');
      input.type = cfg.type || 'text';
      // Agregar clase especial para TotalPedido para hacerlo más ancho
      const baseClass = columnName === 'TotalPedido'
        ? 'inline-edit-input inline-edit-input-wide w-full'
        : 'inline-edit-input w-full';
      input.className = baseClass;
      input.dataset.field = columnName;
      input.dataset.rowId = rowId;

      if (cfg.maxLength) input.maxLength = cfg.maxLength;
      if (cfg.step) input.step = cfg.step;
      if (cfg.min !== undefined) input.min = cfg.min;

      if (cfg.type === 'number') {
        // Mantener el valor original sin redondear para preservar decimales
        // No usar parseFloat que puede perder precisión, usar el valor tal cual
        const numStr = String(currentValue).replace(/[^\d.-]/g, '');
        if (numStr === '' || numStr === '-') {
          input.value = '';
        } else {
          // Preservar todos los decimales del valor original
          input.value = numStr;
        }
      } else {
        input.value = currentValue;
      }
    }

    function cancel() {
      // Limpiar timeout del amarillo si existe
      if (yellowTimeout) {
        clearTimeout(yellowTimeout);
        yellowTimeout = null;
      }

      cell.innerHTML = originalDisplay;
      // Solo quitar el amarillo si no hay otras celdas en edición
      const otherInputs = row.querySelectorAll('.inline-edit-input');
      if (otherInputs.length === 0 || (otherInputs.length === 1 && otherInputs[0] === input)) {
        row.classList.remove('inline-edit-row');
        row.classList.remove('bg-yellow-100');
      }
    }

    // Variable para el timeout del amarillo (ya no se usa, el amarillo permanece hasta guardar)
    let yellowTimeout = null;

    // Mostrar amarillo cuando hay cambios y mantenerlo hasta que se guarde completamente
    input.addEventListener('input', () => {
      const newVal = (input.value ?? '').trim();
      // Para datetime-local, usar el valor original del data-value si existe
      let compareValue = currentValue;
      if (cfg.type === 'datetime-local' && originalDataValue) {
        compareValue = originalDataValue;
      }
      const oldVal = (cfg.type === 'date' || cfg.type === 'datetime-local')
        ? (cfg.inputFormatter ? cfg.inputFormatter(compareValue) : compareValue)
        : String(compareValue ?? '');

      // Limpiar timeout anterior si existe (ya no se usa, pero por si acaso)
      if (yellowTimeout) {
        clearTimeout(yellowTimeout);
        yellowTimeout = null;
      }

      // Si hay cambios, mostrar amarillo y mantenerlo hasta guardar
      if (newVal !== String(oldVal ?? '')) {
        row.classList.add('inline-edit-row');
        row.classList.add('bg-yellow-100');
        // NO quitar el amarillo automáticamente, permanecerá hasta que se guarde
      } else {
        // Si no hay cambios, quitar amarillo inmediatamente
        row.classList.remove('bg-yellow-100');
      }
    });

    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        saveInlineField(input, row, cell);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        cancel();
      }
    });

    input.addEventListener('blur', () => {
      setTimeout(() => {
        const newVal = (input.value ?? '').trim();
        // Para datetime-local, usar el valor original del data-value si existe
        let compareValue = currentValue;
        if (cfg.type === 'datetime-local' && originalDataValue) {
          compareValue = originalDataValue;
        }
        const oldVal = (cfg.type === 'date' || cfg.type === 'datetime-local')
          ? (cfg.inputFormatter ? cfg.inputFormatter(compareValue) : compareValue)
          : String(compareValue ?? '');

        if (newVal !== String(oldVal ?? '')) {
          saveInlineField(input, row, cell);
        } else {
          cancel();
        }
      }, 150);
    });

    wrap.appendChild(input);
    cell.innerHTML = '';
    cell.appendChild(wrap);

    // No hacer focus automático para que no se active el amarillo
    // input.focus();
    // input.select?.();
  }

  // actualizar más celdas si backend recalcula cosas (fechas, saldo, etc.)
  function applyRowUpdatesFromBackend(row, data) {
    if (!row || !data) return;

    const num2Cols = new Set([
      'DiasEficiencia','ProdKgDia','StdDia','ProdKgDia2','StdToaHra','HorasProd','StdHrsEfect','DiasJornada','PesoGRM2',
      'TotalPedido','LargoCrudo','Luchaje','PesoCrudo'
    ]);

    const dateTimeCols = new Set(['FechaInicio','FechaFinal','EntregaCte']);
    const dateOnlyCols = new Set(['EntregaProduc','EntregaPT','ProgramarProd']);

    Object.entries(data).forEach(([backendKey, raw]) => {
      if (raw === undefined) return;

      const td = row.querySelector(`td[data-column="${backendKey}"]`);
      if (!td) return;

      let display = '';
      const cfg = uiInlineEditableFields[backendKey];

      if (dateTimeCols.has(backendKey)) {
        display = raw ? formatDateTimeDisplay(raw) : '';
      } else if (dateOnlyCols.has(backendKey) || backendKey.startsWith('Entrega')) {
        display = raw ? formatDateOnlyDisplay(raw) : '';
      } else if (num2Cols.has(backendKey)) {
        display = formatNumber2(raw);
      } else if (cfg && cfg.displayFormatter) {
        // Para campos select (catálogos), displayFormatter necesita inputEl que no existe aquí.
        // Usar valor raw directamente - el backend devuelve el valor listo para mostrar.
        if (cfg.type === 'select') {
          display = (raw == null || raw === '') ? '' : String(raw);
        } else {
          display = cfg.displayFormatter(raw);
        }
      } else {
        display = (raw == null || raw === '') ? '' : String(raw);
      }

      setCellValue(td, display, raw);
    });
  }

  // Vuelve a consultar Velocidad/Eficiencia STD (mismo endpoint que usa el modal Duplicar/Dividir)
  // y las guarda en la orden. Se dispara al cambiar el hilo (FibraRizo) desde la edición inline.
  async function recalcularStdParaFila(row, fibraId) {
    const rowId = row.getAttribute('data-id');
    const noTelarCell = row.querySelector('td[data-column="NoTelarId"]');
    const calibreCell = row.querySelector('td[data-column="CalibreTrama2"]') || row.querySelector('td[data-column="CalibreTrama"]');
    const noTelar = noTelarCell ? getCellValue(noTelarCell) : '';
    const calibreTrama = calibreCell ? getCellValue(calibreCell) : '';

    if (!fibraId || !noTelar || !calibreTrama) return;

    try {
      const params = new URLSearchParams({ fibra_id: fibraId, no_telar_id: noTelar, calibre_trama: calibreTrama });
      const res = await fetch(`/programa-tejido/eficiencia-velocidad-std?${params.toString()}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      });
      if (!res.ok) return;

      const std = await res.json();
      if (std.velocidad == null && std.eficiencia == null) return;

      // El backend (UpdateTejido::actualizar) solo acepta estas claves snake_case, no los nombres de columna
      const payload = {};
      if (std.velocidad != null) payload.velocidad_std = std.velocidad;
      if (std.eficiencia != null) payload.eficiencia_std = std.eficiencia;

      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const saveRes = await fetch(`/planeacion/programa-tejido/${rowId}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      if (!saveRes.ok) return;

      const saveResult = await saveRes.json().catch(() => ({}));
      const display = {};
      if (std.velocidad != null) display.VelocidadSTD = std.velocidad;
      if (std.eficiencia != null) display.EficienciaSTD = std.eficiencia;
      applyRowUpdatesFromBackend(row, display);
    } catch (e) {
      console.warn('recalcularStdParaFila error', e);
    }
  }

  // Guardar campo individual
  async function saveInlineField(input, row, cell) {
    const columnName = input.dataset.field;
    const rowId = input.dataset.rowId;
    const cfg = uiInlineEditableFields[columnName];

    // Usar directamente el nombre de campo BD (que coincide con data-column) para el payload
    const payloadField = inlineFieldPayloadMap[columnName] || columnName;

    if (!cfg || !payloadField || !rowId) return;

    let value = (input.value ?? '').trim();

    if (cfg.toPayload) value = cfg.toPayload(value);
    else if (cfg.type === 'number') value = (value === '' ? null : Number(value));
    else if (value === '') value = null;

    row.classList.add('inline-saving');

    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const res = await fetch(`/planeacion/programa-tejido/${rowId}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf
        },
        credentials: 'same-origin',
        body: JSON.stringify({ [payloadField]: value })
      });

      const result = await res.json().catch(() => ({}));
      if (!res.ok || result?.success === false) {
        throw new Error(result?.message || 'Error al guardar');
      }

      // display - formatear bien al instante (sin recargar)
      let displayValue = '';
      if (cfg?.displayFormatter) {
        displayValue = cfg.displayFormatter(value, input);
      } else if (cfg?.type === 'number') {
        displayValue = formatNumber2(value);
      } else {
        displayValue = (value == null) ? '' : String(value);
      }

      setCellValue(cell, displayValue, value);

      // si backend manda resumen, actualiza otras celdas (fechas/saldo/etc)
      if (result?.data) applyRowUpdatesFromBackend(row, result.data);

      // Cambiar el hilo (FibraRizo) invalida Velocidad/Eficiencia STD: recalcular contra el catálogo
      if (columnName === 'FibraRizo') {
        await recalcularStdParaFila(row, value);
      }

      // Actualizar índice de filtros en memoria para reflejar los nuevos valores
      if (window.PT?.filterIndex) {
        window.PT.filterIndex.updateRow(row);
      }

      // Quitar amarillo después de guardar exitosamente
      row.classList.remove('bg-yellow-100');

      if (typeof window.showToast === 'function') {
        window.showToast('Campo actualizado', 'success');
      } else if (typeof window.notify !== 'undefined') {
        window.notify.success('Actualizado');
      }
    } catch (e) {
      console.error('saveInlineField error', e);

      // restaurar
      const original = cell.dataset.originalValue ?? '';
      cell.innerHTML = original;

      // Mantener amarillo si hay error (para indicar que hay cambios pendientes)
      // No quitar el amarillo aquí, se mantendrá hasta que se guarde exitosamente

      if (typeof window.showToast === 'function') {
        window.showToast(`Error: ${e.message}`, 'error');
      } else if (typeof window.notify !== 'undefined') {
        window.notify.error(e.message);
      }
    } finally {
      row.classList.remove('inline-saving');
      // No remover inline-edit-row ni bg-yellow-100 aquí
      // El amarillo permanecerá hasta que se guarde exitosamente o se cancele
    }
  }

  // Cerrar edición inline de una fila (restaurar valores originales)
  window.closeInlineEditForRow = function(row) {
    if (!row) return;

    const cells = row.querySelectorAll('td[data-column]');
    cells.forEach(cell => {
      const inputContainer = cell.querySelector('.inline-edit-input-container');
      if (inputContainer) {
        // Siempre restaurar el valor original guardado
        const originalValue = cell.dataset.originalValue;
        if (originalValue !== undefined && originalValue !== null) {
          cell.innerHTML = originalValue;
          // Restaurar también el data-value si existe
          const originalDataValue = cell.dataset.originalDataValue;
          if (originalDataValue !== undefined) {
            cell.dataset.value = originalDataValue;
          }
        } else {
          // Si no hay valor original, obtener el valor del input y formatearlo
          const input = cell.querySelector('.inline-edit-input');
          if (input) {
            const currentValue = input.value;
            const col = cell.getAttribute('data-column');
            const cfg = uiInlineEditableFields[col];
            if (cfg && cfg.displayFormatter) {
              cell.innerHTML = cfg.displayFormatter(currentValue, input);
            } else {
              cell.innerHTML = currentValue || '';
            }
          }
        }
      }
    });

    // Remover clases de edición
    row.classList.remove('inline-edit-row');
    row.classList.remove('bg-yellow-100');
  };

  // Activar edición en todas las celdas editables de una fila
  window.enableInlineEditForAllCellsInRow = async function(row) {
    if (!row) return;

    // NO agregar amarillo al inicio, solo cuando hay cambios

    const cells = row.querySelectorAll('td[data-column]');
    const editableCells = Array.from(cells).filter(cell => {
      const col = cell.getAttribute('data-column');
      return col && uiInlineEditableFields[col] && !cell.querySelector('.inline-edit-input');
    });

    // Activar edición en todas las celdas editables simultáneamente
    const promises = editableCells.map(cell => {
      if (window.enableInlineEditForCell) {
        return window.enableInlineEditForCell(cell);
      }
      return Promise.resolve();
    });

    await Promise.all(promises);
  };

  // Aplicar modo inline
  window.applyInlineModeToRows = function() {
    if (!window.inlineEditMode) return;

    const rows = window.allRows?.length ? window.allRows : $$('.selectable-row');
    rows.forEach(r => r.classList.add('inline-edit-ready'));

    const tb = tbodyEl();
    if (!tb) return;

    // Remover listener anterior si existe
    if (tb.dataset.inlineBound && tb._inlineEditHandler) {
      tb.removeEventListener('click', tb._inlineEditHandler);
    }

    // Crear nuevo handler
    tb._inlineEditHandler = function inlineEditClickHandler(e) {
      if (!window.inlineEditMode) return;

      // Buscar la celda clickeada
      const cell = e.target.closest('td[data-column]');
      if (!cell) return;

      const col = cell.getAttribute('data-column');
      if (!col) return;

      // Verificar si el campo es editable
      if (!uiInlineEditableFields[col]) {
        return; // No es editable, dejar que el evento continúe
      }

      // Evitar que se active si se hace click en un input existente
      if (cell.querySelector('.inline-edit-input')) return;

      // Detener propagación SOLO si es una celda editable
      e.stopPropagation();

      if (window.enableInlineEditForCell) {
        window.enableInlineEditForCell(cell);
      }
    };

    tb.addEventListener('click', tb._inlineEditHandler, true); // Usar capture phase para tener prioridad
    tb.dataset.inlineBound = '1';
  };

  window.toggleInlineEditMode = function() {
    window.inlineEditMode = !window.inlineEditMode;

    const tb = tbodyEl();
    if (window.inlineEditMode) {
      tb?.classList.add('inline-edit-mode');
      // Forzar re-aplicación del modo inline
      if (tb?.dataset.inlineBound) {
        delete tb.dataset.inlineBound;
      }
      window.applyInlineModeToRows();
      if (typeof window.showToast === 'function') window.showToast('Edición inline activada: clic en una celda editable', 'info');
    } else {
      tb?.classList.remove('inline-edit-mode');
      // Remover listener
      if (tb?._inlineEditHandler) {
        tb.removeEventListener('click', tb._inlineEditHandler, true);
        delete tb._inlineEditHandler;
        delete tb.dataset.inlineBound;
      }
      // cerrar inputs abiertos y limpiar ediciones de todas las filas
      const rowsWithInputs = $$('.selectable-row').filter(row => row.querySelector('.inline-edit-input'));
      rowsWithInputs.forEach(row => {
        if (typeof window.closeInlineEditForRow === 'function') {
          window.closeInlineEditForRow(row);
        }
      });
      if (typeof window.showToast === 'function') window.showToast('Edición inline desactivada', 'info');
    }
  };

})();


  (function () {
    window.PT = window.PT || {};
    const PT = window.PT;

    const qs  = (sel, ctx=document) => ctx.querySelector(sel);
    const qsa = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));
    const tbodyEl = () => qs('#mainTable tbody');
    const setNavbarHeightVar = () => {
      const nav = document.querySelector('nav');
      if (!nav) return;
      document.documentElement.style.setProperty('--pt-navbar-height', `${nav.offsetHeight}px`);
    };

    /**
      * Limita la frecuencia de ejecución de una función (útil para operaciones DOM costosas).
      * Usa throttle de utils.js si está disponible.
      * @param {Function} fn - Función a envolver
      * @param {number} delay - Milisegundos mínimos entre ejecuciones
      * @returns {Function}
      */
    const throttle = (fn, delay) => {
      let lastCall = 0;
      return function (...args) {
        const now = Date.now();
        if (now - lastCall >= delay) {
          lastCall = now;
          return fn.apply(this, args);
        }
      };
    };

    const toast = (msg, type='info') => {
      if (typeof window.showToast === 'function') return window.showToast(msg, type);
    };

    // =========================
    // BOTÃ“N DRAG & DROP (GRIS CUANDO ACTIVO)
    // =========================
    const DD_ACTIVE_CLASSES = ['bg-gray-400','hover:bg-gray-500','text-white','opacity-80'];

    function findDragDropButton() {
      return (
        qs('#btnDragDrop') ||
        qs('#layoutBtnDragDrop') ||
        qs('#btn-dragdrop') ||
        qs('button[onclick*="toggleDragDropMode"]') ||
        qs('a[onclick*="toggleDragDropMode"]') ||
        qs('button[title*="Drag"]') ||
        qs('button[title*="Arrastr"]') ||
        qs('a[title*="Drag"]') ||
        qs('a[title*="Arrastr"]')
      );
    }

    function setDragDropButtonGray(isActive) {
      const btn = findDragDropButton();
      if (!btn) return;

      if (!btn.dataset.ddOrigClass) btn.dataset.ddOrigClass = btn.className;

      if (isActive) {
        btn.className = btn.dataset.ddOrigClass + ' ' + DD_ACTIVE_CLASSES.join(' ');
        btn.setAttribute('aria-pressed', 'true');
      } else {
        btn.className = btn.dataset.ddOrigClass;
        btn.setAttribute('aria-pressed', 'false');
      }
    }

    // =========================
    // Loader Ãºnico
    // =========================
    PT.loader = PT.loader || {
      show() {
        let loader = document.getElementById('priority-loader');
        if (!loader) {
          loader = document.createElement('div');
          loader.id = 'priority-loader';
          loader.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.3);z-index:9999;display:flex;align-items:center;justify-content:center;';
          loader.innerHTML = '<div style="background:white;padding:20px;border-radius:8px;box-shadow:0 4px 6px rgba(0,0,0,0.1);"><div style="width:40px;height:40px;border:4px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:spin 0.6s linear infinite;"></div><style>@keyframes spin{to{transform:rotate(360deg);}}</style></div>';
          document.body.appendChild(loader);
        } else {
          loader.style.display = 'flex';
        }
      },
      hide() {
        const loader = document.getElementById('priority-loader');
        if (loader) loader.style.display = 'none';
      }
    };

    // =========================
    // Cache por fila
    // =========================
    PT.rowCache = PT.rowCache || new WeakMap();

    /**
     * Obtiene metadatos de una fila de la tabla (con cache).
     * @param {HTMLTableRowElement} row - Fila de la tabla
     * @returns {Object} Objeto con telar, salon, cambioHilo (string), enProceso (boolean), posicion (number|null)
     */
    function rowMeta(row) {
      if (!row) return { telar:'', salon:'', cambioHilo:'', enProceso:false };
      if (PT.rowCache.has(row)) return PT.rowCache.get(row);

      const telar = row.querySelector('[data-column="NoTelarId"]')?.textContent?.trim() ?? '';
      const salon = row.querySelector('[data-column="SalonTejidoId"]')?.textContent?.trim() ?? '';
      const cambioHilo = row.querySelector('[data-column="CambioHilo"]')?.textContent?.trim() ?? '';
      const posicionRaw =
        row.getAttribute('data-posicion') ??
        row.querySelector('[data-column="Posicion"]')?.textContent?.trim() ??
        '';
      const posicionParsed = parseInt(posicionRaw, 10);
      const posicion = Number.isFinite(posicionParsed) ? posicionParsed : null;
      const enProcesoCell = row.querySelector('[data-column="EnProceso"]');
      const enProceso = !!enProcesoCell?.querySelector('input[type="checkbox"]')?.checked;

      const meta = { telar, salon, cambioHilo, posicion, enProceso };
      PT.rowCache.set(row, meta);
      return meta;
    }

    window.clearRowCache = clearRowCache;
    function clearRowCache() { PT.rowCache = new WeakMap(); }
    // Se publica aqui porque vive dentro del IIFE: como modulo ya no hay global implicito.
    window.clearRowCache = clearRowCache;

    function normalizeTelarValue(value) {
      const str = String(value ?? '').trim();
      if (!str) return '';
      const num = Number(str);
      if (!Number.isNaN(num)) return String(num);
      return str.toUpperCase();
    }
    function isSameTelar(a, b) { return normalizeTelarValue(a) === normalizeTelarValue(b); }

    // =========================
    // Orden actual de filas
    // =========================
    /**
     * Refresca el cache de filas y retorna array de todas las filas seleccionables.
     * @returns {HTMLTableRowElement[]}
     */
    // Un solo listener en tbody, como ya lo hace la seleccion multiple. La fila y su
    // indice se resuelven en el click, asi que no hay indice congelado (el bug que
    // dejaba selectedRowIndex apuntando a otra fila tras filtrar o insertar) ni
    // listeners que reasignar al insertar filas o al entrar/salir del modo arrastrar.
    function bindRowSelectionOnce() {
      const tb = tbodyEl();
      if (!tb || tb.dataset.selectionBound) return;
      tb.dataset.selectionBound = '1';

      tb.addEventListener('click', (e) => {
        // Esos dos modos manejan sus propios clicks.
        if (window.dragDropMode || window.multiSelectMode) return;

        const row = e.target.closest('tr.selectable-row');
        if (!row) return;

        if (window.inlineEditMode) {
          const cell = e.target.closest('td[data-column]');
          const col = cell?.getAttribute('data-column');
          if (col && typeof uiInlineEditableFields !== 'undefined' && uiInlineEditableFields[col]) {
            return; // lo maneja la edicion inline
          }
        }

        e.stopPropagation();
        if (typeof window.selectRow !== 'function') {
          console.warn('[PT] window.selectRow no esta disponible (selection.blade.php no cargo).');
          return;
        }
        const rows = window.allRows?.length ? window.allRows : $$('.selectable-row');
        window.selectRow(row, rows.indexOf(row));
      });
    }

    function refreshAllRows() {
      const tb = tbodyEl();
      if (!tb) return [];
      window.allRows = Array.from(tb.querySelectorAll('.selectable-row'));
      clearRowCache();
      // Si estamos en modo selecciÃ³n mÃºltiple, actualizar visualizaciÃ³n de filas bloqueadas
      if (window.multiSelectMode) {
        updateSelectedRowsVisual();
      }
      return window.allRows;
    }

    // Versión con throttle para contextos de actualización masiva (ej: balanceo batch)
    const refreshAllRowsThrottled = throttle(refreshAllRows, 80);

    // =========================
    // Actualizar tabla despues de Drag & Drop
    // =========================
    const DD_NUM2_COLS = new Set([
      'DiasEficiencia','ProdKgDia','StdDia','ProdKgDia2','StdToaHra','HorasProd','StdHrsEfect','DiasJornada','PesoGRM2',
      'TotalPedido','LargoCrudo','Luchaje','PesoCrudo'
    ]);
    const DD_DATE_TIME_COLS = new Set(['FechaInicio','FechaFinal','EntregaCte']);
    const DD_DATE_ONLY_COLS = new Set(['EntregaProduc','EntregaPT','ProgramarProd','Programado']);

    /**
     * Formatea un valor como fecha y hora (dd/mm/yyyy HH:mm).
     * @param {string|null} raw - Valor crudo de la celda
     * @returns {string}
     */
    function ddFormatDateTime(raw) {
      if (typeof formatDateTimeDisplay === 'function') return formatDateTimeDisplay(raw);
      return raw ? String(raw) : '';
    }

    /**
     * Formatea un valor como fecha sola (dd/mm/yyyy).
     * @param {string|null} raw - Valor crudo de la celda
     * @returns {string}
     */
    function ddFormatDateOnly(raw) {
      if (typeof formatDateOnlyDisplay === 'function') return formatDateOnlyDisplay(raw);
      if (typeof formatDateDisplay === 'function') return formatDateDisplay(raw);
      return raw ? String(raw) : '';
    }

    /**
     * Formatea un valor numérico con 2 decimales (locale es-MX).
     * @param {string|number|null} raw - Valor crudo de la celda
     * @returns {string}
     */
    function ddFormatNumber(raw) {
      if (typeof formatNumber2 === 'function') return formatNumber2(raw);
      const n = Number(raw);
      if (!Number.isFinite(n)) return raw == null ? '' : String(raw);
      return n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function ddSetCellValue(cell, display, rawValue) {
      if (!cell) return;
      cell.innerHTML = display ?? '';
      if (rawValue === null || rawValue === undefined) {
        delete cell.dataset.value;
      } else {
        cell.dataset.value = String(rawValue);
      }
    }

    function ddFormatCell(column, raw) {
      if (column === 'EnProceso') {
        const checked = (raw == 1 || raw === true) ? 'checked' : '';
        return {
          display: `<input type="checkbox" ${checked} disabled class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">`,
          rawValue: raw ?? 0
        };
      }

      if (column === 'Ultimo') {
        const sv = String(raw ?? '').trim().toUpperCase();
        const isUltimo = (sv === 'UL' || sv === '1');
        return { display: isUltimo ? '<strong>ULTIMO</strong>' : '', rawValue: raw ?? '' };
      }

      if (column === 'CambioHilo') {
        const isZero = (raw == 0 || raw === '0' || raw === null || raw === undefined);
        return { display: isZero ? '' : String(raw), rawValue: raw ?? '' };
      }

      if (column === 'EficienciaSTD') {
        const n = Number(raw);
        if (!Number.isFinite(n)) return { display: raw == null ? '' : String(raw), rawValue: raw ?? '' };
        return { display: `${Math.round(n * 100)}%`, rawValue: raw };
      }

      if (DD_DATE_TIME_COLS.has(column)) {
        return { display: raw ? ddFormatDateTime(raw) : '', rawValue: raw ?? '' };
      }

      if (DD_DATE_ONLY_COLS.has(column) || column.startsWith('Entrega')) {
        return { display: raw ? ddFormatDateOnly(raw) : '', rawValue: raw ?? '' };
      }

      if (DD_NUM2_COLS.has(column)) {
        return { display: ddFormatNumber(raw), rawValue: raw ?? '' };
      }

      if (typeof uiInlineEditableFields !== 'undefined' && uiInlineEditableFields[column]?.displayFormatter) {
        return {
          display: uiInlineEditableFields[column].displayFormatter(raw),
          rawValue: raw ?? ''
        };
      }

      return { display: raw == null ? '' : String(raw), rawValue: raw ?? '' };
    }

    function ddApplyUpdatesToRow(row, updates) {
      if (!row || !updates) return;

      if (updates.Posicion !== undefined && updates.Posicion !== null) {
        row.setAttribute('data-posicion', String(updates.Posicion));
      }

      Object.entries(updates).forEach(([column, raw]) => {
        const td = row.querySelector(`td[data-column="${column}"]`);
        if (!td) return;

        const fmt = ddFormatCell(column, raw);
        ddSetCellValue(td, fmt.display, fmt.rawValue);
      });
    }

    /**
     * Reordena las filas del tbody por salón y luego por número de telar.
     * Se invoca después de drag-and-drop o cambios de posición.
     */
    function ddReorderRows() {
      const tb = tbodyEl();
      if (!tb) return;

      const rows = Array.from(tb.querySelectorAll('.selectable-row'));
      rows.sort((a, b) => {
        const telarA = normalizeTelarValue(a.querySelector('[data-column="NoTelarId"]')?.textContent || '');
        const telarB = normalizeTelarValue(b.querySelector('[data-column="NoTelarId"]')?.textContent || '');
        if (telarA !== telarB) return telarA.localeCompare(telarB, undefined, { numeric: true, sensitivity: 'base' });

        const salonA = (a.querySelector('[data-column="SalonTejidoId"]')?.textContent || '').trim().toUpperCase();
        const salonB = (b.querySelector('[data-column="SalonTejidoId"]')?.textContent || '').trim().toUpperCase();
        if (salonA !== salonB) return salonA.localeCompare(salonB);

        const posA = parseInt(a.getAttribute('data-posicion') || '0', 10);
        const posB = parseInt(b.getAttribute('data-posicion') || '0', 10);
        return (Number.isFinite(posA) ? posA : 0) - (Number.isFinite(posB) ? posB : 0);
      });

      const frag = document.createDocumentFragment();
      rows.forEach(r => frag.appendChild(r));
      tb.innerHTML = '';
      tb.appendChild(frag);

      refreshAllRows();
    }

    function ddSelectRowById(registroId) {
      if (!registroId || typeof window.selectRow !== 'function') return;
      const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tbodyEl());
      const row = rows.find(r => r.getAttribute('data-id') == registroId);
      if (!row) return;
      const idx = rows.indexOf(row);
      if (idx >= 0) window.selectRow(row, idx);
      row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    window.updateTableAfterDragDrop = function updateTableAfterDragDrop(detalles, registroId, updates = {}) {
      const tb = tbodyEl();
      if (!tb) return;

      const updatesById = (updates && typeof updates === 'object' && !Array.isArray(updates)) ? { ...updates } : {};
      const detallesList = Array.isArray(detalles) ? detalles : [];

      detallesList.forEach((detalle) => {
        const idKey = String(detalle?.Id ?? detalle?.id ?? '');
        if (!idKey) return;
        const fallback = {};
        if (detalle.NoTelar !== undefined) fallback.NoTelarId = detalle.NoTelar;
        if (detalle.Posicion !== undefined) fallback.Posicion = detalle.Posicion;
        if (detalle.FechaInicio_nueva !== undefined) fallback.FechaInicio = detalle.FechaInicio_nueva;
        if (detalle.FechaFinal_nueva !== undefined) fallback.FechaFinal = detalle.FechaFinal_nueva;
        if (detalle.EnProceso_nuevo !== undefined) fallback.EnProceso = detalle.EnProceso_nuevo;
        if (detalle.Ultimo_nuevo !== undefined) fallback.Ultimo = detalle.Ultimo_nuevo;
        if (detalle.CambioHilo_nuevo !== undefined) fallback.CambioHilo = detalle.CambioHilo_nuevo;
        if (detalle.HorasProd_calc !== undefined) fallback.HorasProd = detalle.HorasProd_calc;

        updatesById[idKey] = { ...fallback, ...(updatesById[idKey] || {}) };
      });

      const ids = Object.keys(updatesById);
      ids.forEach((idKey) => {
        const row = tb.querySelector(`tr.selectable-row[data-id="${idKey}"]`);
        if (!row) return;

        // Aplicar todos los updates
        ddApplyUpdatesToRow(row, updatesById[idKey]);

        // Asegurar que HorasProd (y otras columnas actualizadas) permanezcan visibles
        // Verificar el estado del header para cada columna actualizada
        Object.keys(updatesById[idKey]).forEach((columnField) => {
          const headerTh = document.querySelector(`#mainTable thead th[data-column="${columnField}"]`);
          if (headerTh) {
            const isHeaderHidden = headerTh.style.display === 'none' ||
                                   headerTh.classList.contains('hidden') ||
                                   window.getComputedStyle(headerTh).display === 'none';

            const cell = row.querySelector(`td[data-column="${columnField}"]`);
            if (cell) {
              if (isHeaderHidden) {
                cell.style.display = 'none';
              } else {
                // Asegurar que la celda esté visible si el header está visible
                cell.style.display = '';
                cell.classList.remove('hidden');
              }
            }
          }
        });

        // Si se actualizó SalonTejidoId o NoTelarId, reconstruir Maquina
        // Esto asegura que Maquina se actualice visualmente incluso si el backend ya lo calculó
        if (updatesById[idKey].SalonTejidoId !== undefined || updatesById[idKey].NoTelarId !== undefined) {
          // Si Maquina viene en los updates, usarlo directamente
          if (updatesById[idKey].Maquina !== undefined) {
            const maquinaTd = row.querySelector(`td[data-column="Maquina"]`);
            if (maquinaTd) {
              ddSetCellValue(maquinaTd, updatesById[idKey].Maquina, updatesById[idKey].Maquina);
            }
          }
        }
      });

      clearRowCache();
      ddReorderRows();

      // Restaurar visibilidad de columnas ocultas después de reordenar
      // Esto asegura que las columnas ocultas permanezcan ocultas después del drag and drop
      // Basarse en el estado del header para aplicar el mismo estado a las filas
      const headerCells = document.querySelectorAll('#mainTable thead th[data-column]');
      const tbody = tbodyEl();

      headerCells.forEach((th) => {
        const columnField = th.getAttribute('data-column');
        if (!columnField) return;

        const classList = Array.from(th.classList);
        const columnClass = classList.find(cls => cls.startsWith('column-'));
        if (columnClass) {
          const colIndex = parseInt(columnClass.replace('column-', ''));
          if (!isNaN(colIndex)) {
            // Verificar si la columna está oculta en el header
            const isHidden = th.style.display === 'none' ||
                            th.classList.contains('hidden') ||
                            window.getComputedStyle(th).display === 'none';

            if (isHidden) {
              // Aplicar el mismo estado a todas las celdas correspondientes en las filas
              // Buscar tanto por índice como por data-column para asegurar que todas las celdas se actualicen
              if (tbody) {
                const cellsByIndex = tbody.querySelectorAll(`td.column-${colIndex}`);
                const cellsByField = tbody.querySelectorAll(`td[data-column="${columnField}"]`);

                // Combinar ambos selectores para asegurar que todas las celdas se actualicen
                const allCells = new Set([...cellsByIndex, ...cellsByField]);
                allCells.forEach((cell) => {
                  cell.style.display = 'none';
                });
              }
            } else {
              // Asegurar que las columnas visibles estén visibles
              // Buscar tanto por índice como por data-column para asegurar que todas las celdas se actualicen
              if (tbody) {
                const cellsByIndex = tbody.querySelectorAll(`td.column-${colIndex}`);
                const cellsByField = tbody.querySelectorAll(`td[data-column="${columnField}"]`);

                // Combinar ambos selectores para asegurar que todas las celdas se actualicen
                const allCells = new Set([...cellsByIndex, ...cellsByField]);
                allCells.forEach((cell) => {
                  // Forzar visibilidad removiendo cualquier estilo que oculte la celda
                  cell.style.display = '';
                  cell.style.visibility = '';
                  cell.classList.remove('hidden');

                  // Si por alguna razón el estilo computado sigue siendo none, forzar table-cell
                  const computedStyle = window.getComputedStyle(cell);
                  if (computedStyle.display === 'none') {
                    cell.style.display = 'table-cell';
                  }
                });
              }
            }
          }
        }
      });

      // Restaurar posiciones de columnas fijadas después de reordenar
      if (typeof window.updatePinnedColumnsPositions === 'function') {
        window.updatePinnedColumnsPositions();
      }

      // ⚠️ FIX ESPECÍFICO: Asegurar que HorasProd sea visible después del reordenamiento
      // Esperar un momento para que el DOM se actualice completamente
      setTimeout(() => {
        const horasProdHeader = document.querySelector('#mainTable thead th[data-column="HorasProd"]');
        if (horasProdHeader) {
          const isHeaderHidden = horasProdHeader.style.display === 'none' ||
                                  horasProdHeader.classList.contains('hidden') ||
                                  window.getComputedStyle(horasProdHeader).display === 'none';

          if (!isHeaderHidden) {
            // Si el header está visible, forzar visibilidad de todas las celdas de HorasProd
            const horasProdCells = document.querySelectorAll('#mainTable tbody td[data-column="HorasProd"]');
            horasProdCells.forEach((cell) => {
              cell.style.display = '';
              cell.style.visibility = '';
              cell.classList.remove('hidden');
              // Forzar el estilo inline para asegurar visibilidad
              if (window.getComputedStyle(cell).display === 'none') {
                cell.style.display = 'table-cell';
              }
            });
          }
        }
      }, 0);

      if (window.dragDropMode) {
        window.allRows.forEach((row) => {
          const meta = rowMeta(row);
          row.draggable = !meta.enProceso;
          row.classList.toggle('cursor-move', !meta.enProceso);
          row.classList.toggle('cursor-not-allowed', meta.enProceso);
          row.style.opacity = meta.enProceso ? '0.6' : '';
        });
      }

      // El arrastre reescribe FechaInicio/FechaFinal/Posicion/EnProceso/Ultimo/HorasProd
      // en el DOM, y applyProgramaTejidoFilters() prefiere PT_FILTER_INDEX sobre el DOM
      // (filters.blade.php:156). Sin esto, filtrar despues de arrastrar evalua los valores
      // previos al arrastre y esconde o muestra las filas equivocadas.
      // ponytail: rebuild completo en vez de ir fila por fila; son ~85 filas y solo corre
      // al soltar un arrastre. Si la tabla crece, pasar a updateRow() por fila afectada.
      window.PT?.filterIndex?.rebuild();

      if (registroId) ddSelectRowById(registroId);
      if (typeof window.updateTotales === 'function') window.updateTotales();
    };

    // =========================
    // Context menu
    // =========================
    PT.contextMenu = PT.contextMenu || (function(){
      const menu = qs('#contextMenu');
      if (!menu) return null;

      let menuRow = null;

      function hide() {
        menu.classList.add('hidden');
        menuRow = null;
      }

      function show(e, row) {
        // Cerrar el menú de encabezados si está abierto
        if (PT.contextMenuHeader && typeof PT.contextMenuHeader.hide === 'function') {
          PT.contextMenuHeader.hide();
        }

        menuRow = row;
        menu.style.left = e.clientX + 'px';
        menu.style.top  = e.clientY + 'px';

        const rect = menu.getBoundingClientRect();
        if (rect.right > window.innerWidth)  menu.style.left = (e.clientX - rect.width) + 'px';
        if (rect.bottom > window.innerHeight) menu.style.top = (e.clientY - rect.height) + 'px';

        // Verificar si el registro está en proceso para mostrar/ocultar botones
        const meta = rowMeta(row);
        const enProceso = meta.enProceso;

        const eliminarBtn = qs('#contextMenuEliminar');
        if (eliminarBtn) {
          // Ocultar el botón de eliminar normal si está en proceso
          eliminarBtn.style.display = enProceso ? 'none' : '';
        }

        const eliminarEnProcesoBtn = qs('#contextMenuEliminarEnProceso');
        if (eliminarEnProcesoBtn) {
          // Mostrar el botón "Eliminar en proceso" solo si está en proceso
          eliminarEnProcesoBtn.style.display = enProceso ? '' : 'none';
        }

        // Mostrar/ocultar el botón de desvincular según si el registro tiene OrdCompartida
        const desvincularBtn = qs('#contextMenuDesvincular');
        if (desvincularBtn && row) {
          const ordCompartida = row.getAttribute('data-ord-compartida');
          // Ocultar el botón de desvincular si no tiene OrdCompartida
          if (!ordCompartida || ordCompartida.trim() === '') {
            desvincularBtn.style.display = 'none';
          } else {
            desvincularBtn.style.display = '';
          }
        }

        menu.classList.remove('hidden');
      }

      if (!menu.dataset.bound) {
        menu.dataset.bound = '1';

        document.addEventListener('click', (e) => {
          if (!menu.classList.contains('hidden') && !menu.contains(e.target)) {
            hide();
          }
          // También cerrar el menú de encabezados si está abierto
          if (PT.contextMenuHeader && typeof PT.contextMenuHeader.hide === 'function') {
            const headerMenu = qs('#contextMenuHeader');
            if (headerMenu && !headerMenu.classList.contains('hidden')) {
              PT.contextMenuHeader.hide();
            }
          }
        });

        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && !menu.classList.contains('hidden')) {
            hide();
          }
          // También cerrar el menú de encabezados con Escape
          if (e.key === 'Escape' && PT.contextMenuHeader && typeof PT.contextMenuHeader.hide === 'function') {
            PT.contextMenuHeader.hide();
          }
        });

        // Ocultar menÃº cuando cambia la selecciÃ³n de fila
        document.addEventListener('pt:selection-changed', () => {
          if (!menu.classList.contains('hidden')) {
            hide();
          }
        });

        const tb = tbodyEl();
        if (tb) {
          // Clic derecho, mantener presionado (tablet) o el botón "⋮" del navbar (UX-06):
          // el mismo menú por los tres caminos.
          const abrirMenuFila = (clickedRow, pos) => {
            // Obtener todas las filas para encontrar el índice
            const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tb);
            const clickedRowIndex = rows.indexOf(clickedRow);

            // Si la fila clickeada no está seleccionada, seleccionarla primero
            if (window.selectedRowIndex !== clickedRowIndex) {
              if (typeof window.selectRow === 'function') {
                window.selectRow(clickedRow, clickedRowIndex);
              }
            }

            // Usar la fila clickeada para el menú contextual
            show({ clientX: pos.x, clientY: pos.y }, clickedRow);
          };
          tb.classList.add('towell-acciones-zona');
          ptAccionesTactiles(tb, '.selectable-row', abrirMenuFila);
        }

        qs('#contextMenuCrear')?.addEventListener('click', () => {
          if (menuRow) ptAbrirDuplicar(menuRow);
          hide();
        });

        qs('#contextMenuEditar')?.addEventListener('click', () => {
          hide();
          if (typeof window.editarFilaSeleccionada === 'function') window.editarFilaSeleccionada();
          else toast('Edición inline no disponible', 'info');
        });

        // Editar marbetes de la fila seleccionada
        qs('#contextMenuMarbetes')?.addEventListener('click', () => {
          const row = menuRow || (window.selectedRowIndex != null
            ? (window.allRows || qsa('.selectable-row', tbodyEl()))[window.selectedRowIndex]
            : null);
          hide();
          if (typeof window.abrirModalMarbetes === 'function') window.abrirModalMarbetes(row);
        });

        // Abrir catÃ¡logo de CodificaciÃ³n en nueva ventana
        qs('#contextMenuCodificacion')?.addEventListener('click', () => {
          hide();
          window.open(PT_BOOT.routes.codificacion, '_blank');
        });

        // Abrir catÃ¡logo de CodificaciÃ³n de Modelos en nueva ventana
        qs('#contextMenuModelos')?.addEventListener('click', () => {
          hide();
          window.open(PT_BOOT.routes.codificacionModelos, '_blank');
        });

        // Redbooth - vincular la fila seleccionada.
        qs('#contextMenuRedbooth')?.addEventListener('click', () => {
          const row = menuRow || (window.selectedRowIndex != null
            ? (window.allRows || qsa('.selectable-row', tbodyEl()))[window.selectedRowIndex]
            : null);
          const registroId = row?.getAttribute('data-id') || '';
          hide();
          if (typeof window.abrirModalRedboothProgramaTejido === 'function') {
            window.abrirModalRedboothProgramaTejido({ registroId });
          }
        });

        // Repaso - abrir modal
        qs('#contextMenuRepaso')?.addEventListener('click', () => {
          const row = menuRow || (window.selectedRowIndex != null ? (window.allRows || qsa('.selectable-row', tbodyEl()))[window.selectedRowIndex] : null);
          hide();
          if (typeof window.abrirModalRepaso === 'function') {
            window.abrirModalRepaso(row);
          }
        });

        // Eliminar registro
        qs('#contextMenuEliminar')?.addEventListener('click', () => {
          const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tbodyEl());
          const selectedRow = (window.selectedRowIndex !== null && window.selectedRowIndex !== undefined && window.selectedRowIndex >= 0)
            ? rows[window.selectedRowIndex]
            : null;
          const row = menuRow || selectedRow;
          hide();
          if (row) {
            const id = row.getAttribute('data-id');
            if (id && typeof window.eliminarRegistro === 'function') {
              window.eliminarRegistro(id);
            } else {
              toast('No se pudo obtener el ID del registro', 'error');
            }
          } else {
            toast('No hay registro seleccionado', 'error');
          }
        });

        // Eliminar en proceso
        qs('#contextMenuEliminarEnProceso')?.addEventListener('click', () => {
          const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tbodyEl());
          const selectedRow = (window.selectedRowIndex !== null && window.selectedRowIndex !== undefined && window.selectedRowIndex >= 0)
            ? rows[window.selectedRowIndex]
            : null;
          const row = menuRow || selectedRow;
          hide();
          if (row) {
            const id = row.getAttribute('data-id');
            if (id && typeof window.eliminarEnProcesoRegistro === 'function') {
              window.eliminarEnProcesoRegistro(id);
            } else {
              toast('No se pudo obtener el ID del registro', 'error');
            }
          } else {
            toast('No hay registro seleccionado', 'error');
          }
        });

        // Desvincular registro
        qs('#contextMenuDesvincular')?.addEventListener('click', () => {
          const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tbodyEl());
          const selectedRow = (window.selectedRowIndex !== null && window.selectedRowIndex !== undefined && window.selectedRowIndex >= 0)
            ? rows[window.selectedRowIndex]
            : null;
          const row = menuRow || selectedRow;
          hide();
          if (row) {
            const id = row.getAttribute('data-id');
            if (id && typeof window.desvincularRegistro === 'function') {
              window.desvincularRegistro(id);
            } else {
              toast('No se pudo obtener el ID del registro', 'error');
            }
          } else {
            toast('No hay registro seleccionado', 'error');
          }
        });
      }

      return { show, hide, getRow: () => menuRow };
    })();

    // =========================
    // Helper para escapar valores CSS
    // =========================
    function escapeCSSValue(value) {
      if (typeof value !== 'string') {
        value = String(value);
      }
      try {
        if (typeof CSS !== 'undefined' && CSS.escape) {
          return CSS.escape(value);
        }
      } catch (e) {
        // Fallback si CSS.escape falla
      }
      // Escape manual para atributos HTML y selectores CSS
      return value.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/'/g, "\\'");
    }

    // =========================
    // Context menu para encabezados
    // =========================
    PT.contextMenuHeader = PT.contextMenuHeader || (function(){
      const menu = qs('#contextMenuHeader');
      if (!menu) return null;

      let menuColumnIndex = null;
      let menuColumnField = null;

      function hide() {
        menu.classList.add('hidden');
        menuColumnIndex = null;
        menuColumnField = null;
      }

      function show(e, columnIndex, columnField) {
        // Cerrar el menú de filas si está abierto
        if (PT.contextMenu && typeof PT.contextMenu.hide === 'function') {
          PT.contextMenu.hide();
        }

        menuColumnIndex = columnIndex;
        menuColumnField = columnField;
        PT.selectedColumn = { index: columnIndex, field: columnField };
        menu.style.left = e.clientX + 'px';
        menu.style.top = e.clientY + 'px';

        const rect = menu.getBoundingClientRect();
        if (rect.right > window.innerWidth) menu.style.left = (e.clientX - rect.width) + 'px';
        if (rect.bottom > window.innerHeight) menu.style.top = (e.clientY - rect.height) + 'px';

        menu.classList.remove('hidden');
      }

      if (!menu.dataset.bound) {
        menu.dataset.bound = '1';

        document.addEventListener('click', (e) => {
          if (!menu.classList.contains('hidden') && !menu.contains(e.target)) {
            hide();
          }
          // También cerrar el menú de filas si está abierto
          if (PT.contextMenu && typeof PT.contextMenu.hide === 'function') {
            const rowMenu = qs('#contextMenu');
            if (rowMenu && !rowMenu.classList.contains('hidden')) {
              PT.contextMenu.hide();
            }
          }
        });

        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && !menu.classList.contains('hidden')) {
            hide();
          }
          // También cerrar el menú de filas con Escape
          if (e.key === 'Escape' && PT.contextMenu && typeof PT.contextMenu.hide === 'function') {
            PT.contextMenu.hide();
          }
        });

        // Agregar listener de contextmenu en los encabezados
        const thead = qs('#mainTable thead');
        if (thead) {
          // Clic derecho o mantener presionado (UX-06); el helper hace el preventDefault.
          thead.classList.add('towell-acciones-zona');
          ptAccionesTactiles(thead, 'th', (th, pos) => {
            const e = { clientX: pos.x, clientY: pos.y };

            // Intentar obtener el índice de varias formas
            let columnIndex = parseInt(th.dataset.index, 10);
            if (Number.isNaN(columnIndex)) {
              // Intentar desde el atributo data-index directamente
              const dataIndex = th.getAttribute('data-index');
              if (dataIndex) {
                columnIndex = parseInt(dataIndex, 10);
              }
            }

            // Si aún no tenemos el índice, intentar desde la clase
            if (Number.isNaN(columnIndex)) {
              const classMatch = th.className.match(/column-(\d+)/);
              if (classMatch) {
                columnIndex = parseInt(classMatch[1], 10);
              }
            }

            let columnField = th.dataset.column;
            if (!columnField) {
              columnField = th.getAttribute('data-column');
            }

            if (Number.isNaN(columnIndex) || !columnField) {
              console.error('[contextMenuHeader] No se pudo obtener índice o campo:', {
                index: columnIndex,
                field: columnField,
                th: th,
                dataset: th.dataset
              });
              return;
            }

            show(e, columnIndex, columnField);
          });
        }

        // Filtrar
        qs('#contextMenuHeaderFiltrar')?.addEventListener('click', () => {
          // Guardar valores antes de ocultar
          const savedIndex = menuColumnIndex;
          const savedField = menuColumnField;
          hide();

          if (savedIndex !== null && savedIndex >= 0 && savedField) {
            openFilterModal(savedIndex, savedField);
          } else {
            console.error('[contextMenuHeader] Filtrar - valores inválidos:', {
              index: savedIndex,
              field: savedField
            });
            toast('No se pudo obtener información de la columna', 'error');
          }
        });

        // Fijar
        qs('#contextMenuHeaderFijar')?.addEventListener('click', () => {
          // Guardar valores antes de ocultar
          const savedIndex = menuColumnIndex;
          hide();

          if (savedIndex !== null && savedIndex >= 0) {
            if (typeof window.togglePinColumn === 'function') {
              window.togglePinColumn(savedIndex);
            } else if (typeof window.pinColumn === 'function') {
              window.pinColumn(savedIndex);
            } else {
              toast('Función de fijar columna no disponible', 'error');
              return;
            }
            // Actualizar iconos después de fijar/desfijar
            setTimeout(() => {
              if (typeof window.updateColumnPinIcons === 'function') {
                window.updateColumnPinIcons();
              }
            }, 100);
          } else {
            console.error('[contextMenuHeader] Fijar - índice inválido:', savedIndex);
            toast('No se pudo obtener el índice de la columna', 'error');
          }
        });

        // Ocultar
        qs('#contextMenuHeaderOcultar')?.addEventListener('click', () => {
          // Guardar valores antes de ocultar
          const savedIndex = menuColumnIndex;
          hide();

          if (savedIndex !== null && savedIndex >= 0) {
            if (typeof window.hideColumn === 'function') {
              window.hideColumn(savedIndex);
            } else {
              toast('Función hideColumn no disponible', 'error');
            }
          } else {
            console.error('[contextMenuHeader] Ocultar - índice inválido:', savedIndex);
            toast('No se pudo obtener el índice de la columna', 'error');
          }
        });
      }

      return {
        show: show,
        hide: hide,
        getColumn: function() {
          return { index: menuColumnIndex, field: menuColumnField };
        }
      };
    })();

    // =========================
    // Función para abrir modal de filtro
    // =========================
    /**
     * Abre el modal de filtro para una columna dada.
     * @param {number} columnIndex - Índice de la columna en el DOM
     * @param {string} columnField - Campo de datos de la columna (data-column)
     */
    function openFilterModal(columnIndex, columnField) {
      // Validar parámetros
      if (columnIndex === null || columnIndex === undefined || columnIndex < 0) {
        console.error('[openFilterModal] Índice de columna inválido:', columnIndex);
        toast('No se pudo obtener el índice de la columna', 'error');
        return;
      }

      if (!columnField || typeof columnField !== 'string') {
        console.error('[openFilterModal] Campo de columna inválido:', columnField);
        toast('No se pudo obtener el campo de la columna', 'error');
        return;
      }

      // Obtener el label de la columna desde el encabezado si está disponible
      let columnLabel = columnField;
      const thead = qs('#mainTable thead');
      if (thead) {
        const th = thead.querySelector(`th[data-index="${columnIndex}"], th.column-${columnIndex}`);
        if (th) {
          const labelText = th.textContent || th.innerText || '';
          if (labelText.trim()) {
            columnLabel = labelText.trim();
          }
        }
      }

      // Obtener todos los valores únicos de la columna
      const tb = tbodyEl();
      if (!tb) {
        toast('No se pudo acceder a los datos de la tabla', 'error');
        return;
      }

      const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tb);
      const values = new Set();
      const valueCounts = new Map();

      rows.forEach(row => {
        const escapedField = escapeCSSValue(columnField);
        const selector = '[data-column="' + escapedField + '"]';
        const cell = row.querySelector(selector);
        if (cell) {
          // IMPORTANTE:
          // - data-value: valor crudo para comparación (usado en el filtro)
          // - textContent: valor formateado para mostrar (más legible)
          const rawValue = cell.dataset.value || '';
          const displayValue = (cell.textContent || cell.innerText || '').trim();

          // Usar el valor crudo (data-value) para la comparación del filtro
          // Si no hay data-value, usar textContent como fallback
          const valueForFilter = rawValue || displayValue;

          if (valueForFilter && String(valueForFilter).trim()) {
            const valueStr = String(valueForFilter).trim();
            // Usar el valor crudo como clave para agrupar
            if (!valueCounts.has(valueStr)) {
              values.add(valueStr);
              valueCounts.set(valueStr, {
                rawValue: valueStr,
                displayValue: displayValue || valueStr,
                count: 0
              });
            }
            // Incrementar contador
            const entry = valueCounts.get(valueStr);
            entry.count = (entry.count || 0) + 1;
            valueCounts.set(valueStr, entry);
          }
        }
      });

      const uniqueValues = Array.from(values).sort();

      if (uniqueValues.length === 0) {
        toast('No hay valores para filtrar en esta columna', 'info');
        return;
      }

      // Crear HTML del modal con checkboxes
      const escapedLabel = String(columnLabel).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      let html = '<div class="text-left">' +
        '<p class="text-sm text-gray-600 mb-4">Filtrar por: <strong>' + escapedLabel + '</strong></p>' +
        '<div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-2">' +
        '<div class="mb-2 pb-2 border-b border-gray-200">' +
        '<input type="text" id="filterSearchInput" placeholder="Buscar..." ' +
        'class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">' +
        '</div>' +
        '<div id="filterCheckboxesContainer" class="space-y-1">';

      uniqueValues.forEach(value => {
        const entry = valueCounts.get(value);
        const count = entry ? entry.count : 0;
        const displayValue = entry ? entry.displayValue : value;

        // Usar escape HTML estándar para el atributo data-value y el value del checkbox
        const escapedValueAttr = String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        const escapedDisplayValue = String(displayValue).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

        html += '<label class="flex items-center justify-between p-2 hover:bg-gray-50 rounded cursor-pointer filter-checkbox-item" data-value="' + escapedValueAttr + '">' +
          '<div class="flex items-center gap-2">' +
          '<input type="checkbox" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 filter-checkbox" ' +
          'value="' + escapedValueAttr + '">' +
          '<span class="text-sm text-gray-700">' + escapedDisplayValue + '</span>' +
          '</div>' +
          '<span class="text-xs text-gray-500">(' + count + ')</span>' +
          '</label>';
      });

      html += '</div></div></div>';

      window.notify.form({
        title: 'Filtrar Columna',
        html: html,
        confirmText: 'Aplicar',
        cancelText: 'Cancelar',
        width: 'lg',
        didOpen: () => {
          // Restaurar estado de checkboxes si hay filtros activos para esta columna
          // Usar la variable global 'filters' directamente
          const currentFilters = (typeof filters !== 'undefined' ? filters : window.filters) || [];
          const activeFiltersForColumn = currentFilters.filter(f => f.column === columnField);
          if (activeFiltersForColumn.length > 0) {
            activeFiltersForColumn.forEach(filter => {
              const filterValue = String(filter.value || '').trim();
              const escapedValueAttr = filterValue.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
              const label = Array.from(document.querySelectorAll('.filter-checkbox-item')).find(l => {
                return l.dataset.value === filterValue || l.dataset.value === escapedValueAttr;
              });
              if (label) {
                const checkbox = label.querySelector('.filter-checkbox');
                if (checkbox) checkbox.checked = true;
              }
            });
          }

          // Búsqueda en tiempo real
          const searchInput = document.getElementById('filterSearchInput');
          const container = document.getElementById('filterCheckboxesContainer');
          const items = container.querySelectorAll('.filter-checkbox-item');

          searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            items.forEach(item => {
              const value = item.dataset.value || '';
              const text = item.textContent || '';
              if (value.toLowerCase().includes(searchTerm) || text.toLowerCase().includes(searchTerm)) {
                item.style.display = '';
              } else {
                item.style.display = 'none';
              }
            });
          });

          // Seleccionar/Deseleccionar todos
          const selectAllBtn = document.createElement('button');
          selectAllBtn.type = 'button';
          selectAllBtn.className = 'text-xs text-blue-600 hover:text-blue-800 mt-2';
          selectAllBtn.textContent = 'Seleccionar todos';
          selectAllBtn.addEventListener('click', () => {
            container.querySelectorAll('.filter-checkbox').forEach(cb => cb.checked = true);
          });
          container.parentElement.insertBefore(selectAllBtn, container);

          const deselectAllBtn = document.createElement('button');
          deselectAllBtn.type = 'button';
          deselectAllBtn.className = 'text-xs text-red-600 hover:text-red-800 ml-2 mt-2';
          deselectAllBtn.textContent = 'Deseleccionar todos';
          deselectAllBtn.addEventListener('click', () => {
            container.querySelectorAll('.filter-checkbox').forEach(cb => cb.checked = false);
          });
          container.parentElement.insertBefore(deselectAllBtn, container);
        },
        preConfirm: () => {
          // ⚡ FIX: Obtener el valor desde data-value en lugar de value para evitar problemas con CSS.escape()
          const checked = Array.from(document.querySelectorAll('.filter-checkbox:checked')).map(cb => {
            // Siempre obtener el valor desde el data-value del label padre para evitar problemas con escape
            const label = cb.closest('label.filter-checkbox-item');
            if (label && label.dataset.value !== undefined) {
              // El data-value ya está en el formato correcto (sin escape de CSS)
              return label.dataset.value;
            }
            // Fallback: usar el value del checkbox directamente
            return cb.value;
          });
          return checked;
        }
      }).then((result) => {
        if (result === null) return;

        const selectedValues = result || [];

        // Aplicar filtro
        if (typeof window.applyColumnFilter === 'function') {
          window.applyColumnFilter(columnField, selectedValues);
        } else {
          // Fallback: aplicar filtro manualmente
          applyColumnFilterManual(columnField, selectedValues);
        }
      });
    }

    // Función auxiliar para aplicar filtro manualmente
    /**
     * Aplica o remueve un filtro de columna con los valores seleccionados.
     * @param {string} columnField - Campo de datos de la columna (data-column)
     * @param {string[]} selectedValues - Valores a filtrar; array vacío remueve el filtro
     */
    function applyColumnFilterManual(columnField, selectedValues) {
      if (!Array.isArray(selectedValues) || selectedValues.length === 0) {
        // Remover filtro
        // Usar la variable global 'filters' directamente
        if (typeof filters !== 'undefined') {
          filters = filters.filter(f => f.column !== columnField);
          window.filters = filters;
        } else if (window.filters) {
          window.filters = window.filters.filter(f => f.column !== columnField);
          filters = window.filters;
        }
        // Aplicar filtros usando el sistema existente para actualizar correctamente
        if (typeof window.applyProgramaTejidoFilters === 'function') {
          window.applyProgramaTejidoFilters();
        } else if (typeof applyProgramaTejidoFilters === 'function') {
          applyProgramaTejidoFilters();
        } else if (typeof window.applyFilters === 'function') {
          window.applyFilters();
        } else if (typeof applyFilters === 'function') {
          applyFilters();
        } else {
          // Fallback: mostrar todas las filas
          const tb = tbodyEl();
          if (tb) {
            qsa('.selectable-row', tb).forEach(row => {
              row.style.display = '';
              row.classList.remove('filter-hidden');
            });
          }
        }
        // Actualizar iconos después de remover filtro
        setTimeout(() => {
          if (typeof window.updateColumnFilterIcons === 'function') {
            window.updateColumnFilterIcons();
          }
        }, 100);
        if (typeof window.updateTotales === 'function') window.updateTotales();
        toast('Filtro removido', 'info');
        return;
      }

      // Agregar/actualizar filtros en el formato del sistema existente
      // El sistema de filtros usa formato: { column: string, value: string, operator: string }
      // IMPORTANTE: filters se declara en state.blade.php como 'let filters = []', no window.filters
      // Usar la variable global 'filters' directamente
      if (typeof filters === 'undefined') {
        window.filters = window.filters || [];
        filters = window.filters;
      }
      if (!filters) filters = [];

      // Remover filtros anteriores de esta columna
      filters = filters.filter(f => f.column !== columnField);

      // Agregar nuevos filtros para cada valor seleccionado
      // IMPORTANTE: Los valores vienen del modal tal cual están en data-value
      // Usar 'equals' para coincidencia exacta (normalizado a lowercase para comparación)
      selectedValues.forEach(value => {
        if (value && String(value).trim()) {
          // Guardar el valor original (sin normalizar) para que coincida con data-value
          const originalValue = String(value).trim();
          filters.push({
            column: columnField,
            value: originalValue, // Guardar valor original, se normalizará en la comparación
            operator: 'equals' // Coincidencia exacta del valor seleccionado
          });
        }
      });

      // Asegurar que window.filters también esté actualizado
      window.filters = filters;

      // filters.blade.php siempre expone applyProgramaTejidoFilters en window.
      window.applyProgramaTejidoFilters();

      // Actualizar iconos después de aplicar filtros
      setTimeout(() => {
        if (typeof window.updateColumnFilterIcons === 'function') {
          window.updateColumnFilterIcons();
        }
      }, 100);

      if (typeof window.updateTotales === 'function') window.updateTotales();
      toast(`Filtro aplicado: ${selectedValues.length} valor(es) seleccionado(s)`, 'success');
    }

    // Función para actualizar iconos de filtro en encabezados
    window.updateColumnFilterIcons = updateColumnFilterIcons;
    function updateColumnFilterIcons() {
      const thead = qs('#mainTable thead');
      if (!thead) return;

      // Obtener todas las columnas con filtros activos
      const filteredColumns = new Set();
      const currentFilters = (typeof filters !== 'undefined' ? filters : window.filters) || [];
      if (Array.isArray(currentFilters)) {
        currentFilters.forEach(f => {
          if (f.column) {
            filteredColumns.add(f.column);
          }
        });
      }

      // Recorrer todos los encabezados
      const allHeaders = thead.querySelectorAll('th[data-column]');
      allHeaders.forEach(th => {
        const columnField = th.dataset.column || th.getAttribute('data-column');
        if (!columnField) return;

        // Buscar o crear el icono de filtro
        let filterIcon = th.querySelector('.column-filter-icon');

        if (filteredColumns.has(columnField)) {
          // La columna tiene filtros activos - mostrar icono
          if (!filterIcon) {
            filterIcon = document.createElement('i');
            filterIcon.className = 'fas fa-filter column-filter-icon text-yellow-400 ml-1 text-xs cursor-pointer hover:text-yellow-500';
            filterIcon.title = 'Columna filtrada - Click para quitar filtro';
            filterIcon.style.cursor = 'pointer';

            // Agregar event listener para eliminar filtro al hacer clic
            filterIcon.addEventListener('click', (e) => {
              e.preventDefault();
              e.stopPropagation();

              // Eliminar filtros de esta columna
              if (typeof filters !== 'undefined') {
                filters = filters.filter(f => f.column !== columnField);
                window.filters = filters;
              } else if (window.filters) {
                window.filters = window.filters.filter(f => f.column !== columnField);
                filters = window.filters;
              }

              // Aplicar filtros actualizados
              if (typeof window.applyProgramaTejidoFilters === 'function') {
                window.applyProgramaTejidoFilters();
              } else if (typeof applyProgramaTejidoFilters === 'function') {
                applyProgramaTejidoFilters();
              }

              // Actualizar iconos después de eliminar filtro
              setTimeout(() => {
                if (typeof window.updateColumnFilterIcons === 'function') {
                  window.updateColumnFilterIcons();
                }
              }, 100);

              if (typeof window.updateTotales === 'function') window.updateTotales();
              toast('Filtro removido de la columna', 'info');
            });

            th.appendChild(filterIcon);
          }
          filterIcon.style.display = 'inline-block';
        } else {
          // La columna no tiene filtros - ocultar icono
          if (filterIcon) {
            filterIcon.style.display = 'none';
          }
        }
      });
    }

    // Función para actualizar iconos de columnas fijadas en encabezados
    window.updateColumnPinIcons = updateColumnPinIcons;
    function updateColumnPinIcons() {
      const thead = qs('#mainTable thead');
      if (!thead) return;

      // Obtener todas las columnas fijadas
      const pinnedIndices = new Set();
      if (typeof window.getPinnedColumns === 'function') {
        const pinned = window.getPinnedColumns();
        if (Array.isArray(pinned)) {
          pinned.forEach(idx => pinnedIndices.add(idx));
        }
      } else if (window.pinnedColumns && Array.isArray(window.pinnedColumns)) {
        window.pinnedColumns.forEach(idx => pinnedIndices.add(idx));
      }

      // Recorrer todos los encabezados
      const allHeaders = thead.querySelectorAll('th[data-index], th[class*="column-"]');
      allHeaders.forEach(th => {
        // Obtener el índice de la columna
        let columnIndex = null;
        if (th.dataset.index) {
          columnIndex = parseInt(th.dataset.index, 10);
        } else {
          const classMatch = th.className.match(/column-(\d+)/);
          if (classMatch) {
            columnIndex = parseInt(classMatch[1], 10);
          }
        }

        if (columnIndex === null || Number.isNaN(columnIndex)) return;

        // Buscar o crear el icono de fijado
        let pinIcon = th.querySelector('.column-pin-icon');

        if (pinnedIndices.has(columnIndex)) {
          // La columna está fijada - mostrar icono blanco
          if (!pinIcon) {
            pinIcon = document.createElement('i');
            pinIcon.className = 'fas fa-thumbtack column-pin-icon text-white ml-1 text-xs cursor-pointer';
            pinIcon.title = 'Desfijar columna';
            pinIcon.dataset.columnIndex = String(columnIndex);
            th.appendChild(pinIcon);
          }
          pinIcon.classList.add('cursor-pointer');
          pinIcon.title = 'Desfijar columna';
          if (!pinIcon.dataset.pinBound) {
            pinIcon.addEventListener('click', (e) => {
              e.preventDefault();
              e.stopPropagation();
              const idx = parseInt(pinIcon.dataset.columnIndex || String(columnIndex), 10);
              if (!Number.isNaN(idx)) {
                if (typeof window.unpinColumn === 'function') {
                  window.unpinColumn(idx);
                } else if (typeof window.togglePinColumn === 'function') {
                  window.togglePinColumn(idx);
                }
                if (typeof window.updateColumnPinIcons === 'function') {
                  window.updateColumnPinIcons();
                }
              }
            });
            pinIcon.dataset.pinBound = '1';
          }
          pinIcon.style.display = 'inline-block';
        } else {
          // La columna no está fijada - ocultar icono
          if (pinIcon) {
            pinIcon.style.display = 'none';
          }
        }
      });
    }

    // Interceptar updatePinnedColumnsPositions para actualizar iconos
    const originalUpdatePinnedColumnsPositions = window.updatePinnedColumnsPositions;
    if (typeof originalUpdatePinnedColumnsPositions === 'function') {
      window.updatePinnedColumnsPositions = function() {
        const result = originalUpdatePinnedColumnsPositions.apply(this, arguments);
        // Actualizar iconos después de actualizar posiciones
        setTimeout(() => {
          if (typeof window.updateColumnPinIcons === 'function') {
            window.updateColumnPinIcons();
          }
        }, 50);
        return result;
      };
    }

    // Exponer función globalmente
    window.applyColumnFilter = applyColumnFilterManual;
    window.updateColumnFilterIcons = updateColumnFilterIcons;
    window.updateColumnPinIcons = updateColumnPinIcons;

    // =========================
    // Acciones
    // =========================
    PT.actions = PT.actions || {};

    PT.actions.descargarPrograma = function descargarPrograma() {
      window.notify.form({
        title: 'Descargar Programa',
        html: `
          <div class="text-left">
            <label class="block text-sm font-medium text-gray-700 mb-2">Fecha Inicial:</label>
            <input type="date" id="fechaInicial"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
          </div>
        `,
        icon: 'question',
        confirmText: 'Descargar',
        cancelText: 'Cancelar',
        didOpen: () => {
          const hoy = new Date().toISOString().split('T')[0];
          qs('#fechaInicial').value = hoy;
          qs('#fechaInicial').focus();
        },
        preConfirm: (ctx) => {
          const fechaInicial = qs('#fechaInicial').value;
          if (!fechaInicial) {
            ctx.error('Por favor seleccione una fecha inicial');
            return false;
          }
          return fechaInicial;
        }
      }).then((fechaInicial) => {
        if (fechaInicial === null) return;

        PT.loader.show();
        fetch('/planeacion/programa-tejido/descargar-programa', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ fecha_inicial: fechaInicial })
        })
        .then(r => r.json())
        .then(data => {
          PT.loader.hide();
          if (data.success) toast('Programa descargado correctamente', 'success');
          else toast(data.message || 'Error al descargar el programa', 'error');
        })
        .catch(() => { PT.loader.hide(); toast('Ocurrió un error al procesar la solicitud', 'error'); });
      });
    };

    PT.actions.abrirNuevo = function abrirNuevo() {
      // Funcionalidad de nuevo eliminada - ahora se usa duplicar/vincular/dividir
      console.warn('La funcionalidad de nuevo registro ha sido reemplazada por duplicar/vincular/dividir');
    };

    PT.actions.eliminarRegistro = function eliminarRegistro(id) {
      const applyDeleteSuccessDom = () => {
            // Buscar la fila por su data-id
            const tb = tbodyEl();
            const rowToDelete = tb ? tb.querySelector(`tr.selectable-row[data-id="${id}"]`) : null;

            if (rowToDelete) {
              // Verificar si la fila eliminada está seleccionada antes de eliminarla
              const selectedRowId = window.selectedRowIndex !== null && window.selectedRowIndex !== undefined && window.selectedRowIndex >= 0
                ? (window.allRows && window.allRows[window.selectedRowIndex]
                    ? window.allRows[window.selectedRowIndex].getAttribute('data-id')
                    : null)
                : null;

              const isSelected = selectedRowId === id;

              // Verificar si el registro eliminado tenía Ultimo=1 antes de eliminarlo
              const ultimoCell = rowToDelete.querySelector('[data-column="Ultimo"]');
              const tieneUltimo = ultimoCell && (
                ultimoCell.textContent.includes('ULTIMO') ||
                ultimoCell.querySelector('strong') ||
                ultimoCell.getAttribute('data-value') === '1' ||
                ultimoCell.getAttribute('data-value') === 'UL'
              );
              const salonId = rowToDelete.querySelector('[data-column="SalonTejidoId"]')?.textContent?.trim();
              const telarId = rowToDelete.querySelector('[data-column="NoTelarId"]')?.textContent?.trim();

              // Obtener el ID del registro eliminado para comparar
              const registroIdEliminado = rowToDelete.getAttribute('data-id');

              // Eliminar la fila del DOM
              rowToDelete.remove();
              window.PT?.filterIndex?.removeRow(id);

              // Si el registro eliminado tenía Ultimo=1, buscar el último registro del mismo telar y actualizarlo
              if (tieneUltimo && salonId && telarId) {
                // Usar requestAnimationFrame para asegurar que el DOM se actualice
                requestAnimationFrame(() => {
                  const tb = tbodyEl();
                  if (!tb) return;

                  // Obtener todas las filas del mismo telar (después de eliminar)
                  const filasMismoTelar = Array.from(tb.querySelectorAll('.selectable-row')).filter(f => {
                    const rowId = f.getAttribute('data-id');
                    const fSalon = f.querySelector('[data-column="SalonTejidoId"]')?.textContent?.trim();
                    const fTelar = f.querySelector('[data-column="NoTelarId"]')?.textContent?.trim();
                    return rowId !== registroIdEliminado &&
                      fSalon === salonId &&
                      fTelar === telarId;
                  });

                  if (filasMismoTelar.length > 0) {
                    // Ordenar por FechaFinal primero, luego por FechaInicio (descendente) para encontrar el último
                    filasMismoTelar.sort((a, b) => {
                      // Intentar primero con FechaFinal, luego con FechaInicio
                      const fechaFinalA = a.querySelector('[data-column="FechaFinal"]')?.textContent?.trim() || '';
                      const fechaFinalB = b.querySelector('[data-column="FechaFinal"]')?.textContent?.trim() || '';
                      const fechaInicioA = a.querySelector('[data-column="FechaInicio"]')?.textContent?.trim() || '';
                      const fechaInicioB = b.querySelector('[data-column="FechaInicio"]')?.textContent?.trim() || '';

                      const fechaA = fechaFinalA || fechaInicioA;
                      const fechaB = fechaFinalB || fechaInicioB;

                      if (!fechaA && !fechaB) return 0;
                      if (!fechaA) return 1;
                      if (!fechaB) return -1;

                      try {
                        // Intentar parsear fecha con formato d/m/Y o d/m/Y H:i
                        let partesA = fechaA.split(' ');
                        let partesB = fechaB.split(' ');
                        let fechaSoloA = partesA[0];
                        let fechaSoloB = partesB[0];

                        const datePartsA = fechaSoloA.split('/');
                        const datePartsB = fechaSoloB.split('/');

                        if (datePartsA.length === 3 && datePartsB.length === 3) {
                          const dateA = new Date(datePartsA[2], datePartsA[1] - 1, datePartsA[0]);
                          const dateB = new Date(datePartsB[2], datePartsB[1] - 1, datePartsB[0]);

                          // Comparar por fecha completa si hay hora
                          if (partesA.length > 1 && partesB.length > 1) {
                            const horaA = partesA[1].split(':');
                            const horaB = partesB[1].split(':');
                            if (horaA.length >= 2 && horaB.length >= 2) {
                              dateA.setHours(parseInt(horaA[0]) || 0, parseInt(horaA[1]) || 0);
                              dateB.setHours(parseInt(horaB[0]) || 0, parseInt(horaB[1]) || 0);
                            }
                          }

                          return dateB.getTime() - dateA.getTime(); // Orden descendente (el más reciente primero)
                        }
                      } catch (e) {
                        // Si hay error, mantener orden original
                        console.warn('Error al parsear fecha para ordenar:', e);
                      }
                      return 0;
                    });

                    // La primera fila después de ordenar es la última (más reciente)
                    const nuevoUltimo = filasMismoTelar[0];
                    if (nuevoUltimo) {
                      const nuevoUltimoCell = nuevoUltimo.querySelector('[data-column="Ultimo"]');
                      if (nuevoUltimoCell) {
                        // Actualizar visualmente el campo Ultimo
                        nuevoUltimoCell.innerHTML = '<strong>ULTIMO</strong>';
                        nuevoUltimoCell.setAttribute('data-value', '1');

                        // Forzar repintado del navegador
                        nuevoUltimoCell.style.visibility = 'hidden';
                        nuevoUltimoCell.offsetHeight; // Trigger reflow
                        nuevoUltimoCell.style.visibility = 'visible';
                      }
                    }
                  }
                });
              }

              // Actualizar window.allRows removiendo la fila eliminada y actualizar índices
              window.allRows = Array.from(tb.querySelectorAll('.selectable-row'));

              // Actualizar data-row-index de todas las filas restantes
              window.allRows.forEach((fila, index) => {
                fila.setAttribute('data-row-index', index);
              });

              // Limpiar la selección si la fila eliminada estaba seleccionada
              if (isSelected) {
                window.selectedRowIndex = -1; // -1, no null: null >= 0 es true en JS

                // También limpiar cualquier referencia visual de selección
                if (tb) {
                  const selectedRows = tb.querySelectorAll('.selectable-row.selected, .selectable-row.bg-blue-200');
                  selectedRows.forEach(row => {
                    row.classList.remove('selected', 'bg-blue-200');
                  });
                }
              }

              // Limpiar de selectedRowsIds si existe
              if (window.selectedRowsIds && typeof window.selectedRowsIds.delete === 'function') {
                window.selectedRowsIds.delete(id);
              }

              // Actualizar refreshAllRows para sincronizar el estado (esto también actualizará window.allRows)
              if (typeof refreshAllRows === 'function') {
                refreshAllRows();
              }

              // Actualizar los totales después de actualizar las filas
              if (typeof window.updateTotales === 'function') {
                window.updateTotales();
              }

              // Mostrar mensaje de éxito
              toast('Registro eliminado correctamente', 'success');
            } else {
              // Si no se encuentra la fila en el DOM, mostrar mensaje de éxito de todos modos
              toast('Registro eliminado correctamente', 'success');

              // Actualizar window.allRows por si acaso
              if (typeof refreshAllRows === 'function') {
                refreshAllRows();
              }
            }
      };

      const performDeleteFetch = () => fetch(`/planeacion/programa-tejido/${id}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
        }
      }).then(r => r.json().catch(() => ({})));

      const handleDeleteFailure = (data) => {
        if (data && data.codigo === 'registro_no_encontrado') {
          const ghost = document.querySelector(`tr.selectable-row[data-id="${id}"]`);
          if (ghost) {
            ghost.remove();
          }
          if (typeof refreshAllRows === 'function') refreshAllRows();
        }
        toast(data?.message || 'No se pudo eliminar el registro', data?.codigo === 'registro_no_encontrado' ? 'warning' : 'error');
      };

      const doDeleteWithGlobalLoader = () => {
        const rowCheck = document.querySelector(`tr.selectable-row[data-id="${id}"]`);
        if (!rowCheck) {
          console.warn('[PT eliminar] ADVERTENCIA: la fila con data-id=' + id + ' no existe en DOM. Esto puede indicar un ID incorrecto.');
        }
        PT.loader.show();
        performDeleteFetch()
          .then(data => {
            PT.loader.hide();
            if (!data.success) {
              handleDeleteFailure(data);
              return;
            }
            applyDeleteSuccessDom();
          })
          .catch(() => {
            PT.loader.hide();
            toast('Ocurrió un error al procesar la solicitud', 'error');
          });
      };

      if (typeof window.notify === 'undefined') {
        if (confirm('¿Eliminar registro? Esta acción no se puede deshacer.')) doDeleteWithGlobalLoader();
        return;
      }

      window.notify.form({
        title: '¿Eliminar registro?',
        html: '',
        icon: 'warning',
        confirmText: 'Sí, eliminar',
        cancelText: 'Cancelar',
        danger: true,
        width: 'md',
        preConfirm: async (ctx) => {
          const rowCheck = document.querySelector(`tr.selectable-row[data-id="${id}"]`);
          if (!rowCheck) {
            console.warn('[PT eliminar] ADVERTENCIA: la fila con data-id=' + id + ' no existe en DOM. Esto puede indicar un ID incorrecto.');
          }
          try {
            const data = await performDeleteFetch();
            if (!data.success) {
              if (data.codigo === 'registro_no_encontrado') {
                const ghost = document.querySelector(`tr.selectable-row[data-id="${id}"]`);
                if (ghost) {
                  ghost.remove();
                }
                if (typeof refreshAllRows === 'function') refreshAllRows();
                // Cerrar el modal sin mensaje de error: ya no existe (p. ej. doble DELETE o otra pestaña).
                return { ok: true, alreadyGone: true };
              }
              ctx.error(data.message || 'No se pudo eliminar el registro');
              return false;
            }
            return { ok: true };
          } catch (e) {
            ctx.error('Ocurrió un error de conexión.');
            return false;
          }
        }
      }).then(result => {
        if (!result || !result.ok) return;
        if (result.alreadyGone) {
          if (typeof refreshAllRows === 'function') refreshAllRows();
          if (typeof window.updateTotales === 'function') window.updateTotales();
          toast('El registro ya no existía en el programa. La vista se sincronizó.', 'info');
          return;
        }
        applyDeleteSuccessDom();
      });
    };

    window.descargarPrograma = PT.actions.descargarPrograma;

    window.abrirNuevo = PT.actions.abrirNuevo;
    window.eliminarRegistro = PT.actions.eliminarRegistro;

    // =========================
    // Eliminar en proceso
    // =========================
    window.eliminarEnProcesoRegistro = async function eliminarEnProcesoRegistro(id) {
      const applyEnProcesoSuccess = async (data) => {
        const tb = tbodyEl();
        const rowToDelete = tb ? tb.querySelector(`tr.selectable-row[data-id="${id}"]`) : null;
        if (rowToDelete) {
          rowToDelete.remove();
          window.PT?.filterIndex?.removeRow(id);
          window.selectedRowIndex = -1; // -1, no null: null >= 0 es true en JS
        }
        if (data.registros_ids && Array.isArray(data.registros_ids) && data.registros_ids.length > 0) {
          await actualizarRegistrosVinculados(data.registros_ids, null);
        }
        if (typeof refreshAllRows === 'function') refreshAllRows();
        if (typeof window.updateTotales === 'function') window.updateTotales();
        toast(data.message || 'Registro en proceso eliminado y telar recalculado', 'success');
      };

      const performEnProcesoFetch = () =>
        fetch(`/planeacion/programa-tejido/${id}/en-proceso`, {
          method: 'DELETE',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
          }
        }).then(r => r.json().catch(() => ({})));

      const doDeleteWithGlobalLoader = async () => {
        PT.loader.show();
        try {
          const data = await performEnProcesoFetch();
          PT.loader.hide();
          if (!data.success) {
            toast(data.message || 'No se pudo eliminar el registro en proceso', 'error');
            return;
          }
          await applyEnProcesoSuccess(data);
        } catch (err) {
          PT.loader.hide();
          toast('Ocurrió un error al procesar la solicitud', 'error');
        }
      };

      if (typeof window.notify === 'undefined') {
        if (confirm('¿Eliminar el registro en proceso? El siguiente registro pasará a ser el activo y el telar será recalculado. Esta acción no se puede deshacer.')) {
          doDeleteWithGlobalLoader();
        }
        return;
      }

      window.notify.form({
        title: '¿Eliminar registro en proceso?',
        html: 'El siguiente registro en la cola pasará a estar en proceso y se recalcularán las fechas del telar.',
        icon: 'warning',
        confirmText: 'Sí, eliminar',
        cancelText: 'Cancelar',
        danger: true,
        width: 'md',
        preConfirm: async (ctx) => {
          try {
            const data = await performEnProcesoFetch();
            if (!data.success) {
              if (data.codigo === 'registro_no_encontrado') {
                const ghost = document.querySelector(`tr.selectable-row[data-id="${id}"]`);
                if (ghost) {
                  ghost.remove();
                }
                if (typeof refreshAllRows === 'function') refreshAllRows();
                return { ok: true, alreadyGone: true };
              }
              ctx.error(data.message || 'No se pudo eliminar el registro en proceso');
              return false;
            }
            return { ok: true, payload: data };
          } catch (e) {
            ctx.error('Ocurrió un error de conexión.');
            return false;
          }
        }
      }).then(async (r) => {
        if (!r || !r.ok) return;
        if (r.alreadyGone) {
          if (typeof refreshAllRows === 'function') refreshAllRows();
          if (typeof window.updateTotales === 'function') window.updateTotales();
          toast('El registro ya no existía en el programa. La vista se sincronizó.', 'info');
          return;
        }
        await applyEnProcesoSuccess(r.payload);
      });
    };

    // =========================
    // Desvincular registro
    // =========================
    window.desvincularRegistro = async function desvincularRegistro(id) {
      const doDesvincular = async () => {
        PT.loader.show();
        try {
          const response = await fetch(`/planeacion/programa-tejido/${id}/desvincular`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
            }
          });

          const data = await response.json();
          PT.loader.hide();

          if (data.success) {
            // Actualizar registros sin recargar usando la misma función que para vincular
            if (data.registros_ids && Array.isArray(data.registros_ids) && data.registros_ids.length > 0) {
              await actualizarRegistrosVinculados(data.registros_ids, null);
            }

            toast(data.message || 'Registro desvinculado correctamente', 'success');
          } else {
            toast(data.message || 'No se pudo desvincular el registro', 'error');
          }
        } catch (error) {
          PT.loader.hide();
          toast('Ocurrió un error al procesar la solicitud', 'error');
        }
      };

      if (typeof window.notify === 'undefined') {
        if (confirm('¿Desvincular este registro? Se eliminará su relación con otros registros.')) {
          doDesvincular();
        }
        return;
      }

      window.notify.confirm({
        title: '¿Desvincular registro?',
        text: 'Se eliminará la relación con otros registros vinculados.',
        icon: 'warning',
        confirmText: 'Sí, desvincular',
        cancelText: 'Cancelar',
        confirmColor: '#9333ea',
      }).then(ok => { if (ok) doDesvincular(); });
    };

    // =========================
    // Editar fila seleccionada
    // =========================
    window.editarFilaSeleccionada = function() {
      const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row');
      if (window.selectedRowIndex === null || window.selectedRowIndex === undefined || window.selectedRowIndex < 0) {
        toast('Por favor, selecciona un registro primero', 'info');
        return;
      }

      const row = rows[window.selectedRowIndex];
      if (!row) {
        toast('No se pudo encontrar el registro seleccionado', 'error');
        return;
      }

      // Activar ediciÃ³n inline
      if (typeof window.toggleInlineEditMode === 'function') {
        // Verificar si el modo inline ya estÃ¡ activado mirando la clase en el tbody
        const tb = qs('#mainTable tbody');
        const isActive = tb && tb.classList.contains('inline-edit-mode');

        // Si no estÃ¡ activado, activarlo
        if (!isActive) {
          window.toggleInlineEditMode();
        }

        // Activar ediciÃ³n en todas las celdas editables de esta fila
        if (typeof window.enableInlineEditForAllCellsInRow === 'function') {
          window.enableInlineEditForAllCellsInRow(row);
        } else {
          // Fallback: activar manualmente cada celda editable
          const editableCells = row.querySelectorAll('td[data-column]');
          editableCells.forEach(cell => {
            const col = cell.getAttribute('data-column');
            if (col && typeof uiInlineEditableFields !== 'undefined' && uiInlineEditableFields[col]) {
              if (typeof window.enableInlineEditForCell === 'function') {
                window.enableInlineEditForCell(cell);
              }
            }
          });
        }

        if (typeof window.showToast === 'function') {
          window.showToast('Edición activada en la fila seleccionada', 'success');
        }
      } else {
        toast('Edición inline no disponible', 'error');
      }
    };

    // =========================
    // Drag & Drop
    // =========================
    PT.dragdrop = PT.dragdrop || (function(){
      const state = {
        enabled: false,
        draggedRow: null,
        origin: { telar:'', salon:'', cambioHilo:'', posicion: null },
        lastOverRow: null,
        lastOverTelar: null,
        lastOverIsBefore: false,
        lastDragOverTime: 0,
        blockedReason: null,
        telarCache: { telar: null, rows: null, indexMap: null, lastEnProceso: -1 },
      };

      function resetTelarCache() {
        state.telarCache = { telar: null, rows: null, indexMap: null, lastEnProceso: -1 };
      }

      function buildTelarCache(telarId) {
        const tb = tbodyEl();
        if (!tb) return { rows: [], indexMap: new Map(), lastEnProceso: -1 };

        const norm = normalizeTelarValue(telarId);
        if (state.telarCache.telar === norm && state.telarCache.rows && state.telarCache.indexMap) {
          return state.telarCache;
        }

        const rows = [];
        const indexMap = new Map();
        let lastEnProceso = -1;

        for (const r of tb.querySelectorAll('.selectable-row')) {
          if (r === state.draggedRow) continue;
          const meta = rowMeta(r);
          if (!isSameTelar(meta.telar, telarId)) continue;
          if (meta.enProceso) lastEnProceso = rows.length;
          indexMap.set(r, rows.length);
          rows.push(r);
        }

        state.telarCache = { telar: norm, rows, indexMap, lastEnProceso };
        return state.telarCache;
      }

      function calcTargetPosition(telarId, targetRow, isBefore) {
        const cache = buildTelarCache(telarId);
        if (!targetRow) return cache.rows.length;

        const targetPos = rowMeta(targetRow).posicion;
        if (Number.isFinite(targetPos)) {
          let newPos = isBefore ? (targetPos - 1) : targetPos;
          const originPos = state.origin.posicion;
          if (isSameTelar(telarId, state.origin.telar) && Number.isFinite(originPos) && originPos < targetPos) {
            newPos -= 1;
          }
          return Math.max(0, Math.min(newPos, cache.rows.length));
        }

        const idx = cache.indexMap?.get(targetRow);
        if (typeof idx === 'number') {
          return isBefore ? idx : idx + 1;
        }

        return cache.rows.length;
      }

      function findClosestRow(tb, y) {
        const rows = Array.from(tb.querySelectorAll('.selectable-row'));
        let closest = null;
        let best = Infinity;
        for (const r of rows) {
          if (r === state.draggedRow) continue;
          const rect = r.getBoundingClientRect();
          const dist = Math.abs(y - (rect.top + rect.height / 2));
          if (dist < best) { best = dist; closest = r; }
        }
        return closest;
      }

      function setRowDraggable(row, draggable) {
        if (!row) return;
        row.draggable = !!draggable;
        row.classList.toggle('cursor-move', !!draggable);
        row.classList.toggle('cursor-not-allowed', !draggable);
        row.style.opacity = (!draggable && rowMeta(row).enProceso) ? '0.6' : '';
      }

      function enable() {
        const tb = tbodyEl();
        if (!tb) return;

        state.enabled = true;
        window.dragDropMode = true;
        setDragDropButtonGray(true);

        refreshAllRows();
        if (typeof window.deselectRow === 'function') window.deselectRow();

        // Remover listeners de selecciÃ³n antes de activar drag and drop
        // La seleccion es un listener delegado que se desactiva solo mirando
        // window.dragDropMode; no hay nada que desenganchar por fila.
        window.allRows.forEach(row => setRowDraggable(row, !rowMeta(row).enProceso));

        if (!tb.dataset.ddBound) {
          tb.dataset.ddBound = '1';
          tb.addEventListener('dragstart', onDragStart);
          tb.addEventListener('dragover',  onDragOver);
          tb.addEventListener('drop',      onDrop);
          tb.addEventListener('dragend',   onDragEnd);
        }

        toast('Modo arrastrar activado<br>Arrastra las filas para reorganizarlas', 'info');
      }

      function disable() {
        const tb = tbodyEl();
        if (!tb) return;

        state.enabled = false;
        window.dragDropMode = false;
        setDragDropButtonGray(false);

        refreshAllRows();

        // Remover todos los listeners anteriores y restaurar los de selecciÃ³n
        window.allRows.forEach((row) => {
          row.draggable = false;
          row.classList.remove('cursor-move', 'cursor-not-allowed');
          row.style.opacity = '';
        });

        toast('Modo arrastrar desactivado', 'info');
      }

      function toggle() { state.enabled ? disable() : enable(); }
      function isEnabled(){ return !!state.enabled; }

      function decideTargetTelarFromDOM() {
        return state.lastOverTelar || state.origin.telar;
      }

      function clearVisualRows() {
        (window.allRows || []).forEach(r =>
          r.classList.remove('drag-over', 'drag-over-warning', 'drop-not-allowed', 'dd-drop-before', 'dd-drop-after')
        );
      }

      function onDragStart(e) {
        if (!state.enabled) return;

        const row = e.target.closest('.selectable-row');
        if (!row) return;

        const meta = rowMeta(row);
        if (meta.enProceso) {
          e.preventDefault();
          toast('No se puede mover un registro en proceso', 'error');
          return false;
        }

        state.draggedRow = row;
        state.origin = { telar: meta.telar, salon: meta.salon, cambioHilo: meta.cambioHilo, posicion: meta.posicion };
        state.lastOverRow = null;
        state.lastOverTelar = null;
        state.lastOverIsBefore = false;
        state.lastDragOverTime = 0;
        state.blockedReason = null;
        resetTelarCache();

        if (typeof window.deselectRow === 'function') window.deselectRow();

        row.classList.add('dragging');

        // Permitir tanto move como copy para permitir drag and drop entre telares
        e.dataTransfer.effectAllowed = 'all';
        e.dataTransfer.setData('text/plain', row.getAttribute('data-id') || '');
      }

      function onDragOver(e) {
        if (!state.enabled) return;
        if (!state.draggedRow) return;

        e.preventDefault();
        e.stopPropagation();

        const now = performance.now();
        if (now - state.lastDragOverTime < 16) return false;
        state.lastDragOverTime = now;

        const tb = tbodyEl();
        if (!tb) return false;

        clearVisualRows();

        let targetRow = e.target.closest('.selectable-row');
        if (!targetRow) {
          targetRow = findClosestRow(tb, e.clientY);
        }

        if (!targetRow || targetRow === state.draggedRow) {
          state.blockedReason = null;
          return false;
        }

        const rect = targetRow.getBoundingClientRect();
        const isBefore = e.clientY < (rect.top + rect.height / 2);
        const targetTelar = rowMeta(targetRow).telar;

        state.lastOverRow = targetRow;
        state.lastOverTelar = targetTelar;
        state.lastOverIsBefore = isBefore;

        const targetPosition = calcTargetPosition(targetTelar, targetRow, isBefore);
        const cache = buildTelarCache(targetTelar);
        const minAllowed = cache.lastEnProceso !== -1 ? (cache.lastEnProceso + 1) : 0;

        if (targetPosition < minAllowed) {
          state.blockedReason = 'No se puede colocar antes de un registro en proceso.';
          e.dataTransfer.dropEffect = 'none';
          targetRow.classList.add('drop-not-allowed');
        } else {
          state.blockedReason = null;
          // Permitir drop tanto en el mismo telar como en telares diferentes
          e.dataTransfer.dropEffect = isSameTelar(state.origin.telar, targetTelar) ? 'move' : 'move';
          targetRow.classList.add(isSameTelar(state.origin.telar, targetTelar) ? 'drag-over' : 'drag-over-warning');
        }

        targetRow.classList.add(isBefore ? 'dd-drop-before' : 'dd-drop-after');
        return false;
      }

      async function onDrop(e) {
        if (!state.enabled) return;
        if (!state.draggedRow) return;

        e.preventDefault();
        e.stopPropagation();

        const registroId = e.dataTransfer.getData('text/plain') || state.draggedRow.getAttribute('data-id');
        if (!registroId) {
          toast('Error: No se pudo obtener el ID del registro', 'error');
          clearVisualRows();
          return false;
        }

        const targetTelar = decideTargetTelarFromDOM();
        const targetRow = state.lastOverRow;
        const isBefore = !!state.lastOverIsBefore;

        if (state.blockedReason) {
          toast(state.blockedReason, 'error');
          clearVisualRows();
          return false;
        }

        // Permitir drag and drop tanto dentro del mismo telar como entre telares diferentes
        if (isSameTelar(targetTelar, state.origin.telar)) {
          // Movimiento dentro del mismo telar
          const tb = tbodyEl();
          if (!tb) return false;

          refreshAllRows();
          const telarRows = window.allRows.filter(r => isSameTelar(rowMeta(r).telar, state.origin.telar));
          if (telarRows.length < 2) {
            toast('Se requieren al menos dos registros para reordenar la prioridad', 'info');
            clearVisualRows();
            return false;
          }

          const newPos = calcTargetPosition(state.origin.telar, targetRow, isBefore);
          const originPos = Number.isFinite(state.origin.posicion) ? (state.origin.posicion - 1) : null;

          if (originPos !== null && originPos === newPos) {
            toast('El registro ya está en esa posición', 'info');
            clearVisualRows();
            return false;
          }

          PT.loader.show();
          try {
            const resp = await fetch(`/planeacion/programa-tejido/${registroId}/prioridad/mover`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
              },
              body: JSON.stringify({ new_position: newPos })
            });
            const data = await resp.json();
            PT.loader.hide();

            if (!data.success) {
              toast(data.message || 'No se pudo actualizar la prioridad', 'error');
              clearVisualRows();
              return false;
            }

            toast(`Prioridad actualizada<br>${data.cascaded_records || 0} registro(s) recalculado(s)`, 'success');

            if (typeof window.updateTableAfterDragDrop === 'function') {
              window.updateTableAfterDragDrop(data.detalles, registroId, data.updates || {});
            }
          } catch (err) {
            PT.loader.hide();
            // Sin este aviso el fallo es invisible: la fila queda movida en pantalla
            // y el usuario asume que se guardo. Pasa con 419 (CSRF vencido), 500, o
            // cuando el servidor responde HTML y resp.json() revienta.
            window.notify.alert('No se pudo guardar la prioridad: ' + (err.message || 'Error desconocido'), 'Error', 'error');
            clearVisualRows();
          }
          return false;
        }

        // Movimiento entre telares diferentes - permitir sin bloqueo
        const cache = buildTelarCache(targetTelar);
        const minAllowed = cache.lastEnProceso !== -1 ? (cache.lastEnProceso + 1) : 0;
        let targetPosition = calcTargetPosition(targetTelar, targetRow, isBefore);
        targetPosition = Math.max(minAllowed, Math.min(targetPosition, cache.rows?.length || 0));

        const targetSalon = targetRow ? rowMeta(targetRow).salon : '';
        await procesarMovimientoOtroTelar(registroId, targetTelar, targetPosition, targetSalon);
        return false;
      }

      async function procesarMovimientoOtroTelar(registroId, nuevoTelar, targetPosition, nuevoSalon = '') {
        refreshAllRows();

        if (!nuevoSalon) {
          const sample = window.allRows.find(r => isSameTelar(rowMeta(r).telar, nuevoTelar));
          nuevoSalon = sample ? rowMeta(sample).salon : '';
        }

        // Obtener salón origen
        const salonOrigen = state.origin.salon || '';
        const mismoSalon = salonOrigen && nuevoSalon && salonOrigen.trim() === nuevoSalon.trim();

        PT.loader.show();
        try {
          const verResp = await fetch(`/planeacion/programa-tejido/${registroId}/verificar-cambio-telar`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ nuevo_salon: nuevoSalon, nuevo_telar: nuevoTelar })
          });

          const verificacion = await verResp.json();
          PT.loader.hide();

          if (!verificacion?.puede_mover) {
            const esc = escapeHtmlPtModal;
            window.notify.html(`
                <div class="text-left">
                  <p class="mb-3">${esc(verificacion?.mensaje || 'Validación fallida')}</p>
                  <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm">
                    <p><span class="font-medium">Clave Modelo:</span> ${esc(verificacion?.clave_modelo || 'N/A')}</p>
                    <p><span class="font-medium">Telar Destino:</span> ${esc(verificacion?.telar_destino || nuevoTelar)} (${esc(verificacion?.salon_destino || nuevoSalon || 'N/A')})</p>
                  </div>
                </div>
              `, 'No se puede cambiar de telar', 'error');
            clearVisualRows();
            return;
          }

          // Solo mostrar confirmación si el salón es diferente
          let confirmado = true; // Por defecto, confirmado

          if (!mismoSalon) {
            // Si el salón es diferente, mostrar la alerta de confirmación
            const esc = escapeHtmlPtModal;
            confirmado = await window.notify.confirm({
              icon: 'warning',
              title: 'Cambio de Telar/Salón',
              html: `
                <div class="text-left">
                  <p class="mb-2">${esc(verificacion?.mensaje || 'Se aplicará el cambio de telar')}</p>
                  <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-sm">
                    <p><span class="font-medium">Origen:</span> Telar ${esc(verificacion?.telar_origen || state.origin.telar)} (Salón ${esc(verificacion?.salon_origen || state.origin.salon || 'N/A')})</p>
                    <p><span class="font-medium">Destino:</span> Telar ${esc(verificacion?.telar_destino || nuevoTelar)} (Salón ${esc(verificacion?.salon_destino || nuevoSalon || 'N/A')})</p>
                  </div>
                </div>
              `,
              confirmText: 'Sí, cambiar de telar',
              cancelText: 'Cancelar',
              confirmColor: '#3b82f6',
            });
          }

          if (!confirmado) {
            toast('Operación cancelada', 'info');
            clearVisualRows();
            return;
          }

          PT.loader.show();
          const cambioResp = await fetch(`/planeacion/programa-tejido/${registroId}/cambiar-telar`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
              nuevo_salon: nuevoSalon,
              nuevo_telar: nuevoTelar,
              target_position: targetPosition
            })
          });

          const cambio = await cambioResp.json();
          PT.loader.hide();

          if (!cambio?.success) {
            window.notify.alert(cambio?.message || 'No se pudo cambiar de telar', 'Error al cambiar de telar', 'error');
            clearVisualRows();
            return;
          }

          toast(cambio.message || 'Telar actualizado correctamente', 'success');
          if (typeof window.updateTableAfterDragDrop === 'function') {
            window.updateTableAfterDragDrop(cambio.detalles, cambio.registro_id || registroId, cambio.updates || {});
          }
        } catch (err) {
          PT.loader.hide();
          window.notify.alert('Ocurrió un error al procesar el cambio de telar: ' + (err.message || 'Error desconocido'), 'Error', 'error');
          clearVisualRows();
        }
      }

      function onDragEnd() {
        if (!state.draggedRow) return;

        state.draggedRow.classList.remove('dragging');
        state.draggedRow.style.opacity = '';
        clearVisualRows();

        state.draggedRow = null;
        state.lastOverRow = null;
        state.lastOverTelar = null;
        state.lastOverIsBefore = false;
        state.lastDragOverTime = 0;
        state.blockedReason = null;
        resetTelarCache();
      }

      return { toggle, enable, disable, isEnabled };
    })();

    // Exponer toggle para botón navbar
    window.toggleDragDropMode = function () {
      PT.dragdrop.toggle();
      setDragDropButtonGray(PT.dragdrop.isEnabled());
    };

    // =========================
    // Balance button
    // =========================
    function updateBalanceBtnState() {
      const btn = document.querySelector('a[title="Balancear"]');
      if (!btn) return;

      const disable = () => {
        btn.classList.remove('bg-green-500', 'hover:bg-green-600', 'focus:ring-teal-400');
        btn.classList.add('bg-gray-400', 'opacity-50', 'cursor-not-allowed', 'pointer-events-none');
      };
      const enable = () => {
        btn.classList.add('bg-green-500', 'hover:bg-green-600', 'focus:ring-teal-400');
        btn.classList.remove('bg-gray-400', 'opacity-50', 'cursor-not-allowed', 'pointer-events-none');
      };

      const tb = tbodyEl();
      const rows = window.allRows?.length ? window.allRows : (tb ? qsa('.selectable-row', tb) : []);

      // Validar que selectedRowIndex sea válido
      const selectedIndex = window.selectedRowIndex;
      const isValidIndex = selectedIndex !== null && selectedIndex !== undefined && selectedIndex >= 0 && selectedIndex < rows.length;
      const row = isValidIndex ? rows[selectedIndex] : null;
      const ord = row?.getAttribute('data-ord-compartida');

      // Habilitar solo si hay una fila seleccionada con OrdCompartida
      if (ord && ord.trim() !== '') {
        enable();
      } else {
        disable();
      }
    }

    document.addEventListener('pt:selection-changed', updateBalanceBtnState);

    // =========================
    // Selección múltiple para vincular registros existentes
    // =========================
    window.selectedRowsIds = window.selectedRowsIds || new Set();
    window.selectedRowsOrder = window.selectedRowsOrder || []; // Array para mantener el orden de selección
    window.multiSelectMode = false;

    function toggleMultiSelectMode() {
      window.multiSelectMode = !window.multiSelectMode;
      const btn = qs('#btnVincularExistentes');
      if (btn) {
        if (window.multiSelectMode) {
          btn.classList.remove('bg-blue-500', 'hover:bg-blue-600');
          btn.classList.add('bg-blue-700', 'ring-2', 'ring-blue-300');
          btn.disabled = false; // Mantener habilitado para permitir cancelar
          btn.title = 'Modo selección múltiple activado. Haz click en las filas para seleccionarlas. Click aquí sin selecciones para cancelar.';
          updateVincularButtonState(); // Actualizar estado segÃºn selecciÃ³n
          updateSelectedRowsVisual(); // Actualizar visualizaciÃ³n de filas bloqueadas
        } else {
          btn.classList.remove('bg-blue-700', 'ring-2', 'ring-blue-300', 'bg-blue-300', 'cursor-not-allowed');
          btn.classList.add('bg-blue-500', 'hover:bg-blue-600');
          btn.disabled = false; // Habilitar para activar modo de nuevo
          btn.title = 'Vincular registros existentes - Click para activar modo selección múltiple';
          window.selectedRowsIds.clear();
          window.selectedRowsOrder = [];
          updateSelectedRowsVisual();
        }
      }
      toast(window.multiSelectMode ? 'Modo selección múltiple activado. Selecciona al menos 2 registros. Si el primer registro tiene OrdCompartida, se usará ese para vincular los demás. Click sin selecciones para cancelar.' : 'Modo selección múltiple desactivado', 'info');
    }

    function toggleRowSelection(row) {
      if (!window.multiSelectMode) return;

      const id = row.getAttribute('data-id');
      if (!id) return;

      const ordCompartida = row.getAttribute('data-ord-compartida');
      const tieneOrdCompartida = ordCompartida && ordCompartida.trim() !== '';

      // Si NO hay ningún registro seleccionado todavía, permitir seleccionar cualquier registro
      // (con o sin OrdCompartida) - el primero seleccionado determinarÃ¡ las reglas
      if (window.selectedRowsIds.size === 0) {
        // Permitir seleccionar cualquier registro como primer registro
      } else {
        // Ya hay al menos un registro seleccionado, aplicar validaciones
        // Obtener el OrdCompartida del primer registro seleccionado (si existe)
        // Usar el array de orden para asegurar que el primero es realmente el primero seleccionado
        let primerOrdCompartida = null;
        if (window.selectedRowsOrder.length > 0) {
          const primerId = window.selectedRowsOrder[0];
          const primerRow = window.allRows?.find(r => r.getAttribute('data-id') === primerId);
          if (primerRow) {
            const primerOrd = primerRow.getAttribute('data-ord-compartida');
            if (primerOrd && primerOrd.trim() !== '') {
              primerOrdCompartida = primerOrd.trim();
            }
          }
        }

        // Validación solo si ya hay registros seleccionados:
        // - Si el primer registro NO tiene OrdCompartida y este registro Sí tiene, no permitir
        // - Si el primer registro Sí tiene OrdCompartida y este NO tiene, permitir (usará el del primero)
        // - Si ambos tienen OrdCompartida pero son diferentes, no permitir
        if (!primerOrdCompartida && tieneOrdCompartida) {
          toast('No se puede vincular: El primer registro seleccionado no tiene OrdCompartida, pero este registro sí lo tiene', 'warning');
          return;
        }

        if (primerOrdCompartida && tieneOrdCompartida && ordCompartida.trim() !== primerOrdCompartida) {
          toast(`No se puede vincular: Este registro tiene OrdCompartida ${ordCompartida.trim()}, pero el primer registro tiene ${primerOrdCompartida}`, 'warning');
          return;
        }
      }

      if (window.selectedRowsIds.has(id)) {
        window.selectedRowsIds.delete(id);
        // Remover del array de orden
        window.selectedRowsOrder = window.selectedRowsOrder.filter(selectedId => selectedId !== id);
        row.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-500', 'text-white');
      } else {
        window.selectedRowsIds.add(id);
        // Agregar al array de orden (solo si no está ya)
        if (!window.selectedRowsOrder.includes(id)) {
          window.selectedRowsOrder.push(id);
        }
        row.classList.add('ring-2', 'ring-blue-500', 'bg-blue-500', 'text-white');
      }

      // Actualizar visualización de todos los registros para reflejar el nuevo estado de bloqueo
      updateSelectedRowsVisual();
      updateVincularButtonState();
    }

    function updateSelectedRowsVisual() {
      const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row');

      // Obtener el OrdCompartida del primer registro seleccionado (si existe)
      // Usar el array de orden para asegurar que el primero es realmente el primero seleccionado
      let primerOrdCompartida = null;
      if (window.selectedRowsOrder.length > 0) {
        const primerId = window.selectedRowsOrder[0];
        const primerRow = rows.find(r => r.getAttribute('data-id') === primerId);
        if (primerRow) {
          const primerOrd = primerRow.getAttribute('data-ord-compartida');
          if (primerOrd && primerOrd.trim() !== '') {
            primerOrdCompartida = primerOrd.trim();
          }
        }
      }

      rows.forEach(row => {
        const id = row.getAttribute('data-id');
        const ordCompartida = row.getAttribute('data-ord-compartida');
        const tieneOrdCompartida = ordCompartida && ordCompartida.trim() !== '';
        let debeBloquear = false;
        let mensajeBloqueo = '';

        if (window.multiSelectMode && window.selectedRowsIds.size > 0) {
          // Solo aplicar bloqueo si ya hay al menos un registro seleccionado
          if (!primerOrdCompartida && tieneOrdCompartida) {
            debeBloquear = true;
            mensajeBloqueo = 'No se puede vincular: El primer registro seleccionado no tiene OrdCompartida';
          } else if (primerOrdCompartida && tieneOrdCompartida && ordCompartida.trim() !== primerOrdCompartida) {
            debeBloquear = true;
            mensajeBloqueo = `No se puede vincular: Este registro tiene OrdCompartida ${ordCompartida.trim()}, pero el primer registro tiene ${primerOrdCompartida}`;
          }
        }

        if (debeBloquear) {
          row.classList.add('opacity-50', 'cursor-not-allowed');
          row.classList.remove('hover:bg-blue-50');
          row.title = mensajeBloqueo;
        } else {
          row.classList.remove('opacity-50', 'cursor-not-allowed');
          row.classList.add('hover:bg-blue-50');
          row.removeAttribute('title');
        }

        // El color de las celdas lo decide main.css desde la clase de la fila.
        if (window.selectedRowsIds.has(id)) {
          row.classList.add('ring-2', 'ring-blue-500', 'bg-blue-500', 'text-white');
        } else {
          row.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-500', 'text-white');
        }
      });
    }

    function updateVincularButtonState() {
      const btn = qs('#btnVincularExistentes');
      if (!btn) return;

      // Solo actualizar estado si estamos en modo selección múltiple
      if (!window.multiSelectMode) {
        return;
      }

      const count = window.selectedRowsIds.size;
      if (count >= 2) {
        btn.disabled = false;
        btn.classList.remove('bg-blue-300', 'cursor-not-allowed');
        btn.classList.remove('bg-blue-700', 'ring-2', 'ring-blue-300');
        btn.classList.add('bg-blue-500', 'hover:bg-blue-600', 'ring-2', 'ring-blue-300');
        btn.title = `Vincular ${count} registro(s) seleccionado(s) - Click para vincular`;
      } else {
        // NO deshabilitar el botón cuando no hay selecciones - permitir cancelar el modo
        btn.disabled = false;
        btn.classList.remove('bg-blue-500', 'hover:bg-blue-600');
        btn.classList.add('bg-blue-700', 'ring-2', 'ring-blue-300');
        btn.classList.remove('bg-blue-300', 'cursor-not-allowed');
        btn.title = count === 0 ? 'Click para cancelar modo selección múltiple' : `Selecciona ${2 - count} registro(s) más para vincular o click para cancelar`;
      }
    }

    // Vincular registros existentes
    window.vincularRegistrosExistentes = function() {
      if (!window.multiSelectMode) {
        // Activar modo selección múltiple
        toggleMultiSelectMode();
        return;
      }

      const selectedIds = Array.from(window.selectedRowsIds);

        // Si no hay registros seleccionados, cancelar el modo selección múltiple
      if (selectedIds.length === 0) {
        toggleMultiSelectMode();
        return;
      }

      if (selectedIds.length < 2) {
        toast('Debes seleccionar al menos 2 registros para vincular', 'warning');
        return;
      }

      // Usar el array de orden para asegurar que el primero es realmente el primero seleccionado
      const selectedIdsOrdenados = window.selectedRowsOrder.filter(id => selectedIds.includes(id));

      // Obtener el OrdCompartida del primer registro para el mensaje
      const primerId = selectedIdsOrdenados[0] || selectedIds[0];
      const primerRow = window.allRows?.find(r => r.getAttribute('data-id') === primerId);
      const primerOrdCompartida = primerRow?.getAttribute('data-ord-compartida');
      const primerTieneOrdCompartida = primerOrdCompartida && primerOrdCompartida.trim() !== '';

      // Confirmar acción
      if (typeof window.notify === 'undefined') {
        const mensaje = primerTieneOrdCompartida
          ? `¿Vincular ${selectedIds.length} registro(s) usando el OrdCompartida existente (${primerOrdCompartida.trim()})?`
          : `¿Vincular ${selectedIds.length} registro(s) con un nuevo OrdCompartida?`;
        if (!confirm(mensaje)) return;
        doVincular(selectedIds);
      } else {
        const mensajeHtml = primerTieneOrdCompartida
          ? `Se vincularán <strong>${selectedIds.length} registro(s)</strong> usando el OrdCompartida existente: <strong>${escapeHtmlPtModal(primerOrdCompartida.trim())}</strong>.<br><br>Esto no afectará los datos de los registros, solo los agrupará.`
          : `Se vincularán <strong>${selectedIds.length} registro(s)</strong> con un nuevo OrdCompartida.<br><br>Esto no afectará los datos de los registros, solo los agrupará.`;

        window.notify.form({
          title: 'Vincular registros?',
          html: mensajeHtml,
          icon: 'question',
          confirmText: 'Si, vincular',
          cancelText: 'Cancelar',
          width: 'md',
          preConfirm: async (ctx) => {
            const registrosIdsOrdenados = window.selectedRowsOrder.filter(iid => selectedIds.includes(iid));
            const idsParaEnviar = registrosIdsOrdenados.length > 0 ? registrosIdsOrdenados : selectedIds;
            try {
              const r = await fetch(PT_BOOT.routes.vincularRegistros, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'Accept': 'application/json',
                  'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ registros_ids: idsParaEnviar })
              });
              const data = await r.json().catch(() => ({}));
              if (!data.success) {
                ctx.error(data.message || 'Error al vincular los registros');
                return false;
              }
              return { data, idsParaEnviar };
            } catch (e) {
              ctx.error('Error al procesar la solicitud.');
              return false;
            }
          }
        }).then((result) => {
          if (!result) return;
          const { data, idsParaEnviar } = result;
          actualizarRegistrosVinculados(data.registros_ids || idsParaEnviar, data.ord_compartida);
          toast(data.message || 'Registros vinculados correctamente', 'success');
          window.selectedRowsIds.clear();
          window.selectedRowsOrder = [];
          window.multiSelectMode = false;
          updateSelectedRowsVisual();
          updateVincularButtonState();
          const btn = qs('#btnVincularExistentes');
          if (btn) {
            btn.classList.remove('bg-blue-500','text-white','hover:bg-blue-600','ring-2', 'ring-blue-300', 'bg-blue-300', 'cursor-not-allowed');
            btn.classList.add('bg-blue-500', 'hover:bg-blue-600');
            btn.disabled = false;
            btn.title = 'Vincular registros existentes - Click para activar modo selección múltiple';
          }
        });
      }
    };

    // Función para actualizar registros vinculados sin recargar
    async function actualizarRegistrosVinculados(registrosIds, ordCompartida) {
      const tb = document.querySelector('#mainTable tbody');
      if (!tb) return;

      // Obtener columnas para formatear valores
      const columns = (typeof columnsData !== 'undefined' && columnsData && columnsData.length > 0)
        ? columnsData
        : (window.columns || Array.from(document.querySelectorAll('#mainTable thead th[data-column]')).map(th => ({
          field: th.getAttribute('data-column'),
          label: th.textContent.trim(),
          dateType: null
        })));

      if (!columns || columns.length === 0) return;

      // Función para obtener el tipo de fecha de una columna
      const getDateType = (field) => {
        const col = columns.find(c => c.field === field);
        return col?.dateType || null;
      };

      // Función para formatear valor (usar la función global si existe, sino básica)
      const formatearValor = (registro, field, value) => {
        if (typeof formatearValorCelda === 'function') {
          return formatearValorCelda(registro, field, value, getDateType(field));
        }
        // Fallback básico
        if (value === null || value === undefined || value === '') return '';
        if (getDateType(field) === 'date' || getDateType(field) === 'datetime') {
          try {
            const dt = new Date(value);
            if (dt.getFullYear() <= 1970) return '';
            return getDateType(field) === 'date'
              ? dt.toLocaleDateString('es-MX')
              : dt.toLocaleString('es-MX');
          } catch (e) {
            return escapeHtmlPtModal(value);
          }
        }
        if (!isNaN(value) && !Number.isInteger(parseFloat(value))) {
          return parseFloat(value).toFixed(2);
        }
        // Se asigna con innerHTML => escapar el texto libre de BD.
        return escapeHtmlPtModal(value);
      };

      const idsUnicos = Array.from(new Set(registrosIds || [])).filter(Boolean);
      if (idsUnicos.length === 0) return;

      const fetchDetalle = async (registroId) => {
        try {
          const response = await fetch(`/planeacion/programa-tejido/${registroId}/detalles-balanceo?t=${Date.now()}`, {
            headers: {
              'Accept': 'application/json',
              'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content,
              'Cache-Control': 'no-cache'
            }
          });
          if (!response.ok) return null;
          const result = await response.json();
          if (!result.success || !result.registro) return null;
          return { registroId, registro: result.registro };
        } catch (error) {
          console.warn(`Error al actualizar registro ${registroId}:`, error);
          return null;
        }
      };

      const concurrenciaDetalleBalanceo = 12;
      const resultados = [];
      for (let i = 0; i < idsUnicos.length; i += concurrenciaDetalleBalanceo) {
        const chunk = idsUnicos.slice(i, i + concurrenciaDetalleBalanceo);
        const part = await Promise.all(chunk.map((id) => fetchDetalle(id)));
        resultados.push(...part);
      }

      for (const item of resultados) {
        if (!item) continue;
        const { registroId, registro } = item;
        const fila = tb.querySelector(`tr.selectable-row[data-id="${registroId}"]`);
        if (!fila) continue;

        if (registro.OrdCompartida) {
          fila.setAttribute('data-ord-compartida', registro.OrdCompartida);
        } else {
          fila.removeAttribute('data-ord-compartida');
        }

        columns.forEach(col => {
          const field = col.field;
          const value = registro[field] !== undefined ? registro[field] : null;
          const celda = fila.querySelector(`td[data-column="${field}"]`);
          if (!celda) return;
          celda.setAttribute('data-value', value !== null && value !== undefined ? String(value) : '');
          celda.innerHTML = formatearValor(registro, field, value);
        });

        window.PT?.filterIndex?.updateRow(fila);
      }
    }

    function doVincular(registrosIds) {
      PT.loader.show();

      // Usar el array de orden para asegurar que el primero es realmente el primero seleccionado
      const registrosIdsOrdenados = window.selectedRowsOrder.filter(id => registrosIds.includes(id));
      const idsParaEnviar = registrosIdsOrdenados.length > 0 ? registrosIdsOrdenados : registrosIds;

      fetch(PT_BOOT.routes.vincularRegistros, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ registros_ids: idsParaEnviar })
      })
      .then(r => r.json())
      .then(data => {
        PT.loader.hide();

        if (data.success) {
          // Actualizar registros sin recargar
          actualizarRegistrosVinculados(data.registros_ids || registrosIds, data.ord_compartida);

          toast(data.message || 'Registros vinculados correctamente', 'success');
          // Limpiar selección y desactivar modo
          window.selectedRowsIds.clear();
          window.selectedRowsOrder = [];
          window.multiSelectMode = false;
          updateSelectedRowsVisual();
          updateVincularButtonState();

          const btn = qs('#btnVincularExistentes');
          if (btn) {
            btn.classList.remove('bg-blue-500','text-white','hover:bg-blue-600','ring-2', 'ring-blue-300', 'bg-blue-300', 'cursor-not-allowed');
            btn.classList.add('bg-blue-500', 'hover:bg-blue-600');
            btn.disabled = false;
            btn.title = 'Vincular registros existentes - Click para activar modo selección múltiple';
          }
        } else {
          toast(data.message || 'Error al vincular los registros', 'error');
        }
      })
      .catch(err => {
        PT.loader.hide();
        toast('Error al procesar la solicitud: ' + (err.message || 'Error desconocido'), 'error');
      });
    }

    // Integrar selecciÃ³n mÃºltiple en el handler existente
    document.addEventListener('DOMContentLoaded', () => {
        // Interceptar clicks en filas cuando estÃ¡ activo el modo selecciÃ³n mÃºltiple
        const tb = tbodyEl();
        if (tb) {
          tb.addEventListener('click', (e) => {
            if (!window.multiSelectMode) return;

            const row = e.target.closest('.selectable-row');
            if (!row) return;

            // La validaciÃ³n de OrdCompartida se hace en toggleRowSelection
            // Si estamos en modo selecciÃ³n mÃºltiple, manejar la selecciÃ³n
            e.preventDefault();
            e.stopPropagation();
            toggleRowSelection(row);
          }, true); // Usar capture phase para interceptar antes que otros handlers
        }
    });

    // =========================
    // Actualizar totales basados en filas visibles
    // =========================
    window.updateTotales = function updateTotales() {
      const tb = tbodyEl();
      if (!tb) return;

      // Obtener todas las filas y filtrar solo las visibles
      const allRows = Array.from(tb.querySelectorAll('.selectable-row'));
      // Filtrar filas visibles - verificar mÃºltiples condiciones
      // IMPORTANTE: Verificar primero la clase filter-hidden ya que es lo que usa el sistema de filtros
      const visibleRows = allRows.filter(row => {
        // 1. PRIMERO: Verificar clase filter-hidden (esto es lo mÃ¡s importante - usado por el sistema de filtros)
        if (row.classList.contains('filter-hidden')) {
          return false;
        }

        // 2. Verificar estilo inline (puede estar oculta por display: none)
        const inlineDisplay = row.style.display;
        if (inlineDisplay === 'none') {
          return false;
        }

        // 3. Verificar estilo computado (mÃ¡s confiable, pero mÃ¡s lento)
        const computedStyle = window.getComputedStyle(row);
        const computedDisplay = computedStyle.display;
        const computedVisibility = computedStyle.visibility;

        if (computedDisplay === 'none' || computedVisibility === 'hidden') {
          return false;
        }

        // 4. Verificar que el offsetHeight sea mayor a 0 (otra forma de verificar visibilidad)
        // Esto puede ser Ãºtil si la fila estÃ¡ fuera del viewport pero aÃºn es visible
        // Comentado porque puede dar falsos negativos si la fila estÃ¡ fuera del viewport
        // if (row.offsetHeight === 0 && row.offsetWidth === 0) {
        //   return false;
        // }

        return true;
      });

      // Debug: verificar que los elementos existan
      const totalRegistrosEl = qs('#totalRegistros');


      let totalRegistros = visibleRows.length;
      let totalPedido = 0;
      let totalProduccion = 0;
      let totalSaldos = 0;

      visibleRows.forEach(row => {
        // Obtener TotalPedido - usar data-value primero, luego textContent
        const pedidoCell = row.querySelector('[data-column="TotalPedido"]');
        if (pedidoCell) {
          let pedidoValue = pedidoCell.getAttribute('data-value') || '';
          // Si data-value estÃ¡ vacÃ­o o no existe, usar textContent
          if (!pedidoValue || pedidoValue === '' || pedidoValue === 'null') {
            pedidoValue = (pedidoCell.textContent || pedidoCell.innerText || '0').trim();
          }
          // Limpiar el valor: quitar comas, espacios, caracteres no numÃ©ricos excepto punto y signo negativo
          const cleanedValue = pedidoValue.toString().replace(/[^\d.-]/g, '');
          const pedido = parseFloat(cleanedValue) || 0;
          if (!isNaN(pedido)) {
            totalPedido += pedido;
          }
        }

        // Obtener Produccion - usar data-value primero, luego textContent
        const produccionCell = row.querySelector('[data-column="Produccion"]');
        if (produccionCell) {
          let produccionValue = produccionCell.getAttribute('data-value') || '';
          // Si data-value estÃ¡ vacÃ­o o no existe, usar textContent
          if (!produccionValue || produccionValue === '' || produccionValue === 'null') {
            produccionValue = (produccionCell.textContent || produccionCell.innerText || '0').trim();
          }
          // Limpiar el valor: quitar comas, espacios, caracteres no numÃ©ricos excepto punto y signo negativo
          const cleanedValue = produccionValue.toString().replace(/[^\d.-]/g, '');
          const produccion = parseFloat(cleanedValue) || 0;
          if (!isNaN(produccion)) {
            totalProduccion += produccion;
          }
        }

        // Obtener SaldoPedido - usar data-value primero, luego textContent
        const saldosCell = row.querySelector('[data-column="SaldoPedido"]');
        if (saldosCell) {
          let saldosValue = saldosCell.getAttribute('data-value') || '';
          // Si data-value estÃ¡ vacÃ­o o no existe, usar textContent
          if (!saldosValue || saldosValue === '' || saldosValue === 'null') {
            saldosValue = (saldosCell.textContent || saldosCell.innerText || '0').trim();
          }
          // Limpiar el valor: quitar comas, espacios, caracteres no numÃ©ricos excepto punto y signo negativo
          const cleanedValue = saldosValue.toString().replace(/[^\d.-]/g, '');
          const saldos = parseFloat(cleanedValue) || 0;
          if (!isNaN(saldos)) {
            totalSaldos += saldos;
          }
        }
      });

      // Verificar que los elementos existan
      const totalPedidoEl = qs('#totalPedido');
      const totalProduccionEl = qs('#totalProduccion');
      const totalSaldosEl = qs('#totalSaldos');

      if (!totalRegistrosEl || !totalPedidoEl || !totalProduccionEl || !totalSaldosEl) {
        return;
      }

      // Actualizar los elementos
      totalRegistrosEl.textContent = totalRegistros.toLocaleString('es-MX', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
      totalPedidoEl.textContent = totalPedido.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      totalProduccionEl.textContent = totalProduccion.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      totalSaldosEl.textContent = totalSaldos.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    // =========================
    // Reset (filtros + columnas)
    // =========================
    function resetAllView(e) {
      if (e) e.preventDefault();

      if (typeof window.filters !== 'undefined') window.filters = [];
      if (typeof window.quickFilters !== 'undefined') {
        window.quickFilters = {
          ultimos:false, divididos:false, enProceso:false,
          salonJacquard:false, salonSmit:false, conCambioHilo:false
        };
      }
      if (typeof window.dateRangeFilters !== 'undefined') {
        window.dateRangeFilters = { fechaInicio:{desde:null,hasta:null}, fechaFinal:{desde:null,hasta:null} };
      }
      if (typeof window.lastFilterState !== 'undefined') window.lastFilterState = null;

      const tb = tbodyEl();
      if (tb) {
        qsa('.selectable-row', tb).forEach(r => {
          r.style.display = '';
          r.classList.remove('filter-hidden');
        });
      }

      if (typeof window.updateFilterUI === 'function') window.updateFilterUI();

      if (typeof window.resetColumnVisibility === 'function') window.resetColumnVisibility();
      else {
        const headers = qsa('#mainTable thead th');
        headers.forEach((_, i) => qsa('.column-' + i).forEach(el => el.style.display = ''));
        if (typeof window.updatePinnedColumnsPositions === 'function') window.updatePinnedColumnsPositions();
      }

      updateTotales();
      toast('Vista restablecida (filtros y columnas)', 'success');
    }

    // =========================
    // Bind botones layout
    // =========================
    function bindLayoutButtons() {
      qs('#layoutBtnEditar')?.setAttribute('disabled', 'disabled');
      qs('#layoutBtnEliminar')?.setAttribute('disabled', 'disabled');
      qs('#layoutBtnVerLineas')?.setAttribute('disabled', 'disabled');

      const btnInlineEdit = qs('#btnInlineEdit');
      if (btnInlineEdit) btnInlineEdit.remove();

      qs('#btn-editar-programa')?.addEventListener('click', () => {
        const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row');
        const row = rows[window.selectedRowIndex];
        const id = row?.getAttribute('data-id');
        if (!id) return;
        // Funcionalidad de editar eliminada - ahora se usa duplicar/vincular/dividir
        console.warn('La funcionalidad de editar ha sido reemplazada por duplicar/vincular/dividir');
      });

      qs('#btn-eliminar-programa')?.addEventListener('click', () => {
        const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row');
        const row = rows[window.selectedRowIndex];
        const id = row?.getAttribute('data-id');
        if (!id) return;
        PT.actions.eliminarRegistro(id);
      });

      const openLines = () => {
        const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row');
        const row = rows[window.selectedRowIndex];
        const id = row?.getAttribute('data-id');
        if (!id) return;

        if (typeof window.openLinesModal === 'function') window.openLinesModal(id);
        else toast('Error: No se pudo abrir el modal. Por favor recarga la pÃ¡gina.', 'error');
      };

      qs('#btn-ver-lineas')?.addEventListener('click', openLines);
      qs('#layoutBtnVerLineas')?.addEventListener('click', openLines);

      qs('#btnResetColumns')?.addEventListener('click', resetAllView);
      qs('#btnResetColumnsMobile')?.addEventListener('click', resetAllView);
    }

    // =========================
    // Restaurar selecciÃ³n
    // =========================
    window.yellowHighlightTimeout = null;

    function restoreSelectionAfterReload() {
      const tb = tbodyEl();
      if (!tb) return;

      // Cancelar timeout anterior si existe
      if (window.yellowHighlightTimeout) {
        clearTimeout(window.yellowHighlightTimeout);
        window.yellowHighlightTimeout = null;
      }

      const urlParams = new URLSearchParams(window.location.search);
      const registroIdParam = urlParams.get('registro_id');

      const ssSelect = sessionStorage.getItem('selectRegistroId');
      const ssScroll = sessionStorage.getItem('scrollToRegistroId');

      const idToUse = registroIdParam || ssSelect || ssScroll;
      if (!idToUse) return;

      const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tb);
      const targetRow = rows.find(r => r.getAttribute('data-id') == idToUse);
      if (!targetRow) return;

      const idx = rows.indexOf(targetRow);
      if (typeof window.selectRow === 'function' && idx >= 0) window.selectRow(targetRow, idx);

      targetRow.scrollIntoView({ behavior:'smooth', block:'center' });
      // Solo agregar amarillo temporal si no estÃ¡ en modo ediciÃ³n inline (muy breve, 500ms)
      if (!inlineEditMode && !window.inlineEditMode) {
        targetRow.classList.add('bg-yellow-100');
        window.yellowHighlightTimeout = setTimeout(() => {
          // Solo quitar si no estÃ¡ en modo ediciÃ³n inline y no hay inputs editando
          if (!inlineEditMode && !window.inlineEditMode && !targetRow.querySelector('.inline-edit-input')) {
            targetRow.classList.remove('bg-yellow-100');
          }
          window.yellowHighlightTimeout = null;
        }, 500);
      }

      if (registroIdParam) {
        const cleanUrl = new URL(window.location.href);
        cleanUrl.searchParams.delete('registro_id');
        window.history.replaceState({}, '', cleanUrl.toString());
      }

      sessionStorage.removeItem('selectRegistroId');
      sessionStorage.removeItem('scrollToRegistroId');
    }

    function showSavedToastIfAny() {
      const msg  = sessionStorage.getItem('priorityChangeMessage');
      const type = sessionStorage.getItem('priorityChangeType') || 'success';
      if (!msg) return;
      setTimeout(() => {
        toast(msg, type);
        sessionStorage.removeItem('priorityChangeMessage');
        sessionStorage.removeItem('priorityChangeType');
      }, 350);
    }

    // =========================
    // Dropdown Actualizar
    // =========================
    (function() {
      // Cerrar dropdown al hacer click fuera
      document.addEventListener('click', (e) => {
        const dropdown = document.getElementById('actualizarDropdownMenu');
        const btn = document.getElementById('btnActualizarDropdown');
        if (dropdown && btn && !dropdown.contains(e.target) && !btn.contains(e.target)) {
          dropdown.classList.add('hidden');
        }
      });

      // Manejar click en "Act. Calendarios"
      document.addEventListener('DOMContentLoaded', () => {
        const menuActCalendarios = document.getElementById('menuActCalendarios');
        if (menuActCalendarios) {
          menuActCalendarios.addEventListener('click', () => {
            const dropdown = document.getElementById('actualizarDropdownMenu');
            if (dropdown) {
              dropdown.classList.add('hidden');
            }
            if (typeof window.abrirModalActCalendarios === 'function') {
              window.abrirModalActCalendarios();
            } else {
              toast('Función abrirModalActCalendarios no disponible', 'error');
            }
          });
        }

        // Manejar click en "Act. Fechas" (por ahora no hace nada)
        const menuActFechas = document.getElementById('menuActFechas');
        if (menuActFechas) {
          menuActFechas.addEventListener('click', () => {
            const dropdown = document.getElementById('actualizarDropdownMenu');
            if (dropdown) {
              dropdown.classList.add('hidden');
            }
            toast('Funcionalidad de Actualizar Fechas próximamente', 'info');
          });
        }
      });
    })();

    // =========================
    // Init
    // =========================
    document.addEventListener('DOMContentLoaded', () => {
      setNavbarHeightVar();

      // ⚡ OPTIMIZACIÓN: Cargar estados guardados primero
      // initializeColumnVisibility se llamará automáticamente desde loadPersistedHiddenColumns
      // si no hay estados guardados, evitando conflictos
      if (typeof window.loadPersistedHiddenColumns === 'function') {
        window.loadPersistedHiddenColumns();
      }

      // Los fijados, en el mismo frame que el primer paint: a los 100 ms la tabla
      // ya se habia visto sin ellos y se movia debajo del cursor.
      requestAnimationFrame(() => {
        if (typeof window.pinDefaultColumns === 'function') {
          window.pinDefaultColumns();
        } else if (typeof window.applyDefaultPinsOnce === 'function') {
          window.applyDefaultPinsOnce();
        }

        // Actualizar iconos de filtro al cargar
        if (typeof window.updateColumnFilterIcons === 'function') {
          window.updateColumnFilterIcons();
        }
        // Actualizar iconos de columnas fijadas al cargar
        if (typeof window.updateColumnPinIcons === 'function') {
          window.updateColumnPinIcons();
        }
        if (typeof window.updatePinnedColumnsPositions === 'function') {
          window.updatePinnedColumnsPositions();
          // Los iconos se repintan aqui mismo: updatePinnedColumnsPositions es
          // sincrona, no habia nada que esperar 100 ms mas.
          if (typeof window.updateColumnPinIcons === 'function') {
            window.updateColumnPinIcons();
          }
        }
      });

      const tb = tbodyEl();
      if (tb) {
        refreshAllRows();
        // Función helper para asignar eventos onclick
        bindRowSelectionOnce();
      }

      requestAnimationFrame(() => setDragDropButtonGray(!!window.dragDropMode));

      window.addEventListener('resize', () => {
        setNavbarHeightVar();
        if (typeof window.updatePinnedColumnsPositions === 'function') window.updatePinnedColumnsPositions();
      });

      bindLayoutButtons();
      restoreSelectionAfterReload();
      // Actualizar estado del botÃ³n balancear despuÃ©s de restaurar selecciÃ³n
      // (tambiÃ©n se actualizarÃ¡ automÃ¡ticamente por el evento pt:selection-changed)
      updateBalanceBtnState();
      // Inicializar estado del botÃ³n vincular (solo si estÃ¡ en modo selecciÃ³n mÃºltiple)
      if (window.multiSelectMode) {
        updateVincularButtonState();
      }
      // Inicializar totales
      updateTotales();
      showSavedToastIfAny();

      // Inicializar listeners de Reprogramar
      if (typeof window.initReprogramarListeners === 'function') {
        window.initReprogramarListeners();
      }

      const balanceBtn = document.querySelector('a[title="Balancear"]');
      if (balanceBtn) {
        balanceBtn.addEventListener('click', (e) => {
          e.preventDefault();

          const tb = tbodyEl();
          if (!tb) return;

          const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tb);
          if (window.selectedRowIndex === null || window.selectedRowIndex === undefined || window.selectedRowIndex < 0) {
            toast('Selecciona primero un registro con OrdCompartida', 'info');
            return;
          }

          const row = rows[window.selectedRowIndex];
          const ordCompartida = row?.getAttribute('data-ord-compartida');
          if (!ordCompartida) {
            toast('El registro seleccionado no tiene OrdCompartida', 'info');
            return;
          }

          if (typeof window.verDetallesGrupoBalanceo === 'function') {
            window.verDetallesGrupoBalanceo(parseInt(ordCompartida));
          } else {
            toast('No existe verDetallesGrupoBalanceo()', 'error');
          }
        });
      }
    });

    // =========================
    // Reprogramar checkbox con modal
    // =========================
    (function() {
      // Función para procesar la selección
      async function procesarSeleccionReprogramar(registroId, valor, checkbox, texto) {
        // Estado previo, para poder revertir al valor real y no a vacio: un registro
        // en "P. Siguiente" que falle al pasar a "P. Ultima" quedaba en blanco en
        // pantalla mientras la BD seguia en 1.
        const previo = {
          checked: checkbox.checked,
          valor: checkbox.getAttribute('data-valor-actual') || '',
          texto: texto.textContent
        };
        const revertir = () => {
          checkbox.checked = previo.checked;
          checkbox.setAttribute('data-valor-actual', previo.valor);
          texto.textContent = previo.texto;
        };

        // Actualizar UI
        checkbox.checked = true;
        checkbox.setAttribute('data-valor-actual', valor);

        if (valor == '1') {
          texto.textContent = 'P. Siguiente';
        } else if (valor == '2') {
          texto.textContent = 'P. Ultima';
        }

        // Enviar al backend
        try {
          PT.loader.show();
          const response = await fetch(`/planeacion/programa-tejido/${registroId}/reprogramar`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ reprogramar: valor })
          });

          const data = await response.json();
          PT.loader.hide();

          if (data.success) {
            toast('Reprogramar actualizado correctamente', 'success');
          } else {
            toast(data.message || 'Error al actualizar reprogramar', 'error');
            revertir();
          }
        } catch (error) {
          PT.loader.hide();
          toast('Error al procesar la solicitud', 'error');
          revertir();
        }
      }

      // Manejar click en checkbox usando delegación de eventos
      window.initReprogramarListeners = initReprogramarListeners;
      function initReprogramarListeners() {
        const tb = tbodyEl();
        if (!tb) return;

        // Evitar agregar listener múltiples veces
        if (tb.dataset.reprogramarListenerAdded === 'true') return;
        tb.dataset.reprogramarListenerAdded = 'true';

        // Usar delegación de eventos en el tbody
        tb.addEventListener('click', async (e) => {
          // Verificar si el click fue en el checkbox directamente
          if (!e.target || e.target.type !== 'checkbox') return;
          if (!e.target.classList || !e.target.classList.contains('reprogramar-checkbox')) return;

          const checkbox = e.target;
          const container = checkbox.closest('.reprogramar-container');

          if (!container) return;

          // Verificar si está deshabilitado (no está en proceso)
          if (checkbox.disabled) {
            toast('Solo los registros en proceso pueden tener Reprogramar activo', 'warning');
            return;
          }

          // Verificar el atributo data-en-proceso del contenedor
          const enProceso = container.getAttribute('data-en-proceso');
          if (enProceso !== '1') {
            toast('Solo los registros en proceso pueden tener Reprogramar activo', 'warning');
            return;
          }

          // Capturar el estado ANTES de prevenir el comportamiento por defecto
          const texto = container.querySelector('.reprogramar-texto');
          const registroId = checkbox.getAttribute('data-registro-id');
          const valorActual = checkbox.getAttribute('data-valor-actual') || '';
          const estabaMarcado = checkbox.checked || (valorActual && (valorActual == '1' || valorActual == '2'));

          e.preventDefault();
          e.stopPropagation();
          e.stopImmediatePropagation();

          // Si ya tiene un valor activo (estaba marcado), limpiarlo
          if (estabaMarcado && valorActual && (valorActual == '1' || valorActual == '2')) {
            // Limpiar visualmente - forzar que se vea desmarcado
            checkbox.checked = false;
            checkbox.removeAttribute('checked');
            checkbox.setAttribute('data-valor-actual', '');
            if (texto) texto.textContent = '';

            // Enviar al backend para limpiar
            try {
              PT.loader.show();
              const response = await fetch(`/planeacion/programa-tejido/${registroId}/reprogramar`, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ reprogramar: null })
              });

              const data = await response.json();
              PT.loader.hide();

              if (data.success) {
                // Asegurar que el checkbox estÃ© visualmente desmarcado
                checkbox.checked = false;
                checkbox.removeAttribute('checked');
                checkbox.setAttribute('data-valor-actual', '');
                if (texto) texto.textContent = '';
                toast('Reprogramar limpiado correctamente', 'success');
              } else {
                toast(data.message || 'Error al limpiar reprogramar', 'error');
                // Revertir cambios - restaurar estado marcado
                checkbox.checked = true;
                checkbox.setAttribute('checked', 'checked');
                checkbox.setAttribute('data-valor-actual', valorActual);
                if (texto) {
                  if (valorActual == '1') {
                    texto.textContent = 'P. Siguiente';
                  } else if (valorActual == '2') {
                    texto.textContent = 'P. Ultima';
                  }
                }
              }
            } catch (error) {
              PT.loader.hide();
              toast('Error al procesar la solicitud', 'error');
              // Revertir cambios - restaurar estado marcado
              checkbox.checked = true;
              checkbox.setAttribute('checked', 'checked');
              checkbox.setAttribute('data-valor-actual', valorActual);
              if (texto) {
                if (valorActual == '1') {
                  texto.textContent = 'P. Siguiente';
                } else if (valorActual == '2') {
                  texto.textContent = 'P. Ultima';
                }
              }
            }
            return;
          }

          // Si no está marcado, mostrar modal
          if (typeof window.notify === 'undefined') {
            toast('notify no está disponible', 'error');
            checkbox.checked = false;
            return;
          }

          // Asegurar que el checkbox no está marcado antes de mostrar el modal
          checkbox.checked = false;

          let opcionElegida = null;
          await window.notify.dialog({
            titulo: 'Seleccionar Reprogramar',
            tono: null,
            botones: [{ texto: 'Cancelar', valor: 'cancelar', variante: 'secundario' }],
            html: `
              <div class="text-left">
                <p class="mb-4 text-sm text-gray-600">Selecciona una opción:</p>
                <div class="space-y-2">
                  <button type="button" id="swal-opcion-1" class="w-full text-left px-4 py-3 bg-blue-50 hover:bg-blue-100 border border-blue-300 rounded-md text-blue-700 font-medium transition-colors">
                    P. Siguiente
                  </button>
                  <button type="button" id="swal-opcion-2" class="w-full text-left px-4 py-3 bg-green-50 hover:bg-green-100 border border-green-300 rounded-md text-green-700 font-medium transition-colors">
                    P. Ultima
                  </button>
                </div>
              </div>
            `,
            alAbrir: () => {
              // Manejar click en opciones
              const opcion1 = document.getElementById('swal-opcion-1');
              const opcion2 = document.getElementById('swal-opcion-2');

              if (opcion1) {
                opcion1.addEventListener('click', () => {
                  opcionElegida = '1';
                  window.notify.close();
                });
              }

              if (opcion2) {
                opcion2.addEventListener('click', () => {
                  opcionElegida = '2';
                  window.notify.close();
                });
              }
            }
          });

          // Si se canceló el modal, no hacer nada (el checkbox ya no estará marcado)
          if (opcionElegida) {
            procesarSeleccionReprogramar(registroId, opcionElegida, checkbox, texto);
          } else {
            checkbox.checked = false;
          }
        }, true); // Usar capture phase para capturar antes que otros listeners
      }

      // Inicializar listeners - se ejecutará desde el init principal
      window.initReprogramarListeners = initReprogramarListeners;
    })();

    // Atajo Ctrl+F: abrir filtro de la última columna seleccionada via context menu
    document.addEventListener('keydown', (e) => {
      if (e.ctrlKey && e.key === 'f') {
        const col = PT.selectedColumn;
        if (col && col.index != null && col.field) {
          e.preventDefault();
          if (typeof openFilterModal === 'function') {
            openFilterModal(col.index, col.field);
          }
        }
      }
    });

    // ===== ÍNDICE EN MEMORIA PARA FILTROS =====
    // Construye Map<rowId, {columna: valor}> desde el DOM una sola vez.
    // Evita querySelector repetidos en applyProgramaTejidoFilters() — O(1) vs O(n*cols).
    function buildPTFilterIndex() {
      const tb = tbodyEl();
      if (!tb) { window.PT_FILTER_INDEX = new Map(); return; }
      const index = new Map();
      tb.querySelectorAll('.selectable-row').forEach(row => {
        const id = row.dataset.id;
        if (!id) return;
        const data = {};
        row.querySelectorAll('[data-column]').forEach(cell => {
          const col = cell.dataset.column;
          if (col) data[col] = (cell.dataset.value ?? cell.textContent ?? '').trim();
        });
        data._ordCompartida = row.dataset.ordCompartida ?? '';
        index.set(id, data);
      });
      window.PT_FILTER_INDEX = index;
    }

    function updatePTFilterIndexRow(rowElement) {
      if (!window.PT_FILTER_INDEX) return;
      const id = rowElement?.dataset?.id;
      if (!id) return;
      const data = {};
      rowElement.querySelectorAll('[data-column]').forEach(cell => {
        const col = cell.dataset.column;
        if (col) data[col] = (cell.dataset.value ?? cell.textContent ?? '').trim();
      });
      data._ordCompartida = rowElement.dataset.ordCompartida ?? '';
      window.PT_FILTER_INDEX.set(id, data);
    }

    function removePTFilterIndexRow(rowId) {
      window.PT_FILTER_INDEX?.delete(String(rowId));
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => { buildPTFilterIndex(); });
    } else {
      buildPTFilterIndex();
    }

    // Exponer para invalidación desde inline-edit y operaciones
    window.PT = window.PT || {};
    window.PT.filterIndex = {
      rebuild: buildPTFilterIndex,
      updateRow: updatePTFilterIndexRow,
      removeRow: removePTFilterIndexRow,
    };

  })();

// ===========================================================================
// Paridad con el <script> clasico que esto era antes.
//
// Servido como <script type="module"), una funcion de nivel superior ya no se
// cuelga sola del objeto global, y este bundle se llama a si mismo por window.
// en decenas de sitios (por eso el modal de duplicar no abria). Ademas esbuild
// borra por tree-shaking lo que no ve referenciado dentro del modulo.
//
// Por eso cada una de las 49 funciones que se leen por window.
// lleva su `window.X = X;` pegado a la declaracion: ese es el unico sitio donde
// el nombre esta garantizado en scope. Publicarlas todas juntas al final fallaba
// con las que viven dentro de un bloque anidado.
//
// Vigilan esto ProgramaTejidoJsGlobalsTest (lo leido vs lo publicado) y
// ProgramaTejidoJsSyntaxTest (que el bundle compile).
// ===========================================================================
