/**
 * Pantalla principal de Codificación.
 *
 * La configuración dinámica se inyecta en un bloque JSON desde Blade; toda la
 * interacción de tabla, filtros y acciones permanece fuera de la plantilla.
 */

import { openLMatModal } from './lmat-modal';

(function () {
    // =========================
    //   CONFIG / ESTADO
    // =========================
    const configElement = document.getElementById('catcodificacion-config');
    let pageConfig = {};

    try {
        pageConfig = JSON.parse(configElement?.textContent || '{}');
    } catch (error) {
        console.error('No se pudo leer la configuración de Codificación.', error);
    }

    const CONFIG = {
        ...pageConfig,
        dateColumns: ['FechaTejido', 'FechaCumplimiento', 'FechaCompromiso', 'FechaCreacion', 'FechaModificacion'],
        dateTimeColumns: ['FechaArranque', 'FechaFinaliza'],
    };

    const state = {
        data: [],
        filtered: [],
        filtros: [],       // { columna: index, valor: string }
        filtrosPorColumna: [], // { column: string, value: string } para filtros desde menú contextual
        pinnedColumns: [],
        page: 1,
        perPage: 500,
        total: CONFIG.totalRegistros || 0,
        loading: false,
        selectedRowIndex: null, // Índice global de la fila seleccionada
    };

    // =========================
    //   HELPERS DOM
    // =========================
    const $  = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

    const getCsrf = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function formatDateOnly(val, columnName) {
        if (val == null || val === '') return '';
        const s = String(val).trim();
        if (!s) return '';

        if (CONFIG.dateTimeColumns && CONFIG.dateTimeColumns.includes(columnName)) {
            const normalized = s.replace(' ', 'T');
            const d = new Date(normalized);
            if (!isNaN(d.getTime())) {
                const da = String(d.getDate()).padStart(2, '0');
                const mo = String(d.getMonth() + 1).padStart(2, '0');
                const y = d.getFullYear();
                const h = String(d.getHours()).padStart(2, '0');
                const mi = String(d.getMinutes()).padStart(2, '0');
                return da + '/' + mo + '/' + y + ' ' + h + ':' + mi;
            }

            const mDateTime = s.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
            if (mDateTime) {
                return mDateTime[3] + '/' + mDateTime[2] + '/' + mDateTime[1] + ' ' + mDateTime[4] + ':' + mDateTime[5];
            }
        }

        if (!CONFIG.dateColumns || !CONFIG.dateColumns.includes(columnName)) return s;

        const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (m) return m[3] + '/' + m[2] + '/' + m[1];
        const d = new Date(s);
        if (!isNaN(d.getTime())) {
            const da = String(d.getDate()).padStart(2, '0');
            const mo = String(d.getMonth() + 1).padStart(2, '0');
            const y = d.getFullYear();
            return da + '/' + mo + '/' + y;
        }
        return s;
    }

    function setLoading(isLoading, message = 'Cargando datos...', count = '') {
        state.loading = isLoading;
        const overlay  = $('#loading-overlay');
        const messageEl = $('#loading-message');
        const countEl   = $('#loading-count');

        if (!overlay) return;

        if (messageEl) messageEl.textContent = message;
        if (countEl)   countEl.textContent   = count;

        if (isLoading) {
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
        } else {
            overlay.classList.add('hidden');
            overlay.classList.remove('flex');
        }
    }

    // =========================
    //   TOAST SIMPLE (fallback)
    // =========================
    function internalToast(message, type = 'info') {
        const colors = {
            success: 'bg-green-600',
            error: 'bg-red-600',
            warning: 'bg-yellow-500',
            info: 'bg-blue-600',
        };

        let container = $('#toast-notification');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-notification';
            container.className = 'fixed top-4 right-4 z-[9999] max-w-sm w-full';
            document.body.appendChild(container);
        }

        container.innerHTML =
            '<div class="rounded-lg shadow-lg text-white px-4 py-3 ' + (colors[type] || colors.info) + '">' +
                '<div class="flex items-center justify-between gap-3">' +
                    '<span class="text-sm">' + message + '</span>' +
                    '<button type="button" class="text-sm font-bold hover:opacity-80" onclick="this.closest(\'#toast-notification\').remove()">' +
                        '&times;' +
                    '</button>' +
                '</div>' +
            '</div>';

        setTimeout(() => {
            container?.remove();
        }, 3500);
    }

    // Si ya existe showToast global, úsalo; si no, define el nuestro
    const showToast = window.showToast || internalToast;
    if (!window.showToast) {
        window.showToast = showToast;
    }

    /**
     * Muestra el modal de formulario de codificación al hacer clic en el botón de la navbar.
     */
    function mostrarAlertaNavbar() {
        if (typeof Swal === 'undefined') {
            internalToast('SweetAlert2 no está cargado.', 'warning');
            return;
        }

        // Obtener el registro seleccionado si existe
        const registroSeleccionado = state.selectedRowIndex !== null && state.selectedRowIndex !== undefined
            ? state.filtered[state.selectedRowIndex]
            : null;

        // Valores iniciales desde el registro seleccionado o vacíos
        const ordenTejido = registroSeleccionado?.OrdenTejido || '';
        const ordenDesdeFila = String(ordenTejido || '').trim();
        const telar = registroSeleccionado?.TelarId || '';
        const articulo = registroSeleccionado?.ItemId || registroSeleccionado?.ClaveModelo || '';
        const pesoMuestra = registroSeleccionado?.PesoMuestra || '';
        const actLmat = registroSeleccionado?.ActualizaLmat === true || registroSeleccionado?.ActualizaLmat === 1 || registroSeleccionado?.ActualizaLmat === '1';
        const bomId = registroSeleccionado?.BomId || '';

        Swal.fire({
            title: 'Peso Muestra',
            html: `
                <div class="text-left space-y-4">
                    <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                id="swal-usar-fila-seleccionada"
                                class="swal-usar-fila-seleccionada mt-0.5 w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                            >
                            <span>
                                <span class="text-sm font-medium text-gray-800">Usar orden de la fila seleccionada</span>
                                <span class="block text-xs text-gray-500 mt-0.5">Si está activo, no se listan solo órdenes en proceso: se usa el Orden Tejido del registro marcado en la tabla y se consulta CatCodificados.</span>
                            </span>
                        </label>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Orden Tejido</label>
                            <select
                                id="swal-orden-tejido"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white text-gray-800 appearance-none cursor-pointer swal-select-orden"
                            >
                                <option value="">Seleccione una orden en proceso...</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telar</label>
                            <input
                                type="text"
                                id="swal-telar"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 focus:outline-none"
                                placeholder="Los trae del cat codificados"
                                value="${telar}"
                                readonly
                                title="solo para visualización"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Articulo</label>
                            <input
                                type="text"
                                id="swal-articulo"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 focus:outline-none"
                                placeholder="De cat codificados"
                                value="${articulo}"
                                readonly
                                title="solo para visualización"
                            >
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 border-t border-gray-200 pt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Peso Muestra</label>
                            <input
                                type="number"
                                id="swal-peso-muestra"
                                step="any"
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="lo obtiene de catcodificacos y se puede editar"
                                value="${pesoMuestra !== '' && pesoMuestra !== null && pesoMuestra !== undefined ? Number(pesoMuestra) : ''}"
                            >
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    id="swal-act-lmat"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    ${actLmat ? 'checked' : ''}
                                >
                                <span class="text-sm font-medium text-gray-700">Act Lmat</span>
                            </label>
                        </div>
                        <div id="swal-lista-mat-container">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lista L Mat (BomId) <span id="swal-lista-mat-req" class="text-red-500 ${actLmat ? '' : 'hidden'}">*</span></label>
                            <select
                                id="swal-lista-mat"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white text-gray-800 appearance-none cursor-pointer ${actLmat ? '' : 'bg-gray-100 cursor-not-allowed'}"
                                ${actLmat ? '' : 'disabled'}
                            >
                                <option value="">Seleccione un L.Mat...</option>
                            </select>
                            <p id="swal-lista-mat-message" class="text-xs text-gray-500 mt-1 hidden"></p>
                        </div>
                    </div>
                </div>
            `,
            width: '600px',
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6b7280',
            didOpen: () => {
                // Habilitar/bloquear campo Lista Mat según checkbox Act Lmat (siempre visible)
                const actLmatCheckbox = document.getElementById('swal-act-lmat');
                const listaMatInputRef = document.getElementById('swal-lista-mat');
                const selectOrden = document.getElementById('swal-orden-tejido');
                const telarInput = document.getElementById('swal-telar');
                const articuloInput = document.getElementById('swal-articulo');
                const pesoMuestraInput = document.getElementById('swal-peso-muestra');
                const listaMatMessage = document.getElementById('swal-lista-mat-message');

                function actualizarEstadoListaMat() {
                    const sel = document.getElementById('swal-lista-mat');
                    const reqSpan = document.getElementById('swal-lista-mat-req');
                    if (!sel) return;
                    const activo = actLmatCheckbox && actLmatCheckbox.checked;
                    sel.disabled = !activo;
                    sel.classList.toggle('bg-gray-100', !activo);
                    sel.classList.toggle('cursor-not-allowed', !activo);
                    if (reqSpan) reqSpan.classList.toggle('hidden', !activo);
                    if (!activo) sel.value = '';
                    actualizarBotonGuardar();
                }

                function actualizarBotonGuardar() {
                    const confirmBtn = Swal.getConfirmButton();
                    if (!confirmBtn) return;
                    const actLmatChecked = actLmatCheckbox && actLmatCheckbox.checked;
                    const bomIdVal = listaMatInputRef && listaMatInputRef.value ? listaMatInputRef.value.trim() : '';
                    const debeBloquear = actLmatChecked && (!bomIdVal || bomIdVal === '');
                    confirmBtn.disabled = debeBloquear;
                    if (debeBloquear) {
                        confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    } else {
                        confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    }
                }

                if (actLmatCheckbox && listaMatInputRef) {
                    actLmatCheckbox.addEventListener('change', actualizarEstadoListaMat);
                    listaMatInputRef.addEventListener('change', actualizarBotonGuardar);
                    actualizarEstadoListaMat();
                }

                function poblarSelectLmat(opciones, bomIdSeleccionado) {
                    if (!listaMatInputRef) return;
                    const valorPrevio = bomIdSeleccionado != null ? String(bomIdSeleccionado) : listaMatInputRef.value;
                    listaMatInputRef.innerHTML = '';
                    const optVacia = document.createElement('option');
                    optVacia.value = '';
                    optVacia.textContent = 'Seleccione un L.Mat...';
                    listaMatInputRef.appendChild(optVacia);

                    if (opciones && Array.isArray(opciones) && opciones.length > 0) {
                        opciones.forEach(function(item) {
                            const bomId = (item.bomId != null) ? String(item.bomId) : '';
                            if (!bomId) return;
                            const opt = document.createElement('option');
                            opt.value = bomId;
                            opt.textContent = (item.bomName != null && item.bomName !== '') ? (bomId + ' - ' + String(item.bomName)) : bomId;
                            listaMatInputRef.appendChild(opt);
                        });
                        if (valorPrevio) listaMatInputRef.value = valorPrevio;
                        if (listaMatMessage) {
                            listaMatMessage.textContent = opciones.length + ' L.Mat encontrado' + (opciones.length > 1 ? 's' : '') + '.';
                            listaMatMessage.classList.remove('hidden');
                            listaMatMessage.className = 'text-xs text-green-600 mt-1';
                        }
                    } else if (listaMatMessage) {
                        listaMatMessage.textContent = 'No se encontró ningún L.Mat para este artículo/tamaño.';
                        listaMatMessage.classList.remove('hidden');
                        listaMatMessage.className = 'text-xs text-red-500 mt-1';
                    }
                    actualizarBotonGuardar();
                }

                function cargarCatCodificadosPorOrden(orden) {
                    const ord = (orden || '').toString().trim();
                    if (!ord) {
                        if (pesoMuestraInput) pesoMuestraInput.value = '';
                        if (actLmatCheckbox) actLmatCheckbox.checked = false;
                        if (typeof actualizarEstadoListaMat === 'function') actualizarEstadoListaMat();
                        poblarSelectLmat([]);
                        return;
                    }
                    fetch('/planeacion/codificacion/api/catcodificados-por-orden/' + encodeURIComponent(ord), { headers: { 'Accept': 'application/json' } })
                        .then(resp => resp.json())
                        .then(json => {
                            if (!json.s) return;
                            const d = json.d;
                            if (!d) {
                                if (pesoMuestraInput) pesoMuestraInput.value = '';
                                if (actLmatCheckbox) actLmatCheckbox.checked = false;
                                if (typeof actualizarEstadoListaMat === 'function') actualizarEstadoListaMat();
                                poblarSelectLmat([]);
                                return;
                            }
                            if (pesoMuestraInput) pesoMuestraInput.value = (d.pesoMuestra != null && d.pesoMuestra !== '') ? Number(d.pesoMuestra) : '';
                            if (actLmatCheckbox) actLmatCheckbox.checked = d.actualizaLmat === true || d.actualizaLmat === 1;
                            poblarSelectLmat(d.listaLmat || [], d.bomId);
                            if (typeof actualizarEstadoListaMat === 'function') actualizarEstadoListaMat();
                            if (telarInput && (d.telarId != null && d.telarId !== '')) telarInput.value = String(d.telarId);
                            if (articuloInput && (d.itemId != null || d.nombre != null)) articuloInput.value = (d.itemId != null ? String(d.itemId) : '') || (d.nombre != null ? String(d.nombre) : '');
                        })
                        .catch(() => {});
                }

                const chkUsarFila = document.getElementById('swal-usar-fila-seleccionada');

                function onOrdenTejidoSelectChange() {
                    if (!selectOrden) return;
                    const opt = selectOrden.options[selectOrden.selectedIndex];
                    if (opt && opt.value) {
                        if (telarInput) telarInput.value = opt.dataset.noTelarId || '';
                        if (articuloInput) articuloInput.value = opt.dataset.itemId || opt.dataset.nombreProducto || '';
                        cargarCatCodificadosPorOrden(selectOrden.value);
                    } else {
                        if (telarInput) telarInput.value = '';
                        if (articuloInput) articuloInput.value = '';
                        cargarCatCodificadosPorOrden('');
                    }
                }

                function poblarSelectOrdenesEnProceso() {
                    if (!selectOrden) return;
                    selectOrden.disabled = false;
                    selectOrden.classList.remove('bg-gray-100', 'cursor-not-allowed');
                    selectOrden.innerHTML = '<option value="">Seleccione una orden en proceso...</option>';
                    fetch('/planeacion/codificacion/api/ordenes-en-proceso', { headers: { 'Accept': 'application/json' } })
                        .then(resp => resp.json())
                        .then(json => {
                            if (json.s && Array.isArray(json.d)) {
                                json.d.forEach(item => {
                                    const opt = document.createElement('option');
                                    opt.value = item.noProduccion || '';
                                    opt.dataset.noTelarId = item.noTelarId != null ? String(item.noTelarId) : '';
                                    opt.dataset.itemId = item.itemId != null ? String(item.itemId) : '';
                                    opt.dataset.nombreProducto = item.nombreProducto || '';
                                    opt.textContent = item.noProduccion || '';
                                    selectOrden.appendChild(opt);
                                });
                                if (ordenTejido) selectOrden.value = ordenTejido;
                                if (selectOrden.value && selectOrden.dispatchEvent) selectOrden.dispatchEvent(new Event('change'));
                            }
                        })
                        .catch(() => {});
                }

                function aplicarModoOrdenTejido(usarFila) {
                    if (!selectOrden) return;
                    if (usarFila) {
                        if (!ordenDesdeFila) {
                            internalToast('Selecciona primero una fila en la tabla que tenga Orden Tejido.', 'warning');
                            if (chkUsarFila) chkUsarFila.checked = false;
                            return;
                        }
                        selectOrden.disabled = true;
                        selectOrden.classList.add('bg-gray-100', 'cursor-not-allowed');
                        selectOrden.innerHTML = '';
                        const o = document.createElement('option');
                        o.value = ordenDesdeFila;
                        o.textContent = ordenDesdeFila + ' (fila / catálogo)';
                        selectOrden.appendChild(o);
                        selectOrden.value = ordenDesdeFila;
                        if (telarInput && registroSeleccionado) {
                            telarInput.value = registroSeleccionado.TelarId != null ? String(registroSeleccionado.TelarId) : '';
                        }
                        if (articuloInput && registroSeleccionado) {
                            articuloInput.value = (registroSeleccionado.ItemId != null ? String(registroSeleccionado.ItemId) : '')
                                || (registroSeleccionado.ClaveModelo != null ? String(registroSeleccionado.ClaveModelo) : '');
                        }
                        cargarCatCodificadosPorOrden(ordenDesdeFila);
                    } else {
                        poblarSelectOrdenesEnProceso();
                        selectOrden.focus();
                    }
                }

                if (selectOrden) {
                    selectOrden.addEventListener('change', onOrdenTejidoSelectChange);
                    if (chkUsarFila) {
                        chkUsarFila.addEventListener('change', function() {
                            aplicarModoOrdenTejido(chkUsarFila.checked);
                        });
                    }
                    aplicarModoOrdenTejido(false);
                }
            },
            preConfirm: () => {
                const ordenTejido = document.getElementById('swal-orden-tejido')?.value.trim() || '';
                const pesoMuestraRaw = document.getElementById('swal-peso-muestra')?.value?.trim();
                const pesoMuestra = pesoMuestraRaw === '' || pesoMuestraRaw === undefined
                    ? null
                    : parseFloat(pesoMuestraRaw);
                const actLmat = document.getElementById('swal-act-lmat')?.checked || false;
                const bomIdRaw = document.getElementById('swal-lista-mat')?.value.trim() || '';
                const bomId = actLmat ? bomIdRaw : null;

                // Validaciones básicas
                if (!ordenTejido) {
                    const usarFila = document.getElementById('swal-usar-fila-seleccionada')?.checked;
                    Swal.showValidationMessage(
                        usarFila
                            ? 'La fila seleccionada no tiene Orden Tejido o el catálogo no devolvió datos.'
                            : 'Seleccione una orden en proceso'
                    );
                    return false;
                }
                if (pesoMuestra !== null && (Number.isNaN(pesoMuestra) || pesoMuestra < 0)) {
                    Swal.showValidationMessage('Peso Muestra debe ser un número mayor o igual a 0');
                    return false;
                }
                if (actLmat && (!bomId || bomId.trim() === '')) {
                    Swal.showValidationMessage('Lista L Mat (BomId) es obligatoria cuando Act Lmat está activo');
                    return false;
                }

                return {
                    ordenTejido,
                    pesoMuestra,
                    actLmat,
                    bomId
                };
            }
        }).then(async (result) => {
            if (result.isConfirmed && result.value) {
                const datos = result.value;
                try {
                    Swal.fire({
                        title: 'Guardando...',
                        text: 'Por favor espera',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const resp = await fetch('/planeacion/codificacion/api/actualizar-peso-muestra-lmat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': getCsrf(),
                        },
                        body: JSON.stringify({
                            ordenTejido: datos.ordenTejido,
                            pesoMuestra: datos.pesoMuestra,
                            actualizaLmat: datos.actLmat,
                            bomId: datos.bomId || null,
                        }),
                    });

                    const json = await resp.json();

                    Swal.close();

                    if (!json.s) {
                        showToast(json.e || 'Error al guardar los datos', 'error');
                        return;
                    }

                    showToast(json.message || 'Datos guardados correctamente', 'success');
                    if (json.actualizados && json.actualizados.length > 0) {
                        console.log('Actualizados:', json.actualizados);
                    }
                    // Recargar tabla con datos frescos (nocache + caché limpiada en backend)
                    await loadData(true);
                } catch (error) {
                    Swal.close();
                    showToast('Error al guardar: ' + (error.message || 'Error desconocido'), 'error');
                    console.error('Error al guardar:', error);
                }
            }
        });
    }

    // El módulo L.Mat recibe únicamente el contexto necesario de esta pantalla.
    function actualizarFilaTrasGuardarLMat({ bomId, bomName }) {
        if (state.selectedRowIndex === null || state.selectedRowIndex === undefined) return;
        const registro = state.filtered[state.selectedRowIndex];
        if (!registro) return;

        registro.BomId = bomId;
        registro.BomName = bomName;

        const tbody = $('#catcodificacion-body');
        const fila = tbody?.querySelector(`tr[data-index="${state.selectedRowIndex}"]`);
        if (!fila) return;

        const celdaBomId = fila.querySelector('td[data-column="BomId"]');
        const celdaBomName = fila.querySelector('td[data-column="BomName"]');
        if (celdaBomId) celdaBomId.textContent = bomId;
        if (celdaBomName) celdaBomName.textContent = bomName;
    }

    function mostrarModalLMat() {
        return openLMatModal({
            fallbackToast: internalToast,
            getSelectedRecord: () => (
                state.selectedRowIndex !== null && state.selectedRowIndex !== undefined
                    ? state.filtered[state.selectedRowIndex]
                    : null
            ),
            onSaved: actualizarFilaTrasGuardarLMat,
            showToast,
        });
    }

    // =========================
    //   CARGA DE DATOS
    // =========================
    async function loadData(forceRefresh) {
        if (!CONFIG.apiUrl || state.loading) return;

        setLoading(true, 'Cargando datos...', '');

        const url = forceRefresh
            ? (CONFIG.apiUrl + (CONFIG.apiUrl.indexOf('?') !== -1 ? '&' : '?') + 'nocache=1')
            : CONFIG.apiUrl;

        try {
            const resp = await fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
            });

            if (!resp.ok) {
                throw new Error('HTTP ' + resp.status);
            }

            const json = await resp.json();

            if (!json.s) {
                throw new Error(json.e || 'Error al cargar datos');
            }

            const columnas = (json.c && json.c.length) ? json.c : CONFIG.columnas;
            const raw      = Array.isArray(json.d) ? json.d : [];
            const totalRows = raw.length;

            setLoading(true, 'Procesando registros...', totalRows.toLocaleString() + ' registros');

            state.data = raw.map(rowArr => {
                const obj = {};
                const rowLen = Array.isArray(rowArr) ? rowArr.length : 0;
                const colLen = columnas.length;
                for (let j = 0; j < colLen; j++) {
                    const key = columnas[j];
                    if (key) obj[key] = (j < rowLen ? rowArr[j] : null) ?? null;
                }
                return obj;
            });

            // Ordenar por Id descendente (más nuevos primero)
            const idIndex = columnas.indexOf('Id');
            if (idIndex !== -1) {
                state.data.sort((a, b) => {
                    const idA = parseInt(a.Id) || 0;
                    const idB = parseInt(b.Id) || 0;
                    return idB - idA; // Descendente: mayor Id primero
                });
            }

            state.filtered = [...state.data];
            state.total    = json.t || state.data.length;

            aplicarFiltrosAND();

            renderPage();
            updateFilterCount();
            actualizarEstadoBotonReimprimir();
            actualizarEstadoBotonRevivir();

            setLoading(false);
        } catch (error) {
            console.error('loadData error:', error);
            const tbody = $('#catcodificacion-body');
            const colCount = (CONFIG.columnas && CONFIG.columnas.length) ? CONFIG.columnas.length : 1;
            if (tbody) {
                tbody.innerHTML =
                    '<tr>' +
                        '<td colspan="' + colCount + '" class="py-16 text-center">' +
                            '<div class="flex flex-col items-center gap-2">' +
                                '<i class="fas fa-exclamation-triangle text-red-500 text-3xl"></i>' +
                                '<p class="text-red-600 font-medium">Error al cargar datos</p>' +
                                '<p class="text-sm text-gray-500">' + (error.message || 'Error desconocido') + '</p>' +
                                '<button type="button" class="mt-2 px-3 py-1.5 text-sm rounded bg-blue-500 text-white hover:bg-blue-600" onclick="loadData()">' +
                                    'Reintentar' +
                                '</button>' +
                            '</div>' +
                        '</td>' +
                    '</tr>';
            }
            showToast('Error al cargar datos: ' + (error.message || 'Error desconocido'), 'error');
            setLoading(false);
        }
    }

    // =========================
    //   RENDER / PAGINACIÓN
    // =========================
    function renderPage() {
        const tbody = $('#catcodificacion-body');
        if (!tbody) return;

        const totalCols = CONFIG.columnas.length;
        const data = state.filtered;

        if (!data.length) {
            tbody.innerHTML =
                '<tr>' +
                    '<td colspan="' + totalCols + '" class="py-16 text-center">' +
                        '<div class="flex flex-col items-center gap-2">' +
                            '<i class="fas fa-search text-gray-300 text-4xl"></i>' +
                            '<p class="text-gray-500 text-sm font-medium">No hay datos para mostrar</p>' +
                        '</div>' +
                    '</td>' +
                '</tr>';
            updatePagination();
            return;
        }

        const startIndex = (state.page - 1) * state.perPage;
        const endIndex   = startIndex + state.perPage;
        const pageData   = data.slice(startIndex, endIndex);

        const fragment = document.createDocumentFragment();

        pageData.forEach((row, rowIndex) => {
            const globalIndex = startIndex + rowIndex;
            const tr = document.createElement('tr');
            tr.className = 'cursor-pointer transition-colors';
            tr.dataset.index = globalIndex;

            // Verificar si esta fila está seleccionada
            const isSelected = state.selectedRowIndex === globalIndex;
            const isEven = rowIndex % 2 === 0;

            // Aplicar estilos según selección y alternancia
            if (isSelected) {
                tr.classList.add('codificacion-row-selected');
            } else {
                tr.classList.add(...(isEven ? ['bg-white', 'hover:bg-gray-100'] : ['bg-gray-100', 'hover:bg-gray-200']));
            }

            // Evento click para seleccionar/deseleccionar
            tr.addEventListener('click', () => {
                // Si ya está seleccionada, deseleccionar
                if (state.selectedRowIndex === globalIndex) {
                    state.selectedRowIndex = null;
                    tr.classList.remove('codificacion-row-selected', 'bg-white', 'bg-gray-100', 'hover:bg-gray-100', 'hover:bg-gray-200');
                    tr.classList.add(...(rowIndex % 2 === 0 ? ['bg-white', 'hover:bg-gray-100'] : ['bg-gray-100', 'hover:bg-gray-200']));
                    tr.querySelectorAll('td').forEach(td => {
                        td.classList.remove('text-white');
                        td.classList.add('text-gray-700');
                    });
                    actualizarEstadoBotonReimprimir();
                    actualizarEstadoBotonRevivir();
                } else {
                    // Deseleccionar fila anterior si existe
                    const prevSelected = tbody.querySelector('tr.codificacion-row-selected');
                    if (prevSelected) {
                        const prevIdx = parseInt(prevSelected.dataset.index, 10);
                        const prevRowIdx = prevIdx - startIndex;
                        prevSelected.classList.remove('codificacion-row-selected', 'bg-white', 'bg-gray-100', 'hover:bg-gray-100', 'hover:bg-gray-200');
                        prevSelected.classList.add(...(prevRowIdx % 2 === 0 ? ['bg-white', 'hover:bg-gray-100'] : ['bg-gray-100', 'hover:bg-gray-200']));
                        prevSelected.querySelectorAll('td').forEach(td => {
                            td.classList.remove('text-white');
                            td.classList.add('text-gray-700');
                        });
                    }

                    // Seleccionar nueva fila: quitar fondos alternados y aplicar clase de selección
                    tr.classList.remove('bg-white', 'bg-gray-100', 'hover:bg-gray-100', 'hover:bg-gray-200');
                    tr.classList.add('codificacion-row-selected');
                    tr.querySelectorAll('td').forEach(td => {
                        td.classList.remove('text-gray-700');
                        td.classList.add('text-white');
                    });
                    state.selectedRowIndex = globalIndex;
                    actualizarEstadoBotonReimprimir();
                    actualizarEstadoBotonRevivir();
                }
            });

            CONFIG.columnas.forEach((col, colIdx) => {
                const segmentClass = (CONFIG.columnSegmentClass && CONFIG.columnSegmentClass[col]) ? CONFIG.columnSegmentClass[col] : '';
                const td = document.createElement('td');
                td.className = 'px-3 py-1.5 border-b border-gray-100 whitespace-nowrap text-[11px] column-' + colIdx + ' ' + segmentClass + ' ' +
                    (isSelected ? 'text-white' : 'text-gray-700');
                td.setAttribute('data-column', col);
                td.setAttribute('data-index', colIdx);
                const value = row[col] ?? '';
                td.textContent = formatDateOnly(value, col);
                tr.appendChild(td);
            });

            fragment.appendChild(tr);
        });

        tbody.innerHTML = '';
        tbody.appendChild(fragment);
        try {
            updatePinnedPositions();
            updateColumnHeaderIcons();
        } catch (e) {
            console.warn('updatePinnedPositions/updateColumnHeaderIcons:', e);
        }
        updatePagination();
        actualizarEstadoBotonReimprimir();
        actualizarEstadoBotonRevivir();
    }

    function updatePagination() {
        const total      = state.filtered.length;
        const totalPages = Math.max(1, Math.ceil(total / state.perPage));

        state.page = Math.min(state.page, totalPages);

        const start = total ? (state.page - 1) * state.perPage + 1 : 0;
        const end   = total ? Math.min(state.page * state.perPage, total) : 0;

        const currentEl    = $('#pagination-current');
        const totalPagesEl = $('#pagination-total-pages');
        const startEl      = $('#pagination-start');
        const endEl        = $('#pagination-end');
        const totalEl      = $('#pagination-total');

        if (currentEl)    currentEl.textContent    = state.page;
        if (totalPagesEl) totalPagesEl.textContent = totalPages;
        if (startEl)      startEl.textContent      = start.toLocaleString();
        if (endEl)        endEl.textContent        = end.toLocaleString();
        if (totalEl)      totalEl.textContent      = total.toLocaleString();

        const prev = $('#pagination-prev');
        const next = $('#pagination-next');

        if (prev) prev.disabled = state.page <= 1;
        if (next) next.disabled = state.page >= totalPages;
    }

    // =========================
    //   COLUMNAS FIJADAS
    // =========================
    function getColumnElements(index) {
        return $$('#mainTable .column-' + index);
    }

    function updatePinnedPositions() {
        const table = $('#mainTable');
        if (!table || !state.pinnedColumns || !state.pinnedColumns.length) {
            $$('#mainTable th[data-index], #mainTable td[data-index]').forEach(el => {
                el.classList.remove('codificacion-pinned');
                el.style.left = '';
                el.style.zIndex = '';
                if (el.tagName === 'TD') el.style.position = '';
                if (el.tagName === 'TH') el.style.top = '';
            });
            return;
        }
        let left = 0;
        state.pinnedColumns.forEach(idx => {
            const els = getColumnElements(idx);
            const th = els.find(el => el.tagName === 'TH');
            if (!th) return;
            const w = th.offsetWidth || 80;
            els.forEach(el => {
                el.classList.add('codificacion-pinned');
                el.style.left = left + 'px';
                el.style.position = 'sticky';
                if (el.tagName === 'TH') {
                    el.style.top = '0';
                    el.style.zIndex = '1200';
                } else {
                    el.style.zIndex = '30';
                }
            });
            left += w;
        });
        $$('#mainTable th[data-index], #mainTable td[data-index]').forEach(el => {
            const dataIndex = el.getAttribute('data-index');
            const idx = dataIndex !== null && dataIndex !== '' ? parseInt(dataIndex, 10) : NaN;
            if (Number.isNaN(idx) || !state.pinnedColumns.includes(idx)) {
                el.classList.remove('codificacion-pinned');
                el.style.left = '';
                el.style.zIndex = '';
                if (el.tagName === 'TD') el.style.position = '';
                if (el.tagName === 'TH') el.style.top = '';
            }
        });
    }

    function updateColumnHeaderIcons() {
        if (!CONFIG.columnas || !CONFIG.columnas.length) return;
        CONFIG.columnas.forEach((col, idx) => {
            const th = document.querySelector('#mainTable thead th.column-' + idx);
            if (!th) return;
            const field = col;
            const container = th.querySelector('.codificacion-header-icons');
            if (!container) return;
            let html = '';
            const hasFilter = (state.filtrosPorColumna || []).some(f => f.column === field);
            if (hasFilter) {
                html += '<button type="button" class="codificacion-header-icon" data-action="clear-filter" data-column="' + escapeHtml(field) + '" title="Quitar filtro"><i class="fas fa-filter"></i></button>';
            }
            if (state.pinnedColumns.includes(idx)) {
                html += '<button type="button" class="codificacion-header-icon" data-action="unpin" data-index="' + idx + '" title="Desfijar"><i class="fas fa-thumbtack"></i></button>';
            }
            container.innerHTML = html;
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // =========================
    //   FILTROS
    // =========================
    function aplicarFiltrosAND() {
        if (!state.data.length) {
            state.filtered = [];
            return;
        }

        let filtered = [...state.data];

        // Aplicar filtros antiguos (por índice de columna)
        if (state.filtros.length) {
            filtered = filtered.filter(row => {
                return state.filtros.every(f => {
                    const colName = CONFIG.columnas[f.columna];
                    if (!colName) return true;

                    const cell   = String(row[colName] ?? '').toLowerCase();
                    const needle = f.valor.toLowerCase().trim();
                    return cell.includes(needle);
                });
            });
        }

        // Aplicar filtros por columna (desde menú contextual)
        if (state.filtrosPorColumna && state.filtrosPorColumna.length) {
            const byColumn = {};
            state.filtrosPorColumna.forEach(f => {
                if (!byColumn[f.column]) byColumn[f.column] = [];
                byColumn[f.column].push(String(f.value || '').toLowerCase().trim());
            });
            filtered = filtered.filter(row => {
                return Object.entries(byColumn).every(([col, values]) => {
                    const cellVal = row[col];
                    const str = (cellVal != null ? String(cellVal) : '').toLowerCase().trim();
                    return values.includes(str);
                });
            });
        }

        state.filtered = filtered;

        // Mantener ordenamiento por Id descendente (más nuevos primero)
        state.filtered.sort((a, b) => {
            const idA = parseInt(a.Id) || 0;
            const idB = parseInt(b.Id) || 0;
            return idB - idA; // Descendente: mayor Id primero
        });

        state.page = 1;
        state.selectedRowIndex = null; // Limpiar selección al filtrar
        renderPage();
        updateFilterCount();
        actualizarEstadoBotonReimprimir();
        actualizarEstadoBotonRevivir();
    }

    function updateFilterCount() {
        const counter = document.getElementById('filter-count');
        if (!counter) return;

        const count = state.filtros.length + (state.filtrosPorColumna?.length || 0);
        if (count > 0) {
            counter.textContent = count;
            counter.classList.remove('hidden');
        } else {
            counter.classList.add('hidden');
        }
    }

    /**
     * Acción rápida: filtrar solo registros con OrdCompartida y OrdCompartidaLider llenos.
     */
    function aplicarAccionRapidaOrdCompartida() {
        if (!state.data.length) {
            showToast('Espera a que carguen los datos', 'warning');
            return;
        }

        state.filtered = state.data.filter(row => {
            const ordCompartida = row.OrdCompartida;
            const ordCompartidaLider = row.OrdCompartidaLider;
            const hasOrdCompartida = ordCompartida != null && ordCompartida !== '' && String(ordCompartida).trim() !== '';
            const hasOrdCompartidaLider = ordCompartidaLider != null && ordCompartidaLider !== '' && String(ordCompartidaLider).trim() !== '';
            return hasOrdCompartida && hasOrdCompartidaLider;
        });

        state.page = 1;
        state.selectedRowIndex = null;
        renderPage();
        updatePagination();
        updateFilterCount();
        actualizarEstadoBotonReimprimir();
        actualizarEstadoBotonRevivir();

        showToast(
            state.filtered.length
                ? state.filtered.length + ' de ' + state.data.length + ' registros con OrdCompartida'
                : 'No hay registros con OrdCompartida y OrdCompartidaLider llenos',
            state.filtered.length ? 'success' : 'warning'
        );
    }

    function filtrarCodificacion() {
        Swal.fire({
            html: `
                <div class="text-left">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-semibold text-gray-800">Filtrar datos</h2>
                        <button type="button" id="btn-close-modal" class="text-gray-400 hover:text-red-500 text-xl">&times;</button>
                    </div>

                    <div class="space-y-4">
                        <div class="rounded-lg border-2 border-amber-200 bg-amber-50 p-3">
                            <p class="text-sm font-semibold text-amber-900 mb-2">Acciones rápidas</p>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" id="btn-quick-ordcompartida" class="px-3 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 text-sm font-medium transition-colors flex items-center gap-2">
                                    <i class="fas fa-link"></i> Obtener OrdCompartida
                                </button>
                            </div>
                            <p class="text-xs text-amber-800 mt-1">Solo registros con OrdCompartida y OrdCompartidaLider llenos.</p>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-2">
                            <select
                                id="filtro-columna"
                                class="flex-1 px-3 py-2 border rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">Columna...</option>
                            </select>

                            <input
                                type="text"
                                id="filtro-valor"
                                class="flex-1 px-3 py-2 border rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Valor a buscar..."
                            >

                            <button
                                type="button"
                                id="btn-add-filter"
                                class="px-3 py-2 bg-blue-500 text-white rounded-md text-sm hover:bg-blue-600"
                            >
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>

                        <div id="modal-active-filters" class="space-y-2 hidden">
                            <p class="text-sm font-semibold text-gray-700">Filtros activos:</p>
                            <div id="modal-filters-list" class="flex flex-wrap gap-2"></div>
                        </div>

                        <div id="btn-clear-container" class="hidden">
                            <button
                                type="button"
                                id="btn-clear-filters"
                                class="w-full px-3 py-2 bg-red-500 text-white rounded-md text-sm hover:bg-red-600"
                            >
                                Limpiar todos los filtros
                            </button>
                        </div>
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCancelButton: false,
            width: 580,
            didOpen: () => {
                const btnQuickOrdCompartida = document.getElementById('btn-quick-ordcompartida');
                if (btnQuickOrdCompartida) {
                    btnQuickOrdCompartida.addEventListener('click', () => {
                        aplicarAccionRapidaOrdCompartida();
                        Swal.close();
                    });
                }

                // Poblar select columnas
                const colSelect = document.getElementById('filtro-columna');
                if (colSelect) {
                    CONFIG.columnas.forEach((col, idx) => {
                        const option = document.createElement('option');
                        option.value = idx;
                        option.textContent = col;
                        colSelect.appendChild(option);
                    });
                }

                const activeFilters = document.getElementById('modal-active-filters');
                const clearContainer = document.getElementById('btn-clear-container');
                if (state.filtros.length > 0 || (state.filtrosPorColumna && state.filtrosPorColumna.length > 0)) {
                    activeFilters?.classList.remove('hidden');
                    clearContainer?.classList.remove('hidden');
                }

                const closeBtn = document.getElementById('btn-close-modal');
                closeBtn?.addEventListener('click', () => Swal.close());

                document.getElementById('btn-add-filter')?.addEventListener('click', addFilterFromModal);
                document.getElementById('btn-clear-filters')?.addEventListener('click', () => {
                    state.filtros = [];
                    state.filtrosPorColumna = [];
                    aplicarFiltrosAND();
                    state.page = 1;
                    renderPage();
                    updateColumnHeaderIcons();
                    renderModalFilters();
                    updateFilterCount();
                    Swal.close();
                });

                const valorInput = document.getElementById('filtro-valor');
                valorInput?.addEventListener('keydown', e => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        addFilterFromModal();
                    }
                });

                renderModalFilters();
            }
        });
    }

    function addFilterFromModal() {
        const colSelect  = document.getElementById('filtro-columna');
        const valueInput = document.getElementById('filtro-valor');
        if (!colSelect || !valueInput) return;

        const columna = parseInt(colSelect.value, 10);
        const valor   = valueInput.value.trim();

        if (Number.isNaN(columna)) {
            showToast('Selecciona una columna', 'warning');
            return;
        }
        if (!valor) {
            showToast('Ingresa un valor para filtrar', 'warning');
            return;
        }

        const exists = state.filtros.some(
            f => f.columna === columna && f.valor.toLowerCase() === valor.toLowerCase()
        );
        if (exists) {
            showToast('Este filtro ya existe', 'warning');
            return;
        }

        state.filtros.push({ columna, valor });
        valueInput.value = '';
        colSelect.selectedIndex = 0;

        aplicarFiltrosAND();
        renderModalFilters();

        if (!state.filtered.length) {
            showToast('No se encontraron resultados', 'warning');
        } else {
            showToast(state.filtered.length + ' registros encontrados', 'success');
        }
    }

    function renderModalFilters() {
        const container = document.getElementById('modal-active-filters');
        const list      = document.getElementById('modal-filters-list');
        const clearBox  = document.getElementById('btn-clear-container');

        if (!container || !list) return;

        const totalFiltros = state.filtros.length + (state.filtrosPorColumna?.length || 0);
        if (totalFiltros === 0) {
            container.classList.add('hidden');
            clearBox?.classList.add('hidden');
            return;
        }

        container.classList.remove('hidden');
        clearBox?.classList.remove('hidden');

        let html = '';
        // Filtros antiguos (por índice)
        state.filtros.forEach((filtro, index) => {
            const colName = CONFIG.columnas[filtro.columna] || 'Columna';
            html += '<span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-[10px]">' +
                '<span class="font-semibold">' + escapeHtml(colName) + ':</span>' +
                '<span>"' + escapeHtml(filtro.valor) + '"</span>' +
                '<button type="button" class="ml-1 hover:text-red-600 font-bold" onclick="removeFilterFromModal(' + index + ')">' +
                    '&times;' +
                '</button>' +
            '</span>';
        });
        // Filtros por columna (desde menú contextual)
        if (state.filtrosPorColumna && state.filtrosPorColumna.length) {
            state.filtrosPorColumna.forEach((filtro, index) => {
                const colLabel = CONFIG.columnLabels[filtro.column] || filtro.column;
                html += '<span class="inline-flex items-center gap-1 px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-[10px]">' +
                    '<i class="fas fa-filter text-yellow-600"></i>' +
                    '<span class="font-semibold">' + escapeHtml(colLabel) + ':</span>' +
                    '<span>"' + escapeHtml(filtro.value) + '"</span>' +
                    '<button type="button" class="ml-1 hover:text-red-600 font-bold" onclick="removeFilterPorColumna(' + index + ')">' +
                        '&times;' +
                    '</button>' +
                '</span>';
            });
        }
        list.innerHTML = html;
    }

    function removeFilterPorColumna(index) {
        state.filtrosPorColumna.splice(index, 1);
        aplicarFiltrosAND();
        state.page = 1;
        renderPage();
        updateColumnHeaderIcons();
        renderModalFilters();
        updateFilterCount();
        showToast('Filtro eliminado', 'info');
    }

    function removeFilterFromModal(index) {
        state.filtros.splice(index, 1);
        aplicarFiltrosAND();
        renderModalFilters();
        showToast('Filtro eliminado', 'info');
    }

    function limpiarFiltrosCodificacion() {
        state.filtros = [];
        state.filtrosPorColumna = [];
        aplicarFiltrosAND();
        state.page = 1;
        renderPage();
        updateColumnHeaderIcons();
        updateFilterCount();
        showToast('Filtros limpiados', 'info');
    }


    // =========================
    //   MENÚ CONTEXTUAL EN ENCABEZADOS
    // =========================
    const menu = $('#codificacionContextMenuHeader');
    let menuColumnIndex = null;
    let menuColumnField = null;

    function hideContextMenu() {
        if (menu) {
            menu.classList.add('hidden');
            menu.style.display = 'none';
        }
        menuColumnIndex = null;
        menuColumnField = null;
    }

    function showContextMenu(e, columnIndex, columnField) {
        menuColumnIndex = columnIndex;
        menuColumnField = columnField;
        if (!menu) return;
        const fijarLabel = $('#codificacionCtxFijarLabel');
        if (fijarLabel) fijarLabel.textContent = state.pinnedColumns.includes(columnIndex) ? 'Desfijar' : 'Fijar';
        menu.style.left = e.clientX + 'px';
        menu.style.top = e.clientY + 'px';
        menu.style.display = 'block';
        const rect = menu.getBoundingClientRect();
        if (rect.right > window.innerWidth) menu.style.left = (e.clientX - rect.width) + 'px';
        if (rect.bottom > window.innerHeight) menu.style.top = (e.clientY - rect.height) + 'px';
        menu.classList.remove('hidden');
    }

    function openFilterModal(columnIndex, columnField) {
        const columnLabel = CONFIG.columnLabels[columnField] || columnField;
        const valueCounts = new Map();
        state.filtered.forEach(row => {
            const v = row[columnField];
            const str = (v != null ? String(v) : '').trim();
            if (!valueCounts.has(str)) valueCounts.set(str, { raw: str, count: 0 });
            valueCounts.get(str).count++;
        });
        const uniqueValues = Array.from(valueCounts.keys()).filter(Boolean).sort();
        if (uniqueValues.length === 0) {
            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'info', title: 'Sin valores', text: 'No hay valores para filtrar en esta columna.' });
            return;
        }
        const currentForColumn = (state.filtrosPorColumna || []).filter(f => f.column === columnField).map(f => f.value);

        let html = '<div class="text-left"><p class="text-sm text-gray-600 mb-4">Filtrar por: <strong>' + escapeHtml(columnLabel) + '</strong></p>';
        html += '<div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">';
        html += '<div class="mb-2 pb-2 border-b border-gray-200"><input type="text" id="codificacionFilterSearch" placeholder="Buscar..." class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"></div>';
        html += '<div id="codificacionFilterCheckboxes" class="space-y-1">';
        uniqueValues.forEach(value => {
            const entry = valueCounts.get(value);
            const count = entry ? entry.count : 0;
            const checked = currentForColumn.includes(value) ? ' checked' : '';
            html += '<label class="flex items-center justify-between p-2 hover:bg-gray-50 rounded cursor-pointer"><div class="flex items-center gap-2">';
            html += '<input type="checkbox" class="codificacion-filter-cb w-4 h-4 text-blue-600" value="' + escapeHtml(value) + '"' + checked + '>';
            html += '<span class="text-sm text-gray-700">' + escapeHtml(value) + '</span></div><span class="text-xs text-gray-500">(' + count + ')</span></label>';
        });
        html += '</div></div></div>';

        Swal.fire({
            title: 'Filtrar columna',
            html: html,
            showCancelButton: true,
            confirmButtonText: 'Aplicar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3b82f6',
            width: '500px',
            didOpen: () => {
                const search = document.getElementById('codificacionFilterSearch');
                const container = document.getElementById('codificacionFilterCheckboxes');
                if (search && container) {
                    search.addEventListener('input', () => {
                        const term = search.value.toLowerCase();
                        container.querySelectorAll('label').forEach(lab => {
                            const text = (lab.textContent || '').toLowerCase();
                            lab.style.display = text.includes(term) ? '' : 'none';
                        });
                    });
                }
            },
            preConfirm: () => {
                const checked = $$('.codificacion-filter-cb:checked').map(cb => cb.value);
                return checked;
            }
        }).then(result => {
            if (!result.isConfirmed) return;
            state.filtrosPorColumna = (state.filtrosPorColumna || []).filter(f => f.column !== columnField);
            (result.value || []).forEach(v => {
                state.filtrosPorColumna.push({ column: columnField, value: v });
            });
            aplicarFiltrosAND();
            state.page = 1;
            renderPage();
            updateFilterCount();
        });
    }

    // =========================
    //   INIT
    // =========================
    function initPaginationEvents() {
        const prev = $('#pagination-prev');
        const next = $('#pagination-next');

        if (prev) {
            prev.addEventListener('click', () => {
                if (state.page > 1 && !state.loading) {
                    state.page--;
                    renderPage();
                }
            });
        }

        if (next) {
            next.addEventListener('click', () => {
                const totalPages = Math.max(1, Math.ceil(state.filtered.length / state.perPage));
                if (state.page < totalPages && !state.loading) {
                    state.page++;
                    renderPage();
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initPaginationEvents();
        loadData();
        window.addEventListener('resize', () => updatePinnedPositions());

        // Eventos del menú contextual en encabezados
        const thead = $('#mainTable thead');
        if (thead) {
            thead.addEventListener('contextmenu', (e) => {
                const th = e.target.closest('th');
                if (!th) return;
                e.preventDefault();
                e.stopPropagation();
                const columnIndex = parseInt(th.getAttribute('data-index'), 10);
                const columnField = th.getAttribute('data-column');
                if (Number.isNaN(columnIndex) || !columnField) return;
                showContextMenu(e, columnIndex, columnField);
            });
        }

        document.addEventListener('click', (e) => {
            if (menu && !menu.classList.contains('hidden') && !menu.contains(e.target)) hideContextMenu();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') hideContextMenu();
        });

        // Clic en iconos del encabezado: quitar filtro o desfijar
        const mainTable = $('#mainTable');
        if (mainTable) {
            mainTable.addEventListener('click', (e) => {
                const btn = e.target.closest('.codificacion-header-icon');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                const action = btn.getAttribute('data-action');
                if (action === 'clear-filter') {
                    const field = btn.getAttribute('data-column');
                    if (field) {
                        state.filtrosPorColumna = (state.filtrosPorColumna || []).filter(f => f.column !== field);
                        aplicarFiltrosAND();
                        state.page = 1;
                        renderPage();
                        updateFilterCount();
                    }
                } else if (action === 'unpin') {
                    const idx = parseInt(btn.getAttribute('data-index'), 10);
                    if (!Number.isNaN(idx)) {
                        const i = state.pinnedColumns.indexOf(idx);
                        if (i >= 0) {
                            state.pinnedColumns.splice(i, 1);
                            updatePinnedPositions();
                            updateColumnHeaderIcons();
                        }
                    }
                }
            });
            mainTable.addEventListener('contextmenu', (e) => {
                if (e.target.closest('.codificacion-header-icon')) e.stopPropagation();
            });
        }

        $('#codificacionCtxFiltrar')?.addEventListener('click', () => {
            const idx = menuColumnIndex;
            const field = menuColumnField;
            hideContextMenu();
            if (idx != null && field) openFilterModal(idx, field);
        });

        $('#codificacionCtxFijar')?.addEventListener('click', () => {
            const idx = menuColumnIndex;
            hideContextMenu();
            if (idx == null) return;
            const i = state.pinnedColumns.indexOf(idx);
            if (i >= 0) {
                state.pinnedColumns.splice(i, 1);
            } else {
                state.pinnedColumns.push(idx);
                state.pinnedColumns.sort((a, b) => a - b);
            }
            updatePinnedPositions();
            updateColumnHeaderIcons();
        });
    });

    // =========================
    //   ACTUALIZAR ESTADO BOTÓN REIMPRIMIR Y BALANCEAR
    // =========================
    function actualizarEstadoBotonLMat() {
        const btnLMat = document.getElementById('btn-lmat');
        if (!btnLMat) return;

        const registroSeleccionado = state.selectedRowIndex !== null
            && state.selectedRowIndex !== undefined
            ? state.filtered[state.selectedRowIndex]
            : null;

        const tieneFilaSeleccionada = !!registroSeleccionado;
        const tieneTelar = tieneFilaSeleccionada
            && String(registroSeleccionado?.TelarId ?? '').trim() !== '';

        btnLMat.disabled = !(tieneFilaSeleccionada && tieneTelar);
        btnLMat.title = !tieneFilaSeleccionada
            ? 'Selecciona una fila'
            : (!tieneTelar
                ? 'La fila seleccionada no tiene telar'
                : 'Lista de materiales');
    }

    function actualizarEstadoBotonReimprimir() {
        const btnReimprimir = document.getElementById('btn-reimprimir-seleccionado');
        actualizarEstadoBotonLMat();
        if (!btnReimprimir) return;

        if (state.selectedRowIndex === null || state.selectedRowIndex === undefined) {
            btnReimprimir.disabled = true;
            actualizarEstadoBotonBalancear();
            return;
        }

        const registroSeleccionado = state.filtered[state.selectedRowIndex];
        if (!registroSeleccionado) {
            btnReimprimir.disabled = true;
            actualizarEstadoBotonBalancear();
            return;
        }

        // Verificar que tenga UsuarioCrea (indica que el registro fue creado)
        const usuarioCrea = registroSeleccionado.UsuarioCrea;
        const puedeReimprimir = usuarioCrea !== null && usuarioCrea !== undefined && usuarioCrea !== '';

        btnReimprimir.disabled = !puedeReimprimir || !registroSeleccionado.Id;
        actualizarEstadoBotonBalancear();
    }

    function actualizarEstadoBotonBalancear() {
        const btnBalancear = document.getElementById('btn-balancear');
        if (!btnBalancear) return;

        if (state.selectedRowIndex === null || state.selectedRowIndex === undefined) {
            btnBalancear.disabled = true;
            return;
        }

        const registroSeleccionado = state.filtered[state.selectedRowIndex];
        if (!registroSeleccionado) {
            btnBalancear.disabled = true;
            return;
        }

        const ordCompartida = registroSeleccionado.OrdCompartida;
        const tieneOrdCompartida = ordCompartida != null && ordCompartida !== '' && String(ordCompartida).trim() !== '';

        btnBalancear.disabled = !tieneOrdCompartida;
    }

    function actualizarEstadoBotonRevivir() {
        const btn = document.getElementById('btn-revivir-programa');
        if (!btn) return;

        if (state.selectedRowIndex === null || state.selectedRowIndex === undefined) {
            btn.disabled = true;
            return;
        }

        const r = state.filtered[state.selectedRowIndex];
        if (!r || !r.Id) {
            btn.disabled = true;
            return;
        }

        const orden = String(r.OrdenTejido ?? '').trim();
        const depto = String(r.Departamento ?? '').trim();
        const telar = String(r.TelarId ?? '').trim();

        btn.disabled = !(orden !== '' && depto !== '' && telar !== '');
    }

    // =========================
    //   REVIVIR ORDEN A PROGRAMA DE TEJIDO
    // =========================
    async function revivirOrdenAlPrograma() {
        if (state.selectedRowIndex === null || state.selectedRowIndex === undefined) {
            showToast('Selecciona un registro primero', 'warning');
            return;
        }

        const r = state.filtered[state.selectedRowIndex];
        if (!r || !r.Id) {
            showToast('No se pudo obtener el registro seleccionado', 'error');
            return;
        }

        const orden = String(r.OrdenTejido ?? '').trim();
        const depto = String(r.Departamento ?? '').trim();
        const telar = String(r.TelarId ?? '').trim();
        if (!orden || !depto || !telar) {
            showToast('El registro debe tener OrdenTejido, Departamento y TelarId', 'warning');
            return;
        }

        const confirm = await Swal.fire({
            title: 'Revivir a programa de tejido',
            html: 'Se pondrá <strong>FechaFinaliza</strong> en <em>null</em> en codificación y se creará la orden en el programa (telar ' + escapeHtml(telar) + ').',
            icon: 'question',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'Al final de cola',
            denyButtonText: 'Poner en proceso',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d97706',
            denyButtonColor: '#2563eb',
        });

        if (confirm.isDismissed) return;

        const enProceso = confirm.isDenied === true;

        try {
            Swal.fire({
                title: 'Procesando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
            });

            const resp = await fetch('/planeacion/codificacion/api/revivir-programa', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
                body: JSON.stringify({
                    cat_id: parseInt(r.Id, 10),
                    en_proceso: enProceso,
                }),
            });

            const json = await resp.json().catch(() => ({}));
            Swal.close();

            if (!json.s) {
                const msg = json.e || json.message || 'Error al revivir la orden';
                const errDetail = json.errors && typeof json.errors === 'object'
                    ? Object.values(json.errors).flat().join(' ')
                    : '';
                showToast(errDetail || msg, 'error');
                return;
            }

            showToast('Orden creada en programa (Id ' + (json.d && json.d.programa_id ? json.d.programa_id : '') + ')', 'success');
        } catch (e) {
            Swal.close();
            showToast(e.message || 'Error de red', 'error');
        }
    }

    // =========================
    //   BALANCEAR - VER REGISTROS COMPARTIDOS
    // =========================
    async function abrirModalBalancear() {
        if (state.selectedRowIndex === null || state.selectedRowIndex === undefined) {
            showToast('Selecciona un registro primero', 'warning');
            return;
        }

        const registroSeleccionado = state.filtered[state.selectedRowIndex];
        if (!registroSeleccionado) {
            showToast('No se pudo obtener el registro seleccionado', 'error');
            return;
        }

        const ordCompartida = registroSeleccionado.OrdCompartida;
        const tieneOrdCompartida = ordCompartida != null && ordCompartida !== '' && String(ordCompartida).trim() !== '';

        if (!tieneOrdCompartida) {
            showToast('El registro seleccionado no tiene OrdCompartida', 'warning');
            return;
        }

        try {
            Swal.fire({
                title: 'Cargando registros compartidos...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const resp = await fetch(`/planeacion/codificacion/api/registros-ord-compartida/${encodeURIComponent(ordCompartida)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await resp.json();

            Swal.close();

            if (!data.success || !Array.isArray(data.registros)) {
                showToast(data.message || 'Error al cargar registros compartidos', 'error');
                return;
            }

            const registros = data.registros;
            const cantidad = registros.length;
            const tieneLideres = data.tieneLideres === true;

            const escapeHtml = (t) => {
                const d = document.createElement('div');
                d.textContent = t;
                return d.innerHTML;
            };

            let html = '<div class="text-left">';
            html += '<p class="text-sm text-gray-600 mb-3">OrdCompartida: <strong>' + escapeHtml(String(ordCompartida)) + '</strong> · ' + cantidad + ' registro(s)</p>';
            html += '<div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg">';
            html += '<table class="w-full text-sm">';
            html += '<thead class="bg-blue-500 text-white sticky top-0"><tr>';
            if (tieneLideres) {
                html += '<th class="px-3 py-2 text-left">Líder</th>';
            }
            html += '<th class="px-3 py-2 text-left">Orden</th>';
            html += '<th class="px-3 py-2 text-left">Telar</th>';
            html += '<th class="px-3 py-2 text-left">Modelo</th>';
            html += '<th class="px-3 py-2 text-left">Cantidad</th>';
            html += '<th class="px-3 py-2 text-left">Producción</th>';
            html += '<th class="px-3 py-2 text-left">Saldos</th>';
            html += '<th class="px-3 py-2 text-left">Total Segundas</th>';
            html += '</tr></thead><tbody>';

            registros.forEach(r => {
                const esLider = r.OrdCompartidaLider === 1 || r.OrdCompartidaLider === true || r.OrdCompartidaLider === '1';
                const rowClass = esLider ? 'bg-amber-100 font-semibold' : 'bg-white hover:bg-gray-50';
                html += '<tr class="' + rowClass + '">';
                if (tieneLideres) {
                    html += '<td class="px-3 py-2">' + (esLider ? '<i class="fas fa-crown text-amber-600" title="OrdCompartidaLider"></i>' : '') + '</td>';
                }
                html += '<td class="px-3 py-2">' + escapeHtml(String(r.OrdenTejido ?? '')) + '</td>';
                html += '<td class="px-3 py-2">' + escapeHtml(String(r.TelarId ?? '')) + '</td>';
                html += '<td class="px-3 py-2">' + escapeHtml(String(r.Nombre ?? r.ClaveModelo ?? '')) + '</td>';
                html += '<td class="px-3 py-2">' + escapeHtml(String(r.Cantidad ?? '')) + '</td>';
                html += '<td class="px-3 py-2">' + escapeHtml(String(r.Produccion ?? '')) + '</td>';
                html += '<td class="px-3 py-2">' + escapeHtml(String(r.Saldos ?? '')) + '</td>';
                html += '<td class="px-3 py-2">' + escapeHtml(String(r.TotalSegundas ?? '')) + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div></div>';

            Swal.fire({
                title: 'Registros compartidos',
                html: html,
                width: '880px',
                showConfirmButton: true,
                confirmButtonText: 'Cerrar',
                confirmButtonColor: '#3b82f6'
            });
        } catch (error) {
            Swal.close();
            showToast(error.message || 'Error al cargar registros compartidos', 'error');
        }
    }

    // =========================
    //   REIMPRIMIR ORDEN SELECCIONADA
    // =========================
    function reimprimirOrdenSeleccionada() {
        if (state.selectedRowIndex === null || state.selectedRowIndex === undefined) {
            showToast('Debes seleccionar un registro primero', 'warning');
            return;
        }

        const registroSeleccionado = state.filtered[state.selectedRowIndex];
        if (!registroSeleccionado || !registroSeleccionado.Id) {
            showToast('No se pudo obtener el ID del registro seleccionado', 'error');
            return;
        }

        // Verificar que tenga UsuarioCrea (indica que el registro fue creado)
        const usuarioCrea = registroSeleccionado.UsuarioCrea;
        const puedeReimprimir = usuarioCrea !== null && usuarioCrea !== undefined && usuarioCrea !== '';

        if (!puedeReimprimir) {
            showToast('Este registro no puede ser reimpreso porque no tiene un usuario de creación asignado', 'warning');
            return;
        }

        reimprimirOrden(registroSeleccionado.Id);
    }

    // =========================
    //   REIMPRIMIR ORDEN POR ID
    // =========================
    async function reimprimirOrden(id) {
        if (!id) {
            showToast('ID de orden no válido', 'error');
            return;
        }

        try {
            // Mostrar loading
            Swal.fire({
                title: 'Generando orden de cambio...',
                text: 'Por favor espera',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Llamar a la ruta de reimpresión
            const url = `/planeacion/programa-tejido/reimprimir-ordenes/${id}`;

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                }
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Error ${response.status}: ${response.statusText}`);
            }

            // Obtener el blob del Excel
            const blob = await response.blob();

            // Crear URL temporal y descargar
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = downloadUrl;
            a.download = `REIMPRESION_ORDEN_${id}_${new Date().toISOString().slice(0,10)}.xlsx`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(downloadUrl);

            Swal.close();
            showToast('Orden reimpresa correctamente', 'success');
        } catch (error) {
            Swal.close();
            showToast(error.message || 'Error al reimprimir la orden', 'error');
            console.error('Error al reimprimir orden:', error);
        }
    }

    // =========================
    //   EXPOSE GLOBAL
    // =========================
    window.mostrarAlertaNavbar         = mostrarAlertaNavbar;
    window.mostrarModalLMat            = mostrarModalLMat;
    window.filtrarCodificacion         = filtrarCodificacion;
    window.limpiarFiltrosCodificacion  = limpiarFiltrosCodificacion;
    window.removeFilterFromModal       = removeFilterFromModal;
    window.removeFilterPorColumna      = removeFilterPorColumna;
    window.loadData                    = loadData;
    window.reimprimirOrden              = reimprimirOrden;
    window.revivirOrdenAlPrograma       = revivirOrdenAlPrograma;
    window.reimprimirOrdenSeleccionada  = reimprimirOrdenSeleccionada;
    window.abrirModalBalancear          = abrirModalBalancear;
})();
