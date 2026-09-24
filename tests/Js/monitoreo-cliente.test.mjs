import assert from 'node:assert/strict'
import { afterEach, beforeEach, mock, test } from 'node:test'

import { ignorable, iniciar, tiempos, uuid } from '../../resources/js/monitoreo/cliente.ts'

const UUID_V4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/

const flush = () => new Promise((resolve) => setImmediate(resolve))

const navEntry = {
  responseStart: 120.4,
  domContentLoadedEventEnd: 480.6,
  loadEventEnd: 910.2,
  transferSize: 20480,
  serverTiming: [
    { name: 'app', duration: 85.5, description: '' },
    { name: 'db', duration: 40.2, description: '7 q' },
  ],
}

/**
 * Ventana/documento falsos mínimos. `post` graba y responde con lo que haya en
 * `respuestas` (función o valor); los timers se disparan a mano con `correrTimers`.
 */
function entorno({ metas = {}, readyState = 'complete', onLine = true, respuesta } = {}) {
  const valores = {
    'towell-telemetria': '1',
    'towell-ruta': 'tejido.index',
    'towell-version': 'v123',
    'csrf-token': 'tok',
    ...metas,
  }
  const doc = new EventTarget()
  doc.readyState = readyState
  doc.visibilityState = 'visible'
  doc.querySelector = (sel) => {
    const nombre = sel.match(/name="([^"]+)"/)?.[1]
    return nombre && valores[nombre] !== undefined ? { content: valores[nombre] } : null
  }

  const posts = []
  const beacons = []
  const eventos = []
  const timers = new Map()
  let siguiente = 1
  let recargas = 0

  const win = new EventTarget()
  Object.assign(win, {
    document: doc,
    navigator: {
      onLine,
      sendBeacon: (url, fd) => {
        beacons.push({ url, datos: Object.fromEntries(fd.entries()) })
        return true
      },
    },
    location: { pathname: '/tejido/inventario', href: 'http://towell.local/tejido/inventario?x=1' },
    screen: { width: 1280, height: 800 },
    crypto: globalThis.crypto,
    performance: { getEntriesByType: (t) => (t === 'navigation' ? [navEntry] : []) },
    setTimeout: (fn, ms) => {
      timers.set(siguiente, { fn, ms })
      return siguiente++
    },
    clearTimeout: (id) => timers.delete(id),
  })
  win.addEventListener('towell:conexion', (e) => eventos.push(e.detail))

  const env = {
    win,
    doc,
    posts,
    beacons,
    eventos,
    timers,
    get recargas() {
      return recargas
    },
    respuesta: respuesta ?? (() => ({ cerrar: false, intervalo: 60 })),
    post: (url, body) => {
      posts.push({ url, body })
      try {
        const r = typeof env.respuesta === 'function' ? env.respuesta(url, body) : env.respuesta
        return r instanceof Error ? Promise.reject(r) : Promise.resolve(r)
      } catch (e) {
        return Promise.reject(e)
      }
    },
    recargar: () => {
      recargas++
    },
    /** Corre los timers pendientes (los que se programen durante la corrida quedan para después). */
    correrTimers: async () => {
      const pendientes = [...timers.entries()]
      timers.clear()
      for (const [, t] of pendientes) t.fn()
      await flush()
    },
    ultimoTimerMs: () => [...timers.values()].at(-1)?.ms,
    de: (ruta) => posts.filter((p) => p.url === `/telemetria${ruta}`),
  }

  return env
}

const errorRed = () => Object.assign(new Error('Network Error'), { request: {} })
const errorHttp = (status) => Object.assign(new Error(`Request failed with status code ${status}`), { response: { status } })

let activa = null
const arrancar = async (env, extra = {}) => {
  activa = iniciar({ post: env.post, win: env.win, recargar: env.recargar, ...extra })
  await env.correrTimers()
  return activa
}

beforeEach(() => mock.timers.enable({ apis: ['Date'], now: 1_000_000 }))
afterEach(() => {
  activa?.detener()
  activa = null
  mock.timers.reset()
})

test('sin el meta towell-telemetria no arranca ni hace requests (kill switch)', async () => {
  const env = entorno({ metas: { 'towell-telemetria': undefined } })
  assert.equal(iniciar({ post: env.post, win: env.win }), null)
  env.win.dispatchEvent(new Event('load'))
  env.doc.dispatchEvent(new Event('visibilitychange'))
  env.win.dispatchEvent(Object.assign(new Event('error'), { message: 'boom' }))
  await env.correrTimers()
  assert.equal(env.posts.length, 0)
  assert.equal(env.beacons.length, 0)
})

test('no arranca dos veces en la misma ventana', async () => {
  const env = entorno()
  await arrancar(env)
  assert.equal(iniciar({ post: env.post, win: env.win }), null)
})

test('tras el load registra la vista de carga con Navigation Timing y Server-Timing', async () => {
  const env = entorno({ readyState: 'loading' })
  activa = iniciar({ post: env.post, win: env.win, recargar: env.recargar })
  await env.correrTimers()
  assert.equal(env.posts.length, 0, 'nada antes del load')

  env.win.dispatchEvent(new Event('load'))
  await env.correrTimers()

  const [vista] = env.de('/vista')
  assert.match(vista.body.uuid, UUID_V4)
  assert.deepEqual({ ...vista.body, uuid: 'x' }, {
    uuid: 'x',
    tipo: 'carga',
    ruta: 'tejido.index',
    url: '/tejido/inventario',
    nav: { ttfb: 120, dom: 481, carga: 910, kb: 20 },
    st: { app: 86, db: 40, q: 7 },
  })
  assert.equal(activa.vista(), vista.body.uuid)
})

test('el latido lleva vista, ruta, visibilidad, inactividad, versión y pantalla', async () => {
  const env = entorno()
  await arrancar(env)
  const [latido] = env.de('/latido')
  assert.deepEqual(latido.body, {
    vista: activa.vista(),
    ruta: 'tejido.index',
    visible: true,
    inactivoSeg: 0,
    version: 'v123',
    pantalla: '1280x800',
  })

  mock.timers.tick(95_000)
  env.win.dispatchEvent(new Event('keydown'))
  mock.timers.tick(12_000)
  await activa.latir()
  assert.equal(env.de('/latido').at(-1).body.inactivoSeg, 12)
})

test('respeta el intervalo que devuelve el servidor (acotado a 15–900 s)', async () => {
  const env = entorno({ respuesta: () => ({ cerrar: false, intervalo: 300 }) })
  await arrancar(env)
  assert.equal(env.ultimoTimerMs(), 300_000)

  env.respuesta = () => ({ cerrar: false, intervalo: 2 })
  await env.correrTimers()
  assert.equal(env.ultimoTimerMs(), 15_000)
  assert.equal(env.timers.size, 1, 'un solo latido programado')
})

test('visibilitychange manda latido inmediato con visible:false', async () => {
  const env = entorno()
  await arrancar(env)
  env.doc.visibilityState = 'hidden'
  env.doc.dispatchEvent(new Event('visibilitychange'))
  await flush()
  assert.equal(env.de('/latido').at(-1).body.visible, false)
  assert.equal(env.timers.size, 1)
})

test('cerrar:true del latido recarga la página (cierre remoto)', async () => {
  const env = entorno({ respuesta: (url) => (url.endsWith('/latido') ? { cerrar: true, intervalo: 60 } : null) })
  await arrancar(env)
  assert.equal(env.recargas, 1)
})

test('un 401 o 419 en el latido recarga; un 500 no', async () => {
  const env = entorno({ respuesta: (url) => (url.endsWith('/latido') ? errorHttp(500) : null) })
  await arrancar(env)
  assert.equal(env.recargas, 0)
  env.respuesta = () => errorHttp(401)
  await activa.latir()
  assert.equal(env.recargas, 1)
  env.respuesta = () => errorHttp(419)
  await activa.latir()
  assert.equal(env.recargas, 2)
  assert.deepEqual(env.eventos, [], 'un error HTTP no es falta de conexión')
})

test('towell:conexion solo se emite en las transiciones de red', async () => {
  const env = entorno()
  await arrancar(env)
  env.respuesta = () => errorRed()
  await activa.latir()
  await activa.latir()
  assert.deepEqual(env.eventos, [{ online: false }])

  env.respuesta = () => ({ cerrar: false, intervalo: 60 })
  await activa.latir()
  assert.deepEqual(env.eventos, [{ online: false }, { online: true }])

  env.win.dispatchEvent(new Event('offline'))
  env.win.dispatchEvent(new Event('online'))
  await flush()
  assert.deepEqual(env.eventos.slice(2), [{ online: false }, { online: true }])
  assert.equal(env.timers.size, 1)
})

test('pagehide cierra la vista por sendBeacon con _token y el tiempo visible', async () => {
  const env = entorno()
  await arrancar(env)
  const id = activa.vista()

  mock.timers.tick(10_000)
  env.doc.visibilityState = 'hidden'
  env.doc.dispatchEvent(new Event('visibilitychange'))
  mock.timers.tick(50_000)
  env.doc.visibilityState = 'visible'
  env.doc.dispatchEvent(new Event('visibilitychange'))
  mock.timers.tick(5_000)
  env.win.dispatchEvent(new Event('pagehide'))

  assert.deepEqual(env.beacons, [{ url: `/telemetria/vista/${id}/fin`, datos: { _token: 'tok', visibleMs: '15000' } }])
  env.win.dispatchEvent(new Event('pagehide'))
  assert.equal(env.beacons.length, 1, 'no cierra dos veces')
})

test('livewire:navigated cierra la vista y abre una suave; ignora el disparo inicial', async () => {
  const env = entorno({ readyState: 'loading' })
  activa = iniciar({ post: env.post, win: env.win, recargar: env.recargar })
  env.doc.dispatchEvent(new Event('livewire:navigated'))
  env.win.dispatchEvent(new Event('load'))
  await env.correrTimers()
  assert.equal(env.de('/vista').length, 1)
  const primera = activa.vista()

  env.win.location.pathname = '/tejido/otra'
  env.doc.dispatchEvent(new Event('livewire:navigated'))
  await flush()

  assert.equal(env.beacons[0].url, `/telemetria/vista/${primera}/fin`)
  const suave = env.de('/vista').at(-1).body
  assert.equal(suave.tipo, 'suave')
  assert.equal(suave.url, '/tejido/otra')
  assert.equal(suave.nav, undefined)
  assert.notEqual(suave.uuid, primera)
})

test('el hidden que sigue al pagehide no manda latido', async () => {
  const env = entorno()
  await arrancar(env)
  const antes = env.de('/latido').length
  env.win.dispatchEvent(new Event('pagehide'))
  env.doc.visibilityState = 'hidden'
  env.doc.dispatchEvent(new Event('visibilitychange'))
  await flush()
  assert.equal(env.de('/latido').length, antes)
})

test('volver desde bfcache abre una vista nueva', async () => {
  const env = entorno()
  await arrancar(env)
  env.win.dispatchEvent(new Event('pagehide'))
  env.win.dispatchEvent(Object.assign(new Event('pageshow'), { persisted: true }))
  await flush()
  assert.equal(env.de('/vista').length, 2)
  assert.equal(env.de('/vista')[1].body.tipo, 'suave')
})

const errorEvento = (props) => Object.assign(new Event('error'), props)

test('window error se reporta con fuente, línea, stack, vista y versión', async () => {
  const env = entorno()
  await arrancar(env)
  const err = new TypeError('x is undefined')
  env.win.dispatchEvent(errorEvento({ message: 'Uncaught TypeError: x is undefined', filename: 'http://towell.local/build/app.js', lineno: 10, colno: 5, error: err }))
  await flush()
  const [e] = env.de('/error')
  assert.equal(e.body.origen, 'js')
  assert.equal(e.body.mensaje, 'Uncaught TypeError: x is undefined')
  assert.equal(e.body.fuente, 'http://towell.local/build/app.js')
  assert.equal(e.body.linea, 10)
  assert.equal(e.body.col, 5)
  assert.equal(e.body.stack, err.stack)
  assert.equal(e.body.url, '/tejido/inventario')
  assert.equal(e.body.vista, activa.vista())
  assert.equal(e.body.version, 'v123')
})

test('ignora Script error., extensiones y ResizeObserver loop', async () => {
  const env = entorno()
  await arrancar(env)
  env.win.dispatchEvent(errorEvento({ message: 'Script error.' }))
  env.win.dispatchEvent(errorEvento({ message: 'boom', filename: 'chrome-extension://abc/x.js' }))
  env.win.dispatchEvent(errorEvento({ message: 'boom', filename: 'moz-extension://abc/x.js' }))
  env.win.dispatchEvent(errorEvento({ message: 'ResizeObserver loop completed with undelivered notifications.' }))
  await flush()
  assert.equal(env.de('/error').length, 0)
  assert.equal(ignorable('Script error'), true)
  assert.equal(ignorable('TypeError', 'http://towell.local/app.js'), false)
})

test('dedupe por mensaje+fuente y máximo 10 errores por página', async () => {
  const env = entorno()
  await arrancar(env)
  for (let i = 0; i < 3; i++) env.win.dispatchEvent(errorEvento({ message: 'repetido', filename: 'a.js' }))
  env.win.dispatchEvent(errorEvento({ message: 'repetido', filename: 'b.js' }))
  for (let i = 0; i < 20; i++) env.win.dispatchEvent(errorEvento({ message: `distinto ${i}` }))
  await flush()
  assert.equal(env.de('/error').length, 10)
  assert.equal(env.de('/error').filter((p) => p.body.mensaje === 'repetido').length, 2)
})

test('unhandledrejection reporta errores de JS pero no rechazos HTTP', async () => {
  const env = entorno()
  await arrancar(env)
  env.win.dispatchEvent(Object.assign(new Event('unhandledrejection'), { reason: new Error('falló la promesa') }))
  env.win.dispatchEvent(Object.assign(new Event('unhandledrejection'), { reason: errorHttp(422) }))
  env.win.dispatchEvent(Object.assign(new Event('unhandledrejection'), { reason: { status: 500, message: 'http.js' } }))
  env.win.dispatchEvent(Object.assign(new Event('unhandledrejection'), { reason: 'texto plano' }))
  await flush()
  assert.deepEqual(env.de('/error').map((p) => p.body.mensaje), ['falló la promesa', 'texto plano'])
})

test('towell:http-error: reporta red caída y 5xx; ignora 4xx, telemetría y navegador sin red', async () => {
  const env = entorno()
  await arrancar(env)
  const http = (detail) => env.win.dispatchEvent(new CustomEvent('towell:http-error', { detail }))
  http({ status: 500, url: '/tejido/guardar?id=4', method: 'post' })
  http({ status: 422, url: '/tejido/guardar', method: 'post' })
  http({ status: 419, url: '/tejido/guardar', method: 'post' })
  http({ status: 503, url: 'http://towell.local/telemetria/latido', method: 'post' })
  http({ status: 0, url: '/api/lista', method: 'get' })
  env.win.navigator.onLine = false
  http({ status: 0, url: '/api/otra', method: 'get' })
  await flush()

  const cuerpos = env.de('/error').map((p) => p.body)
  assert.deepEqual(cuerpos.map((b) => [b.origen, b.mensaje, b.status, b.metodo]), [
    ['red', 'HTTP 500 POST /tejido/guardar', 500, 'POST'],
    ['red', 'HTTP 0 GET /api/lista', 0, 'GET'],
  ])
})

test('Livewire.hook request: reporta fallos 5xx con origen livewire', async () => {
  const env = entorno()
  const fallas = []
  env.win.Livewire = {
    hook(nombre, cb) {
      assert.equal(nombre, 'request')
      cb({ url: '/livewire-abc/update', fail: (f) => fallas.push(f) })
    },
  }
  await arrancar(env)
  env.doc.dispatchEvent(new Event('livewire:init'))
  assert.equal(fallas.length, 1, 'el hook se instala una sola vez')

  fallas[0]({ status: 500 })
  fallas[0]({ status: 419 })
  await flush()
  const errores = env.de('/error')
  assert.equal(errores.length, 1)
  assert.equal(errores[0].body.origen, 'livewire')
  assert.equal(errores[0].body.mensaje, 'Livewire 500 POST /livewire-abc/update')
})

test('Livewire que llega después se engancha en livewire:init', async () => {
  const env = entorno()
  await arrancar(env)
  let instalado = false
  env.win.Livewire = { hook: () => { instalado = true } }
  env.doc.dispatchEvent(new Event('livewire:init'))
  assert.equal(instalado, true)
})

test('un fallo al reportar un error no genera otro reporte', async () => {
  const env = entorno()
  await arrancar(env)
  env.respuesta = () => errorHttp(500)
  env.win.dispatchEvent(errorEvento({ message: 'boom' }))
  await flush()
  await flush()
  assert.equal(env.de('/error').length, 1)
})

test('uuid v4 con randomUUID, con getRandomValues y sin crypto', () => {
  assert.equal(uuid({ randomUUID: () => 'fijo' }), 'fijo')
  assert.match(uuid({ getRandomValues: (b) => globalThis.crypto.getRandomValues(b) }), UUID_V4)
  assert.match(uuid(undefined), UUID_V4)
  assert.notEqual(uuid(undefined), uuid(undefined))
})

test('tiempos tolera entradas vacías y Server-Timing ausente', () => {
  assert.deepEqual(tiempos(undefined), {})
  assert.deepEqual(tiempos({ responseStart: 5, domContentLoadedEventEnd: 0, loadEventEnd: 0, transferSize: 0 }), {
    nav: { ttfb: 5, dom: 0, carga: 0, kb: 0 },
    st: {},
  })
})
