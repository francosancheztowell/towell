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
    let calibresPendientes = null;
    const configs = new Map();
    const tamanos = new Map();
    const colores = new Map();
    const nombresColores = new Map();
    const catalogosPendientes = new Map();
    const matrices = new Map();

    const normalizarItemId = (itemId) => String(itemId ?? '').trim();

    async function esperarCatalogosPendientes(itemId) {
        const pendiente = catalogosPendientes.get(itemId);
        if (!pendiente) return;
        try {
            await pendiente;
        } catch {
            // Los getters individuales ejecutan su fallback si falla el lote.
        }
    }

    async function getCalibres() {
        if (calibres) return calibres;
        if (calibresPendientes) return calibresPendientes;

        calibresPendientes = (async () => {
            try {
                const resp = await fetch('/planeacion/lmat/api/calibres', { headers: { Accept: 'application/json' } });
                const json = await resp.json();
                calibres = (json.data || []).map(i => i.ItemId).filter(Boolean);
            } catch (e) {
                console.error('No se pudieron cargar artículos (calibres) L.Mat', e);
                calibres = [];
            } finally {
                calibresPendientes = null;
            }
            return calibres;
        })();

        return calibresPendientes;
    }

    async function getConfigs(itemId) {
        itemId = normalizarItemId(itemId);
        if (!itemId) return [];
        if (configs.has(itemId)) return configs.get(itemId);
        await esperarCatalogosPendientes(itemId);
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
        itemId = normalizarItemId(itemId);
        if (!itemId) return [];
        if (tamanos.has(itemId)) return tamanos.get(itemId);
        await esperarCatalogosPendientes(itemId);
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
        itemId = normalizarItemId(itemId);
        if (!itemId) return [];
        if (colores.has(itemId)) return colores.get(itemId);
        await esperarCatalogosPendientes(itemId);
        if (colores.has(itemId)) return colores.get(itemId);
        let lista = [];
        try {
            const resp = await fetch('/planeacion/lmat/api/colores?itemId=' + encodeURIComponent(itemId), { headers: { Accept: 'application/json' } });
            const json = await resp.json();
            // AX InventColor: InventColorId (código) + Name (nombre color).
            lista = (json.data || [])
                .map((c) => ({
                    InventColorId: String(c.InventColorId ?? '').trim(),
                    Name: String(c.Name ?? '').trim(),
                }))
                .filter((c) => c.InventColorId);
        } catch (e) {
            console.error('No se pudieron cargar colores L.Mat', e);
        }
        colores.set(itemId, lista);
        lista.forEach((color) => {
            nombresColores.set(itemId + '|' + color.InventColorId, color.Name || '');
        });
        return lista;
    }

    async function precargarCatalogos(itemIds) {
        const faltantes = Array.from(new Set((itemIds || []).map(normalizarItemId).filter(Boolean)))
            .filter((itemId) => !configs.has(itemId) || !tamanos.has(itemId) || !colores.has(itemId))
            .slice(0, 10);
        if (!faltantes.length) return;

        const params = new URLSearchParams();
        faltantes.forEach((itemId) => params.append('itemIds[]', itemId));
        const carga = (async () => {
            const resp = await fetch('/planeacion/lmat/api/catalogos-materiales?' + params.toString(), {
                headers: { Accept: 'application/json' },
            });
            const json = await resp.json().catch(() => ({}));
            if (!resp.ok || json.success !== true) {
                throw new Error(json.message || `Error ${resp.status} al precargar catálogos L.Mat`);
            }

            faltantes.forEach((itemId) => {
                const material = json.data?.[itemId] || {};
                const configsItem = (material.configs || [])
                    .map(String)
                    .filter(Boolean)
                    .filter((config) => config.trim().toUpperCase() !== 'HILO');
                const tamanosItem = (material.tamanos || [])
                    .map((tamano) => String(tamano.InventSizeId ?? '').trim())
                    .filter(Boolean);
                const coloresItem = (material.colores || [])
                    .map((color) => ({
                        InventColorId: String(color.InventColorId ?? '').trim(),
                        Name: String(color.Name ?? '').trim(),
                    }))
                    .filter((color) => color.InventColorId);

                configs.set(itemId, configsItem);
                tamanos.set(itemId, tamanosItem);
                colores.set(itemId, coloresItem);
                coloresItem.forEach((color) => {
                    nombresColores.set(itemId + '|' + color.InventColorId, color.Name || '');
                });
            });
        })();

        faltantes.forEach((itemId) => catalogosPendientes.set(itemId, carga));
        try {
            await carga;
        } catch (error) {
            console.error('No se pudieron precargar catálogos L.Mat', error);
        } finally {
            faltantes.forEach((itemId) => {
                if (catalogosPendientes.get(itemId) === carga) catalogosPendientes.delete(itemId);
            });
        }
    }

    function idsColores(lista) {
        return (lista || []).map((c) => (typeof c === 'string' ? c : c.InventColorId)).filter(Boolean);
    }

    function nombreColorPorId(lista, inventColorId) {
        const id = String(inventColorId ?? '').trim();
        if (!id) return '';
        const hit = (lista || []).find((c) => (
            typeof c === 'string' ? c === id : String(c.InventColorId) === id
        ));
        if (!hit || typeof hit === 'string') return '';
        return hit.Name || '';
    }

    async function getNombreColor(itemId, inventColorId) {
        const articulo = String(itemId ?? '').trim();
        const color = String(inventColorId ?? '').trim();
        if (!articulo || !color) return '';

        const key = articulo + '|' + color;
        if (nombresColores.has(key)) return nombresColores.get(key);
        await esperarCatalogosPendientes(articulo);
        if (nombresColores.has(key)) return nombresColores.get(key);

        let nombre = '';
        try {
            const params = new URLSearchParams({ itemId: articulo, inventColorId: color });
            const resp = await fetch('/planeacion/lmat/api/colores?' + params.toString(), {
                headers: { Accept: 'application/json' },
            });
            const json = await resp.json();
            const coincidencia = (json.data || []).find((c) => (
                String(c.InventColorId ?? '').trim() === color
            ));
            nombre = String(coincidencia?.Name ?? '').trim();
        } catch (e) {
            console.error('No se pudo resolver el nombre de color L.Mat', e);
        }

        nombresColores.set(key, nombre);
        return nombre;
    }

    function claveMatrizKey(clave) {
        const calibre = clave.calibre === null ? '' : Number(clave.calibre).toFixed(1);
        return [clave.tipo, calibre, clave.fibraId || '', clave.cuenta || ''].join('|');
    }

    async function getMatrizCalibre(clave) {
        const key = claveMatrizKey(clave);
        if (matrices.has(key)) return matrices.get(key);
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

        const equivalencia = json.found ? json.data : null;
        // No guardar misses: la matriz puede aprender una equivalencia al guardar L.Mat.
        if (equivalencia) matrices.set(key, equivalencia);
        return equivalencia;
    }

    async function getMatricesCalibres(claves) {
        const unicas = new Map();
        (claves || []).filter(Boolean).forEach((clave) => unicas.set(claveMatrizKey(clave), clave));
        const faltantes = Array.from(unicas.entries()).filter(([key]) => !matrices.has(key));

        if (faltantes.length) {
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const resp = await fetch('/planeacion/lmat/api/matriz-calibre/lote', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                    body: JSON.stringify({
                        claves: faltantes.map(([key, clave]) => ({ key, ...clave })),
                    }),
                });
                const json = await resp.json().catch(() => ({}));
                if (!resp.ok || json.success !== true) {
                    throw new Error(json.message || `Error ${resp.status} al consultar Matriz de Calibres`);
                }
                faltantes.forEach(([key]) => {
                    const equivalencia = json.data?.[key] || null;
                    if (equivalencia) matrices.set(key, equivalencia);
                });
            } catch (errorLote) {
                console.warn('Falló consulta por lote; reintentando equivalencias individualmente', errorLote);
                let consultasExitosas = 0;
                await Promise.all(faltantes.map(async ([key, clave]) => {
                    try {
                        const equivalencia = await getMatrizCalibre(clave);
                        consultasExitosas += 1;
                        if (equivalencia) matrices.set(key, equivalencia);
                    } catch (errorIndividual) {
                        console.error('Falló equivalencia individual L.Mat', { key, errorIndividual });
                    }
                }));
                if (consultasExitosas === 0) throw errorLote;
            }
        }

        return new Map(Array.from(unicas.keys()).map((key) => [key, matrices.get(key) || null]));
    }

    function limpiarMatrices() {
        matrices.clear();
    }

    return {
        getCalibres,
        getConfigs,
        getTamanos,
        getColores,
        precargarCatalogos,
        idsColores,
        nombreColorPorId,
        getNombreColor,
        claveMatrizKey,
        getMatrizCalibre,
        getMatricesCalibres,
        limpiarMatrices,
    };
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
    // Acepta artículos AX con sufijo, por ejemplo 450/1T.
    const m = s.match(/^(\d+)(?:[./](\d+))?[A-Za-z]*$/);
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
        onSaved = () => {},
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

    // Consultar siempre equivalencias vigentes: guardar una L.Mat puede enseñar nuevas claves.
    LMatMateriales.limpiarMatrices();

    const telarId = String(registroSeleccionado?.TelarId ?? '').trim();
    if (!telarId) {
        fallbackToast('La fila seleccionada no tiene telar.', 'warning');
        return;
    }

    const orden = registroSeleccionado?.OrdenTejido || '';
    const salon = registroSeleccionado?.Departamento || '';
    const telarSeleccionado = parseInt(registroSeleccionado?.TelarId, 10) || 0;

    // Respuesta visual inmediata mientras se resuelven CatLMat y Matriz de Calibres.
    Swal.fire({
        title: 'L Mat',
        html: '<div class="py-3 text-sm text-gray-600">Preparando materiales...</div>',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => Swal.showLoading(),
    });

    // El catálogo de artículos AX puede cargarse en paralelo con CatLMat.
    const calibresPromise = LMatMateriales.getCalibres();

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
    const formatoPasadasCatLMat = (valor) => {
        const crudo = normalizarTextoCatLMat(valor);
        if (!crudo) return '';
        const n = Number(String(crudo).replace(',', '.'));
        if (!Number.isFinite(n) || n < 0) return '';
        return Number.isInteger(n) ? String(n) : String(n);
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
    // Nombre: TEJ + Nombre artículo
    let nombreLMat = truncLmat(['TEJ', articulo].filter(Boolean).join(' '), 20);
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
    // Trama/Cn = SI(P>0, ((P*(Ancho+13)*curva_peine)/100)*0.59/Hilo, 0)
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
        hiloPie: numLMat(registroSeleccionado?.CalibrePie2),
        cuentaPie: numLMat(registroSeleccionado?.CuentaPie),
        pasadasTrama: numLMat(registroSeleccionado?.PasadasTramaFondoC1),
        hiloTrama: numLMat(registroSeleccionado?.CalibreTrama2),
        pasadasComb: [1, 2, 3, 4, 5].map((n) => numLMat(registroSeleccionado?.[`PasadasComb${n}`])),
        hiloComb: [1, 2, 3, 4, 5].map((n) => numLMat(registroSeleccionado?.[`CalibreComb${n}2`])),
    };
    const calcularPesosComponentesLMat = (pesoCrudoG) => {
        const pesoCrudoTotal = numLMat(pesoCrudoG);
        const { peine, ancho, largo, corte, luchaje, tl, hiloPie, cuentaPie, pasadasTrama, hiloTrama, pasadasComb, hiloComb } = inputsCalculoLMat;
        const curvaLuchaje = luchaje >= 33 ? 1.083 : 1.055;
        const curvaPeine = peine >= 50 ? 1.001 : 1.002;
        const pesoTramaCn = (pasadas, hilo) => {
            if (!(pasadas > 0) || !(hilo > 0) || !(tl > 0)) return 0;
            return ((pasadas * (ancho + 13) * curvaPeine) / 100) * DENSIDAD_HILO_LMAT / hilo;
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
        const cantidad = pesoG / 1000;
        const porcentaje = pesoCrudoTotal > 0
            ? Number(((pesoG / pesoCrudoTotal) * 100).toFixed(2))
            : 0;
        return { cantidad, porcentaje: porcentaje.toFixed(2) + '%' };
    };
    const formatearCantidadLMat = (valor) => {
        const cantidad = Number(valor);
        if (!Number.isFinite(cantidad) || cantidad <= 0) return '0.0000';
        return cantidad.toFixed(4);
    };
    const formatearPorcentajeLMat = (valor) => {
        const porcentaje = Number(String(valor ?? '').replace('%', '').replace(',', '.'));
        return Number.isFinite(porcentaje) && porcentaje >= 0 ? porcentaje.toFixed(2) : '0.00';
    };
    const serializarCantidadRawLMat = (valor) => {
        const cantidad = Number(valor);
        return Number.isFinite(cantidad) && cantidad > 0 ? String(cantidad) : '0';
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
                pasadas: '',
                nombreColor: '',
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
                pasadas: '',
                nombreColor: '',
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
                pasadas: formatoPasadasCatLMat(registroSeleccionado?.PasadasTramaFondoC1),
                // Nombre color: se resuelve con GET AX InventColor.Name al cargar colores.
                nombreColor: '',
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
                pasadas: formatoPasadasCatLMat(registroSeleccionado?.[`PasadasComb${n}`]),
                nombreColor: '',
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
    const completarFilasDesdeMatrizLMat = async (filasPendientes) => {
        const filasConMatriz = filasPendientes.filter((item) => item.matriz);
        if (!filasConMatriz.length) return;
        try {
            const equivalencias = await LMatMateriales.getMatricesCalibres(
                filasConMatriz.map((item) => item.matriz),
            );
            filasConMatriz.forEach((item) => {
                const equivalencia = equivalencias.get(LMatMateriales.claveMatrizKey(item.matriz));
                if (!equivalencia) return;

                item.articulo = equivalencia.ItemId;
                item.config = equivalencia.ConfigId;
                item.tamano = equivalencia.InventSizeId;
                item.color = equivalencia.InventColorId;
                item.almacen = resolverAlmacenLMat(equivalencia.ItemId);
                item.matrizEncontrada = true;
            });
        } catch (error) {
            falloConsultaMatriz = true;
            console.error('No se pudo consultar CatMatrizCalibres para las filas L.Mat', error);
        }
    };

    if (!guardadoLMat) {
        await completarFilasDesdeMatrizLMat(articulos);
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
        const calibresParaMapeo = await calibresPromise;
        const noEsRizoNiPie = (registro) => {
            const id = String(registro.ItemId ?? '').trim();
            return !(id === 'JU-ENG-RI-C' || id.startsWith('JU-ENG-RI')
                || id === 'JU-ENG-PI-C' || id.startsWith('JU-ENG-PI'));
        };
        const defaultsTramaComb = defaults.filter((def) => def.rol !== 'rizo' && def.rol !== 'pie');
        const guardadosTramaComb = guardadoLMat.filter(noEsRizoNiPie);
        const hayHuecosTramaComb = guardadosTramaComb.length < defaultsTramaComb.length;

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
                // Listas antiguas podían omitir combinaciones pequeñas. Cuando hay huecos,
                // empatar por el artículo esperado evita cargar C2 dentro de C1.
                const articuloEsperado = resolverArticuloDesdeCalibres(def.items, calibresParaMapeo);
                if (hayHuecosTramaComb && articuloEsperado) {
                    saved = tomarGuardado((r) => noEsRizoNiPie(r)
                        && String(r.ItemId ?? '').trim() === articuloEsperado);
                } else {
                    saved = tomarGuardado(noEsRizoNiPie);
                }
            }

            // Al actualizar una L.Mat ya guardada, solo se muestran los componentes
            // que realmente están en CatLMat: si un componente (rizo/pie/trama/C1..C5)
            // no tiene fila guardada, no se reconstruye — se omite por completo.
            if (!saved) return null;

            return {
                ...def,
                // Fibra/Calibre informativos: se conservan de CatCodificados (def.combinacion / def.items)
                articulo: saved.ItemId ?? def.articulo,
                config: String(saved.ConfigId ?? '').trim(), // Config = CatLMat (independiente de Fibra)
                tamano: saved.InventSizeId ?? def.tamano,
                color: saved.InventColorId ?? def.color,
                nombreColor: saved.NombreColor ?? def.nombreColor,
                almacen: saved.InventLocationId ?? resolverAlmacenLMat(saved.ItemId ?? def.articulo),
                cantidad: saved.Qty != null ? Number(saved.Qty) : def.cantidad,
                porcentaje: (saved.Porcentaje != null
                    ? Number(saved.Porcentaje).toFixed(2)
                    : String(def.porcentaje || '0.00').replace('%', '')) + '%',
                desdeCatLMat: true,
            };
        }).filter(Boolean);

        // Filas extra en CatLMat que no matchearon defaults (añadidas a mano).
        guardadoLMat.forEach((r, i) => {
            if (usados.has(i)) return;
            const itemId = String(r.ItemId ?? '').trim();
            articulos.push({
                articulo: itemId,
                combinacion: '',
                items: calibreDisplayDesdeItemIdLMat(itemId),
                pasadas: '',
                nombreColor: r.NombreColor ?? '',
                config: String(r.ConfigId ?? '').trim(),
                tamano: r.InventSizeId ?? '',
                color: r.InventColorId ?? '',
                almacen: r.InventLocationId ?? resolverAlmacenLMat(itemId),
                cantidad: r.Qty != null ? Number(r.Qty) : 0,
                porcentaje: (r.Porcentaje != null ? Number(r.Porcentaje).toFixed(2) : '0.00') + '%',
                rol: '',
                matriz: null,
                desdeCatLMat: true,
            });
        });

        // En listas antiguas incompletas, consultar la matriz solo para los roles faltantes.
        // Lo ya persistido en CatLMat conserva prioridad y nunca se sobrescribe al abrir.
        await completarFilasDesdeMatrizLMat(
            articulos.filter((item) => !item.desdeCatLMat),
        );
    }

    if (falloConsultaMatriz) {
        showToast('No se pudo consultar una o más equivalencias. Se usará el proceso actual para esas filas.', 'warning');
    }

    // Disparar una sola carga AX para todos los artículos sin bloquear la apertura del modal.
    void LMatMateriales.precargarCatalogos(articulos.map((item) => item.articulo));

    // Cada fila precargada (Tra/CalibreComb1..5) trae su propio valor real de articulo/tamaño/color;
    // hay que incluirlo aquí para que quede "selected" desde el primer render (si no está en la
    // lista, el <select> no marca nada y el navegador cae al primer option de la lista).
    const sinHiloConfig = (valor) => String(valor ?? '').trim().toUpperCase() !== 'HILO';
    const opcionesSelectLMat = {
        articulo: Array.from(new Set([
            '',
            // En actualización conservar también el ItemId persistido en CatLMat.
            ...articulos
                .filter((f) => f.matrizEncontrada || f.desdeCatLMat)
                .map((f) => f.articulo)
                .filter(Boolean),
        ])),
        // Config NO incluye Fibras de CatCodificados; esas van solo en columna Fibra.
        // Placeholder inicial solamente: la lista real (filtrada por TwVigente=1) de cada
        // fila la trae cargarMaterialesFilaLMat vía AX/ConfigTable, por ItemId específico.
        // No mezclar aquí los ConfigId guardados de OTRAS filas: un config válido/vigente
        // para un artículo puede no serlo (o ni existir) para el artículo de otra fila.
        config: ['ENTERO'],
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
    const bomIdActualCat = String(registroSeleccionado?.BomId ?? '').trim();
    const esBomIdEstand = bomIdActualCat.toUpperCase().startsWith('ESTAND');
    const actLmatInicial = registroSeleccionado?.ActualizaLmat === true
        || registroSeleccionado?.ActualizaLmat === 1
        || registroSeleccionado?.ActualizaLmat === '1'
        || registroSeleccionado?.ActualizaLmat === null
        || registroSeleccionado?.ActualizaLmat === undefined;
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
    const actLmatCheckboxHtml = esBomIdEstand
        ? `
                        <div class="flex flex-col gap-0.5 justify-end">
                            <label class="inline-flex min-h-[34px] items-center gap-2 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    id="lmat-act-lmat"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    ${actLmatInicial ? 'checked' : ''}
                                >
                                <span class="text-sm font-medium text-gray-700">Actualizar L.Mat</span>
                            </label>
                        </div>
        `
        : '';
    const pesoCrudoNumerico = Number(String(pesoCrudo ?? '').replace(',', '.')) || 0;
    const totalCantidad = pesoCrudoNumerico / 1000;
    const totalPorcentaje = articulos.reduce((total, item) => total + parseFloat(String(item.porcentaje || '0').replace('%', '')), 0);
    const totalPorcentajeRedondeado = Number(totalPorcentaje.toFixed(2));
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

    function renderFilaEditableLMat(item = { articulo: '10.1', combinacion: '', items: '', pasadas: '', nombreColor: '', config: '', tamano: '', color: '1000', cantidad: 0 }) {
        return `
            <tr class="border-b border-gray-100">
                <td class="lmat-combinacion-cell px-3 py-2 font-medium text-gray-800">${escapeHtml(item.combinacion || '')}</td>
                <td class="lmat-items-cell px-3 py-2 font-medium tabular-nums text-gray-800">${escapeHtml(item.items || '')}</td>
                <td class="lmat-pasadas-cell px-3 py-2 font-medium tabular-nums text-gray-800">${escapeHtml(item.pasadas || '')}</td>
                <td class="px-3 py-2">${buildSelectLMat('articulo[]', item.articulo, opcionesSelectLMat.articulo)}</td>
                <td class="px-3 py-2">${buildSelectLMat('config[]', item.config, opcionesSelectLMat.config)}</td>
                <td class="px-3 py-2">${buildSelectLMat('tamano[]', item.tamano, opcionesSelectLMat.tamano)}</td>
                <td class="px-3 py-2">
                    ${buildSelectLMat('color[]', item.color, opcionesSelectLMat.color)}
                    <input type="hidden" class="lmat-nombre-color-input" value="${escapeAttr(item.nombreColor || '')}">
                </td>
                <td class="lmat-almacen-cell px-3 py-2 font-medium text-gray-800">${escapeHtml(almacenVisibleLMat(item))}</td>
                <td class="px-3 py-2">
                    <input
                        type="text"
                        name="cantidad[]"
                        inputmode="decimal"
                        data-cantidad-raw="${escapeAttr(serializarCantidadRawLMat(item.cantidad))}"
                        class="lmat-cantidad-input w-20 rounded border border-gray-300 bg-white px-2 py-1.5 text-right text-xs tabular-nums text-gray-900 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                        value="${formatearCantidadLMat(item.cantidad)}"
                    >
                </td>
                <td class="px-3 py-2">
                    <input
                        type="text"
                        name="porcentaje[]"
                        inputmode="decimal"
                        class="lmat-porcentaje-input w-20 rounded border border-gray-300 bg-white px-2 py-1.5 text-right text-xs tabular-nums text-gray-900 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                        value="0.00"
                    >
                </td>
            </tr>
        `;
    }

    const filas = articulos.map(item => `
        <tr class="border-b border-gray-100"${item.rol === 'rizo' || item.rol === 'pie' ? ` data-articulo-fijo="${escapeAttr(item.articulo)}"` : ''}${item.rol ? ` data-rol="${escapeAttr(item.rol)}"` : ''}${item.matriz ? ` ${atributosMatrizLMat(item)}` : ''}${item.desdeCatLMat && !item.matriz ? ' data-preservar-articulo="1"' : ''}>
            <td class="lmat-combinacion-cell px-3 py-2 font-medium text-gray-800">${escapeHtml(item.combinacion || '')}</td>
            <td class="lmat-items-cell px-3 py-2 font-medium tabular-nums text-gray-800">${escapeHtml(item.items || '')}</td>
            <td class="lmat-pasadas-cell px-3 py-2 font-medium tabular-nums text-gray-800">${escapeHtml(item.pasadas || '')}</td>
            <td class="px-3 py-2">${renderPlanoOSelectLMat(item, 'articulo', 'articulo[]', opcionesSelectLMat.articulo)}</td>
            <td class="px-3 py-2">${renderConfigLMat(item)}</td>
            <td class="px-3 py-2">${renderTamanoLMat(item)}</td>
            <td class="px-3 py-2">
                ${buildSelectLMat('color[]', item.color, opcionesSelectLMat.color)}
                <input type="hidden" class="lmat-nombre-color-input" value="${escapeAttr(item.nombreColor || '')}">
            </td>
            <td class="lmat-almacen-cell px-3 py-2 font-medium text-gray-800">${escapeHtml(almacenVisibleLMat(item))}</td>
            <td class="px-3 py-2">
                <input
                    type="text"
                    name="cantidad[]"
                    inputmode="decimal"
                    data-cantidad-raw="${escapeAttr(serializarCantidadRawLMat(item.cantidad))}"
                    class="lmat-cantidad-input w-20 rounded border border-gray-300 bg-white px-2 py-1.5 text-right text-xs tabular-nums text-gray-900 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                    value="${formatearCantidadLMat(item.cantidad)}"
                >
            </td>
            <td class="px-3 py-2">
                <input
                    type="text"
                    name="porcentaje[]"
                    inputmode="decimal"
                    class="lmat-porcentaje-input w-20 rounded border border-gray-300 bg-white px-2 py-1.5 text-right text-xs tabular-nums text-gray-900 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                    value="${escapeAttr(formatearPorcentajeLMat(item.porcentaje))}"
                >
            </td>
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
                    <div class="grid grid-cols-2 ${esBomIdEstand ? 'lg:grid-cols-3' : ''} gap-x-3 gap-y-2">
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
                        ${actLmatCheckboxHtml}
                    </div>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-md">
                    <table class="min-w-full text-xs">
                        <thead class="bg-blue-600 text-white">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Fibra</th>
                                <th class="px-3 py-2 text-left font-semibold">Calibre</th>
                                <th class="px-3 py-2 text-right font-semibold">Pasadas</th>
                                <th class="px-3 py-2 text-left font-semibold">Articulos</th>
                                <th class="px-3 py-2 text-left font-semibold">Config</th>
                                <th class="px-3 py-2 text-left font-semibold">Tamaño</th>
                                <th class="px-3 py-2 text-left font-semibold">Color</th>
                                <th class="px-3 py-2 text-left font-semibold">Almacen</th>
                                <th class="px-3 py-2 text-right font-semibold">Cantidad</th>
                                <th class="px-3 py-2 text-right font-semibold">Porcentaje</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            ${filas}
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold">
                            <tr>
                                <td class="px-3 py-2" colspan="8"></td>
                                <td id="lmat-total-cantidad" class="px-3 py-2 text-right tabular-nums">${totalCantidad.toFixed(4)}</td>
                                <td id="lmat-total-porcentaje" class="px-3 py-2 text-right tabular-nums ${totalPorcentajeClass}">${totalPorcentajeRedondeado.toFixed(2)}%</td>
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
        width: '1360px',
        showConfirmButton: false,
        didOpen: () => {
            const tbodyLMat = document.querySelector('.swal2-html-container tbody');
            const pesoCrudoInput = document.getElementById('lmat-pesocrudo');
            let totalPorcentajeActualLMat = totalPorcentajeRedondeado;
            let onPorcentajeActualizadoLMat = null;

            const obtenerTotalPorcentajeLMat = () => {
                const totalCell = document.getElementById('lmat-total-porcentaje');
                const texto = (totalCell?.textContent || '0').replace('%', '').trim();
                const n = Number(texto);
                return Number.isFinite(n) ? Number(n.toFixed(2)) : 0;
            };

            const obtenerCantidadRawLMat = (input) => {
                const raw = Number(String(input?.dataset?.cantidadRaw ?? '').replace(',', '.'));
                if (Number.isFinite(raw) && raw >= 0) return raw;
                const visible = Number(String(input?.value ?? '').replace(',', '.'));
                return Number.isFinite(visible) && visible >= 0 ? visible : 0;
            };

            /**
             * Limita decimales al teclear (cantidad ≤ 4, porcentaje ≤ 2).
             * No usa selectionStart/End sin null-check (Firefox en type=number).
             */
            const restringirDecimalesLMat = (input, maxDecimales) => {
                if (!input || input.dataset.lmatDecimalesBound === '1') return;
                input.dataset.lmatDecimalesBound = '1';

                const valorConDecimalesLimitados = (valor) => {
                    const texto = String(valor ?? '').replace(',', '.');
                    if (texto === '' || texto === '.' || texto === '-') return texto;
                    const match = texto.match(/^(-?\d*)(?:\.(\d*))?/);
                    if (!match) return texto;
                    const enteros = match[1] ?? '';
                    const decimales = match[2];
                    if (decimales === undefined) return enteros;
                    return enteros + '.' + decimales.slice(0, maxDecimales);
                };

                input.addEventListener('beforeinput', (event) => {
                    if (event.inputType?.startsWith('delete') || event.inputType === 'historyUndo' || event.inputType === 'historyRedo') {
                        return;
                    }
                    const data = event.data;
                    if (data == null) return;
                    if (!/^[0-9.,]+$/.test(data)) {
                        event.preventDefault();
                        return;
                    }

                    const start = input.selectionStart;
                    const end = input.selectionEnd;
                    const actual = String(input.value ?? '');
                    const siguiente = (start != null && end != null)
                        ? actual.slice(0, start) + data + actual.slice(end)
                        : actual + data;
                    const limitado = valorConDecimalesLimitados(siguiente);
                    if (limitado !== String(siguiente).replace(',', '.')) {
                        event.preventDefault();
                    }
                });

                input.addEventListener('input', () => {
                    const limitado = valorConDecimalesLimitados(input.value);
                    if (limitado !== String(input.value ?? '').replace(',', '.') && limitado !== input.value) {
                        input.value = limitado;
                    }
                });
            };

            const asignarCantidadLMat = (input, cantidad) => {
                if (!input) return;
                const raw = Number(cantidad);
                input.dataset.cantidadRaw = Number.isFinite(raw) && raw > 0 ? String(raw) : '0';
                input.value = formatearCantidadLMat(raw);
                input.dataset.cantidadInicialRaw = input.dataset.cantidadRaw;
                input.dataset.cantidadInicialVisible = input.value;
            };

            const actualizarTotalPorcentajeLMat = (totalPorcentaje) => {
                const totalCell = document.getElementById('lmat-total-porcentaje');
                const totalRedondeado = Number(totalPorcentaje.toFixed(2));
                totalPorcentajeActualLMat = totalRedondeado;
                if (totalCell) {
                    totalCell.textContent = totalRedondeado.toFixed(2) + '%';
                    totalCell.classList.remove(...clasesPorcentajeTotal);
                    totalCell.classList.add(...(
                        totalRedondeado === 100
                            ? ['text-green-700', 'bg-green-50']
                            : (totalRedondeado > 100 || totalRedondeado < 90 ? ['text-red-700', 'bg-red-50'] : ['text-orange-700', 'bg-orange-50'])
                    ));
                }
                if (onPorcentajeActualizadoLMat) onPorcentajeActualizadoLMat();
            };

            const recalcularPorcentajesLMat = () => {
                // El total base = PesoCrudo / 1000, leído en vivo del input (cambia al editarlo).
                const totalCantidadActual = (Number(String(pesoCrudoInput?.value ?? '').replace(',', '.')) || 0) / 1000;
                const totalCantidadCell = document.getElementById('lmat-total-cantidad');
                if (totalCantidadCell) totalCantidadCell.textContent = totalCantidadActual.toFixed(4);

                let totalPorcentajeActual = 0;
                document.querySelectorAll('.lmat-cantidad-input').forEach((input) => {
                    const cantidad = obtenerCantidadRawLMat(input);
                    const porcentaje = totalCantidadActual > 0 ? (cantidad / totalCantidadActual) * 100 : 0;
                    totalPorcentajeActual += porcentaje;
                    const porcentajeInput = input.closest('tr')?.querySelector('.lmat-porcentaje-input');
                    if (porcentajeInput) porcentajeInput.value = porcentaje.toFixed(2);
                });

                actualizarTotalPorcentajeLMat(totalPorcentajeActual);
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
                    const pct = fila.querySelector('.lmat-porcentaje-input');
                    asignarCantidadLMat(input, vals.cantidad);
                    if (pct) pct.value = formatearPorcentajeLMat(vals.porcentaje);
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
                    restringirDecimalesLMat(input, 4);
                    input.dataset.cantidadInicialRaw = input.dataset.cantidadRaw || '0';
                    input.dataset.cantidadInicialVisible = input.value;
                    input.addEventListener('input', () => {
                        const cantidadEditada = Number(String(input.value || '0').replace(',', '.'));
                        const regresoAlValorInicial = input.value === input.dataset.cantidadInicialVisible;
                        input.dataset.cantidadRaw = regresoAlValorInicial
                            ? input.dataset.cantidadInicialRaw
                            : (Number.isFinite(cantidadEditada) && cantidadEditada > 0
                                ? String(cantidadEditada)
                                : '0');
                        recalcularPorcentajesLMat();
                    });
                    input.addEventListener('change', () => {
                        input.value = formatearCantidadLMat(obtenerCantidadRawLMat(input));
                    });
                });
            };

            const conectarInputsPorcentajeLMat = () => {
                document.querySelectorAll('.lmat-porcentaje-input').forEach((input) => {
                    if (input.dataset.lmatConnected === '1') return;
                    input.dataset.lmatConnected = '1';
                    restringirDecimalesLMat(input, 2);
                    input.addEventListener('input', () => {
                        const porcentaje = Number(String(input.value || '0').replace(',', '.'));
                        const porcentajeValido = Number.isFinite(porcentaje) && porcentaje >= 0 ? porcentaje : 0;
                        const totalCantidad = (Number(String(pesoCrudoInput?.value ?? '').replace(',', '.')) || 0) / 1000;
                        const cantidadInput = input.closest('tr')?.querySelector('.lmat-cantidad-input');
                        asignarCantidadLMat(cantidadInput, totalCantidad * (porcentajeValido / 100));

                        const totalPorcentaje = Array.from(document.querySelectorAll('.lmat-porcentaje-input'))
                            .reduce((total, porcentajeInput) => {
                                const valor = Number(String(porcentajeInput.value || '0').replace(',', '.'));
                                return total + (Number.isFinite(valor) && valor >= 0 ? valor : 0);
                            }, 0);
                        actualizarTotalPorcentajeLMat(totalPorcentaje);
                    });
                    input.addEventListener('change', () => {
                        input.value = formatearPorcentajeLMat(input.value);
                    });
                });
            };

            // Al cambiar Peso Crudo, recalcular pesos (Rizo por diferencia) y porcentajes.
            pesoCrudoInput?.addEventListener('input', recalcularCantidadesDesdePesoCrudoLMat);



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

            // Nombre color = InventColor.Name del GET AX filtrado por ItemId (columna Artículos)
            // y el InventColorId elegido en columna Color.
            const actualizarNombreColorFilaLMat = (fila, coloresLista, inventColorId = null) => {
                const input = fila?.querySelector('.lmat-nombre-color-input');
                if (!input) return;
                const colorId = inventColorId !== null && inventColorId !== undefined
                    ? inventColorId
                    : (fila.querySelector('select[name="color[]"]')?.value || '');
                const nombre = LMatMateriales.nombreColorPorId(coloresLista, colorId);
                if (nombre || !String(colorId || '').trim()) input.value = nombre;
            };

            const itemIdDeFilaLMat = (fila) => (
                fila?.querySelector('select[name="articulo[]"]')?.value
                || fila?.dataset?.articuloFijo
                || ''
            );

            const actualizarNombreColorPorSeleccionLMat = async (fila) => {
                if (!fila) return;
                const itemId = String(itemIdDeFilaLMat(fila)).trim();
                const inventColorId = String(fila.querySelector('select[name="color[]"]')?.value || '').trim();
                const solicitud = itemId + '|' + inventColorId;
                fila.dataset.nombreColorSolicitud = solicitud;

                if (!itemId || !inventColorId) {
                    actualizarNombreColorFilaLMat(fila, [], '');
                    return;
                }

                const nombre = await LMatMateriales.getNombreColor(itemId, inventColorId);
                if (fila.dataset.nombreColorSolicitud !== solicitud) return;

                const input = fila.querySelector('.lmat-nombre-color-input');
                if (input && nombre) input.value = nombre;
            };

            const inicializarColorBuscableLMat = (fila, colorSelect) => {
                const jq = window.jQuery;
                if (!jq?.fn?.select2 || !colorSelect) return;

                const $select = jq(colorSelect);
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }

                $select.select2({
                    dropdownParent: jq(Swal.getPopup()),
                    width: '100%',
                    placeholder: 'Escribe código o nombre',
                    allowClear: true,
                    minimumResultsForSearch: 0,
                });
                $select.off('change.lmatColor').on('change.lmatColor', () => {
                    actualizarNombreColorPorSeleccionLMat(fila);
                    sincronizarSalidaMatrizLMat(fila, 'color[]', colorSelect.value);
                });
            };

            /** Rellena Color desde AX como "código — nombre"; si solo hay 1, lo selecciona. */
            const aplicarColoresAxFilaLMat = (fila, coloresLista, colorPreferido = null) => {
                const colorSelect = fila?.querySelector('select[name="color[]"]');
                if (!colorSelect) return;

                const jq = window.jQuery;
                const $colorSelect = jq ? jq(colorSelect) : null;
                if ($colorSelect?.hasClass('select2-hidden-accessible')) {
                    $colorSelect.select2('destroy');
                }

                const ids = LMatMateriales.idsColores(coloresLista);
                let preferido = colorPreferido !== null && colorPreferido !== undefined
                    ? String(colorPreferido).trim()
                    : String(colorSelect.value || '').trim();

                // Si AX solo trae 1 color para ese ItemId, rellenarlo automáticamente.
                if (ids.length === 1) {
                    preferido = ids[0];
                }

                setSelectOptionsLMat(colorSelect, ids, preferido);
                Array.from(colorSelect.options).forEach((option) => {
                    const id = String(option.value || '').trim();
                    if (!id) return;
                    const nombre = LMatMateriales.nombreColorPorId(coloresLista, id);
                    option.textContent = nombre ? `${id} — ${nombre}` : id;
                });

                const opcionesNoVacias = Array.from(colorSelect.options)
                    .map((o) => String(o.value || '').trim())
                    .filter(Boolean);
                if (opcionesNoVacias.length === 1) {
                    colorSelect.value = opcionesNoVacias[0];
                }

                actualizarNombreColorFilaLMat(fila, coloresLista, colorSelect.value);
                inicializarColorBuscableLMat(fila, colorSelect);
                actualizarNombreColorPorSeleccionLMat(fila);
            };

            const cargarMaterialesFilaLMat = (fila, itemId, configPreferido = null) => {
                if (!fila || !itemId) {
                    if (fila) actualizarNombreColorFilaLMat(fila, [], '');
                    return;
                }
                const itemIdSolicitado = String(itemId).trim();
                const configSelect = fila.querySelector('select[name="config[]"]');
                const tamanoSelect = fila.querySelector('select[name="tamano[]"]');
                const colorSelect = fila.querySelector('select[name="color[]"]');
                const configInicial = configPreferido !== null
                    ? String(configPreferido || '')
                    : (configSelect?.value || '');
                const colorInicial = colorSelect?.value || '';

                Promise.all([
                    LMatMateriales.getConfigs(itemId),
                    LMatMateriales.getTamanos(itemId),
                    LMatMateriales.getColores(itemId),
                ]).then(([configsItem, tamanos, colores]) => {
                    // Si el usuario cambió Artículos mientras AX respondía, ignorar la respuesta anterior.
                    if (String(itemIdDeFilaLMat(fila)).trim() !== itemIdSolicitado) return;
                    const configVigente = configPreferido !== null
                        ? configInicial
                        : (configSelect?.value || configInicial);
                    // Si AX no devuelve configs para este ItemId (p.ej. los códigos internos
                    // JU-ENG-RI-C / JU-ENG-PI-C de Rizo/Pie no existen en AX), no dejar el
                    // select vacío: conservar "ENTERO" como opción genérica seleccionable.
                    if (configSelect) setSelectOptionsLMat(configSelect, configsItem.length ? configsItem : ['ENTERO'], configVigente);
                    if (tamanoSelect) setSelectOptionsLMat(tamanoSelect, tamanos, tamanoSelect.value);
                    // Color + Nombre color dependen del ItemId (Artículos) vía GET AX.
                    aplicarColoresAxFilaLMat(fila, colores, colorInicial);
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
                const jq = window.jQuery;
                if (jq && jq(select).hasClass('select2-hidden-accessible')) {
                    jq(select).trigger('change.select2');
                }
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
                    if (nombreCampo === 'color[]') {
                        actualizarNombreColorPorSeleccionLMat(filaDestino);
                    }
                });
            };

            const conectarArticuloSelectsLMat = () => {
                document.querySelectorAll('select[name="articulo[]"]').forEach((sel) => {
                    if (sel.dataset.lmatConnected === '1') return;
                    sel.dataset.lmatConnected = '1';

                    const manejarCambioArticulo = () => {
                        const fila = sel.closest('tr');
                        sincronizarSalidaMatrizLMat(fila, 'articulo[]', sel.value);
                        cargarTamanoYColorLMat(sel);
                    };

                    const jq = window.jQuery;
                    if (jq?.fn?.select2) {
                        const $select = jq(sel);
                        $select.select2({
                            dropdownParent: jq(Swal.getPopup()),
                            width: '100%',
                            placeholder: 'Escribe para buscar artículo',
                            allowClear: true,
                            minimumResultsForSearch: 0,
                        });
                        $select.off('change.lmatArticulo').on('change.lmatArticulo', manejarCambioArticulo);
                    } else {
                        sel.addEventListener('change', manejarCambioArticulo);
                    }
                });
            };

            const conectarSelectsSalidaMatrizLMat = () => {
                ['config[]', 'tamano[]', 'color[]'].forEach((nombreCampo) => {
                    document.querySelectorAll(`select[name="${nombreCampo}"]`).forEach((select) => {
                        const dataKey = 'lmatMatrizConnected';
                        if (select.dataset[dataKey] === '1') return;
                        select.dataset[dataKey] = '1';
                        select.addEventListener('change', () => {
                            const fila = select.closest('tr');
                            sincronizarSalidaMatrizLMat(fila, nombreCampo, select.value);
                        });
                    });
                });
            };

            // Delegado: Config limpia error; Color actualiza Nombre color.
            tbodyLMat?.addEventListener('change', (event) => {
                const configSelect = event.target?.closest?.('select[name="config[]"]');
                if (configSelect) {
                    configSelect.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
                    return;
                }
                const colorSelect = event.target?.closest?.('select[name="color[]"]');
                if (!colorSelect) return;
                actualizarNombreColorPorSeleccionLMat(colorSelect.closest('tr'));
            });

            conectarInputsCantidadLMat();
            conectarInputsPorcentajeLMat();
            conectarSelectsSalidaMatrizLMat();
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
                        // Respaldo cuando Fibra + Calibre no existen en CatMatrizCalibres:
                        // elegir el artículo AX de calibre exacto o más cercano de la misma base.
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
                conectarInputsPorcentajeLMat();
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
                const porcentajeOk = Number(totalPorcentajeActualLMat) === 100
                    || Number(obtenerTotalPorcentajeLMat()) === 100;
                const bloqueado = (!esActualizacionLMat && lmatDuplicada) || guardandoLmat || !porcentajeOk;
                guardarBtn.disabled = bloqueado;
                guardarBtn.classList.toggle('opacity-50', bloqueado);
                guardarBtn.classList.toggle('cursor-not-allowed', bloqueado);
                guardarBtn.title = !porcentajeOk
                    ? 'El porcentaje total debe ser exactamente 100%'
                    : (esActualizacionLMat ? 'Actualizar' : 'Guardar');
            };
            onPorcentajeActualizadoLMat = actualizarEstadoGuardarBtn;
            actualizarEstadoGuardarBtn();

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

                const totalPct = Number(obtenerTotalPorcentajeLMat());
                if (totalPct !== 100) {
                    showToast('El porcentaje total debe ser exactamente 100% para guardar.', 'warning');
                    return;
                }

                // Recolectar las filas de la tabla del modal.
                // Se conserva toda cantidad positiva; solo se omiten filas sin cantidad.
                const filasData = [];
                let omitidasSinCantidad = 0;
                const filasSinArticulo = [];
                const filasSinConfig = [];
                document.querySelectorAll('.swal2-html-container tbody tr').forEach((fila, index) => {
                    const qty = obtenerCantidadRawLMat(fila.querySelector('.lmat-cantidad-input'));
                    const configSelect = fila.querySelector('select[name="config[]"]');
                    if (configSelect) {
                        configSelect.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
                    }
                    if (qty <= 0) {
                        omitidasSinCantidad += 1;
                        return;
                    }
                    const articuloVal = fila.querySelector('select[name="articulo[]"]')?.value ?? fila.dataset.articuloFijo ?? '';
                    const rolLabel = String(fila.dataset.rol || `fila ${index + 1}`).toUpperCase();
                    if (!articuloVal) {
                        filasSinArticulo.push(rolLabel);
                        return;
                    }
                    const configVal = String(configSelect?.value || '').trim();
                    if (!configVal) {
                        filasSinConfig.push(rolLabel);
                        if (configSelect) {
                            configSelect.classList.add('border-red-500', 'ring-1', 'ring-red-500');
                        }
                        return;
                    }
                    const almacenVal = (fila.querySelector('.lmat-almacen-cell')?.textContent || '').trim()
                        || resolverAlmacenLMat(articuloVal);
                    filasData.push({
                        itemId: articuloVal,
                        configId: configVal,
                        inventSizeId: normalizarInventSizeIdLMat(fila.querySelector('select[name="tamano[]"]')?.value || ''),
                        inventColorId: fila.querySelector('select[name="color[]"]')?.value || '',
                        nombreColor: fila.querySelector('.lmat-nombre-color-input')?.value || '',
                        inventLocationId: almacenVal,
                        qty: Number(qty.toFixed(4)),
                        porcentaje: Number((
                            parseFloat(fila.querySelector('.lmat-porcentaje-input')?.value || '0') || 0
                        ).toFixed(2)),
                        matrizTipo: fila.dataset.matrizTipo || null,
                        matrizCalibre: fila.dataset.matrizCalibre || null,
                        matrizFibraId: fila.dataset.matrizFibraId || null,
                        matrizCuenta: fila.dataset.matrizCuenta || null,
                    });
                });

                if (filasSinArticulo.length > 0) {
                    showToast('Selecciona Artículos en: ' + filasSinArticulo.join(', ') + '. No se guardó ninguna fila.', 'error');
                    return;
                }

                if (filasSinConfig.length > 0) {
                    showToast('Selecciona Config en: ' + filasSinConfig.join(', ') + '. Es obligatorio en líneas con cantidad.', 'error');
                    return;
                }

                if (filasData.length === 0) {
                    showToast('No hay filas con cantidad mayor a 0 para guardar.', 'warning');
                    return;
                }

                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const actLmatChecked = esBomIdEstand
                    ? Boolean(document.getElementById('lmat-act-lmat')?.checked)
                    : false;
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
                            // No se muestran en el modal; se copian de CatCodificados al guardar.
                            luchaje: registroSeleccionado?.Luchaje != null && registroSeleccionado.Luchaje !== ''
                                ? Number(registroSeleccionado.Luchaje)
                                : null,
                            codigoDibujo: String(registroSeleccionado?.CodigoDibujo ?? '').trim() || null,
                            actualizaLmat: actLmatChecked,
                            filas: filasData,
                        }),
                    });
                    const json = await resp.json().catch(() => ({}));
                    if (resp.ok && json.success) {
                        const updatedBom = json.updatedBom === true;
                        const bomIdGuardado = updatedBom
                            ? (json.bomId ?? nombreInput?.value ?? '')
                            : (json.bomId ?? bomIdActualCat);
                        const bomNameGuardado = updatedBom
                            ? (json.bomName ?? document.getElementById('lmat-descripcion')?.value ?? '')
                            : (json.bomName ?? String(registroSeleccionado?.BomName ?? ''));
                        const baseMsg = esActualizacionLMat
                            ? (json.message || 'L.Mat actualizada.')
                            : (json.message || 'L.Mat guardada.');
                        const msg = omitidasSinCantidad > 0
                            ? baseMsg + ' Se omitieron ' + omitidasSinCantidad + ' fila(s) sin cantidad positiva.'
                            : baseMsg;
                        showToast(msg, 'success');
                        Swal.close();
                        try {
                            onSaved({
                                orden,
                                telarId: String(telarSeleccionado || ''),
                                bomId: bomIdGuardado,
                                bomName: bomNameGuardado,
                                updatedBom,
                                actualizaLmat: json.actualizaLmat,
                            });
                        } catch (error) {
                            console.error('No se pudo actualizar localmente la fila de Codificación', error);
                        }
                    } else {
                        const primerError = json?.errors
                            ? Object.values(json.errors).flat().find(Boolean)
                            : null;
                        showToast(primerError || json.message || 'Error al guardar la L.Mat.', 'error');
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
