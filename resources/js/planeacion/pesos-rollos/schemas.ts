import { z } from 'zod'

export const pesoRolloSchema = z.object({
    id: z.number().int().positive(),
    item_id: z.string(),
    item_name: z.string(),
    invent_size_id: z.string(),
    peso_rollo: z.number().nonnegative(),
    fecha_creacion: z.string().nullable(),
    usuario_crea: z.string().nullable(),
    fecha_modificacion: z.string().nullable(),
    usuario_modifica: z.string().nullable(),
})

export const pesoRolloFormSchema = z.object({
    item_id: z.string().trim().min(1, 'El codigo de articulo es obligatorio.').max(20),
    item_name: z.string().trim().min(1, 'El nombre es obligatorio.').max(60),
    invent_size_id: z.string().trim().min(1, 'El tamano es obligatorio.').max(10),
    peso_rollo: z.number({ message: 'Captura un peso valido.' }).min(0, 'El peso no puede ser negativo.'),
})

export const pesoRolloPageSchema = z.object({
    data: z.array(pesoRolloSchema),
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

export const pesoRolloMutationSchema = z.object({
    data: pesoRolloSchema.nullable(),
    message: z.string(),
})

export type PesoRollo = z.infer<typeof pesoRolloSchema>
export type PesoRolloFormValues = z.infer<typeof pesoRolloFormSchema>
export type PesoRolloPage = z.infer<typeof pesoRolloPageSchema>
export type PesoRolloMutation = z.infer<typeof pesoRolloMutationSchema>
