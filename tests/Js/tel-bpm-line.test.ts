/** Checklist BPM Tejedores (modulos/tejedores/tel-bpm-line/logica.ts). */
import assert from 'node:assert/strict'
import test from 'node:test'

const { CLASES_CELDA, TODAS_LAS_CLASES_CELDA, contarIncompletas, normalizarValor } = await import('../../resources/js/modulos/tejedores/tel-bpm-line/logica.ts')

test('normaliza el valor de la celda; lo desconocido es vacío', () => {
  assert.equal(normalizarValor('OK'), 'OK')
  assert.equal(normalizarValor('M'), 'M')
  assert.equal(normalizarValor(null), '')
  assert.equal(normalizarValor('ok'), '')
})

test('cuenta las celdas sin marcar', () => {
  assert.equal(contarIncompletas(['OK', '', 'X', 'M', 'raro']), 2)
  assert.equal(contarIncompletas([]), 0)
})

test('cada estado tiene sus clases y la lista total las cubre', () => {
  for (const v of ['', 'OK', 'X', 'M'] as const) {
    for (const c of CLASES_CELDA[v]) assert.ok(TODAS_LAS_CLASES_CELDA.includes(c))
  }
})
