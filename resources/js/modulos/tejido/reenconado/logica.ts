/**
 * Producción Reenconado Cabezuela (19-02): lógica pura (sin DOM), probada en
 * tests/Js/tejido-reenconado.test.mjs.
 */

/** Registro tal como viaja al servidor ({ record }) y vuelve en data. */
export interface Registro {
    Folio: string | null;
    Date: string | null;
    Turno: string | number | null;
    numero_empleado: string | null;
    nombreEmpl: string | null;
    Calibre: string | null;
    FibraTrama: string | null;
    CodColor: string | null;
    Color: string | null;
    Cantidad: string | number | null;
    Cabezuela: string | number | null;
    Conos: string | number | null;
    Horas: string | number | null;
    Eficiencia: string | number | null;
    Obs: string | null;
}

export type CampoRegistro = keyof Registro;

/** Orden de las columnas de la tabla (y de los inputs f_<campo> del modal). */
export const CAMPOS: readonly CampoRegistro[] = [
    'Folio', 'Date', 'Turno', 'nombreEmpl', 'Calibre', 'FibraTrama', 'CodColor', 'Color',
    'Cantidad', 'Cabezuela', 'Conos', 'Horas', 'Eficiencia', 'Obs',
];

/** Campo del registro → clave de dataset de la fila (data-folio, data-numero-empleado…). */
export const DATASET: Readonly<Record<CampoRegistro, string>> = {
    Folio: 'folio',
    Date: 'date',
    Turno: 'turno',
    numero_empleado: 'numeroEmpleado',
    nombreEmpl: 'nombreempl',
    Calibre: 'calibre',
    FibraTrama: 'fibratrama',
    CodColor: 'codcolor',
    Color: 'color',
    Cantidad: 'cantidad',
    Cabezuela: 'cabezuela',
    Conos: 'conos',
    Horas: 'horas',
    Eficiencia: 'eficiencia',
    Obs: 'obs',
};

/** Campos numéricos que la tabla muestra con 2 decimales. */
const DECIMALES: ReadonlySet<CampoRegistro> = new Set(['Cantidad', 'Cabezuela', 'Horas', 'Eficiencia']);

/** Número con d decimales; vacío si no hay valor (como el formatNumber local de antes). */
export function fijo(v: unknown, d = 2): string {
    if (v === null || v === undefined || v === '') return '';
    const n = Number(v);
    return Number.isFinite(n) ? n.toFixed(d) : '';
}

/** Texto de una celda: decimales con 2 cifras, el resto tal cual. */
export function textoCelda(campo: CampoRegistro, v: unknown): string {
    if (DECIMALES.has(campo)) return fijo(v);
    return v === null || v === undefined ? '' : String(v);
}

/** data-* de una fila a partir del registro (decimales con 2 cifras, sin separador de miles). */
export function datasetDeRegistro(r: Partial<Registro>): Record<string, string> {
    const out: Record<string, string> = {};
    for (const campo of Object.keys(DATASET) as CampoRegistro[]) out[DATASET[campo]] = textoCelda(campo, r[campo]);
    return out;
}

/** Obligatorios del alta/edición, con el mensaje de antes. */
const REQUERIDOS: ReadonlyArray<[CampoRegistro, string]> = [
    ['Date', 'La fecha es requerida'],
    ['Turno', 'El turno es requerido'],
    ['numero_empleado', 'El número de empleado es requerido'],
    ['nombreEmpl', 'El nombre es requerido'],
    ['Calibre', 'El calibre es requerido'],
    ['FibraTrama', 'La fibra es requerida'],
    ['CodColor', 'El código de color es requerido'],
    ['Color', 'El color es requerido'],
    ['Cantidad', 'La cantidad es requerida'],
    ['Conos', 'Los conos son requeridos'],
    ['Horas', 'Las horas son requeridas'],
    ['Eficiencia', 'La eficiencia es requerida'],
];

/** Primer mensaje de campo obligatorio vacío, o null si el registro está completo. */
export function validar(r: Partial<Registro>): string | null {
    for (const [campo, mensaje] of REQUERIDOS) {
        const v = r[campo];
        if (!v && v !== 0) return mensaje;
    }
    return null;
}

/** Capacidad por hora de la reenconadora (kg/h); el servidor usa el mismo factor. */
export const KG_POR_HORA = 9.3;

/**
 * Eficiencia que guardará el servidor: Cantidad / (Horas × 9.3), a 2 decimales.
 * Antes el modal mostraba (Cantidad / Horas) × 9.3 y al guardar la fila salía con otro número.
 */
export function eficiencia(cantidad: unknown, horas: unknown): string {
    const c = parseFloat(String(cantidad ?? '')) || 0;
    const h = parseFloat(String(horas ?? '')) || 0;
    if (c <= 0 || h <= 0) return '';
    // Mismo redondeo que el controller: capacidad = round(h × 9.3, 2); eficiencia = round(c / capacidad, 2).
    const capacidad = redondear2(h * KG_POR_HORA);
    return redondear2(c / capacidad).toFixed(2);
}

const redondear2 = (n: number): number => Math.round(n * 100) / 100;

/** Turno por hora local (respaldo si falla generar-folio): 6:30–14:30 = 1, 14:30–22:30 = 2, resto 3. */
export function turnoPorMinuto(minutoDelDia: number): 1 | 2 | 3 {
    if (minutoDelDia >= 390 && minutoDelDia < 870) return 1;
    if (minutoDelDia >= 870 && minutoDelDia < 1350) return 2;
    return 3;
}

export interface Opcion {
    value: string;
    label: string;
    name?: string;
}

/** Respuestas de calibres/fibras/colores ({ success, data: [...] }) → opciones del select. */
export function opcionesCalibres(data: ReadonlyArray<{ ItemId?: unknown }> | undefined): string[] {
    return (data ?? []).map((i) => (i?.ItemId == null ? '' : String(i.ItemId))).filter(Boolean);
}

export function opcionesFibras(data: ReadonlyArray<{ ConfigId?: unknown }> | undefined): string[] {
    return (data ?? []).map((i) => (i?.ConfigId == null ? '' : String(i.ConfigId))).filter(Boolean);
}

export function opcionesColores(data: ReadonlyArray<{ InventColorId?: unknown; Name?: unknown }> | undefined): Opcion[] {
    return (data ?? [])
        .map((c) => {
            const value = c?.InventColorId == null ? '' : String(c.InventColorId);
            const name = c?.Name == null ? '' : String(c.Name);
            return { value, label: `${value} - ${name}`, name };
        })
        .filter((c) => c.value);
}

export interface Filtro {
    operador: string;
    calibre: string;
}

/** ¿La fila pasa el filtro? Comparación exacta (sin espacios alrededor), vacío = todos. */
export function pasaFiltro(fila: { operador?: string | undefined; calibre?: string | undefined }, filtro: Filtro): boolean {
    if (filtro.operador && (fila.operador ?? '').trim() !== filtro.operador) return false;
    if (filtro.calibre && (fila.calibre ?? '').trim() !== filtro.calibre) return false;
    return true;
}

/** Valores únicos no vacíos, ordenados como antes (Array.sort por defecto). */
export function unicos(valores: ReadonlyArray<string | undefined>): string[] {
    return Array.from(new Set(valores.filter((v): v is string => !!v))).sort();
}
