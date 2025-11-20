/**
 * Sistema de filtros para tablas - Versión simplificada
 */

// Estado global de filtros
let activeFilters = {};
let allTableRows = [];

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    initializeFilters();
});

function initializeFilters() {
    // Guardar todas las filas de la tabla
    const tbody = document.querySelector('#mainTable tbody');
    if (tbody) {
        allTableRows = Array.from(tbody.querySelectorAll('tr.selectable-row'));
        console.log(`🔍 Sistema de filtros iniciado - ${allTableRows.length} filas disponibles`);
    }

    // Conectar botón de filtros del navbar
    const btnFilters = document.getElementById('btnFilters');
    if (btnFilters) {
        btnFilters.addEventListener('click', openFilterModal);
    }

    // Conectar botón de agregar filtro
    const btnAdd = document.getElementById('f_add_btn');
    if (btnAdd) {
        btnAdd.addEventListener('click', addFilter);
    }

    // Conectar botón de aplicar (no usar onclick, usar addEventListener)
    const btnApply = document.querySelector('button[onclick="confirmFilters()"]');
    if (btnApply) {
        btnApply.removeAttribute('onclick');
        btnApply.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🔘 Botón Aplicar clickeado');
            applyFilters();
        });
        console.log('✅ Botón Aplicar conectado');
    }

    // Conectar botón de cancelar
    const btnCancel = document.querySelectorAll('[onclick="closeFilterModal()"]');
    btnCancel.forEach(btn => {
        btn.onclick = closeFilterModal;
    });

    // Conectar botón de restablecer
    const btnReset = document.getElementById('btnResetFilters');
    if (btnReset) {
        btnReset.onclick = resetFilters;
    }

    // Permitir agregar filtro con Enter
    const inputValue = document.getElementById('f_col_value');
    if (inputValue) {
        inputValue.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                addFilter();
            }
        });
    }
}

function openFilterModal() {
    const modal = document.getElementById('filtersModal');
    if (!modal) {
        console.error('No se encontró el modal de filtros');
        return;
    }

    // Llenar el select de columnas
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

    // Mostrar chips de filtros activos
    renderFilterChips();

    // Limpiar input y conectar Enter
    const input = document.getElementById('f_col_value');
    if (input) {
        input.value = '';

        // Reconectar evento Enter
        const newInput = input.cloneNode(true);
        input.parentNode.replaceChild(newInput, input);

        newInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                console.log('⏎ Enter presionado en input');
                addFilter();
            }
        });
    }

    // ⭐ IMPORTANTE: Reconectar botón de agregar cada vez que se abre el modal
    const btnAdd = document.getElementById('f_add_btn');
    if (btnAdd) {
        // Eliminar cualquier listener previo clonando el botón
        const newBtnAdd = btnAdd.cloneNode(true);
        btnAdd.parentNode.replaceChild(newBtnAdd, btnAdd);

        // Agregar nuevo listener
        newBtnAdd.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🔘 Botón Agregar clickeado');
            addFilter();
        });

        console.log('✅ Botón Agregar reconectado');
    } else {
        console.error('❌ No se encontró botón f_add_btn');
    }

    // Reconectar botón Cancelar
    const btnsCancelar = modal.querySelectorAll('button[onclick="closeFilterModal()"]');
    btnsCancelar.forEach(btn => {
        btn.removeAttribute('onclick');
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        newBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🔘 Botón Cancelar clickeado');
            closeFilterModal();
        });
    });

    // Reconectar botón Aplicar
    const btnsAplicar = modal.querySelectorAll('button[onclick="confirmFilters()"]');
    btnsAplicar.forEach(btn => {
        btn.removeAttribute('onclick');
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        newBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🔘 Botón Aplicar clickeado');
            applyFilters();
        });
    });

    // Reconectar botón Restablecer
    const btnRestablecer = modal.querySelector('#btnResetFilters');
    if (btnRestablecer) {
        btnRestablecer.removeAttribute('onclick');
        const newBtn = btnRestablecer.cloneNode(true);
        btnRestablecer.parentNode.replaceChild(newBtn, btnRestablecer);
        newBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🔘 Botón Restablecer clickeado');
            resetFilters();
        });
    }

    // Mostrar modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    console.log('✅ Modal de filtros abierto - Todos los botones reconectados');
}

function closeFilterModal() {
    const modal = document.getElementById('filtersModal');
    if (modal) {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }
}

function addFilter() {
    const select = document.getElementById('f_col_select');
    const input = document.getElementById('f_col_value');

    if (!select || !input) {
        console.error('❌ No se encontró select o input');
        return;
    }

    const column = select.value;
    const value = input.value.trim();

    console.log(`🔍 addFilter llamado:`, { column, value });

    if (!column) {
        // Resaltar el select brevemente
        select.classList.add('border-red-500', 'ring-2', 'ring-red-300');
        setTimeout(() => {
            select.classList.remove('border-red-500', 'ring-2', 'ring-red-300');
        }, 1500);
        console.warn('⚠️ No se seleccionó columna');
        return;
    }

    if (!value) {
        // Resaltar el input brevemente
        input.classList.add('border-red-500', 'ring-2', 'ring-red-300');
        input.placeholder = '¡Escribe un valor!';
        setTimeout(() => {
            input.classList.remove('border-red-500', 'ring-2', 'ring-red-300');
            input.placeholder = 'Escribe el valor a filtrar';
        }, 1500);
        console.warn('⚠️ No se escribió valor');
        return;
    }

    // Agregar o actualizar filtro
    activeFilters[column] = value;
    console.log(`✓ Filtro agregado: ${getColumnLabel(column)} = "${value}"`);
    console.log(`📊 Estado de activeFilters:`, activeFilters);

    // Renderizar chips
    renderFilterChips();

    // Limpiar campos
    input.value = '';
    select.selectedIndex = 0;

    // Focus en el select para agregar otro filtro rápidamente
    select.focus();
}

function renderFilterChips() {
    const container = document.getElementById('f_list');
    if (!container) {
        console.error('No se encontró contenedor f_list');
        return;
    }

    container.innerHTML = '';

    const filterKeys = Object.keys(activeFilters);
    console.log(`renderFilterChips: ${filterKeys.length} filtros a renderizar`, activeFilters);

    filterKeys.forEach(column => {
        const value = activeFilters[column];
        const label = getColumnLabel(column);

        const chip = document.createElement('div');
        chip.className = 'inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-100 text-blue-800 text-sm border border-blue-300 f-chip';

        // ⭐ IMPORTANTE: Agregar data-col y data-val para que applyFilters pueda leerlos
        chip.setAttribute('data-col', column);
        chip.setAttribute('data-val', value);

        chip.innerHTML = `
            <span class="font-medium">${label}:</span>
            <span>${value}</span>
            <button type="button" class="ml-1 hover:bg-blue-200 rounded-full p-0.5" onclick="removeFilter('${column}')">
                <i class="fa-solid fa-times text-xs"></i>
            </button>
        `;
        container.appendChild(chip);

        console.log(`  ✓ Chip creado: ${label} = "${value}" (data-col="${column}")`);
    });

    // Verificar que los chips se hayan agregado
    const chipsCreados = container.querySelectorAll('.f-chip');
    console.log(`✅ Total de chips en DOM: ${chipsCreados.length}`);
}

function removeFilter(column) {
    const label = getColumnLabel(column);
    delete activeFilters[column];
    console.log(`✓ Filtro eliminado: ${label}`);
    renderFilterChips();
}

function getColumnLabel(column) {
    const th = document.querySelector(`#mainTable thead th[data-column="${column}"]`);
    return th ? th.textContent.trim() : column;
}

function applyFilters() {
    // ⭐ Si hay algo en el input y select, agregarlo automáticamente antes de aplicar
    const select = document.getElementById('f_col_select');
    const input = document.getElementById('f_col_value');

    if (select && input && select.value && input.value.trim()) {
        const column = select.value;
        const value = input.value.trim();
        console.log(`⚡ Auto-agregando filtro antes de aplicar: ${column} = "${value}"`);
        activeFilters[column] = value;
    }

    // Reconstruir activeFilters desde los chips en el DOM por si acaso
    const chips = document.querySelectorAll('#f_list .f-chip');
    console.log(`🔍 Debug: ${chips.length} chips encontrados en el DOM`);

    if (chips.length > 0) {
        activeFilters = {};
        chips.forEach(chip => {
            const col = chip.getAttribute('data-col');
            const val = chip.getAttribute('data-val');
            if (col && val) {
                activeFilters[col] = val;
                console.log(`  → Chip: ${col} = "${val}"`);
            }
        });
    }

    const filterCount = Object.keys(activeFilters).length;
    console.log(`🔍 activeFilters:`, activeFilters, `(${filterCount} filtros)`);

    if (filterCount > 0) {
        console.log('✓ Aplicando filtros:', activeFilters);
    } else {
        console.log('✓ Sin filtros - Mostrando todas las filas');
    }

    const tbody = document.querySelector('#mainTable tbody');
    if (!tbody) {
        console.error('✗ No se encontró tbody');
        return;
    }

    // Si no hay filas guardadas, guardarlas ahora
    if (allTableRows.length === 0) {
        allTableRows = Array.from(tbody.querySelectorAll('tr.selectable-row'));
        console.log(`✓ Filas guardadas: ${allTableRows.length}`);
    }

    // Filtrar filas
    const filterEntries = Object.entries(activeFilters);
    let filteredRows = allTableRows;

    if (filterEntries.length > 0) {
        filteredRows = allTableRows.filter(row => {
            return filterEntries.every(([column, value]) => {
                const cell = row.querySelector(`td[data-column="${column}"]`);
                if (!cell) {
                    console.warn(`⚠ No se encontró celda con columna "${column}"`);
                    return false;
                }

                const cellText = cell.textContent.toLowerCase().trim();
                const searchText = value.toLowerCase().trim();

                return cellText.includes(searchText);
            });
        });

        console.log(`✓ Filtrado: ${filteredRows.length} de ${allTableRows.length} filas`);
    } else {
        console.log(`✓ Mostrando todas las ${allTableRows.length} filas`);
    }

    // Vaciar tabla y agregar filas filtradas
    tbody.innerHTML = '';
    filteredRows.forEach((row, index) => {
        tbody.appendChild(row);

        // Restaurar event listener si existe selectRow
        if (typeof selectRow === 'function') {
            row.onclick = () => selectRow(row, index);
        }
    });

    // Actualizar badge de filtros
    updateFilterBadge();

    // Cerrar modal
    closeFilterModal();

    // Mostrar notificación de éxito
    if (typeof showToast === 'function') {
        if (filterCount > 0) {
            showToast(`${filterCount} filtro(s) aplicado(s)`, 'success');
        } else {
            showToast('Mostrando todas las filas', 'info');
        }
    }

    // Notificar a la vista si tiene función personalizada
    if (typeof window.onFiltersApplied === 'function') {
        window.onFiltersApplied(activeFilters);
    }
}

function resetFilters() {
    const previousCount = Object.keys(activeFilters).length;

    console.log(`🔄 Restableciendo filtros... (${previousCount} activos)`);

    // Animar icono
    const icon = document.getElementById('iconReset');
    if (icon) {
        icon.classList.add('fa-spin');
        setTimeout(() => icon.classList.remove('fa-spin'), 500);
    }

    // Limpiar filtros del objeto
    activeFilters = {};

    // Limpiar chips del DOM
    const container = document.getElementById('f_list');
    if (container) {
        container.innerHTML = '';
    }

    // Limpiar campos del formulario
    const select = document.getElementById('f_col_select');
    const input = document.getElementById('f_col_value');
    if (select) select.selectedIndex = 0;
    if (input) input.value = '';

    // Mostrar todas las filas en la tabla
    const tbody = document.querySelector('#mainTable tbody');
    if (tbody && allTableRows.length > 0) {
        tbody.innerHTML = '';
        allTableRows.forEach((row, index) => {
            tbody.appendChild(row);

            // Restaurar event listener si existe selectRow
            if (typeof selectRow === 'function') {
                row.onclick = () => selectRow(row, index);
            }
        });
        console.log(`✅ Mostrando todas las ${allTableRows.length} filas`);
    }

    // Actualizar badge (quitarlo)
    updateFilterBadge();

    if (previousCount > 0) {
        console.log(`✓ ${previousCount} filtro(s) restablecido(s)`);

        // Mostrar notificación
        if (typeof showToast === 'function') {
            showToast('Filtros restablecidos', 'info');
        }
    } else {
        console.log('⚠ No había filtros activos');
    }

    // NO cerrar el modal para que el usuario vea que se limpió
    console.log('✅ Filtros restablecidos - Modal permanece abierto');
}

function updateFilterBadge() {
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

// Función para restablecer filtros y recargar página
function resetFiltersAndReload() {
    console.log('🔄 Restableciendo filtros y recargando página...');

    // Animar icono
    const icon = document.getElementById('iconReset');
    if (icon) {
        icon.classList.add('fa-spin');
    }

    // Limpiar filtros
    activeFilters = {};

    // Recargar página después de 300ms (para que se vea la animación)
    setTimeout(() => {
        window.location.reload();
    }, 300);
}

// Exponer funciones globalmente para onclick
window.openFilterModal = openFilterModal;
window.closeFilterModal = closeFilterModal;
window.addFilter = addFilter;
window.removeFilter = removeFilter;
window.applyFilters = applyFilters;
window.confirmFilters = applyFilters; // Alias
window.resetFilters = resetFilters;
window.resetFiltersSpin = resetFilters; // Alias
window.resetFiltersAndReload = resetFiltersAndReload; // Nueva función para recargar

// Función para resetear columnas (si existe en la página)
window.resetColumnsSpin = function() {
    const icon = document.getElementById('iconResetColumns');
    if (icon) {
        icon.classList.add('fa-spin');
        setTimeout(() => icon.classList.remove('fa-spin'), 500);
    }
    if (typeof resetColumnVisibility === 'function') {
        resetColumnVisibility();
    }
};
