/**
 * Lista de usuarios: botón "Duplicar".
 * Vista: resources/views/modulos/usuarios/select.blade.php.
 * Crea un usuario nuevo (No. empleado, nombre, turno, contraseña) con los mismos permisos,
 * área y puesto del usuario elegido: POST configuracion.usuarios.duplicar.
 */
import { abrir, cerrarPorId } from '../../../componentes/dialog.ts';
import { delegate, onReady } from '../../../utils/dom.ts';
import { http, HttpError } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';

const MODAL = 'modalDuplicarUsuario';
const RECARGA_MS = 1200;

interface Respuesta {
    success: boolean;
    message?: string;
}

onReady(() => {
    const modal = document.getElementById(MODAL);
    const form = document.getElementById('formDuplicarUsuario') as HTMLFormElement | null;
    const origen = modal?.querySelector<HTMLElement>('[data-duplicar-origen]');
    const guardar = modal?.querySelector<HTMLButtonElement>('[data-duplicar-guardar]');
    const urlBase = modal?.dataset.url ?? '';
    if (!modal || !form || !origen || !guardar || urlBase === '') return;

    let idOrigen = '';
    let enviando = false;

    delegate(document, 'click', '[data-duplicar-usuario]', (_e, boton) => {
        idOrigen = boton.dataset.duplicarUsuario ?? '';
        origen.textContent = `${boton.dataset.nombre ?? ''} (#${boton.dataset.numeroEmpleado ?? ''})`;
        form.reset();
        abrir(MODAL);
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (enviando || idOrigen === '') return;
        if (!form.reportValidity()) return;

        enviando = true;
        guardar.disabled = true;
        try {
            const datos = Object.fromEntries(new FormData(form));
            const res = await http.post<Respuesta>(urlBase.replace('__ID__', encodeURIComponent(idOrigen)), datos);
            cerrarPorId(MODAL);
            notify.success(res.message ?? 'Usuario creado correctamente');
            window.setTimeout(() => window.location.reload(), RECARGA_MS);
        } catch (err) {
            if (err instanceof HttpError && err.status === 422) {
                void notify.validation(err.errors);
            } else if (err instanceof HttpError && err.status !== 419 && err.status !== 401) {
                const mensaje = (err.data as Respuesta | null)?.message;
                notify.error(mensaje ?? 'No se pudo crear el usuario');
            }
        } finally {
            enviando = false;
            guardar.disabled = false;
        }
    });
});
