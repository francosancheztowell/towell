// Botón "Recalcular fechas de producción" del navbar. Vivía inline en
// req-programa-tejido.blade.php; la URL de la superficie llega en PT_BOOT.routes.
import { PT_BOOT } from './boot.ts';

document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btn-recalcular-fechas');
    if (!btn) return;

    const url = PT_BOOT.routes.recalcularFechas;

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.querySelector('i').classList.add('fa-spin');

        http.post(url)
            .then((data) => {
                if (data.ok) {
                    Swal.fire({ icon: 'success', title: 'Listo', text: data.message, timer: 2000, showConfirmButton: false })
                        .then(() => window.location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                }
            })
            // http lanza en todo no-2xx; antes un 4xx/5xx con JSON {ok:false,message} caía en la
            // rama de arriba. El mensaje del servidor sigue llegando por err.data.
            .catch((err) => err?.data?.message
                ? Swal.fire({ icon: 'error', title: 'Error', text: err.data.message })
                : Swal.fire({ icon: 'error', title: 'Error de conexión' }))
            .finally(() => {
                btn.disabled = false;
                btn.querySelector('i').classList.remove('fa-spin');
            });
    });
});
