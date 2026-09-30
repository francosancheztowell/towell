/**
 * Liberar Órdenes — datos que se completan desde AX: flog (lista y sugerido), Hilo AX,
 * código de dibujo y L.Mat (autocompletado y validación de vigencia).
 */
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { debounce } from '../../../utils/format.ts';
import type { ConfigLiberar } from './logica.ts';

type Rutas = ConfigLiberar['rutas'];

interface Respuesta<T> {
    success?: boolean;
    data?: T;
}

interface OpcionBom {
    bomId?: string;
    bomName?: string;
}

const texto = (el: Element | null | undefined) => (el?.textContent || '').trim();

/* ── Flog ─────────────────────────────────────────────────────────────────
   Toda orden lleva flog (AsignarFlogs = 1 por default en CatCodificados). El renglón cuyo
   Clave AX + Tamaño está en el catálogo TwArticulosFelpas —que ya no es sólo felpa: aplica a
   cualquier artículo— muestra el check "Asignar flogs" marcado y el badge "Decide flog":
   desmarcarlo guarda 0. Los demás renglones no mandan el dato y su AsignarFlogs no se toca.
   El flog se puede editar a mano en cualquier renglón. */

/** Flogs vigentes en AX; null mientras no cargue la lista (entonces no se valida en cliente). */
let flogsVigentes: Set<string> | null = null;

/** Un flog escrito a mano que no existe en AX no se puede liberar: se marca y bloquea. */
function marcarFlogInvalido(input: HTMLInputElement): void {
    const valor = (input.value || '').trim().toUpperCase();
    const invalido = !flogsVigentes ? false : valor !== '' && !flogsVigentes.has(valor);
    input.dataset.flogInvalido = invalido ? 'true' : 'false';
    input.classList.toggle('border-red-500', invalido);
    input.classList.toggle('bg-red-50', invalido);
    input.title = invalido ? 'Este flog no existe o no está vigente en AX' : '';
}

/** Autocompletado libre del flog: todos los flogs vigentes de AX, misma lista que usa Duplicar. */
async function cargarOpcionesFlog(rutas: Rutas): Promise<void> {
    const datalist = document.getElementById('flog-options');
    if (!datalist) return;
    try {
        const flogs = await http.get<unknown>(rutas.flogs);
        if (!Array.isArray(flogs)) return;
        datalist.replaceChildren(...flogs.map((f) => new Option('', String(f))));
        flogsVigentes = new Set(flogs.map((f) => String(f).trim().toUpperCase()));
        document.querySelectorAll<HTMLInputElement>('.flog-input').forEach(marcarFlogInvalido);
    } catch {
        // Sin lista se sigue pudiendo capturar el flog a mano; el servidor lo valida al liberar.
    }
}

async function traerFlogSugerido(row: HTMLElement, rutas: Rutas): Promise<void> {
    const itemId = (row.dataset.itemId || '').trim();
    const inventSizeId = (row.dataset.inventSizeId || '').trim();
    const flogInput = row.querySelector<HTMLInputElement>('.flog-input');
    if (!flogInput || !itemId || !inventSizeId) return;
    // No pisar un flog ya capturado o traído previamente.
    if ((flogInput.value || '').trim() !== '') return;

    flogInput.placeholder = 'Buscando en AX…';
    try {
        const payload = await http.get<Respuesta<{ flogsId?: string }>>(rutas.flog, { params: { itemId, inventSizeId } });
        if (payload?.success && payload.data?.flogsId) {
            flogInput.value = payload.data.flogsId;
            flogInput.placeholder = 'Flog';
            return;
        }
        flogInput.placeholder = 'Sin flog en AX: captúralo';
    } catch {
        // Que falle AX no debe dejar la fila bloqueada: el flog se puede escribir a mano.
        flogInput.placeholder = 'AX no respondió: captúralo';
        notify.warning('No se pudo consultar el flog en AX. Puedes capturarlo a mano.');
    }
}

export function enlazarFlogs(rutas: Rutas): void {
    void cargarOpcionesFlog(rutas);
    document.querySelectorAll<HTMLInputElement>('.flog-input').forEach((input) => {
        input.addEventListener('input', () => marcarFlogInvalido(input));
        input.addEventListener('change', () => marcarFlogInvalido(input));
    });
    // El flog sugerido llega ya renderizado desde el controlador. El endpoint sólo se usa si el
    // usuario desmarca y vuelve a marcar el check dejando la celda vacía.
    document.querySelectorAll<HTMLElement>('tr.row-data[data-decision-flog="1"]').forEach((row) => {
        const check = row.querySelector<HTMLInputElement>('.flog-check');
        check?.addEventListener('change', async () => {
            if (!check.checked) return;
            await traerFlogSugerido(row, rutas);
            row.querySelector<HTMLInputElement>('.flog-input')?.focus();
        });
    });
}

/* ── Hilo AX y código de dibujo ────────────────────────────────────────── */

function actualizarHiloAXSelect(row: Element, valorHiloAX: string): void {
    const select = row.querySelector<HTMLSelectElement>('[data-column="HiloAX"] select');
    const valor = (valorHiloAX || '').trim();
    if (!select || !valor) return;
    select.value = valor;
    // Si el valor no está en las opciones, agregarlo arriba.
    if (!Array.from(select.options).some((opt) => opt.value === valor)) {
        const option = new Option(valor, valor, true, true);
        select.insertBefore(option, select.firstChild);
    }
}

/** Hilo AX vacío → TwTipoHiloId de INVENTTABLE, una sola petición para todos los artículos. */
async function autoFillAllHiloAX(rutas: Rutas): Promise<void> {
    const rowsByItemId = new Map<string, Element[]>();
    document.querySelectorAll('.row-data').forEach((row) => {
        const itemIdCell = row.querySelector('[data-column="ItemId"]');
        const hiloAXCell = row.querySelector('[data-column="HiloAX"]');
        if (!itemIdCell || !hiloAXCell) return;
        const itemId = texto(itemIdCell);
        const select = hiloAXCell.querySelector('select');
        const actual = select ? select.value : texto(hiloAXCell);
        if (!actual && itemId) rowsByItemId.set(itemId, [...(rowsByItemId.get(itemId) ?? []), row]);
    });
    if (rowsByItemId.size === 0) return;

    const itemIds = Array.from(rowsByItemId.keys());
    try {
        const payload = await http.get<Respuesta<Record<string, string>>>(rutas.tipoHilo, { params: { itemIds: itemIds.join(',') } });
        if (!payload?.success || !payload.data) return;
        const data = payload.data;
        itemIds.forEach((itemId) => {
            const hilo = data[itemId];
            if (hilo) (rowsByItemId.get(itemId) ?? []).forEach((row) => actualizarHiloAXSelect(row, hilo));
        });
    } catch {
        // Sin respuesta de AX el select queda vacío y se elige a mano.
    }
}

// La L.Mat autoasignable ya viene resuelta del controlador (índice del lote, filtrando por
// item + talla + SALÓN y descartando las estándar). Si quedó vacía es porque no hay una sola
// candidata para ESE salón: no se vuelve a preguntar por item + talla sin el salón.

/** Código de dibujo: siempre el último del catálogo (item + talla + salón), una sola petición. */
async function autoFillAllCodigoDibujo(rutas: Rutas): Promise<void> {
    const combinations: string[] = [];
    const cellsByKey = new Map<string, Element[]>();
    document.querySelectorAll('.row-data').forEach((row) => {
        const itemIdCell = row.querySelector('[data-column="ItemId"]');
        const inventSizeIdCell = row.querySelector('[data-column="InventSizeId"]');
        const codigoDibujoCell = row.querySelector('[data-column="CodigoDibujo"]');
        if (!itemIdCell || !inventSizeIdCell || !codigoDibujoCell) return;

        const itemId = texto(itemIdCell);
        const inventSizeId = texto(inventSizeIdCell);
        const salon = (row.getAttribute('data-salon-tejido-id') || '').trim();
        if (!itemId || !(inventSizeId || salon)) return;

        const cacheKey = `${itemId}|${inventSizeId}|${salon}`;
        if (!cellsByKey.has(cacheKey)) {
            cellsByKey.set(cacheKey, []);
            combinations.push([itemId, inventSizeId, salon].join('::'));
        }
        cellsByKey.get(cacheKey)?.push(codigoDibujoCell);
    });
    if (combinations.length === 0) return;

    try {
        const payload = await http.get<Respuesta<Record<string, string>>>(rutas.codigoDibujo, { params: { combinations: combinations.join(',') } });
        if (!payload?.success || !payload.data) return;
        Object.entries(payload.data).forEach(([cacheKey, codigo]) => {
            (cellsByKey.get(cacheKey) ?? []).forEach((cell) => { cell.textContent = codigo || ''; });
        });
    } catch {
        // Se queda el código que pintó el servidor.
    }
}

export function autocompletarDesdeAx(rutas: Rutas): void {
    void autoFillAllHiloAX(rutas);
    void autoFillAllCodigoDibujo(rutas);
}

/* ── L.Mat ─────────────────────────────────────────────────────────────── */

const bomOptionsByRow = new Map<string, OpcionBom[]>();

async function fetchBomOptions(rutas: Rutas, itemId: string, inventSizeId: string, term: string, salon: string): Promise<OpcionBom[]> {
    // El itemId siempre viaja: un L.Mat pertenece al producto del renglón (sin él el backend
    // devuelve vacío). El fallback relaja talla y salón, nunca el producto.
    const params: Record<string, string> = { fallback: '1' };
    if (itemId) params.itemId = itemId;
    if (inventSizeId) params.inventSizeId = inventSizeId;
    if (salon) params.salon = salon;
    if (term) params.term = term;
    try {
        const payload = await http.get<Respuesta<OpcionBom[]>>(rutas.bom, { params });
        return payload?.success && Array.isArray(payload.data) ? payload.data : [];
    } catch {
        return [];
    }
}

function llenarDatalist(lista: HTMLElement | null, mensaje: HTMLElement | null, opciones: OpcionBom[], valor: keyof OpcionBom, etiqueta: keyof OpcionBom): void {
    if (!lista) return;
    lista.replaceChildren(...opciones.map((o) => {
        const opt = document.createElement('option');
        opt.value = o[valor] || '';
        opt.label = o[etiqueta] || '';
        return opt;
    }));
    if (!mensaje) return;
    // Sin resultados: aviso debajo del input.
    if (opciones.length === 0) mensaje.textContent = 'sin resultados';
    mensaje.classList.toggle('hidden', opciones.length > 0);
}

function updateBomDatalists(rowId: string, opciones: OpcionBom[]): void {
    llenarDatalist(document.getElementById(`bom-id-options-${rowId}`), document.getElementById(`bom-id-message-${rowId}`), opciones, 'bomId', 'bomName');
    llenarDatalist(document.getElementById(`bom-name-options-${rowId}`), document.getElementById(`bom-name-message-${rowId}`), opciones, 'bomName', 'bomId');
}

/** El botón ya trae disabled:opacity-50 / cursor-not-allowed: basta con la propiedad disabled. */
export function actualizarBotonLiberar(): void {
    const boton = document.getElementById('btn-liberar') as HTMLButtonElement | null;
    if (!boton) return; // sin permiso de crear, el botón no se renderiza
    const hayInvalidos = document.querySelector('tr[data-bom-no-vigente="true"]') !== null;
    boton.disabled = hayInvalidos;
    boton.title = hayInvalidos ? 'Hay renglones con L.Mat no vigente' : 'Liberar';
}

// Un <datalist> sólo sugiere: no impide teclear un L.Mat con Vigente = 0. El servidor lo
// rechaza al liberar; esto marca la celda en cuanto se sale del campo.
function marcarBomNoVigente(row: HTMLElement, rowId: string, invalido: boolean): void {
    const message = document.getElementById(`bom-id-message-${rowId}`);
    row.dataset.bomNoVigente = invalido ? 'true' : 'false';
    row.querySelector('.bom-id-input')?.classList.toggle('border-red-500', invalido);
    if (message) {
        message.textContent = invalido ? 'L.Mat no vigente en AX. Elige uno de la lista.' : '';
        message.classList.toggle('hidden', !invalido);
    }
    actualizarBotonLiberar();
}

// El catálogo del renglón sólo trae L.Mat vigentes: un valor que no esté en él es no vigente.
function validarBomDeFila(row: HTMLElement): void {
    const bomIdInput = row.querySelector<HTMLInputElement>('.bom-id-input');
    if (!bomIdInput) return;
    const rowId = row.getAttribute('data-id') || bomIdInput.dataset.rowId || '';
    const options = bomOptionsByRow.get(rowId);
    if (!options) return; // todavía no se consulta el catálogo de este renglón
    const valor = (bomIdInput.value || '').trim();
    marcarBomNoVigente(row, rowId, valor !== '' && !options.some((o) => (o.bomId || '') === valor));
}

function syncBomFromInput(row: HTMLElement, origen: 'bomId' | 'bomName'): void {
    const bomIdInput = row.querySelector<HTMLInputElement>('.bom-id-input');
    const bomNameInput = row.querySelector<HTMLInputElement>('.bom-name-input');
    if (!bomIdInput || !bomNameInput) return;
    const rowId = row.getAttribute('data-id') || bomIdInput.dataset.rowId || '';
    const options = bomOptionsByRow.get(rowId) || [];

    const [fuente, destino, otro]: [HTMLInputElement, HTMLInputElement, keyof OpcionBom] = origen === 'bomId'
        ? [bomIdInput, bomNameInput, 'bomName']
        : [bomNameInput, bomIdInput, 'bomId'];
    const value = (fuente.value || '').trim();
    if (!value) return;
    const match = options.find((o) => (o[origen] || '') === value);
    if (match) destino.value = match[otro] || '';

    validarBomDeFila(row);
}

export function enlazarBom(rutas: Rutas): void {
    document.querySelectorAll<HTMLElement>('.row-data').forEach((row) => {
        const bomIdInput = row.querySelector<HTMLInputElement>('.bom-id-input');
        const bomNameInput = row.querySelector<HTMLInputElement>('.bom-name-input');
        if (!bomIdInput || !bomNameInput) return;

        const itemId = texto(row.querySelector('[data-column="ItemId"]'));
        const inventSizeId = texto(row.querySelector('[data-column="InventSizeId"]'));
        const rowId = row.getAttribute('data-id') || bomIdInput.dataset.rowId || '';
        const salon = (row.getAttribute('data-salon-tejido-id') || '').trim();
        const mensajes = () => [document.getElementById(`bom-id-message-${rowId}`), document.getElementById(`bom-name-message-${rowId}`)];

        const cargar = async (term: string) => {
            const options = await fetchBomOptions(rutas, itemId, inventSizeId, term, salon);
            bomOptionsByRow.set(rowId, options);
            updateBomDatalists(rowId, options);
            validarBomDeFila(row);
        };
        // Al entrar al campo: todas las opciones del renglón.
        const loadAllOptions = () => {
            mensajes().forEach((m) => m?.classList.add('hidden'));
            void cargar('');
        };
        // NO se autocompleta aunque haya una sola opción: el usuario elige del datalist.
        const debouncedFetch = debounce((sourceInput: HTMLInputElement) => {
            const term = (sourceInput.value || '').trim();
            if (!term) mensajes().forEach((m) => m?.classList.add('hidden'));
            void cargar(term);
        }, 300);

        bomIdInput.addEventListener('focus', loadAllOptions);
        bomNameInput.addEventListener('focus', loadAllOptions);

        // Elegir del datalist sincroniza el otro campo (el label trae su valor); teclear busca.
        const alEscribir = (input: HTMLInputElement, otro: HTMLInputElement, lista: string) => () => {
            input.dataset.userEdited = 'true';
            const value = (input.value || '').trim();
            const datalist = document.getElementById(lista) as HTMLDataListElement | null;
            const selected = datalist ? Array.from(datalist.options).find((opt) => opt.value === value) : undefined;
            if (selected) {
                if (selected.label) otro.value = selected.label;
            } else {
                debouncedFetch(input);
            }
        };
        bomIdInput.addEventListener('input', alEscribir(bomIdInput, bomNameInput, `bom-id-options-${rowId}`));
        bomNameInput.addEventListener('input', alEscribir(bomNameInput, bomIdInput, `bom-name-options-${rowId}`));

        bomIdInput.addEventListener('change', () => syncBomFromInput(row, 'bomId'));
        bomNameInput.addEventListener('change', () => syncBomFromInput(row, 'bomName'));
    });
}
