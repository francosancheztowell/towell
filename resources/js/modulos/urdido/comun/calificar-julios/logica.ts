/** Lógica pura del modal Calificar Julios (Urdido y Engomado). Sin DOM: se prueba con node. */

export interface JulioCalificable {
    Id: number;
    Folio?: string | null;
    NoJulio?: string | number | null;
    Fecha?: string | null;
    Metros1?: number | string | null;
    Metros2?: number | string | null;
    Metros3?: number | string | null;
    NomEmpl1?: string | null;
    NomEmpl2?: string | null;
    NomEmpl3?: string | null;
    ClaveDefecto?: number | string | null;
}

export interface Defecto {
    Id: number | string;
    Clave: string;
    Defecto?: string | null;
    Penalizacion?: number | null;
}

/** Clave de defecto → clases Tailwind por familia (mismo mapa que la vista anterior). */
export const COLOR_DEFECTO: Readonly<Record<string, string>> = {
    RHC: 'bg-red-100 text-red-800',
    PHC: 'bg-red-100 text-red-800',
    RHS: 'bg-orange-100 text-orange-800',
    PHS: 'bg-orange-100 text-orange-800',
    RHCE: 'bg-amber-100 text-amber-900',
    PHCE: 'bg-amber-100 text-amber-900',
    N: 'bg-yellow-100 text-yellow-800',
    D: 'bg-yellow-100 text-yellow-800',
    M: 'bg-yellow-100 text-yellow-800',
    MA: 'bg-yellow-100 text-yellow-800',
    J: 'bg-lime-100 text-lime-800',
    HP: 'bg-lime-100 text-lime-800',
    HD: 'bg-lime-100 text-lime-800',
    MI: 'bg-green-100 text-green-800',
    DM: 'bg-teal-100 text-teal-800',
    TL: 'bg-teal-100 text-teal-800',
    FH: 'bg-cyan-100 text-cyan-800',
    G: 'bg-cyan-100 text-cyan-800',
    TF: 'bg-indigo-100 text-indigo-800',
    Z: 'bg-indigo-100 text-indigo-800',
    E: 'bg-indigo-100 text-indigo-800',
    DT: 'bg-purple-100 text-purple-900',
};

export const TODAS_LAS_CLASES_DEFECTO: readonly string[] = [
    ...new Set(Object.values(COLOR_DEFECTO).flatMap((c) => c.split(' '))),
];

export function clasesDefecto(clave: string | null | undefined): string[] {
    const c = clave ? COLOR_DEFECTO[clave] : undefined;
    return c ? c.split(' ') : [];
}

/** "2026-04-07T00:00:00.000000Z" | "2026-04-07 14:30:00" | "2026-04-07" → "2026-04-07". */
export function fechaCorta(valor: unknown): string {
    if (!valor) return '';
    return String(valor).replace('T', ' ').substring(0, 10);
}

/** Fecha de hoy (Y-m-d) en la zona de la app. */
export function fechaHoy(zona: string, ahora: Date = new Date()): string {
    return ahora.toLocaleDateString('en-CA', { timeZone: zona });
}

function texto(v: unknown): string {
    return v === null || v === undefined ? '' : String(v).trim();
}

/** Oficial con mayor metraje; si no hay metros, el primer nombre capturado. */
export function operadorDelJulio(j: Partial<JulioCalificable>): string {
    const slots = [
        { m: parseFloat(String(j.Metros1 ?? '')) || 0, n: texto(j.NomEmpl1) },
        { m: parseFloat(String(j.Metros2 ?? '')) || 0, n: texto(j.NomEmpl2) },
        { m: parseFloat(String(j.Metros3 ?? '')) || 0, n: texto(j.NomEmpl3) },
    ];
    const max = Math.max(...slots.map((s) => s.m));
    if (max > 0) {
        const conMax = slots.find((s) => s.m === max && s.n !== '');
        if (conMax) return conMax.n;
    }
    return slots.find((s) => s.n !== '')?.n ?? '';
}

/** ¿El defecto es el que ya tiene el julio? */
export function defectoSeleccionado(julio: Partial<JulioCalificable>, defecto: Defecto): boolean {
    if (julio.ClaveDefecto === null || julio.ClaveDefecto === undefined || julio.ClaveDefecto === '') return false;
    return parseInt(String(julio.ClaveDefecto), 10) === parseInt(String(defecto.Id), 10);
}
