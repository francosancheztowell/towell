{{-- Modal Duplicar/Importar Codificación --}}
{{-- NOTA: Este archivo se incluye dentro de un bloque <script>, NO agregar etiquetas <script> aquí --}}

// ===== Funciones para Duplicar/Importar Codificación =====

let modoActualCodificacion = 'duplicar'; // 'duplicar' o 'importar'

function getModoActualCodificacion() {
    return modoActualCodificacion;
}

function toggleModoCodificacion() {
    // Alternar entre duplicar e importar
    modoActualCodificacion = modoActualCodificacion === 'duplicar' ? 'importar' : 'duplicar';
    setModoActualCodificacion(modoActualCodificacion);
}

function setModoActualCodificacion(modo) {
    modoActualCodificacion = modo;

    // Actualizar el toggle switch visual
    const track = document.getElementById('toggle-switch-track');
    const sliderText = document.getElementById('slider-text');

    if (track && sliderText) {
        if (modo === 'duplicar') {
            track.classList.add('active');
            sliderText.textContent = 'Duplicar';
        } else {
            track.classList.remove('active');
            sliderText.textContent = 'Codif.';
        }
    }

    actualizarColumnasModalCodificacion();
}

function actualizarColumnasModalCodificacion() {
    const ordenTrabajoCol = document.getElementById('columna-orden-trabajo');
    const ordenTrabajoHeader = document.getElementById('header-orden-trabajo');
    const ordenTrabajoCells = document.querySelectorAll('.celda-orden-trabajo');

    const esImportar = modoActualCodificacion === 'importar';

    if (ordenTrabajoCol) {
        ordenTrabajoCol.style.display = esImportar ? '' : 'none';
    }
    if (ordenTrabajoHeader) {
        ordenTrabajoHeader.style.display = esImportar ? '' : 'none';
    }
    ordenTrabajoCells.forEach(cell => {
        if (cell) cell.style.display = esImportar ? '' : 'none';
    });
}

async function autocompletarNombreDesdeFlogs(inputElement) {
    // Solo funciona en modo duplicar
    if (modoActualCodificacion !== 'duplicar') {
        return;
    }

    const row = inputElement.closest('tr');
    if (!row) return;

    const claveAxInput = row.querySelector('.input-clave-ax');
    const tamanoInput = row.querySelector('.input-tamano');
    const nombreInput = row.querySelector('.input-nombre');
    const idflogInput = row.querySelector('.input-idflog');
    const custnameInput = row.querySelector('.input-custname');

    if (!claveAxInput || !tamanoInput || !nombreInput) return;

    const claveAx = claveAxInput.value?.trim() || '';
    const tamano = tamanoInput.value?.trim() || '';

    // Solo hacer la búsqueda si ambos campos tienen valores
    if (!claveAx || !tamano) {
        return;
    }

    try {
        // Mostrar indicador de carga
        nombreInput.value = 'Buscando...';
        nombreInput.style.color = '#6b7280';

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const response = await fetch('/planeacion/catalogos/codificacion-modelos/flogs-data?' + new URLSearchParams({
            item_id: claveAx,
            invent_size_id: tamano
        }), {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        });

        const data = await response.json();

        if (response.ok && data.success && data.data) {
            nombreInput.value = data.data.nombre || '';
            nombreInput.style.color = '#1f2937';

            if (idflogInput) {
                idflogInput.value = data.data.idflog || '';
            }
            if (custnameInput) {
                custnameInput.value = data.data.custname || '';
            }
        } else {
            nombreInput.value = '';
            nombreInput.style.color = '#dc2626';
            nombreInput.placeholder = data.message || 'No se encontró información';

            if (idflogInput) idflogInput.value = '';
            if (custnameInput) custnameInput.value = '';
        }
    } catch (error) {
        console.error('Error al autocompletar nombre:', error);
        nombreInput.value = '';
        nombreInput.style.color = '#dc2626';
        nombreInput.placeholder = 'Error al buscar';

        if (idflogInput) idflogInput.value = '';
        if (custnameInput) custnameInput.value = '';
    }
}

async function autocompletarDesdeCatCodificados(inputElement) {
    if (modoActualCodificacion !== 'importar') {
        return;
    }

    const row = inputElement.closest('tr');
    if (!row) return;

    const ordenTrabajo = inputElement.value?.trim() || '';
    if (!ordenTrabajo) return;

    const salonSelect = row.querySelector('select[name="salon[]"]');
    const claveModInput = row.querySelector('input[name="clave_mod[]"]');
    const claveAxInput = row.querySelector('input[name="clave_ax[]"]');
    const tamanoInput = row.querySelector('input[name="tamano[]"]');
    const nombreInput = row.querySelector('input[name="nombre[]"]');

    if (nombreInput) {
        nombreInput.value = 'Buscando...';
        nombreInput.style.color = '#6b7280';
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const response = await fetch('/planeacion/catalogos/codificacion-modelos/catcodificados-orden?' + new URLSearchParams({
            orden_trabajo: ordenTrabajo
        }), {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        });

        const data = await response.json();

        if (response.ok && data.success && data.data) {
            if (salonSelect) salonSelect.value = data.data.salon || '';
            if (claveModInput) claveModInput.value = data.data.clave_mod || '';
            if (claveAxInput) claveAxInput.value = data.data.clave_ax || '';
            if (tamanoInput) tamanoInput.value = data.data.tamano || '';
            if (nombreInput) {
                nombreInput.value = data.data.nombre || '';
                nombreInput.style.color = '#1f2937';
            }
        } else {
            if (nombreInput) {
                nombreInput.value = '';
                nombreInput.style.color = '#dc2626';
                nombreInput.placeholder = data.message || 'No se encontro informacion';
            }
        }
    } catch (error) {
        if (nombreInput) {
            nombreInput.value = '';
            nombreInput.style.color = '#dc2626';
            nombreInput.placeholder = 'Error al buscar';
        }
    }
}

function agregarFilaModalCodificacion() {
    const tbody = document.getElementById('tabla-codificacion-body');
    if (!tbody) return;

    const filasExistentes = tbody.querySelectorAll('tr');
    const indiceFila = filasExistentes.length;

    const newRow = document.createElement('tr');
    newRow.className = 'fila-codificacion border-t border-gray-200';

    const esImportar = modoActualCodificacion === 'importar';
    const displayOrdenTrabajo = esImportar ? '' : 'display: none;';

    newRow.innerHTML = `
        <td class="px-3 py-2 celda-orden-trabajo" style="${displayOrdenTrabajo}">
            <input type="text"
                   name="orden_trabajo[]"
                   class="input-orden-trabajo w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                   placeholder="Orden de trabajo"
                   onblur="autocompletarDesdeCatCodificados(this)">
        </td>
        <td class="px-3 py-2">
            <select name="salon[]"
                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 bg-white">
                <option value="">Seleccionar salón</option>
                <option value="JACQUARD">JACQUARD</option>
                <option value="SMIT">SMIT</option>
                <option value="KARL MAYER">KARL MAYER</option>
                <option value="SULZER">SULZER</option>
            </select>
        </td>
        <td class="px-3 py-2">
            <input type="text"
                   name="clave_mod[]"
                   class="input-clave-mod w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                   placeholder="Clave mod">
        </td>
        <td class="px-3 py-2">
            <input type="text"
                   name="clave_ax[]"
                   class="input-clave-ax w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                   placeholder="Clave AX"
                   onblur="autocompletarNombreDesdeFlogs(this)">
            <input type="hidden" name="idflog[]" class="input-idflog" value="">
            <input type="hidden" name="custname[]" class="input-custname" value="">
        </td>
        <td class="px-3 py-2">
            <input type="text"
                   name="tamano[]"
                   class="input-tamano w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                   placeholder="Tamaño"
                   onblur="autocompletarNombreDesdeFlogs(this)">
        </td>
        <td class="px-3 py-2">
            <input type="text"
                   name="nombre[]"
                   class="input-nombre w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                   placeholder="Nombre (se completa automáticamente)">
        </td>
        <td class="px-3 py-2">
            <button type="button"
                    onclick="eliminarFilaModalCodificacion(this)"
                    class="px-2 py-1 text-xs text-red-600 hover:text-red-800 hover:bg-red-50 rounded">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    tbody.appendChild(newRow);

    // Mostrar botones de eliminar en todas las filas ahora que hay más de una
    actualizarVisibilidadBotonesEliminar();
}

function actualizarVisibilidadBotonesEliminar() {
    const tbody = document.getElementById('tabla-codificacion-body');
    if (!tbody) return;

    const filas = tbody.querySelectorAll('tr.fila-codificacion');
    const botonesEliminar = tbody.querySelectorAll('button[onclick*="eliminarFilaModalCodificacion"]');

    // Si hay más de una fila, mostrar todos los botones
    // Si hay solo una fila, ocultar todos los botones
    const mostrar = filas.length > 1;

    botonesEliminar.forEach(btn => {
        if (mostrar) {
            btn.style.display = '';
        } else {
            btn.style.display = 'none';
        }
    });
}

function eliminarFilaModalCodificacion(btn) {
    const row = btn.closest('tr');
    if (row) {
        const tbody = document.getElementById('tabla-codificacion-body');
        const filas = tbody.querySelectorAll('tr.fila-codificacion');
        if (filas.length > 1) {
            row.remove();
            // Actualizar visibilidad de botones después de eliminar
            actualizarVisibilidadBotonesEliminar();
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'No se puede eliminar',
                    text: 'Debe haber al menos una fila',
                    confirmButtonColor: '#3b82f6'
                });
            }
        }
    }
}

function validarYCapturarDatosCodificacion() {
    const filas = document.querySelectorAll('#tabla-codificacion-body tr.fila-codificacion');
    if (filas.length === 0) {
        Swal.showValidationMessage('Debe agregar al menos una fila');
        return false;
    }

    const datos = [];
    let hayErrores = false;
    let mensajeError = '';

    filas.forEach((fila, index) => {
        const salonInput = fila.querySelector('select[name="salon[]"]') || fila.querySelector('input[name="salon[]"]');
        const salon = salonInput?.value?.trim() || '';
        const ordenTrabajo = fila.querySelector('input[name="orden_trabajo[]"]')?.value?.trim() || '';
        const claveMod = fila.querySelector('input[name="clave_mod[]"]')?.value?.trim() || '';
        const claveAx = fila.querySelector('input[name="clave_ax[]"]')?.value?.trim() || '';
        const nombre = fila.querySelector('input[name="nombre[]"]')?.value?.trim() || '';
        const tamano = fila.querySelector('input[name="tamano[]"]')?.value?.trim() || '';
        const idflog = fila.querySelector('input[name="idflog[]"]')?.value?.trim() || '';
        const custname = fila.querySelector('input[name="custname[]"]')?.value?.trim() || '';

        // Validaciones según el modo
        if (modoActualCodificacion === 'importar') {
            if (!salon || !ordenTrabajo || !claveMod || !claveAx || !nombre || !tamano) {
                hayErrores = true;
                mensajeError = `Fila ${index + 1}: Todos los campos son requeridos en modo importar`;
                return;
            }
        } else {
            // Modo duplicar: al menos salon, clave_ax, nombre y tamano son requeridos
            if (!salon || !claveMod || !claveAx || !nombre || !tamano) {
                hayErrores = true;
                mensajeError = `Fila ${index + 1}: Salon, Clave mod, Clave AX, Nombre y Tamaño son requeridos`;
                return;
            }
        }

        datos.push({
            salon: salon,
            orden_trabajo: ordenTrabajo,
            clave_mod: claveMod,
            clave_ax: claveAx,
            nombre: nombre,
            tamano: tamano,
            idflog: idflog,
            custname: custname
        });
    });

    if (hayErrores) {
        Swal.showValidationMessage(mensajeError);
        return false;
    }

    return {
        modo: modoActualCodificacion,
        datos: datos
    };
}

function generarHTMLModalCodificacion(registroOriginal = null) {
    const salonInicial = registroOriginal?.SalonTejidoId || '';
    const claveModInicial = registroOriginal?.TamanoClave || '';
    const claveAxInicial = registroOriginal?.ItemId || '';
    const nombreInicial = registroOriginal?.Nombre || '';
    const tamanoInicial = registroOriginal?.InventSizeId || '';
    const ordenTrabajoInicial = registroOriginal?.OrdenTejido || '';

    return `
        <div class="space-y-4 text-left">
            <style>
                .switch-container-wrapper {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 2rem;
                    margin-bottom: 1.5rem;
                    padding: 1rem;
                    background: #f9fafb;
                    border-radius: 0.75rem;
                    border: 1px solid #e5e7eb;
                }
                .toggle-switch-group {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 1rem;
                    flex: 1;
                }
                .toggle-switch-container {
                    position: relative;
                    display: inline-block;
                    width: 400px;
                    height: 52px;
                }
                .toggle-switch-track {
                    position: absolute;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                    border: 2px solid #059669;
                    border-radius: 26px;
                    cursor: pointer;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
                    overflow: hidden;
                }
                .toggle-switch-slider {
                    position: absolute;
                    top: 6px;
                    left: 6px;
                    width: 190px;
                    height: 38px;
                    background: #ffffff;
                    border-radius: 19px;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15), 0 2px 4px rgba(0, 0, 0, 0.1);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 600;
                    font-size: 0.8125rem;
                    color: #374151;
                    z-index: 2;
                }
                .toggle-switch-track.active .toggle-switch-slider {
                    transform: translateX(0);
                    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
                }
                .toggle-switch-track:not(.active) .toggle-switch-slider {
                    transform: translateX(196px);
                }
                #slider-text {
                    white-space: nowrap;
                    font-size: 0.8125rem;
                }
                .toggle-switch-option {
                    position: absolute;
                    top: 50%;
                    transform: translateY(-50%);
                    font-size: 0.8125rem;
                    font-weight: 600;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    pointer-events: none;
                    z-index: 1;
                    white-space: nowrap;
                }
                .toggle-switch-option.option-left {
                    left: 20px;
                    color: #374151;
                }
                .toggle-switch-option.option-right {
                    right: 20px;
                    color: #374151;
                }
                .toggle-switch-track.active .option-left {
                    color: #ffffff;
                    opacity: 1;
                    font-weight: 700;
                }
                .toggle-switch-track:not(.active) .option-right {
                    color: #ffffff;
                    opacity: 1;
                    font-weight: 700;
                }
                .toggle-switch-track.active .option-right {
                    color: rgba(255, 255, 255, 0.6);
                    opacity: 0.7;
                }
                .toggle-switch-track:not(.active) .option-left {
                    color: rgba(255, 255, 255, 0.6);
                    opacity: 0.7;
                }
                /* Color del texto dentro del slider */
                .toggle-switch-track.active #slider-text {
                    color: #059669;
                }
                .toggle-switch-track:not(.active) #slider-text {
                    color: #059669;
                }
                .btn-agregar-fila {
                    padding: 0.625rem 1.25rem;
                    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                    color: white;
                    border: none;
                    border-radius: 0.5rem;
                    font-weight: 600;
                    font-size: 0.875rem;
                    cursor: pointer;
                    transition: all 0.2s;
                    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
                }
                .btn-agregar-fila:hover {
                    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
                    transform: translateY(-1px);
                }
                .btn-agregar-fila:active {
                    transform: translateY(0);
                }
                #tabla-codificacion {
                    width: 100%;
                    border-collapse: collapse;
                }
                #tabla-codificacion th {
                    background-color: #f3f4f6;
                    font-weight: 600;
                    text-align: left;
                }
                #tabla-codificacion td {
                    padding: 0.5rem;
                }
                /* Columnas más pequeñas para Orden de trabajo, Salón, Clave AX y Tamaño */
                /* Orden de trabajo - columna 1 */
                #tabla-codificacion th:nth-child(1),
                #tabla-codificacion td:nth-child(1),
                #header-orden-trabajo,
                #columna-orden-trabajo {
                    width: 140px;
                    min-width: 140px;
                    max-width: 140px;
                }
                /* Salón - columna 2 */
                #tabla-codificacion th:nth-child(2),
                #tabla-codificacion td:nth-child(2) {
                    width: 140px;
                    min-width: 140px;
                    max-width: 140px;
                }
                /* Clave mod - columna 3 */
                #tabla-codificacion th:nth-child(3),
                #tabla-codificacion td:nth-child(3) {
                    width: 140px;
                    min-width: 140px;
                    max-width: 140px;
                }
                /* Clave AX - columna 4 */
                #tabla-codificacion th:nth-child(4),
                #tabla-codificacion td:nth-child(4) {
                    width: 150px;
                    min-width: 150px;
                    max-width: 150px;
                }
                /* Tamaño - columna 5 */
                #tabla-codificacion th:nth-child(5),
                #tabla-codificacion td:nth-child(5) {
                    width: 120px;
                    min-width: 120px;
                    max-width: 120px;
                }
                /* Nombre - columna 6, ocupa el resto del espacio */
                #tabla-codificacion th:nth-child(6),
                #tabla-codificacion td:nth-child(6) {
                    width: auto;
                }
            </style>

            <div class="switch-container-wrapper">
                <div class="toggle-switch-group">
                    <div class="toggle-switch-container" id="toggle-switch-wrapper">
                        <div class="toggle-switch-track active" id="toggle-switch-track" onclick="toggleModoCodificacion()">
                            <div class="toggle-switch-slider">
                                <span id="slider-text">Duplicar</span>
                            </div>
                            <span class="toggle-switch-option option-left">Duplicar</span>
                            <span class="toggle-switch-option option-right">Desde codificación</span>
                        </div>
                    </div>
                </div>
                <button type="button"
                        onclick="agregarFilaModalCodificacion()"
                        class="btn-agregar-fila">
                    <i class="fas fa-plus mr-2"></i>
                    Agregar fila
                </button>
            </div>



            <div class="overflow-x-auto">
                <table id="tabla-codificacion" class="min-w-full border border-gray-300">
                    <thead>
                        <tr>
                            <th id="header-orden-trabajo" class="px-3 py-2 bg-gray-100" style="display: none;">Orden de trabajo</th>
                            <th class="px-3 py-2 bg-blue-100">Salón</th>
                            <th class="px-3 py-2 bg-blue-100">Clave modelo</th>
                            <th class="px-3 py-2 bg-blue-100">Clave AX</th>
                            <th class="px-3 py-2 bg-blue-100">Tamaño</th>
                            <th class="px-3 py-2">Nombre</th>
                            <th class="px-3 py-2 w-16"></th>
                        </tr>
                    </thead>
                    <tbody id="tabla-codificacion-body">
                        <tr class="fila-codificacion border-t border-gray-200">
                            <td id="columna-orden-trabajo" class="px-3 py-2 celda-orden-trabajo" style="display: none;">
                                <input type="text"
                                       name="orden_trabajo[]"
                                       value="${ordenTrabajoInicial}"
                                       class="input-orden-trabajo w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                       placeholder="Orden de trabajo"
                                       onblur="autocompletarDesdeCatCodificados(this)">
                            </td>
                            <td class="px-3 py-2">
                                <select name="salon[]"
                                        class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 bg-white">
                                    <option value="">Seleccionar salón</option>
                                    <option value="JACQUARD" ${salonInicial === 'JACQUARD' ? 'selected' : ''}>JACQUARD</option>
                                    <option value="SMIT" ${salonInicial === 'SMIT' ? 'selected' : ''}>SMIT</option>
                                    <option value="KARL MAYER" ${salonInicial === 'KARL MAYER' ? 'selected' : ''}>KARL MAYER</option>
                                    <option value="SULZER" ${salonInicial === 'SULZER' ? 'selected' : ''}>SULZER</option>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input type="text"
                                       name="clave_mod[]"
                                       value="${claveModInicial}"
                                       class="input-clave-mod w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                       placeholder="Clave mod">
                            </td>
                            <td class="px-3 py-2">
                                <input type="text"
                                       name="clave_ax[]"
                                       value="${claveAxInicial}"
                                       class="input-clave-ax w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                       placeholder="Clave AX"
                                       onblur="autocompletarNombreDesdeFlogs(this)">
                                <input type="hidden" name="idflog[]" class="input-idflog" value="">
                                <input type="hidden" name="custname[]" class="input-custname" value="">
                            </td>
                            <td class="px-3 py-2">
                                <input type="text"
                                       name="tamano[]"
                                       value="${tamanoInicial}"
                                       class="input-tamano w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                       placeholder="Tamaño"
                                       onblur="autocompletarNombreDesdeFlogs(this)">
                            </td>
                            <td class="px-3 py-2">
                                <input type="text"
                                       name="nombre[]"
                                       value="${nombreInicial}"
                                       class="input-nombre w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                       placeholder="Nombre (se completa automáticamente)">
                            </td>
                            <td class="px-3 py-2">
                                <button type="button"
                                        onclick="eliminarFilaModalCodificacion(this)"
                                        style="display: none;"
                                        class="px-2 py-1 text-xs text-red-600 hover:text-red-800 hover:bg-red-50 rounded">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

async function abrirModalDuplicarImportarCodificacion(registroId) {
    if (!registroId) {
        if (typeof showToast === 'function') {
            showToast('Selecciona un registro', 'warning');
        }
        return;
    }

    // Obtener datos del registro original
    let registroOriginal = null;
    try {
        const response = await fetch(`/planeacion/catalogos/codificacion-modelos/${registroId}`, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });
        if (response.ok) {
            const data = await response.json();
            registroOriginal = data?.data || data;
        }
    } catch (error) {
        console.error('Error al obtener registro:', error);
    }

    // Resetear modo a duplicar
    modoActualCodificacion = 'duplicar';

    const resultado = await Swal.fire({
        html: generarHTMLModalCodificacion(registroOriginal),
        width: '90%',
        maxWidth: '1200px',
        showCancelButton: true,
        confirmButtonText: 'Crear',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        customClass: {
            confirmButton: 'swal-confirm-btn inline-flex justify-center px-6 py-3 text-base font-semibold rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500',
            cancelButton: 'ml-2 inline-flex justify-center px-6 py-3 text-base font-semibold rounded-md text-gray-700 bg-white hover:bg-gray-50 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300'
        },
        didOpen: () => {
            // Actualizar estado del toggle switch (inicia en 'duplicar')
            modoActualCodificacion = 'duplicar';
            setModoActualCodificacion('duplicar');

            // Actualizar columnas según modo inicial
            actualizarColumnasModalCodificacion();

            // Asegurar que el botón de eliminar esté oculto si solo hay una fila
            actualizarVisibilidadBotonesEliminar();
        },
        preConfirm: () => {
            return validarYCapturarDatosCodificacion();
        }
    });

    if (!resultado.isConfirmed) {
        return;
    }

    const datos = resultado.value;

    // Enviar datos al backend
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        const response = await fetch('/planeacion/catalogos/codificacion-modelos/duplicar-importar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                registro_id_original: registroId,
                modo: datos.modo,
                datos: datos.datos
            })
        });

        const responseData = await response.json();

        if (response.ok && responseData.success) {
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: responseData.message || 'Registros creados correctamente',
                confirmButtonColor: '#3b82f6',
                timer: 2000,
                showConfirmButton: false
            });

            // Actualizar la tabla usando loadData() que recarga los datos sin recargar toda la página HTML
            // Esto es más eficiente que window.location.reload() porque solo actualiza los datos de la tabla
            if (typeof window.loadData === 'function') {
                window.loadData();
            } else if (typeof loadData === 'function') {
                loadData();
            } else {
                // Último recurso: reload completo
                window.location.reload();
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: responseData.message || 'Error al crear los registros',
                confirmButtonColor: '#dc2626'
            });
        }
    } catch (error) {
        console.error('Error al enviar datos:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ocurrió un error al procesar la solicitud',
            confirmButtonColor: '#dc2626'
        });
    }
}

// Exponer función globalmente
window.abrirModalDuplicarImportarCodificacion = abrirModalDuplicarImportarCodificacion;
window.agregarFilaModalCodificacion = agregarFilaModalCodificacion;
window.eliminarFilaModalCodificacion = eliminarFilaModalCodificacion;
window.toggleModoCodificacion = toggleModoCodificacion;
window.setModoActualCodificacion = setModoActualCodificacion;
window.getModoActualCodificacion = getModoActualCodificacion;
window.actualizarColumnasModalCodificacion = actualizarColumnasModalCodificacion;
window.actualizarVisibilidadBotonesEliminar = actualizarVisibilidadBotonesEliminar;
window.autocompletarNombreDesdeFlogs = autocompletarNombreDesdeFlogs;
window.autocompletarDesdeCatCodificados = autocompletarDesdeCatCodificados;
