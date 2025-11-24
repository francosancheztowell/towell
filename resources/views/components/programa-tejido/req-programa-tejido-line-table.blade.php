<div id="reqpt-line-wrapper" class="mt-4 hidden">
    <div class="shadow rounded-md overflow-hidden">
        <!-- Tabla con altura máxima fija y scroll interno -->
        <div class="overflow-x-auto max-h-48" style="max-height: 250px; overflow-y: auto;">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-blue-500 text-white sticky top-0">
                    <tr>
                        <th class="px-2 py-1 text-left text-xs font-semibold">Fecha</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Total Piezas</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Total Kilos</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Aplicación</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Trama</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Combinación 1</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Combinación 2</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Combinación 3</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Combinación 4</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Combinación 5</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Rizo</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Pie</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Mts/Pie</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Mts/Rizo</th>

                    </tr>
                </thead>
                <tbody id="reqpt-line-body" class=" divide-y divide-gray-100 bg-white">
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
	/* Scrollbar delgado para el modal */
	#swal2-html-container .overflow-x-auto::-webkit-scrollbar,
	#swal2-html-container [style*="overflow-y: auto"]::-webkit-scrollbar {
		width: 6px;
		height: 6px;
	}

	#swal2-html-container .overflow-x-auto::-webkit-scrollbar-track,
	#swal2-html-container [style*="overflow-y: auto"]::-webkit-scrollbar-track {
		background: #f1f1f1;
		border-radius: 3px;
	}

	#swal2-html-container .overflow-x-auto::-webkit-scrollbar-thumb,
	#swal2-html-container [style*="overflow-y: auto"]::-webkit-scrollbar-thumb {
		background: #cbd5e1;
		border-radius: 3px;
	}

	#swal2-html-container .overflow-x-auto::-webkit-scrollbar-thumb:hover,
	#swal2-html-container [style*="overflow-y: auto"]::-webkit-scrollbar-thumb:hover {
		background: #989b9e;
	}

	/* Para Firefox */
	#swal2-html-container .overflow-x-auto,
	#swal2-html-container [style*="overflow-y: auto"] {
		scrollbar-width: thin;
		scrollbar-color: #cbd5e1 #f1f1f1;
	}
</style>

<script>
// Controlador para cancelar peticiones anteriores
let currentAbortController = null;
let currentRequestId = 0;

async function loadReqProgramaTejidoLines(params = {}) {
    const wrap = document.getElementById('reqpt-line-wrapper');
    const body = document.getElementById('reqpt-line-body');
    const meta = document.getElementById('reqpt-line-meta');
    if (!wrap || !body) return;

    // Cancelar petición anterior si existe
    if (currentAbortController) {
        currentAbortController.abort();
    }

    // Crear nuevo controlador de aborto
    currentAbortController = new AbortController();
    const requestId = ++currentRequestId;

    const qs = new URLSearchParams(params).toString();
    const url = '/planeacion/req-programa-tejido-line' + (qs ? ('?' + qs) : '');

    // Mostrar estado de carga
    body.innerHTML = `<tr><td colspan="14" class="px-3 py-4 text-center text-sm text-gray-500">
        <div class="flex items-center justify-center gap-2">
            <div class="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
            <span>Cargando...</span>
        </div>
    </td></tr>`;
    wrap.classList.remove('hidden');

    try {
        const r = await fetch(url, {
            headers: { 'Accept': 'application/json' },
            signal: currentAbortController.signal
        });

        // Verificar si esta petición sigue siendo la más reciente
        if (requestId !== currentRequestId) {
            return; // Ignorar respuesta si hay una petición más reciente
        }

        if (!r.ok) {
            body.innerHTML = `<tr><td colspan="14" class="px-3 py-6">
                <div class="max-w-xl mx-auto  text-blue-800 rounded-md p-4 text-sm text-center">
                    <div class="font-semibold mb-1">No se pudo cargar el detalle</div>
                    <div>Intenta nuevamente más tarde.</div>
                </div>
            </td></tr>`;
            meta && (meta.textContent = '');
            return;
        }

        const data = await r.json();

        // Verificar nuevamente si esta petición sigue siendo la más reciente
        if (requestId !== currentRequestId) {
            return; // Ignorar respuesta si hay una petición más reciente
        }

        const page = data?.data ?? data; // soporta paginate o arreglo simple
        const items = page?.data ?? page; // paginate.data o arreglo

        if (!Array.isArray(items) || items.length === 0) {
            body.innerHTML = `<tr><td colspan="14" class="px-3 py-6 text-center text-sm text-gray-500">Sin líneas registradas</td></tr>`;
            meta && (meta.textContent = '0 registros');
            return;
        }

        const rows = items.map(it => {
            const f = (v) => {
                if (v === null || v === undefined || v === '') return '';
                if (isNaN(v)) return String(v);
                const num = Number(v);
                return num.toLocaleString('en-US', {
                    minimumFractionDigits: num % 1 === 0 ? 0 : 2,
                    maximumFractionDigits: 2
                });
            };
            const fecha = it.Fecha ? new Date(it.Fecha).toLocaleDateString() : '';
            return `
                <tr class="hover:bg-blue-50">
                    <td class="px-2 py-1 text-xs">${fecha}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Cantidad)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Kilos)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Aplicacion)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Trama)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Combina1)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Combina2)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Combina3)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Combina4)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Combina5)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Rizo)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Pie)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.MtsPie)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.MtsRizo)}</td>
                </tr>`;
        }).join('');

        // Verificar una última vez antes de renderizar
        if (requestId === currentRequestId) {
            body.innerHTML = rows;
            meta && (meta.textContent = `${items.length} registro(s)`);
        }
    } catch(e) {
        // Ignorar errores de aborto
        if (e.name === 'AbortError') {
            return;
        }

        // Verificar si esta petición sigue siendo la más reciente antes de mostrar error
        if (requestId !== currentRequestId) {
            return;
        }

        body.innerHTML = `<tr><td colspan="14" class="px-3 py-6">
            <div class="max-w-xl mx-auto  text-red-700 rounded-md p-4 text-sm text-center">
                <div class="font-semibold mb-1">No se pudo cargar el detalle</div>
                <div>Por favor verifica tu conexión e inténtalo de nuevo.</div>
            </div>
        </td></tr>`;
        meta && (meta.textContent = '');
    }
}

// Exponer para que la vista principal lo invoque al seleccionar una fila
window.loadReqProgramaTejidoLines = loadReqProgramaTejidoLines;

// ===== Función para abrir modal con líneas de detalle =====
let currentLinesAbortController = null;
let currentLinesRequestId = 0;

async function openLinesModal(programaId) {
	// Cancelar petición anterior si existe
	if (currentLinesAbortController) {
		currentLinesAbortController.abort();
	}

	// Crear nuevo controlador de aborto
	currentLinesAbortController = new AbortController();
	const requestId = ++currentLinesRequestId;

	// HTML inicial con loading
	const loadingHTML = `
		<div id="lines-modal-content" class="w-full">
			<div class="flex items-center justify-center py-12">
				<div class="flex flex-col items-center gap-3">
					<div class="w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
					<span class="text-gray-700 font-medium">Cargando detalle del telar...</span>
				</div>
			</div>
		</div>
	`;

	Swal.fire({
		title: '<div class="text-lg font-bold text-gray-800">Detalle del Telar</div>',
		html: loadingHTML,
		width: '90%',
		maxWidth: '900px',
		showConfirmButton: false,
		showCancelButton: false,
		showCloseButton: true,
		closeButtonHtml: '<i class="fa-solid fa-times text-gray-400 hover:text-gray-600 text-sm transition-colors"></i>',
		allowOutsideClick: true,
		allowEscapeKey: true,
		backdrop: true,
		customClass: {
			popup: 'rounded-lg shadow-2xl',
			title: '!mb-1',
			htmlContainer: 'p-0',
			closeButton: '!top-4 !right-4 !w-8 !h-8 !flex !items-center !justify-center !text-xl hover:bg-gray-100 rounded-full transition-colors'
		},
		didOpen: async () => {
			try {
				const url = `/planeacion/req-programa-tejido-line?programa_id=${programaId}`;
				const response = await fetch(url, {
					headers: { 'Accept': 'application/json' },
					signal: currentLinesAbortController.signal
				});

				// Verificar si esta petición sigue siendo la más reciente
				if (requestId !== currentLinesRequestId) {
					return;
				}

				if (!response.ok) {
					const content = document.getElementById('lines-modal-content');
					if (content && requestId === currentLinesRequestId) {
						content.innerHTML = `
							<div class="text-center py-12">
								<div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 mb-4">
									<i class="fa-solid fa-exclamation-triangle text-red-500 text-2xl"></i>
								</div>
								<div class="text-red-600 font-semibold text-base mb-2">No se pudo cargar el detalle</div>
								<div class="text-gray-500 text-sm">Intenta nuevamente más tarde.</div>
							</div>
						`;
					}
					return;
				}

				const data = await response.json();

				// Verificar nuevamente si esta petición sigue siendo la más reciente
				if (requestId !== currentLinesRequestId) {
					return;
				}

				const page = data?.data ?? data;
				const items = page?.data ?? page;

				const content = document.getElementById('lines-modal-content');
				if (!content || requestId !== currentLinesRequestId) {
					return;
				}

				if (!Array.isArray(items) || items.length === 0) {
					content.innerHTML = `
						<div class="text-center py-12">
							<div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
								<i class="fa-solid fa-inbox text-gray-400 text-2xl"></i>
							</div>
							<div class="text-gray-600 font-medium text-base">Sin líneas registradas</div>
							<div class="text-gray-400 text-sm mt-1">No hay fechas de tejido para este registro</div>
						</div>
					`;
					return;
				}

				// Función para formatear números
				const f = (v) => {
					if (v === null || v === undefined || v === '') return '';
					if (isNaN(v)) return String(v);
					const num = Number(v);
					return num.toLocaleString('en-US', {
						minimumFractionDigits: num % 1 === 0 ? 0 : 2,
						maximumFractionDigits: 2
					});
				};

				// Calcular totales
				const totals = items.reduce((acc, it) => {
					acc.cantidad += parseFloat(it.Cantidad) || 0;
					acc.kilos += parseFloat(it.Kilos) || 0;
					acc.aplicacion += parseFloat(it.Aplicacion) || 0;
					acc.trama += parseFloat(it.Trama) || 0;
					acc.combina1 += parseFloat(it.Combina1) || 0;
					acc.combina2 += parseFloat(it.Combina2) || 0;
					acc.combina3 += parseFloat(it.Combina3) || 0;
					acc.combina4 += parseFloat(it.Combina4) || 0;
					acc.combina5 += parseFloat(it.Combina5) || 0;
					acc.rizo += parseFloat(it.Rizo) || 0;
					acc.pie += parseFloat(it.Pie) || 0;
					acc.mtsPie += parseFloat(it.MtsPie) || 0;
					acc.mtsRizo += parseFloat(it.MtsRizo) || 0;
					return acc;
				}, {
					cantidad: 0, kilos: 0, aplicacion: 0, trama: 0,
					combina1: 0, combina2: 0, combina3: 0, combina4: 0, combina5: 0,
					rizo: 0, pie: 0, mtsPie: 0, mtsRizo: 0
				});

				// Generar tabla HTML
				const tableHTML = `
					<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
						<div class="overflow-x-auto relative" style="max-height: 500px; overflow-y: auto;">
							<table class="min-w-full divide-y divide-gray-200">
								<thead class="bg-blue-500 text-white sticky top-0 z-10">
									<tr>
										<th class="px-3 py-2 text-left text-xs font-normal  tracking-wider whitespace-nowrap">Fecha</th>
										<th class="px-3 py-2 text-right text-xs font-normal  tracking-wider whitespace-nowrap">Piezas</th>
										<th class="px-3 py-2 text-right text-xs font-normal  tracking-wider whitespace-nowrap">Kilos</th>
										<th class="px-3 py-2 text-right text-xs font-normal  tracking-wider whitespace-nowrap">Aplicación</th>
										<th class="px-3 py-2 text-right text-xs font-normal  tracking-wider whitespace-nowrap">Trama</th>
										<th class="px-3 py-2 text-right text-xs font-normal  tracking-wider whitespace-nowrap">Comb 1</th>
										<th class="px-3 py-2 text-right text-xs font-normal  tracking-wider whitespace-nowrap">Comb 2</th>
										<th class="px-3 py-2 text-right text-xs font-normal  tracking-wider whitespace-nowrap">Comb 3</th>
										<th class="px-3 py-2 text-right text-xs font-normal  tracking-wider whitespace-nowrap">Comb 4</th>
										<th class="px-3 py-2 text-right text-xs font-normal  tracking-wider whitespace-nowrap">Comb 5</th>
										<th class="px-3 py-2 text-right text-xs font-normal  tracking-wider whitespace-nowrap">Rizo</th>
										<th class="px-3 py-2 text-right text-xs font-normal  tracking-wider whitespace-nowrap">Pie</th>
										<th class="px-3 py-2 text-right text-xs font-normal  tracking-wider whitespace-nowrap">Mts/Pie</th>
										<th class="px-3 py-2 text-right text-xs font-normal  tracking-wider whitespace-nowrap">Mts/Rizo</th>
									</tr>
								</thead>
								<tbody class="bg-white divide-y divide-gray-200">
									${items.map((it, idx) => {
										const fecha = it.Fecha ? new Date(it.Fecha).toLocaleDateString() : '';
										const isEven = idx % 2 === 0;
										return `
											<tr class="modal-table-row ${isEven ? 'bg-white' : 'bg-gray-50'} transition-colors cursor-pointer" data-row-index="${idx}">
												<td class="px-3 py-2 text-xs font-normal text-gray-900 whitespace-nowrap">${fecha}</td>
												<td class="px-3 py-2 text-xs font-normal text-right text-gray-700 whitespace-nowrap">${f(it.Cantidad)}</td>
												<td class="px-3 py-2 text-xs font-normal text-right text-gray-700 whitespace-nowrap">${f(it.Kilos)}</td>
												<td class="px-3 py-2 text-xs font-normal text-right text-gray-700 whitespace-nowrap">${f(it.Aplicacion)}</td>
												<td class="px-3 py-2 text-xs font-normal text-right text-gray-700 whitespace-nowrap">${f(it.Trama)}</td>
												<td class="px-3 py-2 text-xs font-normal text-right text-gray-700 whitespace-nowrap">${f(it.Combina1)}</td>
												<td class="px-3 py-2 text-xs font-normal text-right text-gray-700 whitespace-nowrap">${f(it.Combina2)}</td>
												<td class="px-3 py-2 text-xs font-normal text-right text-gray-700 whitespace-nowrap">${f(it.Combina3)}</td>
												<td class="px-3 py-2 text-xs font-normal text-right text-gray-700 whitespace-nowrap">${f(it.Combina4)}</td>
												<td class="px-3 py-2 text-xs font-normal text-right text-gray-700 whitespace-nowrap">${f(it.Combina5)}</td>
												<td class="px-3 py-2 text-xs font-normal text-right text-gray-700 whitespace-nowrap">${f(it.Rizo)}</td>
												<td class="px-3 py-2 text-xs font-normal text-right text-gray-700 whitespace-nowrap">${f(it.Pie)}</td>
												<td class="px-3 py-2 text-xs font-normal text-right text-gray-700 whitespace-nowrap">${f(it.MtsPie)}</td>
												<td class="px-3 py-2 text-xs font-normal text-right text-gray-700 whitespace-nowrap">${f(it.MtsRizo)}</td>
											</tr>
										`;
									}).join('')}
								</tbody>
								<tfoot class="bg-blue-50 border-t-2 border-blue-300 sticky bottom-0 z-10">
									<tr>
										<td class="px-3 py-2 text-xs font-semibold text-gray-900 whitespace-nowrap bg-blue-50">TOTAL</td>
										<td class="px-3 py-2 text-xs font-semibold text-right text-blue-700 whitespace-nowrap bg-blue-50">${totals.cantidad === 0 ? '&nbsp;' : f(totals.cantidad)}</td>
										<td class="px-3 py-2 text-xs font-semibold text-right text-blue-700 whitespace-nowrap bg-blue-50">${totals.kilos === 0 ? '&nbsp;' : f(totals.kilos)}</td>
										<td class="px-3 py-2 text-xs font-semibold text-right text-blue-700 whitespace-nowrap bg-blue-50">${totals.aplicacion === 0 ? '&nbsp;' : f(totals.aplicacion)}</td>
										<td class="px-3 py-2 text-xs font-semibold text-right text-blue-700 whitespace-nowrap bg-blue-50">${totals.trama === 0 ? '&nbsp;' : f(totals.trama)}</td>
										<td class="px-3 py-2 text-xs font-semibold text-right text-blue-700 whitespace-nowrap bg-blue-50">${totals.combina1 === 0 ? '&nbsp;' : f(totals.combina1)}</td>
										<td class="px-3 py-2 text-xs font-semibold text-right text-blue-700 whitespace-nowrap bg-blue-50">${totals.combina2 === 0 ? '&nbsp;' : f(totals.combina2)}</td>
										<td class="px-3 py-2 text-xs font-semibold text-right text-blue-700 whitespace-nowrap bg-blue-50">${totals.combina3 === 0 ? '&nbsp;' : f(totals.combina3)}</td>
										<td class="px-3 py-2 text-xs font-semibold text-right text-blue-700 whitespace-nowrap bg-blue-50">${totals.combina4 === 0 ? '&nbsp;' : f(totals.combina4)}</td>
										<td class="px-3 py-2 text-xs font-semibold text-right text-blue-700 whitespace-nowrap bg-blue-50">${totals.combina5 === 0 ? '&nbsp;' : f(totals.combina5)}</td>
										<td class="px-3 py-2 text-xs font-semibold text-right text-blue-700 whitespace-nowrap bg-blue-50">${totals.rizo === 0 ? '&nbsp;' : f(totals.rizo)}</td>
										<td class="px-3 py-2 text-xs font-semibold text-right text-blue-700 whitespace-nowrap bg-blue-50">${totals.pie === 0 ? '&nbsp;' : f(totals.pie)}</td>
										<td class="px-3 py-2 text-xs font-semibold text-right text-blue-700 whitespace-nowrap bg-blue-50">${totals.mtsPie === 0 ? '&nbsp;' : f(totals.mtsPie)}</td>
										<td class="px-3 py-2 text-xs font-semibold text-right text-blue-700 whitespace-nowrap bg-blue-50">${totals.mtsRizo === 0 ? '&nbsp;' : f(totals.mtsRizo)}</td>
									</tr>
								</tfoot>
							</table>
						</div>
						<div class="bg-gray-50 px-3 py-1 border-t border-gray-200">
							<span class="text-xs text-gray-500">${items.length} registro(s)</span>
						</div>
					</div>
				`;

				content.innerHTML = tableHTML;

				// Agregar funcionalidad de selección de filas
				const rows = content.querySelectorAll('.modal-table-row');
				rows.forEach(row => {
					// Hover effect
					row.addEventListener('mouseenter', function() {
						if (!this.classList.contains('bg-blue-500')) {
							this.style.backgroundColor = '#dbeafe'; // bg-blue-100
						} else {
							this.style.backgroundColor = '#1d4ed8'; // bg-blue-700
						}
					});

					row.addEventListener('mouseleave', function() {
						if (!this.classList.contains('bg-blue-500')) {
							this.style.backgroundColor = '';
						} else {
							this.style.backgroundColor = '#3b82f6'; // bg-blue-500
						}
					});

					// Click selection
					row.addEventListener('click', function() {
						// Remover selección de todas las filas
						rows.forEach(r => {
							r.classList.remove('bg-blue-500', 'text-white');
							r.classList.add(r.dataset.rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50');
							r.style.backgroundColor = '';
							const cells = r.querySelectorAll('td');
							cells.forEach(cell => {
								cell.classList.remove('text-white');
								cell.classList.add('text-gray-700', 'text-gray-900');
							});
						});

						// Seleccionar la fila clickeada
						this.classList.add('bg-blue-500', 'text-white');
						this.classList.remove('bg-white', 'bg-gray-50');
						this.style.backgroundColor = '#3b82f6'; // bg-blue-500
						const cells = this.querySelectorAll('td');
						cells.forEach(cell => {
							cell.classList.add('text-white');
							cell.classList.remove('text-gray-700', 'text-gray-900');
						});
					});
				});
			} catch(e) {
				// Ignorar errores de aborto
				if (e.name === 'AbortError') {
					return;
				}

				// Verificar si esta petición sigue siendo la más reciente antes de mostrar error
				if (requestId !== currentLinesRequestId) {
					return;
				}

				const content = document.getElementById('lines-modal-content');
				if (content) {
					content.innerHTML = `
						<div class="text-center py-12">
							<div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 mb-4">
								<i class="fa-solid fa-exclamation-triangle text-red-500 text-2xl"></i>
							</div>
							<div class="text-red-600 font-semibold text-base mb-2">No se pudo cargar el detalle</div>
							<div class="text-gray-500 text-sm">Por favor verifica tu conexión e inténtalo de nuevo.</div>
						</div>
					`;
				}
			}
		},
		willClose: () => {
			// Cancelar petición pendiente al cerrar el modal
			if (currentLinesAbortController) {
				currentLinesAbortController.abort();
				currentLinesAbortController = null;
			}
		}
	});
}

// Exponer función para que la vista principal pueda invocarla
window.openLinesModal = openLinesModal;
</script>





