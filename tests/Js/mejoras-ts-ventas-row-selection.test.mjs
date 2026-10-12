// Rama claude/mejora-ts-ventas (a993b662): row-selection.js → .ts. La selección de fila de las
// tablas de Ventas sobrevive a los re-pintados por innerHTML (MutationObserver) y se identifica
// por data-row-key o por "texto de la primera celda#aparición". Se fija esa conducta.
import assert from 'node:assert/strict'
import { test } from 'node:test'

// ---------- DOM mínimo: contenedor > tbody > tr > td ----------
const observers = []
globalThis.MutationObserver = class {
  constructor(fn) { this.fn = fn }
  observe(target) { observers.push({ target, fn: this.fn }) }
}
const mutar = (container) => observers.filter((o) => o.target === container).forEach((o) => o.fn([]))

class ClassList {
  constructor() { this.s = new Set() }
  toggle(c, force) { const on = force ?? !this.s.has(c); if (on) this.s.add(c); else this.s.delete(c); return on }
  contains(c) { return this.s.has(c) }
}

function fila(texto, { rowKey, vacia = false } = {}) {
  const celda = { textContent: `  ${texto}  `, parent: null }
  const tr = {
    dataset: rowKey ? { rowKey } : {},
    cells: [celda],
    classList: new ClassList(),
    vacia,
    querySelector: (sel) => (sel === '.pvoc-empty' && vacia ? {} : null),
    closest: (sel) => (sel === 'tbody tr' ? tr : null),
  }
  celda.closest = (sel) => (sel === 'tbody tr' ? tr : null)
  return tr
}

function contenedor(filas) {
  const listeners = {}
  const c = {
    dataset: {},
    filas,
    addEventListener: (t, fn) => { (listeners[t] ??= []).push(fn) },
    querySelectorAll: (sel) => (sel === 'tbody tr' ? c.filas : []),
    contains: (el) => c.filas.includes(el),
    click: (el) => (listeners.click ?? []).forEach((fn) => fn({ target: el })),
    listenerCount: () => (listeners.click ?? []).length,
  }
  return c
}

const { bindRowSelection, clearRowSelection } = await import('../../resources/js/ventas/row-selection.ts')
const marcadas = (c) => c.filas.map((r) => r.classList.contains('is-selected'))

test('clic marca solo esa fila; clic en otra mueve la marca', () => {
  const c = contenedor([fila('Towel'), fila('Textil'), fila('Total')])
  bindRowSelection(c)
  c.click(c.filas[1].cells[0])
  assert.deepEqual(marcadas(c), [false, true, false])
  c.click(c.filas[0])
  assert.deepEqual(marcadas(c), [true, false, false])
})

test('textos repetidos: la clave es texto#aparición, así que no se marcan los gemelos', () => {
  const c = contenedor([fila('CE'), fila('RS'), fila('CE')])
  bindRowSelection(c)
  c.click(c.filas[2])
  assert.equal(c.dataset.selectedRow, 'CE#1')
  assert.deepEqual(marcadas(c), [false, false, true])
})

test('data-row-key gana sobre el texto (filas de árbol con texto repetido entre ramas)', () => {
  const c = contenedor([fila('2025', { rowKey: '/TOWEL/2025' }), fila('2025', { rowKey: '/TEXTIL/2025' })])
  bindRowSelection(c)
  c.click(c.filas[1])
  assert.equal(c.dataset.selectedRow, '/TEXTIL/2025')
  assert.deepEqual(marcadas(c), [false, true])
})

test('la selección se re-aplica tras re-pintar (innerHTML nuevo, mismas claves)', () => {
  const c = contenedor([fila('A'), fila('B')])
  bindRowSelection(c)
  c.click(c.filas[1])
  c.filas = [fila('A'), fila('B'), fila('C')]
  mutar(c)
  assert.deepEqual(marcadas(c), [false, true, false])
})

test('la fila "No hay datos" (.pvoc-empty) no se puede seleccionar', () => {
  const c = contenedor([fila('No hay datos', { vacia: true })])
  bindRowSelection(c)
  c.click(c.filas[0])
  assert.equal(c.dataset.selectedRow, undefined)
  assert.deepEqual(marcadas(c), [false])
})

test('clearRowSelection quita la marca; bind dos veces no duplica listeners; null no truena', () => {
  const c = contenedor([fila('A'), fila('B')])
  bindRowSelection(c)
  bindRowSelection(c)
  assert.equal(c.listenerCount(), 1)
  c.click(c.filas[0])
  clearRowSelection(c)
  assert.equal(c.dataset.selectedRow, undefined)
  assert.deepEqual(marcadas(c), [false, false])
  assert.doesNotThrow(() => { bindRowSelection(null); clearRowSelection(null) })
})
