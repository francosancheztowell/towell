/**
 * Configuración › Base de datos. Vista: resources/views/modulos/configuracion/basededatos.blade.php.
 * Antes ~360 líneas inline (incluido un filtro por columna propio con clic derecho + Swal, que ahora
 * cubre el filtro por columna común: componentes/tabla-columnas.ts).
 *
 * Aquí solo queda el interruptor Productivo/Prueba de cada usuario: guarda al cambiar y, si falla,
 * regresa el interruptor y avisa.
 */
import { delegate, onReady } from '../../../utils/dom.ts';
import { notify } from '../../../utils/notifications.ts';

interface Respuesta {
    success: boolean;
    message?: string;
}

/** El texto visible de la celda es lo que lee el filtro de la columna Estado. */
function pintarEstado(interruptor: HTMLInputElement): void {
    const etiqueta = interruptor.closest('label')?.querySelector('[data-estado-texto]');
    if (etiqueta) etiqueta.textContent = interruptor.checked ? 'Productivo' : 'Prueba';
}

onReady(() => {
    const tabla = document.getElementById('usuariosTable');
    const ruta = tabla?.dataset.rutaProductivo;
    if (!tabla || !ruta) return;

    delegate<HTMLInputElement>(tabla, 'change', 'input[data-user-id]', async (_e, interruptor) => {
        pintarEstado(interruptor);
        interruptor.disabled = true;
        try {
            const r = await window.http.post<Respuesta>(ruta, { user_id: interruptor.dataset.userId, productivo: interruptor.checked ? 1 : 0 });
            if (!r.success) throw new Error(r.message ?? 'No se pudo actualizar el estado');
            notify.success(r.message ?? 'Estado actualizado');
        } catch (err) {
            interruptor.checked = !interruptor.checked;
            pintarEstado(interruptor);
            const e = err as { data?: { message?: string }; message?: string };
            notify.error(e.data?.message ?? e.message ?? 'Ocurrió un error al actualizar el estado. Intenta nuevamente.');
        } finally {
            interruptor.disabled = false;
        }
    });
});
