/**
 * JuliosCatalog - Implementación específica para catálogo de julios
 */
class JuliosCatalog extends CatalogBase {
    constructor(config) {
        super({
            tableBodyId: 'julios-body',
            route: 'julios',
            idField: 'Id',
            fields: [
                { name: 'NoJulio', label: 'No. Julio', type: 'text', required: true, maxlength: 10 },
                { name: 'Tara', label: 'Tara', type: 'number', required: true, step: '0.01' },
                { name: 'Departamento', label: 'Departamento', type: 'text', required: false, maxlength: 50 }
            ],
            enableFilters: true,
            enableExcel: true,
            createTitle: 'Crear Julio',
            editTitle: 'Editar Julio',
            modalWidth: '500px',
            ...config
        });
    }

    getCreateFormHTML() {
        return `
            <div class="grid grid-cols-1 gap-3 text-left text-sm">
                <div>
                    <label class="block text-xs font-medium mb-1">No. Julio *</label>
                    <input id="swal-NoJulio" class="swal2-input" maxlength="10" placeholder="Ej: 1, 2, 3">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Tara *</label>
                    <input id="swal-Tara" class="swal2-input" type="number" step="0.01" placeholder="Ej: 124.20">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Departamento</label>
                    <input id="swal-Departamento" class="swal2-input" maxlength="50" placeholder="Ej: Urdido">
                </div>
            </div>
        `;
    }

    getEditFormHTML(item) {
        return `
            <div class="grid grid-cols-1 gap-3 text-left text-sm">
                <div>
                    <label class="block text-xs font-medium mb-1">No. Julio *</label>
                    <input id="swal-NoJulio" class="swal2-input" maxlength="10" value="${item.NoJulio || ''}" placeholder="Ej: 1, 2, 3">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Tara *</label>
                    <input id="swal-Tara" class="swal2-input" type="number" step="0.01" value="${item.Tara || 0}" placeholder="Ej: 124.20">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Departamento</label>
                    <input id="swal-Departamento" class="swal2-input" maxlength="50" value="${item.Departamento || ''}" placeholder="Ej: Urdido">
                </div>
            </div>
        `;
    }

    getFilterFormHTML() {
        return `
            <div class="grid grid-cols-1 gap-3 text-left text-sm">
                <div>
                    <label class="block text-xs font-medium mb-1">No. Julio</label>
                    <input id="filter-no-julio" class="swal2-input" placeholder="Buscar por No. Julio">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Departamento</label>
                    <input id="filter-departamento" class="swal2-input" placeholder="Buscar por Departamento">
                </div>
            </div>
        `;
    }

    getFormData() {
        return {
            NoJulio: document.getElementById('swal-NoJulio')?.value?.trim() || '',
            Tara: parseFloat(document.getElementById('swal-Tara')?.value) || 0,
            Departamento: document.getElementById('swal-Departamento')?.value?.trim() || null
        };
    }

    getFilterData() {
        return {
            no_julio: document.getElementById('filter-no-julio')?.value?.trim() || '',
            departamento: document.getElementById('filter-departamento')?.value?.trim() || ''
        };
    }

    validateForm(data) {
        if (!data.NoJulio || data.NoJulio.trim() === '') {
            throw new Error('El No. Julio es requerido');
        }
        if (data.Tara === null || data.Tara === undefined || isNaN(data.Tara)) {
            throw new Error('La Tara es requerida y debe ser un número válido');
        }
        return true;
    }
}

