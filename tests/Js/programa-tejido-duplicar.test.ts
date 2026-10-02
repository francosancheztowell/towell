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
