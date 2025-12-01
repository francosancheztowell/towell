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
                { name: 'NombreColor', label: 'NombreColor', type: 'text', required: false, maxlength: 60 },
                { name: 'N1', label: 'N1', type: 'number', required: false, step: '0.0001' },
                { name: 'N2', label: 'N2', type: 'number', required: false, step: '0.0001' }
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
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">N1</label>
                    <input id="swal-N1" type="number" step="0.0001" class="w-full px-2 py-2 border border-gray-300 rounded text-center" placeholder="0.0000">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">N2</label>
                    <input id="swal-N2" type="number" step="0.0001" class="w-full px-2 py-2 border border-gray-300 rounded text-center" placeholder="0.0000">
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
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">N1</label>
                    <input id="swal-edit-N1" type="number" step="0.0001" class="w-full px-2 py-2 border border-gray-300 rounded text-center" value="${escape(data.n1)}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">N2</label>
                    <input id="swal-edit-N2" type="number" step="0.0001" class="w-full px-2 py-2 border border-gray-300 rounded text-center" value="${escape(data.n2)}">
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
        // Convertir números y limpiar campos vacíos (excepto Hilo que es requerido)
        const numericFields = ['Calibre', 'Calibre2', 'N1', 'N2'];

        numericFields.forEach(field => {
            if (data[field] === '' || data[field] === null) {
                data[field] = null;
            } else if (data[field]) {
                const parsed = parseFloat(data[field]);
                data[field] = isNaN(parsed) ? null : parsed;
            }
        });

        // Limpiar campos vacíos excepto Hilo (que es requerido)
        Object.keys(data).forEach(key => {
            if (key !== 'Hilo' && (data[key] === '' || data[key] === null)) {
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
            <td class="py-2 px-4">${item.Hilo || ''}</td>
            <td class="py-2 px-4">${formatNumber(item.Calibre)}</td>
            <td class="py-2 px-4">${formatNumber(item.Calibre2)}</td>
            <td class="py-2 px-4">${item.CalibreAX || ''}</td>
            <td class="py-2 px-4">${item.Fibra || ''}</td>
            <td class="py-2 px-4">${item.CodColor || ''}</td>
            <td class="py-2 px-4">${item.NombreColor || ''}</td>
            <td class="py-2 px-4">${formatNumber(item.N1)}</td>
            <td class="py-2 px-4">${formatNumber(item.N2)}</td>
        `;
    }

    getRowId(row) {
        if (!row) return null;
        // Intentar obtener el ID de múltiples formas
        let id = row.getAttribute('data-id');

        // Si data-id está vacío o es null, intentar otras formas
        if (!id || id === '') {
            id = row.dataset.id ||
                 row.dataset.selectedId ||
                 row.getAttribute('data-selected-id') ||
                 null;
        }

        // Si aún no hay ID, intentar obtenerlo del onclick
        if (!id || id === '') {
            const onclick = row.getAttribute('onclick');
            if (onclick) {
                const match = onclick.match(/selectRow\([^,]+,\s*['"]?(\d+)['"]?/);
                if (match && match[1]) {
                    id = match[1];
                }
            }
        }

        console.log('MatrizHilosCatalog.getRowId:', {
            id,
            row,
            hasDataId: !!row.getAttribute('data-id'),
            dataIdValue: row.getAttribute('data-id'),
            datasetId: row.dataset.id,
            onclick: row.getAttribute('onclick')
        });
        return id;
    }

    getRowData(row) {
        return {
            id: row.getAttribute('data-id') || '',
            hilo: row.getAttribute('data-hilo') || '',
            calibre: row.getAttribute('data-calibre') || '',
            calibre2: row.getAttribute('data-calibre2') || '',
            calibreax: row.getAttribute('data-calibreax') || '',
            fibra: row.getAttribute('data-fibra') || '',
            codcolor: row.getAttribute('data-codcolor') || '',
            nombrecolor: row.getAttribute('data-nombrecolor') || '',
            n1: row.getAttribute('data-n1') || '',
            n2: row.getAttribute('data-n2') || ''
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
            NombreColor: document.getElementById(`${prefix}NombreColor`)?.value.trim() || '',
            N1: document.getElementById(`${prefix}N1`)?.value.trim() || '',
            N2: document.getElementById(`${prefix}N2`)?.value.trim() || ''
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
                // Mostrar mensaje de error más detallado
                let errorMessage = result.message || `Error HTTP ${response.status}`;
                if (result.errors) {
                    const errorList = Object.values(result.errors).flat().join(', ');
                    errorMessage = errorList || errorMessage;
                }
                throw new Error(errorMessage);
            }

            Swal.fire({
                icon: 'success',
                title: '¡Registro creado!',
                timer: 2000,
                showConfirmButton: false
            }).then(() => location.reload());

        } catch (error) {
            console.error('Error al crear registro:', error);
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
                // Mostrar mensaje de error más detallado
                let errorMessage = result.message || `Error HTTP ${response.status}`;
                if (result.errors) {
                    const errorList = Object.values(result.errors).flat().join(', ');
                    errorMessage = errorList || errorMessage;
                }
                throw new Error(errorMessage);
            }

            Swal.fire({
                icon: 'success',
                title: '¡Registro actualizado!',
                timer: 1800,
                showConfirmButton: false
            }).then(() => location.reload());

        } catch (error) {
            console.error('Error al actualizar registro:', error);
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

