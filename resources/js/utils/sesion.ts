/**
 * Sesión expirada (UX-11): un solo aviso y una sola acción para 419 (CSRF) y 401 (auth),
 * vengan de window.http o de Livewire. Antes solo 5 fetch lo manejaban y Livewire mostraba
 * su confirm() en inglés ("This page has expired").
 *
 * Toast nativo + recarga temporizada, no un modal de SweetAlert2: el catch del caller suele
 * cerrar o abrir otro Swal, lo que cerraría el aviso y recargaría al instante. Al recargar,
 * el middleware auth lleva al login y bootstrap/app.php deja el flash de sesión expirada.
 *
 * Las llamadas con fetch crudo de los módulos no pasan por aquí: la receta de 19-xx los cambia a http.
 */
import { notify } from './notifications.ts';

export const SESSION_EXPIRED_MESSAGE = 'Tu sesión expiró. Recargando para volver a iniciar sesión…';

/** Tiempo para leer el aviso antes de recargar. */
export const SESSION_EXPIRED_RELOAD_MS = 2500;

let avisado = false;

export function esSesionExpirada(status: number): boolean {
    return status === 419 || status === 401;
}

/** Avisa una sola vez por página y recarga. */
export function sesionExpirada(): void {
    if (avisado) return;
    avisado = true;

    notify.warning(SESSION_EXPIRED_MESSAGE);
    setTimeout(() => window.location.reload(), SESSION_EXPIRED_RELOAD_MS);
}

interface FalloLivewire {
    status: number;
    preventDefault: () => void;
}

interface LivewireConHooks {
    hook(name: string, callback: (...args: any[]) => void): void;
}

export const SERVIDOR_OCUPADO_MESSAGE = 'El servidor no respondió. Si estabas guardando algo, vuelve a intentarlo.';

/** Entre avisos de servidor ocupado: un poll cada 15 s no debe llenar la pantalla de toasts. */
export const SERVIDOR_OCUPADO_SILENCIO_MS = 60_000;

let ultimoAvisoOcupado = -Infinity;

/**
 * 502/503/504: el servidor (Apache) no tuvo worker libre o se cayó el proxy. Livewire pinta
 * esa página de error en un modal que bloquea la pantalla; se cambia por un toast. No se
 * reintenta solo: en producción un 503 puede llegar después de que la acción sí corrió
 * (ver crudo/busy-retry.ts). Los polls se recuperan en su siguiente vuelta.
 */
function servidorOcupado(): void {
    const ahora = Date.now();
    if (ahora - ultimoAvisoOcupado < SERVIDOR_OCUPADO_SILENCIO_MS) return;
    ultimoAvisoOcupado = ahora;
    notify.warning(SERVIDOR_OCUPADO_MESSAGE);
}

/** Reemplaza el confirm() de Livewire para 419/401 y su modal de error para 502-504. */
export function escucharSesionLivewire(livewire: LivewireConHooks): void {
    livewire.hook('request', ({ fail }: { fail: (cb: (f: FalloLivewire) => void) => void }) => {
        fail(({ status, preventDefault }) => {
            if (esSesionExpirada(status)) {
                preventDefault();
                sesionExpirada();
            } else if (status >= 502 && status <= 504) {
                preventDefault();
                servidorOcupado();
            }
        });
    });
}
