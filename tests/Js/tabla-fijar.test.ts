/** Fijar columnas (componentes/tabla-fijar.ts): las reglas que pegan las columnas a la izquierda. */
import assert from 'node:assert/strict'
import test from 'node:test'

const { cssColumnasFijas } = await import('../../resources/js/componentes/tabla-fijar.ts')

test('cada fija se pega a la izquierda tras las fijas anteriores; la última lleva sombra', () => {
  const css = cssColumnasFijas('costos-cuotas', [
    { n: 1, left: 0 },
    { n: 3, left: 120 },
  ])

  assert.match(css, /table\[data-fijar-columnas="costos-cuotas"\] tr > :nth-child\(1\) \{ position: sticky; left: 0px; z-index: 5; \}/)
  assert.match(css, /tr > :nth-child\(3\) \{ position: sticky; left: 120px; z-index: 5; box-shadow:/)
  assert.match(css, /tr\[aria-selected='true'\] > :nth-child\(3\) \{ background-color: var\(--color-blue-500\)/, 'la fila seleccionada sigue azul')
})

test('sin fijas no hay reglas; la clave no puede romper el selector', () => {
  assert.equal(cssColumnasFijas('x', []), '')
  assert.ok(!cssColumnasFijas('a"] , body {', [{ n: 1, left: 0 }]).includes('"] , body {"'))
})
