/**
 * CatalogBase - Clase base para manejo de catálogos
 * Implementa patrón Template Method para operaciones CRUD
 */
class CatalogBase {
    constructor(config) {
        this.config = {
            tableBodyId: config.tableBodyId,
            route: config.route,
            fields: config.fields || [],
            filters: config.filters || {},
            enableFilters: config.enableFilters !== false,
            enableExcel: config.enableExcel !== false,
            ...config
        };

        this.state = {
            selectedRow: null,
            selectedId: null,
            selectedKey: null,
            originalData: config.initialData || [],
            currentData: config.initialData || [],
            filters: {},
            filterCache: new Map()
        };

        this.init();
    }

    init() {
        this.setupEventListeners();
        this.disableButtons();
        this.bindGlobalFunctions();
    }

    // ============ Template Methods (deben ser sobrescritos) ============

    /**
     * Define la estructura del formulario para crear
     * @returns {string} HTML del formulario
     */
    getCreateFormHTML() {
        throw new Error('getCreateFormHTML debe ser implementado');
    }

    /**
     * Define la estructura del formulario para editar
     * @param {Object} data - Datos del registro seleccionado
     * @returns {string} HTML del formulario
     */
    getEditFormHTML(data) {
        throw new Error('getEditFormHTML debe ser implementado');
    }

    /**
     * Valida los datos antes de crear
     * @param {Object} data - Datos a validar
     * @returns {Object} { valid: boolean, message?: string }
     */
    validateCreateData(data) {
        return { valid: true };
    }

    /**
     * Valida los datos antes de editar
     * @param {Object} data - Datos a validar
     * @returns {Object} { valid: boolean, message?: string }
     */
    validateEditData(data) {
        return { valid: true };
    }

    /**
     * Procesa los datos antes de enviar al servidor
     * @param {Object} data - Datos a procesar
     * @param {string} action - 'create' | 'edit'
     * @returns {Object} Datos procesados
     */
    processData(data, action) {
        return data;
    }

    /**
     * Renderiza una fila de la tabla
     * @param {Object} item - Datos del item
     * @returns {string} HTML de la fila
     */
    renderRow(item) {
        throw new Error('renderRow debe ser implementado');
    }

    /**
     * Obtiene el ID único del registro desde el elemento HTML
     * @param {HTMLElement} row - Fila seleccionada
     * @returns {string|number} ID del registro
     */
    getRowId(row) {
        return row.dataset.id || row.getAttribute('data-id');
    }

    /**
     * Obtiene los datos del registro desde el elemento HTML
     * @param {HTMLElement} row - Fila seleccionada
     * @returns {Object} Datos del registro
     */
    getRowData(row) {
        const data = {};
        Object.keys(row.dataset).forEach(key => {
            data[key] = row.dataset[key];
        });
        return data;
    }

    // ============ Métodos de Estado ============

    selectRow(row, uniqueId, id) {
        const tbody = document.getElementById(this.config.tableBodyId);
        if (!tbody) return;

        // Deseleccionar todas las filas
        tbody.querySelectorAll('tr').forEach(r => {
            r.classList.remove('bg-blue-500', 'text-white');
            r.classList.add('hover:bg-blue-50');
        });

        // Seleccionar fila actual
        row.classList.remove('hover:bg-blue-50');
        row.classList.add('bg-blue-500', 'text-white');

        // Obtener el ID de la fila si no se proporcionó
        const rowId = id || this.getRowId(row) || uniqueId;

        console.log('selectRow llamado:', { rowId, id, uniqueId, row, getRowIdResult: this.getRowId(row) });

        // Guardar estado
        this.state.selectedRow = row;
        this.state.selectedKey = uniqueId || rowId;
        this.state.selectedId = rowId;

        // Guardar también en el elemento para recuperarlo después si es necesario
        if (rowId) {
            row.dataset.selectedId = rowId;
            row.setAttribute('data-selected-id', rowId);
        }

        console.log('Estado guardado:', {
            selectedRow: !!this.state.selectedRow,
            selectedId: this.state.selectedId,
            selectedKey: this.state.selectedKey
        });

        this.enableButtons();
        this.onRowSelected(row, this.state.selectedId);
    }

    deselectRow(row) {
        if (!row.classList.contains('bg-blue-500')) return;

        row.classList.remove('bg-blue-500', 'text-white');
        row.classList.add('hover:bg-blue-50');

        this.state.selectedRow = null;
        this.state.selectedKey = null;
        this.state.selectedId = null;

        this.disableButtons();
        this.onRowDeselected();
    }

    onRowSelected(row, id) {
        // Hook para lógica adicional al seleccionar
    }

    onRowDeselected() {
        // Hook para lógica adicional al deseleccionar
    }

    // ============ Métodos de UI ============

    enableButtons() {
        const editBtn = document.getElementById('btn-editar');
        const deleteBtn = document.getElementById('btn-eliminar');

        if (editBtn) {
            editBtn.disabled = false;
            editBtn.className = 'p-2 text-blue-600 hover:text-blue-800 rounded-md transition-colors';
        }

        if (deleteBtn) {
            deleteBtn.disabled = false;
            deleteBtn.className = 'p-2 text-red-600 hover:text-red-800 rounded-md transition-colors';
        }
    }

    disableButtons() {
        const editBtn = document.getElementById('btn-editar');
        const deleteBtn = document.getElementById('btn-eliminar');

        if (editBtn) {
            editBtn.disabled = true;
            editBtn.className = 'p-2 text-gray-400 rounded-md transition-colors cursor-not-allowed';
        }

        if (deleteBtn) {
            deleteBtn.disabled = true;
            deleteBtn.className = 'p-2 text-gray-400 rounded-md transition-colors cursor-not-allowed';
        }
    }

    showToast(message, type = 'info', duration = 1500) {
        if (window.showToast) {
            window.showToast(message, type);
            return;
        }

        // Fallback usando SweetAlert2
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: duration,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
        Toast.fire({ icon: type, title: message });
    }

    // ============ Métodos CRUD ============

    async create() {
        const html = this.getCreateFormHTML();

        const result = await Swal.fire({
            title: this.config.createTitle || 'Crear Nuevo Registro',
            html: html,
            width: this.config.modalWidth || '500px',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-plus mr-2"></i>Crear',
            cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancelar',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            didOpen: () => {
                this.onCreateFormOpen();
            },
            preConfirm: async () => {
                const data = this.extractFormData('create');
                const validation = this.validateCreateData(data);

                if (!validation.valid) {
                    Swal.showValidationMessage(validation.message || 'Por favor completa los campos requeridos');
                    return false;
                }

                return this.processData(data, 'create');
            }
        });

        if (!result.isConfirmed) return;

        await this.performCreate(result.value);
    }

    async edit() {
        // Si el estado se perdió, intentar encontrar la fila seleccionada visualmente
        if (!this.state.selectedRow || !this.state.selectedId) {
            const tbody = document.getElementById(this.config.tableBodyId);
            if (tbody) {
                // Buscar fila seleccionada por clase
                const selectedRow = tbody.querySelector('tr.bg-blue-500');
                if (selectedRow) {
                    const id = this.getRowId(selectedRow);
                    console.log('Fila seleccionada encontrada visualmente:', { id, row: selectedRow });
                    if (id) {
                        this.state.selectedRow = selectedRow;
                        this.state.selectedId = id;
                    }
                } else {
                    // Buscar por cualquier fila que tenga el atributo data-selected-id
                    const rows = tbody.querySelectorAll('tr[data-selected-id]');
                    if (rows.length > 0) {
                        const row = rows[0];
                        const id = row.dataset.selectedId || this.getRowId(row);
                        console.log('Fila encontrada por data-selected-id:', { id, row });
                        if (id) {
                            this.state.selectedRow = row;
                            this.state.selectedId = id;
                        }
                    }
                }
            }
        }

        console.log('Estado antes de editar:', {
            selectedRow: !!this.state.selectedRow,
            selectedId: this.state.selectedId,
            config: this.config
        });

        if (!this.state.selectedRow || !this.state.selectedId) {
            this.showToast('Por favor selecciona un registro para editar', 'warning');
            return;
        }

        const rowData = this.getRowData(this.state.selectedRow);
        const html = this.getEditFormHTML(rowData);

        const result = await Swal.fire({
            title: this.config.editTitle || 'Editar Registro',
            html: html,
            width: this.config.modalWidth || '500px',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save mr-2"></i>Actualizar',
            cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancelar',
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6b7280',
            didOpen: () => {
                this.onEditFormOpen(rowData);
            },
            preConfirm: async () => {
                const data = this.extractFormData('edit');
                const validation = this.validateEditData(data);

                if (!validation.valid) {
                    Swal.showValidationMessage(validation.message || 'Por favor completa los campos requeridos');
                    return false;
                }

                return this.processData(data, 'edit');
            }
        });

        if (!result.isConfirmed) return;

        await this.performEdit(this.state.selectedId, result.value);
    }

    async delete() {
        // Si el estado se perdió, intentar encontrar la fila seleccionada visualmente
        if (!this.state.selectedRow || !this.state.selectedId) {
            const tbody = document.getElementById(this.config.tableBodyId);
            if (tbody) {
                // Buscar fila seleccionada por clase
                const selectedRow = tbody.querySelector('tr.bg-blue-500');
                if (selectedRow) {
                    const id = this.getRowId(selectedRow);
                    console.log('Fila seleccionada encontrada visualmente:', { id, row: selectedRow });
                    if (id) {
                        this.state.selectedRow = selectedRow;
                        this.state.selectedId = id;
                    }
                } else {
                    // Buscar por cualquier fila que tenga el atributo data-selected-id
                    const rows = tbody.querySelectorAll('tr[data-selected-id]');
                    if (rows.length > 0) {
                        const row = rows[0];
                        const id = row.dataset.selectedId || this.getRowId(row);
                        console.log('Fila encontrada por data-selected-id:', { id, row });
                        if (id) {
                            this.state.selectedRow = row;
                            this.state.selectedId = id;
                        }
                    }
                }
            }
        }

        console.log('Estado antes de eliminar:', {
            selectedRow: !!this.state.selectedRow,
            selectedId: this.state.selectedId,
            config: this.config
        });

        if (!this.state.selectedRow || !this.state.selectedId) {
            this.showToast('Por favor selecciona un registro para eliminar', 'warning');
            return;
        }

        const rowData = this.getRowData(this.state.selectedRow);
        const confirmMessage = this.getDeleteConfirmMessage(rowData);

        const result = await Swal.fire({
            title: '¿Eliminar Registro?',
            html: confirmMessage,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-trash mr-2"></i>Sí, eliminar',
            cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancelar'
        });

        if (!result.isConfirmed) return;

        await this.performDelete(this.state.selectedId);
    }

    getDeleteConfirmMessage(rowData) {
        return `<p>¿Estás seguro de eliminar este registro?</p>`;
    }

    // ============ Métodos de API ============

    async performCreate(data) {
        Swal.fire({
            title: 'Creando...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const response = await fetch(`/planeacion/${this.config.route}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCSRFToken(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || `HTTP ${response.status}`);
            }

            Swal.fire({
                icon: 'success',
                title: '¡Registro creado!',
                timer: 2000,
                showConfirmButton: false
            }).then(() => location.reload());

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Error al crear el registro'
            });
        }
    }

    async performEdit(id, data) {
        Swal.fire({
            title: 'Actualizando...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const response = await fetch(`/planeacion/${this.config.route}/${encodeURIComponent(id)}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCSRFToken(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || `HTTP ${response.status}`);
            }

            Swal.fire({
                icon: 'success',
                title: '¡Registro actualizado!',
                timer: 1800,
                showConfirmButton: false
            }).then(() => location.reload());

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Error al actualizar el registro'
            });
        }
    }

    async performDelete(id) {
        Swal.fire({
            title: 'Eliminando...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const response = await fetch(`/planeacion/${this.config.route}/${encodeURIComponent(id)}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCSRFToken(),
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || `HTTP ${response.status}`);
            }

            Swal.fire({
                icon: 'success',
                title: '¡Registro eliminado!',
                timer: 1800,
                showConfirmButton: false
            }).then(() => location.reload());

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Error al eliminar el registro'
            });
        }
    }

    // ============ Métodos Helper ============

    extractFormData(action) {
        const prefix = action === 'create' ? 'swal-' : 'swal-edit-';
        const data = {};

        this.config.fields.forEach(field => {
            const inputId = `${prefix}${field.name}`;
            const input = document.getElementById(inputId);
            if (input) {
                let value = input.value.trim();

                // Procesar según tipo
                if (field.type === 'number') {
                    value = value ? parseFloat(value) : null;
                } else if (field.type === 'boolean') {
                    value = input.checked;
                }

                data[field.name] = value;
            }
        });

        return data;
    }

    getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    onCreateFormOpen() {
        // Hook para lógica adicional al abrir formulario de creación
    }

    onEditFormOpen(rowData) {
        // Hook para lógica adicional al abrir formulario de edición
    }

    // ============ Métodos de Filtros ============

    async showFilters() {
        if (!this.config.enableFilters) return;

        const html = this.getFiltersFormHTML();

        const result = await Swal.fire({
            title: 'Filtrar Registros',
            html: html,
            width: this.config.filterModalWidth || '500px',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-filter mr-2"></i>Filtrar',
            cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancelar',
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6b7280',
            didOpen: () => {
                this.onFiltersFormOpen();
            },
            preConfirm: () => {
                return this.extractFiltersData();
            }
        });

        if (result.isConfirmed && result.value) {
            this.applyFilters(result.value);
        }
    }

    getFiltersFormHTML() {
        // Implementación por defecto - puede ser sobrescrita
        return '<p>Filtros no configurados</p>';
    }

    extractFiltersData() {
        return {};
    }

    applyFilters(filters) {
        this.state.filters = filters;
        const cacheKey = JSON.stringify(filters);

        if (this.state.filterCache.has(cacheKey)) {
            this.state.currentData = this.state.filterCache.get(cacheKey);
            this.renderTable();
            this.showToast(`${this.state.currentData.length} registros mostrados`, 'success');
            return;
        }

        const filtered = this.state.originalData.filter(item => {
            return this.matchesFilters(item, filters);
        });

        if (this.state.filterCache.size >= 10) {
            const firstKey = this.state.filterCache.keys().next().value;
            this.state.filterCache.delete(firstKey);
        }

        this.state.filterCache.set(cacheKey, filtered);
        this.state.currentData = filtered;
        this.renderTable();
        this.showToast(`${filtered.length} de ${this.state.originalData.length} registros mostrados`, 'success');
    }

    matchesFilters(item, filters) {
        for (const [key, value] of Object.entries(filters)) {
            if (!value || value === '') continue;

            const itemValue = String(item[key] || '').toLowerCase();
            const filterValue = String(value).toLowerCase();

            if (!itemValue.includes(filterValue)) {
                return false;
            }
        }
        return true;
    }

    clearFilters() {
        this.state.filters = {};
        this.state.filterCache.clear();
        this.state.currentData = this.state.originalData;
        this.renderTable();
        this.showToast(`Filtros limpiados - Mostrando ${this.state.originalData.length} registros`, 'success');
    }

    // ============ Métodos de Renderizado ============

    renderTable() {
        const tbody = document.getElementById(this.config.tableBodyId);
        if (!tbody) return;

        if (!this.state.currentData || this.state.currentData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="100" class="text-center py-8 text-gray-500">
                        <i class="fas fa-search text-4xl mb-2"></i><br>No se encontraron resultados
                    </td>
                </tr>`;
            return;
        }

        const fragment = document.createDocumentFragment();
        this.state.currentData.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = this.renderRow(item);
            tr.className = 'text-center hover:bg-blue-50 transition cursor-pointer';

            const uniqueId = item[this.config.idField] || item.Id || item.id;
            const recordId = item.Id || item.id || uniqueId;

            tr.onclick = () => this.selectRow(tr, uniqueId, recordId);
            tr.ondblclick = () => this.deselectRow(tr);

            // Agregar data attributes
            Object.keys(item).forEach(key => {
                tr.setAttribute(`data-${key.toLowerCase()}`, item[key]);
            });
            tr.setAttribute('data-id', recordId);

            fragment.appendChild(tr);
        });

        tbody.innerHTML = '';
        tbody.appendChild(fragment);
    }

    // ============ Métodos de Excel ============

    async uploadExcel() {
        if (!this.config.enableExcel) return;

        const result = await Swal.fire({
            title: 'Subir Excel',
            html: `
                <div class="text-left">
                    <div class="mb-3">
                        <label class="block text-sm font-medium mb-1">Archivo Excel</label>
                        <input id="excel-file" type="file" accept=".xlsx,.xls" class="swal2-input">
                    </div>
                    <div class="text-xs text-gray-600 bg-blue-50 p-2 rounded">
                        Formatos: .xlsx, .xls (máx 10MB)
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Subir',
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6c757d',
            preConfirm: async () => {
                const file = document.getElementById('excel-file').files[0];
                if (!file) {
                    Swal.showValidationMessage('Selecciona un archivo');
                    return false;
                }
                return file;
            }
        });

        if (!result.isConfirmed) return;

        await this.performExcelUpload(result.value);
    }

    async performExcelUpload(file) {
        Swal.fire({
            title: 'Procesando...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        const formData = new FormData();
        formData.append('archivo_excel', file);
        formData.append('_token', this.getCSRFToken());

        try {
            const response = await fetch(`/planeacion/${this.config.route}/excel`, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || `HTTP ${response.status}`);
            }

            Swal.fire({
                icon: 'success',
                title: '¡Excel procesado!',
                timer: 2000,
                showConfirmButton: false
            }).then(() => location.reload());

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Error al procesar el Excel'
            });
        }
    }

    // ============ Setup ============

    setupEventListeners() {
        const tbody = document.getElementById(this.config.tableBodyId);
        if (!tbody) return;

        // Los event listeners se agregan en renderTable
    }

    bindGlobalFunctions() {
        const routeName = this.config.route.replace(/-/g, '_');

        window[`agregar${this.capitalize(routeName)}`] = () => this.create();
        window[`editar${this.capitalize(routeName)}`] = () => this.edit();
        window[`eliminar${this.capitalize(routeName)}`] = () => this.delete();
        window[`filtrar${this.capitalize(routeName)}`] = () => this.showFilters();
        window[`limpiarFiltros${this.capitalize(routeName)}`] = () => this.clearFilters();
        window[`subirExcel${this.capitalize(routeName)}`] = () => this.uploadExcel();
    }

    capitalize(str) {
        return str.split(/[-_]/).map(word =>
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join('');
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.CatalogBase = CatalogBase;
}

