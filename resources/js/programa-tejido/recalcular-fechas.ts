// Botón "Recalcular fechas de producción" del navbar. Vivía inline en
// req-programa-tejido.blade.php; la URL de la superficie llega en PT_BOOT.routes.
import { PT_BOOT } from './boot.ts';
import { datosDelError } from './respuesta.ts';
import { recargarConservando } from './vista.ts';

interface RespuestaRecalcular {
    ok?: boolean;
    message?: string;
}

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btn-recalcular-fechas') as HTMLButtonElement | null;
    if (!btn) return;

    const url = PT_BOOT.routes?.recalcularFechas ?? '';
    const icono = () => btn.querySelector('i');

    btn.addEventListener('click', async () => {
        // Recalcula las fechas de TODOS los telares: un clic suelto no debe dispararlo.
        const seguir = await notify.confirm({
            title: '¿Recalcular fechas de producción?',
            text: 'Se recalculan las fechas de inicio y fin de todos los telares. La página se recarga al terminar.',
            confirmText: 'Recalcular',
        });
        if (!seguir) return;

        btn.disabled = true;
        icono()?.classList.add('fa-spin');

        http.post<RespuestaRecalcular>(url)
            .then((data) => {
                if (data.ok) {
                    // Cambian las fechas de todos los telares: se recarga ya, conservando scroll, fila y
                    // filtros. El aviso lo muestra showSavedToastIfAny al cargar (antes se esperaban 2 s).
                    try {
                        sessionStorage.setItem('priorityChangeMessage', data.message ?? 'Listo');
                    } catch { /* modo privado: se recarga sin aviso */ }
                    recargarConservando();
                } else {
                    void notify.alert(data.message ?? '', 'Error', 'error');
                }
            })
            // http lanza en todo no-2xx; antes un 4xx/5xx con JSON {ok:false,message} caía en la
            // rama de arriba. El mensaje del servidor sigue llegando por err.data.
            .catch((err: unknown) => {
                const msg = datosDelError<RespuestaRecalcular>(err)?.message;
                void notify.alert(msg ?? '', msg ? 'Error' : 'Error de conexión', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                icono()?.classList.remove('fa-spin');
            });
    });
});
