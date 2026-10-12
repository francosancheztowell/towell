/**
 * Modal L.Mat de Codificación.
 *
 * Este módulo encapsula consultas a catálogos AX, cálculo de cantidades,
 * renderizado del modal y persistencia. El Blade solo proporciona la fila
 * seleccionada y la función para refrescar la tabla.
 */
import { combobox, destruirCombobox, refrescarCombobox } from '../utils/combobox.ts';
import { http, HttpError } from '../utils/http.ts';
import { escapeHtml } from '../utils/format.ts';
import { qs } from '../utils/dom.ts';
import { notify, showToast } from '../utils/notifications.ts';

/** Fila de CatCodificados tal como llega a la grilla (columnas dinámicas). */
export type RegistroCat = Record<string, unknown>;

interface ColorAx { InventColorId: string; Name: string }
interface ClaveMatriz { tipo: string; calibre: number | null; fibraId: string | null; cuenta: string | null }
interface EquivalenciaMatriz { ItemId: string; ConfigId: string; InventSizeId: string; InventColorId: string }
interface RespuestaLMat<T> { success?: boolean; message?: string; data?: T; found?: boolean }

/** Fila guardada en CatLMat (GET por-orden). */
interface FilaCatLMat {
    ItemId?: string | null;
    ConfigId?: string | null;
    InventSizeId?: string | null;
    InventColorId?: string | null;
    NombreColor?: string | null;
    InventLocationId?: string | null;
    Qty?: number | string | null;
    Porcentaje?: number | string | null;
    Tipo?: number | string | null;
    Nombre?: string | null;
    PesoCrudo?: number | string | null;
    ItemIdCrudo?: string | null;
    InventSizeCrudo?: string | null;
}

/** Fila del modal (un componente de la lista de materiales). */
interface FilaLMat {
    articulo: string;
    combinacion: string;
    items: string;
    pasadas: string;
    pasadasField?: string | undefined;
    nombreColor: string;
    config: string;
    tamano: string;
    color: string;
    almacen: string;
    cantidad: number;
    porcentaje: string;
    rol: string;
    calibreFormula?: number | string | undefined;
    matriz: ClaveMatriz | null;
    matrizEncontrada?: boolean;
    desdeCatLMat?: boolean;
}

/** Lo que el modal reporta a la pantalla al guardar. */
export interface LMatGuardada {
    orden: string;
    telarId: string;
    bomId: string;
    bomName: string;
    updatedBom: boolean;
    actualizaLmat: unknown;
    pasadas: Record<string, number>;
    formula: Record<string, number>;
    fibras: Record<string, string>;
    combinacionesVacias: number[];
    luchaje: number | null;
}

interface RespuestaGuardarLMat {
    success?: boolean;
    message?: string;
    updatedBom?: boolean;
    bomId?: string;
    bomName?: string;
    actualizaLmat?: unknown;
}

interface ContextoLMat {
    getSelectedRecord?: () => RegistroCat | null | undefined;
    onSaved?: (datos: LMatGuardada) => void;
}

/// ── Materiales del modal L.Mat (Artículo/Tamaño/Color, cascada por ItemId) ──
const LMatMateriales = (() => {
    let calibres: string[] | null = null;
    let calibresPendientes: Promise<string[]> | null = null;
    const configs = new Map<string, string[]>();
    const tamanos = new Map<string, string[]>();
    const colores = new Map<string, ColorAx[]>();
    const nombresColores = new Map<string, string>();
    const catalogosPendientes = new Map<string, Promise<void>>();
    const matrices = new Map<string, EquivalenciaMatriz>();

    const normalizarItemId = (itemId: unknown): string => String(itemId ?? '').trim();
    const aColorAx = (c: Partial<ColorAx>): ColorAx => ({
        InventColorId: String(c.InventColorId ?? '').trim(),
        Name: String(c.Name ?? '').trim(),
    });

    async function esperarCatalogosPendientes(itemId: string): Promise<void> {
        const pendiente = catalogosPendientes.get(itemId);
        if (!pendiente) return;
        try {
            await pendiente;
        } catch {
            // Los getters individuales ejecutan su fallback si falla el lote.
        }
    }

    async function getCalibres(): Promise<string[]> {
        if (calibres) return calibres;
        if (calibresPendientes) return calibresPendientes;

        calibresPendientes = (async () => {
            let lista: string[] = [];
            try {
                const json = await http.get<RespuestaLMat<{ ItemId?: string }[]>>('/planeacion/lmat/api/calibres');
                lista = (json.data || []).map(i => i.ItemId ?? '').filter(Boolean);
            } catch (e) {
                console.error('No se pudieron cargar artículos (calibres) L.Mat', e);
            } finally {
                calibresPendientes = null;
            }
            calibres = lista;
            return lista;
        })();

        return calibresPendientes;
    }

    /** Lista AX de un ItemId: caché por artículo y espera al lote en curso antes de pedirla sola. */
    async function catalogoPorItem<T>(
        cache: Map<string, T[]>,
        itemId: string,
        cargar: () => Promise<T[]>,
        error: string,
    ): Promise<T[]> {
        if (!itemId) return [];
        if (!cache.has(itemId)) await esperarCatalogosPendientes(itemId);
        const enCache = cache.get(itemId);
        if (enCache) return enCache;
        let lista: T[] = [];
        try {
            lista = await cargar();
        } catch (e) {
            console.error(error, e);
        }
        cache.set(itemId, lista);
        return lista;
    }

    function getConfigs(itemIdCrudo: unknown): Promise<string[]> {
        const itemId = normalizarItemId(itemIdCrudo);
        return catalogoPorItem(configs, itemId, async () => {
            const json = await http.get<RespuestaLMat<{ ConfigId?: string }[]>>('/planeacion/lmat/api/configs', { params: { itemId } });
            return (json.data || [])
                .map(c => c.ConfigId ?? '')
                .filter(Boolean)
                .filter(c => String(c).trim().toUpperCase() !== 'HILO');
        }, 'No se pudieron cargar configs (fibra) L.Mat');
    }

    function getTamanos(itemIdCrudo: unknown): Promise<string[]> {
        const itemId = normalizarItemId(itemIdCrudo);
        return catalogoPorItem(tamanos, itemId, async () => {
            const json = await http.get<RespuestaLMat<{ InventSizeId?: string }[]>>('/planeacion/lmat/api/tamanos', { params: { itemId } });
            return (json.data || []).map(i => i.InventSizeId ?? '').filter(Boolean);
        }, 'No se pudieron cargar tamaños L.Mat');
    }

    async function getColores(itemIdCrudo: unknown): Promise<ColorAx[]> {
        const itemId = normalizarItemId(itemIdCrudo);
        const lista = await catalogoPorItem(colores, itemId, async () => {
            const json = await http.get<RespuestaLMat<Partial<ColorAx>[]>>('/planeacion/lmat/api/colores', { params: { itemId } });
            // AX InventColor: InventColorId (código) + Name (nombre color).
            return (json.data || []).map(aColorAx).filter((c) => c.InventColorId);
        }, 'No se pudieron cargar colores L.Mat');
        lista.forEach((color) => {
            nombresColores.set(itemId + '|' + color.InventColorId, color.Name || '');
        });
        return lista;
    }

    interface MaterialAx { configs?: unknown[]; tamanos?: { InventSizeId?: string }[]; colores?: Partial<ColorAx>[] }

    async function precargarCatalogos(itemIds: unknown[]): Promise<void> {
        const faltantes = Array.from(new Set((itemIds || []).map(normalizarItemId).filter(Boolean)))
            .filter((itemId) => !configs.has(itemId) || !tamanos.has(itemId) || !colores.has(itemId))
            .slice(0, 10);
        if (!faltantes.length) return;

        const params = new URLSearchParams();
        faltantes.forEach((itemId) => params.append('itemIds[]', itemId));
        const carga = (async () => {
            const json = await http.get<RespuestaLMat<Record<string, MaterialAx>>>('/planeacion/lmat/api/catalogos-materiales?' + params.toString());
            if (json.success !== true) {
                throw new Error(json.message || 'Error al precargar catálogos L.Mat');
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
                const coloresItem = (material.colores || []).map(aColorAx).filter((color) => color.InventColorId);

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

    function idsColores(lista: ColorAx[]): string[] {
        return (lista || []).map((c) => c.InventColorId).filter(Boolean);
    }

    function nombreColorPorId(lista: ColorAx[], inventColorId: unknown): string {
        const id = String(inventColorId ?? '').trim();
        if (!id) return '';
        return (lista || []).find((c) => String(c.InventColorId) === id)?.Name || '';
    }

    async function getNombreColor(itemId: unknown, inventColorId: unknown): Promise<string> {
        const articulo = String(itemId ?? '').trim();
        const color = String(inventColorId ?? '').trim();
        if (!articulo || !color) return '';

        const key = articulo + '|' + color;
        if (!nombresColores.has(key)) await esperarCatalogosPendientes(articulo);
        const conocido = nombresColores.get(key);
        if (conocido !== undefined) return conocido;

        let nombre = '';
        try {
            const json = await http.get<RespuestaLMat<Partial<ColorAx>[]>>('/planeacion/lmat/api/colores', {
                params: { itemId: articulo, inventColorId: color },
            });
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

    function claveMatrizKey(clave: ClaveMatriz): string {
        const calibre = clave.calibre === null ? '' : Number(clave.calibre).toFixed(1);
        return [clave.tipo, calibre, clave.fibraId || '', clave.cuenta || ''].join('|');
    }

    async function getMatrizCalibre(clave: ClaveMatriz): Promise<EquivalenciaMatriz | null> {
        const key = claveMatrizKey(clave);
        const enCache = matrices.get(key);
        if (enCache) return enCache;
        const params: Record<string, string> = { tipo: clave.tipo };
        if (clave.calibre !== null) params.calibre = clave.calibre.toFixed(1);
        if (clave.fibraId !== null) params.fibraId = clave.fibraId;
        if (clave.cuenta) params.cuenta = clave.cuenta;

        const json = await http.get<RespuestaLMat<EquivalenciaMatriz>>('/planeacion/lmat/api/matriz-calibre', { params });
        if (json.success !== true) {
            throw new Error(json.message || 'Error al consultar Matriz de Calibres');
        }

        const equivalencia = json.found ? json.data ?? null : null;
        // No guardar misses: la matriz puede aprender una equivalencia al guardar L.Mat.
        if (equivalencia) matrices.set(key, equivalencia);
        return equivalencia;
    }

    async function getMatricesCalibres(claves: (ClaveMatriz | null)[]): Promise<Map<string, EquivalenciaMatriz | null>> {
        const unicas = new Map<string, ClaveMatriz>();
        (claves || []).forEach((clave) => {
            if (clave) unicas.set(claveMatrizKey(clave), clave);
        });
        const faltantes = Array.from(unicas.entries()).filter(([key]) => !matrices.has(key));

        if (faltantes.length) {
            try {
                const json = await http.post<RespuestaLMat<Record<string, EquivalenciaMatriz | null>>>('/planeacion/lmat/api/matriz-calibre/lote', {
                    claves: faltantes.map(([key, clave]) => ({ key, ...clave })),
                });
                if (json.success !== true) {
                    throw new Error(json.message || 'Error al consultar Matriz de Calibres');
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

    function limpiarMatrices(): void {
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
        getMatricesCalibres,
        limpiarMatrices,
    };
})();

function setSelectOptionsLMat(selectEl: HTMLSelectElement, opciones: unknown[], valorActual: unknown): void {
    const esConfig = selectEl.name === 'config[]';
    const esArticulo = selectEl.name === 'articulo[]';
    const esTamano = selectEl.name === 'tamano[]';
    const esColor = selectEl.name === 'color[]';
    let actual = valorActual !== null && valorActual !== undefined ? String(valorActual) : '';
    if (esConfig && actual.trim().toUpperCase() === 'HILO') actual = '';
    let lista = (opciones || []).map(String);
    if (esConfig) lista = lista.filter(v => String(v).trim().toUpperCase() !== 'HILO');
    // Artículos: solo valores del catálogo (GET calibres). No inventar opciones.
    // Otros selects: si el valor capturado no está en catálogo, se conserva.
    // Si AX sí devolvió catálogo y el valor no está, se marca: el guardado lo rechazaría (422).
    const fueraDeAx = !esArticulo && actual !== '' && lista.length > 0 && !lista.includes(actual);
    if (actual !== '' && !lista.includes(actual)) {
        if (esArticulo) actual = '';
        else lista = [actual, ...lista];
    }
    if (lista.length === 0) lista = [''];
    // Config/Artículo/Tamaño/Color: opción vacía inicial para forzar elección manual.
    // (si FibraPie es null, Config debe quedar en "Seleccione..." y Fibra vacía).
    if ((esConfig || esArticulo || esTamano || esColor) && !lista.includes('')) lista = ['', ...lista];

    selectEl.innerHTML = lista.map((valor) => {
        const selected = valor === actual ? ' selected' : '';
        const texto = valor === '' ? 'Seleccione...' : (fueraDeAx && valor === actual ? `${valor} (no existe en AX)` : valor);
        return `<option value="${valor}"${selected}>${texto}</option>`;
    }).join('');
    // Forzar valor vacío explícito (evita selectedIndex=0 con primer Config de AX).
    if (actual === '') selectEl.value = '';
    selectEl.classList.toggle('border-red-500', fueraDeAx);
    selectEl.classList.toggle('ring-1', fueraDeAx);
    selectEl.classList.toggle('ring-red-500', fueraDeAx);
    selectEl.title = fueraDeAx ? `${actual} no existe en AX: elige otro o dalo de alta en AX` : '';
}

function parseCalibrePartsLMat(valor: unknown): { base: string; frac: string | null; num: number } | null {
    const s = String(valor ?? '').trim().replace(',', '.');
    // Acepta artículos AX con sufijo, por ejemplo 450/1T.
    const m = s.match(/^(\d+)(?:[./](\d+))?[A-Za-z]*$/);
    if (!m?.[1]) return null;
    const base = m[1];
    const frac = m[2] != null ? m[2] : null;
    const num = Number(frac != null ? `${base}.${frac}` : base);
    if (!Number.isFinite(num)) return null;
    return { base, frac, num };
}

/** Resuelve Items crudo (ej. 8.0) a un ItemId del GET de calibres: exacto o más cercano del mismo base (8/1). Si no hay ninguno, ''. */
function resolverArticuloDesdeCalibres(itemsCrudo: unknown, calibres: string[]): string {
    const lista = (calibres || []).map(String).filter(Boolean);
    if (!lista.length) return '';
    const parts = parseCalibrePartsLMat(itemsCrudo);
    if (!parts) return '';

    const candidatosExactos: string[] = [];
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

function crearClaveMatrizLMat(tipo: string, calibre: unknown, fibraId: unknown, cuenta: string | null = null): ClaveMatriz | null {
    const tipoNormalizado = String(tipo ?? '').trim().toUpperCase();
    const fibraNormalizada = String(fibraId ?? '').trim().toUpperCase() || null;
    if (!['RIZO', 'PIE', 'TRAMA', 'BARRA1', 'BARRA2', 'BARRA3', 'BARRA4'].includes(tipoNormalizado)) {
        return null;
    }

    let calibreNormalizado: number | null = null;
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

async function openLMatModal(context: ContextoLMat = {}): Promise<void> {
    const {
        getSelectedRecord = () => null,
        onSaved = () => {},
    } = context;
    const registroSeleccionado = getSelectedRecord();

    if (!registroSeleccionado) {
        showToast('Selecciona primero una fila.', 'warning');
        return;
    }

    // Consultar siempre equivalencias vigentes: guardar una L.Mat puede enseñar nuevas claves.
    LMatMateriales.limpiarMatrices();

    const telarId = String(registroSeleccionado?.TelarId ?? '').trim();
    if (!telarId) {
        showToast('La fila seleccionada no tiene telar.', 'warning');
        return;
    }

    const orden = String(registroSeleccionado?.OrdenTejido || '');
    const salon = String(registroSeleccionado?.Departamento || '');
    // Karl Mayer (401-402) no teje rizo/pie/trama/C1..C5: teje 4 barras de JULIO-URDIDO.
    const salonCompactoLMat = String(salon).toUpperCase().replace(/\s+/g, '');
    const esKarlMayerLMat = salonCompactoLMat
        ? ['KM', 'KARLMAYER'].includes(salonCompactoLMat)
        : [401, 402].includes(parseInt(telarId, 10));
    const rolResidualLMat = esKarlMayerLMat ? 'b1' : 'rizo';
    const esJulioFijoLMat = (rol: string): boolean => rol === 'rizo' || rol === 'pie' || /^b[1-4]$/.test(String(rol || ''));
    const telarSeleccionado = parseInt(telarId, 10) || 0;

    // Respuesta visual inmediata mientras se resuelven CatLMat y Matriz de Calibres.
    notify.loading('Lista de materiales');

    // El catálogo de artículos AX puede cargarse en paralelo con CatLMat.
    const calibresPromise = LMatMateriales.getCalibres();

    // Si ya hay una L.Mat guardada para esta Orden en CatLMat, se recarga para editarla.
    let guardadoLMat: FilaCatLMat[] | null = null;
    if (orden) {
        try {
            const jsonGuardado = await http.get<RespuestaLMat<FilaCatLMat[]>>('/planeacion/lmat/api/por-orden/' + encodeURIComponent(orden));
            if (jsonGuardado.success && Array.isArray(jsonGuardado.data) && jsonGuardado.data.length) {
                guardadoLMat = jsonGuardado.data;
            }
        } catch (e) {
            console.error('No se pudo cargar CatLMat', e);
        }
    }
    let tamano = String(registroSeleccionado?.InventSizeId || 'Tamaño');
    let itemId = String(registroSeleccionado?.ItemId || '');
    const articulo = String(registroSeleccionado?.Nombre || registroSeleccionado?.ItemId || 'Nombre Articulo');
    const codigoDibujo = String(registroSeleccionado?.CodigoDibujo || 'Cod Dibujo');
    let pesoCrudo: unknown = registroSeleccionado?.P_crudo || registroSeleccionado?.PesoCrudo || 737;
    const cuentaRizo = String(registroSeleccionado?.CuentaRizo ?? '').trim();
    const cuentaPie = String(registroSeleccionado?.CuentaPie ?? '').trim();
    // Calibre numérico → formato ItemId: 450.1 → 450/1, 16.1 → 16/1, pero entero (10.0) queda como 10.
    const calibreAItemId = (numero: number): string => Number.isInteger(numero) ? String(numero) : numero.toFixed(1).replace('.', '/');
    const formatearCalibre = (valor: unknown, fallback: string): string => {
        const numero = Number(String(valor ?? '').replace(',', '.'));
        return Number.isFinite(numero) ? calibreAItemId(numero) : fallback;
    };
    const calibreRizo = formatearCalibre(registroSeleccionado?.CalibreRizo, 'CalibreRizo');
    const calibrePie = formatearCalibre(registroSeleccionado?.CalibrePie, 'CalibrePie');
    // CatCodificados: FibraRizo / FibraPie / FibraId(trama). null → vacío (no inventar).
    const normalizarTextoCatLMat = (valor: unknown): string => {
        const s = String(valor ?? '').trim();
        if (!s || s.toLowerCase() === 'null' || s.toLowerCase() === 'undefined') return '';
        return s;
    };
    const formatoCalibreCatLMat = (valor: unknown): string => {
        const crudo = normalizarTextoCatLMat(valor);
        if (!crudo) return '';
        const n = Number(String(crudo).replace(',', '.'));
        if (!Number.isFinite(n) || !(n > 0)) return '';
        // Tra "10" → "10"; CalibreRizo "12.1" → "12.1"
        return Number.isInteger(n) ? String(n) : n.toFixed(1);
    };
    const formatoPasadasCatLMat = (valor: unknown): string => {
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
    const normalizarInventSizeIdLMat = (valor: unknown): string => String(valor ?? '')
        .trim()
        .replace(/\s*-\s*/g, '-')
        .replace(/\s+/g, '');
    const truncLmat = (value: unknown, max: number): string => {
        const text = String(value ?? '');
        return text.length > max ? text.slice(0, max) : text;
    };
    function resolverAlmacenLMat(articuloLMat: unknown): string {
        if (String(articuloLMat || '').startsWith('JU-ENG-') || articuloLMat === 'JULIO-URDIDO') return 'A-EP-TEJID';
        if (telarSeleccionado >= 305 && telarSeleccionado <= 316) return 'A-PTE-LISO';
        if (telarSeleccionado >= 200 && telarSeleccionado <= 220) return 'A-PTE-JACQ';
        if ((telarSeleccionado >= 299 && telarSeleccionado <= 304) || (telarSeleccionado >= 317 && telarSeleccionado <= 320)) return 'A-PTE-ITEM';
        return '';
    }
    function almacenVisibleLMat(item: FilaLMat): string {
        const guardado = String(item.almacen ?? '').trim();
        return guardado !== '' ? guardado : resolverAlmacenLMat(item.articulo);
    }
    // Nombre: TEJ + Nombre artículo
    let nombreLMat = truncLmat(['TEJ', articulo].filter(Boolean).join(' '), 20);
    // Descripción: Nombre + CodigoDibujo (sin InventSizeId / tamaño).
    let descripcionLMat = truncLmat([articulo, codigoDibujo].filter(Boolean).join(' '), 60);
    if (!guardadoLMat && itemId) {
        // L.Mat nueva: precargar Nombre/Descrip de la última creada con el mismo ItemIdCrudo + InventSizeCrudo.
        // Si no hay ninguna, quedan los concatenados de arriba.
        try {
            const jsonUltima = await http.get<RespuestaLMat<{ Nombre?: string; Descrip?: string }>>('/planeacion/lmat/api/ultima', {
                params: { itemIdCrudo: itemId, inventSizeCrudo: tamano.trim() },
            });
            if (jsonUltima.success && jsonUltima.data) {
                nombreLMat = truncLmat(jsonUltima.data.Nombre ?? nombreLMat, 20);
                descripcionLMat = truncLmat(jsonUltima.data.Descrip ?? descripcionLMat, 60);
            }
        } catch (e) {
            console.error('No se pudo cargar la última L.Mat por crudo', e);
        }
    }
    const cabeceraGuardada = guardadoLMat?.[0];
    if (cabeceraGuardada) {
        // Recargar cabecera desde lo guardado (Nombre=BomId). Descripción se recalcula sin tamaño.
        nombreLMat = truncLmat(cabeceraGuardada.Nombre ?? nombreLMat, 20);
        if (cabeceraGuardada.PesoCrudo != null && cabeceraGuardada.PesoCrudo !== '') pesoCrudo = cabeceraGuardada.PesoCrudo;
        if (cabeceraGuardada.ItemIdCrudo != null && cabeceraGuardada.ItemIdCrudo !== '') itemId = cabeceraGuardada.ItemIdCrudo;
        if (cabeceraGuardada.InventSizeCrudo != null && cabeceraGuardada.InventSizeCrudo !== '') tamano = cabeceraGuardada.InventSizeCrudo;
    }

    // Cálculo Excel (curvas → pesos g → % → cantidad kg):
    // Trama/Cn = SI(P>0, ((P*(AnchoPeine+(13/TL))*curva_peine)/100)*0.59/Hilo, 0)
    // Pie = (((Largo+Corte)*curva_luchaje/100)*0.59/HiloPie)*1.076*(CuentaPie/TL)
    // Rizo = PesoCrudo - (Pie+Trama+C1..C5)
    // Cantidad(kg) = peso_g / 1000 ; % = peso_g / PesoCrudo
    const numLMat = (valor: unknown): number => {
        const n = Number(String(valor ?? '').replace(',', '.'));
        return Number.isFinite(n) ? n : 0;
    };
    const FACTOR_PIE_LMAT = 1.076;
    const DENSIDAD_HILO_LMAT = 0.59;
    const inputsCalculoLMat = {
        peine: numLMat(registroSeleccionado?.Peine),
        ancho: numLMat(registroSeleccionado?.Ancho),
        // CatCodificados.TramaAnchoPeine ya contiene el ancho en peine POR TIRA
        // (p. ej. 93 = 279 / 3). Excel usa W = H + (13 / NoTiras).
        anchoPeine: numLMat(registroSeleccionado?.TramaAnchoPeine),
        largo: numLMat(registroSeleccionado?.Largo),
        corte: numLMat(registroSeleccionado?.MedidaPlano),
        luchaje: numLMat(registroSeleccionado?.Luchaje),
        tl: numLMat(registroSeleccionado?.NoTiras),
        hiloPie: numLMat(registroSeleccionado?.CalibrePie2),
        cuentaPie: numLMat(registroSeleccionado?.CuentaPie),
        pasadasTrama: numLMat(registroSeleccionado?.PasadasTramaFondoC1),
        // En CatCodificados de producción, CalTramaFondoC1 contiene el divisor
        // numérico (p. ej. 10) y CalTramaFondoC12 el código de hilo (p. ej. 10.1).
        hiloTrama: numLMat(registroSeleccionado?.CalTramaFondoC1),
        pasadasComb: [1, 2, 3, 4, 5].map((n) => numLMat(registroSeleccionado?.[`PasadasComb${n}`])),
        hiloComb: [1, 2, 3, 4, 5].map((n) => numLMat(registroSeleccionado?.[`CalibreComb${n}2`])),
        pasadasBarra: [1, 2, 3, 4].map((n) => numLMat(registroSeleccionado?.[`PasadasBarra${n}`])),
        // Calibre de fórmula (CalibreBarraN2, p. ej. 75.99 para poliéster 70/1); si no se ha
        // capturado se usa el de catálogo, que en algodón es el mismo número (16/1 → 16).
        calibreBarra: [1, 2, 3, 4].map((n) => numLMat(registroSeleccionado?.[`CalibreBarra${n}2`])
            || numLMat(registroSeleccionado?.[`CalibreBarra${n}`])),
    };
    const campoPasadasPorRolLMat: Record<string, string> = {
        trama: 'PasadasTramaFondoC1',
        c1: 'PasadasComb1',
        c2: 'PasadasComb2',
        c3: 'PasadasComb3',
        c4: 'PasadasComb4',
        c5: 'PasadasComb5',
    };
    // Fibra editable → columna de CatCodificados. Solo C1..C5: rizo, pie y trama
    // se muestran como texto informativo.
    const campoFibraPorRolLMat: Record<string, string> = {
        c1: 'FibraComb1',
        c2: 'FibraComb2',
        c3: 'FibraComb3',
        c4: 'FibraComb4',
        c5: 'FibraComb5',
    };

    // CalibreComb{n} (nvarchar, p. ej. '10.1') es el calibre de catálogo y
    // CalibreComb{n}2 (float, p. ej. 10.0 u 8.86) el divisor de la fórmula: NO son
    // el mismo número, por eso una combinación puede tener uno y no el otro.
    const combinacionTieneDatosLMat = (n: number): boolean => (
        formatoCalibreCatLMat(registroSeleccionado?.[`CalibreComb${n}`]) !== ''
        || normalizarTextoCatLMat(registroSeleccionado?.[`FibraComb${n}`]) !== ''
        || numLMat(registroSeleccionado?.[`PasadasComb${n}`]) > 0
        || numLMat(registroSeleccionado?.[`CalibreComb${n}2`]) > 0
    );
    // Una combinación existe si CUALQUIER campo suyo trae dato. Mirar solo el calibre
    // de catálogo perdía las combinaciones capturadas desde este modal (que escribe
    // fibra/pasadas/CalibreComb{n}2 pero no CalibreComb{n}) y descuadraba el empate
    // contra CatLMat al reabrir.
    const defTieneDatosLMat = (def: FilaLMat): boolean => Boolean(
        def.items || def.combinacion || def.pasadas || Number(def.calibreFormula) > 0
    );
    const sumaPasadasInicialLMat = inputsCalculoLMat.pasadasTrama
        + inputsCalculoLMat.pasadasComb.reduce((total, valor) => total + valor, 0);
    const totalPasadasReferenciaLMat = Math.floor(
        numLMat(registroSeleccionado?.Total) > 0
            ? numLMat(registroSeleccionado.Total)
            : sumaPasadasInicialLMat,
    );
    const minimoPasadasLMat = Math.floor(totalPasadasReferenciaLMat * 0.70);
    const maximoPasadasLMat = Math.floor(totalPasadasReferenciaLMat * 1.30);
    const inputsReferenciaLMat = {
        ...inputsCalculoLMat,
        pasadasComb: [...inputsCalculoLMat.pasadasComb],
        hiloComb: [...inputsCalculoLMat.hiloComb],
    };
    const calcularPesosComponentesLMat = (pesoCrudoG: unknown, datosCalculo: typeof inputsCalculoLMat = inputsCalculoLMat) => {
        const pesoCrudoTotal = numLMat(pesoCrudoG);
        const { peine, anchoPeine, largo, corte, luchaje, tl, hiloPie, cuentaPie, hiloTrama, hiloComb } = datosCalculo;
        const pasadasTrama = numLMat(datosCalculo.pasadasTrama);
        const pasadasComb = Array.from(
            { length: 5 },
            (_, index) => numLMat(datosCalculo.pasadasComb?.[index]),
        );
        const curvaLuchaje = luchaje >= 33 ? 1.083 : 1.055;
        const curvaPeine = peine >= 50 ? 1.001 : 1.002;
        const pesoTramaCn = (pasadas: number, hilo: number): number => {
            if (!(pasadas > 0) || !(hilo > 0) || !(tl > 0)) return 0;
            // No usar Ancho como fallback: es el ancho terminado y no sustituye al ancho
            // en peine. Si falta TramaAnchoPeine, la fila queda sin cantidad calculada.
            if (!(anchoPeine > 0)) return 0;
            const anchoTira = anchoPeine + (13 / tl);
            return ((pasadas * anchoTira * curvaPeine) / 100) * DENSIDAD_HILO_LMAT / hilo;
        };
        const tramaG = pesoTramaCn(pasadasTrama, hiloTrama);
        const combG = pasadasComb.map((p, i) => pesoTramaCn(p, hiloComb[i] ?? 0));
        let pieG = 0;
        if (hiloPie > 0 && tl > 0) {
            pieG = (((largo + corte) * curvaLuchaje / 100) * DENSIDAD_HILO_LMAT / hiloPie)
                * FACTOR_PIE_LMAT
                * (cuentaPie / tl);
        }
        const sumaSinRizo = pieG + tramaG + combG.reduce((a, b) => a + b, 0);
        // Igual que el Excel (AX2): resta directa, sin tope en 0.
        const rizoG = pesoCrudoTotal - sumaSinRizo;
        // Karl Mayer: %barra = (Pasadas / Calibre) / Σ(Pasadas / Calibre). La hoja además
        // multiplica por 0.59 y divide entre ΣPasadas, pero ambos factores se cancelan.
        const ponderadoBarras = (datosCalculo.pasadasBarra || []).map((pasadas, i) => {
            const calibre = numLMat(datosCalculo.calibreBarra?.[i]);
            return numLMat(pasadas) > 0 && calibre > 0 ? numLMat(pasadas) / calibre : 0;
        });
        const sumaBarras = ponderadoBarras.reduce((a, b) => a + b, 0);
        const barrasG = ponderadoBarras.map((w) => (sumaBarras > 0 ? pesoCrudoTotal * (w / sumaBarras) : 0));
        return { rizoG, pieG, tramaG, combG, barrasG, pesoCrudoTotal };
    };
    const pesoACantidadYPorcentajeLMat = (pesoG: number, pesoCrudoTotal: number) => {
        const cantidad = pesoG / 1000;
        const porcentaje = pesoCrudoTotal > 0
            ? Number(((pesoG / pesoCrudoTotal) * 100).toFixed(2))
            : 0;
        return { cantidad, porcentaje: porcentaje.toFixed(2) + '%' };
    };
    const formatearCantidadLMat = (valor: unknown): string => {
        const cantidad = Number(valor);
        if (!Number.isFinite(cantidad) || cantidad <= 0) return '0.0000';
        return cantidad.toFixed(4);
    };
    const formatearPorcentajeLMat = (valor: unknown): string => {
        const porcentaje = Number(String(valor ?? '').replace('%', '').replace(',', '.'));
        return Number.isFinite(porcentaje) && porcentaje >= 0 ? porcentaje.toFixed(2) : '0.00';
    };
    const serializarCantidadRawLMat = (valor: unknown): string => {
        const cantidad = Number(valor);
        return Number.isFinite(cantidad) && cantidad > 0 ? String(cantidad) : '0';
    };
    // Tamaño AX de una barra: Cuenta-Calibre/hilos (2028 + 370 → 2028-370/1, 16.1 → 16/1).
    const tamanoBarraLMat = (cuenta: string, calibre: unknown): string => {
        const c = String(cuenta ?? '').trim();
        const n = numLMat(calibre);
        if (!c || !(n > 0)) return '';
        const entero = Math.trunc(n);
        return `${c}-${entero}/${Math.round((n - entero) * 10) || 1}`;
    };
    const armarFilasDesdeCalculoLMat = (pesoCrudoG: unknown): FilaLMat[] => {
        const pesos = calcularPesosComponentesLMat(pesoCrudoG);
        if (esKarlMayerLMat) {
            // Igual que rizo/pie: julio de artículo fijo; Config/Tamaño/Color los resuelve
            // la matriz con una clave por barra (BARRA1..BARRA4).
            return [1, 2, 3, 4].map((n): FilaLMat => {
                const barra = pesoACantidadYPorcentajeLMat(pesos.barrasG[n - 1] ?? 0, pesos.pesoCrudoTotal);
                const cuenta = String(registroSeleccionado?.[`CuentaBarra${n}`] ?? '').trim();
                const calibre = registroSeleccionado?.[`CalibreBarra${n}`];
                const fibra = normalizarTextoCatLMat(registroSeleccionado?.[`FibraBarra${n}`]);
                return {
                    articulo: 'JULIO-URDIDO',
                    combinacion: fibra,
                    items: formatoCalibreCatLMat(calibre),
                    pasadas: formatoPasadasCatLMat(registroSeleccionado?.[`PasadasBarra${n}`]),
                    pasadasField: `PasadasBarra${n}`,
                    nombreColor: '',
                    config: '',
                    tamano: tamanoBarraLMat(cuenta, calibre),
                    color: '1000',
                    almacen: 'A-EP-TEJID',
                    cantidad: barra.cantidad,
                    porcentaje: barra.porcentaje,
                    rol: `b${n}`,
                    calibreFormula: inputsCalculoLMat.calibreBarra[n - 1] || '',
                    matriz: crearClaveMatrizLMat(`BARRA${n}`, calibre, fibra, cuenta),
                };
            });
        }
        const rizo = pesoACantidadYPorcentajeLMat(pesos.rizoG, pesos.pesoCrudoTotal);
        const pie = pesoACantidadYPorcentajeLMat(pesos.pieG, pesos.pesoCrudoTotal);
        const trama = pesoACantidadYPorcentajeLMat(pesos.tramaG, pesos.pesoCrudoTotal);
        // Fibra + Calibre = SOLO informativos desde CatCodificados (no son Config).
        // Config empieza vacío y se elige/guarda aparte en CatLMat.ConfigId.
        const filas: FilaLMat[] = [
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
                calibreFormula: inputsCalculoLMat.hiloPie,
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
                pasadasField: campoPasadasPorRolLMat.trama,
                // Nombre color: se resuelve con GET AX InventColor.Name al cargar colores.
                nombreColor: '',
                config: '',
                tamano: '',
                color: String(registroSeleccionado?.CodColorTrama || ''),
                almacen: '',
                cantidad: trama.cantidad,
                porcentaje: trama.porcentaje,
                rol: 'trama',
                calibreFormula: inputsCalculoLMat.hiloTrama,
                matriz: crearClaveMatrizLMat(
                    'TRAMA',
                    registroSeleccionado?.Tra,
                    fibraTrama,
                ),
            });
        }
        // C1..C5 siempre visibles: llenos o vacíos según CatCodificados.
        for (let n = 1; n <= 5; n++) {
            const comb = pesoACantidadYPorcentajeLMat(pesos.combG[n - 1] ?? 0, pesos.pesoCrudoTotal);
            filas.push({
                articulo: '',
                combinacion: normalizarTextoCatLMat(registroSeleccionado?.[`FibraComb${n}`]),
                items: formatoCalibreCatLMat(registroSeleccionado?.[`CalibreComb${n}`]),
                pasadas: formatoPasadasCatLMat(registroSeleccionado?.[`PasadasComb${n}`]),
                pasadasField: campoPasadasPorRolLMat[`c${n}`],
                nombreColor: '',
                config: '',
                tamano: '',
                color: String(registroSeleccionado?.[`CodColorC${n}`] || ''),
                almacen: '',
                cantidad: comb.cantidad,
                porcentaje: comb.porcentaje,
                rol: 'c' + n,
                calibreFormula: inputsCalculoLMat.hiloComb[n - 1] || '',
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
    const completarFilasDesdeMatrizLMat = async (filasPendientes: FilaLMat[]): Promise<void> => {
        const filasConMatriz = filasPendientes.filter((item) => item.matriz);
        if (!filasConMatriz.length) return;
        try {
            const equivalencias = await LMatMateriales.getMatricesCalibres(
                filasConMatriz.map((item) => item.matriz),
            );
            filasConMatriz.forEach((item) => {
                const equivalencia = item.matriz && equivalencias.get(LMatMateriales.claveMatrizKey(item.matriz));
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

    const filasExistentesLMat: FilaLMat[] = [];
    articulos.forEach((f) => {
        if (f.rol === 'trama' || String(f.rol || '').startsWith('c')) filasExistentesLMat.push(f);
    });

    // Helper: Calibre display desde ItemId (solo filas añadidas manualmente, sin rol CatCodificados).
    const calibreDisplayDesdeItemIdLMat = (itemId: unknown): string => {
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
        const usados = new Set<number>();
        const calibresParaMapeo = await calibresPromise;
        const noEsRizoNiPie = (registro: FilaCatLMat): boolean => {
            const id = String(registro.ItemId ?? '').trim();
            return !(id === 'JU-ENG-RI-C' || id.startsWith('JU-ENG-RI')
                || id === 'JU-ENG-PI-C' || id.startsWith('JU-ENG-PI'));
        };
        // Solo defaults con datos de CatCodificados cuentan para detectar huecos;
        // los C1..C5 vacíos no participan del empate con lo guardado.
        const defaultsTramaComb = defaults.filter((def) => def.rol !== 'rizo' && def.rol !== 'pie' && defTieneDatosLMat(def));
        const guardadosTramaComb = guardadoLMat.filter(noEsRizoNiPie);
        const hayHuecosTramaComb = guardadosTramaComb.length < defaultsTramaComb.length;

        const tomarGuardado = (predicado: (r: FilaCatLMat) => boolean): FilaCatLMat | null => {
            const idx = guardadoLMat.findIndex((r, i) => !usados.has(i) && predicado(r));
            if (idx < 0) return null;
            usados.add(idx);
            return guardadoLMat[idx] ?? null;
        };

        articulos = defaults.map((def): FilaLMat | null => {
            let saved: FilaCatLMat | null = null;
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
            } else if (/^b[1-4]$/.test(String(def.rol))) {
                // Barras KM: por CatLMat.Tipo (1..4); filas viejas sin Tipo, en orden (Barra 1 primero).
                const barra = Number(String(def.rol).slice(1));
                saved = tomarGuardado((r) => Number(r.Tipo) === barra)
                    || tomarGuardado((r) => r.Tipo == null && noEsRizoNiPie(r));
            } else if (defTieneDatosLMat(def)) {
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

            // Al actualizar una L.Mat ya guardada, rizo/pie/trama solo se muestran si
            // están en CatLMat. C1..C5 siempre se muestran (vacíos, cantidad 0) para
            // poder capturarlos sin el botón de añadir fila.
            if (!saved) {
                return /^(c[1-5]|b[1-4])$/.test(String(def.rol)) ? { ...def, cantidad: 0, porcentaje: '0.00%' } : null;
            }

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
        }).filter((f): f is FilaLMat => f !== null);

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
    const sinHiloConfig = (valor: unknown): boolean => String(valor ?? '').trim().toUpperCase() !== 'HILO';
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
        config: [] as string[],
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
    // KM no tiene L.Mat ESTAND: al liberar se le pone una genérica KM (…GEN…-K, …EST…-K)
    // o ninguna, así que en KM siempre se puede reemplazar por la L.Mat propia de la orden.
    const esBomIdEstand = esKarlMayerLMat || bomIdActualCat.toUpperCase().startsWith('ESTAND');
    const nombreInputAttrsLMat = esActualizacionLMat
        ? 'readonly disabled title="El nombre no se puede cambiar al actualizar"'
        : '';
    const nombreInputClassLMat = esActualizacionLMat
        ? 'w-full rounded border border-amber-300 bg-amber-50 px-2 py-1.5 text-sm text-amber-900 cursor-not-allowed'
        : 'w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-800 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400';
    const guardarBtnClassLMat = esActualizacionLMat
        ? 'inline-flex w-full items-center justify-center gap-2 rounded-lg bg-amber-500 px-6 py-3 text-base font-semibold text-white hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400'
        : 'inline-flex w-full items-center justify-center gap-2 rounded-lg bg-black px-6 py-3 text-base font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-400';
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
                                    checked
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

    function buildSelectLMat(nombre: string, valorActual: unknown, opciones: unknown[]): string {
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

    function renderConfigLMat(item: FilaLMat): string {
        return buildSelectLMat('config[]', item.config, opcionesSelectLMat.config);
    }

    function renderTamanoLMat(item: FilaLMat): string {
        return buildSelectLMat('tamano[]', item.tamano, opcionesSelectLMat.tamano);
    }

    function renderPlanoOSelectLMat(item: FilaLMat, campo: keyof FilaLMat, nombre: string, opciones: string[]): string {
        if (esJulioFijoLMat(item.rol)) {
            return `<span class="font-medium text-gray-800">${escapeHtml(item[campo])}</span>`;
        }
        return buildSelectLMat(nombre, item[campo], opciones);
    }

    function atributosMatrizLMat(item: FilaLMat): string {
        const clave = item.matriz;
        if (!clave) return '';

        return [
            `data-matriz-clave="${escapeHtml(LMatMateriales.claveMatrizKey(clave))}"`,
            `data-matriz-tipo="${escapeHtml(clave.tipo)}"`,
            `data-matriz-calibre="${escapeHtml(clave.calibre === null ? '' : clave.calibre.toFixed(1))}"`,
            `data-matriz-fibra-id="${escapeHtml(clave.fibraId || '')}"`,
            `data-matriz-cuenta="${escapeHtml(clave.cuenta || '')}"`,
            `data-matriz-encontrada="${item.matrizEncontrada ? '1' : '0'}"`,
            `data-preservar-articulo="${item.matrizEncontrada || item.desdeCatLMat ? '1' : '0'}"`,
        ].join(' ');
    }

    function renderFibraLMat(item: FilaLMat): string {
        const campo = campoFibraPorRolLMat[item.rol];
        if (!campo) return escapeHtml(item.combinacion || '');

        return `
            <input
                type="text"
                name="fibra[]"
                maxlength="50"
                data-fibra-field="${escapeHtml(campo)}"
                class="lmat-fibra-input w-32 rounded border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-900 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                value="${escapeHtml(item.combinacion || '')}"
            >
        `;
    }

    function renderPasadasLMat(item: FilaLMat): string {
        if (!item.pasadasField) return '';

        return `
            <input
                type="text"
                name="pasadas[]"
                inputmode="numeric"
                data-pasadas-field="${escapeHtml(item.pasadasField)}"
                class="lmat-pasadas-input w-20 rounded border border-gray-300 bg-white px-2 py-1.5 text-right text-xs tabular-nums text-gray-900 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                value="${escapeHtml(item.pasadas || '')}"
            >
        `;
    }

    function renderCalibreFormulaLMat(item: FilaLMat): string {
        if (!['pie', 'trama', 'c1', 'c2', 'c3', 'c4', 'c5', 'b1', 'b2', 'b3', 'b4'].includes(item.rol)) {
            return escapeHtml(item.items || '');
        }

        return `
            <input
                type="text"
                inputmode="decimal"
                data-calibre-rol="${escapeHtml(item.rol)}"
                title="Calibre de catálogo: ${escapeHtml(item.items || '')}"
                class="lmat-calibre-formula-input w-20 rounded border border-gray-300 bg-white px-2 py-1.5 text-right text-xs tabular-nums text-gray-900 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                value="${escapeHtml(String(item.calibreFormula ?? ''))}"
            >
        `;
    }

    const etiquetaRolLMat = (rol: string): string => (
        ({ rizo: 'Rizo', pie: 'Pie', trama: 'Trama' } as Record<string, string>)[rol]
        || (/^b[1-4]$/.test(String(rol || '')) ? `Barra ${String(rol).slice(1)}` : null)
        || (rol ? String(rol).toUpperCase() : 'Extra')
    );
    const esFilaVaciaLMat = (item: FilaLMat): boolean => String(item.rol || '').startsWith('c')
        && !item.combinacion && !item.items && !(Number(item.cantidad) > 0);
    const filas = articulos.map(item => {
        const vacia = esFilaVaciaLMat(item);
        const badgeClass = vacia
            ? 'border border-dashed border-gray-300 bg-white text-gray-400'
            : (esJulioFijoLMat(item.rol)
                ? 'bg-blue-50 text-blue-700'
                : 'bg-gray-100 text-gray-600');
        return `
        <tr class="divide-x divide-gray-200 border-b border-gray-200 ${vacia ? 'bg-gray-100/70 text-gray-400' : 'even:bg-slate-50 hover:bg-blue-50/60'}"${esJulioFijoLMat(item.rol) ? ` data-articulo-fijo="${escapeHtml(item.articulo)}"` : ''}${item.rol ? ` data-rol="${escapeHtml(item.rol)}"` : ''}${item.matriz ? ` ${atributosMatrizLMat(item)}` : ''}${item.desdeCatLMat && !item.matriz ? ' data-preservar-articulo="1"' : ''}>
            <td class="px-2 py-1.5 whitespace-nowrap">
                <span class="inline-flex min-w-[52px] justify-center whitespace-nowrap rounded px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${badgeClass}">${escapeHtml(etiquetaRolLMat(item.rol))}</span>
            </td>
            <td class="lmat-combinacion-cell px-2 py-1.5 font-medium whitespace-nowrap text-gray-800">${renderFibraLMat(item)}</td>
            <td class="lmat-items-cell px-2 py-1.5 font-medium tabular-nums text-gray-800" data-calibre-informativo="${escapeHtml(item.items || '')}">${renderCalibreFormulaLMat(item)}</td>
            <td class="lmat-pasadas-cell px-2 py-1.5 text-right font-medium tabular-nums text-gray-800">${renderPasadasLMat(item)}</td>
            <td class="px-2 py-1.5 min-w-[150px]">${renderPlanoOSelectLMat(item, 'articulo', 'articulo[]', opcionesSelectLMat.articulo)}</td>
            <td class="px-2 py-1.5 min-w-[130px]">${renderConfigLMat(item)}</td>
            <td class="px-2 py-1.5 min-w-[130px]">${renderTamanoLMat(item)}</td>
            <td class="px-2 py-1.5 min-w-[150px]">
                ${buildSelectLMat('color[]', item.color, opcionesSelectLMat.color)}
                <input type="hidden" class="lmat-nombre-color-input" value="${escapeHtml(item.nombreColor || '')}">
            </td>
            <td class="lmat-almacen-cell px-2 py-1.5 font-medium whitespace-nowrap text-gray-800">${escapeHtml(almacenVisibleLMat(item))}</td>
            <td class="px-2 py-1.5 text-right">
                <input
                    type="text"
                    name="cantidad[]"
                    inputmode="decimal"
                    data-cantidad-raw="${escapeHtml(serializarCantidadRawLMat(item.cantidad))}"
                    class="lmat-cantidad-input w-24 rounded border border-gray-300 bg-white px-2 py-1.5 text-right text-xs tabular-nums text-gray-900 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                    value="${formatearCantidadLMat(item.cantidad)}"
                >
            </td>
            <td class="px-2 py-1.5 text-right">
                <input
                    type="text"
                    name="porcentaje[]"
                    inputmode="decimal"
                    class="lmat-porcentaje-input w-20 rounded border border-gray-300 bg-white px-2 py-1.5 text-right text-xs tabular-nums text-gray-900 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                    value="${escapeHtml(formatearPorcentajeLMat(item.porcentaje))}"
                >
            </td>
        </tr>
    `;
    }).join('');

    // Cierra el loading antes de abrir el formulario.
    notify.close();
    notify.dialog({
        titulo: 'Lista de materiales',
        tono: null,
        formulario: true,
        cerrable: false,
        ancho: '2xl',
        html: `
            <div class="ui-dialogo-libre text-left text-sm text-gray-800">
                <div class="mb-3 border-b border-gray-200 pb-3 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold ${esActualizacionLMat ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800'}">
                            <i class="${esActualizacionLMat ? 'fas fa-pen' : 'fas fa-plus'} text-[9px]"></i>
                            ${esActualizacionLMat ? 'Actualización' : 'Nueva L.Mat'}
                        </span>
                    </div>
                    <div class="mt-2 flex flex-wrap items-stretch justify-center gap-3">
                        ${[['Orden', orden], ['Salón', salon], ['Telar', telarSeleccionado || '']].map(([etiqueta, valor]) => `
                        <div class="min-w-[130px] rounded-lg border border-gray-200 bg-gray-50 px-5 py-1.5">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">${etiqueta}</div>
                            <div class="text-2xl font-bold tabular-nums leading-tight text-gray-900">${escapeHtml(String(valor))}</div>
                        </div>`).join('')}
                    </div>
                </div>
                <div id="lmat-banner-ocupada" class="hidden mb-3 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i> L.Mat ocupada — ya existe una con ese nombre.
                </div>
                <div class="mb-3">
                    <div class="grid grid-cols-2 gap-x-3 gap-y-2 sm:grid-cols-4 lg:grid-cols-12">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-gray-700">Peso Crudo</span>
                            <input
                                type="number"
                                id="lmat-pesocrudo"
                                step="0.01"
                                min="0"
                                class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-right tabular-nums text-gray-800 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                                value="${escapeHtml(pesoCrudo)}"
                            >
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-gray-700">No. tiras</span>
                            <span class="min-h-[30px] flex items-center justify-end border-b border-gray-200 text-sm tabular-nums">
                                ${escapeHtml(String(registroSeleccionado?.NoTiras ?? ''))}
                            </span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-gray-700">Largo</span>
                            <input
                                type="number"
                                id="lmat-largo"
                                step="0.01"
                                min="0"
                                class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-right text-sm tabular-nums text-gray-800 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                                value="${escapeHtml(String(registroSeleccionado?.Largo ?? ''))}"
                            >
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-gray-700">Ancho peine</span>
                            <input
                                type="number"
                                id="lmat-ancho-peine"
                                step="0.01"
                                min="0"
                                class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-right text-sm tabular-nums text-gray-800 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                                value="${escapeHtml(String(registroSeleccionado?.TramaAnchoPeine ?? ''))}"
                            >
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-gray-700">Luchaje</span>
                            <input
                                type="number"
                                id="lmat-luchaje"
                                step="1"
                                min="0"
                                class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-right text-sm tabular-nums text-gray-800 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                                value="${escapeHtml(String(registroSeleccionado?.Luchaje ?? ''))}"
                            >
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-gray-700">ItemId</span>
                            <input
                                type="text"
                                id="lmat-itemid"
                                class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-800 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                                value="${escapeHtml(itemId)}"
                            >
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-gray-700">Tamaño</span>
                            <input
                                type="text"
                                id="lmat-tamano"
                                class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-800 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                                value="${escapeHtml(tamano)}"
                            >
                        </div>
                        <div class="col-span-2 flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-gray-700">Nombre (20 caracteres)</span>
                            <input
                                type="text"
                                id="lmat-nombre"
                                maxlength="20"
                                ${nombreInputAttrsLMat}
                                class="${nombreInputClassLMat}"
                                value="${escapeHtml(nombreLMat)}"
                            >
                            <p id="lmat-nombre-error" class="hidden mt-0.5 text-xs font-semibold text-red-600">Ya existe esa L.Mat</p>
                        </div>
                        <div class="col-span-2 flex flex-col gap-0.5 sm:col-span-4 lg:col-span-3">
                            <span class="text-xs font-semibold text-gray-700">Descripción (60 caracteres)</span>
                            <input
                                type="text"
                                id="lmat-descripcion"
                                maxlength="60"
                                class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-800 focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-400"
                                value="${escapeHtml(descripcionLMat)}"
                            >
                        </div>
                        ${actLmatCheckboxHtml}
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
                    <table class="w-full min-w-[1040px] border-collapse text-xs">
                        <thead class="bg-blue-600 text-white">
                            <tr class="divide-x divide-blue-500 whitespace-nowrap text-[11px] uppercase tracking-wide">
                                <th class="px-2 py-1.5 text-center font-semibold">Comp.</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Fibra</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Calibre</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Pasadas</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Artículos</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Config</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Tamaño</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Color</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Almacén</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Cantidad</th>
                                <th class="px-2 py-1.5 text-center font-semibold">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${filas}
                        </tbody>
                        <tfoot class="border-t-2 border-gray-300 bg-gray-100 font-semibold">
                            <tr class="divide-x divide-gray-200">
                                <td class="px-3 py-2" colspan="3"></td>
                                <td id="lmat-total-pasadas" class="px-3 py-2 text-right tabular-nums">${esKarlMayerLMat ? '' : `
                                    <span id="lmat-total-pasadas-valor">${sumaPasadasInicialLMat}</span>
                                    <span class="block whitespace-nowrap text-[10px] font-normal text-gray-500">
                                        Permitido: ${minimoPasadasLMat}–${maximoPasadasLMat}
                                    </span>
                                `}</td>
                                <td class="px-3 py-2" colspan="5"></td>
                                <td id="lmat-total-cantidad" class="px-3 py-2 text-right tabular-nums">${totalCantidad.toFixed(4)}</td>
                                <td id="lmat-total-porcentaje" class="px-3 py-2 text-right tabular-nums ${totalPorcentajeClass}">${totalPorcentajeRedondeado.toFixed(2)}%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div id="lmat-advertencias" class="hidden mt-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800"></div>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:ml-auto sm:w-[560px] sm:grid-cols-2">
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
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-gray-600 px-6 py-3 text-base font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400"
                    >
                        <i class="fas fa-times"></i>
                        <span>Cancelar</span>
                    </button>
                </div>
            </div>
        `,
        alAbrir: (raizLMat) => {
            // Mismo ancho que tenía antes (la tabla tiene muchas columnas).
            const dialogoLMat = raizLMat.closest('dialog');
            if (dialogoLMat) dialogoLMat.style.maxWidth = 'min(1700px, 98vw)';
            const tbodyLMat = raizLMat.querySelector('tbody');
            const pesoCrudoInput = qs<HTMLInputElement>('#lmat-pesocrudo');
            const largoInput = qs<HTMLInputElement>('#lmat-largo');
            const anchoPeineInput = qs<HTMLInputElement>('#lmat-ancho-peine');
            let totalPorcentajeActualLMat = totalPorcentajeRedondeado;
            let totalPasadasValidoLMat = true;
            let onPorcentajeActualizadoLMat: (() => void) | null = null;

            const actualizarTotalPasadasLMat = () => {
                const total = Math.floor(
                    inputsCalculoLMat.pasadasTrama
                    + inputsCalculoLMat.pasadasComb.reduce((suma, valor) => suma + valor, 0),
                );
                totalPasadasValidoLMat = totalPasadasReferenciaLMat <= 0
                    || (total >= minimoPasadasLMat && total <= maximoPasadasLMat);
                const totalCell = document.getElementById('lmat-total-pasadas');
                if (totalCell && !esKarlMayerLMat) {
                    const totalValor = document.getElementById('lmat-total-pasadas-valor');
                    if (totalValor) totalValor.textContent = String(total);
                    totalCell.classList.toggle('text-green-700', totalPasadasValidoLMat);
                    totalCell.classList.toggle('bg-green-50', totalPasadasValidoLMat);
                    totalCell.classList.toggle('text-red-700', !totalPasadasValidoLMat);
                    totalCell.classList.toggle('bg-red-50', !totalPasadasValidoLMat);
                }
                if (onPorcentajeActualizadoLMat) onPorcentajeActualizadoLMat();
                return total;
            };
            actualizarTotalPasadasLMat();

            const obtenerTotalPorcentajeLMat = () => {
                const totalCell = document.getElementById('lmat-total-porcentaje');
                const texto = (totalCell?.textContent || '0').replace('%', '').trim();
                const n = Number(texto);
                return Number.isFinite(n) ? Number(n.toFixed(2)) : 0;
            };

            const obtenerCantidadRawLMat = (input: HTMLInputElement | null | undefined): number => {
                const raw = Number(String(input?.dataset?.cantidadRaw ?? '').replace(',', '.'));
                if (Number.isFinite(raw) && raw >= 0) return raw;
                const visible = Number(String(input?.value ?? '').replace(',', '.'));
                return Number.isFinite(visible) && visible >= 0 ? visible : 0;
            };

            const cantidadesReferenciaPasadasLMat: Record<string, number> = {};
            const capturarReferenciaPasadasLMat = () => {
                ['rizo', 'pie', 'trama', 'c1', 'c2', 'c3', 'c4', 'c5'].forEach((rol) => {
                    const input = raizLMat.querySelector<HTMLInputElement>(
                        `tr[data-rol="${rol}"] .lmat-cantidad-input`,
                    );
                    if (input) cantidadesReferenciaPasadasLMat[rol] = obtenerCantidadRawLMat(input);
                });
                Object.assign(inputsReferenciaLMat, inputsCalculoLMat, {
                    pasadasComb: [...inputsCalculoLMat.pasadasComb],
                    hiloComb: [...inputsCalculoLMat.hiloComb],
                });
            };
            capturarReferenciaPasadasLMat();

            /**
             * Limita decimales al teclear (cantidad ≤ 4, porcentaje ≤ 2).
             * No usa selectionStart/End sin null-check (Firefox en type=number).
             */
            const restringirDecimalesLMat = (input: HTMLInputElement, maxDecimales: number): void => {
                if (!input || input.dataset.lmatDecimalesBound === '1') return;
                input.dataset.lmatDecimalesBound = '1';

                const valorConDecimalesLimitados = (valor: unknown): string => {
                    const texto = String(valor ?? '').replace(',', '.');
                    if (texto === '' || texto === '.' || texto === '-') return texto;
                    const match = texto.match(/^(-?\d*)(?:\.(\d*))?/);
                    if (!match) return texto;
                    const enteros = match[1] ?? '';
                    const decimales = match[2];
                    if (decimales === undefined) return enteros;
                    return enteros + '.' + decimales.slice(0, maxDecimales);
                };

                input.addEventListener('beforeinput', (event: InputEvent) => {
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

            const asignarCantidadLMat = (input: HTMLInputElement | null | undefined, cantidad: number): void => {
                if (!input) return;
                const raw = Number(cantidad);
                input.dataset.cantidadRaw = Number.isFinite(raw) && raw > 0 ? String(raw) : '0';
                input.value = formatearCantidadLMat(raw);
                input.dataset.cantidadInicialRaw = input.dataset.cantidadRaw;
                input.dataset.cantidadInicialVisible = input.value;
            };

            const actualizarTotalPorcentajeLMat = (totalPorcentaje: number): void => {
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
                let sumaCantidades = 0;
                let porcentajeInputRizo = null as HTMLInputElement | null;
                document.querySelectorAll<HTMLInputElement>('.lmat-cantidad-input').forEach((input) => {
                    const cantidad = obtenerCantidadRawLMat(input);
                    sumaCantidades += cantidad;
                    const fila = input.closest('tr');
                    const porcentajeInput = fila?.querySelector<HTMLInputElement>('.lmat-porcentaje-input');
                    // Redondear cada fila a 2 decimales y sumar los REDONDEADOS: lo que se
                    // muestra/guarda es lo que cuadra, no el valor interno sin redondear.
                    const porcentaje = Number((totalCantidadActual > 0 ? (cantidad / totalCantidadActual) * 100 : 0).toFixed(2));
                    if (porcentajeInput) porcentajeInput.value = porcentaje.toFixed(2);
                    if (fila?.dataset?.rol === rolResidualLMat) {
                        porcentajeInputRizo = porcentajeInput ?? null;
                    } else {
                        totalPorcentajeActual += porcentaje;
                    }
                });

                if (porcentajeInputRizo) {
                    // Rizo absorbe el residuo de redondeo (igual que en cantidades): si las
                    // cantidades suman el Peso Crudo, los porcentajes cierran en 100.00 exacto.
                    if (totalCantidadActual > 0 && Math.abs(sumaCantidades - totalCantidadActual) < 1e-6) {
                        porcentajeInputRizo.value = Math.max(0, Number((100 - totalPorcentajeActual).toFixed(2))).toFixed(2);
                    }
                    totalPorcentajeActual += Number(porcentajeInputRizo.value);
                }

                actualizarTotalPorcentajeLMat(totalPorcentajeActual);
            };

            const aplicarCantidadesCalculadasLMat = () => {
                const pesoCrudoActual = Number(String(pesoCrudoInput?.value ?? '').replace(',', '.')) || 0;
                const pesos = calcularPesosComponentesLMat(pesoCrudoActual);
                const aplicar = (rol: string, pesoG: number): void => {
                    const fila = raizLMat.querySelector<HTMLTableRowElement>(`tr[data-rol="${rol}"]`);
                    if (!fila) return;
                    const vals = pesoACantidadYPorcentajeLMat(pesoG, pesos.pesoCrudoTotal);
                    const input = fila.querySelector<HTMLInputElement>('.lmat-cantidad-input');
                    const pct = fila.querySelector<HTMLInputElement>('.lmat-porcentaje-input');
                    asignarCantidadLMat(input, vals.cantidad);
                    if (pct) pct.value = formatearPorcentajeLMat(vals.porcentaje);
                };
                aplicar('rizo', pesos.rizoG);
                aplicar('pie', pesos.pieG);
                aplicar('trama', pesos.tramaG);
                pesos.combG.forEach((g, i) => aplicar('c' + (i + 1), g));
                pesos.barrasG.forEach((g, i) => aplicar('b' + (i + 1), g));
                recalcularPorcentajesLMat();
                capturarReferenciaPasadasLMat();
            };

            // Rizo = residuo real (PesoCrudo − todas las demás filas), no delta de fórmula:
            // absorbe el redondeo a 4 decimales de los Qty guardados y el total da 100% exacto.
            const ajustarRizoResidualLMat = () => {
                const inputRizo = raizLMat.querySelector<HTMLInputElement>(`tr[data-rol="${rolResidualLMat}"] .lmat-cantidad-input`);
                if (!inputRizo) return;
                const pesoCrudoActual = Number(String(pesoCrudoInput?.value ?? '').replace(',', '.')) || 0;
                let sumaOtras = 0;
                document.querySelectorAll<HTMLInputElement>('.lmat-cantidad-input').forEach((otro) => {
                    if (otro !== inputRizo) sumaOtras += obtenerCantidadRawLMat(otro);
                });
                asignarCantidadLMat(inputRizo, Math.max(0, (pesoCrudoActual / 1000) - sumaOtras));
            };

            const aplicarDiferenciaPasadasLMat = () => {
                // KM: el % de cada barra depende de las 4, así que se recalculan todas.
                if (esKarlMayerLMat) {
                    aplicarCantidadesCalculadasLMat();
                    return;
                }
                const pesoCrudoActual = Number(String(pesoCrudoInput?.value ?? '').replace(',', '.')) || 0;
                const pesosActuales = calcularPesosComponentesLMat(pesoCrudoActual);
                const pesosReferencia = calcularPesosComponentesLMat(pesoCrudoActual, inputsReferenciaLMat);
                const aplicarDiferencia = (rol: string, pesoActualG: number, pesoReferenciaG: number): void => {
                    const referencia = cantidadesReferenciaPasadasLMat[rol];
                    if (referencia === undefined) return;
                    const fila = raizLMat.querySelector<HTMLTableRowElement>(`tr[data-rol="${rol}"]`);
                    const input = fila?.querySelector<HTMLInputElement>('.lmat-cantidad-input');
                    const cantidad = Math.max(
                        0,
                        referencia + ((pesoActualG - pesoReferenciaG) / 1000),
                    );
                    asignarCantidadLMat(input, cantidad);
                };

                aplicarDiferencia('trama', pesosActuales.tramaG, pesosReferencia.tramaG);
                aplicarDiferencia('pie', pesosActuales.pieG, pesosReferencia.pieG);
                pesosActuales.combG.forEach((pesoG, index) => {
                    aplicarDiferencia(`c${index + 1}`, pesoG, pesosReferencia.combG[index] ?? 0);
                });
                ajustarRizoResidualLMat();
                recalcularPorcentajesLMat();
            };

            const recalcularCantidadesDesdePesoCrudoLMat = () => {
                // KM: las cantidades salen solo de la fórmula de barras, guardada o no.
                if (!guardadoLMat || esKarlMayerLMat) {
                    aplicarCantidadesCalculadasLMat();
                    return;
                }
                // L.Mat ya guardada: se respetan los Qty capturados y el rizo absorbe el cambio
                // de peso (como al editar una cantidad). Antes solo se recalculaba el % y el total
                // quedaba fuera de 100, bloqueando Actualizar.
                ajustarRizoResidualLMat();
                recalcularPorcentajesLMat();
            };

            // Al editar Fibra hay que mover también la clave de matriz de la fila: si no,
            // CatMatrizCalibres aprendería la equivalencia con la fibra vieja.
            const conectarInputsFibraLMat = () => {
                document.querySelectorAll<HTMLInputElement>('.lmat-fibra-input').forEach((input) => {
                    if (input.dataset.lmatConnected === '1') return;
                    input.dataset.lmatConnected = '1';
                    input.addEventListener('input', () => {
                        const fila = input.closest('tr');
                        if (!fila || !fila.dataset.matrizTipo) return;
                        fila.dataset.matrizFibraId = input.value.trim().toUpperCase();
                    });
                });
            };

            const conectarInputsPasadasLMat = () => {
                document.querySelectorAll<HTMLInputElement>('.lmat-pasadas-input').forEach((input) => {
                    if (input.dataset.lmatConnected === '1') return;
                    input.dataset.lmatConnected = '1';
                    restringirDecimalesLMat(input, 0);
                    input.addEventListener('input', () => {
                        const valor = Number(input.value || 0);
                        const pasadas = Number.isInteger(valor) && valor >= 0 ? valor : 0;
                        const campo = input.dataset.pasadasField;
                        if (campo === 'PasadasTramaFondoC1') {
                            inputsCalculoLMat.pasadasTrama = pasadas;
                        } else {
                            const match = String(campo || '').match(/^PasadasComb([1-5])$/);
                            if (match) inputsCalculoLMat.pasadasComb[Number(match[1]) - 1] = pasadas;
                            const barra = String(campo || '').match(/^PasadasBarra([1-4])$/);
                            if (barra) inputsCalculoLMat.pasadasBarra[Number(barra[1]) - 1] = pasadas;
                        }
                        aplicarDiferenciaPasadasLMat();
                        actualizarTotalPasadasLMat();
                    });
                });
            };

            const conectarInputsCalibreFormulaLMat = () => {
                document.querySelectorAll<HTMLInputElement>('.lmat-calibre-formula-input').forEach((input) => {
                    if (input.dataset.lmatConnected === '1') return;
                    input.dataset.lmatConnected = '1';
                    restringirDecimalesLMat(input, 4);
                    input.addEventListener('input', () => {
                        const calibre = numLMat(input.value);
                        const rol = input.dataset.calibreRol;
                        if (rol === 'pie') {
                            inputsCalculoLMat.hiloPie = calibre;
                        } else if (rol === 'trama') {
                            inputsCalculoLMat.hiloTrama = calibre;
                        } else {
                            const match = String(rol || '').match(/^c([1-5])$/);
                            if (match) inputsCalculoLMat.hiloComb[Number(match[1]) - 1] = calibre;
                            const barra = String(rol || '').match(/^b([1-4])$/);
                            if (barra) inputsCalculoLMat.calibreBarra[Number(barra[1]) - 1] = calibre;
                        }
                        aplicarDiferenciaPasadasLMat();
                    });
                });
            };

            const conectarInputsCantidadLMat = () => {
                document.querySelectorAll<HTMLInputElement>('.lmat-cantidad-input').forEach((input) => {
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
                        // Sin esto, vaciar o editar una cantidad dejaba el total en 99.xx%
                        // y el boton Guardar bloqueado por la regla de 100% exacto.
                        if (input.closest('tr')?.dataset?.rol !== rolResidualLMat) ajustarRizoResidualLMat();
                        recalcularPorcentajesLMat();
                    });
                    input.addEventListener('change', () => {
                        input.value = formatearCantidadLMat(obtenerCantidadRawLMat(input));
                    });
                });
            };

            const conectarInputsPorcentajeLMat = () => {
                document.querySelectorAll<HTMLInputElement>('.lmat-porcentaje-input').forEach((input) => {
                    if (input.dataset.lmatConnected === '1') return;
                    input.dataset.lmatConnected = '1';
                    restringirDecimalesLMat(input, 2);
                    input.addEventListener('input', () => {
                        const porcentaje = Number(String(input.value || '0').replace(',', '.'));
                        const porcentajeValido = Number.isFinite(porcentaje) && porcentaje >= 0 ? porcentaje : 0;
                        const totalCantidad = (Number(String(pesoCrudoInput?.value ?? '').replace(',', '.')) || 0) / 1000;
                        const cantidadInput = input.closest('tr')?.querySelector<HTMLInputElement>('.lmat-cantidad-input');
                        asignarCantidadLMat(cantidadInput, totalCantidad * (porcentajeValido / 100));

                        const totalPorcentaje = Array.from(document.querySelectorAll<HTMLInputElement>('.lmat-porcentaje-input'))
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
            largoInput?.addEventListener('input', () => {
                inputsCalculoLMat.largo = numLMat(largoInput.value);
                aplicarDiferenciaPasadasLMat();
            });
            const luchajeInput = qs<HTMLInputElement>('#lmat-luchaje');
            luchajeInput?.addEventListener('input', () => {
                inputsCalculoLMat.luchaje = numLMat(luchajeInput.value);
                aplicarDiferenciaPasadasLMat();
            });
            anchoPeineInput?.addEventListener('input', () => {
                inputsCalculoLMat.anchoPeine = numLMat(anchoPeineInput.value);
                aplicarDiferenciaPasadasLMat();
            });



            const actualizarAlmacenFilaLMat = (fila: HTMLTableRowElement | null, articulo: unknown): void => {
                const cell = fila?.querySelector('.lmat-almacen-cell');
                if (cell) cell.textContent = resolverAlmacenLMat(articulo);
            };

            // Fibra y Calibre son informativos (CatCodificados): NUNCA se sincronizan con Config.
            // Config es independiente y es lo que se guarda en CatLMat.ConfigId.

            const actualizarCalibreFilaLMat = (fila: HTMLTableRowElement | null, articulo: unknown): void => {
                const cell = fila?.querySelector('.lmat-items-cell');
                if (!cell) return;
                const rol = String(fila?.dataset?.rol || '');
                // Filas con rol CatCodificados: Calibre ya viene de Tra/CalibreRizo/CalibreCombN.
                if (rol === 'rizo' || rol === 'pie' || rol === 'trama' || rol.startsWith('c')) return;
                cell.textContent = calibreDisplayDesdeItemIdLMat(articulo);
            };

            // Nombre color = InventColor.Name del GET AX filtrado por ItemId (columna Artículos)
            // y el InventColorId elegido en columna Color.
            const actualizarNombreColorFilaLMat = (fila: HTMLTableRowElement, coloresLista: ColorAx[], inventColorId: string | null = null): void => {
                const input = fila?.querySelector<HTMLInputElement>('.lmat-nombre-color-input');
                if (!input) return;
                const colorId = inventColorId !== null && inventColorId !== undefined
                    ? inventColorId
                    : (fila.querySelector<HTMLSelectElement>('select[name="color[]"]')?.value || '');
                const nombre = LMatMateriales.nombreColorPorId(coloresLista, colorId);
                if (nombre || !String(colorId || '').trim()) input.value = nombre;
            };

            const itemIdDeFilaLMat = (fila: HTMLTableRowElement): string => (
                fila?.querySelector<HTMLSelectElement>('select[name="articulo[]"]')?.value
                || fila?.dataset?.articuloFijo
                || ''
            );

            const actualizarNombreColorPorSeleccionLMat = async (fila: HTMLTableRowElement | null): Promise<void> => {
                if (!fila) return;
                const itemId = String(itemIdDeFilaLMat(fila)).trim();
                const inventColorId = String(fila.querySelector<HTMLSelectElement>('select[name="color[]"]')?.value || '').trim();
                const solicitud = itemId + '|' + inventColorId;
                fila.dataset.nombreColorSolicitud = solicitud;

                if (!itemId || !inventColorId) {
                    actualizarNombreColorFilaLMat(fila, [], '');
                    return;
                }

                const nombre = await LMatMateriales.getNombreColor(itemId, inventColorId);
                if (fila.dataset.nombreColorSolicitud !== solicitud) return;

                const input = fila.querySelector<HTMLInputElement>('.lmat-nombre-color-input');
                if (input && nombre) input.value = nombre;
            };

            // Al elegir color, el `change` nativo lo atienden el delegado del tbody
            // (Nombre color) y conectarSelectsSalidaMatrizLMat (filas de la matriz).
            const inicializarColorBuscableLMat = (colorSelect: HTMLSelectElement): void => {
                destruirCombobox(colorSelect);
                combobox(colorSelect, {
                    placeholder: 'Escribe código o nombre',
                    permitirVacio: true,
                });
            };

            /** Rellena Color desde AX como "código — nombre"; si solo hay 1, lo selecciona. */
            const aplicarColoresAxFilaLMat = (fila: HTMLTableRowElement, coloresLista: ColorAx[], colorPreferido: string | null = null): void => {
                const colorSelect = fila?.querySelector<HTMLSelectElement>('select[name="color[]"]');
                if (!colorSelect) return;

                destruirCombobox(colorSelect);

                const ids = LMatMateriales.idsColores(coloresLista);
                let preferido = colorPreferido !== null && colorPreferido !== undefined
                    ? String(colorPreferido).trim()
                    : String(colorSelect.value || '').trim();

                // Si AX solo trae 1 color para ese ItemId, rellenarlo automáticamente.
                if (ids.length === 1) {
                    preferido = ids[0] ?? '';
                }

                setSelectOptionsLMat(colorSelect, ids, preferido);
                Array.from(colorSelect.options).forEach((option: HTMLOptionElement) => {
                    const id = String(option.value || '').trim();
                    if (!id) return;
                    const nombre = LMatMateriales.nombreColorPorId(coloresLista, id);
                    option.textContent = nombre ? `${id} — ${nombre}` : id;
                });

                const opcionesNoVacias = Array.from(colorSelect.options)
                    .map((o: HTMLOptionElement) => String(o.value || '').trim())
                    .filter(Boolean);
                if (opcionesNoVacias.length === 1) {
                    colorSelect.value = opcionesNoVacias[0] ?? '';
                }

                actualizarNombreColorFilaLMat(fila, coloresLista, colorSelect.value);
                inicializarColorBuscableLMat(colorSelect);
                actualizarNombreColorPorSeleccionLMat(fila);
            };

            const cargarMaterialesFilaLMat = (fila: HTMLTableRowElement | null, itemId: string | undefined, configPreferido: string | null = null): void => {
                if (!fila || !itemId) {
                    if (fila) actualizarNombreColorFilaLMat(fila, [], '');
                    return;
                }
                const itemIdSolicitado = String(itemId).trim();
                const configSelect = fila.querySelector<HTMLSelectElement>('select[name="config[]"]');
                const tamanoSelect = fila.querySelector<HTMLSelectElement>('select[name="tamano[]"]');
                const colorSelect = fila.querySelector<HTMLSelectElement>('select[name="color[]"]');
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
                    // Si AX no devuelve configs para este ItemId, el select queda vacío.
                    // El backend validará que Config exista; no se permite "ENTERO" genérico.
                    if (configSelect) setSelectOptionsLMat(configSelect, configsItem, configVigente);
                    if (tamanoSelect) setSelectOptionsLMat(tamanoSelect, tamanos, tamanoSelect.value);
                    // Color + Nombre color dependen del ItemId (Artículos) vía GET AX.
                    aplicarColoresAxFilaLMat(fila, colores, colorInicial);
                });
            };

            const cargarTamanoYColorLMat = (articuloSelect: HTMLSelectElement): void => {
                const fila = articuloSelect.closest('tr');
                actualizarAlmacenFilaLMat(fila, articuloSelect.value);
                actualizarCalibreFilaLMat(fila, articuloSelect.value);
                cargarMaterialesFilaLMat(fila, articuloSelect.value);
            };

            const asignarValorSelectLMat = (select: HTMLSelectElement, valor: unknown): void => {
                const normalizado = String(valor ?? '');
                if (normalizado !== '' && !Array.from(select.options).some((option: HTMLOptionElement) => option.value === normalizado)) {
                    select.add(new Option(normalizado, normalizado));
                }
                select.value = normalizado;
                refrescarCombobox(select);
            };

            const sincronizarSalidaMatrizLMat = (filaOrigen: HTMLTableRowElement | null, nombreCampo: string, valor: string): void => {
                const clave = filaOrigen?.dataset?.matrizClave || '';
                if (!clave) return;

                document.querySelectorAll<HTMLTableRowElement>('tr[data-matriz-clave]').forEach((filaDestino) => {
                    if (filaDestino === filaOrigen || filaDestino.dataset.matrizClave !== clave) return;
                    const selectDestino = filaDestino.querySelector<HTMLSelectElement>(`select[name="${nombreCampo}"]`);
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
                document.querySelectorAll<HTMLSelectElement>('select[name="articulo[]"]').forEach((sel) => {
                    if (sel.dataset.lmatConnected === '1') return;
                    sel.dataset.lmatConnected = '1';

                    const manejarCambioArticulo = () => {
                        const fila = sel.closest('tr');
                        sincronizarSalidaMatrizLMat(fila, 'articulo[]', sel.value);
                        cargarTamanoYColorLMat(sel);
                    };

                    combobox(sel, {
                        placeholder: 'Escribe para buscar artículo',
                        permitirVacio: true,
                    });
                    sel.addEventListener('change', manejarCambioArticulo);
                });
            };

            const conectarSelectsSalidaMatrizLMat = () => {
                ['config[]', 'tamano[]', 'color[]'].forEach((nombreCampo) => {
                    document.querySelectorAll<HTMLSelectElement>(`select[name="${nombreCampo}"]`).forEach((select) => {
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
                const origen = event.target instanceof Element ? event.target : null;
                const configSelect = origen?.closest<HTMLSelectElement>('select[name="config[]"], select[name="tamano[]"]');
                if (configSelect) {
                    configSelect.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
                    configSelect.title = '';
                    return;
                }
                const colorSelect = origen?.closest('select[name="color[]"]');
                if (!colorSelect) return;
                actualizarNombreColorPorSeleccionLMat(colorSelect.closest('tr'));
            });

            conectarInputsCantidadLMat();
            conectarInputsPorcentajeLMat();
            conectarInputsPasadasLMat();
            conectarInputsCalibreFormulaLMat();
            conectarInputsFibraLMat();
            conectarSelectsSalidaMatrizLMat();
            // Al editar una L.Mat ya guardada, respetar los Qty/Porcentaje tal cual están en
            // CatLMat: recalcular aquí los sobreescribe con un valor distinto por el redondeo
            // de Qty a 4 decimales, "descuadrando" el porcentaje mostrado vs. el guardado.
            if (!guardadoLMat) recalcularPorcentajesLMat();

            LMatMateriales.getCalibres().then((calibresDisponibles) => {
                document.querySelectorAll<HTMLSelectElement>('select[name="articulo[]"]').forEach((sel) => {
                    const fila = sel.closest('tr');
                    const itemsCell = fila?.querySelector<HTMLElement>('.lmat-items-cell');
                    const itemsVal = String(itemsCell?.dataset?.calibreInformativo ?? itemsCell?.textContent ?? '').trim();
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
            document.querySelectorAll<HTMLTableRowElement>('tr[data-articulo-fijo]').forEach((fila) => {
                const configSelect = fila.querySelector<HTMLSelectElement>('select[name="config[]"]');
                const configGuardado = configSelect?.value || '';
                cargarMaterialesFilaLMat(fila, fila.dataset.articuloFijo, configGuardado);
            });

            // Validación: el Nombre (BOMID / columna "L.Mat" de liberar órdenes) no debe existir ya en BOMTABLE.
            // En actualización el nombre queda bloqueado y no se revalida contra AX.
            const nombreInput = qs<HTMLInputElement>('#lmat-nombre');
            const nombreError = document.getElementById('lmat-nombre-error');
            const bannerOcupada = document.getElementById('lmat-banner-ocupada');
            const guardarBtn = qs<HTMLButtonElement>('#lmat-guardar-front');
            let lmatDuplicada = false;
            let guardandoLmat = false;
            let lmatCheckTimer: ReturnType<typeof setTimeout> | undefined;
            // Firma de las advertencias ya mostradas: un segundo clic con las mismas confirma.
            let advertenciasReconocidas = '';

            const actualizarEstadoGuardarBtn = () => {
                if (!guardarBtn) return;
                const porcentajeOk = Number(totalPorcentajeActualLMat) === 100
                    || Number(obtenerTotalPorcentajeLMat()) === 100;
                const bloqueado = (!esActualizacionLMat && lmatDuplicada)
                    || guardandoLmat
                    || !porcentajeOk
                    || !totalPasadasValidoLMat;
                guardarBtn.disabled = bloqueado;
                guardarBtn.classList.toggle('opacity-50', bloqueado);
                guardarBtn.classList.toggle('cursor-not-allowed', bloqueado);
                guardarBtn.title = !totalPasadasValidoLMat
                    ? `El total de Pasadas debe estar entre ${minimoPasadasLMat} y ${maximoPasadasLMat}`
                    : (!porcentajeOk
                        ? 'El porcentaje total debe ser exactamente 100%'
                        : (esActualizacionLMat ? 'Actualizar' : 'Guardar'));
            };
            onPorcentajeActualizadoLMat = actualizarEstadoGuardarBtn;
            actualizarEstadoGuardarBtn();

            const setGuardarLmatLoading = (isLoading: boolean): void => {
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

            const marcarLmatDuplicada = (duplicado: boolean): void => {
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
                    const json = await http.get<{ existe?: boolean }>('/planeacion/lmat/api/existe', { params: { nombre } });
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

                const totalPasadas = actualizarTotalPasadasLMat();
                if (!totalPasadasValidoLMat) {
                    showToast(
                        `El total de Pasadas es ${totalPasadas}. Debe estar entre ${minimoPasadasLMat} y ${maximoPasadasLMat}.`,
                        'warning',
                    );
                    return;
                }

                // Recolectar las filas de la tabla del modal.
                // Se conserva toda cantidad positiva; solo se omiten filas sin cantidad.
                const filasData: Record<string, unknown>[] = [];
                let omitidasSinCantidad = 0;
                const filasSinArticulo: string[] = [];
                const filasSinConfig: string[] = [];
                const filasSinTamano: string[] = [];
                const filasSinColor: string[] = [];
                const pasadasData: Record<string, number> = {};
                const pasadasInvalidas: string[] = [];
                raizLMat.querySelectorAll<HTMLTableRowElement>('tbody tr').forEach((fila, index) => {
                    const pasadasInput = fila.querySelector<HTMLInputElement>('.lmat-pasadas-input');
                    // Filas C vacías (sin pasadas capturadas) no envían el campo ni bloquean.
                    if (pasadasInput && String(pasadasInput.value).trim() !== '') {
                        const pasadas = Number(pasadasInput.value);
                        const campoPasadas = pasadasInput.dataset.pasadasField;
                        if (!Number.isInteger(pasadas) || pasadas < 0) {
                            pasadasInvalidas.push(String(fila.dataset.rol || `fila ${index + 1}`).toUpperCase());
                            pasadasInput.classList.add('border-red-500', 'ring-1', 'ring-red-500');
                        } else if (campoPasadas) {
                            pasadasInput.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
                            pasadasData[campoPasadas] = pasadas;
                        }
                    }
                    const qty = obtenerCantidadRawLMat(fila.querySelector<HTMLInputElement>('.lmat-cantidad-input'));
                    const configSelect = fila.querySelector<HTMLSelectElement>('select[name="config[]"]');
                    const tamanoSelect = fila.querySelector<HTMLSelectElement>('select[name="tamano[]"]');
                    const colorSelect = fila.querySelector<HTMLSelectElement>('select[name="color[]"]');
                    [configSelect, tamanoSelect, colorSelect].forEach((sel) => {
                        if (sel) sel.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
                    });
                    if (qty <= 0) {
                        omitidasSinCantidad += 1;
                        return;
                    }
                    const articuloVal = fila.querySelector<HTMLSelectElement>('select[name="articulo[]"]')?.value ?? fila.dataset.articuloFijo ?? '';
                    const rolLabel = String(fila.dataset.rol || `fila ${index + 1}`).toUpperCase();
                    if (!articuloVal) {
                        filasSinArticulo.push(rolLabel);
                        return;
                    }
                    const configVal = String(configSelect?.value || '').trim();
                    if (!configVal) {
                        filasSinConfig.push(rolLabel);
                        if (configSelect) configSelect.classList.add('border-red-500', 'ring-1', 'ring-red-500');
                        return;
                    }
                    const tamanoVal = String(tamanoSelect?.value || '').trim();
                    if (!tamanoVal) {
                        filasSinTamano.push(rolLabel);
                        if (tamanoSelect) tamanoSelect.classList.add('border-red-500', 'ring-1', 'ring-red-500');
                        return;
                    }
                    const colorVal = String(colorSelect?.value || '').trim();
                    if (!colorVal) {
                        filasSinColor.push(rolLabel);
                        if (colorSelect) colorSelect.classList.add('border-red-500', 'ring-1', 'ring-red-500');
                        return;
                    }
                    const almacenVal = (fila.querySelector('.lmat-almacen-cell')?.textContent || '').trim()
                        || resolverAlmacenLMat(articuloVal);
                    filasData.push({
                        itemId: articuloVal,
                        configId: configVal,
                        inventSizeId: normalizarInventSizeIdLMat(fila.querySelector<HTMLSelectElement>('select[name="tamano[]"]')?.value || ''),
                        inventColorId: fila.querySelector<HTMLSelectElement>('select[name="color[]"]')?.value || '',
                        nombreColor: fila.querySelector<HTMLInputElement>('.lmat-nombre-color-input')?.value || '',
                        inventLocationId: almacenVal,
                        qty: Number(qty.toFixed(4)),
                        porcentaje: Number((
                            parseFloat(fila.querySelector<HTMLInputElement>('.lmat-porcentaje-input')?.value || '0') || 0
                        ).toFixed(2)),
                        // KM: b1..b4 → CatLMat.Tipo 1..4 (el servidor lo ignora fuera de Karl Mayer).
                        tipo: /^b[1-4]$/.test(String(fila.dataset.rol || '')) ? Number(String(fila.dataset.rol).slice(1)) : null,
                        matrizTipo: fila.dataset.matrizTipo || null,
                        matrizCalibre: fila.dataset.matrizCalibre || null,
                        matrizFibraId: fila.dataset.matrizFibraId || null,
                        matrizCuenta: fila.dataset.matrizCuenta || null,
                    });
                });

                if (pasadasInvalidas.length > 0) {
                    showToast('Captura Pasadas enteras mayores o iguales a 0 en: ' + pasadasInvalidas.join(', ') + '.', 'error');
                    return;
                }

                const formulaData: Record<string, number> = {
                    Largo: numLMat(largoInput?.value),
                    TramaAnchoPeine: numLMat(anchoPeineInput?.value),
                };
                // KM: la fórmula de barras no los usa; se guardan solo si vienen capturados.
                if (esKarlMayerLMat) {
                    Object.keys(formulaData).forEach((campo) => {
                        if (!((formulaData[campo] ?? 0) > 0)) delete formulaData[campo];
                    });
                }
                const calibreFieldPorRol: Record<string, string> = {
                    pie: 'CalibrePie2',
                    trama: 'CalTramaFondoC1',
                    c1: 'CalibreComb12',
                    c2: 'CalibreComb22',
                    c3: 'CalibreComb32',
                    c4: 'CalibreComb42',
                    c5: 'CalibreComb52',
                    b1: 'CalibreBarra12',
                    b2: 'CalibreBarra22',
                    b3: 'CalibreBarra32',
                    b4: 'CalibreBarra42',
                };
                const formulaInvalidos: string[] = [];
                if (!esKarlMayerLMat && !((formulaData.Largo ?? 0) > 0)) formulaInvalidos.push('Largo');
                if (!esKarlMayerLMat && !((formulaData.TramaAnchoPeine ?? 0) > 0)) formulaInvalidos.push('Ancho peine');
                document.querySelectorAll<HTMLInputElement>('.lmat-calibre-formula-input').forEach((input) => {
                    const rol = String(input.dataset.calibreRol || '');
                    const campo = calibreFieldPorRol[rol];
                    const calibre = numLMat(input.value);
                    if (!campo) return;
                    if (!(calibre > 0)) {
                        // Filas C vacías (sin cantidad) no exigen calibre.
                        const qtyFila = obtenerCantidadRawLMat(input.closest('tr')?.querySelector<HTMLInputElement>('.lmat-cantidad-input'));
                        if (qtyFila <= 0) {
                            input.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
                            return;
                        }
                        formulaInvalidos.push(`Calibre ${rol.toUpperCase()}`);
                        input.classList.add('border-red-500', 'ring-1', 'ring-red-500');
                        return;
                    }
                    input.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
                    formulaData[campo] = calibre;
                });
                if (formulaInvalidos.length > 0) {
                    showToast('Captura valores mayores a 0 en: ' + formulaInvalidos.join(', ') + '.', 'error');
                    return;
                }

                // Fibras editables → CatCodificados. Solo se envían las filas con cantidad,
                // para no borrar la fibra de una combinación que quedó vacía en el modal.
                const fibrasData: Record<string, string> = {};
                document.querySelectorAll<HTMLInputElement>('.lmat-fibra-input').forEach((input) => {
                    const campo = input.dataset.fibraField;
                    const fila = input.closest('tr');
                    if (!campo || !fila) return;
                    if (obtenerCantidadRawLMat(fila.querySelector<HTMLInputElement>('.lmat-cantidad-input')) <= 0) return;
                    fibrasData[campo] = input.value.trim();
                });

                if (filasSinArticulo.length > 0) {
                    showToast('Selecciona Artículos en: ' + filasSinArticulo.join(', ') + '. No se guardó ninguna fila.', 'error');
                    return;
                }

                if (filasSinConfig.length > 0) {
                    showToast('Selecciona Config en: ' + filasSinConfig.join(', ') + '. Es obligatorio en líneas con cantidad.', 'error');
                    return;
                }

                if (filasSinTamano.length > 0) {
                    showToast('Selecciona Tamaño en: ' + filasSinTamano.join(', ') + '. Es obligatorio en líneas con cantidad.', 'error');
                    return;
                }

                if (filasSinColor.length > 0) {
                    showToast('Selecciona Color en: ' + filasSinColor.join(', ') + '. Es obligatorio en líneas con cantidad.', 'error');
                    return;
                }

                if (filasData.length === 0) {
                    showToast('No hay filas con cantidad mayor a 0 para guardar.', 'warning');
                    return;
                }

                // Advertencias no bloqueantes: el primer clic las muestra en el banner;
                // un segundo clic con las mismas advertencias confirma y guarda.
                const advertencias: string[] = [];
                const qtyPorRol: Record<string, number> = {};
                const datosSinCantidad: string[] = [];
                raizLMat.querySelectorAll<HTMLTableRowElement>('tbody tr').forEach((fila) => {
                    const rol = String(fila.dataset.rol || '');
                    const qty = obtenerCantidadRawLMat(fila.querySelector<HTMLInputElement>('.lmat-cantidad-input'));
                    if (rol) qtyPorRol[rol] = qty;
                    if (qty > 0 || !/^(trama|c[1-5])$/.test(rol)) return;
                    const articuloVal = fila.querySelector<HTMLSelectElement>('select[name="articulo[]"]')?.value || '';
                    const pasadasVal = Number(fila.querySelector<HTMLInputElement>('.lmat-pasadas-input')?.value || 0);
                    const calibreVal = numLMat(fila.querySelector<HTMLInputElement>('.lmat-calibre-formula-input')?.value);
                    if (articuloVal || pasadasVal > 0 || calibreVal > 0) datosSinCantidad.push(rol.toUpperCase());
                });
                for (let n = 2; n <= 5; n++) {
                    if (!((qtyPorRol[`c${n}`] ?? 0) > 0)) continue;
                    const vacias: string[] = [];
                    for (let m = 1; m < n; m++) {
                        if (!((qtyPorRol[`c${m}`] ?? 0) > 0)) vacias.push(`C${m}`);
                    }
                    if (vacias.length) {
                        advertencias.push(
                            `C${n} tiene cantidad pero ${vacias.join(' y ')} `
                            + `${vacias.length > 1 ? 'están vacías' : 'está vacía'}. ¿Es correcto el orden de combinaciones?`,
                        );
                        break;
                    }
                }
                // Combinaciones que tenian datos al abrir y quedaron completamente vacias:
                // se limpian en CatCodificados para que no reaparezcan al reabrir el modal.
                const combinacionesVacias: number[] = [];
                raizLMat.querySelectorAll<HTMLTableRowElement>('tbody tr[data-rol]').forEach((fila) => {
                    const indice = String(fila.dataset.rol).match(/^c([1-5])$/);
                    if (!indice) return;
                    const n = Number(indice[1]);
                    const vacia = String(fila.querySelector<HTMLInputElement>('.lmat-fibra-input')?.value ?? '').trim() === ''
                        && String(fila.querySelector<HTMLInputElement>('.lmat-pasadas-input')?.value ?? '').trim() === ''
                        && numLMat(fila.querySelector<HTMLInputElement>('.lmat-calibre-formula-input')?.value) <= 0
                        && String(fila.querySelector<HTMLSelectElement>('select[name="articulo[]"]')?.value ?? '').trim() === ''
                        && obtenerCantidadRawLMat(fila.querySelector<HTMLInputElement>('.lmat-cantidad-input')) <= 0;
                    if (vacia && combinacionTieneDatosLMat(n)) combinacionesVacias.push(n);
                });
                if (combinacionesVacias.length) {
                    advertencias.push(
                        `Se vaciará ${combinacionesVacias.map((n) => 'C' + n).join(', ')} en Codificación: `
                        + 'se borrarán fibra, calibre, pasadas y color de esa(s) combinación(es).',
                    );
                }
                if (datosSinCantidad.length) {
                    advertencias.push(`${datosSinCantidad.join(', ')} tiene(n) datos capturados pero cantidad 0: esa(s) fila(s) no se guardará(n).`);
                }
                if (qtyPorRol.rizo !== undefined && !(qtyPorRol.rizo > 0)) {
                    advertencias.push('La cantidad de RIZO quedó en 0: revisa el Peso Crudo y las demás cantidades.');
                }
                const bannerAdvertencias = document.getElementById('lmat-advertencias');
                const firmaAdvertencias = advertencias.join('|');
                if (advertencias.length && firmaAdvertencias !== advertenciasReconocidas) {
                    advertenciasReconocidas = firmaAdvertencias;
                    if (bannerAdvertencias) {
                        bannerAdvertencias.innerHTML =
                            '<p class="font-semibold"><i class="fas fa-exclamation-triangle mr-1"></i>Revisa antes de guardar:</p>'
                            + '<ul class="mt-1 list-disc space-y-0.5 pl-5">'
                            + advertencias.map((a) => `<li>${escapeHtml(a)}</li>`).join('')
                            + '</ul>'
                            + `<p class="mt-2 font-semibold">Si es correcto, presiona ${guardarBtnLabelLMat} de nuevo para confirmar.</p>`;
                        bannerAdvertencias.classList.remove('hidden');
                        bannerAdvertencias.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }
                    return;
                }
                advertenciasReconocidas = '';
                bannerAdvertencias?.classList.add('hidden');

                const luchajeTexto = String(qs<HTMLInputElement>('#lmat-luchaje')?.value ?? '').trim();
                const luchajeGuardadoLMat = luchajeTexto !== '' ? Math.trunc(Number(luchajeTexto)) : null;
                const actLmatChecked = esBomIdEstand
                    ? Boolean(qs<HTMLInputElement>('#lmat-act-lmat')?.checked)
                    : false;
                setGuardarLmatLoading(true);
                try {
                    const json = await http.post<RespuestaGuardarLMat>('/planeacion/lmat/api/guardar', {
                        orden: orden,
                        salon: salon,
                        telarId: String(telarSeleccionado || ''),
                        nombre: nombreInput?.value || '',
                        descrip: qs<HTMLInputElement>('#lmat-descripcion')?.value || '',
                        pesoCrudo: String(qs<HTMLInputElement>('#lmat-pesocrudo')?.value || ''),
                        itemIdCrudo: qs<HTMLInputElement>('#lmat-itemid')?.value || '',
                        inventSizeCrudo: qs<HTMLInputElement>('#lmat-tamano')?.value || '',
                        luchaje: luchajeGuardadoLMat,
                        // No se muestra en el modal; se copia de CatCodificados al guardar.
                        codigoDibujo: String(registroSeleccionado?.CodigoDibujo ?? '').trim() || null,
                        actualizaLmat: actLmatChecked,
                        pasadas: pasadasData,
                        formula: formulaData,
                        fibras: fibrasData,
                        combinacionesVacias,
                        filas: filasData,
                    });
                    if (json.success) {
                        const updatedBom = json.updatedBom === true;
                        const bomIdGuardado = updatedBom
                            ? (json.bomId ?? nombreInput?.value ?? '')
                            : (json.bomId ?? bomIdActualCat);
                        const bomNameGuardado = updatedBom
                            ? (json.bomName ?? qs<HTMLInputElement>('#lmat-descripcion')?.value ?? '')
                            : (json.bomName ?? String(registroSeleccionado?.BomName ?? ''));
                        const baseMsg = esActualizacionLMat
                            ? (json.message || 'L.Mat actualizada.')
                            : (json.message || 'L.Mat guardada.');
                        const msg = omitidasSinCantidad > 0
                            ? baseMsg + ' Se omitieron ' + omitidasSinCantidad + ' fila(s) sin cantidad positiva.'
                            : baseMsg;
                        showToast(msg, 'success');
                        notify.close();
                        try {
                            onSaved({
                                orden,
                                telarId: String(telarSeleccionado || ''),
                                bomId: bomIdGuardado,
                                bomName: bomNameGuardado,
                                updatedBom,
                                actualizaLmat: json.actualizaLmat,
                                pasadas: pasadasData,
                                formula: formulaData,
                                fibras: fibrasData,
                                combinacionesVacias,
                                luchaje: luchajeGuardadoLMat,
                            });
                        } catch (error) {
                            console.error('No se pudo actualizar localmente la fila de Codificación', error);
                        }
                    } else {
                        showToast(json.message || 'Error al guardar la L.Mat.', 'error');
                    }
                } catch (e) {
                    const primerError = e instanceof HttpError && e.errors
                        ? Object.values(e.errors).flat().find(Boolean)
                        : null;
                    showToast(primerError || 'Error al guardar: ' + ((e instanceof Error && e.message) || 'desconocido'), 'error');
                } finally {
                    setGuardarLmatLoading(false);
                }
            });
            document.getElementById('lmat-cerrar-front')?.addEventListener('click', () => {
                notify.close();
            });
        },
    });
}

export { openLMatModal };
