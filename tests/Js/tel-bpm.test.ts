/** BPM Tejedores (modulos/tejedores/tel-bpm/logica.ts): alcance de la tabla. */
import assert from 'node:assert/strict'
import test from 'node:test'

const { alcanceInicial, alternar, filaVisible, mensajeSinResultados } = await import('../../resources/js/modulos/tejedores/tel-bpm/logica.ts')

test('alcance inicial: el supervisor ve todos; el resto, sus folios', () => {
  assert.deepEqual(alcanceInicial(true), { autorizados: false, misFolios: false, todos: true })
  assert.deepEqual(alcanceInicial(false), { autorizados: false, misFolios: true, todos: false })
})

test('"Todos" apaga los otros; encender otro apaga "Todos"', () => {
  const todos = alternar(alcanceInicial(false), 'todos')
  assert.deepEqual(todos, { autorizados: false, misFolios: false, todos: true })
  assert.equal(alternar(todos, 'autorizados').todos, false)
  assert.equal(alternar(todos, 'misFolios').todos, false)
})

test('Autorizados ocultos salvo con "Autorizados" o "Todos"; mis folios por nombre', () => {
  const ana = 'Ana Pérez'
  const solo = { autorizados: false, misFolios: false, todos: false }
  assert.equal(filaVisible({ status: 'Autorizado', nombreRecibe: ana }, solo, ana), false)
  assert.equal(filaVisible({ status: 'Autorizado', nombreRecibe: ana }, { ...solo, autorizados: true }, ana), true)
  assert.equal(filaVisible({ status: 'Autorizado', nombreRecibe: 'x' }, { ...solo, todos: true }, ana), true)
  assert.equal(filaVisible({ status: 'Creado', nombreRecibe: ' ana pérez ' }, alcanceInicial(false), ana), true)
  assert.equal(filaVisible({ status: 'Creado', nombreRecibe: 'Beto' }, alcanceInicial(false), ana), false)
})

test('mensaje sin resultados', () => {
  assert.equal(mensajeSinResultados(alcanceInicial(false)), 'No tienes folios asignados')
  assert.equal(mensajeSinResultados(alcanceInicial(true)), 'Sin resultados con los filtros aplicados')
})
