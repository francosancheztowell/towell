/**
 * MaquinasUrdidoCatalog - Implementación específica para catálogo de máquinas de Urdido
 */
class MaquinasUrdidoCatalog extends CatalogBase {
    constructor(config) {
        super({
            tableBodyId: 'maquinas-body',
            route: 'maquinas',
            idField: 'MaquinaId',
            fields: [
                { name: 'MaquinaId', label: 'Máquina ID', type: 'text', required: true, maxlength: 50 },
                { name: 'Nombre', label: 'Nombre', type: 'text', required: false, maxlength: 100 },
                { name: 'Departamento', label: 'Departamento', type: 'text', required: false, maxlength: 50 }
            ],
            enableFilters: true,
            enableExcel: true,
            createTitle: 'Crear Máquina',
            editTitle: 'Editar Máquina',
            modalWidth: '500px',
            ...config
        });
    }

    getCreateFormHTML() {
        return `
            <div class="grid grid-cols-1 gap-3 text-left text-sm">
                <div>
                    <label class="block text-xs font-medium mb-1">Máquina ID *</label>
                    <input id="swal-MaquinaId" class="swal2-input" maxlength="50" placeholder="Ej: MC Coy 1, MC Coy 2">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Nombre</label>
                    <input id="swal-Nombre" class="swal2-input" maxlength="100" placeholder="Ej: MC Coy 1">
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
                    <label class="block text-xs font-medium mb-1">Máquina ID *</label>
                    <input id="swal-MaquinaId" class="swal2-input" maxlength="50" value="${item.MaquinaId || ''}" placeholder="Ej: MC Coy 1, MC Coy 2">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Nombre</label>
                    <input id="swal-Nombre" class="swal2-input" maxlength="100" value="${item.Nombre || ''}" placeholder="Ej: MC Coy 1">
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
                    <label class="block text-xs font-medium mb-1">Máquina ID</label>
                    <input id="filter-maquina-id" class="swal2-input" placeholder="Buscar por Máquina ID">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Nombre</label>
                    <input id="filter-nombre" class="swal2-input" placeholder="Buscar por Nombre">
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
            MaquinaId: document.getElementById('swal-MaquinaId')?.value?.trim() || '',
            Nombre: document.getElementById('swal-Nombre')?.value?.trim() || null,
            Departamento: document.getElementById('swal-Departamento')?.value?.trim() || null
        };
    }

    getFilterData() {
        return {
            maquina_id: document.getElementById('filter-maquina-id')?.value?.trim() || '',
            nombre: document.getElementById('filter-nombre')?.value?.trim() || '',
            departamento: document.getElementById('filter-departamento')?.value?.trim() || ''
        };
    }

    validateForm(data) {
        if (!data.MaquinaId || data.MaquinaId.trim() === '') {
            throw new Error('El Máquina ID es requerido');
        }
        return true;
    }
}

