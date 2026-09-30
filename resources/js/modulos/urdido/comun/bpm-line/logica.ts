/**
 * Lógica pura del checklist BPM (BPM-Line) de Urdido y Engomado. Sin DOM: tests/Js/urdeng-bpm-line.test.mjs.
 * Valor de cada actividad: 0 = sin marcar, 1 = palomita (✓), 2 = tache (✗).
 */

export type ValorActividad = 0 | 1 | 2;

/** Normaliza lo que venga del data-valor (texto, vacío, fuera de rango) a 0/1/2. */
export function valorDe(crudo: string | number | null | undefined): ValorActividad {
    const n = Number.parseInt(String(crudo ?? ''), 10);
    return n === 1 || n === 2 ? n : 0;
}

/** Ciclo del botón: 0 → 1 → 2 → 0. */
export function siguienteValor(actual: ValorActividad): ValorActividad {
    return ((actual + 1) % 3) as ValorActividad;
}

/** Clases de color por valor (las mismas que pinta el Blade en la carga). */
export const CLASES_VALOR: Readonly<Record<ValorActividad, readonly string[]>> = {
    0: ['bg-gray-50', 'border-gray-300', 'text-gray-400'],
    1: ['bg-green-100', 'border-green-400', 'text-green-700'],
    2: ['bg-red-100', 'border-red-400', 'text-red-700'],
};

export const TODAS_LAS_CLASES_VALOR: readonly string[] = Object.values(CLASES_VALOR).flat();

export const ICONO_VALOR: Readonly<Record<ValorActividad, string>> = { 0: '○', 1: '✓', 2: '✗' };

export const ETIQUETA_VALOR: Readonly<Record<ValorActividad, string>> = {
    0: 'sin marcar',
    1: 'cumple',
    2: 'no cumple',
};

/** Actividades que faltan por marcar (✓ o ✗) antes de terminar el folio. */
export function pendientes(valores: readonly (string | number | null | undefined)[]): number {
    return valores.filter((v) => valorDe(v) === 0).length;
}

export function mensajePendientes(faltantes: number): string {
    return `Faltan ${faltantes} actividad(es) por marcar. Todas las actividades deben estar marcadas (✓ o ✗) antes de terminar.`;
}
