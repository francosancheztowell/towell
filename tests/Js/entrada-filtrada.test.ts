/** Campos que solo admiten lo suyo (componentes/entrada-filtrada.ts). */
import assert from 'node:assert/strict'
import test from 'node:test'

const { filtrarEntrada } = await import('../../resources/js/componentes/entrada-filtrada.ts')

test('decimal: dígitos y un solo punto, coma como punto, tope de decimales y enteros', () => {
  const op = { decimales: 4, enteros: 14 }
  assert.equal(filtrarEntrada('12abc', 'decimal', op), '12')
  assert.equal(filtrarEntrada('1,5', 'decimal', op), '1.5')
  assert.equal(filtrarEntrada('1.2.3', 'decimal', op), '1.23')
  assert.equal(filtrarEntrada('-5', 'decimal', op), '5', 'sin negativos')
  assert.equal(filtrarEntrada('1.234567', 'decimal', op), '1.2345')
  assert.equal(filtrarEntrada('1234567890123456', 'decimal', op), '12345678901234')
  assert.equal(filtrarEntrada('3,000.50', 'decimal', op), '3.0005', 'la coma de miles se vuelve punto: se nota y se corrige')
  assert.equal(filtrarEntrada('16.', 'decimal', op), '16.', 'deja escribir el punto')
})

test('entero y texto', () => {
  assert.equal(filtrarEntrada('20a26', 'entero'), '2026')
  assert.equal(filtrarEntrada('Urdido<script>', 'texto'), 'Urdidoscript')
  assert.equal(filtrarEntrada('Tejido  Plano-2', 'texto'), 'Tejido Plano-2')
  assert.equal(filtrarEntrada('Peñasco Ácido', 'texto'), 'Peñasco Ácido')
})
