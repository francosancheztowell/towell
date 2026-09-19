import assert from 'node:assert/strict'
import test from 'node:test'

import {
    destinoInicial,
    destinoPorTelar,
    normalizarDestino,
    opcionesDestino,
    requiereDestinoManual,
} from '../../resources/js/programa-urd-eng/creacion-ordenes/destinos.ts'

test('el numero de telar decide el salon', () => {
    assert.equal(destinoPorTelar('209'), 'Jacquard Sulzer')
    assert.equal(destinoPorTelar('201'), 'Jacquard Smit')
    assert.equal(destinoPorTelar('310'), 'Smit')
    assert.equal(destinoPorTelar('303'), 'Itema Viejo')
    assert.equal(destinoPorTelar('299'), 'Itema Nuevo')
})

test('un telar fuera de los rangos no tiene salon', () => {
    assert.equal(destinoPorTelar('999'), '')
    assert.equal(destinoPorTelar(''), '')
    assert.equal(destinoPorTelar(null), '')
    assert.equal(destinoPorTelar('abc'), '')
})

test('los bordes de cada rango entran', () => {
    assert.equal(destinoPorTelar('207'), 'Jacquard Sulzer')
    assert.equal(destinoPorTelar('211'), 'Jacquard Sulzer')
    assert.equal(destinoPorTelar('206'), 'Jacquard Smit', '206 es Smit, no Sulzer')
    assert.equal(destinoPorTelar('212'), '', '212 no esta asignado')
    assert.equal(destinoPorTelar('305'), 'Smit')
    assert.equal(destinoPorTelar('316'), 'Smit')
})

test('los alias del ERP se normalizan', () => {
    assert.equal(normalizarDestino('SULZER'), 'Jacquard Sulzer')
    assert.equal(normalizarDestino('jacquard'), 'Jacquard Smit')
    assert.equal(normalizarDestino('JAC'), 'Jacquard Smit')
    assert.equal(normalizarDestino('SMITH'), 'Smit')
    assert.equal(normalizarDestino('  itema   nuevo  '), 'Itema Nuevo', 'espacios de sobra incluidos')
})

test('un destino desconocido se descarta, no se propaga', () => {
    assert.equal(normalizarDestino('Taller 5'), '')
    assert.equal(normalizarDestino(null), '')
})

test('un grupo de varios telares exige destino manual', () => {
    assert.equal(requiereDestinoManual({ telares: [{}, {}] }), true)
    assert.equal(requiereDestinoManual({ telares: [{}] }), false)
    assert.equal(requiereDestinoManual({ telares: [] }), false)
    assert.equal(requiereDestinoManual(null), false)
})

test('el destino inicial sale del telar cuando el grupo es de uno', () => {
    assert.equal(destinoInicial({ telares: [{ no_telar: '310' }], destino: '' }), 'Smit')
})

test('un destino explicito y valido gana al deducido', () => {
    assert.equal(destinoInicial({ telares: [{ no_telar: '310' }], destino: 'Itema Nuevo' }), 'Itema Nuevo')
})

test('un grupo de varios arranca sin destino aunque lo traiga', () => {
    assert.equal(destinoInicial({ telares: [{ no_telar: '310' }, { no_telar: '311' }], destino: 'Smit' }), '')
})

test('la lista del servidor manda sobre la fija', () => {
    assert.deepEqual(opcionesDestino(['A', 'B']), ['A', 'B'])
    assert.equal(opcionesDestino([]).length, 5, 'una lista vacia cae a la de por defecto')
    assert.equal(opcionesDestino(undefined).length, 5)
})
