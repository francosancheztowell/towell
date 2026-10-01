/**
 * Notificaciones unificadas de la aplicación.
 *
 * - Toasts (success/error/warning/info): nativos, sin Toastr ni jQuery. Contenedor
 *   único con aria-live="polite", pila de máximo 4, cierre manual accesible y pausa
 *   al pasar el puntero o enfocar. Van debajo del navbar (no tapan Crear/Editar/Eliminar,
 *   HANDOFF 16 A4), en el mismo lugar que x-ui.flash, y duran lo mismo todos (UX-13). No usan el toast de SweetAlert2 porque comparte
 *   singleton con los modales y cerraba el que estuviera abierto.
 * - Modales (alert/validation/confirm/loading/close): <dialog> nativo con aspecto de Flux
 *   (utils/dialogo.ts). Ya no usan SweetAlert2.
 *
 * Uso (en scripts inline de Blade, vía window.notify):
 *   notify.success('Guardado');
 *   notify.error('Algo salió mal');
 *   if (await notify.confirm({ text: '¿Eliminar?' })) { ... }
 *   notify.validation(err.errors);   // errores 422 de Laravel
 */
import { actualizarDialogo, cerrarDialogo, dialogo, type AnchoDialogo, type ContextoValidar, type OpcionesDialogo, type TonoDialogo } from './dialogo.ts';
import { escapeHtml } from './format.ts';

/** Mismos nombres que los íconos de SweetAlert2, para no tocar a los que llaman. */
export type IconoAviso = 'success' | 'error' | 'warning' | 'info' | 'question';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

/**
 * UX-13: una sola duración para todos los toasts (antes iba de 1.3 a 6 s según el tipo y el
 * módulo). 5 s alcanza para leer una línea; pasar el puntero o enfocar el toast lo pausa, y
 * el botón × lo cierra antes. TOAST_DURATIONS se conserva para quien lo consulte por tipo.
 */
export const TOAST_DURATION = 5000;

export const TOAST_DURATIONS: Readonly<Record<ToastType, number>> = {
    success: TOAST_DURATION,
    info: TOAST_DURATION,
    warning: TOAST_DURATION,
    error: TOAST_DURATION,
};

export const MAX_TOASTS = 4;

const CONTAINER_ID = 'towell-toasts';
const STYLE_ID = 'towell-toasts-style';

const ICONS: Record<ToastType, string> = { success: '✓', info: 'i', warning: '!', error: '✕' };

const STYLES = `
.towell-toasts{position:fixed;top:calc(var(--pt-navbar-height,72px) + .75rem);right:1rem;z-index:1000000;display:flex;flex-direction:column;gap:.5rem;width:min(22rem,calc(100vw - 2rem));pointer-events:none}
.towell-toast{pointer-events:auto;display:flex;align-items:flex-start;gap:.625rem;padding:.75rem .75rem .75rem .875rem;border-radius:.5rem;border-left:4px solid;background:#fff;color:#1f2937;box-shadow:0 10px 15px -3px rgb(0 0 0/.15),0 4px 6px -4px rgb(0 0 0/.1);font:500 .875rem/1.35 system-ui,-apple-system,"Segoe UI",sans-serif;animation:towell-toast-in .18s ease-out}
.towell-toast__icon{flex:none;display:inline-flex;align-items:center;justify-content:center;width:1.25rem;height:1.25rem;border-radius:9999px;color:#fff;font-size:.75rem;font-weight:700}
.towell-toast__msg{flex:1;min-width:0;overflow-wrap:anywhere;white-space:pre-line}
.towell-toast__close{flex:none;border:0;background:none;color:#6b7280;cursor:pointer;font-size:1.125rem;line-height:1;padding:0 .125rem}
.towell-toast__close:hover,.towell-toast__close:focus-visible{color:#111827}
.towell-toast--success{border-color:#16a34a}.towell-toast--success .towell-toast__icon{background:#16a34a}
.towell-toast--info{border-color:#2563eb}.towell-toast--info .towell-toast__icon{background:#2563eb}
.towell-toast--warning{border-color:#d97706}.towell-toast--warning .towell-toast__icon{background:#d97706}
.towell-toast--error{border-color:#dc2626}.towell-toast--error .towell-toast__icon{background:#dc2626}
/* popover: el contenedor sube al top layer para no quedar bajo un <dialog> modal (utils/dialogo.ts). */
.towell-toasts[popover]{inset:auto;top:calc(var(--pt-navbar-height,72px) + .75rem);right:1rem;margin:0;border:0;padding:0;background:transparent;color:inherit;overflow:visible;height:auto}
@keyframes towell-toast-in{from{opacity:0;transform:translateY(-.5rem)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){.towell-toast{animation:none}}
`;

function container(): HTMLElement | null {
    const existing = document.getElementById(CONTAINER_ID);
    if (existing) return existing;
    if (!document.body) return null;

    if (!document.getElementById(STYLE_ID)) {
        const style = document.createElement('style');
        style.id = STYLE_ID;
        style.textContent = STYLES;
        document.head.appendChild(style);
    }

    const el = document.createElement('div');
    el.id = CONTAINER_ID;
    el.className = 'towell-toasts';
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.setAttribute('popover', 'manual');
    document.body.appendChild(el);

    return el;
}

/** Re-muestra el popover para quedar encima del último <dialog> abierto (el top layer apila por orden). */
function alFrente(root: HTMLElement): void {
    if (typeof root.showPopover !== 'function') return;
    try {
        if (root.matches(':popover-open')) root.hidePopover();
        root.showPopover();
    } catch {
        // Navegador sin popover: el z-index de siempre basta fuera de un <dialog>.
    }
}

/** Muestra un toast y devuelve su elemento (null si todavía no hay <body>). */
export function toast(type: ToastType, message: unknown): HTMLElement | null {
    const root = container();
    if (!root) return null;

    while (root.children.length >= MAX_TOASTS) root.firstElementChild?.remove();

    const el = document.createElement('div');
    el.className = `towell-toast towell-toast--${type}`;

    const icon = document.createElement('span');
    icon.className = 'towell-toast__icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = ICONS[type];

    // textContent: el mensaje puede traer datos del usuario o del servidor (sin XSS).
    const text = document.createElement('span');
    text.className = 'towell-toast__msg';
    text.textContent = message == null ? '' : String(message);

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'towell-toast__close';
    close.setAttribute('aria-label', 'Cerrar notificación');
    close.textContent = '×';

    let timer: ReturnType<typeof setTimeout> | undefined;
    const dismiss = (): void => {
        clearTimeout(timer);
        el.remove();
    };
    const start = (): void => {
        clearTimeout(timer);
        timer = setTimeout(dismiss, TOAST_DURATIONS[type]);
    };

    close.addEventListener('click', dismiss);
    el.addEventListener('mouseenter', () => clearTimeout(timer));
    el.addEventListener('mouseleave', start);
    el.addEventListener('focusin', () => clearTimeout(timer));
    el.addEventListener('focusout', start);

    el.appendChild(icon);
    el.appendChild(text);
    el.appendChild(close);
    root.appendChild(el);
    alFrente(root);
    start();

    return el;
}

export interface ConfirmOptions {
    title?: string;
    text?: string;
    html?: string | null;
    icon?: IconoAviso;
    confirmText?: string;
    cancelText?: string;
    /** Compatibilidad con el confirmButtonColor de Swal: un color rojizo pinta el botón de peligro. */
    confirmColor?: string;
}

/** ¿El hex es rojizo? (#d33, #dc2626, #ef4444…) → botón de peligro. */
export function esColorPeligro(color: string | undefined): boolean {
    const hex = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(color ?? '')?.[1];
    if (!hex) return false;
    const full = hex.length === 3 ? [...hex].map((c) => c + c).join('') : hex;
    const canal = (i: number): number => parseInt(full.slice(i, i + 2), 16);
    const [r, g, b] = [canal(0), canal(2), canal(4)];
    return r > g + 60 && r > b + 60;
}

const ACEPTAR = [{ texto: 'Aceptar', valor: 'ok' }];

export interface FormOptions<T> {
    title: string;
    /** Campos del formulario. HTML de confianza: escapar con escapeHtml los datos del usuario/servidor. */
    html: string;
    confirmText?: string;
    cancelText?: string;
    /** Botón de confirmar rojo (acciones destructivas). */
    danger?: boolean;
    width?: AnchoDialogo;
    icon?: IconoAviso;
    /**
     * Lee y valida los campos (como preConfirm de Swal). `ctx.error(msg)` o devolver false lo deja
     * abierto con el mensaje; puede ser async (los botones se deshabilitan mientras corre).
     * Lo que devuelva es el resultado de notify.form (undefined → true).
     */
    preConfirm?: (ctx: ContextoValidar) => T | false | Promise<T | false>;
    /** Ya abierto: enganchar listeners, llenar selects, enfocar (como didOpen de Swal). */
    didOpen?: (root: HTMLElement) => void;
}

export const notify = {
    // Toasts ligeros (esquina). Equivalentes al antiguo showToast(msg, tipo).
    success: (msg: unknown) => toast('success', msg),
    error: (msg: unknown) => toast('error', msg),
    warning: (msg: unknown) => toast('warning', msg),
    info: (msg: unknown) => toast('info', msg),

    // Alerta modal bloqueante (para errores que el usuario debe ver sí o sí).
    async alert(message: string, title = 'Aviso', icon: IconoAviso = 'info'): Promise<void> {
        await dialogo({ titulo: title, texto: message, tono: icon, botones: ACEPTAR });
    },

    /** Como alert, con HTML. El HTML debe venir armado por el código: escapar con escapeHtml todo dato del usuario o del servidor. */
    async html(html: string, title = 'Aviso', icon: IconoAviso = 'info'): Promise<void> {
        await dialogo({ titulo: title, html, tono: icon, botones: ACEPTAR });
    },

    // Muestra los errores de validación de Laravel (422) en una lista.
    async validation(errors: Record<string, string[] | string> | null | undefined, title = 'Revisa los datos'): Promise<void> {
        const list = Object.values(errors || {}).flat();

        await dialogo({
            titulo: title,
            tono: 'warning',
            html: list.length ? `<ul>${list.map((e) => `<li>${escapeHtml(e)}</li>`).join('')}</ul>` : null,
            texto: 'Hay errores en el formulario.',
            botones: ACEPTAR,
        });
    },

    /** Confirmación. Devuelve Promise<boolean> (true si el usuario confirma). */
    async confirm({
        title = '¿Confirmar?',
        text = '',
        html = null,
        icon = 'warning',
        confirmText = 'Sí',
        cancelText = 'Cancelar',
        confirmColor,
    }: ConfirmOptions = {}): Promise<boolean> {
        const peligro = esColorPeligro(confirmColor);
        const valor = await dialogo({
            titulo: title,
            texto: text,
            html,
            tono: (peligro && icon === 'warning' ? 'danger' : icon) as TonoDialogo,
            botones: [
                { texto: cancelText, valor: 'cancelar', variante: 'secundario' },
                { texto: confirmText, valor: 'confirmar', variante: peligro ? 'peligro' : 'primario' },
            ],
        });

        return valor === 'confirmar';
    },

    // Loader modal bloqueante (p. ej. mientras se procesa una petición). Se cierra con notify.close().
    async loading(title = 'Cargando...'): Promise<void> {
        await dialogo({ titulo: title, tono: 'loading', cerrable: false });
    },

    close(): void {
        cerrarDialogo();
    },

    /** Cambia el título/texto del diálogo abierto (progreso de un loading). */
    update(cambios: { title?: string; text?: string; html?: string }): void {
        actualizarDialogo({ titulo: cambios.title, texto: cambios.text, html: cambios.html });
    },

    /**
     * Formulario en diálogo (reemplaza los modales de SweetAlert2 con inputs + preConfirm).
     * Devuelve lo que devolvió preConfirm, o null si se canceló.
     */
    async form<T = true>({
        title,
        html,
        confirmText = 'Aceptar',
        cancelText = 'Cancelar',
        danger = false,
        width = 'lg',
        icon,
        preConfirm,
        didOpen,
    }: FormOptions<T>): Promise<T | null> {
        let datos: unknown = true;
        const valor = await dialogo({
            titulo: title,
            html,
            tono: icon ?? null,
            formulario: true,
            ancho: width,
            botones: [
                { texto: cancelText, valor: 'cancelar', variante: 'secundario' },
                { texto: confirmText, valor: 'confirmar', variante: danger ? 'peligro' : 'primario', valida: true },
            ],
            validar: preConfirm ? (ctx) => preConfirm(ctx) : undefined,
            alValidar: (d) => (datos = d === undefined ? true : d),
            alAbrir: didOpen,
        });

        return valor === 'confirmar' ? (datos as T) : null;
    },

    /** Diálogo con botones libres (p. ej. tres opciones). Devuelve el `valor` del botón o null. */
    dialog(opciones: OpcionesDialogo): Promise<string | null> {
        return dialogo(opciones);
    },
};

export type Notify = typeof notify;

/**
 * Shim de compatibilidad para el `showToast(message, type)` histórico (firma estándar).
 * Tipo desconocido → info. NOTA: existen variantes locales con OTRA firma —`(icon, title)`
 * en engomado/urdido y `(options)` en cortes-eficiencia— que se auto-sombrean en su scope
 * y NO deben tocarse.
 */
export function showToast(message: unknown, type: string = 'success'): void {
    const fn = type in TOAST_DURATIONS ? notify[type as ToastType] : notify.info;
    fn(message);
}

export default notify;
