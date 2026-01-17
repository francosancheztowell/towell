{{-- Funciones compartidas para duplicar/vincular y dividir --}}
{{-- NOTA: Este archivo se incluye dentro de un bloque <script>, NO agregar etiquetas <script> aquí --}}

// Variable global para almacenar registros existentes de OrdCompartida
let registrosOrdCompartidaExistentes = [];
let ordCompartidaActual = null;

// Función global para obtener el modo actual (para compatibilidad con radio buttons)
function getModoActual() {
	if (document.getElementById('modo-duplicar')?.checked) return 'duplicar';
	if (document.getElementById('modo-dividir')?.checked) return 'dividir';
	return 'duplicar'; // por defecto
}

// Función para verificar si el checkbox de vincular está activo
function estaVincularActivado() {
	const checkbox = document.getElementById('checkbox-vincular');
	return checkbox && checkbox.checked;
}

// Helpers para obtener datos de la fila
function getRowCellText(row, column, fallback = '') {
	if (!row) return fallback;
	const cell = row.querySelector(`[data-column="${column}"]`);
	const text = cell?.textContent?.trim();
	return text || fallback;
}

function getRowTelar(row) {
	return getRowCellText(row, 'NoTelarId', null);
}

function getRowSalon(row) {
	return getRowCellText(row, 'SalonTejidoId', null);
}

function getCsrfToken() {
	return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function buildCalendarErrorHtml(data) {
	const calendarioHtml = (data.calendario_id && data.fecha_inicio && data.fecha_fin)
		? `<div class="mt-3 text-xs text-red-600"><p><strong>Calendario:</strong> ${data.calendario_id}</p></div>`
		: '';

	return `
		<div class="text-left">
			<div class="bg-red-50 border-l-4 border-red-400 p-4 mb-3">
				<p class="font-semibold text-red-800 mb-2">No se puede duplicar</p>
				<p class="text-sm text-red-700 mb-2">${data.message}</p>
				${calendarioHtml}
			</div>
			<p class="text-xs text-gray-600 mt-3">Por favor, agregue fechas al calendario en el catalogo de calendarios antes de intentar duplicar nuevamente.</p>
		</div>
	`;
}

function buildCalendarWarningHtml(message, advertencias) {
	const detalles = Array.isArray(advertencias?.detalles) ? advertencias.detalles : [];
	const detallesHtml = detalles.length > 0
		? `<ul class="list-disc list-inside text-xs text-yellow-700 space-y-1">${detalles.slice(0, 5).map(detalle => (
			`<li>Calendario '<strong>${detalle.calendario_id}</strong>': ${detalle.mensaje}</li>`
		)).join('')}${detalles.length > 5 ? `<li>... y ${detalles.length - 5} mas</li>` : ''}</ul>`
		: '';

	return `
		<div class="text-left">
			<p class="mb-3 text-sm text-gray-700">${message}</p>
			<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-3">
				<p class="font-semibold text-yellow-800 mb-2">Advertencia: Problemas con calendarios</p>
				<p class="text-sm text-yellow-700 mb-2">${advertencias.total_errores} programa(s) no pudieron generar lineas diarias porque no hay fechas disponibles en el calendario.</p>
				${detallesHtml}
			</div>
			<p class="text-xs text-gray-600">Los programas se crearon correctamente, pero necesitas agregar fechas al calendario para generar las lineas diarias.</p>
		</div>
	`;
}

async function redirectToRegistro(data) {
	// Si hay múltiples registros duplicados o vinculados, agregarlos todos sin recargar
	const tieneMultiplesRegistros = (data?.registros_duplicados && data.registros_duplicados > 1) ||
	                                (data?.registros_vinculados && data.registros_vinculados > 1) ||
	                                (data?.registros_ids && Array.isArray(data.registros_ids) && data.registros_ids.length > 1);

	if (tieneMultiplesRegistros) {
		// Preferir usar registros_ids si está disponible (más confiable)
		// Si no está disponible, usar el método anterior con IDs secuenciales
		if (data?.registros_ids && Array.isArray(data.registros_ids) && data.registros_ids.length > 0) {
			try {
				// Delay inicial para asegurar que los registros estén completamente guardados en la BD
				await new Promise(resolve => setTimeout(resolve, 800));

				const tb = document.querySelector('#mainTable tbody');
				if (!tb) {
					console.error('[DEBUG] No se encontró el tbody');
					window.location.reload();
					return;
				}

				// Obtener IDs de filas existentes en el DOM
				const filasExistentes = Array.from(tb.querySelectorAll('.selectable-row')).map(f => f.getAttribute('data-id'));
				const registrosAgregados = [];

				console.log(`[DEBUG] 📋 IDs de registros a agregar:`, data.registros_ids);
				console.log(`[DEBUG] 📋 IDs de filas existentes en DOM:`, filasExistentes);

				// Agregar todos los registros usando los IDs devueltos por el backend
				for (const registroId of data.registros_ids) {
					const idStr = String(registroId);

					// Verificar si ya existe en el DOM
					if (filasExistentes.includes(idStr)) {
						console.log(`[DEBUG] ⏭️ Registro ${idStr} ya existe en el DOM, saltando`);
						registrosAgregados.push(parseInt(idStr));
						continue;
					}

					try {
						// Delay antes de obtener cada registro para asegurar que esté guardado
						await new Promise(resolve => setTimeout(resolve, 200));

						// Obtener el registro con cache-busting
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
								// Para vincular puede haber múltiples telares destino, así que la validación es más flexible
								// Solo validar telar destino si es duplicar (un solo telar destino)
								const esVincular = data?.registros_vinculados > 0 || data?.ord_compartida;
								const telarValido = esVincular ||
									(result.registro.SalonTejidoId === data.salon_destino &&
									 result.registro.NoTelarId === data.telar_destino);

								if (telarValido) {
									console.log(`[DEBUG] ➕ Agregando registro ID: ${registroId}`, {
										TamanoClave: result.registro.TamanoClave,
										ItemId: result.registro.ItemId,
										InventSizeId: result.registro.InventSizeId,
										SalonTejidoId: result.registro.SalonTejidoId,
										NoTelarId: result.registro.NoTelarId
									});
									await agregarRegistroSinRecargar({ registro_id: registroId, message: '' });
									registrosAgregados.push(parseInt(idStr));

									// Actualizar lista de filas existentes después de cada inserción
									filasExistentes.push(idStr);

									await new Promise(resolve => setTimeout(resolve, 150));
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
						// Si falla, continuar con el siguiente
						console.warn(`[DEBUG] ⚠️ Excepción al obtener el registro ${registroId}:`, e);
					}
				}

				// ⚡ MEJORA: Actualizar el registro original después de dividir
				// Si es dividir, actualizar el registro original con los nuevos valores de TotalPedido y SaldoPedido
				if (data?.modo === 'dividir' && data?.registro_id_original) {
					try {
						// Usar datos del registro original de la respuesta si están disponibles (más rápido)
						if (data?.registro_original) {
							const filaOriginal = tb.querySelector(`tr.selectable-row[data-id="${data.registro_id_original}"]`);
							if (filaOriginal) {
								const regOriginal = data.registro_original;
								
								// Actualizar TotalPedido y SaldoPedido en el DOM directamente
								const totalPedidoCell = filaOriginal.querySelector('[data-column="TotalPedido"]');
								const saldoPedidoCell = filaOriginal.querySelector('[data-column="SaldoPedido"]');
								const fechaFinalCell = filaOriginal.querySelector('[data-column="FechaFinal"]');
								const horasCell = filaOriginal.querySelector('[data-column="HorasProd"]');
								
								if (totalPedidoCell && regOriginal.TotalPedido !== undefined) {
									totalPedidoCell.textContent = regOriginal.TotalPedido || '0';
									totalPedidoCell.setAttribute('data-value', regOriginal.TotalPedido || '0');
								}
								if (saldoPedidoCell && regOriginal.SaldoPedido !== undefined) {
									saldoPedidoCell.textContent = regOriginal.SaldoPedido || '0';
									saldoPedidoCell.setAttribute('data-value', regOriginal.SaldoPedido || '0');
								}
								// ⚡ MEJORA: Actualizar FechaFinal si está disponible
								if (fechaFinalCell && regOriginal.FechaFinal) {
									const fechaFinal = new Date(regOriginal.FechaFinal);
									const fechaFormateada = fechaFinal.toLocaleDateString('es-ES', {
										day: '2-digit',
										month: '2-digit',
										year: 'numeric'
									});
									fechaFinalCell.textContent = fechaFormateada;
									fechaFinalCell.setAttribute('data-value', regOriginal.FechaFinal);
								}
								// ⚡ MEJORA: Actualizar campos de fórmulas si están disponibles
								if (horasCell && regOriginal.HorasProd !== undefined) {
									horasCell.textContent = regOriginal.HorasProd ? parseFloat(regOriginal.HorasProd).toFixed(2) : '0';
									horasCell.setAttribute('data-value', regOriginal.HorasProd || '0');
								}
								// Actualizar DiasJornada si está disponible
								const diasJornadaCell = filaOriginal.querySelector('[data-column="DiasJornada"]');
								if (diasJornadaCell && regOriginal.DiasJornada !== undefined) {
									diasJornadaCell.textContent = regOriginal.DiasJornada ? parseFloat(regOriginal.DiasJornada).toFixed(2) : '0';
									diasJornadaCell.setAttribute('data-value', regOriginal.DiasJornada || '0');
								}
								// Actualizar StdDia si está disponible
								const stdDiaCell = filaOriginal.querySelector('[data-column="StdDia"]');
								if (stdDiaCell && regOriginal.StdDia !== undefined) {
									stdDiaCell.textContent = regOriginal.StdDia ? parseFloat(regOriginal.StdDia).toFixed(2) : '0';
									stdDiaCell.setAttribute('data-value', regOriginal.StdDia || '0');
								}
								// Actualizar ProdKgDia si está disponible
								const prodKgDiaCell = filaOriginal.querySelector('[data-column="ProdKgDia"]');
								if (prodKgDiaCell && regOriginal.ProdKgDia !== undefined) {
									prodKgDiaCell.textContent = regOriginal.ProdKgDia ? parseFloat(regOriginal.ProdKgDia).toFixed(2) : '0';
									prodKgDiaCell.setAttribute('data-value', regOriginal.ProdKgDia || '0');
								}
							}
						} else {
							// Fallback: obtener datos del endpoint si no están en la respuesta (sin setTimeout para mayor velocidad)
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
										const regOriginal = resultOriginal.registro;
										const totalPedidoCell = filaOriginal.querySelector('[data-column="TotalPedido"]');
										const saldoPedidoCell = filaOriginal.querySelector('[data-column="SaldoPedido"]');
										const fechaFinalCell = filaOriginal.querySelector('[data-column="FechaFinal"]');
										const horasCell = filaOriginal.querySelector('[data-column="HorasNecesarias"]');
										const eficienciaCell = filaOriginal.querySelector('[data-column="Eficiencia"]');
										
										if (totalPedidoCell && regOriginal.TotalPedido !== undefined) {
											totalPedidoCell.textContent = regOriginal.TotalPedido || '0';
											totalPedidoCell.setAttribute('data-value', regOriginal.TotalPedido || '0');
										}
										if (saldoPedidoCell && regOriginal.SaldoPedido !== undefined) {
											saldoPedidoCell.textContent = regOriginal.SaldoPedido || '0';
											saldoPedidoCell.setAttribute('data-value', regOriginal.SaldoPedido || '0');
										}
										// ⚡ MEJORA: Actualizar FechaFinal si está disponible
										if (fechaFinalCell && regOriginal.FechaFinal) {
											const fechaFinal = new Date(regOriginal.FechaFinal);
											const fechaFormateada = fechaFinal.toLocaleDateString('es-ES', {
												day: '2-digit',
												month: '2-digit',
												year: 'numeric'
											});
											fechaFinalCell.textContent = fechaFormateada;
											fechaFinalCell.setAttribute('data-value', regOriginal.FechaFinal);
										}
										// ⚡ MEJORA: Actualizar campos de fórmulas si están disponibles
										if (horasCell && regOriginal.HorasNecesarias !== undefined) {
											horasCell.textContent = regOriginal.HorasNecesarias ? parseFloat(regOriginal.HorasNecesarias).toFixed(2) : '0';
											horasCell.setAttribute('data-value', regOriginal.HorasNecesarias || '0');
										}
										if (eficienciaCell && regOriginal.Eficiencia !== undefined) {
											eficienciaCell.textContent = regOriginal.Eficiencia ? parseFloat(regOriginal.Eficiencia).toFixed(2) + '%' : '0%';
											eficienciaCell.setAttribute('data-value', regOriginal.Eficiencia || '0');
										}
									}
								}
							}
						}
					} catch (e) {
						console.warn(`[DEBUG] ⚠️ Error al actualizar registro original:`, e);
					}
				}

				// Verificar si se agregaron todos los registros
				if (registrosAgregados.length < data.registros_ids.length) {
					console.warn(`[DEBUG] ⚠️ Solo se agregaron ${registrosAgregados.length} de ${data.registros_ids.length} registros esperados`);

					// Intentar agregar los registros faltantes
					const idsFaltantes = data.registros_ids.filter(id => !registrosAgregados.includes(parseInt(id)));
					console.log(`[DEBUG] 🔄 Intentando agregar registros faltantes:`, idsFaltantes);

					for (const idFaltante of idsFaltantes) {
						try {
							await new Promise(resolve => setTimeout(resolve, 300));
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
									await agregarRegistroSinRecargar({ registro_id: idFaltante, message: '' });
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
				console.log(`[DEBUG] ✅ RESUMEN FINAL: Se agregaron ${registrosAgregados.length} de ${data.registros_ids.length} registros esperados. Total filas en tabla: ${totalFilasFinal}`);

				// Verificar que todos los registros agregados estén visibles con sus datos correctos
				registrosAgregados.forEach((id, index) => {
					const fila = tb.querySelector(`tr.selectable-row[data-id="${id}"]`);
					if (fila) {
						const itemId = fila.querySelector('[data-column="ItemId"]')?.textContent?.trim() || fila.querySelector('[data-column="ItemId"]')?.getAttribute('data-value') || 'NO ENCONTRADO';
						const inventSizeId = fila.querySelector('[data-column="InventSizeId"]')?.textContent?.trim() || fila.querySelector('[data-column="InventSizeId"]')?.getAttribute('data-value') || 'NO ENCONTRADO';
						const tamanoClave = fila.querySelector('[data-column="TamanoClave"]')?.textContent?.trim() || fila.querySelector('[data-column="TamanoClave"]')?.getAttribute('data-value') || 'NO ENCONTRADO';
						console.log(`[DEBUG] 📋 Registro ${index + 1} (ID: ${id}):`, {
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
					const mensajeExito = data?.registros_vinculados
						? `Se vincularon ${data.registros_vinculados} registro(s) correctamente`
						: `Se duplicaron ${data.registros_duplicados || totalEsperado} registro(s) correctamente`;

					if (registrosAgregados.length === totalEsperado) {
						showToast(data.message || mensajeExito, 'success');
					} else {
						showToast(`Se agregaron ${registrosAgregados.length} de ${totalEsperado} registro(s). Algunos pueden no estar visibles.`, 'warning');
					}
				}

				return;
			} catch (error) {
				console.error('[DEBUG] ❌ Error al agregar múltiples registros con registros_ids:', error);
				// Continuar con fallback
			}
		}

		// Fallback: Si registros_ids no está disponible, usar método anterior con IDs secuenciales
		const totalRegistrosFallback = data?.registros_duplicados || data?.registros_vinculados || 1;
		if (data?.registro_id && (data?.salon_destino || data?.registros_vinculados)) {
			try {
				console.log('[DEBUG] 🔄 Fallback: Usando método de IDs secuenciales');
				await new Promise(resolve => setTimeout(resolve, 800));

				await agregarRegistroSinRecargar({ registro_id: data.registro_id, message: data.message });
				await new Promise(resolve => setTimeout(resolve, 300));

				const tb = document.querySelector('#mainTable tbody');
				if (tb) {
					const filasExistentes = Array.from(tb.querySelectorAll('.selectable-row')).map(f => f.getAttribute('data-id'));
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

		// Último fallback: si no se pueden obtener los IDs o hay error, intentar agregar al menos el primer registro
		if (data?.registro_id) {
			console.log('[DEBUG] 🔄 Fallback: Agregando solo el primer registro');
			await agregarRegistroSinRecargar(data);
			if (typeof showToast === 'function') {
				showToast(data.message || `Se duplicaron ${data.registros_duplicados} registro(s). Para ver todos los registros, recarga la página.`, 'warning');
			}
		} else if (data?.salon_destino && data?.telar_destino) {
			const url = new URL(window.location.href);
			url.searchParams.set('salon', data.salon_destino);
			url.searchParams.set('telar', data.telar_destino);
			window.location.href = url.toString();
		} else {
			window.location.reload();
		}
		return;
	}

	// Si solo hay un registro, agregarlo sin recargar
	if (data?.registro_id) {
		await agregarRegistroSinRecargar(data);
	} else if (data?.salon_destino && data?.telar_destino) {
		// Si no hay registro_id pero hay destino, recargar con filtros
		const url = new URL(window.location.href);
		url.searchParams.set('salon', data.salon_destino);
		url.searchParams.set('telar', data.telar_destino);
		window.location.href = url.toString();
	} else {
		window.location.reload();
	}
}

async function agregarRegistroSinRecargar(data) {
	if (!data?.registro_id) return;

	try {
		// Obtener los datos completos del registro usando el endpoint de detalles de balanceo
		// Este endpoint devuelve algunos campos, pero podemos complementar con los necesarios
		// Usar cache-busting para asegurar que obtenemos los datos más recientes
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

		const registro = result.registro;

		// Verificar que los campos clave estén presentes y actualizados
		console.log(`[DEBUG] 📥 Datos recibidos del endpoint para registro ${registro.Id}:`, {
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
			// Si no hay columnas, recargar
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

		console.log(`[DEBUG] ✅ Fila construida para registro ${registro.Id}:`, {
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
		const fechaInicio = registro.FechaInicio ? new Date(registro.FechaInicio) : null;
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
		console.log(`[DEBUG] 📍 Insertando fila ${registro.Id}`, {
			insertarAntes: insertarAntes ? insertarAntes.getAttribute('data-id') : 'null (al final)',
			totalFilasAntes: tb.querySelectorAll('.selectable-row').length
		});

		if (insertarAntes) {
			tb.insertBefore(row, insertarAntes);
		} else {
			tb.appendChild(row);
		}

		console.log(`[DEBUG] ✅ Fila ${registro.Id} insertada. Total filas después:`, tb.querySelectorAll('.selectable-row').length);

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
		const rowIndex = window.allRows.indexOf(row);
		if (rowIndex >= 0 && !window.dragDropMode) {
			// Remover listener anterior si existe
			if (row._selectionHandler) {
				row.removeEventListener('click', row._selectionHandler);
			}

			// Crear nuevo handler de selección
			row._selectionHandler = function(e) {
				// No seleccionar si estamos en modo inline edit y se hace click en una celda editable
				if (typeof inlineEditMode !== 'undefined' && inlineEditMode) {
					const cell = e.target.closest('td[data-column]');
					if (cell) {
						const col = cell.getAttribute('data-column');
						if (col && typeof uiInlineEditableFields !== 'undefined' && uiInlineEditableFields[col]) {
							return;
						}
					}
				}

				// No seleccionar si estamos en modo selección múltiple
				if (window.multiSelectMode) {
					return;
				}

				e.stopPropagation();
				const currentIndex = window.allRows.indexOf(row);
				if (typeof window.selectRow === 'function') {
					window.selectRow(row, currentIndex >= 0 ? currentIndex : rowIndex);
				}
			};

			// Asignar el evento click
			row.addEventListener('click', row._selectionHandler);
		}

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
		// Si hay error, recargar la página
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
	const tr = document.createElement('tr');
	tr.className = 'hover:bg-blue-50 cursor-pointer selectable-row';
	tr.setAttribute('data-id', registro.Id);

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
		let clases = 'px-3 py-2 text-sm text-gray-700 column-' + colIndex;
		if (col.dateType) {
			clases += ' whitespace-normal';
		} else {
			clases += ' whitespace-nowrap';
		}

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
			const dt = new Date(value);
			if (dt.getFullYear() <= 1970) return '';
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
			const dt = new Date(value);
			if (dt.getFullYear() <= 1970) return '';
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

	// Valor por defecto
	return String(value);
}

// Funciones helper para mostrar/ocultar loading
function showLoading() {
	if (window.PT && window.PT.loader) {
		window.PT.loader.show();
	} else if (typeof Swal !== 'undefined') {
		Swal.showLoading();
	}
}

function hideLoading() {
	if (window.PT && window.PT.loader) {
		window.PT.loader.hide();
	} else if (typeof Swal !== 'undefined') {
		Swal.hideLoading();
	}
}

// Función helper para obtener datos de una fila
function getRowInputs(row) {
	return {
		pedidoTempoInput: row.querySelector('input[name="pedido-tempo-destino[]"]'),
		porcentajeSegundosInput: row.querySelector('input[name="porcentaje-segundos-destino[]"]'),
		totalInput: row.querySelector('input[name="pedido-destino[]"]')
	};
}

function getProduccionInputFromRow(row) {
	const produccionCell = row?.querySelector('.produccion-cell');
	if (!produccionCell) {
		return null;
	}
	return produccionCell.querySelector('input[readonly]') ||
		produccionCell.querySelector('input:not([name])') ||
		produccionCell.querySelector('input[type="hidden"]') ||
		produccionCell.querySelector('input');
}
