/**
 * Utilería — modal "Mover Órdenes": dos paneles (origen/destino) con arrastrar y soltar para
 * reordenar o pasar órdenes de un telar a otro; nada se guarda hasta "Guardar".
 */
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { aviso, icono, llenarTelares, nodo, postConResultado, procesando } from './comun.ts';
import { hayCambios, mismoTelar, moverRegistro, tipoSalonDisplay, type ConfigUtileria, type Telar } from './logica.ts';

type Panel = 'origen' | 'destino';

interface RegistroMover {
    id: number;
    noOrden?: string;
    tamanoClave?: string;
    modelo?: string;
    telar?: string;
    produccion?: number;
    enProceso?: boolean;
    esRepaso1?: boolean;
    isMoved?: boolean;
}

const estado = {
    telares: [] as Telar[],
    origenTelar: null as Telar | null,
    destinoTelar: null as Telar | null,
    origenRegistros: [] as RegistroMover[],
    destinoRegistros: [] as RegistroMover[],
    originalOrigenIds: [] as number[],
    originalDestinoIds: [] as number[],
    hasChanges: false,
    arrastrado: null as { panel: Panel; id: number } | null,
};

let rutas: ConfigUtileria['mover'];

const $ = <T extends HTMLElement = HTMLElement>(id: string) => document.getElementById(id) as T;
const cap = (p: Panel) => (p === 'origen' ? 'Origen' : 'Destino');
const registrosDe = (p: Panel) => (p === 'origen' ? estado.origenRegistros : estado.destinoRegistros);
const telarDe = (p: Panel) => (p === 'origen' ? estado.origenTelar : estado.destinoTelar);
const ANILLO: Record<Panel, [string, string]> = { origen: ['ring-2', 'ring-amber-300'], destino: ['ring-2', 'ring-blue-300'] };

function quitarAnillos(): void {
    $('panelDestinoContainer').classList.remove(...ANILLO.destino);
    $('panelOrigenContainer').classList.remove(...ANILLO.origen);
}

function quitarMarcasFila(): void {
    document.querySelectorAll('.border-t-2').forEach((el) => el.classList.remove('border-t-2', 'border-blue-500', 'border-amber-500'));
}

/** Un telar no puede ser origen y destino a la vez: se deshabilita en el otro select. */
function syncTelarSelects(): void {
    const selOrigen = $<HTMLSelectElement>('moverSelectOrigen');
    const selDestino = $<HTMLSelectElement>('moverSelectDestino');
    const telar = (v: string) => (v !== '' ? estado.telares[parseInt(v, 10)] : null);
    const tOrigen = telar(selOrigen.value);
    const tDestino = telar(selDestino.value);
    Array.from(selOrigen.options).forEach((opt) => {
        if (opt.value !== '') opt.disabled = !!tDestino && mismoTelar(telar(opt.value), tDestino);
    });
    Array.from(selDestino.options).forEach((opt) => {
        if (opt.value !== '') opt.disabled = !!tOrigen && mismoTelar(telar(opt.value), tOrigen);
    });
}

function actualizarBotones(): void {
    $<HTMLButtonElement>('btnMoverConfirm').disabled = !estado.hasChanges;
    $('btnMoverRevertir').classList.toggle('hidden', !estado.hasChanges);
    const resumen = $('moverResumen');
    if (!estado.hasChanges) {
        resumen.replaceChildren();
        return;
    }
    const aviso = nodo('span', 'text-blue-700 bg-blue-100 px-3 py-1.5 rounded-lg shadow-sm inline-block');
    aviso.append(icono('fas fa-exclamation-triangle mr-3'), 'Hay cambios en el orden o asignación que deben ser guardados');
    resumen.replaceChildren(aviso);
}

function checkIfChanged(): void {
    estado.hasChanges = hayCambios(estado.origenRegistros, estado.originalOrigenIds)
        || hayCambios(estado.destinoRegistros, estado.originalDestinoIds);
    if (!estado.hasChanges) {
        estado.origenRegistros.forEach((r) => { r.isMoved = false; });
        estado.destinoRegistros.forEach((r) => { r.isMoved = false; });
    }
    actualizarBotones();
}

function reset(): void {
    Object.assign(estado, {
        telares: [], origenTelar: null, destinoTelar: null, origenRegistros: [], destinoRegistros: [],
        originalOrigenIds: [], originalDestinoIds: [], hasChanges: false, arrastrado: null,
    });
    ['moverSelectOrigen', 'moverSelectDestino'].forEach((id) => $<HTMLSelectElement>(id).replaceChildren(new Option('Seleccione un telar', '')));
    (['origen', 'destino'] as Panel[]).forEach((p) => {
        $(`mover${cap(p)}Tbody`).replaceChildren();
        $(`mover${cap(p)}Items`).classList.add('hidden');
        $(`mover${cap(p)}Empty`).style.display = '';
        $(`mover${cap(p)}Tipo`).classList.add('hidden');
    });
    $('moverResumen').replaceChildren();
    $<HTMLButtonElement>('btnMoverConfirm').disabled = true;
    $('btnMoverRevertir').classList.add('hidden');
    quitarAnillos();
}

function filaMensaje(td: HTMLTableCellElement): HTMLTableRowElement {
    td.colSpan = 5;
    const tr = nodo('tr');
    tr.appendChild(td);
    return tr;
}

function insignia(clase: string, texto: string, icon?: string): HTMLElement {
    const s = nodo('span', `text-sm ${clase} px-2 py-0.5 rounded-full font-bold ml-2 shadow-sm border`);
    if (icon) s.appendChild(icono(icon));
    s.append(texto);
    return s;
}

function filaRegistro(panel: Panel, r: RegistroMover, i: number): HTMLTableRowElement {
    const isOrigen = panel === 'origen';
    const isNativeOrigen = !!estado.origenTelar && r.telar === estado.origenTelar.telar;
    const isPendingMoveToDestino = !isOrigen && isNativeOrigen;
    const isPendingReorder = isOrigen && !!r.isMoved;
    const isDraggable = isOrigen || isPendingMoveToDestino;

    let clases = 'transition-all duration-200 border-b border-gray-100 '
        + (isPendingMoveToDestino || isPendingReorder ? 'bg-green-50 hover:bg-green-100' : 'hover:bg-gray-50 bg-white')
        + (isDraggable ? ' cursor-grab active:cursor-grabbing hover:shadow-md' : ' opacity-80');
    clases += ' group';

    const tr = nodo('tr', clases);
    tr.dataset.id = String(r.id);
    tr.dataset.panel = panel;
    tr.dataset.indice = String(i);
    if (isDraggable) tr.draggable = true;

    const tdOrden = nodo('td', 'px-3 py-3 font-medium text-gray-800 flex items-center gap-1', r.noOrden || '');
    if (isPendingMoveToDestino) tdOrden.appendChild(insignia('bg-green-200 text-green-800 border-green-300', 'A mover', 'fas fa-arrow-right mr-1'));
    else if (isPendingReorder) tdOrden.appendChild(insignia('bg-green-200 text-green-800 border-green-300', 'Modificado', 'fas fa-arrows-alt-v mr-1'));
    else if (r.enProceso) tdOrden.appendChild(insignia('bg-amber-100 text-amber-800 border-amber-200', 'En proceso'));
    if (r.esRepaso1) tdOrden.appendChild(insignia('bg-red-100 text-red-800 border-red-200', 'Repaso'));

    const tdPos = nodo('td', 'px-2 py-3 align-middle');
    tdPos.appendChild(nodo('div', 'w-5 h-5 rounded-full bg-gray-200 text-gray-600 text-xs flex items-center justify-center font-bold', String(i + 1)));
    const tdModelo = nodo('td', 'px-3 py-3 text-gray-600 align-middle truncate max-w-[150px]', r.modelo || '');
    tdModelo.title = r.modelo || '';

    tr.append(
        tdPos,
        tdOrden,
        nodo('td', 'px-3 py-3 text-gray-600 align-middle', r.tamanoClave || ''),
        tdModelo,
        nodo('td', 'px-3 py-3 text-gray-800 font-semibold align-middle text-right', new Intl.NumberFormat('en-US').format(r.produccion ?? 0)),
    );
    return tr;
}

function renderPanel(panel: Panel): void {
    const registros = registrosDe(panel);
    const telar = telarDe(panel);
    const conDatos = registros.length > 0 || !!telar;
    $(`mover${cap(panel)}Items`).classList.toggle('hidden', !conDatos);
    $(`mover${cap(panel)}Empty`).style.display = conDatos ? 'none' : '';

    const tbody = $(`mover${cap(panel)}Tbody`);
    if (registros.length === 0 && telar) {
        tbody.replaceChildren(filaMensaje(nodo('td', 'px-4 py-12 text-center text-gray-400 bg-white border-2 border-dashed border-gray-200 m-2 rounded-lg block italic', 'Arrastre órdenes aquí')));
        return;
    }
    tbody.replaceChildren(...registros.map((r, i) => filaRegistro(panel, r, i)));
}

async function fetchRegistros(panel: Panel): Promise<void> {
    const telar = telarDe(panel);
    const tbody = $(`mover${cap(panel)}Tbody`);
    if (!telar) return;

    $(`mover${cap(panel)}Empty`).style.display = 'none';
    $(`mover${cap(panel)}Items`).classList.remove('hidden');
    const cargando = nodo('td', 'px-4 py-8 text-center text-gray-400');
    cargando.append(icono('fas fa-spinner fa-spin text-lg'), nodo('p', 'text-sm mt-1', 'Cargando...'));
    tbody.replaceChildren(filaMensaje(cargando));

    try {
        const data = await http.get<{ success?: boolean; registros?: RegistroMover[] }>(rutas.registros, { params: { salon: telar.salon, telar: telar.telar } });
        if (data?.success && Array.isArray(data.registros)) {
            const regs = data.registros.map((r) => ({ ...r, isMoved: false }));
            if (panel === 'origen') {
                estado.origenRegistros = regs;
                estado.originalOrigenIds = regs.map((r) => r.id);
            } else {
                estado.destinoRegistros = regs;
                estado.originalDestinoIds = regs.map((r) => r.id);
            }
            checkIfChanged();
        } else {
            tbody.replaceChildren();
        }
        renderPanel(panel);
    } catch (e) {
        tbody.replaceChildren();
        console.error('Error cargando registros ' + panel + ':', e);
    }
}

async function cargarTelares(): Promise<void> {
    try {
        const data = await http.get<{ success?: boolean; telares?: Telar[] }>(rutas.telares);
        if (data?.success && Array.isArray(data.telares)) {
            estado.telares = data.telares;
            llenarTelares($<HTMLSelectElement>('moverSelectOrigen'), data.telares);
            llenarTelares($<HTMLSelectElement>('moverSelectDestino'), data.telares);
            syncTelarSelects();
        }
    } catch (e) {
        console.error('Error cargando telares para mover:', e);
    }
}

async function cambiarTelar(panel: Panel): Promise<void> {
    if (estado.hasChanges) {
        void notify.alert('Guarde sus cambios antes de cambiar de telar.', 'Cambios sin guardar', 'warning');
        // El select vuelve al telar que sigue en pantalla (antes se quedaba en el nuevo y
        // "Guardar" mandaba el anterior).
        const actual = telarDe(panel);
        $<HTMLSelectElement>(`moverSelect${cap(panel)}`).value = actual ? String(estado.telares.indexOf(actual)) : '';
        syncTelarSelects();
        return;
    }
    const idx = $<HTMLSelectElement>(`moverSelect${cap(panel)}`).value;
    if (panel === 'origen') {
        estado.origenRegistros = [];
        estado.originalOrigenIds = [];
    } else {
        estado.destinoRegistros = [];
        estado.originalDestinoIds = [];
    }

    const tipo = $(`mover${cap(panel)}Tipo`);
    if (idx === '') {
        if (panel === 'origen') estado.origenTelar = null;
        else estado.destinoTelar = null;
        $(`mover${cap(panel)}Tbody`).replaceChildren();
        $(`mover${cap(panel)}Items`).classList.add('hidden');
        $(`mover${cap(panel)}Empty`).style.display = '';
        tipo.classList.add('hidden');
        syncTelarSelects();
        actualizarBotones();
        return;
    }

    const telar = estado.telares[parseInt(idx, 10)] ?? null;
    if (panel === 'origen') estado.origenTelar = telar;
    else estado.destinoTelar = telar;
    tipo.textContent = tipoSalonDisplay(telar?.salon);
    tipo.classList.remove('hidden');
    syncTelarSelects();
    await fetchRegistros(panel);
}

export async function abrirModalMover(): Promise<void> {
    $('modalMover').style.display = 'flex';
    reset();
    await cargarTelares();
}

async function cerrarModalMover(): Promise<void> {
    if (estado.hasChanges) {
        const ok = await notify.confirm({
            title: '¿Cerrar sin guardar?',
            text: 'Hay cambios sin guardar. Si cierra el modal se perderán.',
            confirmText: 'Sí, cerrar',
        });
        if (!ok) return;
    }
    $('modalMover').style.display = 'none';
    reset();
}

async function revertirCambios(): Promise<void> {
    if (!estado.hasChanges) return;
    estado.hasChanges = false;
    estado.origenRegistros = [];
    estado.destinoRegistros = [];
    if (estado.origenTelar) await fetchRegistros('origen');
    if (estado.destinoTelar) await fetchRegistros('destino');
}

async function ejecutarMover(): Promise<void> {
    procesando('Guardando el nuevo orden y asignaciones');
    const payload = {
        ordenes_origen: estado.origenRegistros.map((r) => r.id),
        origen_salon: estado.origenTelar ? estado.origenTelar.salon : null,
        origen_telar: estado.origenTelar ? estado.origenTelar.telar : null,
        ordenes_destino: estado.destinoRegistros.map((r) => r.id),
        destino_salon: estado.destinoTelar ? estado.destinoTelar.salon : null,
        destino_telar: estado.destinoTelar ? estado.destinoTelar.telar : null,
    };
    const data = await postConResultado(http.post<{ success?: boolean; message?: string }>(rutas.procesar, payload));
    if (!data) {
        void aviso('error', 'Error de conexión', 'No se pudo comunicar con el servidor', '#ef4444');
        return;
    }
    if (!data.success) {
        void aviso('error', 'Error', data.message || 'No se pudieron guardar los cambios', '#ef4444');
        return;
    }
    void aviso('success', 'Cambios Guardados', data.message ?? '', '#2563eb', 2500);
    estado.hasChanges = false;
    if (estado.origenTelar) await fetchRegistros('origen');
    if (estado.destinoTelar) await fetchRegistros('destino');
}

async function confirmarMover(): Promise<void> {
    if (!estado.hasChanges) return;
    const ok = await notify.confirm({
        title: '¿Guardar Cambios?',
        html: 'Se actualizarán las posiciones y asignaciones en base al orden mostrado en pantalla.',
        icon: 'question',
        confirmText: 'Sí, guardar',
        confirmColor: '#2563eb',
    });
    if (ok) await ejecutarMover();
}

/** Suelta lo arrastrado en `panel`, en la posición `indice` o al final (null). */
function soltar(panel: Panel, indice: number | null): void {
    const arrastrado = estado.arrastrado;
    if (!arrastrado || !telarDe(panel)) return;
    if (moverRegistro(registrosDe(arrastrado.panel), registrosDe(panel), arrastrado.id, indice)) {
        checkIfChanged();
        renderPanel('origen');
        renderPanel('destino');
    }
}

/** Arrastrar y soltar (antes ondragstart/ondragover/ondrop en el HTML armado por JS). */
function enlazarArrastre(): void {
    (['origen', 'destino'] as Panel[]).forEach((panel) => {
        const contenedor = $(`panel${cap(panel)}Container`);
        const tbody = $(`mover${cap(panel)}Tbody`);

        tbody.addEventListener('dragstart', (e) => {
            const tr = (e.target as Element).closest<HTMLElement>('tr[draggable="true"]');
            if (!tr || !e.dataTransfer) return;
            const id = Number(tr.dataset.id);
            estado.arrastrado = { panel, id };
            e.dataTransfer.setData('application/json', JSON.stringify({ panel, id }));
            e.dataTransfer.effectAllowed = 'move';
            setTimeout(() => tr.classList.add('opacity-40', 'scale-[0.99]', 'bg-gray-100'), 10);
        });
        tbody.addEventListener('dragend', (e) => {
            (e.target as Element).closest('tr')?.classList.remove('opacity-40', 'scale-[0.99]', 'bg-gray-100');
            quitarAnillos();
            quitarMarcasFila();
            estado.arrastrado = null;
        });
        // Sobre una fila: línea arriba de ella (se inserta antes).
        tbody.addEventListener('dragover', (e) => {
            const tr = (e.target as Element).closest<HTMLElement>('tr[data-indice]');
            if (!tr) return;
            e.preventDefault();
            e.stopPropagation();
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
            quitarMarcasFila();
            tr.classList.add('border-t-2', panel === 'destino' ? 'border-blue-500' : 'border-amber-500');
        });
        tbody.addEventListener('dragleave', (e) => {
            (e.target as Element).closest('tr[data-indice]')?.classList.remove('border-t-2', 'border-blue-500', 'border-amber-500');
        });
        tbody.addEventListener('drop', (e) => {
            const tr = (e.target as Element).closest<HTMLElement>('tr[data-indice]');
            if (!tr) return;
            e.preventDefault();
            e.stopPropagation();
            soltar(panel, Number(tr.dataset.indice));
        });

        // Sobre el panel (fuera de una fila): al final.
        contenedor.addEventListener('dragover', (e) => {
            e.preventDefault();
            if (telarDe(panel)) contenedor.classList.add(...ANILLO[panel]);
        });
        contenedor.addEventListener('dragleave', (e) => {
            if (!contenedor.contains(e.relatedTarget as Node | null)) contenedor.classList.remove(...ANILLO[panel]);
        });
        contenedor.addEventListener('drop', (e) => {
            e.preventDefault();
            quitarAnillos();
            soltar(panel, null);
        });
    });
}

export function iniciarMover(r: ConfigUtileria['mover']): void {
    rutas = r;
    const modal = $('modalMover');
    if (!modal) return;

    $('moverSelectOrigen').addEventListener('change', () => { void cambiarTelar('origen'); });
    $('moverSelectDestino').addEventListener('change', () => { void cambiarTelar('destino'); });
    $('btnMoverRevertir').addEventListener('click', () => { void revertirCambios(); });
    $('btnMoverConfirm').addEventListener('click', () => { void confirmarMover(); });
    modal.querySelector('[data-accion-modal="cerrar"]')?.addEventListener('click', () => { void cerrarModalMover(); });
    modal.addEventListener('click', (e) => { if (e.target === modal) void cerrarModalMover(); });
    enlazarArrastre();
}
