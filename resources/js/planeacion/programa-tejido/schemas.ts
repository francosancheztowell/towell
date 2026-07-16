import { z } from 'zod'

export const programaTejidoSchema = z.object({
    id: z.number().int().positive(),
    en_proceso: z.boolean(),
    salon: z.string().nullable(),
    telar: z.string().nullable(),
    posicion: z.number().int().nullable(),
    orden_produccion: z.string().nullable(),
    producto: z.string().nullable(),
    item_id: z.string().nullable(),
    invent_size_id: z.string().nullable(),
    flog_id: z.string().nullable(),
    total_pedido: z.number().nullable(),
    produccion: z.number().nullable(),
    saldo_pedido: z.number().nullable(),
    fecha_inicio: z.string().nullable(),
    fecha_final: z.string().nullable(),
    prioridad: z.string().nullable(),
})

export const programaTejidoPageSchema = z.object({
    data: z.array(programaTejidoSchema),
    links: z.object({
        first: z.string().nullable(),
        last: z.string().nullable(),
        prev: z.string().nullable(),
        next: z.string().nullable(),
    }),
    meta: z.object({
        current_page: z.number().int(),
        from: z.number().int().nullable(),
        last_page: z.number().int(),
        path: z.string(),
        per_page: z.number().int(),
        to: z.number().int().nullable(),
        total: z.number().int(),
    }),
})

export type ProgramaTejido = z.infer<typeof programaTejidoSchema>
export type ProgramaTejidoPage = z.infer<typeof programaTejidoPageSchema>
