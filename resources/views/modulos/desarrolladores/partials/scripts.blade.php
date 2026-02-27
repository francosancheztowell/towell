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
        inputTramaAncho:    document.getElementById('TramaAnchoPeine'),
        inputLongLucha:     document.getElementById('LongitudLuchaTot'),
        checkboxCambio:     document.getElementById('CambioTelarActivo'),
        selectDestino:      document.getElementById('TelarDestino'),
        modalPasadas:       document.getElementById('modalPasadas'),
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
    };

    // ── Estado ─────────────────────────────────────────────────────────────
    const state = {
        salonTejido: '',
        tamanoClave: '',
        codificacionFetchAttempted: false,
        omitirConfirmacionPasadas: false,
        contadorFilasNuevas: 1000,
    };

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
                els.codificacionSuffix.textContent = suffix ? `.${suffix}` : '';
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
    function crearFilaDetalle(index, calibre = '', hilo = '', fibra = '', codColor = '', nombreColor = '', pasadas = '', pasadasKey = null, usarSelects = false) {
        const key = pasadasKey ?? `nuevo_${state.contadorFilasNuevas++}`;
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50 transition-colors fila-detalle';
        row.dataset.index = index;

        const inputField = (name, val, ph) => `<input type="text" name="${name}" value="${val}" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="${ph}">`;
        const selectField = (name, cls, ph, disabled = false) => `<select name="${name}" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm ${cls}" ${disabled ? 'disabled' : ''}><option value="">${ph}</option></select>`;

        row.innerHTML = `
            <td class="px-4 py-2">${usarSelects ? selectField('detalle_calibre[]', 'detalle-calibre', 'Cargando...') : inputField('detalle_calibre[]', calibre, 'Calibre')}</td>
            <td class="px-4 py-2">${inputField('detalle_hilo[]', hilo, 'Hilo')}</td>
            <td class="px-4 py-2">${usarSelects ? selectField('detalle_fibra[]', 'detalle-fibra', 'Selecciona calibre', true) : inputField('detalle_fibra[]', fibra, 'Fibra')}</td>
            <td class="px-4 py-2">${usarSelects ? selectField('detalle_codcolor[]', 'detalle-codcolor', 'Selecciona calibre', true) : inputField('detalle_codcolor[]', codColor, 'Cod Color')}</td>
            <td class="px-4 py-2">
                <input type="text" name="detalle_nombrecolor[]" value="${nombreColor}"
                       class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm ${usarSelects ? 'bg-gray-50 detalle-color' : ''}"
                       placeholder="Nombre Color" ${usarSelects ? 'readonly' : ''}>
            </td>
            <td class="px-4 py-2">
                <input type="number" name="pasadas[${key}]" value="${pasadas}" min="1" step="1" required
                       class="w-20 px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="0">
            </td>
            <td class="px-4 py-2 text-center">
                <button type="button" onclick="eliminarFilaDetalle(this)" class="p-1.5 text-red-600 hover:bg-red-100 rounded-md transition-colors" title="Eliminar fila">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </td>`;

        if (usarSelects) void DetalleSelects.initForRow(row, { calibre, fibra, codColor, colorName: nombreColor });
        return row;
    }

    window.eliminarFilaDetalle = function (boton) {
        boton.closest('tr')?.remove();
        Pasadas.sincronizar();
        if (!els.bodyDetallesOrden.querySelector('.fila-detalle')) {
            els.bodyDetallesOrden.innerHTML = emptyRowHtml(7, 'No hay detalles. Usa el botón "Agregar Fila" para añadir.');
        }
    };

    // ── Cambio de telar ───────────────────────────────────────────────────
    function actualizarEstadoCambioTelar() {
        if (!els.checkboxCambio || !els.selectDestino) return;
        const activo = els.checkboxCambio.checked;
        els.selectDestino.disabled = !activo;
        els.selectDestino.required = activo;
        if (!activo) { 
            els.selectDestino.value = ''; 
            // Restaurar el código de dibujo del telar original si se desactiva el cambio
            if (state.salonTejido && state.tamanoClave && els.inputTelarId?.value) {
                buscarYActualizarCodigoDibujo(state.salonTejido, els.inputTelarId.value, state.tamanoClave);
            }
            return; 
        }
        const destino = parseDestinoValue(els.selectDestino.value);
        if (destino.salon === state.salonTejido && destino.telar === (els.inputTelarId?.value || '')) els.selectDestino.value = '';
    }

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
    }

    // ── Reset completo (usado en cancelar y después de guardar) ───────────
    function resetFormularioCompleto() {
        els.form.reset();
        NumberSelectorManager.resetAll();
        state.salonTejido = '';
        state.tamanoClave = '';
        if (els.checkboxCambio) els.checkboxCambio.checked = false;
        if (els.selectDestino) els.selectDestino.value = '';
        actualizarEstadoCambioTelar();
        Codificacion.clear();
        els.formContainer.classList.add('hidden');
        document.querySelectorAll('.checkbox-produccion').forEach(cb => { cb.checked = false; });
        els.bodyDetallesOrden.innerHTML = emptyRowHtml(7, DETALLE_EMPTY_MSG);
        Pasadas.reset();
        els.modalPasadas?.classList.add('hidden');
        actualizarResumen(null);
        prefillDesde(null);
        checkFormValidity();
    }

    // ── Cargas AJAX ───────────────────────────────────────────────────────
    function cargarProducciones(telarId) {
        els.bodyProducciones.innerHTML = spinnerHtml(6, 'Cargando producciones...');
        els.tablaProducciones.classList.remove('hidden');
        els.noDataMessage.classList.add('hidden');

        fetch(`/desarrolladores/telar/${telarId}/producciones`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.producciones.length > 0) {
                    els.bodyProducciones.innerHTML = '';
                    data.producciones.forEach((p) => {
                        const row = document.createElement('tr');
                        row.className = 'hover:bg-gray-100 transition-colors';
                        row.innerHTML = `
                            <td class="px-3 py-3 whitespace-nowrap text-sm font-medium text-gray-900 bg-blue-50">${p.SalonTejidoId ?? 'N/A'}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm font-medium text-gray-900 bg-white">${p.NoProduccion}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-600 bg-blue-50">${p.FechaInicio ? new Date(p.FechaInicio).toLocaleDateString('es-ES', {day:'2-digit',month:'2-digit',year:'numeric'}) : 'N/A'}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-600 bg-white">${p.TamanoClave ?? 'N/A'}</td>
                            <td class="px-3 py-3 text-sm text-gray-600 break-words bg-blue-50">${p.NombreProducto || 'N/A'}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-center bg-white">
                                <input type="checkbox" class="checkbox-produccion w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 cursor-pointer"
                                       data-telar="${telarId}" data-salon="${p.SalonTejidoId ?? ''}" data-tamano="${p.TamanoClave ?? ''}"
                                       data-produccion="${p.NoProduccion}" data-modelo="${p.NombreProducto || ''}" onchange="seleccionarProduccion(this)">
                            </td>`;
                        els.bodyProducciones.appendChild(row);
                    });
                } else {
                    els.bodyProducciones.innerHTML = '';
                    els.noDataMessage.classList.remove('hidden');
                }
            })
            .catch(() => {
                els.bodyProducciones.innerHTML = `<tr><td colspan="6" class="px-3 py-3 text-center text-red-500">Error al cargar las producciones</td></tr>`;
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
        if (els.checkboxCambio) els.checkboxCambio.checked = false;
        if (els.selectDestino) els.selectDestino.value = '';
        actualizarEstadoCambioTelar();

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
    function validarCambioTelar() {
        const activo = Boolean(els.checkboxCambio?.checked);
        if (!activo) return true;

        const rawDestino = (els.selectDestino?.value || '').trim();
        if (!rawDestino) {
            Swal.fire({ icon: 'warning', title: 'Cambio de telar', text: 'Selecciona un telar destino para continuar.', confirmButtonColor: '#2563eb' });
            return false;
        }
        const destino = parseDestinoValue(rawDestino);
        if (!destino.salon || !destino.telar) {
            Swal.fire({ icon: 'warning', title: 'Cambio de telar', text: 'El telar destino tiene un formato invalido.', confirmButtonColor: '#2563eb' });
            return false;
        }
        if (destino.salon === state.salonTejido && destino.telar === (els.inputTelarId?.value || '')) {
            Swal.fire({ icon: 'warning', title: 'Cambio de telar', text: 'El telar destino debe ser diferente al telar origen.', confirmButtonColor: '#2563eb' });
            return false;
        }
        return true;
    }

    function enviarFormulario() {
        if (!validarCambioTelar()) return;

        Swal.fire({ title: 'Guardando...', text: 'Por favor espera', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });

        const formData = new FormData(els.form);
        const cambioActivo = Boolean(els.checkboxCambio?.checked);
        formData.set('CambioTelarActivo', cambioActivo ? '1' : '0');
        if (cambioActivo) formData.set('TelarDestino', (els.selectDestino?.value || '').trim());
        else formData.delete('TelarDestino');

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
        Codificacion.updateSuffix(this.value);
        Codificacion.updateHiddenValue();
        cargarProducciones(this.value);
    });

    els.checkboxCambio?.addEventListener('change', actualizarEstadoCambioTelar);
    els.selectDestino?.addEventListener('change', function () {
        if (!this.value) return;
        const d = parseDestinoValue(this.value);
        if (d.salon === state.salonTejido && d.telar === (els.inputTelarId?.value || '')) {
            this.value = '';
            Swal.fire({ icon: 'warning', title: 'Destino invalido', text: 'El telar destino debe ser diferente al telar origen.', confirmButtonColor: '#2563eb' });
        } else {
            // Actualizar código de dibujo con el nuevo telar destino
            buscarYActualizarCodigoDibujo(d.salon, d.telar, state.tamanoClave);
        }
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

    // Longitud Lucha Total = Trama Ancho Peine + Desperdicio Trama
    function calcularLongitudLucha() {
        const total = (parseFloat(els.inputTramaAncho?.value) || 0) + (parseFloat(els.inputDesperdicio?.value) || 0);
        if (els.inputLongLucha) els.inputLongLucha.value = total > 0 ? total.toFixed(2) : '';
    }
    els.inputTramaAncho?.addEventListener('input', calcularLongitudLucha);
    els.inputDesperdicio?.addEventListener('input', calcularLongitudLucha);

    // ── Inicialización ────────────────────────────────────────────────────
    Codificacion.initListeners();
    NumberSelectorManager.init();
    actualizarEstadoCambioTelar();
});
</script>