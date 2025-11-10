/**
 * TelaresCatalog - Implementación específica para catálogo de telares
 */
class TelaresCatalog extends CatalogBase {
    constructor(config) {
        super({
            tableBodyId: 'telares-body',
            route: 'telares',
            idField: 'uid',
            fields: [
                { name: 'SalonTejidoId', label: 'Salón', type: 'text', required: true, maxlength: 20 },
                { name: 'NoTelarId', label: 'Telar', type: 'text', required: true, maxlength: 10 },
                { name: 'Nombre', label: 'Nombre', type: 'text', required: true, maxlength: 30 },
                { name: 'Grupo', label: 'Grupo', type: 'text', required: false, maxlength: 30 }
            ],
            enableFilters: true,
            enableExcel: true,
            createTitle: 'Crear Telar',
            editTitle: 'Editar Telar',
            modalWidth: '500px',
            ...config
        });
    }

    /**
     * Genera el nombre automáticamente basado en salón y telar
     */
    nameFrom(salon, telar) {
        const up = String(salon || '').toUpperCase().trim();
        const pref = up.includes('JACQUARD') ? 'JAC' : (up.includes('SMITH') ? 'Smith' : up.slice(0, 3).toUpperCase());
        return `${pref} ${telar || ''}`.trim();
    }

    getCreateFormHTML() {
        return `
            <div class="grid grid-cols-2 gap-3 text-left text-sm">
                <div>
                    <label class="block text-xs font-medium mb-1">Salón *</label>
                    <input id="swal-SalonTejidoId" class="swal2-input" maxlength="20" placeholder="Jacquard / Smith">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Telar *</label>
                    <input id="swal-NoTelarId" class="swal2-input" maxlength="10" placeholder="200, 300">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium mb-1">Nombre *</label>
                    <input id="swal-Nombre" class="swal2-input" maxlength="30" placeholder="JAC 200">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium mb-1">Grupo</label>
                    <input id="swal-Grupo" class="swal2-input" maxlength="30" placeholder="Prueba / Jacquard Smith">
                </div>
            </div>
        `;
    }

    getEditFormHTML(data) {
        const escape = (str) => (str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        
        return `
            <div class="grid grid-cols-2 gap-3 text-left text-sm">
                <div>
                    <label class="block text-xs font-medium mb-1">Salón *</label>
                    <input id="swal-edit-SalonTejidoId" class="swal2-input" maxlength="20" value="${escape(data.salon)}">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Telar *</label>
                    <input id="swal-edit-NoTelarId" class="swal2-input" maxlength="10" value="${escape(data.telar)}">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium mb-1">Nombre *</label>
                    <input id="swal-edit-Nombre" class="swal2-input" maxlength="30" value="${escape(data.nombre)}">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium mb-1">Grupo</label>
                    <input id="swal-edit-Grupo" class="swal2-input" maxlength="30" value="${escape(data.grupo)}">
                </div>
            </div>
        `;
    }

    onCreateFormOpen() {
        const salon = document.getElementById('swal-SalonTejidoId');
        const telar = document.getElementById('swal-NoTelarId');
        const nombre = document.getElementById('swal-Nombre');
        let touched = false;

        const auto = () => {
            if (!touched && nombre) {
                nombre.value = this.nameFrom(salon.value, telar.value);
            }
        };

        if (salon) salon.addEventListener('input', auto);
        if (telar) telar.addEventListener('input', auto);
        if (nombre) nombre.addEventListener('input', () => touched = true);
        auto();
    }

    onEditFormOpen(rowData) {
        const salon = document.getElementById('swal-edit-SalonTejidoId');
        const telar = document.getElementById('swal-edit-NoTelarId');
        const nombre = document.getElementById('swal-edit-Nombre');
        let touched = false;

        const auto = () => {
            if (!touched && nombre) {
                nombre.value = this.nameFrom(salon.value, telar.value);
            }
        };

        if (salon) salon.addEventListener('input', auto);
        if (telar) telar.addEventListener('input', auto);
        if (nombre) nombre.addEventListener('input', () => touched = true);
    }

    validateCreateData(data) {
        if (!data.SalonTejidoId || !data.NoTelarId) {
            return { valid: false, message: 'Completa Salón y Telar' };
        }
        if (!data.Nombre) {
            data.Nombre = this.nameFrom(data.SalonTejidoId, data.NoTelarId);
        }
        return { valid: true };
    }

    validateEditData(data) {
        if (!data.SalonTejidoId || !data.NoTelarId) {
            return { valid: false, message: 'Completa Salón y Telar' };
        }
        if (!data.Nombre) {
            data.Nombre = this.nameFrom(data.SalonTejidoId, data.NoTelarId);
        }
        return { valid: true };
    }

    renderRow(item) {
        return `
            <td class="py-2 px-4 border-b">${item.SalonTejidoId || ''}</td>
            <td class="py-2 px-4 border-b">${item.NoTelarId || ''}</td>
            <td class="py-2 px-4 border-b">${item.Nombre || ''}</td>
            <td class="py-2 px-4 border-b">${item.Grupo || 'N/A'}</td>
        `;
    }

    getRowId(row) {
        return row.dataset.uid || row.getAttribute('data-uid');
    }

    getRowData(row) {
        return {
            uid: row.getAttribute('data-uid') || '',
            salon: row.getAttribute('data-salon') || '',
            telar: row.getAttribute('data-telar') || '',
            nombre: row.getAttribute('data-nombre') || '',
            grupo: row.getAttribute('data-grupo') || ''
        };
    }

    getDeleteConfirmMessage(rowData) {
        const salon = rowData.salon || '';
        const telar = rowData.telar || '';
        const nombre = rowData.nombre || '';
        return `
            <div class="text-left">
                <p><strong>Salón:</strong> ${salon}</p>
                <p><strong>Telar:</strong> ${telar}</p>
                <p><strong>Nombre:</strong> ${nombre}</p>
            </div>
        `;
    }

    extractFormData(action) {
        const prefix = action === 'create' ? 'swal-' : 'swal-edit-';
        return {
            SalonTejidoId: document.getElementById(`${prefix}SalonTejidoId`)?.value.trim() || '',
            NoTelarId: document.getElementById(`${prefix}NoTelarId`)?.value.trim() || '',
            Nombre: document.getElementById(`${prefix}Nombre`)?.value.trim() || '',
            Grupo: document.getElementById(`${prefix}Grupo`)?.value.trim() || ''
        };
    }

    getFiltersFormHTML() {
        return `
            <div class="grid grid-cols-2 gap-3 text-left text-sm">
                <div>
                    <label class="block text-xs font-medium mb-1">Salón</label>
                    <input id="swal-salon-filter" class="swal2-input" placeholder="Jacquard / Smith" value="${this.state.filters.salon || ''}">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Telar</label>
                    <input id="swal-telar-filter" class="swal2-input" placeholder="200" value="${this.state.filters.telar || ''}">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium mb-1">Nombre</label>
                    <input id="swal-nombre-filter" class="swal2-input" placeholder="JAC 200" value="${this.state.filters.nombre || ''}">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium mb-1">Grupo</label>
                    <input id="swal-grupo-filter" class="swal2-input" placeholder="Prueba" value="${this.state.filters.grupo || ''}">
                </div>
            </div>
        `;
    }

    extractFiltersData() {
        return {
            salon: document.getElementById('swal-salon-filter')?.value.trim().toLowerCase() || '',
            telar: document.getElementById('swal-telar-filter')?.value.trim().toLowerCase() || '',
            nombre: document.getElementById('swal-nombre-filter')?.value.trim().toLowerCase() || '',
            grupo: document.getElementById('swal-grupo-filter')?.value.trim().toLowerCase() || ''
        };
    }

    matchesFilters(item, filters) {
        const salon = String(item.SalonTejidoId || '').toLowerCase();
        const telar = String(item.NoTelarId || '').toLowerCase();
        const nombre = String(item.Nombre || '').toLowerCase();
        const grupo = String(item.Grupo || '').toLowerCase();

        if (filters.salon && !salon.includes(filters.salon)) return false;
        if (filters.telar && !telar.includes(filters.telar)) return false;
        if (filters.nombre && !nombre.includes(filters.nombre)) return false;
        if (filters.grupo && !grupo.includes(filters.grupo)) return false;

        return true;
    }

    async performCreate(data) {
        Swal.fire({
            title: 'Creando...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const response = await fetch('/planeacion/telares', {
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
                title: '¡Creado!',
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
            const response = await fetch(`/planeacion/telares/${encodeURIComponent(id)}`, {
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

            // Actualizar la fila en la UI sin recargar
            if (this.state.selectedRow) {
                this.state.selectedRow.dataset.salon = data.SalonTejidoId;
                this.state.selectedRow.dataset.telar = data.NoTelarId;
                this.state.selectedRow.dataset.nombre = data.Nombre;
                this.state.selectedRow.dataset.grupo = data.Grupo || '';
                this.state.selectedRow.dataset.uid = `${data.SalonTejidoId}_${data.NoTelarId}`;

                const tds = this.state.selectedRow.querySelectorAll('td');
                if (tds[0]) tds[0].textContent = data.SalonTejidoId;
                if (tds[1]) tds[1].textContent = data.NoTelarId;
                if (tds[2]) tds[2].textContent = data.Nombre;
                if (tds[3]) tds[3].textContent = data.Grupo || 'N/A';
            }

            Swal.fire({
                icon: 'success',
                title: '¡Actualizado!',
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
            const response = await fetch(`/planeacion/telares/${encodeURIComponent(id)}`, {
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
                title: '¡Eliminado!',
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
}

// Exportar
if (typeof window !== 'undefined') {
    window.TelaresCatalog = TelaresCatalog;
}

