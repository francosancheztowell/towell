/**
 * Catálogo de actividades de mecánicos: el <script> inline pasó a
 * resources/js/modulos/mecanicos/actividades/index.ts. Se fija el contrato con el controller
 * (MecActividadesController: {ok, message, item}) y la tabla resultante.
 */
import assert from 'node:assert/strict'
import test from 'node:test'

import axios, { AxiosError } from 'axios'

// @ts-expect-error -- helper .mjs sin tipos
import { instalar, click, tick } from './mejoras-inline-mecanicos-usuarios-dom.mjs'

const { doc, h } = instalar()

type Llamada = { method?: string; url?: string; campos: Array<[string, string]> }
const llamadas: Llamada[] = []
let respuesta: { status: number; data: unknown } = { status: 200, data: {} }
axios.defaults.adapter = async (config) => {
    const campos: Array<[string, string]> = config.data instanceof FormData
        ? [...config.data.entries()].map(([k, v]) => [k, String(v)])
        : []
    llamadas.push({ method: config.method, url: config.url, campos })
    const res = { data: respuesta.data, status: respuesta.status, statusText: '', headers: {}, config, request: {} }
    if (respuesta.status >= 200 && respuesta.status < 300) return res
    throw new AxiosError('fallo', 'ERR_BAD_RESPONSE', config, {}, res)
}

const { notify } = await import('../../resources/js/utils/notifications.ts')
const avisos: Array<[string, unknown]> = []
let confirmar = true
Object.assign(notify, {
    success: (m: string) => { avisos.push(['success', m]) },
    error: (m: string) => { avisos.push(['error', m]) },
    validation: async (e: unknown) => { avisos.push(['validation', e]) },
    confirm: async (o: unknown) => { avisos.push(['confirm', o]); return confirmar },
})

const fila = (id: string, orden: string, act: string) =>
    h('tr', { class: 'actividad-row border-b hover:bg-gray-50', 'data-id': id, 'data-orden': orden, 'data-actividad': act },
        h('td', { class: 'c1' }, orden), h('td', { class: 'c2' }, act))

doc.body.append(
    h('button', { id: 'btn-nuevo' }),
    h('button', { id: 'btn-editar', disabled: true }),
    h('button', { id: 'btn-eliminar', disabled: true }),
    h('div', {
        id: 'actividades',
        'data-store-url': '/mecanicos/catalogos/actividades',
        'data-update-url': '/mecanicos/catalogos/actividades/__ID__',
        'data-destroy-url': '/mecanicos/catalogos/actividades/__ID__',
    }, h('table', {}, h('tbody', { id: 'tbody-actividades' }, fila('1', '1', 'Lubricar'), fila('2', '3', 'Ajustar')))),
    h('div', { id: 'modal-form', class: 'fixed hidden' },
        h('h3', { id: 'modal-title' }, 'Nueva actividad'),
        h('button', { id: 'modal-cerrar' }),
        h('form', { id: 'form-actividad', method: 'POST' },
            h('input', { type: 'hidden', name: '_token', value: 'tok' }),
            h('input', { type: 'hidden', name: '_method', id: 'form-method', value: 'POST' }),
            h('input', { name: 'Orden', id: 'Orden' }),
            h('input', { name: 'Actividad', id: 'Actividad' }),
            h('button', { type: 'submit', id: 'form-submit' }),
        ),
    ),
)

await import('../../resources/js/modulos/mecanicos/actividades/index.ts')

const q = (s: string) => doc.querySelector(s)
const filas = () => q('#tbody-actividades').querySelectorAll('tr.actividad-row')
const textos = () => filas().map((tr: any) => tr.cells.map((td: any) => td.textContent).join('|'))
async function enviar() {
    q('#form-actividad').dispatchEvent({ type: 'submit', bubbles: true })
    for (let i = 0; i < 5; i++) await tick()
}

test('sin selección Editar/Eliminar quedan deshabilitados; al elegir fila se habilitan', () => {
    assert.equal(q('#btn-editar').disabled, true)
    assert.equal(q('#btn-eliminar').disabled, true)
    click(filas()[0].cells[1])
    assert.equal(q('#btn-editar').disabled, false)
    assert.equal(q('#btn-eliminar').disabled, false)
    assert.ok(filas()[0].classList.contains('selected'))
})

test('Nuevo: POST a store, inserta la fila ordenada por Orden y pinta el texto sin interpretar HTML', async () => {
    click(q('#btn-nuevo'))
    assert.equal(q('#modal-form').classList.contains('hidden'), false)
    assert.equal(q('#form-actividad').action, '/mecanicos/catalogos/actividades')
    assert.equal(q('#form-method').value, 'POST')

    q('#Orden').value = '2'
    q('#Actividad').value = '<b>Cambiar</b>'
    respuesta = { status: 200, data: { ok: true, message: 'Creada', item: { Id: 9, Orden: 2, Actividad: '<b>Cambiar</b>' } } }
    await enviar()

    const ultima = llamadas.at(-1)!
    assert.equal(ultima.method, 'post')
    assert.equal(ultima.url, '/mecanicos/catalogos/actividades')
    assert.deepEqual(ultima.campos.filter(([k]) => k !== '_token'), [['_method', 'POST'], ['Orden', '2'], ['Actividad', '<b>Cambiar</b>']])

    assert.deepEqual(textos(), ['1|Lubricar', '2|<b>Cambiar</b>', '3|Ajustar'])
    const nueva = filas()[1]
    assert.equal(nueva.dataset.id, '9')
    assert.equal(nueva.dataset.orden, '2')
    assert.equal(q('#modal-form').classList.contains('hidden'), true)
    assert.deepEqual(avisos.at(-1), ['success', 'Creada'])
    assert.equal(q('#form-submit').disabled, false)
})

test('Editar: PUT (vía _method) a update/{id} y reordena con el Orden nuevo', async () => {
    click(filas()[0]) // Lubricar (Id 1)
    click(q('#btn-editar'))
    assert.equal(q('#form-actividad').action, '/mecanicos/catalogos/actividades/1')
    assert.equal(q('#Orden').value, '1')
    assert.equal(q('#Actividad').value, 'Lubricar')

    q('#Orden').value = '5'
    respuesta = { status: 200, data: { ok: true, item: { Id: 1, Orden: 5, Actividad: 'Lubricar' } } }
    await enviar()

    const ultima = llamadas.at(-1)!
    assert.equal(ultima.url, '/mecanicos/catalogos/actividades/1')
    // El último _method manda (Laravel lee el último valor repetido).
    assert.equal(ultima.campos.filter(([k]) => k === '_method').at(-1)?.[1], 'PUT')
    assert.deepEqual(textos(), ['2|<b>Cambiar</b>', '3|Ajustar', '5|Lubricar'])
    assert.equal(filas()[2].dataset.orden, '5')
    assert.deepEqual(avisos.at(-1), ['success', 'Guardado'])
})

test('422: muestra la validación, el modal sigue abierto y Guardar se rehabilita', async () => {
    click(q('#btn-nuevo'))
    respuesta = { status: 422, data: { message: 'invalido', errors: { Actividad: ['La actividad es obligatoria.'] } } }
    await enviar()

    assert.deepEqual(avisos.at(-1), ['validation', { Actividad: ['La actividad es obligatoria.'] }])
    assert.equal(q('#modal-form').classList.contains('hidden'), false)
    assert.equal(q('#form-submit').disabled, false)
    assert.equal(filas().length, 3)
    click(q('#modal-cerrar'))
    assert.equal(q('#modal-form').classList.contains('hidden'), true)
})

test('Eliminar con 403 {ok:false}: muestra el mensaje del controller y no quita la fila', async () => {
    click(filas()[0])
    respuesta = { status: 403, data: { ok: false, message: 'No tienes permiso para eliminar.' } }
    click(q('#btn-eliminar'))
    for (let i = 0; i < 5; i++) await tick()

    const ultima = llamadas.at(-1)!
    assert.equal(ultima.url, '/mecanicos/catalogos/actividades/9')
    assert.deepEqual(ultima.campos, [['_method', 'DELETE']])
    assert.deepEqual(avisos.at(-1), ['error', 'No tienes permiso para eliminar.'])
    assert.equal(filas().length, 3)
})

test('Eliminar cancelado no hace petición', async () => {
    const antes = llamadas.length
    confirmar = false
    click(q('#btn-eliminar'))
    for (let i = 0; i < 3; i++) await tick()
    confirmar = true
    assert.equal(llamadas.length, antes)
})

test('Eliminar todas deja la fila vacía #tr-empty y deshabilita las acciones', async () => {
    respuesta = { status: 200, data: { ok: true, message: 'Eliminada' } }
    while (filas().length) {
        click(filas()[0])
        click(q('#btn-eliminar'))
        for (let i = 0; i < 5; i++) await tick()
    }
    const vacia = q('#tr-empty')
    assert.ok(vacia)
    assert.equal(vacia.textContent, 'No hay actividades registradas.')
    assert.equal(vacia.cells[0].colSpan, 2)
    assert.equal(q('#btn-editar').disabled, true)
    assert.equal(q('#btn-eliminar').disabled, true)

    // Y al crear una nueva, la fila vacía desaparece.
    click(q('#btn-nuevo'))
    respuesta = { status: 200, data: { ok: true, item: { Id: 20, Orden: 1, Actividad: 'Nueva' } } }
    await enviar()
    assert.equal(q('#tr-empty'), null)
    assert.deepEqual(textos(), ['1|Nueva'])
})
