/**
 * OT Diarias (mecánicos): el <script> inline pasó a
 * resources/js/modulos/mecanicos/reportes/ot-diarias/index.ts. Se fija que el recálculo por
 * fila/pie y la exportación den lo mismo que el script viejo.
 */
import assert from 'node:assert/strict'
import test from 'node:test'

// @ts-expect-error -- helper .mjs sin tipos
import { instalar, click } from './mejoras-inline-mecanicos-usuarios-dom.mjs'

const { doc, h } = instalar()

const filas = [
    { cve: '7', realizadas_semana: 10, firmadas_semana: '8' },
    { cve: '9', realizadas_semana: null, firmadas_semana: null },
]

const input = (cve: string, campo: string) => h('input', { class: 'js-ot-input', 'data-cve': cve, 'data-campo': campo, value: '' })
const celda = (clase: string, cve: string) => h('td', { class: clase, 'data-cve': cve })

const navbar = h('div', {},
    h('button', { type: 'button', 'data-ot-descargar': 'excel', id: 'b-excel' }),
    h('button', { type: 'button', 'data-ot-descargar': 'pdf', id: 'b-pdf' }),
)
const raiz = h('div', {
    id: 'reporte-ot-diarias',
    'data-excel-url': '/mecanicos/reportes/ot-diarias/excel',
    'data-pdf-url': '/mecanicos/reportes/ot-diarias/pdf',
    'data-imagen-url': '/mecanicos/reportes/ot-diarias/telegram-imagen',
    'data-fecha': '2026-09-28',
    'data-nombre-imagen': 'ot-diarias_2026-09-28_2026-10-04.png',
    'data-filas': JSON.stringify(filas),
},
    h('input', { id: 'fecha', value: '2026-09-28' }),
    h('button', { id: 'btn-consultar' }),
    h('form', { id: 'form-export-ot-diarias' }, h('div', { id: 'inputs-export-ot-diarias' })),
    h('div', { id: 'hoja-ot-diarias' },
        ...['7', '9'].flatMap((cve) => [
            input(cve, 'ot_trama'), input(cve, 'cumplidas_trama'), input(cve, 'ocupacion_pct'),
            celda('js-total-realizadas', cve), celda('js-total-cumplidas', cve),
            celda('js-pct-cumplimiento', cve), celda('js-pct-ot-final', cve),
        ]),
        h('td', { class: 'js-pie-ot-trama' }), h('td', { class: 'js-pie-cumplidas-trama' }), h('td', { class: 'js-pie-ot-final' }),
    ),
)
doc.body.append(navbar, raiz)

await import('../../resources/js/modulos/mecanicos/reportes/ot-diarias/index.ts')

const q = (s: string) => doc.querySelector(s)
function capturar(cve: string, campo: string, valor: string) {
    const el = q(`.js-ot-input[data-cve="${cve}"][data-campo="${campo}"]`)
    el.value = valor
    el.dispatchEvent({ type: 'input', bubbles: true })
}

test('Consultar se habilita según la fecha, como antes', () => {
    assert.equal(q('#btn-consultar').disabled, false)
    const fecha = q('#fecha')
    fecha.value = ''
    fecha.dispatchEvent({ type: 'change', bubbles: true })
    assert.equal(q('#btn-consultar').disabled, true)
    fecha.value = '2026-09-28'
    fecha.dispatchEvent({ type: 'change', bubbles: true })
    assert.equal(q('#btn-consultar').disabled, false)
})

test('recalcula la fila con OT/cumplidas de trama y % ocupación (realizadas numéricas y en texto)', () => {
    capturar('7', 'ot_trama', '2')
    capturar('7', 'cumplidas_trama', '1')
    capturar('7', 'ocupacion_pct', '80')

    // 10+2=12 realizadas; 8+1=9 cumplidas; 9/12=75.0 %; (80+75)/2=77.5
    assert.equal(q('.js-total-realizadas[data-cve="7"]').textContent, '12.0')
    assert.equal(q('.js-total-cumplidas[data-cve="7"]').textContent, '9.0')
    assert.equal(q('.js-pct-cumplimiento[data-cve="7"]').textContent, '75.0')
    assert.equal(q('.js-pct-ot-final[data-cve="7"]').textContent, '77.5')
    // La otra fila no se toca.
    assert.equal(q('.js-total-realizadas[data-cve="9"]').textContent, '')
})

test('el pie suma trama y promedia el % OT final de TODAS las filas (incluida la de nulos)', () => {
    // fila 9: sin realizadas → 0 %, (0+0)/2 = 0. Promedio (77.5+0)/2 = 38.75 → 38.8
    assert.equal(q('.js-pie-ot-trama').textContent, '2.0')
    assert.equal(q('.js-pie-cumplidas-trama').textContent, '1.0')
    assert.equal(q('.js-pie-ot-final').textContent, '38.8')
})

test('acepta coma decimal y valores no numéricos cuentan 0', () => {
    capturar('9', 'ot_trama', '2,5')
    capturar('9', 'cumplidas_trama', 'abc')
    assert.equal(q('.js-total-realizadas[data-cve="9"]').textContent, '2.5')
    assert.equal(q('.js-total-cumplidas[data-cve="9"]').textContent, '0.0')
    assert.equal(q('.js-pct-cumplimiento[data-cve="9"]').textContent, '0.0')
    assert.equal(q('.js-pie-ot-trama').textContent, '4.5')
})

test('exportar PDF/Excel llena el form oculto con inputs[cve][campo] y lo envía a la URL correcta', () => {
    const form = q('#form-export-ot-diarias')
    click(q('#b-pdf'))
    assert.equal(form.action, '/mecanicos/reportes/ot-diarias/pdf')
    assert.equal(form.submits, 1)

    const ocultos = q('#inputs-export-ot-diarias').children.map((i: any) => [i.name, i.value, i.type])
    assert.deepEqual(ocultos, [
        ['inputs[7][ot_trama]', '2', 'hidden'],
        ['inputs[7][cumplidas_trama]', '1', 'hidden'],
        ['inputs[7][ocupacion_pct]', '80', 'hidden'],
        ['inputs[9][ot_trama]', '2,5', 'hidden'],
        ['inputs[9][cumplidas_trama]', 'abc', 'hidden'],
        ['inputs[9][ocupacion_pct]', '', 'hidden'],
    ])

    click(q('#b-excel'))
    assert.equal(form.action, '/mecanicos/reportes/ot-diarias/excel')
    assert.equal(form.submits, 2)
    // Reemplaza, no acumula: sigue habiendo 6 ocultos.
    assert.equal(q('#inputs-export-ot-diarias').children.length, 6)
})
