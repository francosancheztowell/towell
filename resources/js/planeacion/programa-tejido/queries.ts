import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { listProgramaTejido, type ProgramaTejidoCriteria } from './api'

const rootKey = ['planeacion', 'programa-tejido'] as const

export function useProgramaTejido(indexUrl: string, criteria: ProgramaTejidoCriteria) {
    return useQuery({
        queryKey: [...rootKey, criteria],
        queryFn: () => listProgramaTejido(indexUrl, criteria),
        placeholderData: keepPreviousData,
    })
}
