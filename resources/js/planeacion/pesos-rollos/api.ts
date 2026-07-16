import { apiClient } from '@/lib/http'
import {
    pesoRolloMutationSchema,
    pesoRolloPageSchema,
    type PesoRolloFormValues,
    type PesoRolloMutation,
    type PesoRolloPage,
} from './schemas'

export interface PesoRolloCriteria {
    page: number
    perPage: number
    search: string
    sort: string
    direction: 'asc' | 'desc'
    filters: {
        item_id?: string
        item_name?: string
        invent_size_id?: string
        peso_min?: number
        peso_max?: number
    }
}

export async function listPesoRollos(url: string, criteria: PesoRolloCriteria): Promise<PesoRolloPage> {
    const payload = await apiClient.get<unknown>(url, {
        params: {
            page: criteria.page,
            per_page: criteria.perPage,
            search: criteria.search || undefined,
            sort: criteria.sort,
            direction: criteria.direction,
            filters: criteria.filters,
        },
    })

    return pesoRolloPageSchema.parse(payload)
}

export async function createPesoRollo(url: string, values: PesoRolloFormValues): Promise<PesoRolloMutation> {
    const payload = await apiClient.post<unknown>(url, values)
    return pesoRolloMutationSchema.parse(payload)
}

export async function updatePesoRollo(
    url: string,
    id: number,
    values: PesoRolloFormValues,
): Promise<PesoRolloMutation> {
    const payload = await apiClient.put<unknown>(`${url}/${id}`, values)
    return pesoRolloMutationSchema.parse(payload)
}

export async function deletePesoRollo(url: string, id: number): Promise<PesoRolloMutation> {
    const payload = await apiClient.delete<unknown>(`${url}/${id}`)
    return pesoRolloMutationSchema.parse(payload)
}
