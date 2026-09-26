/**
 * Catálogo de Julios (Urdido y Engomado; el controller decide el departamento por la ruta).
 * Vista: resources/views/catalogosurdido/catalago-julios.blade.php (data-pagina).
 * Antes: 420 líneas inline con Swal de formulario, axios con CSRF a mano y rutas armadas en JS.
 */
import { abrir, cerrarPorId } from '../../../componentes/dialog.ts';
import { onReady } from '../../../utils/dom.ts';
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { exigirExito, leerDatos, rutaCon, type RespuestaApi } from '../comun/pagina.ts';
import {
    activarBotones,
    avisarError,
    crearSeleccion,
    errorCampo,
    escucharAcciones,
    exitoYRecargar,
    girar,
} from '../comun/catalogo.ts';
import { urlFiltro, validarJulio } from './logica.ts';

interface Pagina {
    departamento: string;
    rutas: { guardar: string; actualizar: string; eliminar: string; listado: string };
}

const MODAL = 'julioModal';
const MODAL_FILTRO = 'filtroJuliosModal';

onReady(() => {
    const raiz = document.getElementById('catalogo-julios');
    const pagina = leerDatos<Pagina>(raiz);
    const cuerpo = document.getElementById('julios-body');
    const form = document.getElementById('julioForm') as HTMLFormElement | null;
    const filtro = document.getElementById('filtroJuliosForm') as HTMLFormElement | null;
    if (!raiz || !pagina || !cuerpo || !form) return;

    const botones = [
        document.getElementById('btnEdit') as HTMLButtonElement | null,
        document.getElementById('btnDelete') as HTMLButtonElement | null,
    ];
    const campoNo = form.querySelector<HTMLInputElement>('#julio-no');
    const campoTara = form.querySelector<HTMLInputElement>('#julio-tara');
    const titulo = document.getElementById(`${MODAL}-titulo`);
    const botonGuardar = document.querySelector<HTMLButtonElement>('[data-julio-guardar]');
    let editandoId: string | null = null;
    let ocupado = false;

    const idDe = (fila: HTMLTableRowElement | null): string =>
        (fila?.dataset.id || fila?.dataset.noJulio || '').trim();

    const seleccion = crearSeleccion(
        cuerpo,
        'tr[data-fila]',
        { seleccionada: ['bg-blue-500', 'text-white', 'hover:bg-blue-600'], normal: ['text-black', 'hover:bg-blue-50'] },
        (fila) => activarBotones(botones, idDe(fila) !== ''),
    );
    activarBotones(botones, false);

    const avisoSinSeleccion = (): void => {
        void notify.alert('Debes seleccionar un registro para continuar.', 'Selecciona un julio', 'warning');
    };

    const abrirFormulario = (fila: HTMLTableRowElement | null): void => {
        form.reset();
        errorCampo(campoNo, null);
        errorCampo(campoTara, null);
        editandoId = fila ? idDe(fila) : null;
        if (fila && campoNo && campoTara) {
            campoNo.value = fila.dataset.noJulio ?? '';
            campoTara.value = fila.dataset.tara ?? '';
        }
        if (titulo) titulo.textContent = fila ? 'Editar Julio' : 'Nuevo Julio';
        const texto = botonGuardar?.querySelector('[data-texto-guardar]');
        if (texto) texto.textContent = fila ? 'Actualizar' : 'Guardar';
        abrir(MODAL);
    };

    const guardar = async (): Promise<void> => {
        if (ocupado) return;
        errorCampo(campoNo, null);
        errorCampo(campoTara, null);
        const v = validarJulio(campoNo?.value ?? '', campoTara?.value ?? '', pagina.departamento);
        if (!v.ok) {
            errorCampo(v.campo === 'Tara' ? campoTara : campoNo, v.mensaje);
            return;
        }

        ocupado = true;
        if (botonGuardar) botonGuardar.disabled = true;
        try {
            const r = editandoId
                ? await http.put<RespuestaApi>(rutaCon(pagina.rutas.actualizar, { id: editandoId }), v.datos)
                : await http.post<RespuestaApi>(pagina.rutas.guardar, v.datos);
            exigirExito(r, 'No se pudo guardar el julio');
            cerrarPorId(MODAL);
            exitoYRecargar(r.message ?? (editandoId ? '¡Actualizado!' : '¡Creado!'));
        } catch (err) {
            avisarError(err, 'No se pudo guardar el julio');
        } finally {
            ocupado = false;
            if (botonGuardar) botonGuardar.disabled = false;
        }
    };

    const eliminar = async (): Promise<void> => {
        const fila = seleccion.actual();
        const id = idDe(fila);
        if (!fila || !id) {
            avisoSinSeleccion();
            return;
        }
        const confirmado = await notify.confirm({
            title: 'Confirmar Eliminación',
            text: '¿Está seguro que desea eliminar este julio? Esta acción no se puede deshacer.',
            confirmText: 'Eliminar',
            confirmColor: '#ef4444',
        });
        if (!confirmado) return;

        try {
            const r = await http.delete<RespuestaApi>(rutaCon(pagina.rutas.eliminar, { id }));
            exigirExito(r, 'No se pudo eliminar el julio');
            seleccion.limpiar();
            fila.remove();
            notify.success(r.message ?? '¡Eliminado!');
        } catch (err) {
            avisarError(err, 'No se pudo eliminar el julio');
        }
    };

    form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        void guardar();
    });

    filtro?.addEventListener('submit', (ev) => {
        ev.preventDefault();
        const noJulio = filtro.querySelector<HTMLInputElement>('[name="no_julio"]')?.value ?? '';
        window.location.href = urlFiltro(pagina.rutas.listado, { no_julio: noJulio });
    });

    escucharAcciones({
        crear: () => abrirFormulario(null),
        editar: () => {
            const fila = seleccion.actual();
            if (!fila || !idDe(fila)) return avisoSinSeleccion();
            abrirFormulario(fila);
        },
        eliminar: () => void eliminar(),
        filtrar: () => abrir(MODAL_FILTRO),
        restablecer: (boton) => {
            girar(boton);
            window.location.href = pagina.rutas.listado;
        },
    });
});
