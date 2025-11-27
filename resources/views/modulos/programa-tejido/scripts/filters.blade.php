// ===== Sistema de Filtros Avanzado – Programa Tejido =====

// Estado de filtros
// NOTE: `filters` se declara globalmente en state.blade.php
let quickFilters = {
    ultimos: false,
    divididos: false,
    enProceso: false,
};

let lastFilterState = null;

// ===== Filtros Rápidos =====
const quickFilterConfig = {
    ultimos: {
        label: 'Últimos',
        icon: 'fa-flag-checkered',
        color: 'emerald',
        description: 'Mostrar solo los últimos registros de cada telar',
        check: (row) => {
            const cell = row.querySelector('[data-column="Ultimo"]');
            if (!cell) return false;
            const val = (cell.dataset.value || cell.textContent || '').toString().trim();
            return val === '1' || val.toUpperCase() === 'UL';
        },
    },
    divididos: {
        label: 'Telares divididos',
        icon: 'fa-code-branch',
        color: 'violet',
        description: 'Telares con orden compartida',
        check: (row) => {
            const cell = row.querySelector('[data-column="OrdCompartida"]');
            if (!cell) return false;
            const val = (cell.dataset.value || cell.textContent || '').toString().trim();
            return val !== '' && val !== '0' && val.toLowerCase() !== 'null';
        },
    },
    enProceso: {
        label: 'En proceso',
        icon: 'fa-spinner',
        color: 'amber',
        description: 'Registros actualmente en proceso',
        check: (row) => {
            const cell = row.querySelector('[data-column="EnProceso"]');
            if (!cell) return false;
            const checkbox = cell.querySelector('input[type="checkbox"]');
            if (checkbox) return checkbox.checked;
            const val = (cell.dataset.value || cell.textContent || '').toString().trim();
            return val === '1' || val.toLowerCase() === 'true';
        },
    },
};

// ===== Aplicar filtros (quick + personalizados) =====
function applyProgramaTejidoFilters() {
    const tb = tbodyEl();
    if (!tb) return;

    const currentState = JSON.stringify({ filters, quickFilters });
    if (currentState === lastFilterState) {
        return;
    }

    const rows = Array.from(tb.querySelectorAll('.selectable-row'));
    const hasQuickFilters = Object.values(quickFilters).some(Boolean);
    const hasCustomFilters = filters.length > 0;

    const activeQuickChecks = hasQuickFilters
        ? Object.entries(quickFilters)
            .filter(([, active]) => active)
            .map(([key]) => quickFilterConfig[key].check)
        : [];

    const customChecks = hasCustomFilters
        ? filters.map(f => ({
            column: f.column,
            value: String(f.value || '').toLowerCase(),
            operator: f.operator || 'contains',
        }))
        : [];

    let visibleRows = 0;

    rows.forEach(row => {
        const matchesQuick = !hasQuickFilters || activeQuickChecks.some(check => check(row));
        const matchesCustom = !hasCustomFilters || customChecks.every(f => {
            const cell = row.querySelector(`[data-column="${f.column}"]`);
            if (!cell) return false;
            const cellValue = (cell.dataset.value || cell.textContent || '').toLowerCase().trim();

            switch (f.operator) {
                case 'equals':   return cellValue === f.value;
                case 'starts':   return cellValue.startsWith(f.value);
                case 'ends':     return cellValue.endsWith(f.value);
                case 'not':      return !cellValue.includes(f.value);
                case 'empty':    return cellValue === '';
                case 'notEmpty': return cellValue !== '';
                default:         return cellValue.includes(f.value);
            }
        });

        const shouldShow = matchesQuick && matchesCustom;
        if (shouldShow) {
            row.style.display = '';
            row.classList.remove('filter-hidden');
            visibleRows++;
        } else {
            row.style.display = 'none';
            row.classList.add('filter-hidden');
        }
    });

    allRows = rows;
    clearRowCache();
    if (inlineEditMode) applyInlineModeToRows();

    lastFilterState = currentState;
    updateFilterUI();

    const totalFilters = filters.length + Object.values(quickFilters).filter(Boolean).length;
    if (typeof showToast === 'function') {
        if (totalFilters > 0) {
            showToast(`${visibleRows} resultado(s) encontrado(s)`, visibleRows > 0 ? 'success' : 'warning');
        } else {
            showToast('Mostrando todas las filas', 'info');
        }
    }

    if (typeof window.onFiltersApplied === 'function') {
        window.onFiltersApplied(filters);
    }
}

// ===== Quick filters: toggle + UI =====
function toggleQuickFilter(filterKey) {
    quickFilters[filterKey] = !quickFilters[filterKey];
    lastFilterState = null;
    applyProgramaTejidoFilters();

    const btn = document.querySelector(`[data-quick-filter="${filterKey}"]`);
    if (btn) {
        updateQuickFilterButton(btn, filterKey);
    }
}

function renderQuickFilterButtons() {
    return Object.entries(quickFilterConfig)
        .map(([key, config]) => {
            const isActive = quickFilters[key];
            return `
                <button
                    type="button"
                    data-quick-filter="${key}"
                    class="relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs sm:text-sm
                           ${isActive ? 'bg-blue-50 text-blue-800 ring-1 ring-blue-200' : 'bg-gray-50 text-slate-700 hover:bg-gray-100'}
                           transition-all">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg ${isActive ? 'bg-blue-100' : 'bg-white shadow-sm'}">
                        <i class="fa-solid ${config.icon} ${isActive ? 'text-blue-600' : 'text-gray-400'}"></i>
                    </div>
                    <div class="flex-1 text-left">
                        <div class="font-medium">${config.label}</div>
                        <div class="text-[10px] ${isActive ? 'text-blue-600/70' : 'text-gray-400'}">${config.description}</div>
                    </div>
                    ${isActive ? `<div class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-500 text-white text-[10px]"><i class="fa-solid fa-check"></i></div>` : ''}
                </button>
            `;
        })
        .join('');
}

function updateQuickFilterButton(btn, filterKey) {
    const config = quickFilterConfig[filterKey];
    const isActive = quickFilters[filterKey];

    btn.className =
        `relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs sm:text-sm transition-all ` +
        (isActive
            ? 'bg-blue-50 text-blue-800 ring-1 ring-blue-200'
            : 'bg-gray-50 text-slate-700 hover:bg-gray-100');

    const iconWrapper = btn.querySelector('div');
    if (iconWrapper) {
        iconWrapper.className = `flex h-8 w-8 items-center justify-center rounded-lg ${isActive ? 'bg-blue-100' : 'bg-white shadow-sm'}`;
    }

    const icon = btn.querySelector('i');
    if (icon) {
        icon.className = `fa-solid ${config.icon} ${isActive ? 'text-blue-600' : 'text-gray-400'}`;
    }

    const descEl = btn.querySelector('.text-\\[10px\\]');
    if (descEl) {
        descEl.className = `text-[10px] ${isActive ? 'text-blue-600/70' : 'text-gray-400'}`;
    }
}

function updateQuickFilterButtonInModal(key) {
    const btn = document.querySelector(`[data-quick-filter="${key}"]`);
    if (!btn) return;
    updateQuickFilterButton(btn, key);
}

// ===== Modal Filtros – Programa Tejido (SweetAlert2) =====
function openProgramaTejidoFilterModal() {
    if (typeof Swal === 'undefined') {
        console.warn('SweetAlert2 no está disponible');
        return;
    }

    const html = `
        <div class="w-full max-h-[80vh] overflow-hidden flex flex-col">
            <section class="flex-1 overflow-y-auto bg-white px-6 py-5 space-y-5">
                <div class="space-y-3">

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        ${renderQuickFilterButtons()}
                    </div>
                </div>

                <div id="activeFiltersSection" class="${filters.length === 0 ? 'hidden' : ''} space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold tracking-wide uppercase text-gray-500">Filtros activos</span>
                        <span class="inline-flex items-center justify-center rounded-full bg-blue-100 px-1.5 text-[10px] font-bold text-blue-600">
                            ${filters.length}
                        </span>
                    </div>
                    <div id="activeFiltersList" class="flex flex-wrap gap-1.5">
                        ${renderActiveFilters()}
                    </div>
                </div>

                <div class="space-y-2 pt-2 border-t border-gray-100">
                    <span class="text-xs font-semibold tracking-wide uppercase text-gray-500">Buscar en columna</span>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <select id="filtro-columna"
                                class="flex-1 rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-700
                                       focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                onchange="handleColumnaChange(this.value)">
                            <option value="">Columna...</option>
                            ${columnsData.map(c => `<option value="${c.field}">${c.label}</option>`).join('')}
                        </select>
                        <div id="filtro-valor-container" class="flex-[2]">
                            <input type="text" id="filtro-valor" placeholder="Valor a buscar..."
                                   class="w-full rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-700
                                          focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                   onkeypress="if(event.key==='Enter')addCustomFilter()">
                        </div>
                        <button type="button" data-action="add"
                                class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span class="hidden sm:inline">Agregar</span>
                        </button>
                    </div>
                </div>
            </section>

            <footer class="flex items-center justify-between gap-3 mt-2 px-6 py-4 bg-gray-50">

                <div class="flex gap-2 w-full">
                    <button type="button" data-action="close"
                            class="justify-center p-2 inline-flex items-center w-full rounded-xl bg-gray-200 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-300 transition-colors">
                        Cerrar
                    </button>
                    <button type="button" data-action="apply"
                            class="justify-center p-2 inline-flex items-center w-full gap-2 rounded-xl bg-blue-600 px-4 py-2 text-xs font-medium text-white hover:bg-blue-700 transition-colors">
                        <i class="fa-solid fa-check"></i>
                        <span>Aplicar</span>
                    </button>
                </div>
            </footer>
        </div>
    `;

    Swal.fire({
        html,
        width: '640px',
        padding: 0,
        showConfirmButton: false,
        showCloseButton: false,
        customClass: {
            popup: 'rounded-xl overflow-hidden p-0 shadow-xl',
            htmlContainer: 'p-0 m-0'
        },
        backdrop: 'rgba(0,0,0,0.4)',
        didOpen: (modalEl) => {
            modalEl.querySelectorAll('[data-quick-filter]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const key = btn.dataset.quickFilter;
                    toggleQuickFilter(key);
                    updateQuickFilterButtonInModal(key);
                });
            });

            modalEl.querySelector('[data-action="add"]')?.addEventListener('click', (e) => {
                e.preventDefault();
                addCustomFilter();
            });

            modalEl.querySelector('[data-action="apply"]')?.addEventListener('click', (e) => {
                e.preventDefault();
                applyAndCloseProgramaTejidoFilterModal();
            });

            modalEl.querySelector('[data-action="close"]')?.addEventListener('click', (e) => {
                e.preventDefault();
                closeProgramaTejidoFilterModal();
            });

            modalEl.querySelector('[data-action="reset"]')?.addEventListener('click', (e) => {
                e.preventDefault();
                resetAllFilters();
            });

            setTimeout(() => modalEl.querySelector('#filtro-columna')?.focus(), 50);
        }
    });
}

function closeProgramaTejidoFilterModal() {
    if (typeof Swal !== 'undefined') {
        Swal.close();
    }
}

// ===== Filtros personalizados =====
function renderActiveFilters() {
    return filters
        .map((f, i) => {
            const colLabel = columnsData.find(c => c.field === f.column)?.label || f.column;

            return `
                <div class="inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1 bg-blue-50 rounded-full text-xs text-blue-800">
                    <span class="font-medium">${colLabel}:</span>
                    <span class="text-blue-600">${f.value}</span>
                    <button onclick="removeFilter(${i})"
                            class="ml-1 flex h-5 w-5 items-center justify-center rounded-full hover:bg-blue-100 text-blue-500 transition-colors">
                        <i class="fa-solid fa-xmark text-[10px]"></i>
                    </button>
                </div>
            `;
        })
        .join('');
}

// Cambiar input/select según columna seleccionada
function handleColumnaChange(columnValue) {
    const container = document.getElementById('filtro-valor-container');
    if (!container) return;

    if (columnValue === 'Salon') {
        container.innerHTML = `
            <select id="filtro-valor"
                    class="w-full rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-700
                           focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                <option value="">Seleccionar salón...</option>
                <option value="JACQUARD">JACQUARD</option>
                <option value="SMIT">SMIT</option>
            </select>
        `;
    } else {
        container.innerHTML = `
            <input type="text" id="filtro-valor" placeholder="Valor a buscar..."
                   class="w-full rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-700
                          focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                   onkeypress="if(event.key==='Enter')addCustomFilter()">
        `;
    }
}

function addCustomFilter() {
    const colSelect = document.getElementById('filtro-columna');
    const valEl = document.getElementById('filtro-valor');

    const column = colSelect?.value;
    const operator = 'contains'; // Siempre usar "contiene" - simplificado
    const value = valEl?.value?.trim() || '';

    if (!column) {
        showToast('Selecciona una columna', 'warning');
        colSelect?.focus();
        return;
    }

    if (!value) {
        showToast('Ingresa un valor a buscar', 'warning');
        valEl?.focus();
        return;
    }

    if (filters.some(f => f.column === column && f.value === value)) {
        showToast('Este filtro ya existe', 'warning');
        return;
    }

    filters.push({ column, operator, value });
    lastFilterState = null;

    const section = document.getElementById('activeFiltersSection');
    const list = document.getElementById('activeFiltersList');
    if (section && list) {
        section.classList.remove('hidden');
        list.innerHTML = renderActiveFilters();
        const counter = section.querySelector('span.bg-blue-50, span.bg-blue-100');
        if (counter) counter.textContent = filters.length;
    }

    // Limpiar valor
    if (valEl.tagName === 'SELECT') {
        valEl.selectedIndex = 0;
    } else {
        valEl.value = '';
    }
    valEl?.focus();

    showToast('Filtro agregado', 'success');
}

function removeFilter(index) {
    filters.splice(index, 1);
    lastFilterState = null;

    const section = document.getElementById('activeFiltersSection');
    const list = document.getElementById('activeFiltersList');
    if (section && list) {
        if (filters.length === 0) {
            section.classList.add('hidden');
        } else {
            list.innerHTML = renderActiveFilters();
            const counter = section.querySelector('span.bg-blue-50, span.bg-blue-100');
            if (counter) counter.textContent = filters.length;
        }
    }

    applyProgramaTejidoFilters();
    showToast('Filtro eliminado', 'info');
}

function applyAndCloseProgramaTejidoFilterModal() {
    applyProgramaTejidoFilters();
    closeProgramaTejidoFilterModal();
}

// ===== Reset de filtros (Programa Tejido) =====
function resetAllFilters() {
    filters = [];
    quickFilters = { ultimos: false, divididos: false, enProceso: false };
    lastFilterState = null;

    // Restaurar todas las filas
    const tb = tbodyEl();
    tb.innerHTML = '';

    const fragment = document.createDocumentFragment();
    for (let i = 0; i < allRows.length; i++) {
        const r = allRows[i];
        r.classList.remove('bg-blue-500', 'text-white', 'hover:bg-blue-50');
        r.classList.add('hover:bg-blue-50');
        r.style.display = '';
        r.classList.remove('filter-hidden');

        const tds = r.querySelectorAll('td');
        for (let j = 0; j < tds.length; j++) {
            tds[j].classList.remove('text-white', 'text-gray-700');
        }

        if (dragDropMode) {
            const enProceso = isRowEnProceso(r);
            r.draggable = !enProceso;
            r.onclick = null;
            if (!enProceso) {
                r.classList.add('cursor-move');
                r.addEventListener('dragstart', handleDragStart);
                r.addEventListener('dragover', handleDragOver);
                r.addEventListener('drop', handleDrop);
                r.addEventListener('dragend', handleDragEnd);
            } else {
                r.classList.add('cursor-not-allowed');
                r.style.opacity = '0.6';
            }
        } else {
            r.onclick = () => selectRow(r, i);
        }
        fragment.appendChild(r);
    }
    tb.appendChild(fragment);

    allRows = Array.from(tb.querySelectorAll('.selectable-row'));
    clearRowCache();
    if (inlineEditMode) applyInlineModeToRows();

    // Columnas ocultas
    hiddenColumns.forEach(idx => {
        $$(`.column-${idx}`).forEach(el => el.style.display = '');
    });
    hiddenColumns = [];

    // Columnas fijadas
    pinnedColumns = [];
    updatePinnedColumnsPositions();

    updateFilterUI();
    selectedRowIndex = -1;

    // Botones de acción
    ['btn-editar-programa', 'btn-eliminar-programa', 'btn-ver-lineas', 'layoutBtnVerLineas'].forEach(id => {
        const btn = document.getElementById(id);
        if (btn) btn.disabled = true;
    });

    closeProgramaTejidoFilterModal();
    showToast('Filtros restablecidos', 'success');
}

// ===== Badge de filtros en navbar =====
function updateFilterUI() {
    const badge = document.getElementById('filterCount');
    if (!badge) return;

    const totalFilters = filters.length + Object.values(quickFilters).filter(v => v).length;

    if (totalFilters > 0) {
        badge.textContent = totalFilters;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

function updateFilterCount() {
    updateFilterUI();
}

// ===== Exponer funciones globalmente =====
window.openProgramaTejidoFilterModal = openProgramaTejidoFilterModal;
window.closeProgramaTejidoFilterModal = closeProgramaTejidoFilterModal;
window.toggleQuickFilter = toggleQuickFilter;
window.addCustomFilter = addCustomFilter;
window.removeFilter = removeFilter;
window.resetAllFilters = resetAllFilters;
window.resetFilters = resetAllFilters;
window.applyProgramaTejidoFilters = applyProgramaTejidoFilters;
window.applyAndCloseProgramaTejidoFilterModal = applyAndCloseProgramaTejidoFilterModal;
window.handleColumnaChange = handleColumnaChange;
