// Rama claude/mejora-dedup-ts (e0c969f7): los tres autoguardados de Calificar atadores
// (observaciones 2 s, merma 1.5 s, folio paro 1.2 s) pasaron de setTimeout/clearTimeout a
// mano a `debounce` de utils/format. Estos tests montan la página real (index.ts) sobre un
// DOM mínimo y fijan la conducta que tenía la versión vieja:
//   - una sola petición por ráfaga de tecleo, con el valor final y no antes del plazo;
//   - la merma que excede el tope guarda 5 al instante y NO deja un guardado programado
//     vivo (antes clearTimeout(tMerma); ahora guardarMermaLuego.cancel());
//   - borrar la merma cancela el guardado pendiente;
//   - folio recortado y nada se guarda en solo lectura.
import assert from 'node:assert/strict'
import { afterEach, beforeEach, mock, test } from 'node:test'

// ---------- DOM mínimo (solo lo que index.ts y devolucion.ts tocan al iniciar) ----------
class ClassList {
  constructor() { this.set = new Set() }
  add(...c) { c.forEach((x) => this.set.add(x)) }
  remove(...c) { c.forEach((x) => this.set.delete(x)) }
  toggle(c, force) { const on = force ?? !this.set.has(c); if (on) this.set.add(c); else this.set.delete(c); return on }
  contains(c) { return this.set.has(c) }
}

class El {
  constructor(id = '') {
    this.id = id
    this.value = ''
    this.checked = false
    this.disabled = false
    this.dataset = {}
    this.classList = new ClassList()
    this.className = ''
    this.textContent = ''
    this.listeners = {}
    this.parentNode = null
  }
  addEventListener(t, fn) { (this.listeners[t] ??= []).push(fn) }
  removeEventListener(t, fn) { this.listeners[t] = (this.listeners[t] ?? []).filter((f) => f !== fn) }
  dispatch(type) {
    const ev = { type, target: this, preventDefault() {} }
    for (const fn of this.listeners[type] ?? []) fn(ev)
  }
  querySelector() { return null }
  querySelectorAll() { return [] }
  closest() { return null }
  contains(x) { return x === this }
  focus() {}
}

let elementos
function montarDom(config) {
  elementos = new Map()
  const raiz = new El('calificar-atado')
  raiz.dataset.pagina = JSON.stringify(config)
  for (const id of ['calificar-atado', 'observaciones', 'autoSaveIndicator', 'savedIndicator', 'mergaKg',
    'mergaSavedIndicator', 'folioParo', 'folioParoSavedIndicator', 'formObservaciones']) {
    elementos.set(id, id === 'calificar-atado' ? raiz : new El(id))
  }
  const doc = new El('document')
  doc.getElementById = (id) => elementos.get(id) ?? null
  doc.body = new El('body')
  doc.createElement = (tag) => new El(tag)
  globalThis.document = doc
  globalThis.window ??= globalThis
}

// ---------- Red y avisos ----------
const { http } = await import('../../resources/js/utils/http.ts')
const { notify } = await import('../../resources/js/utils/notifications.ts')

let posts
let alertas
http.post = async (url, data) => {
  posts.push({ url, data })
  return { ok: true, folio_paro: typeof data?.folio_paro === 'string' ? data.folio_paro : undefined }
}
notify.alert = async (texto, titulo) => { alertas.push({ texto, titulo }) }
notify.success = () => {}

const config = (soloLectura = false) => ({
  rutas: { guardar: '/atadores/save', programa: '/atadores/programa', devolucionGuardar: '', devolucionEliminar: '', devolucionJulios: '', devolucionUbicaciones: '' },
  usuario: { cve: '123', nombre: 'Prueba' },
  esKm: false,
  atado: { noJulio: 'J-1', noOrden: 'O-1', refId: null, telar: null, tipo: null, soloLectura },
  devolucion: null,
  km: null,
})

let instancia = 0
async function montar(soloLectura = false) {
  montarDom(config(soloLectura))
  // ?n= fuerza una instancia nueva del módulo (se ejecuta al importarse).
  await import(`../../resources/js/modulos/atadores/calificar/index.ts?n=${++instancia}`)
  return { $: (id) => elementos.get(id) }
}

const teclear = (el, valor) => { el.value = valor; el.dispatch('input') }
const acciones = () => posts.map((p) => p.data.action)
const flush = () => new Promise((r) => setImmediate(r))

beforeEach(() => {
  posts = []
  alertas = []
  mock.timers.enable({ apis: ['setTimeout'] })
})
afterEach(() => mock.timers.reset())

test('observaciones: una ráfaga de tecleo hace UN guardado, con el último texto, a los 2 s', async () => {
  const { $ } = await montar()
  const obs = $('observaciones')
  teclear(obs, 'a')
  mock.timers.tick(1500)
  teclear(obs, 'ab')
  mock.timers.tick(1500)
  teclear(obs, 'abc')
  assert.deepEqual(posts, [], 'no guarda antes de 2 s sin teclear')
  assert.equal($('autoSaveIndicator').classList.contains('hidden'), false, 'muestra "guardando" mientras espera')
  mock.timers.tick(1999)
  assert.equal(posts.length, 0)
  mock.timers.tick(1)
  await flush()
  assert.equal(posts.length, 1)
  assert.deepEqual(posts[0].data, { action: 'observaciones', no_julio: 'J-1', no_orden: 'O-1', observaciones: 'abc' })
  assert.equal(posts[0].url, '/atadores/save')
})

test('merma: valor válido se guarda a los 1.5 s con el valor del momento del disparo', async () => {
  const { $ } = await montar()
  const merma = $('mergaKg')
  teclear(merma, '1')
  mock.timers.tick(1000)
  teclear(merma, '1.25')
  mock.timers.tick(1499)
  assert.equal(posts.length, 0)
  mock.timers.tick(1)
  await flush()
  assert.equal(posts.length, 1)
  assert.equal(posts[0].data.action, 'merga')
  assert.equal(posts[0].data.mergaKg, 1.25)
})

test('merma: exceder el tope guarda 5 al instante y cancela el guardado programado (sin doble POST)', async () => {
  const { $ } = await montar()
  const merma = $('mergaKg')
  teclear(merma, '4')
  mock.timers.tick(500)
  teclear(merma, '7')
  await flush()
  assert.equal(posts.length, 1, 'el exceso guarda de inmediato')
  assert.equal(posts[0].data.mergaKg, 5)
  assert.equal(merma.value, '5')
  assert.equal(alertas.length, 1, 'avisa el rango')
  mock.timers.tick(5000)
  await flush()
  assert.deepEqual(acciones(), ['merga'], 'el "4" programado antes no debe dispararse después')
})

test('merma: borrar el campo cancela el guardado pendiente', async () => {
  const { $ } = await montar()
  const merma = $('mergaKg')
  teclear(merma, '3')
  mock.timers.tick(1000)
  teclear(merma, '')
  mock.timers.tick(5000)
  await flush()
  assert.deepEqual(posts, [])
})

test('folio paro: un guardado por ráfaga, recortado, a los 1.2 s', async () => {
  const { $ } = await montar()
  const folio = $('folioParo')
  teclear(folio, ' F')
  mock.timers.tick(800)
  teclear(folio, ' F-12 ')
  mock.timers.tick(1199)
  assert.equal(posts.length, 0)
  mock.timers.tick(1)
  await flush()
  assert.equal(posts.length, 1)
  assert.equal(posts[0].data.action, 'folio_paro')
  assert.equal(posts[0].data.folio_paro, 'F-12')
  assert.equal(folio.value, 'F-12')
})

test('solo lectura: ni merma ni folio llegan al servidor', async () => {
  const { $ } = await montar(true)
  teclear($('mergaKg'), '2')
  teclear($('folioParo'), 'X')
  mock.timers.tick(5000)
  await flush()
  assert.deepEqual(acciones().filter((a) => a !== 'observaciones'), [])
})

test('los tres autoguardados son independientes: teclear en uno no cancela al otro', async () => {
  const { $ } = await montar()
  teclear($('folioParo'), 'F1')
  mock.timers.tick(600)
  teclear($('mergaKg'), '2')
  mock.timers.tick(600)
  await flush()
  assert.deepEqual(acciones(), ['folio_paro'])
  mock.timers.tick(900)
  await flush()
  assert.deepEqual(acciones(), ['folio_paro', 'merga'])
})
