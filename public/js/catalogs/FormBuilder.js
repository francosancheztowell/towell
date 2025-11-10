/**
 * FormBuilder - Helper para construir formularios HTML de manera declarativa
 * Facilita la creación de formularios para modales de catálogos
 */
class FormBuilder {
    constructor() {
        this.fields = [];
    }

    /**
     * Agrega un campo al formulario
     * @param {Object} config - Configuración del campo
     * @returns {FormBuilder} this para chaining
     */
    addField(config) {
        this.fields.push({
            name: config.name,
            label: config.label,
            type: config.type || 'text',
            value: config.value || '',
            required: config.required || false,
            placeholder: config.placeholder || '',
            options: config.options || [],
            maxlength: config.maxlength,
            step: config.step,
            min: config.min,
            max: config.max,
            colSpan: config.colSpan || 1,
            ...config
        });
        return this;
    }

    /**
     * Construye el HTML del formulario
     * @param {string} prefix - Prefijo para los IDs (swal- o swal-edit-)
     * @param {number} columns - Número de columnas del grid
     * @returns {string} HTML del formulario
     */
    build(prefix = 'swal-', columns = 3) {
        let html = `<div class="grid grid-cols-${columns} gap-3 text-sm text-left">`;

        this.fields.forEach(field => {
            const colClass = field.colSpan ? `col-span-${field.colSpan}` : '';
            html += `<div class="${colClass}">`;
            html += this.renderField(field, prefix);
            html += `</div>`;
        });

        html += `</div>`;
        return html;
    }

    /**
     * Renderiza un campo individual
     * @param {Object} field - Configuración del campo
     * @param {string} prefix - Prefijo para el ID
     * @returns {string} HTML del campo
     */
    renderField(field, prefix) {
        const inputId = `${prefix}${field.name}`;
        const escapedValue = (field.value || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        const required = field.required ? 'required' : '';
        const requiredStar = field.required ? '<span class="text-red-500">*</span>' : '';

        let html = `
            <label class="block text-xs font-medium text-gray-700 mb-1">
                ${field.label} ${requiredStar}
            </label>
        `;

        if (field.type === 'select') {
            html += `<select id="${inputId}" name="${field.name}" class="swal2-input" ${required}>`;
            html += `<option value="">Seleccionar</option>`;
            field.options.forEach(option => {
                const optionValue = typeof option === 'object' ? option.value : option;
                const optionLabel = typeof option === 'object' ? option.label : option;
                const selected = optionValue == field.value ? 'selected' : '';
                html += `<option value="${optionValue}" ${selected}>${optionLabel}</option>`;
            });
            html += `</select>`;
        } else if (field.type === 'textarea') {
            html += `<textarea id="${inputId}" name="${field.name}" class="swal2-input" placeholder="${field.placeholder}" ${required} ${field.maxlength ? `maxlength="${field.maxlength}"` : ''}>${escapedValue}</textarea>`;
        } else if (field.type === 'range') {
            html += `
                <div class="flex items-center space-x-2">
                    <input type="range" id="${inputId}" name="${field.name}" 
                           class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                           min="${field.min || 0}" max="${field.max || 100}" 
                           value="${field.value || field.min || 0}" step="${field.step || 1}"
                           oninput="document.getElementById('${inputId}-value').textContent = this.value + '${field.suffix || ''}'">
                    <span id="${inputId}-value" class="text-xs font-bold text-blue-600 w-12">${field.value || field.min || 0}${field.suffix || ''}</span>
                </div>
            `;
        } else {
            const inputAttrs = [
                `id="${inputId}"`,
                `name="${field.name}"`,
                `type="${field.type}"`,
                `class="swal2-input"`,
                field.placeholder ? `placeholder="${field.placeholder}"` : '',
                field.value !== undefined && field.value !== null ? `value="${escapedValue}"` : '',
                required,
                field.maxlength ? `maxlength="${field.maxlength}"` : '',
                field.step ? `step="${field.step}"` : '',
                field.min !== undefined ? `min="${field.min}"` : '',
                field.max !== undefined ? `max="${field.max}"` : ''
            ].filter(attr => attr).join(' ');

            html += `<input ${inputAttrs}>`;
        }

        return html;
    }

    /**
     * Limpia todos los campos
     * @returns {FormBuilder} this para chaining
     */
    clear() {
        this.fields = [];
        return this;
    }
}

// Exportar
if (typeof window !== 'undefined') {
    window.FormBuilder = FormBuilder;
}

