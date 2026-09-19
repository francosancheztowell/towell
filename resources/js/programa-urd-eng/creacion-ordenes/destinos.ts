/**
 * Destino (salon de tejido) de una orden.
 *
 * Un grupo de un solo telar deduce su destino del numero de telar; un grupo de
 * varios obliga a elegirlo a mano, porque pueden venir de salones distintos.
 */

import { estaVacio } from './formato.ts'
import type { Grupo } from './types.ts'

export const DESTINOS_POR_DEFECTO = ['Itema Nuevo', 'Itema Viejo', 'Jacquard Sulzer', 'Jacquard Smit', 'Smit']

/** El servidor manda la lista; si no llega, se usa la fija. */
export const opcionesDestino = (deConfig?: string[]): string[] =>
    Array.isArray(deConfig) && deConfig.length ? deConfig : DESTINOS_POR_DEFECTO

/** Devuelve '' si el valor no es un destino reconocido. */
export const normalizarDestino = (destino: unknown): string => {
    const valor = String(destino ?? '').trim()
    if (!valor) return ''

    const n = valor.toUpperCase().replace(/\s+/g, ' ')

    if (n === 'ITEMA NUEVO') return 'Itema Nuevo'
    if (n === 'ITEMA VIEJO') return 'Itema Viejo'
    if (n === 'JACQUARD SULZER' || n === 'SULZER') return 'Jacquard Sulzer'
    if (n === 'JACQUARD SMIT' || n === 'JACQUARD' || n === 'JAC') return 'Jacquard Smit'
    if (n === 'SMIT' || n === 'SMITH') return 'Smit'

    return ''
}

export const requiereDestinoManual = (grupo: Pick<Grupo, 'telares'> | null | undefined): boolean =>
    Array.isArray(grupo?.telares) && grupo.telares.length > 1

/** Los rangos de telar por salon los fija planta; no estan en base. */
export const destinoPorTelar = (noTelar: unknown): string => {
    const n = parseInt(String(noTelar), 10)
    if (!n) return ''

    if (n >= 207 && n <= 211) return 'Jacquard Sulzer'
    if ([201, 202, 203, 204, 205, 206, 213, 214, 215].includes(n)) return 'Jacquard Smit'
    if (n >= 305 && n <= 316) return 'Smit'
    if ([303, 304, 317, 318].includes(n)) return 'Itema Viejo'
    if ([299, 300, 301, 302, 319, 320].includes(n)) return 'Itema Nuevo'

    return ''
}

export const destinoInicial = (grupo: Grupo | null | undefined): string => {
    if (requiereDestinoManual(grupo)) return ''

    const explicito = normalizarDestino(grupo?.destino)
    if (explicito) return explicito

    if (Array.isArray(grupo?.telares) && grupo.telares.length === 1) {
        return destinoPorTelar(grupo.telares[0]?.no_telar)
    }

    return ''
}

/** Marca en ambar el select mientras no tenga destino. */
export const marcarDestinoPendiente = (select: HTMLSelectElement | null): void => {
    if (!select) return

    const tieneValor = !estaVacio(select.value)
    select.classList.toggle('border-amber-400', !tieneValor)
    select.classList.toggle('bg-amber-50', !tieneValor)
}
