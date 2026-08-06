import { eventElement, numberValue, queryElement } from './dom';
import { ScrollManager } from './scroll-manager';
import type { RollosRow } from './types';

export class ProductionDetail {
    private activeFilter = 'todos';

    public constructor(
        private readonly result: HTMLElement,
        private readonly rollosModal: HTMLElement,
        private readonly scroll: ScrollManager,
    ) {
        this.bind();
    }

    public render(): void {
        this.applyFilter('todos');
    }

    private bind(): void {
        this.result.addEventListener('click', (event) => {
            const target = eventElement(event);
            if (!target) return;

            const machineCard = target.closest<HTMLElement>('.prod-rollos-maquina-card');
            if (machineCard) {
                this.openRollos(machineCard);
                return;
            }

            const filter = target.closest<HTMLElement>('.prod-filter-btn');
            if (filter) {
                this.applyFilter(filter.dataset.filter || 'todos');
                return;
            }

            if (target.closest('[data-abrir-modal-telares]')) {
                this.openLoomSummary();
                return;
            }

            if (target.closest('[data-modal-resumen-telares-close]')) {
                this.closeLoomSummary();
            }
        });

        this.rollosModal.addEventListener('click', (event) => {
            if (eventElement(event)?.closest('[data-modal-rollos-close]')) {
                this.closeRollos();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            this.closeRollos();
            this.closeLoomSummary();
        });
    }

    private applyFilter(filter: string): void {
        this.activeFilter = filter || 'todos';
        const area = queryElement<HTMLElement>('#produccion-contenido .prod-area--crudo', this.result);
        if (!area) return;

        area.querySelectorAll<HTMLElement>('.prod-filter-btn').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.filter === this.activeFilter);
        });

        let visible = 0;
        const cards = area.querySelectorAll<HTMLElement>('.prod-crudo-card');
        cards.forEach((card) => {
            const show = this.activeFilter === 'todos' || card.dataset.estado === this.activeFilter;
            card.hidden = !show;
            if (show) visible++;
        });

        queryElement<HTMLElement>('.prod-sin-resultados', area)
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
            tableRow.className = 'border-b border-slate-100 hover:bg-slate-50/80';
            this.appendCell(tableRow, row.orden || '—', 'px-3 py-2 font-mono font-semibold');
            this.appendCell(
                tableRow,
                [row.articulo, row.nombreArticulo].filter(Boolean).join(' · ') || '—',
            );
            this.appendCell(
                tableRow,
                [row.color, row.nombreColor].filter(Boolean).join(' · ') || '—',
            );
            this.appendCell(tableRow, this.formatNumber(pieces, 0), 'px-3 py-2 text-right tabular-nums');
            this.appendCell(tableRow, this.formatNumber(kg, 2), 'px-3 py-2 text-right tabular-nums');
            body.appendChild(tableRow);
        });

        this.setText('#modal-rollos-maquina-titulo', card.dataset.maquina || 'Detalle máquina');
        this.setText('#modal-rollos-total-pzas', this.formatNumber(totalPieces, 0));
        this.setText('#modal-rollos-total-kg', this.formatNumber(totalKg, 2));
        this.rollosModal.classList.remove('hidden');
        this.rollosModal.style.display = 'flex';
        this.scroll.sync();
    }

    private closeRollos(): void {
        if (this.rollosModal.classList.contains('hidden')) return;

        this.rollosModal.classList.add('hidden');
        this.rollosModal.style.display = '';
        this.scroll.sync();
    }

    private openLoomSummary(): void {
        const modal = queryElement<HTMLElement>('#modal-resumen-telares');
        if (!modal) return;

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        this.scroll.sync();
    }

    private closeLoomSummary(): void {
        const modal = queryElement<HTMLElement>('#modal-resumen-telares');
        if (!modal || modal.classList.contains('hidden')) return;

        modal.classList.add('hidden');
        modal.style.display = '';
        this.scroll.sync();
    }

    private appendCell(
        row: HTMLTableRowElement,
        value: string,
        className = 'px-3 py-2',
    ): void {
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

