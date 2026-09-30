/** Catálogo de Telares: reglas puras (tests en tests/Js/catalogos-telares.test.ts). */
import type { Registro } from '../../../catalogos/catalog-base.ts';

/** Nombre sugerido: "JAC 200", "Smith 305" o las 3 primeras letras del salón (igual que makeName del servidor). */
export function nombreDesde(salon: unknown, telar: unknown): string {
    const up = String(salon ?? '').toUpperCase().trim();
    const pref = up.includes('JACQUARD') ? 'JAC' : up.includes('SMITH') ? 'Smith' : up.slice(0, 3).toUpperCase();

    return `${pref} ${String(telar ?? '')}`.trim();
}

/** Sin nombre, se manda el sugerido. */
export function procesarTelar(datos: Registro): Registro {
    const nombre = String(datos.Nombre ?? '').trim();

    return { ...datos, Nombre: nombre !== '' ? nombre : nombreDesde(datos.SalonTejidoId, datos.NoTelarId) };
}

export function resumenTelar(v: Registro): string {
    return `Salón: ${String(v.SalonTejidoId ?? '')} · Telar: ${String(v.NoTelarId ?? '')} · Nombre: ${String(v.Nombre ?? '')}`;
}
