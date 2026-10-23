/**
 * Glue de la grilla con el modal Livewire Duplicar/Dividir (resources/js/programa-tejido/duplicar.ts).
 */
import assert from 'node:assert/strict'
import test from 'node:test'

// @ts-expect-error -- utils-fake-dom.mjs no trae declaración de tipos
import { installFakeDom } from './utils-fake-dom.mjs'

installFakeDom()

const { abrirDuplicar, idsTocados, procesarGuardado, escucharGuardado } = await import('../../resources/js/programa-tejido/duplicar.ts')

function fila(id: string | null): Element {
    const tr = { getAttribute: (n: string) => (n === 'data-id' ? id : null), closest: () => tr }
    return tr as unknown as Element
}

function livewireFalso() {
    const llamadas: Array<[string, unknown]> = []
    return { llamadas, cliente: { dispatch: (e: string, p?: unknown) => { llamadas.push([e, p]) }, on: () => () => {}, hook: () => {} } }
}

test('abrir despacha pt-duplicar-abrir con el Id numérico de la fila', () => {
    const lw = livewireFalso()
    assert.equal(abrirDuplicar(fila('42'), lw.cliente), true)
    assert.deepEqual(lw.llamadas, [['pt-duplicar-abrir', { id: 42 }]])
})

test('sin Id válido o sin Livewire no despacha', () => {
    const lw = livewireFalso()
    assert.equal(abrirDuplicar(fila(null), lw.cliente), false)
    assert.equal(abrirDuplicar(fila('abc'), lw.cliente), false)
    assert.equal(abrirDuplicar(fila('5'), null), false)
    assert.deepEqual(lw.llamadas, [])
})

test('idsTocados: en dividir, el original y las nuevas sin repetir; en duplicar, nada', () => {
    assert.deepEqual(idsTocados({ modo: 'dividir', registro_id_original: 7, registros_ids: [8, 9, 7] }), ['7', '8', '9'])
    assert.deepEqual(idsTocados({ modo: 'duplicar', registros_ids: [8] }), [])
})

function posproceso() {
    const orden: string[] = []
    return {
        orden,
        p: {
            insertar: async () => { orden.push('insertar') },
            refrescar: async (ids: string[]) => { orden.push('refrescar:' + ids.join(',')) },
            advertenciaHtml: () => '',
        },
    }
}

test('dividir inserta y luego refresca las filas tocadas', async () => {
    const { orden, p } = posproceso()
    await procesarGuardado({ success: true, modo: 'dividir', registro_id_original: 1, registros_ids: [2] }, p)
    assert.deepEqual(orden, ['insertar', 'refrescar:1,2'])
})

test('duplicar solo inserta', async () => {
    const { orden, p } = posproceso()
    await procesarGuardado({ success: true, modo: 'duplicar', registros_ids: [3] }, p)
    assert.deepEqual(orden, ['insertar'])
})

test('escucharGuardado toma el detail del evento del componente', async () => {
    const recibidos: unknown[] = []
    const destino = new EventTarget()
    escucharGuardado({ insertar: async (d) => { recibidos.push(d) }, refrescar: async () => {}, advertenciaHtml: () => '' }, destino)
    destino.dispatchEvent(new CustomEvent('pt-duplicar-guardado', { detail: { modo: 'duplicar', registros_ids: [4] } }))
    await new Promise((r) => setTimeout(r, 0))
    assert.deepEqual(recibidos, [{ modo: 'duplicar', registros_ids: [4] }])
})

const { saldoDuplicar, pedidoDerivado, cuadre, alEscribir, cuadreVivo } = await import('../../resources/js/programa-tejido/duplicar.ts')

test('saldoDuplicar: pedido × (1 + %seg/100) con el formato de FilasDestino::numero', () => {
    assert.equal(saldoDuplicar('1000', '5'), '1050')
    assert.equal(saldoDuplicar('1,000', '5'), '1050')
    assert.equal(saldoDuplicar('333', '1.5'), '337.99')
    assert.equal(saldoDuplicar('101', '0.5'), '101.51')
    assert.equal(saldoDuplicar('', ''), '0')
})

test('pedidoDerivado: (saldo + producción) / (1 + %seg/100), con 2 decimales', () => {
    assert.equal(pedidoDerivado({ saldo: '1050', produccion: 0, porcSeg: '5' }), '1,000.00')
    assert.equal(pedidoDerivado({ saldo: '500', produccion: 550, porcSeg: 5 }), '1,000.00')
    assert.equal(pedidoDerivado({ saldo: '100', produccion: 0, porcSeg: '-3' }), '100.00')
})

test('cuadre: Faltan / Sobran / Cuadra con ±0.5 y sin saldos negativos', () => {
    assert.deepEqual(cuadre([{ saldo: '600' }, { saldo: '' }], 1000), { asignado: '600.00', estado: 'Faltan 400.00', cuadra: false })
    assert.deepEqual(cuadre([{ saldo: '600' }, { saldo: '400.4' }], 1000), { asignado: '1,000.40', estado: 'Cuadra', cuadra: true })
    assert.deepEqual(cuadre([{ saldo: '1200' }, { saldo: '1' }], 1000), { asignado: '1,201.00', estado: 'Sobran 201.00', cuadra: false })
    assert.equal(cuadre([{ saldo: '1100' }, { saldo: '-100' }], 1000).cuadra, false)
})

function wireFalso(datos: Record<string, unknown>) {
    const sets: Array<[string, unknown, boolean | undefined]> = []
    return {
        sets,
        wire: {
            $get: (ruta: string) => datos[ruta],
            $set: (ruta: string, valor: unknown, live?: boolean) => { sets.push([ruta, valor, live]) },
        },
    }
}

test('alEscribir: recalcula el saldo de la fila con lo tecleado, sin ir al servidor', () => {
    const { wire, sets } = wireFalso({ 'filas.duplicar.0.pedido': '1000', 'filas.duplicar.0.porcSeg': '0' })
    alEscribir(wire, 'filas.duplicar.0', 'porcSeg', '5')
    alEscribir(wire, 'filas.duplicar.0', 'pedido', '2000')
    assert.deepEqual(sets, [['filas.duplicar.0.saldo', '1050', false], ['filas.duplicar.0.saldo', '2000', false]])
})

test('cuadreVivo: lee las filas de Dividir de $wire en cada acceso', () => {
    const datos: Record<string, unknown> = { 'filas.dividir': [{ saldo: '600' }] }
    const vivo = cuadreVivo(1000)
    vivo.$wire = wireFalso(datos).wire
    assert.equal(vivo.c.estado, 'Faltan 400.00')
    datos['filas.dividir'] = [{ saldo: '600' }, { saldo: '400' }]
    assert.equal(vivo.c.estado, 'Cuadra')
})
