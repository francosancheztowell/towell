/**
 * Liberar Órdenes — recálculo en la fila de peso de rollo → repeticiones, metros, piezas,
 * total de rollos, No. marbetes y total de piezas. Nada se guarda hasta "Liberar".
 */
import { calcularRollo, esInventSizeFel, parseNumeroGrid, totalRollos } from './logica.ts';

let pesoKarlMayer = '';

/** Peso estándar de Karl Mayer que llega del servidor (data-pagina). */
export function configurarPesoKarlMayer(peso: string): void {
    pesoKarlMayer = peso;
}

const fila = (el: Element) => el.closest<HTMLTableRowElement>('.row-data');

export function bloquearNegativoEnNumero(event: KeyboardEvent): void {
    if (event.key === '-' || event.key === 'Minus') event.preventDefault();
}

export function normalizarNumeroNoNegativo(input: HTMLInputElement | null): void {
    if (input && parseNumeroGrid(input.value) < 0) input.value = '';
}

function actualizarSpanNumerico(row: Element, columnName: string, value: number | null, decimals = 0, includeZero = false): void {
    const span = row.querySelector(`td[data-column="${columnName}"] span`);
    if (!span) return;

    const allow = value !== null && Number.isFinite(value) && (includeZero ? value >= 0 : value > 0);
    if (allow) {
        const rounded = decimals > 0 ? Number(value.toFixed(decimals)) : Math.round(value);
        span.textContent = rounded.toLocaleString('es-MX', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        span.setAttribute('data-calculated-value', String(rounded));
    } else {
        span.textContent = '';
        span.removeAttribute('data-calculated-value');
    }
}

/** FEL en AX o Felpa (tamaño/nombre FELPA): marbetes ×2, mts y pzas por rollo ÷2. Karl Mayer no. */
function esFilaAjusteFelRollo(row: HTMLElement): boolean {
    if (row.dataset.esKm === '1') return false;
    if (row.dataset.esFelpa === '1') return true;
    return esInventSizeFel(row.dataset.inventSizeId);
}

/**
 * Base de las fórmulas: la MISMA que usa el servidor al liberar (TotalPedido, con respaldo a
 * SaldoPedido). Con SaldoPedido, No. marbetes y Total rollos de la grilla no coincidían con lo guardado.
 */
function leerBasePedido(row: HTMLElement): number {
    const base = parseNumeroGrid(row.dataset.basePedido);
    return base > 0 ? base : parseNumeroGrid(row.dataset.saldoPedido);
}

/** Piezas por rollo visibles en la grilla (prioridad sobre data-pzas-rollo del input). */
function leerPzasRolloDesdeFila(row: HTMLElement): number {
    const cell = row.querySelector('td[data-column="PzasRollo"]');
    if (!cell) return 0;
    const span = cell.querySelector('span');
    if (span && span.hasAttribute('data-calculated-value')) return parseNumeroGrid(span.getAttribute('data-calculated-value'));
    return parseNumeroGrid(span ? span.textContent : cell.textContent);
}

export function calcularTotalPzas(changedInput: HTMLInputElement): void {
    if (!changedInput.getAttribute('data-row-id')) return;
    const row = fila(changedInput);
    if (!row) return;

    const totalRollosInput = row.querySelector<HTMLInputElement>('input[data-field="TotalRollos"]');
    const totalPzasSpan = row.querySelector('td[data-column="TotalPzas"] span');

    const pzasRolloDesdeCelda = leerPzasRolloDesdeFila(row);
    const pzasRolloAttr = parseFloat(changedInput.getAttribute('data-pzas-rollo') ?? '') || 0;
    const pzasRollo = pzasRolloDesdeCelda > 0 ? pzasRolloDesdeCelda : pzasRolloAttr;
    if (pzasRollo > 0 && totalRollosInput) totalRollosInput.setAttribute('data-pzas-rollo', String(pzasRollo));

    if (!totalRollosInput || !totalPzasSpan) return;

    const rollos = parseFloat(totalRollosInput.value) || 0;
    // No. marbetes = TotalRollos − producidos; antes de liberar producidos = 0 (mismo que el servidor).
    actualizarSpanNumerico(row, 'SaldoMarbete', rollos > 0 ? Math.ceil(rollos) : null, 0, true);

    const totalPzas = rollos * pzasRollo;
    if (totalPzas > 0) {
        const nuevo = Math.round(totalPzas);
        totalPzasSpan.textContent = nuevo.toLocaleString('es-MX');
        totalPzasSpan.setAttribute('data-calculated-value', nuevo.toString());
    } else {
        totalPzasSpan.textContent = '';
        totalPzasSpan.removeAttribute('data-calculated-value');
    }
}

function actualizarTotalRollosYTotalPzas(row: HTMLElement, pzasRollo: number | null): void {
    const input = row.querySelector<HTMLInputElement>('input[data-field="TotalRollos"]');
    if (!input) return;

    const rollos = totalRollos(leerBasePedido(row), pzasRollo);
    input.value = rollos !== null ? String(rollos) : '';
    input.setAttribute('data-pzas-rollo', pzasRollo && pzasRollo > 0 ? String(pzasRollo) : '0');
    calcularTotalPzas(input);
}

export function recalcularPorPesoRollo(pesoInput: HTMLInputElement): void {
    const row = fila(pesoInput);
    if (!row) return;

    const esFelpa = row.dataset.esFelpa === '1';
    const esKm = row.dataset.esKm === '1';
    // Peso estándar del salón/tamaño: se sugiere SOLO si el input viene vacío (carga inicial sin
    // valor capturado). Si el usuario tipea otro valor, se respeta siempre, KM incluido.
    const pesoSugerido = esKm ? pesoKarlMayer : esFelpa ? '90' : null;
    if (pesoSugerido !== null && (pesoInput.value === '' || parseNumeroGrid(pesoInput.value) <= 0)) {
        pesoInput.value = pesoSugerido;
    }

    const r = calcularRollo({
        pesoRollo: parseNumeroGrid(pesoInput.value),
        pesoCrudo: parseNumeroGrid(row.dataset.pesoCrudo),
        noTiras: parseNumeroGrid(row.dataset.noTiras),
        largoCrudo: parseNumeroGrid(row.dataset.largoCrudo),
        esFelpa,
        esKm,
        ajusteFel: esFilaAjusteFelRollo(row),
    });

    if (!r) {
        actualizarSpanNumerico(row, 'Repeticiones', null);
        actualizarSpanNumerico(row, 'SaldoMarbete', null);
        actualizarSpanNumerico(row, 'MtsRollo', null, 2);
        actualizarSpanNumerico(row, 'PzasRollo', null);
        actualizarTotalRollosYTotalPzas(row, null);
        return;
    }

    actualizarSpanNumerico(row, 'Repeticiones', r.repeticiones, 0, true);
    actualizarSpanNumerico(row, 'MtsRollo', r.mtsRollo, 2);
    actualizarSpanNumerico(row, 'PzasRollo', r.pzasRollo);
    // No. marbetes = TotalRollos (pendientes; al liberar no hay producidos): lo fija calcularTotalPzas.
    actualizarTotalRollosYTotalPzas(row, r.pzasRollo);
}

/**
 * Campos editables (peso de rollo, total de rollos y, en Karl Mayer, tiras). Ninguno se
 * guarda solo: todo viaja al presionar "Liberar".
 */
export function enlazarCamposEditables(): void {
    document.querySelectorAll<HTMLInputElement>('.editable-field').forEach((field) => {
        const fieldName = field.getAttribute('data-field');
        if (fieldName === 'PesoRollo') {
            field.addEventListener('keydown', bloquearNegativoEnNumero);
            const recalcular = () => { normalizarNumeroNoNegativo(field); recalcularPorPesoRollo(field); };
            field.addEventListener('input', recalcular);
            field.addEventListener('blur', recalcular);
        }
        if (fieldName === 'TotalRollos') {
            field.addEventListener('input', () => calcularTotalPzas(field));
            field.addEventListener('blur', () => calcularTotalPzas(field));
        }
        // Karl Mayer: las tiras se capturan y arrastran repeticiones, marbetes y rollos.
        // Se escriben de vuelta al dataset porque de ahí las leen el recálculo y el POST de liberar.
        if (fieldName === 'NoTiras') {
            const recalcularPorTiras = () => {
                normalizarNumeroNoNegativo(field);
                const row = fila(field);
                if (!row) return;
                row.dataset.noTiras = String(field.value || '').trim();
                const peso = row.querySelector<HTMLInputElement>('.peso-rollo-input');
                if (peso) recalcularPorPesoRollo(peso);
            };
            field.addEventListener('keydown', bloquearNegativoEnNumero);
            field.addEventListener('input', recalcularPorTiras);
            field.addEventListener('blur', recalcularPorTiras);
        }
    });

    // Felpa y Karl Mayer: peso estándar sugerido y recálculo al cargar. El resto NO se
    // recalcula al cargar (el PesoRollo del usuario no se persiste: sería pisarlo con el maestro).
    document.querySelectorAll<HTMLInputElement>('tr.row-data[data-es-felpa="1"] .peso-rollo-input, tr.row-data[data-es-km="1"] .peso-rollo-input')
        .forEach(recalcularPorPesoRollo);

    // Alinear Total Pzas con Pzas x Rollo × Total Rollos al cargar (la BD puede traer totales viejos).
    document.querySelectorAll<HTMLInputElement>('tr.row-data input[data-field="TotalRollos"]').forEach(calcularTotalPzas);
}
