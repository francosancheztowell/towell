// Rama claude/mejora-ts-inline-tejedores-config: los <script> inline de Configuración
// (Departamentos y Secuencia de folios → catalogo-simple/index.ts) y de Tejedores › Cortado
// de rollo (notificar-mont-rollos/index.ts) pasaron a TS. Estos tests montan la página real
// sobre un DOM mínimo y fijan la conducta que tenía el script viejo:
//   catalogo-simple: editar pinta la fila con el item del servidor (null → '—'), borrar la
//     última deja la fila "vacío" con su colspan, un 422 deja el modal abierto con los errores,
//     y crear en Secuencia usa la llave 'Id' (no 'id').
//   cortado: la URL de órdenes reemplaza :telar, los textos del servidor van escapados,
//     EnProceso '1' (string de sqlsrv) pinta el badge, CUANTAS 0 ya no es 'N/A', y Notificar
//     manda el MISMO objeto marbete que devolvió el servidor y recarga las órdenes.
import assert from 'node:assert/strict'
import { test } from 'node:test'

// ---------------------------------------------------------------- DOM mínimo
class ClassList {
  constructor(el) { this.el = el }
  get set() { return new Set(this.el.className.split(/\s+/).filter(Boolean)) }
  write(s) { this.el.className = [...s].join(' ') }
  add(...c) { const s = this.set; c.forEach((x) => s.add(x)); this.write(s) }
  remove(...c) { const s = this.set; c.forEach((x) => s.delete(x)); this.write(s) }
  contains(c) { return this.set.has(c) }
  toggle(c, force) { const on = force ?? !this.contains(c); if (on) this.add(c); else this.remove(c); return on }
  replace(a, b) { if (!this.contains(a)) return false; this.remove(a); this.add(b); return true }
}

const camel = (s) => s.replace(/-(\w)/g, (_, c) => c.toUpperCase())

class El {
  constructor(tag = 'div', props = {}) {
    this.tagName = tag.toUpperCase()
    this.className = ''
    this.id = ''
    this.dataset = {}
    this.attrs = {}
    this.style = {}
    this.children = []
    this.parentNode = null
    this.listeners = {}
    this.value = ''
    this.defaultValue = ''
    this.disabled = false
    this.checked = false
    this.colSpan = 1
    this._text = ''
    this._html = ''
    this.classList = new ClassList(this)
    Object.assign(this, props)
    if (props.value !== undefined && props.defaultValue === undefined) this.defaultValue = props.value
  }
  get parentElement() { return this.parentNode }
  get textContent() { return this._text + this.children.map((c) => c.textContent).join('') }
  set textContent(v) { this._text = String(v); this.children = [] }
  get innerHTML() { return this._html }
  set innerHTML(v) { this._html = String(v); this.children = [] }
  setAttribute(n, v) { this.attrs[n] = String(v); if (n.startsWith('data-')) this.dataset[camel(n.slice(5))] = String(v) }
  getAttribute(n) { return this.attrs[n] ?? null }
  append(...hs) { for (const h of hs) { h.parentNode = this; this.children.push(h) } return this }
  remove() { if (!this.parentNode) return; const s = this.parentNode.children; s.splice(s.indexOf(this), 1); this.parentNode = null }
  *todos() { for (const c of this.children) { yield c; yield* c.todos() } }
  contains(x) { for (let n = x; n; n = n.parentNode) if (n === this) return true; return false }
  coincide(sel) {
    const m = /^(\w+)?((?:\.[\w-]+)*)((?:\[[^\]]+\])*)$/.exec(sel.trim())
    if (!m) return false
    if (m[1] && m[1].toUpperCase() !== this.tagName) return false
    for (const [, c] of (m[2] ?? '').matchAll(/\.([\w-]+)/g)) if (!this.classList.contains(c)) return false
    for (const [, n, v] of (m[3] ?? '').matchAll(/\[([\w-]+)(?:="([^"]*)")?\]/g)) {
      const actual = n.startsWith('data-') ? this.dataset[camel(n.slice(5))] : (n === 'name' ? this.name : this.attrs[n])
      if (actual === undefined || actual === null) return false
      if (v !== undefined && String(actual) !== v) return false
    }
    return true
  }
  querySelectorAll(sel) { return [...this.todos()].filter((e) => e.coincide(sel)) }
  querySelector(sel) { return this.querySelectorAll(sel)[0] ?? null }
  closest(sel) { for (let n = this; n; n = n.parentNode) if (n.coincide?.(sel)) return n; return null }
  addEventListener(t, fn) { (this.listeners[t] ??= []).push(fn) }
  removeEventListener(t, fn) { this.listeners[t] = (this.listeners[t] ?? []).filter((f) => f !== fn) }
  /** Evento que burbujea hasta document (como el nativo); devuelve el objeto evento. */
  emit(type) {
    let parado = false
    const ev = { type, target: this, preventDefault() { ev.defaultPrevented = true }, stopPropagation() { parado = true } }
    // Cadena de ancestros y al final document (los nodos sueltos cuelgan de él).
    const cadena = []
    for (let n = this; n; n = n.parentNode) cadena.push(n)
    if (globalThis.document && !cadena.includes(globalThis.document)) cadena.push(globalThis.document)
    for (const n of cadena) {
      for (const fn of [...(n.listeners[type] ?? [])]) fn(ev)
      if (parado) break
    }
    return ev
  }
  dispatchEvent(e) { return this.emit(e.type) }
  // tablas
  get tBodies() { return this.children.filter((c) => c.tagName === 'TBODY') }
  get cells() { return this.children.filter((c) => c.tagName === 'TD') }
  insertRow() { const tr = new El('tr'); this.append(tr); return tr }
  insertCell() { const td = new El('td'); this.append(td); return td }
  // formularios
  reset() { for (const c of this.todos()) if ('name' in c) c.value = c.defaultValue }
}

class FakeFormData {
  constructor(form) {
    this.pares = []
    if (form) for (const c of form.todos()) if (c.name) this.pares.push([c.name, c.value])
  }
  append(k, v) { this.pares.push([k, String(v)]) }
  get(k) { const p = [...this.pares].reverse().find(([n]) => n === k); return p ? p[1] : null }
  getAll(k) { return this.pares.filter(([n]) => n === k).map(([, v]) => v) }
}

function montarDocumento(elementos) {
  const doc = new El('#document')
  doc.readyState = 'complete'
  doc.getElementById = (id) => elementos.get(id) ?? null
  doc.querySelector = (sel) => [...elementos.values()].find((e) => e.coincide(sel)) ?? null
  globalThis.document = doc
  globalThis.window = globalThis
  globalThis.FormData = FakeFormData
  return doc
}

const flush = () => new Promise((r) => setTimeout(r, 0))

function fakeNotify() {
  const n = {
    llamadas: [],
    confirmar: true,
    confirm: async (opts) => { n.llamadas.push(['confirm', opts]); return n.confirmar },
    alert: async (...a) => { n.llamadas.push(['alert', ...a]) },
    success: (m) => n.llamadas.push(['success', m]),
    loading: async (m) => { n.llamadas.push(['loading', m]) },
    close: () => n.llamadas.push(['close']),
    de(tipo) { return n.llamadas.filter((l) => l[0] === tipo) },
  }
  return n
}

/** http falso: `respuestas` es una función (metodo, url, datos/config) → valor o Error a lanzar. */
function fakeHttp(responder) {
  const h = { llamadas: [] }
  for (const metodo of ['get', 'post']) {
    h[metodo] = async (url, arg) => {
      h.llamadas.push({ metodo, url, arg })
      const r = await responder(metodo, url, arg)
      if (r instanceof Error) throw r
      return r
    }
  }
  return h
}

function httpError(status, data) {
  return Object.assign(new Error(`HTTP ${status}`), { status, data })
}

let instancia = 0

// ---------------------------------------------------------------- catalogo-simple
const CRUD_DEPTOS = {
  store: '/configuracion/departamentos',
  update: '/configuracion/departamentos/:id',
  destroy: '/configuracion/departamentos/:id',
  campos: ['Depto', 'Descripcion'],
  llaveId: 'id',
  tituloNuevo: 'Nuevo departamento',
  tituloEditar: 'Editar departamento',
  tituloEliminar: '¿Eliminar departamento?',
  campoNombre: 'Depto',
  nombrePorDefecto: 'este registro',
  vacio: 'No hay departamentos registrados.',
}

const CRUD_SECUENCIA = {
  store: '/configuracion/secuencia-de-folios',
  update: '/configuracion/secuencia-de-folios/:id',
  destroy: '/configuracion/secuencia-de-folios/:id',
  campos: ['Modulo', 'Prefijo', 'Consecutivo'],
  llaveId: 'Id',
  tituloNuevo: 'Nueva secuencia',
  tituloEditar: 'Editar secuencia',
  tituloEliminar: '¿Eliminar secuencia?',
  campoNombre: 'Modulo',
  nombrePorDefecto: 'esta secuencia',
  vacio: 'No hay registros de secuencia.',
}

/** filas: [{ id, valores: {Campo: valor} }]; sin filas pinta tr#tr-empty como la vista. */
function montarCatalogo(cfg, filas, defaults = {}) {
  const els = new Map()
  const reg = (el) => { if (el.id) els.set(el.id, el); return el }
  const tabla = reg(new El('table', { id: 'tabla' }))
  tabla.dataset.crud = JSON.stringify(cfg)
  const tbody = new El('tbody', { id: 'tbody' })
  tabla.append(tbody)
  for (const f of filas) {
    const tr = new El('tr', { className: 'border-b hover:bg-gray-50 cursor-pointer' })
    tr.dataset.id = String(f.id)
    tr.append(new El('td', { _text: String(f.id) }))
    for (const c of cfg.campos) {
      tr.dataset[c.toLowerCase()] = String(f.valores[c] ?? '')
      tr.append(new El('td', { _text: f.valores[c] ?? '—' }))
    }
    tbody.append(tr)
  }
  if (!filas.length) {
    const tr = reg(new El('tr', { id: 'tr-empty' }))
    tr.append(new El('td', { _text: cfg.vacio }))
    tbody.append(tr)
  }
  const modal = reg(new El('div', { id: 'modal-form', className: 'fixed hidden items-center' }))
  const form = new El('form', { action: '' })
  modal.append(form)
  form.append(new El('input', { name: '_token', value: 'tok' }))
  form.append(reg(new El('input', { id: 'form-method', name: '_method', value: 'POST' })))
  for (const c of cfg.campos) form.append(reg(new El('input', { id: c, name: c, value: defaults[c] ?? '' })))
  form.append(reg(new El('button', { id: 'form-submit' })))
  form.append(reg(new El('button', { id: 'form-cancelar' })))
  modal.append(reg(new El('h3', { id: 'modal-title' })), reg(new El('button', { id: 'modal-cerrar' })))
  for (const id of ['btn-nuevo', 'btn-editar', 'btn-eliminar']) reg(new El('button', { id }))
  montarDocumento(els)
  return { els, tabla, tbody, modal, form }
}

async function cargarCatalogo() {
  await import(`../../resources/js/modulos/configuracion/catalogo-simple/index.ts?n=${++instancia}`)
}

test('catalogo-simple: editar manda PUT a la URL del id y repinta la fila (null → —)', async () => {
  const { els, tbody, modal, form } = montarCatalogo(CRUD_DEPTOS, [
    { id: 7, valores: { Depto: 'Atadores', Descripcion: 'Viejo' } },
    { id: 8, valores: { Depto: 'Tejido', Descripcion: '' } },
  ])
  const http = fakeHttp(() => ({ ok: true, message: 'Departamento actualizado', item: { id: 7, Depto: 'Atadores 2', Descripcion: null } }))
  const notify = fakeNotify()
  globalThis.window.http = http
  globalThis.window.notify = notify
  await cargarCatalogo()

  assert.equal(els.get('btn-editar').disabled, true, 'sin selección Editar arranca deshabilitado')
  const fila = tbody.children[0]
  fila.cells[1].emit('click')
  assert.equal(els.get('btn-editar').disabled, false)
  assert.equal(els.get('btn-eliminar').disabled, false)

  els.get('btn-editar').emit('click')
  assert.equal(form.action, '/configuracion/departamentos/7')
  assert.equal(els.get('form-method').value, 'PUT')
  assert.equal(els.get('Depto').value, 'Atadores', 'precarga desde data-depto (campo en minúsculas)')
  assert.equal(els.get('Descripcion').value, 'Viejo')
  assert.ok(modal.classList.contains('flex'))

  form.emit('submit')
  await flush()
  assert.equal(http.llamadas.length, 1)
  assert.equal(http.llamadas[0].url, '/configuracion/departamentos/7')
  assert.equal(http.llamadas[0].arg.get('_method'), 'PUT', 'el spoof PUT viaja en el FormData')
  assert.deepEqual(fila.cells.map((td) => td.textContent), ['7', 'Atadores 2', '—'])
  assert.equal(fila.dataset.depto, 'Atadores 2')
  assert.equal(fila.dataset.descripcion, '', 'null se guarda como "" para la próxima edición')
  assert.ok(modal.classList.contains('hidden'))
  assert.deepEqual(notify.de('success'), [['success', 'Departamento actualizado']])
  assert.equal(els.get('form-submit').disabled, false)
})

test('catalogo-simple: borrar la última fila deja la fila vacío con colspan campos+1', async () => {
  const { els, tbody } = montarCatalogo(CRUD_DEPTOS, [{ id: 3, valores: { Depto: "O'Hara", Descripcion: 'x' } }])
  const http = fakeHttp(() => ({ ok: true, message: 'Eliminado ok' }))
  const notify = fakeNotify()
  globalThis.window.http = http
  globalThis.window.notify = notify
  await cargarCatalogo()

  tbody.children[0].cells[0].emit('click')
  els.get('btn-eliminar').emit('click')
  await flush()

  const [, opts] = notify.de('confirm')[0]
  assert.equal(opts.title, '¿Eliminar departamento?')
  assert.match(opts.text, /O'Hara/)
  assert.equal(http.llamadas[0].url, '/configuracion/departamentos/3')
  assert.equal(http.llamadas[0].arg.get('_method'), 'DELETE')
  assert.equal(tbody.querySelectorAll('tr[data-id]').length, 0)
  const vacia = tbody.children[0]
  assert.equal(vacia.id, 'tr-empty')
  assert.equal(vacia.cells[0].colSpan, 3)
  assert.equal(vacia.textContent, 'No hay departamentos registrados.')
  assert.equal(els.get('btn-editar').disabled, true)
  assert.equal(els.get('btn-eliminar').disabled, true)
})

test('catalogo-simple: borrar cancelado no llama al servidor', async () => {
  const { els, tbody } = montarCatalogo(CRUD_DEPTOS, [{ id: 3, valores: { Depto: 'A', Descripcion: '' } }])
  const http = fakeHttp(() => ({ ok: true }))
  const notify = fakeNotify()
  notify.confirmar = false
  globalThis.window.http = http
  globalThis.window.notify = notify
  await cargarCatalogo()

  tbody.children[0].cells[0].emit('click')
  els.get('btn-eliminar').emit('click')
  await flush()
  assert.equal(http.llamadas.length, 0)
  assert.equal(tbody.querySelectorAll('tr[data-id]').length, 1)
})

test('catalogo-simple: un 422 muestra los errores y deja el modal abierto', async () => {
  const { els, modal, form } = montarCatalogo(CRUD_DEPTOS, [])
  const http = fakeHttp(() => httpError(422, { message: 'The given data was invalid.', errors: { Depto: ['El depto es obligatorio.'], Descripcion: ['Muy largo.'] } }))
  const notify = fakeNotify()
  globalThis.window.http = http
  globalThis.window.notify = notify
  await cargarCatalogo()

  els.get('btn-nuevo').emit('click')
  assert.equal(form.action, '/configuracion/departamentos')
  assert.equal(els.get('form-method').value, 'POST')
  form.emit('submit')
  await flush()

  assert.deepEqual(notify.de('alert'), [['alert', '', 'El depto es obligatorio. Muy largo.', 'error']])
  assert.ok(modal.classList.contains('flex'), 'el modal sigue abierto para corregir')
  assert.equal(els.get('form-submit').disabled, false)
  assert.ok(els.get('tr-empty'), 'la fila vacío no se quita si no se creó nada')
})

test('catalogo-simple: un fallo de red (sin status) avisa "Error de conexión"', async () => {
  const { els, form } = montarCatalogo(CRUD_DEPTOS, [])
  const http = fakeHttp(() => httpError(0, null))
  const notify = fakeNotify()
  globalThis.window.http = http
  globalThis.window.notify = notify
  await cargarCatalogo()

  els.get('btn-nuevo').emit('click')
  form.emit('submit')
  await flush()
  assert.deepEqual(notify.de('alert'), [['alert', '', 'Error de conexión', 'error']])
})

test('secuencia: crear sobre tabla vacía usa la llave Id y quita tr-empty; Nuevo resetea Consecutivo a 0', async () => {
  const { els, tbody, form } = montarCatalogo(CRUD_SECUENCIA, [], { Consecutivo: '0' })
  const http = fakeHttp(() => ({ ok: true, message: 'Secuencia creada', item: { Id: 41, Modulo: 'Urdido', Prefijo: null, Consecutivo: 5 } }))
  const notify = fakeNotify()
  globalThis.window.http = http
  globalThis.window.notify = notify
  await cargarCatalogo()

  els.get('Consecutivo').value = '99'
  els.get('btn-nuevo').emit('click')
  assert.equal(els.get('Consecutivo').value, '0', 'form.reset() vuelve al value="0" de la vista')

  els.get('Modulo').value = 'Urdido'
  els.get('Consecutivo').value = '5'
  form.emit('submit')
  await flush()

  assert.equal(http.llamadas[0].url, '/configuracion/secuencia-de-folios')
  assert.equal(http.llamadas[0].arg.get('_method'), 'POST')
  assert.equal(els.get('tr-empty').parentNode, null, 'tr-empty fuera del tbody')
  const filas = tbody.querySelectorAll('tr[data-id]')
  assert.equal(filas.length, 1)
  assert.equal(filas[0].dataset.id, '41')
  assert.equal(filas[0].dataset.modulo, 'Urdido')
  assert.equal(filas[0].dataset.prefijo, '')
  assert.equal(filas[0].dataset.consecutivo, '5')
  assert.deepEqual(filas[0].cells.map((td) => td.textContent), ['41', 'Urdido', '—', '5'])
})

// ---------------------------------------------------------------- cortado de rollo
const RUTAS_CORTADO = {
  rutaOrdenes: '/tejedores/cortadoderollo/telar/:telar/ordenes-en-proceso',
  rutaMarbetes: '/tejedores/cortadoderollo/datos-produccion',
  rutaInsertar: '/tejedores/cortadoderollo/insertar',
}

function montarCortado() {
  const els = new Map()
  const reg = (el) => { if (el.id) els.set(el.id, el); return el }
  const raiz = reg(new El('div', { id: 'cortadoRollo' }))
  Object.assign(raiz.dataset, RUTAS_CORTADO)
  for (const id of ['tablaProduccionesCortadoContainer', 'noDataProduccionesCortado', 'tablaProduccionCortadoContainer',
    'mensajeEstadoCortado', 'mensajeSeleccionaTelar', 'labelTelarSeleccionado', 'iconDropdownCortado', 'btnDropdownTelaresCortado']) {
    reg(new El('div', { id }))
  }
  reg(new El('select', { id: 'selectTelarCortado' }))
  reg(new El('tbody', { id: 'tablaProduccionesCortadoBody' }))
  reg(new El('tbody', { id: 'tablaProduccionCortadoBody' }))
  reg(new El('button', { id: 'btnNotificarCortado', disabled: true }))
  const menu = reg(new El('div', { id: 'menuDropdownTelaresCortado', className: 'hidden' }))
  for (const t of ['', '201']) {
    const b = new El('button')
    b.dataset.telar = t
    menu.append(b)
  }
  montarDocumento(els)
  return els
}

async function cargarCortado() {
  await import(`../../resources/js/modulos/tejedores/notificar-mont-rollos/index.ts?n=${++instancia}`)
}

/** Simula lo que el navegador haría con el innerHTML de las órdenes: una fila con su checkbox. */
function filaOrdenConCheckbox(tbody, { telar, salon, produccion }) {
  const tr = new El('tr', { className: 'orden-row' })
  const td = new El('td', { className: 'text-gray-700' })
  const cb = new El('input', { className: 'checkbox-produccion-cortado' })
  Object.assign(cb.dataset, { telar, salon, produccion })
  td.append(cb)
  tr.append(td)
  tbody.append(tr)
  return cb
}

test('cortado: el dropdown elige telar, pide órdenes con :telar reemplazado y escapa los textos', async () => {
  const els = montarCortado()
  const http = fakeHttp(() => ({
    success: true,
    ordenes: [
      { SalonTejidoId: 'JACQUARD', NoProduccion: 'OP-1', FechaInicio: null, TamanoClave: null, NombreProducto: '<img src=x onerror=alert(1)>', EnProceso: '1' },
      { SalonTejidoId: null, NoProduccion: 'OP-"2"', NombreProducto: '', EnProceso: 0 },
    ],
  }))
  globalThis.window.http = http
  globalThis.window.notify = fakeNotify()
  await cargarCortado()

  const menu = els.get('menuDropdownTelaresCortado')
  els.get('btnDropdownTelaresCortado').emit('click')
  assert.equal(menu.classList.contains('hidden'), false, 'el botón abre el menú')
  menu.children[1].emit('click')
  await flush()

  assert.equal(menu.classList.contains('hidden'), true, 'elegir un telar cierra el menú')
  assert.equal(els.get('labelTelarSeleccionado').textContent, 'Telar 201')
  assert.equal(els.get('selectTelarCortado').value, '201')
  assert.equal(els.get('mensajeSeleccionaTelar').style.display, 'none')
  assert.equal(http.llamadas[0].url, '/tejedores/cortadoderollo/telar/201/ordenes-en-proceso')

  const html = els.get('tablaProduccionesCortadoBody').innerHTML
  assert.ok(!html.includes('<img'), 'NombreProducto del servidor no se inyecta como HTML')
  assert.ok(html.includes('&lt;img src=x onerror=alert(1)&gt;'))
  assert.ok(html.includes('data-produccion="OP-&quot;2&quot;"'), 'comillas escapadas dentro del atributo')
  assert.equal((html.match(/EN PROCESO/g) ?? []).length, 1, "EnProceso '1' (string) pinta el badge; 0 no")
  assert.ok(html.includes('data-telar="201"'))
  assert.ok(els.get('noDataProduccionesCortado').classList.contains('hidden'))
})

test('cortado: "Todos los telares" (data-telar="") limpia sin pedir nada', async () => {
  const els = montarCortado()
  const http = fakeHttp(() => ({ success: true, ordenes: [] }))
  globalThis.window.http = http
  globalThis.window.notify = fakeNotify()
  await cargarCortado()

  els.get('menuDropdownTelaresCortado').children[0].emit('click')
  await flush()
  assert.equal(http.llamadas.length, 0)
  assert.equal(els.get('labelTelarSeleccionado').textContent, 'Telares')
  assert.equal(els.get('mensajeSeleccionaTelar').style.display, 'flex')
  assert.equal(els.get('tablaProduccionesCortadoContainer').style.display, 'none')
})

test('cortado: un 500 con {error} en órdenes muestra "No hay órdenes"; un fallo de red pinta la fila de error', async () => {
  let fallo = httpError(500, { success: false, error: 'boom' })
  const els = montarCortado()
  globalThis.window.http = fakeHttp(() => fallo)
  globalThis.window.notify = fakeNotify()
  await cargarCortado()

  const select = els.get('selectTelarCortado')
  select.value = '305'
  select.emit('change')
  await flush()
  assert.equal(els.get('noDataProduccionesCortado').classList.contains('hidden'), false)
  assert.equal(els.get('noDataProduccionesCortado').textContent, 'No hay órdenes disponibles para este telar')

  fallo = httpError(0, null)
  select.emit('change')
  await flush()
  assert.match(els.get('tablaProduccionesCortadoBody').innerHTML, /Error al cargar las órdenes del telar/)
})

test('cortado: marbete → Notificar manda el objeto del servidor tal cual y recarga las órdenes', async () => {
  const marbete = { PurchBarCode: 'MB-9', ItemId: 'IT', QtySched: '12.70', CUANTAS: 0, InventSizeId: null, InventBatchId: 'L1', WMSLocationId: 'W', Salon: 'SMIT', Extra: 'se conserva' }
  const els = montarCortado()
  const http = fakeHttp((metodo, url) => {
    if (metodo === 'post') return { success: true, mensaje: 'Liberado OK' }
    if (url.includes('datos-produccion')) return { success: true, datos: [marbete] }
    return { success: true, ordenes: [{ SalonTejidoId: 'SMIT', NoProduccion: 'OP-7', EnProceso: 1 }] }
  })
  const notify = fakeNotify()
  globalThis.window.http = http
  globalThis.window.notify = notify
  await cargarCortado()

  const select = els.get('selectTelarCortado')
  select.value = '207'
  select.emit('change')
  await flush()

  const ordenes = els.get('tablaProduccionesCortadoBody')
  const cb = filaOrdenConCheckbox(ordenes, { telar: '207', salon: 'SMIT', produccion: 'OP-7' })
  cb.checked = true
  cb.emit('change')
  await flush()

  const pedido = http.llamadas.find((l) => l.url.includes('datos-produccion'))
  assert.deepEqual(pedido.arg, { params: { no_produccion: 'OP-7', no_telar: '207', salon: 'SMIT' } })
  assert.ok(cb.closest('tr').classList.contains('bg-blue-600'), 'la orden elegida queda marcada')

  const marbetesBody = els.get('tablaProduccionCortadoBody')
  const htmlMarbetes = marbetesBody.innerHTML
  assert.match(htmlMarbetes, />0<\/td>/, 'CUANTAS 0 se pinta 0 (antes || lo volvía N/A)')
  assert.match(htmlMarbetes, />12<\/td>/, 'QtySched truncado a piezas')
  assert.equal(els.get('tablaProduccionCortadoContainer').style.display, 'block')
  assert.equal(els.get('btnNotificarCortado').disabled, true, 'sin marbete elegido no se notifica')

  // El navegador convertiría el innerHTML en filas; aquí la fila 0 a mano.
  const tr = new El('tr', { className: 'marbete-row' })
  tr.dataset.index = '0'
  tr.append(new El('td', { className: 'text-gray-700' }))
  marbetesBody.append(tr)
  tr.cells[0].emit('click')
  assert.ok(tr.classList.contains('selected'))
  assert.equal(els.get('btnNotificarCortado').disabled, false)

  const antes = http.llamadas.filter((l) => l.url.includes('ordenes-en-proceso')).length
  els.get('btnNotificarCortado').emit('click')
  await flush()
  await flush()

  const post = http.llamadas.find((l) => l.metodo === 'post')
  assert.equal(post.url, '/tejedores/cortadoderollo/insertar')
  assert.deepEqual(post.arg, { marbetes: [marbete] }, 'mismo objeto que dio el servidor (antes JSON.parse(data-marbete))')
  assert.match(notify.de('confirm')[0][1].text, /MB-9/)
  assert.deepEqual(notify.de('success'), [['success', 'Liberado OK']])
  assert.equal(http.llamadas.filter((l) => l.url.includes('ordenes-en-proceso')).length, antes + 1, 'recarga las órdenes del telar')
  assert.equal(els.get('btnNotificarCortado').disabled, true)
})

test('cortado: insertar con {success:false,error} (4xx) avisa el error del servidor', async () => {
  const els = montarCortado()
  const http = fakeHttp((metodo, url) => {
    if (metodo === 'post') return httpError(422, { success: false, error: 'Marbete ya liberado' })
    if (url.includes('datos-produccion')) return { success: true, datos: [{ PurchBarCode: 'MB-1' }] }
    return { success: true, ordenes: [] }
  })
  const notify = fakeNotify()
  globalThis.window.http = http
  globalThis.window.notify = notify
  await cargarCortado()

  const cb = filaOrdenConCheckbox(els.get('tablaProduccionesCortadoBody'), { telar: '1', salon: '', produccion: 'OP' })
  cb.checked = true
  cb.emit('change')
  await flush()
  const tr = new El('tr', { className: 'marbete-row' })
  tr.dataset.index = '0'
  els.get('tablaProduccionCortadoBody').append(tr)
  tr.emit('click')
  els.get('btnNotificarCortado').emit('click')
  await flush()
  await flush()

  assert.deepEqual(notify.de('alert'), [['alert', 'Marbete ya liberado', 'Error', 'error']])
  assert.equal(notify.de('success').length, 0)
})
