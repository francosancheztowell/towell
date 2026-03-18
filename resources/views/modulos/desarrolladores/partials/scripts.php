<script>
document.addEventListener('DOMContentLoaded', function () {
    const DETALLE_EMPTY_MSG = 'Selecciona una producción para ver los detalles';
    const MATERIAL_ROUTES = {
        calibres: "{{ route('tejido.produccion.reenconado.calibres') }}",
        fibras: "{{ route('tejido.produccion.reenconado.fibras') }}",
        colores: "{{ route('tejido.produccion.reenconado.colores') }}"
    };

    // ── Refs DOM ──────────────────────────────────────────────────────────
    const els = {
        selectTelar:        document.getElementById('telarOperador'),
        tablaProducciones:  document.getElementById('tablaProducciones'),
        filtroSoloConOrden:  document.getElementById('filtroSoloConOrden'),
        filtroOrdenContainer: document.getElementById('filtroOrdenContainer'),
        msgValidacionOrden:   document.getElementById('msgValidacionOrden'),
        bodyProducciones:   document.getElementById('bodyProducciones'),
        bodyDetallesOrden:  document.getElementById('bodyDetallesOrden'),
        noDataMessage:      document.getElementById('noDataMessage'),
        formContainer:      document.getElementById('formContainer'),
        inputTelarId:       document.getElementById('inputTelarId'),
        inputNoProduccion:  document.getElementById('inputNoProduccion'),
        formTelarId:        document.getElementById('formTelarId'),
        formNoProduccion:   document.getElementById('formNoProduccion'),
        formNombreProducto: document.getElementById('formNombreProducto'),
        btnCancelar:        document.getElementById('btnCancelarFormulario'),
        btnGuardar:         document.querySelector('#formDesarrollador button[type="submit"]'),
        form:               document.getElementById('formDesarrollador'),
        totalPasadasDibujo: document.getElementById('TotalPasadasDibujo'),
        selectJulioRizo:    document.getElementById('NumeroJulioRizo'),
        selectJulioPie:     document.getElementById('NumeroJulioPie'),
        inputDesperdicio:   document.getElementById('DesperdicioTrama'),
        modalPasadas:       document.getElementById('modalPasadas'),
        modalReprogramar:       document.getElementById('modalReprogramar'),
        modalReprogramarOrden:  document.getElementById('modalReprogramarOrden'),
        modalReprogramarMensaje: document.getElementById('modalReprogramarMensaje'),
        btnReprogramarSiguiente: document.getElementById('btnReprogramarSiguiente'),
        btnReprogramarUltimo:   document.getElementById('btnReprogramarUltimo'),
        btnModalAceptar:    document.getElementById('modalPasadasAceptar'),
        btnModalCancelar:   document.getElementById('modalPasadasCancelar'),
        btnAgregarFila:     document.getElementById('btnAgregarFilaDetalle'),
        codificacionInputs: document.querySelectorAll('.codificacion-char'),
        codificacionHidden: document.getElementById('CodificacionModelo'),
        codificacionNoData: document.getElementById('codificacionNoData'),
        codificacionSuffix: document.getElementById('codificacionSuffix'),
        resumen: {
            JulioRizo:       document.getElementById('resumenJulioRizo'),
            JulioPie:        document.getElementById('resumenJulioPie'),
            EfiInicial:      document.getElementById('resumenEfiInicial'),
            EfiFinal:        document.getElementById('resumenEfiFinal'),
            DesperdicioTrama:document.getElementById('resumenDesperdicioTrama'),
        },
        formJulioRizoInfo: document.getElementById('formJulioRizoInfo'),
        formJulioPieInfo:  document.getElementById('formJulioPieInfo'),
        ordenEnProcesoBanner: document.getElementById('ordenEnProcesoBanner'),
        ordenEnProcesoNum:   document.getElementById('ordenEnProcesoNum'),
        ordenEnProcesoFecha: document.getElementById('ordenEnProcesoFecha'),
        ordenEnProcesoNombre: document.getElementById('ordenEnProcesoNombre'),
        ordenEnProcesoTelar: document.getElementById('ordenEnProcesoTelar'),
        modalReprogramarLoading: document.getElementById('modalReprogramarLoading'),
        btnFinalizarOrden:   document.getElementById('btnFinalizarOrden'),
        btnRepSiguiente:    document.getElementById('btnRepSiguiente'),
        btnRepFinal:        document.getElementById('btnRepFinal'),
    };

    // ── Estado ─────────────────────────────────────────────────────────────
    const state = {
        salonTejido: '',
        tamanoClave: '',
        noProduccionActual: '',
        nombreProductoActual: '',
        ordenEnProceso: '',
        ordenEnProcesoNombre: '',
        reprogramarOrden: null,
        reprogramarAccion: null,
        contadorFilasNuevas: 0,
        omitirConfirmacionPasadas: false,
    };

    // ── Handlers de botones del banner ───────────────────────────────────────
    els.btnFinalizarOrden?.addEventListener('click', function () {
        if (!state.ordenEnProceso) return;
        if (confirm('¿Finalizar la orden "' + state.ordenEnProceso + '"? Quedara como proceso nulo.')) {
            alert('Orden "' + state.ordenEnProceso + '" finalizada. Proceso nulo.');
            // TODO: Call endpoint to finalize
        }
    });

    els.btnRepSiguiente?.addEventListener('click', function () {
        if (!state.ordenEnProceso) return;
        alert('La orden "' + state.ordenEnProceso + '" se movera al siguiente.');
        // TODO: Call endpoint to reprogramar siguiente
    });

    els.btnRepFinal?.addEventListener('click', function () {
        if (!state.ordenEnProceso) return;
        alert('La orden "' + state.ordenEnProceso + '" se movera al final.');
        // TODO: Call endpoint to reprogramar final
    });

    // ── Listener para telar-destino selects ─────────────────────────────────
    function setupTelarDestinoListeners() {
        document.querySelectorAll('.telar-destino-select').forEach(function(select) {
            select.addEventListener('change', function() {
                const hasDestino = this.value && this.value !== '';
                // Mostrar botones de reprogramar si hay destino seleccionado
                if (els.btnRepSiguiente) els.btnRepSiguiente.classList.toggle('hidden', !hasDestino);
                if (els.btnRepFinal) els.btnRepFinal.classList.toggle('hidden', !hasDestino);
            });
        });
    }

    document.getElementById('modalReprogramarCancelar')?.addEventListener('click', function () {
        els.modalReprogramar?.classList.add('hidden');
    });

    // ── Utilidades ────────────────────────────────────────────────────────
    function spinnerHtml(colspan, mensaje) {
        return `<tr><td colspan="${colspan}" class="px-3 py-3 text-center text-gray-500">
            <svg class="animate-spin h-5 w-5 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="mt-2">${mensaje}</p>
        </td></tr>`;
    }

    function emptyRowHtml(colspan, mensaje) {
        return `<tr><td colspan="${colspan}" class="px-6 py-4 text-center text-gray-500 text-sm">${mensaje}</td></tr>`;
    }

    function parseDestinoValue(value) {
        const [salon = '', telar = ''] = String(value || '').split('|');
        return { salon: salon.trim(), telar: telar.trim() };
    }

    function setSelectValue(select, value) {
        if (!select) return;
        if (value === null || value === undefined || value === '') {
            const placeholder = select.querySelector('option[disabled]');
            placeholder ? (placeholder.selected = true) : (select.value = '');
            return;
        }
        const val = String(value);
        let option = Array.from(select.options).find(o => o.value === val);
        if (!option) { option = new Option(val, val); select.add(option); }
        option.selected = true;
    }

    function resetJulioSelect(select, placeholder = 'Selecciona un Julio') {
        if (!select) return;
        select.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        option.disabled = true;
        option.selected = true;
        select.appendChild(option);
    }

    function populateJulioSelect(select, items, placeholder = 'Selecciona un Julio') {
        resetJulioSelect(select, placeholder);
        (items || []).forEach(item => {
            const noJulio = String(item?.NoJulio ?? '').trim();
            if (!noJulio) return;
            const option = new Option(noJulio, noJulio);
            option.dataset.inventsizeid = item?.InventSizeId ?? '';
            option.dataset.configid = item?.ConfigId ?? '';
            select.appendChild(option);
        });
    }

    function checkFormValidity() {
        if (!els.form || !els.btnGuardar) return;
        
        let isValid = true;
        const requiredElements = els.form.querySelectorAll('[required]');
        
        requiredElements.forEach(el => {
            if (!el.value || el.value.trim() === '') {
                isValid = false;
            }
        });

        if (isValid) {
            els.btnGuardar.disabled = false;
            els.btnGuardar.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            els.btnGuardar.disabled = true;
            els.btnGuardar.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }

    async function fetchJson(url, params = {}) {
        const query = new URLSearchParams(params);
        const fullUrl = query.toString() ? `${url}?${query}` : url;
        const response = await fetch(fullUrl);
        if (!response.ok) throw new Error(`Request failed: ${response.status}`);
        return response.json();
    }

    // ── Selectores Numéricos ──────────────────────────────────────────────
    const NumberSelectorManager = (() => {
        const selectors = [];
        let documentListenerAttached = false;

        function closeAll() {
            selectors.forEach(s => s.optionsWrapper.classList.add('hidden'));
        }

        function build(track, min, max, step) {
            track.innerHTML = '';
            for (let v = min; v <= max; v += step) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.dataset.value = String(v);
                btn.textContent = String(v);
                btn.className = 'number-option shrink-0 px-3 py-2 text-sm font-semibold border border-gray-300 rounded-md bg-white hover:bg-blue-50 focus:outline-none focus:ring-1 focus:ring-blue-500';
                track.appendChild(btn);
            }
        }

        function init() {
            document.querySelectorAll('[data-number-selector]').forEach(wrapper => {
                if (wrapper.dataset.selectorInitialized === 'true') return;

                const hiddenInput = wrapper.querySelector('input[type="number"]');
                const triggerBtn = wrapper.querySelector('.number-selector-btn');
                const valueSpan = wrapper.querySelector('.number-selector-value');
                const optionsWrapper = wrapper.querySelector('.number-selector-options');
                const track = wrapper.querySelector('.number-selector-track');
                if (!hiddenInput || !triggerBtn || !valueSpan || !optionsWrapper || !track) return;

                const min = parseInt(wrapper.dataset.min ?? hiddenInput.min ?? '0', 10);
                const max = parseInt(wrapper.dataset.max ?? hiddenInput.max ?? '100', 10);
                const step = parseInt(wrapper.dataset.step ?? hiddenInput.step ?? '1', 10);
                build(track, min, max, step);

                const setValue = (value) => {
                    hiddenInput.value = value;
                    valueSpan.textContent = value;
                    valueSpan.classList.replace('text-gray-400', 'text-blue-600');
                    track.querySelectorAll('.number-option').forEach(opt => {
                        const isActive = opt.dataset.value === String(value);
                        opt.classList.toggle('bg-blue-600', isActive);
                        opt.classList.toggle('text-white', isActive);
                        opt.classList.toggle('border-blue-600', isActive);
                    });
                    optionsWrapper.classList.add('hidden');
                    checkFormValidity();
                };

                const reset = () => {
                    hiddenInput.value = '';
                    valueSpan.textContent = 'Selecciona';
                    valueSpan.classList.replace('text-blue-600', 'text-gray-400');
                    track.querySelectorAll('.number-option').forEach(opt => {
                        opt.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
                    });
                    optionsWrapper.classList.add('hidden');
                    checkFormValidity();
                };

                triggerBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const shouldOpen = optionsWrapper.classList.contains('hidden');
                    closeAll();
                    if (!shouldOpen) return;
                    optionsWrapper.classList.remove('hidden');
                    const suggested = wrapper.dataset.suggested;
                    if (suggested && hiddenInput.value === '') {
                        setTimeout(() => {
                            const el = track.querySelector(`[data-value="${suggested}"]`);
                            if (!el) return;
                            el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
                            el.classList.add('ring-2', 'ring-yellow-400');
                            setTimeout(() => el.classList.remove('ring-2', 'ring-yellow-400'), 1500);
                        }, 100);
                    }
                });

                track.addEventListener('click', (e) => {
                    const opt = e.target.closest('.number-option');
                    if (!opt) return;
                    e.preventDefault();
                    setValue(opt.dataset.value);
                });

                hiddenInput.value !== '' ? setValue(hiddenInput.value) : reset();
                wrapper.dataset.selectorInitialized = 'true';
                selectors.push({ optionsWrapper, reset, setValue, input: hiddenInput });
            });

            if (!documentListenerAttached) {
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('[data-number-selector]')) closeAll();
                });
                documentListenerAttached = true;
            }
        }

        function resetAll() { selectors.forEach(s => s.reset()); }

        function setById(inputId, value) {
            const s = selectors.find(item => item.input?.id === inputId);
            const v = (value === null || value === undefined || value === '') ? '' : String(value);
            if (!s) { const inp = document.getElementById(inputId); if (inp) inp.value = v; return; }
            v === '' ? s.reset() : s.setValue(v);
        }

        return { init, resetAll, setById, closeAll };
    })();

    // ── Codificación Modelo ───────────────────────────────────────────────
    const Codificacion = (() => {
        const inputs = els.codificacionInputs;
        const hidden = els.codificacionHidden;

        function getActiveTelar() {
            if (els.checkboxCambio?.checked && els.selectDestino?.value) {
                return parseDestinoValue(els.selectDestino.value).telar;
            }
            return els.inputTelarId?.value || els.selectTelar?.value || '';
        }

        function getSuffix(telarId) {
            const n = Number.parseInt(telarId, 10);
            if (Number.isFinite(n) && n >= 200 && n <= 299) return 'JC5';
            if (Number.isFinite(n) && n >= 300) return '';
            return 'JC5';
        }

        function updateSuffix(telarId = null) {
            const currentTelar = telarId || getActiveTelar();
            const suffix = getSuffix(currentTelar);
            if (els.codificacionSuffix) {
                els.codificacionSuffix.textContent = suffix ? ('.' + suffix) : '';
                els.codificacionSuffix.classList.toggle('hidden', !suffix);
            }
            return suffix;
        }

        function updateHiddenValue() {
            const fullCode = Array.from(inputs).map(i => i.value).join('');
            const suffix = updateSuffix();
            hidden.value = fullCode ? (suffix ? `${fullCode}.${suffix}` : fullCode) : '';
            updateNoDataMessage();
            checkFormValidity();
        }

        function updateNoDataMessage() {
            if (!els.codificacionNoData) return;
            els.codificacionNoData.classList.toggle('hidden', !(state.codificacionFetchAttempted && !hidden.value));
        }

        function setFromCodigoDibujo(codigoDibujo) {
            const normalized = String(codigoDibujo ?? '').toUpperCase().trim()
                .replace(/\.JC5$/i, '').replace(/\.JCS$/i, '').replace(/\s+/g, '');
            inputs.forEach((input, i) => { input.value = normalized[i] ?? ''; });
            updateHiddenValue();
        }

        function clear() {
            inputs.forEach(i => { i.value = ''; });
            hidden.value = '';
            state.codificacionFetchAttempted = false;
            updateNoDataMessage();
            checkFormValidity();
        }

        function initListeners() {
            inputs.forEach((input, idx) => {
                input.addEventListener('input', function () {
                    this.value = this.value.toUpperCase();
                    if (this.value.length === 1 && idx < inputs.length - 1) inputs[idx + 1].focus();
                    updateHiddenValue();
                });
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && this.value === '' && idx > 0) inputs[idx - 1].focus();
                });
                input.addEventListener('paste', function (e) {
                    e.preventDefault();
                    const chars = (e.clipboardData || window.clipboardData).getData('text').toUpperCase().split('');
                    chars.forEach((char, i) => { if (idx + i < inputs.length) inputs[idx + i].value = char; });
                    inputs[Math.min(idx + chars.length, inputs.length - 1)].focus();
                    updateHiddenValue();
                });
            });
        }

        return { updateSuffix, updateHiddenValue, setFromCodigoDibujo, clear, initListeners };
    })();

    // ── Material Cache (calibres/fibras/colores) ──────────────────────────
    const MaterialCache = (() => {
        let calibres = null;
        const fibras = new Map();
        const colores = new Map();

        async function getCalibres() {
            if (calibres) return calibres;
            try {
                const data = await fetchJson(MATERIAL_ROUTES.calibres);
                calibres = (data?.data || []).map(i => i.ItemId).filter(Boolean);
                return calibres;
            } catch (e) { console.error('No se pudieron cargar calibres', e); return []; }
        }

        async function getFibras(itemId) {
            if (fibras.has(itemId)) return fibras.get(itemId);
            try {
                const data = await fetchJson(MATERIAL_ROUTES.fibras, { itemId });
                const items = (data?.data || []).map(i => i.ConfigId).filter(Boolean);
                fibras.set(itemId, items);
                return items;
            } catch (e) { console.error('No se pudieron cargar fibras', e); return []; }
        }

        async function getColores(itemId) {
            if (colores.has(itemId)) return colores.get(itemId);
            try {
                const data = await fetchJson(MATERIAL_ROUTES.colores, { itemId });
                const items = (data?.data || []).map(c => ({ value: c.InventColorId, label: `${c.InventColorId} - ${c.Name}`, name: c.Name })).filter(c => c.value);
                colores.set(itemId, items);
                return items;
            } catch (e) { console.error('No se pudieron cargar colores', e); return []; }
        }

        return { getCalibres, getFibras, getColores };
    })();

    // ── Detalle de la Orden (selects de materiales por fila) ──────────────
    const DetalleSelects = (() => {
        function setOptions(select, options, placeholder, selectedValue = '') {
            if (!select) return;
            select.innerHTML = '';
            const ph = document.createElement('option');
            ph.value = '';
            ph.textContent = placeholder;
            select.appendChild(ph);
            options.forEach(opt => {
                const o = document.createElement('option');
                if (typeof opt === 'string') { o.value = opt; o.textContent = opt; }
                else { o.value = opt.value; o.textContent = opt.label; if (opt.name) o.dataset.name = opt.name; }
                select.appendChild(o);
            });
            select.value = selectedValue || '';
            select.disabled = options.length === 0;
        }

        function ensureOption(select, value, label, name = '') {
            if (!select || !value) return;
            if (!Array.from(select.options).some(o => o.value === value)) {
                const o = document.createElement('option');
                o.value = value; o.textContent = label || value;
                if (name) o.dataset.name = name;
                select.appendChild(o);
            }
        }

        function getRowEls(row) {
            return {
                calibreEl:  row.querySelector('.detalle-calibre'),
                fibraEl:    row.querySelector('.detalle-fibra'),
                codColorEl: row.querySelector('.detalle-codcolor'),
                colorEl:    row.querySelector('.detalle-color'),
            };
        }

        function updateColorFromCod(row, fallback = '') {
            const { codColorEl, colorEl } = getRowEls(row);
            if (colorEl) colorEl.value = codColorEl?.selectedOptions?.[0]?.dataset?.name || fallback || '';
        }

        async function loadDependents(row, itemId, selections = {}) {
            const { fibraEl, codColorEl } = getRowEls(row);
            if (!itemId) {
                setOptions(fibraEl, [], 'Selecciona calibre');
                setOptions(codColorEl, [], 'Selecciona calibre');
                const { colorEl } = getRowEls(row);
                if (colorEl) colorEl.value = '';
                return;
            }
            setOptions(fibraEl, [], 'Cargando...');
            setOptions(codColorEl, [], 'Cargando...');
            const [fibras, colores] = await Promise.all([MaterialCache.getFibras(itemId), MaterialCache.getColores(itemId)]);
            setOptions(fibraEl, fibras, 'Selecciona fibra', selections.fibra || '');
            setOptions(codColorEl, colores, 'Selecciona color', selections.codColor || '');
            if (selections.fibra) { ensureOption(fibraEl, selections.fibra, selections.fibra); fibraEl.value = selections.fibra; }
            if (selections.codColor) { ensureOption(codColorEl, selections.codColor, selections.codColor, selections.colorName); codColorEl.value = selections.codColor; }
            updateColorFromCod(row, selections.colorName || '');
        }

        async function initForRow(row, selections = {}) {
            const { calibreEl, codColorEl } = getRowEls(row);
            if (!calibreEl) return;
            setOptions(calibreEl, [], 'Cargando...');
            const calibres = await MaterialCache.getCalibres();
            setOptions(calibreEl, calibres, 'Selecciona calibre', selections.calibre || '');
            if (selections.calibre) { ensureOption(calibreEl, selections.calibre, selections.calibre); calibreEl.value = selections.calibre; }
            await loadDependents(row, selections.calibre || '', selections);
            calibreEl.addEventListener('change', async (e) => await loadDependents(row, e.target.value, {}));
            codColorEl?.addEventListener('change', () => updateColorFromCod(row, ''));
        }

        return { initForRow };
    })();

    // ── Pasadas (sincronización detalle ↔ total) ──────────────────────────
    const Pasadas = (() => {
        function getInputs() {
            return els.bodyDetallesOrden ? Array.from(els.bodyDetallesOrden.querySelectorAll('input[name^="pasadas"]')) : [];
        }

        function calcularSuma() {
            return getInputs().reduce((sum, inp) => {
                const v = parseInt(inp.value, 10);
                return sum + (Number.isFinite(v) ? v : 0);
            }, 0);
        }

        function sincronizar() {
            if (!els.totalPasadasDibujo) return;
            const inputs = getInputs();
            els.totalPasadasDibujo.value = inputs.length === 0 ? '' : String(calcularSuma());
        }

        function adjuntarListeners() {
            getInputs().forEach(inp => inp.addEventListener('input', sincronizar));
            sincronizar();
        }

        function reset() {
            if (els.totalPasadasDibujo) els.totalPasadasDibujo.value = '';
        }

        return { getInputs, calcularSuma, sincronizar, adjuntarListeners, reset };
    })();

    // ── Fila de detalle (crear/eliminar/agregar) ──────────────────────────
    function crearFilaDetalle(index, calibre, hilo, fibra, codColor, nombreColor, pasadas, pasadasKey, usarSelects) {
        calibre = calibre || '';
        hilo = hilo || '';
        fibra = fibra || '';
        codColor = codColor || '';
        nombreColor = nombreColor || '';
        pasadas = pasadas || '';
        pasadasKey = pasadasKey || ('nuevo_' + (state.contadorFilasNuevas++));
        usarSelects = Boolean(usarSelects);

        var inp = 'w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm';
        var svgDel = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>';
        var selDis = usarSelects ? ' disabled' : '';
        var selCls = usarSelects ? (inp + ' bg-gray-50 detalle-color') : inp;
        var selRdonly = usarSelects ? ' readonly' : '';
        var claveCell = usarSelects
            ? '<td class="px-4 py-2"><select name="detalle_calibre[]" class="' + inp + '"><option value="">Cargando...</option></select></td>'
            : '<td class="px-4 py-2"><input type="text" name="detalle_calibre[]" value="' + calibre + '" class="' + inp + '" placeholder="Calibre"></td>';
        var hiloCell = '<td class="px-4 py-2"><input type="number" name="detalle_hilo[]" value="' + hilo + '" step="0.1" min="0" class="' + inp + '" placeholder="Hilo"></td>';
        var fibraCell = usarSelects
            ? '<td class="px-4 py-2"><select name="detalle_fibra[]" class="' + inp + '" disabled><option value="">Selecciona calibre</option></select></td>'
            : '<td class="px-4 py-2"><input type="text" name="detalle_fibra[]" value="' + fibra + '" class="' + inp + '" placeholder="Fibra"></td>';
        var codColorCell = usarSelects
            ? '<td class="px-4 py-2"><select name="detalle_codcolor[]" class="' + inp + '" disabled><option value="">Selecciona calibre</option></select></td>'
            : '<td class="px-4 py-2"><input type="text" name="detalle_codcolor[]" value="' + codColor + '" class="' + inp + '" placeholder="Cod Color"></td>';
        var nombreColorCell = '<td class="px-4 py-2"><input type="text" name="detalle_nombrecolor[]" value="' + nombreColor + '" class="' + selCls + '" placeholder="Nombre Color"' + selRdonly + '></td>';
        var pasadasCell = '<td class="px-4 py-2"><input type="number" name="pasadas[' + pasadasKey + ']" value="' + pasadas + '" min="1" step="1" required class="w-20 px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="0"></td>';
        var accionesCell = '<td class="px-4 py-2 text-center"><button type="button" onclick="eliminarFilaDetalle(this)" class="p-1.5 text-red-600 hover:bg-red-100 rounded-md transition-colors" title="Eliminar fila">' + svgDel + '</button></td>';

        var row = document.createElement('tr');
        row.className = 'hover:bg-gray-50 transition-colors fila-detalle';
        row.dataset.index = index;
        row.innerHTML = claveCell + hiloCell + fibraCell + codColorCell + nombreColorCell + pasadasCell + accionesCell;

        if (usarSelects) {
            void DetalleSelects.initForRow(row, { calibre: calibre, fibra: fibra, codColor: codColor, colorName: nombreColor });
        }
        return row;
    }

    window.eliminarFilaDetalle = function (boton) {
        boton.closest('tr')?.remove();
        Pasadas.sincronizar();
        if (!els.bodyDetallesOrden.querySelector('.fila-detalle')) {
            els.bodyDetallesOrden.innerHTML = emptyRowHtml(7, 'No hay detalles. Usa el botón "Agregar Fila" para añadir.');
        }
    };

    // ── Resumen CatCodificados ────────────────────────────────────────────
    function actualizarResumen(data) {
        Object.entries(els.resumen).forEach(([key, el]) => {
            if (el) el.textContent = (data && data[key] != null && data[key] !== '') ? String(data[key]) : '-';
        });
    }

    function prefillDesde(data) {
        setSelectValue(els.selectJulioRizo, data ? data.JulioRizo : '');
        setSelectValue(els.selectJulioPie, data ? data.JulioPie : '');
        NumberSelectorManager.setById('EficienciaInicio', data ? data.EfiInicial : '');
        NumberSelectorManager.setById('EficienciaFinal', data ? data.EfiFinal : '');
        if (els.inputDesperdicio) els.inputDesperdicio.value = (data && data.DesperdicioTrama !== null) ? data.DesperdicioTrama : 11;
        // Actualizar badges de julios
        function updateJulioBadge(select, badgeEl, tipo) {
            const selected = select ? select.selectedOptions[0] : null;
            if (selected && selected.value) {
                const invSize = selected.dataset.inventsizeid || '-';
                const cfgId = selected.dataset.configid || '-';
                if (badgeEl) badgeEl.textContent = 'Tamaño ' + tipo + ': ' + invSize + ' / Configuración ' + tipo + ': ' + cfgId;
            } else {
                if (badgeEl) badgeEl.textContent = 'No se ha seleccionado Julio ' + tipo;
            }
        }
        updateJulioBadge(els.selectJulioRizo, els.formJulioRizoInfo, 'Rizo');
        updateJulioBadge(els.selectJulioPie, els.formJulioPieInfo, 'Pie');
    }

    // ── Reset completo (usado en cancelar y después de guardar) ───────────
    function resetFormularioCompleto() {
        els.form.reset();
        NumberSelectorManager.resetAll();
        state.salonTejido = '';
        state.tamanoClave = '';
        state.noProduccionActual = '';
        state.nombreProductoActual = '';
        state.ordenEnProceso = '';
        state.ordenEnProcesoNombre = '';
        Codificacion.clear();
        els.formContainer.classList.add('hidden');
        document.querySelectorAll('.checkbox-produccion').forEach(cb => { cb.checked = false; });
        els.bodyDetallesOrden.innerHTML = emptyRowHtml(7, DETALLE_EMPTY_MSG);
        Pasadas.reset();
        els.modalPasadas?.classList.add('hidden');
        if (els.formJulioRizoInfo) els.formJulioRizoInfo.textContent = '—';
        if (els.formJulioPieInfo) els.formJulioPieInfo.textContent = '—';
        actualizarResumen(null);
        prefillDesde(null);
        checkFormValidity();
    }

    // ── Filtro Solo con Orden ─────────────────────────────────────────────
    els.filtroSoloConOrden?.addEventListener('change', function () {
        filtrarFilasConOrden();
    });

    function filtrarFilasConOrden() {
        const soloConOrden = els.filtroSoloConOrden?.checked;

        if (soloConOrden) {
            // Verificar si hay filas sin orden seleccionadas
            const filasSinOrden = els.bodyProducciones?.querySelectorAll('tr') || [];
            let tieneSeleccionSinOrden = false;
            filasSinOrden.forEach(row => {
                const checkbox = row.querySelector('.checkbox-produccion');
                const ordenSpan = row.querySelector('.orden-value');
                const ordenInput = row.querySelector('.orden-input');
                const tieneOrden = ordenSpan?.textContent?.trim() || ordenInput?.value?.trim();
                if (!tieneOrden && checkbox?.checked) {
                    tieneSeleccionSinOrden = true;
                }
            });

            if (tieneSeleccionSinOrden) {
                alert('Deselecciona primero las filas que no tienen orden antes de filtrar.');
                els.filtroSoloConOrden.checked = false;
                return;
            }
        }

        const filas = els.bodyProducciones?.querySelectorAll('tr') || [];
        filas.forEach(row => {
            const ordenSpan = row.querySelector('.orden-value');
            const ordenInput = row.querySelector('.orden-input');
            const tieneOrden = ordenSpan?.textContent?.trim() || ordenInput?.value?.trim();
            row.style.display = (soloConOrden && !tieneOrden) ? 'none' : '';
        });
    }

    // ── Cargas AJAX ───────────────────────────────────────────────────────
    function cargarProducciones(telarId) {
        const soloConOrden = els.filtroSoloConOrden?.checked ? '?solo_con_orden=1' : '';
        const url = `/desarrolladores/telar/${telarId}/producciones-html${soloConOrden}`;
        if (els.ordenEnProcesoBanner) els.ordenEnProcesoBanner.classList.add('hidden');
        els.bodyProducciones.innerHTML = spinnerHtml(7, 'Cargando producciones...');
        els.tablaProducciones.classList.remove('hidden');
        els.filtroOrdenContainer?.classList.remove('hidden');
        els.noDataMessage.classList.add('hidden');

        fetch(url)
            .then(r => r.text())
            .then(html => {
                els.bodyProducciones.innerHTML = html || emptyRowHtml(7, 'No se encontraron producciones');
                if (!html || html.trim() === '') {
                    els.noDataMessage.classList.remove('hidden');
                    els.filtroOrdenContainer?.classList.add('hidden');
                }
                // Configurar listeners para telar-destino
                setupTelarDestinoListeners();
                // Deshabilitar todos los selects de telar-destino inicialmente
                document.querySelectorAll('.telar-destino-select').forEach(function(sel) {
                    sel.disabled = true;
                });
            })
            .catch(() => {
                els.bodyProducciones.innerHTML = `<tr><td colspan="7" class="px-3 py-3 text-center text-red-500">Error al cargar las producciones</td></tr>`;
                els.filtroOrdenContainer?.classList.add('hidden');
            });

        // ── Cargar orden en proceso (SIEMPRE) ──
        fetch(`/desarrolladores/telar/${telarId}/orden-en-proceso`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.orden) {
                    state.ordenEnProceso = data.orden.noProduccion;
                    state.ordenEnProcesoNombre = data.orden.nombreProducto || '';
                    if (els.ordenEnProcesoBanner) {
                        els.ordenEnProcesoNum.textContent = state.ordenEnProceso;
                        els.ordenEnProcesoFecha.textContent = data.orden.fechaInicio || '-';
                        els.ordenEnProcesoNombre.textContent = state.ordenEnProcesoNombre || '-';
                        els.ordenEnProcesoTelar.textContent = telarId;
                        els.ordenEnProcesoBanner.classList.remove('hidden');
                    }
                } else {
                    state.ordenEnProceso = '';
                    state.ordenEnProcesoNombre = '';
                    if (els.ordenEnProcesoBanner) els.ordenEnProcesoBanner.classList.add('hidden');
                }
            })
            .catch(() => {});
    }

    function cargarJuliosPorTelar(telarId) {
        resetJulioSelect(els.selectJulioRizo, 'Cargando julios...');
        resetJulioSelect(els.selectJulioPie, 'Cargando julios...');

        fetch(`/desarrolladores/telar/${encodeURIComponent(telarId)}/julios`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error('Error al cargar julios');
                populateJulioSelect(els.selectJulioRizo, data.juliosRizo);
                populateJulioSelect(els.selectJulioPie, data.juliosPie);
            })
            .catch(() => {
                resetJulioSelect(els.selectJulioRizo, 'Sin julios disponibles');
                resetJulioSelect(els.selectJulioPie, 'Sin julios disponibles');
            });
    }

    function cargarDetallesOrden(noProduccion) {
        els.bodyDetallesOrden.innerHTML = spinnerHtml(6, 'Cargando detalles...');

        fetch(`/desarrolladores/orden/${noProduccion}/detalles`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.detalles.length > 0) {
                    els.bodyDetallesOrden.innerHTML = '';
                    data.detalles.forEach((d, i) => {
                        els.bodyDetallesOrden.appendChild(crearFilaDetalle(
                            i, d.Calibre ?? d.calibre ?? '', d.Hilo ?? d.hilo ?? '',
                            d.Fibra ?? d.fibra ?? '', d.CodColor ?? d.codColor ?? '',
                            d.NombreColor ?? d.nombreColor ?? '', d.Pasadas ?? d.pasadas ?? '',
                            d.pasadasField ?? d.pasadas_key ?? i, false
                        ));
                    });
                    Pasadas.adjuntarListeners();
                } else {
                    els.bodyDetallesOrden.innerHTML = emptyRowHtml(7, 'No se encontraron detalles para esta orden');
                    Pasadas.reset();
                }
            })
            .catch(() => {
                els.bodyDetallesOrden.innerHTML = `<tr><td colspan="7" class="px-6 py-4 text-center text-red-500">Error al cargar los detalles</td></tr>`;
                Pasadas.reset();
            });
    }

    function cargarResumenCatCodificados(telarId, noProduccion) {
        actualizarResumen(null);
        prefillDesde(null);
        if (!telarId || !noProduccion) return;

        fetch(`/desarrolladores/catcodificados/${encodeURIComponent(telarId)}/${encodeURIComponent(noProduccion)}`)
            .then(r => r.json())
            .then(data => { if (data.success && data.registro) { actualizarResumen(data.registro); prefillDesde(data.registro); } })
            .catch(e => console.error('Error al obtener datos registrados:', e));
    }

    // ── Funciones de Codigo Dibujo ─────────────────────────────────────────
    function buscarYActualizarCodigoDibujo(salon, telar, tamano) {
        Codificacion.updateSuffix(telar);
        state.codificacionFetchAttempted = true;
        if (salon && tamano) {
            fetch(`/desarrolladores/modelo-codificado/${encodeURIComponent(salon)}/${encodeURIComponent(tamano)}`)
                .then(r => r.json())
                .then(d => Codificacion.setFromCodigoDibujo(d.success && d.codigoDibujo ? d.codigoDibujo : ''))
                .catch(() => Codificacion.setFromCodigoDibujo(''));
        } else {
            Codificacion.setFromCodigoDibujo('');
        }
    }

    // ── Seleccionar producción ────────────────────────────────────────────
    window.seleccionarProduccion = function (checkbox) {
        if (!checkbox.checked) return;
        document.querySelectorAll('.checkbox-produccion').forEach(cb => { if (cb !== checkbox) cb.checked = false; });

        const { telar, produccion, modelo = '', salon = '', tamano = '' } = checkbox.dataset;

        els.inputTelarId.value = telar;
        els.inputNoProduccion.value = produccion;
        els.formTelarId.textContent = telar;
        els.formNoProduccion.textContent = produccion;
        els.formNombreProducto.textContent = modelo || '-';
        state.salonTejido = salon;
        state.tamanoClave = tamano;
        state.noProduccionActual = produccion;
        state.nombreProductoActual = modelo;

        // Habilitar telar-destino selects
        document.querySelectorAll('.telar-destino-select').forEach(function(sel) {
            sel.disabled = false;
        });
        // Ocultar botones reprogramar
        if (els.btnRepSiguiente) els.btnRepSiguiente.classList.add('hidden');
        if (els.btnRepFinal) els.btnRepFinal.classList.add('hidden');

        buscarYActualizarCodigoDibujo(salon, telar, tamano);

        NumberSelectorManager.resetAll();
        actualizarResumen(null);
        prefillDesde(null);
        Pasadas.reset();
        cargarDetallesOrden(produccion);
        cargarResumenCatCodificados(telar, produccion);

        els.formContainer.classList.remove('hidden');
        requestAnimationFrame(() => {
            const ajusteSuperior = 70;
            els.formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(() => {
                window.scrollBy({ top: -ajusteSuperior, behavior: 'smooth' });
                checkFormValidity();
            }, 180);
        });
    };

    // ── Validar y enviar formulario ───────────────────────────────────────
    function enviarFormulario() {
        Swal.fire({ title: 'Guardando...', text: 'Por favor espera', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });

        const formData = new FormData(els.form);

        fetch(els.form.action, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.message || 'Error al guardar los datos');
                Swal.fire({ icon: 'success', title: '¡Guardado exitosamente!', text: data.message || 'Los datos se han guardado correctamente', confirmButtonColor: '#2563eb', confirmButtonText: 'Aceptar' })
                    .then(() => {
                        window.location.href = "{{ route('produccion.index') }}";
                    });
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({ icon: 'error', title: 'Error al guardar', text: error.message || 'Ocurrió un error. Intenta nuevamente.', confirmButtonColor: '#dc2626', confirmButtonText: 'Aceptar' });
            });
    }

    // ── Event Listeners ───────────────────────────────────────────────────
    els.form?.addEventListener('input', checkFormValidity);
    els.form?.addEventListener('change', checkFormValidity);

    els.selectTelar.addEventListener('change', function () {
        if (!this.value) return;
        window.__TELAR_ACTUAL__ = this.value;
        Codificacion.updateSuffix(this.value);
        Codificacion.updateHiddenValue();
        cargarJuliosPorTelar(this.value);
        cargarProducciones(this.value);
    });

    els.btnCancelar.addEventListener('click', resetFormularioCompleto);

    els.totalPasadasDibujo?.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const wrapper = document.querySelector('[data-number-selector] #EficienciaInicio');
        wrapper?.closest('[data-number-selector]')?.querySelector('.number-selector-btn')?.click();
    });

    els.btnAgregarFila?.addEventListener('click', function () {
        const filas = els.bodyDetallesOrden.querySelectorAll('.fila-detalle');
        if (els.bodyDetallesOrden.querySelector('td[colspan]')) els.bodyDetallesOrden.innerHTML = '';
        const nuevaFila = crearFilaDetalle(filas.length, '', '', '', '', '', '', null, true);
        els.bodyDetallesOrden.appendChild(nuevaFila);
        const inputPasadas = nuevaFila.querySelector('input[name^="pasadas"]');
        inputPasadas?.addEventListener('input', Pasadas.sincronizar);
        nuevaFila.querySelector('input')?.focus();
        Pasadas.sincronizar();
    });

    els.form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (state.omitirConfirmacionPasadas) {
            state.omitirConfirmacionPasadas = false;
        } else {
            const suma = Pasadas.calcularSuma();
            const total = parseInt(els.totalPasadasDibujo?.value ?? '0', 10);
            if (suma > 0 && !(Number.isFinite(total) && total === suma)) {
                els.modalPasadas?.classList.remove('hidden');
                return;
            }
        }
        enviarFormulario();
    });

    els.btnModalCancelar?.addEventListener('click', () => els.modalPasadas?.classList.add('hidden'));
    els.btnModalAceptar?.addEventListener('click', () => {
        els.modalPasadas?.classList.add('hidden');
        state.omitirConfirmacionPasadas = true;
        els.form.dispatchEvent(new Event('submit'));
    });


    els.selectJulioRizo?.addEventListener('change', function () {
        const selected = this.selectedOptions[0];
        if (selected && selected.value) {
            const invSize = selected.dataset.inventsizeid || '-';
            const cfgId = selected.dataset.configid || '-';
            els.formJulioRizoInfo.textContent = 'Tamaño Rizo: ' + invSize + ' / Configuración Rizo: ' + cfgId;
        } else {
            els.formJulioRizoInfo.textContent = 'No se ha seleccionado Julio Rizo';
        }
    });

    els.selectJulioPie?.addEventListener('change', function () {
        const selected = this.selectedOptions[0];
        if (selected && selected.value) {
            const invSize = selected.dataset.inventsizeid || '-';
            const cfgId = selected.dataset.configid || '-';
            els.formJulioPieInfo.textContent = 'Tamaño Pie: ' + invSize + ' / Configuración Pie: ' + cfgId;
        } else {
            els.formJulioPieInfo.textContent = 'No se ha seleccionado Julio Pie';
        }
    });


    Codificacion.initListeners();
    NumberSelectorManager.init();

    // ── Validación de Orden ─────────────────────────────────────────────
    let ordenValidacionTimer = null;

    function validarOrden(input) {
        clearTimeout(ordenValidacionTimer);
        const valor = input.value.trim();
        if (!valor || valor.length < 4) {
            input.classList.remove('border-red-500', 'border-green-500');
            input.classList.add('border-gray-300');
            input.dataset.valido = 'false';
            if (els.msgValidacionOrden) els.msgValidacionOrden.classList.add('hidden');
            return;
        }
        ordenValidacionTimer = setTimeout(() => {
            fetch(`/desarrolladores/verificar-orden?noProduccion=${encodeURIComponent(valor)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.exists) {
                        input.classList.remove('border-gray-300', 'border-green-500');
                        input.classList.add('border-red-500');
                        input.dataset.valido = 'false';
                        if (els.msgValidacionOrden) {
                            els.msgValidacionOrden.classList.remove('hidden');
                            els.msgValidacionOrden.querySelector('span').textContent = `La orden "${valor}" ya existe`;
                        }
                    } else {
                        input.classList.remove('border-gray-300', 'border-red-500');
                        input.classList.add('border-green-500');
                        input.dataset.valido = 'true';
                        if (els.msgValidacionOrden) els.msgValidacionOrden.classList.add('hidden');
                    }
                });
        }, 400);
    }

    els.bodyProducciones?.addEventListener('input', function (e) {
        if (e.target.classList.contains('orden-input')) {
            validarOrden(e.target);
        }
    });
});
</script>
