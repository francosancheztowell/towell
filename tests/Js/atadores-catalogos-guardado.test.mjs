import assert from 'node:assert/strict'
import test from 'node:test'

import { capturarGuardado } from '../../resources/js/modulos/catalogos-atadores/guardado.ts'

const httpFalso = (respuesta) => ({
  get: async () => respuesta,
  delete: async () => respuesta,
  post: async () => respuesta,
  put: async () => respuesta,
})

test('un alta que devuelve data deja la fila (con su Id) para pintarla', async () => {
  const captura = capturarGuardado(httpFalso({ success: true, data: { Id: 7, Nota1: 'a/b' } }))
  await captura.http.post('/x', { Nota1: 'a/b' })
  assert.deepEqual(captura.tomar(), { Id: 7, Nota1: 'a/b' })
  assert.equal(captura.tomar(), null, 'se entrega una sola vez')
})

test('sin data (Actividades, Máquinas) no hay fila y catalog-base relee como siempre', async () => {
  const captura = capturarGuardado(httpFalso({ success: true, message: 'ok' }))
  await captura.http.put('/x/1', {})
  assert.equal(captura.tomar(), null)
})

test('un guardado que falla no deja la fila del anterior', async () => {
  let fallar = false
  const http = {
    get: async () => ({}),
    delete: async () => ({}),
    post: async () => ({ data: { Id: 1 } }),
    put: async () => {
      if (fallar) throw new Error('500')
      return {}
    },
  }
  const captura = capturarGuardado(http)
  await captura.http.post('/x', {})
  fallar = true
  await assert.rejects(captura.http.put('/x/1', {}))
  assert.equal(captura.tomar(), null)
})

test('GET y DELETE pasan tal cual', async () => {
  const captura = capturarGuardado(httpFalso({ data: { Id: 3 } }))
  assert.deepEqual(await captura.http.get('/x/3'), { data: { Id: 3 } })
  assert.equal(captura.tomar(), null)
})
