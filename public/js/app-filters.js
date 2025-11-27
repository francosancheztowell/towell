/**
 * Sistema de filtros para tablas (genérico)
 * - Usa #mainTable, #filtersModal y campos:
 *   - #f_col_select, #f_col_value, #f_list
 * - Mantiene API global: openFilterModal, applyFilters, resetFilters, etc.
 * - Diseño minimalista en los chips, sin clones ni listeners duplicados.
 */

// Estado global de filtros
let activeFilters = {};
let allTableRows = [];

// Namespace interno (por si quieres usarlo desde consola)
const TableFilters = {
    init,
    openModal,
    closeModal,
    addFilter,
    removeFilter,
    apply,
    reset,
    resetAndReload,
    updateBadge,
};
window.TableFilters = TableFilters;

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    init();
});

function init() {
    // Guardar todas las filas de la tabla
    const tbody = document.querySelector('#mainTable tbody');
    if (tbody) {
        allTableRows = Array.from(tbody.querySelectorAll('tr.selectable-row'));
    }

    // Botón de filtros del navbar
    const btnFilters = document.getElementById('btnFilters');
    if (btnFilters) {
        btnFilters.addEventListener('click', (e) => {
            e.preventDefault();
            if (typeof window.openProgramaTejidoFilterModal === 'function') {
                window.openProgramaTejidoFilterModal();
                return;
            }
            openModal();
        });
    }

    // Botón de agregar filtro
    const btnAdd = document.getElementById('f_add_btn');
    if (btnAdd) {
        btnAdd.removeAttribute('onclick');
        btnAdd.addEventListener('click', (e) => {
            e.preventDefault();
            addFilter();
        });
    }

    // Botón de aplicar (antes usaba onclick="confirmFilters()")
    const btnApply = document.querySelector('button[onclick="confirmFilters()"]');
    if (btnApply) {
        btnApply.removeAttribute('onclick');
        btnApply.addEventListener('click', (e) => {
            e.preventDefault();
            apply();
        });
    }

    // Botones de cancelar (antes onclick="closeFilterModal()")
    const btnsCancel = document.querySelectorAll('button[onclick="closeFilterModal()"]');
    btnsCancel.forEach(btn => {
        btn.removeAttribute('onclick');
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            closeModal();
        });
    });

    // Botón de restablecer
    const btnReset = document.getElementById('btnResetFilters');
    if (btnReset) {
        btnReset.removeAttribute('onclick');
        btnReset.addEventListener('click', (e) => {
            e.preventDefault();
            reset();
        });
    }

    // Enter en el input de valor
    const inputValue = document.getElementById('f_col_value');
    if (inputValue) {
        inputValue.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addFilter();
            }
        });
    }
}

function openModal() {
    const modal = document.getElementById('filtersModal');
    if (!modal) return;

    // Llenar select de columnas visibles
    const select = document.getElementById('f_col_select');
    if (select) {
        select.innerHTML = '<option value="">-- Selecciona una columna --</option>';

        const headers = document.querySelectorAll('#mainTable thead th[data-column]');
        headers.forEach(th => {
            if (th.offsetParent !== null) { // Solo columnas visibles
                const column = th.getAttribute('data-column');
                const label = th.textContent.trim();
                const option = document.createElement('option');
                option.value = column;
                option.textContent = label;
                select.appendChild(option);
            }
        });
    }

    // Chips actuales
    renderFilterChips();

    // Limpiar input y hacer focus
    const input = document.getElementById('f_col_value');
    if (input) {
        input.value = '';
        input.focus();
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeModal() {
    const modal = document.getElementById('filtersModal');
    if (!modal) return;

    modal.classList.remove('flex');
    modal.classList.add('hidden');
}

function addFilter() {
    const select = document.getElementById('f_col_select');
    const input = document.getElementById('f_col_value');

    if (!select || !input) return;

    const column = select.value;
    const value = input.value.trim();

    if (!column) {
        select.classList.add('border-red-500', 'ring-2', 'ring-red-300');
        setTimeout(() => {
            select.classList.remove('border-red-500', 'ring-2', 'ring-red-300');
        }, 1500);
        return;
    }

    if (!value) {
        input.classList.add('border-red-500', 'ring-2', 'ring-red-300');
        const prevPlaceholder = input.placeholder;
        input.placeholder = '¡Escribe un valor!';
        setTimeout(() => {
            input.classList.remove('border-red-500', 'ring-2', 'ring-red-300');
            input.placeholder = prevPlaceholder || 'Escribe el valor a filtrar';
        }, 1500);
        return;
    }

    // Agregar / actualizar filtro
    activeFilters[column] = value;

    renderFilterChips();

    // Limpiar campos
    input.value = '';
    select.selectedIndex = 0;
    select.focus();
}

function renderFilterChips() {
    const container = document.getElementById('f_list');
    if (!container) return;

    container.innerHTML = '';
    const keys = Object.keys(activeFilters);

    keys.forEach(column => {
        const value = activeFilters[column];
        const label = getColumnLabel(column);

        const chip = document.createElement('div');
        chip.className =
            'f-chip inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ' +
            'bg-slate-100 text-slate-800 text-xs border border-slate-200';

        chip.setAttribute('data-col', column);
        chip.setAttribute('data-val', value);

        chip.innerHTML = `
            <span class="font-medium">${label}:</span>
            <span>${value}</span>
            <button type="button"
                    class="ml-1 rounded-full p-0.5 hover:bg-slate-200"
                    onclick="removeFilter('${column}')">
                <i class="fa-solid fa-times text-[11px]"></i>
            </button>
        `;
        container.appendChild(chip);
    });
}

function removeFilter(column) {
    delete activeFilters[column];
    renderFilterChips();
}

function getColumnLabel(column) {
    const th = document.querySelector(`#mainTable thead th[data-column="${column}"]`);
    return th ? th.textContent.trim() : column;
}

function apply() {
    // Si hay algo escrito y columna seleccionada, lo agregamos antes
    const select = document.getElementById('f_col_select');
    const input = document.getElementById('f_col_value');
    if (select && input && select.value && input.value.trim()) {
        activeFilters[select.value] = input.value.trim();
    }

    // Releer filtros desde chips (por si se manipularon)
    const chips = document.querySelectorAll('#f_list .f-chip');
    if (chips.length > 0) {
        activeFilters = {};
        chips.forEach(chip => {
            const col = chip.getAttribute('data-col');
            const val = chip.getAttribute('data-val');
            if (col && val != null) {
                activeFilters[col] = val;
            }
        });
    }

    const tbody = document.querySelector('#mainTable tbody');
    if (!tbody) return;

    if (!allTableRows.length) {
        allTableRows = Array.from(tbody.querySelectorAll('tr.selectable-row'));
    }

    const entries = Object.entries(activeFilters);
    let filteredRows = allTableRows;

    if (entries.length) {
        filteredRows = allTableRows.filter(row => {
            return entries.every(([column, value]) => {
                const cell = row.querySelector(`td[data-column="${column}"]`);
                if (!cell) return false;
                const cellText = cell.textContent.toLowerCase().trim();
                const searchText = value.toLowerCase().trim();
                return cellText.includes(searchText);
            });
        });
    }

    // Render
    tbody.innerHTML = '';
    filteredRows.forEach((row, index) => {
        tbody.appendChild(row);
        if (typeof window.selectRow === 'function') {
            row.onclick = () => window.selectRow(row, index);
        }
    });

    updateBadge();
    closeModal();

    const filterCount = entries.length;
    if (typeof window.showToast === 'function') {
        if (filterCount > 0) {
            window.showToast(`${filterCount} filtro(s) aplicado(s)`, 'success');
        } else {
            window.showToast('Mostrando todas las filas', 'info');
        }
    }

    if (typeof window.onFiltersApplied === 'function') {
        window.onFiltersApplied(activeFilters);
    }
}

function reset() {
    const previousCount = Object.keys(activeFilters).length;

    const icon = document.getElementById('iconReset');
    if (icon) {
        icon.classList.add('fa-spin');
        setTimeout(() => icon.classList.remove('fa-spin'), 500);
    }

    activeFilters = {};

    const container = document.getElementById('f_list');
    if (container) container.innerHTML = '';

    const select = document.getElementById('f_col_select');
    const input = document.getElementById('f_col_value');
    if (select) select.selectedIndex = 0;
    if (input) input.value = '';

    const tbody = document.querySelector('#mainTable tbody');
    if (tbody && allTableRows.length) {
        tbody.innerHTML = '';
        allTableRows.forEach((row, index) => {
            tbody.appendChild(row);
            if (typeof window.selectRow === 'function') {
                row.onclick = () => window.selectRow(row, index);
            }
        });
    }

    updateBadge();

    if (previousCount > 0 && typeof window.showToast === 'function') {
        window.showToast('Filtros restablecidos', 'info');
    }
}

function updateBadge() {
    const badge = document.getElementById('filterCount');
    const btn = document.getElementById('btnFilters');
    if (!badge || !btn) return;

    const count = Object.keys(activeFilters).length;

    if (count > 0) {
        badge.textContent = count;
        badge.classList.remove('hidden');
        btn.classList.add('bg-blue-50', 'ring-1', 'ring-blue-300');
    } else {
        badge.classList.add('hidden');
        btn.classList.remove('bg-blue-50', 'ring-1', 'ring-blue-300');
    }
}

function resetAndReload() {
    const icon = document.getElementById('iconReset');
    if (icon) {
        icon.classList.add('fa-spin');
    }

    activeFilters = {};

    setTimeout(() => {
        window.location.reload();
    }, 300);
}

// Exponer funciones globalmente para compatibilidad
window.openFilterModal = openModal;
window.closeFilterModal = closeModal;
window.addFilter = addFilter;
window.removeFilter = removeFilter;
window.applyFilters = apply;
window.confirmFilters = apply;
window.resetFilters = reset;
window.resetFiltersSpin = reset;
window.resetFiltersAndReload = resetAndReload;

// Reset de columnas (si existe la función)
window.resetColumnsSpin = function () {
    const icon = document.getElementById('iconResetColumns');
    if (icon) {
        icon.classList.add('fa-spin');
        setTimeout(() => icon.classList.remove('fa-spin'), 500);
    }

    // Llamar a la función de reset si existe
    if (typeof window.resetColumnVisibility === 'function') {
        window.resetColumnVisibility();
    } else {
        // Fallback: intentar mostrar todas las columnas directamente
        const table = document.getElementById('mainTable');
        if (table) {
            const allCells = table.querySelectorAll('[class*="column-"]');
            allCells.forEach(el => {
                el.style.display = '';
                el.style.visibility = '';
            });
            if (typeof window.showToast === 'function') {
                window.showToast('Columnas restablecidas', 'success');
            }
        }
    }
};
