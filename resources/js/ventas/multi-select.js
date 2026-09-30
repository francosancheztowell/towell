/**
 * Filtro de selección múltiple (botón + panel con casillas) compartido por Resumen general y Ventas históricas.
 * Nada seleccionado = todos. El panel usa position: fixed porque la fila de filtros tiene scroll horizontal
 * y recortaría un popover absoluto.
 */

/** Listas largas (clientes, artículos) solo pintan este máximo; el buscador acota el resto. */
const LIST_LIMIT = 200;
const SEARCH_THRESHOLD = 8;

const openInstances = new Set();
let globalListenersBound = false;

const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
}[character]));

/** Clic fuera, Escape, scroll o resize cierran cualquier panel abierto. */
const bindGlobalListeners = () => {
    if (globalListenersBound) return;
    globalListenersBound = true;
    document.addEventListener('mousedown', (event) => {
        openInstances.forEach((instance) => { if (!instance.element.contains(event.target)) instance.close(); });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') openInstances.forEach((instance) => instance.close());
    });
    window.addEventListener('resize', () => openInstances.forEach((instance) => instance.close()));
    window.addEventListener('scroll', (event) => {
        openInstances.forEach((instance) => { if (!instance.panel.contains(event.target)) instance.close(); });
    }, true);
};

/**
 * @param {object} options
 * @param {string} options.label
 * @param {Array} options.values Valores posibles, ya en el orden en que se muestran.
 * @param {Set} options.selected Set del llamador; el componente lo muta directamente.
 * @param {(value) => string} [options.format] Texto visible de cada valor.
 * @param {(selected: Set) => void} [options.onChange]
 */
export const createMultiSelect = ({ label, values = [], selected = new Set(), format = (value) => value, onChange = () => {} }) => {
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

    const button = element.querySelector('.pvoc-multi-button');
    const buttonLabel = element.querySelector('[data-multi-label]');
    const panel = element.querySelector('.pvoc-multi-panel');
    const search = element.querySelector('.pvoc-multi-search');
    const list = element.querySelector('.pvoc-multi-list');
    const clear = element.querySelector('.pvoc-multi-clear');

    let options = values;
    let available = null;
    let term = '';
    let shown = [];

    const summaryText = () => {
        if (!selected.size) return 'Todos';
        const [first] = selected;
        return selected.size === 1 ? String(format(first)) : `${selected.size} seleccionados`;
    };

    const renderList = () => {
        const needle = term.trim().toLowerCase();
        const matches = options.filter((value) => !needle || String(format(value)).toLowerCase().includes(needle));
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
        panel.style.minWidth = `${Math.max(rect.width, 200)}px`;
        panel.style.top = `${rect.bottom + 4}px`;
        panel.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - panel.offsetWidth - 8))}px`;
    };

    const instance = {
        element,
        panel,
        open() {
            openInstances.forEach((other) => other.close());
            term = '';
            search.value = '';
            search.hidden = options.length <= SEARCH_THRESHOLD;
            panel.hidden = false;
            button.setAttribute('aria-expanded', 'true');
            openInstances.add(instance);
            renderList();
            position();
            if (!search.hidden) search.focus({ preventScroll: true });
        },
        close() {
            if (panel.hidden) return;
            panel.hidden = true;
            button.setAttribute('aria-expanded', 'false');
            openInstances.delete(instance);
        },
        sync,
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
        const value = shown[Number(event.target.dataset.index)];
        event.target.checked ? selected.add(value) : selected.delete(value);
        emit();
    });
    clear.addEventListener('click', () => {
        selected.clear();
        emit();
    });

    sync();
    return instance;
};
