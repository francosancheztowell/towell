/**
 * Sistema de filtros reactivos para tablas
 */

let filtersState = { active: false, count: 0, values: {} };

const $btnFilters = document.getElementById('btnFilters');
if ($btnFilters) {
    $btnFilters.addEventListener('click', function() {
        openFilterModal();
    });
}

function openFilterModal() {
    const m = document.getElementById('filtersModal');
    if (!m) return;
    
    try {
        const sel = document.getElementById('f_col_select');
        const list = document.getElementById('f_list');
        const inp = document.getElementById('f_col_value');
        
        if (sel) {
            sel.innerHTML = '';
            const headers = Array.from(document.querySelectorAll('#mainTable thead th'))
                .filter(th => th && th.offsetParent !== null);
            const optDefault = document.createElement('option');
            optDefault.value = '';
            optDefault.textContent = 'Selecciona una columna…';
            sel.appendChild(optDefault);
            
            headers.forEach(th => {
                const key = th.getAttribute('data-column') || '';
                const label = (th.textContent || '').trim();
                if (!key || !label) return;
                const opt = document.createElement('option');
                opt.value = key;
                opt.textContent = label;
                sel.appendChild(opt);
            });
        }
        
        if (list) list.innerHTML = '';
        if (filtersState && filtersState.values) {
            Object.entries(filtersState.values).forEach(([col, val]) => addFilterChip(col, val));
        }
        if (inp) inp.value = '';
    } catch (e) {}
    
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function closeFilterModal() {
    const m = document.getElementById('filtersModal');
    if (!m) return;
    m.classList.remove('flex');
    m.classList.add('hidden');
}

function confirmFilters() {
    const chips = Array.from(document.querySelectorAll('#f_list .f-chip'));
    const values = {};
    chips.forEach(ch => {
        const col = ch.getAttribute('data-col');
        const val = ch.getAttribute('data-val') || '';
        if (col && val !== '') {
            values[col] = val;
        }
    });
    
    const count = Object.keys(values).length;
    filtersState.values = values;
    applyFilters(count);
    
    if (typeof window.applyTableFilters === 'function') {
        try {
            window.applyTableFilters(values);
        } catch (e) {}
    } else {
        filterMainTable(values);
    }
    
    closeFilterModal();
}

function applyFilters(count) {
    filtersState.active = count > 0;
    filtersState.count = count;
    updateFilterUI();
}

function clearFilters() {
    filtersState = { active: false, count: 0, values: {} };
    updateFilterUI();
    
    const list = document.getElementById('f_list');
    if (list) list.innerHTML = '';
    const inp = document.getElementById('f_col_value');
    if (inp) inp.value = '';
    const sel = document.getElementById('f_col_select');
    if (sel) sel.selectedIndex = 0;
    
    if (typeof window.applyTableFilters === 'function') {
        try {
            window.applyTableFilters({});
        } catch (e) {}
    }
}

function addFilterChip(col, value) {
    const list = document.getElementById('f_list');
    if (!list) return;
    
    const existing = list.querySelector(`.f-chip[data-col="${CSS.escape(col)}"]`);
    if (existing) {
        existing.setAttribute('data-val', value);
        existing.querySelector('.f-chip-text').textContent = `${getColumnLabel(col)}: ${value}`;
        return;
    }
    
    const chip = document.createElement('div');
    chip.className = 'f-chip inline-flex items-center gap-2 px-2 py-1 rounded-full bg-blue-50 text-blue-700 text-xs border border-blue-200';
    chip.setAttribute('data-col', col);
    chip.setAttribute('data-val', value);
    chip.innerHTML = `
        <span class="f-chip-text">${getColumnLabel(col)}: ${value}</span>
        <button type="button" class="p-1 text-blue-600 hover:text-blue-800" aria-label="Quitar" onclick="removeFilterChip('${col}')">
            <i class="fa-solid fa-xmark"></i>
        </button>
    `;
    list.appendChild(chip);
}

function removeFilterChip(col) {
    const list = document.getElementById('f_list');
    if (!list) return;
    
    const chip = list.querySelector(`.f-chip[data-col="${CSS.escape(col)}"]`);
    if (chip) list.removeChild(chip);
    
    const values = currentFiltersFromChips();
    const count = Object.keys(values).length;
    filtersState.values = values;
    applyFilters(count);
    
    if (typeof window.applyTableFilters === 'function') {
        try {
            window.applyTableFilters(values);
        } catch (e) {}
    } else {
        filterMainTable(values);
    }
}

function getColumnLabel(col) {
    const th = document.querySelector(`#mainTable thead th[data-column="${CSS.escape(col)}"]`);
    return th ? (th.textContent || '').trim() : col;
}

function currentFiltersFromChips() {
    const chips = Array.from(document.querySelectorAll('#f_list .f-chip'));
    const values = {};
    chips.forEach(ch => {
        const col = ch.getAttribute('data-col');
        const val = ch.getAttribute('data-val') || '';
        if (col && val !== '') {
            values[col] = val;
        }
    });
    return values;
}

(function() {
    const btn = document.getElementById('f_add_btn');
    if (btn) {
        btn.addEventListener('click', function() {
            const sel = document.getElementById('f_col_select');
            const inp = document.getElementById('f_col_value');
            const col = sel ? sel.value : '';
            const val = inp ? (inp.value || '').trim() : '';
            
            if (!col || val === '') return;
            
            addFilterChip(col, val);
            if (inp) inp.value = '';
            
            const values = currentFiltersFromChips();
            const count = Object.keys(values).length;
            filtersState.values = values;
            applyFilters(count);
            
            if (typeof window.applyTableFilters === 'function') {
                try {
                    window.applyTableFilters(values);
                } catch (e) {}
            }
        });
    }
})();

function updateFilterUI() {
    const badge = document.getElementById('filterCount');
    const btn = document.getElementById('btnFilters');
    if (!badge || !btn) return;
    
    if (filtersState.active) {
        badge.textContent = String(filtersState.count);
        badge.classList.remove('hidden');
        btn.classList.add('bg-blue-50', 'ring-1', 'ring-blue-300');
    } else {
        badge.classList.add('hidden');
        btn.classList.remove('bg-blue-50', 'ring-1', 'ring-blue-300');
    }
}

function resetFiltersSpin() {
    const icon = document.getElementById('iconReset');
    if (!icon) return;
    icon.classList.add('spin-1s');
    setTimeout(() => icon.classList.remove('spin-1s'), 900);
    clearFilters();
}

function filterMainTable(values) {
    try {
        const tb = document.querySelector('#mainTable tbody');
        if (!tb) return;
        
        if (!window.layoutFilterBaseRows || !Array.isArray(window.layoutFilterBaseRows) || window.layoutFilterBaseRows.length === 0) {
            window.layoutFilterBaseRows = Array.from(tb.querySelectorAll('tr.selectable-row'));
        }
        
        const base = window.layoutFilterBaseRows;
        const entries = Object.entries(values || {});
        const filtered = entries.length ? base.filter(tr => {
            return entries.every(([col, val]) => {
                const cell = tr.querySelector(`[data-column="${CSS.escape(col)}"]`);
                return cell ? (cell.textContent || '').toLowerCase().includes(String(val).toLowerCase()) : false;
            });
        }) : base.slice();
        
        tb.innerHTML = '';
        filtered.forEach(tr => tb.appendChild(tr));
    } catch (e) {}
}

function resetColumnsSpin() {
    const icon = document.getElementById('iconResetColumns');
    if (icon) {
        icon.classList.add('spin-1s');
        setTimeout(() => icon.classList.remove('spin-1s'), 900);
    }
    if (typeof resetColumnVisibility === 'function') resetColumnVisibility();
}

// Exponer funciones globalmente
window.openFilterModal = openFilterModal;
window.applyFilters = applyFilters;
window.clearFilters = clearFilters;
window.confirmFilters = confirmFilters;
window.resetFiltersSpin = resetFiltersSpin;
window.removeFilterChip = removeFilterChip;




















