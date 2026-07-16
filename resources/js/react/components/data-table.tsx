import {
    flexRender,
    getCoreRowModel,
    useReactTable,
    type ColumnDef,
    type OnChangeFn,
    type PaginationState,
    type Row,
    type SortingState,
} from '@tanstack/react-table'
import { ArrowUpDown, ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { cn } from '@/lib/utils'

interface DataTableProps<TData, TValue> {
    columns: Array<ColumnDef<TData, TValue>>
    data: TData[]
    rowCount: number
    pagination: PaginationState
    onPaginationChange: OnChangeFn<PaginationState>
    sorting: SortingState
    onSortingChange: OnChangeFn<SortingState>
    getRowId: (row: TData) => string
    isLoading?: boolean
    emptyMessage?: string
    selectedRowId?: string | null
    onRowClick?: (row: Row<TData>) => void
}

export function DataTable<TData, TValue>({
    columns,
    data,
    rowCount,
    pagination,
    onPaginationChange,
    sorting,
    onSortingChange,
    getRowId,
    isLoading = false,
    emptyMessage = 'No hay registros para mostrar.',
    selectedRowId,
    onRowClick,
}: DataTableProps<TData, TValue>) {
    const pageCount = Math.max(1, Math.ceil(rowCount / pagination.pageSize))
    const table = useReactTable({
        data,
        columns,
        getRowId,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        manualSorting: true,
        rowCount,
        pageCount,
        state: { pagination, sorting },
        onPaginationChange,
        onSortingChange,
    })

    return (
        <div className="space-y-3">
            <div className="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
                <Table>
                    <TableHeader className="bg-muted/70">
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id}>
                                        {header.isPlaceholder ? null : header.column.getCanSort() ? (
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1 font-semibold text-foreground"
                                                onClick={header.column.getToggleSortingHandler()}
                                            >
                                                {flexRender(header.column.columnDef.header, header.getContext())}
                                                <ArrowUpDown className="size-3.5 text-muted-foreground" />
                                            </button>
                                        ) : (
                                            flexRender(header.column.columnDef.header, header.getContext())
                                        )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {isLoading ? (
                            Array.from({ length: Math.min(pagination.pageSize, 8) }).map((_, rowIndex) => (
                                <TableRow key={`loading-${rowIndex}`}>
                                    {columns.map((_, cellIndex) => (
                                        <TableCell key={`loading-${rowIndex}-${cellIndex}`}>
                                            <Skeleton className="h-4 w-full" />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : table.getRowModel().rows.length > 0 ? (
                            table.getRowModel().rows.map((row) => {
                                const selected = selectedRowId === row.id
                                return (
                                    <TableRow
                                        key={row.id}
                                        aria-selected={selected}
                                        className={cn(
                                            onRowClick && 'cursor-pointer',
                                            selected && 'bg-primary/10 hover:bg-primary/15',
                                        )}
                                        onClick={() => onRowClick?.(row)}
                                    >
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell key={cell.id}>
                                                {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                )
                            })
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="h-28 text-center text-muted-foreground">
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            <div className="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <span>{rowCount.toLocaleString('es-MX')} registros</span>
                <div className="flex items-center gap-2">
                    <Select
                        value={String(pagination.pageSize)}
                        onValueChange={(value) => table.setPageSize(Number(value))}
                    >
                        <SelectTrigger className="w-[112px]" aria-label="Filas por pagina">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {[10, 25, 50, 100].map((size) => (
                                <SelectItem key={size} value={String(size)}>{size} filas</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <span className="min-w-24 text-center">
                        Pagina {pagination.pageIndex + 1} de {pageCount}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => table.previousPage()}
                        disabled={!table.getCanPreviousPage() || isLoading}
                        aria-label="Pagina anterior"
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => table.nextPage()}
                        disabled={!table.getCanNextPage() || isLoading}
                        aria-label="Pagina siguiente"
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    )
}
