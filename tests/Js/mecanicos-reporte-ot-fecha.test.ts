/**
 * OT x Fecha (mecánicos): resources/js/modulos/mecanicos/reportes/ot-fecha/index.ts mantiene el
 * rango válido, solo habilita Consultar con ambas fechas y exporta con el form oculto.
 */
import assert from 'node:assert/strict'
import test from 'node:test'

// @ts-expect-error -- helper .mjs sin tipos
import { click, instalar } from './mejoras-inline-mecanicos-usuarios-dom.mjs'

const { doc, h } = instalar()

doc.body.append(
    h('button', { type: 'button', 'data-ot-fecha-descargar': 'excel', id: 'b-excel' }),
    h('button', { type: 'button', 'data-ot-fecha-descargar': 'pdf', id: 'b-pdf' }),
    h('div', {
        id: 'reporte-ot-fecha',
        'data-excel-url': '/mecanicos/reportes/ot-fecha/excel',
        'data-pdf-url': '/mecanicos/reportes/ot-fecha/pdf',
    },
        h('input', { id: 'desde', value: '2026-10-01' }),
        h('input', { id: 'hasta', value: '2026-10-05' }),
        h('button', { id: 'btn-consultar' }),
        h('form', { id: 'form-export-ot-fecha' },
            h('input', { type: 'hidden', name: 'desde', value: '2026-10-01' }),
            h('input', { type: 'hidden', name: 'hasta', value: '2026-10-05' }),
        ),
    ),
)

await import('../../resources/js/modulos/mecanicos/reportes/ot-fecha/index.ts')

const q = (s: string) => doc.querySelector(s)
function cambiar(id: string, valor: string) {
    const el = q(`#${id}`)
    el.value = valor
    el.dispatchEvent({ type: 'change', bubbles: true })
}

test('al cargar, la fecha final no permite días antes de la inicial', () => {
    assert.equal(q('#hasta').min, '2026-10-01')
    assert.equal(q('#btn-consultar').disabled, false)
})

test('si la inicial pasa a la final, la final se recorre a la inicial', () => {
    cambiar('desde', '2026-10-08')
    assert.equal(q('#hasta').value, '2026-10-08')
    assert.equal(q('#hasta').min, '2026-10-08')
})

test('Excel y PDF envían el form oculto a su URL', () => {
    const form = q('#form-export-ot-fecha')
    click(q('#b-excel'))
    assert.equal(form.action, '/mecanicos/reportes/ot-fecha/excel')
    click(q('#b-pdf'))
    assert.equal(form.action, '/mecanicos/reportes/ot-fecha/pdf')
    assert.equal(form.submits, 2)
})

test('sin alguna de las fechas, Consultar queda deshabilitado', () => {
    cambiar('hasta', '')
    assert.equal(q('#btn-consultar').disabled, true)
    cambiar('hasta', '2026-10-09')
    assert.equal(q('#btn-consultar').disabled, false)
})
