/**
 * Inventario de telas (19-02): lógica pura del menú "Telares" del navbar (sin DOM).
 */

/** Telar a enfocar al abrir la página: ?telar=### o #telar-###; null si no hay. */
export function telarDesdeUrl(href: string): string | null {
    const url = new URL(href);
    const porQuery = url.searchParams.get('telar');
    if (porQuery) return porQuery;
    if (url.hash.startsWith('#telar-')) return url.hash.slice('#telar-'.length) || null;
    return null;
}

/** URL con ?telar=X#telar-X, o sin ambos si el telar está vacío ("Todos los telares"). */
export function urlParaTelar(href: string, telar: string): string {
    const url = new URL(href);
    if (!telar) {
        url.searchParams.delete('telar');
        url.hash = '';
    } else {
        url.searchParams.set('telar', telar);
        url.hash = 'telar-' + telar;
    }
    return url.toString();
}

/** Aire que se deja arriba del telar al hacer scroll (px). */
export const AIRE_SUPERIOR = 50;

/** Posición de scroll para dejar el telar debajo del navbar fijo. Nunca negativa. */
export function destinoScroll(topTelar: number, topScroller: number, scrollActual: number, altoFijo: number, aire = AIRE_SUPERIOR): number {
    return Math.max(0, topTelar - topScroller + scrollActual - altoFijo - aire);
}
