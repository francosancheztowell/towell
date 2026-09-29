/**
 * Fila seleccionada de una tabla de Ventas: un clic la marca y queda así hasta elegir otra fila o
 * tocar un filtro (clearRowSelection). Las tablas se re-pintan con innerHTML al expandir o colapsar,
 * así que la selección se guarda por clave en el contenedor y se vuelve a aplicar tras cada re-pintado.
 *
 * Clave de fila: data-row-key si la trae (filas de árbol, cuyo texto se repite entre ramas); si no,
 * el texto de su primera celda más el número de aparición de ese texto en el contenedor.
 */
const rowKey = (row, rows) => {
    if (row.dataset.rowKey) return row.dataset.rowKey;
    const text = row.cells[0]?.textContent.trim() ?? '';
    const occurrence = rows.filter((other) => !other.dataset.rowKey && other.cells[0]?.textContent.trim() === text).indexOf(row);
    return `${text}#${occurrence}`;
};

const bodyRows = (container) => [...container.querySelectorAll('tbody tr')].filter((row) => !row.querySelector('.pvoc-empty'));

const applySelection = (container) => {
    const selected = container.dataset.selectedRow;
    const rows = bodyRows(container);
    rows.forEach((row) => row.classList.toggle('is-selected', Boolean(selected) && rowKey(row, rows) === selected));
};

export const bindRowSelection = (container) => {
    if (!container || container.dataset.rowSelectionBound) return;
    container.dataset.rowSelectionBound = '1';

    container.addEventListener('click', (event) => {
        const row = event.target.closest('tbody tr');
        if (!row || !container.contains(row) || row.querySelector('.pvoc-empty')) return;
        container.dataset.selectedRow = rowKey(row, bodyRows(container));
        applySelection(container);
    });

    new MutationObserver(() => applySelection(container)).observe(container, { childList: true });
};

export const clearRowSelection = (container) => {
    if (!container) return;
    delete container.dataset.selectedRow;
    applySelection(container);
};
