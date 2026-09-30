/**
 * Secuencias de Tejido (19-02): inv-telas, inv-trama, corte-eficiencia y marcas-finales en un
 * solo bundle. La vista es resources/views/modulos/tejido/secuencia/comun.blade.php.
 */
import { delegate, onReady, qs, qsa } from '../../../utils/dom.ts';
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { alertaError, exigirExito, leerDatos, type RespuestaApi } from '../comun/pagina.ts';
import {
    construirPayload,
    esRequerido,
    ordenDesdeLlaves,
    soltarAntes,
    validar,
    type CampoSecuencia,
    type ModoFormulario,
    type OrdenSecuencia,
    type Valores,
} from './logica.ts';

interface ConfigSecuencia {
    variante: string;
    nombre: string;
    campos: CampoSecuencia[];
    orden: OrdenSecuencia;
    descripcion: [string, string];
    permisos: { editar: boolean; eliminar: boolean };
    rutas: { store: string; orden: string; update: string; destroy: string };
}

const CLASES_FILA = ['bg-white', 'bg-gray-100'];
const CLASES_SELECCION = ['bg-blue-500', 'text-white'];
const RECARGA_MS = 900;

function iniciar(): void {
    const raiz = qs('#pagina-secuencia');
    const cfg = leerDatos<ConfigSecuencia>(raiz);
    const cuerpo = qs('#secuencia-body');
    const modal = qs<HTMLDialogElement>('#modal-secuencia');
    const form = qs<HTMLFormElement>('#form-secuencia');
    if (!raiz || !cfg || !cuerpo || !modal || !form) return;

    const btnEditar = qs<HTMLButtonElement>('#btn-editar');
    const btnEliminar = qs<HTMLButtonElement>('#btn-eliminar');
    const btnGuardar = qs<HTMLButtonElement>('#btn-guardar-secuencia');
    const errorForm = qs('#form-secuencia-error');

    let seleccionada: HTMLTableRowElement | null = null;
    let arrastrada: HTMLTableRowElement | null = null;
    let modo: ModoFormulario = 'crear';
    let enviando = false;

    const filas = (): HTMLTableRowElement[] => qsa<HTMLTableRowElement>('tr.secuencia-row', cuerpo);
    const valoresFila = (fila: HTMLTableRowElement): Valores => leerDatos<Valores>(fila, 'valores') ?? {};

    /* ---------- Botones del navbar ---------- */
    const pintarBoton = (boton: HTMLButtonElement | null, permitido: boolean, activo: string, inactivo: string, habilitar: boolean): void => {
        if (!boton || !permitido) return;
        boton.disabled = !habilitar;
        boton.className = habilitar ? activo : inactivo;
    };
    const actualizarBotones = (): void => {
        const hay = seleccionada !== null;
        pintarBoton(btnEditar, cfg.permisos.editar,
            'p-2 text-yellow-600 hover:text-yellow-800 hover:bg-yellow-100 rounded-md transition-colors',
            'p-2 text-gray-400 hover:text-gray-600 rounded-md transition-colors cursor-not-allowed', hay);
        pintarBoton(btnEliminar, cfg.permisos.eliminar,
            'p-2 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors',
            'p-2 text-red-400 hover:text-red-600 rounded-md transition-colors cursor-not-allowed', hay);
    };

    /* ---------- Selección ---------- */
    const pintarFilas = (): void => {
        filas().forEach((fila, i) => {
            fila.classList.remove(...CLASES_SELECCION, ...CLASES_FILA);
            fila.setAttribute('aria-selected', fila === seleccionada ? 'true' : 'false');
            if (fila === seleccionada) fila.classList.add(...CLASES_SELECCION);
            else fila.classList.add(i % 2 === 0 ? 'bg-white' : 'bg-gray-100');
        });
    };
    const seleccionar = (fila: HTMLTableRowElement | null): void => {
        seleccionada = fila;
        pintarFilas();
        actualizarBotones();
    };

    delegate<HTMLTableRowElement>(cuerpo, 'click', 'tr.secuencia-row', (_ev, fila) => {
        if (!arrastrada) seleccionar(fila);
    });
    delegate<HTMLTableRowElement>(cuerpo, 'dblclick', 'tr.secuencia-row', (_ev, fila) => {
        if (fila === seleccionada) seleccionar(null);
    });

    /* ---------- Arrastrar para reordenar ---------- */
    const limpiarMarcas = (): void => filas().forEach((f) => f.classList.remove('drag-over-top', 'drag-over-bottom'));

    delegate<HTMLTableRowElement, DragEvent>(cuerpo, 'dragstart', 'tr.secuencia-row', (ev, fila) => {
        arrastrada = fila;
        if (ev.dataTransfer) {
            ev.dataTransfer.effectAllowed = 'move';
            ev.dataTransfer.setData('text/plain', fila.dataset.id ?? '');
            ev.dataTransfer.setDragImage(fila, 0, 0);
        }
        fila.classList.add('dragging');
    });
    delegate<HTMLTableRowElement>(cuerpo, 'dragend', 'tr.secuencia-row', () => {
        arrastrada?.classList.remove('dragging');
        arrastrada = null;
        limpiarMarcas();
    });
    delegate<HTMLTableRowElement, DragEvent>(cuerpo, 'dragover', 'tr.secuencia-row', (ev, fila) => {
        ev.preventDefault();
        if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'move';
        if (fila === arrastrada) return;
        limpiarMarcas();
        const r = fila.getBoundingClientRect();
        fila.classList.add(soltarAntes(ev.clientY, r.top, r.height) ? 'drag-over-top' : 'drag-over-bottom');
    });
    delegate<HTMLTableRowElement>(cuerpo, 'dragleave', 'tr.secuencia-row', (_ev, fila) => {
        fila.classList.remove('drag-over-top', 'drag-over-bottom');
    });
    delegate<HTMLTableRowElement, DragEvent>(cuerpo, 'drop', 'tr.secuencia-row', (ev, destino) => {
        ev.preventDefault();
        const movida = arrastrada;
        if (!movida || destino === movida) return;
        destino.classList.remove('drag-over-top', 'drag-over-bottom');
        const r = destino.getBoundingClientRect();
        destino.parentNode?.insertBefore(movida, soltarAntes(ev.clientY, r.top, r.height) ? destino : destino.nextSibling);
        movida.classList.remove('dragging');
        movida.classList.add('drop-success');
        window.setTimeout(() => movida.classList.remove('drop-success'), 500);
        arrastrada = null;
        void guardarOrden();
    });

    async function guardarOrden(): Promise<void> {
        const lista = filas();
        lista.forEach((fila, i) => {
            const celda = qs(`td[data-columna="${cfg!.orden.campo}"]`, fila);
            if (celda) celda.textContent = String(i + 1);
            const valores = valoresFila(fila);
            valores[cfg!.orden.campo] = String(i + 1);
            fila.dataset.valores = JSON.stringify(valores);
        });
        pintarFilas();
        const orden = ordenDesdeLlaves(lista.map((f) => f.dataset.id ?? ''), cfg!.orden);
        try {
            exigirExito(await http.post<RespuestaApi>(cfg!.rutas.orden, { orden }), 'No se pudo guardar el orden');
            notify.success('Orden guardado');
        } catch (err) {
            alertaError(err, 'Error de red al guardar el orden');
        }
    }

    /* ---------- Modal crear / editar ---------- */
    const control = (nombre: string): HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | null =>
        form.elements.namedItem(nombre) as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | null;

    const mostrarError = (mensaje: string | null): void => {
        if (!errorForm) return;
        errorForm.textContent = mensaje ?? '';
        errorForm.classList.toggle('hidden', !mensaje);
    };

    const abrirModal = (nuevoModo: ModoFormulario, valores: Valores): void => {
        modo = nuevoModo;
        for (const campo of cfg.campos) {
            const el = control(campo.nombre);
            if (el) el.value = valores[campo.nombre] ?? '';
            const marca = qs(`label[for="campo-${campo.nombre}"] [data-requerido]`, form);
            if (marca) marca.textContent = esRequerido(campo, modo) ? ' *' : '';
            if (campo.nombre === 'Orden' && el && 'placeholder' in el) el.placeholder = modo === 'crear' ? 'Auto' : '';
        }
        mostrarError(null);
        const titulo = qs('#modal-secuencia-titulo');
        if (titulo) titulo.textContent = `${modo === 'crear' ? 'Crear' : 'Editar'} ${cfg.nombre}`;
        if (btnGuardar) {
            const texto = qs('span', btnGuardar);
            if (texto) texto.textContent = modo === 'crear' ? 'Crear' : 'Actualizar';
            btnGuardar.classList.toggle('bg-blue-600', modo === 'crear');
            btnGuardar.classList.toggle('hover:bg-blue-700', modo === 'crear');
            btnGuardar.classList.toggle('bg-yellow-500', modo === 'editar');
            btnGuardar.classList.toggle('hover:bg-yellow-600', modo === 'editar');
        }
        modal.classList.remove('hidden');
    };
    const cerrarModal = (): void => modal.classList.add('hidden');

    const recargar = (mensaje: string): void => {
        notify.success(mensaje);
        window.setTimeout(() => location.reload(), RECARGA_MS);
    };

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if (enviando) return;
        const valores: Valores = {};
        for (const campo of cfg.campos) valores[campo.nombre] = control(campo.nombre)?.value ?? '';
        const faltante = validar(cfg.campos, valores, modo);
        if (faltante) {
            mostrarError(faltante);
            return;
        }
        const payload = construirPayload(cfg.campos, valores);
        const id = seleccionada?.dataset.id ?? '';
        enviando = true;
        cerrarModal();
        void notify.loading(modo === 'crear' ? 'Creando...' : 'Actualizando...');
        try {
            if (modo === 'crear') {
                exigirExito(await http.post<RespuestaApi>(cfg.rutas.store, payload), 'Error al crear');
                notify.close();
                recargar('¡Registro creado!');
            } else {
                const url = cfg.rutas.update.replace('__ID__', encodeURIComponent(id));
                exigirExito(await http.put<RespuestaApi>(url, payload), 'Error al actualizar');
                notify.close();
                recargar('¡Registro actualizado!');
            }
        } catch (err) {
            notify.close();
            alertaError(err, modo === 'crear' ? 'Error al crear el registro.' : 'No se pudo actualizar el registro.');
        } finally {
            enviando = false;
        }
    });

    /* ---------- Acciones ---------- */
    const acciones: Record<string, () => void> = {
        crear: () => abrirModal('crear', {}),
        editar: () => {
            if (!seleccionada) {
                void notify.alert('Por favor selecciona un registro para editar', 'Error', 'warning');
                return;
            }
            abrirModal('editar', valoresFila(seleccionada));
        },
        eliminar: () => void eliminar(),
        cancelar: cerrarModal,
    };

    async function eliminar(): Promise<void> {
        const fila = seleccionada;
        if (!fila) {
            void notify.alert('Selecciona un registro para eliminar', 'Error', 'warning');
            return;
        }
        const valores = valoresFila(fila);
        const [a, b] = cfg!.descripcion;
        const ok = await notify.confirm({
            title: '¿Eliminar Registro?',
            text: `¿Estás seguro de eliminar el telar ${valores[a] ?? ''} (${valores[b] ?? ''})?`,
            confirmText: 'Sí, eliminar',
            confirmColor: '#dc2626',
        });
        if (!ok) return;
        void notify.loading('Eliminando...');
        try {
            const url = cfg!.rutas.destroy.replace('__ID__', encodeURIComponent(fila.dataset.id ?? ''));
            exigirExito(await http.delete<RespuestaApi>(url), 'Error al eliminar');
            notify.close();
            recargar('¡Registro eliminado!');
        } catch (err) {
            notify.close();
            alertaError(err, 'No se pudo eliminar el registro.');
        }
    }

    delegate(document, 'click', '[data-accion]', (ev, el) => {
        const accion = acciones[el.dataset.accion ?? ''];
        if (!accion || (el as HTMLButtonElement).disabled) return;
        ev.preventDefault();
        accion();
    });

    actualizarBotones();
}

onReady(iniciar);
