/**
 * HANDOFF 17-02 B1/B2 en Programa Tejido: "Liberar órdenes" por data-accion (sin onclick) y
 * el botón "⋮" del navbar que abre el menú de la fila seleccionada.
 */
import assert from 'node:assert/strict'
import test from 'node:test'

// DOM falso de tests/Js (sin tipos: es de otra área). Si se migra a .ts, cambiar la extensión aquí.
// @ts-expect-error -- utils-fake-dom.mjs no trae declaración de tipos
import { installFakeDom } from './utils-fake-dom.mjs'

// DOM falso sin tipos: se usa como any a propósito.
const document: any = installFakeDom()

const { enlazarDiasLiberar } = await import('../../resources/js/programa-tejido/acciones.ts')

// El DOM falso no entiende [attr="valor"], isConnected ni getBoundingClientRect: se completan aquí.
const matchesBase = Object.getPrototypeOf(document.body).matches
Object.getPrototypeOf(document.body).matches = function (this: { getAttribute(n: string): string | null }, selector: string) {
  const m = /^\[([\w-]+)="([^"]*)"\]$/.exec(selector.trim())
  return m ? this.getAttribute(m[1] ?? '') === m[2] : matchesBase.call(this, selector)
}
Object.defineProperty(Object.getPrototypeOf(document.body), 'isConnected', { get(this: unknown) { return document.contains(this) } })
Object.getPrototypeOf(document.body).getBoundingClientRect = () => ({ left: 5, bottom: 9 })

function evento(type: string, props: Record<string, unknown> = {}): Event {
  return {
    type, bubbles: true, defaultPrevented: false,
    preventDefault(this: { defaultPrevented: boolean }) { this.defaultPrevented = true }, stopPropagation() {}, ...props,
  } as unknown as Event
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
