import assert from 'node:assert/strict'
import test from 'node:test'

import { installFakeDom } from './utils-fake-dom.mjs'

const document = installFakeDom()
const { delegate, onReady, qs, qsa } = await import('../../resources/js/utils/dom.ts')

function tabla() {
  const root = document.createElement('div')
  for (let i = 0; i < 3; i++) {
    const fila = document.createElement('tr')
    fila.className = 'fila'
    fila.setAttribute('data-id', String(i))
    const boton = document.createElement('button')
    boton.className = 'borrar'
    fila.appendChild(boton)
    root.appendChild(fila)
  }
  document.body.appendChild(root)

  return root
}

test('qs y qsa buscan dentro de la raíz; qsa devuelve un array', () => {
  const root = tabla()

  assert.equal(qs('.fila', root).getAttribute('data-id'), '0')
  assert.ok(Array.isArray(qsa('.fila', root)))
  assert.deepEqual(qsa('.fila', root).map((f) => f.getAttribute('data-id')), ['0', '1', '2'])
  assert.equal(qs('.inexistente', root), null)
})

test('delegate entrega el elemento que coincide, incluso si se agrega después', () => {
  const root = tabla()
  const vistos = []
  delegate(root, 'click', '.fila', (_event, fila) => vistos.push(fila.getAttribute('data-id')))

  root.children[1].children[0].dispatchEvent({ type: 'click', bubbles: true })

  const nueva = document.createElement('tr')
  nueva.className = 'fila'
  nueva.setAttribute('data-id', 'nueva')
  root.appendChild(nueva)
  nueva.dispatchEvent({ type: 'click', bubbles: true })

  assert.deepEqual(vistos, ['1', 'nueva'])
})

test('delegate ignora coincidencias fuera de la raíz y se puede quitar', () => {
  const root = tabla()
  const fuera = document.createElement('div')
  fuera.className = 'fila'
  fuera.appendChild(root)
  document.body.appendChild(fuera)

  let llamadas = 0
  const quitar = delegate(root, 'click', '.fila', () => llamadas++)

  root.dispatchEvent({ type: 'click', bubbles: true })
  assert.equal(llamadas, 0, 'el ancestro .fila está fuera de la raíz')

  quitar()
  root.children[0].dispatchEvent({ type: 'click', bubbles: true })
  assert.equal(llamadas, 0)
  assert.equal(root.listenerCount('click'), 0)
})

test('onReady corre de inmediato con el DOM listo y espera DOMContentLoaded si no', () => {
  let corridas = 0
  onReady(() => corridas++)
  assert.equal(corridas, 1)

  document.readyState = 'loading'
  onReady(() => corridas++)
  assert.equal(corridas, 1)
  document.dispatchEvent({ type: 'DOMContentLoaded' })
  assert.equal(corridas, 2)
  document.readyState = 'complete'
})
