// Modal "Actualizar calendarios" (menú Actualizar del navbar de la grilla).
import { mensajeDelServidor } from '../respuesta.ts';
import { rutaSuperficie } from '../rutas.ts';

interface Calendario {
    CalendarioId: string;
    Nombre?: string | null;
}

interface RegistroCalendario {
    Id: number | string;
    NoTelarId?: string | null;
    NombreProducto?: string | null;
}

interface Respuesta<T> {
    success?: boolean;
    message?: string;
    data?: T;
}

const porId = <T extends HTMLElement>(id: string) => document.getElementById(id) as T | null;

function conLoader<T>(tarea: () => Promise<T>): Promise<T> {
    window.PT?.loader?.show();
    return tarea().finally(() => window.PT?.loader?.hide());
}

/** Error propio con el texto que el usuario ya veía ("No se pudieron obtener…"). */
class ErrorCalendarios extends Error {}

function detalle(error: unknown, porDefecto: string): string {
    return error instanceof ErrorCalendarios ? error.message : mensajeDelServidor(error, porDefecto);
}

async function cargarCalendariosEnSelect(): Promise<void> {
    const select = porId<HTMLSelectElement>('selectCalendario');
    if (!select) return;

    try {
        await conLoader(async () => {
            const result = await http.get<Respuesta<Calendario[]>>('/planeacion/calendarios/json');
            if (!result?.success || !result.data) throw new ErrorCalendarios('No se pudieron obtener los calendarios');

            select.replaceChildren(new Option('Seleccione un calendario...', ''));
            result.data.forEach((calendario) => {
                select.appendChild(new Option(`${calendario.CalendarioId} - ${calendario.Nombre}`, calendario.CalendarioId));
            });
        });
    } catch (error) {
        console.error('Error al cargar calendarios:', error);
        notify.error('Error al cargar los calendarios: ' + detalle(error, 'Error al obtener calendarios'));
    }
}

function filaRegistro(registro: RegistroCalendario): HTMLTableRowElement {
    const row = document.createElement('tr');
    row.className = 'hover:bg-blue-50 transition';
    row.dataset.registroId = String(registro.Id);

    const tdCheckbox = document.createElement('td');
    tdCheckbox.className = 'px-3 py-2';
    const check = document.createElement('input');
    check.type = 'checkbox';
    check.className = 'checkbox-registro w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500';
    check.dataset.registroId = String(registro.Id);
    check.checked = true;
    check.title = 'Seleccionar';
    tdCheckbox.appendChild(check);

    const tdTelares = document.createElement('td');
    tdTelares.className = 'px-3 py-2 text-sm font-semibold text-gray-800';
    tdTelares.textContent = registro.NoTelarId || '';

    const tdProducto = document.createElement('td');
    tdProducto.className = 'px-3 py-2 text-sm text-gray-700';
    tdProducto.textContent = registro.NombreProducto || '';

    row.append(tdCheckbox, tdTelares, tdProducto);
    return row;
}

async function cargarRegistrosEnTabla(): Promise<void> {
    const tbody = porId<HTMLTableSectionElement>('tbodyRegistros');
    if (!tbody) return;

    try {
        await conLoader(async () => {
            const result = await http.get<Respuesta<RegistroCalendario[]>>(
                rutaSuperficie('/planeacion/programa-tejido/all-registros-json'),
            );
            if (!result?.success || !result.data) throw new ErrorCalendarios('No se pudieron obtener los registros');

            tbody.replaceChildren(...result.data.map(filaRegistro));

            if (result.data.length === 0) {
                const row = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 3;
                td.className = 'px-3 py-4 text-center text-gray-500';
                td.textContent = 'No hay registros disponibles';
                row.appendChild(td);
                tbody.appendChild(row);
            }

            const selectAll = porId<HTMLInputElement>('selectAllRegistros');
            if (selectAll) {
                selectAll.checked = true; // Por defecto todos seleccionados
                selectAll.onchange = () => {
                    document.querySelectorAll<HTMLInputElement>('.checkbox-registro').forEach((cb) => {
                        cb.checked = selectAll.checked;
                    });
                };
            }
        });
    } catch (error) {
        console.error('Error al cargar registros:', error);
        notify.error('Error al cargar los registros: ' + detalle(error, 'Error al obtener registros'));
    }
}

async function abrirModalActCalendarios(): Promise<void> {
    const modal = porId('modalActCalendarios');
    if (!modal) {
        console.error('Modal no encontrado');
        return;
    }

    porId('actualizarDropdownMenu')?.classList.add('hidden');

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    await Promise.all([cargarCalendariosEnSelect(), cargarRegistrosEnTabla()]);
}

function cerrarModalActCalendarios(): void {
    const modal = porId('modalActCalendarios');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

async function guardarCalendariosSeleccionados(): Promise<void> {
    const calendarioId = porId<HTMLSelectElement>('selectCalendario')?.value;
    if (!calendarioId) {
        notify.warning('Por favor selecciona un calendario');
        return;
    }

    const registrosSeleccionados = Array.from(
        document.querySelectorAll<HTMLInputElement>('.checkbox-registro:checked'),
        (cb) => cb.dataset.registroId,
    );
    if (registrosSeleccionados.length === 0) {
        notify.warning('Por favor selecciona al menos un registro');
        return;
    }

    try {
        await conLoader(async () => {
            const result = await http.post<Respuesta<{ tiempo_segundos?: number }>>(
                rutaSuperficie('/planeacion/programa-tejido/actualizar-calendarios-masivo'),
                { calendario_id: calendarioId, registros_ids: registrosSeleccionados },
            );
            if (!result?.success) throw new ErrorCalendarios(result?.message || 'Error al actualizar los calendarios');

            const tiempoMsg = result.data?.tiempo_segundos ? ` en ${result.data.tiempo_segundos}s` : '';
            notify.success(
                result.message ||
                    `Se actualizaron ${registrosSeleccionados.length} registro(s) con el calendario ${calendarioId}${tiempoMsg}`,
            );
            cerrarModalActCalendarios();
            // Recargar la página para reflejar los cambios
            setTimeout(() => window.location.reload(), 500);
        });
    } catch (error) {
        console.error('Error al actualizar calendarios:', error);
        notify.error('Error al actualizar los calendarios: ' + detalle(error, 'Error al actualizar los calendarios'));
    }
}

// PUENTE PT-TS 1: index.js (menú Actualizar → Calendarios) abre el modal.
window.abrirModalActCalendarios = abrirModalActCalendarios;
// PUENTE PT-TS 1: x-ui.modal-base (onclose del botón × y Esc) en modal/act-calendarios.blade.php.
window.cerrarModalActCalendarios = cerrarModalActCalendarios;

document.addEventListener('click', (e) => {
    const objetivo = e.target as Element | null;
    if (objetivo?.closest?.('#btnGuardarCalendarios')) void guardarCalendariosSeleccionados();
    else if (objetivo?.closest?.('#btnCancelarCalendarios')) cerrarModalActCalendarios();
    // Cerrar al hacer clic fuera del panel (el propio <dialog>).
    else if (objetivo && objetivo === porId('modalActCalendarios')) cerrarModalActCalendarios();
});
