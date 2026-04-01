/**
 * Formateadores para celdas de la tabla programa-tejido.
 * Extraídos de main.blade.php para reducir deuda técnica.
 */
window.PTFormatters = {
    /** Columnas numéricas con 2 decimales */
    DD_NUM2_COLS: new Set([
        'DiasEficiencia','ProdKgDia','StdDia','ProdKgDia2','StdToaHra','HorasProd','StdHrsEfect','DiasJornada','PesoGRM2',
        'TotalPedido','LargoCrudo','Luchaje','PesoCrudo'
    ]),

    /** Columnas de fecha y hora */
    DD_DATE_TIME_COLS: new Set(['FechaInicio','FechaFinal','EntregaCte']),

    /** Columnas de fecha sola */
    DD_DATE_ONLY_COLS: new Set(['EntregaProduc','EntregaPT','ProgramarProd','Programado']),

    /**
     * Formatea un valor como fecha y hora (dd/mm/yyyy HH:mm).
     * @param {string|null} raw - Valor crudo de la celda
     * @returns {string}
     */
    formatDateTime(raw) {
        if (typeof window.formatDateTimeDisplay === 'function') return window.formatDateTimeDisplay(raw);
        return raw ? String(raw) : '';
    },

    /**
     * Formatea un valor como fecha sola (dd/mm/yyyy).
     * @param {string|null} raw - Valor crudo de la celda
     * @returns {string}
     */
    formatDateOnly(raw) {
        if (typeof window.formatDateOnlyDisplay === 'function') return window.formatDateOnlyDisplay(raw);
        if (typeof window.formatDateDisplay === 'function') return window.formatDateDisplay(raw);
        return raw ? String(raw) : '';
    },

    /**
     * Formatea un valor numérico con 2 decimales (locale es-MX).
     * @param {string|number|null} raw - Valor crudo de la celda
     * @returns {string}
     */
    formatNumber(raw) {
        if (typeof window.formatNumber2 === 'function') return window.formatNumber2(raw);
        const n = Number(raw);
        if (!Number.isFinite(n)) return raw == null ? '' : String(raw);
        return n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    /**
     * Establece el valor de una celda.
     * @param {HTMLElement} cell - Celda TD
     * @param {string} display - HTML a mostrar
     * @param {*} rawValue - Valor crudo para dataset
     */
    setCellValue(cell, display, rawValue) {
        if (!cell) return;
        cell.innerHTML = display ?? '';
        if (rawValue === null || rawValue === undefined) {
            delete cell.dataset.value;
        } else {
            cell.dataset.value = String(rawValue);
        }
    },

    /**
     * Formatea una celda según el tipo de columna.
     * @param {string} column - Nombre de la columna
     * @param {*} raw - Valor crudo
     * @returns {{display: string, rawValue: *}}
     */
    formatCell(column, raw) {
        if (column === 'EnProceso') {
            const checked = (raw == 1 || raw === true) ? 'checked' : '';
            return {
                display: `<input type="checkbox" ${checked} disabled class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">`,
                rawValue: raw ?? 0
            };
        }

        if (column === 'Ultimo') {
            const sv = String(raw ?? '').trim().toUpperCase();
            const isUltimo = (sv === 'UL' || sv === '1');
            return { display: isUltimo ? '<strong>ULTIMO</strong>' : '', rawValue: raw ?? '' };
        }

        if (column === 'CambioHilo') {
            const isZero = (raw == 0 || raw === '0' || raw === null || raw === undefined);
            return { display: isZero ? '' : String(raw), rawValue: raw ?? '' };
        }

        if (column === 'EficienciaSTD') {
            const n = Number(raw);
            if (!Number.isFinite(n)) return { display: raw == null ? '' : String(raw), rawValue: raw ?? '' };
            return { display: `${Math.round(n * 100)}%`, rawValue: raw };
        }

        if (this.DD_DATE_TIME_COLS.has(column)) {
            return { display: raw ? this.formatDateTime(raw) : '', rawValue: raw ?? '' };
        }

        if (this.DD_DATE_ONLY_COLS.has(column) || column.startsWith('Entrega')) {
            return { display: raw ? this.formatDateOnly(raw) : '', rawValue: raw ?? '' };
        }

        if (this.DD_NUM2_COLS.has(column)) {
            return { display: this.formatNumber(raw), rawValue: raw ?? '' };
        }

        if (typeof window.uiInlineEditableFields !== 'undefined' && window.uiInlineEditableFields[column]?.displayFormatter) {
            return {
                display: window.uiInlineEditableFields[column].displayFormatter(raw),
                rawValue: raw ?? ''
            };
        }

        return { display: raw == null ? '' : String(raw), rawValue: raw ?? '' };
    }
};
