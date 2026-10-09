import { eventElement, queryElement } from './dom';

/** Filtro por estado de las líneas del Flog (botones con aria-pressed). */
export class FlogDetail {
    private readonly result: HTMLElement;

    public constructor(result: HTMLElement) {
        this.result = result;
        this.result.addEventListener('click', (event) => {
            const filter = eventElement(event)?.closest<HTMLElement>('[data-flog-linea-filtro]');
            if (filter) this.applyLineFilter(filter.dataset.flogLineaFiltro || 'todos');
        });
    }

    public render(): void {
        this.applyLineFilter('todos');
    }

    private applyLineFilter(filter: string): void {
        const wrapper = queryElement<HTMLElement>('#flogs-contenido .flog-lineas-wrap', this.result);
        if (!wrapper) return;

        wrapper.querySelectorAll<HTMLElement>('[data-flog-linea-filtro]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.flogLineaFiltro === filter));
        });

        let visible = 0;
        const rows = wrapper.querySelectorAll<HTMLTableRowElement>('.flog-lineas-table tbody tr[data-estado-linea]');
        rows.forEach((row) => {
            const show = filter === 'todos' || (row.dataset.estadoLinea ?? '') === filter;
            row.hidden = !show;
            if (show) visible++;
        });

        queryElement<HTMLElement>('.flog-lineas-sin-filtro', wrapper)
            ?.classList.toggle('hidden', visible > 0 || rows.length === 0);
    }
}
