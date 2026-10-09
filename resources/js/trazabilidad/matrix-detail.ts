import type {
    MatrixAreaRow,
    MatrixDetailRow,
    MatrixPeriod,
    SparseValues,
} from './types';

/**
 * Matriz de piezas por área × mes → semana → día.
 *
 * El servidor solo pinta las columnas de mes. Las de semana/día de un mes se
 * construyen aquí la primera vez que se expande ese mes (o con "Expandir todo"),
 * a partir de `meta.columnas`, `meta.filas` y `meta.totales`; lo mismo las filas
 * de artículo/color (`meta.detalles`).
 */
export class MatrixDetail {
    private details: MatrixDetailRow[][] = [];
    private areaRows: MatrixAreaRow[] = [];
    private totals: SparseValues = {};
    private periods: MatrixPeriod[] = [];
    private decimals = 0;

    private readonly result: HTMLElement;

    public constructor(result: HTMLElement) {
        this.result = result;
        this.result.addEventListener('click', (event) => this.handleClick(event));
    }

    public render(meta: Record<string, unknown>): void {
        this.details = Array.isArray(meta.detalles)
            ? meta.detalles as MatrixDetailRow[][]
            : [];
        this.areaRows = Array.isArray(meta.filas)
            ? meta.filas as MatrixAreaRow[]
            : [];
        this.totals = meta.totales !== null && typeof meta.totales === 'object'
            ? meta.totales as SparseValues
            : {};
        this.periods = Array.isArray(meta.columnas)
            ? meta.columnas as MatrixPeriod[]
            : [];
        this.decimals = Number(meta.decimales) === 1 ? 1 : 0;
    }

    private handleClick(event: MouseEvent): void {
        // Element (no HTMLElement): el clic puede caer en el <svg> del caret.
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        const periodToggle = target.closest<HTMLElement>('[data-periodo-toggle]');
        if (periodToggle) {
            event.preventDefault();
            event.stopPropagation();
            this.togglePeriod(periodToggle);
            return;
        }

        const expandAll = target.closest<HTMLElement>('[data-expandir-periodos]');
        if (expandAll) {
            this.toggleAll(expandAll);
            return;
        }

        const area = target.closest<HTMLElement>('.area-fila');
        if (area) this.toggleArea(area);
    }

    private togglePeriod(toggle: HTMLElement): void {
        const level = toggle.dataset.periodoToggle;
        const key = toggle.dataset.periodoKey || '';
        const open = toggle.getAttribute('aria-expanded') !== 'true';

        if (level === 'mes') {
            this.changeMonth(key, open);
        } else {
            this.changeWeek(key, open);
        }

        this.updateExpandAllButton();
    }

    private toggleAll(button: HTMLElement): void {
        const open = button.getAttribute('aria-expanded') !== 'true';

        if (open) {
            new Set(this.periods.map((period) => period.mesClave))
                .forEach((monthKey) => this.ensureMonthColumns(monthKey));
        }

        this.result.querySelectorAll<HTMLElement>(
            '[data-periodo-nivel="semana"], [data-periodo-nivel="dia"]',
        ).forEach((element) => element.classList.toggle('hidden', !open));

        this.result.querySelectorAll<HTMLElement>('[data-periodo-toggle]')
            .forEach((toggle) => this.updateToggle(toggle, open));

        this.result.querySelectorAll<HTMLElement>(
            '[data-periodo-nivel="mes"], [data-periodo-nivel="semana"]',
        ).forEach((element) => element.classList.toggle('traza-periodo-subtotal-abierto', open));

        this.updateExpandAllButton();
    }

    private changeWeek(weekKey: string, open: boolean): void {
        this.result.querySelectorAll<HTMLElement>(
            `[data-periodo-nivel="dia"][data-semana-key="${CSS.escape(weekKey)}"]`,
        ).forEach((element) => element.classList.toggle('hidden', !open));

        const toggle = this.result.querySelector<HTMLElement>(
            `[data-periodo-toggle="semana"][data-periodo-key="${CSS.escape(weekKey)}"]`,
        );
        if (toggle) this.updateToggle(toggle, open);
        this.markSubtotal('semana', weekKey, open);
    }

    private changeMonth(monthKey: string, open: boolean): void {
        if (open) this.ensureMonthColumns(monthKey);

        this.result.querySelectorAll<HTMLElement>(
            `[data-periodo-nivel="semana"][data-mes-key="${CSS.escape(monthKey)}"]`,
        ).forEach((element) => element.classList.toggle('hidden', !open));

        const toggle = this.result.querySelector<HTMLElement>(
            `[data-periodo-toggle="mes"][data-periodo-key="${CSS.escape(monthKey)}"]`,
        );
        if (toggle) this.updateToggle(toggle, open);
        this.markSubtotal('mes', monthKey, open);

        if (!open) {
            this.result.querySelectorAll<HTMLElement>(
                `[data-periodo-nivel="semana"][data-mes-key="${CSS.escape(monthKey)}"] [data-periodo-toggle="semana"]`,
            ).forEach((weekToggle) => this.changeWeek(weekToggle.dataset.periodoKey || '', false));
        }
    }

    private updateToggle(toggle: HTMLElement, open: boolean): void {
        toggle.setAttribute('aria-expanded', String(open));
    }

    private markSubtotal(level: 'mes' | 'semana', key: string, open: boolean): void {
        const attribute = level === 'mes' ? 'data-mes-key' : 'data-semana-key';
        this.result.querySelectorAll<HTMLElement>(
            `[data-periodo-nivel="${level}"][${attribute}="${CSS.escape(key)}"]`,
        ).forEach((element) => element.classList.toggle('traza-periodo-subtotal-abierto', open));
    }

    private updateExpandAllButton(): void {
        const button = this.result.querySelector<HTMLElement>('[data-expandir-periodos]');
        if (!button) return;

        const toggles = [...this.result.querySelectorAll<HTMLElement>('[data-periodo-toggle]')];
        const allOpen = toggles.length > 0
            && toggles.every((toggle) => toggle.getAttribute('aria-expanded') === 'true');

        button.setAttribute('aria-expanded', String(allOpen));
        const label = button.querySelector<HTMLElement>('[data-expandir-periodos-label]');
        if (label) label.textContent = allOpen ? 'Contraer todo' : 'Expandir todo';
    }

    // --- Columnas de semana/día (perezosas, por mes) ---

    private table(): HTMLTableElement | null {
        return this.result.querySelector<HTMLTableElement>('table.traza-matriz-periodos');
    }

    /** Meses cuyas columnas de semana/día ya están en el DOM. */
    private builtMonths(table: HTMLTableElement): Set<string> {
        return new Set(
            [...(table.tHead?.querySelectorAll<HTMLElement>('[data-periodo-nivel="semana"]') ?? [])]
                .map((cell) => cell.dataset.mesKey || ''),
        );
    }

    /**
     * Inserta, ocultas, las columnas de semana/día del mes justo después de su
     * columna de mes en cada fila: encabezado, áreas, detalle y total.
     */
    private ensureMonthColumns(monthKey: string): void {
        const table = this.table();
        if (!table || this.builtMonths(table).has(monthKey)) return;

        const periods = this.periods.filter(
            (period) => period.nivel !== 'mes' && period.mesClave === monthKey,
        );
        if (!periods.length) return;

        const caret = table.tHead?.querySelector('.periodo-caret') ?? null;
        const anchorSelector = `[data-periodo-nivel="mes"][data-mes-key="${CSS.escape(monthKey)}"]`;

        [...table.rows].forEach((row) => {
            const anchor = row.querySelector(anchorSelector);
            if (!anchor) return;

            const build = this.cellBuilderFor(row, caret);
            if (!build) return;

            anchor.after(...periods.map((period) => build(period, false)));
        });
    }

    private cellBuilderFor(
        row: HTMLTableRowElement,
        caret: Element | null,
    ): ((period: MatrixPeriod, visible: boolean) => HTMLTableCellElement) | null {
        const section = row.parentElement?.tagName;

        if (section === 'THEAD') {
            return (period, visible) => this.buildHeaderCell(period, visible, caret);
        }
        if (section === 'TFOOT') {
            return (period, visible) => this.buildTotalCell(period, visible);
        }
        if (row.classList.contains('detalle-fila')) {
            const detail = this.details[Number(row.dataset.areaKey)]?.[Number(row.dataset.detalleIndex)];

            return detail ? (period, visible) => this.buildPeriodCell(detail, period, visible) : null;
        }

        const areaRow = this.areaRows[Number(row.dataset.areaIndex)];

        return areaRow ? (period, visible) => this.buildAreaCell(areaRow, period, visible) : null;
    }

    private createPeriodCell<K extends 'th' | 'td'>(
        tag: K,
        period: MatrixPeriod,
        visible: boolean,
    ): HTMLElementTagNameMap[K] {
        const cell = document.createElement(tag);
        cell.className = `traza-periodo-col traza-periodo-col--${period.nivel}`;
        cell.dataset.periodoNivel = period.nivel;
        cell.dataset.mesKey = period.mesClave;
        if (period.semanaClave) cell.dataset.semanaKey = period.semanaClave;
        cell.classList.toggle('hidden', !visible);

        return cell;
    }

    private buildHeaderCell(
        period: MatrixPeriod,
        visible: boolean,
        caret: Element | null,
    ): HTMLTableCellElement {
        const cell = this.createPeriodCell('th', period, visible);
        cell.scope = 'col';

        const labels = document.createElement('span');
        const label = document.createElement('span');
        label.textContent = period.label;
        const subLabel = document.createElement('small');
        subLabel.textContent = period.subLabel;
        labels.append(label, subLabel);

        if (period.nivel === 'semana') {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'traza-periodo-toggle';
            button.dataset.periodoToggle = 'semana';
            button.dataset.periodoKey = period.semanaClave || '';
            button.setAttribute('aria-expanded', 'false');
            button.title = 'Mostrar días de la semana';

            labels.className = 'flex flex-col leading-tight';
            const subtotal = document.createElement('span');
            subtotal.className = 'traza-periodo-subtotal';
            subtotal.textContent = 'Subtotal semana';
            labels.appendChild(subtotal);

            if (caret) button.appendChild(caret.cloneNode(true));
            button.appendChild(labels);
            cell.appendChild(button);

            return cell;
        }

        labels.className = period.destacada
            ? 'flex flex-col leading-tight font-semibold text-blue-700'
            : 'flex flex-col leading-tight';
        subLabel.className = 'font-normal text-zinc-500';
        cell.appendChild(labels);

        return cell;
    }

    private buildAreaCell(
        area: MatrixAreaRow,
        period: MatrixPeriod,
        visible: boolean,
    ): HTMLTableCellElement {
        const cell = this.createPeriodCell('td', period, visible);
        const value = this.sumPeriod(area.valores, period.indices);

        if (value === null) {
            cell.classList.add('traza-vacio');
            cell.textContent = '—';

            return cell;
        }

        cell.style.color = area.text;
        cell.style.backgroundColor = this.cellBackground(area, period);
        cell.textContent = this.formatNumber(value);

        return cell;
    }

    private cellBackground(area: MatrixAreaRow, period: MatrixPeriod): string {
        const dayIndex = period.indices[0];
        const alpha = period.nivel === 'dia' && dayIndex !== undefined
            ? area.alfas[dayIndex]
            : undefined;

        return alpha === undefined || !area.rgb ? area.tint : `rgba(${area.rgb},${alpha})`;
    }

    private buildTotalCell(period: MatrixPeriod, visible: boolean): HTMLTableCellElement {
        const cell = this.createPeriodCell('td', period, visible);
        const value = this.sumPeriod(this.totals, period.indices);
        cell.textContent = value === null ? '—' : this.formatNumber(value);

        return cell;
    }

    // --- Filas de artículo/color (perezosas, por área) ---

    private toggleArea(area: HTMLElement): void {
        const key = area.dataset.areaKey || '';
        const wasOpen = area.classList.contains('area-abierta');
        this.ensureDetailRows(area, key);
        area.classList.toggle('area-abierta', !wasOpen);
        area.querySelector('.traza-area-toggle')?.setAttribute('aria-expanded', String(!wasOpen));

        this.result.querySelectorAll<HTMLElement>(
            `tr.detalle-fila[data-area-key="${CSS.escape(key)}"]`,
        ).forEach((row) => {
            row.hidden = wasOpen;
            row.classList.toggle('hidden', wasOpen);
        });
    }

    private ensureDetailRows(area: HTMLElement, key: string): void {
        if (
            this.result.querySelector(`tr.detalle-fila[data-area-key="${CSS.escape(key)}"]`)
        ) {
            return;
        }

        const areaIndex = Number(key);
        const rows = Number.isInteger(areaIndex) ? this.details[areaIndex] : undefined;
        if (!rows?.length) return;

        // Solo las columnas que existen en el DOM: meses y los meses ya expandidos.
        const table = this.table();
        const built = table ? this.builtMonths(table) : new Set<string>();
        const periods = this.periods.filter(
            (period) => period.nivel === 'mes' || built.has(period.mesClave),
        );

        const fragment = document.createDocumentFragment();
        rows.forEach((detail, index) => fragment.appendChild(this.buildDetailRow(
            detail,
            index,
            key,
            area.dataset.areaDot || '#94a3b8',
            periods,
        )));
        area.after(fragment);
    }

    private buildDetailRow(
        detail: MatrixDetailRow,
        index: number,
        areaKey: string,
        areaDot: string,
        periods: MatrixPeriod[],
    ): HTMLTableRowElement {
        const row = document.createElement('tr');
        row.className = 'detalle-fila';
        row.dataset.areaKey = areaKey;
        row.dataset.detalleIndex = String(index);

        const areaCell = document.createElement('th');
        areaCell.scope = 'row';
        areaCell.className = 'traza-col-area';
        areaCell.style.setProperty('--area-dot', areaDot);

        const labels = document.createElement('span');
        labels.className = 'flex flex-col py-1.5 pl-[2.125rem] pr-3 leading-tight';
        const article = document.createElement('span');
        article.className = 'truncate text-xs font-semibold text-zinc-700';
        article.textContent = detail.articulo || '—';
        const color = document.createElement('span');
        color.className = 'truncate text-xs font-normal text-zinc-500';
        color.textContent = detail.color || 'Sin color';
        labels.append(article, color);
        areaCell.appendChild(labels);
        row.appendChild(areaCell);

        const totalCell = document.createElement('td');
        totalCell.className = 'traza-col-total';
        totalCell.textContent = detail.total
            ? this.formatNumber(detail.total)
            : '—';
        row.appendChild(totalCell);

        periods.forEach((period) => row.appendChild(
            this.buildPeriodCell(detail, period, this.isPeriodVisible(period)),
        ));

        return row;
    }

    private buildPeriodCell(
        detail: MatrixDetailRow,
        period: MatrixPeriod,
        visible: boolean,
    ): HTMLTableCellElement {
        const cell = this.createPeriodCell('td', period, visible);
        const value = this.sumPeriod(detail.valores, period.indices);
        if (value === null || value === 0) cell.classList.add('traza-vacio');
        cell.textContent = value !== null && value !== 0 ? this.formatNumber(value) : '—';

        return cell;
    }

    private sumPeriod(values: SparseValues, indices: number[]): number | null {
        let hasValue = false;
        let sum = 0;

        indices.forEach((index) => {
            const value = values[index];
            if (value === null || value === undefined) return;
            hasValue = true;
            sum += Number(value);
        });

        return hasValue ? Number(sum.toFixed(this.decimals)) : null;
    }

    private isPeriodVisible(period: MatrixPeriod): boolean {
        if (period.nivel === 'mes') return true;

        const selector = period.nivel === 'semana'
            ? `[data-periodo-toggle="mes"][data-periodo-key="${CSS.escape(period.mesClave)}"]`
            : `[data-periodo-toggle="semana"][data-periodo-key="${CSS.escape(period.semanaClave || '')}"]`;

        return this.result.querySelector<HTMLElement>(selector)
            ?.getAttribute('aria-expanded') === 'true';
    }

    private formatNumber(value: number): string {
        return Number(value).toLocaleString('es-MX', {
            minimumFractionDigits: this.decimals,
            maximumFractionDigits: this.decimals,
        });
    }
}
