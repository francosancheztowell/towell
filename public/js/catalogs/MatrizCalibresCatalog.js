/**
 * MatrizCalibresCatalog - Catálogo de matriz de calibres
 */
class MatrizCalibresCatalog extends CatalogBase {
    constructor(config) {
        super({
            tableBodyId: 'matriz-calibres-body',
            route: 'catalogos/matrizcalibres',
            idField: 'Id',
            fields: [
                { name: 'Tipo', label: 'Tipo', type: 'text', maxlength: 60 },
                { name: 'Calibre', label: 'Calibre', type: 'number', step: '0.0001' },
                { name: 'FibraId', label: 'Fibra', type: 'text', maxlength: 60 },
                { name: 'Cuenta', label: 'Cuenta', type: 'text', maxlength: 60 },
                { name: 'ItemId', label: 'ItemId', type: 'text', maxlength: 60 },
                { name: 'ConfigId', label: 'Config', type: 'text', maxlength: 60 },
                { name: 'InventSizeId', label: 'Tamaño', type: 'text', maxlength: 60 },
                { name: 'InventColorId', label: 'Color', type: 'text', maxlength: 60 },
            ],
            enableFilters: true,
            enableExcel: false,
            createTitle: 'Nuevo calibre',
            editTitle: 'Editar calibre',
            modalWidth: '640px',
            ...config,
        });

        this.searchTerm = '';
        this.tipoFilter = '';
        this.setupLocalFilters();
    }

    setupLocalFilters() {
        const searchInput = document.getElementById('matriz-calibres-search');
        const tipoSelect = document.getElementById('matriz-calibres-tipo');
        const clearBtn = document.getElementById('matriz-calibres-clear');

        if (searchInput) {
            searchInput.addEventListener('input', (event) => {
                this.searchTerm = (event.target.value || '').trim().toLowerCase();
                this.applyLocalFilters();
            });
        }

        if (tipoSelect) {
            tipoSelect.addEventListener('change', (event) => {
                this.tipoFilter = event.target.value || '';
                this.applyLocalFilters();
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                this.searchTerm = '';
                this.tipoFilter = '';
                if (searchInput) searchInput.value = '';
                if (tipoSelect) tipoSelect.value = '';
                this.applyLocalFilters();
                this.deselectCurrent();
            });
        }
    }

    deselectCurrent() {
        if (this.state.selectedRow) {
            this.deselectRow(this.state.selectedRow);
        }
    }

    applyLocalFilters() {
        const filtered = this.state.originalData.filter((item) => {
            const tipo = (item.Tipo || '').toString();
            const matchesTipo = !this.tipoFilter || tipo === this.tipoFilter;

            if (!matchesTipo) return false;
            if (!this.searchTerm) return true;

            const haystack = [
                item.Id,
                item.Tipo,
                item.Calibre,
                item.FibraId,
                item.Cuenta,
                item.ItemId,
                item.ConfigId,
                item.InventSizeId,
                item.InventColorId,
            ]
                .map((value) => (value ?? '').toString().toLowerCase())
                .join(' ');

            return haystack.includes(this.searchTerm);
        });

        this.state.currentData = filtered;
        this.renderTable();
        this.updateCounter(filtered.length);
        this.disableButtons();
        this.state.selectedRow = null;
        this.state.selectedId = null;
        this.state.selectedKey = null;
    }

    updateCounter(count) {
        const counter = document.getElementById('matriz-calibres-count');
        const total = document.getElementById('matriz-calibres-total');
        if (counter) counter.textContent = String(count);
        if (total) total.textContent = String(this.state.originalData.length);
    }

    renderTable() {
        const tbody = document.getElementById(this.config.tableBodyId);
        const emptyState = document.getElementById('matriz-calibres-empty');
        const data = this.state.currentData || [];
        if (!tbody) return;

        if (!data.length) {
            tbody.innerHTML = '';
            if (emptyState) emptyState.classList.remove('hidden');
            return;
        }

        if (emptyState) emptyState.classList.add('hidden');

        tbody.innerHTML = data.map((item) => {
            const id = item.Id ?? item.id ?? '';
            const calibre = item.Calibre === null || item.Calibre === undefined || item.Calibre === ''
                ? ''
                : Number(item.Calibre).toFixed(4).replace(/\.?0+$/, '');

            return `
                <tr class="text-center hover:bg-sky-50 transition cursor-pointer border-b border-slate-100"
                    onclick="window.catalogManager?.selectRow(this, '${id}', '${id}')"
                    ondblclick="window.catalogManager?.deselectRow(this)"
                    data-id="${id}"
                    data-tipo="${this.escapeAttr(item.Tipo)}"
                    data-calibre="${this.escapeAttr(item.Calibre)}"
                    data-fibraid="${this.escapeAttr(item.FibraId)}"
                    data-cuenta="${this.escapeAttr(item.Cuenta)}"
                    data-itemid="${this.escapeAttr(item.ItemId)}"
                    data-configid="${this.escapeAttr(item.ConfigId)}"
                    data-inventsizeid="${this.escapeAttr(item.InventSizeId)}"
                    data-inventcolorid="${this.escapeAttr(item.InventColorId)}"
                >
                    <td class="py-2.5 px-3 text-slate-500 font-mono text-xs">${id}</td>
                    <td class="py-2.5 px-3">
                        ${item.Tipo
                            ? `<span class="inline-flex items-center rounded-full bg-sky-100 text-sky-800 px-2.5 py-0.5 text-xs font-semibold">${this.escapeHtml(item.Tipo)}</span>`
                            : '<span class="text-slate-300">—</span>'}
                    </td>
                    <td class="py-2.5 px-3 font-semibold tabular-nums text-slate-800">${calibre || '<span class="text-slate-300">—</span>'}</td>
                    <td class="py-2.5 px-3">${this.escapeHtml(item.FibraId) || '<span class="text-slate-300">—</span>'}</td>
                    <td class="py-2.5 px-3">${this.escapeHtml(item.Cuenta) || '<span class="text-slate-300">—</span>'}</td>
                    <td class="py-2.5 px-3 font-mono text-xs">${this.escapeHtml(item.ItemId) || '<span class="text-slate-300">—</span>'}</td>
                    <td class="py-2.5 px-3">${this.escapeHtml(item.ConfigId) || '<span class="text-slate-300">—</span>'}</td>
                    <td class="py-2.5 px-3">${this.escapeHtml(item.InventSizeId) || '<span class="text-slate-300">—</span>'}</td>
                    <td class="py-2.5 px-3">${this.escapeHtml(item.InventColorId) || '<span class="text-slate-300">—</span>'}</td>
                </tr>
            `;
        }).join('');
    }

    escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    escapeAttr(value) {
        return this.escapeHtml(value).replace(/\n/g, ' ');
    }

    getFormHTML(prefix, data = {}) {
        const val = (key) => this.escapeAttr(data[key] ?? '');

        return `
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-left text-sm">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Tipo</label>
                    <input id="${prefix}Tipo" type="text" maxlength="60" value="${val('Tipo')}"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500" placeholder="Ej: Rizo">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Calibre</label>
                    <input id="${prefix}Calibre" type="number" step="0.0001" value="${val('Calibre')}"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500" placeholder="0.0000">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Fibra</label>
                    <input id="${prefix}FibraId" type="text" maxlength="60" value="${val('FibraId')}"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500" placeholder="FibraId">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Cuenta</label>
                    <input id="${prefix}Cuenta" type="text" maxlength="60" value="${val('Cuenta')}"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500" placeholder="Cuenta">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">ItemId</label>
                    <input id="${prefix}ItemId" type="text" maxlength="60" value="${val('ItemId')}"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500" placeholder="ItemId">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Config</label>
                    <input id="${prefix}ConfigId" type="text" maxlength="60" value="${val('ConfigId')}"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500" placeholder="ConfigId">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Tamaño</label>
                    <input id="${prefix}InventSizeId" type="text" maxlength="60" value="${val('InventSizeId')}"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500" placeholder="InventSizeId">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Color</label>
                    <input id="${prefix}InventColorId" type="text" maxlength="60" value="${val('InventColorId')}"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500" placeholder="InventColorId">
                </div>
            </div>
        `;
    }

    getCreateFormHTML() {
        return this.getFormHTML('swal-');
    }

    getEditFormHTML(data) {
        return this.getFormHTML('swal-edit-', {
            Tipo: data.tipo,
            Calibre: data.calibre,
            FibraId: data.fibraid,
            Cuenta: data.cuenta,
            ItemId: data.itemid,
            ConfigId: data.configid,
            InventSizeId: data.inventsizeid,
            InventColorId: data.inventcolorid,
        });
    }

    extractFormData(action) {
        const prefix = action === 'create' ? 'swal-' : 'swal-edit-';
        return {
            Tipo: document.getElementById(`${prefix}Tipo`)?.value.trim() || '',
            Calibre: document.getElementById(`${prefix}Calibre`)?.value.trim() || '',
            FibraId: document.getElementById(`${prefix}FibraId`)?.value.trim() || '',
            Cuenta: document.getElementById(`${prefix}Cuenta`)?.value.trim() || '',
            ItemId: document.getElementById(`${prefix}ItemId`)?.value.trim() || '',
            ConfigId: document.getElementById(`${prefix}ConfigId`)?.value.trim() || '',
            InventSizeId: document.getElementById(`${prefix}InventSizeId`)?.value.trim() || '',
            InventColorId: document.getElementById(`${prefix}InventColorId`)?.value.trim() || '',
        };
    }

    validateCreateData(data) {
        const hasAny = Object.values(data).some((value) => value !== '' && value !== null && value !== undefined);
        if (!hasAny) {
            return { valid: false, message: 'Captura al menos un campo' };
        }
        return { valid: true };
    }

    validateEditData(data) {
        return this.validateCreateData(data);
    }

    processData(data) {
        const processed = { ...data };

        if (processed.Calibre === '' || processed.Calibre === null) {
            processed.Calibre = null;
        } else {
            const parsed = parseFloat(processed.Calibre);
            processed.Calibre = Number.isNaN(parsed) ? null : parsed;
        }

        Object.keys(processed).forEach((key) => {
            if (key !== 'Calibre' && processed[key] === '') {
                processed[key] = null;
            }
        });

        return processed;
    }

    renderRow() {
        return '';
    }

    getRowData(row) {
        return {
            id: row.getAttribute('data-id') || '',
            tipo: row.getAttribute('data-tipo') || '',
            calibre: row.getAttribute('data-calibre') || '',
            fibraid: row.getAttribute('data-fibraid') || '',
            cuenta: row.getAttribute('data-cuenta') || '',
            itemid: row.getAttribute('data-itemid') || '',
            configid: row.getAttribute('data-configid') || '',
            inventsizeid: row.getAttribute('data-inventsizeid') || '',
            inventcolorid: row.getAttribute('data-inventcolorid') || '',
        };
    }

    getDeleteConfirmMessage(rowData) {
        const label = rowData.tipo || rowData.itemid || rowData.id || 'seleccionado';
        return `<p>¿Eliminar el registro <strong>${this.escapeHtml(label)}</strong>?</p>`;
    }
}

if (typeof window !== 'undefined') {
    window.MatrizCalibresCatalog = MatrizCalibresCatalog;
}
