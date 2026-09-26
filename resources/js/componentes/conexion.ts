/**
 * Banner "sin conexión" (UX-12). Escucha `towell:conexion` ({ online }), que emite el
 * cliente de telemetría (resources/js/monitoreo/cliente.ts) cuando el latido falla o vuelve,
 * y además `online`/`offline` del navegador (la telemetría puede estar apagada con
 * MONITOREO_ENABLED=false). Estilos en app.css (.towell-conexion), bajo el navbar.
 *
 * El elemento se crea al primer corte y se vuelve a pintar tras wire:navigate (que cambia el <body>).
 */

export const ID_BANNER = 'towell-conexion';
export const MENSAJE_SIN_CONEXION = 'Sin conexión con el servidor. Lo que guardes ahora podría no llegar; revisa la red.';

// Dos fuentes: el navegador (hay red) y el latido de telemetría (el servidor responde). El
// banner se ve si cualquiera dice que no: que vuelva el Wi-Fi no quita el aviso mientras el
// servidor siga sin responder (la telemetría solo avisa cuando su estado cambia).
let navegadorEnLinea = true;
let servidorEnLinea = true;
let enLinea = true;

function banner(doc: Document): HTMLElement | null {
    const existente = doc.getElementById(ID_BANNER);
    if (existente) return existente;
    if (enLinea || !doc.body) return null;

    const el = doc.createElement('div');
    el.id = ID_BANNER;
    el.className = 'towell-conexion';
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.textContent = MENSAJE_SIN_CONEXION;
    doc.body.appendChild(el);

    return el;
}

export function pintarConexion(doc: Document = document): void {
    const el = banner(doc);
    if (!el) return;
    el.hidden = enLinea;
    // Justo debajo del navbar real (72 px en tablet; --pt-navbar-height solo existe en PT).
    const nav = doc.querySelector('nav');
    if (!enLinea && nav && typeof nav.getBoundingClientRect === 'function') {
        el.style.top = `${Math.round(nav.getBoundingClientRect().bottom)}px`;
    }
}

export function actualizarConexion(online: boolean, doc: Document = document, fuente: 'servidor' | 'navegador' = 'servidor'): void {
    if (fuente === 'navegador') navegadorEnLinea = online;
    else servidorEnLinea = online;
    enLinea = navegadorEnLinea && servidorEnLinea;
    pintarConexion(doc);
}

export function estaEnLinea(): boolean {
    return enLinea;
}

export function iniciarConexion(win: Window = window, doc: Document = document): void {
    win.addEventListener('towell:conexion', (event) => {
        const online = (event as CustomEvent<{ online?: boolean }>).detail?.online;
        if (typeof online === 'boolean') actualizarConexion(online, doc);
    });
    win.addEventListener('offline', () => actualizarConexion(false, doc, 'navegador'));
    win.addEventListener('online', () => actualizarConexion(true, doc, 'navegador'));

    if (win.navigator && win.navigator.onLine === false) actualizarConexion(false, doc, 'navegador');
}
