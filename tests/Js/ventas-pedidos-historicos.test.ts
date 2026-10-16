// Pedidos Históricos (Ventas > Compara): decodificación del payload, filtros y árbol
// Empresa > Tipo > Cliente > OC con los artículos como hojas.
import assert from 'node:assert/strict'
import { test } from 'node:test'

import {
    ESTATUS, FILTER_KEYS, buildTree, decodePedidos, isOpen, matchesLine, progress, sumLines,
    type Filters, type PedidosPayload,
} from '../../resources/js/ventas/pedidos-logica.ts'

const SF = ['mes', 'empresa', 'tipo', 'cliente', 'oc', 'articulo', 'nombre', 'talla', 'colorId', 'color', 'codigo', 'creado', 'facturado']
const NF = ['precio', 'cantidad', 'entregado', 'pendiente', 'importe']

const payload = (rows: (string | number)[][]): PedidosPayload => {
    const dict: string[] = []
    const encoded = rows.map((row) => row.map((value, i) => {
        if (i >= SF.length) return value as number
        const at = dict.indexOf(String(value))
        if (at !== -1) return at
        dict.push(String(value))
        return dict.length - 1
    }))
    return { v: 1, anio: '2026', sf: SF, nf: NF, dict, rows: encoded }
}

const linea = (cambios: Partial<Record<string, string | number>> = {}) => {
    const base: Record<string, string | number> = {
        mes: '10', empresa: 'TOWEL', tipo: 'CE HT', cliente: 'GRAN DESIGN FACTORY', oc: '6100157353.', articulo: '6577',
        nombre: 'TOALLA FACIAL AM RESORTS VI', talla: 'FAC', colorId: '4014', color: 'BLANCO JOSEFINA', codigo: '6577',
        creado: '01/10/2026', facturado: '05/10/2026', precio: 10.85, cantidad: 3000, entregado: 3000, pendiente: 0, importe: 32550,
    }
    const fila = { ...base, ...cambios }
    return [...SF, ...NF].map((campo) => fila[campo]!)
}

const sinFiltros = (): Filters => Object.fromEntries(FILTER_KEYS.map((key) => [key, new Set<string>()])) as Filters

test('decodifica cada línea con sus columnas, el año y el estatus', () => {
    const [primera, segunda] = decodePedidos(payload([linea(), linea({ pendiente: 5, entregado: 2995 })]))
    assert.equal(primera!.nombre, 'TOALLA FACIAL AM RESORTS VI')
    assert.equal(primera!.color, 'BLANCO JOSEFINA')
    assert.equal(primera!.precio, 10.85)
    assert.equal(primera!.importe, 32550)
    assert.equal(primera!.anio, '2026')
    assert.equal(primera!.estatus, ESTATUS.completo)
    assert.equal(segunda!.estatus, ESTATUS.pendiente)
})

test('rechaza una versión de payload desconocida', () => {
    assert.throws(() => decodePedidos({ ...payload([linea()]), v: 99 }), /no soportado/)
})

test('filtros vacíos dejan pasar todo; uno con valores exige coincidencia', () => {
    const [towel, textil] = decodePedidos(payload([linea(), linea({ empresa: 'TEXTIL' })]))
    const filtros = sinFiltros()
    assert.ok(matchesLine(towel!, filtros))
    filtros.empresa.add('TEXTIL')
    assert.equal(matchesLine(towel!, filtros), false)
    assert.ok(matchesLine(textil!, filtros))
    assert.ok(matchesLine(towel!, filtros, 'empresa'), 'el propio filtro se ignora al calcular sus opciones')
})

test('el árbol agrupa Empresa > Tipo > Cliente > OC con Towel primero y suma cada nivel', () => {
    const lineas = decodePedidos(payload([
        linea({ empresa: 'TEXTIL', tipo: 'CE', cliente: 'ZETA', oc: 'A1', importe: 100, cantidad: 10, entregado: 4, pendiente: 6 }),
        linea(),
        linea({ articulo: '6578', nombre: 'TOALLA BAÑO', facturado: '07/10/2026' }),
        linea({ oc: 'HZC-276', creado: '02/10/2026' }),
    ]))
    const arbol = buildTree(lineas)

    assert.deepEqual(arbol.map((nodo) => nodo.value), ['TOWEL', 'TEXTIL'])
    const cliente = arbol[0]!.children[0]!.children[0]!
    assert.equal(cliente.level, 'cliente')
    assert.equal(cliente.orders, 2)
    assert.deepEqual(cliente.children.map((nodo) => nodo.value), ['6100157353.', 'HZC-276'])

    const oc = cliente.children[0]!
    assert.equal(oc.created, '01/10/2026')
    assert.deepEqual(oc.lines.map((l) => l.nombre), ['TOALLA BAÑO', 'TOALLA FACIAL AM RESORTS VI'])
    assert.deepEqual(oc.totals, { cantidad: 6000, entregado: 6000, pendiente: 0, importe: 65100 })
    assert.deepEqual(arbol[1]!.totals, { cantidad: 10, entregado: 4, pendiente: 6, importe: 100 })
    assert.equal(progress(arbol[1]!.totals), 40)
    assert.deepEqual(sumLines([]), { cantidad: 0, entregado: 0, pendiente: 0, importe: 0 })
})

test('los niveles abiertos de entrada y las flechas invierten nodos sueltos', () => {
    const toggled = new Set<string>()
    assert.ok(isOpen({ id: '/TOWEL', depth: 0 }, 2, toggled))
    assert.equal(isOpen({ id: '/TOWEL/CE/X', depth: 2 }, 2, toggled), false)
    toggled.add('/TOWEL/CE/X')
    assert.ok(isOpen({ id: '/TOWEL/CE/X', depth: 2 }, 2, toggled))
    toggled.add('/TOWEL')
    assert.equal(isOpen({ id: '/TOWEL', depth: 0 }, 2, toggled), false)
})
