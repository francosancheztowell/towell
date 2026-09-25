import assert from 'node:assert/strict'
import test from 'node:test'

import { unaVez } from '../../resources/js/utils/librerias.ts'

test('unaVez descarga una sola vez aunque la pidan varias pantallas a la vez', async () => {
  let cargas = 0
  const cargar = unaVez(async () => {
    cargas++
    return { lib: true }
  })

  const [a, b] = await Promise.all([cargar(), cargar()])
  const c = await cargar()

  assert.equal(cargas, 1)
  assert.equal(a, b)
  assert.equal(a, c)
})

test('unaVez reintenta si la descarga falló (no se queda con la promesa rechazada)', async () => {
  let intentos = 0
  const cargar = unaVez(async () => {
    intentos++
    if (intentos === 1) throw new Error('sin red')
    return 'ok'
  })

  await assert.rejects(cargar(), /sin red/)
  assert.equal(await cargar(), 'ok')
  assert.equal(intentos, 2)
})
