/** Catálogo de Telares: reglas puras. El nombre ("JAC 201") lo calcula el servidor. */
import type { Registro } from '../../../catalogos/catalog-base.ts';

export function resumenTelar(v: Registro): string {
    return `Salón: ${String(v.SalonTejidoId ?? '')} · Telar: ${String(v.NoTelarId ?? '')}`;
}
