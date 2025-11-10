/**
 * MatrizHilosCatalog - Implementación específica para catálogo de matriz de hilos
 */
class MatrizHilosCatalog extends CatalogBase {
    constructor(config) {
        super({
            tableBodyId: 'matriz-hilos-body',
            route: 'catalogos/matriz-hilos',
            idField: 'id',
            fields: [
                { name: 'Hilo', label: 'Hilo', type: 'text', required: true, maxlength: 30 },
                { name: 'Calibre', label: 'Calibre', type: 'number', required: false, step: '0.0001' },
                { name: 'Calibre2', label: 'Calibre2', type: 'number', required: false, step: '0.0001' },
                { name: 'CalibreAX', label: 'CalibreAX', type: 'text', required: false, maxlength: 20 },
                { name: 'Fibra', label: 'Fibra', type: 'text', required: false, maxlength: 30 },
                { name: 'CodColor', label: 'CodColor', type: 'text', required: false, maxlength: 10 },
                { name: 'NombreColor', label: 'NombreColor', type: 'text', required: false, maxlength: 60 }
            ],
            enableFilters: false,
            enableExcel: false,
            createTitle: 'Crear Nueva Matriz de Hilos',
            editTitle: 'Editar Matriz de Hilos',
            modalWidth: '500px',
            ...config
        });
    }

    getCreateFormHTML() {
        return `
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Hilo *</label>
                    <input id="swal-Hilo" type="text" class="w-full px-2 py-2 border border-gray-300 rounded text-center" placeholder="Ej: H001" maxlength="30" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Calibre</label>
                    <input id="swal-Calibre" type="number" step="0.0001" class="w-full px-2 py-2 border border-gray-300 rounded text-center" placeholder="0.0000">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Calibre2</label>
                    <input id="swal-Calibre2" type="number" step="0.0001" class="w-full px-2 py-2 border border-gray-300 rounded text-center" placeholder="0.0000">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">CalibreAX</label>
                    <input id="swal-CalibreAX" type="text" maxlength="20" class="w-full px-2 py-2 border border-gray-300 rounded text-center" placeholder="Calibre AX">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Fibra</label>
                    <input id="swal-Fibra" type="text" maxlength="30" class="w-full px-2 py-2 border border-gray-300 rounded text-center" placeholder="Tipo de fibra">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">CodColor</label>
                    <input id="swal-CodColor" type="text" maxlength="10" class="w-full px-2 py-2 border border-gray-300 rounded text-center" placeholder="Código color">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">NombreColor</label>
                    <input id="swal-NombreColor" type="text" maxlength="60" class="w-full px-2 py-2 border border-gray-300 rounded text-center" placeholder="Nombre del color">
                </div>
            </div>
        `;
    }

    getEditFormHTML(data) {
        const escape = (str) => (str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

        return `
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Hilo *</label>
                    <input id="swal-edit-Hilo" type="text" class="w-full px-2 py-2 border border-gray-300 rounded text-center" maxlength="30" required value="${escape(data.hilo)}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Calibre</label>
                    <input id="swal-edit-Calibre" type="number" step="0.0001" class="w-full px-2 py-2 border border-gray-300 rounded text-center" value="${escape(data.calibre)}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Calibre2</label>
                    <input id="swal-edit-Calibre2" type="number" step="0.0001" class="w-full px-2 py-2 border border-gray-300 rounded text-center" value="${escape(data.calibre2)}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">CalibreAX</label>
                    <input id="swal-edit-CalibreAX" type="text" maxlength="20" class="w-full px-2 py-2 border border-gray-300 rounded text-center" value="${escape(data.calibreax)}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Fibra</label>
                    <input id="swal-edit-Fibra" type="text" maxlength="30" class="w-full px-2 py-2 border border-gray-300 rounded text-center" value="${escape(data.fibra)}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">CodColor</label>
                    <input id="swal-edit-CodColor" type="text" maxlength="10" class="w-full px-2 py-2 border border-gray-300 rounded text-center" value="${escape(data.codcolor)}">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">NombreColor</label>
                    <input id="swal-edit-NombreColor" type="text" maxlength="60" class="w-full px-2 py-2 border border-gray-300 rounded text-center" value="${escape(data.nombrecolor)}">
                </div>
            </div>
        `;
    }

    validateCreateData(data) {
        if (!data.Hilo) {
            return { valid: false, message: 'El campo Hilo es requerido' };
        }
        return { valid: true };
    }

    validateEditData(data) {
        return this.validateCreateData(data);
    }

    processData(data, action) {
        // Convertir números y limpiar campos vacíos
        if (data.Calibre === '' || data.Calibre === null) data.Calibre = null;
        else if (data.Calibre) data.Calibre = parseFloat(data.Calibre);

        if (data.Calibre2 === '' || data.Calibre2 === null) data.Calibre2 = null;
        else if (data.Calibre2) data.Calibre2 = parseFloat(data.Calibre2);

        Object.keys(data).forEach(key => {
            if (data[key] === '' || data[key] === null) {
                data[key] = null;
            }
        });

        return data;
    }

    renderRow(item) {
        const formatNumber = (val) => {
            if (!val && val !== 0) return '';
            const num = parseFloat(val);
            return isNaN(num) ? '' : num.toFixed(4);
        };
        return `
            <td class="py-2 px-4 border-b">${item.Hilo || ''}</td>
            <td class="py-2 px-4 border-b">${formatNumber(item.Calibre)}</td>
            <td class="py-2 px-4 border-b">${formatNumber(item.Calibre2)}</td>
            <td class="py-2 px-4 border-b">${item.CalibreAX || ''}</td>
            <td class="py-2 px-4 border-b">${item.Fibra || ''}</td>
            <td class="py-2 px-4 border-b">${item.CodColor || ''}</td>
            <td class="py-2 px-4 border-b">${item.NombreColor || ''}</td>
        `;
    }

    getRowData(row) {
        return {
            hilo: row.getAttribute('data-hilo') || '',
            calibre: row.getAttribute('data-calibre') || '',
            calibre2: row.getAttribute('data-calibre2') || '',
            calibreax: row.getAttribute('data-calibreax') || '',
            fibra: row.getAttribute('data-fibra') || '',
            codcolor: row.getAttribute('data-codcolor') || '',
            nombrecolor: row.getAttribute('data-nombrecolor') || ''
        };
    }

    getDeleteConfirmMessage(rowData) {
        const hilo = rowData.hilo || '';
        return `<p>¿Estás seguro de eliminar el hilo "${hilo}"?</p>`;
    }

    extractFormData(action) {
        const prefix = action === 'create' ? 'swal-' : 'swal-edit-';
        return {
            Hilo: document.getElementById(`${prefix}Hilo`)?.value.trim() || '',
            Calibre: document.getElementById(`${prefix}Calibre`)?.value.trim() || '',
            Calibre2: document.getElementById(`${prefix}Calibre2`)?.value.trim() || '',
            CalibreAX: document.getElementById(`${prefix}CalibreAX`)?.value.trim() || '',
            Fibra: document.getElementById(`${prefix}Fibra`)?.value.trim() || '',
            CodColor: document.getElementById(`${prefix}CodColor`)?.value.trim() || '',
            NombreColor: document.getElementById(`${prefix}NombreColor`)?.value.trim() || ''
        };
    }

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
                text: error.message || 'No se pudo actualizar el registro'
            });
        }
    }
}

// Exportar
if (typeof window !== 'undefined') {
    window.MatrizHilosCatalog = MatrizHilosCatalog;
}

