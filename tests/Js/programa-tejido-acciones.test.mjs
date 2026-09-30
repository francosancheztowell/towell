/**
 * HANDOFF 17-02 B1/B2 en Programa Tejido: "Liberar órdenes" por data-accion (sin onclick) y
 * el botón "⋮" del navbar que abre el menú de la fila seleccionada.
 */
import assert from 'node:assert/strict'
import test from 'node:test'

import { installFakeDom } from './utils-fake-dom.mjs'

const document = installFakeDom()

const { enlazarDiasLiberar, enlazarBotonAccionesFila, ID_BOTON_FILA, SIN_SELECCION } = await import('../../resources/js/programa-tejido/acciones.ts')

// El DOM falso no entiende [attr="valor"], isConnected ni getBoundingClientRect: se completan aquí.
const matchesBase = Object.getPrototypeOf(document.body).matches
Object.getPrototypeOf(document.body).matches = function (selector) {
  const m = /^\[([\w-]+)="([^"]*)"\]$/.exec(selector.trim())
  return m ? this.getAttribute(m[1]) === m[2] : matchesBase.call(this, selector)
}
Object.defineProperty(Object.getPrototypeOf(document.body), 'isConnected', { get() { return document.contains(this) } })
Object.getPrototypeOf(document.body).getBoundingClientRect = () => ({ left: 5, bottom: 9 })

function evento(type, props = {}) {
  return {
    type, bubbles: true, defaultPrevented: false,
    preventDefault() { this.defaultPrevented = true }, stopPropagation() {}, ...props,
  }
}

test('el botón con data-accion="dias-liberar" abre el modal de días', () => {
  const boton = document.body.appendChild(document.createElement('button'))
  boton.setAttribute('data-accion', 'dias-liberar')
  const icono = boton.appendChild(document.createElement('i'))
  let abiertos = 0
  const soltar = enlazarDiasLiberar(document, () => { abiertos++ })

  const click = evento('click')
  icono.dispatchEvent(click)
  assert.equal(abiertos, 1)
  assert.ok(click.defaultPrevented)

  document.body.appendChild(document.createElement('button')).dispatchEvent(evento('click'))
  assert.equal(abiertos, 1, 'otros botones no')
  soltar()
  boton.remove()
})

test('el "⋮" abre el menú de la fila seleccionada y avisa sin selección', () => {
  assert.equal(enlazarBotonAccionesFila(() => {}, () => null, () => {}), null, 'sin contenedor no hace nada')

  const contenedor = document.body.appendChild(document.createElement('span'))
  contenedor.id = ID_BOTON_FILA
  const fila = document.body.appendChild(document.createElement('tr'))
  let seleccionada = null
  const abiertos = []
  const avisos = []
  const boton = enlazarBotonAccionesFila((f) => abiertos.push(f), () => seleccionada, (m) => avisos.push(m))

  assert.equal(boton.getAttribute('aria-label'), 'Acciones de la fila')
  assert.equal(enlazarBotonAccionesFila(() => {}, () => null, () => {}), boton, 'idempotente')

  boton.dispatchEvent(evento('click'))
  assert.deepEqual(avisos, [SIN_SELECCION])

  seleccionada = fila
  boton.dispatchEvent(evento('click'))
  assert.deepEqual(abiertos, [fila])

  fila.remove()
  boton.dispatchEvent(evento('click'))
  assert.equal(abiertos.length, 1, 'fila desconectada = sin selección')
  contenedor.remove()
})
