import { eventElement, numberValue, queryElement } from './dom';
import type { RollosRow } from './types';

export class ProductionDetail {
    private readonly result: HTMLElement;

    public constructor(result: HTMLElement) {
        this.result = result;
        this.result.addEventListener('click', (event) => {
            const target = eventElement(event);
            if (!target) return;

            const machineCard = target.closest<HTMLElement>('.prod-rollos-maquina-card');
            if (machineCard) {
                this.openRollos(machineCard);
                return;
            }

            const filter = target.closest<HTMLElement>('[data-prod-filtro]');
            if (filter) this.applyFilter(filter.dataset.prodFiltro || 'todos');
        });
    }

    public render(): void {
        this.applyFilter('todos');
    }

    private applyFilter(filter: string): void {
        const area = queryElement<HTMLElement>('[data-prod-crudo]', this.result);
        if (!area) return;

        area.querySelectorAll<HTMLElement>('[data-prod-filtro]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.prodFiltro === filter));
        });

        let visible = 0;
        const cards = area.querySelectorAll<HTMLElement>('.prod-crudo-card');
        cards.forEach((card) => {
            const show = filter === 'todos' || card.dataset.estado === filter;
            card.hidden = !show;
            if (show) visible++;
        });

        queryElement<HTMLElement>('[data-prod-sin-resultados]', area)
            ?.classList.toggle('hidden', visible > 0 || cards.length === 0);
    }

    private openRollos(card: HTMLElement): void {
        let rows: RollosRow[] = [];
        try {
            const parsed: unknown = JSON.parse(card.dataset.filas || '[]');
            if (Array.isArray(parsed)) rows = parsed as RollosRow[];
        } catch {
            window.notify?.warning('No fue posible leer el detalle de esta máquina.');
        }

        const body = queryElement<HTMLTableSectionElement>('#modal-rollos-maquina-body');
        if (!body) return;

        body.replaceChildren();
        let totalPieces = 0;
        let totalKg = 0;

        rows.forEach((row) => {
            const pieces = numberValue(row.cantidad);
            const kg = numberValue(row.peso);
            totalPieces += pieces;
            totalKg += kg;

            const tableRow = document.createElement('tr');
            this.appendCell(tableRow, row.orden || '—', 'px-3 py-3 font-mono font-medium text-zinc-900');
            this.appendCell(tableRow, [row.articulo, row.nombreArticulo].filter(Boolean).join(' · ') || '—');
            this.appendCell(tableRow, [row.color, row.nombreColor].filter(Boolean).join(' · ') || '—');
            this.appendCell(tableRow, this.formatNumber(pieces, 0), 'px-3 py-3 text-right tabular-nums');
            this.appendCell(tableRow, this.formatNumber(kg, 2), 'px-3 py-3 text-right tabular-nums');
            body.appendChild(tableRow);
        });

        this.setText('[data-rollos-maquina]', card.dataset.maquina || '');
        this.setText('#modal-rollos-total-pzas', this.formatNumber(totalPieces, 0));
        this.setText('#modal-rollos-total-kg', this.formatNumber(totalKg, 2));
        this.setText('[data-rollos-ordenes]', this.formatNumber(new Set(rows.map((row) => row.orden)).size, 0));
        // flux:modal: foco atrapado, Esc y cierre al tocar fuera ya vienen incluidos.
        window.Flux?.modal('rollos-maquina').show();
    }

    private appendCell(row: HTMLTableRowElement, value: string, className = 'px-3 py-3 text-zinc-600'): void {
        const cell = document.createElement('td');
        cell.className = className;
        cell.textContent = value;
        row.appendChild(cell);
    }

    private formatNumber(value: number, decimals: number): string {
        return value.toLocaleString('es-MX', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    }

    private setText(selector: string, value: string): void {
        const element = queryElement<HTMLElement>(selector);
        if (element) element.textContent = value;
    }
}
