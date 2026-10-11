/**
 * Reporte "Estado de Máquina" (mecánicos): el <script> inline pasó a
 * resources/js/modulos/mecanicos/reportes/estado-maquina/index.ts.
 * Fija: carga de semanas al abrir con ?mes&semana (y que quede seleccionada), Consultar,
 * y que la exportación mande solo prioridades 1/2/3 con el name prioridades[id].
 */
import assert from 'node:assert/strict'
import test from 'node:test'

import axios from 'axios'

// @ts-expect-error -- helper .mjs sin tipos
import { instalar, click, tick } from './mejoras-inline-mecanicos-usuarios-dom.mjs'

const { doc, h } = instalar()

const pedidas: Array<{ url?: string; params?: unknown }> = []
axios.defaults.adapter = async (config) => {
    pedidas.push({ url: config.url, params: config.params })
    const data = {
        semanas: [
            { lunes: '2026-09-07', etiqueta: 'Semana 1' },
            { lunes: '2026-09-14', etiqueta: 'Semana 2' },
        ],
    }
    return { data, status: 200, statusText: '', headers: {}, config, request: {} }
}

const navbar = h('div', {},
    h('button', { type: 'button', 'data-em-descargar': 'excel', id: 'b-excel' }),
    h('button', { type: 'button', 'data-em-descargar': 'pdf', id: 'b-pdf' }),
)
const raiz = h('div', {
    id: 'reporte-estado-maquina',
    'data-semanas-url': '/mecanicos/reportes/estado-maquina/semanas',
    'data-excel-url': '/mecanicos/reportes/estado-maquina/excel',
    'data-pdf-url': '/mecanicos/reportes/estado-maquina/pdf',
    'data-imagen-url': '/mecanicos/reportes/estado-maquina/telegram-imagen',
    'data-mes': '2026-09',
    'data-semana': '2026-09-14',
    'data-nombre-imagen': 'hoja.png',
},
    h('input', { id: 'mes', value: '2026-09' }),
    h('select', { id: 'semana' }, h('option', { value: '' }, 'Seleccione una semana')),
    h('button', { id: 'btn-consultar' }),
    h('form', { id: 'form-export-estado-maquina' }, h('div', { id: 'prioridades-export' })),
    h('div', { id: 'hoja-verificacion' },
        h('input', { class: 'js-prioridad', 'data-actividad-id': '11', value: '1' }),
        h('input', { class: 'js-prioridad', 'data-actividad-id': '12', value: ' 3 ' }),
        h('input', { class: 'js-prioridad', 'data-actividad-id': '13', value: '4' }),
        h('input', { class: 'js-prioridad', 'data-actividad-id': '14', value: '' }),
        h('input', { class: 'js-prioridad', 'data-actividad-id': '15', value: '2' }),
    ),
)
doc.body.append(navbar, raiz)

await import('../../resources/js/modulos/mecanicos/reportes/estado-maquina/index.ts')
await tick()

const q = (s: string) => doc.querySelector(s)

test('al abrir con ?mes&semana carga las semanas de ese mes y deja seleccionada la de la URL', () => {
    assert.equal(pedidas.length, 1)
    assert.equal(pedidas[0]?.url, '/mecanicos/reportes/estado-maquina/semanas')
    assert.deepEqual(pedidas[0]?.params, { mes: '2026-09' })

    const sel = q('#semana')
    assert.deepEqual(sel.options.map((o: any) => o.value), ['', '2026-09-07', '2026-09-14'])
    assert.equal(sel.value, '2026-09-14')
    assert.equal(sel.disabled, false)
    assert.equal(q('#btn-consultar').disabled, false)
})

test('cambiar de mes recarga sin selección y Consultar queda deshabilitado', async () => {
    const mes = q('#mes')
    mes.value = '2026-10'
    mes.dispatchEvent({ type: 'change', bubbles: true })
    await tick()

    assert.deepEqual(pedidas[1]?.params, { mes: '2026-10' })
    assert.equal(q('#semana').value, '')
    assert.equal(q('#btn-consultar').disabled, true)
})

test('exportar manda solo prioridades 1/2/3 (con trim) como prioridades[id] y a la URL del tipo', () => {
    const form = q('#form-export-estado-maquina')
    click(q('#b-pdf'))
    assert.equal(form.action, '/mecanicos/reportes/estado-maquina/pdf')
    assert.equal(form.submits, 1)
    assert.deepEqual(
        q('#prioridades-export').children.map((i: any) => [i.name, i.value]),
        [['prioridades[11]', '1'], ['prioridades[12]', '3'], ['prioridades[15]', '2']],
    )

    click(q('#b-excel'))
    assert.equal(form.action, '/mecanicos/reportes/estado-maquina/excel')
    assert.equal(q('#prioridades-export').children.length, 3)
})
