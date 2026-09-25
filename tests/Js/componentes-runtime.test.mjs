import assert from 'node:assert/strict'
import test from 'node:test'

// DOM mínimo propio: el de utils-fake-dom.mjs no tiene classList mutable ni foco.
class El {
  constructor(tag, attrs = {}) {
    this.tagName = tag.toUpperCase()
    this.attrs = { ...attrs }
    this.clases = new Set((attrs.class ?? '').split(/\s+/).filter(Boolean))
    this.children = []
    this.parentNode = null
    this.isConnected = true
    this.classList = {
      contains: (c) => this.clases.has(c),
      add: (c) => this.clases.add(c),
      remove: (c) => this.clases.delete(c),
    }
  }
  append(...hijos) { for (const h of hijos) { h.parentNode = this; this.children.push(h) } return this }
  hasAttribute(n) { return n in this.attrs }
  getAttribute(n) { return this.attrs[n] ?? null }
  setAttribute(n, v) { this.attrs[n] = String(v) }
  removeAttribute(n) { delete this.attrs[n] }
  focus() { globalThis.document.activeElement = this }
  getClientRects() { return this.hasAttribute('data-oculto') ? [] : [{}] }
  click() { this.onclick?.() }
  *todos() { for (const c of this.children) { yield c; yield* c.todos() } }
  coincide(sel) {
    return sel.split(',').some((s) => {
      s = s.trim()
      const m = /^(\w+)?(?:\[([\w-]+)\])?/.exec(s)
      if (m[1] && m[1].toUpperCase() !== this.tagName) return false
      if (m[2] && !this.hasAttribute(m[2])) return false
      if (s.includes(':not([disabled])') && this.hasAttribute('disabled')) return false
      return Boolean(m[1] || m[2])
    })
  }
  querySelectorAll(sel) { return [...this.todos()].filter((e) => e.coincide(sel)) }
  querySelector(sel) { return this.querySelectorAll(sel)[0] ?? null }
  contains(n) { for (let x = n; x; x = x.parentNode) if (x === this) return true; return false }
}

globalThis.HTMLElement = El
const body = new El('body')
globalThis.document = {
  activeElement: body,
  body,
  querySelectorAll: (s) => body.querySelectorAll(s),
  querySelector: (s) => body.querySelector(s),
  getElementById: (id) => [...body.todos()].find((e) => e.attrs.id === id) ?? null,
}

const { sincronizar, modalActivo, cerrar } = await import('../../resources/js/componentes/dialog.ts')
const { filaCoincide } = await import('../../resources/js/componentes/filter-bar.ts')
const { loader } = await import('../../resources/js/componentes/loader.ts')

function modal(id) {
  const cerrarBtn = new El('button', { 'data-ui-modal-close': '' })
  const input = new El('input', {})
  const d = new El('dialog', { id, class: 'ui-modal hidden', 'data-ui-modal': '' })
  d.append(new El('div').append(cerrarBtn, input))
  body.append(d)
  cerrarBtn.onclick = () => d.classList.add('hidden')
  return { d, cerrarBtn, input }
}

test('quitar hidden abre el dialog y enfoca el primer control del cuerpo, no la ×', () => {
  const disparador = new El('button')
  body.append(disparador)
  disparador.focus()
  const { d, input } = modal('m1')

  sincronizar(d)
  assert.equal(d.hasAttribute('open'), false, 'con hidden sigue cerrado')

  d.classList.remove('hidden')
  sincronizar(d)
  assert.equal(d.hasAttribute('open'), true)
  assert.equal(document.activeElement, input)

  sincronizar(d) // idempotente
  assert.equal(document.activeElement, input)
})

test('un control oculto no recibe el foco inicial', () => {
  const { d, input } = modal('m5')
  input.setAttribute('data-oculto', '')
  const visible = new El('input', {})
  d.children[0].append(visible)
  d.classList.remove('hidden')
  sincronizar(d)
  assert.equal(document.activeElement, visible)
  d.classList.add('hidden')
  sincronizar(d)
})

test('poner hidden cierra el dialog y devuelve el foco a quien lo abrió', () => {
  const disparador = new El('button')
  body.append(disparador)
  disparador.focus()
  const { d } = modal('m2')
  d.classList.remove('hidden')
  sincronizar(d)

  d.classList.add('hidden')
  sincronizar(d)
  assert.equal(d.hasAttribute('open'), false)
  assert.equal(document.activeElement, disparador)
})

test('cerrar() pasa por el botón × (el onclose de la vista) y modalActivo toma el último visible', () => {
  const a = modal('m3')
  const b = modal('m4')
  a.d.classList.remove('hidden')
  b.d.classList.remove('hidden')
  assert.equal(modalActivo(), b.d)

  let llamado = 0
  b.cerrarBtn.onclick = () => { llamado++; b.d.classList.add('hidden') }
  cerrar(b.d)
  assert.equal(llamado, 1)
  assert.equal(modalActivo(), a.d)
  a.d.classList.add('hidden')
})

test('filaCoincide: texto contiene (sin mayúsculas) y columnas con los operadores del motor', () => {
  const fila = { texto: 'KM-01 Karl Mayer', valor: (c) => ({ tipo: 'Urdidora', depto: '' })[c] ?? null }

  assert.equal(filaCoincide(fila, '', []), true)
  assert.equal(filaCoincide(fila, 'karl', []), true)
  assert.equal(filaCoincide(fila, 'staubli', []), false)
  assert.equal(filaCoincide(fila, '', [{ column: 'tipo', operator: 'equals', value: 'urdidora' }]), true)
  assert.equal(filaCoincide(fila, '', [{ column: 'tipo', operator: 'equals', value: 'Urd' }]), false)
  assert.equal(filaCoincide(fila, '', [{ column: 'tipo', operator: 'starts', value: 'Urd' }]), true)
  assert.equal(filaCoincide(fila, '', [{ column: 'tipo', operator: 'equals', value: '' }]), true, 'vacío = sin filtro')
  assert.equal(filaCoincide(fila, 'km', [{ column: 'depto', operator: 'empty', value: 'x' }]), true)
})

test('loader.show/hide alternan hidden y aria-busy en #globalLoader', async () => {
  const el = new El('div', { id: 'globalLoader', class: 'hidden' })
  body.append(el)

  loader.show()
  assert.equal(el.classList.contains('hidden'), false)
  assert.equal(el.getAttribute('aria-busy'), 'true')
  loader.hide()
  assert.equal(el.classList.contains('hidden'), true)

  loader.show(20)
  loader.hide() // cancela el show diferido
  await new Promise((r) => setTimeout(r, 40))
  assert.equal(el.classList.contains('hidden'), true)
})
