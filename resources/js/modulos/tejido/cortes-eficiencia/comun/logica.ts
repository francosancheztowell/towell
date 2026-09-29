/**
 * Lógica pura de Cortes de Eficiencia (19-02): límites de RPM/eficiencia, valor sugerido por
 * horario, horas, línea que se manda a store() y revisión antes de finalizar. Sin DOM; la
 * prueban tests/Js/tejido-cortes-eficiencia.test.mjs. Las reglas son las de la vista anterior.
 */

export type TipoValor = 'rpm' | 'eficiencia';
export type Horario = 1 | 2 | 3;
export const HORARIOS: readonly Horario[] = [1, 2, 3];

/** Telares con tope de 650 RPM; el resto, 500. */
const TELARES_RPM_ALTA = [401, 402];

/** Línea de TejEficienciaLine tal como la manda y la devuelve el controller. */
export interface LineaCorte {
    NoTelar: number;
    SalonTejidoId?: string | null;
    RpmStd: number | string | null;
    EficienciaStd: number | string | null;
    RpmR1: number | string | null;
    EficienciaR1: number | string | null;
    RpmR2: number | string | null;
    EficienciaR2: number | string | null;
    RpmR3: number | string | null;
    EficienciaR3: number | string | null;
    ObsR1: string | null;
    ObsR2: string | null;
    ObsR3: string | null;
    StatusOB1: number | null;
    StatusOB2: number | null;
    StatusOB3: number | null;
}

/** '85%' → 85; '' / null / texto → null. */
export function parsePct(valor: unknown): number | null {
    if (valor == null) return null;
    const n = parseFloat(String(valor).replace('%', ''));
    return Number.isFinite(n) ? n : null;
}

export function maxRpm(telar: number): number {
    return TELARES_RPM_ALTA.includes(telar) ? 650 : 500;
}

export function maxValor(tipo: TipoValor, telar: number): number {
    return tipo === 'rpm' ? maxRpm(telar) : 100;
}

/** Lo que el input acepta mientras se escribe: entero entre 0 y el tope del tipo. */
export function limitarValor(crudo: string, tipo: TipoValor, telar: number): number {
    const val = parseInt(crudo, 10) || 0;
    return Math.min(Math.max(val, 0), maxValor(tipo, telar));
}

/** Eficiencia estándar como porcentaje: 0.85 → '85%', 85 → '85%', vacío → '0%'. */
export function pctStd(valor: unknown): string {
    const n = Number(valor);
    const ef = n ? (n > 1 ? n : n * 100) : 0;
    return `${Math.round(ef)}%`;
}

/** EficienciaStd guardada en la línea: null → '' ; 84.6 → '85%'. */
export function pctGuardado(valor: unknown): string {
    return valor == null ? '' : `${parseFloat(String(valor)).toFixed(0)}%`;
}

/** Valor guardado para pintar en el input, o null si no hay (se deja lo que tenga). */
export function valorGuardado(tipo: TipoValor, valor: unknown): string | null {
    if (valor == null || valor === '') return null;
    return tipo === 'rpm' ? String(parseInt(String(valor), 10)) : parseFloat(String(valor)).toFixed(0);
}

/**
 * Valor que se sugiere al entrar a un input en 0: el del horario anterior del mismo telar
 * (el más cercano distinto de 0) o, si no hay, el estándar.
 */
export function sugerirValor(horario: number, estandar: number, previos: { 1?: number; 2?: number }): number {
    const h1 = previos[1] ?? 0;
    const h2 = previos[2] ?? 0;
    if (horario === 1) return estandar;
    if (horario === 2) return h1 || estandar;
    if (horario === 3) return h2 || h1 || estandar;
    return 0;
}

/** Estándar numérico del input STD (RPM tal cual, eficiencia desde '85%'). */
export function estandarDe(tipo: TipoValor, texto: string | null | undefined): number {
    if (!texto) return 0;
    const crudo = tipo === 'rpm' ? parseFloat(texto) : parsePct(texto);
    return Math.round(crudo || 0);
}

/** El encabezado del horario ya tiene hora ('--:--' o '--' = no). */
export function horaDeTexto(texto: string | null | undefined): string | null {
    const t = (texto ?? '').trim();
    return t && t !== '--:--' && t !== '--' ? t : null;
}

export function horaActual(fecha: Date): string {
    const dos = (n: number): string => String(n).padStart(2, '0');
    return `${dos(fecha.getHours())}:${dos(fecha.getMinutes())}`;
}

/** '07:05:00.0000000' → '07:05'. */
export function recortarHora(valor: unknown): string {
    return (String(valor).split('.')[0] ?? '').slice(0, 5);
}

/**
 * Fecha local YYYY-MM-DD. La vista anterior usaba toISOString() (UTC): de las 18:00 en adelante
 * (UTC-6) proponía el día siguiente.
 */
export function hoyLocal(fecha: Date): string {
    const dos = (n: number): string => String(n).padStart(2, '0');
    return `${fecha.getFullYear()}-${dos(fecha.getMonth() + 1)}-${dos(fecha.getDate())}`;
}

/** Con observación marcada se manda el valor aunque sea 0; si no, 0 viaja como null. */
export function valorLinea(marcado: boolean, valor: number): number | null {
    return marcado ? valor : valor || null;
}

/** Lo que se lee de una fila de la tabla para armar la línea. */
export interface FilaCaptura {
    telar: number;
    /** Texto de los inputs STD; null si el input no existe. */
    rpmStd: string | null;
    efStd: string | null;
    rpm: Record<Horario, number>;
    eficiencia: Record<Horario, number>;
    marcado: Record<Horario, boolean>;
    obs: Record<Horario, string>;
}

export function construirLinea(f: FilaCaptura): LineaCorte {
    return {
        NoTelar: f.telar,
        SalonTejidoId: null,
        RpmStd: f.rpmStd === null ? null : parseFloat(f.rpmStd) || null,
        EficienciaStd: f.efStd === null ? null : parsePct(f.efStd),
        RpmR1: valorLinea(f.marcado[1], f.rpm[1]),
        EficienciaR1: valorLinea(f.marcado[1], f.eficiencia[1]),
        RpmR2: valorLinea(f.marcado[2], f.rpm[2]),
        EficienciaR2: valorLinea(f.marcado[2], f.eficiencia[2]),
        RpmR3: valorLinea(f.marcado[3], f.rpm[3]),
        EficienciaR3: valorLinea(f.marcado[3], f.eficiencia[3]),
        ObsR1: f.obs[1] || null,
        ObsR2: f.obs[2] || null,
        ObsR3: f.obs[3] || null,
        StatusOB1: f.marcado[1] ? 1 : 0,
        StatusOB2: f.marcado[2] ? 1 : 0,
        StatusOB3: f.marcado[3] ? 1 : 0,
    };
}

export function esVacioOCero(valor: unknown): boolean {
    if (valor === null || valor === undefined) return true;
    if (typeof valor === 'string' && valor.trim() === '') return true;
    const n = Number(valor);
    if (Number.isNaN(n)) return true;
    return n <= 0;
}

export interface RevisionFinalizar {
    /** Telares con al menos un campo vacío o en cero. */
    telares: number;
    /** Total de campos vacíos o en cero. */
    campos: number;
}

const CAMPOS_REVISADOS: readonly (keyof LineaCorte)[] = ['RpmR1', 'EficienciaR1', 'RpmR2', 'EficienciaR2', 'RpmR3', 'EficienciaR3'];

/** Cuántos RPM/% EF de los 3 horarios faltan antes de finalizar. */
export function revisarLineas(lineas: readonly Partial<LineaCorte>[]): RevisionFinalizar {
    let telares = 0;
    let campos = 0;
    for (const linea of lineas) {
        const vacios = CAMPOS_REVISADOS.filter((c) => esVacioOCero(linea[c])).length;
        if (vacios > 0) {
            telares++;
            campos += vacios;
        }
    }
    return { telares, campos };
}

/** Title del checkbox de observación: 'Obs: …' recortado a 80 caracteres. */
export function tituloObservacion(texto: string | null | undefined): string {
    const limpio = String(texto ?? '').trim();
    return limpio ? `Obs: ${limpio.length > 80 ? limpio.slice(0, 80) + '…' : limpio}` : '';
}

export const MAX_OBSERVACION = 100;

/** Primer folio cuya fecha (YYYY-MM-DD) coincide; null si ninguno. */
export function folioParaFecha(filas: readonly { folio: string; fecha: string }[], fecha: string): string | null {
    return filas.find((f) => f.fecha === fecha)?.folio ?? null;
}

/** Corte como lo devuelve show() (y los alias que aceptaba la vista del supervisor). */
export interface CorteDetalle {
    folio?: string;
    fecha?: string | null;
    turno?: string | number | null;
    status?: string | null;
    usuario?: string | null;
    noEmpleado?: string | null;
    numero_empleado?: string | null;
    nombreEmpl?: string | null;
    horario_1?: string | null;
    horario_2?: string | null;
    horario_3?: string | null;
    datos_telares?: LineaCorte[];
}

export interface DatosEdicion {
    fecha: string;
    turno: string;
    empleado: string;
    nombre: string;
    status: string;
}

/** Valores iniciales del modal "Editar Registro (Supervisor)". */
export function datosEdicion(corte: CorteDetalle): DatosEdicion {
    const fecha = String(corte.fecha ?? '');
    return {
        fecha: /^\d{4}-\d{2}-\d{2}/.test(fecha) ? fecha.slice(0, 10) : '',
        turno: String(corte.turno || '1'),
        empleado: corte.numero_empleado || corte.noEmpleado || '',
        nombre: corte.nombreEmpl || corte.usuario || '',
        status: corte.status || 'En Proceso',
    };
}
