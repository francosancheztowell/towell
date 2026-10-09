export function queryElement<T extends Element>(
    selector: string,
    root: ParentNode = document,
): T | null {
    return root.querySelector<T>(selector);
}

/**
 * Elemento HTML que recibió el evento. Un toque sobre el icono de un botón Flux
 * llega desde el <svg>/<path>: se sube al elemento HTML que lo contiene.
 */
export function eventElement(event: Event): HTMLElement | null {
    const target = event.target instanceof Element ? event.target : null;

    return target instanceof HTMLElement ? target : (target?.closest('svg')?.parentElement ?? null);
}

export function errorMessage(error: unknown, fallback: string): string {
    return error instanceof Error && error.message ? error.message : fallback;
}

export function numberValue(value: unknown): number {
    const parsed = Number(value ?? 0);

    return Number.isFinite(parsed) ? parsed : 0;
}

