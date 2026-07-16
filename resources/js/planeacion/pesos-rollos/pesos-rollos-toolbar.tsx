import { useRef, type FormEvent } from 'react'
import { Eraser, ExternalLink, Filter, Pencil, Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import type { PesoRolloCriteria } from './api'

interface ToolbarProps {
    legacyUrl: string
    selected: boolean
    values: Pick<PesoRolloCriteria, 'search' | 'filters'>
    onApply: (values: Pick<PesoRolloCriteria, 'search' | 'filters'>) => void
    onCreate: () => void
    onEdit: () => void
    onDelete: () => void
}

export function PesosRollosToolbar({
    legacyUrl,
    selected,
    values,
    onApply,
    onCreate,
    onEdit,
    onDelete,
}: ToolbarProps) {
    const formRef = useRef<HTMLFormElement>(null)

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()
        const data = new FormData(event.currentTarget)
        const pesoMin = optionalNumber(data.get('peso_min'))
        const pesoMax = optionalNumber(data.get('peso_max'))

        onApply({
            search: String(data.get('search') ?? '').trim(),
            filters: {
                item_id: optionalString(data.get('item_id')),
                invent_size_id: optionalString(data.get('invent_size_id')),
                peso_min: pesoMin,
                peso_max: pesoMax,
            },
        })
    }

    const clear = () => {
        formRef.current?.reset()
        onApply({ search: '', filters: {} })
    }

    return (
        <div className="space-y-3 rounded-lg border border-border bg-card p-4 shadow-sm">
            <div className="flex flex-wrap items-center gap-2">
                <Button type="button" onClick={onCreate}><Plus className="size-4" />Nuevo</Button>
                <Button type="button" variant="outline" onClick={onEdit} disabled={!selected}>
                    <Pencil className="size-4" />Editar
                </Button>
                <Button type="button" variant="destructive" onClick={onDelete} disabled={!selected}>
                    <Trash2 className="size-4" />Eliminar
                </Button>
                <Button type="button" variant="ghost" asChild className="ml-auto">
                    <a href={legacyUrl}><ExternalLink className="size-4" />Vista anterior</a>
                </Button>
            </div>

            <form ref={formRef} className="grid gap-2 md:grid-cols-6" onSubmit={submit}>
                <Input
                    name="search"
                    defaultValue={values.search}
                    placeholder="Buscar en todo el catalogo"
                    className="md:col-span-2"
                    aria-label="Busqueda general"
                />
                <Input name="item_id" defaultValue={values.filters.item_id} placeholder="Codigo" aria-label="Filtrar por codigo" />
                <Input name="invent_size_id" defaultValue={values.filters.invent_size_id} placeholder="Tamano" aria-label="Filtrar por tamano" />
                <div className="grid grid-cols-2 gap-2">
                    <Input name="peso_min" type="number" min="0" step="0.01" defaultValue={values.filters.peso_min} placeholder="Peso min." aria-label="Peso minimo" />
                    <Input name="peso_max" type="number" min="0" step="0.01" defaultValue={values.filters.peso_max} placeholder="Peso max." aria-label="Peso maximo" />
                </div>
                <div className="flex gap-2">
                    <Button type="submit" variant="secondary"><Filter className="size-4" />Aplicar</Button>
                    <Button type="button" variant="ghost" size="icon" onClick={clear} aria-label="Limpiar filtros">
                        <Eraser className="size-4" />
                    </Button>
                </div>
            </form>
        </div>
    )
}

function optionalString(value: FormDataEntryValue | null): string | undefined {
    const parsed = String(value ?? '').trim()
    return parsed === '' ? undefined : parsed
}

function optionalNumber(value: FormDataEntryValue | null): number | undefined {
    const parsed = optionalString(value)
    if (parsed === undefined) return undefined

    const number = Number(parsed)
    return Number.isFinite(number) ? number : undefined
}
