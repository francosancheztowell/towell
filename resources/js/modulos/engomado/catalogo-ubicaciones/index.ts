/**
 * Catálogo de Ubicaciones de Engomado.
 * Vista: resources/views/modulos/engomado/configuracion/catalogo-ubicaciones.blade.php (data-pagina).
 * Antes: fetch con CSRF a mano, Swal de formulario y rutas armadas en JS.
 */
import { abrir, cerrarPorId } from '../../../componentes/dialog.ts';
import { onReady } from '../../../utils/dom.ts';
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import {
    activarBotones,
    avisarError,
    crearSeleccion,
    errorCampo,
    escucharAcciones,
    exitoYRecargar,
} from '../../urdido/comun/catalogo.ts';
import { exigirExito, leerDatos, rutaCon, type RespuestaApi } from '../../urdido/comun/pagina.ts';
import { validarUbicacion } from './logica.ts';

interface Pagina {
    rutas: { guardar: string; actualizar: string; eliminar: string };
}

const MODAL = 'ubicacionModal';

onReady(() => {
    const raiz = document.getElementById('catalogo-ubicaciones');
    const pagina = leerDatos<Pagina>(raiz);
    const cuerpo = document.getElementById('ubicaciones-body');
    const form = document.getElementById('ubicacionForm') as HTMLFormElement | null;
    if (!pagina || !cuerpo || !form) return;

    const botones = [
        document.getElementById('btnEdit') as HTMLButtonElement | null,
        document.getElementById('btnDelete') as HTMLButtonElement | null,
    ];
    const campo = form.querySelector<HTMLInputElement>('#ubicacion-codigo');
    const titulo = document.getElementById(`${MODAL}-titulo`);
    const botonGuardar = document.querySelector<HTMLButtonElement>('[data-ubicacion-guardar]');
    let editandoId: string | null = null;
    let ocupado = false;

    const idDe = (fila: HTMLTableRowElement | null): string => (fila?.dataset.id ?? '').trim();

    const seleccion = crearSeleccion(
        cuerpo,
        'tr[data-fila]',
        { seleccionada: ['bg-blue-500', 'text-white', 'hover:bg-blue-600'], normal: ['text-black', 'hover:bg-blue-50'] },
        (fila) => activarBotones(botones, idDe(fila) !== ''),
    );
    activarBotones(botones, false);

    const filaSeleccionada = (): HTMLTableRowElement | null => {
        const fila = seleccion.actual();
        if (fila && idDe(fila)) return fila;
        void notify.alert('Debes seleccionar un registro para continuar.', 'Selecciona una ubicación', 'warning');
        return null;
    };

    const abrirFormulario = (fila: HTMLTableRowElement | null): void => {
        form.reset();
        errorCampo(campo, null);
        editandoId = fila ? idDe(fila) : null;
        if (campo) campo.value = fila?.dataset.codigo ?? '';
        if (titulo) titulo.textContent = fila ? 'Editar Ubicación' : 'Nueva Ubicación';
        const texto = botonGuardar?.querySelector('[data-texto-guardar]');
        if (texto) texto.textContent = fila ? 'Actualizar' : 'Guardar';
        abrir(MODAL);
    };

    const guardar = async (): Promise<void> => {
        if (ocupado) return;
        errorCampo(campo, null);
        const v = validarUbicacion(campo?.value ?? '');
        if (!v.ok) {
            errorCampo(campo, v.mensaje);
            return;
        }

        ocupado = true;
        if (botonGuardar) botonGuardar.disabled = true;
        try {
            const r = editandoId
                ? await http.put<RespuestaApi>(rutaCon(pagina.rutas.actualizar, { id: editandoId }), v.datos)
                : await http.post<RespuestaApi>(pagina.rutas.guardar, v.datos);
            exigirExito(r, 'No se pudo guardar la ubicación');
            cerrarPorId(MODAL);
            exitoYRecargar(r.message ?? (editandoId ? '¡Actualizado!' : '¡Creado!'));
        } catch (err) {
            avisarError(err, 'No se pudo guardar la ubicación');
        } finally {
            ocupado = false;
            if (botonGuardar) botonGuardar.disabled = false;
        }
    };

    const eliminar = async (): Promise<void> => {
        const fila = filaSeleccionada();
        if (!fila) return;
        const confirmado = await notify.confirm({
            title: 'Confirmar Eliminación',
            text: '¿Está seguro que desea eliminar esta ubicación? Esta acción no se puede deshacer.',
            confirmText: 'Eliminar',
            confirmColor: '#ef4444',
        });
        if (!confirmado) return;

        try {
            const r = await http.delete<RespuestaApi>(rutaCon(pagina.rutas.eliminar, { id: idDe(fila) }));
            exigirExito(r, 'No se pudo eliminar la ubicación');
            exitoYRecargar(r.message ?? '¡Eliminado!');
        } catch (err) {
            avisarError(err, 'No se pudo eliminar la ubicación');
        }
    };

    form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        void guardar();
    });

    escucharAcciones({
        crear: () => abrirFormulario(null),
        editar: () => {
            const fila = filaSeleccionada();
            if (fila) abrirFormulario(fila);
        },
        eliminar: () => void eliminar(),
    });
});
