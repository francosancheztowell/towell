/**
 * Lógica pura del modal de fechas de los reportes de Tejido (19-02): rango (inv-telas,
 * promedio paros, marcas finales) y semana (RPM semanal). Sin DOM: la prueba
 * tests/Js/tejido-reportes.test.mjs.
 */

export type CampoRango = 'fecha_ini' | 'fecha_fin' | 'semana';

export type ResultadoRango = { ok: true } | { ok: false; campo: CampoRango; mensaje: string };

export const MENSAJE_FALTAN = 'Seleccione fecha inicial y final';
export const MENSAJE_ORDEN = 'La fecha inicial no puede ser mayor que la final';
export const MENSAJE_SEMANA = 'Seleccione una fecha';

export function mensajeMaximo(maxDias: number): string {
    return `El rango debe ser de máximo ${maxDias} días`;
}

const ISO = /^(\d{4})-(\d{2})-(\d{2})$/;
const DIA_MS = 86_400_000;

/** 'YYYY-MM-DD' → milisegundos UTC de ese día (null si no es fecha válida). */
function aUtc(iso: string): number | null {
    const m = ISO.exec(iso.trim());
    if (!m) return null;
    const [a, me, d] = [Number(m[1]), Number(m[2]), Number(m[3])];
    const t = Date.UTC(a, me - 1, d);
    const f = new Date(t);
    if (f.getUTCFullYear() !== a || f.getUTCMonth() !== me - 1 || f.getUTCDate() !== d) return null;
    return t;
}

function aIso(t: number): string {
    return new Date(t).toISOString().slice(0, 10);
}

/** Días del rango contando ambos extremos (2026-01-01..2026-01-05 = 5). null si alguna fecha no es válida. */
export function diasEnRango(fechaIni: string, fechaFin: string): number | null {
    const a = aUtc(fechaIni);
    const b = aUtc(fechaFin);
    if (a === null || b === null) return null;
    return Math.round((b - a) / DIA_MS) + 1;
}

/**
 * Mismas reglas que el preConfirm del Swal de antes: ambas fechas, en orden y, si el reporte
 * lo pide (inv-telas), un máximo de días.
 */
export function validarRango(fechaIni: string, fechaFin: string, maxDias: number | null = null): ResultadoRango {
    const fi = fechaIni.trim();
    const ff = fechaFin.trim();
    if (!fi) return { ok: false, campo: 'fecha_ini', mensaje: MENSAJE_FALTAN };
    if (!ff) return { ok: false, campo: 'fecha_fin', mensaje: MENSAJE_FALTAN };
    if (fi > ff) return { ok: false, campo: 'fecha_fin', mensaje: MENSAJE_ORDEN };
    if (maxDias !== null) {
        const dias = diasEnRango(fi, ff);
        if (dias !== null && dias > maxDias) return { ok: false, campo: 'fecha_fin', mensaje: mensajeMaximo(maxDias) };
    }
    return { ok: true };
}

export function validarSemana(fecha: string): ResultadoRango {
    return fecha.trim() ? { ok: true } : { ok: false, campo: 'semana', mensaje: MENSAJE_SEMANA };
}

/** Lunes y domingo de la semana de `fecha` (como Carbon::startOfWeek(MONDAY)/endOfWeek(SUNDAY)). */
export function semanaLunesDomingo(fecha: string): { lunes: string; domingo: string } | null {
    const t = aUtc(fecha);
    if (t === null) return null;
    const dow = new Date(t).getUTCDay(); // 0 = domingo
    const desdeLunes = (dow + 6) % 7;
    const lunes = t - desdeLunes * DIA_MS;
    return { lunes: aIso(lunes), domingo: aIso(lunes + 6 * DIA_MS) };
}

const DIAS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

/** "Semana: lun 28 sep al dom 4 oct 2026" (mismo formato que el encabezado del reporte). */
export function textoSemana(fecha: string): string {
    const s = semanaLunesDomingo(fecha);
    if (!s) return '';
    const corto = (iso: string, conAnio: boolean): string => {
        const d = new Date(`${iso}T00:00:00Z`);
        const base = `${DIAS[d.getUTCDay()]} ${d.getUTCDate()} ${MESES[d.getUTCMonth()]}`;
        return conAnio ? `${base} ${d.getUTCFullYear()}` : base;
    };
    return `Semana: ${corto(s.lunes, false)} al ${corto(s.domingo, true)}`;
}

/** URL de la consulta: ruta?param=valor… (sin parámetros vacíos). */
export function urlConsulta(ruta: string, params: Record<string, string>): string {
    const q = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) if (v.trim()) q.set(k, v.trim());
    return `${ruta}${ruta.includes('?') ? '&' : '?'}${q.toString()}`;
}
