import { escapeHtml } from '../utils/format';

/**
 * Sidebar de secciones (Compara, Ventas históricas) y subsecciones. Una subsección puede tener su
 * propio panel (data-pvoc-nav = panel), como Pedidos Históricos dentro de Compara. Reemplaza las pestañas:
 * mostrar una sección emite 'pvoc:tab' (lo escuchan el botón Filtrar y Ventas históricas) y
 * una subsección de Ventas históricas emite 'vh:report' sobre su panel.
 * history = reportes de Ventas históricas ({ id, title }), en el orden en que se muestran.
 */
export const bindSidebar = (root: HTMLElement, history: { id: string; title: string }[]) => {
    const sidebar = root.querySelector<HTMLElement>('[data-pvoc-sidebar]');
    if (!sidebar) return;

    const historyList = sidebar.querySelector<HTMLElement>('[data-pvoc-nav-history]');
    if (historyList) {
        historyList.innerHTML = history.map(({ id, title }) =>
            `<li><button type="button" class="pvoc-nav-item" data-pvoc-nav="history" data-pvoc-nav-sub="${escapeHtml(id)}">${escapeHtml(title)}</button></li>`).join('');
    }

    // Ocultar / mostrar toda la sidebar: arranca cerrada y se cierra al elegir una subsección.
    const toggle = sidebar.querySelector<HTMLElement>('[data-pvoc-sidebar-toggle]');
    const setCollapsed = (collapsed: boolean) => {
        sidebar.classList.toggle('is-collapsed', collapsed);
        toggle?.setAttribute('aria-expanded', String(!collapsed));
        toggle?.setAttribute('title', collapsed ? 'Mostrar secciones' : 'Ocultar secciones');
        toggle?.setAttribute('aria-label', collapsed ? 'Mostrar secciones' : 'Ocultar secciones');
    };
    toggle?.addEventListener('click', () => setCollapsed(!sidebar.classList.contains('is-collapsed')));
    setCollapsed(true);

    // Cada sección se despliega o pliega sin cambiar lo que se está viendo.
    sidebar.querySelectorAll<HTMLElement>('[data-pvoc-nav-section]').forEach((button) => button.addEventListener('click', () => {
        const list = document.getElementById(button.getAttribute('aria-controls') ?? '');
        const expanded = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(expanded));
        if (list) list.hidden = !expanded;
    }));

    let activeTab = root.querySelector<HTMLElement>('[data-pvoc-panel]:not(.is-hidden)')?.dataset.pvocPanel ?? 'summary';

    const show = (button: HTMLElement) => {
        const tab = button.dataset.pvocNav ?? 'summary';
        const sub = button.dataset.pvocNavSub ?? '';
        sidebar.querySelectorAll<HTMLElement>('[data-pvoc-nav]').forEach((item) => {
            const active = item === button;
            item.classList.toggle('is-active', active);
            if (active) item.setAttribute('aria-current', 'page'); else item.removeAttribute('aria-current');
        });
        // El grupo resaltado es el que contiene el botón: Pedidos Históricos es su propio panel pero vive en Compara.
        sidebar.querySelectorAll<HTMLElement>('[data-pvoc-nav-group]').forEach((group) =>
            group.classList.toggle('is-current', group.contains(button)));

        const panel = root.querySelector<HTMLElement>(`[data-pvoc-panel="${tab}"]`);
        if (!panel) return;
        if (tab === 'history') panel.dataset.vhRequested = sub;
        else {
            panel.querySelectorAll<HTMLElement>('[data-pvoc-subpanel]').forEach((subpanel) =>
                subpanel.classList.toggle('is-hidden', subpanel.dataset.pvocSubpanel !== sub));
        }

        if (tab !== activeTab) {
            activeTab = tab;
            root.querySelectorAll<HTMLElement>('[data-pvoc-panel]').forEach((element) =>
                element.classList.toggle('is-hidden', element.dataset.pvocPanel !== tab));
            root.dispatchEvent(new CustomEvent('pvoc:tab', { detail: tab }));
        }
        // Si Ventas históricas aún no se monta, toma el reporte de data-vh-requested al montarse.
        if (tab === 'history') panel.dispatchEvent(new CustomEvent('vh:report', { detail: sub }));
    };

    sidebar.addEventListener('click', (event) => {
        const button = (event.target as Element).closest<HTMLElement>('[data-pvoc-nav]');
        if (!button) return;
        show(button);
        setCollapsed(true);
    });
};
