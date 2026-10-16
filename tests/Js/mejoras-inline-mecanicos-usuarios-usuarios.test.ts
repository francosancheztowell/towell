/**
 * Lista de usuarios: el <script> inline pasó a resources/js/modulos/usuarios/lista/index.ts
 * (ganchos data-usuarios-filtrar / data-usuarios-crear / data-usuarios-cerrar-crear /
 * form[data-confirmar-eliminar]). Fija filtros en cliente, badge, modal de alta con el select
 * de áreas copiado de la página create y la confirmación de borrado.
 */
import assert from 'node:assert/strict'
import test from 'node:test'

import axios from 'axios'

// @ts-expect-error -- helper .mjs sin tipos
import { instalar, click, tick } from './mejoras-inline-mecanicos-usuarios-dom.mjs'

const { doc, h } = instalar()

const pedidas: Array<{ url?: string; responseType?: string; accept?: unknown }> = []
axios.defaults.adapter = async (config) => {
    pedidas.push({ url: config.url, responseType: config.responseType, accept: (config.headers as any)?.get?.('Accept') })
    const html = '<html><body><select name="area"><option value="Tejido">Tejido</option><option value="Urdido">Urdido</option></select></body></html>'
    return { data: html, status: 200, statusText: '', headers: {}, config, request: {} }
}

// DOMParser no existe en node: uno mínimo que devuelve el <select name="area"> del HTML.
;(globalThis as any).DOMParser = class {
    parseFromString(html: string) {
        const m = /<select[^>]*name="area"[^>]*>([\s\S]*?)<\/select>/.exec(html)
        return { querySelector: (s: string) => (s === 'select[name="area"]' && m ? { innerHTML: m[1] } : null) }
    }
}

const { notify } = await import('../../resources/js/utils/notifications.ts')
let dialogoOpts: any = null
let respuestaDialogo: { valor: string; campos: Record<string, string> } = { valor: 'aplicar', campos: {} }
let confirmaciones = 0
let confirmar = true
Object.assign(notify, {
    dialog: async (opts: any) => {
        dialogoOpts = opts
        const c = respuestaDialogo.campos
        const raiz = h('div', {},
            h('input', { id: 'filtro_numero_empleado', value: c.numero_empleado ?? '' }),
            h('select', { id: 'filtro_area' }, h('option', { value: c.area ?? '', selected: true })),
            h('select', { id: 'filtro_turno' }, h('option', { value: c.turno ?? '', selected: true })),
        )
        if (respuestaDialogo.valor === 'aplicar') opts.alValidar(await opts.validar({ raiz }))
        return respuestaDialogo.valor
    },
    confirm: async () => { confirmaciones++; return confirmar },
})

const usuario = (num: string, area: string, turno: string) =>
    h('div', { class: 'usuario-item', 'data-numero-empleado': num, 'data-area': area, 'data-turno': turno },
        h('form', { 'data-confirmar-eliminar': '', id: `borrar-${num}` }, h('button', { type: 'submit', id: `btn-borrar-${num}` })))

const btnFiltrar = h('button', { type: 'button', 'data-usuarios-filtrar': '', id: 'btn-filtrar' }, h('i', {}))
doc.body.append(
    btnFiltrar,
    h('button', { type: 'button', 'data-usuarios-crear': '', id: 'btn-crear' }, h('span', {}, 'Nuevo Usuario')),
    h('div', { id: 'usuarios-container' },
        usuario('2045', 'Tejido', '1'),
        usuario('12045', 'Urdido', '2'),
        usuario('300', 'Tejido', '4'),
        usuario('301', 'A<b>&"x', '1'),
        usuario('302', '', ''),
    ),
    h('div', { id: 'modalCrearUsuario', class: 'hidden fixed' },
        h('button', { type: 'button', 'data-usuarios-cerrar-crear': '', id: 'cerrar-x' }),
        h('form', { id: 'formCrearUsuario', method: 'POST', action: '/configuracion/usuarios' },
            h('input', { name: 'nombre', id: 'nombre_modal', value: '' }),
            h('select', { id: 'area_modal', name: 'area' }, h('option', { value: '' }, 'Selecciona el área')),
        ),
    ),
)

await import('../../resources/js/modulos/usuarios/lista/index.ts')

const q = (s: string) => doc.querySelector(s)
const visibles = () => doc.querySelectorAll('.usuario-item').filter((i: any) => i.style.display !== 'none').map((i: any) => i.dataset.numeroEmpleado)
const settle = async () => { for (let i = 0; i < 5; i++) await tick() }

test('el diálogo de filtros lista las áreas únicas, ordenadas, sin vacías y escapadas', async () => {
    respuestaDialogo = { valor: 'cancelar', campos: {} }
    click(btnFiltrar.children[0]) // clic en el ícono dentro del botón
    await settle()

    assert.ok(dialogoOpts, 'el clic en [data-usuarios-filtrar] abre el diálogo')
    const html: string = dialogoOpts.html
    const valores = [...html.matchAll(/id="filtro_area"[\s\S]*?<\/select>/g)][0]![0]
    const opciones = [...valores.matchAll(/<option value="([^"]*)"/g)].map((m) => m[1])
    assert.deepEqual(opciones, ['', 'A&lt;b&gt;&amp;&quot;x', 'Tejido', 'Urdido'])
    assert.ok(!html.includes('A<b>'), 'el área va escapada')
    assert.deepEqual(visibles(), ['2045', '12045', '300', '301', '302'], 'cancelar no filtra')
})

test('aplicar: número por "contiene", área y turno exactos; pone el badge', async () => {
    respuestaDialogo = { valor: 'aplicar', campos: { numero_empleado: '2045', area: '', turno: '' } }
    click(btnFiltrar)
    await settle()
    assert.deepEqual(visibles(), ['2045', '12045'])
    assert.ok(btnFiltrar.querySelector('.badge-filtros'), 'badge de filtros activos')

    respuestaDialogo = { valor: 'aplicar', campos: { numero_empleado: '', area: 'Tejido', turno: '4' } }
    click(btnFiltrar)
    await settle()
    assert.deepEqual(visibles(), ['300'])
    assert.equal(btnFiltrar.querySelectorAll('.badge-filtros').length, 1, 'no duplica el badge')
})

test('el diálogo reabre con los filtros activos preseleccionados', async () => {
    respuestaDialogo = { valor: 'cancelar', campos: {} }
    click(btnFiltrar)
    await settle()
    assert.match(dialogoOpts.html, /<option value="Tejido" selected>/)
    assert.match(dialogoOpts.html, /<option value="4" selected>Turno 4<\/option>/)
})

test('sin resultados muestra el mensaje y al limpiar lo quita, quita el badge y muestra todos', async () => {
    respuestaDialogo = { valor: 'aplicar', campos: { numero_empleado: '999', area: '', turno: '' } }
    click(btnFiltrar)
    await settle()
    assert.deepEqual(visibles(), [])
    assert.ok(q('.mensaje-no-resultados'))

    respuestaDialogo = { valor: 'limpiar', campos: {} }
    click(btnFiltrar)
    await settle()
    assert.deepEqual(visibles(), ['2045', '12045', '300', '301', '302'])
    assert.equal(q('.mensaje-no-resultados'), null)
    assert.equal(btnFiltrar.querySelector('.badge-filtros'), null)
})

test('Nuevo Usuario abre el modal sin pedir nada: las áreas ya vienen en la vista', async () => {
    click(q('#btn-crear').children[0])
    await settle()

    assert.equal(q('#modalCrearUsuario').classList.contains('hidden'), false)
    assert.equal(pedidas.length, 0)
})

test('cerrar el modal lo oculta y resetea el form', () => {
    const form = q('#formCrearUsuario')
    q('#nombre_modal').value = 'Algo'
    click(q('#cerrar-x'))
    assert.equal(q('#modalCrearUsuario').classList.contains('hidden'), true)
    assert.equal(form.resets, 1)
    assert.equal(q('#nombre_modal').value, '')
})

test('el form del modal NO se intercepta: el submit sigue siendo un POST normal', () => {
    const ev = { type: 'submit', bubbles: true } as any
    q('#formCrearUsuario').dispatchEvent(ev)
    assert.equal(ev.defaultPrevented, false)
})

test('eliminar pide confirmación; cancelar no envía y confirmar envía el form', async () => {
    const form = q('#borrar-300')
    confirmar = false
    const ev1 = { type: 'submit', bubbles: true, target: form } as any
    form.dispatchEvent(ev1)
    await settle()
    assert.equal(ev1.defaultPrevented, true)
    assert.equal(confirmaciones, 1)
    assert.equal(form.submits, 0)

    confirmar = true
    form.dispatchEvent({ type: 'submit', bubbles: true })
    await settle()
    assert.equal(confirmaciones, 2)
    assert.equal(form.submits, 1)
})
