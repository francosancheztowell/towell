import { http } from '../utils/http';

/** Cada cuánto se pregunta si hay una versión nueva de los datos (el comando corre cada minuto). */
const INTERVALO_MS = 60_000;

/**
 * Pregunta cada minuto la versión de los datos de Ventas (VentasDatosController::version). Si ya no
 * es la de la página, muestra el aviso "Hay información nueva" con su botón para recargar; no recarga
 * solo para no perder lo que el usuario está viendo. Con la pestaña oculta no pregunta.
 */
export const bindDataVersionWatch = (root: HTMLElement) => {
    const url = root.dataset.versionUrl;
    const actual = root.dataset.datosVersion;
    const aviso = root.querySelector<HTMLElement>('[data-pvoc-datos-nuevos]');
    if (!url || !actual || !aviso) return;

    aviso.querySelector('[data-pvoc-datos-recargar]')?.addEventListener('click', () => window.location.reload());

    const timer = window.setInterval(async () => {
        if (document.hidden) return;
        try {
            const { version } = await http.get<{ version: string }>(url);
            if (version === actual) return;
            aviso.hidden = false;
            window.clearInterval(timer);
        } catch {
            // Sin conexión o sesión vencida: el banner global ya avisa; se reintenta el siguiente minuto.
        }
    }, INTERVALO_MS);
};
