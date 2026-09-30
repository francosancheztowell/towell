/**
 * Reportes de Mantenimiento (19-08): el selector (reportes-mantenimiento-index) y
 * Fallas y Paros comparten el modal de rango de fechas. Antes: dos <script> inline
 * y onclick="mostrarModalFechas()".
 *
 *   data-accion="abrir-rango" [data-url]  abre el modal (la URL destino; si falta, la de la página)
 *   data-accion="confirmar-rango"         valida y navega con ?fecha_ini&fecha_fin
 *   data-accion="cerrar-rango"            cierra
 */
import { delegate } from '../../../utils/dom.ts';
import { notify } from '../../../utils/notifications.ts';
import { leerDatos } from '../comun/pagina.ts';
import { relojLocal, urlConRango, validarRango } from '../comun/fechas.ts';

interface ConfigReportes {
    /** URL del reporte cuando el botón no trae data-url (Fallas y Paros). */
    destino?: string;
    /** Sin rango elegido, el modal se abre solo (Fallas y Paros). */
    abrirAlCargar?: boolean;
}

function iniciar(): void {
    const raiz = document.getElementById('pagina-reportes-mant');
    const modal = document.querySelector<HTMLElement>('[data-modal-rango]');
    const ini = modal?.querySelector<HTMLInputElement>('[data-rango="ini"]');
    const fin = modal?.querySelector<HTMLInputElement>('[data-rango="fin"]');
    if (!raiz || !modal || !ini || !fin) return;
    const cfg = leerDatos<ConfigReportes>(raiz) ?? {};

    let destino = cfg.destino ?? '';

    const abrir = (url?: string): void => {
        if (url) destino = url;
        const { fecha } = relojLocal();
        // El selector siempre propone hoy; Fallas y Paros conserva el rango consultado.
        if (url || !ini.value) ini.value = fecha;
        if (url || !fin.value) fin.value = fecha;
        modal.classList.replace('hidden', 'flex');
        ini.focus();
    };
    const cerrar = (): void => {
        modal.classList.replace('flex', 'hidden');
    };
    const confirmar = (): void => {
        const problema = validarRango(ini.value, fin.value);
        if (problema) {
            notify.warning(problema);
            return;
        }
        window.location.href = urlConRango(destino, ini.value, fin.value);
    };

    const acciones: Record<string, (el: HTMLElement) => void> = {
        'abrir-rango': (el) => {
            if (el.getAttribute('aria-disabled') !== 'true') abrir(el.dataset.url);
        },
        'confirmar-rango': confirmar,
        'cerrar-rango': cerrar,
    };
    delegate(document, 'click', '[data-accion]', (_ev, el) => acciones[el.dataset.accion ?? '']?.(el));

    // Tocar el velo cierra; Escape también (el modal es un div, no un <dialog>).
    modal.addEventListener('click', (ev) => {
        if (ev.target === modal) cerrar();
    });
    document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape' && modal.classList.contains('flex')) cerrar();
    });
    [ini, fin].forEach((input) =>
        input.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                confirmar();
            }
        }),
    );

    if (cfg.abrirAlCargar) abrir();
}

iniciar();
