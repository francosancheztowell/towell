import assert from 'node:assert/strict'
import test from 'node:test'

import axios, { AxiosError } from 'axios'
import Swal from 'sweetalert2'

// Entorno de navegador mínimo: meta CSRF, window con eventos y location.reload.
let reloads = 0
globalThis.document = {
  querySelector: (selector) =>
    selector === 'meta[name="csrf-token"]' ? { getAttribute: () => 'token-fresco' } : null,
}
globalThis.window = Object.assign(new EventTarget(), {
  location: { reload: () => reloads++ },
})

let alerts = []
Swal.fire = async (options) => {
  alerts.push(options)

  return { isConfirmed: true }
}

const { http, HttpError, HTTP_ERROR_EVENT } = await import('../../resources/js/utils/http.ts')

/** Adapter de axios que responde `status` con `data` y guarda la config enviada. */
function adapter(status, data, sent = []) {
  return async (config) => {
    sent.push(config)
    const response = { data, status, statusText: '', headers: {}, config, request: {} }
    if (status >= 200 && status < 300) return response
    throw new AxiosError(`Request failed with status code ${status}`, 'ERR_BAD_RESPONSE', config, {}, response)
  }
}

function captureErrors() {
  const events = []
  const listener = (event) => events.push(event.detail)
  window.addEventListener(HTTP_ERROR_EVENT, listener)

  return { events, stop: () => window.removeEventListener(HTTP_ERROR_EVENT, listener) }
}

test('devuelve directamente el cuerpo JSON', async () => {
  const data = await http.get('/catalogo', { adapter: adapter(200, { ok: true }) })

  assert.deepEqual(data, { ok: true })
})

test('manda siempre Accept JSON, X-Requested-With y el CSRF de la meta', async () => {
  const sent = []
  await http.post('/guardar', { a: 1 }, { adapter: adapter(200, {}, sent), headers: { 'X-Extra': 'si' } })
  await http.upload('/subir', new FormData(), { adapter: adapter(200, {}, sent) })

  for (const config of sent) {
    assert.equal(config.headers.get('Accept'), 'application/json')
    assert.equal(config.headers.get('X-CSRF-TOKEN'), 'token-fresco')
    assert.equal(config.headers.get('X-Requested-With'), 'XMLHttpRequest')
  }
  assert.equal(sent[0].headers.get('X-Extra'), 'si')
})

test('un 422 lanza HttpError con status, data y errores de validación', async () => {
  const body = { message: 'Datos inválidos', errors: { nombre: ['Requerido'] } }

  await assert.rejects(http.put('/x/1', {}, { adapter: adapter(422, body) }), (err) => {
    assert.ok(err instanceof HttpError)
    assert.equal(err.message, 'Datos inválidos')
    assert.equal(err.status, 422)
    assert.deepEqual(err.data, body)
    assert.deepEqual(err.errors, { nombre: ['Requerido'] })
    assert.ok(err.original instanceof AxiosError)

    return true
  })
})

test('todo fallo emite towell:http-error con status, ruta sin query y método', async () => {
  const { events, stop } = captureErrors()

  await assert.rejects(http.delete('/catalogo/5?force=1#x', { adapter: adapter(500, 'Server Error') }))
  await assert.rejects(http.patch('/catalogo/5', {}, { adapter: adapter(404, { message: 'No existe' }) }))
  stop()

  assert.deepEqual(events, [
    { status: 500, url: '/catalogo/5', method: 'DELETE' },
    { status: 404, url: '/catalogo/5', method: 'PATCH' },
  ])
})

test('un fallo de red se reporta con status 0 y mensaje genérico legible', async () => {
  const { events, stop } = captureErrors()
  const network = async (config) => {
    throw new AxiosError('Network Error', 'ERR_NETWORK', config, {})
  }

  await assert.rejects(http.get('/lento', { adapter: network }), (err) => err.status === 0 && err.message === 'Network Error')
  stop()

  assert.deepEqual(events, [{ status: 0, url: '/lento', method: 'GET' }])
})

test('una petición cancelada no se reporta como error', async () => {
  const { events, stop } = captureErrors()
  const cancelled = async () => {
    throw new axios.CanceledError()
  }

  await assert.rejects(http.get('/detalle', { adapter: cancelled }))
  stop()

  assert.deepEqual(events, [])
})

test('419 avisa y recarga una sola vez por página, y el error llega al caller', async () => {
  alerts = []
  reloads = 0
  const { events, stop } = captureErrors()

  await assert.rejects(http.post('/a', {}, { adapter: adapter(419, { message: 'CSRF token mismatch.' }) }), (err) => err.status === 419)
  await assert.rejects(http.post('/b', {}, { adapter: adapter(419, { message: 'CSRF token mismatch.' }) }), (err) => err.status === 419)
  await new Promise((resolve) => setImmediate(resolve))
  stop()

  assert.equal(alerts.length, 1)
  assert.equal(alerts[0].title, 'Sesión expirada')
  assert.equal(reloads, 1)
  assert.equal(events.length, 2, 'cada 419 sigue emitiendo su evento')
})
