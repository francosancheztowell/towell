/**
 * Catálogo de Máquinas de Urdido.
 * Vista: resources/views/catalogosurdido/catalago-maquinas.blade.php (data-pagina).
 * Los modales Crear/Editar y Eliminar son los <div> de siempre (mismo diseño); el de filtro
 * pasó de Swal a x-ui.modal-base.
 */
import { abrir } from '../../../componentes/dialog.ts';
import { onReady } from '../../../utils/dom.ts';
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import {
    activarBotones,
    avisarError,
    crearSeleccion,
    escucharAcciones,
    exitoYRecargar,
    girar,
} from '../comun/catalogo.ts';
import { urlFiltro } from '../catalogo-julios/logica.ts';
import { exigirExito, leerDatos, rutaCon, type RespuestaApi } from '../comun/pagina.ts';
import { validarMaquina } from './logica.ts';

interface Pagina {
    rutas: { guardar: string; actualizar: string; eliminar: string; listado: string };
}

const MODAL_FORM = 'formModal';
const MODAL_ELIMINAR = 'deleteModal';
const MODAL_FILTRO = 'filtroMaquinasModal';

function mostrar(id: string, visible: boolean): void {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.toggle('hidden', !visible);
    modal.classList.toggle('flex', visible);
}

onReady(() => {
    const raiz = document.getElementById('catalogo-maquinas');
    const pagina = leerDatos<Pagina>(raiz);
    const cuerpo = document.getElementById('maquinas-body');
    const form = document.getElementById('maquinaForm') as HTMLFormElement | null;
    if (!pagina || !cuerpo || !form) return;

    const botones = [
        document.getElementById('btnEdit') as HTMLButtonElement | null,
        document.getElementById('btnDelete') as HTMLButtonElement | null,
    ];
    const campo = (id: string) => document.getElementById(id) as HTMLInputElement | null;
    const original = campo('original_maquinaid');
    const titulo = document.getElementById('formModalTitle');
    let ocupado = false;

    const seleccion = crearSeleccion(
        cuerpo,
        'tr[data-fila]',
        { seleccionada: ['bg-blue-100', 'border-l-4', 'border-blue-500'], normal: [] },
        (fila) => activarBotones(botones, fila !== null),
    );
    activarBotones(botones, false);

    const abrirFormulario = (fila: HTMLTableRowElement | null): void => {
        form.reset();
        if (original) original.value = fila?.dataset.maquinaId ?? '';
        if (titulo) titulo.textContent = fila ? 'Editar Máquina' : 'Nueva Máquina';
        if (fila) {
            const valores: Record<string, string> = {
                MaquinaId: fila.dataset.maquinaId ?? '',
                Nombre: fila.dataset.nombre ?? '',
                Departamento: fila.dataset.departamento ?? '',
            };
            for (const [id, valor] of Object.entries(valores)) {
                const c = campo(id);
                if (c) c.value = valor;
            }
        }
        mostrar(MODAL_FORM, true);
        campo('MaquinaId')?.focus();
    };

    const guardar = async (): Promise<void> => {
        if (ocupado) return;
        const v = validarMaquina(campo('MaquinaId')?.value ?? '', campo('Nombre')?.value ?? '', campo('Departamento')?.value ?? '');
        if (!v.ok) {
            notify.error(v.mensaje);
            campo(v.campo)?.focus();
            return;
        }
        const id = original?.value ?? '';

        ocupado = true;
        try {
            const r = id
                ? await http.put<RespuestaApi>(rutaCon(pagina.rutas.actualizar, { id }), v.datos)
                : await http.post<RespuestaApi>(pagina.rutas.guardar, v.datos);
            exigirExito(r, 'No se pudo guardar la máquina');
            mostrar(MODAL_FORM, false);
            exitoYRecargar(r.message ?? (id ? '¡Actualizado!' : '¡Creado!'));
        } catch (err) {
            avisarError(err, 'No se pudo guardar la máquina');
        } finally {
            ocupado = false;
        }
    };

    const eliminar = async (): Promise<void> => {
        const fila = seleccion.actual();
        const id = fila?.dataset.maquinaId ?? '';
        if (!fila || !id || ocupado) return;
        ocupado = true;
        try {
            const r = await http.delete<RespuestaApi>(rutaCon(pagina.rutas.eliminar, { id }));
            exigirExito(r, 'No se pudo eliminar la máquina');
            seleccion.limpiar();
            fila.remove();
            mostrar(MODAL_ELIMINAR, false);
            notify.success(r.message ?? '¡Eliminado!');
        } catch (err) {
            mostrar(MODAL_ELIMINAR, false);
            avisarError(err, 'No se pudo eliminar la máquina');
        } finally {
            ocupado = false;
        }
    };

    form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        void guardar();
    });

    const filtro = document.getElementById('filtroMaquinasForm') as HTMLFormElement | null;
    filtro?.addEventListener('submit', (ev) => {
        ev.preventDefault();
        const valor = (n: string) => filtro.querySelector<HTMLInputElement>(`[name="${n}"]`)?.value ?? '';
        window.location.href = urlFiltro(pagina.rutas.listado, {
            maquina_id: valor('maquina_id'),
            nombre: valor('nombre'),
            departamento: valor('departamento'),
        });
    });

    escucharAcciones({
        crear: () => abrirFormulario(null),
        editar: () => {
            const fila = seleccion.actual();
            if (fila) abrirFormulario(fila);
        },
        eliminar: () => {
            if (seleccion.actual()) mostrar(MODAL_ELIMINAR, true);
        },
        'confirmar-eliminar': () => void eliminar(),
        'cerrar-modal': (boton) => mostrar(boton.dataset.modal ?? '', false),
        filtrar: () => abrir(MODAL_FILTRO),
        restablecer: (boton) => {
            girar(boton);
            window.location.href = pagina.rutas.listado;
        },
    });

    // Cerrar los modales propios al tocar el fondo o con Escape (antes se asignaba window.onclick).
    document.addEventListener('click', (ev) => {
        for (const id of [MODAL_FORM, MODAL_ELIMINAR]) {
            if (ev.target === document.getElementById(id)) mostrar(id, false);
        }
    });
    document.addEventListener('keydown', (ev) => {
        if (ev.key !== 'Escape' || document.querySelector('.swal2-container')) return;
        mostrar(MODAL_FORM, false);
        mostrar(MODAL_ELIMINAR, false);
    });
});
