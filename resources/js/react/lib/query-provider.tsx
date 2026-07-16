import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { Toaster } from '@/components/ui/sonner'
import { HttpError } from '@/lib/http'

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 30_000,
            refetchOnWindowFocus: false,
            retry: (failureCount, error) => {
                if (error instanceof HttpError && error.status >= 400 && error.status < 500) return false
                return failureCount < 2
            },
        },
        mutations: {
            retry: false,
        },
    },
})

export function PlaneacionProvider({ children }: { children: ReactNode }) {
    return (
        <QueryClientProvider client={queryClient}>
            {children}
            <Toaster />
        </QueryClientProvider>
    )
}
