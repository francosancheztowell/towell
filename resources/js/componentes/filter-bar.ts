/**
 * x-ui.filter-bar en modo cliente (DS-10): filtra las filas [data-filter-row] de la tabla
 * que indica data-ui-filter-target. Reusa el motor puro de Programa Tejido (solo lectura;
 * se reubica a un lugar común en ADOP), así "contiene / igual / empieza" significan lo mismo
 * en toda la app.
 *
 *   input[data-ui-filter-text]              busca en el texto de la fila (contiene)
 *   select[data-ui-filter-column="campo"]   compara con el atributo data-campo de la fila
 *            [data-ui-filter-operator]      operador del motor (default 'equals')
 *   [data-ui-filter-clear]                  limpia todo
 *   [data-ui-filter-count]                  "N de M"
 *   [data-ui-filter-empty]                  se muestra si nada coincide
 */
import { checkFilterMatch, type ColumnFilter } from '../programa-tejido/filter-engine.ts';

export interface CriterioColumna extends ColumnFilter {
    column: string;
}

export interface FilaFiltrable {
    texto: string;
    valor(column: string): string | null;
}

/** Texto: contiene (sin mayúsculas). Columnas: AND entre criterios; vacío = sin filtro. */
export function filaCoincide(fila: FilaFiltrable, texto: string, criterios: CriterioColumna[]): boolean {
    if (texto.trim() !== '' && !checkFilterMatch(fila.texto, { operator: 'contains', value: texto })) {
        return false;
    }

    return criterios.every((c) => c.value === '' || checkFilterMatch(fila.valor(c.column), c));
}

const barras = new WeakSet<HTMLElement>();

function aplicar(barra: HTMLElement): void {
    const selector = barra.dataset.uiFilterTarget;
    const destino = selector ? document.querySelector(selector) : null;
    if (!destino) return;

    const texto = barra.querySelector<HTMLInputElement>('[data-ui-filter-text]')?.value ?? '';
    const criterios: CriterioColumna[] = [...barra.querySelectorAll<HTMLSelectElement | HTMLInputElement>('[data-ui-filter-column]')].map(
        (el) => ({
            column: el.dataset.uiFilterColumn ?? '',
            operator: el.dataset.uiFilterOperator ?? 'equals',
            value: el.value,
        }),
    );

    const filas = [...destino.querySelectorAll<HTMLElement>('[data-filter-row]')];
    let visibles = 0;
    for (const tr of filas) {
        const ok = filaCoincide(
            { texto: tr.textContent ?? '', valor: (col) => tr.getAttribute(`data-${col}`) },
            texto,
            criterios,
        );
        tr.hidden = !ok;
        if (ok) visibles++;
    }

    const conteo = barra.querySelector<HTMLElement>('[data-ui-filter-count]');
    if (conteo) conteo.textContent = `${visibles} de ${filas.length}`;
    destino.querySelectorAll<HTMLElement>('[data-ui-filter-empty]').forEach((el) => {
        el.hidden = visibles > 0 || filas.length === 0;
    });
}

export function iniciarFiltros(root: ParentNode = document): void {
    root.querySelectorAll<HTMLElement>('[data-ui-filter-bar][data-ui-filter-target]').forEach((barra) => {
        if (barras.has(barra)) return;
        barras.add(barra);
        barra.addEventListener('input', () => aplicar(barra));
        barra.addEventListener('change', () => aplicar(barra));
        barra.addEventListener('click', (e) => {
            if (!(e.target instanceof Element) || !e.target.closest('[data-ui-filter-clear]')) return;
            barra.querySelectorAll<HTMLInputElement | HTMLSelectElement>('[data-ui-filter-text], [data-ui-filter-column]').forEach((el) => {
                el.value = '';
            });
            aplicar(barra);
        });
    });
}

/** Re-aplica los filtros de todas las barras (p. ej. después de insertar filas). */
export function refrescarFiltros(root: ParentNode = document): void {
    root.querySelectorAll<HTMLElement>('[data-ui-filter-bar][data-ui-filter-target]').forEach(aplicar);
}
