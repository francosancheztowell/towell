// Detalle de líneas diarias (tabla y modal). Vivía inline en
// components/programa-tejido/req-programa-tejido-line-table.blade.php (HANDOFF PT B1).
// Solo se usa desde fuera por window.loadReqProgramaTejidoLines / window.openLinesModal.
import {
    COLUMNAS_LINEA,
    fechaLinea,
    formatoEntero,
    lineasDeRespuesta,
    totalesLineas,
    type LineaDiaria,
} from './lineas-logica.ts';
import { notify } from '../utils/notifications.ts';
import { el, icono, spinner } from './nodos.ts';
import { esCancelacion, statusDelError } from './respuesta.ts';
import { rutaSuperficie } from './rutas.ts';

const URL_LINEAS = '/planeacion/req-programa-tejido-line';

// ===== Tabla bajo la grilla =====

// Controlador para cancelar peticiones anteriores
let currentAbortController: AbortController | null = null;
let currentRequestId = 0;

function filaMensaje(contenido: Node, clase = 'px-3 py-6'): HTMLTableRowElement {
    return el('tr', {}, el('td', { clase, attrs: { colspan: '14' } }, contenido));
}

function avisoTabla(claseColor: string, detalle: string): HTMLTableRowElement {
    return filaMensaje(
        el('div', { clase: `max-w-xl mx-auto  ${claseColor} rounded-md p-4 text-sm text-center` },
            el('div', { clase: 'font-semibold mb-1', texto: 'No se pudo cargar el detalle' }),
            el('div', { texto: detalle })),
    );
}

function filaLinea(it: LineaDiaria): HTMLTableRowElement {
    return el('tr', { clase: 'hover:bg-blue-50' },
        el('td', { clase: 'px-2 py-1 text-xs', texto: fechaLinea(it.Fecha) }),
        ...COLUMNAS_LINEA.map((c) => el('td', { clase: 'px-2 py-1 text-xs text-right', texto: formatoEntero(it[c]) })));
}

async function loadReqProgramaTejidoLines(params: Record<string, string> = {}): Promise<void> {
    const wrap = document.getElementById('reqpt-line-wrapper');
    const body = document.getElementById('reqpt-line-body');
    const meta = document.getElementById('reqpt-line-meta');
    if (!wrap || !body) return;
    const ponerMeta = (texto: string) => { if (meta) meta.textContent = texto; };

    currentAbortController?.abort();
    currentAbortController = new AbortController();
    const requestId = ++currentRequestId;

    const qs = new URLSearchParams(params).toString();
    const url = rutaSuperficie(URL_LINEAS + (qs ? '?' + qs : ''));

    body.replaceChildren(filaMensaje(
        spinner('w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin', 'Cargando...'),
        'px-3 py-4 text-center text-sm text-gray-500',
    ));
    wrap.classList.remove('hidden');

    try {
        const data = await http.get<unknown>(url, { signal: currentAbortController.signal });

        // Ignorar la respuesta si hay una petición más reciente
        if (requestId !== currentRequestId) return;

        const items = lineasDeRespuesta(data);
        if (!items || items.length === 0) {
            body.replaceChildren(filaMensaje(document.createTextNode('Sin líneas registradas'),
                'px-3 py-6 text-center text-sm text-gray-500'));
            ponerMeta('0 registros');
            return;
        }

        body.replaceChildren(...items.map(filaLinea));
        ponerMeta(`${items.length} registro(s)`);
    } catch (e) {
        if (esCancelacion(e) || requestId !== currentRequestId) return;
        // Con respuesta del servidor (4xx/5xx) o sin red: dos avisos distintos, como antes.
        body.replaceChildren(statusDelError(e) > 0
            ? avisoTabla('text-blue-800', 'Intenta nuevamente más tarde.')
            : avisoTabla('text-red-700', 'Por favor verifica tu conexión e inténtalo de nuevo.'));
        ponerMeta('');
    }
}

// ===== Modal "Detalle del Telar" =====
let currentLinesAbortController: AbortController | null = null;
let currentLinesRequestId = 0;

const CLASE_TH = 'px-3 py-2 text-right text-xs font-normal  tracking-wider whitespace-nowrap';
const ENCABEZADOS = ['Piezas', 'Kilos', 'Aplicación', 'Trama', 'Comb 1', 'Comb 2', 'Comb 3', 'Comb 4', 'Comb 5',
    'Rizo', 'Pie', 'Mts/Pie', 'Mts/Rizo'];

function estadoModal(fondo: string, clasesIcono: string, titulo: string, claseTitulo: string, detalle: string, claseDetalle: string): HTMLElement {
    return el('div', { clase: 'text-center py-12' },
        el('div', { clase: `inline-flex items-center justify-center w-16 h-16 rounded-full ${fondo} mb-4` }, icono(clasesIcono)),
        el('div', { clase: claseTitulo, texto: titulo }),
        el('div', { clase: claseDetalle, texto: detalle }));
}

const errorModal = (detalle: string) => estadoModal('bg-red-100', 'fa-solid fa-exclamation-triangle text-red-500 text-2xl',
    'No se pudo cargar el detalle', 'text-red-600 font-semibold text-base mb-2', detalle, 'text-gray-500 text-sm');

function tablaModal(items: LineaDiaria[]): HTMLElement {
    const totales = totalesLineas(items);
    const celda = 'px-3 py-2 text-xs font-normal text-right text-gray-700 whitespace-nowrap';
    const celdaTotal = 'px-3 py-2 text-xs font-semibold text-right text-blue-700 whitespace-nowrap bg-blue-50';

    const filas = items.map((it, idx) => el('tr', {
        clase: `modal-table-row ${idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'} transition-colors cursor-pointer`,
        attrs: { 'data-row-index': String(idx) },
    },
    el('td', { clase: 'px-3 py-2 text-xs font-normal text-gray-900 whitespace-nowrap', texto: fechaLinea(it.Fecha) }),
    ...COLUMNAS_LINEA.map((c) => el('td', { clase: celda, texto: formatoEntero(it[c]) }))));

    const tabla = el('table', { clase: 'min-w-full divide-y divide-gray-200' },
        el('thead', { clase: 'bg-blue-500 text-white sticky top-0 z-10' },
            el('tr', {},
                el('th', { clase: 'px-3 py-2 text-left text-xs font-normal  tracking-wider whitespace-nowrap', texto: 'Fecha' }),
                ...ENCABEZADOS.map((t) => el('th', { clase: CLASE_TH, texto: t })))),
        el('tbody', { clase: 'bg-white divide-y divide-gray-200' }, ...filas),
        el('tfoot', { clase: 'bg-blue-50 border-t-2 border-blue-300 sticky bottom-0 z-10' },
            el('tr', {},
                el('td', { clase: 'px-3 py-2 text-xs font-semibold text-gray-900 whitespace-nowrap bg-blue-50', texto: 'TOTAL' }),
                // Total 0 → &nbsp; (celda vacía que conserva la altura).
                ...COLUMNAS_LINEA.map((c) => el('td', { clase: celdaTotal, texto: totales[c] === 0 ? ' ' : formatoEntero(totales[c]) })))));

    const scroll = el('div', { clase: 'overflow-x-auto relative' }, tabla);
    scroll.style.maxHeight = '500px';
    scroll.style.overflowY = 'auto';

    enlazarSeleccion(filas);

    return el('div', { clase: 'bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden' },
        scroll,
        el('div', { clase: 'bg-gray-50 px-3 py-1 border-t border-gray-200' },
            el('span', { clase: 'text-xs text-gray-500', texto: `${items.length} registro(s)` })));
}

/** Hover y selección de una fila del modal (azul), igual que antes. */
function enlazarSeleccion(rows: HTMLTableRowElement[]): void {
    rows.forEach((row) => {
        row.addEventListener('mouseenter', () => {
            row.style.backgroundColor = row.classList.contains('bg-blue-500') ? '#1d4ed8' : '#dbeafe';
        });
        row.addEventListener('mouseleave', () => {
            row.style.backgroundColor = row.classList.contains('bg-blue-500') ? '#3b82f6' : '';
        });
        row.addEventListener('click', () => {
            rows.forEach((r) => {
                r.classList.remove('bg-blue-500', 'text-white');
                r.classList.add(Number(r.dataset.rowIndex) % 2 === 0 ? 'bg-white' : 'bg-gray-50');
                r.style.backgroundColor = '';
                r.querySelectorAll('td').forEach((cell) => {
                    cell.classList.remove('text-white');
                    cell.classList.add('text-gray-700', 'text-gray-900');
                });
            });
            row.classList.add('bg-blue-500', 'text-white');
            row.classList.remove('bg-white', 'bg-gray-50');
            row.style.backgroundColor = '#3b82f6';
            row.querySelectorAll('td').forEach((cell) => {
                cell.classList.add('text-white');
                cell.classList.remove('text-gray-700', 'text-gray-900');
            });
        });
    });
}

async function cargarModal(programaId: string | number, requestId: number, signal: AbortSignal): Promise<void> {
    const contenido = () => (requestId === currentLinesRequestId ? document.getElementById('lines-modal-content') : null);
    try {
        const data = await http.get<unknown>(rutaSuperficie(`${URL_LINEAS}?programa_id=${programaId}`), { signal });
        const content = contenido();
        if (!content) return;

        const items = lineasDeRespuesta(data);
        if (!items || items.length === 0) {
            content.replaceChildren(estadoModal('bg-gray-100', 'fa-solid fa-inbox text-gray-400 text-2xl',
                'Sin líneas registradas', 'text-gray-600 font-medium text-base',
                'No hay fechas de tejido para este registro', 'text-gray-400 text-sm mt-1'));
            return;
        }
        content.replaceChildren(tablaModal(items));
    } catch (e) {
        if (esCancelacion(e)) return;
        contenido()?.replaceChildren(statusDelError(e) > 0
            ? errorModal('Intenta nuevamente más tarde.')
            : errorModal('Por favor verifica tu conexión e inténtalo de nuevo.'));
    }
}

function openLinesModal(programaId: string | number): void {
    currentLinesAbortController?.abort();
    const controlador = new AbortController();
    currentLinesAbortController = controlador;
    const requestId = ++currentLinesRequestId;

    const cargando = el('div', { attrs: { id: 'lines-modal-content' }, clase: 'w-full' },
        el('div', { clase: 'flex items-center justify-center py-12' },
            el('div', { clase: 'flex flex-col items-center gap-3' },
                el('div', { clase: 'w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full animate-spin' }),
                el('span', { clase: 'text-gray-700 font-medium', texto: 'Cargando detalle del telar...' }))));

    // Modal propio con tabla, totales y selección (solo lectura: botón Cerrar, Esc o clic fuera).
    void notify.dialog({
        titulo: 'Detalle del Telar',
        html: '<div id="lines-modal-content" class="w-full"></div>',
        tono: null,
        ancho: '2xl',
        botones: [{ texto: 'Cerrar', valor: 'cerrar', variante: 'secundario' }],
        alAbrir: (root) => {
            root.replaceChildren(cargando);
            void cargarModal(programaId, requestId, controlador.signal);
        },
    }).then(() => {
        // Cancelar petición pendiente al cerrar el modal (si no la reemplazó otro modal ya abierto).
        if (currentLinesAbortController !== controlador) return;
        controlador.abort();
        currentLinesAbortController = null;
    });
}

// PUENTE PT-TS 1: index.js (selección de fila y botón "Ver líneas") los llama.
window.loadReqProgramaTejidoLines = loadReqProgramaTejidoLines;
window.openLinesModal = openLinesModal;
