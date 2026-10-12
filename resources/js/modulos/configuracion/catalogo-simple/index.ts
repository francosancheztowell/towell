/**
 * Catálogo simple de Configuración: tabla con selección de fila + Nuevo/Editar/Eliminar del navbar
 * y un modal con formulario. Lo usan Departamentos y Secuencia de folios (antes el mismo <script>
 * inline copiado en las dos vistas: configuracion/departamentos y configuracion/secuencia-de-folios).
 *
 * La vista pone en la <table> data-crud='@json([...])' (ConfigCrud) y usa los ids fijos de abajo.
 * Cada fila: <tr data-id data-<campo en minúsculas>>, celdas Id + una por campo.
 * Los endpoints responden { ok, message?, item?, errors? }.
 */
import { onReady } from '../../../utils/dom.ts';

interface ConfigCrud {
    store: string;
    /** URLs con ':id' en lugar del id. */
    update: string;
    destroy: string;
    /** Campos en el orden de las columnas (= name de los inputs). */
    campos: string[];
    /** Llave del id en el item que devuelve el servidor ('id' o 'Id'). */
    llaveId: string;
    tituloNuevo: string;
    tituloEditar: string;
    tituloEliminar: string;
    /** Campo cuyo valor nombra el registro al confirmar el borrado. */
    campoNombre: string;
    nombrePorDefecto: string;
    vacio: string;
}

interface Respuesta {
    ok?: boolean;
    message?: string;
    item?: Record<string, unknown>;
    errors?: Record<string, string[]>;
}

const CLASES_SELECCION = ['selected', 'bg-blue-500!', 'text-white', 'hover:bg-blue-600'];

/** El servidor responde 4xx con el mismo JSON { ok:false, message }; sin status es fallo de red. */
async function enviar(url: string, datos: FormData): Promise<Respuesta> {
    try {
        return await window.http.post<Respuesta>(url, datos);
    } catch (err) {
        const e = err as { status?: number; data?: Respuesta | null };
        if (!e.status) throw err;
        return e.data ?? {};
    }
}

function mensajeFallo(r: Respuesta, porDefecto: string): string {
    const errores = Object.values(r.errors ?? {}).flat();
    return errores.length ? errores.join(' ') : r.message || porDefecto;
}

function texto(valor: unknown): string {
    return valor === null || valor === undefined || valor === '' ? '—' : String(valor);
}

function iniciar(tabla: HTMLTableElement, cfg: ConfigCrud): void {
    const tbody = tabla.tBodies[0]!;
    const modal = document.getElementById('modal-form')!;
    const form = modal.querySelector('form')!;
    const titulo = document.getElementById('modal-title')!;
    const metodo = document.getElementById('form-method') as HTMLInputElement;
    const btnEditar = document.getElementById('btn-editar') as HTMLButtonElement;
    const btnEliminar = document.getElementById('btn-eliminar') as HTMLButtonElement;
    const btnGuardar = document.getElementById('form-submit') as HTMLButtonElement;
    const input = (campo: string): HTMLInputElement | null => form.querySelector<HTMLInputElement>(`[name="${campo}"]`);
    const url = (plantilla: string, id: string): string => plantilla.replace(':id', encodeURIComponent(id));
    let fila: HTMLTableRowElement | null = null;

    const abrirModal = (): void => void modal.classList.replace('hidden', 'flex');
    const cerrarModal = (): void => void modal.classList.replace('flex', 'hidden');

    function seleccionar(tr: HTMLTableRowElement | null): void {
        fila?.classList.remove(...CLASES_SELECCION);
        fila?.classList.add('hover:bg-gray-50');
        fila?.querySelectorAll('td').forEach((td) => td.classList.remove('text-white'));
        fila = tr;
        tr?.classList.remove('hover:bg-gray-50');
        tr?.classList.add(...CLASES_SELECCION);
        tr?.querySelectorAll('td').forEach((td) => td.classList.add('text-white'));
        btnEditar.disabled = btnEliminar.disabled = !tr;
    }

    function pintarFila(tr: HTMLTableRowElement, item: Record<string, unknown>): void {
        tr.dataset.id = String(item[cfg.llaveId] ?? '');
        const valores = [item[cfg.llaveId], ...cfg.campos.map((c) => item[c])];
        cfg.campos.forEach((c) => (tr.dataset[c.toLowerCase()] = String(item[c] ?? '')));
        valores.forEach((v, i) => {
            const td = tr.cells[i] ?? tr.insertCell();
            if (!td.className) td.className = `px-4 py-3 text-base ${i === 1 ? 'font-medium text-gray-900' : 'text-gray-700'}`;
            td.textContent = i === 0 ? String(v ?? '') : texto(v);
        });
    }

    function filaVacia(): void {
        if (tbody.querySelector('tr[data-id]')) return;
        const td = tbody.insertRow().insertCell();
        td.colSpan = cfg.campos.length + 1;
        td.className = 'px-4 py-8 text-center text-gray-500 text-base';
        td.textContent = cfg.vacio;
        td.parentElement!.id = 'tr-empty';
    }

    tbody.addEventListener('click', (e) => {
        const tr = (e.target as Element).closest<HTMLTableRowElement>('tr[data-id]');
        if (tr) seleccionar(tr);
    });

    document.getElementById('btn-nuevo')?.addEventListener('click', () => {
        titulo.textContent = cfg.tituloNuevo;
        form.action = cfg.store;
        form.reset();
        metodo.value = 'POST';
        abrirModal();
    });

    btnEditar.addEventListener('click', () => {
        if (!fila) return;
        titulo.textContent = cfg.tituloEditar;
        form.action = url(cfg.update, fila.dataset.id ?? '');
        metodo.value = 'PUT';
        cfg.campos.forEach((c) => {
            const el = input(c);
            if (el) el.value = fila!.dataset[c.toLowerCase()] ?? '';
        });
        abrirModal();
    });

    btnEliminar.addEventListener('click', async () => {
        const tr = fila;
        if (!tr) return;
        const nombre = tr.dataset[cfg.campoNombre.toLowerCase()] || cfg.nombrePorDefecto;
        const ok = await window.notify.confirm({
            title: cfg.tituloEliminar,
            text: `Se eliminará "${nombre}".`,
            icon: 'warning',
            confirmColor: '#dc2626',
            confirmText: 'Sí, eliminar',
        });
        if (!ok) return;
        const datos = new FormData();
        datos.append('_method', 'DELETE');
        try {
            const r = await enviar(url(cfg.destroy, tr.dataset.id ?? ''), datos);
            if (!r.ok) return void window.notify.alert('', mensajeFallo(r, 'No se pudo eliminar'), 'error');
            seleccionar(null);
            tr.remove();
            filaVacia();
            window.notify.success(r.message || 'Eliminado');
        } catch {
            void window.notify.alert('', 'Error de conexión', 'error');
        }
    });

    document.getElementById('modal-cerrar')?.addEventListener('click', cerrarModal);
    document.getElementById('form-cancelar')?.addEventListener('click', cerrarModal);

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const editando = metodo.value === 'PUT' && fila !== null;
        btnGuardar.disabled = true;
        try {
            const r = await enviar(form.action, new FormData(form));
            if (!r.ok) return void window.notify.alert('', mensajeFallo(r, 'No se pudo guardar'), 'error');
            cerrarModal();
            if (editando) {
                pintarFila(fila!, r.item ?? {});
            } else {
                document.getElementById('tr-empty')?.remove();
                const pares = tbody.querySelectorAll('tr[data-id]').length % 2 === 0;
                const tr = tbody.insertRow();
                tr.className = `border-b border-gray-100 hover:bg-gray-50 cursor-pointer ${pares ? 'bg-gray-50/50' : ''}`;
                pintarFila(tr, r.item ?? {});
            }
            window.notify.success(r.message || 'Guardado');
        } catch {
            void window.notify.alert('', 'Error de conexión', 'error');
        } finally {
            btnGuardar.disabled = false;
        }
    });

    seleccionar(null);
}

onReady(() => {
    const tabla = document.querySelector<HTMLTableElement>('table[data-crud]');
    if (!tabla?.dataset.crud) return;
    iniciar(tabla, JSON.parse(tabla.dataset.crud) as ConfigCrud);
});
