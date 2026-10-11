// Rama claude/mejora-micro-ui-kit: el toast se desvanece los últimos 150 ms antes de quitarse.
// Fija que el fundido no deje toasts "zombis" (que nunca se van) ni que se vayan antes de
// tiempo cuando el usuario pausa con el puntero o el teclado.
import assert from 'node:assert/strict'
import test from 'node:test'

import { installFakeDom } from './utils-fake-dom.mjs'

const document = installFakeDom()

const { TOAST_DURATION, notify } = await import('../../resources/js/utils/notifications.ts')

const SALIDA_MS = 150
const container = () => document.getElementById('towell-toasts')
const vivos = () => container()?.children.length ?? 0
const saliendo = (el) => el.classList.contains('towell-toast--saliendo')

test.beforeEach(() => {
  container()?.remove()
})

test('el fundido empieza exactamente SALIDA_MS antes de quitarse y no antes', (t) => {
  t.mock.timers.enable({ apis: ['setTimeout'] })
  const el = notify.info('hola')

  t.mock.timers.tick(TOAST_DURATION - SALIDA_MS - 1)
  assert.equal(saliendo(el), false, 'todavía no se desvanece')
  t.mock.timers.tick(1)
  assert.equal(saliendo(el), true, 'empieza el fundido')
  assert.equal(vivos(), 1, 'sigue en el DOM mientras se desvanece')
  t.mock.timers.tick(SALIDA_MS)
  assert.equal(vivos(), 0)
})

test('el puntero que entra durante el fundido no lo deja pegado para siempre', (t) => {
  t.mock.timers.enable({ apis: ['setTimeout'] })
  const el = notify.success('ok')

  t.mock.timers.tick(TOAST_DURATION - 50)
  assert.ok(saliendo(el))
  el.dispatchEvent({ type: 'mouseenter' })
  el.dispatchEvent({ type: 'focusin' })
  t.mock.timers.tick(50)
  assert.equal(vivos(), 0, 'se quita aunque el puntero/foco haya entrado durante el fundido')

  // mouseleave tardío sobre un toast ya quitado no lo revive ni programa nada que truene.
  el.dispatchEvent({ type: 'mouseleave' })
  t.mock.timers.tick(TOAST_DURATION * 2)
  assert.equal(vivos(), 0)
})

test('pausar antes del fundido cancela ambos timers; al salir vuelve a contar completo', (t) => {
  t.mock.timers.enable({ apis: ['setTimeout'] })
  const el = notify.warning('w')

  t.mock.timers.tick(TOAST_DURATION - SALIDA_MS - 10)
  el.dispatchEvent({ type: 'focusin' })
  t.mock.timers.tick(TOAST_DURATION * 3)
  assert.equal(vivos(), 1, 'pausado por foco: ni se desvanece ni se quita')
  assert.equal(saliendo(el), false)

  el.dispatchEvent({ type: 'focusout' })
  t.mock.timers.tick(TOAST_DURATION - SALIDA_MS - 1)
  assert.equal(saliendo(el), false, 'reinicia la cuenta completa al salir')
  t.mock.timers.tick(SALIDA_MS + 1)
  assert.equal(vivos(), 0)
})

test('la × durante el fundido lo quita al instante y los timers pendientes no rompen nada', (t) => {
  t.mock.timers.enable({ apis: ['setTimeout'] })
  const a = notify.error('a')
  t.mock.timers.tick(TOAST_DURATION - 10)
  assert.ok(saliendo(a))

  a.querySelector('button').dispatchEvent({ type: 'click' })
  assert.equal(vivos(), 0)

  // Un toast nuevo justo después no se lleva el timer del anterior.
  const b = notify.info('b')
  t.mock.timers.tick(10)
  assert.equal(vivos(), 1, 'el timer del toast cerrado no quita al nuevo')
  assert.equal(saliendo(b), false)
})

test('varios toasts escalonados se van cada uno a su tiempo (timers independientes)', (t) => {
  t.mock.timers.enable({ apis: ['setTimeout'] })
  const primero = notify.info('1')
  t.mock.timers.tick(1000)
  const segundo = notify.info('2')

  t.mock.timers.tick(TOAST_DURATION - 1000)
  assert.equal(vivos(), 1)
  assert.equal(container().children[0], segundo)
  assert.equal(primero.parentNode == null || !container().children.includes(primero), true)

  t.mock.timers.tick(1000)
  assert.equal(vivos(), 0)
})
