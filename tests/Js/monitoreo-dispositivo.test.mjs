import assert from 'node:assert/strict'
import { test } from 'node:test'

import { iniciarDispositivo } from '../../resources/js/monitoreo/dispositivo.ts'

const flush = () => new Promise((resolve) => setImmediate(resolve))

const elemento = (props = {}) => {
  const el = new EventTarget()
  const clases = new Set(props.clases ?? [])
  Object.assign(el, {
    dataset: {},
    textContent: '',
    value: '',
    classList: { add: (c) => clases.add(c), remove: (c) => clases.delete(c), contains: (c) => clases.has(c) },
    focus() {},
    select() {},
    click() {
      el.dispatchEvent(new Event('click'))
    },
    ...props,
  })
  return el
}

function modal({ nombre = '', viejo = null, post = true, falla = false } = {}) {
  const els = {
    'device-name': elemento({ dataset: { default: 'Tablet', nombre, llaveLocal: 'device_name_abc' }, textContent: nombre || 'Tablet' }),
    'edit-device-name': elemento(),
    'device-name-editor': elemento({ clases: ['hidden'] }),
    'device-name-input': elemento(),
    'save-device-name': elemento(),
    'cancel-device-name': elemento(),
  }
  const doc = { getElementById: (id) => els[id] ?? null }
  const guardado = new Map(viejo === null ? [] : [['device_name_abc', viejo]])
  const almacen = { getItem: (k) => guardado.get(k) ?? null, removeItem: (k) => guardado.delete(k) }
  const posts = []
  const fn = post
    ? (url, body) => {
        posts.push({ url, body })
        return falla ? Promise.reject(new Error('500')) : Promise.resolve(null)
      }
    : null
  iniciarDispositivo(fn, doc, almacen)
  return { els, posts, guardado, etiqueta: els['device-name'] }
}

test('con el monitoreo apagado no envía nada ni habilita la edición', async () => {
  const m = modal({ viejo: 'Tablet 1', post: false })
  m.etiqueta.click()
  await flush()
  assert.equal(m.posts.length, 0)
  assert.equal(m.els['device-name-editor'].classList.contains('hidden'), true)
  assert.equal(m.guardado.has('device_name_abc'), true)
})

test('migra el nombre viejo de localStorage si el servidor no tiene uno', async () => {
  const m = modal({ viejo: 'Tablet Telar 12' })
  await flush()
  assert.deepEqual(m.posts, [{ url: '/telemetria/dispositivo/nombre', body: { nombre: 'Tablet Telar 12' } }])
  assert.equal(m.etiqueta.textContent, 'Tablet Telar 12')
  // El 204 no prueba que se guardó: la llave se borra cuando el render ya trae el nombre.
  assert.equal(m.guardado.has('device_name_abc'), true)
})

test('si la migración falla conserva la llave vieja para el siguiente intento', async () => {
  const m = modal({ viejo: 'Tablet Telar 12', falla: true })
  await flush()
  assert.equal(m.posts.length, 1)
  assert.equal(m.guardado.has('device_name_abc'), true)
  assert.equal(m.etiqueta.textContent, 'Tablet')
})

test('si el servidor ya tiene nombre, gana el servidor y la llave vieja se borra', async () => {
  const m = modal({ nombre: 'Andón Crudo', viejo: 'Otro' })
  await flush()
  assert.equal(m.posts.length, 0)
  assert.equal(m.etiqueta.textContent, 'Andón Crudo')
  assert.equal(m.guardado.has('device_name_abc'), false)
})

test('guardar desde el editor envía el nombre recortado a 80 caracteres', async () => {
  const m = modal({ nombre: 'Andón Crudo' })
  m.etiqueta.click()
  assert.equal(m.els['device-name-editor'].classList.contains('hidden'), false)
  assert.equal(m.els['device-name-input'].value, 'Andón Crudo')

  m.els['device-name-input'].value = `  ${'x'.repeat(100)}  `
  m.els['save-device-name'].click()
  await flush()
  assert.equal(m.posts[0].body.nombre, 'x'.repeat(80))
  assert.equal(m.etiqueta.textContent, 'x'.repeat(80))
  assert.equal(m.els['device-name-editor'].classList.contains('hidden'), true)
})

test('nombre vacío vuelve al nombre detectado; un fallo revierte la etiqueta', async () => {
  const m = modal({ nombre: 'Andón Crudo', falla: true })
  m.etiqueta.click()
  m.els['device-name-input'].value = ''
  m.els['device-name-input'].dispatchEvent(Object.assign(new Event('keydown'), { key: 'Enter' }))
  assert.equal(m.etiqueta.textContent, 'Tablet')
  await flush()
  assert.deepEqual(m.posts[0].body, { nombre: '' })
  assert.equal(m.etiqueta.textContent, 'Andón Crudo')
})

test('volver a iniciar sobre el mismo modal (livewire:navigated) no duplica listeners', async () => {
  const m = modal({ nombre: 'Andón' })
  const doc = { getElementById: (id) => m.els[id] ?? null }
  const posts = []
  iniciarDispositivo((url, body) => { posts.push(body); return Promise.resolve(null) }, doc, null)
  m.etiqueta.click()
  m.els['device-name-input'].value = 'Nuevo'
  m.els['save-device-name'].click()
  await flush()
  assert.equal(m.posts.length, 1)
  assert.equal(posts.length, 0)
})
