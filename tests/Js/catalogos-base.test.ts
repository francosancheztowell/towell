import assert from 'node:assert/strict'
import test from 'node:test'

// DOM mínimo para CatalogBase (sin jsdom en el proyecto). Es un doble de prueba: acepta
// cualquier propiedad (índice any) igual que el DOM real acepta dataset/value/etc.
/* eslint-disable @typescript-eslint/no-explicit-any */
class El {
  [prop: string]: any
  constructor(tag: string, attrs: Record<string, unknown> = {}) {
    this.tagName = tag.toUpperCase()
    this.attrs = {}
    this.children = []
    this.parentNode = null
    this.listeners = {}
    this.dataset = {}
    this.hidden = false
    this.disabled = false
    this.value = ''
    this._text = ''
    for (const [k, v] of Object.entries(attrs)) this.setAttribute(k, v)
  }
  setAttribute(n: string, v: unknown) {
    this.attrs[n] = String(v)
    if (n.startsWith('data-')) this.dataset[n.slice(5).replace(/-(\w)/g, (_, c) => c.toUpperCase())] = String(v)
  }
  getAttribute(n: string) { return this.attrs[n] ?? null }
  focus() { (globalThis as any).enfocado = this }
  get nextElementSibling() { const s = this.parentNode?.children ?? []; return s[s.indexOf(this) + 1] ?? null }
  get previousElementSibling() { const s = this.parentNode?.children ?? []; return s[s.indexOf(this) - 1] ?? null }
  matches(sel: string) { return this.coincide(sel) }
  hasAttribute(n: string) { return n in this.attrs }
  addEventListener(t: string, fn: (e: any) => void) { (this.listeners[t] ??= []).push(fn) }
  emit(t: string, extra: Record<string, unknown> = {}) { for (const fn of this.listeners[t] ?? []) fn({ target: this, preventDefault() {}, ...extra }) }
  append(...hs: any[]) { for (const h of hs) { h.parentNode = this; this.children.push(h) } return this }
  insertBefore(n: any, ref: any) {
    n.parentNode = this
    const i = ref ? this.children.indexOf(ref) : -1
    if (i < 0) this.children.push(n); else this.children.splice(i, 0, n)
  }
  remove() { if (this.parentNode) { const s = this.parentNode.children; s.splice(s.indexOf(this), 1); this.parentNode = null } }
  replaceWith(n: any) { const s = this.parentNode.children; n.parentNode = this.parentNode; s[s.indexOf(this)] = n; this.parentNode = null }
  get textContent(): string { return this._text + this.children.map((c: any) => c.textContent).join('') }
  set textContent(v: unknown) { this._text = String(v); this.children = [] }
  cloneNode() {
    const c = new El(this.tagName.toLowerCase(), this.attrs)
    c.append(...this.children.map((h: any) => h.cloneNode()))
    c._text = this._text
    return c
  }
  *todos(): Generator<any> { for (const c of this.children) { yield c; yield* c.todos() } }
  coincide(sel: string): boolean {
    const m = /^(\w+)?((?:\[[^\]]+\])*)$/.exec(sel.trim())
    if (!m) return false
    if (m[1] && m[1].toUpperCase() !== this.tagName) return false
    for (const [, n = '', v] of (m[2] ?? '').matchAll(/\[([\w-]+)(?:="([^"]*)")?\]/g)) {
      if (!this.hasAttribute(n)) return false
      if (v !== undefined && this.getAttribute(n) !== v) return false
    }
    return true
  }
  querySelectorAll(sel: string): any[] { return [...this.todos()].filter((e: any) => e.coincide(sel)) }
  querySelector(sel: string) { return this.querySelectorAll(sel)[0] ?? null }
  closest(sel: string) { for (let n: any = this; n; n = n.parentNode) if (n.coincide?.(sel)) return n; return null }
  reset() { for (const c of this.todos()) c.value = '' }
}

const g = globalThis as any
g.HTMLDialogElement = class {}
g.HTMLElement = El
g.document = { getElementById: () => null, querySelector: () => null, querySelectorAll: () => [] }

const { CatalogBase, urlRecurso, textoCelda, mensajeDeError } = await import('../../resources/js/catalogos/catalog-base.ts')

test('urlRecurso codifica la llave (Comentarios usa texto libre)', () => {
  assert.equal(urlRecurso('/atadores/catalogos/actividades/'), '/atadores/catalogos/actividades')
  assert.equal(urlRecurso('/atadores/catalogos/comentarios', 'Falta de peine ñ'), '/atadores/catalogos/comentarios/Falta%20de%20peine%20%C3%B1')
})

test('textoCelda pinta vacío sin sufijo y agrega el sufijo si hay valor', () => {
  assert.equal(textoCelda(null, '%'), '')
  assert.equal(textoCelda('  ', '%'), '')
  assert.equal(textoCelda(40, '%'), '40%')
  assert.equal(textoCelda('<b>x</b>'), '<b>x</b>') // va a textContent, no a innerHTML
})

test('mensajeDeError usa el mensaje del servidor si viene', () => {
  assert.equal(mensajeDeError({ data: { message: 'Error al crear: duplicado' } }, 'x'), 'Error al crear: duplicado')
  assert.equal(mensajeDeError({ data: {} }, 'Por defecto'), 'Por defecto')
  assert.equal(mensajeDeError(null, 'Por defecto'), 'Por defecto')
})

function montar({ http }: { http: any }) {
  const fila = (id: string, pct: number | string) => {
    const tr = new El('tr', { 'data-fila': '', 'data-id': id, 'aria-selected': 'false' })
    const a = new El('td', { 'data-campo': 'ActividadId' }); a.textContent = id
    const b = new El('td', { 'data-campo': 'Porcentaje', 'data-sufijo': '%' }); b.textContent = pct + '%'
    return tr.append(a, b)
  }
  const cuerpo = new El('tbody')
  const vacio = new El('tr', { 'data-catalogo-vacio': '' })
  vacio.hidden = true
  cuerpo.append(fila('MONTAJE', 40), fila('LIMPIEZA', 25), vacio)
  const plantilla = new El('template')
  plantilla.content = new El('div').append(fila('', ''))
  const formulario = new El('form')
  const oculto = new El('input', { name: '__original' })
  const id = new El('input', { name: 'ActividadId' })
  const pct = new El('input', { name: 'Porcentaje' })
  formulario.append(oculto, id, pct)
  const notas: string[][] = []
  const catalogo: any = new CatalogBase(
    {
      clave: 'actividades', endpoint: '/atadores/catalogos/actividades', llave: 'ActividadId',
      columnas: [{ campo: 'ActividadId' }, { campo: 'Porcentaje', sufijo: '%' }],
      campos: [{ nombre: 'ActividadId', tipo: 'text', requerido: true, etiqueta: 'Actividad ID' }, { nombre: 'Porcentaje', tipo: 'number', requerido: true }],
      textos: { errorGuardar: 'No se pudo guardar la actividad' },
    },
    ({
      raiz: new El('div'), cuerpo, plantilla, vacio, formulario, tituloFormulario: null,
      botonEditar: new El('button'), botonEliminar: new El('button'), botonCrear: null, botonConfirmarEliminar: null, botonGuardar: new El('button'),
    }) as any,
    { http, notify: { success: (m: string) => notas.push(['ok', m]), error: (m: string) => notas.push(['error', m]) } },
  )
  return { catalogo, cuerpo, vacio, id, pct, oculto, notas }
}

const filas = (cuerpo: any): string[] => cuerpo.querySelectorAll('tr[data-fila]').map((f: any) => f.textContent)

test('seleccionar alterna aria-selected y habilita/deshabilita editar y eliminar', () => {
  const { catalogo, cuerpo } = montar({ http: {} })
  const [a, b] = cuerpo.querySelectorAll('tr[data-fila]')
  assert.equal(catalogo.el.botonEditar.disabled, true)

  cuerpo.emit('click', { target: a })
  assert.equal(a.getAttribute('aria-selected'), 'true')
  assert.equal(catalogo.el.botonEliminar.disabled, false)

  cuerpo.emit('click', { target: b })
  assert.equal(a.getAttribute('aria-selected'), 'false')
  assert.equal(b.getAttribute('aria-selected'), 'true')

  cuerpo.emit('click', { target: b }) // tocar la seleccionada la deselecciona
  assert.equal(catalogo.seleccionada, null)
  assert.equal(catalogo.el.botonEditar.disabled, true)
})

test('guardar nuevo: POST al endpoint y agrega la fila desde la plantilla', async () => {
  const llamadas: any[][] = []
  const { catalogo, cuerpo, id, pct, notas } = montar({
    http: {
      post: async (url: string, data: unknown) => { llamadas.push(['post', url, data]); return { success: true, message: 'Actividad creada exitosamente' } },
      // Relectura: lo que guardó el servidor (recortado y normalizado).
      get: async (url: string) => { llamadas.push(['get', url]); return { success: true, data: { ActividadId: 'ENHEBRADO', Porcentaje: 35 } } },
    },
  })
  id.value = 'ENHEBRADO '; pct.value = '035'
  await catalogo.guardar()

  assert.deepEqual(llamadas, [
    ['post', '/atadores/catalogos/actividades', { ActividadId: 'ENHEBRADO ', Porcentaje: '035' }],
    ['get', '/atadores/catalogos/actividades/ENHEBRADO'],
  ])
  assert.deepEqual(filas(cuerpo), ['MONTAJE40%', 'LIMPIEZA25%', 'ENHEBRADO35%'])
  assert.equal(cuerpo.querySelectorAll('tr[data-fila]').at(-1).dataset.id, 'ENHEBRADO')
  assert.deepEqual(notas, [['ok', 'Actividad creada exitosamente']])
})

test('guardar edición: PUT a la llave original y reemplaza la fila (aunque cambie la llave)', async () => {
  const llamadas: any[][] = []
  const { catalogo, cuerpo, id, pct, oculto } = montar({
    http: { put: async (url: string, data: unknown) => { llamadas.push([url, data]); return { success: true, message: 'ok' } } },
  })
  const montaje = cuerpo.querySelectorAll('tr[data-fila]')[0]
  cuerpo.emit('click', { target: montaje })
  oculto.value = 'MONTAJE'; id.value = 'MONTAJE 2'; pct.value = '45'
  await catalogo.guardar()

  assert.equal(llamadas[0]?.[0], '/atadores/catalogos/actividades/MONTAJE')
  assert.deepEqual(filas(cuerpo), ['MONTAJE 245%', 'LIMPIEZA25%'])
  assert.equal(catalogo.seleccionada.dataset.id, 'MONTAJE 2', 'la fila editada sigue seleccionada')
})

test('guardar con error del servidor avisa con su mensaje y no toca la tabla', async () => {
  const { catalogo, cuerpo, id, pct, notas } = montar({
    http: { post: async () => { throw { status: 500, data: { success: false, message: 'Error al crear la actividad: duplicado' } } } },
  })
  id.value = 'MONTAJE'; pct.value = '1'
  await catalogo.guardar()

  assert.deepEqual(notas, [['error', 'Error al crear la actividad: duplicado']])
  assert.equal(filas(cuerpo).length, 2)
  assert.equal(catalogo.el.botonGuardar.disabled, false)
})

test('un 2xx con success:false muestra el mensaje del servidor', async () => {
  const { catalogo, cuerpo, notas } = montar({ http: { delete: async () => ({ success: false, message: 'La máquina está en uso' }) } })
  cuerpo.emit('click', { target: cuerpo.querySelectorAll('tr[data-fila]')[0] })
  await catalogo.eliminar()

  assert.deepEqual(notas, [['error', 'La máquina está en uso']])
  assert.equal(filas(cuerpo).length, 2)
})

test('validar() de base exige los campos requeridos antes de llamar al servidor', async () => {
  let llamado = false
  const { catalogo, notas } = montar({ http: { post: async () => { llamado = true } } })
  await catalogo.guardar()

  assert.equal(llamado, false)
  assert.deepEqual(notas, [['error', 'Actividad ID es obligatorio']])
})

test('eliminar: DELETE de la seleccionada, quita la fila y muestra el vacío al quedar sin filas', async () => {
  const urls: string[] = []
  const { catalogo, cuerpo, vacio, notas } = montar({ http: { delete: async (url: string) => { urls.push(url); return { success: true, message: 'Eliminada' } } } })

  cuerpo.emit('click', { target: cuerpo.querySelectorAll('tr[data-fila]')[0] })
  await catalogo.eliminar()
  assert.equal(g.enfocado.dataset.id, 'LIMPIEZA', 'el foco pasa a la fila vecina')

  cuerpo.emit('click', { target: cuerpo.querySelectorAll('tr[data-fila]')[0] })
  await catalogo.eliminar()

  assert.deepEqual(urls, ['/atadores/catalogos/actividades/MONTAJE', '/atadores/catalogos/actividades/LIMPIEZA'])
  assert.deepEqual(notas, [['ok', 'Eliminada'], ['ok', 'Eliminada']])
  assert.equal(filas(cuerpo).length, 0)
  assert.equal(vacio.hidden, false)
  assert.equal(catalogo.el.botonEliminar.disabled, true)
})

test('botonesExternos: no enlaza Crear/Editar/Eliminar (los despacha catalog-actions) pero sí los habilita', () => {
  const botonEditar = new El('button')
  const cuerpo = new El('tbody')
  const tr = new El('tr', { 'data-fila': '', 'data-id': '1' })
  cuerpo.append(tr)
  const catalogo: any = new CatalogBase(
    { clave: 'x', endpoint: '/x', llave: 'Id', columnas: [], campos: [], textos: {} },
    ({
      raiz: new El('div'), cuerpo, plantilla: null, vacio: null, formulario: new El('form'), tituloFormulario: null,
      botonEditar, botonEliminar: null, botonCrear: null, botonConfirmarEliminar: null, botonGuardar: null, botonesExternos: true,
    }) as any,
    { http: {} as any, notify: { success() {}, error() {} } },
  )
  assert.equal(botonEditar.listeners.click, undefined)
  assert.equal(botonEditar.disabled, true)
  cuerpo.emit('click', { target: tr })
  assert.equal(botonEditar.disabled, false)
  assert.equal(catalogo.seleccionada, tr)
})

test('ganchos: valoresParaEditar y despuesDeGuardar se pueden reemplazar (Planeación no tiene GET por id)', async () => {
  const { catalogo, cuerpo, id, pct, oculto } = montar({
    http: { get: async () => { throw new Error('no debe pedir GET') }, put: async () => ({ success: true, message: 'ok' }) },
  })
  const vistos: unknown[] = []
  catalogo.valoresParaEditar = async () => ({ ActividadId: 'DE-LA-FILA', Porcentaje: 7 })
  catalogo.despuesDeGuardar = async (res: any, cuerpoEnviado: any, esEdicion: boolean, original: string) => {
    vistos.push([res.message, cuerpoEnviado.ActividadId, esEdicion, original])
  }
  cuerpo.emit('click', { target: cuerpo.querySelectorAll('tr[data-fila]')[0] })
  await catalogo.abrirEditar()
  assert.equal(id.value, 'DE-LA-FILA')
  assert.equal(pct.value, '7')
  assert.equal(oculto.value, 'MONTAJE')
  await catalogo.guardar()
  assert.deepEqual(vistos, [['ok', 'DE-LA-FILA', true, 'MONTAJE']])
})
