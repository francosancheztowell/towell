/**
 * Utilería — modal "Finalizar Órdenes": telar → órdenes en proceso → finalizar las elegidas
 * (EnProceso = 0 y FechaFinaliza = ahora, en el servidor).
 */
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { aviso, llenarTelares, nodo, postConResultado, procesando } from './comun.ts';
import { claveId, tieneSeleccionSinProduccion, textoSeleccion, type ConfigUtileria, type Telar } from './logica.ts';

interface OrdenFin {
    id: number | string;
    noOrden?: string;
    fechaCambio?: string;
    tamanoClave?: string;
    modelo?: string;
    enProceso?: boolean;
    produccion?: number | string | null;
    totalPedido?: number | string | null;
    saldoPedido?: number | string | null;
}

const estado = {
    telares: [] as Telar[],
    ordenes: [] as OrdenFin[],
    seleccion: new Set<number | string>(),
};

let rutas: ConfigUtileria['finalizar'];

const $ = <T extends HTMLElement = HTMLElement>(id: string) => document.getElementById(id) as T;

function reset(): void {
    estado.telares = [];
    estado.ordenes = [];
    estado.seleccion.clear();
    $<HTMLSelectElement>('finalizarSelectTelar').replaceChildren(new Option('Selecciona un telar', ''));
    $('finalizarTablaContainer').classList.add('hidden');
    $('finalizarEmpty').classList.add('hidden');
    $('finalizarLoader').classList.add('hidden');
    $('finalizarTbody').replaceChildren();
    $('finalizarSeleccionados').textContent = '';
    $('finalizarContador').textContent = '';
    $<HTMLButtonElement>('btnFinalizarConfirm').disabled = true;
}

async function cargarTelares(): Promise<void> {
    try {
        const data = await http.get<{ success?: boolean; telares?: Telar[] }>(rutas.telares);
        if (data?.success && Array.isArray(data.telares)) {
            estado.telares = data.telares;
            llenarTelares($<HTMLSelectElement>('finalizarSelectTelar'), data.telares);
        }
    } catch (e) {
        console.error('Error cargando telares para finalizar:', e);
    }
}

export async function abrirModalFinalizar(): Promise<void> {
    $('modalFinalizar').style.display = 'flex';
    reset();
    await cargarTelares();
}

function cerrarModalFinalizar(): void {
    $('modalFinalizar').style.display = 'none';
    reset();
}

function actualizarPie(): void {
    const count = estado.seleccion.size;
    const sinProd = count > 0 && tieneSeleccionSinProduccion(estado.seleccion, estado.ordenes);
    $('finalizarSeleccionados').textContent = textoSeleccion(count, sinProd);
    $<HTMLButtonElement>('btnFinalizarConfirm').disabled = count === 0 || sinProd;
}

const numero = (v: number | string | null | undefined) => (v != null ? Number(v).toLocaleString('es-MX') : '-');
const etiqueta = (clase: string, texto: string) => nodo('span', `text-xs ${clase} text-white px-1.5 py-0.5 rounded font-medium whitespace-nowrap inline-block ml-1`, texto);

function filaOrden(o: OrdenFin, i: number): HTMLTableRowElement {
    const rowBg = o.enProceso ? 'bg-amber-50' : i % 2 === 0 ? 'bg-white' : 'bg-gray-50';
    const tr = nodo('tr', `${rowBg}${o.enProceso ? ' border-l-4 border-l-amber-400' : ''} hover:bg-gray-100 transition-colors cursor-pointer`);
    tr.dataset.id = String(o.id);

    const cb = nodo('input', 'finalizar-check w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-400');
    cb.type = 'checkbox';
    cb.dataset.id = String(o.id);
    cb.checked = estado.seleccion.has(claveId(o.id));
    cb.setAttribute('aria-label', `Seleccionar orden ${o.noOrden ?? ''}`.trim());
    const tdCheck = nodo('td', 'px-3 py-2');
    tdCheck.appendChild(cb);

    const tdOrden = nodo('td', 'px-3 py-2 font-medium text-gray-800 whitespace-nowrap', o.noOrden || '');
    if (o.enProceso) tdOrden.append(' ', etiqueta('bg-amber-500', 'En proceso'));
    if ((o.modelo || '').toUpperCase().includes('REPASO1')) tdOrden.append(' ', etiqueta('bg-red-500', 'Repaso'));

    const saldo = o.saldoPedido != null ? Number(o.saldoPedido) : null;
    const tdSaldo = nodo('td', 'px-3 py-2 text-right tabular-nums');
    tdSaldo.appendChild(saldo !== null && saldo < 0
        ? nodo('span', 'text-xs bg-red-500 text-white px-1.5 py-0.5 rounded font-medium', saldo.toLocaleString('es-MX'))
        : nodo('span', 'text-gray-600', saldo === null ? '-' : saldo.toLocaleString('es-MX')));

    tr.append(
        tdCheck,
        tdOrden,
        nodo('td', 'px-3 py-2 text-gray-600 whitespace-nowrap', o.fechaCambio || ''),
        nodo('td', 'px-3 py-2 text-gray-600', o.tamanoClave || ''),
        nodo('td', 'px-3 py-2 text-gray-600', o.modelo || ''),
        nodo('td', 'px-3 py-2 text-right text-gray-600 tabular-nums', numero(o.totalPedido)),
        nodo('td', 'px-3 py-2 text-right text-gray-600 tabular-nums', numero(o.produccion)),
        tdSaldo,
    );
    return tr;
}

async function cargarOrdenes(): Promise<void> {
    const idx = $<HTMLSelectElement>('finalizarSelectTelar').value;
    estado.ordenes = [];
    estado.seleccion.clear();

    if (idx === '') {
        $('finalizarTablaContainer').classList.add('hidden');
        $('finalizarEmpty').classList.add('hidden');
        actualizarPie();
        return;
    }
    const telar = estado.telares[parseInt(idx, 10)];
    if (!telar) return;

    $('finalizarLoader').classList.remove('hidden');
    $('finalizarTablaContainer').classList.add('hidden');
    $('finalizarEmpty').classList.add('hidden');

    try {
        const data = await http.get<{ success?: boolean; ordenes?: OrdenFin[] }>(rutas.ordenes, { params: { salon: telar.salon, telar: telar.telar } });
        $('finalizarLoader').classList.add('hidden');
        if (data?.success && Array.isArray(data.ordenes) && data.ordenes.length > 0) {
            estado.ordenes = data.ordenes;
            $('finalizarTbody').replaceChildren(...data.ordenes.map(filaOrden));
            $('finalizarTablaContainer').classList.remove('hidden');
            $('finalizarContador').textContent = '(' + data.ordenes.length + ')';
        } else {
            $('finalizarEmpty').classList.remove('hidden');
            $('finalizarContador').textContent = '';
        }
    } catch (e) {
        $('finalizarLoader').classList.add('hidden');
        console.error('Error cargando órdenes:', e);
    }
    actualizarPie();
}

function alternar(id: string): void {
    const key = claveId(id);
    if (estado.seleccion.has(key)) estado.seleccion.delete(key);
    else estado.seleccion.add(key);
    document.querySelectorAll<HTMLInputElement>('.finalizar-check').forEach((cb) => {
        cb.checked = estado.seleccion.has(claveId(cb.dataset.id ?? ''));
    });
    actualizarPie();
}

async function ejecutarFinalizar(): Promise<void> {
    procesando('Finalizando órdenes seleccionadas');
    const data = await postConResultado(http.post<{ success?: boolean; message?: string }>(rutas.procesar, { ids: Array.from(estado.seleccion) }));
    if (!data) {
        void aviso('error', 'Error de conexión', 'No se pudo comunicar con el servidor', '#ef4444');
        return;
    }
    if (data.success) {
        void aviso('success', 'Órdenes finalizadas', data.message ?? '', '#16a34a', 2500);
        void cargarOrdenes(); // recargar las órdenes del telar actual
    } else {
        void aviso('error', 'Error', data.message || 'No se pudieron finalizar las órdenes', '#ef4444');
    }
}

async function confirmarFinalizar(): Promise<void> {
    const count = estado.seleccion.size;
    if (count === 0 || tieneSeleccionSinProduccion(estado.seleccion, estado.ordenes)) return;
    const ok = await notify.confirm({
        title: '¿Finalizar órdenes?',
        html: 'Se finalizarán <strong>' + count + '</strong> orden(es) de producción.',
        icon: 'question',
        confirmText: 'Sí, finalizar',
        confirmColor: '#16a34a',
    });
    if (ok) await ejecutarFinalizar();
}

export function iniciarFinalizar(r: ConfigUtileria['finalizar']): void {
    rutas = r;
    const modal = $('modalFinalizar');
    if (!modal) return;

    $('finalizarSelectTelar').addEventListener('change', () => { void cargarOrdenes(); });
    $('btnFinalizarConfirm').addEventListener('click', () => { void confirmarFinalizar(); });
    modal.querySelector('[data-accion-modal="cerrar"]')?.addEventListener('click', cerrarModalFinalizar);
    // Clic en la fila o en su casilla: alterna la selección.
    $('finalizarTbody').addEventListener('click', (e) => {
        const tr = (e.target as Element).closest<HTMLElement>('tr[data-id]');
        if (tr?.dataset.id) alternar(tr.dataset.id);
    });
    // Cerrar al hacer clic fuera del panel.
    modal.addEventListener('click', (e) => { if (e.target === modal) cerrarModalFinalizar(); });
}
