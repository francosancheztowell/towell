/**
 * Filtro de selección múltiple (botón + panel con casillas) compartido por Resumen general y Ventas históricas.
 * Nada seleccionado = todos. El panel usa position: fixed porque la fila de filtros tiene scroll horizontal
 * y recortaría un popover absoluto.
 */
import { escapeHtml } from '../utils/format';
import { panelPlacement } from './multi-select-placement';

export type MultiSelect = {
    element: HTMLDivElement;
    panel: HTMLDivElement;
    open(): void;
    close(): void;
    sync(): void;
    reposition(): void;
    setAvailable(next: Set<string> | null): void;
};

type MultiSelectOptions = {
    label: string;
    /** Valores posibles, ya en el orden en que se muestran. */
    values?: string[];
    /** Set del llamador; el componente lo muta directamente. */
    selected?: Set<string>;
    /** Texto visible de cada valor. */
    format?: ((value: string) => string) | undefined;
    onChange?: (selected: Set<string>) => void;
};

/** Listas largas (clientes, artículos) solo pintan este máximo; el buscador acota el resto. */
const LIST_LIMIT = 200;
const SEARCH_THRESHOLD = 8;

const openInstances = new Set<MultiSelect>();
let globalListenersBound = false;

/** En tablet el buscador no se enfoca solo: abriría el teclado y taparía la lista. */
const isTouch = () => window.matchMedia?.('(pointer: coarse)').matches ?? false;

/**
 * Toque/clic fuera o Escape cierran el panel. Scroll y resize (p. ej. el teclado de la tablet)
 * solo lo recolocan; antes lo cerraban y en tablet el filtro no llegaba a abrirse.
 */
const bindGlobalListeners = () => {
    if (globalListenersBound) return;
    globalListenersBound = true;
    document.addEventListener('pointerdown', (event) => {
        openInstances.forEach((instance) => { if (!instance.element.contains(event.target as Node)) instance.close(); });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') openInstances.forEach((instance) => instance.close());
    });
    const reposition = () => openInstances.forEach((instance) => instance.reposition());
    window.addEventListener('resize', reposition);
    window.visualViewport?.addEventListener('resize', reposition);
    window.addEventListener('scroll', (event) => {
        openInstances.forEach((instance) => { if (!instance.panel.contains(event.target as Node)) instance.reposition(); });
    }, true);
};

export const createMultiSelect = ({ label, values = [], selected = new Set(), format = (value) => value, onChange = () => {} }: MultiSelectOptions): MultiSelect => {
    bindGlobalListeners();

    const element = document.createElement('div');
    element.className = 'pvoc-select-label pvoc-multi';
    element.innerHTML = `<span>${escapeHtml(label)}</span>
        <button type="button" class="pvoc-multi-button" aria-haspopup="true" aria-expanded="false">
            <span data-multi-label></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="pvoc-multi-panel" hidden>
            <input type="search" class="pvoc-multi-search" placeholder="Buscar…" aria-label="Buscar en ${escapeHtml(label)}" autocomplete="off">
            <div class="pvoc-multi-list" role="group" aria-label="${escapeHtml(label)}"></div>
            <button type="button" class="pvoc-multi-clear">Quitar selección</button>
        </div>`;

    // El innerHTML de arriba garantiza cada nodo.
    const button = element.querySelector<HTMLButtonElement>('.pvoc-multi-button')!;
    const buttonLabel = element.querySelector<HTMLElement>('[data-multi-label]')!;
    const panel = element.querySelector<HTMLDivElement>('.pvoc-multi-panel')!;
    const search = element.querySelector<HTMLInputElement>('.pvoc-multi-search')!;
    const list = element.querySelector<HTMLDivElement>('.pvoc-multi-list')!;
    const clear = element.querySelector<HTMLButtonElement>('.pvoc-multi-clear')!;

    let available: Set<string> | null = null;
    let term = '';
    let shown: string[] = [];

    const summaryText = () => {
        if (!selected.size) return 'Todos';
        const [first = ''] = selected;
        return selected.size === 1 ? String(format(first)) : `${selected.size} seleccionados`;
    };

    const renderList = () => {
        const needle = term.trim().toLowerCase();
        const matches = values.filter((value) => !needle || String(format(value)).toLowerCase().includes(needle));
        // En listas recortadas, lo seleccionado va primero para que no quede fuera del límite.
        shown = matches.length > LIST_LIMIT
            ? [...matches.filter((value) => selected.has(value)), ...matches.filter((value) => !selected.has(value))].slice(0, LIST_LIMIT)
            : matches;

        list.innerHTML = shown.map((value, index) => `<label class="pvoc-multi-option${available && !available.has(value) ? ' is-empty' : ''}">
                <input type="checkbox" data-index="${index}" ${selected.has(value) ? 'checked' : ''}>
                <span>${escapeHtml(format(value))}</span>
            </label>`).join('')
            + (matches.length > shown.length ? `<span class="pvoc-multi-note">Mostrando ${shown.length} de ${matches.length}; escribe para buscar.</span>` : '')
            + (matches.length ? '' : '<span class="pvoc-multi-note">Sin coincidencias</span>');
    };

    const sync = () => {
        buttonLabel.textContent = summaryText();
        element.classList.toggle('is-filtered', selected.size > 0);
        clear.hidden = !selected.size;
        if (!panel.hidden) renderList();
    };

    const position = () => {
        const rect = button.getBoundingClientRect();
        // Con teclado abierto, visualViewport es el alto realmente visible.
        const viewport = { width: window.innerWidth, height: window.visualViewport?.height ?? window.innerHeight };
        const place = panelPlacement(rect, viewport, panel.offsetWidth);
        panel.style.minWidth = `${place.minWidth}px`;
        panel.style.maxHeight = `${place.maxHeight}px`;
        panel.style.top = place.top === null ? '' : `${place.top}px`;
        panel.style.bottom = place.bottom === null ? '' : `${place.bottom}px`;
        panel.style.left = `${place.left}px`;
    };

    const instance: MultiSelect = {
        element,
        panel,
        open() {
            openInstances.forEach((other) => other.close());
            term = '';
            search.value = '';
            search.hidden = values.length <= SEARCH_THRESHOLD;
            panel.hidden = false;
            button.setAttribute('aria-expanded', 'true');
            openInstances.add(instance);
            renderList();
            position();
            if (!search.hidden && !isTouch()) search.focus({ preventScroll: true });
        },
        close() {
            if (panel.hidden) return;
            panel.hidden = true;
            button.setAttribute('aria-expanded', 'false');
            openInstances.delete(instance);
        },
        sync,
        reposition() {
            if (!panel.hidden) position();
        },
        /** Valores con datos bajo el resto de filtros; los demás se muestran atenuados. */
        setAvailable(next) {
            available = next;
            sync();
        },
    };

    const emit = () => {
        sync();
        onChange(selected);
    };

    button.addEventListener('click', () => (panel.hidden ? instance.open() : instance.close()));
    search.addEventListener('input', () => {
        term = search.value;
        renderList();
    });
    list.addEventListener('change', (event) => {
        const checkbox = event.target as HTMLInputElement;
        const value = shown[Number(checkbox.dataset.index)];
        if (value === undefined) return;
        if (checkbox.checked) selected.add(value); else selected.delete(value);
        emit();
    });
    clear.addEventListener('click', () => {
        selected.clear();
        emit();
    });

    sync();
    return instance;
};
