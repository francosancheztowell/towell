import assert from 'node:assert/strict'
import test from 'node:test'

import { ejemploKardex } from '../../resources/js/componentes/diagrama-flujo/ejemplo-kardex.ts'
import {
    anclasArista,
    calcularArista,
    rutaArista,
    validar,
    vecinos,
    vecinosArista,
} from '../../resources/js/componentes/diagrama-flujo/geometria.ts'
import type { AristaFlujo, DiagramaFlujo, NodoFlujo } from '../../resources/js/componentes/diagrama-flujo/tipos.ts'

const nodo = (id: string, x: number, y: number, ancho = 100, alto = 50): NodoFlujo => ({
    id,
    x,
    y,
    ancho,
    alto,
    titulo: id,
    color: '#000000',
})

const arista = (id: string, de: string, a: string, extra: Partial<AristaFlujo> = {}): AristaFlujo => ({
    id,
    de,
    a,
    etiqueta: id,
    ...extra,
})

const base: DiagramaFlujo = {
    ancho: 800,
    alto: 400,
    nodos: [nodo('A', 0, 0), nodo('B', 200, 0), nodo('C', 200, 200), nodo('D', 400, 100)],
    aristas: [arista('ab', 'A', 'B'), arista('bd', 'B', 'D'), arista('ac', 'A', 'C')],
}

test('anclas: destino a la derecha sale por la derecha y entra por la izquierda', () => {
    const a = anclasArista(base.aristas[0]!, base.nodos[0]!, base.nodos[1]!)
    assert.deepEqual(a.desde, { x: 100, y: 25 })
    assert.deepEqual(a.hasta, { x: 200, y: 25 })
    assert.equal(a.ladoDesde, 'derecha')
    assert.equal(a.ladoHasta, 'izquierda')
})

test('anclas: destino a la izquierda invierte los lados', () => {
    const a = anclasArista(arista('x', 'B', 'A'), base.nodos[1]!, base.nodos[0]!)
    assert.deepEqual(a.desde, { x: 200, y: 25 })
    assert.deepEqual(a.hasta, { x: 100, y: 25 })
})

test('anclas: cajas alineadas en vertical usan abajo/arriba', () => {
    const abajo = anclasArista(arista('x', 'B', 'C'), base.nodos[1]!, base.nodos[2]!)
    assert.deepEqual(abajo.desde, { x: 250, y: 50 })
    assert.deepEqual(abajo.hasta, { x: 250, y: 200 })
    const arriba = anclasArista(arista('x', 'C', 'B'), base.nodos[2]!, base.nodos[1]!)
    assert.equal(arriba.ladoDesde, 'arriba')
    assert.deepEqual(arriba.desde, { x: 250, y: 200 })
})

test('anclas: desde/hasta explícitos sustituyen el punto', () => {
    const e = arista('ab', 'A', 'B', { desde: { x: 100, y: 10 }, hasta: { x: 200, y: 40 } })
    const a = anclasArista(e, base.nodos[0]!, base.nodos[1]!)
    assert.deepEqual(a.desde, { x: 100, y: 10 })
    assert.deepEqual(a.hasta, { x: 200, y: 40 })
    assert.equal(a.ladoDesde, 'derecha')
})

test('ruta recta: misma curva y etiqueta que el flujo original', () => {
    const e = arista('e', 'A', 'B')
    const r = rutaArista(e, { desde: { x: 160, y: 95 }, hasta: { x: 230, y: 125 }, ladoDesde: 'derecha', ladoHasta: 'izquierda' })
    assert.equal(r.d, 'M160,95 C195,95 195,125 230,125')
    assert.deepEqual(r.etiqueta, { x: 195, y: 104 })
})

test('ruta con curva (dip) por debajo: control en y=dip y etiqueta debajo', () => {
    const e = arista('e', 'A', 'B', { curva: 442 })
    const r = rutaArista(e, { desde: { x: 635, y: 378 }, hasta: { x: 1170, y: 262 }, ladoDesde: 'derecha', ladoHasta: 'izquierda' })
    assert.equal(r.d, 'M635,378 C902.5,442 902.5,442 1170,262')
    assert.deepEqual(r.etiqueta, { x: 902.5, y: 446 })
})

test('ruta con curva por encima: etiqueta arriba del control', () => {
    const e = arista('e', 'A', 'B', { curva: 50 })
    const r = rutaArista(e, { desde: { x: 0, y: 100 }, hasta: { x: 100, y: 100 }, ladoDesde: 'derecha', ladoHasta: 'izquierda' })
    assert.deepEqual(r.etiqueta, { x: 50, y: 42 })
})

test('ruta vertical: controles verticales', () => {
    const r = calcularArista(base, arista('bc', 'B', 'C'))
    assert.equal(r.d, 'M250,50 C250,125 250,125 250,200')
    assert.deepEqual(r.etiqueta, { x: 250, y: 119 })
})

test('ruta con controles y posición de etiqueta explícitos', () => {
    const e = arista('e', 'A', 'B', { controles: [430, 380, 510, 200], posEtiqueta: { x: 452, y: 400 } })
    const r = rutaArista(e, { desde: { x: 380, y: 472 }, hasta: { x: 696, y: 172 }, ladoDesde: 'derecha', ladoHasta: 'izquierda' })
    assert.equal(r.d, 'M380,472 C430,380 510,200 696,172')
    assert.deepEqual(r.etiqueta, { x: 452, y: 400 })
})

test('vecinos de un nodo: él mismo, sus extremos y sus aristas', () => {
    const v = vecinos(base, 'B')
    assert.deepEqual(v.nodos, ['A', 'B', 'D'])
    assert.deepEqual(v.aristas, ['ab', 'bd'])
    assert.deepEqual(vecinos(base, 'D'), { nodos: ['B', 'D'], aristas: ['bd'] })
})

test('vecinos de una arista: ella y sus dos extremos', () => {
    assert.deepEqual(vecinosArista(base, 'ac'), { nodos: ['A', 'C'], aristas: ['ac'] })
    assert.deepEqual(vecinosArista(base, 'nope'), { nodos: [], aristas: [] })
})

test('validar acepta un diagrama correcto', () => {
    assert.doesNotThrow(() => validar(base))
})

test('validar rechaza aristas hacia nodos inexistentes', () => {
    assert.throws(() => validar({ ...base, aristas: [arista('x', 'A', 'Z')] }), /inexistente: Z/)
    assert.throws(() => validar({ ...base, aristas: [arista('x', 'Z', 'A')] }), /inexistente: Z/)
})

test('validar rechaza ids duplicados', () => {
    assert.throws(() => validar({ ...base, nodos: [...base.nodos, nodo('A', 5, 5)] }), /Nodo duplicado: A/)
    assert.throws(() => validar({ ...base, aristas: [arista('x', 'A', 'B'), arista('x', 'B', 'D')] }), /Arista duplicada: x/)
})

test('el fixture del Kardex valida y todas sus aristas tienen ruta', () => {
    assert.doesNotThrow(() => validar(ejemploKardex))
    assert.equal(ejemploKardex.nodos.length, 12)
    assert.equal(ejemploKardex.aristas.length, 14)
    for (const a of ejemploKardex.aristas) assert.match(calcularArista(ejemploKardex, a).d, /^M[\d.]+,[\d.]+ C/)
})

test('el fixture reproduce las coordenadas del original', () => {
    const porId = new Map(ejemploKardex.aristas.map((a) => [a.id, calcularArista(ejemploKardex, a)]))
    assert.equal(porId.get('urdid-julurd')!.d, 'M390,135 C425,135 425,135 460,135')
    assert.equal(porId.get('mp-urdid')!.d, 'M160,95 C195,95 195,125 230,125')
    assert.equal(porId.get('julurd-eptejid')!.d, 'M610,170 C757.5,215 757.5,215 905,172')
    assert.equal(porId.get('mateng-formeng')!.d, 'M220,502 C260,502 260,502 300,502')
    assert.equal(porId.get('jacq-crudo')!.d, 'M635,378 C902.5,442 902.5,442 1170,262')
    assert.deepEqual(porId.get('formeng-jultela')!.etiqueta, { x: 452, y: 400 })
})
