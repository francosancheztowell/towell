export function queryElement<T extends Element>(
    selector: string,
    root: ParentNode = document,
): T | null {
    return root.querySelector<T>(selector);
}

export function eventElement(event: Event): HTMLElement | null {
    return event.target instanceof HTMLElement ? event.target : null;
}

export function isOpen(element: HTMLElement | null): boolean {
    return element !== null
        && !element.classList.contains('hidden')
        && element.style.display !== 'none';
}

export function errorMessage(error: unknown, fallback: string): string {
    return error instanceof Error && error.message ? error.message : fallback;
}

export function numberValue(value: unknown): number {
    const parsed = Number(value ?? 0);

    return Number.isFinite(parsed) ? parsed : 0;
}

