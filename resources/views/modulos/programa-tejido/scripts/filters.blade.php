// ===== Sistema de Filtros Avanzado – Programa Tejido =====

// Estado de filtros
// NOTE: `filters` se declara globalmente en state.blade.php
let quickFilters = {
    ultimos: false,
    divididos: false,
    enProceso: false,
    salonJacquard: false,
    salonSmit: false,
    conCambioHilo: false,
};

let dateRangeFilters = {
    fechaInicio: { desde: null, hasta: null },
    fechaFinal: { desde: null, hasta: null },
};

let lastFilterState = null;
let debounceTimer = null;

// Columnas excluidas del selector (se manejan como quickfilters o fechas)
const excludedColumns = ['Estado', 'Salon', 'SalonTejidoId', 'CambioHilo', 'FechaInicio', 'FechaFinal'];

// ===== Filtros Rápidos =====
const quickFilterConfig = {
    ultimos: {
        label: 'Últimos',
        icon: 'fa-flag-checkered',
        description: 'Últimos registros de cada telar',
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
    salonJacquard: {
        label: 'JACQUARD',
        icon: 'fa-industry',
        description: 'Solo salón Jacquard',
        check: (row) => {
            const cell = row.querySelector('[data-column="Salon"]') || row.querySelector('[data-column="SalonTejidoId"]');
            if (!cell) return false;
            const val = (cell.dataset.value || cell.textContent || '').toString().trim().toUpperCase();
            return val.includes('JACQUARD');
        },
    },
    salonSmit: {
        label: 'SMIT',
        icon: 'fa-industry',
        description: 'Solo salón Smit',
        check: (row) => {
            const cell = row.querySelector('[data-column="Salon"]') || row.querySelector('[data-column="SalonTejidoId"]');
            if (!cell) return false;
            const val = (cell.dataset.value || cell.textContent || '').toString().trim().toUpperCase();
            return val.includes('SMIT');
        },
    },
    conCambioHilo: {
        label: 'Cambio de hilo',
        icon: 'fa-exchange-alt',
        description: 'Con cambio de hilo',
        check: (row) => {
            const cell = row.querySelector('[data-column="CambioHilo"]');
            if (!cell) return false;
            const val = (cell.dataset.value || cell.textContent || '').toString().trim();
            return val === '1' || val === 'true' || val === 'Sí';
        },
    },
};

// ===== Aplicar filtros (quick + personalizados + fechas) =====
function applyProgramaTejidoFilters() {
    const tb = tbodyEl();
    if (!tb) return;

    const currentState = JSON.stringify({ filters, quickFilters, dateRangeFilters });
    if (currentState === lastFilterState) {
        return;
    }

    const rows = Array.from(tb.querySelectorAll('.selectable-row'));
    const hasQuickFilters = Object.values(quickFilters).some(Boolean);
    const hasCustomFilters = filters.length > 0;
    const hasDateFilters = Object.values(dateRangeFilters).some(d => d.desde || d.hasta);

    // Filtros rápidos activos (excepto salones que son mutuamente excluyentes)
    const activeQuickChecks = hasQuickFilters
        ? Object.entries(quickFilters)
            .filter(([, active]) => active)
            .map(([key]) => quickFilterConfig[key].check)
        : [];

    // Agrupar filtros por columna para permitir múltiples valores en la misma columna
    // Lógica: OR entre valores de la misma columna, AND entre diferentes columnas
    const filtersByColumn = {};
    if (hasCustomFilters) {
        filters.forEach(f => {
            const col = f.column;
            if (!filtersByColumn[col]) {
                filtersByColumn[col] = [];
            }
            filtersByColumn[col].push({
                value: String(f.value || '').toLowerCase(),
                operator: f.operator || 'contains',
            });
        });
    }

    // Función para verificar un valor contra un filtro
    const checkFilterMatch = (cellValue, filter) => {
        switch (filter.operator) {
            case 'equals':   return cellValue === filter.value;
            case 'starts':   return cellValue.startsWith(filter.value);
            case 'ends':     return cellValue.endsWith(filter.value);
            case 'not':      return !cellValue.includes(filter.value);
            case 'empty':    return cellValue === '';
            case 'notEmpty': return cellValue !== '';
            default:         return cellValue.includes(filter.value);
        }
    };

    let visibleRows = 0;

    rows.forEach(row => {
        // AND: debe cumplir TODOS los filtros rápidos seleccionados
        const matchesQuick = !hasQuickFilters || activeQuickChecks.every(check => check(row));

        // Verificar filtros personalizados con lógica OR por columna, AND entre columnas
        let matchesCustom = true;
        if (hasCustomFilters) {
            // Para cada columna con filtros, al menos uno debe coincidir (OR)
            // Todas las columnas deben tener al menos una coincidencia (AND entre columnas)
            matchesCustom = Object.entries(filtersByColumn).every(([column, columnFilters]) => {
                const cell = row.querySelector(`[data-column="${column}"]`);
                if (!cell) return false;
                const cellValue = (cell.dataset.value || cell.textContent || '').toLowerCase().trim();

                // OR: al menos un filtro de esta columna debe coincidir
                return columnFilters.some(filter => checkFilterMatch(cellValue, filter));
            });
        }

        const matchesDates = !hasDateFilters || checkDateFilters(row);

        const shouldShow = matchesQuick && matchesCustom && matchesDates;
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
    // clearRowCache puede no estar disponible en este scope, verificar antes de llamar
    if (typeof clearRowCache === 'function') {
        clearRowCache();
    } else if (typeof window.clearRowCache === 'function') {
        window.clearRowCache();
    } else if (window.PT && typeof window.PT.clearRowCache === 'function') {
        window.PT.clearRowCache();
    }
    if (inlineEditMode) applyInlineModeToRows();

    lastFilterState = currentState;
    updateFilterUI();
    
    // Actualizar totales después de aplicar filtros (con delay para asegurar que los estilos se aplicaron)
    // Usar requestAnimationFrame para asegurar que el DOM se actualizó
    requestAnimationFrame(() => {
        setTimeout(() => {
            if (typeof window.updateTotales === 'function') {
                window.updateTotales();
            } else {
                console.warn('updateTotales no está disponible');
            }
        }, 50);
    });

    const totalFilters = filters.length + Object.values(quickFilters).filter(Boolean).length +
                        (hasDateFilters ? 1 : 0);
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

// ===== Verificar filtros de fecha =====
function checkDateFilters(row) {
    for (const [field, range] of Object.entries(dateRangeFilters)) {
        if (!range.desde && !range.hasta) continue;

        const columnName = field === 'fechaInicio' ? 'FechaInicio' : 'FechaFinal';
        const cell = row.querySelector(`[data-column="${columnName}"]`);
        if (!cell) return false;

        const cellValue = (cell.dataset.value || cell.textContent || '').trim();
        if (!cellValue) return false;

        const cellDate = parseDate(cellValue);
        if (!cellDate) return false;

        if (range.desde) {
            const desdeDate = new Date(range.desde);
            desdeDate.setHours(0, 0, 0, 0);
            if (cellDate < desdeDate) return false;
        }

        if (range.hasta) {
            const hastaDate = new Date(range.hasta);
            hastaDate.setHours(23, 59, 59, 999);
            if (cellDate > hastaDate) return false;
        }
    }
    return true;
}

function parseDate(str) {
    if (!str) return null;

    // Intentar varios formatos
    // Formato dd/mm/yyyy
    let match = str.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
    if (match) {
        return new Date(match[3], match[2] - 1, match[1]);
    }

    // Formato yyyy-mm-dd
    match = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (match) {
        return new Date(match[1], match[2] - 1, match[3]);
    }

    // Intentar parse directo
    const d = new Date(str);
    return isNaN(d.getTime()) ? null : d;
}

// ===== Quick filters: toggle + UI =====
function toggleQuickFilter(filterKey) {
    // Si es un filtro de salón, desactivar el otro
    if (filterKey === 'salonJacquard' && !quickFilters.salonJacquard) {
        quickFilters.salonSmit = false;
    } else if (filterKey === 'salonSmit' && !quickFilters.salonSmit) {
        quickFilters.salonJacquard = false;
    }

    quickFilters[filterKey] = !quickFilters[filterKey];
    lastFilterState = null;
    applyProgramaTejidoFilters();

    // Actualizar UI de todos los botones de salón
    ['salonJacquard', 'salonSmit'].forEach(key => {
        const btn = document.querySelector(`[data-quick-filter="${key}"]`);
        if (btn) updateQuickFilterButton(btn, key);
    });

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
                    class="relative flex items-center gap-2 px-3 py-2 rounded-lg text-xs
                           ${isActive ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}
                           transition-all">
                    <i class="fa-solid ${config.icon} ${isActive ? 'text-white' : 'text-gray-400'}"></i>
                    <div class="text-left">
                        <div class="font-medium text-xs">${config.label}</div>
                    </div>
                    ${isActive ? `<i class="fa-solid fa-check text-[10px] ml-auto"></i>` : ''}
                </button>
            `;
        })
        .join('');
}

function updateQuickFilterButton(btn, filterKey) {
    const config = quickFilterConfig[filterKey];
    const isActive = quickFilters[filterKey];

    btn.className =
        `relative flex items-center gap-2 px-3 py-2 rounded-lg text-xs transition-all ` +
        (isActive ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200');

    const icon = btn.querySelector('i:first-child');
    if (icon) {
        icon.className = `fa-solid ${config.icon} ${isActive ? 'text-white' : 'text-gray-400'}`;
    }

    // Actualizar o agregar check
    const existingCheck = btn.querySelector('.fa-check');
    if (isActive && !existingCheck) {
        btn.insertAdjacentHTML('beforeend', `<i class="fa-solid fa-check text-[10px] ml-auto"></i>`);
    } else if (!isActive && existingCheck) {
        existingCheck.remove();
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

    // Filtrar columnas excluidas
    const filteredColumns = columnsData.filter(c => !excludedColumns.includes(c.field));

    const html = `
        <div class="w-full max-h-[80vh] overflow-hidden flex flex-col">
            <section class="flex-1 overflow-y-auto bg-white px-5 py-4 space-y-4">
                <!-- Filtros rápidos -->
                <div class="space-y-2">
                    <span class="text-[11px] font-semibold uppercase text-gray-400">Filtros rápidos</span>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        ${renderQuickFilterButtons()}
                    </div>
                </div>

                <!-- Filtros de fecha -->
                <div class="space-y-2 pt-3 border-t border-gray-100">
                    <span class="text-[11px] font-semibold uppercase text-gray-400">Rango de fechas</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="text-[11px] text-gray-500">Fecha Inicio</label>
                            <div class="flex gap-2">
                                <input type="date" id="fecha-inicio-desde"
                                       value="${dateRangeFilters.fechaInicio.desde || ''}"
                                       class="flex-1 rounded-lg bg-gray-100 px-2 py-1.5 text-xs text-gray-700
                                              focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                       placeholder="Desde">
                                <input type="date" id="fecha-inicio-hasta"
                                       value="${dateRangeFilters.fechaInicio.hasta || ''}"
                                       class="flex-1 rounded-lg bg-gray-100 px-2 py-1.5 text-xs text-gray-700
                                              focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                       placeholder="Hasta">
                            </div>
                        </div>
                <div class="space-y-1">
                            <label class="text-[11px] text-gray-500">Fecha Final</label>
                            <div class="flex gap-2">
                                <input type="date" id="fecha-final-desde"
                                       value="${dateRangeFilters.fechaFinal.desde || ''}"
                                       class="flex-1 rounded-lg bg-gray-100 px-2 py-1.5 text-xs text-gray-700
                                              focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                       placeholder="Desde">
                                <input type="date" id="fecha-final-hasta"
                                       value="${dateRangeFilters.fechaFinal.hasta || ''}"
                                       class="flex-1 rounded-lg bg-gray-100 px-2 py-1.5 text-xs text-gray-700
                                              focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                       placeholder="Hasta">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros activos -->
                <div id="activeFiltersSection" class="${filters.length === 0 ? 'hidden' : ''} space-y-2 pt-3 border-t border-gray-100">
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-semibold uppercase text-gray-400">Filtros activos</span>
                        <span class="inline-flex items-center justify-center rounded-full bg-blue-100 px-1.5 text-[10px] font-bold text-blue-600">
                            ${filters.length}
                        </span>
                    </div>
                    <div id="activeFiltersList" class="flex flex-wrap gap-1.5">
                        ${renderActiveFilters()}
                        </div>
                </div>

                <!-- Buscar en columna -->
                <div class="space-y-2 pt-3 border-t border-gray-100">
                    <span class="text-[11px] font-semibold uppercase text-gray-400">Buscar en columna</span>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <select id="filtro-columna"
                                class="flex-1 rounded-lg bg-gray-100 px-3 py-2 text-xs text-gray-700
                                       focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                            <option value="">Columna...</option>
                            ${filteredColumns.map(c => `<option value="${c.field}">${c.label}</option>`).join('')}
                    </select>
                        <div id="filtro-valor-container" class="flex-[2]">
                            <input type="text" id="filtro-valor" placeholder="Valor a buscar..."
                                   class="w-full rounded-lg bg-gray-100 px-3 py-2 text-xs text-gray-700
                                          focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                   onkeypress="if(event.key==='Enter')addCustomFilter()">
                </div>
                        <button type="button" data-action="add"
                                class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700 transition-colors">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                    </button>
                </div>
                </div>
            </section>


            </div>
	`;

	Swal.fire({
        html,
        width: '580px',
        padding: 0,
        showConfirmButton: false,
        showCloseButton: true,
        customClass: {
            popup: 'rounded-xl overflow-hidden p-0 shadow-xl',
            htmlContainer: 'p-0 m-0'
        },
        backdrop: 'rgba(0,0,0,0.4)',
        didOpen: (modalEl) => {
            // Quick filters - ya aplican automáticamente
            modalEl.querySelectorAll('[data-quick-filter]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const key = btn.dataset.quickFilter;
                    toggleQuickFilter(key);
                    // Actualizar todos los botones (para el caso de salones mutuamente excluyentes)
                    modalEl.querySelectorAll('[data-quick-filter]').forEach(b => {
                        const k = b.dataset.quickFilter;
                        updateQuickFilterButton(b, k);
                    });
                });
            });

            // Agregar filtro - aplica automáticamente
            modalEl.querySelector('[data-action="add"]')?.addEventListener('click', (e) => {
                e.preventDefault();
                addCustomFilter();
            });

            // Cerrar modal
            modalEl.querySelector('[data-action="close"]')?.addEventListener('click', (e) => {
                e.preventDefault();
                closeProgramaTejidoFilterModal();
            });

            // Limpiar todo
            modalEl.querySelector('[data-action="reset"]')?.addEventListener('click', (e) => {
                e.preventDefault();
                resetAllFiltersInModal(modalEl);
            });

            // Filtros de fecha - aplicar automáticamente con debounce
            const dateInputs = modalEl.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                input.addEventListener('change', () => {
                    saveDateRangeFilters();
                    // Debounce para evitar muchas llamadas seguidas
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        lastFilterState = null;
                        applyProgramaTejidoFilters();
                    }, 300);
                });
            });

            setTimeout(() => modalEl.querySelector('#filtro-columna')?.focus(), 50);
		}
	});
}

function saveDateRangeFilters() {
    dateRangeFilters.fechaInicio.desde = document.getElementById('fecha-inicio-desde')?.value || null;
    dateRangeFilters.fechaInicio.hasta = document.getElementById('fecha-inicio-hasta')?.value || null;
    dateRangeFilters.fechaFinal.desde = document.getElementById('fecha-final-desde')?.value || null;
    dateRangeFilters.fechaFinal.hasta = document.getElementById('fecha-final-hasta')?.value || null;
    lastFilterState = null;
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
                <div class="inline-flex items-center gap-1.5 pl-2 pr-1 py-0.5 bg-blue-50 rounded-full text-[11px] text-blue-800">
                    <span class="font-medium">${colLabel}:</span>
                    <span class="text-blue-600">${f.value}</span>
                    <button onclick="removeFilter(${i})"
                            class="flex h-4 w-4 items-center justify-center rounded-full hover:bg-blue-100 text-blue-500 transition-colors">
                        <i class="fa-solid fa-xmark text-[9px]"></i>
                    </button>
                </div>
            `;
        })
        .join('');
}

function addCustomFilter() {
    const colSelect = document.getElementById('filtro-columna');
    const valEl = document.getElementById('filtro-valor');

    const column = colSelect?.value;
    const operator = 'contains';
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
        const counter = section.querySelector('span.bg-blue-100');
        if (counter) counter.textContent = filters.length;
    }

    if (valEl.tagName === 'SELECT') {
        valEl.selectedIndex = 0;
	} else {
        valEl.value = '';
    }
    colSelect.value = '';
    valEl?.focus();

    // Aplicar filtros automáticamente
    applyProgramaTejidoFilters();
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
            const counter = section.querySelector('span.bg-blue-100');
            if (counter) counter.textContent = filters.length;
        }
    }

    applyProgramaTejidoFilters();
}

// Limpiar filtros sin cerrar el modal
function resetAllFiltersInModal(modalEl) {
    // Limpiar arrays
    filters = [];
    quickFilters = {
        ultimos: false,
        divididos: false,
        enProceso: false,
        salonJacquard: false,
        salonSmit: false,
        conCambioHilo: false,
    };
    dateRangeFilters = {
        fechaInicio: { desde: null, hasta: null },
        fechaFinal: { desde: null, hasta: null },
    };
    lastFilterState = null;

    // Actualizar UI de quick filters
    if (modalEl) {
        modalEl.querySelectorAll('[data-quick-filter]').forEach(btn => {
            const key = btn.dataset.quickFilter;
            updateQuickFilterButton(btn, key);
        });

        // Limpiar inputs de fecha
        modalEl.querySelectorAll('input[type="date"]').forEach(input => {
            input.value = '';
        });

        // Limpiar inputs de texto
        const colSelect = modalEl.querySelector('#filtro-columna');
        const valInput = modalEl.querySelector('#filtro-valor');
        if (colSelect) colSelect.value = '';
        if (valInput) valInput.value = '';
    }

    // Ocultar sección de filtros activos
    const section = document.getElementById('activeFiltersSection');
    if (section) section.classList.add('hidden');

    // Aplicar (mostrar todas las filas)
    applyProgramaTejidoFilters();
    showToast('Filtros limpiados', 'info');
}

function applyAndCloseProgramaTejidoFilterModal() {
    applyProgramaTejidoFilters();
    closeProgramaTejidoFilterModal();
}

// ===== Reset de filtros (Programa Tejido) =====
function resetAllFilters() {
    // Limpiar arrays
    filters = [];
    quickFilters = {
        ultimos: false,
        divididos: false,
        enProceso: false,
        salonJacquard: false,
        salonSmit: false,
        conCambioHilo: false,
    };
    dateRangeFilters = {
        fechaInicio: { desde: null, hasta: null },
        fechaFinal: { desde: null, hasta: null },
    };
    lastFilterState = null;

    // Mostrar todas las filas
    const tb = tbodyEl();
    if (tb) {
        const rows = tb.querySelectorAll('.selectable-row');
        rows.forEach((row, i) => {
            row.style.display = '';
            row.classList.remove('filter-hidden');
        });
    }

    // Actualizar UI
    updateFilterUI();

    // Cerrar modal si está abierto
    closeProgramaTejidoFilterModal();
    showToast('Filtros restablecidos', 'success');
}

// ===== Badge de filtros en navbar =====
function updateFilterUI() {
    const badge = document.getElementById('filterCount');
    if (!badge) return;

    const hasDateFilters = Object.values(dateRangeFilters).some(d => d.desde || d.hasta);
    const totalFilters = filters.length +
                        Object.values(quickFilters).filter(v => v).length +
                        (hasDateFilters ? 1 : 0);

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
window.resetAllFiltersInModal = resetAllFiltersInModal;
window.resetFilters = resetAllFilters;
window.applyProgramaTejidoFilters = applyProgramaTejidoFilters;
window.applyAndCloseProgramaTejidoFilterModal = applyAndCloseProgramaTejidoFilterModal;
window.saveDateRangeFilters = saveDateRangeFilters;
