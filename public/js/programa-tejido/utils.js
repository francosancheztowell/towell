/**
 * Utilidades compartidas para el módulo de Programa de Tejido
 */
window.ProgramaTejidoUtils = {

    /**
     * Formatear fecha para input datetime-local
     * @param {Date|string} fecha - Fecha a formatear
     * @returns {string} Fecha formateada para input datetime-local (YYYY-MM-DDTHH:MM:SS)
     */
    formatearFechaParaInput(fecha) {
        if (!fecha) return '';

        const fechaObj = fecha instanceof Date ? fecha : new Date(fecha);

        if (isNaN(fechaObj.getTime())) {
            return '';
        }

        const year = fechaObj.getFullYear();
        const month = String(fechaObj.getMonth() + 1).padStart(2, '0');
        const day = String(fechaObj.getDate()).padStart(2, '0');
        const hours = String(fechaObj.getHours()).padStart(2, '0');
        const minutes = String(fechaObj.getMinutes()).padStart(2, '0');
        const seconds = String(fechaObj.getSeconds()).padStart(2, '0');

        return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}`;
    },

    /**
     * Parsear fecha flexible desde varios formatos
     * @param {string} str - String de fecha a parsear
     * @returns {Date|null} Objeto Date o null si no es válida
     */
    parseDateFlexible(str) {
        if (!str) return null;
        let s = String(str).trim();

        // Quitar milisegundos tipo .000
        s = s.replace(/\.\d{3}$/,'');

        // dd/mm/yyyy -> yyyy-mm-dd
        if (/^\d{2}\/\d{2}\/\d{4}/.test(s)) {
            const [d,m,y] = s.split(/[\/\s]/);
            const time = s.split(' ')[1] || '00:00:00';
            s = `${y}-${m}-${d}T${time}`;
        }

        // yyyy-mm-dd hh:mm:ss -> yyyy-mm-ddThh:mm:ss
        if (/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/.test(s)) {
            s = s.replace(' ', 'T');
        }

        // yyyy-mm-dd -> agregar hora por defecto
        if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
            s += 'T00:00:00';
        }

        const d = new Date(s);
        return isNaN(d.getTime()) ? null : d;
    },

    /**
     * Formatear fecha a YYYY-MM-DD HH:MM:SS
     * @param {Date} d - Fecha a formatear
     * @returns {string} Fecha formateada
     */
    formatYmdHms(d) {
        if (!(d instanceof Date)) return '';
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    },

    /**
     * Formatear número decimal
     * @param {*} valor - Valor a formatear
     * @returns {*} Valor formateado o el valor original si no es número
     */
    formatearDecimal(valor) {
        if (valor === null || valor === undefined || valor === '') return valor;

        const numero = parseFloat(valor);
        if (isNaN(numero)) return valor;

        return parseFloat(numero.toFixed(2)).toString();
    },

    /**
     * Hacer una petición fetch con CSRF token
     * @param {string} url - URL de la petición
     * @param {Object} options - Opciones de fetch
     * @returns {Promise} Promesa con la respuesta
     */
    async fetchConCSRF(url, options = {}) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const defaultHeaders = {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        };

        options.headers = { ...defaultHeaders, ...(options.headers || {}) };

        const response = await fetch(url, options);

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(errorText || `HTTP ${response.status}`);
        }

        return response.json();
    },

    /**
     * Mostrar alerta con SweetAlert o fallback a alert nativo
     * @param {string} tipo - 'success', 'error', 'warning', 'info'
     * @param {string} titulo - Título de la alerta
     * @param {string} mensaje - Mensaje de la alerta
     * @param {Object} opciones - Opciones adicionales para SweetAlert
     */
    mostrarAlerta(tipo, titulo, mensaje = '', opciones = {}) {
        if (window.Swal) {
            Swal.fire({
                icon: tipo,
                title: titulo,
                text: mensaje,
                ...opciones
            });
        } else {
            alert(`${titulo}${mensaje ? ': ' + mensaje : ''}`);
        }
    },

    /**
     * Mostrar loading con SweetAlert
     * @param {string} mensaje - Mensaje a mostrar
     */
    mostrarLoading(mensaje = 'Cargando...') {
        if (window.Swal) {
            Swal.fire({
                title: mensaje,
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });
        }
    },

    /**
     * Cerrar loading de SweetAlert
     */
    cerrarLoading() {
        if (window.Swal) {
            Swal.close();
        }
    },

    /**
     * Debounce para funciones
     * @param {Function} func - Función a ejecutar
     * @param {number} wait - Tiempo de espera en ms
     * @returns {Function} Función con debounce
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Limpiar un campo del formulario
     * @param {string} fieldId - ID del campo
     */
    limpiarCampo(fieldId) {
        const elemento = document.getElementById(fieldId);
        if (elemento) {
            elemento.value = '';
            // Remover cada clase individualmente
            const clases = ProgramaTejidoConfig.ui.clasesInputSeleccionado.split(' ');
            clases.forEach(clase => {
                if (clase) elemento.classList.remove(clase);
            });
        }
    },

    /**
     * Establecer valor de un campo y marcarlo como seleccionado
     * @param {string} fieldId - ID del campo
     * @param {*} valor - Valor a establecer
     * @param {boolean} habilitarCampo - Si se debe habilitar el campo
     */
    establecerValorCampo(fieldId, valor, habilitarCampo = true) {
        const elemento = document.getElementById(fieldId);
        if (!elemento) return;

        if (valor !== undefined && valor !== null && valor !== '') {
            elemento.value = valor;

            if (habilitarCampo) {
                elemento.disabled = false;
            }
            // Agregar cada clase individualmente
            const clases = ProgramaTejidoConfig.ui.clasesInputSeleccionado.split(' ');
            clases.forEach(clase => {
                if (clase) elemento.classList.add(clase);
            });

            // Si es un select, disparar el evento change
            if (elemento.tagName === 'SELECT') {
                elemento.dispatchEvent(new Event('change'));
            }
        }
    },

    /**
     * Obtener valor de un campo
     * @param {string} fieldId - ID del campo
     * @param {*} valorPorDefecto - Valor por defecto si el campo está vacío
     * @returns {*} Valor del campo
     */
    obtenerValorCampo(fieldId, valorPorDefecto = '') {
        const elemento = document.getElementById(fieldId);
        return elemento ? (elemento.value || valorPorDefecto) : valorPorDefecto;
    },

    /**
     * Verificar si un campo está habilitado
     * @param {string} fieldId - ID del campo
     * @returns {boolean} True si está habilitado
     */
    esCampoHabilitado(fieldId) {
        const elemento = document.getElementById(fieldId);
        return elemento ? !elemento.disabled : false;
    },

    /**
     * Agregar clases CSS a un elemento
     * @param {HTMLElement} elemento - Elemento DOM
     * @param {string|Array} clases - Clases a agregar (string con espacios o array)
     * @private
     */
    _agregarClases(elemento, clases) {
        if (!elemento) return;

        if (typeof clases === 'string') {
            // Si es string, dividir por espacios
            const clasesArray = clases.split(' ').filter(c => c);
            clasesArray.forEach(clase => elemento.classList.add(clase));
        } else if (Array.isArray(clases)) {
            // Si es array, agregar cada una
            clases.forEach(clase => {
                if (clase) elemento.classList.add(clase);
            });
        }
    },

    /**
     * Remover clases CSS de un elemento
     * @param {HTMLElement} elemento - Elemento DOM
     * @param {string|Array} clases - Clases a remover (string con espacios o array)
     * @private
     */
    _removerClases(elemento, clases) {
        if (!elemento) return;

        if (typeof clases === 'string') {
            // Si es string, dividir por espacios
            const clasesArray = clases.split(' ').filter(c => c);
            clasesArray.forEach(clase => elemento.classList.remove(clase));
        } else if (Array.isArray(clases)) {
            // Si es array, remover cada una
            clases.forEach(clase => {
                if (clase) elemento.classList.remove(clase);
            });
        }
    }
};
