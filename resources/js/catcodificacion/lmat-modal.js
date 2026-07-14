/**
 * Modal L.Mat de Codificación.
 *
 * Este módulo encapsula consultas a catálogos AX, cálculo de cantidades,
 * renderizado del modal y persistencia. El Blade solo proporciona la fila
 * seleccionada y la función para refrescar la tabla.
 */

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function defaultToast(message, type = 'info') {
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
        return;
    }

    console.warn(message);
}

// ── Materiales del modal L.Mat (Artículo/Tamaño/Color, cascada por ItemId) ──
const LMatMateriales = (() => {
    let calibres = null;
    const configs = new Map();
    const tamanos = new Map();
    const colores = new Map();

    async function getCalibres() {
        if (calibres) return calibres;
        try {
            const resp = await fetch('/planeacion/lmat/api/calibres', { headers: { Accept: 'application/json' } });
            const json = await resp.json();
            calibres = (json.data || []).map(i => i.ItemId).filter(Boolean);
        } catch (e) {
            console.error('No se pudieron cargar artículos (calibres) L.Mat', e);
            calibres = [];
        }
        return calibres;
    }

    async function getConfigs(itemId) {
        if (!itemId) return [];
        if (configs.has(itemId)) return configs.get(itemId);
        let lista = [];
        try {
            const resp = await fetch('/planeacion/lmat/api/configs?itemId=' + encodeURIComponent(itemId), { headers: { Accept: 'application/json' } });
            const json = await resp.json();
            lista = (json.data || [])
                .map(c => c.ConfigId)
                .filter(Boolean)
                .filter(c => String(c).trim().toUpperCase() !== 'HILO');
        } catch (e) {
            console.error('No se pudieron cargar configs (fibra) L.Mat', e);
        }
        configs.set(itemId, lista);
        return lista;
    }

    async function getTamanos(itemId) {
        if (!itemId) return [];
        if (tamanos.has(itemId)) return tamanos.get(itemId);
        let lista = [];
        try {
            const resp = await fetch('/planeacion/lmat/api/tamanos?itemId=' + encodeURIComponent(itemId), { headers: { Accept: 'application/json' } });
            const json = await resp.json();
            lista = (json.data || []).map(i => i.InventSizeId).filter(Boolean);
        } catch (e) {
            console.error('No se pudieron cargar tamaños L.Mat', e);
        }
        tamanos.set(itemId, lista);
        return lista;
    }

    async function getColores(itemId) {
        if (!itemId) return [];
        if (colores.has(itemId)) return colores.get(itemId);
        let lista = [];
        try {
            const resp = await fetch('/planeacion/lmat/api/colores?itemId=' + encodeURIComponent(itemId), { headers: { Accept: 'application/json' } });
            const json = await resp.json();
            lista = (json.data || []).map(c => c.InventColorId).filter(Boolean);
        } catch (e) {
            console.error('No se pudieron cargar colores L.Mat', e);
        }
        colores.set(itemId, lista);
        return lista;
    }

    async function getMatrizCalibre(clave) {
        const params = new URLSearchParams({
            tipo: clave.tipo,
        });
        if (clave.calibre !== null) params.set('calibre', clave.calibre.toFixed(1));
        if (clave.fibraId !== null) params.set('fibraId', clave.fibraId);
        if (clave.cuenta) params.set('cuenta', clave.cuenta);

        const resp = await fetch('/planeacion/lmat/api/matriz-calibre?' + params.toString(), {
            headers: { Accept: 'application/json' },
        });
        const json = await resp.json().catch(() => ({}));
        if (!resp.ok || json.success !== true) {
            throw new Error(json.message || `Error ${resp.status} al consultar Matriz de Calibres`);
        }

        return json.found ? json.data : null;
    }

    return { getCalibres, getConfigs, getTamanos, getColores, getMatrizCalibre };
})();

function setSelectOptionsLMat(selectEl, opciones, valorActual) {
    if (!selectEl) return;
    const esConfig = selectEl.name === 'config[]';
    const esArticulo = selectEl.name === 'articulo[]';
    let actual = valorActual !== null && valorActual !== undefined ? String(valorActual) : '';
    if (esConfig && actual.trim().toUpperCase() === 'HILO') actual = '';
    let lista = (opciones || []).map(String);
    if (esConfig) lista = lista.filter(v => String(v).trim().toUpperCase() !== 'HILO');
    // Artículos: solo valores del catálogo (GET calibres). No inventar opciones.
    // Otros selects: si el valor capturado no está en catálogo, se conserva.
    if (actual !== '' && !lista.includes(actual)) {
        if (esArticulo) actual = '';
        else lista = [actual, ...lista];
    }
    if (lista.length === 0) lista = [''];
    // Config/Artículo: siempre opción vacía para que el navegador NO auto-seleccione el primero de AX
    // (si FibraPie es null, Config debe quedar en "Seleccione..." y Fibra vacía).
    if ((esConfig || esArticulo) && !lista.includes('')) lista = ['', ...lista];

    selectEl.innerHTML = lista.map((valor) => {
        const selected = valor === actual ? ' selected' : '';
        const texto = valor === '' ? 'Seleccione...' : valor;
        return `<option value="${valor}"${selected}>${texto}</option>`;
    }).join('');
    // Forzar valor vacío explícito (evita selectedIndex=0 con primer Config de AX).
    if (actual === '') selectEl.value = '';
}

function parseCalibrePartsLMat(valor) {
    const s = String(valor ?? '').trim().replace(',', '.');
    const m = s.match(/^(\d+)(?:[./](\d+))?$/);
    if (!m) return null;
    const base = m[1];
    const frac = m[2] != null ? m[2] : null;
    const num = Number(frac != null ? `${base}.${frac}` : base);
    if (!Number.isFinite(num)) return null;
    return { base, frac, num };
}

/** Resuelve Items crudo (ej. 8.0) a un ItemId del GET de calibres: exacto o más cercano del mismo base (8/1). Si no hay ninguno, ''. */
function resolverArticuloDesdeCalibres(itemsCrudo, calibres) {
    const lista = (calibres || []).map(String).filter(Boolean);
    if (!lista.length) return '';
    const parts = parseCalibrePartsLMat(itemsCrudo);
    if (!parts) return '';

    const candidatosExactos = [];
    if (parts.frac != null) {
        candidatosExactos.push(`${parts.base}/${parts.frac}`);
        candidatosExactos.push(`${parts.base}.${parts.frac}`);
    }
    candidatosExactos.push(parts.base);
    const n = Number(String(itemsCrudo ?? '').replace(',', '.'));
    if (Number.isFinite(n)) {
        candidatosExactos.push(Number.isInteger(n) ? String(n) : n.toFixed(1).replace('.', '/'));
    }
    for (const c of candidatosExactos) {
        if (lista.includes(c)) return c;
    }

    const mismosBase = lista.filter((item) => {
        const p = parseCalibrePartsLMat(item);
        return p && p.base === parts.base;
    });
    if (!mismosBase.length) return '';

    let mejor = '';
    let mejorDist = Infinity;
    for (const item of mismosBase) {
        const p = parseCalibrePartsLMat(item);
        if (!p) continue;
        const dist = Math.abs(p.num - parts.num);
        if (dist < mejorDist) {
            mejorDist = dist;
            mejor = item;
        }
    }
    return mejor;
}

function crearClaveMatrizLMat(tipo, calibre, fibraId, cuenta = null) {
    const tipoNormalizado = String(tipo ?? '').trim().toUpperCase();
    const fibraNormalizada = String(fibraId ?? '').trim().toUpperCase() || null;
    if (!['RIZO', 'PIE', 'TRAMA'].includes(tipoNormalizado)) {
        return null;
    }

    let calibreNormalizado = null;
    if (String(calibre ?? '').trim() !== '') {
        const calibreNumerico = Number(String(calibre ?? '').replace(',', '.'));
        if (Number.isFinite(calibreNumerico) && calibreNumerico > 0) {
            calibreNormalizado = Math.round(calibreNumerico * 10) / 10;
        }
    }
    if (tipoNormalizado !== 'PIE' && (!fibraNormalizada || calibreNormalizado === null)) return null;
    if (tipoNormalizado === 'PIE' && !fibraNormalizada && calibreNormalizado === null) return null;

    const cuentaNormalizada = tipoNormalizado === 'TRAMA'
        ? null
        : String(cuenta ?? '').trim().toUpperCase();
    if (tipoNormalizado !== 'TRAMA' && !cuentaNormalizada) return null;

    return {
        tipo: tipoNormalizado,
        calibre: calibreNormalizado,
        fibraId: fibraNormalizada,
        cuenta: cuentaNormalizada,
    };
}

function serializarClaveMatrizLMat(clave) {
    if (!clave) return '';
    const calibre = clave.calibre === null ? '' : clave.calibre.toFixed(1);
    return [clave.tipo, calibre, clave.fibraId || '', clave.cuenta || ''].join('|');
}

async function openLMatModal(context = {}) {
    const {
        fallbackToast = defaultToast,
        getSelectedRecord = () => null,
        reloadData = () => Promise.resolve(),
        showToast = window.showToast || defaultToast,
    } = context;
    if (typeof Swal === 'undefined') {
        fallbackToast('SweetAlert2 no está cargado.', 'warning');
        return;
    }

    const registroSeleccionado = getSelectedRecord();

    if (!registroSeleccionado) {
        fallbackToast('Selecciona primero una fila.', 'warning');
        return;
    }

    const telarId = String(registroSeleccionado?.TelarId ?? '').trim();
    if (!telarId) {
        fallbackToast('La fila seleccionada no tiene telar.', 'warning');
        return;
    }

    const orden = registroSeleccionado?.OrdenTejido || '';
    const salon = registroSeleccionado?.Departamento || '';
    const telarSeleccionado = parseInt(registroSeleccionado?.TelarId, 10) || 0;

    // Si ya hay una L.Mat guardada para esta Orden en CatLMat, se recarga para editarla.
    let guardadoLMat = null;
    if (orden) {
        try {
            const respGuardado = await fetch('/planeacion/lmat/api/por-orden/' + encodeURIComponent(orden), { headers: { Accept: 'application/json' } });
            const jsonGuardado = await respGuardado.json();
            if (jsonGuardado.success && Array.isArray(jsonGuardado.data) && jsonGuardado.data.length) {
                guardadoLMat = jsonGuardado.data;
            }
        } catch (e) {
            console.error('No se pudo cargar CatLMat', e);
        }
    }
    let tamano = registroSeleccionado?.InventSizeId || 'Tamaño';
    let itemId = registroSeleccionado?.ItemId || '';
    const articulo = registroSeleccionado?.Nombre || registroSeleccionado?.ItemId || 'Nombre Articulo';
    const codigoDibujo = registroSeleccionado?.CodigoDibujo || 'Cod Dibujo';
    let pesoCrudo = registroSeleccionado?.P_crudo || registroSeleccionado?.PesoCrudo || 737;
    const cuentaRizo = String(registroSeleccionado?.CuentaRizo ?? '').trim();
    const cuentaPie = String(registroSeleccionado?.CuentaPie ?? '').trim();
    // Calibre numérico → formato ItemId: 450.1 → 450/1, 16.1 → 16/1, pero entero (10.0) queda como 10.
    const calibreAItemId = (numero) => Number.isInteger(numero) ? String(numero) : numero.toFixed(1).replace('.', '/');
    const formatearCalibre = (valor, fallback) => {
        const numero = Number(String(valor ?? '').replace(',', '.'));
        return Number.isFinite(numero) ? calibreAItemId(numero) : fallback;
    };
    const calibreRizo = formatearCalibre(registroSeleccionado?.CalibreRizo, 'CalibreRizo');
    const calibrePie = formatearCalibre(registroSeleccionado?.CalibrePie, 'CalibrePie');
    // CatCodificados: FibraRizo / FibraPie / FibraId(trama). null → vacío (no inventar).
    const normalizarTextoCatLMat = (valor) => {
        const s = String(valor ?? '').trim();
        if (!s || s.toLowerCase() === 'null' || s.toLowerCase() === 'undefined') return '';
        return s;
    };
    const formatoCalibreCatLMat = (valor) => {
        const crudo = normalizarTextoCatLMat(valor);
        if (!crudo) return '';
        const n = Number(String(crudo).replace(',', '.'));
        if (!Number.isFinite(n) || !(n > 0)) return '';
        // Tra "10" → "10"; CalibreRizo "12.1" → "12.1"
        return Number.isInteger(n) ? String(n) : n.toFixed(1);
    };
    const fibraRizo = normalizarTextoCatLMat(registroSeleccionado?.FibraRizo);
    const fibraPie = normalizarTextoCatLMat(registroSeleccionado?.FibraPie);
    const fibraTrama = normalizarTextoCatLMat(
        registroSeleccionado?.FibraTramaFondoC1 ?? registroSeleccionado?.FibraId
    );
    // InventSizeId sin espacios: 2766-16/1 (no "2766 - 16/1").
    const tamanoRizo = String(cuentaRizo || 'CuentaRizo').trim() + '-' + String(calibreRizo).trim();
    const tamanoPie = String(cuentaPie || 'CuentaPie').trim() + '-' + String(calibrePie).trim();
    const normalizarInventSizeIdLMat = (valor) => String(valor ?? '')
        .trim()
        .replace(/\s*-\s*/g, '-')
        .replace(/\s+/g, '');
    const truncLmat = (value, max) => {
        const text = String(value ?? '');
        return text.length > max ? text.slice(0, max) : text;
    };
    function resolverAlmacenLMat(articuloLMat) {
        if (String(articuloLMat || '').startsWith('JU-ENG-')) return 'A-EP-TEJID';
        if (telarSeleccionado >= 305 && telarSeleccionado <= 316) return 'A-PTE-LISO';
        if (telarSeleccionado >= 200 && telarSeleccionado <= 220) return 'A-PTE-JACQ';
        if ((telarSeleccionado >= 299 && telarSeleccionado <= 304) || (telarSeleccionado >= 317 && telarSeleccionado <= 320)) return 'A-PTE-ITEM';
        return '';
    }
    function almacenVisibleLMat(item) {
        const guardado = String(item?.almacen ?? '').trim();
        return guardado !== '' ? guardado : resolverAlmacenLMat(item?.articulo);
    }
    let nombreLMat = truncLmat(['TEJ', tamano, articulo].filter(Boolean).join(' '), 20);
    // Descripción: Nombre + CodigoDibujo (sin InventSizeId / tamaño).
    let descripcionLMat = truncLmat([articulo, codigoDibujo].filter(Boolean).join(' '), 60);
    if (guardadoLMat) {
        // Recargar cabecera desde lo guardado (Nombre=BomId). Descripción se recalcula sin tamaño.
        nombreLMat = truncLmat(guardadoLMat[0].Nombre ?? nombreLMat, 20);
        if (guardadoLMat[0].PesoCrudo != null && guardadoLMat[0].PesoCrudo !== '') pesoCrudo = guardadoLMat[0].PesoCrudo;
        if (guardadoLMat[0].ItemIdCrudo != null && guardadoLMat[0].ItemIdCrudo !== '') itemId = guardadoLMat[0].ItemIdCrudo;
        if (guardadoLMat[0].InventSizeCrudo != null && guardadoLMat[0].InventSizeCrudo !== '') tamano = guardadoLMat[0].InventSizeCrudo;
    }
    const escapeAttr = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    // Cálculo Excel (curvas → pesos g → % → cantidad kg):
    // Trama/Cn = SI(P>0, ((P*(Ancho+13/TL)*curva_peine)/100)*0.59/Hilo, 0)
    // Pie = (((Largo+Corte)*curva_luchaje/100)*0.59/HiloPie)*1.076*(CuentaPie/TL)
    // Rizo = PesoCrudo - (Pie+Trama+C1..C5)
    // Cantidad(kg) = peso_g / 1000 ; % = peso_g / PesoCrudo
    const numLMat = (valor) => {
        const n = Number(String(valor ?? '').replace(',', '.'));
        return Number.isFinite(n) ? n : 0;
    };
    const FACTOR_PIE_LMAT = 1.076;
    const DENSIDAD_HILO_LMAT = 0.59;
    const inputsCalculoLMat = {
        peine: numLMat(registroSeleccionado?.Peine),
        ancho: numLMat(registroSeleccionado?.Ancho),
        largo: numLMat(registroSeleccionado?.Largo),
        corte: numLMat(registroSeleccionado?.MedidaPlano),
        luchaje: numLMat(registroSeleccionado?.Luchaje),
        tl: numLMat(registroSeleccionado?.NoTiras),
        hiloPie: numLMat(registroSeleccionado?.CalibrePie),
        cuentaPie: numLMat(registroSeleccionado?.CuentaPie),
        pasadasTrama: numLMat(registroSeleccionado?.PasadasTramaFondoC1),
        hiloTrama: numLMat(registroSeleccionado?.Tra),
        pasadasComb: [1, 2, 3, 4, 5].map((n) => numLMat(registroSeleccionado?.[`PasadasComb${n}`])),
        hiloComb: [1, 2, 3, 4, 5].map((n) => numLMat(registroSeleccionado?.[`CalibreComb${n}`])),
    };
    const calcularPesosComponentesLMat = (pesoCrudoG) => {
        const pesoCrudoTotal = numLMat(pesoCrudoG);
        const { peine, ancho, largo, corte, luchaje, tl, hiloPie, cuentaPie, pasadasTrama, hiloTrama, pasadasComb, hiloComb } = inputsCalculoLMat;
        const curvaLuchaje = luchaje >= 33 ? 1.083 : 1.055;
        const curvaPeine = peine >= 50 ? 1.001 : 1.002;
        const pesoTramaCn = (pasadas, hilo) => {
            if (!(pasadas > 0) || !(hilo > 0) || !(tl > 0)) return 0;
            return ((pasadas * (ancho + (13 / tl)) * curvaPeine) / 100) * DENSIDAD_HILO_LMAT / hilo;
        };
        const tramaG = pesoTramaCn(pasadasTrama, hiloTrama);
        const combG = pasadasComb.map((p, i) => pesoTramaCn(p, hiloComb[i]));
        let pieG = 0;
        if (hiloPie > 0 && tl > 0) {
            pieG = (((largo + corte) * curvaLuchaje / 100) * DENSIDAD_HILO_LMAT / hiloPie)
                * FACTOR_PIE_LMAT
                * (cuentaPie / tl);
        }
        const sumaSinRizo = pieG + tramaG + combG.reduce((a, b) => a + b, 0);
        const rizoG = Math.max(0, pesoCrudoTotal - sumaSinRizo);
        return { rizoG, pieG, tramaG, combG, pesoCrudoTotal };
    };
    const pesoACantidadYPorcentajeLMat = (pesoG, pesoCrudoTotal) => {
        const cantidad = Number((pesoG / 1000).toFixed(3));
        const porcentaje = pesoCrudoTotal > 0
            ? Number(((pesoG / pesoCrudoTotal) * 100).toFixed(1))
            : 0;
        return { cantidad, porcentaje: porcentaje.toFixed(1) + '%' };
    };
    const armarFilasDesdeCalculoLMat = (pesoCrudoG) => {
        const pesos = calcularPesosComponentesLMat(pesoCrudoG);
        const rizo = pesoACantidadYPorcentajeLMat(pesos.rizoG, pesos.pesoCrudoTotal);
        const pie = pesoACantidadYPorcentajeLMat(pesos.pieG, pesos.pesoCrudoTotal);
        const trama = pesoACantidadYPorcentajeLMat(pesos.tramaG, pesos.pesoCrudoTotal);
        // Fibra + Calibre = SOLO informativos desde CatCodificados (no son Config).
        // Config empieza vacío y se elige/guarda aparte en CatLMat.ConfigId.
        const filas = [
            {
                articulo: 'JU-ENG-RI-C',
                combinacion: fibraRizo,
                items: formatoCalibreCatLMat(registroSeleccionado?.CalibreRizo),
                config: '',
                tamano: tamanoRizo,
                color: '1000',
                almacen: 'A-EP-TEJID',
                cantidad: rizo.cantidad,
                porcentaje: rizo.porcentaje,
                rol: 'rizo',
                matriz: crearClaveMatrizLMat(
                    'RIZO',
                    registroSeleccionado?.CalibreRizo,
                    fibraRizo,
                    cuentaRizo,
                ),
            },
            {
                articulo: 'JU-ENG-PI-C',
                combinacion: fibraPie,
                items: formatoCalibreCatLMat(registroSeleccionado?.CalibrePie),
                config: '',
                tamano: tamanoPie,
                color: '1000',
                almacen: 'A-EP-TEJID',
                cantidad: pie.cantidad,
                porcentaje: pie.porcentaje,
                rol: 'pie',
                matriz: crearClaveMatrizLMat(
                    'PIE',
                    registroSeleccionado?.CalibrePie,
                    fibraPie,
                    cuentaPie,
                ),
            },
        ];
        const tramaCalibre = numLMat(registroSeleccionado?.Tra);
        if (tramaCalibre > 0) {
            filas.push({
                articulo: '',
                combinacion: fibraTrama,
                items: formatoCalibreCatLMat(registroSeleccionado?.Tra),
                config: '',
                tamano: 'ENTERO',
                color: registroSeleccionado?.CodColorTrama || '',
                almacen: '',
                cantidad: trama.cantidad,
                porcentaje: trama.porcentaje,
                rol: 'trama',
                matriz: crearClaveMatrizLMat(
                    'TRAMA',
                    registroSeleccionado?.Tra,
                    fibraTrama,
                ),
            });
        }
        for (let n = 1; n <= 5; n++) {
            const calibre = numLMat(registroSeleccionado?.[`CalibreComb${n}`]);
            if (!(calibre > 0)) continue;
            const comb = pesoACantidadYPorcentajeLMat(pesos.combG[n - 1], pesos.pesoCrudoTotal);
            filas.push({
                articulo: '',
                combinacion: normalizarTextoCatLMat(registroSeleccionado?.[`FibraComb${n}`]),
                items: formatoCalibreCatLMat(registroSeleccionado?.[`CalibreComb${n}`]),
                config: '',
                tamano: 'ENTERO',
                color: registroSeleccionado?.[`CodColorC${n}`] || '',
                almacen: '',
                cantidad: comb.cantidad,
                porcentaje: comb.porcentaje,
                rol: 'c' + n,
                matriz: crearClaveMatrizLMat(
                    'TRAMA',
                    registroSeleccionado?.[`CalibreComb${n}`],
                    registroSeleccionado?.[`FibraComb${n}`],
                ),
            });
        }
        return filas;
    };

    let articulos = armarFilasDesdeCalculoLMat(pesoCrudo);

    let falloConsultaMatriz = false;
    if (!guardadoLMat) {
        await Promise.all(articulos.map(async (item) => {
            if (!item.matriz) return;

            try {
                const equivalencia = await LMatMateriales.getMatrizCalibre(item.matriz);
                if (!equivalencia) return;

                item.articulo = equivalencia.ItemId;
                item.config = equivalencia.ConfigId;
                item.tamano = equivalencia.InventSizeId;
                item.color = equivalencia.InventColorId;
                item.almacen = resolverAlmacenLMat(equivalencia.ItemId);
                item.matrizEncontrada = true;
            } catch (error) {
                falloConsultaMatriz = true;
                console.error('No se pudo consultar CatMatrizCalibres para una fila L.Mat', error);
            }
        }));
    }

    if (falloConsultaMatriz) {
        showToast('No se pudo consultar una o más equivalencias. Se usará el proceso actual para esas filas.', 'warning');
    }

    const filasExistentesLMat = [];
    articulos.forEach((f) => {
        if (f.rol === 'trama' || String(f.rol || '').startsWith('c')) filasExistentesLMat.push(f);
    });

    // Helper: Calibre display desde ItemId (solo filas añadidas manualmente, sin rol CatCodificados).
    const calibreDisplayDesdeItemIdLMat = (itemId) => {
        const s = String(itemId ?? '').trim();
        if (!s || s.startsWith('JU-ENG-')) return '';
        const parts = parseCalibrePartsLMat(s);
        if (!parts) return '';
        return Number.isInteger(parts.num) ? String(parts.num) : parts.num.toFixed(1);
    };

    // Si hay CatLMat guardada: se sobrepone articulo/config/qty/etc.
    // Fibra (combinacion) y Calibre (items) SIEMPRE se quedan de CatCodificados.
    if (guardadoLMat) {
        const defaults = articulos.slice();
        const usados = new Set();

        const tomarGuardado = (predicado) => {
            const idx = guardadoLMat.findIndex((r, i) => !usados.has(i) && predicado(r));
            if (idx < 0) return null;
            usados.add(idx);
            return guardadoLMat[idx];
        };

        articulos = defaults.map((def) => {
            let saved = null;
            if (def.rol === 'rizo') {
                saved = tomarGuardado((r) => {
                    const id = String(r.ItemId ?? '').trim();
                    return id === 'JU-ENG-RI-C' || id.startsWith('JU-ENG-RI');
                });
                if (!saved) saved = tomarGuardado(() => true);
            } else if (def.rol === 'pie') {
                saved = tomarGuardado((r) => {
                    const id = String(r.ItemId ?? '').trim();
                    return id === 'JU-ENG-PI-C' || id.startsWith('JU-ENG-PI');
                });
                if (!saved) saved = tomarGuardado(() => true);
            } else {
                // trama / c1..c5: siguiente fila CatLMat que no sea rizo/pie
                saved = tomarGuardado((r) => {
                    const id = String(r.ItemId ?? '').trim();
                    return !(id === 'JU-ENG-RI-C' || id.startsWith('JU-ENG-RI')
                        || id === 'JU-ENG-PI-C' || id.startsWith('JU-ENG-PI'));
                });
            }

            if (!saved) return def;

            return {
                ...def,
                // Fibra/Calibre informativos: se conservan de CatCodificados (def.combinacion / def.items)
                articulo: saved.ItemId ?? def.articulo,
                config: String(saved.ConfigId ?? '').trim(), // Config = CatLMat (independiente de Fibra)
                tamano: saved.InventSizeId ?? def.tamano,
                color: saved.InventColorId ?? def.color,
                almacen: saved.InventLocationId ?? resolverAlmacenLMat(saved.ItemId ?? def.articulo),
                cantidad: saved.Qty != null ? Number(saved.Qty) : def.cantidad,
                porcentaje: (saved.Porcentaje != null
                    ? Number(saved.Porcentaje).toFixed(1)
                    : String(def.porcentaje || '0.0').replace('%', '')) + '%',
                desdeCatLMat: true,
            };
        });

        // Filas extra en CatLMat que no matchearon defaults (añadidas a mano).
        guardadoLMat.forEach((r, i) => {
            if (usados.has(i)) return;
            const itemId = String(r.ItemId ?? '').trim();
            articulos.push({
                articulo: itemId,
                combinacion: '',
                items: calibreDisplayDesdeItemIdLMat(itemId),
                config: String(r.ConfigId ?? '').trim(),
                tamano: r.InventSizeId ?? '',
                color: r.InventColorId ?? '',
                almacen: r.InventLocationId ?? resolverAlmacenLMat(itemId),
                cantidad: r.Qty != null ? Number(r.Qty) : 0,
                porcentaje: (r.Porcentaje != null ? Number(r.Porcentaje).toFixed(1) : '0.0') + '%',
                rol: '',
                matriz: null,
                desdeCatLMat: true,
            });
        });
    }

    // Cada fila precargada (Tra/CalibreComb1..5) trae su propio valor real de articulo/tamaño/color;
    // hay que incluirlo aquí para que quede "selected" desde el primer render (si no está en la
    // lista, el <select> no marca nada y el navegador cae al primer option de la lista).
    const sinHiloConfig = (valor) => String(valor ?? '').trim().toUpperCase() !== 'HILO';
    const opcionesSelectLMat = {
        articulo: Array.from(new Set([
            '',
            ...articulos.filter(f => f.matrizEncontrada).map(f => f.articulo).filter(Boolean),
        ])),
        // Config NO incluye Fibras de CatCodificados; esas van solo en columna Fibra.
        config: Array.from(new Set([
            'ENTERO',
            ...articulos.map(f => f.config).filter(Boolean),
        ].filter(Boolean).filter(sinHiloConfig))),
        tamano: Array.from(new Set([
            tamano,
            tamanoRizo,
            tamanoPie,
            ...filasExistentesLMat.map(f => f.tamano).filter(Boolean),
            ...articulos.map(f => f.tamano).filter(Boolean),
        ].filter(Boolean))),
        color: Array.from(new Set([
            '1000',
            ...filasExistentesLMat.map(f => f.color).filter(Boolean),
            ...articulos.map(f => f.color).filter(Boolean),
        ])),
    };
    const esActualizacionLMat = Array.isArray(guardadoLMat) && guardadoLMat.length > 0;
    const nombreInputAttrsLMat = esActualizacionLMat
        ? 'readonly disabled title="El nombre no se puede cambiar al actualizar"'
        : '';
    const nombreInputClassLMat = esActualizacionLMat
        ? 'w-full rounded border border-amber-300 bg-amber-50 px-2 py-1.5 text-sm text-gray-700 cursor-not-allowed'
        : 'w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-800 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400';
    const guardarBtnClassLMat = esActualizacionLMat
        ? 'inline-flex min-w-[150px] items-center justify-center gap-2 rounded bg-amber-500 px-6 py-2.5 text-sm font-semibold text-white hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400'
        : 'inline-flex min-w-[150px] items-center justify-center gap-2 rounded bg-black px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-400';
    const guardarBtnIconLMat = esActualizacionLMat ? 'fas fa-edit' : 'fas fa-save';
    const guardarBtnLabelLMat = esActualizacionLMat ? 'Actualizar' : 'Guardar';
    const pesoCrudoNumerico = Number(String(pesoCrudo ?? '').replace(',', '.')) || 0;
    const totalCantidad = pesoCrudoNumerico / 1000;
    const totalPorcentaje = articulos.reduce((total, item) => total + parseFloat(String(item.porcentaje || '0').replace('%', '')), 0);
    const totalPorcentajeRedondeado = Number(totalPorcentaje.toFixed(1));
    const totalPorcentajeClass = totalPorcentajeRedondeado === 100
        ? 'text-green-700 bg-green-50'
        : (totalPorcentajeRedondeado > 100 || totalPorcentajeRedondeado < 90 ? 'text-red-700 bg-red-50' : 'text-orange-700 bg-orange-50');
    const clasesPorcentajeTotal = ['text-green-700', 'bg-green-50', 'text-orange-700', 'bg-orange-50', 'text-red-700', 'bg-red-50'];

    function buildSelectLMat(nombre, valorActual, opciones) {
        const esConfig = nombre === 'config[]';
        const esArticulo = nombre === 'articulo[]';
        let actual = valorActual != null ? String(valorActual) : '';
        if (esConfig && actual.trim().toUpperCase() === 'HILO') actual = '';
        let lista = (opciones || []).map(String);
        if (esConfig) lista = lista.filter(sinHiloConfig);
        // Artículos: no inventar opciones fuera del catálogo.
        if (actual !== '' && !lista.includes(actual)) {
            if (esArticulo) actual = '';
            else lista = [actual, ...lista];
        }
        if (actual === '' && !lista.includes('')) lista = ['', ...lista];
        return `
            <select
                name="${nombre}"
                class="w-full min-w-[110px] rounded border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-800 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
            >
                ${lista.map(opcion => {
                    const valor = String(opcion);
                    const selected = valor === actual ? ' selected' : '';
                    const texto = valor === '' ? 'Seleccione...' : valor;
                    return `<option value="${valor}"${selected}>${texto}</option>`;
                }).join('')}
            </select>
        `;
    }

    function renderConfigLMat(item) {
        return buildSelectLMat('config[]', item.config, opcionesSelectLMat.config);
    }

    function renderTamanoLMat(item) {
        return buildSelectLMat('tamano[]', item.tamano, opcionesSelectLMat.tamano);
    }

    function renderPlanoOSelectLMat(item, campo, nombre, opciones) {
        if (item.rol === 'rizo' || item.rol === 'pie') {
            return `<span class="font-medium text-gray-800">${escapeHtml(item[campo])}</span>`;
        }
        return buildSelectLMat(nombre, item[campo], opciones);
    }

    function atributosMatrizLMat(item) {
        const clave = item.matriz;
        if (!clave) return '';

        return [
            `data-matriz-clave="${escapeAttr(serializarClaveMatrizLMat(clave))}"`,
            `data-matriz-tipo="${escapeAttr(clave.tipo)}"`,
            `data-matriz-calibre="${escapeAttr(clave.calibre === null ? '' : clave.calibre.toFixed(1))}"`,
            `data-matriz-fibra-id="${escapeAttr(clave.fibraId || '')}"`,
            `data-matriz-cuenta="${escapeAttr(clave.cuenta || '')}"`,
            `data-matriz-encontrada="${item.matrizEncontrada ? '1' : '0'}"`,
            `data-preservar-articulo="${item.matrizEncontrada || item.desdeCatLMat ? '1' : '0'}"`,
        ].join(' ');
    }

    function renderFilaEditableLMat(item = { articulo: '10.1', combinacion: '', items: '', config: 'ENTERO', tamano: '', color: '1000', cantidad: 0 }) {
        return `
            <tr class="border-b border-gray-100">
                <td class="lmat-combinacion-cell px-3 py-2 font-medium text-gray-800">${escapeHtml(item.combinacion || '')}</td>
                <td class="lmat-items-cell px-3 py-2 font-medium tabular-nums text-gray-800">${escapeHtml(item.items || '')}</td>
                <td class="px-3 py-2">${buildSelectLMat('articulo[]', item.articulo, opcionesSelectLMat.articulo)}</td>
                <td class="px-3 py-2">${buildSelectLMat('tamano[]', item.tamano, opcionesSelectLMat.tamano)}</td>
                <td class="px-3 py-2">${buildSelectLMat('config[]', item.config, opcionesSelectLMat.config)}</td>
                <td class="px-3 py-2">${buildSelectLMat('color[]', item.color, opcionesSelectLMat.color)}</td>
                <td class="lmat-almacen-cell px-3 py-2 font-medium text-gray-800">${escapeHtml(almacenVisibleLMat(item))}</td>
                <td class="px-3 py-2">
                    <input
                        type="number"
                        name="cantidad[]"
                        step="0.001"
                        min="0"
                        class="lmat-cantidad-input w-20 rounded border border-gray-300 bg-white px-2 py-1.5 text-right text-xs tabular-nums text-gray-900 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                        value="${Number(item.cantidad || 0).toFixed(3)}"
                    >
                </td>
                <td class="lmat-porcentaje-cell px-3 py-2 text-right tabular-nums text-gray-900">0.0%</td>
                <!-- Columna Acción oculta
                <td class="px-3 py-2 text-center">
                    <button
                        type="button"
                        class="lmat-quitar-fila inline-flex h-8 w-8 items-center justify-center rounded bg-red-600 text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-400"
                        title="Quitar fila"
                    >
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
                -->
            </tr>
        `;
    }

    const filas = articulos.map(item => `
        <tr class="border-b border-gray-100"${item.rol === 'rizo' || item.rol === 'pie' ? ` data-articulo-fijo="${escapeAttr(item.articulo)}"` : ''}${item.rol ? ` data-rol="${escapeAttr(item.rol)}"` : ''}${item.matriz ? ` ${atributosMatrizLMat(item)}` : ''}${item.desdeCatLMat && !item.matriz ? ' data-preservar-articulo="1"' : ''}>
            <td class="lmat-combinacion-cell px-3 py-2 font-medium text-gray-800">${escapeHtml(item.combinacion || '')}</td>
            <td class="lmat-items-cell px-3 py-2 font-medium tabular-nums text-gray-800">${escapeHtml(item.items || '')}</td>
            <td class="px-3 py-2">${renderPlanoOSelectLMat(item, 'articulo', 'articulo[]', opcionesSelectLMat.articulo)}</td>
            <td class="px-3 py-2">${renderTamanoLMat(item)}</td>
            <td class="px-3 py-2">${renderConfigLMat(item)}</td>
            <td class="px-3 py-2">${buildSelectLMat('color[]', item.color, opcionesSelectLMat.color)}</td>
            <td class="lmat-almacen-cell px-3 py-2 font-medium text-gray-800">${escapeHtml(almacenVisibleLMat(item))}</td>
            <td class="px-3 py-2">
                <input
                    type="number"
                    name="cantidad[]"
                    step="0.001"
                    min="0"
                    class="lmat-cantidad-input w-20 rounded border border-gray-300 bg-white px-2 py-1.5 text-right text-xs tabular-nums text-gray-900 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                    value="${item.cantidad.toFixed(3)}"
                >
            </td>
            <td class="lmat-porcentaje-cell px-3 py-2 text-right tabular-nums text-gray-900">${item.porcentaje}</td>
            <!-- Columna Acción oculta -->
        </tr>
    `).join('');

    Swal.fire({
        title: 'L Mat',
        html: `
            <div class="text-left text-sm text-gray-800">
                <div id="lmat-banner-ocupada" class="hidden mb-3 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i> L.Mat ocupada — ya existe una con ese nombre.
                </div>
                <div class="mb-5 space-y-2">
                    <div class="grid grid-cols-5 gap-x-3 gap-y-1">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-gray-700">Orden</span>
                            <span class="min-h-[30px] flex items-center border-b border-gray-200 text-sm">${orden}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-gray-700">Salon</span>
                            <span class="min-h-[30px] flex items-center border-b border-gray-200 text-sm">${salon}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-gray-700">Peso Crudo</span>
                            <input
                                type="number"
                                id="lmat-pesocrudo"
                                step="0.01"
                                min="0"
                                class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-right tabular-nums text-gray-800 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                                value="${escapeAttr(pesoCrudo)}"
                            >
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-gray-700">ItemId</span>
                            <input
                                type="text"
                                id="lmat-itemid"
                                class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-800 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                                value="${escapeAttr(itemId)}"
                            >
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-gray-700">Tamaño</span>
                            <input
                                type="text"
                                id="lmat-tamano"
                                class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-800 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                                value="${escapeAttr(tamano)}"
                            >
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-x-3">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-gray-700">Nombre (20 caracteres)</span>
                            <input
                                type="text"
                                id="lmat-nombre"
                                maxlength="20"
                                ${nombreInputAttrsLMat}
                                class="${nombreInputClassLMat}"
                                value="${escapeAttr(nombreLMat)}"
                            >
                            <p id="lmat-nombre-error" class="hidden mt-0.5 text-xs font-semibold text-red-600">Ya existe esa L.Mat</p>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-gray-700">Descripción (60 caracteres)</span>
                            <input
                                type="text"
                                id="lmat-descripcion"
                                maxlength="60"
                                class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-800 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                                value="${escapeAttr(descripcionLMat)}"
                            >
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-md">
                    <table class="min-w-full text-xs">
                        <thead class="bg-blue-600 text-white">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Fibra</th>
                                <th class="px-3 py-2 text-left font-semibold">Calibre</th>
                                <th class="px-3 py-2 text-left font-semibold">Articulos</th>
                                <th class="px-3 py-2 text-left font-semibold">Tamaño</th>
                                <th class="px-3 py-2 text-left font-semibold">Config</th>
                                <th class="px-3 py-2 text-center font-semibold">Color</th>
                                <th class="px-3 py-2 text-left font-semibold">Almacen</th>
                                <th class="px-3 py-2 text-right font-semibold">Cantidad</th>
                                <th class="px-3 py-2 text-right font-semibold">Porcentaje</th>
                                <!-- <th class="px-3 py-2 text-center font-semibold">Acción</th> -->
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            ${filas}
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold">
                            <tr>
                                <td class="px-3 py-2" colspan="7"></td>
                                <td id="lmat-total-cantidad" class="px-3 py-2 text-right tabular-nums">${totalCantidad.toFixed(3)}</td>
                                <td id="lmat-total-porcentaje" class="px-3 py-2 text-right tabular-nums ${totalPorcentajeClass}">${totalPorcentajeRedondeado.toFixed(1)}%</td>
                                <!-- Columna Acción oculta -->
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="mt-4 flex justify-between gap-3">
                    <button
                        type="button"
                        id="lmat-anadir-fila"
                        class="inline-flex min-w-[150px] items-center justify-center gap-2 rounded bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-blue-600"
                    >
                        <i class="fas fa-plus"></i>
                        <span>Añadir fila</span>
                    </button>
                    <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        id="lmat-guardar-front"
                        class="${guardarBtnClassLMat}"
                    >
                        <i class="${guardarBtnIconLMat}"></i>
                        <span>${guardarBtnLabelLMat}</span>
                    </button>
                    <button
                        type="button"
                        id="lmat-cerrar-front"
                        class="inline-flex min-w-[150px] items-center justify-center gap-2 rounded bg-gray-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400"
                    >
                        <i class="fas fa-times"></i>
                        <span>Cerrar</span>
                    </button>
                    </div>
                </div>
            </div>
        `,
        width: '1120px',
        showConfirmButton: false,
        didOpen: () => {
            const tbodyLMat = document.querySelector('.swal2-html-container tbody');
            const pesoCrudoInput = document.getElementById('lmat-pesocrudo');
            const recalcularPorcentajesLMat = () => {
                // El total base = PesoCrudo / 1000, leído en vivo del input (cambia al editarlo).
                const totalCantidadActual = (Number(String(pesoCrudoInput?.value ?? '').replace(',', '.')) || 0) / 1000;
                const totalCantidadCell = document.getElementById('lmat-total-cantidad');
                if (totalCantidadCell) totalCantidadCell.textContent = totalCantidadActual.toFixed(3);

                let totalPorcentajeActual = 0;
                document.querySelectorAll('.lmat-cantidad-input').forEach((input) => {
                    const cantidad = Number(String(input.value || '0').replace(',', '.')) || 0;
                    const porcentaje = totalCantidadActual > 0 ? (cantidad / totalCantidadActual) * 100 : 0;
                    totalPorcentajeActual += porcentaje;
                    const porcentajeCell = input.closest('tr')?.querySelector('.lmat-porcentaje-cell');
                    if (porcentajeCell) porcentajeCell.textContent = porcentaje.toFixed(1) + '%';
                });

                const totalCell = document.getElementById('lmat-total-porcentaje');
                if (totalCell) {
                    const totalRedondeado = Number(totalPorcentajeActual.toFixed(1));
                    totalCell.textContent = totalRedondeado.toFixed(1) + '%';
                    totalCell.classList.remove(...clasesPorcentajeTotal);
                    totalCell.classList.add(...(
                        totalRedondeado === 100
                            ? ['text-green-700', 'bg-green-50']
                            : (totalRedondeado > 100 || totalRedondeado < 90 ? ['text-red-700', 'bg-red-50'] : ['text-orange-700', 'bg-orange-50'])
                    ));
                }
            };

            const recalcularCantidadesDesdePesoCrudoLMat = () => {
                if (guardadoLMat) {
                    recalcularPorcentajesLMat();
                    return;
                }
                const pesoCrudoActual = Number(String(pesoCrudoInput?.value ?? '').replace(',', '.')) || 0;
                const pesos = calcularPesosComponentesLMat(pesoCrudoActual);
                const aplicar = (rol, pesoG) => {
                    const fila = document.querySelector(`.swal2-html-container tr[data-rol="${rol}"]`);
                    if (!fila) return;
                    const vals = pesoACantidadYPorcentajeLMat(pesoG, pesos.pesoCrudoTotal);
                    const input = fila.querySelector('.lmat-cantidad-input');
                    const pct = fila.querySelector('.lmat-porcentaje-cell');
                    if (input) input.value = vals.cantidad.toFixed(3);
                    if (pct) pct.textContent = vals.porcentaje;
                };
                aplicar('rizo', pesos.rizoG);
                aplicar('pie', pesos.pieG);
                aplicar('trama', pesos.tramaG);
                pesos.combG.forEach((g, i) => aplicar('c' + (i + 1), g));
                recalcularPorcentajesLMat();
            };

            const conectarInputsCantidadLMat = () => {
                document.querySelectorAll('.lmat-cantidad-input').forEach((input) => {
                    if (input.dataset.lmatConnected === '1') return;
                    input.dataset.lmatConnected = '1';
                    input.addEventListener('input', recalcularPorcentajesLMat);
                });
            };

            // Al cambiar Peso Crudo, recalcular pesos (Rizo por diferencia) y porcentajes.
            pesoCrudoInput?.addEventListener('input', recalcularCantidadesDesdePesoCrudoLMat);

            /* Columna Acción oculta: quitar fila deshabilitado
            const conectarQuitarFilasLMat = () => {
                document.querySelectorAll('.lmat-quitar-fila').forEach((btn) => {
                    if (btn.dataset.lmatConnected === '1') return;
                    btn.dataset.lmatConnected = '1';
                    btn.addEventListener('click', () => {
                        btn.closest('tr')?.remove();
                        recalcularPorcentajesLMat();
                    });
                });
            };
            */

            const actualizarAlmacenFilaLMat = (fila, articulo) => {
                const cell = fila?.querySelector('.lmat-almacen-cell');
                if (cell) cell.textContent = resolverAlmacenLMat(articulo);
            };

            // Fibra y Calibre son informativos (CatCodificados): NUNCA se sincronizan con Config.
            // Config es independiente y es lo que se guarda en CatLMat.ConfigId.

            const actualizarCalibreFilaLMat = (fila, articulo) => {
                const cell = fila?.querySelector('.lmat-items-cell');
                if (!cell) return;
                const rol = String(fila?.dataset?.rol || '');
                // Filas con rol CatCodificados: Calibre ya viene de Tra/CalibreRizo/CalibreCombN.
                if (rol === 'rizo' || rol === 'pie' || rol === 'trama' || rol.startsWith('c')) return;
                cell.textContent = calibreDisplayDesdeItemIdLMat(articulo);
            };

            // Carga en cascada: al elegir Artículo se recargan Config/Tamaño/Color de esa fila.
            const cargarMaterialesFilaLMat = (fila, itemId, configPreferido = null) => {
                if (!fila || !itemId) return;
                const configSelect = fila.querySelector('select[name="config[]"]');
                const tamanoSelect = fila.querySelector('select[name="tamano[]"]');
                const colorSelect = fila.querySelector('select[name="color[]"]');
                const configInicial = configPreferido !== null
                    ? String(configPreferido || '')
                    : (configSelect?.value || '');

                Promise.all([
                    LMatMateriales.getConfigs(itemId),
                    LMatMateriales.getTamanos(itemId),
                    LMatMateriales.getColores(itemId),
                ]).then(([configsItem, tamanos, colores]) => {
                    const configVigente = configPreferido !== null
                        ? configInicial
                        : (configSelect?.value || configInicial);
                    if (configSelect) setSelectOptionsLMat(configSelect, configsItem, configVigente);
                    if (tamanoSelect) setSelectOptionsLMat(tamanoSelect, tamanos, tamanoSelect.value);
                    if (colorSelect) setSelectOptionsLMat(colorSelect, colores, colorSelect.value);
                });
            };

            const cargarTamanoYColorLMat = (articuloSelect) => {
                const fila = articuloSelect?.closest('tr');
                actualizarAlmacenFilaLMat(fila, articuloSelect?.value);
                actualizarCalibreFilaLMat(fila, articuloSelect?.value);
                cargarMaterialesFilaLMat(fila, articuloSelect?.value);
            };

            const asignarValorSelectLMat = (select, valor) => {
                if (!select) return;
                const normalizado = String(valor ?? '');
                if (normalizado !== '' && !Array.from(select.options).some(option => option.value === normalizado)) {
                    select.add(new Option(normalizado, normalizado));
                }
                select.value = normalizado;
            };

            const sincronizarSalidaMatrizLMat = (filaOrigen, nombreCampo, valor) => {
                const clave = filaOrigen?.dataset?.matrizClave || '';
                if (!clave) return;

                document.querySelectorAll('tr[data-matriz-clave]').forEach((filaDestino) => {
                    if (filaDestino === filaOrigen || filaDestino.dataset.matrizClave !== clave) return;
                    const selectDestino = filaDestino.querySelector(`select[name="${nombreCampo}"]`);
                    if (!selectDestino) return;

                    asignarValorSelectLMat(selectDestino, valor);
                    if (nombreCampo === 'articulo[]') {
                        cargarTamanoYColorLMat(selectDestino);
                    }
                });
            };

            const conectarArticuloSelectsLMat = () => {
                document.querySelectorAll('select[name="articulo[]"]').forEach((sel) => {
                    if (sel.dataset.lmatConnected === '1') return;
                    sel.dataset.lmatConnected = '1';
                    sel.addEventListener('change', () => {
                        const fila = sel.closest('tr');
                        sincronizarSalidaMatrizLMat(fila, 'articulo[]', sel.value);
                        cargarTamanoYColorLMat(sel);
                    });
                });
            };

            const conectarSelectsSalidaMatrizLMat = () => {
                ['config[]', 'tamano[]', 'color[]'].forEach((nombreCampo) => {
                    document.querySelectorAll(`select[name="${nombreCampo}"]`).forEach((select) => {
                        const dataKey = 'lmatMatrizConnected';
                        if (select.dataset[dataKey] === '1') return;
                        select.dataset[dataKey] = '1';
                        select.addEventListener('change', () => {
                            sincronizarSalidaMatrizLMat(select.closest('tr'), nombreCampo, select.value);
                        });
                    });
                });
            };

            conectarInputsCantidadLMat();
            conectarSelectsSalidaMatrizLMat();
            // conectarQuitarFilasLMat(); // Columna Acción oculta
            recalcularPorcentajesLMat();

            LMatMateriales.getCalibres().then((calibresDisponibles) => {
                document.querySelectorAll('select[name="articulo[]"]').forEach((sel) => {
                    const fila = sel.closest('tr');
                    const itemsVal = (fila?.querySelector('.lmat-items-cell')?.textContent || '').trim();
                    let valorSeleccionado = '';
                    let opcionesDisponibles = calibresDisponibles;
                    if (fila?.dataset?.preservarArticulo === '1' && sel.value) {
                        valorSeleccionado = sel.value;
                        opcionesDisponibles = Array.from(new Set([...calibresDisponibles, sel.value]));
                    } else if (itemsVal) {
                        valorSeleccionado = resolverArticuloDesdeCalibres(itemsVal, calibresDisponibles);
                    } else if (sel.value && calibresDisponibles.includes(sel.value)) {
                        valorSeleccionado = sel.value;
                    }
                    setSelectOptionsLMat(sel, opcionesDisponibles, valorSeleccionado);
                    cargarTamanoYColorLMat(sel);
                });
                conectarArticuloSelectsLMat();
                conectarSelectsSalidaMatrizLMat();
            });

            // Rizo/Pie: cargar Config AX guardado en la fila (independiente de Fibra informativa).
            document.querySelectorAll('tr[data-articulo-fijo]').forEach((fila) => {
                const configSelect = fila.querySelector('select[name="config[]"]');
                const configGuardado = configSelect?.value || '';
                cargarMaterialesFilaLMat(fila, fila.dataset.articuloFijo, configGuardado);
            });

            // Máximo trama + C1..C5 (6). Rizo/Pie no cuentan.
            const MAX_FILAS_TRAMA_COMB_LMAT = 6;
            const anadirFilaBtn = document.getElementById('lmat-anadir-fila');
            const contarFilasTramaCombLMat = () => {
                if (!tbodyLMat) return 0;
                return Array.from(tbodyLMat.querySelectorAll('tr')).filter((tr) => {
                    const rol = String(tr.dataset.rol || '');
                    return rol !== 'rizo' && rol !== 'pie';
                }).length;
            };
            const actualizarEstadoAnadirFilaLMat = () => {
                if (!anadirFilaBtn) return;
                const count = contarFilasTramaCombLMat();
                const bloqueado = count >= MAX_FILAS_TRAMA_COMB_LMAT;
                anadirFilaBtn.disabled = bloqueado;
                anadirFilaBtn.title = bloqueado
                    ? 'Máximo trama + C1 a C5 (6 filas)'
                    : 'Añadir fila';
            };
            actualizarEstadoAnadirFilaLMat();

            document.getElementById('lmat-anadir-fila')?.addEventListener('click', () => {
                if (!tbodyLMat) return;
                if (contarFilasTramaCombLMat() >= MAX_FILAS_TRAMA_COMB_LMAT) {
                    actualizarEstadoAnadirFilaLMat();
                    showToast('Solo se permiten trama y C1 a C5 (máximo 6 filas).', 'warning');
                    return;
                }
                tbodyLMat.insertAdjacentHTML('beforeend', renderFilaEditableLMat());
                conectarInputsCantidadLMat();
                conectarSelectsSalidaMatrizLMat();
                // conectarQuitarFilasLMat(); // Columna Acción oculta
                recalcularPorcentajesLMat();
                actualizarEstadoAnadirFilaLMat();

                const nuevaFila = tbodyLMat.lastElementChild;
                const articuloSelect = nuevaFila?.querySelector('select[name="articulo[]"]');
                if (articuloSelect) {
                    LMatMateriales.getCalibres().then((calibresDisponibles) => {
                        setSelectOptionsLMat(articuloSelect, calibresDisponibles, articuloSelect.value);
                        conectarArticuloSelectsLMat();
                        cargarTamanoYColorLMat(articuloSelect);
                    });
                }
            });

            // Validación: el Nombre (BOMID / columna "L.Mat" de liberar órdenes) no debe existir ya en BOMTABLE.
            // En actualización el nombre queda bloqueado y no se revalida contra AX.
            const nombreInput = document.getElementById('lmat-nombre');
            const nombreError = document.getElementById('lmat-nombre-error');
            const bannerOcupada = document.getElementById('lmat-banner-ocupada');
            const guardarBtn = document.getElementById('lmat-guardar-front');
            let lmatDuplicada = false;
            let guardandoLmat = false;
            let lmatCheckTimer = null;

            const actualizarEstadoGuardarBtn = () => {
                if (!guardarBtn) return;
                const bloqueado = (!esActualizacionLMat && lmatDuplicada) || guardandoLmat;
                guardarBtn.disabled = bloqueado;
                guardarBtn.classList.toggle('opacity-50', bloqueado);
                guardarBtn.classList.toggle('cursor-not-allowed', bloqueado);
            };

            const setGuardarLmatLoading = (isLoading) => {
                guardandoLmat = isLoading;
                const iconEl = guardarBtn?.querySelector('i');
                const spanEl = guardarBtn?.querySelector('span');
                if (iconEl) {
                    iconEl.className = isLoading
                        ? 'fas fa-spinner fa-spin'
                        : (esActualizacionLMat ? 'fas fa-edit' : 'fas fa-save');
                }
                if (spanEl) {
                    spanEl.textContent = isLoading
                        ? (esActualizacionLMat ? 'Actualizando...' : 'Guardando...')
                        : (esActualizacionLMat ? 'Actualizar' : 'Guardar');
                }
                actualizarEstadoGuardarBtn();
            };

            const marcarLmatDuplicada = (duplicado) => {
                if (esActualizacionLMat) {
                    lmatDuplicada = false;
                    actualizarEstadoGuardarBtn();
                    return;
                }
                lmatDuplicada = duplicado;
                if (nombreInput) {
                    nombreInput.classList.toggle('border-red-500', duplicado);
                    nombreInput.classList.toggle('ring-1', duplicado);
                    nombreInput.classList.toggle('ring-red-500', duplicado);
                }
                if (nombreError) nombreError.classList.toggle('hidden', !duplicado);
                if (bannerOcupada) bannerOcupada.classList.toggle('hidden', !duplicado);
                actualizarEstadoGuardarBtn();
            };

            const validarLmat = async () => {
                if (esActualizacionLMat) {
                    marcarLmatDuplicada(false);
                    return;
                }
                const nombre = (nombreInput?.value || '').trim();
                if (!nombre) { marcarLmatDuplicada(false); return; }
                try {
                    const resp = await fetch('/planeacion/lmat/api/existe?nombre=' + encodeURIComponent(nombre), { headers: { Accept: 'application/json' } });
                    const json = await resp.json();
                    marcarLmatDuplicada(!!json.existe);
                } catch (e) {
                    console.error('No se pudo validar la L.Mat', e);
                    marcarLmatDuplicada(false);
                }
            };

            if (!esActualizacionLMat) {
                nombreInput?.addEventListener('input', () => {
                    clearTimeout(lmatCheckTimer);
                    lmatCheckTimer = setTimeout(validarLmat, 400);
                });
                validarLmat(); // validar el nombre precargado al abrir
            } else {
                marcarLmatDuplicada(false);
            }

            guardarBtn?.addEventListener('click', async () => {
                if ((!esActualizacionLMat && lmatDuplicada) || guardandoLmat) {
                    if (lmatDuplicada) showToast('Ya existe esa L.Mat.', 'error');
                    return;
                }

                // Recolectar las filas de la tabla del modal.
                // Solo se guardan filas con cantidad >= 0.01 (cantidad 0 se omite).
                const filasData = [];
                let omitidasPorCantidadCero = 0;
                document.querySelectorAll('.swal2-html-container tbody tr').forEach((fila) => {
                    const articuloVal = fila.querySelector('select[name="articulo[]"]')?.value ?? fila.dataset.articuloFijo ?? '';
                    if (!articuloVal) return;
                    const qty = parseFloat(fila.querySelector('.lmat-cantidad-input')?.value || '0') || 0;
                    if (qty < 0.01) {
                        omitidasPorCantidadCero += 1;
                        return;
                    }
                    const almacenVal = (fila.querySelector('.lmat-almacen-cell')?.textContent || '').trim()
                        || resolverAlmacenLMat(articuloVal);
                    filasData.push({
                        itemId: articuloVal,
                        configId: fila.querySelector('select[name="config[]"]')?.value || '',
                        inventSizeId: normalizarInventSizeIdLMat(fila.querySelector('select[name="tamano[]"]')?.value || ''),
                        inventColorId: fila.querySelector('select[name="color[]"]')?.value || '',
                        inventLocationId: almacenVal,
                        qty,
                        porcentaje: parseFloat((fila.querySelector('.lmat-porcentaje-cell')?.textContent || '0').replace('%', '')) || 0,
                        matrizTipo: fila.dataset.matrizTipo || null,
                        matrizCalibre: fila.dataset.matrizCalibre || null,
                        matrizFibraId: fila.dataset.matrizFibraId || null,
                        matrizCuenta: fila.dataset.matrizCuenta || null,
                    });
                });

                if (filasData.length === 0) {
                    showToast('No hay filas con cantidad mínima de 0.01 para guardar.', 'warning');
                    return;
                }

                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                setGuardarLmatLoading(true);
                try {
                    const resp = await fetch('/planeacion/lmat/api/guardar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        body: JSON.stringify({
                            orden: orden,
                            salon: salon,
                            telarId: String(telarSeleccionado || ''),
                            nombre: nombreInput?.value || '',
                            descrip: document.getElementById('lmat-descripcion')?.value || '',
                            pesoCrudo: String(document.getElementById('lmat-pesocrudo')?.value || ''),
                            itemIdCrudo: document.getElementById('lmat-itemid')?.value || '',
                            inventSizeCrudo: document.getElementById('lmat-tamano')?.value || '',
                            filas: filasData,
                        }),
                    });
                    const json = await resp.json();
                    if (json.success) {
                        const baseMsg = esActualizacionLMat
                            ? (json.message || 'L.Mat actualizada.')
                            : (json.message || 'L.Mat guardada.');
                        const msg = omitidasPorCantidadCero > 0
                            ? baseMsg + ' Se omitieron ' + omitidasPorCantidadCero + ' fila(s) con cantidad 0.'
                            : baseMsg;
                        showToast(msg, 'success');
                        Swal.close();
                        await reloadData();
                    } else {
                        showToast(json.message || 'Error al guardar la L.Mat.', 'error');
                    }
                } catch (e) {
                    showToast('Error al guardar: ' + (e.message || 'desconocido'), 'error');
                } finally {
                    setGuardarLmatLoading(false);
                }
            });
            document.getElementById('lmat-cerrar-front')?.addEventListener('click', () => {
                Swal.close();
            });
        },
    });
}

export { openLMatModal };
