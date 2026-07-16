import { describe, expect, it } from 'vitest'
import { pesoRolloFormSchema, pesoRolloPageSchema } from './schemas'

describe('pesoRolloFormSchema', () => {
    it('acepta el peso cero y normaliza espacios de texto', () => {
        const result = pesoRolloFormSchema.parse({
            item_id: '  AX-100  ',
            item_name: ' Modelo prueba ',
            invent_size_id: ' 50X90 ',
            peso_rollo: 0,
        })

        expect(result).toEqual({
            item_id: 'AX-100',
            item_name: 'Modelo prueba',
            invent_size_id: '50X90',
            peso_rollo: 0,
        })
    })

    it('rechaza pesos negativos', () => {
        const result = pesoRolloFormSchema.safeParse({
            item_id: 'AX-100',
            item_name: 'Modelo prueba',
            invent_size_id: '50X90',
            peso_rollo: -0.01,
        })

        expect(result.success).toBe(false)
    })
})

describe('pesoRolloPageSchema', () => {
    it('rechaza respuestas del servidor con peso no numerico', () => {
        const result = pesoRolloPageSchema.safeParse({
            data: [{
                id: 1,
                item_id: 'AX-100',
                item_name: 'Modelo',
                invent_size_id: '50X90',
                peso_rollo: '12.5',
                fecha_creacion: null,
                usuario_crea: null,
                fecha_modificacion: null,
                usuario_modifica: null,
            }],
            links: { first: null, last: null, prev: null, next: null },
            meta: { current_page: 1, from: 1, last_page: 1, path: '/api', per_page: 25, to: 1, total: 1 },
        })

        expect(result.success).toBe(false)
    })
})
