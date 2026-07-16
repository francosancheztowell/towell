import { describe, expect, it } from 'vitest'
import { programaTejidoPageSchema } from './schemas'

describe('programaTejidoPageSchema', () => {
    it('valida el contrato paginado de Laravel', () => {
        const parsed = programaTejidoPageSchema.parse({
            data: [{
                id: 10,
                en_proceso: true,
                salon: 'SMIT',
                telar: '12',
                posicion: 1,
                orden_produccion: 'OT-100',
                producto: 'TOALLA',
                item_id: 'AX-100',
                invent_size_id: '50X90',
                flog_id: 'FL-1',
                total_pedido: 1000,
                produccion: 250,
                saldo_pedido: 750,
                fecha_inicio: '2026-07-16T08:00:00-06:00',
                fecha_final: null,
                prioridad: 'URGENTE',
            }],
            links: { first: null, last: null, prev: null, next: null },
            meta: {
                current_page: 1,
                from: 1,
                last_page: 1,
                path: '/planeacion/api/v1/programa-tejido',
                per_page: 25,
                to: 1,
                total: 1,
            },
        })

        expect(parsed.data[0]?.saldo_pedido).toBe(750)
        expect(parsed.data[0]?.en_proceso).toBe(true)
    })

    it('rechaza respuestas con tipos distintos al contrato', () => {
        expect(() => programaTejidoPageSchema.parse({ data: [{ id: '10' }] })).toThrow()
    })
})
