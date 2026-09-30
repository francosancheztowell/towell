/**
 * Programación Karl Mayer: lógica pura (sin DOM). La prueban tests/Js/programa-urd-eng-karl-mayer.test.mjs.
 */
import {
    aNumero,
    claveMaterial,
    materialAPayload,
    type MaterialInventario,
    type MaterialPayload,
} from '../comun/inventario-materiales.ts';
import { formatNumber } from '../../../utils/format.ts';

/** Lo mínimo de FormData que usa la pantalla (en tests se pasa un FormData real). */
export interface LectorCampos {
    get(nombre: string): FormDataEntryValue | null;
    getAll(nombre: string): FormDataEntryValue[];
}

export interface PayloadKarlMayer {
    no_telar: FormDataEntryValue | null;
    barras: FormDataEntryValue | null;
    fibra: FormDataEntryValue | null;
    tamano: FormDataEntryValue | null;
    cuenta: FormDataEntryValue | null;
    calibre: FormDataEntryValue | null;
    metros: FormDataEntryValue | null;
    fecha_programada: FormDataEntryValue | null;
    tipo_atado: FormDataEntryValue | null;
    bom_id: FormDataEntryValue | null;
    lote_proveedor: FormDataEntryValue | null;
    observaciones: FormDataEntryValue | null;
    julios: FormDataEntryValue[];
    hilos: FormDataEntryValue[];
    obs: FormDataEntryValue[];
    materiales: MaterialPayload[];
    fechaRequerimiento: string;
}

export const MAX_OPCIONES_TAMANO = 60;
export const MAX_OPCIONES_BOM = 15;
export const MIN_CARACTERES_BOM = 2;

function texto(v: unknown): string {
    return String(v ?? '').trim();
}

/** Kilos con 2 decimales en es-MX ("1,234.50"). */
export function formatKilos(valor: unknown): string {
    const n = parseFloat(String(valor));
    return formatNumber(Number.isFinite(n) ? n : 0, 2);
}

/**
 * Fecha de producción a dd/mm/aaaa (acepta ISO, dd/mm/aaaa o algo que Date entienda).
 * No usa utils/format.formatDate a propósito: el JS viejo tomaba la parte de fecha del ISO
 * tal cual ('…T00:00:00Z' → ese día), y formatDate lo pasaría a hora de CDMX (día anterior).
 */
export function formatFecha(valor: unknown): string {
    const raw = texto(valor);
    if (!raw) return '';
    const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (iso) return `${iso[3]}/${iso[2]}/${iso[1]}`;
    if (/^(\d{2})\/(\d{2})\/(\d{4})$/.test(raw)) return raw;
    const fecha = new Date(raw);
    if (!Number.isNaN(fecha.getTime())) {
        return fecha.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }
    return raw;
}

/** Tamaño "2960-12/1" → Cuenta 2960, Calibre 12; otro formato → Cuenta = tamaño, Calibre vacío. */
export function cuentaYCalibre(tamano: unknown): { cuenta: string; calibre: string } {
    const t = texto(tamano);
    if (!t) return { cuenta: '', calibre: '' };
    const m = t.match(/^([^-]+)-([^/]+)\/1$/);
    return m ? { cuenta: (m[1] ?? '').trim(), calibre: (m[2] ?? '').trim() } : { cuenta: t, calibre: '' };
}

/** Opciones de tamaño que contienen el término (sin distinguir mayúsculas), máx. 60. */
export function filtrarTamanos(opciones: string[], termino: unknown, max = MAX_OPCIONES_TAMANO): string[] {
    const t = texto(termino).toLowerCase();
    const lista = t ? opciones.filter((o) => o.toLowerCase().includes(t)) : opciones;
    return lista.slice(0, max);
}

/** Tamaño escrito a mano que no está en el catálogo (vacío = válido: lo exige `required`). */
export function tamanoInvalido(valor: unknown, opciones: string[]): boolean {
    const v = texto(valor);
    return v !== '' && !opciones.includes(v);
}

/** Respuesta {success, data:[{campo}]} → lista de valores no vacíos. */
export function listaDeCatalogo(respuesta: unknown, campo: string): string[] {
    const r = respuesta as { success?: boolean; data?: unknown } | null;
    if (!r?.success || !Array.isArray(r.data)) return [];
    return (r.data as Record<string, unknown>[]).map((i) => String(i?.[campo] || '')).filter(Boolean);
}

/** Resultados de buscar-bom-urdido → opciones del datalist (máx. 15, sin BOM vacío). */
export function opcionesBom(respuesta: unknown): { valor: string; nombre: string }[] {
    const r = respuesta as { data?: unknown } | unknown[] | null;
    const filas = (Array.isArray(r) ? r : ((r as { data?: unknown } | null)?.data ?? [])) as Record<string, unknown>[];
    if (!Array.isArray(filas)) return [];
    return filas
        .slice(0, MAX_OPCIONES_BOM)
        .map((i) => ({ valor: String(i?.BOMID ?? i?.bomId ?? ''), nombre: String(i?.NAME ?? i?.name ?? '') }))
        .filter((o) => o.valor !== '');
}

/** Lo que muestra una fila de la tabla de inventario (13 celdas de texto + datos para totales). */
export function filaDetalle(m: MaterialInventario): { clave: string; celdas: string[]; conos: number; kilos: number; lote: string } {
    const kilos = aNumero(m.PhysicalInvent, 0);
    const conos = aNumero(m.TwTiras, 0);
    const celdas = [
        m.ItemId,
        m.ConfigId,
        m.InventSizeId,
        m.InventColorId,
        m.InventLocationId,
        m.InventBatchId,
        m.WMSLocationId,
        m.InventSerialId,
        m.TwClienteFlog || '-',
        m.TwCalidadFlog || '-',
        m.ProdDate ? formatFecha(m.ProdDate) : m.ProdDate || '-',
    ].map((v) => String(v ?? ''));
    celdas.push(conos.toFixed(0), formatKilos(kilos));
    return { clave: claveMaterial(m), celdas, conos, kilos, lote: texto(m.InventBatchId) };
}

/** Totales del pie y Lote Proveedor (primer lote no vacío de lo seleccionado). */
export function totalesSeleccion(filas: { conos: number; kilos: number; lote: string }[]): {
    registros: string;
    conos: string;
    kilos: string;
    lote: string;
} {
    const conos = filas.reduce((s, f) => s + (Math.trunc(f.conos) || 0), 0);
    const kilos = filas.reduce((s, f) => s + (Number.isFinite(f.kilos) ? f.kilos : 0), 0);
    const hay = filas.length > 0;
    return {
        registros: `Total: ${filas.length}`,
        conos: hay ? String(conos) : '',
        kilos: hay ? formatKilos(kilos) : '',
        lote: filas.find((f) => f.lote !== '')?.lote ?? '',
    };
}

/** Mismas reglas que habilitaban el botón "Crear Orden". */
export function formularioValido(campos: LectorCampos, materialesSeleccionados: number): boolean {
    const v = (n: string): string => texto(campos.get(n));
    if (['no_telar', 'barras', 'fibra', 'tamano', 'cuenta', 'calibre'].some((n) => !v(n))) return false;
    const metros = parseFloat(v('metros'));
    if (!Number.isFinite(metros) || metros < 0) return false;
    if (!v('fecha_programada') || !v('tipo_atado') || !v('bom_id')) return false;
    if (materialesSeleccionados === 0) return false;
    const julios = campos.getAll('julios[]');
    const hilos = campos.getAll('hilos[]');
    for (let i = 0; i < Math.max(julios.length, hilos.length); i++) {
        if (texto(julios[i]) !== '' || texto(hilos[i]) !== '') return true;
    }
    return false;
}

/** Payload de POST programa.urd.eng.crear.orden.karl.mayer (el CSRF va en el header de http). */
export function armarPayload(campos: LectorCampos, materiales: MaterialInventario[], fechaRequerimiento: string): PayloadKarlMayer {
    const g = (n: string): FormDataEntryValue | null => campos.get(n);
    return {
        no_telar: g('no_telar'),
        barras: g('barras'),
        fibra: g('fibra'),
        tamano: g('tamano'),
        cuenta: g('cuenta'),
        calibre: g('calibre'),
        metros: g('metros'),
        fecha_programada: g('fecha_programada'),
        tipo_atado: g('tipo_atado'),
        bom_id: g('bom_id'),
        lote_proveedor: g('lote_proveedor'),
        observaciones: g('observaciones'),
        julios: campos.getAll('julios[]'),
        hilos: campos.getAll('hilos[]'),
        obs: campos.getAll('obs[]'),
        materiales: materiales.map(materialAPayload).filter((m) => m.itemId || m.inventSerialId),
        fechaRequerimiento,
    };
}

