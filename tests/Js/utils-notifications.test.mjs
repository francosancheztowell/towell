import assert from 'node:assert/strict'
import test from 'node:test'

import Swal from 'sweetalert2'

import { installFakeDom } from './utils-fake-dom.mjs'

const document = installFakeDom()

let fired = []
Swal.fire = async (options) => {
  fired.push(options)

  return { isConfirmed: options.showCancelButton === true }
}

const { MAX_TOASTS, TOAST_DURATION, TOAST_DURATIONS, notify, showToast } = await import('../../resources/js/utils/notifications.ts')

const container = () => document.getElementById('towell-toasts')

test.beforeEach(() => {
  fired = []
  container()?.remove()
})

test('el primer toast crea un contenedor aria-live y los estilos una sola vez', () => {
  notify.success('Guardado')
  notify.info('Otro')

  const root = container()
  assert.equal(root.getAttribute('aria-live'), 'polite')
  assert.equal(root.getAttribute('role'), 'status')
  assert.equal(document.head.querySelectorAll('style').length, 1)
  assert.equal(document.body.querySelectorAll('.towell-toasts').length, 1)
})

test('el mensaje va como texto (sin HTML interpretado) con botón de cierre accesible', () => {
  const el = notify.error('<b>hola</b>')

  assert.ok(el.classList.contains('towell-toast--error'))
  assert.equal(el.querySelector('.towell-toast__msg').textContent, '<b>hola</b>')
  const close = el.querySelector('button')
  assert.equal(close.getAttribute('aria-label'), 'Cerrar notificación')

  close.dispatchEvent({ type: 'click' })
  assert.equal(container().children.length, 0)
})

test('la pila no pasa de 4: se descarta el más viejo', () => {
  for (let i = 1; i <= 6; i++) notify.info(`m${i}`)

  const mensajes = container().children.map((el) => el.querySelector('.towell-toast__msg').textContent)
  assert.equal(MAX_TOASTS, 4)
  assert.deepEqual(mensajes, ['m3', 'm4', 'm5', 'm6'])
})

test('todos los tipos duran lo mismo (UX-13) y el puntero encima pausa el cierre', (t) => {
  t.mock.timers.enable({ apis: ['setTimeout'] })
  assert.equal(TOAST_DURATION, 5000)
  assert.deepEqual({ ...TOAST_DURATIONS }, { success: 5000, info: 5000, warning: 5000, error: 5000 })

  notify.success('s')
  notify.warning('w')
  t.mock.timers.tick(4999)
  assert.equal(container().children.length, 2, 'ninguno se va antes de tiempo')
  t.mock.timers.tick(1)
  assert.equal(container().children.length, 0, 'éxito y advertencia se van a la vez')

  const error = notify.error('e')
  error.dispatchEvent({ type: 'mouseenter' })
  t.mock.timers.tick(10000)
  assert.equal(container().children.length, 1, 'pausado mientras el puntero está encima')

  error.dispatchEvent({ type: 'mouseleave' })
  t.mock.timers.tick(5000)
  assert.equal(container().children.length, 0)
})

test('la pila va debajo del navbar (no tapa Crear/Editar/Eliminar)', () => {
  notify.info('x')
  const css = document.getElementById('towell-toasts-style').textContent
  assert.match(css, /\.towell-toasts\{position:fixed;top:calc\(var\(--pt-navbar-height,64px\) \+ \.75rem\)/)
})

test('los toasts no usan SweetAlert2 (no cierran un modal abierto)', () => {
  notify.success('a')
  notify.error('b')
  showToast('c', 'warning')

  assert.equal(fired.length, 0)
})

test('showToast mantiene la firma (message, type) y cae en info con tipos desconocidos', () => {
  showToast('por defecto')
  showToast('raro', 'danger')

  const [first, second] = container().children
  assert.ok(first.classList.contains('towell-toast--success'))
  assert.ok(second.classList.contains('towell-toast--info'))
})

test('confirm devuelve boolean y validation escapa los mensajes', async () => {
  assert.equal(await notify.confirm({ text: '¿Eliminar?' }), true)
  assert.equal(fired[0].text, '¿Eliminar?')

  await notify.validation({ nombre: ['<script>x</script>'], clave: 'Requerida' })
  assert.match(fired[1].html, /&lt;script&gt;x&lt;\/script&gt;/)
  assert.match(fired[1].html, /<li>Requerida<\/li>/)
})
