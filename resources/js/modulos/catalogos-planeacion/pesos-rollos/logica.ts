/** Pesos por Rollos: reglas puras (antes en el <script> de catalagos/pesos-rollos). */
import type { Registro } from '../../../catalogos/catalog-base.ts';

export function validarPeso(datos: Registro): string | null {
    const peso = Number(String(datos.PesoRollo ?? '').trim());

    return Number.isFinite(peso) && peso >= 0 ? null : 'El peso debe ser un número válido mayor o igual a 0';
}

export function procesarPeso(datos: Registro): Registro {
    const limpio: Registro = {};
    for (const [k, v] of Object.entries(datos)) limpio[k] = typeof v === 'string' ? v.trim() : v;

    return { ...limpio, PesoRollo: Number(limpio.PesoRollo) };
}

export function resumenPeso(v: Registro): string {
    return `Cod Artículo: ${String(v.ItemId ?? '')} · Tamaño: ${String(v.InventSizeId ?? '')}`;
}
