// Ramas claude/mejora-dedup-ts (4c776396) y claude/mejora-ts-ventas: los formateadores locales
// de Trazabilidad (matrix-detail, production-detail), Liberar órdenes (calculos.ts), Ventas
// Compara (dashboard) y Ventas históricas se reemplazaron por utils/format.formatNumber y
// escapeHtml. Aquí se compara la salida nueva contra la implementación vieja, copiada tal cual
// del código borrado, para los valores que esas pantallas pintan.
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { escapeHtml, formatNumber } from '../../resources/js/utils/format.ts'

const VALORES = [0, 1, -1, 0.5, 1.005, 2.345, 12.5, 999.995, 1234.5678, -9876.54321, 1e6, 123456789.987, 0.004, -0.004]

// --- Viejos (copiados del diff) ---
const viejoTraza = (value, decimals) => Number(value).toLocaleString('es-MX', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })
const viejoLiberar = (value, decimals) => {
  const rounded = decimals > 0 ? Number(value.toFixed(decimals)) : Math.round(value)
  return rounded.toLocaleString('es-MX', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })
}
const viejoDashboard = (value) => new Intl.NumberFormat('es-MX', { maximumFractionDigits: 0 }).format(value || 0)
const viejoHistoricas = (value, decimals = 2) => new Intl.NumberFormat('es-MX', {
  minimumFractionDigits: decimals, maximumFractionDigits: decimals,
}).format(Number.isFinite(value) ? value : 0)
const viejoEscape = (value) => String(value ?? '').replace(/[&<>'"]/g, (c) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
}[c]))

// --- Nuevos (como los usan las pantallas) ---
const nuevoLiberar = (value, decimals) => {
  const rounded = decimals > 0 ? Number(value.toFixed(decimals)) : Math.round(value)
  return formatNumber(rounded, decimals)
}
const nuevoDashboard = (value) => formatNumber(value || 0, 0)
const nuevoHistoricas = (value, decimals = 2) => formatNumber(Number.isFinite(value) ? value : 0, decimals)

test('Trazabilidad: formatNumber(v, d) = toLocaleString es-MX con d decimales (0, 1, 2)', () => {
  for (const d of [0, 1, 2]) for (const v of VALORES) assert.equal(formatNumber(v, d), viejoTraza(v, d), `v=${v} d=${d}`)
})

test('Liberar órdenes: el span numérico pinta lo mismo que antes', () => {
  for (const d of [0, 2, 3]) for (const v of VALORES.filter((x) => x >= 0)) assert.equal(nuevoLiberar(v, d), viejoLiberar(v, d), `v=${v} d=${d}`)
})

test('Ventas Compara: enteros con miles y null/undefined/NaN → "0"', () => {
  for (const v of [...VALORES, null, undefined, Number.NaN]) assert.equal(nuevoDashboard(v), viejoDashboard(v), `v=${v}`)
  assert.equal(nuevoDashboard(1234567.6), '1,234,568')
})

test('Ventas históricas: decimales fijos y no finitos → 0 con sus decimales', () => {
  for (const d of [0, 2]) for (const v of [...VALORES, Number.NaN, Number.POSITIVE_INFINITY]) assert.equal(nuevoHistoricas(v, d), viejoHistoricas(v, d), `v=${v} d=${d}`)
  assert.equal(nuevoHistoricas(Number.NaN), '0.00')
})

test('escapeHtml compartido: misma neutralización que el local (solo cambia &#039; por &#39;)', () => {
  const casos = ['<img src=x onerror="alert(1)">', "O'Neil & Hijos", 'A>B<C', '', null, undefined, 0, 'Ñandú "TOWEL"']
  for (const c of casos) assert.equal(escapeHtml(c), viejoEscape(c).replaceAll('&#039;', '&#39;'), String(c))
  // 0 se pinta como "0", no como vacío (cantidades en etiquetas de Ventas).
  assert.equal(escapeHtml(0), '0')
})
