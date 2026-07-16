import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
    createPesoRollo,
    deletePesoRollo,
    listPesoRollos,
    updatePesoRollo,
    type PesoRolloCriteria,
} from './api'
import type { PesoRolloFormValues } from './schemas'

const rootKey = ['planeacion', 'pesos-rollos'] as const

export function usePesoRollos(indexUrl: string, criteria: PesoRolloCriteria) {
    return useQuery({
        queryKey: [...rootKey, criteria],
        queryFn: () => listPesoRollos(indexUrl, criteria),
        placeholderData: keepPreviousData,
    })
}

export function usePesoRolloMutations(indexUrl: string) {
    const queryClient = useQueryClient()
    const invalidate = () => queryClient.invalidateQueries({ queryKey: rootKey })

    return {
        create: useMutation({
            mutationFn: (values: PesoRolloFormValues) => createPesoRollo(indexUrl, values),
            onSuccess: invalidate,
        }),
        update: useMutation({
            mutationFn: ({ id, values }: { id: number; values: PesoRolloFormValues }) =>
                updatePesoRollo(indexUrl, id, values),
            onSuccess: invalidate,
        }),
        remove: useMutation({
            mutationFn: (id: number) => deletePesoRollo(indexUrl, id),
            onSuccess: invalidate,
        }),
    }
}
