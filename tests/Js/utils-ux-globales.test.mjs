// UX global (fase 17-02): sesión expirada compartida con Livewire (UX-11), banner sin
// conexión (UX-12) y el modal de días de Liberar órdenes que salió del navbar (HANDOFF PT B2).
import assert from 'node:assert/strict'
import test from 'node:test'

import Swal from 'sweetalert2'

import { installFakeDom } from './utils-fake-dom.mjs'

const document = installFakeDom()
let reloads = 0
let navegado = null
globalThis.window = Object.assign(new EventTarget(), {
  location: {
    reload: () => reloads++,
    set href(url) { navegado = url },
  },
  navigator: { onLine: true },
})

const sesion = await import('../../resources/js/utils/sesion.ts')
const conexion = await import('../../resources/js/componentes/conexion.ts')
const dias = await import('../../resources/js/componentes/dias-liberar.ts')

test('419 y 401 de Livewire: mismo aviso que http, sin el confirm() en inglés, una sola vez', (t) => {
  t.mock.timers.enable({ apis: ['setTimeout'] })
  const hooks = {}
  sesion.escucharSesionLivewire({ hook: (nombre, cb) => { hooks[nombre] = cb } })

  const fallos = []
  hooks.request({ fail: (cb) => fallos.push(cb) })
  let prevenidos = 0
  const falla = (status) => fallos.forEach((cb) => cb({ status, preventDefault: () => prevenidos++ }))

  falla(500)
  assert.equal(prevenidos, 0, 'otros errores siguen su curso normal')

  falla(419)
  falla(401)
  assert.equal(prevenidos, 2)
  const avisos = document.body.querySelectorAll('.towell-toast__msg').filter((el) => el.textContent === sesion.SESSION_EXPIRED_MESSAGE)
  assert.equal(avisos.length, 1, 'un solo aviso por página')

  t.mock.timers.tick(sesion.SESSION_EXPIRED_RELOAD_MS)
  assert.equal(reloads, 1)
})

test('http reexporta el mismo mensaje de sesión (un solo texto en la app)', async () => {
  const http = await import('../../resources/js/utils/http.ts')
  assert.equal(http.SESSION_EXPIRED_MESSAGE, sesion.SESSION_EXPIRED_MESSAGE)
  assert.ok(sesion.esSesionExpirada(419) && sesion.esSesionExpirada(401) && !sesion.esSesionExpirada(403))
})

test('banner sin conexión: aparece con towell:conexion {online:false} y se oculta al volver', () => {
  conexion.iniciarConexion(window, document)
  assert.equal(document.getElementById(conexion.ID_BANNER), null, 'en línea no se crea nada')

  window.dispatchEvent(new CustomEvent('towell:conexion', { detail: { online: false } }))
  const banner = document.getElementById(conexion.ID_BANNER)
  assert.ok(banner)
  assert.equal(banner.hidden, false)
  assert.equal(banner.getAttribute('role'), 'status')
  assert.equal(banner.textContent, conexion.MENSAJE_SIN_CONEXION)

  window.dispatchEvent(new CustomEvent('towell:conexion', { detail: { online: true } }))
  assert.equal(banner.hidden, true)

  window.dispatchEvent(new Event('offline'))
  assert.equal(banner.hidden, false, 'también con el evento del navegador')
  window.dispatchEvent(new Event('online'))
  assert.equal(banner.hidden, true)
})

test('tras wire:navigate (body nuevo) el banner se vuelve a pintar si sigue sin conexión', () => {
  conexion.actualizarConexion(false, document)
  document.getElementById(conexion.ID_BANNER).remove()
  conexion.pintarConexion(document)
  assert.equal(document.getElementById(conexion.ID_BANNER).hidden, false)
  conexion.actualizarConexion(true, document)
})

test('días para liberar: misma validación que tenía el navbar', () => {
  assert.equal(dias.validarDias('10.999'), null)
  assert.equal(dias.validarDias('0'), null)
  assert.equal(dias.validarDias(''), 'Por favor ingrese un número válido')
  assert.equal(dias.validarDias('-1'), 'Por favor ingrese un número válido')
  assert.equal(dias.validarDias('abc'), 'Por favor ingrese un número válido')
  assert.equal(dias.validarDias('1.2345'), 'Máximo 3 decimales permitidos')
  assert.equal(dias.urlLiberar('/planeacion/muestras', '5.5'), '/planeacion/muestras/liberar-ordenes?dias=5.5')
})

test('días para liberar: lee los datos del navbar y navega a liberar-ordenes', async () => {
  const datos = document.body.appendChild(document.createElement('div'))
  datos.id = dias.ID_DATOS
  datos.dataset = { dias: '7.5', base: '/planeacion/muestras' }
  document.createElement = ((crear) => (tag) => {
    const el = crear(tag)
    el.append = (...hijos) => hijos.forEach((h) => el.appendChild(h))
    el.style = {}
    return el
  })(document.createElement.bind(document))

  let opciones = null
  Swal.fire = async (o) => {
    opciones = o
    return { isConfirmed: true, value: o.preConfirm() }
  }
  await dias.mostrarModalDiasLiberar()

  assert.equal(opciones.title, 'Rango de días a considerar')
  assert.equal(opciones.html.children[1].value, '7.5')
  assert.equal(navegado, '/planeacion/muestras/liberar-ordenes?dias=7.5')
})
