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

const { saldo, cuadre, cuadreVivo, absorber } = await import('../../resources/js/programa-tejido/duplicar.ts')

test('saldo: pedido × (1 + %seg/100) − producción, como lo muestra la columna Saldo', () => {
    assert.equal(saldo({ pedido: '1000', porcSeg: '5' }), '1,050.00')
    assert.equal(saldo({ pedido: '1,000', porcSeg: '5' }), '1,050.00')
    assert.equal(saldo({ pedido: '333', porcSeg: '1.5' }), '337.99')
    assert.equal(saldo({ pedido: '700', porcSeg: '0', produccion: 200 }), '500.00')
    assert.equal(saldo({ pedido: '100', porcSeg: '0', produccion: 200 }), '-100.00') // menor a lo producido
    assert.equal(saldo({ pedido: '', porcSeg: '' }), '0.00')
})

test('cuadre: Σ saldos desde el pedido; Faltan / Sobran / Cuadra con ±0.5 y sin saldos negativos', () => {
    assert.deepEqual(cuadre([{ pedido: '800', produccion: 200 }, { pedido: '' }], 1000), { asignado: '600.00', estado: 'Faltan 400.00', cuadra: false })
    assert.deepEqual(cuadre([{ pedido: '600' }, { pedido: '400.4' }], 1000), { asignado: '1,000.40', estado: 'Cuadra', cuadra: true })
    assert.deepEqual(cuadre([{ pedido: '1200' }, { pedido: '1' }], 1000), { asignado: '1,201.00', estado: 'Sobran 201.00', cuadra: false })
    assert.equal(cuadre([{ pedido: '100', produccion: 200 }, { pedido: '1100' }], 1000).cuadra, false)
})

function wireFalso(datos: Record<string, unknown>) {
    const sets: Array<[string, unknown, boolean | undefined]> = []
    return { sets, wire: { $get: (ruta: string) => datos[ruta], $set: (ruta: string, valor: unknown, live?: boolean) => { sets.push([ruta, valor, live]) } } }
}

test('cuadreVivo: lee las filas de Dividir de $wire en cada acceso', () => {
    const datos: Record<string, unknown> = { 'filas.dividir': [{ pedido: '600' }] }
    const vivo = cuadreVivo(1000)
    vivo.$wire = wireFalso(datos).wire
    assert.equal(vivo.c.estado, 'Faltan 400.00')
    datos['filas.dividir'] = [{ pedido: '600' }, { pedido: '400' }]
    assert.equal(vivo.c.estado, 'Cuadra')
})

test('absorber: el registro abierto toma la diferencia para que siga cuadrando, sin ir al servidor', () => {
    // Original Id 1: saldo 800 con 200 producidas (pedido 1000). Fila nueva y fila vacía.
    const datos = {
        modo: 'dividir',
        origen: { id: 1, disponible: 800 },
        'filas.dividir': [{ registroId: 1, pedido: '1000', produccion: 200, porcSeg: '0' }, { registroId: null, pedido: '', produccion: 0, porcSeg: '0' }],
    }
    const { wire, sets } = wireFalso(datos)
    absorber(wire, 1, 'pedido', '300')
    assert.deepEqual(sets, [['filas.dividir.0.pedido', '700', false]]) // 700 − 200 = 500 de saldo + 300 = 800

    sets.length = 0
    absorber(wire, 0, 'pedido', '650') // el propio original: se corrige a mano, no se toca
    absorber({ ...wire, $get: (r: string) => (r === 'modo' ? 'duplicar' : wire.$get(r)) }, 1, 'pedido', '300') // en Duplicar no aplica
    assert.deepEqual(sets, [])
})
