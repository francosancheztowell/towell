import { useMemo, useState } from 'react'
import type { ColumnDef, PaginationState, SortingState } from '@tanstack/react-table'
import { ListChecks } from 'lucide-react'
import { DataTable } from '@/components/data-table'
import { ErrorState } from '@/components/page-feedback'
import { Badge } from '@/components/ui/badge'
import type { ProgramaTejidoCriteria } from './api'
import { ProgramaTejidoToolbar } from './programa-tejido-toolbar'
import { useProgramaTejido } from './queries'
import type { ProgramaTejido } from './schemas'

interface ProgramaTejidoPageProps {
    indexUrl: string
    legacyUrl: string
}

const initialFilters: Pick<ProgramaTejidoCriteria, 'search' | 'filters'> = {
    search: '',
    filters: {},
}

export function ProgramaTejidoPage({ indexUrl, legacyUrl }: ProgramaTejidoPageProps) {
    const [pagination, setPagination] = useState<PaginationState>({ pageIndex: 0, pageSize: 25 })
    const [sorting, setSorting] = useState<SortingState>([{ id: 'telar', desc: false }])
    const [filters, setFilters] = useState(initialFilters)
    const sort = sorting[0] ?? { id: 'telar', desc: false }
    const criteria: ProgramaTejidoCriteria = {
        page: pagination.pageIndex + 1,
        perPage: pagination.pageSize,
        search: filters.search,
        sort: sort.id,
        direction: sort.desc ? 'desc' : 'asc',
        filters: filters.filters,
    }
    const query = useProgramaTejido(indexUrl, criteria)
    const columns = useMemo<Array<ColumnDef<ProgramaTejido>>>(() => buildColumns(), [])

    const applyFilters = (next: Pick<ProgramaTejidoCriteria, 'search' | 'filters'>) => {
        setFilters(next)
        setPagination((current) => ({ ...current, pageIndex: 0 }))
    }

    return (
        <section className="space-y-4 p-3 sm:p-5" aria-labelledby="programa-tejido-title">
            <header className="flex flex-wrap items-start gap-3">
                <div className="rounded-lg bg-primary/10 p-2 text-primary"><ListChecks className="size-6" /></div>
                <div>
                    <h1 id="programa-tejido-title" className="text-2xl font-semibold tracking-tight">
                        Programa de Tejido
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Vista React inicial con datos y filtros controlados por Laravel.
                    </p>
                </div>
                {query.isFetching && !query.isLoading ? (
                    <Badge variant="secondary" className="ml-auto">Actualizando</Badge>
                ) : null}
            </header>

            <ProgramaTejidoToolbar legacyUrl={legacyUrl} values={filters} onApply={applyFilters} />

            {query.isError && !query.data ? (
                <ErrorState
                    message={query.error instanceof Error ? query.error.message : 'Error desconocido.'}
                    onRetry={() => void query.refetch()}
                />
            ) : (
                <DataTable
                    columns={columns}
                    data={query.data?.data ?? []}
                    rowCount={query.data?.meta.total ?? 0}
                    pagination={pagination}
                    onPaginationChange={setPagination}
                    sorting={sorting}
                    onSortingChange={(updater) => {
                        setSorting(updater)
                        setPagination((current) => ({ ...current, pageIndex: 0 }))
                    }}
                    getRowId={(row) => String(row.id)}
                    isLoading={query.isLoading}
                    emptyMessage="No se encontraron órdenes con los filtros actuales."
                />
            )}
        </section>
    )
}

function buildColumns(): Array<ColumnDef<ProgramaTejido>> {
    return [
        {
            accessorKey: 'en_proceso',
            header: 'Estado',
            cell: ({ row }) => row.original.en_proceso
                ? <Badge>En proceso</Badge>
                : <Badge variant="secondary">Programado</Badge>,
        },
        { accessorKey: 'salon', header: 'Salón', cell: nullableCell('salon') },
        { accessorKey: 'telar', header: 'Telar', cell: nullableCell('telar') },
        { accessorKey: 'posicion', header: 'Pos.', cell: nullableCell('posicion') },
        { accessorKey: 'orden_produccion', header: 'Orden', cell: nullableCell('orden_produccion') },
        { accessorKey: 'producto', header: 'Producto', cell: nullableCell('producto') },
        { accessorKey: 'item_id', header: 'Artículo', cell: nullableCell('item_id') },
        { accessorKey: 'invent_size_id', header: 'Tamaño', cell: nullableCell('invent_size_id') },
        { accessorKey: 'total_pedido', header: 'Pedido', cell: numberCell('total_pedido') },
        { accessorKey: 'produccion', header: 'Producción', cell: numberCell('produccion') },
        {
            accessorKey: 'saldo_pedido',
            header: 'Saldo',
            cell: ({ row }) => {
                const value = row.original.saldo_pedido
                return value === null
                    ? <span className="text-muted-foreground">—</span>
                    : <span className={value < 0 ? 'font-semibold text-destructive' : undefined}>{formatNumber(value)}</span>
            },
        },
        { accessorKey: 'fecha_inicio', header: 'Inicio', cell: dateCell('fecha_inicio') },
        { accessorKey: 'fecha_final', header: 'Fin', cell: dateCell('fecha_final') },
        { accessorKey: 'prioridad', header: 'Prioridad', cell: nullableCell('prioridad') },
    ]
}

function nullableCell(key: keyof ProgramaTejido) {
    return ({ row }: { row: { original: ProgramaTejido } }) => {
        const value = row.original[key]
        return value === null || value === ''
            ? <span className="text-muted-foreground">—</span>
            : <span className="whitespace-nowrap">{String(value)}</span>
    }
}

function numberCell(key: 'total_pedido' | 'produccion') {
    return ({ row }: { row: { original: ProgramaTejido } }) => {
        const value = row.original[key]
        return value === null ? <span className="text-muted-foreground">—</span> : formatNumber(value)
    }
}

function dateCell(key: 'fecha_inicio' | 'fecha_final') {
    return ({ row }: { row: { original: ProgramaTejido } }) => {
        const value = row.original[key]
        return value === null ? <span className="text-muted-foreground">—</span> : formatDate(value)
    }
}

function formatNumber(value: number): string {
    return value.toLocaleString('es-MX', { maximumFractionDigits: 2 })
}

function formatDate(value: string): string {
    const date = new Date(value)
    return Number.isNaN(date.getTime())
        ? '—'
        : date.toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' })
}
