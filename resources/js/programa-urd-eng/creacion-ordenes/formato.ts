/** Conversion y formato de valores. Puro: sin DOM, sin red. */

export const esNulo = (v: unknown): v is null | undefined => v === null || v === undefined

export const estaVacio = (v: unknown): boolean => esNulo(v) || String(v).trim() === ''

/** Acepta '1,234.5' porque asi viene de los inputs con separador de miles. */
export function aNumero(v: unknown, porDefecto: number): number
export function aNumero(v: unknown, porDefecto: null): number | null
export function aNumero(v: unknown, porDefecto: number | null = 0): number | null {
    if (esNulo(v)) return porDefecto

    const num = parseFloat(String(v).replace(/,/g, ''))

    return Number.isNaN(num) ? porDefecto : num
}

export const formatoNumero = (v: unknown, decimales = 2): string => {
    const n = aNumero(v, null)
    if (n === null) return ''

    return n.toLocaleString('es-MX', { minimumFractionDigits: decimales, maximumFractionDigits: decimales })
}

/**
 * Las fechas llegan del ERP como '2025-01-15', '2025-01-15 14:30:00' o ISO.
 * Se pinta solo la fecha; si no se puede interpretar, se devuelve tal cual.
 */
export const formatoFecha = (fecha: unknown): string => {
    if (estaVacio(fecha)) return ''

    let parte = String(fecha).trim()
    if (parte.includes(' ')) parte = parte.split(' ')[0] ?? parte
    if (parte.includes('T')) parte = parte.split('T')[0] ?? parte

    const d = new Date(`${parte}T00:00:00`)
    if (Number.isNaN(d.getTime())) return parte

    return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

/** Rizo/Pie con la capitalizacion que espera el servidor. */
export const normalizarTipo = (tipo: unknown): string => {
    const up = String(tipo ?? '').toUpperCase().trim()
    if (up === 'RIZO') return 'Rizo'
    if (up === 'PIE') return 'Pie'

    return typeof tipo === 'string' ? tipo : ''
}
