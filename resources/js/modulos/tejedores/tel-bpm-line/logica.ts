/**
 * Lógica pura del checklist BPM Tejedores (actividad × telar). Sin DOM: tests/Js/tel-bpm-line.test.ts.
 * Cada celda rota vacío → OK (✓) → X (✗) → M (mantenimiento) → vacío; el valor nuevo lo decide
 * el servidor (toggle), aquí solo se pinta.
 */

export type ValorCelda = '' | 'OK' | 'X' | 'M';

export const CLASES_CELDA: Readonly<Record<ValorCelda, readonly string[]>> = {
    OK: ['bg-green-100', 'border-green-400', 'text-green-700', 'hover:bg-green-200'],
    X: ['bg-red-100', 'border-red-400', 'text-red-700', 'hover:bg-red-200'],
    M: ['bg-amber-100', 'border-amber-400', 'text-amber-700', 'hover:bg-amber-200'],
    '': ['bg-gray-50', 'border-gray-300', 'text-gray-400', 'hover:bg-gray-100'],
};
export const TODAS_LAS_CLASES_CELDA: readonly string[] = Object.values(CLASES_CELDA).flat();

/** Texto de la celda; M lleva ícono (null) en vez de texto. */
export const TEXTO_CELDA: Readonly<Record<ValorCelda, string | null>> = { OK: '✓', X: '✗', M: null, '': '○' };

export function normalizarValor(valor: unknown): ValorCelda {
    return valor === 'OK' || valor === 'X' || valor === 'M' ? valor : '';
}

export function contarIncompletas(valores: readonly string[]): number {
    return valores.filter((v) => normalizarValor(v) === '').length;
}
