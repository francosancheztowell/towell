import { eventElement, queryElement } from './dom';

export class FlogDetail {
    private activeFilter = 'todos';

    public constructor(private readonly result: HTMLElement) {
        this.result.addEventListener('click', (event) => this.handleClick(event));
    }

    public render(): void {
        this.applyLineFilter('todos');
    }

    private handleClick(event: MouseEvent): void {
        const target = eventElement(event);
        if (!target) return;

        const filter = target.closest<HTMLElement>('.flog-lineas-filtro-btn');
        if (filter) {
            this.applyLineFilter(filter.dataset.flogLineaFiltro || 'todos');
            return;
        }

        const toggle = target.closest<HTMLElement>('.flog-card__toggle');
        const card = toggle?.closest<HTMLElement>('.flog-card--collapsible');
        if (!toggle || !card) return;

        const expanded = card.classList.toggle('is-expanded');
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.title = expanded ? 'Ocultar información general' : 'Mostrar información general';
    }

    private applyLineFilter(filter: string): void {
        this.activeFilter = filter || 'todos';
        const wrapper = queryElement<HTMLElement>('#flogs-contenido .flog-lineas-wrap', this.result);
        if (!wrapper) return;

        wrapper.querySelectorAll<HTMLElement>('.flog-lineas-filtro-btn').forEach((button) => {
            button.classList.toggle(
                'is-active',
                String(button.dataset.flogLineaFiltro) === this.activeFilter,
            );
        });

        let visible = 0;
        const rows = wrapper.querySelectorAll<HTMLTableRowElement>(
            '.flog-lineas-table tbody tr[data-estado-linea]',
        );
        rows.forEach((row) => {
            const show = this.activeFilter === 'todos'
                || String(row.dataset.estadoLinea ?? '') === this.activeFilter;
            row.hidden = !show;
            if (show) visible++;
        });

        queryElement<HTMLElement>('.flog-lineas-sin-filtro', wrapper)
            ?.classList.toggle('hidden', visible > 0 || rows.length === 0);
    }
}

