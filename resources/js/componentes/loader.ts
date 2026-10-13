/**
 * Loader global único (DS-07): #globalLoader de components/layout/global-loader.blade.php.
 * app-core.js lo usa al navegar (HANDOFF 16 A3) y las vistas en vez de otro overlay propio: `window.loader.show()` / `window.loader.hide()`.
 */

const ID = 'globalLoader';
/** La pone App\Http\Middleware\MarcarDescarga en cada respuesta de descarga (Excel, PDF…). */
const COOKIE_DESCARGA = 'towell_descarga';
let temporizador: ReturnType<typeof setTimeout> | undefined;
let vigilancia: ReturnType<typeof setInterval> | undefined;

function elemento(): HTMLElement | null {
    return document.getElementById(ID);
}

function cookieDescarga(): string {
    // ?? '': sin navegador (tests en node) no hay cookies.
    return (document.cookie ?? '').split('; ').find((c) => c.startsWith(`${COOKIE_DESCARGA}=`)) ?? '';
}

/**
 * Una descarga no cambia de página: ni `pageshow` ni la navegación quitan el loader. Mientras está
 * visible, si la cookie de descarga cambia, el archivo ya llegó y el loader se oculta.
 */
function vigilarDescarga(): void {
    clearInterval(vigilancia);
    const antes = cookieDescarga();
    vigilancia = setInterval(() => {
        if (cookieDescarga() !== antes) hide();
    }, 400);
}

/** @param retrasoMs espera antes de mostrarlo (evita el parpadeo en operaciones rápidas) */
export function show(retrasoMs = 0): void {
    clearTimeout(temporizador);
    vigilarDescarga();
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
    clearInterval(vigilancia);
    const el = elemento();
    if (!el) return;
    el.classList.add('hidden');
    el.removeAttribute('aria-busy');
}

export const loader = { show, hide };
