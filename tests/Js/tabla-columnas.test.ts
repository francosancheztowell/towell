/** Filtros por columna reutilizables (componentes/tabla-columnas.ts): la regla de coincidencia. */
import assert from 'node:assert/strict'
import test from 'node:test'

const { celdasCoinciden, normalizar, partesFiltro } = await import('../../resources/js/componentes/tabla-columnas.ts')

test('normaliza mayúsculas, acentos y espacios', () => {
  assert.equal(normalizar('  José PÉREZ '), 'jose perez')
})

test('contiene, sin acentos, AND entre columnas; vacío = sin filtro', () => {
  const fila = ['URD-001', 'Terminado', 'José Pérez']
  assert.ok(celdasCoinciden(fila, ['', '', '']))
  assert.ok(celdasCoinciden(fila, ['001', '', 'jose']))
  assert.ok(celdasCoinciden(fila, ['', 'TERM', '  ']))
  assert.equal(celdasCoinciden(fila, ['001', 'creado', '']), false)
  assert.equal(celdasCoinciden(fila, ['', '', '', 'x']), false, 'columna inexistente no coincide')
})

test('como en AX: comas = varios valores (OR) dentro de la columna', () => {
  assert.deepEqual(partesFiltro(' 2229, 2039,,2049 '), ['2229', '2039', '2049'])
  assert.deepEqual(partesFiltro(' , '), [], 'solo comas = sin filtro')
  const filtro = ['2229,2039,2049']
  assert.ok(celdasCoinciden(['2039'], filtro))
  assert.ok(celdasCoinciden(['2049'], filtro))
  assert.equal(celdasCoinciden(['1500'], filtro), false)
  assert.ok(celdasCoinciden(['2039', 'Tejido'], ['2229,2039', 'tej']), 'OR dentro de la columna, AND entre columnas')
  assert.equal(celdasCoinciden(['2039', 'Urdido'], ['2229,2039', 'tej']), false)
})
