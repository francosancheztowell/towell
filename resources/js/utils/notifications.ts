/**
 * Notificaciones unificadas de la aplicación.
 *
 * - Toasts (success/error/warning/info): nativos, sin Toastr ni jQuery. Contenedor
 *   único con aria-live="polite", pila de máximo 4, cierre manual accesible y pausa
 *   al pasar el puntero o enfocar. No usan el toast de SweetAlert2 porque comparte
 *   singleton con los modales y cerraba el que estuviera abierto.
 * - Modales (alert/validation/confirm/loading/close): SweetAlert2.
 *
 * Uso (en scripts inline de Blade, vía window.notify):
 *   notify.success('Guardado');
 *   notify.error('Algo salió mal');
 *   if (await notify.confirm({ text: '¿Eliminar?' })) { ... }
 *   notify.validation(err.errors);   // errores 422 de Laravel
 */
import Swal from 'sweetalert2';
import type { SweetAlertIcon, SweetAlertResult } from 'sweetalert2';
import { escapeHtml } from './format.ts';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

export const TOAST_DURATIONS: Readonly<Record<ToastType, number>> = {
    success: 2500,
    info: 3000,
    warning: 5000,
    error: 6000,
};

export const MAX_TOASTS = 4;

const CONTAINER_ID = 'towell-toasts';
const STYLE_ID = 'towell-toasts-style';

const ICONS: Record<ToastType, string> = { success: '✓', info: 'i', warning: '!', error: '✕' };

const STYLES = `
.towell-toasts{position:fixed;top:1rem;right:1rem;z-index:1000000;display:flex;flex-direction:column;gap:.5rem;width:min(22rem,calc(100vw - 2rem));pointer-events:none}
.towell-toast{pointer-events:auto;display:flex;align-items:flex-start;gap:.625rem;padding:.75rem .75rem .75rem .875rem;border-radius:.5rem;border-left:4px solid;background:#fff;color:#1f2937;box-shadow:0 10px 15px -3px rgb(0 0 0/.15),0 4px 6px -4px rgb(0 0 0/.1);font:500 .875rem/1.35 system-ui,-apple-system,"Segoe UI",sans-serif;animation:towell-toast-in .18s ease-out}
.towell-toast__icon{flex:none;display:inline-flex;align-items:center;justify-content:center;width:1.25rem;height:1.25rem;border-radius:9999px;color:#fff;font-size:.75rem;font-weight:700}
.towell-toast__msg{flex:1;min-width:0;overflow-wrap:anywhere;white-space:pre-line}
.towell-toast__close{flex:none;border:0;background:none;color:#6b7280;cursor:pointer;font-size:1.125rem;line-height:1;padding:0 .125rem}
.towell-toast__close:hover,.towell-toast__close:focus-visible{color:#111827}
.towell-toast--success{border-color:#16a34a}.towell-toast--success .towell-toast__icon{background:#16a34a}
.towell-toast--info{border-color:#2563eb}.towell-toast--info .towell-toast__icon{background:#2563eb}
.towell-toast--warning{border-color:#d97706}.towell-toast--warning .towell-toast__icon{background:#d97706}
.towell-toast--error{border-color:#dc2626}.towell-toast--error .towell-toast__icon{background:#dc2626}
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
    document.body.appendChild(el);

    return el;
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
    start();

    return el;
}

export interface ConfirmOptions {
    title?: string;
    text?: string;
    html?: string | null;
    icon?: SweetAlertIcon;
    confirmText?: string;
    cancelText?: string;
    confirmColor?: string;
}

export const notify = {
    // Toasts ligeros (esquina). Equivalentes al antiguo showToast(msg, tipo).
    success: (msg: unknown) => toast('success', msg),
    error: (msg: unknown) => toast('error', msg),
    warning: (msg: unknown) => toast('warning', msg),
    info: (msg: unknown) => toast('info', msg),

    // Alerta modal bloqueante (para errores que el usuario debe ver sí o sí).
    alert(message: string, title = 'Aviso', icon: SweetAlertIcon = 'info'): Promise<SweetAlertResult> {
        return Swal.fire({ icon, title, text: message });
    },

    // Muestra los errores de validación de Laravel (422) en una lista.
    validation(errors: Record<string, string[] | string> | null | undefined, title = 'Revisa los datos'): Promise<SweetAlertResult> {
        const list = Object.values(errors || {}).flat();

        return Swal.fire({
            icon: 'warning',
            title,
            html: list.length
                ? `<ul style="text-align:left;margin:0;padding-left:1.2rem">${list
                      .map((e) => `<li>${escapeHtml(e)}</li>`)
                      .join('')}</ul>`
                : 'Hay errores en el formulario.',
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
        confirmColor = '#3085d6',
    }: ConfirmOptions = {}): Promise<boolean> {
        const res = await Swal.fire({
            title,
            icon,
            ...(html ? { html } : { text }),
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#6b7280',
        });

        return res.isConfirmed;
    },

    // Loader modal bloqueante (p. ej. mientras se procesa una petición).
    loading(title = 'Cargando...'): Promise<SweetAlertResult> {
        return Swal.fire({
            title,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => Swal.showLoading(),
        });
    },

    close(): void {
        Swal.close();
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
