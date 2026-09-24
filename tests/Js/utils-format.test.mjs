import assert from 'node:assert/strict'
import test from 'node:test'

import { debounce, escapeHtml, formatDate, formatDateTime, formatNumber } from '../../resources/js/utils/format.ts'

test('escapeHtml neutraliza los cinco caracteres peligrosos y trata null como vacío', () => {
  assert.equal(escapeHtml(`<img src=x onerror="a('b')">&`), '&lt;img src=x onerror=&quot;a(&#39;b&#39;)&quot;&gt;&amp;')
  assert.equal(escapeHtml(null), '')
  assert.equal(escapeHtml(undefined), '')
  assert.equal(escapeHtml(42), '42')
})

test('debounce ejecuta una sola vez con los últimos argumentos', (t) => {
  t.mock.timers.enable({ apis: ['setTimeout'] })
  const calls = []
  const fn = debounce((...args) => calls.push(args), 200)

  fn(1)
  fn(2)
  t.mock.timers.tick(199)
  assert.deepEqual(calls, [])
  fn(3)
  t.mock.timers.tick(200)
  assert.deepEqual(calls, [[3]])
})

test('debounce.cancel descarta la llamada pendiente', (t) => {
  t.mock.timers.enable({ apis: ['setTimeout'] })
  let calls = 0
  const fn = debounce(() => calls++, 100)

  fn()
  fn.cancel()
  t.mock.timers.tick(500)
  assert.equal(calls, 0)
})

test('formatNumber usa separadores es-MX y respeta los decimales pedidos', () => {
  assert.equal(formatNumber(1234567.891), '1,234,567.89')
  assert.equal(formatNumber(1234.5, 2), '1,234.50')
  assert.equal(formatNumber('1,500', 0), '1,500')
  assert.equal(formatNumber(0), '0')
})

test('formatNumber devuelve vacío para null, vacío o no numérico', () => {
  assert.equal(formatNumber(null), '')
  assert.equal(formatNumber(''), '')
  assert.equal(formatNumber('abc'), '')
  assert.equal(formatNumber(Number.NaN), '')
})

test('formatDate muestra fechas de calendario sin corrimiento de zona', () => {
  assert.equal(formatDate('2026-01-05'), '05/01/2026')
})

test('formatDate y formatDateTime interpretan instantes en hora de Ciudad de México', () => {
  // 03:30 UTC del 6 de enero = 21:30 del 5 de enero en CDMX (UTC-6).
  assert.equal(formatDate('2026-01-06T03:30:00Z'), '05/01/2026')
  assert.match(formatDateTime('2026-01-06T03:30:00Z'), /^05\/01\/2026,? 21:30$/)
})

test('formatDate devuelve vacío para valores inválidos', () => {
  assert.equal(formatDate(null), '')
  assert.equal(formatDate(''), '')
  assert.equal(formatDate('no es fecha'), '')
  assert.equal(formatDateTime(new Date('x')), '')
})
