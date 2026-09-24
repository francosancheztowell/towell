/**
 * Atajos de DOM tipados. Reemplazan document.querySelector repetido y los
 * listeners por elemento (que se pierden al re-renderizar filas).
 */

type Root = ParentNode;

/** Primer elemento que coincide con `selector`, o null. */
export function qs<T extends Element = HTMLElement>(selector: string, root: Root = document): T | null {
    return root.querySelector<T>(selector);
}

/** Todos los elementos que coinciden, como array (con map/filter). */
export function qsa<T extends Element = HTMLElement>(selector: string, root: Root = document): T[] {
    return Array.from(root.querySelectorAll<T>(selector));
}

/**
 * Un solo listener en `root` para todos los descendientes que coincidan con
 * `selector`, presentes o futuros. `handler` recibe el elemento que coincidió.
 * Devuelve la función que quita el listener.
 */
export function delegate<T extends Element = HTMLElement, E extends Event = Event>(
    root: Element | Document,
    type: string,
    selector: string,
    handler: (event: E, target: T) => void,
    options?: AddEventListenerOptions,
): () => void {
    const listener = (event: Event): void => {
        const origin = event.target;
        if (!origin || typeof (origin as Element).closest !== 'function') return;

        const target = (origin as Element).closest<T>(selector);
        if (target && root.contains(target)) handler(event as E, target);
    };

    root.addEventListener(type, listener, options);

    return () => root.removeEventListener(type, listener, options);
}

/** Ejecuta `fn` cuando el DOM está listo (de inmediato si ya lo está). */
export function onReady(fn: () => void): void {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
        fn();
    }
}
