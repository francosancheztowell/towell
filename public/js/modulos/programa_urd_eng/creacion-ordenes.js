(() => {
    'use strict';

    /* =================== Config & Utils =================== */

    const qs  = (sel, ctx=document) => ctx.querySelector(sel);
    const qsa = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));

    const isNil   = v => v === null || v === undefined;
    const isBlank = v => isNil(v) || String(v).trim() === '';
    // Función helper para validar si un campo está vacío o no tiene valor
    const isEmpty = (v) => {
        if (v === null || v === undefined) return true;
        const str = String(v).trim();
        return str === '' || str === 'null' || str === 'undefined';
    };

    const toNumber = (v, def=0) => {
        if (isNil(v)) return def;
        const num = parseFloat(String(v).replace(/,/g, ''));
        return Number.isNaN(num) ? def : num;
    };

    const fmtNumber = (v, dec=2) => {
        const n = toNumber(v, null);
        if (n === null) return '';
        return n.toLocaleString('es-MX', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    };

    const fmtDate = (dateStr) => {
        if (isBlank(dateStr)) return '';
        try {
            // Si viene como datetime (ej: "2025-01-15 14:30:00"), tomar solo la parte de la fecha
            let datePart = String(dateStr).trim();
            // Si contiene espacio, tomar solo la parte antes del espacio
            if (datePart.includes(' ')) {
                datePart = datePart.split(' ')[0];
            }
            // Si contiene 'T' (formato ISO), tomar solo la parte antes de la T
            if (datePart.includes('T')) {
                datePart = datePart.split('T')[0];
            }
            // Crear fecha y formatear solo la fecha sin hora
            const d = new Date(datePart + 'T00:00:00');
            if (Number.isNaN(d.getTime())) return datePart;
            // Formatear solo la fecha (día/mes/año) sin hora
            return d.toLocaleDateString('es-MX', { day:'2-digit', month:'2-digit', year:'numeric' });
        } catch {
            // Si falla, intentar retornar solo la parte de la fecha
            const str = String(dateStr).trim();
            if (str.includes(' ')) return str.split(' ')[0];
            if (str.includes('T')) return str.split('T')[0];
            return str;
        }
    };

    const normalizarTipo = (tipo) => {
        const up = String(tipo || '').toUpperCase().trim();
        if (up === 'RIZO') return 'Rizo';
        if (up === 'PIE')  return 'Pie';
        return tipo || '';
    };

    const safeJSON = {
        get(key, fallback=null) {
            try { return JSON.parse(localStorage.getItem(key) || 'null') ?? fallback; }
            catch { return fallback; }
        },
        set(key, val) {
            try { localStorage.setItem(key, JSON.stringify(val)); } catch {}
        }
    };

    const fetchJSON = async (url, opts={}) => {
        const res = await fetch(url, { headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }, ...opts });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    };

    const debounce = (fn, ms=300) => {
        let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    };

    const positionDropdown = (inputEl, container) => {
        const rect = inputEl.getBoundingClientRect();
        container.style.top  = (rect.bottom + window.scrollY + 4) + 'px';
        container.style.left = (rect.left   + window.scrollX) + 'px';
        container.style.width = rect.width + 'px';
    };

    /* =================== Estado =================== */
    const STORAGE_KEY_MATERIALES  = 'creacion_ordenes_materiales';
    const STORAGE_KEY_SELECCIONES = 'creacion_ordenes_selecciones';

    let telaresData = [];
    let filaSeleccionadaId = null;
    const gruposData = Object.create(null); // { filaId: { grupo, bomId, kilos, materialesUrdido } }
    let config = {}; // Configuración con rutas y datos

    /* =================== LS helpers específicos =================== */
    const LS = {
        getMateriales(bomId) {
            const all = safeJSON.get(STORAGE_KEY_MATERIALES, {});
            return all[bomId] || null;
        },
        setMateriales(bomId, materialesUrdido=[], materialesEngomado=[]) {
            if (isBlank(bomId)) return;
            const all = safeJSON.get(STORAGE_KEY_MATERIALES, {});
            all[bomId] = { materialesUrdido: Array.isArray(materialesUrdido)?materialesUrdido:[],
                           materialesEngomado: Array.isArray(materialesEngomado)?materialesEngomado:[],
                           timestamp: Date.now() };
            safeJSON.set(STORAGE_KEY_MATERIALES, all);
        },
        wipeMateriales(bomId) {
            const all = safeJSON.get(STORAGE_KEY_MATERIALES, {});
            delete all[bomId]; safeJSON.set(STORAGE_KEY_MATERIALES, all);
        },
        getSelecciones(bomId) {
            const all = safeJSON.get(STORAGE_KEY_SELECCIONES, {});
            return all[bomId] || [];
        },
        setSelecciones(bomId, selecciones=[]) {
            const all = safeJSON.get(STORAGE_KEY_SELECCIONES, {});
            all[bomId] = selecciones; safeJSON.set(STORAGE_KEY_SELECCIONES, all);
        }
    };

    /* =================== Normalización entrada =================== */
    function normalizeInput(arr) {
        return (arr || []).map(t => ({
            ...t,
            tipo: normalizarTipo(t.tipo),
            hilo: !isBlank(t.hilo) ? String(t.hilo).trim() : null,
            metros: toNumber(t.metros, 0),
            kilos : toNumber(t.kilos, 0),
            agrupar: !!t.agrupar
        }));
    }

    /* =================== Agrupar telares =================== */
    function agruparTelares(telares) {
        const grupos = Object.create(null);
        const singles = [];

        for (const telar of (telares || [])) {
            if (!telar.agrupar) { singles.push(telar); continue; }

            const tipoN = normalizarTipo(telar.tipo);
            const up    = String(tipoN || '').toUpperCase();
            const esPie = up === 'PIE';

            const cuenta   = String(telar.cuenta || '').trim();
            const calibre  = !isBlank(telar.calibre) ? parseFloat(telar.calibre).toFixed(2) : '';
            const hilo     = esPie ? '' : (!isBlank(telar.hilo) ? String(telar.hilo).trim() : '');
            const urdido   = String(telar.urdido || '').trim();
            const tipoAtado= String(telar.tipo_atado || 'Normal').trim();
            const destino  = String(telar.destino || '').trim();

            const clave = esPie
                ? `${cuenta}|${calibre}|${up}|${urdido}|${tipoAtado}|${destino}`
                : `${cuenta}|${hilo}|${calibre}|${up}|${urdido}|${tipoAtado}|${destino}`;

            if (!grupos[clave]) {
                grupos[clave] = { telares:[], cuenta, calibre, hilo, tipo:tipoN, urdido, tipoAtado, destino,
                                  fechaReq: telar.fecha_req || '', metros:0, kilos:0, maquinaId: telar.urdido || telar.maquina_urd || telar.maquinaId || '' };
            }
            grupos[clave].telares.push(telar);
            grupos[clave].metros += telar.metros || 0;
            grupos[clave].kilos  += telar.kilos  || 0;
        }

        const out = Object.values(grupos).map(g => ({ ...g, telaresStr: g.telares.map(t=>t.no_telar).join(',') }));
        for (const t of singles) {
            out.push({
                telares:[t], telaresStr:t.no_telar, cuenta:t.cuenta || '', calibre:t.calibre || '', hilo:t.hilo || '',
                tipo: normalizarTipo(t.tipo), urdido:t.urdido || '', tipoAtado:t.tipo_atado || 'Normal', destino:t.destino || '',
                fechaReq:t.fecha_req || '', metros:t.metros || 0, kilos:t.kilos || 0, maquinaId: t.urdido || t.maquina_urd || t.maquinaId || ''
            });
        }
        return out;
    }

    /* =================== Render tabla principal =================== */
    function renderTabla() {
        const tbody = qs('#tbodyOrdenes');
        tbody.innerHTML = '';

        if (!telaresData.length) {
            tbody.innerHTML = `<tr><td colspan="11" class="px-4 py-8 text-center text-gray-500">
                <i class="fa-solid fa-circle-info text-gray-400 mb-2"></i><p>No hay telares seleccionados.</p></td></tr>`;
            return;
        }

        const grupos = agruparTelares(telaresData);
        grupos.forEach((g, idx) => {
            const tipoUp = String(g.tipo||'').toUpperCase();
            const tipoCls = tipoUp==='RIZO' ? 'bg-rose-100 text-rose-700'
                           : tipoUp==='PIE' ? 'bg-teal-100 text-teal-700' : 'bg-gray-100 text-gray-700';

            const filaId = `fila-${idx}-${g.telaresStr}`;
            gruposData[filaId] = { grupo:g, bomId:'', kilos:g.kilos||0, materialesUrdido:null };

            const tr = document.createElement('tr');
            tr.id = filaId;
            tr.className = ' hover:bg-gray-50 cursor-pointer transition-colors';
            tr.dataset.filaId = filaId;

            tr.innerHTML = `
                <td class="px-2 py-3 text-xs text-center">${g.telaresStr || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${fmtDate(g.fechaReq)}</td>
                <td class="px-2 py-3 text-xs text-center">${g.cuenta || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${g.calibre || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${g.hilo || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${g.urdido || '-'}</td>
                <td class="px-2 py-3 text-center"><span class="px-2 py-1 inline-block text-xs font-medium rounded-md ${tipoCls}">${g.tipo || 'N/A'}</span></td>
                <td class="px-2 py-3 text-xs text-center">${g.destino || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${fmtNumber(g.metros)}</td>
                <td class="px-2 py-3 text-xs text-center">${fmtNumber(g.kilos)}</td>
                <td class="px-2 py-3 text-center">
                    <input type="text" placeholder="Buscar BOM..." class="w-full px-2 py-1.5 border border-gray-500 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"
                           data-grupo="${g.telaresStr}" data-fila-id="${filaId}" data-kilos="${g.kilos || 0}" data-bom-input="true" data-bom-id="" autocomplete="off"
                           onclick="event.stopPropagation();">
                </td>
            `;

            tr.addEventListener('click', (e) => {
                if (e.target.tagName === 'INPUT') return;
                seleccionarFila(filaId);
            });

            tbody.appendChild(tr);

            // restaurar valor bom si existe
            queueMicrotask(() => {
                const inputBom = tr.querySelector('[data-bom-input="true"]');
                if (inputBom && gruposData[filaId].bomId) {
                    inputBom.value = gruposData[filaId].bomId;
                    inputBom.dataset.bomId = gruposData[filaId].bomId;
                }
            });
        });

        if (grupos.length) setTimeout(() => seleccionarFila(`fila-0-${grupos[0].telaresStr}`), 150);
    }

    /* =================== Selección de fila =================== */
    function seleccionarFila(filaId) {
        if (filaSeleccionadaId) {
            const prev = document.getElementById(filaSeleccionadaId);
            if (prev) { prev.classList.remove('bg-blue-100','border-l-4','border-blue-500'); prev.classList.add('hover:bg-gray-50'); }
        }
        filaSeleccionadaId = filaId;
        const fila = document.getElementById(filaId);
        if (fila) { fila.classList.add('bg-blue-100','border-l-4','border-blue-500'); fila.classList.remove('hover:bg-gray-50'); }

        const data = gruposData[filaId];
        if (!data) {
            renderTablaMaterialesUrdido([],0);
            renderTablaMaterialesEngomado([]);
            return;
        }

        const inputBom = fila?.querySelector('[data-bom-input="true"]');
        const bomId = (data.bomId || inputBom?.dataset.bomId || inputBom?.value || '').trim();

        if (data.bomId && inputBom && (!inputBom.value || inputBom.value !== data.bomId)) {
            inputBom.value = data.bomId; inputBom.dataset.bomId = data.bomId;
        }

        if (!isBlank(bomId)) {
            cargarMaterialesUrdido(bomId, data.kilos || 0, false);
            data.bomId = bomId;
        } else {
            renderTablaMaterialesUrdido([], data.kilos);
            renderTablaMaterialesEngomado([], null);
        }

        // Dependencias de la fila: anchos balona y metraje
        const cuenta = data.grupo?.cuenta || '';
        const tipo   = data.grupo?.tipo || '';
        if (!isBlank(cuenta) && !isBlank(tipo)) cargarAnchosBalona(cuenta, tipo);
        else limpiarSelectAnchosBalona();

        actualizarMetrajeTelas();
    }

    /* =================== Autocomplete genérico =================== */
    function setupAutocomplete({ inputsSelector, searchRoute, param='q', containerId, getLabel, onSelect }) {
        const inputs = qsa(inputsSelector);
        let activeInput = null, selectedIndex = -1, list = [], open = false;

        let container = document.getElementById(containerId);
        if (!container) {
            container = document.createElement('div');
            container.id = containerId;
            container.className = 'fixed z-[99999] bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-60 overflow-y-auto';
            document.body.appendChild(container);
        }

        const hide = () => { container.classList.add('hidden'); open=false; selectedIndex=-1; list=[]; activeInput=null; };
        const show = (el) => { positionDropdown(el, container); container.classList.remove('hidden'); open=true; };

        const render = (items) => {
            container.innerHTML = '';
            items.forEach((it, idx) => {
                const div = document.createElement('div');
                div.className = 'px-3 py-2 hover:bg-blue-50 cursor-pointer text-xs  border-gray-100';
                div.textContent = getLabel(it);
                div.addEventListener('click', () => { if (activeInput) onSelect(activeInput, it, hide); });
                div.addEventListener('mouseenter', () => {
                    qsa('div', container).forEach(d => d.classList.remove('bg-blue-100'));
                    div.classList.add('bg-blue-100'); selectedIndex = idx;
                });
                container.appendChild(div);
            });
        };

        const doSearch = debounce(async (q, inputEl) => {
            if (isBlank(q)) { hide(); return; }
            try {
                const url = new URL(searchRoute, window.location.origin);
                url.searchParams.set(param, q.trim());
                const data = await fetchJSON(url.toString());
                list = Array.isArray(data) ? data : (data.data || []);
                if (!list || !list.length) { hide(); return; }
                activeInput = inputEl; render(list); show(inputEl);
            } catch(e){ hide(); }
        }, 300);

        const onKey = (e) => {
            if (!open) return;
            const items = qsa('div', container);
            if (e.key === 'ArrowDown') {
                e.preventDefault(); selectedIndex = Math.min(selectedIndex+1, items.length-1);
                items[selectedIndex]?.scrollIntoView({ block:'nearest' });
                items.forEach((it,i)=>it.classList.toggle('bg-blue-100', i===selectedIndex));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault(); selectedIndex = Math.max(selectedIndex-1, -1);
                items.forEach((it,i)=>it.classList.toggle('bg-blue-100', i===selectedIndex));
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedIndex>=0 && items[selectedIndex]) items[selectedIndex].click();
                else hide();
            } else if (e.key === 'Escape') { hide(); }
        };

        window.addEventListener('scroll', () => { if (activeInput && open) positionDropdown(activeInput, container); }, true);
        window.addEventListener('resize', () => { if (activeInput && open) positionDropdown(activeInput, container); });
        document.addEventListener('click', (e) => {
            if (activeInput && !activeInput.contains(e.target) && !container.contains(e.target)) hide();
        }, true);

        inputs.forEach(input => {
            input.addEventListener('input', e => doSearch(e.target.value, e.target));
            input.addEventListener('focus', e => { if (!isBlank(e.target.value)) doSearch(e.target.value, e.target); });
            input.addEventListener('keydown', onKey);
            input.addEventListener('click', e => e.stopPropagation());
        });
    }

    /* =================== Autocomplete: BOM Urdido =================== */
    function initAutocompleteBOMUrdido() {
        setupAutocomplete({
            inputsSelector: '[data-bom-input="true"]',
            searchRoute: config.routes.buscarBomUrdido,
            containerId: 'bom-suggestions-global',
            getLabel: s => `${s.BOMID} - ${s.NAME || ''}`,
            onSelect: (inputEl, sug, hide) => {
                inputEl.value = sug.BOMID;
                inputEl.dataset.bomId = sug.BOMID;

                const filaId = inputEl.dataset.filaId;
                const kilosProgramados = toNumber(inputEl.dataset.kilos, 0);

                if (filaId && gruposData[filaId]) {
                    const anterior = gruposData[filaId].bomId;
                    gruposData[filaId].bomId = sug.BOMID;
                    if (anterior && anterior !== sug.BOMID) LS.wipeMateriales(anterior);
                }
                if (filaId === filaSeleccionadaId) cargarMaterialesUrdido(sug.BOMID, kilosProgramados, true);
                hide();
            }
        });

        // blur manual mantiene comportamiento original
        qsa('[data-bom-input="true"]').forEach(input => {
            input.addEventListener('blur', (e) => {
                const filaId = e.target.dataset.filaId;
                const bomId  = (e.target.value || '').trim();
                if (filaId === filaSeleccionadaId && bomId) {
                    const kilos = toNumber(e.target.dataset.kilos, 0);
                    if (gruposData[filaId]) {
                        const prev = gruposData[filaId].bomId;
                        gruposData[filaId].bomId = bomId;
                        e.target.dataset.bomId = bomId;
                        if (prev && prev !== bomId) { LS.wipeMateriales(prev); cargarMaterialesUrdido(bomId, kilos, true); }
                        else { cargarMaterialesUrdido(bomId, kilos, false); }
                    }
                }
            });
        });
    }

    /* =================== Autocomplete: BOM Engomado (texto libre) =================== */
    function initAutocompleteBOMEngomado() {
        setupAutocomplete({
            inputsSelector: '#inputLMatEngomado',
            searchRoute: config.routes.buscarBomEngomado,
            containerId: 'bom-engomado-suggestions',
            getLabel: s => `${s.BOMID} - ${s.NAME || ''}`,
            onSelect: (inputEl, sug, hide) => { inputEl.value = sug.BOMID; hide(); }
        });
    }

    /* =================== Materiales: Urdido =================== */
    async function cargarMaterialesUrdido(bomId, kilosProgramados=0, forzar=false) {
        const id = (bomId || '').trim();
        if (isBlank(id)) { renderTablaMaterialesUrdido([], kilosProgramados, null); renderTablaMaterialesEngomado([], null); return; }

        if (!forzar) {
            const saved = LS.getMateriales(id);
            if (saved && Array.isArray(saved.materialesUrdido) && saved.materialesUrdido.length &&
                Array.isArray(saved.materialesEngomado) && saved.materialesEngomado.length) {
                renderTablaMaterialesUrdidoDesdeStorage(saved.materialesUrdido, kilosProgramados, id);
                renderTablaMaterialesEngomado(saved.materialesEngomado, id);
                setTimeout(() => restaurarSelecciones(LS.getSelecciones(id)), 100);
                return;
            }
        }

        try {
            const url = new URL(config.routes.materialesUrdido, window.location.origin);
            url.searchParams.set('bomId', id);
            const data = await fetchJSON(url.toString());
            renderTablaMaterialesUrdido(Array.isArray(data)?data:[], kilosProgramados, id, true);
        } catch(e){
            renderTablaMaterialesUrdido([], kilosProgramados, id, false);
        }
    }

    function renderTablaMaterialesUrdido(materiales=[], kilosProgramados=0, bomId=null, forzarEngomado=false) {
        const tbody = qs('#tbodyMaterialesUrdido');
        if (!materiales.length) {
            tbody.innerHTML = `<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">
                <i class="fa-solid fa-circle-info text-gray-400 mb-2"></i><p>No hay materiales de urdido disponibles.</p></td></tr>`;
            renderTablaMaterialesEngomado([], bomId);
            return;
        }

        // guarda temporal en gruposData
        if (bomId) {
            if (filaSeleccionadaId && gruposData[filaSeleccionadaId]) {
                gruposData[filaSeleccionadaId].materialesUrdido = materiales;
                gruposData[filaSeleccionadaId].bomId = bomId;
            } else {
                Object.keys(gruposData).forEach(fid => {
                    if (gruposData[fid].bomId === bomId) gruposData[fid].materialesUrdido = materiales;
                });
            }
        }

        tbody.innerHTML = '';
        const kilosProg = toNumber(kilosProgramados, 0);

        for (const m of materiales) {
            const consumo = Math.round(toNumber(m.BomQty,0)*1000)/1000;
            const kilos   = kilosProg * consumo;
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50';
            tr.innerHTML = `
                <td class="px-2 py-3 text-xs text-center">${m.ItemId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${m.ConfigId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${fmtNumber(consumo, 3)}</td>
                <td class="px-2 py-3 text-xs text-center">${fmtNumber(kilos)}</td>
            `;
            tbody.appendChild(tr);
        }

        const itemIds  = [...new Set(materiales.map(m=>m.ItemId).filter(Boolean))];
        const configIds= [...new Set(materiales.map(m=>m.ConfigId).filter(v => !isBlank(v)))];
        if (itemIds.length) cargarMaterialesEngomado(itemIds, configIds, bomId, forzarEngomado);
        else renderTablaMaterialesEngomado([], bomId);
    }

    function renderTablaMaterialesUrdidoDesdeStorage(materiales=[], kilosProgramados=0, bomId=null) {
        const tbody = qs('#tbodyMaterialesUrdido');
        if (!materiales.length) {
            tbody.innerHTML = `<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">
                <i class="fa-solid fa-circle-info text-gray-400 mb-2"></i><p>No hay materiales de urdido disponibles.</p></td></tr>`;
            return;
        }
        tbody.innerHTML = '';
        const kilosProg = toNumber(kilosProgramados, 0);

        for (const m of materiales) {
            const consumo = Math.round(toNumber(m.BomQty,0)*1000)/1000;
            const kilos   = kilosProg * consumo;
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50';
            tr.innerHTML = `
                <td class="px-2 py-3 text-xs text-center">${m.ItemId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${m.ConfigId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${fmtNumber(consumo, 3)}</td>
                <td class="px-2 py-3 text-xs text-center">${fmtNumber(kilos)}</td>
            `;
            tbody.appendChild(tr);
        }
    }

    /* =================== Materiales: Engomado =================== */
    async function cargarMaterialesEngomado(itemIds, configIds=[], bomId=null, forzar=false) {
        if (!itemIds || !itemIds.length) { renderTablaMaterialesEngomado([], bomId); return; }

        if (bomId && !forzar) {
            const saved = LS.getMateriales(bomId);
            if (saved && Array.isArray(saved.materialesEngomado) && saved.materialesEngomado.length) {
                renderTablaMaterialesEngomado(saved.materialesEngomado, bomId);
                setTimeout(()=>restaurarSelecciones(LS.getSelecciones(bomId)), 50);
                return;
            }
        }

        try {
            const base = config.routes.materialesEngomado;
            const params = new URLSearchParams();
            itemIds.forEach(v => params.append('itemIds[]', v));
            (configIds || []).forEach(v => params.append('configIds[]', v));
            const data = await fetchJSON(`${base}?${params.toString()}`);

            if (bomId) {
                let matUrd = [];
                if (filaSeleccionadaId && gruposData[filaSeleccionadaId]?.materialesUrdido) {
                    matUrd = gruposData[filaSeleccionadaId].materialesUrdido;
                } else {
                    Object.keys(gruposData).forEach(fid => {
                        if (!matUrd.length && gruposData[fid].bomId === bomId && gruposData[fid].materialesUrdido) {
                            matUrd = gruposData[fid].materialesUrdido;
                        }
                    });
                    if (!matUrd.length) {
                        const saved = LS.getMateriales(bomId);
                        if (saved?.materialesUrdido?.length) matUrd = saved.materialesUrdido;
                    }
                }
                LS.setMateriales(bomId, matUrd, Array.isArray(data)?data:[]);
            }

            renderTablaMaterialesEngomado(Array.isArray(data)?data:[], bomId);
        } catch(e){
            renderTablaMaterialesEngomado([], bomId);
        }
    }

    function renderTablaMaterialesEngomado(materiales=[], bomId=null) {
        const tbody = qs('#tbodyMaterialesEngomado');
        if (!Array.isArray(materiales) || !materiales.length) {
            tbody.innerHTML = `<tr><td colspan="14" class="px-4 py-8 text-center text-gray-500">
                <i class="fa-solid fa-circle-info text-gray-400 mb-2"></i><p>No hay materiales de engomado disponibles.</p></td></tr>`;

            // Guardar materiales vacíos en gruposData
            if (filaSeleccionadaId && gruposData[filaSeleccionadaId]) {
                gruposData[filaSeleccionadaId].materialesEngomado = [];
            }
            return;
        }

        // Guardar materiales en gruposData para uso posterior
        if (filaSeleccionadaId && gruposData[filaSeleccionadaId]) {
            gruposData[filaSeleccionadaId].materialesEngomado = materiales;
        } else {
            Object.keys(gruposData).forEach(fid => {
                if (gruposData[fid].bomId === bomId) {
                    gruposData[fid].materialesEngomado = materiales;
                }
            });
        }

        tbody.innerHTML = '';
        for (const m of materiales) {
            const kilos  = toNumber(m.PhysicalInvent, 0);
            const conos  = toNumber(m.TwTiras, 0);
            const lotePr = m.TwCalidadFlog || '-';
            const noProv = m.TwClienteFlog || '-';
            const prodDate = m.ProdDate ? fmtDate(m.ProdDate) : (m.ProdDate || '-');
            const checkboxKey = `${m.ItemId || ''}_${m.InventSerialId || ''}`;

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50';
            tr.dataset.materialData = JSON.stringify(m); // Almacenar datos completos en el DOM
            tr.innerHTML = `
                <td class="px-2 py-3 text-xs text-center">${m.ItemId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${m.ConfigId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${m.InventSizeId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${m.InventColorId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${m.InventLocationId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${m.InventBatchId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${m.WMSLocationId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${m.InventSerialId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${fmtNumber(kilos)}</td>
                <td class="px-2 py-3 text-xs text-center">${fmtNumber(conos)}</td>
                <td class="px-2 py-3 text-xs text-center">${lotePr}</td>
                <td class="px-2 py-3 text-xs text-center">${noProv}</td>
                <td class="px-2 py-3 text-xs text-center">${prodDate}</td>
                <td class="px-2 py-3 text-center">
                    <input type="checkbox" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 checkbox-material"
                        data-material-id="${m.ItemId || ''}" data-serial-id="${m.InventSerialId || ''}"
                        data-checkbox-key="${checkboxKey}" data-bom-id="${bomId || ''}">
                </td>
            `;
            tbody.appendChild(tr);
        }

        agregarEventListenersCheckboxes(bomId);
        if (bomId) setTimeout(()=>restaurarSelecciones(LS.getSelecciones(bomId)), 50);
    }

    /* =================== Checkboxes (selecciones) =================== */
    function agregarEventListenersCheckboxes(bomId) {
        qsa('.checkbox-material').forEach(chk => {
            chk.addEventListener('change', () => guardarSeleccionesCheckboxes(bomId));
        });
    }

    function guardarSeleccionesCheckboxes(bomId) {
        let id = bomId;
        if (isBlank(id)) {
            const first = qs('.checkbox-material');
            id = first?.dataset.bomId || gruposData[filaSeleccionadaId]?.bomId || '';
            if (isBlank(id)) return;
        }
        const selecciones = qsa('.checkbox-material').filter(c => c.checked).map(c => ({
            materialId: c.dataset.materialId || '',
            serialId  : c.dataset.serialId || '',
            checkboxKey: c.dataset.checkboxKey || ''
        }));
        LS.setSelecciones(id, selecciones);
    }

    function restaurarSelecciones(selecciones) {
        if (!selecciones || !selecciones.length) return;
        const keys = new Set(selecciones.map(s => s.checkboxKey || `${s.materialId}_${s.serialId}`));
        qsa('.checkbox-material').forEach(chk => { if (keys.has(chk.dataset.checkboxKey || '')) chk.checked = true; });
    }

    /* =================== Anchos Balona & Máquinas Engomado =================== */
    async function cargarAnchosBalona(cuenta, tipo) {
        try {
            const url = new URL(config.routes.anchosBalona, window.location.origin);
            if (!isBlank(cuenta)) url.searchParams.set('cuenta', cuenta);
            if (!isBlank(tipo))   url.searchParams.set('tipo', tipo);
            const data = await fetchJSON(url.toString());
            const list = (data && data.success && Array.isArray(data.data)) ? data.data : [];
            const select = qs('#inputAnchoBalonas'); if (!select) return;
            select.innerHTML = '<option value="">Seleccione</option>';
            list.forEach(it => {
                const opt = document.createElement('option');
                opt.value = it.anchoBalona || '';
                opt.textContent = it.anchoBalona || '';
                select.appendChild(opt);
            });
            if (list.length && list[0].anchoBalona) select.value = list[0].anchoBalona;
        } catch(e) { limpiarSelectAnchosBalona(); }
    }

    function limpiarSelectAnchosBalona() {
        const select = qs('#inputAnchoBalonas');
        if (select) { select.innerHTML = '<option value="">Seleccione</option>'; select.value = ''; }
    }

    async function cargarMaquinasEngomado() {
        try {
            const data = await fetchJSON(config.routes.maquinasEngomado);
            const list = (data && data.success && Array.isArray(data.data)) ? data.data : [];
            const select = qs('#inputMaquinaEngomado'); if (!select) return;
            select.innerHTML = '<option value="">Seleccione</option>';
            list.forEach(it => {
                const opt = document.createElement('option');
                opt.value = it.maquinaId || '';
                opt.textContent = it.nombre || it.maquinaId || '';
                select.appendChild(opt);
            });
            // Seleccionar automáticamente el primer valor si existe
            if (list.length && list[0].maquinaId) {
                select.value = list[0].maquinaId;
            }
        } catch(e){ /* noop */ }
    }

    /* =================== Metraje de Telas =================== */
    function actualizarMetrajeTelas() {
        if (!filaSeleccionadaId || !gruposData[filaSeleccionadaId]) return;
        const metros = toNumber(gruposData[filaSeleccionadaId].grupo?.metros, 0);
        const noTelas = toNumber(qs('#inputNoTelas')?.value, 2);
        const target = qs('#inputMetrajeTelas');
        if (!target) return;
        target.value = (metros>0 && noTelas>0) ? fmtNumber(metros/noTelas, 2) : '';
    }

    /* =================== Init =================== */
    function initCreacionOrdenes(cfg) {
        config = cfg || {};

        // Inicializar datos de telares
        telaresData = normalizeInput(config.telaresData || []);
        if (!telaresData.length) {
            const raw = new URLSearchParams(location.search).get('telares');
            if (raw) {
                try {
                    telaresData = normalizeInput(JSON.parse(decodeURIComponent(raw)));
                } catch(e){
                    // Error silencioso al parsear telares
                }
            }
        }

        renderTabla();
        renderTablaMaterialesUrdido();
        renderTablaMaterialesEngomado();

        setTimeout(() => {
            initAutocompleteBOMUrdido();
            initAutocompleteBOMEngomado();
            cargarMaquinasEngomado();

            const inputNoTelas = qs('#inputNoTelas');
            if (inputNoTelas) {
                inputNoTelas.addEventListener('input', actualizarMetrajeTelas);
                inputNoTelas.addEventListener('change', actualizarMetrajeTelas);
            }
        }, 200);
    }

    // Exponer función de inicialización globalmente
    window.initCreacionOrdenes = initCreacionOrdenes;

    /* =================== Crear Órdenes =================== */
    window.crearOrdenes = async function() {
        try {
            // Validar que haya una fila seleccionada
            if (!filaSeleccionadaId || !gruposData[filaSeleccionadaId]) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Selección requerida',
                    text: 'Por favor, seleccione una fila de la tabla principal.',
                    confirmButtonColor: '#2563eb'
                });
                return;
            }

            const grupoData = gruposData[filaSeleccionadaId];
            const grupo = grupoData.grupo;
            const bomId = grupoData.bomId;

            // Validar que tenga BOM ID
            if (isBlank(bomId)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'BOM ID requerido',
                    text: 'Por favor, ingrese un BOM ID (L.Mat Urdido) para la fila seleccionada.',
                    confirmButtonColor: '#2563eb'
                });
                return;
            }

            // Obtener materiales de engomado seleccionados (checkboxes marcados)
            const checkboxesMarcados = qsa('.checkbox-material:checked');
            if (!checkboxesMarcados.length) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Materiales requeridos',
                    text: 'Por favor, seleccione al menos un material de engomado.',
                    confirmButtonColor: '#2563eb'
                });
                return;
            }

            // Obtener datos completos de materiales seleccionados
            const materialesEngomado = [];
            const materialesCompletos = grupoData.materialesEngomado || [];

            // Obtener materiales desde gruposData o desde el DOM
            checkboxesMarcados.forEach(checkbox => {
                const materialId = checkbox.dataset.materialId;
                const serialId = checkbox.dataset.serialId;

                // Buscar el material completo en materialesCompletos
                let material = materialesCompletos.find(m =>
                    (m.ItemId || '') === materialId &&
                    (m.InventSerialId || '') === serialId
                );

                // Si no se encuentra, buscar en el DOM
                if (!material) {
                    const fila = Array.from(qsa('#tbodyMaterialesEngomado tr')).find(tr => {
                        const chk = tr.querySelector(`[data-material-id="${materialId}"][data-serial-id="${serialId}"]`);
                        return chk && chk.checked;
                    });

                    if (fila && fila.dataset.materialData) {
                        try {
                            material = JSON.parse(fila.dataset.materialData);
                        } catch(e) {
                            // Error silencioso al parsear material data
                        }
                    }
                }

                if (material) {
                    materialesEngomado.push({
                        itemId: material.ItemId || materialId || '',
                        configId: material.ConfigId || '',
                        inventSizeId: material.InventSizeId || '',
                        inventColorId: material.InventColorId || '',
                        inventLocationId: material.InventLocationId || '',
                        inventBatchId: material.InventBatchId || '',
                        wmsLocationId: material.WMSLocationId || '',
                        inventSerialId: material.InventSerialId || serialId || '',
                        kilos: toNumber(material.PhysicalInvent, 0),
                        conos: toNumber(material.TwTiras, 0),
                        loteProv: material.TwCalidadFlog || '',
                        noProv: material.TwClienteFlog || '',
                        prodDate: material.ProdDate || null,
                        status: 'Programado'
                    });
                }
            });

            // Obtener datos de construcción urdido (Tabla 4)
            const construccionUrdido = [];
            const filasConstruccion = qsa('#tbodyConstruccionUrdido tr');
            filasConstruccion.forEach(fila => {
                const inputs = fila.querySelectorAll('input');
                if (inputs.length >= 2) {
                    const julios = inputs[0]?.value?.trim();
                    const hilos = inputs[1]?.value?.trim();
                    const observaciones = inputs[2]?.value?.trim() || '';

                    // Solo agregar si tiene al menos julios o hilos
                    if (!isBlank(julios) || !isBlank(hilos)) {
                        construccionUrdido.push({
                            julios: julios || '',
                            hilos: hilos || '',
                            observaciones: observaciones
                        });
                    }
                }
            });

            // Validar que haya al menos una fila de construcción con datos
            if (!construccionUrdido.length) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Construcción requerida',
                    text: 'Por favor, complete al menos una fila de construcción urdido (No. Julios o Hilos).',
                    confirmButtonColor: '#2563eb'
                });
                return;
            }

            // Obtener datos de engomado (Tabla 5)
            const inputNucleo = qs('#inputNucleo');
            const inputNoTelas = qs('#inputNoTelas');
            const inputAnchoBalonas = qs('#inputAnchoBalonas');
            const inputMetrajeTelas = qs('#inputMetrajeTelas');
            const inputCuendeadosMin = qs('#inputCuendeadosMin');
            const inputMaquinaEngomado = qs('#inputMaquinaEngomado');
            const inputLMatEngomado = qs('#inputLMatEngomado');
            const inputObservaciones = qs('#inputObservaciones');

            // Obtener valores directamente desde los elementos DOM justo antes de validar
            // Leer los valores frescos desde el DOM para evitar problemas de sincronización
            const datosEngomado = {
                nucleo: inputNucleo ? (inputNucleo.value || '') : '',
                noTelas: inputNoTelas ? (inputNoTelas.value || '') : '',
                anchoBalonas: inputAnchoBalonas ? (inputAnchoBalonas.value || '') : '',
                metrajeTelas: inputMetrajeTelas ? (inputMetrajeTelas.value || '') : '',
                cuendeadosMin: inputCuendeadosMin ? (inputCuendeadosMin.value || '') : '',
                maquinaEngomado: inputMaquinaEngomado ? (inputMaquinaEngomado.value || '') : '',
                lMatEngomado: inputLMatEngomado ? (inputLMatEngomado.value || '').trim() : '',
                observaciones: inputObservaciones ? (inputObservaciones.value || '').trim() : ''
            };

            // Validar campos requeridos - leer valores directamente desde DOM para validación
            const camposFaltantes = [];

            // Validar Núcleo (select)
            const nucleoVal = inputNucleo ? inputNucleo.value : null;
            if (!nucleoVal || nucleoVal === '' || (inputNucleo && inputNucleo.selectedIndex === 0 && inputNucleo.options[0] && inputNucleo.options[0].value === '')) {
                camposFaltantes.push('Núcleo');
            }

            // Validar No. de Telas (number input)
            const noTelasVal = inputNoTelas ? inputNoTelas.value : null;
            if (!noTelasVal || noTelasVal === '' || noTelasVal === null) {
                camposFaltantes.push('No. de Telas');
            }

            // Validar Ancho Balonas (select)
            const anchoBalonasVal = inputAnchoBalonas ? inputAnchoBalonas.value : null;
            if (!anchoBalonasVal || anchoBalonasVal === '' || (inputAnchoBalonas && inputAnchoBalonas.selectedIndex === 0 && inputAnchoBalonas.options[0] && inputAnchoBalonas.options[0].value === '')) {
                camposFaltantes.push('Ancho Balonas');
            }

            // Validar Metraje de Telas (text input - calculado)
            const metrajeVal = inputMetrajeTelas ? inputMetrajeTelas.value : null;
            if (!metrajeVal || metrajeVal === '' || metrajeVal.trim() === '') {
                camposFaltantes.push('Metraje de Telas');
            }

            // Validar Cuendeados Mín. por Tela (number input)
            const cuendeadosVal = inputCuendeadosMin ? inputCuendeadosMin.value : null;
            if (!cuendeadosVal || cuendeadosVal === '' || cuendeadosVal === null) {
                camposFaltantes.push('Cuendeados Mín. por Tela');
            }

            // Validar Máquina Engomado (select)
            const maquinaVal = inputMaquinaEngomado ? inputMaquinaEngomado.value : null;
            if (!maquinaVal || maquinaVal === '' || (inputMaquinaEngomado && inputMaquinaEngomado.selectedIndex === 0 && inputMaquinaEngomado.options[0] && inputMaquinaEngomado.options[0].value === '')) {
                camposFaltantes.push('Máquina Engomado');
            }

            // Validar L Mat Engomado (text input con autocomplete)
            const lMatVal = inputLMatEngomado ? inputLMatEngomado.value.trim() : null;
            if (!lMatVal || lMatVal === '') {
                camposFaltantes.push('L Mat Engomado');
            }

            if (camposFaltantes.length > 0) {
                const listaCampos = '<ul style="text-align:left;margin-left:1rem;margin-top:0.5rem;">' +
                    camposFaltantes.map(campo => `<li>• ${campo}</li>`).join('') +
                    '</ul>';

                Swal.fire({
                    icon: 'warning',
                    title: 'Campos requeridos',
                    html: `Por favor, complete los siguientes campos requeridos de Datos de Engomado:${listaCampos}`,
                    confirmButtonColor: '#2563eb'
                });

                // Enfocar el primer campo faltante
                if (camposFaltantes.includes('Núcleo') && inputNucleo) {
                    inputNucleo.focus();
                    inputNucleo.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else if (camposFaltantes.includes('No. de Telas') && inputNoTelas) {
                    inputNoTelas.focus();
                    inputNoTelas.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else if (camposFaltantes.includes('Ancho Balonas') && inputAnchoBalonas) {
                    inputAnchoBalonas.focus();
                    inputAnchoBalonas.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else if (camposFaltantes.includes('Metraje de Telas') && inputMetrajeTelas) {
                    inputMetrajeTelas.focus();
                    inputMetrajeTelas.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else if (camposFaltantes.includes('Cuendeados Mín. por Tela') && inputCuendeadosMin) {
                    inputCuendeadosMin.focus();
                    inputCuendeadosMin.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else if (camposFaltantes.includes('Máquina Engomado') && inputMaquinaEngomado) {
                    inputMaquinaEngomado.focus();
                    inputMaquinaEngomado.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else if (camposFaltantes.includes('L Mat Engomado') && inputLMatEngomado) {
                    inputLMatEngomado.focus();
                    inputLMatEngomado.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                return;
            }

            // Preparar datos del grupo
            const grupoPayload = {
                telaresStr: grupo.telaresStr || '',
                noTelarId: grupo.telares?.[0]?.no_telar || grupo.telaresStr || '',
                tipo: grupo.tipo || '',
                cuenta: grupo.cuenta || '',
                calibre: grupo.calibre || '',
                fechaReq: grupo.fechaReq || '',
                fibra: grupo.hilo || '',
                hilo: grupo.hilo || '',
                metros: grupo.metros || 0,
                kilos: grupo.kilos || 0,
                noProduccion: grupo.noProduccion || '',
                salonTejidoId: grupo.destino || '',
                destino: grupo.destino || '',
                maquinaId: grupo.maquinaId || grupo.urdido || '',
                bomId: bomId,
                tipoAtado: grupo.tipoAtado || 'Normal',
                status: 'Programado'
            };

            // Preparar payload completo
            const payload = {
                grupo: grupoPayload,
                materialesEngomado: materialesEngomado,
                construccionUrdido: construccionUrdido,
                datosEngomado: datosEngomado
            };

            // Mostrar indicador de carga
            const button = document.querySelector('[onclick="crearOrdenes()"]');
            const originalText = button?.textContent || 'Crear Órdenes';
            if (button) {
                button.disabled = true;
                button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creando...';
            }

            // Enviar datos al servidor
            const response = await fetch(config.routes.crearOrdenes, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            // Restaurar botón
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }

            if (result.success) {
                // Limpiar cache de localStorage
                localStorage.removeItem('creacion_ordenes_materiales');
                localStorage.removeItem('creacion_ordenes_selecciones');

                // Mostrar mensaje de éxito y redirigir
                Swal.fire({
                    icon: 'success',
                    title: '¡Órdenes creadas exitosamente!',
                    html: `<p>Folio: <strong>${result.data.folio}</strong></p><p>Folio Consumo: <strong>${result.data.folioConsumo}</strong></p>`,
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    // Redirigir a la ruta relativa (Laravel manejará el hostname/IP automáticamente)
                    window.location.href = '/programa-urd-eng/reservar-programar';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al crear órdenes',
                    text: result.error || 'Error desconocido',
                    confirmButtonColor: '#2563eb'
                });
            }

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error al crear órdenes',
                text: error.message || 'Ocurrió un error inesperado',
                confirmButtonColor: '#2563eb'
            });

            // Restaurar botón en caso de error
            const button = document.querySelector('[onclick="crearOrdenes()"]');
            if (button) {
                button.disabled = false;
                button.textContent = 'Crear Órdenes';
            }
        }
    };

    })();
