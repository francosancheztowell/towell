import { eventElement } from './dom';
import type {
    MatrixDetailMeta,
    MatrixDetailRow,
    MatrixPeriod,
} from './types';

export class MatrixDetail {
    private details: MatrixDetailRow[][] = [];
    private periods: MatrixPeriod[] = [];
    private decimals = 0;

    public constructor(private readonly result: HTMLElement) {
        this.result.addEventListener('click', (event) => this.handleClick(event));
    }

    public render(meta: Record<string, unknown>): void {
        this.details = Array.isArray(meta.detalles)
            ? meta.detalles as MatrixDetailRow[][]
            : [];
        this.periods = Array.isArray(meta.columnas)
            ? meta.columnas as MatrixPeriod[]
            : [];
        this.decimals = Number(meta.decimales) === 1 ? 1 : 0;
    }

    private handleClick(event: MouseEvent): void {
        const target = eventElement(event);
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
        toggle.querySelector('.periodo-caret')?.classList.toggle('rotate-90', open);
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
        button.querySelector('i')?.classList.toggle('fa-expand', !allOpen);
        button.querySelector('i')?.classList.toggle('fa-compress', allOpen);
    }

    private toggleArea(area: HTMLElement): void {
        const key = area.dataset.areaKey || '';
        const wasOpen = area.classList.contains('area-abierta');
        this.ensureDetailRows(area, key);
        area.classList.toggle('area-abierta', !wasOpen);
        area.querySelector('.area-caret')?.classList.toggle('rotate-90', !wasOpen);

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

        const fragment = document.createDocumentFragment();
        rows.forEach((detail) => fragment.appendChild(this.buildDetailRow(
            detail,
            key,
            area.dataset.areaDot || '#94a3b8',
        )));
        area.after(fragment);
    }

    private buildDetailRow(
        detail: MatrixDetailRow,
        areaKey: string,
        areaDot: string,
    ): HTMLTableRowElement {
        const row = document.createElement('tr');
        row.className = 'detalle-fila bg-slate-100';
        row.dataset.areaKey = areaKey;

        const areaCell = document.createElement('td');
        areaCell.className = 'traza-col-area sticky left-0 z-20 w-[350px] border-b border-slate-300 bg-slate-200 px-3 py-1.5';
        areaCell.style.boxShadow = `inset 4px 0 0 0 ${areaDot}`;
        areaCell.style.borderRight = '2px solid #cbd5e1';

        const labels = document.createElement('span');
        labels.className = 'flex flex-col pl-6 leading-tight';
        const article = document.createElement('span');
        article.className = 'whitespace-nowrap text-[11px] font-semibold text-slate-600';
        article.textContent = detail.articulo || '—';
        const color = document.createElement('span');
        color.className = 'whitespace-nowrap text-[10px] text-slate-400';
        const palette = document.createElement('i');
        palette.className = 'fa-solid fa-palette mr-0.5';
        color.append(palette, detail.color || 'Sin color');
        labels.append(article, color);
        areaCell.appendChild(labels);
        row.appendChild(areaCell);

        const totalCell = document.createElement('td');
        totalCell.className = 'traza-col-total sticky left-[350px] z-[19] min-w-[72px] border-b border-r border-slate-200 bg-blue-50 px-2 py-1.5 text-center text-[12px] font-semibold text-slate-700 tabular-nums';
        totalCell.textContent = detail.total
            ? this.formatNumber(detail.total)
            : '—';
        row.appendChild(totalCell);

        this.periods.forEach((period) => row.appendChild(this.buildPeriodCell(detail, period)));

        return row;
    }

    private buildPeriodCell(
        detail: MatrixDetailRow,
        period: MatrixPeriod,
    ): HTMLTableCellElement {
        const cell = document.createElement('td');
        const value = this.sumPeriod(detail.valores, period.indices);
        cell.className = 'traza-periodo-col border-b border-r border-slate-200 px-2 py-1.5 text-center text-[12px] tabular-nums';
        cell.classList.add(`traza-periodo-col--${period.nivel}`);
        cell.classList.add(...(
            period.nivel === 'mes'
                ? ['font-bold', 'text-slate-700']
                : period.nivel === 'semana'
                    ? ['font-semibold', 'text-slate-600']
                    : ['text-slate-600']
        ));
        if (value === null || value === 0) {
            cell.classList.add('text-slate-300', 'select-none');
        }

        cell.dataset.periodoNivel = period.nivel;
        cell.dataset.mesKey = period.mesClave;
        if (period.semanaClave) cell.dataset.semanaKey = period.semanaClave;
        cell.classList.toggle('hidden', !this.isPeriodVisible(period));
        cell.textContent = value !== null && value !== 0 ? this.formatNumber(value) : '·';

        return cell;
    }

    private sumPeriod(values: Array<number | null>, indices: number[]): number | null {
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
