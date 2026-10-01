import assert from 'node:assert/strict'
import test from 'node:test'

import {
  aplicarFiltro, atributoOrden, botonesActivos, coincideEstatus, comparar,
  filaVisible, motivoNoIniciar, numeroOrden,
} from '../../resources/js/modulos/atadores/programa/logica.ts'

const ctx = { esTejedor: false, esSupervisor: false, telaresUsuario: [], filtroGlobalActivo: true }
const base = '/atadores/programaatadores'

test('estatus por filtro del modal', () => {
  assert.equal(coincideEstatus('Activo', '1', 'activo', ctx), true)
  assert.equal(coincideEstatus('En Proceso', '1', 'activo-proceso', ctx), true)
  assert.equal(coincideEstatus('Calificado', '1', 'calificados', ctx), true)
  assert.equal(coincideEstatus('Autorizado', '1', 'en-proceso', ctx), false)
  assert.equal(coincideEstatus('Activo', '1', 'desconocido', ctx), false)
})

test('terminados: el tejedor solo ve los de sus telares', () => {
  const tejedor = { ...ctx, esTejedor: true, telaresUsuario: ['15'] }
  assert.equal(coincideEstatus('Terminado', '15', 'terminados', tejedor), true)
  assert.equal(coincideEstatus('Terminado', '16', 'terminados', tejedor), false)
  assert.equal(coincideEstatus('Terminado', '16', 'terminados', { ...tejedor, telaresUsuario: [] }), true)
})

test('fila visible: estatus en unión y columnas en intersección', () => {
  const fila = { status: 'En Proceso', telar: '201', valores: { telar: '201', 'no-julio': 'J-101' } }
  assert.equal(filaVisible(fila, [], {}, ctx), true)
  assert.equal(filaVisible(fila, ['activo', 'en-proceso'], {}, ctx), true)
  assert.equal(filaVisible(fila, ['calificados'], {}, ctx), false)
  assert.equal(filaVisible(fila, [], { telar: '20', 'no-julio': 'j-1' }, ctx), true, 'contiene, sin mayúsculas')
  assert.equal(filaVisible(fila, [], { telar: '30' }, ctx), false)
})

test('orden: columnas numéricas con vacíos al principio', () => {
  assert.equal(atributoOrden('julio'), 'data-no-julio')
  assert.equal(atributoOrden('hr-paro'), 'data-hora-paro')
  assert.equal(atributoOrden('metros'), 'data-metros')
  assert.equal(numeroOrden(''), -999999)
  assert.equal(numeroOrden('x'), -999999)
  assert.ok(comparar('10', '9', true, 'asc') > 0)
  assert.ok(comparar('J-10', 'J-9', false, 'asc') > 0, 'orden natural')
  assert.ok(comparar('10', '9', true, 'desc') < 0)
})

test('filtros del modal: cuándo se navega y cuándo se filtra en el navegador', () => {
  assert.deepEqual(aplicarFiltro('autorizados', [], true, base), { navegar: `${base}?filtro=autorizados` })
  assert.deepEqual(aplicarFiltro('autorizados', ['autorizados'], true, base), { navegar: `${base}?filtro=todos` })
  assert.deepEqual(aplicarFiltro('autorizados', ['autorizados'], false, base), { navegar: base })
  assert.deepEqual(aplicarFiltro('autorizados', ['autorizados', 'activo'], true, base), { filtros: ['activo'] })
  assert.deepEqual(aplicarFiltro('todos', ['activo'], true, base), { filtros: [] })
  assert.deepEqual(aplicarFiltro('todos', [], false, base), { navegar: `${base}?filtro=todos` })
  assert.deepEqual(aplicarFiltro('activo', [], true, base), { filtros: ['activo'] })
  assert.deepEqual(aplicarFiltro('activo', ['activo'], true, base), { filtros: [] })
  assert.deepEqual(aplicarFiltro('en-proceso', ['activo'], false, base), { navegar: `${base}?filtro=todos&vista=activo%2Cen-proceso` })
  assert.deepEqual(aplicarFiltro('activo', ['activo'], false, base), { navegar: `${base}?filtro=todos` })
})

test('botones de estatus activos', () => {
  assert.deepEqual(botonesActivos(['activo'], ctx), ['activo'])
  assert.deepEqual(botonesActivos([], { esSupervisor: true, filtroGlobalActivo: false }), ['calificados', 'activo', 'en-proceso'])
  assert.deepEqual(botonesActivos([], { esSupervisor: true, filtroGlobalActivo: true }), ['todos'])
})

test('motivo para no iniciar', () => {
  assert.match(motivoNoIniciar('Activo', '', 'J', 'O'), /hora de paro/)
  assert.equal(motivoNoIniciar('Autorizado', '', 'J', 'O'), null, 'autorizado solo se consulta')
  assert.match(motivoNoIniciar('Activo', '08:00', '', 'O'), /No. Julio/)
  assert.equal(motivoNoIniciar('Activo', '08:00', 'J', 'O'), null)
})
