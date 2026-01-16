{{-- Modal Duplicar Registro - Componente separado --}}
{{-- NOTA: Este archivo se incluye dentro de un bloque <script>, NO agregar etiquetas <script> aquí --}}

{{-- Incluir módulos compartidos y específicos --}}
@include('modulos.programa-tejido.modal._shared-helpers')
@include('modulos.programa-tejido.modal._duplicar-vincular')
@include('modulos.programa-tejido.modal._dividir')

const TELAR_VALUE_SEP = '::';
function buildTelarValue(salon, telar) {
	const s = (salon || '').trim();
	const t = (telar || '').trim();
	if (!s) return t;
	return `${s}${TELAR_VALUE_SEP}${t}`;
}
function parseTelarValue(value) {
	const raw = (value || '').trim();
	if (!raw.includes(TELAR_VALUE_SEP)) {
		return { salon: '', telar: raw };
	}
	const [salon, telar] = raw.split(TELAR_VALUE_SEP);
	return { salon: (salon || '').trim(), telar: (telar || '').trim() };
}
window.buildTelarValue = buildTelarValue;
window.parseTelarValue = parseTelarValue;
const detallesBalanceoCache = new Map();
const descripcionFlogCache = new Map(); // ⚡ Caché para descripciones de flogs

async function obtenerDetalleBalanceo(registroId) {
	if (!registroId) return null;
	if (detallesBalanceoCache.has(registroId)) {
		return detallesBalanceoCache.get(registroId);
	}
	try {
		const resp = await fetch(`/planeacion/programa-tejido/${registroId}/detalles-balanceo`, {
			headers: { 'Accept': 'application/json' }
		});
		if (!resp.ok) return null;
		const data = await resp.json();
		detallesBalanceoCache.set(registroId, data);
		return data;
	} catch (err) {
		return null;
	}
}

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
		const data = await obtenerDetalleBalanceo(registroId);
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
		width: '95%',
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
			const popup = Swal.getPopup();
			if (popup) popup.style.maxWidth = '1800px';
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
	const claveModeloReadonly = yaDividido ? 'readonly' : '';
	const claveModeloClass = yaDividido
		? 'w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed'
		: 'w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500';
	const flogReadonly = '';
	const flogClass = 'w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500';

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

			<!-- Campos ocultos para Clave Modelo, Cod. Artículo, Producto, Pedido Total, Salón, Flog, Descripción y Hilo (se usan en la tabla inferior) -->
			<input type="text" id="swal-claveModelo" value="${claveModelo}" data-salon="${salon}" class="hidden">
			<input type="text" id="swal-codArticulo" value="${codArticulo}" class="hidden">
			<input type="text" id="swal-producto" value="${producto}" class="hidden">
			<input type="text" id="swal-pedido" value="${pedido}" class="hidden">
			<select id="swal-salon" data-salon-actual="${salon}" class="hidden">
				${salon ? `<option value="${salon}" selected>${salon}</option>` : '<option value="">Seleccionar...</option>'}
			</select>
			<input type="text" id="swal-flog" value="${flog}" class="hidden">
			<textarea id="swal-descripcion" class="hidden">${descripcion || ''}</textarea>
			<select id="swal-hilo" data-hilo-actual="${hilo}" class="hidden">
				${hilo ? `<option value="${hilo}" selected>${hilo}</option>` : '<option value="">Seleccionar...</option>'}
			</select>
			<select id="swal-aplicacion" class="hidden">
				${aplicacion ? `<option value="${aplicacion}" selected>${aplicacion}</option>` : '<option value="">Seleccionar...</option>'}
			</select>
			<div id="swal-claveModelo-suggestions" class="absolute z-50 w-full bg-white border border-gray-300 rounded-t shadow-lg hidden max-h-40 overflow-y-auto" style="bottom: 100%; margin-bottom: 2px;"></div>
			<div id="swal-flog-suggestions" class="absolute w-full bg-white border border-gray-300 rounded-t shadow-lg hidden" style="max-height: 500px; overflow-y: auto; z-index: 99999; bottom: 100%; margin-bottom: 2px;"></div>

			<!-- Campos ocultos para datos adicionales del codificado -->
			<input type="hidden" id="swal-custname" value="">
			<input type="hidden" id="swal-inventsizeid" value="">
			<input type="hidden" id="swal-aplicacion-original" value="${aplicacion}">

			<!-- Switch Dividir/Duplicar (pill reactivo: Duplicar azul, Dividir verde) -->
			<div class="my-4 flex items-center justify-between gap-4">
				<!-- Espaciador izquierdo para centrar -->
				<div class="flex-1"></div>

				<!-- Contenedor central con switches -->
				<div class="flex items-center justify-center gap-4">
					<!-- radio buttons ocultos para estado lógico de los 2 modos -->
					<input type="radio" id="modo-duplicar" name="modo-switch" class="hidden" checked>
					<input type="radio" id="modo-dividir" name="modo-switch" class="hidden">
					<!-- checkbox manteniendo compatibilidad con lógica existente -->
					<input type="checkbox" id="switch-modo" class="hidden" checked>

					<div class="inline-flex items-center rounded-full px-1 py-1 text-base font-medium shadow-sm gap-1">
						<button
							type="button"
							id="pill-duplicar"
							class="px-6 py-2 rounded-full transition-all duration-200 bg-blue-500 text-white shadow-md opacity-100">
							Duplicar
						</button>
						<button
							type="button"
							id="pill-dividir"
							class="px-6 py-2 rounded-full transition-all duration-200 bg-white text-gray-700 shadow-sm opacity-80">
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

				<!-- Botón para agregar fila alineado a la derecha -->
				<div class="flex-1 flex justify-end">
					<button type="button" id="btn-add-telar-row" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium" title="Añadir fila">
						<i class="fas fa-plus-circle mr-2"></i>Añadir Fila
					</button>
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
			<div class="border border-gray-300 rounded-lg overflow-visible" style="overflow: visible;">
				<table class="w-full border-collapse" style="overflow: visible;">
					<thead class="bg-gray-100">
						<tr>
							<th id="th-clave-modelo" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300">Clave Modelo</th>
							<th id="th-producto" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300">Producto</th>
							<th id="th-flogs" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300" style="min-width: 200px;">Flogs</th>
							<th id="th-descripcion" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300" style="min-width: 250px;">Descripcion</th>
							<th id="th-aplicacion" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300">Aplicación</th>
							<th id="th-telar" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300">Telar</th>
							<th id="th-pedido-tempo" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300">Pedido</th>
							<th id="th-porcentaje-segundos" class="py-2 px-3 text-xs font-medium text-gray-700 text-left border-b border-r border-gray-300 hidden">% Segundas</th>
							<th id="th-produccion" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300">Produccion</th>
							<th id="th-saldo-total" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300 hidden">Saldo Total</th>
							<th id="th-saldo" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300 hidden">Saldos</th>
							<th id="th-obs" class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300">Obs</th>
							<th id="th-acciones" class="py-2 px-2 text-center border-b border-gray-300 w-16 hidden font-normal"></th>
						</tr>
					</thead>
					<tbody id="telar-pedido-body">
						<tr class="telar-row" id="fila-principal">
							<td class="p-2 border-r border-gray-200 clave-modelo-cell">
								<input type="text" value="${claveModelo || ''}" ${claveModeloReadonly}
									class="${claveModeloClass}">
							</td>
							<td class="p-2 border-r border-gray-200 producto-cell">
								<textarea rows="2" readonly
									class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed resize-none">${producto || ''}</textarea>
							</td>
							<td class="p-2 border-r border-gray-200 flogs-cell" style="min-width: 200px; position: relative;">
								<textarea rows="2" ${flogReadonly}
									class="${flogClass} resize-none">${flog || ''}</textarea>
							</td>
							<td class="p-2 border-r border-gray-200 descripcion-cell" style="min-width: 250px;">
								<textarea rows="2"
									class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 resize-none">${descripcion || ''}</textarea>
							</td>
							<td class="p-2 border-r border-gray-200 aplicacion-cell">
								<select name="aplicacion-destino[]" class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
									<option value="">Seleccionar...</option>
								</select>
							</td>
							<td class="p-2 border-r border-gray-200">
								<select name="telar-destino[]" data-telar-actual="${telar}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 telar-destino-select">
									${telar ? `<option value="${buildTelarValue(salon, telar)}" selected>${telar}</option>` : '<option value="">Seleccionar...</option>'}
								</select>
							</td>
							<td class="p-2 border-r border-gray-200 pedido-tempo-cell">
								<input type="number" name="pedido-tempo-destino[]" value="${pedido}" step="0.01" min="0"
									class="w-24 px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
							</td>
						<td class="p-2 border-r border-gray-200 porcentaje-segundos-cell">
							<input type="number" name="porcentaje-segundos-destino[]" value="0" step="0.01" min="0"
								placeholder="0.00"
								class="w-20 px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
						</td>
						<td class="p-2 border-r border-gray-200 produccion-cell hidden">
								<input type="hidden" name="pedido-destino[]" value="${pedido}">
						</td>
							<td class="p-2 border-r border-gray-200 saldo-total-cell hidden">
								<input type="text" value="${saldo || ''}" readonly
									class="w-24 px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
							</td>
							<td class="p-2 border-r border-gray-200 saldo-cell">
								<input type="number" name="saldo-destino[]" value="${pedido || ''}" step="0.01" min="0"
									class="w-24 px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
							</td>
						<td class="p-2 border-r border-gray-200">
							<textarea rows="2" name="observaciones-destino[]"
								placeholder="Observaciones..."
								class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 resize-none"></textarea>
							</td>
				<td class="p-2 text-center acciones-cell">
					<button type="button" class="btn-remove-row px-2 py-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded transition-colors" title="Eliminar fila">
						<i class="fas fa-times"></i>
					</button>
				</td>
						<input type="hidden" name="salon-destino[]" value="${salon}">
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

	if (!tbody || !selectHilo || !selectSalon || !selectAplicacion) {
		return;
	}

	// Datos de OrdCompartida
	const ordCompartidaActualLocal = ordCompartidaParam || document.getElementById('ord-compartida-original')?.value || '';
	const registroIdActual = registroIdParam || document.getElementById('registro-id-original')?.value || '';
	const tieneOrdCompartida = ordCompartidaActualLocal && ordCompartidaActualLocal !== '' && ordCompartidaActualLocal !== '0';

	// Variables para almacenar datos cargados
	let telaresDisponibles = [];
	let salonesDisponibles = [];
	let sugerenciasClaveModelo = [];
	let sugerenciasFlog = [];
	let todasOpcionesFlog = []; // Mantener para compatibilidad
	let todasOpcionesFlogGeneral = []; // Todos los flogs disponibles para búsqueda libre (sin filtros)
	let debounceTimer = null;
	let debounceTimerFlog = null;
	let suppressClaveAutocomplete = false;
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
		const pedidoInputs = document.querySelectorAll('input[name="pedido-destino[]"]');
		const filas = document.querySelectorAll('#telar-pedido-body tr');

		let firstComplete = false;
		let hasAnyFilled = false;
		let allDestinationsValid = true;

		telarInputs.forEach((input, idx) => {
			const telarVal = input.value.trim();
			const pedidoVal = (pedidoInputs[idx]?.value || '').trim();
			const fila = filas[idx];
			const salonInputFila = fila?.querySelector('input[name="salon-destino[]"]');
			const salonVal = (salonInputFila?.value || '').trim();

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
					if (telarVal === '' || pedidoVal === '') {
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
		const thAcciones = document.getElementById('th-acciones');

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
			// En modo duplicar, la columna de acciones no se llama "Líder"
			if (thAcciones) {
				thAcciones.textContent = ''; // Vacío o solo el ícono de eliminar
			}
			// ⚡ FIX: Mostrar columna de acciones solo para filas agregadas (no la fila principal)
			const filasAgregadas = document.querySelectorAll('tr.telar-row:not(#fila-principal)');
			if (filasAgregadas.length > 0) {
				// Si hay filas agregadas, mostrar la columna de acciones
				if (thAcciones) thAcciones.classList.remove('hidden');
				document.querySelectorAll('tr.telar-row:not(#fila-principal) .acciones-cell').forEach((cell) => cell.classList.remove('hidden'));
			} else {
				// Si no hay filas agregadas, ocultar la columna de acciones
				if (thAcciones) thAcciones.classList.add('hidden');
				document.querySelectorAll('.acciones-cell').forEach((cell) => cell.classList.add('hidden'));
			}
			// La fila principal siempre tiene la columna de acciones oculta
			document.querySelectorAll('tr#fila-principal .acciones-cell').forEach((cell) => cell.classList.add('hidden'));

			document.querySelectorAll('.salon-cell').forEach((cell) => cell.classList.add('hidden'));
			document.querySelectorAll('.pedido-tempo-cell').forEach((cell) => cell.classList.remove('hidden'));
			document.querySelectorAll('.porcentaje-segundos-cell').forEach((cell) => cell.classList.remove('hidden'));
			document.querySelectorAll('.produccion-cell').forEach((cell) => cell.classList.add('hidden'));
			document.querySelectorAll('.saldo-total-cell').forEach((cell) => cell.classList.add('hidden'));
			document.querySelectorAll('.saldo-cell').forEach((cell) => cell.classList.remove('hidden'));
			// ⚡ FIX: Mostrar columna de acciones solo para filas agregadas (no la fila principal)
			document.querySelectorAll('tr.telar-row:not(#fila-principal) .acciones-cell').forEach((cell) => cell.classList.remove('hidden'));
			document.querySelectorAll('tr#fila-principal .acciones-cell').forEach((cell) => cell.classList.add('hidden'));
		} else {
			// Modo dividir: salon, telar, pedido, %segundas, produccion, saldo total
			// En modo dividir, la columna de acciones se llama "Líder"
			if (thAcciones) {
				thAcciones.textContent = 'Líder';
			}
			if (thSalon) thSalon.classList.remove('hidden');
			if (thPedidoTempo) {
				thPedidoTempo.classList.remove('hidden');
				thPedidoTempo.textContent = 'Pedido';
			}
			if (thPorcentajeSegundos) thPorcentajeSegundos.classList.remove('hidden');
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
			if (thAcciones) thAcciones.classList.remove('hidden');

			document.querySelectorAll('.salon-cell').forEach((cell) => cell.classList.remove('hidden'));
			document.querySelectorAll('.pedido-tempo-cell').forEach((cell) => cell.classList.remove('hidden'));
			document.querySelectorAll('.porcentaje-segundos-cell').forEach((cell) => cell.classList.remove('hidden'));
			document.querySelectorAll('.produccion-cell').forEach((cell) => cell.classList.remove('hidden'));
			document.querySelectorAll('.saldo-total-cell').forEach((cell) => cell.classList.remove('hidden'));
			document.querySelectorAll('.saldo-cell').forEach((cell) => cell.classList.add('hidden'));
			document.querySelectorAll('.acciones-cell').forEach((cell) => cell.classList.remove('hidden'));

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

function buildBaseInfoCells({ claveModelo, producto, flog, descripcion, aplicacionOptionsHTML, aplicacionSeleccionada, ringClass, editableClaveModelo, editableFlog }) {
		const readonlyClass = 'w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed';
		const editableClass = `w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 ${ringClass}`;
		const selectClass = `w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 ${ringClass}`;
		const claveClass = editableClaveModelo
			? `w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 ${ringClass}`
			: readonlyClass;
		const claveReadonly = editableClaveModelo ? '' : 'readonly';
		const flogClass = editableFlog
			? `w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 ${ringClass}`
			: readonlyClass;
		const flogReadonly = editableFlog ? '' : 'readonly';

		return `
		<td class="p-2 border-r border-gray-200 clave-modelo-cell">
			<input type="text" value="${claveModelo || ''}" ${claveReadonly} class="${claveClass}">
		</td>
			<td class="p-2 border-r border-gray-200 producto-cell">
				<textarea rows="2" readonly class="${readonlyClass} resize-none">${producto || ''}</textarea>
		</td>
		<td class="p-2 border-r border-gray-200 flogs-cell" style="min-width: 200px; position: relative;">
			<textarea rows="2" ${flogReadonly} class="${flogClass} resize-none">${flog || ''}</textarea>
		</td>
			<td class="p-2 border-r border-gray-200 descripcion-cell" style="min-width: 250px;">
				<textarea rows="2" class="${editableClass} resize-none">${descripcion || ''}</textarea>
			</td>
			<td class="p-2 border-r border-gray-200 aplicacion-cell">
				<select name="aplicacion-destino[]" class="${selectClass}" data-valor-seleccionado="${aplicacionSeleccionada || ''}">
					${aplicacionOptionsHTML.replace(/<option value="([^"]*)">/g, (match, value) => {
						return `<option value="${value}"${value === (aplicacionSeleccionada || '') ? ' selected' : ''}>`;
					})}
				</select>
			</td>
		`;
	}

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
		const thSaldoTotal = document.getElementById('th-saldo-total');
		if (thSaldoTotal) {
			thSaldoTotal.classList.remove('hidden');
		}

		const telarOriginal = document.getElementById('telar-original')?.value || telarActual;
		const pedidoOriginal = document.getElementById('pedido-original')?.value || '';
		const saldoOriginal = document.getElementById('saldo-original')?.value || '';
		const produccionOriginal = document.getElementById('produccion-original')?.value || '';
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
					aplicacionOptionsHTML += `<option value="${option.value}">${option.textContent}</option>`;
				}
			});
		}

		const thTelar = document.getElementById('th-telar');
		const thPedidoTempo = document.getElementById('th-pedido-tempo');
		// Para nuevas filas, no preseleccionar ninguna aplicación (dejar que el usuario elija)
		const aplicacionParaFila = '';

		const baseCells = buildBaseInfoCells({
			claveModelo,
			producto,
			flog,
			descripcion,
			aplicacionOptionsHTML,
			aplicacionSeleccionada: aplicacionParaFila,
			ringClass: esDuplicar ? 'focus:ring-blue-500' : 'focus:ring-green-500',
			editableClaveModelo: esDuplicar,
			editableFlog: esDuplicar
		});

		if (esDuplicar) {
			if (thTelar) thTelar.textContent = 'Telar';
			if (thPedidoTempo) thPedidoTempo.textContent = 'Pedido';

			filaPrincipal.innerHTML = `
				${baseCells}
				<td class="p-2 border-r border-gray-200">
					<select name="telar-destino[]" data-telar-actual="${telarOriginal}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 telar-destino-select">
						${telarOriginal ? `<option value="${buildTelarValue(salonActualLocal || salonActual, telarOriginal)}" selected>${telarOriginal}</option>` : '<option value="">Seleccionar...</option>'}
					</select>
				</td>
				<td class="p-2 border-r border-gray-200 pedido-tempo-cell">
					<input type="number" name="pedido-tempo-destino[]" value="${pedidoOriginal}" step="0.01" min="0"
						class="w-24 px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
				</td>
				<td class="p-2 border-r border-gray-200 porcentaje-segundos-cell">
					<input type="number" name="porcentaje-segundos-destino[]" value="0" step="0.01" min="0"
						placeholder="0.00"
						class="w-20 px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
				</td>
				<td class="p-2 border-r border-gray-200 produccion-cell hidden">
					<input type="hidden" name="pedido-destino[]" value="${pedidoOriginal}">
				</td>
				<td class="p-2 border-r border-gray-200 saldo-total-cell hidden">
					<input type="text" value="${saldoOriginal}" readonly
						class="w-24 px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
				</td>
				<td class="p-2 border-r border-gray-200 saldo-cell">
					<input type="number" name="saldo-destino[]" value="${pedidoOriginal || ''}" step="0.01" min="0"
						class="w-24 px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
				</td>
						<td class="p-2 border-r border-gray-200">
							<textarea rows="2" name="observaciones-destino[]"
								placeholder="Observaciones..."
								class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 resize-none"></textarea>
							</td>
				<td class="p-2 text-center acciones-cell">
					<button type="button" class="btn-remove-row px-2 py-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded transition-colors" title="Eliminar fila">
						<i class="fas fa-times"></i>
					</button>
				</td>
				<input type="hidden" name="salon-destino[]" value="${selectSalon?.value || salonActualLocal || ''}">
			`;

			const selectTelar = filaPrincipal.querySelector('select[name="telar-destino[]"]');
			if (selectTelar && telaresDisponibles.length > 0) {
				selectTelar.innerHTML = '<option value="">Seleccionar...</option>';
				telaresDisponibles.forEach(t => {
					const option = document.createElement('option');
					const isObj = t && typeof t === 'object';
					const optionValue = isObj ? (t.value || t.telar || '') : t;
					// Solo mostrar el número del telar, sin el salón
					const optionLabel = isObj ? (t.telar || t.value || '') : t;
					const optionSalon = isObj ? (t.salon || '') : '';
					option.value = optionValue;
					option.textContent = optionLabel;
					if (optionSalon) option.dataset.salon = optionSalon;
					// Comparar con telarOriginal usando el valor o el telar del objeto
					const telarComparar = isObj ? (t.telar || t.value) : t;
					if (telarComparar == telarOriginal || optionValue == telarOriginal) {
						option.selected = true;
					}
					selectTelar.appendChild(option);
				});
			}

			const telarSelect = filaPrincipal.querySelector('select[name="telar-destino[]"]');
			const pedidoInput = filaPrincipal.querySelector('input[name="pedido-destino[]"]');
			if (telarSelect) telarSelect.addEventListener('change', recomputeState);
			if (telarSelect) {
				telarSelect.addEventListener('change', () => {
					const hiddenSalon = filaPrincipal.querySelector('input[name="salon-destino[]"]');
					if (!hiddenSalon) return;
					const parsed = parseTelarValue(telarSelect.value);
					if (parsed.salon) hiddenSalon.value = parsed.salon;
				});
			}
			if (pedidoInput) pedidoInput.addEventListener('input', recomputeState);

			agregarListenersCalculoAutomatico(filaPrincipal);

			// ⚡ FIX: Agregar event listener para el botón de eliminar si existe
			const btnRemoveFilaPrincipal = filaPrincipal.querySelector('.btn-remove-row');
			if (btnRemoveFilaPrincipal) {
				btnRemoveFilaPrincipal.addEventListener('click', () => {
					// No permitir eliminar la fila principal
					console.warn('No se puede eliminar la fila principal');
				});
			}

			// ⚡ FIX: Bindear el textarea de descripción para sincronización bidireccional
			bindDescripcionEditableInput();

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
					${baseCells}
					<td class="p-2 border-r border-gray-200">
						<div class="flex items-center gap-2">
							<input type="text" name="telar-destino[]" value="${telarOriginal}" readonly
								data-registro-id=""
								class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
						</div>
					</td>
					<td class="p-2 border-r border-gray-200 pedido-tempo-cell">
						<input type="number" name="pedido-tempo-destino[]" value="${pedidoOriginal}" data-pedido-total="true" step="0.01" min="0" readonly
							class="w-24 px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
					</td>
					<td class="p-2 border-r border-gray-200 porcentaje-segundos-cell">
						<input type="number" name="porcentaje-segundos-destino[]" value="0" step="0.01" min="0" readonly
							class="w-20 px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
					</td>
					<td class="p-2 border-r border-gray-200 produccion-cell">
						<input type="text" value="${produccionOriginal || ''}" readonly
							class="w-24 px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
						<input type="hidden" name="pedido-destino[]" value="${pedidoOriginal}">
					</td>
					<td class="p-2 border-r border-gray-200 saldo-total-cell">
						<input type="text" value="${saldoOriginal}" readonly
							class="w-24 px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 text-gray-700 cursor-not-allowed">
					</td>
					<td class="p-2 border-r border-gray-200">
						<textarea rows="2" name="observaciones-destino[]"
							placeholder="Observaciones..."
							class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-green-500 resize-none"></textarea>
					</td>
					<td class="p-2 text-center acciones-cell">
						<div class="w-3 h-3 rounded-full bg-green-500 mx-auto" title="Líder"></div>
					</td>
				`;
				const hiddenSalon = document.createElement('input');
				hiddenSalon.type = 'hidden';
				hiddenSalon.name = 'salon-destino[]';
				hiddenSalon.value = selectSalon?.value || salonActualLocal || '';
				filaPrincipal.appendChild(hiddenSalon);

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
		bindClaveModeloEditableInput();
		bindFlogEditableInput();
		bindDescripcionEditableInput();
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
		if (suppressClaveAutocomplete) {
			suppressClaveAutocomplete = false;
			return;
		}
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

		// Asegurar que el contenedor esté posicionado arriba
		const claveCell = document.querySelector('#telar-pedido-body tr#fila-principal .clave-modelo-cell');
		if (claveCell && inputClaveModelo) {
			if (!claveCell.contains(containerSugerencias)) {
				claveCell.style.position = 'relative';
				claveCell.appendChild(containerSugerencias);
			}
			containerSugerencias.style.position = 'absolute';
			containerSugerencias.style.bottom = '100%';
			containerSugerencias.style.top = 'auto';
			containerSugerencias.style.left = '0';
			containerSugerencias.style.marginBottom = '2px';
		}

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
		clearTimeout(debounceTimer);
		suppressClaveAutocomplete = true;
		inputClaveModelo.value = clave;
		containerSugerencias.classList.add('hidden');
		// Cargar datos relacionados solo para la fila principal
		const filaPrincipal = document.querySelector('#telar-pedido-body tr#fila-principal');
		if (filaPrincipal && typeof window.cargarDatosRelacionadosRow === 'function') {
			window.cargarDatosRelacionadosRow(filaPrincipal, clave);
		} else {
			// Fallback a la función global (para compatibilidad)
			cargarDatosRelacionados(clave);
		}
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

					// NO usar FlogsId ni NombreProyecto de ReqModelosCodificados
					// El flog y descripción se cargarán desde TI_PRO usando ItemId e InventSizeId
					if (inputCustname) inputCustname.value = data.datos.CustName || '';
					if (inputInventSizeId) inputInventSizeId.value = data.datos.InventSizeId || '';

					inputCodArticulo.dispatchEvent(new Event('input', { bubbles: true }));
					inputProducto.dispatchEvent(new Event('input', { bubbles: true }));

					const itemId = data.datos.ItemId || '';
					const inventSizeId = data.datos.InventSizeId || '';
					if (itemId && inventSizeId) {
						const params = new URLSearchParams();
						params.append('item_id', itemId);
						params.append('invent_size_id', inventSizeId);

						fetch('/programa-tejido/flog-by-item?' + params.toString(), {
							headers: { 'Accept': 'application/json' }
						})
							.then(r => {
								return r.json();
							})
							.then(info => {
								// LOG: CustName desde flog
								const custNameFromFlog = (info?.custName || info?.CustName || info?.custname || '').trim();
								console.log('[cargarDatosRelacionados] 🏢 CUSTNAME DESDE FLOG-BY-ITEM:', {
									custNameFromFlog,
									infoCompleto: info
								});

								// Actualizar CustName desde flog-by-item
								if (custNameFromFlog && inputCustname) {
									inputCustname.value = custNameFromFlog;
									console.log('[cargarDatosRelacionados] ✅ CustName actualizado desde flog:', custNameFromFlog);
								}

								// LOG: Datos del flog obtenidos
								console.log('[cargarDatosRelacionadosRow] 📋 DATOS DEL FLOG OBTENIDOS:', {
									idflog: info?.idflog,
									nombreProyecto: info?.nombreProyecto,
									custName: info?.custName || info?.CustName,
									itemId: itemId,
									inventSizeId: inventSizeId
								});

								// Autocompletar flog y descripción desde TI_PRO
								// Si no se obtiene, dejar en blanco (el usuario puede escribir libremente)
								if (info?.idflog) {
									if (inputFlog) inputFlog.value = info.idflog;

									// La descripción debe ser: NAMEPROYECT (IDFLOG)
									if (inputDescripcion) {
										if (info?.nombreProyecto) {
											const descripcionCompleta = `${info.nombreProyecto} (${info.idflog})`;
											inputDescripcion.value = descripcionCompleta;
										} else {
											// Si no hay nombreProyecto, usar solo el idflog entre paréntesis
											inputDescripcion.value = `(${info.idflog})`;
										}
										inputDescripcion.dispatchEvent(new Event('input', { bubbles: true }));
									}
								} else {
									if (inputFlog) inputFlog.value = '';
									if (inputDescripcion) inputDescripcion.value = '';
								}
							})
							.catch((error) => {
								console.error('[cargarDatosRelacionados] Error al buscar flog desde TI_PRO:', error);
								// Si hay error, dejar en blanco para que el usuario pueda escribir libremente
								if (inputFlog) inputFlog.value = '';
								if (inputDescripcion) inputDescripcion.value = '';
							});
					} else {
						// Si no hay itemId o inventSizeId, dejar en blanco
						if (inputFlog) inputFlog.value = '';
						if (inputDescripcion) inputDescripcion.value = '';
					}
				}
				actualizarTelaresPorClaveModelo(tamanoClave);
			})
			.catch(() => {});
	}

	function esSalonJacquardOSmit(salon) {
		const val = (salon || '').toUpperCase();
		return val.includes('JAC') || val.includes('SMI') || val.includes('SMIT');
	}

	function normalizarClaveModelo(valor) {
		return String(valor || '').trim().toUpperCase();
	}

	async function existeClaveEnSalon(salon, claveModelo) {
		const params = new URLSearchParams();
		params.append('salon_tejido_id', salon);
		params.append('search', claveModelo);

		try {
			const res = await fetch('/programa-tejido/tamano-clave-by-salon?' + params);
			if (!res.ok) return false;
			const opciones = await res.json();
			if (!Array.isArray(opciones)) return false;
			const claveNorm = normalizarClaveModelo(claveModelo);
			return opciones.some(op => normalizarClaveModelo(op) === claveNorm);
		} catch (error) {
			return false;
		}
	}

	function actualizarHiddenSalonPorTelar() {
		const filas = document.querySelectorAll('#telar-pedido-body tr');
		filas.forEach(fila => {
			const telarSelect = fila.querySelector('select[name="telar-destino[]"]');
			const salonInput = fila.querySelector('input[name="salon-destino[]"]');
			if (!telarSelect || !salonInput) return;
			const parsed = typeof window.parseTelarValue === 'function'
				? window.parseTelarValue(telarSelect.value)
				: { salon: '' };
			if (parsed.salon) {
				salonInput.value = parsed.salon;
			}
		});
	}

	async function actualizarTelaresPorClaveModelo(claveModelo) {

		if (!claveModelo || !Array.isArray(salonesDisponibles) || salonesDisponibles.length === 0) {
			return;
		}

		// Buscar en TODOS los salones disponibles, no solo JACQUARD y SMIT
		const candidatos = salonesDisponibles;

		if (candidatos.length === 0) {
			return;
		}

		const claveNorm = normalizarClaveModelo(claveModelo);

		// Verificar en qué salones existe la clave modelo
		const checks = await Promise.all(candidatos.map(salon => existeClaveEnSalon(salon, claveNorm)));

		const salonesMatch = candidatos.filter((salon, idx) => checks[idx]);

		if (salonesMatch.length === 0) {
			return;
		}

		// Si solo hay un salón, preseleccionarlo
		if (salonesMatch.length === 1 && selectSalon) {
			selectSalon.value = salonesMatch[0];
			salonActualLocal = selectSalon.value;
		}

		// Cargar telares de todos los salones donde existe la clave
		const telas = await Promise.all(
			salonesMatch.map(salon =>
				fetch('/programa-tejido/telares-by-salon?salon_tejido_id=' + encodeURIComponent(salon), {
					headers: { 'Accept': 'application/json' }
				}).then(r => r.json()).catch(() => [])
			)
		);


		const merged = [];
		salonesMatch.forEach((salon, idx) => {
			const lista = Array.isArray(telas[idx]) ? telas[idx] : [];
			lista.forEach(telar => {
				merged.push({
					salon,
					telar,
					value: buildTelarValue(salon, telar),
					label: telar
				});
			});
		});

		telaresDisponibles = merged;
		window.telaresDisponibles = merged;


		if (typeof actualizarSelectsTelares === 'function') {
			actualizarSelectsTelares(true);
		}
		actualizarHiddenSalonPorTelar();

	}

	// Autocompletado de Flog - Funciona independientemente de la clave modelo
	// Permite búsqueda libre de CUALQUIER flog sin restricciones
	async function cargarOpcionesFlog(search = '') {

		try {
			// SIEMPRE cargar todos los flogs disponibles (búsqueda libre, sin depender de clave modelo)
			// Solo cargar del servidor si no los tenemos en caché
			if (todasOpcionesFlogGeneral.length === 0) {
				const response = await fetch('/programa-tejido/flogs-id-from-twflogs', {
					headers: {
						'Accept': 'application/json',
						'X-CSRF-TOKEN': getCsrfToken()
					}
				});

				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}

				const opciones = await response.json();
				const opcionesArray = Array.isArray(opciones) ? opciones : [];
				todasOpcionesFlogGeneral = opcionesArray.filter(f => f && String(f).trim()).map(f => String(f).trim());
			} else {
			}

			// Verificar que tenemos flogs disponibles
			if (todasOpcionesFlogGeneral.length === 0) {
				if (containerSugerenciasFlog) {
					containerSugerenciasFlog.classList.add('hidden');
				}
				return;
			}

			// Filtrar según la búsqueda del usuario (BÚSQUEDA LIBRE - sin filtros de clave modelo o tamaño)
			if (search && search.length >= 1) {
				const searchLower = search.toLowerCase().trim();

				// SIEMPRE filtrar en TODOS los flogs generales (búsqueda libre, sin filtros)
				const flogsFiltrados = todasOpcionesFlogGeneral.filter(opcion => {
					const opcionStr = String(opcion || '').toLowerCase().trim();
					return opcionStr && opcionStr.includes(searchLower);
				});

				// Convertir a formato de objetos para mostrar
				sugerenciasFlog = flogsFiltrados.map(id => ({ idflog: String(id), nombreProyecto: '' }));

			} else if (!search || search.length === 0) {
				// Si no hay búsqueda, mostrar todos los disponibles (búsqueda libre)
				sugerenciasFlog = todasOpcionesFlogGeneral.map(id => ({ idflog: String(id), nombreProyecto: '' }));
			}


		// ⚡ DEBUG: Log para verificar cuántas sugerencias se encontraron
		console.log('[cargarOpcionesFlog] ⚡ Total sugerencias encontradas:', sugerenciasFlog.length);
		console.log('[cargarOpcionesFlog] ⚡ Primeras 10 sugerencias:', sugerenciasFlog.slice(0, 10));

		// SIEMPRE mostrar las sugerencias (incluso si hay 0, para mostrar "No se encontraron coincidencias")
		mostrarSugerenciasFlog(sugerenciasFlog);
		} catch (error) {
			console.error('[cargarOpcionesFlog] Error:', error);
			if (containerSugerenciasFlog) {
				containerSugerenciasFlog.classList.add('hidden');
			}
		}
	}

	function mostrarSugerenciasFlog(sugerencias) {
		if (!containerSugerenciasFlog) {
			return;
		}

		if (!inputFlog) {
			return;
		}

		containerSugerenciasFlog.innerHTML = '';

		// ⚡ FIX: Buscar el textarea visible en la fila principal de la tabla, no el input oculto
		const filaPrincipal = document.querySelector('#telar-pedido-body tr#fila-principal');
		const flogCell = filaPrincipal ? filaPrincipal.querySelector('.flogs-cell') : null;

		if (flogCell) {
			// Asegurar que la celda tenga position relative y overflow visible
			flogCell.style.position = 'relative';
			flogCell.style.overflow = 'visible';
			flogCell.style.zIndex = '1';

			// Si el contenedor no está dentro de la celda, moverlo
			if (!flogCell.contains(containerSugerenciasFlog)) {
				flogCell.appendChild(containerSugerenciasFlog);
			}

			// Buscar el textarea visible dentro de la celda
			const flogTextarea = flogCell.querySelector('textarea') || flogCell.querySelector('input');

			if (flogTextarea) {
				// Obtener la posición del textarea relativa a la celda
				const textareaRect = flogTextarea.getBoundingClientRect();
				const cellRect = flogCell.getBoundingClientRect();

				// Posicionar el contenedor arriba del textarea
				containerSugerenciasFlog.style.position = 'absolute';
				containerSugerenciasFlog.style.bottom = '100%'; // Posicionar arriba del textarea
				containerSugerenciasFlog.style.top = 'auto';
				containerSugerenciasFlog.style.left = '0';
				containerSugerenciasFlog.style.right = 'auto';
				containerSugerenciasFlog.style.marginBottom = '2px'; // Pequeño espacio entre el contenedor y el textarea
				containerSugerenciasFlog.style.width = Math.max(flogTextarea.offsetWidth, 300) + 'px'; // Mínimo 300px de ancho
				containerSugerenciasFlog.style.borderRadius = '0.375rem 0.375rem 0 0'; // Redondeo arriba
				containerSugerenciasFlog.style.zIndex = '99999'; // ⚡ Z-index muy alto para estar por encima de todo (incluido SweetAlert)
				containerSugerenciasFlog.style.maxHeight = '500px'; // ⚡ Aumentado para mostrar más registros (3 o más)
				containerSugerenciasFlog.style.overflowY = 'auto';
				containerSugerenciasFlog.style.overflowX = 'hidden';
				containerSugerenciasFlog.style.backgroundColor = 'white';
				containerSugerenciasFlog.style.border = '1px solid #d1d5db';
				containerSugerenciasFlog.style.borderRadius = '0.375rem';
				containerSugerenciasFlog.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 10px 15px -3px rgba(0, 0, 0, 0.1)';
				containerSugerenciasFlog.style.display = 'block';
				containerSugerenciasFlog.style.visibility = 'visible';
				containerSugerenciasFlog.style.opacity = '1';
				containerSugerenciasFlog.style.pointerEvents = 'auto';
				containerSugerenciasFlog.classList.remove('hidden');

				// ⚡ DEBUG: Log para verificar posicionamiento
				console.log('[mostrarSugerenciasFlog] ⚡ Contenedor posicionado en flogCell:', {
					cellPosition: flogCell.style.position,
					containerPosition: containerSugerenciasFlog.style.position,
					containerZIndex: containerSugerenciasFlog.style.zIndex,
					containerBottom: containerSugerenciasFlog.style.bottom,
					containerWidth: containerSugerenciasFlog.style.width,
					containerMaxHeight: containerSugerenciasFlog.style.maxHeight
				});
			}
		} else {
			// ⚡ FIX: Si no se encuentra la celda, posicionar el contenedor de forma fija relativo al viewport
			console.warn('[mostrarSugerenciasFlog] ⚠️ No se encontró flogCell, usando posicionamiento alternativo');
			containerSugerenciasFlog.style.position = 'fixed';
			containerSugerenciasFlog.style.zIndex = '99999';
			containerSugerenciasFlog.style.maxHeight = '500px';
			containerSugerenciasFlog.style.overflowY = 'auto';
			containerSugerenciasFlog.style.backgroundColor = 'white';
			containerSugerenciasFlog.style.border = '1px solid #d1d5db';
			containerSugerenciasFlog.style.borderRadius = '0.375rem';
			containerSugerenciasFlog.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
			containerSugerenciasFlog.style.display = 'block';
			containerSugerenciasFlog.style.visibility = 'visible';
			containerSugerenciasFlog.style.opacity = '1';
			containerSugerenciasFlog.style.pointerEvents = 'auto';
			containerSugerenciasFlog.classList.remove('hidden');
		}

		if (!sugerencias || sugerencias.length === 0) {
			const div = document.createElement('div');
			div.className = 'px-3 py-2 text-gray-500 text-xs italic';
			div.textContent = 'No se encontraron coincidencias';
			containerSugerenciasFlog.appendChild(div);
			containerSugerenciasFlog.classList.remove('hidden');
			containerSugerenciasFlog.style.display = 'block';
			containerSugerenciasFlog.style.visibility = 'visible';
			return;
		}

		// ⚡ DEBUG: Log para verificar cuántas sugerencias se van a mostrar
		console.log('[mostrarSugerenciasFlog] ⚡ Mostrando', sugerencias.length, 'sugerencias');

		// Si las sugerencias son objetos con idflog y nombreProyecto
		const esArrayObjetos = Array.isArray(sugerencias) && sugerencias.length > 0 && typeof sugerencias[0] === 'object' && sugerencias[0] !== null && sugerencias[0].idflog;

		// ⚡ FIX: Asegurar que se muestren TODAS las sugerencias, sin límite
		sugerencias.forEach((sugerencia, index) => {
			const div = document.createElement('div');
			div.className = 'px-3 py-2 hover:bg-blue-100 cursor-pointer text-sm';

			if (esArrayObjetos) {
				// Mostrar idflog y descripción
				div.innerHTML = `
					<div class="font-medium">${sugerencia.idflog || ''}</div>
					<div class="text-xs text-gray-600">${sugerencia.nombreProyecto || ''}</div>
				`;
				// ⚡ FIX: Usar mousedown en vez de click para evitar que el blur oculte antes del click
				div.addEventListener('mousedown', (e) => {
					e.preventDefault(); // Prevenir que el input pierda el foco
					e.stopPropagation();

					const flogValue = sugerencia.idflog || '';

					// ⚡ FIX: Actualizar tanto el input oculto como el textarea visible
					if (inputFlog) {
						inputFlog.value = flogValue;
						inputFlog.dispatchEvent(new Event('input', { bubbles: true }));
					}

					// Actualizar el textarea visible en la fila principal
					const filaPrincipal = document.querySelector('#telar-pedido-body tr#fila-principal');
					const flogTextarea = filaPrincipal ? filaPrincipal.querySelector('.flogs-cell textarea') : null;
					if (flogTextarea) {
						flogTextarea.value = flogValue;
						flogTextarea.dispatchEvent(new Event('input', { bubbles: true }));
					}

					// ⚡ FIX: Ocultar sugerencias inmediatamente después de seleccionar
					containerSugerenciasFlog.classList.add('hidden');
					containerSugerenciasFlog.style.display = 'none';

					// ⚡ FIX: SIEMPRE hacer el get para cargar la descripción automáticamente
					// No es necesario presionar Enter, se carga automáticamente al hacer click
					if (flogValue) {
						cargarDescripcionPorFlog(flogValue).then(() => {
							// Sincronizar todas las columnas después de cargar la descripción
							if (typeof actualizarColumnasInformacion === 'function') {
								actualizarColumnasInformacion();
							}
						}).catch(() => {
							// Si hay error, al menos sincronizar las columnas
							if (typeof actualizarColumnasInformacion === 'function') {
								actualizarColumnasInformacion();
							}
						});
					}
				});
			} else {
				// Comportamiento anterior: solo string
				const flogValue = String(sugerencia || '').trim();
				if (!flogValue) return; // Saltar si está vacío

				div.textContent = flogValue;
				// ⚡ FIX: Usar mousedown en vez de click para evitar que el blur oculte antes del click
				div.addEventListener('mousedown', (e) => {
					e.preventDefault(); // Prevenir que el input pierda el foco
					e.stopPropagation();

					// ⚡ FIX: Actualizar tanto el input oculto como el textarea visible
					if (inputFlog) {
						inputFlog.value = flogValue;
						inputFlog.dispatchEvent(new Event('input', { bubbles: true }));
					}

					// Actualizar el textarea visible en la fila principal
					const filaPrincipal = document.querySelector('#telar-pedido-body tr#fila-principal');
					const flogTextarea = filaPrincipal ? filaPrincipal.querySelector('.flogs-cell textarea') : null;
					if (flogTextarea) {
						flogTextarea.value = flogValue;
						flogTextarea.dispatchEvent(new Event('input', { bubbles: true }));
					}

					// ⚡ FIX: Ocultar sugerencias inmediatamente después de seleccionar
					containerSugerenciasFlog.classList.add('hidden');
					containerSugerenciasFlog.style.display = 'none';

					// ⚡ FIX: SIEMPRE hacer el get para cargar la descripción automáticamente
					cargarDescripcionPorFlog(flogValue).then(() => {
						// Sincronizar todas las columnas después de cargar la descripción
						if (typeof actualizarColumnasInformacion === 'function') {
							actualizarColumnasInformacion();
						}
					});
				});
			}

			containerSugerenciasFlog.appendChild(div);
		});

		// ⚡ FIX: Asegurar que el contenedor sea visible y tenga el tamaño correcto
		containerSugerenciasFlog.classList.remove('hidden');
		containerSugerenciasFlog.style.display = 'block';
		containerSugerenciasFlog.style.visibility = 'visible';
		containerSugerenciasFlog.style.opacity = '1';
		containerSugerenciasFlog.style.pointerEvents = 'auto';

		// ⚡ FIX: Asegurar que los elementos padre no tengan overflow hidden que corte el autocompletado
		const tablaContainer = flogCell?.closest('.border.border-gray-300');
		if (tablaContainer) {
			tablaContainer.style.overflow = 'visible';
		}
		const tbody = flogCell?.closest('tbody');
		if (tbody) {
			tbody.style.overflow = 'visible';
		}
		const table = flogCell?.closest('table');
		if (table) {
			table.style.overflow = 'visible';
		}

		// ⚡ DEBUG: Verificar que el contenedor esté visible
		console.log('[mostrarSugerenciasFlog] ⚡ Contenedor visible:', containerSugerenciasFlog.style.display, '| Hidden class:', containerSugerenciasFlog.classList.contains('hidden'));
		console.log('[mostrarSugerenciasFlog] ⚡ Total elementos agregados:', containerSugerenciasFlog.children.length);
		console.log('[mostrarSugerenciasFlog] ⚡ Z-index:', containerSugerenciasFlog.style.zIndex);
	}

	// Nueva función para mostrar sugerencias con descripción (usada cuando se carga desde clave modelo)
	function mostrarSugerenciasFlogConDescripcion(sugerencias) {
		mostrarSugerenciasFlog(sugerencias);
	}

	async function cargarDescripcionPorFlog(flog) {
		if (!flog || flog.trim() === '') {
			return Promise.resolve();
		}

		const flogKey = String(flog).trim();

		// ⚡ OPTIMIZACIÓN: Verificar caché primero para respuesta instantánea
		if (descripcionFlogCache.has(flogKey)) {
			const cachedData = descripcionFlogCache.get(flogKey);
			const filaPrincipal = document.querySelector('#telar-pedido-body tr#fila-principal');

			if (inputDescripcion && flog) {
				let descripcionCompleta = '';
				if (cachedData.nombreProyecto) {
					descripcionCompleta = `${cachedData.nombreProyecto} (${flog})`;
				} else {
					descripcionCompleta = `(${flog})`;
				}

				// Actualizar el input oculto
				inputDescripcion.value = descripcionCompleta;
				inputDescripcion.dispatchEvent(new Event('input', { bubbles: true }));

				// Actualizar también el textarea visible de descripción
				const descripcionTextarea = filaPrincipal ? filaPrincipal.querySelector('.descripcion-cell textarea') : null;
				if (descripcionTextarea) {
					descripcionTextarea.value = descripcionCompleta;
				}
			}

			return Promise.resolve(cachedData);
		}

		try {
			//  OPTIMIZACIÓN: Usar AbortController para cancelar si hay múltiples requests
			const controller = new AbortController();
			const timeoutId = setTimeout(() => controller.abort(), 5000); // Timeout de 5 segundos

			const response = await fetch(`/programa-tejido/descripcion-by-idflog/${encodeURIComponent(flog)}`, {
				headers: {
					'Accept': 'application/json',
					'X-CSRF-TOKEN': getCsrfToken()
				},
				signal: controller.signal
			});

			clearTimeout(timeoutId);
			const data = await response.json();

			// ⚡ OPTIMIZACIÓN: Guardar en caché para próximas búsquedas
			descripcionFlogCache.set(flogKey, data);

			// La descripción debe ser: NAMEPROYECT (IDFLOG)
			const filaPrincipal = document.querySelector('#telar-pedido-body tr#fila-principal');

			if (inputDescripcion && flog) {
				let descripcionCompleta = '';
				if (data.nombreProyecto) {
					descripcionCompleta = `${data.nombreProyecto} (${flog})`;
				} else {
					// Si no hay nombreProyecto, usar solo el flog entre paréntesis
					descripcionCompleta = `(${flog})`;
				}

				// Actualizar el input oculto
				inputDescripcion.value = descripcionCompleta;
				inputDescripcion.dispatchEvent(new Event('input', { bubbles: true }));

				// Actualizar también el textarea visible de descripción
				const descripcionTextarea = filaPrincipal ? filaPrincipal.querySelector('.descripcion-cell textarea') : null;
				if (descripcionTextarea) {
					descripcionTextarea.value = descripcionCompleta;
				}
			}

			return Promise.resolve(data);
		} catch (error) {
			if (error.name === 'AbortError') {
				console.warn('[cargarDescripcionPorFlog] Request cancelado por timeout');
			} else {
				console.error('[cargarDescripcionPorFlog] Error al cargar descripción:', error);
			}
			return Promise.reject(error);
		}
	}

	// Función para configurar autocompletadores independientes para cada fila
	// Cada fila tiene su propio autocompletador para Clave Modelo y Flog
	window.setupRowAutocompletadores = function setupRowAutocompletadores(row) {
		if (!row) return;

		const claveModeloInput = row.querySelector('.clave-modelo-cell input');
		const flogInput = row.querySelector('.flogs-cell textarea') || row.querySelector('.flogs-cell input');
		const descripcionTextarea = row.querySelector('.descripcion-cell textarea');

		if (!claveModeloInput && !flogInput) return;

		// Crear contenedor de sugerencias para Clave Modelo específico de esta fila
		let containerSugerenciasClave = row.querySelector('.clave-modelo-suggestions');
		if (!containerSugerenciasClave && claveModeloInput) {
			containerSugerenciasClave = document.createElement('div');
			containerSugerenciasClave.className = 'clave-modelo-suggestions absolute z-50 w-full bg-white border border-gray-300 rounded-t shadow-lg hidden max-h-40 overflow-y-auto';
			containerSugerenciasClave.style.bottom = '100%';
			containerSugerenciasClave.style.marginBottom = '2px';
			const claveCell = row.querySelector('.clave-modelo-cell');
			if (claveCell) {
				claveCell.style.position = 'relative';
				claveCell.appendChild(containerSugerenciasClave);
			}
		}

		// Crear contenedor de sugerencias para Flog específico de esta fila
		let containerSugerenciasFlogRow = row.querySelector('.flog-suggestions');
		if (!containerSugerenciasFlogRow && flogInput) {
			containerSugerenciasFlogRow = document.createElement('div');
			containerSugerenciasFlogRow.className = 'flog-suggestions absolute w-full bg-white border border-gray-300 rounded-t shadow-lg hidden';
			containerSugerenciasFlogRow.style.maxHeight = '500px';
			containerSugerenciasFlogRow.style.overflowY = 'auto';
			containerSugerenciasFlogRow.style.zIndex = '99999';
			containerSugerenciasFlogRow.style.bottom = '100%';
			containerSugerenciasFlogRow.style.marginBottom = '2px';
			const flogCell = row.querySelector('.flogs-cell');
			if (flogCell) {
				flogCell.style.position = 'relative';
				flogCell.style.overflow = 'visible';
				flogCell.style.zIndex = '1';
				flogCell.appendChild(containerSugerenciasFlogRow);
			}
		}

		// Configurar autocompletador para Clave Modelo de esta fila
		if (claveModeloInput && containerSugerenciasClave) {
			// Verificar que no se haya configurado ya
			if (claveModeloInput.dataset.autocompleteSetup === '1') return;
			claveModeloInput.dataset.autocompleteSetup = '1';

			let debounceTimerClave = null;
			let suppressAutocompleteClave = false;
			let clickedSuggestion = false;

			const buscarClaveModeloRow = (busqueda) => {
				if (suppressAutocompleteClave) {
					suppressAutocompleteClave = false;
					return;
				}
				const selectSalon = document.getElementById('swal-salon');
				const salonParaBuscar = selectSalon?.value || '';
				if (!salonParaBuscar || busqueda.length < 1) {
					containerSugerenciasClave.classList.add('hidden');
					return;
				}

				const params = new URLSearchParams();
				params.append('salon_tejido_id', salonParaBuscar);
				params.append('search', busqueda);

				fetch('/programa-tejido/tamano-clave-by-salon?' + params)
					.then(r => r.json())
					.then(opciones => {
						const sugerencias = Array.isArray(opciones) ? opciones : [];
						containerSugerenciasClave.innerHTML = '';
						if (sugerencias.length === 0) {
							const div = document.createElement('div');
							div.className = 'px-3 py-2 text-gray-500 text-xs italic';
							div.textContent = 'No se encontraron coincidencias';
							containerSugerenciasClave.appendChild(div);
						} else {
							sugerencias.forEach(sug => {
								const div = document.createElement('div');
								div.className = 'px-3 py-2 hover:bg-blue-100 cursor-pointer text-sm';
								div.textContent = sug;
								div.addEventListener('mousedown', (e) => {
									// Prevenir que el blur se ejecute antes del click
									e.preventDefault();
									e.stopPropagation();
									clickedSuggestion = true;
									suppressAutocompleteClave = true;
									claveModeloInput.value = sug;
									containerSugerenciasClave.classList.add('hidden');
									// Cargar datos relacionados solo para esta fila
									if (typeof window.cargarDatosRelacionadosRow === 'function') {
										window.cargarDatosRelacionadosRow(row, sug);
									}
								});
								div.addEventListener('click', (e) => {
									e.preventDefault();
									e.stopPropagation();
								});
								containerSugerenciasClave.appendChild(div);
							});
						}
						containerSugerenciasClave.classList.remove('hidden');
					})
					.catch(() => {
						containerSugerenciasClave.classList.add('hidden');
					});
			};

			claveModeloInput.addEventListener('input', (e) => {
				clearTimeout(debounceTimerClave);
				debounceTimerClave = setTimeout(() => buscarClaveModeloRow(e.target.value), 150);
			});

			claveModeloInput.addEventListener('focus', () => {
				if (claveModeloInput.value.length >= 1) {
					buscarClaveModeloRow(claveModeloInput.value);
				}
			});

			claveModeloInput.addEventListener('blur', (e) => {
				// Esperar un poco para permitir que el click en la sugerencia se ejecute primero
				setTimeout(() => {
					// Si se hizo click en una sugerencia, no hacer nada
					if (clickedSuggestion) {
						clickedSuggestion = false;
						suppressAutocompleteClave = false;
						containerSugerenciasClave.classList.add('hidden');
						return;
					}

					// Si no se hizo click en una sugerencia, cargar datos si hay valor
					const val = claveModeloInput.value?.trim();
					if (val && typeof window.cargarDatosRelacionadosRow === 'function') {
						window.cargarDatosRelacionadosRow(row, val);
					}
					suppressAutocompleteClave = false; // Resetear la bandera
					containerSugerenciasClave.classList.add('hidden');
				}, 250);
			});

			claveModeloInput.addEventListener('keydown', (e) => {
				if (e.key === 'Enter') {
					e.preventDefault();
					containerSugerenciasClave.classList.add('hidden');
					const val = claveModeloInput.value?.trim();
					if (val && typeof window.cargarDatosRelacionadosRow === 'function') {
						window.cargarDatosRelacionadosRow(row, val);
					}
				}
			});
		}

		// Configurar autocompletador para Flog de esta fila
		if (flogInput && containerSugerenciasFlogRow) {
			// Verificar que no se haya configurado ya
			if (flogInput.dataset.autocompleteSetup === '1') return;
			flogInput.dataset.autocompleteSetup = '1';

			let debounceTimerFlogRow = null;

			const cargarOpcionesFlogRow = async (search = '') => {
				try {
					// Cargar todos los flogs disponibles si no están en caché global
					let todasOpcionesFlog = [];
					if (typeof window.todasOpcionesFlogGeneral !== 'undefined' && window.todasOpcionesFlogGeneral.length > 0) {
						todasOpcionesFlog = window.todasOpcionesFlogGeneral;
					} else {
						const response = await fetch('/programa-tejido/flogs-id-from-twflogs', {
							headers: {
								'Accept': 'application/json',
								'X-CSRF-TOKEN': getCsrfToken()
							}
						});
						if (response.ok) {
							const opciones = await response.json();
							const opcionesArray = Array.isArray(opciones) ? opciones : [];
							todasOpcionesFlog = opcionesArray.filter(f => f && String(f).trim()).map(f => String(f).trim());
							window.todasOpcionesFlogGeneral = todasOpcionesFlog;
						}
					}

					if (todasOpcionesFlog.length === 0) {
						containerSugerenciasFlogRow.classList.add('hidden');
						return;
					}

					// Filtrar según búsqueda
					let sugerencias = [];
					if (search && search.length >= 1) {
						const searchLower = search.toLowerCase().trim();
						sugerencias = todasOpcionesFlog.filter(opcion => {
							const opcionStr = String(opcion || '').toLowerCase().trim();
							return opcionStr && opcionStr.includes(searchLower);
						}).map(id => ({ idflog: String(id), nombreProyecto: '' }));
					} else {
						sugerencias = todasOpcionesFlog.map(id => ({ idflog: String(id), nombreProyecto: '' }));
					}

					// Mostrar sugerencias
					containerSugerenciasFlogRow.innerHTML = '';
					if (sugerencias.length === 0) {
						const div = document.createElement('div');
						div.className = 'px-3 py-2 text-gray-500 text-xs italic';
						div.textContent = 'No se encontraron coincidencias';
						containerSugerenciasFlogRow.appendChild(div);
					} else {
						sugerencias.forEach(sug => {
							const div = document.createElement('div');
							div.className = 'px-3 py-2 hover:bg-blue-100 cursor-pointer text-sm';
							div.textContent = sug.idflog;
							div.addEventListener('click', () => {
								flogInput.value = sug.idflog;
								containerSugerenciasFlogRow.classList.add('hidden');
								// Cargar descripción solo para esta fila
								cargarDescripcionPorFlogRow(row, sug.idflog);
							});
							containerSugerenciasFlogRow.appendChild(div);
						});
					}

					// Posicionar el contenedor arriba del input
					const flogCell = row.querySelector('.flogs-cell');
					if (flogCell) {
						containerSugerenciasFlogRow.style.position = 'absolute';
						containerSugerenciasFlogRow.style.bottom = '100%';
						containerSugerenciasFlogRow.style.left = '0';
						containerSugerenciasFlogRow.style.width = '100%';
						containerSugerenciasFlogRow.style.marginBottom = '2px';
					}

					containerSugerenciasFlogRow.classList.remove('hidden');
				} catch (error) {
					console.error('[cargarOpcionesFlogRow] Error:', error);
					containerSugerenciasFlogRow.classList.add('hidden');
				}
			};

			flogInput.addEventListener('input', (e) => {
				clearTimeout(debounceTimerFlogRow);
				const valor = e.target.value.trim();
				if (valor.length >= 1) {
					debounceTimerFlogRow = setTimeout(() => cargarOpcionesFlogRow(valor), 100);
				} else {
					containerSugerenciasFlogRow.classList.add('hidden');
				}
			});

			flogInput.addEventListener('focus', async () => {
				if (flogInput.value && flogInput.value.trim().length >= 1) {
					await cargarOpcionesFlogRow(flogInput.value.trim());
				} else {
					await cargarOpcionesFlogRow('');
				}
			});

			flogInput.addEventListener('blur', (e) => {
				setTimeout(() => {
					const activeElement = document.activeElement;
					if (!containerSugerenciasFlogRow.contains(activeElement)) {
						containerSugerenciasFlogRow.classList.add('hidden');
					}
				}, 200);
				const val = flogInput.value?.trim();
				if (val) cargarDescripcionPorFlogRow(row, val);
			});

			flogInput.addEventListener('keydown', (e) => {
				if (e.key === 'Enter') {
					e.preventDefault();
					containerSugerenciasFlogRow.classList.add('hidden');
					const val = flogInput.value?.trim();
					if (val) cargarDescripcionPorFlogRow(row, val);
				}
			});
		}
	}

	// Función auxiliar para cargar datos relacionados solo para una fila específica
	window.cargarDatosRelacionadosRow = function cargarDatosRelacionadosRow(row, tamanoClave) {
		if (!row || !tamanoClave || !tamanoClave.trim()) {
			console.warn('[cargarDatosRelacionadosRow] Fila o clave modelo inválidos', { row, tamanoClave });
			return;
		}

		const selectSalon = document.getElementById('swal-salon');
		const salonParaBuscar = selectSalon?.value || '';
		if (!salonParaBuscar) {
			console.warn('[cargarDatosRelacionadosRow] No hay salón seleccionado');
			return;
		}

		const params = new URLSearchParams();
		params.append('salon_tejido_id', salonParaBuscar);
		params.append('tamano_clave', tamanoClave.trim());

		fetch('/programa-tejido/datos-relacionados?' + params.toString(), {
			method: 'GET',
			headers: { 'Accept': 'application/json' }
		})
			.then(r => {
				if (!r.ok) throw new Error(`HTTP error! status: ${r.status}`);
				return r.json();
			})
			.then(data => {
				if (data && data.datos) {
					const datos = data.datos;

					// Guardar todos los datos del modelo codificado en atributos data de la fila
					// Estos datos se usarán cuando se guarde el formulario
					if (datos.CuentaRizo !== undefined && datos.CuentaRizo !== null) row.dataset.cuentaRizo = String(datos.CuentaRizo);
					// LOG: Campos guardados en data attributes de la fila
					console.log('[cargarDatosRelacionadosRow] 💾 CAMPOS GUARDADOS EN ROW.DATASET:', {
						calibreRizo: datos.CalibreRizo,
						calibreRizo2: datos.CalibreRizo2,
						ancho: datos.Ancho,
						fibraRizo: datos.FibraRizo,
						calibrePie: datos.CalibrePie,
						calibrePie2: datos.CalibrePie2,
						calibreTrama: datos.CalibreTrama,
						calibreTrama2: datos.CalibreTrama2,
						noTiras: datos.NoTiras,
						peine: datos.Peine,
						luchaje: datos.Luchaje,
						pesoCrudo: datos.PesoCrudo,
						dobladilloId: datos.DobladilloId,
						pasadasTrama: datos.PasadasTrama,
						anchoToalla: datos.AnchoToalla,
						codColorTrama: datos.CodColorTrama,
						colorTrama: datos.ColorTrama,
						medidaPlano: datos.MedidaPlano,
						cuentaPie: datos.CuentaPie,
						rasurado: datos.Rasurado,
						velocidadSTD: datos.VelocidadSTD,
						eficienciaSTD: datos.VelocidadSTD ? 'Se obtendrá después' : null
					});

					// IMPORTANTE: Guardar TODOS los campos que deben actualizarse al cambiar la clave modelo
					if (datos.CalibreRizo !== undefined && datos.CalibreRizo !== null) row.dataset.calibreRizo = String(datos.CalibreRizo);
					if (datos.CalibreRizo2 !== undefined && datos.CalibreRizo2 !== null) row.dataset.calibreRizo2 = String(datos.CalibreRizo2);
					if (datos.Ancho !== undefined && datos.Ancho !== null) row.dataset.ancho = String(datos.Ancho);
					if (datos.FibraRizo !== undefined && datos.FibraRizo !== null) row.dataset.fibraRizo = String(datos.FibraRizo);
					if (datos.CalibrePie !== undefined && datos.CalibrePie !== null) row.dataset.calibrePie = String(datos.CalibrePie);
					if (datos.CalibrePie2 !== undefined && datos.CalibrePie2 !== null) row.dataset.calibrePie2 = String(datos.CalibrePie2);
					if (datos.CalibreTrama !== undefined && datos.CalibreTrama !== null) row.dataset.calibreTrama = String(datos.CalibreTrama);
					if (datos.CalibreTrama2 !== undefined && datos.CalibreTrama2 !== null) row.dataset.calibreTrama2 = String(datos.CalibreTrama2);
					if (datos.Rasurado !== undefined && datos.Rasurado !== null) row.dataset.rasurado = String(datos.Rasurado);
					if (datos.NoTiras !== undefined && datos.NoTiras !== null) row.dataset.noTiras = String(datos.NoTiras);
					if (datos.Peine !== undefined && datos.Peine !== null) row.dataset.peine = String(datos.Peine);
					if (datos.Luchaje !== undefined && datos.Luchaje !== null) row.dataset.luchaje = String(datos.Luchaje);
					if (datos.PesoCrudo !== undefined && datos.PesoCrudo !== null) row.dataset.pesoCrudo = String(datos.PesoCrudo);
					if (datos.CalibreTrama !== undefined && datos.CalibreTrama !== null) row.dataset.calibreTrama = String(datos.CalibreTrama);
					if (datos.CalibreTrama2 !== undefined && datos.CalibreTrama2 !== null) row.dataset.calibreTrama2 = String(datos.CalibreTrama2);
					if (datos.FibraTrama !== undefined && datos.FibraTrama !== null) row.dataset.fibraTrama = String(datos.FibraTrama);
					if (datos.DobladilloId !== undefined && datos.DobladilloId !== null) row.dataset.dobladilloId = String(datos.DobladilloId);
					if (datos.PasadasTrama !== undefined && datos.PasadasTrama !== null) row.dataset.pasadasTrama = String(datos.PasadasTrama);
					if (datos.PasadasComb1 !== undefined && datos.PasadasComb1 !== null) row.dataset.pasadasComb1 = String(datos.PasadasComb1);
					if (datos.PasadasComb2 !== undefined && datos.PasadasComb2 !== null) row.dataset.pasadasComb2 = String(datos.PasadasComb2);
					if (datos.PasadasComb3 !== undefined && datos.PasadasComb3 !== null) row.dataset.pasadasComb3 = String(datos.PasadasComb3);
					if (datos.PasadasComb4 !== undefined && datos.PasadasComb4 !== null) row.dataset.pasadasComb4 = String(datos.PasadasComb4);
					if (datos.PasadasComb5 !== undefined && datos.PasadasComb5 !== null) row.dataset.pasadasComb5 = String(datos.PasadasComb5);
					if (datos.AnchoToalla !== undefined && datos.AnchoToalla !== null) row.dataset.anchoToalla = String(datos.AnchoToalla);
					if (datos.CodColorTrama !== undefined && datos.CodColorTrama !== null) row.dataset.codColorTrama = String(datos.CodColorTrama);
					if (datos.ColorTrama !== undefined && datos.ColorTrama !== null) row.dataset.colorTrama = String(datos.ColorTrama);
					if (datos.CalibreComb1 !== undefined && datos.CalibreComb1 !== null) row.dataset.calibreComb1 = String(datos.CalibreComb1);
					if (datos.CalibreComb12 !== undefined && datos.CalibreComb12 !== null) row.dataset.calibreComb12 = String(datos.CalibreComb12);
					if (datos.FibraComb1 !== undefined && datos.FibraComb1 !== null) row.dataset.fibraComb1 = String(datos.FibraComb1);
					if (datos.CodColorComb1 !== undefined && datos.CodColorComb1 !== null) row.dataset.codColorComb1 = String(datos.CodColorComb1);
					if (datos.NombreCC1 !== undefined && datos.NombreCC1 !== null) row.dataset.nombreCC1 = String(datos.NombreCC1);
					if (datos.CalibreComb2 !== undefined && datos.CalibreComb2 !== null) row.dataset.calibreComb2 = String(datos.CalibreComb2);
					if (datos.CalibreComb22 !== undefined && datos.CalibreComb22 !== null) row.dataset.calibreComb22 = String(datos.CalibreComb22);
					if (datos.FibraComb2 !== undefined && datos.FibraComb2 !== null) row.dataset.fibraComb2 = String(datos.FibraComb2);
					if (datos.CodColorComb2 !== undefined && datos.CodColorComb2 !== null) row.dataset.codColorComb2 = String(datos.CodColorComb2);
					if (datos.NombreCC2 !== undefined && datos.NombreCC2 !== null) row.dataset.nombreCC2 = String(datos.NombreCC2);
					if (datos.CalibreComb3 !== undefined && datos.CalibreComb3 !== null) row.dataset.calibreComb3 = String(datos.CalibreComb3);
					if (datos.CalibreComb32 !== undefined && datos.CalibreComb32 !== null) row.dataset.calibreComb32 = String(datos.CalibreComb32);
					if (datos.FibraComb3 !== undefined && datos.FibraComb3 !== null) row.dataset.fibraComb3 = String(datos.FibraComb3);
					if (datos.CodColorComb3 !== undefined && datos.CodColorComb3 !== null) row.dataset.codColorComb3 = String(datos.CodColorComb3);
					if (datos.NombreCC3 !== undefined && datos.NombreCC3 !== null) row.dataset.nombreCC3 = String(datos.NombreCC3);
					if (datos.CalibreComb4 !== undefined && datos.CalibreComb4 !== null) row.dataset.calibreComb4 = String(datos.CalibreComb4);
					if (datos.CalibreComb42 !== undefined && datos.CalibreComb42 !== null) row.dataset.calibreComb42 = String(datos.CalibreComb42);
					if (datos.FibraComb4 !== undefined && datos.FibraComb4 !== null) row.dataset.fibraComb4 = String(datos.FibraComb4);
					if (datos.CodColorComb4 !== undefined && datos.CodColorComb4 !== null) row.dataset.codColorComb4 = String(datos.CodColorComb4);
					if (datos.NombreCC4 !== undefined && datos.NombreCC4 !== null) row.dataset.nombreCC4 = String(datos.NombreCC4);
					if (datos.CalibreComb5 !== undefined && datos.CalibreComb5 !== null) row.dataset.calibreComb5 = String(datos.CalibreComb5);
					if (datos.CalibreComb52 !== undefined && datos.CalibreComb52 !== null) row.dataset.calibreComb52 = String(datos.CalibreComb52);
					if (datos.FibraComb5 !== undefined && datos.FibraComb5 !== null) row.dataset.fibraComb5 = String(datos.FibraComb5);
					if (datos.CodColorComb5 !== undefined && datos.CodColorComb5 !== null) row.dataset.codColorComb5 = String(datos.CodColorComb5);
					if (datos.NombreCC5 !== undefined && datos.NombreCC5 !== null) row.dataset.nombreCC5 = String(datos.NombreCC5);
					if (datos.MedidaPlano !== undefined && datos.MedidaPlano !== null) row.dataset.medidaPlano = String(datos.MedidaPlano);
					if (datos.CuentaPie !== undefined && datos.CuentaPie !== null) row.dataset.cuentaPie = String(datos.CuentaPie);
					// CodColorCtaPie no existe en ReqModelosCodificados, se obtendrá de otra fuente si es necesario
					if (datos.VelocidadSTD !== undefined && datos.VelocidadSTD !== null) row.dataset.velocidadSTD = String(datos.VelocidadSTD);
					// Guardar InventSizeId e ItemId también
					if (datos.InventSizeId !== undefined && datos.InventSizeId !== null) {
						row.dataset.inventSizeId = String(datos.InventSizeId);
					}
					if (datos.ItemId !== undefined && datos.ItemId !== null) {
						row.dataset.itemId = String(datos.ItemId);
					}

					// Guardar CustName si viene de datos-relacionados (aunque normalmente viene del flog)
					if (datos.CustName !== undefined && datos.CustName !== null && datos.CustName !== '') {
						row.dataset.custName = String(datos.CustName);
						const inputCustnameGlobal = document.getElementById('swal-custname');
						if (inputCustnameGlobal) {
							inputCustnameGlobal.value = String(datos.CustName);
							console.log('[cargarDatosRelacionadosRow] CustName actualizado desde datos-relacionados:', datos.CustName);
						}
					}

					// Actualizar también los campos globales (swal-codArticulo y swal-inventsizeid)
					// Estos campos se usan como fallback cuando se guarda el formulario
					const inputCodArticuloGlobal = document.getElementById('swal-codArticulo');
					const inputInventSizeIdGlobal = document.getElementById('swal-inventsizeid');

					if (datos.ItemId !== undefined && datos.ItemId !== null && inputCodArticuloGlobal) {
						inputCodArticuloGlobal.value = String(datos.ItemId);
						console.log('[cargarDatosRelacionadosRow] Clave AX (ItemId) actualizado globalmente:', datos.ItemId);
					}

					if (datos.InventSizeId !== undefined && datos.InventSizeId !== null && inputInventSizeIdGlobal) {
						inputInventSizeIdGlobal.value = String(datos.InventSizeId);
						console.log('[cargarDatosRelacionadosRow] Tamaño AX (InventSizeId) actualizado globalmente:', datos.InventSizeId);
					}

					// Actualizar solo los campos visibles de esta fila
					const productoInput = row.querySelector('.producto-cell textarea') || row.querySelector('.producto-cell input');
					if (productoInput) {
						const nombreProducto = datos.Nombre || datos.NombreProducto || '';
						if (nombreProducto) {
							productoInput.value = nombreProducto;
							console.log('[cargarDatosRelacionadosRow] Producto actualizado:', nombreProducto);
						}
					}

					// Cargar flog y descripción si hay ItemId e InventSizeId
					const itemId = (datos.ItemId || '').toString().trim();
					const inventSizeId = (datos.InventSizeId || '').toString().trim();

					// LOG DETALLADO: Campos extraídos del modelo codificado
					console.log('[cargarDatosRelacionadosRow] 📋 CAMPOS EXTRAÍDOS DEL MODELO CODIFICADO:', {
						tamanoClave,
						itemId,
						inventSizeId,
						// Campos técnicos principales
						CuentaRizo: data.datos.CuentaRizo,
						CalibreRizo: data.datos.CalibreRizo,
						CalibreRizo2: data.datos.CalibreRizo2,
						FibraRizo: data.datos.FibraRizo,
						CalibrePie: data.datos.CalibrePie,
						CalibrePie2: data.datos.CalibrePie2,
						CalibreTrama: data.datos.CalibreTrama,
						CalibreTrama2: data.datos.CalibreTrama2,
						FibraTrama: data.datos.FibraTrama,
						// Campos técnicos secundarios
						NoTiras: data.datos.NoTiras,
						Peine: data.datos.Peine,
						Luchaje: data.datos.Luchaje,
						PesoCrudo: data.datos.PesoCrudo,
						DobladilloId: data.datos.DobladilloId,
						PasadasTrama: data.datos.PasadasTrama,
						AnchoToalla: data.datos.AnchoToalla,
						CodColorTrama: data.datos.CodColorTrama,
						ColorTrama: data.datos.ColorTrama,
						MedidaPlano: data.datos.MedidaPlano,
						CuentaPie: data.datos.CuentaPie,
						Rasurado: data.datos.Rasurado,
						// Campos de combinaciones
						PasadasComb1: data.datos.PasadasComb1,
						PasadasComb2: data.datos.PasadasComb2,
						PasadasComb3: data.datos.PasadasComb3,
						PasadasComb4: data.datos.PasadasComb4,
						PasadasComb5: data.datos.PasadasComb5,
						CalibreComb1: data.datos.CalibreComb1,
						CalibreComb12: data.datos.CalibreComb12,
						CalibreComb2: data.datos.CalibreComb2,
						CalibreComb22: data.datos.CalibreComb22,
						// Y otros campos importantes
						VelocidadSTD: data.datos.VelocidadSTD,
						Ancho: data.datos.Ancho,
						NombreProducto: data.datos.NombreProducto,
						FlogsId: data.datos.FlogsId,
						NombreProyecto: data.datos.NombreProyecto
					});

					if (itemId && inventSizeId) {
						const paramsFlog = new URLSearchParams();
						paramsFlog.append('item_id', itemId);
						paramsFlog.append('invent_size_id', inventSizeId);

						const urlFlog = '/programa-tejido/flog-by-item?' + paramsFlog.toString();
						console.log('[cargarDatosRelacionadosRow] Haciendo GET a flog-by-item:', urlFlog);
						console.log('[cargarDatosRelacionadosRow] Parámetros:', {
							item_id: itemId,
							invent_size_id: inventSizeId
						});

						fetch(urlFlog, {
							headers: { 'Accept': 'application/json' }
						})
							.then(rFlog => {
								if (!rFlog.ok) {
									console.error('[cargarDatosRelacionadosRow] Error HTTP al cargar flog:', rFlog.status);
									throw new Error(`HTTP error! status: ${rFlog.status}`);
								}
								return rFlog.json();
							})
							.then(info => {
								console.log('[cargarDatosRelacionadosRow] Respuesta flog-by-item completa:', JSON.stringify(info));

								// Intentar obtener el idflog de diferentes posibles propiedades
								let idflog = info?.idflog || info?.idFlog || info?.flog || info?.FlogId || info?.IDFLOG ||
												(info?.data && (info.data.idflog || info.data.idFlog || info.data.flog)) ||
												(info?.response && (info.response.idflog || info.response.idFlog || info.response.flog));

								// Convertir a string y limpiar espacios
								if (idflog != null) {
									idflog = String(idflog).trim();
								}

								// Validar que idflog no sea null, undefined, ni cadena vacía
								const idflogValido = idflog && idflog !== 'null' && idflog !== '';

								console.log('[cargarDatosRelacionadosRow] idflog extraído:', idflog);
								console.log('[cargarDatosRelacionadosRow] idflog válido:', idflogValido);

								const flogInput = row.querySelector('.flogs-cell textarea') || row.querySelector('.flogs-cell input');
								const descripcionTextarea = row.querySelector('.descripcion-cell textarea');

								if (idflogValido) {
									// Autocompletar flog y descripción desde TI_PRO
									if (flogInput) {
										flogInput.value = idflog;
										console.log('[cargarDatosRelacionadosRow] Flog actualizado en la fila:', idflog);
									} else {
										console.warn('[cargarDatosRelacionadosRow] No se encontró el input de flog en la fila');
									}

									const nombreProyecto = (info?.nombreProyecto || info?.NombreProyecto || info?.nameProyecto ||
															(info?.data && info.data.nombreProyecto) ||
															(info?.response && info.response.nombreProyecto) || '').trim();
									const custName = (info?.custName || info?.CustName || info?.custname ||
														(info?.data && info.data.custName) ||
														(info?.response && info.response.custName) || '').trim();

									// Guardar CustName en data attribute y actualizar input global
									if (custName) {
										row.dataset.custName = custName;
										const inputCustnameGlobal = document.getElementById('swal-custname');
										if (inputCustnameGlobal) {
											inputCustnameGlobal.value = custName;
											console.log('[cargarDatosRelacionadosRow] 👤 CUSTNAME GUARDADO Y ACTUALIZADO:', custName);
										}
									}

									if (descripcionTextarea) {
										if (nombreProyecto) {
											descripcionTextarea.value = `${nombreProyecto} (${idflog})`;
										} else {
											descripcionTextarea.value = `(${idflog})`;
										}
										console.log('[cargarDatosRelacionadosRow] Descripción actualizada en la fila');
									} else {
										console.warn('[cargarDatosRelacionadosRow] No se encontró el textarea de descripción en la fila');
									}

									// Obtener eficiencia y velocidad si tenemos telar, hilo y calibre trama
									console.log('[cargarDatosRelacionadosRow] Llamando a cargarEficienciaVelocidadRow y construirMaquinaRow');
									if (typeof window.cargarEficienciaVelocidadRow === 'function') {
										window.cargarEficienciaVelocidadRow(row, datos);
									} else {
										console.warn('[cargarDatosRelacionadosRow] cargarEficienciaVelocidadRow no está disponible');
									}
									// Construir Maquina basándose en salón y telar
									if (typeof window.construirMaquinaRow === 'function') {
										window.construirMaquinaRow(row);
									} else {
										console.warn('[cargarDatosRelacionadosRow] construirMaquinaRow no está disponible');
									}
								} else {
									// Si no se obtiene, dejar en blanco (el usuario puede escribir libremente)
									console.warn('[cargarDatosRelacionadosRow] No se recibió idflog válido en la respuesta. Limpiando campos.');
									if (flogInput) flogInput.value = '';
									if (descripcionTextarea) descripcionTextarea.value = '';
									// Aún intentar cargar eficiencia y velocidad si tenemos los datos necesarios
									console.log('[cargarDatosRelacionadosRow] Llamando a cargarEficienciaVelocidadRow y construirMaquinaRow (sin flog)');
									if (typeof window.cargarEficienciaVelocidadRow === 'function') {
										window.cargarEficienciaVelocidadRow(row, datos);
									}
									// Construir Maquina basándose en salón y telar
									if (typeof window.construirMaquinaRow === 'function') {
										window.construirMaquinaRow(row);
									}
								}
							})
							.catch((error) => {
								console.error('[cargarDatosRelacionadosRow] Error al cargar flog:', error);
							});
					} else {
						console.warn('[cargarDatosRelacionadosRow] No hay ItemId o InventSizeId para cargar el flog', {
							itemId,
							inventSizeId
						});
						// Si no hay ItemId o InventSizeId, limpiar flog y descripción
						const flogInput = row.querySelector('.flogs-cell textarea') || row.querySelector('.flogs-cell input');
						const descripcionTextarea = row.querySelector('.descripcion-cell textarea');
						if (flogInput) flogInput.value = '';
						if (descripcionTextarea) descripcionTextarea.value = '';

						// Aún intentar cargar eficiencia y velocidad si tenemos los datos necesarios
						console.log('[cargarDatosRelacionadosRow] Llamando a cargarEficienciaVelocidadRow y construirMaquinaRow (sin ItemId/InventSizeId)');
						if (typeof window.cargarEficienciaVelocidadRow === 'function') {
							window.cargarEficienciaVelocidadRow(row, datos);
						}
						// Construir Maquina basándose en salón y telar
						if (typeof window.construirMaquinaRow === 'function') {
							window.construirMaquinaRow(row);
						}
					}
				} else {
					console.warn('[cargarDatosRelacionadosRow] No se recibieron datos válidos:', data);
				}

				// Resumen final de todos los datos guardados en la fila
				console.log('[cargarDatosRelacionadosRow] Resumen final de datos guardados en la fila:', {
					cuentaRizo: row.dataset.cuentaRizo,
					calibreRizo: row.dataset.calibreRizo,
					calibreRizo2: row.dataset.calibreRizo2,
					ancho: row.dataset.ancho,
					fibraRizo: row.dataset.fibraRizo,
					calibrePie: row.dataset.calibrePie,
					calibrePie2: row.dataset.calibrePie2,
					rasurado: row.dataset.rasurado,
					noTiras: row.dataset.noTiras,
					peine: row.dataset.peine,
					luchaje: row.dataset.luchaje,
					pesoCrudo: row.dataset.pesoCrudo,
					calibreTrama: row.dataset.calibreTrama,
					calibreTrama2: row.dataset.calibreTrama2,
					eficienciaSTD: row.dataset.eficienciaSTD,
					velocidadSTD: row.dataset.velocidadSTD,
					maquina: row.dataset.maquina,
					itemId: row.dataset.itemId,
					inventSizeId: row.dataset.inventSizeId,
					custName: row.dataset.custName
				});
			})
			.catch((error) => {
				console.error('[cargarDatosRelacionadosRow] Error al cargar datos relacionados:', error);
			});
	}

	// Función auxiliar para cargar eficiencia y velocidad basándose en telar, hilo y calibre trama
	window.cargarEficienciaVelocidadRow = function cargarEficienciaVelocidadRow(row, datosModelo) {
		if (!row || !datosModelo) return;

		// Obtener telar de la fila - puede venir del select o del data attribute
		const telarSelect = row.querySelector('select[name="telar-destino[]"]');
		let telar = telarSelect?.value || '';

		// Limpiar el telar: extraer solo el número
		// Puede venir como "SMIT::320", "SMIT 320", "SMISMIT::320", etc.
		if (telar) {
			// Manejar formato "SALON::TELAR" o múltiples "::" (ej: "SMIT::320" o "SMISMIT::320")
			if (telar.includes('::')) {
				const parts = telar.split('::');
				// Tomar la última parte que debería ser el número del telar
				telar = parts[parts.length - 1] || telar;
			}
			// Manejar formato "SALON TELAR" (ej: "SMIT 320")
			else if (telar.includes(' ')) {
				const parsed = typeof window.parseTelarValue === 'function' ? window.parseTelarValue(telar) : null;
				if (parsed && parsed.telar) {
					telar = parsed.telar;
				} else {
					// Extraer solo el número (última parte después del espacio)
					const parts = telar.trim().split(/\s+/);
					telar = parts[parts.length - 1] || telar;
				}
			}
		}

		// Limpiar cualquier carácter no numérico al inicio (por si acaso)
		// Solo conservar números y letras al final si es necesario
		telar = telar ? String(telar).trim().replace(/^[^0-9]+/, '') : '';

		// Obtener hilo (FibraRizo) del modelo codificado o del data attribute
		// Priorizar FibraRizo sobre FibraId
		const fibraRizo = datosModelo.FibraRizo || row.dataset.fibraRizo || datosModelo.FibraId || row.dataset.fibraTrama || '';

		// Obtener calibre trama del modelo codificado o del data attribute
		const calibreTrama = datosModelo.CalibreTrama || datosModelo.CalibreTrama2 || row.dataset.calibreTrama || row.dataset.calibreTrama2 || '';

		if (!telar || !fibraRizo || !calibreTrama) {
			console.warn('[cargarEficienciaVelocidadRow] Faltan datos para obtener eficiencia y velocidad', {
				telar,
				fibraRizo,
				calibreTrama,
				datosModelo: {
					FibraRizo: datosModelo.FibraRizo,
					FibraId: datosModelo.FibraId,
					CalibreTrama: datosModelo.CalibreTrama,
					CalibreTrama2: datosModelo.CalibreTrama2
				},
				rowDataset: {
					fibraRizo: row.dataset.fibraRizo,
					fibraTrama: row.dataset.fibraTrama,
					calibreTrama: row.dataset.calibreTrama,
					calibreTrama2: row.dataset.calibreTrama2
				}
			});
			return;
		}

		const params = new URLSearchParams();
		params.append('no_telar_id', telar);
		params.append('fibra_id', fibraRizo);
		params.append('calibre_trama', calibreTrama);

		const url = '/programa-tejido/eficiencia-velocidad-std?' + params.toString();
		console.log('[cargarEficienciaVelocidadRow] Obteniendo eficiencia y velocidad:', {
			url,
			telar,
			fibraRizo,
			calibreTrama
		});

		fetch(url, {
			headers: { 'Accept': 'application/json' }
		})
			.then(r => {
				if (!r.ok) {
					console.error('[cargarEficienciaVelocidadRow] Error HTTP:', r.status, r.statusText);
					throw new Error(`HTTP error! status: ${r.status}`);
				}
				return r.json();
			})
							.then(result => {
								console.log('[cargarEficienciaVelocidadRow] 📊 EFICIENCIA Y VELOCIDAD OBTENIDAS:', {
									eficiencia: result.eficiencia,
									velocidad: result.velocidad,
									fibraRizo: fibraRizo,
									calibreTrama: calibreTrama,
									telar: telar
								});

								if (result.eficiencia !== null && result.eficiencia !== undefined) {
									row.dataset.eficienciaSTD = String(result.eficiencia);
					console.log('[cargarEficienciaVelocidadRow] Eficiencia guardada:', result.eficiencia);
				} else {
					console.warn('[cargarEficienciaVelocidadRow] No se obtuvo eficiencia en la respuesta');
				}
				if (result.velocidad !== null && result.velocidad !== undefined) {
					row.dataset.velocidadSTD = String(result.velocidad);
					console.log('[cargarEficienciaVelocidadRow] Velocidad guardada:', result.velocidad);
				} else {
					console.warn('[cargarEficienciaVelocidadRow] No se obtuvo velocidad en la respuesta');
				}
				console.log('[cargarEficienciaVelocidadRow] Eficiencia y velocidad cargadas:', {
					eficiencia: result.eficiencia,
					velocidad: result.velocidad,
					telar,
					fibraRizo,
					calibreTrama,
					densidad: result.densidad,
					error: result.error
				});
			})
			.catch((error) => {
				console.error('[cargarEficienciaVelocidadRow] Error al cargar eficiencia y velocidad:', error);
			});
	}

	// Función auxiliar para construir Maquina basándose en salón y telar
	window.construirMaquinaRow = function construirMaquinaRow(row) {
		if (!row) return '';

		const selectSalon = document.getElementById('swal-salon');
		let salon = selectSalon?.value || '';
		const telarSelect = row.querySelector('select[name="telar-destino[]"]');
		let telar = telarSelect?.value || '';

		// Si el telar viene con formato "SALON TELAR", extraer salón y telar
		if (telar && telar.includes(' ') && typeof window.parseTelarValue === 'function') {
			const parsed = window.parseTelarValue(telar);
			if (parsed.salon) salon = parsed.salon;
			if (parsed.telar) telar = parsed.telar;
		}

		// Si aún no tenemos salón, intentar obtenerlo del hidden input de la fila
		if (!salon) {
			const salonInputFila = row.querySelector('input[name="salon-destino[]"]');
			salon = salonInputFila?.value || '';
		}

		if (!salon || !telar) return '';

		// Extraer solo el número del telar si viene con formato "SALON TELAR"
		const telarNumero = telar.split(' ').pop() || telar;

		// Determinar prefijo basándose en el salón
		let prefijo = '';
		const salonUpper = salon.toUpperCase();
		if (salonUpper.includes('SMIT') || salonUpper.includes('SMI')) {
			prefijo = 'SMI';
		} else if (salonUpper.includes('JAC')) {
			prefijo = 'JAC';
		} else {
			prefijo = salonUpper.substring(0, 3);
		}

		const maquina = `${prefijo}${telarNumero}`;
		row.dataset.maquina = maquina;
		console.log('[construirMaquinaRow] Maquina construida:', maquina, { salon, telar, telarNumero, prefijo });
		return maquina;
	}

	// Función auxiliar para cargar descripción por flog solo para una fila específica
	window.cargarDescripcionPorFlogRow = async function cargarDescripcionPorFlogRow(row, flog) {
		if (!flog || flog.trim() === '' || !row) return;

		try {
			const response = await fetch(`/programa-tejido/descripcion-by-idflog/${encodeURIComponent(flog)}`, {
				headers: {
					'Accept': 'application/json',
					'X-CSRF-TOKEN': getCsrfToken()
				}
			});

			const data = await response.json();
			const descripcionTextarea = row.querySelector('.descripcion-cell textarea');
			if (descripcionTextarea && flog) {
				let descripcionCompleta = '';
				if (data.nombreProyecto) {
					descripcionCompleta = `${data.nombreProyecto} (${flog})`;
				} else {
					descripcionCompleta = `(${flog})`;
				}
				descripcionTextarea.value = descripcionCompleta;
			}
		} catch (error) {
			console.error('[cargarDescripcionPorFlogRow] Error:', error);
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

	async function validarClaveModeloEnSalon(salon, claveModelo) {
		if (!salon || !claveModelo) {
			ocultarAlertaClaveModelo();
			return;
		}

		const existeEnSalon = await existeClaveEnSalon(salon, claveModelo);
		if (existeEnSalon) {
			ocultarAlertaClaveModelo();
			// Cargar datos relacionados solo para la fila principal
			const filaPrincipal = document.querySelector('#telar-pedido-body tr#fila-principal');
			if (filaPrincipal && typeof cargarDatosRelacionadosRow === 'function') {
				cargarDatosRelacionadosRow(filaPrincipal, claveModelo);
			} else {
				cargarDatosRelacionados(claveModelo);
			}
			return;
		}

		const candidatos = (salonesDisponibles || []).filter(esSalonJacquardOSmit).filter(s => s !== salon);
		const checks = await Promise.all(candidatos.map(s => existeClaveEnSalon(s, claveModelo)));
		const salonesMatch = candidatos.filter((s, idx) => checks[idx]);
		if (salonesMatch.length > 0) {
			ocultarAlertaClaveModelo();
			actualizarTelaresPorClaveModelo(claveModelo);
			return;
		}

		mostrarAlertaClaveModelo(`La clave modelo "${claveModelo}" no se encuentra en los codificados de Jacquard o SMIT.`);
		inputClaveModelo.value = '';
		inputCodArticulo.value = '';
		inputProducto.value = '';
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
		{
			const baseSalon = selectSalon.value || salonActualLocal || salonActual;
			const lista = Array.isArray(dataTelares) ? dataTelares : [];
			telaresDisponibles = lista.map(t => ({
				salon: baseSalon,
				telar: t,
				value: buildTelarValue(baseSalon, t),
				label: t // Solo mostrar el número del telar, sin el salón
			}));
			window.telaresDisponibles = telaresDisponibles; // Actualizar global
		}
		if (typeof actualizarSelectsTelares === 'function') {
			actualizarSelectsTelares(true);
		}

		// Procesar aplicaciones
		if (dataAplicaciones && (Array.isArray(dataAplicaciones) ? dataAplicaciones.length > 0 : true)) {
			const aplicacionesArray = Array.isArray(dataAplicaciones) ? dataAplicaciones : [];
			window.aplicacionesDisponibles = aplicacionesArray;
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
				selectAplicacion.appendChild(optionNA);
			}
			// Solo seleccionar la aplicación original si existe y está disponible
			if (aplicacionOriginal && !selectAplicacion.value) {
				const optOriginal = Array.from(selectAplicacion.options).find(o => o.value === aplicacionOriginal);
				if (optOriginal) {
					optOriginal.selected = true;
				}
			}
			// NO forzar selección automática de "NA" u otra opción
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

		// ⚡ OPTIMIZACIÓN: Cargar datos iniciales en paralelo si existen valores
		// (Después de que salones, hilos, telares y aplicaciones ya estén cargados)
		const claveModeloInicial = inputClaveModelo?.value?.trim() || '';
		const flogInicial = inputFlog?.value?.trim() || '';
		const salonInicial = selectSalon?.value || salonActualLocal || salonActual || '';

		// Array de promesas para cargar en paralelo
		const promesasCargaInicial = [];

		// 1. Cargar datos relacionados de la clave modelo (si existe) y luego buscar flog en TI_PRO
		if (claveModeloInicial && salonInicial) {
			const paramsDatos = new URLSearchParams();
			paramsDatos.append('salon_tejido_id', salonInicial);
			paramsDatos.append('tamano_clave', claveModeloInicial);

			promesasCargaInicial.push(
				fetch('/programa-tejido/datos-relacionados?' + paramsDatos.toString(), {
					method: 'GET',
					headers: { 'Accept': 'application/json' }
				})
					.then(r => r.json())
					.then(data => {
						if (data.datos) {
							const itemId = data.datos.ItemId || '';
							const inventSizeId = data.datos.InventSizeId || '';

							// Llenar campos básicos
							if (inputCodArticulo && !inputCodArticulo.value) inputCodArticulo.value = itemId;
							if (inputProducto && !inputProducto.value) inputProducto.value = data.datos.Nombre || data.datos.NombreProducto || '';
							if (inputCustname && !inputCustname.value) inputCustname.value = data.datos.CustName || '';
							if (inputInventSizeId && !inputInventSizeId.value) inputInventSizeId.value = inventSizeId;

							// ⚡ BUSCAR FLOG DIRECTAMENTE EN TI_PRO usando ItemId e InventSizeId
							if (itemId && inventSizeId) {

								const paramsFlog = new URLSearchParams();
								paramsFlog.append('item_id', itemId);
								paramsFlog.append('invent_size_id', inventSizeId);

								return fetch('/programa-tejido/flog-by-item?' + paramsFlog.toString(), {
									headers: { 'Accept': 'application/json' }
								})
									.then(r => r.json())
									.then(flogData => {

										// Autocompletar flog y descripción desde TI_PRO
										// Si no se obtiene, dejar en blanco (el usuario puede escribir libremente)
										if (flogData?.idflog) {
											if (inputFlog) inputFlog.value = flogData.idflog;
										} else {
											if (inputFlog) inputFlog.value = '';
										}

										// La descripción debe ser: NAMEPROYECT (IDFLOG)
										if (flogData?.nombreProyecto && flogData?.idflog) {
											const descripcionCompleta = `${flogData.nombreProyecto} (${flogData.idflog})`;
											if (inputDescripcion) {
												inputDescripcion.value = descripcionCompleta;
												inputDescripcion.dispatchEvent(new Event('input', { bubbles: true }));
											}
										} else if (flogData?.nombreProyecto) {
											// Si solo hay nombreProyecto sin idflog, usar solo el nombre
											if (inputDescripcion) {
												inputDescripcion.value = flogData.nombreProyecto;
												inputDescripcion.dispatchEvent(new Event('input', { bubbles: true }));
											}
										} else {
											if (inputDescripcion) inputDescripcion.value = '';
										}

										return { datos: data.datos, flog: flogData };
									})
									.catch(err => {
										console.error('[initModalDuplicar] Error cargando flog desde TI_PRO:', err);
										// Si hay error, dejar en blanco para que el usuario pueda escribir libremente
										if (inputFlog) inputFlog.value = '';
										if (inputDescripcion) inputDescripcion.value = '';
										return { datos: data.datos, flog: null };
									});
							} else {
								// Si no hay itemId o inventSizeId, dejar en blanco
								if (inputFlog) inputFlog.value = '';
								if (inputDescripcion) inputDescripcion.value = '';
							}

							return { datos: data.datos, flog: null };
						}
						return null;
					})
					.catch(err => {
						console.error('[initModalDuplicar] Error cargando datos relacionados:', err);
						return null;
					})
			);
		}

		// 3. Cargar telares relacionados con la clave modelo (si existe)
		// IMPORTANTE: Esto se hace DESPUÉS de que salonesDisponibles esté cargado
		if (claveModeloInicial && salonesDisponibles && salonesDisponibles.length > 0) {
			promesasCargaInicial.push(
				Promise.resolve(actualizarTelaresPorClaveModelo(claveModeloInicial))
					.catch(err => {
						console.error('[initModalDuplicar] Error cargando telares relacionados:', err);
					})
			);
		}

		// 4. Cargar todos los flogs generales (para busqueda libre) solo si no hay cache
		if (todasOpcionesFlogGeneral.length === 0) {
			promesasCargaInicial.push(
				fetch('/programa-tejido/flogs-id-from-twflogs', {
					headers: {
						'Accept': 'application/json',
						'X-CSRF-TOKEN': getCsrfToken()
					}
				})
					.then(r => r.json())
					.then(data => {
						if (Array.isArray(data)) {
							todasOpcionesFlogGeneral = data.filter(f => f && String(f).trim()).map(f => String(f).trim());
						} else {
							todasOpcionesFlogGeneral = [];
						}
						return data;
					})
					.catch(err => {
						console.error('[initModalDuplicar] Error cargando flogs generales:', err);
						todasOpcionesFlogGeneral = [];
						return [];
					})
			);
		}

		// 5. Si hay flog inicial, cargar su descripción
		if (flogInicial && inputDescripcion && !inputDescripcion.value) {
			promesasCargaInicial.push(
				fetch(`/programa-tejido/descripcion-by-idflog/${encodeURIComponent(flogInicial)}`, {
					headers: {
						'Accept': 'application/json',
						'X-CSRF-TOKEN': getCsrfToken()
					}
				})
					.then(r => r.json())
					.then(data => {
						// La descripción debe ser: NAMEPROYECT (IDFLOG)
						if (inputDescripcion && flogInicial) {
							if (data?.nombreProyecto) {
								const descripcionCompleta = `${data.nombreProyecto} (${flogInicial})`;
								inputDescripcion.value = descripcionCompleta;
							} else {
								// Si no hay nombreProyecto, usar solo el flogInicial entre paréntesis
								inputDescripcion.value = `(${flogInicial})`;
							}
							inputDescripcion.dispatchEvent(new Event('input', { bubbles: true }));
						}
						return data;
					})
					.catch(err => {
						return null;
					})
			);
		}

		// Ejecutar todas las cargas en paralelo
		if (promesasCargaInicial.length > 0) {
			Promise.all(promesasCargaInicial)
				.catch(err => {
					console.error('[initModalDuplicar] Error en cargas iniciales:', err);
				});
		}
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

	// Inicializar el contenedor de sugerencias de flogs
	if (containerSugerenciasFlog && inputFlog) {
		// Asegurar que el contenedor tenga las clases CSS correctas
		containerSugerenciasFlog.className = 'absolute bg-white border border-gray-300 rounded-b shadow-lg hidden';
		containerSugerenciasFlog.style.maxHeight = '500px';
		containerSugerenciasFlog.style.overflowY = 'auto';
		containerSugerenciasFlog.style.zIndex = '99999'; // ⚡ Z-index muy alto para estar por encima de todo (incluido SweetAlert)

		// ⚡ FIX: Buscar la celda de flogs en la fila principal de la tabla
		const filaPrincipal = document.querySelector('#telar-pedido-body tr#fila-principal');
		const flogCell = filaPrincipal ? filaPrincipal.querySelector('.flogs-cell') : null;

		if (flogCell) {
			// Asegurar que la celda tenga position relative y overflow visible
			flogCell.style.position = 'relative';
			flogCell.style.overflow = 'visible';

			if (!flogCell.contains(containerSugerenciasFlog)) {
				flogCell.appendChild(containerSugerenciasFlog);
			}

			// Configurar estilos iniciales del contenedor
			containerSugerenciasFlog.style.position = 'absolute';
			containerSugerenciasFlog.style.zIndex = '99999'; // ⚡ Aumentado para estar por encima de todo
			containerSugerenciasFlog.style.display = 'none'; // Oculto inicialmente
		}
	}

	if (inputFlog && containerSugerenciasFlog) {

		// Event listener para cuando el usuario escribe (búsqueda libre)
		inputFlog.addEventListener('input', (e) => {
			clearTimeout(debounceTimerFlog);
			const valor = e.target.value.trim();

			if (valor.length >= 1) {
				// Reducir debounce para que aparezcan más rápido las sugerencias
				debounceTimerFlog = setTimeout(() => {
					cargarOpcionesFlog(valor);
				}, 100); // Reducido a 100ms para respuesta más rápida
			} else {
				containerSugerenciasFlog.classList.add('hidden');
			}
		});

		inputFlog.addEventListener('focus', async () => {
			// SIEMPRE cargar todos los flogs disponibles (búsqueda libre)
			// Si hay flogs desde clave modelo, se mostrarán primero, pero también se mostrarán todos los demás
			await cargarOpcionesFlog('');
		});

		inputFlog.addEventListener('blur', (e) => {
			// ⚡ FIX: Verificar si el click fue en el contenedor de sugerencias antes de ocultar
			setTimeout(() => {
				// Solo ocultar si el nuevo elemento activo no está dentro del contenedor de sugerencias
				const activeElement = document.activeElement;
				if (!containerSugerenciasFlog.contains(activeElement)) {
					containerSugerenciasFlog.classList.add('hidden');
					containerSugerenciasFlog.style.display = 'none';
				}
			}, 200);
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

	} else {
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
			// ⚡ FIX: Mostrar columna de acciones después de agregar una fila
			const thAcciones = document.getElementById('th-acciones');
			if (thAcciones) thAcciones.classList.remove('hidden');
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

	// Función para actualizar las columnas de información en todas las filas
	// NOTA: Esta función ya no se usa porque cada fila es independiente
	function actualizarColumnasInformacion() {
		// Esta función se mantiene por compatibilidad pero ya no hace nada
		// Cada fila ahora maneja sus propios valores independientemente
	}

	// NOTA: Ya no sincronizamos descripción desde la tabla hacia el input oculto
	// Cada fila es independiente y maneja sus propios valores
	function bindDescripcionEditableInput() {
		// Esta función se mantiene por compatibilidad pero ya no hace nada
		// Cada fila ahora maneja su propia descripción de forma independiente
	}

	function bindClaveModeloEditableInput() {
		// Configurar autocompletadores independientes para la fila principal
		const filaPrincipal = document.querySelector('#telar-pedido-body tr#fila-principal');
		if (filaPrincipal && getModoActual() === 'duplicar') {
			setupRowAutocompletadores(filaPrincipal);
		}
	}

	function bindFlogEditableInput() {
		// Configurar autocompletadores independientes para la fila principal
		// La función setupRowAutocompletadores ya maneja el autocompletado de Flog
		const filaPrincipal = document.querySelector('#telar-pedido-body tr#fila-principal');
		if (filaPrincipal && getModoActual() === 'duplicar') {
			setupRowAutocompletadores(filaPrincipal);
		}
	}

	// NOTA: Ya no sincronizamos desde los inputs globales a las filas
	// Cada fila es independiente y maneja sus propios valores
	// Los listeners globales se eliminaron para permitir que cada fila funcione de forma independiente

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

		// Event listener para cambios en selects de aplicación
		document.addEventListener('change', (event) => {
			if (event.target.matches('select[name="aplicacion-destino[]"]')) {
				const fila = event.target.closest('tr');
				if (fila) {
					fila.dataset.aplicacionSeleccionada = event.target.value;
				}
			}
		});
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
	const aplicacion = document.getElementById('swal-aplicacion')?.value || '';
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

	// Capturar múltiples filas de telar/pedido-tempo/observaciones/pedido/porcentaje_segundos/aplicacion
	// Nota: en modo dividir, el primer telar es un input readonly, no un select
	const telarInputs = document.querySelectorAll('[name="telar-destino[]"]'); // Captura tanto select como input
	const pedidoTempoInputs = document.querySelectorAll('input[name="pedido-tempo-destino[]"]');
	const pedidoInputs = document.querySelectorAll('input[name="pedido-destino[]"]');
	const observacionesInputs = document.querySelectorAll('textarea[name="observaciones-destino[]"]');
	const porcentajeSegundosInputs = document.querySelectorAll('input[name="porcentaje-segundos-destino[]"]');
	const saldoInputs = document.querySelectorAll('input[name="saldo-destino[]"]');
	const aplicacionInputs = document.querySelectorAll('select[name="aplicacion-destino[]"]');
	const filas = document.querySelectorAll('#telar-pedido-body tr');
	const destinos = [];
	const esDuplicar = modo === 'duplicar';

	telarInputs.forEach((input, idx) => {
		const telarRaw = input.value.trim();
		const parsedTelar = parseTelarValue(telarRaw);
		const telarVal = parsedTelar.telar || telarRaw;
		const fila = filas[idx];
		const salonInputFila = fila?.querySelector('input[name="salon-destino[]"]');
		const salonVal = (salonInputFila?.value || parsedTelar.salon || salon || '').trim();
		const pedidoTempoVal = pedidoTempoInputs[idx]?.value.trim() || null;
		const pedidoVal = pedidoInputs[idx]?.value.trim() || '';
		const observacionesVal = observacionesInputs[idx]?.value.trim() || null;
		const porcentajeSegundosVal = porcentajeSegundosInputs[idx]?.value.trim() || null;
		const saldoVal = saldoInputs[idx]?.value.trim() || '';
		// Obtener aplicación de la fila actual directamente
		const aplicacionSelect = fila?.querySelector('select[name="aplicacion-destino[]"]');
		const aplicacionValRaw = aplicacionSelect?.value?.trim() || '';
		const aplicacionVal = aplicacionValRaw !== '' ? aplicacionValRaw : null;
		const registroId = input.dataset?.registroId || pedidoInputs[idx]?.dataset?.registroId || '';
		const esExistente = fila?.dataset?.esExistente === 'true';
		const esNuevo = fila?.dataset?.esNuevo === 'true';

		// Obtener los valores específicos de cada fila para Clave Modelo, Producto, Flog y Descripción
		const claveModeloInput = fila?.querySelector('.clave-modelo-cell input');
		const productoTextarea = fila?.querySelector('.producto-cell textarea') || fila?.querySelector('.producto-cell input');
		const flogTextarea = fila?.querySelector('.flogs-cell textarea') || fila?.querySelector('.flogs-cell input');
		const descripcionTextarea = fila?.querySelector('.descripcion-cell textarea');

		// Leer valores actuales de los inputs (sin fallback a global)
		const claveModeloFila = (claveModeloInput?.value || '').trim();
		const productoFila = (productoTextarea?.value || '').trim();
		const flogFila = (flogTextarea?.value || '').trim();
		const descripcionFila = (descripcionTextarea?.value || '').trim();
		const aplicacionFila = (aplicacionInputs[idx]?.value || '').trim();

		// Leer todos los campos guardados en data attributes de la fila
		// IMPORTANTE: Los valores vacíos ('') se convierten a undefined para que el backend sepa que no hay valor
		// LOG: Datos que se van a enviar al backend para guardar
		// Guardar valores actuales en data attributes de la fila para persistencia
		if (fila) {
			fila.dataset.claveModelo = claveModeloFila;
			fila.dataset.producto = productoFila;
			fila.dataset.flog = flogFila;
			fila.dataset.descripcion = descripcionFila;
			fila.dataset.aplicacion = aplicacionFila;
		}

		// IMPORTANTE: Solo enviar campos DEL MODELO, NO campos del usuario como FibraRizo o Aplicacion
		// FibraRizo viene del input de hilo del modal, AplicacionId viene del select
		const datosFila = {
			cuentaRizo: fila?.dataset.cuentaRizo && fila.dataset.cuentaRizo !== '' ? fila.dataset.cuentaRizo : undefined,
			calibreRizo: fila?.dataset.calibreRizo && fila.dataset.calibreRizo !== '' ? fila.dataset.calibreRizo : undefined,
			calibreRizo2: fila?.dataset.calibreRizo2 && fila.dataset.calibreRizo2 !== '' ? fila.dataset.calibreRizo2 : undefined,
			ancho: fila?.dataset.ancho && fila.dataset.ancho !== '' ? fila.dataset.ancho : undefined,
			// fibraRizo NO se incluye aquí - viene del input de hilo del modal
			calibrePie: fila?.dataset.calibrePie && fila.dataset.calibrePie !== '' ? fila.dataset.calibrePie : undefined,
			calibrePie2: fila?.dataset.calibrePie2 && fila.dataset.calibrePie2 !== '' ? fila.dataset.calibrePie2 : undefined,
			rasurado: fila?.dataset.rasurado && fila.dataset.rasurado !== '' ? fila.dataset.rasurado : undefined,
			noTiras: fila?.dataset.noTiras && fila.dataset.noTiras !== '' ? fila.dataset.noTiras : undefined,
			peine: fila?.dataset.peine && fila.dataset.peine !== '' ? fila.dataset.peine : undefined,
			luchaje: fila?.dataset.luchaje && fila.dataset.luchaje !== '' ? fila.dataset.luchaje : undefined,
			pesoCrudo: fila?.dataset.pesoCrudo && fila.dataset.pesoCrudo !== '' ? fila.dataset.pesoCrudo : undefined,
			calibreTrama: fila?.dataset.calibreTrama && fila.dataset.calibreTrama !== '' ? fila.dataset.calibreTrama : undefined,
			calibreTrama2: fila?.dataset.calibreTrama2 && fila.dataset.calibreTrama2 !== '' ? fila.dataset.calibreTrama2 : undefined,
			fibraTrama: fila?.dataset.fibraTrama && fila.dataset.fibraTrama !== '' ? fila.dataset.fibraTrama : undefined,
			dobladilloId: fila?.dataset.dobladilloId && fila.dataset.dobladilloId !== '' ? fila.dataset.dobladilloId : undefined,
			pasadasTrama: fila?.dataset.pasadasTrama && fila.dataset.pasadasTrama !== '' ? fila.dataset.pasadasTrama : undefined,
			pasadasComb1: fila?.dataset.pasadasComb1 && fila.dataset.pasadasComb1 !== '' ? fila.dataset.pasadasComb1 : undefined,
			pasadasComb2: fila?.dataset.pasadasComb2 && fila.dataset.pasadasComb2 !== '' ? fila.dataset.pasadasComb2 : undefined,
			pasadasComb3: fila?.dataset.pasadasComb3 && fila.dataset.pasadasComb3 !== '' ? fila.dataset.pasadasComb3 : undefined,
			pasadasComb4: fila?.dataset.pasadasComb4 && fila.dataset.pasadasComb4 !== '' ? fila.dataset.pasadasComb4 : undefined,
			pasadasComb5: fila?.dataset.pasadasComb5 && fila.dataset.pasadasComb5 !== '' ? fila.dataset.pasadasComb5 : undefined,
			anchoToalla: fila?.dataset.anchoToalla && fila.dataset.anchoToalla !== '' ? fila.dataset.anchoToalla : undefined,
			codColorTrama: fila?.dataset.codColorTrama && fila.dataset.codColorTrama !== '' ? fila.dataset.codColorTrama : undefined,
			colorTrama: fila?.dataset.colorTrama && fila.dataset.colorTrama !== '' ? fila.dataset.colorTrama : undefined,
			calibreComb1: fila?.dataset.calibreComb1 && fila.dataset.calibreComb1 !== '' ? fila.dataset.calibreComb1 : undefined,
			calibreComb12: fila?.dataset.calibreComb12 && fila.dataset.calibreComb12 !== '' ? fila.dataset.calibreComb12 : undefined,
			fibraComb1: fila?.dataset.fibraComb1 && fila.dataset.fibraComb1 !== '' ? fila.dataset.fibraComb1 : undefined,
			codColorComb1: fila?.dataset.codColorComb1 && fila.dataset.codColorComb1 !== '' ? fila.dataset.codColorComb1 : undefined,
			nombreCC1: fila?.dataset.nombreCC1 && fila.dataset.nombreCC1 !== '' ? fila.dataset.nombreCC1 : undefined,
			calibreComb2: fila?.dataset.calibreComb2 && fila.dataset.calibreComb2 !== '' ? fila.dataset.calibreComb2 : undefined,
			calibreComb22: fila?.dataset.calibreComb22 && fila.dataset.calibreComb22 !== '' ? fila.dataset.calibreComb22 : undefined,
			fibraComb2: fila?.dataset.fibraComb2 && fila.dataset.fibraComb2 !== '' ? fila.dataset.fibraComb2 : undefined,
			codColorComb2: fila?.dataset.codColorComb2 && fila.dataset.codColorComb2 !== '' ? fila.dataset.codColorComb2 : undefined,
			nombreCC2: fila?.dataset.nombreCC2 && fila.dataset.nombreCC2 !== '' ? fila.dataset.nombreCC2 : undefined,
			calibreComb3: fila?.dataset.calibreComb3 && fila.dataset.calibreComb3 !== '' ? fila.dataset.calibreComb3 : undefined,
			calibreComb32: fila?.dataset.calibreComb32 && fila.dataset.calibreComb32 !== '' ? fila.dataset.calibreComb32 : undefined,
			fibraComb3: fila?.dataset.fibraComb3 && fila.dataset.fibraComb3 !== '' ? fila.dataset.fibraComb3 : undefined,
			codColorComb3: fila?.dataset.codColorComb3 && fila.dataset.codColorComb3 !== '' ? fila.dataset.codColorComb3 : undefined,
			nombreCC3: fila?.dataset.nombreCC3 && fila.dataset.nombreCC3 !== '' ? fila.dataset.nombreCC3 : undefined,
			calibreComb4: fila?.dataset.calibreComb4 && fila.dataset.calibreComb4 !== '' ? fila.dataset.calibreComb4 : undefined,
			calibreComb42: fila?.dataset.calibreComb42 && fila.dataset.calibreComb42 !== '' ? fila.dataset.calibreComb42 : undefined,
			fibraComb4: fila?.dataset.fibraComb4 && fila.dataset.fibraComb4 !== '' ? fila.dataset.fibraComb4 : undefined,
			codColorComb4: fila?.dataset.codColorComb4 && fila.dataset.codColorComb4 !== '' ? fila.dataset.codColorComb4 : undefined,
			nombreCC4: fila?.dataset.nombreCC4 && fila.dataset.nombreCC4 !== '' ? fila.dataset.nombreCC4 : undefined,
			calibreComb5: fila?.dataset.calibreComb5 && fila.dataset.calibreComb5 !== '' ? fila.dataset.calibreComb5 : undefined,
			calibreComb52: fila?.dataset.calibreComb52 && fila.dataset.calibreComb52 !== '' ? fila.dataset.calibreComb52 : undefined,
			fibraComb5: fila?.dataset.fibraComb5 && fila.dataset.fibraComb5 !== '' ? fila.dataset.fibraComb5 : undefined,
			codColorComb5: fila?.dataset.codColorComb5 && fila.dataset.codColorComb5 !== '' ? fila.dataset.codColorComb5 : undefined,
			nombreCC5: fila?.dataset.nombreCC5 && fila.dataset.nombreCC5 !== '' ? fila.dataset.nombreCC5 : undefined,
			medidaPlano: fila?.dataset.medidaPlano && fila.dataset.medidaPlano !== '' ? fila.dataset.medidaPlano : undefined,
			cuentaPie: fila?.dataset.cuentaPie && fila.dataset.cuentaPie !== '' ? fila.dataset.cuentaPie : undefined,
			codColorCtaPie: fila?.dataset.codColorCtaPie && fila.dataset.codColorCtaPie !== '' ? fila.dataset.codColorCtaPie : undefined,
			eficienciaSTD: fila?.dataset.eficienciaSTD && fila.dataset.eficienciaSTD !== '' ? fila.dataset.eficienciaSTD : undefined,
			velocidadSTD: fila?.dataset.velocidadSTD && fila.dataset.velocidadSTD !== '' ? fila.dataset.velocidadSTD : undefined,
			maquina: fila?.dataset.maquina && fila.dataset.maquina !== '' ? fila.dataset.maquina : undefined,
			custName: fila?.dataset.custName && fila.dataset.custName !== '' ? fila.dataset.custName : undefined
		};

		if (telarVal || pedidoVal || saldoVal) {
			// LOG: Datos que se van a enviar al backend para esta fila
			console.log('[validarYCapturarDatosDuplicar] 📤 DATOS ENVIADOS AL BACKEND PARA FILA:', {
				telar: telarVal,
				salon: salonVal,
				pedidoTempo: pedidoTempoVal,
				pedido: pedidoVal,
				saldo: saldoVal,
				aplicacion: aplicacionVal,
				camposTecnicos: {
					cuentaRizo: datosFila.cuentaRizo,
					calibreRizo: datosFila.calibreRizo,
					fibraRizo: datosFila.fibraRizo,
					noTiras: datosFila.noTiras,
					peine: datosFila.peine,
					luchaje: datosFila.luchaje,
					pesoCrudo: datosFila.pesoCrudo,
					tipoPedido: 'Se determinará en backend'
				},
				datosFilaCompletos: datosFila,
				totalCamposEnDatosFila: Object.keys(datosFila).length
			});

			// En modo duplicar/vincular:
			// - pedido (TotalPedido) = valor del pedido tempo (sin % de segundas)
			// - saldo (SaldoPedido) = valor calculado con % de segundas
			const pedidoFinal = esDuplicar ? (pedidoTempoVal || pedidoVal) : pedidoVal;
			const saldoFinal = esDuplicar ? (saldoVal || pedidoTempoVal || pedidoVal) : pedidoVal;

			// Construir Maquina si no está ya guardado
			if (!datosFila.maquina && salonVal && telarVal) {
				const telarNumero = telarVal.split(' ').pop() || telarVal;
				let prefijo = '';
				const salonUpper = salonVal.toUpperCase();
				if (salonUpper.includes('SMIT') || salonUpper.includes('SMI')) {
					prefijo = 'SMI';
				} else if (salonUpper.includes('JAC')) {
					prefijo = 'JAC';
				} else {
					prefijo = salonUpper.substring(0, 3);
				}
				datosFila.maquina = `${prefijo}${telarNumero}`;
			}

			// Obtener CustName de la fila si está disponible
			const custNameFila = fila?.dataset.custName || '';

			// IMPORTANTE: Crear objeto destino completo con TODOS los campos técnicos
			const destinoObj = {
				salon_destino: salonVal,
				telar: telarVal,
				pedido_tempo: pedidoTempoVal,
				pedido: pedidoFinal, // TotalPedido (sin % de segundas)
				saldo: saldoFinal, // SaldoPedido (con % de segundas)
				observaciones: observacionesVal,
				porcentaje_segundos: porcentajeSegundosVal ? parseFloat(porcentajeSegundosVal) : null,
				aplicacion: aplicacionVal,
				// Usar valores de la fila si existen, sino usar valores globales
				tamano_clave: claveModeloFila || claveModelo,
				producto: productoFila || producto,
				flog: flogFila || flog,
				descripcion: descripcionFila || descripcion,
				custName: custNameFila || custname || '',
				registro_id: registroId,
				es_existente: esExistente,
				es_nuevo: esNuevo,
				itemId: fila?.dataset.itemId || codArticulo || '',
				inventSizeId: fila?.dataset.inventSizeId || inventSizeId || ''
			};

			// Agregar TODOS los campos técnicos del modelo usando Object.assign
			// PERO preservar el campo aplicacion que ya está asignado
			const aplicacionTemp = destinoObj.aplicacion;
			Object.assign(destinoObj, datosFila);
			// Asegurar que aplicacion siempre esté presente (incluso si es null)
			destinoObj.aplicacion = aplicacionTemp !== undefined ? aplicacionTemp : null;

			destinos.push(destinoObj);
		}
	});

	return {
		codArticulo, claveModelo, producto, hilo, pedido, flog, salon, aplicacion,
		modo, vincular, descripcion, custname, inventSizeId, destinos,
		ord_compartida_existente: ordCompartidaExistente,
		registro_id_original: registroIdOriginal
	};
}
