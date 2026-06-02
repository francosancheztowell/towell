/**
 * Notificaciones unificadas de la aplicación.
 *
 * Centraliza Toastr (toasts ligeros) y SweetAlert2 (modales) para reemplazar las
 * múltiples definiciones duplicadas de `showToast()` repartidas por las vistas.
 *
 * Uso (en scripts inline de Blade, vía window.notify):
 *   notify.success('Guardado');
 *   notify.error('Algo salió mal');
 *   if (await notify.confirm({ text: '¿Eliminar?' })) { ... }
 *   notify.validation(err.errors);   // errores 422 de Laravel
 */
import Swal from 'sweetalert2';
import toastr from 'toastr';

// Escapa HTML para evitar XSS al interpolar mensajes que pudieran contener datos de usuario.
function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
}

export const notify = {
    // Toasts ligeros (esquina). Equivalentes al antiguo showToast(msg, tipo).
    success: (msg) => toastr.success(escapeHtml(msg)),
    error: (msg) => toastr.error(escapeHtml(msg)),
    warning: (msg) => toastr.warning(escapeHtml(msg)),
    info: (msg) => toastr.info(escapeHtml(msg)),

    // Alerta modal bloqueante (para errores que el usuario debe ver sí o sí).
    alert(message, title = 'Aviso', icon = 'info') {
        return Swal.fire({ icon, title, text: message });
    },

    // Muestra los errores de validación de Laravel (422) en una lista.
    validation(errors, title = 'Revisa los datos') {
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

    /**
     * Confirmación. Devuelve Promise<boolean> (true si el usuario confirma).
     */
    async confirm({
        title = '¿Confirmar?',
        text = '',
        html = null,
        icon = 'warning',
        confirmText = 'Sí',
        cancelText = 'Cancelar',
        confirmColor = '#3085d6',
    } = {}) {
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
    loading(title = 'Cargando...') {
        return Swal.fire({
            title,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => Swal.showLoading(),
        });
    },

    close() {
        Swal.close();
    },
};

/**
 * Shim de compatibilidad para el `showToast(message, type)` histórico (firma estándar).
 * Centraliza en toastr vía `notify` las múltiples copias del toast custom repartidas
 * por las vistas. NOTA: existen variantes locales con OTRA firma —`(icon, title)` en
 * engomado/urdido y `(options)` en cortes-eficiencia— que se auto-sombrean en su scope
 * y NO deben tocarse.
 */
export function showToast(message, type = 'success') {
    const fn = notify[type] || notify.info;
    fn(message);
}

export default notify;
