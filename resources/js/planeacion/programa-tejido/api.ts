import { apiClient } from '@/lib/http'
import { programaTejidoPageSchema, type ProgramaTejidoPage } from './schemas'

export interface ProgramaTejidoCriteria {
    page: number
    perPage: number
    search: string
    sort: string
    direction: 'asc' | 'desc'
    filters: {
        salon?: string
        telar?: string
        en_proceso?: 0 | 1
    }
}

export async function listProgramaTejido(
    url: string,
    criteria: ProgramaTejidoCriteria,
): Promise<ProgramaTejidoPage> {
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

    return programaTejidoPageSchema.parse(payload)
}
