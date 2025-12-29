{{-- Modal Duplicar Registro - Componente separado --}}
{{-- NOTA: Este archivo se incluye dentro de un bloque <script>, NO agregar etiquetas <script> aquí --}}

{{-- Incluir módulos compartidos y específicos --}}
@include('modulos.programa-tejido.modal._shared-helpers')
@include('modulos.programa-tejido.modal._duplicar-vincular')
@include('modulos.programa-tejido.modal._dividir')

// ===== Función principal para duplicar y dividir telar =====
async function duplicarTelar(row) {
	const telar = getRowTelar(row);
	const salon = getRowSalon(row);

	if (!telar || !salon) {
		showToast('No se pudo obtener la información del telar', 'error');
		return;
	}

	// Obtener datos del registro seleccionado para prellenar
	const codArticulo = getRowCellText(row, 'ItemId');
	// Clave Modelo se toma de la columna TamanoClave (si existe) o cae a Cod. Artículo
	const claveModelo = getRowCellText(row, 'TamanoClave', codArticulo);
	const producto = getRowCellText(row, 'NombreProducto');
	const hilo = getRowCellText(row, 'FibraRizo');
	const pedido = getRowCellText(row, 'TotalPedido');
	const flog = getRowCellText(row, 'FlogsId');
	const saldo = getRowCellText(row, 'SaldoPedido', pedido);
	const aplicacion = getRowCellText(row, 'AplicacionId');
	const descripcion = getRowCellText(row, 'NombreProyecto');

	// Verificar si el registro ya tiene OrdCompartida (ya fue dividido antes)
	const ordCompartidaCell = getRowCellText(row, 'OrdCompartida');
	const ordCompartidaAttr = row.getAttribute('data-ord-compartida') || row.dataset?.ordCompartida || '';
	let ordCompartida = (ordCompartidaCell || ordCompartidaAttr || '').toString().trim();
	const registroId = row.getAttribute('data-id');

	// Fallback: si no se obtuvo del DOM, intentar obtener del backend
	let aplicacionBackend = '';
	let pedidoBackend = '';
	let saldoBackend = '';
	let produccionBackend = '';
	if (registroId) {
		try {
			const resp = await fetch(`/planeacion/programa-tejido/${registroId}/detalles-balanceo`, {
				headers: { 'Accept': 'application/json' }
			});
			if (resp.ok) {
				const data = await resp.json();
				if (data?.registro?.OrdCompartida !== undefined && data.registro.OrdCompartida !== null) {
					ordCompartida = String(data.registro.OrdCompartida).trim();
				}
				if (data?.registro?.AplicacionId !== undefined && data.registro.AplicacionId !== null) {
					aplicacionBackend = String(data.registro.AplicacionId).trim();
				}
				if (data?.registro?.TotalPedido !== undefined && data.registro.TotalPedido !== null) {
					pedidoBackend = String(data.registro.TotalPedido).trim();
				}
				if (data?.registro?.SaldoPedido !== undefined && data.registro.SaldoPedido !== null) {
					saldoBackend = String(data.registro.SaldoPedido).trim();
				}
				if (data?.registro?.Produccion !== undefined && data.registro.Produccion !== null) {
					produccionBackend = String(data.registro.Produccion).trim();
				}
			}
		} catch (err) {
		}
	}

	// Usar aplicación del backend si no se obtuvo del DOM
	const aplicacionFinal = aplicacion || aplicacionBackend;
	const pedidoFinal = pedidoBackend || pedido;
	const saldoFinal = saldoBackend || saldo;
	const produccionFinal = produccionBackend || '';

	// Resetear variables globales
	registrosOrdCompartidaExistentes = [];
	const ordNum = Number(ordCompartida);
	ordCompartidaActual = Number.isFinite(ordNum) ? ordNum : null;

	// Modal con formato de tabla
	const resultado = await Swal.fire({
		html: generarHTMLModalDuplicar({ telar, salon, codArticulo, claveModelo, producto, hilo, pedido: pedidoFinal, saldo: saldoFinal, produccion: produccionFinal, flog, ordCompartida, aplicacion: aplicacionFinal, registroId, descripcion }),
		width: '980px',
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
		const csrfToken = getCsrfToken();
		const response = await fetch(endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': csrfToken
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
			const mensajeError = buildCalendarErrorHtml(data);

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
				const mensajeAdvertencia = buildCalendarWarningHtml(data.message, data.advertencias);

				Swal.fire({
					title: 'Duplicacion completada con advertencias',
					html: mensajeAdvertencia,
					icon: 'warning',
					confirmButtonText: 'Entendido',
					confirmButtonColor: '#f59e0b',
					width: '600px'
				}).then(() => {
					redirectToRegistro(data);
				});
			} else {
				// Sin advertencias, mostrar mensaje de éxito normal
				showToast(data.message || mensajeExito, 'success');

				// Redirigir inmediatamente al registro creado
				redirectToRegistro(data);
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
function generarHTMLModalDuplicar({ telar, salon, codArticulo, claveModelo, producto, hilo, pedido, saldo, produccion, flog, ordCompartida, aplicacion, registroId, descripcion = '' }) {
	// Determinar si ya está dividido (tiene OrdCompartida)
	const ordNum = Number(ordCompartida);
	const yaDividido = Number.isFinite(ordNum) && ordNum !== 0;

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
									<label class="block mb-1 text-sm font-medium text-gray-700">Pedido Total</label>
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
							<div class="grid grid-cols-3 gap-4">
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
								<div>
									<label class="block mb-1 text-sm font-medium text-gray-700">Descripción</label>
									<input type="text" id="swal-descripcion" value="${descripcion || ''}"
										placeholder="Descripción del proyecto..."
										class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
								</div>
							</div>
						</td>
					</tr>
				</tbody>
			</table>

			<!-- Campos ocultos para datos adicionales del codificado -->
			<input type="hidden" id="swal-custname" value="">
			<input type="hidden" id="swal-inventsizeid" value="">
			<input type="hidden" id="swal-aplicacion-original" value="${aplicacion}">

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
			<input type="hidden" id="produccion-original" value="${produccion || ''}">
			<input type="hidden" id="ord-compartida-original" value="${ordCompartida}">
			<input type="hidden" id="registro-id-original" value="${registroId}">

			<!-- Tabla de salones, telares y cantidades -->
			<div class="border border-gray-300 rounded-lg overflow-hidden">
				<table class="w-full border-collapse">
					<thead class="bg-gray-100">
						<tr>
							<th id="th-salon" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300 hidden">Salón</th>
							<th id="th-telar" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300">Telar</th>
							<th id="th-pedido-tempo" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300">Pedido</th>
							<th id="th-porcentaje-segundos" class="py-2 px-3 text-xs font-medium text-gray-700 text-left border-b border-r border-gray-300 hidden">% Segundas</th>
							<th id="th-produccion" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300">Produccion</th>
							<th id="th-saldo-total" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300 hidden">Saldo Total</th>
							<th id="th-saldo" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300 hidden">Saldos</th>
							<th id="th-obs" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300">Obs</th>
							<th class="py-2 px-2 text-center border-b border-gray-300 w-10">
								<button type="button" id="btn-add-telar-row" class="text-green-600 hover:text-green-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Añadir fila">
									<i class="fas fa-plus-circle text-lg"></i>
								</button>
							</th>
						</tr>
					</thead>
					<tbody id="telar-pedido-body">
						<tr class="telar-row" id="fila-principal">
							<td class="p-2 border-r border-gray-200 salon-cell hidden">
								<input type="hidden" name="salon-destino[]" value="${salon}">
							</td>
							<td class="p-2 border-r border-gray-200">
								<select name="telar-destino[]" data-telar-actual="${telar}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 telar-destino-select">
									${telar ? `<option value="${telar}" selected>${telar}</option>` : '<option value="">Seleccionar...</option>'}
								</select>
							</td>
							<td class="p-2 border-r border-gray-200 pedido-tempo-cell">
								<input type="number" name="pedido-tempo-destino[]" value="${pedido}" step="0.01" min="0"
									class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
							</td>
						<td class="p-2 border-r border-gray-200 porcentaje-segundos-cell">
							<input type="number" name="porcentaje-segundos-destino[]" value="0" step="0.01" min="0"
								placeholder="0.00"
								class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
						</td>
						<td class="p-2 border-r border-gray-200 produccion-cell hidden">
								<input type="hidden" name="pedido-destino[]" value="${pedido}">
						</td>
							<td class="p-2 border-r border-gray-200 saldo-total-cell hidden">
								<input type="text" value="${saldo || ''}" readonly
									class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
							</td>
							<td class="p-2 border-r border-gray-200 saldo-cell">
								<input type="number" name="saldo-destino[]" value="${pedido || ''}" step="0.01" min="0"
									class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
							</td>
						<td class="p-2 border-r border-gray-200">
							<input type="text" name="observaciones-destino[]" value=""
								placeholder="Observaciones..."
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
	// Limpiar valores originales guardados al inicializar el modal
	if (window.valoresOriginalesFilas) {
		window.valoresOriginalesFilas.clear();
	}

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
	let salonActualLocal = salonActual;

	// Hacer telaresDisponibles y salonesDisponibles globales para que estén disponibles en otras funciones
	window.telaresDisponibles = telaresDisponibles;
	window.salonesDisponibles = salonesDisponibles;

	// Referencias a campos para datos adicionales
	const inputDescripcion = document.getElementById('swal-descripcion');
	const inputCustname = document.getElementById('swal-custname');
	const inputInventSizeId = document.getElementById('swal-inventsizeid');
	const aplicacionOriginal = document.getElementById('swal-aplicacion-original')?.value || '';

	// Inputs/Selects de la primera fila Telar/Pedido
	const firstTelarSelect = tbody.querySelector('select[name="telar-destino[]"]');
	const firstPedidoInput = tbody.querySelector('input[name="pedido-destino[]"]');

	// Función helper para obtener inputs de una fila
	// Función para agregar event listeners de cálculo automático a una fila
	function agregarListenersCalculoAutomatico(row) {
		if (!row) {
			return;
		}

		// Remover listeners anteriores si existen (usando un atributo de datos para almacenar referencias)
		if (row.dataset.listenersConfigured === 'true') {
			// Limpiar listeners anteriores removiendo los event listeners si es posible
			// Nota: No podemos remover listeners anónimos fácilmente, pero podemos marcarlos para evitar duplicados
		}
		row.dataset.listenersConfigured = 'true';

		const { pedidoTempoInput, porcentajeSegundosInput, totalInput } = getRowInputs(row);
		const saldoInput = row.querySelector('input[name="saldo-destino[]"]');

		if (pedidoTempoInput) {
			pedidoTempoInput.addEventListener('input', (event) => {
				// No ejecutar si se est? redistribuyendo
				if (window.redistribuyendo) {
					return;
				}

				const modoActual = getModoActual();
				if (modoActual === 'duplicar') {
					// En modo duplicar, calcular saldo basado en pedido y %segundas
					calcularSaldoDuplicar(row);
					return;
				}
				// En modo dividir, el listener global se encarga del c?lculo
				if (!window.modalDuplicarListenersGlobalesAgregados) {
					const calcularFn = window.calcularSaldoTotal || (typeof calcularSaldoTotal !== 'undefined' ? calcularSaldoTotal : null);
					if (calcularFn && typeof calcularFn === 'function') {
						try {
							calcularFn(row);
						} catch (error) {
						}
					}
				}
			}, { capture: true }); // Usar capture para ejecutar antes que otros listeners
		}

		if (porcentajeSegundosInput) {
			porcentajeSegundosInput.addEventListener('input', (event) => {
				// No ejecutar si se est? redistribuyendo
				if (window.redistribuyendo) {
					return;
				}

				const modoActual = getModoActual();
				if (modoActual === 'duplicar') {
					// En modo duplicar, calcular saldo basado en pedido y %segundas
					calcularSaldoDuplicar(row);
					return;
				}
				// Calcular autom?ticamente cuando cambia el porcentaje de segundas
				// Intentar desde window primero, luego desde scope global
				if (modoActual === 'dividir' && !window.modalDuplicarListenersGlobalesAgregados) {
					const calcularFn = window.calcularSaldoTotal || (typeof calcularSaldoTotal !== 'undefined' ? calcularSaldoTotal : null);
					if (calcularFn && typeof calcularFn === 'function') {
						try {
							calcularFn(row);
						} catch (error) {
						}
					}
				}
			}, { capture: true }); // Usar capture para ejecutar antes que otros listeners
		}

		if (totalInput) {
			totalInput.addEventListener('input', (event) => {
				if (window.redistribuyendo || (event && event.isTrusted === false)) {
					return;
				}
				const modoActual = getModoActual();
				if (modoActual === 'dividir') {
					// En modo dividir, sincronizar pedido tempo cuando cambia el total
					if (typeof sincronizarPedidoTempoYTotal === 'function') {
						sincronizarPedidoTempoYTotal(row, true);
					}
				}
				// En modo duplicar, no hacer nada especial
			});
		}
	}

	// Función para calcular el estado y habilitar/deshabilitar botones
	function recomputeState() {
		const modoActual = getModoActual();
		const esDuplicar = modoActual === 'duplicar';
		const telarInputs = document.querySelectorAll('[name="telar-destino[]"]');
		const salonInputs = document.querySelectorAll('[name="salon-destino[]"]');
		const pedidoInputs = document.querySelectorAll('input[name="pedido-destino[]"]');

		let firstComplete = false;
		let hasAnyFilled = false;
		let allDestinationsValid = true;

		telarInputs.forEach((input, idx) => {
			const telarVal = input.value.trim();
			const pedidoVal = (pedidoInputs[idx]?.value || '').trim();

			if (esDuplicar) {
				if (idx === 0 && telarVal !== '' && pedidoVal !== '') {
					firstComplete = true;
				}
				if (telarVal !== '' || pedidoVal !== '') {
					hasAnyFilled = true;
				}
			} else {
				if (idx === 0) {
					if (telarVal !== '' && pedidoVal !== '') {
						firstComplete = true;
					}
					hasAnyFilled = telarVal !== '';
				} else {
					const salonVal = (salonInputs[idx]?.value || '').trim();
					if (telarVal === '' || pedidoVal === '' || salonVal === '') {
						allDestinationsValid = false;
					}
					if (telarVal !== '' || pedidoVal !== '') {
						hasAnyFilled = true;
					}
				}
			}
		});

		btnAdd.disabled = esDuplicar ? !firstComplete : false;

		if (esDuplicar) {
			confirmButton.disabled = !hasAnyFilled;
		} else {
			const tieneDestinos = telarInputs.length > 1;
			const origenTieneCantidad = pedidoInputs[0]?.value?.trim() !== '';
			confirmButton.disabled = !tieneDestinos || !allDestinationsValid || !origenTieneCantidad;
		}
	}

	// Función para aplicar visibilidad de columnas según el modo
	function aplicarVisibilidadColumnas(esDuplicar) {
		const thSalon = document.getElementById('th-salon');
		const thPedidoTempo = document.getElementById('th-pedido-tempo');
		const thPorcentajeSegundos = document.getElementById('th-porcentaje-segundos');
		const thProduccion = document.getElementById('th-produccion');
		const thSaldoTotal = document.getElementById('th-saldo-total');
		const thSaldo = document.getElementById('th-saldo');

		if (esDuplicar) {
			// Modo duplicar: telar, pedido, %segundas, saldos
			if (thSalon) thSalon.classList.add('hidden');
			if (thPedidoTempo) {
				thPedidoTempo.classList.remove('hidden');
				thPedidoTempo.textContent = 'Pedido';
			}
			if (thPorcentajeSegundos) thPorcentajeSegundos.classList.remove('hidden');
			if (thProduccion) thProduccion.classList.add('hidden');
			if (thSaldoTotal) thSaldoTotal.classList.add('hidden');
			if (thSaldo) {
				thSaldo.classList.remove('hidden');
				thSaldo.textContent = 'Saldos';
			}

			document.querySelectorAll('.salon-cell').forEach((cell) => cell.classList.add('hidden'));
			document.querySelectorAll('.pedido-tempo-cell').forEach((cell) => cell.classList.remove('hidden'));
			document.querySelectorAll('.porcentaje-segundos-cell').forEach((cell) => cell.classList.remove('hidden'));
			document.querySelectorAll('.produccion-cell').forEach((cell) => cell.classList.add('hidden'));
			document.querySelectorAll('.saldo-total-cell').forEach((cell) => cell.classList.add('hidden'));
			document.querySelectorAll('.saldo-cell').forEach((cell) => cell.classList.remove('hidden'));
		} else {
			// Modo dividir: salon, telar, pedido, produccion, saldo total (sin %segundas, sin saldos)
			if (thSalon) thSalon.classList.remove('hidden');
			if (thPedidoTempo) {
				thPedidoTempo.classList.remove('hidden');
				thPedidoTempo.textContent = 'Pedido';
			}
			if (thPorcentajeSegundos) thPorcentajeSegundos.classList.add('hidden');
			if (thProduccion) {
				thProduccion.classList.remove('hidden');
				thProduccion.textContent = 'Produccion';
			}
			if (thSaldoTotal) {
				thSaldoTotal.classList.remove('hidden');
				thSaldoTotal.textContent = 'Saldo Total';
			}
			if (thSaldo) {
				thSaldo.classList.add('hidden');
			}

			document.querySelectorAll('.salon-cell').forEach((cell) => cell.classList.remove('hidden'));
			document.querySelectorAll('.pedido-tempo-cell').forEach((cell) => cell.classList.remove('hidden'));
			document.querySelectorAll('.porcentaje-segundos-cell').forEach((cell) => cell.classList.add('hidden'));
			document.querySelectorAll('.produccion-cell').forEach((cell) => cell.classList.remove('hidden'));
			document.querySelectorAll('.saldo-total-cell').forEach((cell) => cell.classList.remove('hidden'));
			document.querySelectorAll('.saldo-cell').forEach((cell) => cell.classList.add('hidden'));

			// Deshabilitar inputs de pedido SOLO en filas existentes (no en las nuevas)
			document.querySelectorAll('tr.telar-row[data-es-existente="true"] input[name="pedido-tempo-destino[]"]').forEach((input) => {
				if (!input.readOnly) {
					input.readOnly = true;
					input.classList.add('bg-gray-100', 'text-gray-700', 'cursor-not-allowed');
					input.classList.remove('focus:outline-none', 'focus:ring-1', 'focus:ring-green-500', 'focus:ring-blue-500');
				}
			});
		}
	}

	// Función para calcular saldo en modo duplicar/vincular
	function calcularSaldoDuplicar(row) {
		if (!row) return;

		const modoActual = getModoActual();
		if (modoActual !== 'duplicar') return;

		const { pedidoTempoInput, porcentajeSegundosInput } = getRowInputs(row);
		const saldoInput = row.querySelector('input[name="saldo-destino[]"]');
		const pedidoHiddenInput = row.querySelector('input[name="pedido-destino[]"]');

		if (!pedidoTempoInput || !saldoInput) return;

		const pedido = parseFloat(pedidoTempoInput.value) || 0;
		const porcentajeSegundos = parseFloat(porcentajeSegundosInput?.value || 0) || 0;

		// El pedido NO se modifica, solo se usa como base para calcular el saldo
		// Actualizar el campo hidden con el valor del pedido (sin % de segundas)
		if (pedidoHiddenInput) {
			pedidoHiddenInput.value = pedido.toFixed(2);
		}

		// Calcular saldo: Pedido * (1 + PorcentajeSegundos / 100)
		// Solo el saldo se ve afectado por el % de segundas
		const saldo = pedido * (1 + porcentajeSegundos / 100);

		saldoInput.value = saldo.toFixed(2);
		saldoInput.dispatchEvent(new Event('input', { bubbles: true }));
	}

	window.recomputeState = recomputeState;
	window.agregarListenersCalculoAutomatico = agregarListenersCalculoAutomatico;
	window.aplicarVisibilidadColumnas = aplicarVisibilidadColumnas;
	window.calcularSaldoDuplicar = calcularSaldoDuplicar;

	// Función para reconstruir la tabla según el modo
	async function reconstruirTablaSegunModo(esDuplicar) {
		const filasAdicionales = tbody.querySelectorAll('tr:not(#fila-principal)');
		filasAdicionales.forEach(fila => fila.remove());

		const filaPrincipal = document.getElementById('fila-principal');
		if (!filaPrincipal) return;

		const resumenCantidades = document.getElementById('resumen-cantidades');
		if (resumenCantidades) {
			resumenCantidades.classList.toggle('hidden', esDuplicar);
		}
		const thSalon = document.getElementById('th-salon');
		if (thSalon) {
			thSalon.classList.toggle('hidden', esDuplicar);
		}
		const thSaldoTotal = document.getElementById('th-saldo-total');
		if (thSaldoTotal) {
			thSaldoTotal.classList.remove('hidden');
		}

		const telarOriginal = document.getElementById('telar-original')?.value || telarActual;
		const pedidoOriginal = document.getElementById('pedido-original')?.value || '';
		const saldoOriginal = document.getElementById('saldo-original')?.value || '';
		const produccionOriginal = document.getElementById('produccion-original')?.value || '';

		const thTelar = document.getElementById('th-telar');
		const thPedidoTempo = document.getElementById('th-pedido-tempo');

		if (esDuplicar) {
			if (thTelar) thTelar.textContent = 'Telar';
			if (thPedidoTempo) thPedidoTempo.textContent = 'Pedido';

			filaPrincipal.innerHTML = `
				<td class="p-2 border-r border-gray-200 salon-cell hidden">
					<input type="hidden" name="salon-destino[]" value="${selectSalon?.value || salonActualLocal || ''}">
				</td>
				<td class="p-2 border-r border-gray-200">
					<select name="telar-destino[]" data-telar-actual="${telarOriginal}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 telar-destino-select">
						${telarOriginal ? `<option value="${telarOriginal}" selected>${telarOriginal}</option>` : '<option value="">Seleccionar...</option>'}
					</select>
				</td>
				<td class="p-2 border-r border-gray-200 pedido-tempo-cell">
					<input type="number" name="pedido-tempo-destino[]" value="${pedidoOriginal}" step="0.01" min="0"
						class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
				</td>
				<td class="p-2 border-r border-gray-200 porcentaje-segundos-cell">
					<input type="number" name="porcentaje-segundos-destino[]" value="0" step="0.01" min="0"
						placeholder="0.00"
						class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
				</td>
				<td class="p-2 border-r border-gray-200 produccion-cell hidden">
					<input type="hidden" name="pedido-destino[]" value="${pedidoOriginal}">
				</td>
				<td class="p-2 border-r border-gray-200 saldo-total-cell hidden">
					<input type="text" value="${saldoOriginal}" readonly
						class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
				</td>
				<td class="p-2 border-r border-gray-200 saldo-cell">
					<input type="number" name="saldo-destino[]" value="${pedidoOriginal || ''}" step="0.01" min="0"
						class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
				</td>
				<td class="p-2 border-r border-gray-200">
					<input type="text" name="observaciones-destino[]" value=""
						placeholder="Observaciones..."
						class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
				</td>
				<td class="p-2 text-center w-10"></td>
			`;

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

			const telarSelect = filaPrincipal.querySelector('select[name="telar-destino[]"]');
			const pedidoInput = filaPrincipal.querySelector('input[name="pedido-destino[]"]');
			if (telarSelect) telarSelect.addEventListener('change', recomputeState);
			if (pedidoInput) pedidoInput.addEventListener('input', recomputeState);

			agregarListenersCalculoAutomatico(filaPrincipal);

			// Calcular saldo inicial en modo duplicar
			if (typeof calcularSaldoDuplicar === 'function') {
				calcularSaldoDuplicar(filaPrincipal);
			}

		} else {
			if (thTelar) thTelar.textContent = 'Telar';
			if (thPedidoTempo) thPedidoTempo.textContent = 'Pedido';

			if (tieneOrdCompartida) {
				if (typeof cargarRegistrosOrdCompartida === 'function') {
					await cargarRegistrosOrdCompartida(ordCompartidaActualLocal);
				}
			} else {
				filaPrincipal.innerHTML = `
					<td class="p-2 border-r border-gray-200 salon-cell">
						<input type="text" name="salon-destino[]" value="${selectSalon?.value || salonActualLocal || ''}" readonly
							class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
					</td>
					<td class="p-2 border-r border-gray-200">
						<div class="flex items-center gap-2">
							<input type="text" name="telar-destino[]" value="${telarOriginal}" readonly
								data-registro-id=""
								class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
						</div>
					</td>
					<td class="p-2 border-r border-gray-200 pedido-tempo-cell">
						<input type="number" name="pedido-tempo-destino[]" value="${pedidoOriginal}" data-pedido-total="true" step="0.01" min="0" readonly
							class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
					</td>
					<td class="p-2 border-r border-gray-200 porcentaje-segundos-cell hidden">
						<input type="hidden" name="porcentaje-segundos-destino[]" value="0">
					</td>
					<td class="p-2 border-r border-gray-200 produccion-cell">
						<input type="text" value="${produccionOriginal || ''}" readonly
							class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
						<input type="hidden" name="pedido-destino[]" value="${pedidoOriginal}">
					</td>
					<td class="p-2 border-r border-gray-200 saldo-total-cell">
						<input type="text" value="${saldoOriginal}" readonly
							class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
					</td>
					<td class="p-2 border-r border-gray-200">
						<input type="text" name="observaciones-destino[]" value=""
							placeholder="Observaciones..."
							class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-green-500">
					</td>
					<td class="p-2 text-center w-10">
						<i class="fas fa-lock text-gray-400" title="Telar origen"></i>
					</td>
				`;

				if (typeof agregarFilaDividir === 'function') {
					agregarFilaDividir();
				}

				agregarListenersCalculoAutomatico(filaPrincipal);

				// Calcular automáticamente los totales
				setTimeout(() => {
					if (typeof calcularSaldoTotal === 'function') {
						calcularSaldoTotal(filaPrincipal);
					}
				}, 100);
			}

			const pedidoInput = filaPrincipal.querySelector('input[name="pedido-destino[]"]:not([readonly])');
			if (pedidoInput) pedidoInput.addEventListener('input', recomputeState);
		}

		aplicarVisibilidadColumnas(esDuplicar);
				recomputeState();
	}

	// Función para actualizar el estilo del switch y reconstruir la tabla
	function actualizarEstiloSwitch() {
		const modoActual = getModoActual();
		const checkboxVincular = document.getElementById('checkbox-vincular');
		const vincularActivado = checkboxVincular && checkboxVincular.checked;
		const pillDuplicar = document.getElementById('pill-duplicar');
		const pillDividir = document.getElementById('pill-dividir');
		const descDuplicar = document.getElementById('desc-duplicar');
		const descDividir = document.getElementById('desc-dividir');
		const checkboxVincularContainer = document.getElementById('checkbox-vincular-container');

		[pillDuplicar, pillDividir].forEach(pill => {
			if (pill) {
				pill.classList.add('bg-white', 'text-gray-700', 'opacity-80', 'shadow-sm');
				pill.classList.remove('bg-blue-500', 'bg-green-500', 'text-white', 'opacity-100', 'shadow-md');
			}
		});

		[descDuplicar, descDividir].forEach(desc => {
			if (desc) desc.classList.add('hidden');
		});

		if (checkboxVincularContainer) {
			checkboxVincularContainer.style.display = modoActual === 'duplicar' ? 'flex' : 'none';
		}

		if (modoActual === 'duplicar') {
			if (pillDuplicar) {
				pillDuplicar.classList.add('bg-blue-500', 'text-white', 'opacity-100', 'shadow-md');
				pillDuplicar.classList.remove('bg-white', 'text-gray-700', 'opacity-80', 'shadow-sm');
			}
			if (descDuplicar) descDuplicar.classList.remove('hidden');

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
			if (pillDividir) {
				pillDividir.classList.add('bg-green-500', 'text-white', 'opacity-100', 'shadow-md');
				pillDividir.classList.remove('bg-white', 'text-gray-700', 'opacity-80', 'shadow-sm');
			}
			if (descDividir) descDividir.classList.remove('hidden');

			if (confirmButton) {
				confirmButton.textContent = 'Dividir';
				confirmButton.classList.remove('bg-blue-600', 'hover:bg-blue-700', 'bg-purple-500', 'hover:bg-purple-600');
				confirmButton.classList.add('bg-green-500', 'hover:bg-green-600');
			}
		}

		reconstruirTablaSegunModo(modoActual === 'duplicar');
	}

	// Autocompletado de Clave Modelo
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
			headers: { 'Accept': 'application/json' }
		})
			.then(r => r.json())
			.then(data => {
				if (data.datos) {
					inputCodArticulo.value = data.datos.ItemId || '';
					inputProducto.value = data.datos.Nombre || data.datos.NombreProducto || '';

					if (inputFlog && data.datos.FlogsId) {
						inputFlog.value = data.datos.FlogsId;
					}

					if (inputDescripcion) inputDescripcion.value = data.datos.NombreProyecto || '';
					if (inputCustname) inputCustname.value = data.datos.CustName || '';
					if (inputInventSizeId) inputInventSizeId.value = data.datos.InventSizeId || '';

					inputCodArticulo.dispatchEvent(new Event('input', { bubbles: true }));
					inputProducto.dispatchEvent(new Event('input', { bubbles: true }));
				}
			})
			.catch(() => {});
	}

	// Autocompletado de Flog
	async function cargarOpcionesFlog(search = '') {
		try {
			if (!search && sugerenciasFlog && sugerenciasFlog.length > 0) {
				mostrarSugerenciasFlog(sugerenciasFlog);
				return;
			}

			const response = await fetch('/programa-tejido/flogs-id-from-twflogs', {
				headers: {
					'Accept': 'application/json',
					'X-CSRF-TOKEN': getCsrfToken()
				}
			});

			const opciones = await response.json();
			const opcionesArray = Array.isArray(opciones) ? opciones : [];

			if (!search) {
				todasOpcionesFlog = opcionesArray;
			}

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
					cargarDescripcionPorFlog(sugerencia);
				}
			});
			containerSugerenciasFlog.appendChild(div);
		});

		containerSugerenciasFlog.classList.remove('hidden');
	}

	async function cargarDescripcionPorFlog(flog) {
		if (!flog || flog.trim() === '') return;

		try {
			const response = await fetch(`/programa-tejido/descripcion-by-idflog/${encodeURIComponent(flog)}`, {
				headers: {
					'Accept': 'application/json',
					'X-CSRF-TOKEN': getCsrfToken()
				}
			});

			const data = await response.json();
			if (inputDescripcion && data.nombreProyecto) {
				inputDescripcion.value = data.nombreProyecto;
				inputDescripcion.dispatchEvent(new Event('input', { bubbles: true }));
			}
		} catch (error) {
		}
	}

	// Validación de clave modelo
	const alertaClaveModelo = document.getElementById('alerta-clave-modelo');
	const alertaClaveModeloTexto = document.getElementById('alerta-clave-modelo-texto');

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
					inputClaveModelo.value = '';
					inputCodArticulo.value = '';
					inputProducto.value = '';
				} else {
					ocultarAlertaClaveModelo();
					cargarDatosRelacionados(claveModelo);
				}
			})
			.catch(() => {
			});
	}

	// Event listeners
	btnAdd.disabled = true;
	confirmButton.disabled = true;

	if (firstTelarSelect) {
		firstTelarSelect.addEventListener('change', recomputeState);
	}
	if (firstPedidoInput) {
		firstPedidoInput.addEventListener('input', recomputeState);
	}

	const filaPrincipalInicial = document.getElementById('fila-principal');
	if (filaPrincipalInicial) {
		agregarListenersCalculoAutomatico(filaPrincipalInicial);
		// Calcular saldo inicial si está en modo duplicar
		setTimeout(() => {
			if (typeof calcularSaldoDuplicar === 'function' && getModoActual() === 'duplicar') {
				calcularSaldoDuplicar(filaPrincipalInicial);
			}
		}, 100);
	}

	// Cargar datos en paralelo
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

	Promise.all([fetchSalones, fetchHilos, fetchTelares, fetchAplicaciones]).then(([dataSalones, dataHilos, dataTelares, dataAplicaciones]) => {
		// Procesar salones
		let opciones = [];
		if (Array.isArray(dataSalones)) {
			opciones = dataSalones;
		} else if (dataSalones?.data && Array.isArray(dataSalones.data)) {
			opciones = dataSalones.data;
		} else if (dataSalones && typeof dataSalones === 'object') {
			opciones = Object.values(dataSalones).filter(v => typeof v === 'string');
		}

		if (opciones.length > 0) {
			salonesDisponibles = opciones;
			window.salonesDisponibles = opciones; // Hacer global
			const valorActualSalon = selectSalon.value;
			selectSalon.innerHTML = '<option value="">Seleccionar...</option>';
			opciones.forEach(item => {
				const option = document.createElement('option');
				option.value = item;
				option.textContent = item;
				if (item === valorActualSalon || item === salonActual) option.selected = true;
				selectSalon.appendChild(option);
			});
			salonActualLocal = selectSalon.value || salonActualLocal;
		}

		// Procesar hilos
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

		// Procesar telares
		telaresDisponibles = Array.isArray(dataTelares) ? dataTelares : [];
		window.telaresDisponibles = telaresDisponibles; // Actualizar global
		if (typeof actualizarSelectsTelares === 'function') {
			actualizarSelectsTelares(true);
		}

		// Procesar aplicaciones
		if (dataAplicaciones && (Array.isArray(dataAplicaciones) ? dataAplicaciones.length > 0 : true)) {
			const aplicacionesArray = Array.isArray(dataAplicaciones) ? dataAplicaciones : [];
			selectAplicacion.innerHTML = '<option value="">Seleccionar...</option>';
			aplicacionesArray.forEach(item => {
				const option = document.createElement('option');
				option.value = item;
				option.textContent = item;
				if (item === aplicacionOriginal) {
					option.selected = true;
				}
				selectAplicacion.appendChild(option);
			});
			if (!aplicacionesArray.includes('NA')) {
				const optionNA = document.createElement('option');
				optionNA.value = 'NA';
				optionNA.textContent = 'NA';
				if (!aplicacionOriginal && !selectAplicacion.value) {
					optionNA.selected = true;
				}
				selectAplicacion.appendChild(optionNA);
			}
			if (aplicacionOriginal && !selectAplicacion.value) {
				const optOriginal = Array.from(selectAplicacion.options).find(o => o.value === aplicacionOriginal);
				if (optOriginal) {
					optOriginal.selected = true;
				}
			}
			if (!selectAplicacion.value) {
				const optNa = Array.from(selectAplicacion.options).find(o => o.value === 'NA');
				if (optNa) optNa.selected = true;
			}
		}

		// Estado inicial - Determinar modo basado en si tiene registros divididos
		const modoDuplicar = document.getElementById('modo-duplicar');
		const modoDividir = document.getElementById('modo-dividir');
		const switchModo = document.getElementById('switch-modo');

		// Si tiene registros divididos, abrir en modo dividir automáticamente
		if (tieneOrdCompartida) {
			if (modoDividir) modoDividir.checked = true;
			if (modoDuplicar) modoDuplicar.checked = false;
			if (switchModo) switchModo.checked = false;
		} else {
		if (modoDuplicar) modoDuplicar.checked = true;
			if (modoDividir) modoDividir.checked = false;
		if (switchModo) switchModo.checked = true;
		}

		actualizarEstiloSwitch();
		recomputeState();
	});

	// Event listeners para autocompletado de Clave Modelo
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

		inputClaveModelo.addEventListener('blur', () => {
			setTimeout(() => containerSugerencias.classList.add('hidden'), 200);
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

	// Event listeners para autocompletado de Flog
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

		inputFlog.addEventListener('blur', () => {
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

	// Event listener para cambio de salón
	selectSalon.addEventListener('change', () => {
		salonActualLocal = selectSalon.value;
		if (typeof cargarTelaresPorSalon === 'function') {
		cargarTelaresPorSalon(selectSalon.value, false);
		}
		const claveModeloActual = inputClaveModelo?.value?.trim();
		if (claveModeloActual) {
			validarClaveModeloEnSalon(selectSalon.value, claveModeloActual);
		} else {
			ocultarAlertaClaveModelo();
		}
	});

	// Event listener para añadir filas
	btnAdd.addEventListener('click', () => {
		const modoActual = getModoActual();
		const esDuplicar = modoActual === 'duplicar';

		if (!esDuplicar) {
			if (typeof agregarFilaDividir === 'function') {
			agregarFilaDividir();
			}
			recomputeState();
			return;
		}

		if (typeof agregarFilaDuplicar === 'function') {
			agregarFilaDuplicar();
		}
		recomputeState();
	});

	// Event listener para Pedido Total (redistribuir en modo dividir)
				const inputPedidoTotal = document.getElementById('swal-pedido');
				if (inputPedidoTotal) {
		inputPedidoTotal.addEventListener('input', () => {
			if (typeof redistribuirPedidoTotalEntreTelares === 'function') {
				redistribuirPedidoTotalEntreTelares();
			}
		});
	}

	// Event listeners para el switch de modo
	const switchModo = document.getElementById('switch-modo');
	const pillDuplicar = document.getElementById('pill-duplicar');
	const pillDividir = document.getElementById('pill-dividir');

	if (pillDuplicar) {
		pillDuplicar.addEventListener('click', () => {
			const modoDuplicar = document.getElementById('modo-duplicar');
			if (modoDuplicar) modoDuplicar.checked = true;
			if (switchModo) switchModo.checked = true;
			actualizarEstiloSwitch();
		});
	}

	if (pillDividir) {
		pillDividir.addEventListener('click', () => {
			const modoDividir = document.getElementById('modo-dividir');
			if (modoDividir) modoDividir.checked = true;
			if (switchModo) switchModo.checked = false;
			actualizarEstiloSwitch();
		});
	}

	// Event listener para el checkbox de vincular
	const checkboxVincular = document.getElementById('checkbox-vincular');
	if (checkboxVincular) {
		checkboxVincular.checked = false;
		checkboxVincular.addEventListener('change', () => {
			actualizarEstiloSwitch();
		});
	}

	// Estado inicial
	actualizarEstiloSwitch();
	recomputeState();

	// Listener global para capturar cambios en inputs de pedido y porcentaje (DELEGACIÓN DE EVENTOS)
	// Solo agregar una vez usando una bandera global
	if (!window.modalDuplicarListenersGlobalesAgregados) {
		window.modalDuplicarListenersGlobalesAgregados = true;

		document.addEventListener('input', (event) => {
			// No ejecutar si se está redistribuyendo
			if (window.redistribuyendo) {
				return;
			}

			const target = event.target;
			const modoActual = getModoActual();

			// Si es modo duplicar y el input es pedido-tempo-destino o porcentaje-segundos-destino
			if (modoActual === 'duplicar' && (target.matches('input[name="pedido-tempo-destino[]"]') || target.matches('input[name="porcentaje-segundos-destino[]"]'))) {
				// Encontrar la fila padre
				const row = target.closest('tr.telar-row') || target.closest('tr');
				if (row && typeof window.calcularSaldoDuplicar === 'function') {
					window.calcularSaldoDuplicar(row);
				}
				return;
			}

			// Si es modo dividir y el input es pedido-tempo-destino
			if (target.matches('input[name="pedido-tempo-destino[]"]') && modoActual === 'dividir') {
				// Encontrar la fila padre
				const row = target.closest('tr.telar-row') || target.closest('tr');
				if (row && typeof window.calcularSaldoTotal === 'function') {
					window.calcularSaldoTotal(row);
				}
				return;
			}

			// Si es modo dividir y el input es porcentaje-segundos-destino
			if (target.matches('input[name="porcentaje-segundos-destino[]"]') && modoActual === 'dividir') {
				// Encontrar la fila padre
				const row = target.closest('tr.telar-row') || target.closest('tr');
				if (row && typeof window.calcularSaldoTotal === 'function') {
					window.calcularSaldoTotal(row);
				}
				return;
			}
		}, true); // Usar capture para capturar antes que otros listeners
	}
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
	// Datos adicionales del codificado
	const descripcion = document.getElementById('swal-descripcion')?.value || '';
	const custname = document.getElementById('swal-custname')?.value || '';
	const inventSizeId = document.getElementById('swal-inventsizeid')?.value || '';

	// OrdCompartida existente (si el registro ya fue dividido antes)
	// IMPORTANTE: Si el checkbox de vincular está activo, siempre debe ser null para crear uno nuevo
	const ordCompartidaExistenteRaw = document.getElementById('ord-compartida-original')?.value || '';
	const ordCompartidaExistente = vincular ? null : (ordCompartidaExistenteRaw || null);
	const registroIdOriginal = document.getElementById('registro-id-original')?.value || '';

	// Capturar múltiples filas de telar/pedido-tempo/observaciones/pedido/porcentaje_segundos
	// Nota: en modo dividir, el primer telar es un input readonly, no un select
	const telarInputs = document.querySelectorAll('[name="telar-destino[]"]'); // Captura tanto select como input
	const salonInputs = document.querySelectorAll('[name="salon-destino[]"]');
	const pedidoTempoInputs = document.querySelectorAll('input[name="pedido-tempo-destino[]"]');
	const pedidoInputs = document.querySelectorAll('input[name="pedido-destino[]"]');
	const observacionesInputs = document.querySelectorAll('input[name="observaciones-destino[]"]');
	const porcentajeSegundosInputs = document.querySelectorAll('input[name="porcentaje-segundos-destino[]"]');
	const saldoInputs = document.querySelectorAll('input[name="saldo-destino[]"]');
	const filas = document.querySelectorAll('#telar-pedido-body tr');
	const destinos = [];
	const esDuplicar = modo === 'duplicar';

	telarInputs.forEach((input, idx) => {
		const telarVal = input.value.trim();
		const salonVal = (salonInputs[idx]?.value || salon || '').trim();
		const pedidoTempoVal = pedidoTempoInputs[idx]?.value.trim() || null;
		const pedidoVal = pedidoInputs[idx]?.value.trim() || '';
		const observacionesVal = observacionesInputs[idx]?.value.trim() || null;
		const porcentajeSegundosVal = porcentajeSegundosInputs[idx]?.value.trim() || null;
		const saldoVal = saldoInputs[idx]?.value.trim() || '';
		const registroId = input.dataset?.registroId || pedidoInputs[idx]?.dataset?.registroId || '';
		const fila = filas[idx];
		const esExistente = fila?.dataset?.esExistente === 'true';
		const esNuevo = fila?.dataset?.esNuevo === 'true';

		if (telarVal || pedidoVal || saldoVal) {
			// En modo duplicar/vincular:
			// - pedido (TotalPedido) = valor del pedido tempo (sin % de segundas)
			// - saldo (SaldoPedido) = valor calculado con % de segundas
			const pedidoFinal = esDuplicar ? (pedidoTempoVal || pedidoVal) : pedidoVal;
			const saldoFinal = esDuplicar ? (saldoVal || pedidoTempoVal || pedidoVal) : pedidoVal;

			destinos.push({
				salon_destino: salonVal,
				telar: telarVal,
				pedido_tempo: pedidoTempoVal,
				pedido: pedidoFinal, // TotalPedido (sin % de segundas)
				saldo: saldoFinal, // SaldoPedido (con % de segundas)
				observaciones: observacionesVal,
				porcentaje_segundos: porcentajeSegundosVal ? parseFloat(porcentajeSegundosVal) : null,
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
