import assert from 'node:assert/strict'
import { test } from 'node:test'

import { nuevas } from '../../scripts/calidad.mjs'

const v = (rule, method, description = '', ruleSet = 'Code Size Rules') => ({
  rule,
  ruleSet,
  class: 'App\\Foo',
  method,
  description,
  beginLine: 1,
})

test('codesize: el mismo metodo que ya violaba y crece no es nuevo', () => {
  const antes = [v('ExcessiveMethodLength', 'grande', 'has 120 lines')]
  const despues = [v('ExcessiveMethodLength', 'grande', 'has 180 lines')]
  assert.deepEqual(nuevas(antes, despues), [])
})

test('codesize: un metodo nuevo por encima del umbral si es nuevo', () => {
  const antes = [v('CyclomaticComplexity', 'viejo', 'of 12')]
  const despues = [v('CyclomaticComplexity', 'viejo', 'of 12'), v('CyclomaticComplexity', 'nuevo', 'of 11')]
  assert.deepEqual(nuevas(antes, despues).map((x) => x.method), ['nuevo'])
})

test('unusedcode: la descripcion distingue variables del mismo metodo', () => {
  const u = (nombre) => v('UnusedLocalVariable', 'm', `Avoid unused local variables such as '${nombre}'.`, 'Unused Code Rules')
  assert.deepEqual(nuevas([u('$a')], [u('$a'), u('$b')]).map((x) => x.description), [u('$b').description])
})

test('una violacion repetida solo se perdona tantas veces como estaba', () => {
  const x = v('UnusedPrivateMethod', null, "such as 'f'.", 'Unused Code Rules')
  assert.equal(nuevas([x], [x, x]).length, 1)
})

test('archivo nuevo: todo cuenta', () => {
  assert.equal(nuevas([], [v('NPathComplexity', 'm', 'of 300')]).length, 1)
})
