// Rama claude/mejora-ts-inline-tejedores-config (ca50b5d6): Cargar planeación pasó de un
// fetch crudo que leía el cuerpo como texto a http.upload. Se fija la conducta vieja:
//   - modo normal → ruta upload; modo actualización → upload-update (las dos de data-*);
//   - éxito: resumen con los conteos (0 si faltan) y redirección al programa de tejido;
//   - error HTTP: "HTTP <status> - <message|error|texto crudo>" y el botón vuelve a habilitarse;
//   - 200 que no es JSON (HTML de un error de PHP): "Respuesta no es JSON válido";
//   - success:false con 200: alerta con data.message;
//   - tipo de archivo inválido no sube nada.
import assert from 'node:assert/strict'
import { test } from 'node:test'

class ClassList {
  constructor() { this.s = new Set() }
  add(...c) { c.forEach((x) => this.s.add(x)) }
  remove(...c) { c.forEach((x) => this.s.delete(x)) }
  contains(c) { return this.s.has(c) }
  toggle(c, f) { const on = f ?? !this.s.has(c); if (on) this.s.add(c); else this.s.delete(c); return on }
  replace(a, b) { if (!this.s.has(a)) return false; this.s.delete(a); this.s.add(b); return true }
}

class El {
  constructor(id, props = {}) {
    this.id = id
    this.dataset = {}
    this.style = {}
    this.classList = new ClassList()
    this.listeners = {}
    this.disabled = false
    this.checked = false
    this.textContent = ''
    this.innerHTML = ''
    Object.assign(this, props)
  }
  addEventListener(t, fn) { (this.listeners[t] ??= []).push(fn) }
  emit(t) { const ev = { type: t, target: this, preventDefault() {} }; for (const fn of this.listeners[t] ?? []) fn(ev); return ev }
}

class FakeFormData {
  constructor() { this.pares = [] }
  append(k, v) { this.pares.push([k, v]) }
  get(k) { return this.pares.find(([n]) => n === k)?.[1] ?? null }
}

let instancia = 0
const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'

async function montar({ responder, actualizacion = false, archivo = { name: 'plan.xlsx', size: 2048, type: XLSX } }) {
  const els = new Map()
  const form = new El('excelForm')
  Object.assign(form.dataset, {
    rutaUpload: '/configuracion/cargar-planeacion/upload',
    rutaUpdate: '/configuracion/utileria/cargarplaneacion/upload-update',
    rutaPrograma: '/planeacion/programa-tejido',
  })
  els.set('excelForm', form)
  els.set('excel_file', new El('excel_file', { files: archivo ? [archivo] : [] }))
  els.set('modoActualizacion', new El('modoActualizacion', { checked: actualizacion }))
  els.set('uploadBtn', new El('uploadBtn'))
  for (const id of ['progress-section', 'progress-bar', 'progress-text', 'modo-texto', 'file-info', 'file-name', 'file-size']) els.set(id, new El(id))
  els.get('progress-bar').classList.add('bg-blue-600')
  els.get('progress-section').classList.add('hidden')

  const llamadas = []
  const avisos = []
  globalThis.window = globalThis
  globalThis.FormData = FakeFormData
  globalThis.location = { href: '/configuracion/cargar-planeacion' }
  globalThis.history = { back() {} }
  globalThis.document = { readyState: 'complete', getElementById: (id) => els.get(id) ?? null, querySelector: () => null }
  globalThis.http = {
    upload: async (url, datos) => {
      llamadas.push({ url, datos })
      const r = await responder(url)
      if (r instanceof Error) throw r
      return r
    },
  }
  globalThis.notify = {
    alert: async (...a) => { avisos.push(['alert', ...a]) },
    html: async (...a) => { avisos.push(['html', ...a]) },
  }
  await import(`../../resources/js/modulos/configuracion/cargar-planeacion/index.ts?n=${++instancia}`)
  return { els, form, llamadas, avisos }
}

const esperar = (ms) => new Promise((r) => setTimeout(r, ms))
const httpError = (status, data) => Object.assign(new Error(`HTTP ${status}`), { status, data })

test('cargar planeación: modo normal sube a upload, resume conteos y redirige al programa', async () => {
  const { els, form, llamadas, avisos } = await montar({
    responder: () => ({ success: true, deleted: 4, processed: 10, created: 9, skipped: 1, total_before: 4, total_after: 9 }),
  })
  form.emit('submit')
  await esperar(1100)

  assert.equal(llamadas.length, 1)
  assert.equal(llamadas[0].url, '/configuracion/cargar-planeacion/upload')
  assert.equal(llamadas[0].datos.get('excel_file').name, 'plan.xlsx')
  const [tipo, html, titulo, icono] = avisos[0]
  assert.equal(tipo, 'html')
  assert.equal(titulo, '¡Archivo procesado exitosamente!')
  assert.equal(icono, 'success')
  assert.match(html, /Registros eliminados:<\/strong> 4/)
  assert.match(html, /Total después:<\/strong> 9/)
  assert.ok(!html.includes('actualizados'), 'el resumen normal no lleva "actualizados"')
  assert.ok(els.get('progress-bar').classList.contains('bg-green-600'))
  assert.equal(globalThis.location.href, '/planeacion/programa-tejido')
})

test('cargar planeación: modo actualización sube a upload-update y los conteos faltantes salen 0', async () => {
  const { form, llamadas, avisos } = await montar({ actualizacion: true, responder: () => ({ success: true, updated: 3 }) })
  form.emit('submit')
  await esperar(1100)

  assert.equal(llamadas[0].url, '/configuracion/utileria/cargarplaneacion/upload-update')
  const [, html, titulo] = avisos[0]
  assert.equal(titulo, '¡Archivo actualizado exitosamente!')
  assert.match(html, /Registros actualizados:<\/strong> 3/)
  assert.match(html, /Registros procesados:<\/strong> 0/)
  assert.match(html, /recalculadas para cada telar/)
})

test('cargar planeación: error HTTP muestra "HTTP <status> - mensaje" y rehabilita el botón', async () => {
  const { els, form, avisos } = await montar({ responder: () => httpError(500, { success: false, message: 'Columna Telar faltante' }) })
  form.emit('submit')
  await esperar(10)

  assert.deepEqual(avisos, [['alert', 'HTTP 500 - Columna Telar faltante', 'Error al procesar el archivo', 'error']])
  assert.equal(els.get('uploadBtn').disabled, false)
  assert.match(els.get('uploadBtn').innerHTML, /Procesar Excel/)
  assert.ok(els.get('progress-bar').classList.contains('bg-red-600'))
  assert.equal(globalThis.location.href, '/configuracion/cargar-planeacion', 'no redirige')
})

test('cargar planeación: error con {error} o con HTML crudo también se reporta', async () => {
  let r = httpError(422, { error: 'Archivo vacío' })
  const { form, avisos } = await montar({ responder: () => r })
  form.emit('submit')
  await esperar(10)
  assert.equal(avisos[0][1], 'HTTP 422 - Archivo vacío')

  r = httpError(502, '<html>Bad gateway</html>')
  form.emit('submit')
  await esperar(10)
  assert.equal(avisos[1][1], 'HTTP 502 - <html>Bad gateway</html>')
})

test('cargar planeación: un 200 que no es JSON avisa "Respuesta no es JSON válido"', async () => {
  const { form, avisos } = await montar({ responder: () => '<br /><b>Fatal error</b>' })
  form.emit('submit')
  await esperar(10)
  assert.equal(avisos.length, 1)
  assert.match(avisos[0][1], /^Respuesta no es JSON válido: <br \/><b>Fatal error<\/b>/)
})

test('cargar planeación: success:false con 200 muestra data.message', async () => {
  const { form, avisos } = await montar({ responder: () => ({ success: false, message: 'Sin filas válidas' }) })
  form.emit('submit')
  await esperar(10)
  assert.deepEqual(avisos, [['alert', 'Sin filas válidas', 'Error al procesar el archivo', 'error']])
})

test('cargar planeación: un archivo que no es Excel no se sube', async () => {
  const { form, llamadas, avisos } = await montar({ responder: () => ({ success: true }), archivo: { name: 'x.csv', size: 10, type: 'text/csv' } })
  form.emit('submit')
  await esperar(10)
  assert.equal(llamadas.length, 0)
  assert.equal(avisos[0][2], 'Tipo de archivo inválido')
})
