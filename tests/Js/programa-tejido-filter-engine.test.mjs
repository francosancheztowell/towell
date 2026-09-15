import assert from 'node:assert/strict'
import test from 'node:test'

import { checkFilterMatch, dateInRange, groupFiltersByColumn, rowMatchesCustomFilters } from '../../resources/js/programa-tejido/filter-engine.js'

test('dateInRange incluye los dos extremos del rango', () => {
  // El bug viejo: new Date('2026-09-15') es medianoche UTC, o sea el 14 en
  // America/Mexico_City, asi que el 'hasta' quedaba fuera y el 'desde' de mas.
  assert.equal(dateInRange('2026-09-15 07:30:00', '2026-09-15', '2026-09-15'), true)
  assert.equal(dateInRange('2026-09-14 23:59:59', '2026-09-15', '2026-09-20'), false)
  assert.equal(dateInRange('2026-09-21 00:00:00', '2026-09-15', '2026-09-20'), false)
})

test('dateInRange acepta rangos abiertos y descarta la fecha vacia', () => {
  assert.equal(dateInRange('2026-01-01', '2025-12-31', null), true)
  assert.equal(dateInRange('2026-01-01', null, '2026-01-01'), true)
  assert.equal(dateInRange('', '2026-01-01', null), false)
})

test('checkFilterMatch normaliza mayusculas y espacios en los dos lados', () => {
  assert.equal(checkFilterMatch(' karl mayer ', { operator: 'equals', value: 'KARL MAYER' }), true)
  assert.equal(checkFilterMatch('smi 401', { operator: 'starts', value: 'SMI' }), true)
  assert.equal(checkFilterMatch('', { operator: 'empty', value: '' }), true)
  assert.equal(checkFilterMatch('algo', { operator: 'notEmpty', value: '' }), true)
  assert.equal(checkFilterMatch('smi 401', { operator: 'not', value: 'km' }), true)
  assert.equal(checkFilterMatch('smi 401', { operator: 'contains', value: '401' }), true)
})

test('rowMatchesCustomFilters: OR dentro de la columna, AND entre columnas', () => {
  const filtros = groupFiltersByColumn([
    { column: 'SalonTejidoId', operator: 'equals', value: 'SMI' },
    { column: 'SalonTejidoId', operator: 'equals', value: 'KM' },
    { column: 'NoTelarId', operator: 'equals', value: '401' },
  ])

  assert.equal(rowMatchesCustomFilters({ SalonTejidoId: 'KM', NoTelarId: '401' }, filtros), true)
  assert.equal(rowMatchesCustomFilters({ SalonTejidoId: 'JAC', NoTelarId: '401' }, filtros), false)
  assert.equal(rowMatchesCustomFilters({ SalonTejidoId: 'SMI', NoTelarId: '402' }, filtros), false)
  assert.equal(rowMatchesCustomFilters({ SalonTejidoId: 'SMI' }, filtros), false)
})
