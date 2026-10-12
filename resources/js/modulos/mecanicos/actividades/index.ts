/**
 * Catálogo de actividades de mecánicos: selección de fila, alta/edición en modal y borrado.
 * Vista: resources/views/modulos/mecanicos/catalogos/actividades/index.blade.php.
 */
import { onReady, qs, qsa } from '../../../utils/dom.ts';
import { http, HttpError } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';

interface Actividad {
    Id?: number | string;
    Orden?: number | string | null;
    Actividad?: string | null;
}

interface Respuesta {
    ok?: boolean;
    message?: string;
    item?: Actividad;
    errors?: Record<string, string[]>;
}

const SELECCION = ['selected', 'bg-blue-500', 'text-white', 'hover:bg-blue-600'];

/**
 * POST que devuelve el JSON también en errores HTTP (el controller contesta {ok:false, message}).
 * null: era un 422 y ya se mostró la validación.
 */
async function enviar(url: string, datos: FormData): Promise<Respuesta | null> {
    try {
        return await http.post<Respuesta>(url, datos);
    } catch (err) {
        if (!(err instanceof HttpError) || !err.status) throw err;
        if (err.status === 422 && err.errors) {
            void notify.validation(err.errors);
            return null;
        }
        return (err.data as Respuesta | null) ?? {};
    }
}

function mensajeError(data: Respuesta): string {
    const errores = Object.values(data.errors ?? {}).flat();
    return errores.length ? errores.join(' ') : data.message || 'No se pudo guardar';
}

onReady(() => {
    const raiz = qs('#actividades');
    const modal = qs('#modal-form');
    const form = qs<HTMLFormElement>('#form-actividad');
    const titulo = qs('#modal-title');
    const metodo = qs<HTMLInputElement>('#form-method');
    const tbody = qs<HTMLTableSectionElement>('#tbody-actividades');
    const orden = qs<HTMLInputElement>('#Orden');
    const actividad = qs<HTMLInputElement>('#Actividad');
    const guardar = qs<HTMLButtonElement>('#form-submit');
    const btnEditar = qs<HTMLButtonElement>('#btn-editar');
    const btnEliminar = qs<HTMLButtonElement>('#btn-eliminar');
    if (!raiz || !modal || !form || !titulo || !metodo || !tbody || !orden || !actividad || !guardar) return;
    const urls = raiz.dataset;

    let seleccionada: HTMLTableRowElement | null = null;

    const abrirModal = (): void => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        orden.focus();
    };
    const cerrarModal = (): void => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };
    const estadoAcciones = (): void => {
        if (btnEditar) btnEditar.disabled = !seleccionada;
        if (btnEliminar) btnEliminar.disabled = !seleccionada;
    };

    const seleccionar = (tr: HTMLTableRowElement | null): void => {
        qsa('.actividad-row.selected', tbody).forEach((r) => {
            r.classList.remove(...SELECCION);
            r.classList.add('hover:bg-gray-50');
            qsa('td', r).forEach((td) => td.classList.remove('text-white'));
        });
        seleccionada = tr;
        if (tr) {
            tr.classList.remove('hover:bg-gray-50');
            tr.classList.add(...SELECCION);
            qsa('td', tr).forEach((td) => td.classList.add('text-white'));
        }
        estadoAcciones();
    };

    const ordenar = (): void => {
        const filas = qsa<HTMLTableRowElement>('tr.actividad-row', tbody);
        const n = (v: string | undefined): number => Number.parseInt(v || '0', 10);
        filas.sort((a, b) => n(a.dataset.orden) - n(b.dataset.orden) || n(a.dataset.id) - n(b.dataset.id));
        tbody.append(...filas);
    };

    const pintarFila = (tr: HTMLTableRowElement, item: Actividad): void => {
        tr.dataset.orden = String(item.Orden ?? '');
        tr.dataset.actividad = item.Actividad || '';
        const celdas = [
            ['px-4 py-3 text-gray-700 text-base', tr.dataset.orden],
            ['px-4 py-3 font-medium text-gray-900 text-base', tr.dataset.actividad],
        ];
        celdas.forEach(([clase, texto], i) => {
            const td = tr.cells[i] ?? tr.insertCell();
            if (!td.className) td.className = clase ?? '';
            td.textContent = texto ?? '';
        });
    };

    tbody.addEventListener('click', (e) => {
        const tr = (e.target as Element).closest<HTMLTableRowElement>('tr.actividad-row');
        if (tr) seleccionar(tr);
    });

    qs('#btn-nuevo')?.addEventListener('click', () => {
        titulo.textContent = 'Nueva actividad';
        form.action = urls.storeUrl ?? '';
        metodo.value = 'POST';
        form.reset();
        abrirModal();
    });

    btnEditar?.addEventListener('click', () => {
        if (!seleccionada) return;
        titulo.textContent = 'Editar actividad';
        form.action = (urls.updateUrl ?? '').replace('__ID__', seleccionada.dataset.id ?? '');
        metodo.value = 'PUT';
        orden.value = seleccionada.dataset.orden || '';
        actividad.value = seleccionada.dataset.actividad || '';
        abrirModal();
    });

    btnEliminar?.addEventListener('click', async () => {
        const fila = seleccionada;
        if (!fila) return;
        const confirmado = await notify.confirm({
            title: '¿Eliminar actividad?',
            text: `Se eliminará "${fila.dataset.actividad || 'esta actividad'}".`,
            confirmText: 'Sí, eliminar',
            cancelText: 'Cancelar',
            confirmColor: '#dc2626',
        });
        if (!confirmado) return;

        const datos = new FormData();
        datos.append('_method', 'DELETE');
        try {
            const data = await enviar((urls.destroyUrl ?? '').replace('__ID__', fila.dataset.id ?? ''), datos);
            if (!data) return;
            if (!data.ok) {
                notify.error(data.message || 'No se pudo eliminar');
                return;
            }
            fila.remove();
            seleccionar(null);
            if (!tbody.querySelector('tr.actividad-row') && !qs('#tr-empty')) {
                const vacia = tbody.insertRow();
                vacia.id = 'tr-empty';
                const td = vacia.insertCell();
                td.colSpan = 2;
                td.className = 'px-4 py-8 text-center text-gray-500 text-base';
                td.textContent = 'No hay actividades registradas.';
            }
            notify.success(data.message || 'Eliminado');
        } catch (err) {
            notify.error(err instanceof Error && err.message ? err.message : 'Error de conexión');
        }
    });

    qs('#modal-cerrar')?.addEventListener('click', cerrarModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) cerrarModal();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const esEdicion = metodo.value === 'PUT';
        const datos = new FormData(form);
        if (esEdicion) datos.append('_method', 'PUT');

        guardar.disabled = true;
        try {
            const data = await enviar(form.action, datos);
            if (!data) return;
            if (!data.ok) {
                notify.error(mensajeError(data));
                return;
            }
            cerrarModal();
            const item = data.item ?? {};
            qs('#tr-empty')?.remove();
            if (esEdicion && seleccionada) {
                pintarFila(seleccionada, item);
            } else {
                const par = tbody.querySelectorAll('tr.actividad-row').length % 2 === 0;
                const tr = tbody.insertRow();
                tr.className = `actividad-row border-b border-gray-100 hover:bg-gray-50 cursor-pointer ${par ? 'bg-gray-50/50' : ''}`;
                tr.dataset.id = String(item.Id ?? '');
                pintarFila(tr, item);
            }
            ordenar();
            notify.success(data.message || 'Guardado');
        } catch (err) {
            notify.error(err instanceof Error && err.message ? err.message : 'Error de conexión');
        } finally {
            guardar.disabled = false;
        }
    });

    estadoAcciones();
});
