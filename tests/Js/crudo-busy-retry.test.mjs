import assert from 'node:assert/strict'
import test from 'node:test'

import { BUSY_RETRIES, withBusyRetry } from '../../resources/js/crudo/busy-retry.ts'

const noWait = async () => {}
const matchesLivewire = (url) => url.includes('/livewire/update')

test('un 503 del servidor ocupado se reintenta hasta que responde', async () => {
  const statuses = [503, 503, 200]
  let calls = 0
  const fetchMock = async () => ({ status: statuses[calls++] })

  const response = await withBusyRetry(fetchMock, matchesLivewire, noWait)('/livewire/update')

  assert.equal(response.status, 200)
  assert.equal(calls, 3)
})

test('se rinde tras agotar los reintentos y devuelve el último 503', async () => {
  let calls = 0
  const fetchMock = async () => {
    calls++

    return { status: 503 }
  }

  const response = await withBusyRetry(fetchMock, matchesLivewire, noWait)('/livewire/update')

  assert.equal(response.status, 503)
  assert.equal(calls, BUSY_RETRIES + 1)
})

test('otros errores y otras urls no se reintentan', async () => {
  let calls = 0
  const fetchMock = async () => {
    calls++

    return { status: 500 }
  }
  const retrying = withBusyRetry(fetchMock, matchesLivewire, noWait)

  assert.equal((await retrying('/livewire/update')).status, 500)
  assert.equal(calls, 1)

  await retrying('/Crudo/auditorias/telar/201/hoy')
  assert.equal(calls, 2)
})

test('un Request ya construido no se reintenta: su body solo se puede leer una vez', async () => {
  let calls = 0
  const fetchMock = async () => {
    calls++

    return { status: 503 }
  }

  const peticion = { url: 'http://localhost/livewire/update' }
  Object.setPrototypeOf(peticion, Request.prototype)

  await withBusyRetry(fetchMock, matchesLivewire, noWait)(peticion)

  assert.equal(calls, 1)
})
