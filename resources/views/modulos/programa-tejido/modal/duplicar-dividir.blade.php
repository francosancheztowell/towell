{{-- Modal Duplicar Registro - Componente separado --}}
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

// ===== Función para duplicar y dividir telar =====
async function duplicarTelar(row) {
	const telar = getRowTelar(row);
	const salon = getRowSalon(row);

	if (!telar || !salon) {
		showToast('No se pudo obtener la información del telar', 'error');
		return;
	}

	// Obtener datos del registro seleccionado para prellenar
	const codArticulo = row.querySelector('[data-column="ItemId"]')?.textContent?.trim() || '';
	// Clave Modelo se toma de la columna TamanoClave (si existe) o cae a Cod. Artículo
	const claveModelo = row.querySelector('[data-column="TamanoClave"]')?.textContent?.trim() || codArticulo;
	const producto = row.querySelector('[data-column="NombreProducto"]')?.textContent?.trim() || '';
	const hilo = row.querySelector('[data-column="FibraRizo"]')?.textContent?.trim() || '';
	const pedido = row.querySelector('[data-column="TotalPedido"]')?.textContent?.trim() || '';
	const flog = row.querySelector('[data-column="FlogsId"]')?.textContent?.trim() || '';
	const saldo = row.querySelector('[data-column="SaldoPedido"]')?.textContent?.trim() || pedido;

	// Verificar si el registro ya tiene OrdCompartida (ya fue dividido antes)
	const ordCompartidaCell = row.querySelector('[data-column="OrdCompartida"]')?.textContent || '';
	const ordCompartidaAttr = row.getAttribute('data-ord-compartida') || row.dataset?.ordCompartida || '';
	let ordCompartida = (ordCompartidaCell || ordCompartidaAttr || '').toString().trim();
	const registroId = row.getAttribute('data-id');

	// Fallback: si no se obtuvo del DOM, intentar obtener del backend
	if (!ordCompartida && registroId) {
		try {
			const resp = await fetch(`/planeacion/programa-tejido/${registroId}/detalles-balanceo`, {
				headers: { 'Accept': 'application/json' }
			});
			if (resp.ok) {
				const data = await resp.json();
				if (data?.registro?.OrdCompartida !== undefined && data.registro.OrdCompartida !== null) {
					ordCompartida = String(data.registro.OrdCompartida).trim();
				}
			}
		} catch (err) {
			console.warn('No se pudo obtener OrdCompartida del backend', err);
		}
	}

	// Resetear variables globales
	registrosOrdCompartidaExistentes = [];
	const ordNum = Number(ordCompartida);
	ordCompartidaActual = Number.isFinite(ordNum) ? ordNum : null;

	// Modal con formato de tabla
	const resultado = await Swal.fire({
		html: generarHTMLModalDuplicar({ telar, salon, codArticulo, claveModelo, producto, hilo, pedido, saldo, flog, ordCompartida, registroId }),
		width: '750px',
		showCancelButton: true,
		confirmButtonText: 'Aceptar',
		cancelButtonText: 'Cancelar',
		// Desactivar estilos por defecto de SweetAlert (morado) y usar clases Tailwind
		buttonsStyling: false,
		customClass: {
			confirmButton: 'swal-confirm-btn inline-flex justify-center px-4 py-2 text-sm font-semibold rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500',
			cancelButton: 'ml-2 inline-flex justify-center px-4 py-2 text-sm font-semibold rounded-md text-gray-700 bg-white hover:bg-gray-50 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300'
		},
		didOpen: () => {
			initModalDuplicar(telar, hilo, ordCompartida, registroId);
		},
		preConfirm: () => {
			return validarYCapturarDatosDuplicar();
		}
	});

	if (!resultado.isConfirmed) {
		return;
	}

	const datos = resultado.value;

	// Determinar endpoint según el modo y si el checkbox de vincular está activo
	const usarVincular = datos.vincular === true && datos.modo === 'duplicar';
	const endpoint = datos.modo === 'dividir'
		? '/planeacion/programa-tejido/dividir-saldo'
		: usarVincular
			? '/planeacion/programa-tejido/vincular-telar'
			: '/planeacion/programa-tejido/duplicar-telar';

	const mensajeExito = datos.modo === 'dividir'
		? 'Registro dividido correctamente'
		: usarVincular
			? 'Registro vinculado correctamente'
			: 'Telar duplicado correctamente';

	// Enviar al backend
	showLoading();
	try {
		const response = await fetch(endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
			},
			body: JSON.stringify({
				salon_tejido_id: salon,
				no_telar_id: telar,
				salon_destino: datos.salon,
				destinos: datos.destinos,
				tamano_clave: datos.claveModelo,
				cod_articulo: datos.codArticulo,
				producto: datos.producto,
				hilo: datos.hilo,
				pedido: datos.pedido,
				flog: datos.flog,
				aplicacion: datos.aplicacion,
				modo: datos.modo,
				descripcion: datos.descripcion,
				custname: datos.custname,
				invent_size_id: datos.inventSizeId,
				ord_compartida_existente: datos.ord_compartida_existente,
				registro_id_original: datos.registro_id_original
			})
		});

		const data = await response.json();
		hideLoading();

		// Verificar si hay error de validación de calendario (422)
		if (response.status === 422 && data.tipo_error === 'calendario_sin_fechas') {
			// Mostrar alerta detallada sobre el problema de calendario
			let mensajeError = `<div class="text-left">`;
			mensajeError += `<div class="bg-red-50 border-l-4 border-red-400 p-4 mb-3">`;
			mensajeError += `<p class="font-semibold text-red-800 mb-2">No se puede duplicar</p>`;
			mensajeError += `<p class="text-sm text-red-700 mb-2">${data.message}</p>`;

			if (data.calendario_id && data.fecha_inicio && data.fecha_fin) {
				mensajeError += `<div class="mt-3 text-xs text-red-600">`;
				mensajeError += `<p><strong>Calendario:</strong> ${data.calendario_id}</p>`;
				mensajeError += `</div>`;
			}

			mensajeError += `</div>`;
			mensajeError += `<p class="text-xs text-gray-600 mt-3">Por favor, agregue fechas al calendario en el catálogo de calendarios antes de intentar duplicar nuevamente.</p>`;
			mensajeError += `</div>`;

			Swal.fire({
				title: 'Error: Calendario sin fechas',
				html: mensajeError,
				icon: 'error',
				confirmButtonText: 'Entendido',
				confirmButtonColor: '#dc2626',
				width: '600px'
			});
			return;
		}

		if (data.success) {
			// Verificar si hay advertencias de calendario
			if (data.advertencias && data.advertencias.tipo === 'calendario_sin_fechas') {
				// Mostrar alerta detallada sobre los problemas de calendario
				const detalles = data.advertencias.detalles || [];
				let mensajeAdvertencia = `<div class="text-left">`;
				mensajeAdvertencia += `<p class="mb-3 text-sm text-gray-700">${data.message}</p>`;
				mensajeAdvertencia += `<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-3">`;
				mensajeAdvertencia += `<p class="font-semibold text-yellow-800 mb-2">Advertencia: Problemas con calendarios</p>`;
				mensajeAdvertencia += `<p class="text-sm text-yellow-700 mb-2">${data.advertencias.total_errores} programa(s) no pudieron generar líneas diarias porque no hay fechas disponibles en el calendario.</p>`;

				if (detalles.length > 0) {
					mensajeAdvertencia += `<ul class="list-disc list-inside text-xs text-yellow-700 space-y-1">`;
					detalles.forEach((detalle, index) => {
						if (index < 5) { // Mostrar máximo 5 detalles
							mensajeAdvertencia += `<li>Calendario '<strong>${detalle.calendario_id}</strong>': ${detalle.mensaje}</li>`;
						}
					});
					if (detalles.length > 5) {
						mensajeAdvertencia += `<li>... y ${detalles.length - 5} más</li>`;
					}
					mensajeAdvertencia += `</ul>`;
				}
				mensajeAdvertencia += `</div>`;
				mensajeAdvertencia += `<p class="text-xs text-gray-600">Los programas se crearon correctamente, pero necesitas agregar fechas al calendario para generar las líneas diarias.</p>`;
				mensajeAdvertencia += `</div>`;

				Swal.fire({
					title: 'Duplicación completada con advertencias',
					html: mensajeAdvertencia,
					icon: 'warning',
					confirmButtonText: 'Entendido',
					confirmButtonColor: '#f59e0b',
					width: '600px'
				}).then(() => {
					// Redirigir después de cerrar la alerta
					if (data.salon_destino && data.telar_destino) {
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
				});
			} else {
				// Sin advertencias, mostrar mensaje de éxito normal
				showToast(data.message || mensajeExito, 'success');

				// Redirigir inmediatamente al registro creado
				if (data.salon_destino && data.telar_destino) {
					// Construir URL con parámetros para seleccionar el registro
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
		} else {
			showToast(data.message || 'Error al procesar la solicitud', 'error');
		}
	} catch (error) {
		hideLoading();
		showToast('Ocurrió un error al procesar la solicitud', 'error');
	}
}

// Genera el HTML del modal de duplicar
function generarHTMLModalDuplicar({ telar, salon, codArticulo, claveModelo, producto, hilo, pedido, saldo, flog, ordCompartida, registroId }) {
	// Determinar si ya está dividido (tiene OrdCompartida)
	const ordNum = Number(ordCompartida);
	const yaDividido = Number.isFinite(ordNum) && ordNum !== 0;
	const badgeOrdCompartida = yaDividido
		? ''
		: '';

	return `
		<div class="text-left">
			<div id="alerta-clave-modelo" class="hidden mb-3 px-4 py-2 bg-amber-50 border border-amber-300 rounded-md text-amber-700 text-sm">
				<i class="fas fa-exclamation-triangle mr-2"></i>
				<span id="alerta-clave-modelo-texto"></span>
			</div>

			<!-- Indicador de registro ya dividido -->
			${yaDividido ? `
			<div id="info-ord-compartida" class="mb-3 px-4 py-2 bg-green-50 border border-green-300 rounded-md text-green-700 text-sm">
				<span>Este registro ya pertenece a un grupo dividido. Al cambiar a modo "Dividir", verás los telares existentes.</span>
			</div>
			` : ''}

			<table class="w-full border-collapse">
				<tbody>
					<tr class="border-b border-gray-200">
						<td colspan="2" class="py-3">
							<div class="grid grid-cols-3 gap-4">
								<div class="relative">
									<label class="block mb-1 text-sm font-medium text-gray-700">Clave Modelo</label>
									<input type="text" id="swal-claveModelo" value="${claveModelo}" data-salon="${salon}"
										placeholder="Escriba para buscar..."
										class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
									<div id="swal-claveModelo-suggestions" class="absolute z-50 w-full bg-white border border-gray-300 rounded-b shadow-lg hidden max-h-40 overflow-y-auto"></div>
								</div>
								<div>
									<label class="block mb-1 text-sm font-medium text-gray-700">Cod. Artículo</label>
									<input type="text" id="swal-codArticulo" value="${codArticulo}" readonly
										class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-sm">
								</div>
								<div>
									<label class="block mb-1 text-sm font-medium text-gray-700">Producto</label>
									<input type="text" id="swal-producto" value="${producto}" readonly
										class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-sm">
								</div>
							</div>
						</td>
					</tr>
					<tr class="border-b border-gray-200">
						<td colspan="2" class="py-3">
							<div class="grid grid-cols-3 gap-4">
								<div>
									<label class="block mb-1 text-sm font-medium text-gray-700">Hilo</label>
									<select id="swal-hilo" data-hilo-actual="${hilo}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
										${hilo ? `<option value="${hilo}" selected>${hilo}</option>` : '<option value="">Seleccionar...</option>'}
									</select>
								</div>
								<div>
									<label class="block mb-1 text-sm font-medium text-gray-700">Pedido Total ${badgeOrdCompartida}</label>
									<input type="text" id="swal-pedido" value="${pedido}"
										class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
								</div>
								<div class="relative">
									<label class="block mb-1 text-sm font-medium text-gray-700">Flog</label>
									<input type="text" id="swal-flog" value="${flog}" placeholder="Escriba para buscar..."
										class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
									<div id="swal-flog-suggestions" class="absolute z-50 w-full bg-white border border-gray-300 rounded-b shadow-lg hidden max-h-40 overflow-y-auto"></div>
								</div>
							</div>
						</td>
					</tr>
					<tr class="border-b border-gray-200">
						<td colspan="2" class="py-1">
							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block mb-1 text-sm font-medium text-gray-700">Salón</label>
									<select id="swal-salon" data-salon-actual="${salon}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
										${salon ? `<option value="${salon}" selected>${salon}</option>` : '<option value="">Seleccionar...</option>'}
									</select>
								</div>
								<div>
									<label class="block mb-1 text-sm font-medium text-gray-700">Aplicación</label>
									<select id="swal-aplicacion" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
										<option value="">Seleccionar...</option>
									</select>
								</div>
							</div>
						</td>
					</tr>
				</tbody>
			</table>

			<!-- Campos ocultos para datos adicionales del codificado -->
			<input type="hidden" id="swal-descripcion" value="">
			<input type="hidden" id="swal-custname" value="">
			<input type="hidden" id="swal-inventsizeid" value="">

			<!-- Switch Dividir/Duplicar (pill reactivo: Duplicar azul, Dividir verde) -->
			<div class="my-4 flex items-center justify-center gap-4">
				<!-- radio buttons ocultos para estado lógico de los 2 modos -->
				<input type="radio" id="modo-duplicar" name="modo-switch" class="hidden" checked>
				<input type="radio" id="modo-dividir" name="modo-switch" class="hidden">
				<!-- checkbox manteniendo compatibilidad con lógica existente -->
				<input type="checkbox" id="switch-modo" class="hidden" checked>

				<div class="inline-flex items-center rounded-full px-1 py-1 text-xs font-medium shadow-sm gap-1">
					<button
						type="button"
						id="pill-duplicar"
						class="px-4 py-1 rounded-full transition-all duration-200 bg-blue-500 text-white shadow-md opacity-100">
						Duplicar
					</button>
					<button
						type="button"
						id="pill-dividir"
						class="px-4 py-1 rounded-full transition-all duration-200 bg-white text-gray-700 shadow-sm opacity-80">
						Dividir
					</button>
				</div>

				<!-- Checkbox Vincular (solo visible en modo Duplicar) -->
				<div id="checkbox-vincular-container" class="flex items-center gap-2">
					<input type="checkbox" id="checkbox-vincular" class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
					<label for="checkbox-vincular" class="text-sm text-gray-700 cursor-pointer">
						Vincular
					</label>
				</div>

				<div id="modo-descripcion" class="hidden">
					<span id="desc-duplicar">Copia el registro al telar destino</span>
					<span id="desc-dividir" class="hidden">Divide la cantidad entre los telares</span>
				</div>
			</div>

			<!-- Campos ocultos para datos del telar original -->
			<input type="hidden" id="telar-original" value="${telar}">
			<input type="hidden" id="pedido-original" value="${pedido}">
			<input type="hidden" id="saldo-original" value="${saldo}">
			<input type="hidden" id="ord-compartida-original" value="${ordCompartida}">
			<input type="hidden" id="registro-id-original" value="${registroId}">



			<!-- Tabla de Telar y Pedido -->
			<div class="border border-gray-300 rounded-lg overflow-hidden">
				<table class="w-full border-collapse">
					<thead class="bg-gray-100">
						<tr>
							<th id="th-telar" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300 w-1/2">Telar</th>
							<th id="th-pedido" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-gray-300 w-1/2">Pedido</th>
							<th class="py-2 px-2 text-center border-b border-gray-300 w-10">
								<button type="button" id="btn-add-telar-row" class="text-green-600 hover:text-green-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Añadir fila">
									<i class="fas fa-plus-circle text-lg"></i>
								</button>
							</th>
						</tr>
					</thead>
					<tbody id="telar-pedido-body">
						<tr class="telar-row" id="fila-principal">
							<td class="p-2 border-r border-gray-200">
								<select name="telar-destino[]" data-telar-actual="${telar}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 telar-destino-select">
									${telar ? `<option value="${telar}" selected>${telar}</option>` : '<option value="">Seleccionar...</option>'}
								</select>
							</td>
							<td class="p-2">
								<input type="text" name="pedido-destino[]" value="${pedido}"
									class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
							</td>
							<td class="p-2 text-center w-10"></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	`;
}

// Inicializa los eventos y carga de datos del modal
function initModalDuplicar(telar, hiloActualParam, ordCompartidaParam, registroIdParam) {
	const btnAdd = document.getElementById('btn-add-telar-row');
	const tbody = document.getElementById('telar-pedido-body');
	const selectHilo = document.getElementById('swal-hilo');
	const selectSalon = document.getElementById('swal-salon');
	const selectAplicacion = document.getElementById('swal-aplicacion');
	const inputClaveModelo = document.getElementById('swal-claveModelo');
	const containerSugerencias = document.getElementById('swal-claveModelo-suggestions');
	const inputCodArticulo = document.getElementById('swal-codArticulo');
	const inputProducto = document.getElementById('swal-producto');
	const inputFlog = document.getElementById('swal-flog');
	const containerSugerenciasFlog = document.getElementById('swal-flog-suggestions');
	const hiloActual = selectHilo?.dataset?.hiloActual || hiloActualParam || '';
	const salonActual = selectSalon?.dataset?.salonActual || '';
	const telarActual = telar || '';
	const confirmButton = Swal.getConfirmButton();

	// Datos de OrdCompartida
	const ordCompartidaActualLocal = ordCompartidaParam || document.getElementById('ord-compartida-original')?.value || '';
	const registroIdActual = registroIdParam || document.getElementById('registro-id-original')?.value || '';
	const tieneOrdCompartida = ordCompartidaActualLocal && ordCompartidaActualLocal !== '' && ordCompartidaActualLocal !== '0';

	// Variables para almacenar datos cargados
	let telaresDisponibles = [];
	let salonesDisponibles = [];
	let sugerenciasClaveModelo = [];
	let sugerenciasFlog = [];
	let todasOpcionesFlog = [];
	let debounceTimer = null;
	let debounceTimerFlog = null;

	// Referencias a campos ocultos para datos adicionales
	const inputDescripcion = document.getElementById('swal-descripcion');
	const inputCustname = document.getElementById('swal-custname');
	const inputInventSizeId = document.getElementById('swal-inventsizeid');

	// Inputs/Selects de la primera fila Telar/Pedido
	const firstTelarSelect = tbody.querySelector('select[name="telar-destino[]"]');
	const firstPedidoInput = tbody.querySelector('input[name="pedido-destino[]"]');

	function recomputeState() {
		const modoActual = getModoActual();
		const esDuplicar = modoActual === 'duplicar';
		const telarInputs = document.querySelectorAll('[name="telar-destino[]"]'); // select o input
		const pedidoInputs = document.querySelectorAll('input[name="pedido-destino[]"]');

		let firstComplete = false;
		let hasAnyFilled = false;
		let allDestinationsValid = true;

		telarInputs.forEach((input, idx) => {
			const telarVal = input.value.trim();
			const pedidoVal = (pedidoInputs[idx]?.value || '').trim();

			if (esDuplicar) {
				// Modo Duplicar: primera fila debe estar completa
				if (idx === 0 && telarVal !== '' && pedidoVal !== '') {
					firstComplete = true;
				}
				if (telarVal !== '' || pedidoVal !== '') {
					hasAnyFilled = true;
				}
			} else {
				// Modo Dividir:
				// - Primera fila (origen) siempre tiene telar (readonly), solo necesita pedido
				// - Las siguientes filas (destino) necesitan telar Y pedido
				if (idx === 0) {
					// El origen siempre tiene telar, verificar si tiene cantidad
					if (telarVal !== '' && pedidoVal !== '') {
						firstComplete = true;
					}
					hasAnyFilled = telarVal !== '';
				} else {
					// Destinos: deben tener telar seleccionado Y cantidad
					if (telarVal === '' || pedidoVal === '') {
						allDestinationsValid = false;
					}
					if (telarVal !== '' || pedidoVal !== '') {
						hasAnyFilled = true;
					}
				}
			}
		});

		// En modo duplicar: primera fila completa habilita agregar más
		// En modo dividir: siempre puede agregar más destinos
		btnAdd.disabled = esDuplicar ? !firstComplete : false;

		// En modo duplicar: al menos un registro lleno
		// En modo dividir: origen con cantidad Y al menos un destino válido
		if (esDuplicar) {
			confirmButton.disabled = !hasAnyFilled;
		} else {
			const tieneDestinos = telarInputs.length > 1;
			const origenTieneCantidad = pedidoInputs[0]?.value?.trim() !== '';
			confirmButton.disabled = !tieneDestinos || !allDestinationsValid || !origenTieneCantidad;
		}
	}

	// Función para actualizar todos los selects de telar en la tabla de destinos
	function actualizarSelectsTelares(preseleccionarPrimero = false) {
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

	// Función para cargar telares por salón
	function cargarTelaresPorSalon(salon, preseleccionarTelar = false) {
		if (!salon) {
			telaresDisponibles = [];
			actualizarSelectsTelares(false);
			recomputeState();
			return;
		}

		fetch('/programa-tejido/telares-by-salon?salon_tejido_id=' + encodeURIComponent(salon))
			.then(response => response.json())
			.then(data => {
				telaresDisponibles = Array.isArray(data) ? data : [];
				// Actualizar los selects de la tabla de destinos, preseleccionando si se indica
				actualizarSelectsTelares(preseleccionarTelar);
				recomputeState();
			})
			.catch(() => {
				telaresDisponibles = [];
				actualizarSelectsTelares(false);
				recomputeState();
			});
	}

	// ===== Autocompletado de Clave Modelo =====
	function buscarClaveModelo(busqueda) {
		const salonParaBuscar = selectSalon?.value || salonActual;
		if (!salonParaBuscar || busqueda.length < 1) {
			containerSugerencias.classList.add('hidden');
			return;
		}

		const params = new URLSearchParams();
		params.append('salon_tejido_id', salonParaBuscar);
		params.append('search', busqueda);

		fetch('/programa-tejido/tamano-clave-by-salon?' + params)
			.then(r => r.json())
			.then(opciones => {
				sugerenciasClaveModelo = Array.isArray(opciones) ? opciones : [];
				mostrarSugerenciasClaveModelo(sugerenciasClaveModelo);
			})
			.catch(() => {
				sugerenciasClaveModelo = [];
				containerSugerencias.classList.add('hidden');
			});
	}

	function mostrarSugerenciasClaveModelo(sugerencias) {
		containerSugerencias.innerHTML = '';
		if (sugerencias.length === 0) {
			const div = document.createElement('div');
			div.className = 'px-3 py-2 text-gray-500 text-xs italic';
			div.textContent = 'No se encontraron coincidencias';
			containerSugerencias.appendChild(div);
			containerSugerencias.classList.remove('hidden');
			return;
		}

		sugerencias.forEach(sug => {
			const div = document.createElement('div');
			div.className = 'px-3 py-2 hover:bg-blue-100 cursor-pointer text-sm';
			div.textContent = sug;
			div.addEventListener('click', () => seleccionarClaveModelo(sug));
			containerSugerencias.appendChild(div);
		});
		containerSugerencias.classList.remove('hidden');
	}

	function seleccionarClaveModelo(clave) {
		inputClaveModelo.value = clave;
		containerSugerencias.classList.add('hidden');
		cargarDatosRelacionados(clave);
	}

	function cargarDatosRelacionados(tamanoClave) {
		const salonParaBuscar = selectSalon?.value || salonActual;
		if (!salonParaBuscar || !tamanoClave) return;

		const params = new URLSearchParams();
		params.append('salon_tejido_id', salonParaBuscar);
		params.append('tamano_clave', tamanoClave);

		fetch('/programa-tejido/datos-relacionados?' + params.toString(), {
			method: 'GET',
			headers: {
				'Accept': 'application/json'
			}
		})
			.then(r => r.json())
			.then(data => {
				if (data.datos) {
					// Llenar Cod. Artículo y Producto
					inputCodArticulo.value = data.datos.ItemId || '';
					inputProducto.value = data.datos.Nombre || data.datos.NombreProducto || '';

					// Llenar Flog con el FlogsId del codificado
					if (inputFlog && data.datos.FlogsId) {
						inputFlog.value = data.datos.FlogsId;
					}

					// Almacenar descripcion, custname e InventSizeId en campos ocultos
					if (inputDescripcion) inputDescripcion.value = data.datos.NombreProyecto || '';
					if (inputCustname) inputCustname.value = data.datos.CustName || '';
					if (inputInventSizeId) inputInventSizeId.value = data.datos.InventSizeId || '';

					// Disparar cambio visual/interno si hay listeners
					inputCodArticulo.dispatchEvent(new Event('input', { bubbles: true }));
					inputProducto.dispatchEvent(new Event('input', { bubbles: true }));
				}
			})
			.catch(() => {});
	}

	// Eventos para el autocompletado de Clave Modelo
	if (inputClaveModelo) {
		inputClaveModelo.addEventListener('input', (e) => {
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(() => buscarClaveModelo(e.target.value), 150);
		});

		inputClaveModelo.addEventListener('focus', () => {
			if (inputClaveModelo.value.length >= 1) {
				buscarClaveModelo(inputClaveModelo.value);
			}
		});

		inputClaveModelo.addEventListener('blur', (e) => {
			// Delay para permitir click en sugerencia
			setTimeout(() => containerSugerencias.classList.add('hidden'), 200);
		// Si hay valor escrito, cargar datos relacionados
		const val = inputClaveModelo.value?.trim();
		if (val) cargarDatosRelacionados(val);
		});

		inputClaveModelo.addEventListener('keydown', (e) => {
			if (e.key === 'Enter') {
				e.preventDefault();
				containerSugerencias.classList.add('hidden');
				if (inputClaveModelo.value) {
					cargarDatosRelacionados(inputClaveModelo.value);
				}
			}
		});
	}

	// ===== Autocompletado de Flog =====
	async function cargarOpcionesFlog(search = '') {
		try {
			// Si no hay búsqueda y ya tenemos opciones cargadas, usar esas
			if (!search && sugerenciasFlog && sugerenciasFlog.length > 0) {
				mostrarSugerenciasFlog(sugerenciasFlog);
				return;
			}

			const response = await fetch('/programa-tejido/flogs-id-from-twflogs', {
				headers: {
					'Accept': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
				}
			});

			const opciones = await response.json();
			const opcionesArray = Array.isArray(opciones) ? opciones : [];

			// Si no hay búsqueda, guardar todas las opciones para uso futuro
			if (!search) {
				todasOpcionesFlog = opcionesArray;
			}

			// Filtrar opciones localmente si hay búsqueda
			let opcionesFiltradas = opcionesArray;
			if (search && search.length >= 2) {
				const searchLower = search.toLowerCase();
				const opcionesBase = todasOpcionesFlog.length > 0 ? todasOpcionesFlog : opcionesArray;
				opcionesFiltradas = opcionesBase.filter(opcion =>
					opcion && String(opcion).toLowerCase().includes(searchLower)
				);
			}

			sugerenciasFlog = opcionesFiltradas;
			mostrarSugerenciasFlog(opcionesFiltradas);
		} catch (error) {
			console.error('Error al cargar Flog:', error);
			if (containerSugerenciasFlog) {
				containerSugerenciasFlog.classList.add('hidden');
			}
		}
	}

	function mostrarSugerenciasFlog(sugerencias) {
		if (!containerSugerenciasFlog) return;

		containerSugerenciasFlog.innerHTML = '';

		if (sugerencias.length === 0) {
			const div = document.createElement('div');
			div.className = 'px-3 py-2 text-gray-500 text-xs italic';
			div.textContent = 'No se encontraron coincidencias';
			containerSugerenciasFlog.appendChild(div);
			containerSugerenciasFlog.classList.remove('hidden');
			return;
		}

		sugerencias.forEach(sugerencia => {
			const div = document.createElement('div');
			div.className = 'px-3 py-2 hover:bg-blue-100 cursor-pointer text-sm';
			div.textContent = sugerencia;
			div.addEventListener('click', () => {
				if (inputFlog) {
					inputFlog.value = sugerencia;
					containerSugerenciasFlog.classList.add('hidden');
					// Cargar descripción cuando se selecciona un Flog
					cargarDescripcionPorFlog(sugerencia);
				}
			});
			containerSugerenciasFlog.appendChild(div);
		});

		containerSugerenciasFlog.classList.remove('hidden');
	}

	async function cargarDescripcionPorFlog(flog) {
		if (!flog || flog.trim() === '') {
			return;
		}

		try {
			const response = await fetch(`/programa-tejido/descripcion-by-idflog/${encodeURIComponent(flog)}`, {
				headers: {
					'Accept': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
				}
			});

			const data = await response.json();
			if (inputDescripcion && data.nombreProyecto) {
				inputDescripcion.value = data.nombreProyecto;
			}
		} catch (error) {
			console.error('Error al cargar descripción por Flog:', error);
		}
	}

	// Eventos para el autocompletado de Flog
	if (inputFlog && containerSugerenciasFlog) {
		inputFlog.addEventListener('input', (e) => {
			clearTimeout(debounceTimerFlog);
			const valor = e.target.value;
			if (valor.length >= 2) {
				debounceTimerFlog = setTimeout(() => cargarOpcionesFlog(valor), 300);
			} else {
				containerSugerenciasFlog.classList.add('hidden');
			}
		});

		inputFlog.addEventListener('focus', async () => {
			if (sugerenciasFlog && sugerenciasFlog.length > 0) {
				mostrarSugerenciasFlog(sugerenciasFlog);
			} else {
				await cargarOpcionesFlog('');
			}
		});

		inputFlog.addEventListener('blur', (e) => {
			// Delay para permitir click en sugerencia
			setTimeout(() => containerSugerenciasFlog.classList.add('hidden'), 200);
		});

		inputFlog.addEventListener('keydown', (e) => {
			if (e.key === 'Enter') {
				e.preventDefault();
				containerSugerenciasFlog.classList.add('hidden');
				if (inputFlog.value) {
					cargarDescripcionPorFlog(inputFlog.value);
				}
			}
		});
	}

	// Estado inicial: bloqueado
	btnAdd.disabled = true;
	confirmButton.disabled = true;

	if (firstTelarSelect) {
		firstTelarSelect.addEventListener('change', recomputeState);
	}
	if (firstPedidoInput) {
		firstPedidoInput.addEventListener('input', recomputeState);
	}

	// Cargar datos en paralelo para mayor velocidad
	const fetchSalones = fetch('/programa-tejido/salon-tejido-options', {
		headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
	}).then(r => r.json()).catch(() => null);

	const fetchHilos = fetch('/planeacion/catalogos/matriz-hilos/list', {
		headers: { 'Accept': 'application/json' }
	}).then(r => r.json()).catch(() => null);

	const fetchTelares = salonActual
		? fetch('/programa-tejido/telares-by-salon?salon_tejido_id=' + encodeURIComponent(salonActual), {
			headers: { 'Accept': 'application/json' }
		}).then(r => r.json()).catch(() => [])
		: Promise.resolve([]);

	const fetchAplicaciones = fetch('/programa-tejido/aplicacion-id-options', {
		headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
	}).then(r => r.json()).catch(() => []);

	// Procesar resultados en paralelo (preservando valores preseleccionados)
	Promise.all([fetchSalones, fetchHilos, fetchTelares, fetchAplicaciones]).then(([dataSalones, dataHilos, dataTelares, dataAplicaciones]) => {
		// Procesar salones - mantener valor actual y agregar opciones
		let opciones = [];
		if (Array.isArray(dataSalones)) {
			opciones = dataSalones;
		} else if (dataSalones?.data && Array.isArray(dataSalones.data)) {
			opciones = dataSalones.data;
		} else if (dataSalones && typeof dataSalones === 'object') {
			opciones = Object.values(dataSalones).filter(v => typeof v === 'string');
		}

		// Solo reconstruir si hay opciones nuevas
		if (opciones.length > 0) {
			salonesDisponibles = opciones;
			const valorActualSalon = selectSalon.value;
			selectSalon.innerHTML = '<option value="">Seleccionar...</option>';
			opciones.forEach(item => {
				const option = document.createElement('option');
				option.value = item;
				option.textContent = item;
				if (item === valorActualSalon || item === salonActual) option.selected = true;
				selectSalon.appendChild(option);
			});
		}

		// Procesar hilos - mantener valor actual y agregar opciones
		if (dataHilos?.success && dataHilos.data && dataHilos.data.length > 0) {
			const valorActualHilo = selectHilo.value;
			selectHilo.innerHTML = '<option value="">Seleccionar...</option>';
			dataHilos.data.forEach(item => {
				const option = document.createElement('option');
				option.value = item.Hilo;
				option.textContent = item.Hilo + (item.Fibra ? ' - ' + item.Fibra : '');
				if (item.Hilo === valorActualHilo || item.Hilo === hiloActual) option.selected = true;
				selectHilo.appendChild(option);
			});
		}

		// Procesar telares y preseleccionar el telar actual en la primera fila
		telaresDisponibles = Array.isArray(dataTelares) ? dataTelares : [];
		actualizarSelectsTelares(true); // true = preseleccionar telar actual en primera fila

		// Procesar aplicaciones
		if (dataAplicaciones && (Array.isArray(dataAplicaciones) ? dataAplicaciones.length > 0 : true)) {
			const aplicacionesArray = Array.isArray(dataAplicaciones) ? dataAplicaciones : [];
			selectAplicacion.innerHTML = '<option value="">Seleccionar...</option>';
			aplicacionesArray.forEach(item => {
				const option = document.createElement('option');
				option.value = item;
				option.textContent = item;
				selectAplicacion.appendChild(option);
			});
			// Agregar opción NA si no existe
			if (!aplicacionesArray.includes('NA')) {
				const optionNA = document.createElement('option');
				optionNA.value = 'NA';
				optionNA.textContent = 'NA';
				selectAplicacion.appendChild(optionNA);
			}
			// Si no hay selección previa, forzar NA como valor por defecto
			if (!selectAplicacion.value) {
				const optNa = Array.from(selectAplicacion.options).find(o => o.value === 'NA');
				if (optNa) optNa.selected = true;
			}
		}

		// Siempre empezar en modo Duplicar (el usuario puede cambiar manualmente a Dividir si lo desea)
		document.getElementById('modo-duplicar').checked = true;
		switchModo.checked = true;

		// Aplicar estilo inicial del switch (después de que los telares estén cargados)
		actualizarEstiloSwitch();
		recomputeState();
	});

	// Elementos para mostrar alerta de clave modelo
	const alertaClaveModelo = document.getElementById('alerta-clave-modelo');
	const alertaClaveModeloTexto = document.getElementById('alerta-clave-modelo-texto');

	// Función para mostrar/ocultar alerta de clave modelo
	function mostrarAlertaClaveModelo(mensaje) {
		if (alertaClaveModelo && alertaClaveModeloTexto) {
			alertaClaveModeloTexto.textContent = mensaje;
			alertaClaveModelo.classList.remove('hidden');
		}
	}

	function ocultarAlertaClaveModelo() {
		if (alertaClaveModelo) {
			alertaClaveModelo.classList.add('hidden');
		}
	}

	// Función para validar si la clave modelo existe en el salón seleccionado
	function validarClaveModeloEnSalon(salon, claveModelo) {
		if (!salon || !claveModelo) {
			ocultarAlertaClaveModelo();
			return;
		}

		const params = new URLSearchParams();
		params.append('salon_tejido_id', salon);
		params.append('search', claveModelo);

		fetch('/programa-tejido/tamano-clave-by-salon?' + params)
			.then(r => r.json())
			.then(opciones => {
				const existe = Array.isArray(opciones) && opciones.some(op => op === claveModelo);
				if (!existe) {
					mostrarAlertaClaveModelo(`La clave modelo "${claveModelo}" no se encuentra en los codificados del salón "${salon}".`);
					// Limpiar campos relacionados ya que la clave no es válida para este salón
					inputClaveModelo.value = '';
					inputCodArticulo.value = '';
					inputProducto.value = '';
				} else {
					ocultarAlertaClaveModelo();
				}
			})
			.catch(() => {
				// En caso de error, no bloquear pero avisar
				console.warn('No se pudo validar la clave modelo en el salón seleccionado');
			});
	}

	// Evento cuando cambia el salón - cargar telares y validar clave modelo
	selectSalon.addEventListener('change', () => {
		cargarTelaresPorSalon(selectSalon.value, false);
		// Validar si la clave modelo actual existe en el nuevo salón
		const claveModeloActual = inputClaveModelo?.value?.trim();
		if (claveModeloActual) {
			validarClaveModeloEnSalon(selectSalon.value, claveModeloActual);
		}
	});

	// Evento para añadir nuevas filas de telar/pedido
	btnAdd.addEventListener('click', () => {
		const modoActual = getModoActual();
		const esDuplicar = modoActual === 'duplicar';

		if (!esDuplicar) {
			// Modo dividir: usar la función especializada
			agregarFilaDividir();
			recomputeState();
			return;
		}

		// Modo duplicar: comportamiento original
		const newRow = document.createElement('tr');
		newRow.className = 'telar-row border-t border-gray-200';

		// Crear el select con las opciones de telares disponibles del salón seleccionado
		let telarOptionsHTML = '<option value="">Seleccionar...</option>';
		telaresDisponibles.forEach(t => {
			telarOptionsHTML += '<option value="' + t + '">' + t + '</option>';
		});

		newRow.innerHTML =
			'<td class="p-2 border-r border-gray-200">' +
				'<select name="telar-destino[]" class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 telar-destino-select">' +
					telarOptionsHTML +
				'</select>' +
			'</td>' +
			'<td class="p-2">' +
				'<input type="text" name="pedido-destino[]" placeholder=""' +
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
			recomputeState();
		});

		const telarSelect = newRow.querySelector('select[name="telar-destino[]"]');
		const pedidoInput = newRow.querySelector('input[name="pedido-destino[]"]');
		if (telarSelect) telarSelect.addEventListener('change', recomputeState);
		if (pedidoInput) pedidoInput.addEventListener('input', recomputeState);
	});

	// ===== Switch Dividir/Duplicar =====
	const switchModo = document.getElementById('switch-modo');
	const pillDuplicar = document.getElementById('pill-duplicar');
	const pillDividir = document.getElementById('pill-dividir');
	const descDuplicar = document.getElementById('desc-duplicar');
	const descDividir = document.getElementById('desc-dividir');
	const checkboxVincular = document.getElementById('checkbox-vincular');
	const checkboxVincularContainer = document.getElementById('checkbox-vincular-container');
	const thTelar = document.getElementById('th-telar');
	const thPedido = document.getElementById('th-pedido');
	const telarOriginal = document.getElementById('telar-original')?.value || telarActual;
	const pedidoOriginal = document.getElementById('pedido-original')?.value || '';

	// Función para reconstruir la tabla según el modo
	async function reconstruirTablaSegunModo(esDuplicar) {
		// Limpiar filas adicionales
		const filasAdicionales = tbody.querySelectorAll('tr:not(#fila-principal)');
		filasAdicionales.forEach(fila => fila.remove());

		const filaPrincipal = document.getElementById('fila-principal');
		if (!filaPrincipal) return;

		// Mostrar/ocultar resumen de cantidades
		const resumenCantidades = document.getElementById('resumen-cantidades');
		if (resumenCantidades) {
			resumenCantidades.classList.toggle('hidden', esDuplicar);
		}

		if (esDuplicar) {
			// === MODO DUPLICAR ===
			// Header normal
			if (thTelar) thTelar.textContent = 'Telar';
			if (thPedido) thPedido.textContent = 'Pedido';

			// Primera fila: select editable para telar destino
			filaPrincipal.innerHTML = `
				<td class="p-2 border-r border-gray-200">
					<select name="telar-destino[]" data-telar-actual="${telarOriginal}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 telar-destino-select">
						${telarOriginal ? `<option value="${telarOriginal}" selected>${telarOriginal}</option>` : '<option value="">Seleccionar...</option>'}
					</select>
				</td>
				<td class="p-2">
					<input type="text" name="pedido-destino[]" value="${pedidoOriginal}"
						class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
				</td>
				<td class="p-2 text-center w-10"></td>
			`;

			// Actualizar opciones del select de telar
			const selectTelar = filaPrincipal.querySelector('select[name="telar-destino[]"]');
			if (selectTelar && telaresDisponibles.length > 0) {
				selectTelar.innerHTML = '<option value="">Seleccionar...</option>';
				telaresDisponibles.forEach(t => {
					const option = document.createElement('option');
					option.value = t;
					option.textContent = t;
					if (t == telarOriginal) option.selected = true;
					selectTelar.appendChild(option);
				});
			}

			// Re-registrar eventos
			const telarSelect = filaPrincipal.querySelector('select[name="telar-destino[]"]');
			const pedidoInput = filaPrincipal.querySelector('input[name="pedido-destino[]"]');
			if (telarSelect) telarSelect.addEventListener('change', recomputeState);
			if (pedidoInput) pedidoInput.addEventListener('input', recomputeState);

		} else {
			// === MODO DIVIDIR ===
			// Header indica origen y destino
			if (thTelar) thTelar.textContent = 'Telar';
			if (thPedido) thPedido.textContent = 'Cantidad a asignar';

			// Verificar si ya tiene OrdCompartida (ya fue dividido antes)
			if (tieneOrdCompartida) {
				// Cargar registros existentes del grupo
				await cargarRegistrosOrdCompartida(ordCompartidaActualLocal);
			} else {
				// Primera división - comportamiento original
				// Primera fila: telar ORIGINAL bloqueado (readonly)
				filaPrincipal.innerHTML = `
					<td class="p-2 border-r border-gray-200">
						<div class="flex items-center gap-2">
							<input type="text" name="telar-destino[]" value="${telarOriginal}" readonly
								data-registro-id=""
								class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
						</div>
					</td>
					<td class="p-2">
						<input type="text" name="pedido-destino[]" value="${pedidoOriginal}" placeholder="Cantidad para este telar..."
							class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-green-500"
							oninput="actualizarResumenCantidades()">
					</td>
					<td class="p-2 text-center w-10">
						<i class="fas fa-lock text-gray-400" title="Telar origen"></i>
					</td>
				`;

				// Agregar automáticamente una fila para el telar destino
				agregarFilaDividir();
			}

			// Re-registrar eventos
			const pedidoInput = filaPrincipal.querySelector('input[name="pedido-destino[]"]:not([readonly])');
			if (pedidoInput) pedidoInput.addEventListener('input', recomputeState);
		}

		recomputeState();
	}

	// Función para cargar registros existentes de OrdCompartida
	async function cargarRegistrosOrdCompartida(ordCompartida) {
		if (!ordCompartida) return;

		try {
			const response = await fetch(`/planeacion/programa-tejido/registros-ord-compartida/${ordCompartida}`, {
				headers: {
					'Accept': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
				}
			});

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

				// Crear filas para cada registro existente
				data.registros.forEach((reg, index) => {
					const esRegistroActual = reg.Id == registroIdActual;
					const esPrimero = index === 0;
					const puedeEliminar = !esPrimero && !reg.EnProceso;

					const newRow = document.createElement('tr');
					newRow.className = 'telar-row border-t border-gray-200';
					newRow.id = esPrimero ? 'fila-principal' : '';
					newRow.dataset.registroId = reg.Id;
					newRow.dataset.esExistente = 'true';

					newRow.innerHTML = `
						<td class="p-2 border-r border-gray-200">
							<div class="flex items-center gap-2">
								<input type="text" name="telar-destino[]" value="${reg.NoTelarId}" readonly
									data-registro-id="${reg.Id}"
									class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
								${reg.EnProceso ? '<span class="text-xs text-blue-600"><i class="fas fa-play-circle"></i></span>' : ''}
							</div>
						</td>
						<td class="p-2">
							<input type="text" name="pedido-destino[]" value="${reg.TotalPedido || 0}"
								placeholder="Cantidad..."
								data-registro-id="${reg.Id}"
								class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-green-500"
								oninput="actualizarResumenCantidades()">
						</td>
						<td class="p-2 text-center w-10">
							${esPrimero
								? '<i class="fas fa-lock text-gray-400" title="Telar origen"></i>'
								: (puedeEliminar
									? '<button type="button" class="btn-remove-row text-red-500 hover:text-red-700 transition-colors" title="Eliminar"><i class="fas fa-times"></i></button>'
									: '<i class="fas fa-lock text-gray-400" title="En proceso"></i>')}
						</td>
					`;

				tbody.appendChild(newRow);

					// Evento para eliminar fila (solo si puede eliminar)
					if (puedeEliminar) {
						const btnRemove = newRow.querySelector('.btn-remove-row');
						if (btnRemove) {
							btnRemove.addEventListener('click', function(e) {
								e.preventDefault();
								e.stopPropagation();

								const registroIdEliminar = reg.Id;
								const telarEliminar = reg.NoTelarId;
								const cantidadRegistro = parseFloat(reg.TotalPedido) || 0;
								const filaAEliminar = newRow;

								// Confirmar con SweetAlert2
								Swal.fire({
									title: '¿Eliminar registro?',
									html: `<p>Se eliminará el registro del telar <strong>${telarEliminar}</strong> de la base de datos.</p>
										   <p class="text-sm text-gray-500 mt-2">Esta acción no se puede deshacer.</p>`,
									icon: 'warning',
									showCancelButton: true,
									confirmButtonText: 'Sí, eliminar',
									cancelButtonText: 'Cancelar',
									confirmButtonColor: '#dc2626',
									cancelButtonColor: '#6b7280',
									reverseButtons: true,
									focusCancel: true
								}).then((result) => {
									if (result.isConfirmed) {
										// Mostrar loading
										Swal.fire({
											title: 'Eliminando...',
											text: 'Por favor espere',
											allowOutsideClick: false,
											allowEscapeKey: false,
											showConfirmButton: false,
											didOpen: () => {
												Swal.showLoading();
											}
										});

										// Eliminar de la base de datos
										fetch(`/planeacion/programa-tejido/${registroIdEliminar}`, {
											method: 'DELETE',
											headers: {
												'Content-Type': 'application/json',
												'Accept': 'application/json',
												'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
											}
										})
										.then(response => response.json())
										.then(dataDelete => {
											Swal.close();

										if (dataDelete.success) {
												// Mostrar mensaje de éxito y recargar la página
												Swal.fire({
													icon: 'success',
													title: '¡Eliminado!',
													text: 'El registro ha sido eliminado correctamente',
													timer: 1500,
													showConfirmButton: false
												}).then(() => {
													// Recargar la página
													window.location.reload();
												});
											} else {
												Swal.fire({
													icon: 'error',
													title: 'Error',
													text: dataDelete.message || 'Error al eliminar el registro'
												});
											}
										})
										.catch(error => {
											console.error('Error al eliminar registro:', error);
											Swal.fire({
												icon: 'error',
												title: 'Error',
												text: 'Error al eliminar el registro'
											});
										});
									}
								});
							});
						}
					}

					// Evento para actualizar al cambiar cantidad
					const pedidoInput = newRow.querySelector('input[name="pedido-destino[]"]');
					if (pedidoInput) {
						pedidoInput.addEventListener('input', () => {
							actualizarResumenCantidades();
							recomputeState();
						});
					}
				});

				// Actualizar resumen
				actualizarResumenCantidades();
			}
		} catch (error) {
			console.error('Error al cargar registros de OrdCompartida:', error);
		}
	}

	// Función para actualizar el resumen de cantidades
	function actualizarResumenCantidades() {
		const pedidoInputs = document.querySelectorAll('input[name="pedido-destino[]"]');
		const sumaCantidades = document.getElementById('suma-cantidades');
		const totalDisponible = document.getElementById('total-disponible');
		const diferenciaCantidades = document.getElementById('diferencia-cantidades');

		if (!sumaCantidades || !totalDisponible) return;

		let suma = 0;
		pedidoInputs.forEach(input => {
			const val = parseFloat(input.value) || 0;
			suma += val;
		});

		const total = parseFloat(totalDisponible.textContent) || 0;
		const diferencia = total - suma;

		sumaCantidades.textContent = suma.toLocaleString('es-MX');


	}

	// Hacer la función global para que se pueda llamar desde oninput
	window.actualizarResumenCantidades = actualizarResumenCantidades;

	// Función para agregar fila en modo dividir
	function agregarFilaDividir() {
		const newRow = document.createElement('tr');
		newRow.className = 'telar-row border-t border-gray-200';
		newRow.dataset.esExistente = 'false';
		newRow.dataset.esNuevo = 'true';

		// Obtener telares ya usados en la tabla
		const telaresUsados = new Set();
		document.querySelectorAll('[name="telar-destino[]"]').forEach(input => {
			if (input.value) telaresUsados.add(input.value);
		});

		let telarOptionsHTML = '<option value="">Seleccionar destino...</option>';
		telaresDisponibles.forEach(t => {
			// Excluir telares ya usados
			if (!telaresUsados.has(t)) {
				telarOptionsHTML += '<option value="' + t + '">' + t + '</option>';
			}
		});

		newRow.innerHTML = `
			<td class="p-2 border-r border-gray-200">
				<div class="flex items-center gap-2">
					<select name="telar-destino[]" class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-green-500 telar-destino-select">
						${telarOptionsHTML}
					</select>
				</div>
			</td>
			<td class="p-2">
				<input type="text" name="pedido-destino[]" placeholder="Cantidad para este telar..."
					class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-green-500"
					oninput="actualizarResumenCantidades()">
			</td>
			<td class="p-2 text-center w-10">
				<button type="button" class="btn-remove-row text-red-500 hover:text-red-700 transition-colors" title="Eliminar fila">
					<i class="fas fa-times"></i>
				</button>
			</td>
		`;

		tbody.appendChild(newRow);

		// Eventos
		newRow.querySelector('.btn-remove-row')?.addEventListener('click', () => {
			newRow.remove();
			actualizarResumenCantidades();
			recomputeState();
		});

		const telarSelect = newRow.querySelector('select[name="telar-destino[]"]');
		const pedidoInput = newRow.querySelector('input[name="pedido-destino[]"]');
		if (telarSelect) telarSelect.addEventListener('change', recomputeState);
		if (pedidoInput) {
			pedidoInput.addEventListener('input', () => {
				actualizarResumenCantidades();
				recomputeState();
			});
		}
	}

	function actualizarEstiloSwitch() {
		const modoActual = getModoActual();
		const vincularActivado = checkboxVincular && checkboxVincular.checked;

		// Resetear todos los botones a estado inactivo
		[pillDuplicar, pillDividir].forEach(pill => {
			if (pill) {
				pill.classList.add('bg-white', 'text-gray-700', 'opacity-80', 'shadow-sm');
				pill.classList.remove('bg-blue-500', 'bg-green-500', 'text-white', 'opacity-100', 'shadow-md');
			}
		});

		// Ocultar todas las descripciones
		[descDuplicar, descDividir].forEach(desc => {
			if (desc) desc.classList.add('hidden');
		});

		// Mostrar/ocultar checkbox de vincular según el modo
		if (checkboxVincularContainer) {
			checkboxVincularContainer.style.display = modoActual === 'duplicar' ? 'flex' : 'none';
		}

		if (modoActual === 'duplicar') {
			// Activo: Duplicar (azul)
			if (pillDuplicar) {
				pillDuplicar.classList.add('bg-blue-500', 'text-white', 'opacity-100', 'shadow-md');
				pillDuplicar.classList.remove('bg-white', 'text-gray-700', 'opacity-80', 'shadow-sm');
			}
			if (descDuplicar) descDuplicar.classList.remove('hidden');

			// Botón confirmar: azul si no está vinculando, morado si está vinculando
			if (confirmButton) {
				if (vincularActivado) {
					confirmButton.textContent = 'Vincular';
					confirmButton.classList.remove('bg-blue-600', 'hover:bg-blue-700', 'bg-green-500', 'hover:bg-green-600');
					confirmButton.classList.add('bg-purple-500', 'hover:bg-purple-600');
				} else {
					confirmButton.textContent = 'Duplicar';
					confirmButton.classList.remove('bg-green-500', 'hover:bg-green-600', 'bg-purple-500', 'hover:bg-purple-600');
					confirmButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
				}
			}
		} else if (modoActual === 'dividir') {
			// Activo: Dividir (verde)
			if (pillDividir) {
				pillDividir.classList.add('bg-green-500', 'text-white', 'opacity-100', 'shadow-md');
				pillDividir.classList.remove('bg-white', 'text-gray-700', 'opacity-80', 'shadow-sm');
			}
			if (descDividir) descDividir.classList.remove('hidden');

			// Botón confirmar verde
			if (confirmButton) {
				confirmButton.textContent = 'Dividir';
				confirmButton.classList.remove('bg-blue-600', 'hover:bg-blue-700', 'bg-purple-500', 'hover:bg-purple-600');
				confirmButton.classList.add('bg-green-500', 'hover:bg-green-600');
			}
		}

		// Reconstruir la tabla según el modo
		reconstruirTablaSegunModo(modoActual === 'duplicar');
	}

	// Event listeners para los botones del switch
	if (pillDuplicar) {
		pillDuplicar.addEventListener('click', () => {
			document.getElementById('modo-duplicar').checked = true;
			switchModo.checked = true; // mantener compatibilidad
			actualizarEstiloSwitch();
		});
	}

	if (pillDividir) {
		pillDividir.addEventListener('click', () => {
			document.getElementById('modo-dividir').checked = true;
			switchModo.checked = false; // mantener compatibilidad
			actualizarEstiloSwitch();
		});
	}

	// Event listener para el checkbox de vincular
	if (checkboxVincular) {
		// Asegurar que el checkbox esté desactivado por defecto
		checkboxVincular.checked = false;
		checkboxVincular.addEventListener('change', () => {
			actualizarEstiloSwitch();
		});
	}

	// Estado inicial: actualizar estilos y mostrar/ocultar checkbox según el modo
	actualizarEstiloSwitch();

	// Evaluar estado inicial (por si ya vienen valores prellenados)
	recomputeState();
}

// Valida y captura los datos del modal para enviar al backend
function validarYCapturarDatosDuplicar() {
	const codArticulo = document.getElementById('swal-codArticulo').value;
	const claveModelo = document.getElementById('swal-claveModelo').value;
	const producto = document.getElementById('swal-producto').value;
	const hilo = document.getElementById('swal-hilo').value;
	const pedido = document.getElementById('swal-pedido').value;
	const flog = document.getElementById('swal-flog').value;
	const salon = document.getElementById('swal-salon').value;
	const aplicacion = document.getElementById('swal-aplicacion').value;
	// Modo: duplicar o dividir
	const modo = getModoActual();
	// Verificar si el checkbox de vincular está activo
	const vincular = estaVincularActivado();
	// Datos adicionales del codificado (no mostrados en el modal)
	const descripcion = document.getElementById('swal-descripcion')?.value || '';
	const custname = document.getElementById('swal-custname')?.value || '';
	const inventSizeId = document.getElementById('swal-inventsizeid')?.value || '';

	// OrdCompartida existente (si el registro ya fue dividido antes)
	// IMPORTANTE: Si el checkbox de vincular está activo, siempre debe ser null para crear uno nuevo
	const ordCompartidaExistenteRaw = document.getElementById('ord-compartida-original')?.value || '';
	const ordCompartidaExistente = vincular ? null : (ordCompartidaExistenteRaw || null);
	const registroIdOriginal = document.getElementById('registro-id-original')?.value || '';

	// Capturar múltiples filas de telar/pedido
	// Nota: en modo dividir, el primer telar es un input readonly, no un select
	const telarInputs = document.querySelectorAll('[name="telar-destino[]"]'); // Captura tanto select como input
	const pedidoInputs = document.querySelectorAll('input[name="pedido-destino[]"]');
	const filas = document.querySelectorAll('#telar-pedido-body tr');
	const destinos = [];

	telarInputs.forEach((input, idx) => {
		const telarVal = input.value.trim();
		const pedidoVal = pedidoInputs[idx]?.value.trim() || '';
		const registroId = input.dataset?.registroId || pedidoInputs[idx]?.dataset?.registroId || '';
		const fila = filas[idx];
		const esExistente = fila?.dataset?.esExistente === 'true';
		const esNuevo = fila?.dataset?.esNuevo === 'true';

		if (telarVal || pedidoVal) {
			destinos.push({
				telar: telarVal,
				pedido: pedidoVal,
				registro_id: registroId,
				es_existente: esExistente,
				es_nuevo: esNuevo
			});
		}
	});

	return {
		codArticulo, claveModelo, producto, hilo, pedido, flog, salon, aplicacion,
		modo, vincular, descripcion, custname, inventSizeId, destinos,
		ord_compartida_existente: ordCompartidaExistente,
		registro_id_original: registroIdOriginal
	};
}

