/**
 * AplicacionesCatalog - Implementación específica para catálogo de aplicaciones
 */
class AplicacionesCatalog extends CatalogBase {
    constructor(config) {
        super({
            tableBodyId: 'aplicaciones-body',
            route: 'aplicaciones',
            idField: 'AplicacionId',
            fields: [
                { name: 'AplicacionId', label: 'Clave', type: 'text', required: true, maxlength: 50 },
                { name: 'Nombre', label: 'Nombre', type: 'text', required: true, maxlength: 100 },
                { name: 'Factor', label: 'Factor', type: 'number', required: false, step: '0.0001' }
            ],
            filters: {
                clave: '',
                nombre: '',
                factor: ''
            },
            enableFilters: true,
            enableExcel: true,
            createTitle: 'Agregar Nueva Aplicación',
            editTitle: 'Editar Aplicación',
            modalWidth: '520px',
            ...config
        });
    }

    getCreateFormHTML() {
        return `
            <div class="grid grid-cols-3 gap-3 text-sm text-left">
                <div class="col-span-1">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Clave *</label>
                    <input id="swal-AplicacionId" type="text" class="swal2-input" placeholder="APP001" maxlength="50" required>
                </div>
                <div class="col-span-1">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Nombre *</label>
                    <input id="swal-Nombre" type="text" class="swal2-input" placeholder="TR / RZ" maxlength="100" required>
                </div>
                <div class="col-span-1">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Factor</label>
                    <input id="swal-Factor" type="number" step="0.0001" class="swal2-input" placeholder="0">
                </div>
            </div>
        `;
    }

    getEditFormHTML(data) {
        const clave = (data.aplicacionid || data.clave || '').replace(/"/g, '&quot;');
        const nombre = (data.nombre || '').replace(/"/g, '&quot;');
        const factor = (data.factor || '').replace(/"/g, '&quot;');
        
        return `
            <div class="grid grid-cols-3 gap-3 text-sm text-left">
                <div class="col-span-1">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Clave *</label>
                    <input id="swal-edit-AplicacionId" type="text" class="swal2-input" maxlength="50" required value="${clave}">
                </div>
                <div class="col-span-1">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Nombre *</label>
                    <input id="swal-edit-Nombre" type="text" class="swal2-input" maxlength="100" required value="${nombre}">
                </div>
                <div class="col-span-1">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Factor</label>
                    <input id="swal-edit-Factor" type="number" step="0.0001" class="swal2-input" value="${factor}">
                </div>
            </div>
        `;
    }

    validateCreateData(data) {
        if (!data.AplicacionId || !data.Nombre) {
            return { valid: false, message: 'Clave y Nombre son obligatorios' };
        }
        return { valid: true };
    }

    validateEditData(data) {
        if (!data.AplicacionId || !data.Nombre) {
            return { valid: false, message: 'Clave y Nombre son obligatorios' };
        }
        return { valid: true };
    }

    processData(data, action) {
        // Limpiar campos vacíos
        if (data.Factor === '' || data.Factor === null) {
            delete data.Factor;
        }
        return data;
    }

    renderRow(item) {
        return `
            <td class="py-1 px-4 border-b">${item.AplicacionId || ''}</td>
            <td class="py-1 px-4 border-b">${item.Nombre || ''}</td>
            <td class="py-1 px-4 border-b font-semibold">${item.Factor || ''}</td>
        `;
    }

    getRowData(row) {
        // Para aplicaciones, necesitamos mapear los datos correctamente
        return {
            aplicacionid: row.getAttribute('data-aplicacion-id') || row.getAttribute('data-clave') || '',
            clave: row.getAttribute('data-clave') || '',
            nombre: row.getAttribute('data-nombre') || '',
            factor: row.getAttribute('data-factor') || ''
        };
    }

    getFiltersFormHTML() {
        return `
            <div class="grid grid-cols-3 gap-3 text-sm text-left">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Clave</label>
                    <input id="swal-clave-filter" type="text" class="swal2-input" placeholder="APP001" value="${this.state.filters.clave || ''}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Nombre</label>
                    <input id="swal-nombre-filter" type="text" class="swal2-input" placeholder="TR / DC" value="${this.state.filters.nombre || ''}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Factor</label>
                    <input id="swal-factor-filter" type="text" class="swal2-input" placeholder="0 / 1 / 2" value="${this.state.filters.factor || ''}">
                </div>
            </div>
            <div class="mt-3 text-xs text-gray-500 bg-blue-50 p-2 rounded">
                <i class="fas fa-info-circle mr-1"></i> Deja campos vacíos para no aplicar filtro.
            </div>
        `;
    }

    extractFiltersData() {
        return {
            clave: document.getElementById('swal-clave-filter')?.value.trim() || '',
            nombre: document.getElementById('swal-nombre-filter')?.value.trim() || '',
            factor: document.getElementById('swal-factor-filter')?.value.trim() || ''
        };
    }

    matchesFilters(item, filters) {
        const clave = String(item.AplicacionId || '').toLowerCase();
        const nombre = String(item.Nombre || '').toLowerCase();
        const factor = String(item.Factor || '').toLowerCase();

        if (filters.clave && !clave.includes(filters.clave.toLowerCase())) return false;
        if (filters.nombre && !nombre.includes(filters.nombre.toLowerCase())) return false;
        if (filters.factor && !factor.includes(filters.factor.toLowerCase())) return false;

        return true;
    }

    getDeleteConfirmMessage(rowData) {
        const clave = rowData.aplicacionid || rowData.clave || '';
        const nombre = rowData.nombre || '';
        return `
            <div class="text-left">
                <p><strong>Clave:</strong> ${clave}</p>
                <p><strong>Nombre:</strong> ${nombre}</p>
                <hr>
            </div>
        `;
    }

    // Sobrescribir métodos de API para usar FormData
    async performCreate(data) {
        Swal.fire({
            title: 'Creando...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const formData = new FormData();
            formData.append('_token', this.getCSRFToken());
            
            Object.keys(data).forEach(key => {
                if (data[key] !== undefined && data[key] !== null && data[key] !== '') {
                    formData.append(key, data[key]);
                }
            });

            const response = await fetch(`/planeacion/${this.config.route}`, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });

            if (response.status === 422) {
                const result = await response.json();
                const errors = result?.errors || {};
                let message = result?.message || 'Error en la validación';
                
                if (errors.AplicacionId?.length) message = errors.AplicacionId[0];
                else if (errors.Nombre?.length) message = errors.Nombre[0];
                else if (errors.Factor?.length) message = errors.Factor[0];
                
                throw new Error(message);
            }

            if (!response.ok) {
                const text = await response.text();
                throw new Error(text || `HTTP ${response.status}`);
            }

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'No se pudo crear');
            }

            Swal.fire({
                icon: 'success',
                title: '¡Aplicación creada!',
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
            const formData = new FormData();
            formData.append('_token', this.getCSRFToken());
            formData.append('_method', 'PUT');
            
            Object.keys(data).forEach(key => {
                if (data[key] !== undefined && data[key] !== null && data[key] !== '') {
                    formData.append(key, data[key]);
                }
            });

            const response = await fetch(`/planeacion/${this.config.route}/${encodeURIComponent(id)}`, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });

            if (response.status === 422) {
                const result = await response.json();
                const errors = result?.errors || {};
                let message = result?.message || 'Error en la validación';
                
                if (errors.AplicacionId?.length) message = errors.AplicacionId[0];
                else if (errors.Nombre?.length) message = errors.Nombre[0];
                else if (errors.Factor?.length) message = errors.Factor[0];
                
                throw new Error(message);
            }

            if (!response.ok) {
                const text = await response.text();
                throw new Error(text || `HTTP ${response.status}`);
            }

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'No se pudo actualizar');
            }

            Swal.fire({
                icon: 'success',
                title: '¡Aplicación actualizada!',
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
}

// Exportar
if (typeof window !== 'undefined') {
    window.AplicacionesCatalog = AplicacionesCatalog;
}

