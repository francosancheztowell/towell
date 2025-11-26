{{-- Modal Duplicar Registro - Componente separado --}}
{{-- NOTA: Este archivo se incluye dentro de un bloque <script>, NO agregar etiquetas <script> aquí --}}

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

	// Modal con formato de tabla
	const resultado = await Swal.fire({
		html: generarHTMLModalDuplicar({ telar, salon, codArticulo, claveModelo, producto, hilo, pedido, flog }),
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
			initModalDuplicar(telar, hilo);
		},
		preConfirm: () => {
			return validarYCapturarDatosDuplicar();
		}
	});

	if (!resultado.isConfirmed) {
		return;
	}

	const datos = resultado.value;

	showLoading();
	try {
		const response = await fetch('/planeacion/programa-tejido/duplicar-telar', {
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
				cod_articulo: datos.codArticulo,
				producto: datos.producto,
				hilo: datos.hilo,
				pedido: datos.pedido,
				flog: datos.flog,
				aplicacion: datos.aplicacion,
				modo: datos.modo,
				descripcion: datos.descripcion,
				custname: datos.custname,
				invent_size_id: datos.inventSizeId
			})
		});

		const data = await response.json();
		hideLoading();

		if (data.success) {
			showToast(data.message || 'Telar duplicado correctamente', 'success');
			setTimeout(() => {
				window.location.reload();
			}, 1000);
		} else {
			showToast(data.message || 'Error al duplicar el telar', 'error');
		}
	} catch (error) {
		hideLoading();
		showToast('Ocurrió un error al procesar la solicitud', 'error');
	}
}

// Genera el HTML del modal de duplicar
function generarHTMLModalDuplicar({ telar, salon, codArticulo, claveModelo, producto, hilo, pedido, flog }) {
	return `
		<div class="text-left">
			<div id="alerta-clave-modelo" class="hidden mb-3 px-4 py-2 bg-amber-50 border border-amber-300 rounded-md text-amber-700 text-sm">
				<i class="fas fa-exclamation-triangle mr-2"></i>
				<span id="alerta-clave-modelo-texto"></span>
			</div>
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
										<option value="">Cargando...</option>
									</select>
								</div>
								<div>
									<label class="block mb-1 text-sm font-medium text-gray-700">Pedido</label>
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
										<option value="">Cargando...</option>
									</select>
								</div>
								<div>
									<label class="block mb-1 text-sm font-medium text-gray-700">Aplicación</label>
									<select id="swal-aplicacion" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
										<option value="">Cargando...</option>
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
			<div class="my-4 flex justify-center">
				<!-- checkbox oculto solo para estado lógico -->
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

				<div id="modo-descripcion" class="hidden">
					<span id="desc-duplicar">Copia el registro al telar destino</span>
					<span id="desc-dividir" class="hidden">Divide la cantidad entre los telares</span>
				</div>
			</div>

			<!-- Tabla de Telar y Pedido -->
			<div class="border border-gray-300 rounded-lg overflow-hidden">
				<table class="w-full border-collapse">
					<thead class="bg-gray-100">
						<tr>
							<th class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-r border-gray-300 w-1/2">Telar</th>
							<th class="py-2 px-3 text-sm font-medium text-gray-700 text-left border-b border-gray-300 w-1/2">Pedido</th>
							<th class="py-2 px-2 text-center border-b border-gray-300 w-10">
								<button type="button" id="btn-add-telar-row" class="text-green-600 hover:text-green-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Añadir fila">
									<i class="fas fa-plus-circle text-lg"></i>
								</button>
							</th>
						</tr>
					</thead>
					<tbody id="telar-pedido-body">
						<tr class="telar-row">
							<td class="p-2 border-r border-gray-200">
								<select name="telar-destino[]" class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 telar-destino-select">
									<option value="">Seleccionar...</option>
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
function initModalDuplicar(telar, hiloActualParam) {
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
		const telarSelects = document.querySelectorAll('select[name="telar-destino[]"]');
		const pedidoInputs = document.querySelectorAll('input[name="pedido-destino[]"]');

		let firstComplete = false;
		let hasAnyFilled = false;

		telarSelects.forEach((select, idx) => {
			const telarVal = select.value.trim();
			const pedidoVal = (pedidoInputs[idx]?.value || '').trim();

			if (idx === 0 && telarVal !== '' && pedidoVal !== '') {
				firstComplete = true;
			}
			if (telarVal !== '' || pedidoVal !== '') {
				hasAnyFilled = true;
			}
		});

		// El usuario debe llenar completamente el primer registro
		btnAdd.disabled = !firstComplete;
		// Debe existir al menos un registro no vacío para poder aceptar
		confirmButton.disabled = !hasAnyFilled;
	}

	// Función para actualizar todos los selects de telar en la tabla de destinos
	function actualizarSelectsTelares(preseleccionarPrimero = false) {
		const telarSelects = document.querySelectorAll('select[name="telar-destino[]"]');
		telarSelects.forEach((select, idx) => {
			const valorActual = select.value;
			// Para el primer select, preseleccionar el telar actual si se indica
			const valorPreseleccionar = (idx === 0 && preseleccionarPrimero && !valorActual) ? telarActual : valorActual;

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

		fetch('/programa-tejido/datos-relacionados', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
			},
			body: JSON.stringify({
				salon_tejido_id: salonParaBuscar,
				tamano_clave: tamanoClave
			})
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

	// Procesar resultados en paralelo
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

		selectSalon.innerHTML = '<option value="">Seleccionar...</option>';
		if (opciones.length > 0) {
			salonesDisponibles = opciones;
			opciones.forEach(item => {
				const option = document.createElement('option');
				option.value = item;
				option.textContent = item;
				if (item === salonActual) option.selected = true;
				selectSalon.appendChild(option);
			});
		} else if (salonActual) {
			const option = document.createElement('option');
			option.value = salonActual;
			option.textContent = salonActual;
			option.selected = true;
			selectSalon.appendChild(option);
		}

		// Procesar hilos
		selectHilo.innerHTML = '<option value="">Seleccionar...</option>';
		if (dataHilos?.success && dataHilos.data) {
			dataHilos.data.forEach(item => {
				const option = document.createElement('option');
				option.value = item.Hilo;
				option.textContent = item.Hilo + (item.Fibra ? ' - ' + item.Fibra : '');
				if (item.Hilo === hiloActual) option.selected = true;
				selectHilo.appendChild(option);
			});
		} else if (hiloActual) {
			const option = document.createElement('option');
			option.value = hiloActual;
			option.textContent = hiloActual;
			option.selected = true;
			selectHilo.appendChild(option);
		}

		// Procesar telares y preseleccionar el telar actual en la primera fila
		telaresDisponibles = Array.isArray(dataTelares) ? dataTelares : [];
		actualizarSelectsTelares(true); // true = preseleccionar telar actual en primera fila

		// Procesar aplicaciones
		selectAplicacion.innerHTML = '<option value="">Seleccionar...</option>';
		const aplicacionesArray = Array.isArray(dataAplicaciones) ? dataAplicaciones : [];
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

	function actualizarEstiloSwitch() {
		const esDuplicar = switchModo.checked;

		if (esDuplicar) {
			// Activo: Duplicar (azul), Dividir en modo "apagado" (blanco/gris)
			if (pillDuplicar && pillDividir) {
				// Duplicar → azul
				pillDuplicar.classList.add('bg-blue-500', 'text-white', 'opacity-100', 'shadow-md');
				pillDuplicar.classList.remove('bg-white', 'text-gray-700', 'opacity-80', 'shadow-sm');

				// Dividir → blanco/gris
				pillDividir.classList.add('bg-white', 'text-gray-700', 'opacity-80', 'shadow-sm');
				pillDividir.classList.remove('bg-green-500', 'text-white', 'opacity-100', 'shadow-md');
			}
			descDuplicar.classList.remove('hidden');
			descDividir.classList.add('hidden');

			// Botón confirmar azul
			if (confirmButton) {
				confirmButton.textContent = 'Duplicar';
				confirmButton.classList.remove('bg-green-500', 'hover:bg-green-600');
				confirmButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
			}
		} else {
			// Activo: Dividir (verde), Duplicar en modo "apagado" (blanco/gris)
			if (pillDuplicar && pillDividir) {
				// Dividir → verde
				pillDividir.classList.add('bg-green-500', 'text-white', 'opacity-100', 'shadow-md');
				pillDividir.classList.remove('bg-white', 'text-gray-700', 'opacity-80', 'shadow-sm');

				// Duplicar → blanco/gris
				pillDuplicar.classList.add('bg-white', 'text-gray-700', 'opacity-80', 'shadow-sm');
				pillDuplicar.classList.remove('bg-blue-500', 'text-white', 'opacity-100', 'shadow-md');
			}
			descDividir.classList.remove('hidden');
			descDuplicar.classList.add('hidden');

			// Botón confirmar verde
			if (confirmButton) {
				confirmButton.textContent = 'Dividir';
				confirmButton.classList.remove('bg-blue-600', 'hover:bg-blue-700');
				confirmButton.classList.add('bg-green-500', 'hover:bg-green-600');
			}
		}
	}

	if (switchModo) {
		// Click en Duplicar
		if (pillDuplicar) {
			pillDuplicar.addEventListener('click', () => {
				switchModo.checked = true;
				actualizarEstiloSwitch();
			});
		}

		// Click en Dividir
		if (pillDividir) {
			pillDividir.addEventListener('click', () => {
				switchModo.checked = false;
				actualizarEstiloSwitch();
			});
		}

		// Estado inicial
		actualizarEstiloSwitch();
	}

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
	// Modo: duplicar (true) o dividir (false)
	const switchModo = document.getElementById('switch-modo');
	const modo = switchModo?.checked ? 'duplicar' : 'dividir';
	// Datos adicionales del codificado (no mostrados en el modal)
	const descripcion = document.getElementById('swal-descripcion')?.value || '';
	const custname = document.getElementById('swal-custname')?.value || '';
	const inventSizeId = document.getElementById('swal-inventsizeid')?.value || '';

	// Capturar múltiples filas de telar/pedido
	const telarSelects = document.querySelectorAll('select[name="telar-destino[]"]');
	const pedidoInputs = document.querySelectorAll('input[name="pedido-destino[]"]');
	const destinos = [];

	telarSelects.forEach((select, idx) => {
		const telarVal = select.value.trim();
		const pedidoVal = pedidoInputs[idx]?.value.trim() || '';
		if (telarVal || pedidoVal) {
			destinos.push({ telar: telarVal, pedido: pedidoVal });
		}
	});

	return { codArticulo, claveModelo, producto, hilo, pedido, flog, salon, aplicacion, modo, descripcion, custname, inventSizeId, destinos };
}

