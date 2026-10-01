// Balanceo de órdenes compartidas (modal notify.form + Gantt). Vivía inline en
// modulos/programa-tejido/balancear.blade.php (73 KB por carga, sin cache).
// Las URL se escriben con la ruta de Programa y rutaSuperficie() las pasa a Muestras
// (antes lo hacía el parche de window.fetch de index.js).
// http/notify son los globales de bootstrap.js (ver respuesta.ts: por qué no se importan).
import {
    buildDateRange,
    celdaGantt,
    elegirLider,
    formatearFecha,
    getDateKeyLocal,
    mapWithScaledTimeline,
    normalizeToLocalMidnight,
    parseDateOnlyTimeToMs,
    parseFechaBackendALocal,
    parseNumber,
    parseSQLDateToMs,
    repartirTotal,
    sortRegistrosPorFechaTelar,
    tieneOrdCompartida,
    toDateInputValueLocal,
    type CandidatoLider,
    type DatosInput,
    type MapaDias,
    type RegistroBalanceo,
} from './balancear-logica.ts';
import { el } from './nodos.ts';
import { datosDelError, esCancelacion } from './respuesta.ts';
import { rutaSuperficie } from './rutas.ts';
import { escapeHtml } from '../utils/format.ts';

interface FilaGantt {
    label: string;
    map: MapaDias;
    capByDay: MapaDias | null;
}

interface LineaPrograma {
    Fecha?: string | null;
    Cantidad?: number | string | null;
}

interface RespuestaApi {
    success?: boolean;
    message?: string;
}

type Ord = number | string;

// ==========================
// Estado global / caches
// ==========================
let adjustingPedidos = false;
let adjustingFromTotal = false;
let totalDisponibleBalanceo: number | null = null;

let gruposDataCache: Record<string, RegistroBalanceo[]> = {}; // ordCompartida => registros
let lineasCache: Record<string, LineaPrograma[]> = {}; // programaId => líneas originales
let currentGanttRegistros: RegistroBalanceo[] = []; // registros actuales en modal

// preview debounce + abort (debounce bajo = UI más reactiva; el servidor aborta peticiones viejas)
let previewTimer: ReturnType<typeof setTimeout> | null = null;
let previewAbort: AbortController | null = null;
let previewVersion = 0;
const PREVIEW_DEBOUNCE_MS = 100;

const PEDIDO_INPUT_BASE_CLASS =
    'pedido-input w-20 sm:w-24 px-2 py-1 text-xs sm:text-sm text-right border rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500';
const TOTAL_PEDIDO_INPUT_BASE_CLASS =
    'w-20 sm:w-24 px-2 py-1 text-xs sm:text-sm text-right font-bold text-gray-900 border rounded focus:outline-none';

const pedidoInputs = () => Array.from(document.querySelectorAll<HTMLInputElement>('.pedido-input'));

function setBalanceoPreviewLoading(show: boolean): void {
    const loading = document.getElementById('gantt-preview-loading');
    if (!loading) return;
    loading.classList.toggle('hidden', !show);
    loading.setAttribute('aria-busy', show ? 'true' : 'false');
}

// ==========================
// Helpers de DOM
// ==========================
function getRowById(id: Ord): HTMLTableRowElement | null {
    const selector = `tr[data-registro-id="${CSS.escape(String(id))}"]`;
    // Buscar primero en el modal de balanceo si existe
    return document.querySelector('.balanceo-orden-body')?.querySelector<HTMLTableRowElement>(selector)
        ?? document.querySelector<HTMLTableRowElement>(selector);
}

function getInputById(id: Ord): HTMLInputElement | null {
    const selector = `.pedido-input[data-id="${CSS.escape(String(id))}"]`;
    return document.querySelector('.balanceo-orden-body')?.querySelector<HTMLInputElement>(selector)
        ?? document.querySelector<HTMLInputElement>(selector);
}

function resolveBalanceoLeader(registros: RegistroBalanceo[] = currentGanttRegistros, pedidosById: Record<string, number> | null = null): CandidatoLider | null {
    const items = Array.from(registros || []).map((reg): CandidatoLider => {
        const input = getInputById(reg.Id);
        const pedidoActual = pedidosById && Object.prototype.hasOwnProperty.call(pedidosById, reg.Id)
            ? Math.round(Number(pedidosById[String(reg.Id)]) || 0)
            : (input ? Math.round(Number(input.value) || 0) : Math.round(Number(reg.TotalPedido || 0)));
        const fechaInicioMs = input
            ? (Number(input.dataset.fechaInicio) || parseSQLDateToMs(reg.FechaInicio))
            : parseSQLDateToMs(reg.FechaInicio);

        return {
            id: Number(reg.Id) || 0,
            noTelarId: reg.NoTelarId || '-',
            isLeader: reg.OrdCompartidaLider === 1 || reg.OrdCompartidaLider === true || reg.OrdCompartidaLider === '1',
            fechaInicioMs: fechaInicioMs || null,
            fechaInicioKey: fechaInicioMs ? getDateKeyLocal(new Date(fechaInicioMs)) : null,
            fechaCreacionMs: parseDateOnlyTimeToMs(reg.FechaCreacion, reg.HoraCreacion),
            pedidoActual,
        };
    });

    return elegirLider(items);
}

function renderBalanceoLeaderBadge(registros: RegistroBalanceo[] = currentGanttRegistros): void {
    const leader = resolveBalanceoLeader(registros);
    const leaderId = leader?.id || 0;
    const badge = document.getElementById('balanceo-no-telar-principal');
    if (badge) badge.textContent = leader?.noTelarId || '-';

    document.querySelectorAll<HTMLTableRowElement>('tr[data-registro-id]').forEach((row) => {
        const isLeader = leaderId > 0 && Number(row.dataset.registroId || 0) === leaderId;
        row.className = isLeader ? 'bg-amber-100 border-b border-amber-300' : 'hover:bg-gray-50 border-b border-gray-200';
    });
}

function getLockedTotalBalanceo(inputs: ArrayLike<HTMLInputElement> | null = null): number {
    if (typeof totalDisponibleBalanceo === 'number' && !Number.isNaN(totalDisponibleBalanceo)) {
        return totalDisponibleBalanceo;
    }

    const totalDisponibleEl = document.getElementById('total-disponible');
    if (totalDisponibleEl) {
        totalDisponibleBalanceo = Math.round(parseNumber(totalDisponibleEl.textContent));
        return totalDisponibleBalanceo;
    }

    const list = Array.from(inputs || pedidoInputs());
    totalDisponibleBalanceo = list.reduce((sum, input) => sum + (Number(input.dataset.original) || 0), 0);
    return totalDisponibleBalanceo;
}

function setLockedTotalBalanceo(total: number): void {
    totalDisponibleBalanceo = Math.round(Number(total) || 0);
    const totalDisponibleEl = document.getElementById('total-disponible');
    if (totalDisponibleEl) totalDisponibleEl.textContent = totalDisponibleBalanceo.toLocaleString('es-MX');
}

function getCurrentInputsPayload(): { id: number; total_pedido: number; modo: 'total' }[] {
    return pedidoInputs().map((inp) => ({
        id: Number(inp.dataset.id),
        total_pedido: Math.round(Number(inp.value) || 0),
        modo: 'total',
    }));
}

function hasPedidoChanges(): boolean {
    return pedidoInputs().some((inp) => Math.round(Number(inp.value) || 0) !== (Number(inp.dataset.original) || 0));
}

/** Pedido usado para sumas/saldos sin forzar el valor del input si el usuario sigue editando. */
function pedidoEfectivoParaCalculo(input: HTMLInputElement, forceNormalize: boolean): number {
    const produccion = Number(input.dataset.produccion || 0) || 0;
    const raw = String(input.value ?? '').trim();
    const n = Math.round(Number(raw) || 0);
    const isFocused = !forceNormalize && document.activeElement === input;

    if (isFocused && raw === '') return produccion > 0 ? produccion : 0;
    if (produccion > 0) return Math.max(produccion, n);
    return Math.max(0, n);
}

function sumaPedidos(inputs: HTMLInputElement[]): number {
    return inputs.reduce((sum, inp) => sum + pedidoEfectivoParaCalculo(inp, false), 0);
}

function isBalanceoTotalsBalanced(): boolean {
    const inputs = pedidoInputs();
    const locked = getLockedTotalBalanceo(inputs);
    if (locked <= 0 || inputs.length === 0) return true;
    return Math.abs(sumaPedidos(inputs) - locked) <= 0.0001;
}

function updateBalanceoTotalVisualState(): void {
    const inputs = pedidoInputs();
    const totalInput = document.getElementById('total-pedido-input');
    const msg = document.getElementById('balanceo-total-mensaje');
    const locked = getLockedTotalBalanceo(inputs);
    const mismatch = locked > 0 && inputs.length > 0 && Math.abs(sumaPedidos(inputs) - locked) > 0.0001;
    const error = mismatch ? 'border-red-500 ring-1 ring-red-200' : 'border-gray-300';

    inputs.forEach((inp) => { inp.className = `${PEDIDO_INPUT_BASE_CLASS} ${error}`; });
    if (totalInput) totalInput.className = `${TOTAL_PEDIDO_INPUT_BASE_CLASS} ${error}`;

    if (msg) {
        msg.classList.toggle('hidden', !mismatch);
        if (mismatch) {
            msg.textContent = 'La suma de pedidos debe coincidir con el total del grupo (' + locked.toLocaleString('es-MX') +
                '). Al salir del campo se ajusta el último telar cuando sea posible.';
        }
    }

    syncBalanceoGuardarButtonState();
}

function syncBalanceoGuardarButtonState(): void {
    const btn = document.querySelector('.balanceo-orden-modal')?.querySelector<HTMLButtonElement>('button[type="submit"]');
    if (!btn) return;
    const ok = isBalanceoTotalsBalanced();
    btn.disabled = !ok;
    btn.setAttribute('aria-disabled', ok ? 'false' : 'true');
    btn.title = ok ? '' : 'La suma de pedidos debe coincidir con el total del grupo antes de guardar.';
    btn.classList.toggle('opacity-50', !ok);
    btn.classList.toggle('cursor-not-allowed', !ok);
}

// ==========================
// API / datos
// ==========================
async function fetchRegistrosOrdCompartida(ordCompartida: Ord): Promise<RegistroBalanceo[]> {
    const enCache = gruposDataCache[String(ordCompartida)];
    if (enCache) return enCache;

    let data: (RespuestaApi & { registros?: RegistroBalanceo[] }) | null;
    try {
        data = await http.get(rutaSuperficie(`/planeacion/programa-tejido/registros-ord-compartida/${ordCompartida}`));
    } catch (err) {
        // Antes un 4xx/5xx con JSON caía en la rama de abajo con su mensaje.
        data = datosDelError(err);
        if (!data) throw err;
    }
    if (data?.success && Array.isArray(data.registros)) {
        const ordenados = sortRegistrosPorFechaTelar(data.registros);
        gruposDataCache[String(ordCompartida)] = ordenados;
        return ordenados;
    }
    throw new Error(data?.message || 'No se pudieron obtener los registros');
}

async function fetchLineasPrograma(programaId: Ord): Promise<LineaPrograma[]> {
    const enCache = lineasCache[String(programaId)];
    if (enCache) return enCache;

    const json = await http.get<RespuestaApi & { data?: { data?: LineaPrograma[] } }>(
        rutaSuperficie(`/planeacion/req-programa-tejido-line?programa_id=${programaId}&per_page=5000&sort=Fecha&dir=asc`),
    );
    if (!json?.success || !json.data?.data) return [];

    lineasCache[String(programaId)] = json.data.data;
    return json.data.data;
}

async function prefetchLineas(registros: RegistroBalanceo[]): Promise<void> {
    await Promise.all(registros.slice(0, 30).map((r) => fetchLineasPrograma(r.Id).catch(() => [])));
}

// ==========================
// GANTT
// ==========================
function formatShort(d: Date): string {
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function getCurrentInputsMap(): Record<string, DatosInput> {
    const map: Record<string, DatosInput> = {};
    pedidoInputs().forEach((inp) => {
        const id = inp.dataset.id;
        if (!id) return;
        map[id] = {
            pedido: Number(inp.value) || 0,
            fechaInicioMs: Number(inp.dataset.fechaInicio) || 0,
            duracionOriginalMs: Number(inp.dataset.duracionOriginal) || 0,
            fechaFinalCalcMs: Number(inp.dataset.fechaFinalCalculada) || 0,
        };
    });
    return map;
}

function mensajeGantt(cont: HTMLElement, texto: string, color = 'text-gray-500'): void {
    cont.replaceChildren(el('div', { clase: `p-3 text-sm ${color}`, texto }));
}

function renderGanttGrid(dates: Date[], rows: FilaGantt[]): void {
    const cont = document.getElementById('gantt-ord');
    const loader = document.getElementById('gantt-loading');
    const wrapper = document.getElementById('gantt-ord-container');
    if (!cont) return;
    if (loader) loader.classList.add('hidden');

    if (!dates.length || !rows.length) {
        mensajeGantt(cont, 'Sin datos para mostrar.');
        if (wrapper) wrapper.style.height = '180px';
        return;
    }

    const isSmall = window.innerWidth <= 639;
    if (wrapper) {
        const rowPx = isSmall ? 32 : 40;
        const neededHeight = 42 + rows.length * rowPx + 32;
        const maxHeight = Math.round(window.innerHeight * (isSmall ? 0.55 : 0.7));
        wrapper.style.height = `${Math.min(neededHeight, maxHeight)}px`;
    }

    /* Columna telar compacta; sin max-width en CSS que deje hueco antes de la 1.ª fecha */
    const labelCol = isSmall ? '96px' : '156px';
    const dateCol = isSmall ? '50px' : '60px';
    const grid = el('div', { clase: 'gantt-grid' },
        el('div', { clase: 'gantt-cell gantt-header gantt-label gantt-corner' }),
        ...dates.map((d) => el('div', { clase: 'gantt-cell gantt-header', texto: formatShort(d) })));
    grid.style.gridTemplateColumns = `${labelCol} repeat(${dates.length}, ${dateCol})`;

    rows.forEach((row, idx) => {
        grid.append(el('div', { clase: 'gantt-cell gantt-label', texto: row.label }));
        dates.forEach((d) => {
            const key = getDateKeyLocal(d) as string;
            const qty = Math.round(row.map[key] || 0);
            const cap = row.capByDay && row.capByDay[key] != null ? Number(row.capByDay[key]) : null;
            const { cls, title } = celdaGantt(qty, cap, idx);
            const celda = el('div', { clase: `gantt-cell ${cls}`, texto: qty > 0 ? qty.toLocaleString('es-MX') : '' });
            if (title) celda.title = title;
            grid.append(celda);
        });
    });

    cont.replaceChildren(grid);
}

function etiquetaGantt(reg: RegistroBalanceo): string {
    return `Telar ${reg.NoTelarId || '-'} · ${reg.NombreProducto || ''}`.trim();
}

async function renderGanttOrd(registros: RegistroBalanceo[]): Promise<void> {
    const loader = document.getElementById('gantt-loading');
    if (loader) loader.classList.remove('hidden');

    const cont = document.getElementById('gantt-ord');
    cont?.replaceChildren();

    try {
        const data = await Promise.all(registros.slice(0, 30).map(async (reg) => ({ reg, lineas: await fetchLineasPrograma(reg.Id) })));
        currentGanttRegistros = registros;

        let minD: Date | null = null;
        let maxD: Date | null = null;
        const rows: FilaGantt[] = [];

        data.forEach(({ reg, lineas }) => {
            const map: MapaDias = {};
            let localMin = parseFechaBackendALocal(String(reg.FechaInicio || '').trim());
            let localMax = parseFechaBackendALocal(String(reg.FechaFinal || '').trim());

            (Array.isArray(lineas) ? lineas : []).forEach((l) => {
                const d = parseFechaBackendALocal(String(l.Fecha || '').trim());
                if (!d) return;
                const key = getDateKeyLocal(d) as string;
                const qty = Number(l.Cantidad || 0);
                map[key] = (map[key] || 0) + Math.round((qty + Number.EPSILON) * 1000) / 1000;
                if (!localMin || d < localMin) localMin = d;
                if (!localMax || d > localMax) localMax = d;
            });

            if (!localMin || !localMax) return;

            // Normalizar fechas a medianoche local para evitar problemas de zona horaria
            const localMinNormalized = normalizeToLocalMidnight(localMin);
            const localMaxNormalized = normalizeToLocalMidnight(localMax);
            if (localMinNormalized && (!minD || localMinNormalized < minD)) minD = localMinNormalized;
            if (localMaxNormalized && (!maxD || localMaxNormalized > maxD)) maxD = localMaxNormalized;

            const stdDiaReg = Number(reg.StdDia || 0) || 0;
            const capByDay = stdDiaReg > 0 && Object.keys(map).length
                ? Object.fromEntries(Object.keys(map).map((k) => [k, stdDiaReg]))
                : null;
            rows.push({ label: etiquetaGantt(reg), map, capByDay });
        });

        if (!minD || !maxD || rows.length === 0) {
            if (cont) mensajeGantt(cont, 'No hay líneas para mostrar.');
            return;
        }

        renderGanttGrid(buildDateRange(minD, maxD), rows);
    } catch (e) {
        console.error('Error al renderizar gantt', e);
        if (cont) mensajeGantt(cont, 'No se pudo cargar el gantt.', 'text-red-600');
    } finally {
        if (loader) loader.classList.add('hidden');
    }
}

function updateGanttPreview(): void {
    if (!currentGanttRegistros || !currentGanttRegistros.length) return;

    const inputsMap = getCurrentInputsMap();
    const rows: FilaGantt[] = [];
    let minD: Date | null = null;
    let maxD: Date | null = null;

    currentGanttRegistros.forEach((reg) => {
        const result = mapWithScaledTimeline(reg, inputsMap);
        const map = result.map || {};
        if (!Object.keys(map).length) return;

        if (result.min) {
            const minNormalized = normalizeToLocalMidnight(new Date(result.min));
            if (minNormalized && (!minD || minNormalized < minD)) minD = minNormalized;
        }
        if (result.max) {
            const maxNormalized = normalizeToLocalMidnight(new Date(result.max));
            if (maxNormalized && (!maxD || maxNormalized > maxD)) maxD = maxNormalized;
        }

        rows.push({ label: etiquetaGantt(reg), map, capByDay: result.capByDay ?? null });
    });

    if (!rows.length || !minD || !maxD) return;
    renderGanttGrid(buildDateRange(minD, maxD), rows);
}

// ==========================
// PREVIEW EXACTO (backend)
// ==========================
interface FechaPreview {
    id: number | string;
    fecha_inicio?: string | null;
    fecha_final?: string | null;
}

function pintarFechasPreview(items: FechaPreview[]): void {
    items.forEach((item) => {
        const id = Number(item.id);
        const row = getRowById(id);
        const inp = getInputById(id);
        if (!row) { console.warn('No se encontró fila para id:', id); return; }
        if (!inp) { console.warn('No se encontró input para id:', id); return; }

        const inicioMs = parseSQLDateToMs(item.fecha_inicio);
        const finMs = parseSQLDateToMs(item.fecha_final);
        // actualizar datasets para gantt preview
        if (inicioMs) inp.dataset.fechaInicio = String(inicioMs);
        if (finMs) inp.dataset.fechaFinalCalculada = String(finMs);

        const inicioCell = row.querySelector<HTMLElement>('.fecha-inicio-display');
        const finalCell = row.querySelector<HTMLElement>('.fecha-final-display');

        if (inicioCell && item.fecha_inicio) inicioCell.textContent = formatearFecha(item.fecha_inicio);

        if (!finalCell) {
            console.warn('No se encontró celda fecha-final-display para id:', id);
        } else if (item.fecha_final) {
            finalCell.textContent = formatearFecha(item.fecha_final);
            finalCell.dataset.fechaFinal = item.fecha_final;
        } else {
            finalCell.textContent = '-';
        }
    });
}

async function previewFechasExactas(ordCompartida: Ord, options: { force?: boolean } = {}): Promise<void> {
    if (options.force !== true && !hasPedidoChanges()) {
        setBalanceoPreviewLoading(false);
        return;
    }
    if (!isBalanceoTotalsBalanced()) {
        setBalanceoPreviewLoading(false);
        return;
    }
    const myVersion = ++previewVersion;

    previewAbort?.abort();
    previewAbort = new AbortController();

    const cambios = getCurrentInputsPayload();

    setBalanceoPreviewLoading(true);
    try {
        let data: (RespuestaApi & { data?: FechaPreview[] }) | null;
        try {
            data = await http.post(rutaSuperficie('/planeacion/programa-tejido/preview-fechas-balanceo'),
                { ord_compartida: ordCompartida, cambios }, { signal: previewAbort.signal });
        } catch (err) {
            if (esCancelacion(err)) throw err;
            data = datosDelError(err);
        }
        if (!data?.success || !Array.isArray(data.data)) {
            if (myVersion === previewVersion) setBalanceoPreviewLoading(false);
            return;
        }
        if (myVersion !== previewVersion) return;
        const items = data.data;

        // Pintar fechas y quitar loading tras pintar el Gantt (evita parpadeo)
        requestAnimationFrame(() => {
            pintarFechasPreview(items);
            renderBalanceoLeaderBadge(currentGanttRegistros);
            updateGanttPreview();
            requestAnimationFrame(() => {
                if (myVersion === previewVersion) setBalanceoPreviewLoading(false);
            });
        });
    } catch (e) {
        if (esCancelacion(e)) return;
        console.error('previewFechasExactas error', e);
        if (myVersion === previewVersion) setBalanceoPreviewLoading(false);
    }
}

function schedulePreview(ordCompartida: Ord): void {
    if (previewTimer) clearTimeout(previewTimer);
    if (!isBalanceoTotalsBalanced()) return;
    previewTimer = setTimeout(() => { void previewFechasExactas(ordCompartida); }, PREVIEW_DEBOUNCE_MS);
}

// ==========================
// Totales / saldos (SIN calcular fecha aquí)
// ==========================
function calcularTotalesYFechas(ordCompartida: Ord | null = null, forceRebalance = false): void {
    if (adjustingPedidos) return;

    const inputs = pedidoInputs();
    let totalPedido = 0;
    let totalSaldo = 0;

    inputs.forEach((input) => {
        if (input.value && input.value.includes('.')) {
            const r = Math.round(Number(input.value) || 0);
            input.value = r ? String(r) : '';
        }

        const produccion = Number(input.dataset.produccion || 0) || 0;
        const isFocused = !forceRebalance && document.activeElement === input;
        const pedido = Math.round(Number(input.value) || 0);

        if (produccion > 0 && pedido < produccion && !isFocused) {
            input.value = produccion ? String(produccion) : '';
        }

        const saldoCell = input.closest('tr')?.querySelector<HTMLElement>('.saldo-display');
        const pedidoCalc = pedidoEfectivoParaCalculo(input, forceRebalance);
        const saldo = Math.max(0, pedidoCalc - produccion);

        totalPedido += pedidoCalc;
        totalSaldo += saldo;

        if (saldoCell) {
            saldoCell.textContent = saldo.toLocaleString('es-MX');
            saldoCell.className = 'px-3 py-2 text-sm text-right saldo-display ' + (saldo > 0 ? 'text-green-600 font-medium' : 'text-gray-500');
        }
    });

    const activo = document.activeElement;
    const skipLastRowAdjust = !forceRebalance && !!activo && typeof activo.matches === 'function' && activo.matches('.pedido-input');

    if (!adjustingFromTotal && !skipLastRowAdjust) {
        const totalDisponible = getLockedTotalBalanceo(inputs);
        const diff = totalDisponible - totalPedido;
        const target = inputs[inputs.length - 1];
        if (totalDisponible > 0 && target && inputs.length >= 2 && Math.abs(diff) > 0.0001) {
            const valActualTarget = Number(target.value) || 0;
            const produccionTarget = Number(target.dataset.produccion || 0) || 0;

            const adjusted = Math.round(Math.max(produccionTarget, valActualTarget + diff));
            // Si el último telar ya está en su mínimo (producción) no hay nada que ajustar: antes se
            // volvía a llamar igual y reventaba la pila ("Maximum call stack size exceeded") cuando la
            // producción de ese telar supera lo que le toca del total. Se sigue y se marca el descuadre.
            if (adjusted !== valActualTarget) {
                adjustingPedidos = true;
                target.value = adjusted ? String(adjusted) : '';
                adjustingPedidos = false;

                calcularTotalesYFechas(ordCompartida, forceRebalance);
                return;
            }
        }
    }

    const totalPedidoInput = document.getElementById('total-pedido-input') as HTMLInputElement | null;
    const totalSaldoEl = document.getElementById('total-saldo');

    if (totalPedidoInput && !adjustingPedidos && !adjustingFromTotal && document.activeElement !== totalPedidoInput) {
        totalPedidoInput.value = String(totalPedido);
    }
    if (totalSaldoEl) totalSaldoEl.textContent = totalSaldo.toLocaleString('es-MX');
    renderBalanceoLeaderBadge(currentGanttRegistros);
    updateBalanceoTotalVisualState();
}

function actualizarPedidosDesdeTotal(totalInput: HTMLInputElement, ordCompartida: Ord): void {
    if (adjustingPedidos || adjustingFromTotal) return;

    if (totalInput.value && totalInput.value.includes('.')) totalInput.value = String(Math.round(Number(totalInput.value) || 0));

    const nuevoTotal = Math.round(Number(totalInput.value) || 0);
    const inputs = pedidoInputs();
    if (inputs.length === 0) return;

    setLockedTotalBalanceo(nuevoTotal);

    const filas = inputs.map((input) => ({ valor: Number(input.value) || 0, produccion: Number(input.dataset.produccion || 0) || 0 }));
    const totalActual = filas.reduce((s, f) => s + f.valor, 0);
    if (Math.abs(nuevoTotal - totalActual) < 0.0001) return;

    adjustingFromTotal = true;
    repartirTotal(filas, nuevoTotal).forEach((nuevo, i) => {
        const input = inputs[i];
        if (input) input.value = nuevo ? String(nuevo) : '';
    });
    calcularTotalesYFechas(ordCompartida);
    adjustingFromTotal = false;
    schedulePreview(ordCompartida);
}

// ==========================
// Balanceo automático con fecha fin objetivo
// ==========================
async function aplicarBalanceoAutomatico(ordCompartida: Ord): Promise<void> {
    const inputs = pedidoInputs();
    if (inputs.length < 2) return;

    const fechaInput = document.getElementById('fecha-fin-objetivo-balanceo') as HTMLInputElement | null;
    if (!fechaInput) return;

    const fechaFinObjetivo = fechaInput.value;
    if (!fechaFinObjetivo) {
        fechaInput.focus();
        return;
    }
    if (fechaInput.min && fechaFinObjetivo < fechaInput.min) {
        notify.error('La fecha objetivo no puede ser anterior al inicio más tardío del grupo (' + fechaInput.min + ').');
        fechaInput.focus();
        return;
    }

    const cambiosActuales = getCurrentInputsPayload();
    const totalObjetivo = getLockedTotalBalanceo(inputs);

    const btn = document.getElementById('btn-balancear-auto') as HTMLButtonElement | null;
    const btnIcon = btn?.querySelector('.btn-balancear-icon');
    const btnLabel = btn?.querySelector('.btn-balancear-label');
    const setLoading = (loading: boolean) => {
        if (!btn) return;
        btn.disabled = loading;
        if (btnIcon) btnIcon.className = loading ? 'fa-solid fa-spinner fa-spin text-xs btn-balancear-icon' : 'fa-solid fa-scale-balanced text-xs btn-balancear-icon';
        if (btnLabel) btnLabel.textContent = loading ? 'Calculando...' : 'Balancear';
        setBalanceoPreviewLoading(loading);
    };

    setLoading(true);
    try {
        type Respuesta = RespuestaApi & { advertencia_total?: string; cambios?: { id: Ord; total_pedido: number }[] };
        let data: Respuesta | null;
        try {
            data = await http.post<Respuesta>(rutaSuperficie('/planeacion/programa-tejido/balancear-automatico'), {
                ord_compartida: ordCompartida,
                fecha_fin_objetivo: fechaFinObjetivo,
                cambios: cambiosActuales,
                total_objetivo: totalObjetivo,
            });
        } catch (err) {
            // Un 4xx/5xx con JSON trae el mensaje del servidor, como antes con fetch.
            data = datosDelError<Respuesta>(err);
            if (!data) throw err;
        }

        if (!data.success) {
            notify.error(data.message || 'No se pudo realizar el balanceo.');
            return;
        }
        if (data.advertencia_total) notify.warning(data.advertencia_total);

        if (Array.isArray(data.cambios)) {
            // Bloquear recálculos intermedios para que cada input no reajuste al último
            adjustingPedidos = true;
            data.cambios.forEach((cambio) => {
                const input = getInputById(cambio.id);
                if (!input) return;
                const produccion = Number(input.dataset.produccion || 0) || 0;
                const nuevoValor = Math.round(Math.max(produccion, cambio.total_pedido));
                input.value = nuevoValor ? String(nuevoValor) : '';
            });
            // El total disponible no cambia: calcularTotalesYFechas no debe redistribuir fuera de él.
            setLockedTotalBalanceo(totalObjetivo);
            adjustingPedidos = false;

            calcularTotalesYFechas(ordCompartida);
            setTimeout(() => {
                void previewFechasExactas(ordCompartida, { force: true });
                setTimeout(() => {
                    const ganttContainer = document.getElementById('gantt-ord-container');
                    if (ganttContainer) ganttContainer.scrollLeft = ganttContainer.scrollWidth;
                }, 350);
            }, 100);
        }
    } catch (error) {
        console.error('Error al balancear:', error);
    } finally {
        setLoading(false);
    }
}

// ==========================
// Actualizar registros en tabla principal sin recargar
// ==========================
interface ColumnaGrilla {
    field: string;
    label?: string;
    dateType?: 'date' | 'datetime' | null;
}

/** Valor de celda. formatearValorCelda de index.js no está en window, así que siempre se usó este respaldo. */
function formatearValorBasico(value: unknown, dateType: ColumnaGrilla['dateType']): string {
    if (value === null || value === undefined || value === '') return '';
    if (dateType === 'date' || dateType === 'datetime') {
        const dt = new Date(value as string);
        if (isNaN(dt.getTime())) return 'Invalid Date';
        if (dt.getFullYear() <= 1970) return '';
        return dateType === 'date' ? dt.toLocaleDateString('es-MX') : dt.toLocaleString('es-MX');
    }
    if (!isNaN(value as number) && !Number.isInteger(parseFloat(String(value)))) return parseFloat(String(value)).toFixed(2);
    return String(value);
}

async function actualizarRegistrosBalanceo(registrosIds: Ord[]): Promise<void> {
    const tb = document.querySelector('#mainTable tbody');
    if (!tb) return;

    // columnsData de index.js vive en su scope; aquí solo se ve window.columns o el DOM (como antes).
    const columns: ColumnaGrilla[] = window.columns ||
        Array.from(document.querySelectorAll('#mainTable thead th[data-column]'), (th) => ({
            field: th.getAttribute('data-column') ?? '',
            label: th.textContent?.trim() ?? '',
            dateType: null,
        }));
    if (!columns.length) return;

    for (const registroId of registrosIds) {
        try {
            const result = await http.get<RespuestaApi & { registro?: Record<string, unknown> }>(
                rutaSuperficie(`/planeacion/programa-tejido/${registroId}/detalles-balanceo?t=${Date.now()}`),
                { headers: { 'Cache-Control': 'no-cache' } },
            );
            if (!result.success || !result.registro) continue;
            const registro = result.registro;

            const fila = tb.querySelector(`tr.selectable-row[data-id="${CSS.escape(String(registroId))}"]`);
            if (!fila) continue;

            if (registro.OrdCompartida) fila.setAttribute('data-ord-compartida', String(registro.OrdCompartida));

            columns.forEach((col) => {
                const value = registro[col.field] !== undefined ? registro[col.field] : null;
                const celda = fila.querySelector(`td[data-column="${CSS.escape(col.field)}"]`);
                if (!celda) return;
                celda.setAttribute('data-value', value !== null && value !== undefined ? String(value) : '');
                celda.textContent = formatearValorBasico(value, col.dateType ?? null);
            });

            // Pequeño delay para no saturar
            await new Promise((resolve) => setTimeout(resolve, 50));
        } catch (error) {
            console.warn(`Error al actualizar registro ${registroId}:`, error);
        }
    }
}

// ==========================
// Guardar cambios
// ==========================
/** `aviso` muestra el error en el modal (ctx.error de notify.form); devolver false lo deja abierto. */
async function guardarCambiosPedido(ordCompartida: Ord, aviso: (mensaje: string) => void): Promise<boolean> {
    calcularTotalesYFechas(ordCompartida, true);
    if (!isBalanceoTotalsBalanced()) {
        aviso('La suma de pedidos no coincide con el total del grupo. Revisa el mínimo por producción en el último telar o ajusta los demás telares.');
        return false;
    }

    // Mismo snapshot que el preview (getCurrentInputsPayload): todas las filas con el pedido actual.
    // Si solo se enviaran filas "cambiadas", el backend aplicaba otra cascada que el preview.
    const cambios = pedidoInputs().map((input) => ({ id: input.dataset.id, total_pedido: Math.round(Number(input.value) || 0), modo: 'total' }));

    if (cambios.length === 0) {
        aviso('No hay filas de pedido para guardar');
        return false;
    }
    if (!hasPedidoChanges()) {
        aviso('No hay cambios para guardar');
        return false;
    }

    setBalanceoPreviewLoading(true);
    try {
        type Respuesta = RespuestaApi & { registros_ids?: Ord[] };
        let data: Respuesta | null;
        try {
            data = await http.post<Respuesta>(rutaSuperficie('/planeacion/programa-tejido/actualizar-pedidos-balanceo'), { cambios, ord_compartida: ordCompartida });
        } catch (err) {
            data = datosDelError<Respuesta>(err);
            if (!data) throw err;
        }

        if (data.success) {
            lineasCache = {};
            delete gruposDataCache[String(ordCompartida)];

            // Aviso con cierre solo; el modal de balanceo se cierra al devolver true.
            notify.success(data.message || 'Los cambios se guardaron correctamente');

            if (Array.isArray(data.registros_ids) && data.registros_ids.length > 0) {
                void actualizarRegistrosBalanceo(data.registros_ids);
            }
            return true;
        }

        aviso(data.message || 'Error al guardar los cambios');
        return false;
    } catch {
        aviso('Error de conexión al guardar los cambios');
        return false;
    } finally {
        setBalanceoPreviewLoading(false);
    }
}

// ==========================
// Modal
// ==========================
async function recargarGanttOrdCompartida(ordCompartida: Ord): Promise<void> {
    const registros = await fetchRegistrosOrdCompartida(ordCompartida);
    currentGanttRegistros = registros;
    lineasCache = {};
    await prefetchLineas(registros);
    void renderGanttOrd(registros);
}

/** Pedido actual de cada registro en la grilla (después de balanceos previos), si es > 0. */
function valoresDeLaGrilla(registros: RegistroBalanceo[]): Record<string, number> {
    const tb = document.querySelector('#mainTable tbody');
    const valores: Record<string, number> = {};
    if (!tb) return valores;
    registros.forEach((reg) => {
        const celda = tb.querySelector(`tr.selectable-row[data-id="${CSS.escape(String(reg.Id))}"] td[data-column="TotalPedido"]`);
        if (!celda) return;
        const dataValue = celda.getAttribute('data-value');
        const textValue = celda.textContent?.trim();
        const valorNumerico = parseNumber(dataValue || textValue?.replace(/[^\d.-]/g, ''));
        if (valorNumerico > 0) valores[String(reg.Id)] = valorNumerico;
    });
    return valores;
}

function filaModal(reg: RegistroBalanceo, pedidoActualCrudo: number, esLider: boolean): string {
    const fechaInicio = reg.FechaInicio ? (parseFechaBackendALocal(String(reg.FechaInicio).trim())?.getTime() ?? 0) : 0;
    const fechaFinal = reg.FechaFinal ? (parseFechaBackendALocal(String(reg.FechaFinal).trim())?.getTime() ?? 0) : 0;
    const duracionOriginalMs = fechaInicio && fechaFinal ? fechaFinal - fechaInicio : 0;

    const pedidoActual = Math.round(pedidoActualCrudo);
    const pedidoOriginal = Math.round(Number(reg.TotalPedido || 0)); // el original va en data-original
    const produccion = Math.round(Number(reg.Produccion || 0));
    const saldoActual = Math.max(0, pedidoActual - produccion);
    const stdDia = Number(reg.StdDia || 0);
    const minPedido = produccion > 0 ? produccion : 0;
    const id = escapeHtml(reg.Id);
    const producto = escapeHtml(reg.NombreProducto || '-');

    return `
        <tr class="${esLider ? 'bg-amber-100 border-b border-amber-300' : 'hover:bg-gray-50 border-b border-gray-200'}" data-registro-id="${id}">
          <td class="px-3 py-2 text-xs sm:text-sm font-medium text-gray-900 whitespace-nowrap">
            ${escapeHtml(reg.NoTelarId || '-')}
          </td>
          <td class="px-3 py-2 text-xs sm:text-sm text-gray-600 max-w-[100px] sm:max-w-none truncate" title="${producto}">${producto}</td>
          <td class="px-3 py-2 text-xs sm:text-sm text-right text-gray-600">${Math.round(stdDia).toLocaleString('es-MX')}</td>
          <td class="px-3 py-2 text-right">
            <input
              type="number"
              class="${PEDIDO_INPUT_BASE_CLASS} border-gray-300"
              aria-label="Pedido del telar ${escapeHtml(reg.NoTelarId || '-')}"
              data-id="${id}"
              data-original="${pedidoOriginal}"
              data-fecha-inicio="${fechaInicio}"
              data-duracion-original="${duracionOriginalMs}"
              data-std-dia="${stdDia}"
              data-produccion="${produccion}"
              value="${pedidoActual || ''}"
              min="${minPedido}"
              step="1"
            >
          </td>
          <td class="px-3 py-2 text-xs sm:text-sm text-right text-gray-600">${produccion.toLocaleString('es-MX')}</td>
          <td class="px-3 py-2 text-xs sm:text-sm text-right saldo-display ${saldoActual > 0 ? 'text-green-600 font-medium' : 'text-gray-500'}"
              data-produccion="${produccion}"
              data-saldo-original="${saldoActual}">
            ${saldoActual.toLocaleString('es-MX')}
          </td>
          <td class="px-3 py-2 text-xs sm:text-sm text-center text-gray-600 fecha-inicio-display">
            ${formatearFecha(reg.FechaInicio)}
          </td>
          <td class="px-3 py-2 text-xs sm:text-sm text-center text-gray-600 fecha-final-display">
            ${formatearFecha(reg.FechaFinal)}
          </td>
        </tr>
      `;
}

const ESTILOS_MODAL = `
        <style>
          /* Contenedor balanceo: ancho relativo al viewport (vw ≈ % del ancho pantalla); gana al max-width de .ui-dialogo--2xl */
          .balanceo-modal-content { max-width: 100%; }
          .ui-dialogo.balanceo-orden-modal { width: min(98vw, 100%); max-width: min(98vw, 100%); }
          .balanceo-orden-modal .ui-dialogo__cuerpo { padding: 0.5rem; gap: 0.75rem; }
          @media (min-width: 640px) {
            .ui-dialogo.balanceo-orden-modal { width: min(96vw, 100%); max-width: min(96vw, 100%); }
            .balanceo-orden-modal .ui-dialogo__cuerpo { padding: 0.75rem 1rem; }
          }
          @media (min-width: 1024px) {
            .ui-dialogo.balanceo-orden-modal { width: min(94vw, 100%); max-width: min(94vw, 100%); }
            .balanceo-orden-modal .ui-dialogo__cuerpo { padding: 1rem 1.25rem; }
          }
          .balanceo-orden-body { overflow-x: hidden; overflow-y: auto; max-height: 88vh; padding: 0.5rem; }
          @media (min-width: 640px) { .balanceo-orden-body { padding: 0.5rem 0.75rem; } }
          @media (min-width: 1024px) { .balanceo-orden-body { max-height: 90vh; padding: 0.75rem 1rem; } }
          /* Tabla: scroll horizontal, evitar que encabezados y números se apilen */
          .balanceo-tabla-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 0 -2px; }
          .balanceo-tabla-wrap table { min-width: 620px; }
          .balanceo-tabla-wrap table th,
          .balanceo-tabla-wrap table td { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
          .balanceo-tabla-wrap table th:nth-child(1), .balanceo-tabla-wrap table td:nth-child(1) { min-width: 64px; max-width: 78px; }
          .balanceo-tabla-wrap table th:nth-child(2), .balanceo-tabla-wrap table td:nth-child(2) { min-width: 110px; max-width: 200px; }
          .balanceo-tabla-wrap table th:nth-child(3), .balanceo-tabla-wrap table td:nth-child(3) { min-width: 62px; }
          .balanceo-tabla-wrap table th:nth-child(4), .balanceo-tabla-wrap table td:nth-child(4) { min-width: 70px; }
          .balanceo-tabla-wrap table th:nth-child(5), .balanceo-tabla-wrap table td:nth-child(5) { min-width: 72px; }
          .balanceo-tabla-wrap table th:nth-child(6), .balanceo-tabla-wrap table td:nth-child(6) { min-width: 62px; }
          .balanceo-tabla-wrap table th:nth-child(7), .balanceo-tabla-wrap table td:nth-child(7),
          .balanceo-tabla-wrap table th:nth-child(8), .balanceo-tabla-wrap table td:nth-child(8) { min-width: 76px; }
          @media (max-width: 639px) {
            .balanceo-tabla-wrap table th, .balanceo-tabla-wrap table td { padding: 0.35rem 0.4rem; font-size: 0.75rem; }
            .balanceo-tabla-wrap .pedido-input { width: 4rem; min-width: 4rem; padding: 0.25rem 0.35rem; font-size: 0.75rem; }
            .balanceo-tabla-wrap #total-pedido-input { width: 4rem; min-width: 4rem; padding: 0.25rem 0.35rem; font-size: 0.75rem; }
          }
          /* Gantt: contenedor con scroll */
          #gantt-ord-container { max-height: 75vh; overflow: auto; -webkit-overflow-scrolling: touch; min-height: 150px; }
          .gantt-grid {
            display: grid;
            grid-auto-rows: minmax(36px, auto);
            width: max-content;
            min-width: 100%;
            column-gap: 0;
            row-gap: 0;
          }
          @media (max-width: 639px) { .gantt-grid { grid-auto-rows: minmax(32px, auto); } }
          /* min-width: 0 evita que el contenido ensanche la cuadrícula; fechas respetan el ancho fijado en template */
          .gantt-cell {
            border: 1px solid #e5e7eb;
            padding: 4px 4px;
            font-size: 10px;
            line-height: 1.2;
            text-align: center;
            min-width: 0;
            box-sizing: border-box;
          }
          @media (max-width: 639px) { .gantt-cell { padding: 3px 2px; font-size: 9px; } }
          .gantt-header { background: #f9fafb; font-weight: 600; color: #374151; position: sticky; top: 0; z-index: 10; }
          .gantt-label {
            font-weight: 600;
            background: #f3f4f6;
            text-align: left;
            position: sticky;
            left: 0;
            z-index: 21;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            max-width: none;
            min-width: 0;
            box-shadow: 1px 0 0 #e5e7eb;
          }
          @media (max-width: 639px) { .gantt-label { font-size: 9px; } }
          .gantt-header.gantt-label.gantt-corner { z-index: 30; }
          .gantt-bar { background: #f3f4f6; color: #4b5563; font-weight: 600; }
          .gantt-bar-alt { background: #e5e7eb; color: #374151; font-weight: 600; }
          /* Por Std/Día prorrateado: ámbar = aún hay espacio respecto al std; verde = cerca del tope */
          .gantt-bar-space { background: #fef3c7; color: #b45309; font-weight: 600; }
          .gantt-bar-at-cap { background: #ecfdf3; color: #166534; font-weight: 600; }
        </style>`;

function contenidoModal(filasHTML: string, noTelarPrincipal: string | null, totales: { pedido: number; produccion: number; saldo: number }): string {
    return `
      <div class="balanceo-modal-content space-y-3 sm:space-y-4 text-left">
        ${ESTILOS_MODAL}

        <div class="flex flex-col sm:flex-row sm:justify-end gap-2 items-stretch sm:items-end">
          <div class="flex items-center gap-2 rounded-md bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900 border border-amber-200 w-full sm:w-auto sm:mr-auto">
            <span>No telar principal es <strong id="balanceo-no-telar-principal">${escapeHtml(noTelarPrincipal || '-')}</strong></span>
          </div>
          <div class="flex flex-col sm:flex-row gap-2 items-center w-full sm:w-auto">
            <label for="fecha-fin-objetivo-balanceo" class="text-xs text-gray-700 whitespace-nowrap">Fecha Objetivo:</label>
            <input
              type="date"
              id="fecha-fin-objetivo-balanceo"
              class="px-2 py-1 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-green-500"
            >
            <button type="button"
              id="btn-balancear-auto"
              class="inline-flex items-center justify-center gap-1 rounded-md bg-blue-500 px-4 py-1 text-md font-medium text-white shadow-sm hover:bg-blue-600 disabled:opacity-60 disabled:cursor-not-allowed">
              <i class="fa-solid fa-scale-balanced text-xs btn-balancear-icon" aria-hidden="true"></i>
              <span class="btn-balancear-label">Balancear</span>
            </button>
          </div>


        </div>

        <div class="flex flex-col lg:flex-row gap-4 items-stretch">
          <div class="w-full lg:w-5/12 min-w-0 flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white">
            <div class="balanceo-tabla-wrap overflow-x-auto">
              <table class="min-w-full">
                <thead class="bg-blue-500 text-white">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs">Telar</th>
                    <th class="px-3 py-2 text-left text-xs">Producto</th>
                    <th class="px-3 py-2 text-right text-xs">Std/Día</th>
                    <th class="px-3 py-2 text-right text-xs">Pedido</th>
                    <th class="px-3 py-2 text-right text-xs">Producción</th>
                    <th class="px-3 py-2 text-right text-xs">Saldo</th>
                    <th class="px-3 py-2 text-center text-xs">F.Inicio</th>
                    <th class="px-3 py-2 text-center text-xs">F.Final</th>
                  </tr>
                </thead>
                <tbody>${filasHTML}</tbody>
                <tfoot class="bg-gray-100">
                  <tr>
                    <td colspan="3" class="px-3 py-2 text-xs sm:text-sm font-semibold text-gray-700 text-right">Totales:</td>
                    <td class="px-3 py-2 text-right">
                      <input type="number"
                        id="total-pedido-input"
                        aria-label="Total de pedido del grupo"
                        class="${TOTAL_PEDIDO_INPUT_BASE_CLASS} border-gray-300"
                        value="${Math.round(totales.pedido)}">
                    </td>
                    <td class="px-3 py-2 text-xs sm:text-sm text-right font-bold text-gray-900">${Math.round(totales.produccion).toLocaleString('es-MX')}</td>
                    <td class="px-3 py-2 text-xs sm:text-sm text-right font-bold text-green-600" id="total-saldo">${Math.round(totales.saldo).toLocaleString('es-MX')}</td>
                    <td colspan="2"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <p id="balanceo-total-mensaje" class="hidden px-1 pt-1 text-xs text-red-600" role="status"></p>
            <div id="total-disponible" class="hidden">${Math.round(totales.pedido)}</div>
          </div>

          <div class="w-full lg:flex-1 min-w-0 flex flex-col rounded-lg border border-gray-200 bg-white p-2">
            <div id="gantt-ord-container" class="relative min-h-[150px] overflow-auto">
              <div id="gantt-loading" class="p-3 text-sm text-gray-500">Cargando líneas...</div>
              <div id="gantt-preview-loading" class="hidden absolute inset-0 z-20 flex items-center justify-center rounded bg-white/80 backdrop-blur-[1px]" aria-busy="false">
                <span class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm">
                  <i class="fa-solid fa-spinner fa-spin text-blue-500" aria-hidden="true"></i>
                  Actualizando fechas…
                </span>
              </div>
              <div id="gantt-ord"></div>
            </div>
          </div>
        </div>
      </div>
    `;
}

/** Eventos del contenido del modal (antes oninput/onblur/onkeydown/onclick en el HTML). */
function enlazarEventosModal(cuerpo: HTMLElement, ordCompartida: Ord): void {
    cuerpo.addEventListener('input', (e) => {
        const t = e.target as HTMLElement;
        if (t.matches('.pedido-input')) calcularTotalesYFechas(ordCompartida);
        else if (t.id === 'total-pedido-input') actualizarPedidosDesdeTotal(t as HTMLInputElement, ordCompartida);
    });
    cuerpo.addEventListener('keydown', (e) => {
        const t = e.target as HTMLElement;
        if (e.key === 'Enter' && t.matches('.pedido-input')) {
            e.preventDefault();
            t.blur();
        }
    });
    cuerpo.addEventListener('click', (e) => {
        if ((e.target as Element).closest('#btn-balancear-auto')) void aplicarBalanceoAutomatico(ordCompartida);
    });
    // Al salir de un pedido: antes el onblur del input programaba el preview, y este listener
    // (captura) normalizaba y lo volvía a programar en el siguiente tick.
    cuerpo.addEventListener('blur', (e) => {
        if (!(e.target as Element | null)?.classList?.contains('pedido-input')) return;
        schedulePreview(ordCompartida);
        window.setTimeout(() => {
            if (!document.querySelector('.balanceo-orden-modal')) return;
            calcularTotalesYFechas(ordCompartida, true);
            schedulePreview(ordCompartida);
        }, 0);
    }, true);
}

/** Máximo de una fecha del grupo (FechaInicio o FechaFinal), > 1970. */
function fechaMaximaDe(registros: RegistroBalanceo[], campo: 'FechaInicio' | 'FechaFinal'): Date | null {
    let max: Date | null = null;
    registros.forEach((reg) => {
        const fecha = reg[campo] ? parseFechaBackendALocal(String(reg[campo]).trim()) : null;
        if (fecha && fecha.getFullYear() > 1970 && (!max || fecha > max)) max = fecha;
    });
    return max;
}

/** Fecha objetivo: mínimo = inicio más tardío del grupo; valor = fin más tardío. */
function prepararFechaObjetivo(registros: RegistroBalanceo[]): void {
    const fechaInput = document.getElementById('fecha-fin-objetivo-balanceo') as HTMLInputElement | null;
    if (!fechaInput || registros.length === 0) return;

    const fechaInicioMax = fechaMaximaDe(registros, 'FechaInicio');
    if (fechaInicioMax) fechaInput.min = toDateInputValueLocal(fechaInicioMax);
    else fechaInput.removeAttribute('min');

    const fechaMaxima = fechaMaximaDe(registros, 'FechaFinal');
    if (fechaMaxima) fechaInput.value = toDateInputValueLocal(fechaMaxima);
    else if (fechaInput.min) fechaInput.value = fechaInput.min;

    if (fechaInput.min && fechaInput.value && fechaInput.value < fechaInput.min) fechaInput.value = fechaInput.min;
}

async function verDetallesGrupoBalanceo(ordCompartida: Ord): Promise<void> {
    void notify.loading('Cargando balanceo');

    let registros: RegistroBalanceo[];
    try {
        registros = await fetchRegistrosOrdCompartida(ordCompartida);
        await prefetchLineas(registros);
    } catch (err) {
        console.error(err);
        void notify.alert('Revisa la conexión e inténtalo de nuevo.', 'No se pudo abrir el balanceo', 'error');
        return;
    }

    notify.close();
    currentGanttRegistros = registros;

    const valoresActuales = valoresDeLaGrilla(registros);
    const pedidoDe = (r: RegistroBalanceo) => valoresActuales[String(r.Id)] ?? Number(r.TotalPedido || 0);
    const totales = {
        pedido: registros.reduce((s, r) => s + pedidoDe(r), 0),
        produccion: registros.reduce((s, r) => s + (Number(r.Produccion) || 0), 0),
        saldo: registros.reduce((s, r) => s + Math.max(0, pedidoDe(r) - Number(r.Produccion || 0)), 0),
    };
    totalDisponibleBalanceo = totales.pedido;

    const leaderInfo = resolveBalanceoLeader(registros, valoresActuales);
    const filasHTML = registros.map((reg) => filaModal(reg, pedidoDe(reg), leaderInfo?.id === Number(reg.Id))).join('');

    // Modal propio (tabla editable + Gantt): formulario de notify con el guardado en preConfirm.
    void notify.form({
        title: 'Balanceo de orden',
        html: contenidoModal(filasHTML, leaderInfo?.noTelarId || null, totales),
        confirmText: 'Guardar',
        cancelText: 'Cancelar',
        width: '2xl',
        preConfirm: (ctx) => guardarCambiosPedido(ordCompartida, ctx.error),
        didOpen: (cuerpo) => {
            cuerpo.classList.add('balanceo-orden-body');
            const form = cuerpo.closest('form');
            cuerpo.closest('dialog')?.classList.add('balanceo-orden-modal');
            // Validación propia (guardarCambiosPedido), no la burbuja nativa del min de los pedidos.
            form?.setAttribute('novalidate', '');
            // Enter en un campo no guarda (los pedidos ya lo anulan en enlazarEventosModal).
            cuerpo.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && (e.target as Element).matches('input')) e.preventDefault();
            });
            enlazarEventosModal(cuerpo, ordCompartida);

            void (async () => {
                await renderGanttOrd(registros);
                updateBalanceoTotalVisualState();
                await previewFechasExactas(ordCompartida, { force: true });
                prepararFechaObjetivo(registros);
                requestAnimationFrame(() => syncBalanceoGuardarButtonState());
            })();
        },
    });
}

// ==========================
// Botón Balancear del navbar según la selección
// ==========================
function updateBalancearButton(rowElement: Element | null | undefined): void {
    const btn = document.getElementById('btnBalancear') as HTMLButtonElement | null;
    if (!btn) return;

    const ordCompartida = rowElement?.getAttribute('data-ord-compartida');
    const habilitar = !!rowElement && tieneOrdCompartida(ordCompartida);

    btn.disabled = !habilitar;
    btn.classList.remove(...(habilitar ? ['bg-gray-400', 'hover:bg-gray-500'] : ['bg-green-500', 'hover:bg-green-600']));
    btn.classList.add(...(habilitar ? ['bg-green-500', 'hover:bg-green-600'] : ['bg-gray-400', 'hover:bg-gray-500']));
    btn.title = !rowElement
        ? 'Balancear (selecciona un registro con orden compartida)'
        : habilitar
            ? `Balancear orden compartida: ${(ordCompartida as string).trim()}`
            : 'Balancear (este registro no tiene orden compartida)';
}

function abrirBalancearDesdeSeleccion(): void {
    const selectedRow = Array.from(document.querySelectorAll('.selectable-row'))
        .find((r) => r.classList.contains('bg-blue-700') || r.classList.contains('bg-blue-400'));

    if (!selectedRow) {
        void notify.alert('Selecciona un registro con orden compartida para balancear.', 'Sin selección', 'warning');
        return;
    }

    const ordCompartida = selectedRow.getAttribute('data-ord-compartida');
    if (!tieneOrdCompartida(ordCompartida)) {
        void notify.alert('El registro seleccionado no tiene orden compartida para balancear.', 'Sin orden compartida', 'info');
        return;
    }

    void verDetallesGrupoBalanceo(parseInt(ordCompartida.trim(), 10));
}

document.addEventListener('pt:selection-changed', (e) => {
    updateBalancearButton((e as CustomEvent<{ rowElement?: Element | null }>).detail?.rowElement);
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => updateBalancearButton(null));
} else {
    updateBalancearButton(null);
}

// PUENTE PT-TS 1: index.js (menú contextual "Balancear" y detalle de grupo) abre el modal.
window.verDetallesGrupoBalanceo = verDetallesGrupoBalanceo;
// PUENTE PT-TS 1: onclick del botón #btnBalancear en components/navbar/sections/programa-tejido.blade.php.
window.abrirBalancearDesdeSeleccion = abrirBalancearDesdeSeleccion;
// Sin llamador fuera de este archivo: se dejan publicados solo para depurar desde la consola,
// como antes (tests/Js/programa-tejido-bundle.test.ts los comprueba).
window.aplicarBalanceoAutomatico = aplicarBalanceoAutomatico;
window.recargarGanttOrdCompartida = recargarGanttOrdCompartida;
