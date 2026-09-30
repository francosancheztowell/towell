import assert from 'node:assert/strict'
import test from 'node:test'

import { installFakeDom } from './utils-fake-dom.mjs'

const document = installFakeDom()

const { accionesTactiles, botonAcciones, DEMORA_LARGO_MS } = await import('../../resources/js/utils/acciones-tactiles.ts')

/** Tabla con una fila `tr.fila` y una celda adentro; devuelve { tabla, fila, celda }. */
function tabla() {
  const t = document.body.appendChild(document.createElement('table'))
  const fila = t.appendChild(document.createElement('tr'))
  fila.className = 'fila'
  const celda = fila.appendChild(document.createElement('td'))

  return { tabla: t, fila, celda }
}

function evento(type, props = {}) {
  return {
    type,
    bubbles: true,
    defaultPrevented: false,
    propagationStopped: false,
    preventDefault() { this.defaultPrevented = true },
    stopPropagation() { this.propagationStopped = true },
    ...props,
  }
}

test('mantener el dedo abre las acciones de la fila una vez y se come el click al soltar', (t) => {
  t.mock.timers.enable({ apis: ['setTimeout', 'Date'] })
  const { tabla: root, fila, celda } = tabla()
  const abiertos = []
  const soltar = accionesTactiles(root, '.fila', (el, pos, origen) => abiertos.push({ el, pos, origen }))

  celda.dispatchEvent(evento('pointerdown', { pointerType: 'touch', button: 0, clientX: 10, clientY: 20 }))
  t.mock.timers.tick(DEMORA_LARGO_MS - 1)
  assert.equal(abiertos.length, 0, 'todavía no')
  t.mock.timers.tick(1)
  assert.deepEqual(abiertos, [{ el: fila, pos: { x: 10, y: 20 }, origen: 'largo' }])

  // Android dispara su propio contextmenu después: no se abre dos veces.
  const nativo = evento('contextmenu', { clientX: 10, clientY: 20 })
  celda.dispatchEvent(nativo)
  assert.equal(abiertos.length, 1)
  assert.ok(nativo.defaultPrevented)

  celda.dispatchEvent(evento('pointerup'))
  const click = evento('click')
  celda.dispatchEvent(click)
  assert.ok(click.defaultPrevented && click.propagationStopped, 'el click de soltar no activa la fila')

  soltar()
  root.remove()
})

test('mover el dedo (scroll) o soltarlo antes de tiempo cancela el long-press', (t) => {
  t.mock.timers.enable({ apis: ['setTimeout', 'Date'] })
  const { tabla: root, celda } = tabla()
  const abiertos = []
  accionesTactiles(root, '.fila', () => abiertos.push(1))

  celda.dispatchEvent(evento('pointerdown', { pointerType: 'touch', button: 0, clientX: 0, clientY: 0 }))
  celda.dispatchEvent(evento('pointermove', { clientX: 0, clientY: 30 }))
  t.mock.timers.tick(DEMORA_LARGO_MS * 2)

  celda.dispatchEvent(evento('pointerdown', { pointerType: 'pen', button: 0, clientX: 0, clientY: 0 }))
  t.mock.timers.tick(100)
  celda.dispatchEvent(evento('pointerup'))
  t.mock.timers.tick(DEMORA_LARGO_MS * 2)

  const click = evento('click')
  celda.dispatchEvent(click)
  assert.equal(abiertos.length, 0)
  assert.equal(click.defaultPrevented, false, 'un toque normal sigue siendo click')
  root.remove()
})

test('con ratón: el clic derecho abre el mismo menú y el long-press no aplica', (t) => {
  t.mock.timers.enable({ apis: ['setTimeout', 'Date'] })
  const { tabla: root, fila, celda } = tabla()
  const abiertos = []
  accionesTactiles(root, '.fila', (el, pos, origen) => abiertos.push({ el, pos, origen }))

  celda.dispatchEvent(evento('pointerdown', { pointerType: 'mouse', button: 0, clientX: 1, clientY: 1 }))
  t.mock.timers.tick(DEMORA_LARGO_MS * 2)
  assert.equal(abiertos.length, 0)

  const derecho = evento('contextmenu', { clientX: 5, clientY: 6 })
  celda.dispatchEvent(derecho)
  assert.ok(derecho.defaultPrevented, 'sin el menú del navegador')
  assert.deepEqual(abiertos, [{ el: fila, pos: { x: 5, y: 6 }, origen: 'contextmenu' }])

  // Fuera del selector no hace nada.
  const fuera = evento('contextmenu')
  root.dispatchEvent(fuera)
  assert.equal(fuera.defaultPrevented, false)
  root.remove()
})

test('el botón ⋮ es accesible, abre las acciones y no se duplica', () => {
  const { tabla: root, fila, celda } = tabla()
  celda.getBoundingClientRect = undefined
  const abiertos = []
  const abrir = (el, pos, origen) => abiertos.push({ el, pos, origen })

  const boton = botonAcciones(fila, abrir, { contenedor: celda })
  boton.getBoundingClientRect = () => ({ left: 40, bottom: 60 })
  assert.equal(boton.getAttribute('aria-label'), 'Más acciones')
  assert.equal(boton.getAttribute('aria-haspopup'), 'menu')
  assert.equal(boton.textContent, '⋮')
  assert.equal(botonAcciones(fila, abrir, { contenedor: celda }), boton)
  assert.equal(celda.children.length, 1)

  const click = evento('click')
  boton.dispatchEvent(click)
  assert.ok(click.propagationStopped, 'no dispara el click de la fila')
  assert.deepEqual(abiertos, [{ el: fila, pos: { x: 40, y: 60 }, origen: 'boton' }])
  root.remove()
})

test('si el navegador no manda click al soltar, el siguiente toque no se pierde', (t) => {
  t.mock.timers.enable({ apis: ['setTimeout', 'Date'] })
  const { tabla: root, celda } = tabla()
  accionesTactiles(root, '.fila', () => {})

  celda.dispatchEvent(evento('pointerdown', { pointerType: 'touch', button: 0, clientX: 0, clientY: 0 }))
  t.mock.timers.tick(DEMORA_LARGO_MS)
  celda.dispatchEvent(evento('pointerup'))

  celda.dispatchEvent(evento('pointerdown', { pointerType: 'touch', button: 0, clientX: 0, clientY: 0 }))
  celda.dispatchEvent(evento('pointerup'))
  const click = evento('click')
  celda.dispatchEvent(click)
  assert.equal(click.defaultPrevented, false)
  root.remove()
})

test('el click de soltar tampoco activa el menú que se abrió bajo el dedo', (t) => {
  t.mock.timers.enable({ apis: ['setTimeout', 'Date'] })
  const { tabla: root, celda } = tabla()
  let menu = null
  accionesTactiles(root, '.fila', () => {
    menu = document.body.appendChild(document.createElement('button'))
  })

  celda.dispatchEvent(evento('pointerdown', { pointerType: 'touch', button: 0, clientX: 0, clientY: 0 }))
  t.mock.timers.tick(DEMORA_LARGO_MS)
  const click = evento('click')
  menu.dispatchEvent(click)
  assert.ok(click.defaultPrevented && click.propagationStopped, '"Eliminar" no se ejecuta solo')

  const siguiente = evento('click')
  menu.dispatchEvent(siguiente)
  assert.equal(siguiente.defaultPrevented, false, 'el toque siguiente sí cuenta')
  menu.remove()
  root.remove()
})
