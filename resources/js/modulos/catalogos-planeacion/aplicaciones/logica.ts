/** Catálogo de Aplicaciones: reglas puras. */
import type { Registro } from '../../../catalogos/catalog-base.ts';

/** Factor vacío no se manda (como antes: el servidor lo guarda como null). */
export function procesarAplicacion(datos: Registro): Registro {
    const { Factor, ...resto } = datos;

    return Factor === undefined || String(Factor ?? '').trim() === '' ? resto : { ...resto, Factor };
}

export function resumenAplicacion(v: Registro): string {
    return `Clave: ${String(v.AplicacionId ?? '')} · Nombre: ${String(v.Nombre ?? '')}`;
}
