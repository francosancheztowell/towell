/**
 * ==========================================
 * CORE CATALOG JS - Funciones Reutilizables
 * ==========================================
 *
 * Este archivo contiene funciones comunes que se usan en múltiples
 * vistas de catálogos para evitar duplicación de código.
 *
 * Uso:
 * <script src="{{ asset('js/catalog-core.js') }}"></script>
 */

(function(window) {
    'use strict';

    /**
     * =============================
     * Funciones de Botones Globales
     * =============================
     */
    window.CatalogCore = {
        /**
         * Habilitar botones de editar y eliminar
         * @param {Object} options - Opciones de configuración
         */
        enableButtons: function(options = {}) {
            const config = {
                editBtnId: options.editBtnId || 'btn-editar',
                deleteBtnId: options.deleteBtnId || 'btn-eliminar',
                editClass: options.editClass || 'inline-flex items-center px-3 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg text-sm font-medium',
                deleteClass: options.deleteClass || 'inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium',
                ...options
            };

            const editBtn = document.getElementById(config.editBtnId);
            const deleteBtn = document.getElementById(config.deleteBtnId);

            if (editBtn) {
                editBtn.disabled = false;
                editBtn.className = config.editClass;
                if (config.onEditClick) {
                    editBtn.onclick = config.onEditClick;
                }
            }

            if (deleteBtn) {
                deleteBtn.disabled = false;
                deleteBtn.className = config.deleteClass;
                if (config.onDeleteClick) {
                    deleteBtn.onclick = config.onDeleteClick;
                }
            }
        },

        /**
         * Deshabilitar botones de editar y eliminar
         */
        disableButtons: function(options = {}) {
            const config = {
                editBtnId: options.editBtnId || 'btn-editar',
                deleteBtnId: options.deleteBtnId || 'btn-eliminar',
                disabledClass: options.disabledClass || 'inline-flex items-center px-3 py-2 bg-gray-400 text-gray-200 rounded-lg text-sm font-medium cursor-not-allowed',
                ...options
            };

            const editBtn = document.getElementById(config.editBtnId);
            const deleteBtn = document.getElementById(config.deleteBtnId);

            if (editBtn) {
                editBtn.disabled = true;
                editBtn.className = config.disabledClass;
                editBtn.onclick = null;
            }

            if (deleteBtn) {
                deleteBtn.disabled = true;
                deleteBtn.className = config.disabledClass;
                deleteBtn.onclick = null;
            }
        },

        /**
         * Seleccionar una fila en la tabla
         * @param {HTMLElement} row - Elemento tr seleccionado
         * @param {string|number} id - ID del registro
         * @param {Object} options - Opciones de configuración
         */
        selectRow: function(row, id, options = {}) {
            const config = {
                tbodyId: options.tbodyId || 'catalog-body',
                selectedClass: options.selectedClass || 'bg-blue-500 text-white',
                normalClass: options.normalClass || 'hover:bg-blue-50',
                onSelect: options.onSelect || null,
                ...options
            };

            // Remover selección anterior
            document.querySelectorAll(`#${config.tbodyId} tr`).forEach(r => {
                r.classList.remove(...config.selectedClass.split(' '));
                r.classList.add(...config.normalClass.split(' '));
            });

            // Seleccionar fila actual
            row.classList.remove(...config.normalClass.split(' '));
            row.classList.add(...config.selectedClass.split(' '));

            // Callback personalizado si existe
            if (config.onSelect) {
                config.onSelect(row, id);
            }

            // Habilitar botones
            this.enableButtons(config);
        },

        /**
         * Toast notificaciones reutilizable
         * @param {string} message - Mensaje a mostrar
         * @param {string} type - Tipo: success, error, warning, info
         * @param {number} duration - Duración en ms
         */
        showToast: function(message, type = 'info', duration = 3500) {
            const colors = {
                success: 'bg-green-600',
                error: 'bg-red-600',
                warning: 'bg-yellow-600',
                info: 'bg-blue-600'
            };

            let toast = document.getElementById('toast-notification');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'toast-notification';
                toast.className = 'fixed top-4 right-4 z-50 max-w-sm w-full';
                document.body.appendChild(toast);
            }

            toast.innerHTML = `
                <div class="${colors[type] || colors.info} text-white px-4 py-3 rounded-md shadow-lg transition-all" id="toast-content">
                    <div class="flex items-center justify-between gap-4">
                        <div class="text-sm">${message}</div>
                        <button onclick="document.getElementById('toast-notification').remove()" class="opacity-80 hover:opacity-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            `;

            setTimeout(() => {
                const t = document.getElementById('toast-notification');
                if (t) t.remove();
            }, duration);
        },

        /**
         * Confirmación de eliminación reutilizable
         * @param {string} message - Mensaje de confirmación
         * @param {Function} onConfirm - Callback cuando se confirma
         */
        confirmDelete: function(message, onConfirm) {
            if (typeof Swal === 'undefined') {
                if (confirm(message)) {
                    onConfirm();
                }
                return;
            }

            Swal.fire({
                title: '¿Estás seguro?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed && onConfirm) {
                    onConfirm();
                }
            });
        },

        /**
         * Cargar datos desde API
         * @param {string} url - URL del endpoint
         * @param {Object} options - Opciones de configuración
         */
        fetchData: async function(url, options = {}) {
            const config = {
                method: options.method || 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    ...options.headers
                },
                ...options
            };

            try {
                const response = await fetch(url, config);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return await response.json();
            } catch (error) {
                this.showToast('Error de conexión: ' + error.message, 'error');
                throw error;
            }
        },

        /**
         * Repoblar select con opciones
         * @param {HTMLElement} selectEl - Elemento select
         * @param {Array} opciones - Array de opciones
         * @param {string} selectedValue - Valor seleccionado
         */
        repoblarSelect: function(selectEl, opciones, selectedValue = '') {
            if (!selectEl) return;

            selectEl.innerHTML = '';
            const def = document.createElement('option');
            def.value = '';
            def.textContent = 'Seleccionar';
            selectEl.appendChild(def);

            (opciones || []).forEach(v => {
                const opt = document.createElement('option');
                opt.value = String(v);
                opt.textContent = v;
                selectEl.appendChild(opt);
            });

            if (selectedValue !== undefined && selectedValue !== null && selectedValue !== '') {
                selectEl.value = String(selectedValue);
            }
        },

        /**
         * Formatear número con comas
         */
        formatNumber: function(num) {
            return String(num).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },

        /**
         * Parsear fecha DD/MM/YYYY a Date
         */
        parseDate: function(str) {
            if (!str) return null;
            const m = str.trim().match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
            if (!m) return null;
            let [, d, mo, y] = m;
            if (y.length === 2) y = +y >= 70 ? '19'+y : '20'+y;
            const dt = new Date(+y, +mo-1, +d);
            return isNaN(dt.getTime()) ? null : dt;
        }
    };

    // Helper global para compatibilidad
    window.showToast = window.CatalogCore.showToast.bind(window.CatalogCore);
    window.enableButtons = window.CatalogCore.enableButtons.bind(window.CatalogCore);
    window.disableButtons = window.CatalogCore.disableButtons.bind(window.CatalogCore);

})(window);

