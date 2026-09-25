/**
 * Loader global único (DS-07): #globalLoader de components/layout/global-loader.blade.php.
 * app-core.js lo sigue mostrando al navegar con la clase `hidden`; esto le da una API a las
 * vistas en vez de otro overlay propio: `window.loader.show()` / `window.loader.hide()`.
 */

const ID = 'globalLoader';
let temporizador: ReturnType<typeof setTimeout> | undefined;

function elemento(): HTMLElement | null {
    return document.getElementById(ID);
}

/** @param retrasoMs espera antes de mostrarlo (evita el parpadeo en operaciones rápidas) */
export function show(retrasoMs = 0): void {
    clearTimeout(temporizador);
    const mostrar = () => {
        const el = elemento();
        if (!el) return;
        el.classList.remove('hidden');
        el.setAttribute('aria-busy', 'true');
    };
    if (retrasoMs > 0) temporizador = setTimeout(mostrar, retrasoMs);
    else mostrar();
}

export function hide(): void {
    clearTimeout(temporizador);
    const el = elemento();
    if (!el) return;
    el.classList.add('hidden');
    el.removeAttribute('aria-busy');
}

export const loader = { show, hide };
