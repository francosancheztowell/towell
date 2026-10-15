/**
 * OT x Fecha (mecánicos): resources/js/modulos/mecanicos/reportes/ot-fecha/index.ts mantiene el
 * rango válido y solo habilita Consultar con ambas fechas.
 */
import assert from 'node:assert/strict'
import test from 'node:test'

// @ts-expect-error -- helper .mjs sin tipos
import { instalar } from './mejoras-inline-mecanicos-usuarios-dom.mjs'

const { doc, h } = instalar()

doc.body.append(h('div', { id: 'reporte-ot-fecha' },
    h('input', { id: 'desde', value: '2026-10-01' }),
    h('input', { id: 'hasta', value: '2026-10-05' }),
    h('button', { id: 'btn-consultar' }),
))

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

test('sin alguna de las fechas, Consultar queda deshabilitado', () => {
    cambiar('hasta', '')
    assert.equal(q('#btn-consultar').disabled, true)
    cambiar('hasta', '2026-10-09')
    assert.equal(q('#btn-consultar').disabled, false)
})
