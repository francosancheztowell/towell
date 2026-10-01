/** Filtros por columna reutilizables (componentes/tabla-columnas.ts): la regla de coincidencia. */
import assert from 'node:assert/strict'
import test from 'node:test'

const { celdasCoinciden, normalizar } = await import('../../resources/js/componentes/tabla-columnas.ts')

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
