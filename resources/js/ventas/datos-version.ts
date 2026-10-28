import { http } from '../utils/http';
import { notify } from '../utils/notifications';
import { respuestaActualizar, type Actualizacion } from './actualizar-logica';

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

/**
 * Botones "Actualizar datos" (uno en cada panel de filtros): piden al servidor publicar ya lo que se
 * cargó, sin esperar la calma del comando (VentasDatosController::actualizar). Si hay otra versión
 * recarga; si no, avisa por qué. Mientras uno trabaja, todos quedan deshabilitados.
 */
export const bindRefreshButton = (root: HTMLElement) => {
    const url = root.dataset.actualizarUrl;
    const actual = root.dataset.datosVersion;
    const botones = [...root.querySelectorAll<HTMLButtonElement>('[data-pvoc-actualizar]')];
    if (!url || !actual || botones.length === 0) return;

    const ocupado = (si: boolean) => {
        botones.forEach((boton) => {
            boton.disabled = si;
            boton.toggleAttribute('aria-busy', si);
            const texto = boton.querySelector<HTMLElement>('[data-pvoc-actualizar-texto]');
            if (texto) texto.textContent = si ? 'Actualizando…' : 'Actualizar datos';
        });
    };

    const actualizar = async () => {
        ocupado(true);
        try {
            const respuesta = respuestaActualizar(await http.post<Actualizacion>(url), actual);
            if (respuesta.accion === 'recargar') {
                window.location.reload();
                return;
            }
            notify[respuesta.tipo](respuesta.texto);
        } catch {
            notify.error('No se pudo actualizar la información de Ventas. Intenta de nuevo.');
        }
        ocupado(false);
    };

    botones.forEach((boton) => boton.addEventListener('click', actualizar));
};
