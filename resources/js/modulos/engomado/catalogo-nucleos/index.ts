/**
 * Catálogo de Núcleos (Urdido/Engomado).
 * Vista: resources/views/modulos/engomado/urd-eng-nucleos/index.blade.php (data-pagina).
 * El flujo sigue siendo por formulario (POST/PUT/DELETE + redirect con flash): el modal lleva
 * un <form> real y el JS solo valida, fija acción/método y envía.
 */
import { abrir } from '../../../componentes/dialog.ts';
import { onReady } from '../../../utils/dom.ts';
import { notify } from '../../../utils/notifications.ts';
import {
    activarBotones,
    crearSeleccion,
    errorCampo,
    escucharAcciones,
} from '../../urdido/comun/catalogo.ts';
import { leerDatos, rutaCon } from '../../urdido/comun/pagina.ts';
import { destinoFormulario, validarNucleo } from './logica.ts';

interface Pagina {
    rutas: { guardar: string; actualizar: string; eliminar: string };
}

const MODAL = 'nucleoModal';

onReady(() => {
    const raiz = document.getElementById('catalogo-nucleos');
    const pagina = leerDatos<Pagina>(raiz);
    const cuerpo = raiz?.querySelector<HTMLElement>('tbody');
    const form = document.getElementById('nucleoForm') as HTMLFormElement | null;
    const formEliminar = document.getElementById('globalDeleteForm') as HTMLFormElement | null;
    if (!pagina || !cuerpo || !form) return;

    const botones = [
        document.getElementById('btn-top-edit') as HTMLButtonElement | null,
        document.getElementById('btn-top-delete') as HTMLButtonElement | null,
    ];
    const salon = form.querySelector<HTMLSelectElement>('#nucleo-salon');
    const nombre = form.querySelector<HTMLInputElement>('#nucleo-nombre');
    const metodoPut = form.querySelector<HTMLInputElement>('[data-metodo-put]');
    const titulo = document.getElementById(`${MODAL}-titulo`);
    const botonGuardar = document.querySelector<HTMLButtonElement>('[data-nucleo-guardar]');
    let editandoId: string | null = null;
    let enviando = false;

    const seleccion = crearSeleccion(cuerpo, 'tr[data-fila]', 'aria', (fila) =>
        activarBotones(botones, Boolean(fila?.dataset.key), false),
    );
    activarBotones(botones, false, false);

    const abrirFormulario = (fila: HTMLTableRowElement | null): void => {
        form.reset();
        errorCampo(salon, null);
        errorCampo(nombre, null);
        editandoId = fila?.dataset.key || null;
        if (fila && salon && nombre) {
            // Un salón que no está en la lista deja "-- Seleccione --", como antes.
            salon.value = fila.dataset.salon ?? '';
            if (salon.value !== (fila.dataset.salon ?? '')) salon.value = '';
            nombre.value = fila.dataset.nombre ?? '';
        }
        if (titulo) titulo.textContent = fila ? 'Editar Núcleo' : 'Nuevo Núcleo';
        const texto = botonGuardar?.querySelector('[data-texto-guardar]');
        if (texto) texto.textContent = fila ? 'Actualizar' : 'Guardar';
        abrir(MODAL);
    };

    form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        if (enviando) return;
        errorCampo(salon, null);
        errorCampo(nombre, null);
        const v = validarNucleo(salon?.value ?? '', nombre?.value ?? '');
        if (!v.ok) {
            errorCampo(v.campo === 'Salon' ? salon : nombre, v.mensaje);
            return;
        }
        if (nombre) nombre.value = v.datos.Nombre;
        const destino = destinoFormulario(pagina.rutas, editandoId);
        form.action = destino.accion;
        if (metodoPut) metodoPut.disabled = destino.metodo !== 'PUT';
        enviando = true;
        if (botonGuardar) botonGuardar.disabled = true;
        form.submit();
    });

    const eliminar = async (): Promise<void> => {
        const id = seleccion.actual()?.dataset.key;
        if (!id || !formEliminar) return;
        const confirmado = await notify.confirm({
            title: '¿Estás seguro?',
            text: 'Esta acción no se puede deshacer',
            confirmText: 'Sí, eliminar',
            confirmColor: '#dc2626',
        });
        if (!confirmado) return;
        formEliminar.action = rutaCon(pagina.rutas.eliminar, { id });
        formEliminar.submit();
    };

    escucharAcciones({
        crear: () => abrirFormulario(null),
        editar: () => {
            const fila = seleccion.actual();
            if (fila?.dataset.key) abrirFormulario(fila);
        },
        eliminar: () => void eliminar(),
    });
});
