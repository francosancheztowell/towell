import assert from 'node:assert/strict'
import test from 'node:test'

import { agruparTelares, normalizarTelares } from '../../resources/js/programa-urd-eng/creacion-ordenes/agrupar.ts'

/**
 * Sustituye al viejo tests/Js/agruparTelares.test.cjs, que copiaba las
 * funciones dentro del propio test y por tanto no probaba el codigo real.
 */

const telar = (extra = {}) => ({
    no_telar: '299',
    tipo: 'Rizo',
    cuenta: '3156',
    calibre: '20',
    hilo: 'ALG',
    tamano: 'T1',
    urdido: 'URD1',
    tipo_atado: 'Normal',
    metros: 1000,
    kilos: 500,
    agrupar: true,
    ...extra,
})

test('los telares sin agrupar quedan cada uno en su orden', () => {
    const grupos = agruparTelares(normalizarTelares([
        telar({ no_telar: '299', agrupar: false }),
        telar({ no_telar: '300', agrupar: false }),
    ]))

    assert.equal(grupos.length, 2)
    assert.deepEqual(grupos.map((g) => g.telaresStr), ['299', '300'])
})

test('misma cuenta, tipo, urdido y atado van juntos', () => {
    const grupos = agruparTelares(normalizarTelares([
        telar({ no_telar: '299' }),
        telar({ no_telar: '300' }),
    ]))

    assert.equal(grupos.length, 1)
    assert.equal(grupos[0].telaresStr, '299,300')
    assert.equal(grupos[0].metros, 2000, 'los metros se suman')
    assert.equal(grupos[0].kilos, 1000)
})

test('el hilo no separa grupos', () => {
    const grupos = agruparTelares(normalizarTelares([
        telar({ no_telar: '299', hilo: 'ALG' }),
        telar({ no_telar: '300', hilo: 'POLY' }),
    ]))

    assert.equal(grupos.length, 1, 'telares con hilo distinto se urden juntos')
})

test('cuenta, tipo, urdido y atado si separan', () => {
    const casos = [
        ['cuenta', { cuenta: '4020' }],
        ['tipo', { tipo: 'Pie' }],
        ['urdido', { urdido: 'URD2' }],
        ['tipo_atado', { tipo_atado: 'Especial' }],
    ]

    for (const [campo, distinto] of casos) {
        const grupos = agruparTelares(normalizarTelares([
            telar({ no_telar: '299' }),
            telar({ no_telar: '300', ...distinto }),
        ]))

        assert.equal(grupos.length, 2, `${campo} distinto debe separar la orden`)
    }
})

test('un grupo de varios telares pierde el destino heredado', () => {
    const grupos = agruparTelares(normalizarTelares([
        telar({ no_telar: '299', destino: 'Itema Nuevo' }),
        telar({ no_telar: '300', destino: 'Itema Nuevo' }),
    ]))

    assert.equal(grupos[0].destino, '', 'con varios telares el destino se elige a mano')
})

test('un grupo de un solo telar conserva su destino', () => {
    const grupos = agruparTelares(normalizarTelares([
        telar({ no_telar: '299', agrupar: false, destino: 'Itema Nuevo' }),
    ]))

    assert.equal(grupos[0].destino, 'Itema Nuevo')
})

test('normalizar deja el tipo y los numeros en su forma canonica', () => {
    const [t] = normalizarTelares([
        telar({ tipo: 'PIE', calibre: '20.5', metros: '1,200.50', kilos: null, hilo: '  ', tamano: null }),
    ])

    assert.equal(t.tipo, 'Pie')
    assert.equal(t.calibre, 20.5)
    assert.equal(t.metros, 1200.5, 'el separador de miles no debe romper el numero')
    assert.equal(t.kilos, 0)
    assert.equal(t.hilo, null, 'los espacios cuentan como vacio')
    assert.equal(t.tamano, null)
})

test('sin tipo se asume Rizo', () => {
    const [t] = normalizarTelares([telar({ tipo: null })])

    assert.equal(t.tipo, 'Rizo')
})

test('una lista vacia no revienta', () => {
    assert.deepEqual(agruparTelares(normalizarTelares(null)), [])
    assert.deepEqual(agruparTelares(undefined), [])
})
