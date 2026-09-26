/**
 * Captura de Fórmula: tabla de componentes del modal (BOM de AX al crear, EngFormulacionLine al
 * editar/ver). Filas nuevas con select de artículo (calibres) y ConfigId jalado de fibras.
 */
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { delegate } from '../../../utils/dom.ts';
import { el, exigirExito, mensajeError, type RespuestaApi } from '../../urdido/comun/pagina.ts';
import { estado, mostrar, porId } from './estado.ts';
import {
    aplicarMaxConsumoTotal,
    limiteConsumo,
    mensajeLimiteConsumo,
    num,
    redondear2,
    tieneArticulo,
    type Componente,
} from './logica.ts';

interface Calibre {
    ItemId: string;
    ItemName?: string | null;
}

interface RespuestaComponentes extends RespuestaApi {
    componentes?: Componente[];
}

const cache = { calibres: null as Calibre[] | null, fibras: new Map<string, string[]>() };

const CLASE_BASE = 'w-full border border-gray-300 rounded px-2 py-1 text-sm';
const CLASE_FOCO = ' focus:ring-1 focus:ring-blue-500 focus:border-blue-500';
const CLASE_BLOQUEADO = ' bg-gray-50 cursor-not-allowed';

async function obtenerCalibres(): Promise<Calibre[]> {
    if (cache.calibres) return cache.calibres;
    try {
        const r = await http.get<RespuestaApi & { data?: Calibre[] }>(estado.cfg.rutas.calibres);
        cache.calibres = (r.data ?? []).filter((i) => i.ItemId);
        return cache.calibres;
    } catch (err) {
        console.error('No se pudieron cargar calibres', err);
        return [];
    }
}

async function obtenerFibras(itemId: string): Promise<string[]> {
    const guardadas = cache.fibras.get(itemId);
    if (guardadas) return guardadas;
    try {
        const url = `${estado.cfg.rutas.fibras}?${new URLSearchParams({ itemId })}`;
        const r = await http.get<RespuestaApi & { data?: { ConfigId?: string | null }[] }>(url);
        const fibras = (r.data ?? []).map((i) => i.ConfigId ?? '').filter(Boolean);
        cache.fibras.set(itemId, fibras);
        return fibras;
    } catch (err) {
        console.error('No se pudieron cargar fibras', err);
        return [];
    }
}

function opcionesArticulo(select: HTMLSelectElement, calibres: Calibre[], placeholder: string, seleccionado = ''): void {
    select.replaceChildren(
        el('option', { texto: placeholder, attrs: { value: '' } }),
        ...calibres.map((c) => el('option', { texto: c.ItemId, attrs: { value: c.ItemId, 'data-itemname': c.ItemName ?? '' } })),
    );
    select.value = seleccionado;
    select.disabled = calibres.length === 0;
}

function asegurarOpcion(select: HTMLSelectElement, valor: string): void {
    if (valor && ![...select.options].some((o) => o.value === valor)) {
        select.append(el('option', { texto: valor, attrs: { value: valor } }));
    }
}

function campo(index: number, nombre: string): Record<string, string> {
    return { 'data-index': String(index), 'data-field': nombre };
}

function inputTexto(valor: string, clase: string, attrs: Record<string, string>): HTMLInputElement {
    const input = el('input', { clase, attrs: { type: 'text', ...attrs } });
    input.value = valor;
    return input;
}

function td(...hijos: Node[]): HTMLTableCellElement {
    return el('td', { clase: 'px-4 py-2 text-sm' }, ...hijos);
}

/** Pinta la tabla del modal desde estado.componentes (Consumo Total = unitario × Litros, con tope). */
export function renderizarComponentes(): void {
    const tbody = porId<HTMLTableSectionElement>('create_componentes_tbody');
    tbody.replaceChildren();

    if (estado.componentes.length === 0) {
        tbody.append(
            el('tr', {}, el('td', { clase: 'px-4 py-6 text-center text-gray-500', texto: 'No hay componentes para esta formula', attrs: { colspan: '4' } })),
        );
        return;
    }

    const litros = estado.litros;
    const ro = estado.soloLectura;
    const claseRo = ro ? ' bg-gray-100 cursor-not-allowed' : '';

    estado.componentes.forEach((comp, index) => {
        const consumoTotal = aplicarMaxConsumoTotal(comp, num(comp.ConsumoUnitario) * litros, litros);
        const limite = limiteConsumo(comp, litros);
        const sinArticulo = !tieneArticulo(comp);

        let celdas: HTMLTableCellElement[];
        if (comp.esNuevo) {
            // Filas nuevas: select Artículo, Nombre y ConfigId bloqueados (ConfigId sale de fibras).
            const select = el(
                'select',
                { clase: `componente-calibre ${CLASE_BASE}${CLASE_FOCO}${claseRo}`, attrs: { ...campo(index, 'ItemId'), 'aria-label': 'Artículo' } },
                el('option', { texto: 'Cargando...', attrs: { value: '' } }),
            );
            select.disabled = ro;
            const bloqueado = `${CLASE_BASE}${CLASE_BLOQUEADO}`;
            celdas = [
                td(select),
                td(inputTexto(comp.ItemName ?? '', bloqueado, { ...campo(index, 'ItemName'), readonly: '', 'aria-label': 'Nombre' })),
                td(inputTexto(comp.ConfigId ?? '', bloqueado, { ...campo(index, 'ConfigId'), readonly: '', 'aria-label': 'ConfigId' })),
            ];
        } else {
            // Existentes (AX o EngFormulacionLine): Artículo, Nombre y ConfigId de solo lectura.
            const clase = `${CLASE_BASE}${CLASE_FOCO}${claseRo}${CLASE_BLOQUEADO}`;
            const bloquear = (input: HTMLInputElement): HTMLInputElement => {
                input.readOnly = true;
                input.disabled = ro;
                return input;
            };
            celdas = [
                td(bloquear(inputTexto(comp.ItemId ?? '', clase, { ...campo(index, 'ItemId'), 'aria-label': 'Artículo' }))),
                td(bloquear(inputTexto(comp.ItemName ?? '', clase, { ...campo(index, 'ItemName'), 'aria-label': 'Nombre' }))),
                td(bloquear(inputTexto(comp.ConfigId ?? '', clase, { ...campo(index, 'ConfigId'), 'aria-label': 'ConfigId' }))),
            ];
        }

        const total = el('input', {
            clase: `${CLASE_BASE} text-right font-semibold text-blue-700${CLASE_FOCO}${claseRo}`,
            attrs: {
                type: 'number',
                step: '0.01',
                min: '0',
                max: String(limite.max),
                title: sinArticulo ? 'Seleccione Artículo (calibre) primero' : limite.title,
                'aria-label': 'Consumo Total',
                ...campo(index, 'ConsumoTotal'),
            },
        });
        total.value = consumoTotal.toFixed(2);
        total.disabled = sinArticulo || ro;

        const fila = el('tr', { clase: 'hover:bg-blue-50/50 transition-colors' + (index % 2 === 1 ? ' bg-gray-50/30' : '') }, ...celdas, td(total));
        tbody.append(fila);
        if (comp.esNuevo) void iniciarArticuloNuevo(fila, comp, index);
    });
}

async function iniciarArticuloNuevo(fila: HTMLTableRowElement, comp: Componente, index: number): Promise<void> {
    const select = fila.querySelector<HTMLSelectElement>('select[data-field="ItemId"]');
    const config = fila.querySelector<HTMLInputElement>('[data-field="ConfigId"]');
    if (!select) return;

    opcionesArticulo(select, [], 'Cargando...');
    const calibres = await obtenerCalibres();
    opcionesArticulo(select, calibres, 'Selecciona calibre', comp.ItemId ?? '');
    const itemId = comp.ItemId ?? '';
    if (!itemId) return;

    asegurarOpcion(select, itemId);
    select.value = itemId;
    // Jalar ConfigId si ya hay artículo (p. ej. al re-pintar).
    const fibras = await obtenerFibras(itemId);
    const configId = fibras[0] || comp.ConfigId || '';
    if (config) config.value = configId;
    const actual = estado.componentes[index];
    if (actual) actual.ConfigId = configId;
}

async function cambiarArticuloNuevo(select: HTMLSelectElement, index: number): Promise<void> {
    const fila = select.closest('tr');
    const nombre = fila?.querySelector<HTMLInputElement>('[data-field="ItemName"]');
    const config = fila?.querySelector<HTMLInputElement>('[data-field="ConfigId"]');
    const comp = estado.componentes[index];
    const itemId = select.value;

    if (itemId) {
        const itemName = select.selectedOptions[0]?.dataset.itemname ?? '';
        if (nombre) nombre.value = itemName;
        if (comp) {
            comp.ItemId = itemId;
            comp.ItemName = itemName;
        }
        const fibras = await obtenerFibras(itemId);
        const configId = fibras[0] || '';
        if (config) config.value = configId;
        if (comp) comp.ConfigId = configId;
    } else {
        if (nombre) nombre.value = '';
        if (config) config.value = '';
        if (comp) {
            comp.ItemId = '';
            comp.ItemName = '';
            comp.ConfigId = '';
        }
    }
    renderizarComponentes();
}

/** Consumo Total capturado a mano: tope, aviso una vez por tope y ConsumoUnitario = total / Litros. */
function capturarConsumoTotal(input: HTMLInputElement, index: number): void {
    if (input.value === '') return;
    const capturado = num(input.value);
    let nuevo = capturado;
    const comp = estado.componentes[index];
    if (comp) {
        const limite = limiteConsumo(comp, estado.litros);
        nuevo = redondear2(aplicarMaxConsumoTotal(comp, nuevo, estado.litros));
        if (capturado > limite.max) {
            const clave = `${comp.ItemId || ''}-${limite.max}`;
            if (input.dataset.limitAlertKey !== clave) {
                input.dataset.limitAlertKey = clave;
                notify.warning(mensajeLimiteConsumo(comp, estado.litros));
            }
        } else {
            delete input.dataset.limitAlertKey;
        }
        if (parseFloat(input.value) !== nuevo) input.value = nuevo.toFixed(2);
        comp.ConsumoTotal = nuevo;
        comp.ConsumoUnitario = estado.litros > 0 ? nuevo / estado.litros : 0;
    }
}

export function iniciarTablaComponentes(): void {
    const tbody = porId<HTMLTableSectionElement>('create_componentes_tbody');
    const indice = (nodo: HTMLElement): number => parseInt(nodo.dataset.index ?? '', 10);

    delegate<HTMLInputElement | HTMLSelectElement>(tbody, 'change', '[data-field="ItemId"]', (_e, campoArticulo) => {
        const index = indice(campoArticulo);
        if (campoArticulo instanceof HTMLSelectElement) {
            void cambiarArticuloNuevo(campoArticulo, index);
            return;
        }
        // Artículo de texto (filas existentes): sincroniza y re-pinta para habilitar Consumo Total.
        const comp = estado.componentes[index];
        if (comp) comp.ItemId = campoArticulo.value.trim();
        renderizarComponentes();
    });
    delegate<HTMLInputElement>(tbody, 'input', '[data-field="ConsumoTotal"]', (_e, input) => capturarConsumoTotal(input, indice(input)));
    delegate<HTMLInputElement>(tbody, 'focusout', '[data-field="ConsumoTotal"]', (_e, input) => {
        const valor = parseFloat(input.value);
        if (!Number.isNaN(valor)) input.value = redondear2(valor).toFixed(2);
    });
}

/** Componentes tal como están en la tabla (lo que se guarda). */
export function leerComponentesDeTabla(): Componente[] {
    const filas = [...porId('create_componentes_tbody').querySelectorAll('tr')];
    return filas.map((fila, index) => {
        const valor = (nombre: string): string =>
            fila.querySelector<HTMLInputElement | HTMLSelectElement>(`[data-field="${nombre}"]`)?.value ?? '';
        const consumoTotal = num(valor('ConsumoTotal'));
        const original = estado.componentes[index] ?? {};
        // Si el usuario editó el Consumo Total, el unitario se recalcula con los Litros.
        const consumoUnitario = estado.litros > 0 && consumoTotal > 0 ? consumoTotal / estado.litros : num(original.ConsumoUnitario);
        return {
            ItemId: valor('ItemId'),
            ItemName: valor('ItemName'),
            ConfigId: valor('ConfigId'),
            ConsumoUnitario: consumoUnitario,
            ConsumoTotal: consumoTotal,
            Unidad: original.Unidad || '',
            Almacen: original.Almacen || '',
        };
    });
}

export function mostrarErrorComponentes(mensaje: string): void {
    porId('create_componentes_error_message').textContent = mensaje;
    mostrar('create_componentes_error', true);
}

/** Muestra u oculta la tabla (y apaga carga/error). */
export function estadoTabla(visible: boolean): void {
    mostrar('create_componentes_tabla_container', visible);
    mostrar('create_componentes_loading', false);
    mostrar('create_componentes_error', false);
}

export function cargandoComponentes(): void {
    mostrar('create_componentes_loading', true);
    mostrar('create_componentes_error', false);
    mostrar('create_componentes_tabla_container', false);
}

/** Componentes del BOM de AX para una fórmula (crear). */
export async function cargarComponentesAx(formula: string): Promise<void> {
    if (!formula) {
        estado.componentes = [];
        renderizarComponentes();
        estadoTabla(false);
        return;
    }
    cargandoComponentes();
    try {
        const url = `${estado.cfg.rutas.componentes}?${new URLSearchParams({ formula })}`;
        const r = exigirExito(await http.get<RespuestaComponentes>(url), 'Error al cargar componentes');
        estado.componentes = r.componentes ?? [];
        renderizarComponentes();
        estadoTabla(true);
    } catch (err) {
        mostrar('create_componentes_loading', false);
        mostrarErrorComponentes(mensajeError(err, 'Error al cargar componentes'));
    }
}

export function agregarFila(): void {
    if (estado.componentes.some((c) => !tieneArticulo(c))) {
        void notify.alert(
            'No puede agregar una fila nueva si alguna fila no tiene Artículo (calibre) seleccionado. Seleccione el calibre en la fila incompleta o elimínela.',
            'Seleccione calibre primero',
            'warning',
        );
        return;
    }
    estado.componentes.push({ ItemId: '', ItemName: '', ConfigId: '', ConsumoUnitario: 0, Unidad: '', Almacen: '', esNuevo: true });
    renderizarComponentes();
    estadoTabla(true);
    // El formulario escucha 'change' para habilitar Guardar en edición.
    porId('createForm').dispatchEvent(new Event('change'));
}
