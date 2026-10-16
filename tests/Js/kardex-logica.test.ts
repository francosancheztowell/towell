import assert from 'node:assert/strict'
import test from 'node:test'

import { datosSimulados } from '../../resources/js/modulos/kardex/datos-simulados.ts'
import {
    agruparArticulos,
    agruparSalones,
    agruparTejidoPorTipo,
    columnasVisibles,
    desgloseRenglon,
    estadoCuadre,
    filtrarCrudo,
    limpiarEncabezado,
    movimientosSimulados,
    ordenarRenglones,
    tonoSegundas,
} from '../../resources/js/modulos/kardex/logica.ts'
import type { DetalleCrudo, SeccionKardex } from '../../resources/js/modulos/kardex/tipos.ts'

const seccion = (clave: string): SeccionKardex => {
    const s = datosSimulados.secciones.find((x) => x.clave === clave)
    assert.ok(s, `falta la sección ${clave}`)
    return s
}

const detalle = (parcial: Partial<DetalleCrudo>): DetalleCrudo => ({
    articulo: 'A1',
    nombre: 'TOALLA',
    tamano: 'MB',
    telar: '201',
    salon: 'JACQUARD',
    piezas: 10,
    kg: 100,
    segPiezas: 1,
    segKg: 10,
    diaIni: 1,
    diaFin: 2,
    entradas: 3,
    ...parcial,
})

test('limpiarEncabezado quita el sufijo Kg y corrige erratas del libro', () => {
    assert.equal(limpiarEncabezado('Exitencia Kg.'), 'Existencia')
    assert.equal(limpiarEncabezado('Sal Jaqcuard Kg'), 'Sal Jacquard')
    assert.equal(limpiarEncabezado('Kilos Iniciales'), 'Inicial')
    assert.equal(limpiarEncabezado('SalMC1Kg'), 'Sal MC1')
})

test('columnasVisibles omite las columnas que son cero en todos los renglones y el total', () => {
    const mp = seccion('mp')
    const visibles = columnasVisibles(mp)
    const iMaquila = mp.encabezados.indexOf('Sal Maquila Kg') - 1
    const iAjuste = mp.encabezados.indexOf('Ajuste Pza') - 1
    assert.ok(visibles.includes(iMaquila))
    assert.ok(!visibles.includes(iAjuste))
    assert.ok(visibles.includes(0))
    assert.equal(visibles.at(-1), mp.encabezados.length - 2)
})

test('ordenarRenglones ordena por columna en ambos sentidos sin mutar', () => {
    const urd = seccion('urdido')
    const antes = urd.renglones.map((r) => r.etiqueta)
    const desc = ordenarRenglones(urd.renglones, 0, 1).map((r) => r.valores[0] as number)
    const asc = ordenarRenglones(urd.renglones, 0, -1).map((r) => r.valores[0] as number)
    assert.deepEqual(desc, [...desc].sort((a, b) => b - a))
    assert.deepEqual(asc, [...desc].reverse())
    assert.deepEqual(urd.renglones.map((r) => r.etiqueta), antes)
})

test('agruparTejidoPorTipo agrupa los telares en orden Jacquard, Itema, Smith, KM y suma al total del libro', () => {
    const tejido = seccion('tejido')
    const grupos = agruparTejidoPorTipo(tejido, datosSimulados.telarTipo)
    assert.deepEqual(grupos.map((g) => g.tipo), ['JACQUARD', 'ITEMA', 'SMITH', 'KM'])
    assert.equal(grupos.reduce((a, g) => a + g.renglones.length, 0), tejido.renglones.length)
    const iSaldo = tejido.encabezados.length - 2
    const saldo = grupos.reduce((a, g) => a + (g.sumas[iSaldo] ?? 0), 0)
    assert.ok(Math.abs(saldo - (tejido.total?.valores[iSaldo] as number)) < 1)
})

test('desgloseRenglon filtra por la regla de la sección y ordena por saldo absoluto', () => {
    const items = desgloseRenglon('urdido', 'MC3', datosSimulados.articulos)
    assert.ok(items.length > 0)
    assert.ok(items.every((r) => r.loc === 'MC3' && r.alm === 'A-EP-URDID'))
    const saldos = items.map((r) => Math.abs(r.saldo))
    assert.deepEqual(saldos, [...saldos].sort((a, b) => b - a))
    assert.deepEqual(desgloseRenglon('desconocida', 'X', datosSimulados.articulos), [])
})

test('estadoCuadre clasifica la diferencia del final ERP contra la meta del libro', () => {
    assert.deepEqual(estadoCuadre(100.4, 100), { tono: 'ok', texto: 'Cuadra' })
    assert.equal(estadoCuadre(1030, 1000).tono, 'warn')
    assert.equal(estadoCuadre(1300, 1000).tono, 'serious')
    assert.equal(estadoCuadre(2000, 1000).tono, 'crit')
    assert.equal(estadoCuadre(null, 1000).tono, 'na')
    assert.equal(estadoCuadre(10, null).tono, 'na')
})

test('tonoSegundas marca aviso arriba de 8 % y crítico arriba de 15 %', () => {
    assert.equal(tonoSegundas(0.05), 'ok')
    assert.equal(tonoSegundas(0.1), 'warn')
    assert.equal(tonoSegundas(0.2), 'crit')
})

test('filtrarCrudo combina búsqueda (código o nombre, sin importar mayúsculas), tamaño, telar y salón', () => {
    const filas = [detalle({}), detalle({ articulo: 'B2', nombre: 'CORUÑA', tamano: 'CH', telar: '305', salon: 'SMITH' })]
    const vacio = { busqueda: '', tamano: '', telar: '', salon: '' }
    assert.equal(filtrarCrudo(filas, vacio).length, 2)
    assert.deepEqual(filtrarCrudo(filas, { ...vacio, busqueda: 'coruña' }).map((r) => r.articulo), ['B2'])
    assert.deepEqual(filtrarCrudo(filas, { ...vacio, tamano: 'MB' }).map((r) => r.articulo), ['A1'])
    assert.deepEqual(filtrarCrudo(filas, { ...vacio, telar: '305', salon: 'SMITH' }).map((r) => r.articulo), ['B2'])
    assert.equal(filtrarCrudo(filas, { ...vacio, telar: '305', salon: 'JACQUARD' }).length, 0)
})

test('agruparArticulos junta artículo+tamaño y ordena telares por kg', () => {
    const grupos = agruparArticulos([detalle({ telar: '201', kg: 50 }), detalle({ telar: '202', kg: 80 }), detalle({ tamano: 'CH', kg: 500 })])
    assert.equal(grupos.length, 2)
    assert.equal(grupos[0]?.tamano, 'CH')
    assert.equal(grupos[1]?.kg, 130)
    assert.deepEqual(grupos[1]?.telares, ['202', '201'])
})

test('agruparSalones cuadra con el total de crudo del mes', () => {
    const salones = agruparSalones(datosSimulados.crudo.detalle)
    const kg = salones.reduce((a, s) => a + s.kg, 0)
    assert.ok(Math.abs(kg - datosSimulados.crudo.total.kg) < 0.05)
    assert.equal(salones.reduce((a, s) => a + s.telares, 0), datosSimulados.crudo.total.nTel)
})

test('movimientosSimulados son deterministas y suman las entradas y salidas del artículo', () => {
    const [art] = desgloseRenglon('urdido', 'MC1', datosSimulados.articulos)
    assert.ok(art)
    const movs = movimientosSimulados(art)
    assert.deepEqual(movimientosSimulados(art), movs)
    const ent = movs.filter((m) => m.tipo === 'E').reduce((a, m) => a + m.kg, 0)
    const sal = movs.filter((m) => m.tipo === 'S').reduce((a, m) => a + m.kg, 0)
    assert.ok(Math.abs(ent - art.ent) < 0.011)
    assert.ok(Math.abs(sal + art.sal) < 0.011)
    assert.ok(movs.every((m) => m.dia >= 1 && m.dia <= 31))
})
