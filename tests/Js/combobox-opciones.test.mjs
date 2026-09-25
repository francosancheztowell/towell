import assert from 'node:assert/strict'
import test from 'node:test'

import {
  TEXTOS_COMBOBOX,
  cargadorRemoto,
  opcionesRemotas,
  plantillas,
} from '../../resources/js/utils/combobox-opciones.ts'

test('opcionesRemotas lee el formato {results:[{id,text}]} de los endpoints actuales', () => {
  assert.deepEqual(
    opcionesRemotas({ results: [{ id: 12, text: '12 — Tarea' }, { id: 'F-1', text: 'F-1' }] }),
    [{ value: '12', text: '12 — Tarea' }, { value: 'F-1', text: 'F-1' }],
  )
})

test('opcionesRemotas acepta un arreglo, usa el id si falta el texto e ignora filas sin id', () => {
  assert.deepEqual(
    opcionesRemotas([{ id: 7 }, { text: 'sin id' }, null, { id: '', text: 'vacío' }, { id: 0, text: 'cero' }]),
    [{ value: '7', text: '7' }, { value: '0', text: 'cero' }],
  )
})

test('opcionesRemotas devuelve [] ante respuestas inesperadas', () => {
  for (const respuesta of [null, undefined, 'x', 42, {}, { results: 'no' }]) {
    assert.deepEqual(opcionesRemotas(respuesta), [])
  }
})

test('cargadorRemoto manda q + params extra leídos en cada búsqueda', async () => {
  const llamadas = []
  let mes = '2026-01'
  const get = async (url, config) => {
    llamadas.push([url, config])
    return { results: [{ id: 'A', text: 'Artículo A' }] }
  }
  const cargar = cargadorRemoto({ url: '/opciones', params: () => ({ mes }) }, get)

  const primera = await new Promise((resolve) => cargar('ab', resolve))
  mes = '2026-02'
  await new Promise((resolve) => cargar('', resolve))

  assert.deepEqual(primera, [{ value: 'A', text: 'Artículo A' }])
  assert.deepEqual(llamadas, [
    ['/opciones', { params: { q: 'ab', mes: '2026-01' } }],
    ['/opciones', { params: { q: '', mes: '2026-02' } }],
  ])
  assert.equal(cargar.fallo(), false)
})

test('cargadorRemoto: si el servidor falla, callback sin opciones y fallo() hasta la siguiente respuesta buena', async () => {
  let falla = true
  const get = async () => {
    if (falla) throw new Error('500')
    return []
  }
  const cargar = cargadorRemoto({ url: '/x' }, get)

  const conError = await new Promise((resolve) => cargar('q', (...args) => resolve(args)))
  assert.deepEqual(conError, [])
  assert.equal(cargar.fallo(), true)

  falla = false
  await new Promise((resolve) => cargar('q', resolve))
  assert.equal(cargar.fallo(), false)
})

test('plantillas escapan el texto de opciones e ítems', () => {
  const p = plantillas(TEXTOS_COMBOBOX)
  assert.equal(p.option({ text: '<img src=x onerror=alert(1)>' }), '<div>&lt;img src=x onerror=alert(1)&gt;</div>')
  assert.equal(p.item({ text: 'A & B' }), '<div>A &amp; B</div>')
})

test('plantillas: sin resultados, buscando y error de carga en español (y personalizables)', () => {
  let fallo = false
  const p = plantillas({ ...TEXTOS_COMBOBOX, sinResultados: 'No se encontraron tareas' }, () => fallo)
  assert.equal(p.no_results({}), '<div class="no-results">No se encontraron tareas</div>')
  assert.match(p.loading({}), /Buscando…/)
  fallo = true
  assert.equal(p.no_results({}), '<div class="no-results">No se pudieron cargar las opciones</div>')
})
