import assert from 'node:assert/strict'
import test from 'node:test'

import {
  empleadoRepetido, fechaHoraLocal, nombreTarjeta, textoEmpleado,
} from '../../resources/js/modulos/atadores/comun/proceso-km-logica.ts'

test('texto de la opción de empleado', () => {
  assert.equal(textoEmpleado('1001', 'Ana'), '1001 - Ana')
  assert.equal(textoEmpleado('1001', ''), '1001')
})

test('una persona solo en una fila de la tarjeta', () => {
  assert.equal(empleadoRepetido(['1001', '', ''], 2, '1001'), true)
  assert.equal(empleadoRepetido(['1001', '', ''], 1, '1001'), false, 'su propia fila no cuenta')
  assert.equal(empleadoRepetido(['1001', '1002', ''], 3, ' 1002 '), true)
  assert.equal(empleadoRepetido(['', '', ''], 1, ''), false)
})

test('fecha de inicio en formato datetime-local', () => {
  assert.equal(fechaHoraLocal(new Date(2026, 0, 5, 7, 3)), '2026-01-05T07:03')
})

test('nombre de la tarjeta', () => {
  assert.equal(nombreTarjeta('enhebrado'), 'Enhebrado')
  assert.equal(nombreTarjeta('montado'), 'Montado')
})
