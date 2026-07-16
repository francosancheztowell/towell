import { useEffect, useMemo, useState } from 'react'
import type { ColumnDef, PaginationState, SortingState } from '@tanstack/react-table'
import { Weight } from 'lucide-react'
import { toast } from 'sonner'
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { DataTable } from '@/components/data-table'
import { ErrorState } from '@/components/page-feedback'
import { type PesoRolloCriteria } from './api'
import { PesoRolloFormDialog } from './peso-rollo-form-dialog'
import { PesosRollosToolbar } from './pesos-rollos-toolbar'
import { usePesoRolloMutations, usePesoRollos } from './queries'
import type { PesoRollo, PesoRolloFormValues } from './schemas'

interface PesosRollosPageProps {
    indexUrl: string
    legacyUrl: string
}

const initialFilters: Pick<PesoRolloCriteria, 'search' | 'filters'> = {
    search: '',
    filters: {},
}

export function PesosRollosPage({ indexUrl, legacyUrl }: PesosRollosPageProps) {
    const [pagination, setPagination] = useState<PaginationState>({ pageIndex: 0, pageSize: 25 })
    const [sorting, setSorting] = useState<SortingState>([{ id: 'item_id', desc: false }])
    const [filters, setFilters] = useState(initialFilters)
    const [selected, setSelected] = useState<PesoRollo | null>(null)
    const [formOpen, setFormOpen] = useState(false)
    const [editing, setEditing] = useState<PesoRollo | null>(null)
    const [deleteOpen, setDeleteOpen] = useState(false)

    const sort = sorting[0] ?? { id: 'item_id', desc: false }
    const criteria: PesoRolloCriteria = {
        page: pagination.pageIndex + 1,
        perPage: pagination.pageSize,
        search: filters.search,
        sort: sort.id,
        direction: sort.desc ? 'desc' : 'asc',
        filters: filters.filters,
    }
    const query = usePesoRollos(indexUrl, criteria)
    const mutations = usePesoRolloMutations(indexUrl)

    useEffect(() => {
        if (selected && !query.data?.data.some((row) => row.id === selected.id)) setSelected(null)
    }, [query.data, selected])

    const columns = useMemo<Array<ColumnDef<PesoRollo>>>(() => [
        { accessorKey: 'item_id', header: 'Codigo' },
        { accessorKey: 'item_name', header: 'Nombre' },
        { accessorKey: 'invent_size_id', header: 'Tamano' },
        {
            accessorKey: 'peso_rollo',
            header: 'Peso por rollo',
            cell: ({ row }) => `${row.original.peso_rollo.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} kg`,
        },
    ], [])

    const applyFilters = (next: Pick<PesoRolloCriteria, 'search' | 'filters'>) => {
        setFilters(next)
        setPagination((current) => ({ ...current, pageIndex: 0 }))
        setSelected(null)
    }

    const submitForm = async (values: PesoRolloFormValues) => {
        const result = editing
            ? await mutations.update.mutateAsync({ id: editing.id, values })
            : await mutations.create.mutateAsync(values)
        toast.success(result.message)
        setSelected(result.data)
    }

    const confirmDelete = async () => {
        if (!selected) return

        try {
            const result = await mutations.remove.mutateAsync(selected.id)
            toast.success(result.message)
            setSelected(null)
            setDeleteOpen(false)
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'No fue posible eliminar el registro.')
        }
    }

    const openCreate = () => {
        setEditing(null)
        setFormOpen(true)
    }

    const openEdit = () => {
        if (!selected) return
        setEditing(selected)
        setFormOpen(true)
    }

    return (
        <section className="space-y-4 p-3 sm:p-5" aria-labelledby="pesos-rollos-title">
            <header className="flex items-start gap-3">
                <div className="rounded-lg bg-primary/10 p-2 text-primary"><Weight className="size-6" /></div>
                <div>
                    <h1 id="pesos-rollos-title" className="text-2xl font-semibold tracking-tight">Pesos por rollo</h1>
                    <p className="text-sm text-muted-foreground">
                        Catalogo paginado; validacion y persistencia controladas por Laravel.
                    </p>
                </div>
            </header>

            <PesosRollosToolbar
                legacyUrl={legacyUrl}
                selected={selected !== null}
                values={filters}
                onApply={applyFilters}
                onCreate={openCreate}
                onEdit={openEdit}
                onDelete={() => setDeleteOpen(true)}
            />

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
                    selectedRowId={selected ? String(selected.id) : null}
                    onRowClick={(row) => setSelected(row.original)}
                    isLoading={query.isLoading || query.isFetching}
                    emptyMessage="No se encontraron pesos con los filtros actuales."
                />
            )}

            <PesoRolloFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                record={editing}
                pending={mutations.create.isPending || mutations.update.isPending}
                onSubmit={submitForm}
            />

            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Eliminar peso por rollo</AlertDialogTitle>
                        <AlertDialogDescription>
                            Se eliminara {selected?.item_id} / {selected?.invent_size_id}. Esta accion no se puede deshacer.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={mutations.remove.isPending}>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            disabled={mutations.remove.isPending}
                            onClick={(event) => {
                                event.preventDefault()
                                void confirmDelete()
                            }}
                        >
                            {mutations.remove.isPending ? 'Eliminando...' : 'Eliminar'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </section>
    )
}
