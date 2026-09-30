// Botón "Recalcular fechas de producción" del navbar. Vivía inline en
// req-programa-tejido.blade.php; la URL de la superficie llega en PT_BOOT.routes.
import { PT_BOOT } from './boot.ts';
import { datosDelError } from './respuesta.ts';

interface RespuestaRecalcular {
    ok?: boolean;
    message?: string;
}

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btn-recalcular-fechas') as HTMLButtonElement | null;
    if (!btn) return;

    const url = PT_BOOT.routes?.recalcularFechas ?? '';
    const icono = () => btn.querySelector('i');

    btn.addEventListener('click', () => {
        btn.disabled = true;
        icono()?.classList.add('fa-spin');

        http.post<RespuestaRecalcular>(url)
            .then((data) => {
                if (data.ok) {
                    // Aviso modal con cierre solo y recarga, como antes.
                    void Swal.fire({ icon: 'success', title: 'Listo', text: data.message, timer: 2000, showConfirmButton: false })
                        .then(() => window.location.reload());
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
