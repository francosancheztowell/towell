import assert from 'node:assert/strict'
import test from 'node:test'

import { ejemploKardex } from '../../resources/js/componentes/diagrama-flujo/ejemplo-kardex.ts'
import { datosSimulados } from '../../resources/js/modulos/kardex/datos-simulados.ts'
import { aplicarLibro, corteInicial, esSimulado, flujoConLibro, type LibroKardex } from '../../resources/js/modulos/kardex/libro.ts'

const urdido = datosSimulados.secciones.find((s) => s.clave === 'urdido')

const libro: LibroKardex = {
    periodo: { anio: 2026, mes: 7, nombre: 'Julio 2026', corte: '31/07/2026' },
    kpis: { julUrd: 1, julTela: 2, crudoKg: 3, crudoPz: 4 },
    secciones: urdido ? [{ ...urdido, renglones: [], total: null }] : [],
    articulos: { 'A-EP-URDID': [] },
    crudo: { referenciaKdx: { 'Ent Jacquard Kg': 99 } },
    flujo: {
        nodos: { 'A-EP-URDID': { inicial: 1000, final: 2500.4 } },
        aristas: { 'julurd-eptejid': 34554.8, 'eptejid-crudo': 225019.53 },
        crudoKg: 278200.82,
    },
}

test('corteInicial es el último día del mes anterior, también en enero', () => {
    assert.equal(corteInicial({ anio: 2026, mes: 8 }), '31/07/2026')
    assert.equal(corteInicial({ anio: 2026, mes: 3 }), '28/02/2026')
    assert.equal(corteInicial({ anio: 2026, mes: 1 }), '31/12/2025')
})

test('aplicarLibro reemplaza lo que trae el libro y marca simuladas las demás secciones', () => {
    const d = aplicarLibro(datosSimulados, libro)

    assert.equal(d.meta.mes, 'Julio 2026')
    assert.equal(d.meta.corte_inicial, '30/06/2026')
    assert.deepEqual([d.kpis.julUrd, d.kpis.crudoKg], [1, 3])
    assert.equal(d.kpis.consumoMp, datosSimulados.kpis.consumoMp, 'el consumo de MP sigue simulado')
    const urd = d.secciones.find((s) => s.clave === 'urdido')
    assert.deepEqual(urd?.renglones, [])
    assert.equal(urd?.simulado, undefined)
    assert.equal(d.secciones.find((s) => s.clave === 'mp')?.simulado, true)
    assert.equal(d.secciones.length, datosSimulados.secciones.length)
    assert.deepEqual(d.articulos['A-EP-URDID'], [])
    assert.equal(d.articulos['A-MP'], datosSimulados.articulos['A-MP'])
    assert.equal(d.crudo.referenciaKdx['Ent Jacquard Kg'], 99)
    assert.equal(d.libro, libro.flujo)
    assert.equal(datosSimulados.secciones.find((s) => s.clave === 'mp')?.simulado, undefined, 'no muta los simulados')
})

test('flujoConLibro pone las cifras del libro en cajas y flechas y marca el resto simulado', () => {
    const f = flujoConLibro(ejemploKardex, libro.flujo)
    const nodo = (id: string) => f.nodos.find((n) => n.id === id)
    const arista = (id: string) => f.aristas.find((a) => a.id === id)

    assert.equal(nodo('A-EP-URDID')?.valor, '1,000 → 2,500 kg')
    assert.equal(esSimulado(nodo('A-EP-URDID')?.datos), false)
    assert.match(nodo('A-CRUDO')?.valor ?? '', /278,200\.82/)
    assert.equal(esSimulado(nodo('A-MP')?.datos), true)
    assert.equal(arista('eptejid-crudo')?.etiqueta, '225,020')
    assert.equal(arista('julurd-eptejid')?.etiqueta, '34,555 (KM)')
    assert.deepEqual(arista('eptejid-crudo')?.datos, { desglose: [{ concepto: 'Libro (hoja Kdx)', kg: 225019.53 }] })
    assert.equal(esSimulado(arista('mp-jacq')?.datos), true)
    assert.equal(f.nodos.length, ejemploKardex.nodos.length)
})
