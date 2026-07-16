import type { ColumnDef } from '@tanstack/react-table'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { DataTable } from './data-table'

interface RowData {
    id: number
    name: string
}

const columns: Array<ColumnDef<RowData>> = [
    { accessorKey: 'name', header: 'Nombre' },
]

describe('DataTable', () => {
    it('renderiza filas y comunica la seleccion sin estado global', async () => {
        const user = userEvent.setup()
        const onRowClick = vi.fn()

        render(
            <DataTable
                columns={columns}
                data={[{ id: 1, name: 'Rollo azul' }]}
                rowCount={1}
                pagination={{ pageIndex: 0, pageSize: 10 }}
                onPaginationChange={vi.fn()}
                sorting={[]}
                onSortingChange={vi.fn()}
                getRowId={(row) => String(row.id)}
                onRowClick={onRowClick}
            />,
        )

        await user.click(screen.getByText('Rollo azul'))
        expect(onRowClick).toHaveBeenCalledOnce()
        expect(screen.getByText('1 registros')).toBeInTheDocument()
    })

    it('presenta un estado vacio consistente', () => {
        render(
            <DataTable
                columns={columns}
                data={[]}
                rowCount={0}
                pagination={{ pageIndex: 0, pageSize: 10 }}
                onPaginationChange={vi.fn()}
                sorting={[]}
                onSortingChange={vi.fn()}
                getRowId={(row) => String(row.id)}
                emptyMessage="Sin coincidencias"
            />,
        )

        expect(screen.getByText('Sin coincidencias')).toBeInTheDocument()
    })
})
