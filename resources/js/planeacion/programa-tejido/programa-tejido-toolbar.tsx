import { useRef, useState, type FormEvent } from 'react'
import { Eraser, ExternalLink, Filter } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { ProgramaTejidoCriteria } from './api'

interface ProgramaTejidoToolbarProps {
    legacyUrl: string
    values: Pick<ProgramaTejidoCriteria, 'search' | 'filters'>
    onApply: (values: Pick<ProgramaTejidoCriteria, 'search' | 'filters'>) => void
}

type StatusFilter = 'all' | '1' | '0'

export function ProgramaTejidoToolbar({ legacyUrl, values, onApply }: ProgramaTejidoToolbarProps) {
    const formRef = useRef<HTMLFormElement>(null)
    const [status, setStatus] = useState<StatusFilter>(statusValue(values.filters.en_proceso))

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()
        const data = new FormData(event.currentTarget)

        onApply({
            search: String(data.get('search') ?? '').trim(),
            filters: {
                salon: optionalString(data.get('salon')),
                telar: optionalString(data.get('telar')),
                en_proceso: status === 'all' ? undefined : Number(status) as 0 | 1,
            },
        })
    }

    const clear = () => {
        formRef.current?.reset()
        setStatus('all')
        onApply({ search: '', filters: {} })
    }

    return (
        <div className="space-y-3 rounded-lg border border-border bg-card p-4 shadow-sm">
            <div className="flex flex-wrap items-center gap-2">
                <p className="text-sm text-muted-foreground">
                    Lectura paginada. Las operaciones siguen temporalmente en la vista anterior.
                </p>
                <Button type="button" variant="outline" asChild className="ml-auto">
                    <a href={legacyUrl}><ExternalLink className="size-4" />Abrir operaciones</a>
                </Button>
            </div>

            <form ref={formRef} className="grid gap-2 md:grid-cols-6" onSubmit={submit}>
                <Input
                    name="search"
                    defaultValue={values.search}
                    placeholder="Orden, producto, articulo, tamano o Flog"
                    className="md:col-span-2"
                    aria-label="Busqueda general"
                />
                <Input
                    name="salon"
                    defaultValue={values.filters.salon}
                    placeholder="Salón"
                    aria-label="Filtrar por salón"
                />
                <Input
                    name="telar"
                    defaultValue={values.filters.telar}
                    placeholder="Telar"
                    aria-label="Filtrar por telar"
                />
                <Select value={status} onValueChange={(value) => setStatus(value as StatusFilter)}>
                    <SelectTrigger aria-label="Filtrar por estado">
                        <SelectValue placeholder="Estado" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos los estados</SelectItem>
                        <SelectItem value="1">En proceso</SelectItem>
                        <SelectItem value="0">Programado</SelectItem>
                    </SelectContent>
                </Select>
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

function statusValue(value: 0 | 1 | undefined): StatusFilter {
    if (value === 1) return '1'
    if (value === 0) return '0'
    return 'all'
}

function optionalString(value: FormDataEntryValue | null): string | undefined {
    const parsed = String(value ?? '').trim()
    return parsed === '' ? undefined : parsed
}
