import { useEffect } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { HttpError } from '@/lib/http'
import { pesoRolloFormSchema, type PesoRollo, type PesoRolloFormValues } from './schemas'

interface PesoRolloFormDialogProps {
    open: boolean
    onOpenChange: (open: boolean) => void
    record: PesoRollo | null
    pending: boolean
    onSubmit: (values: PesoRolloFormValues) => Promise<void>
}

const emptyValues: PesoRolloFormValues = {
    item_id: '',
    item_name: '',
    invent_size_id: '',
    peso_rollo: 0,
}

export function PesoRolloFormDialog({
    open,
    onOpenChange,
    record,
    pending,
    onSubmit,
}: PesoRolloFormDialogProps) {
    const form = useForm<PesoRolloFormValues>({
        resolver: zodResolver(pesoRolloFormSchema),
        defaultValues: emptyValues,
    })

    useEffect(() => {
        form.reset(record ? {
            item_id: record.item_id,
            item_name: record.item_name,
            invent_size_id: record.invent_size_id,
            peso_rollo: record.peso_rollo,
        } : emptyValues)
    }, [form, record, open])

    const submit = form.handleSubmit(async (values) => {
        try {
            await onSubmit(values)
            onOpenChange(false)
        } catch (error) {
            if (!(error instanceof HttpError)) throw error

            for (const [field, messages] of Object.entries(error.errors)) {
                if (field in emptyValues && messages[0]) {
                    form.setError(field as keyof PesoRolloFormValues, { message: messages[0] })
                }
            }

            form.setError('root', { message: error.message })
        }
    })

    return (
        <Dialog open={open} onOpenChange={(next) => !pending && onOpenChange(next)}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{record ? 'Editar peso por rollo' : 'Nuevo peso por rollo'}</DialogTitle>
                    <DialogDescription>
                        Laravel valida y guarda el registro; este formulario solo captura los datos.
                    </DialogDescription>
                </DialogHeader>

                <form className="space-y-4" onSubmit={submit} noValidate>
                    <FormField id="peso-rollo-item-id" label="Codigo de articulo" error={form.formState.errors.item_id?.message}>
                        <Input id="peso-rollo-item-id" maxLength={20} autoFocus {...form.register('item_id')} />
                    </FormField>
                    <FormField id="peso-rollo-item-name" label="Nombre" error={form.formState.errors.item_name?.message}>
                        <Input id="peso-rollo-item-name" maxLength={60} {...form.register('item_name')} />
                    </FormField>
                    <FormField id="peso-rollo-size" label="Tamano" error={form.formState.errors.invent_size_id?.message}>
                        <Input id="peso-rollo-size" maxLength={10} {...form.register('invent_size_id')} />
                    </FormField>
                    <FormField id="peso-rollo-weight" label="Peso por rollo (kg)" error={form.formState.errors.peso_rollo?.message}>
                        <Input id="peso-rollo-weight" type="number" min="0" step="0.01" {...form.register('peso_rollo', { valueAsNumber: true })} />
                    </FormField>

                    {form.formState.errors.root?.message && (
                        <p role="alert" className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            {form.formState.errors.root.message}
                        </p>
                    )}

                    <DialogFooter>
                        <Button type="submit" disabled={pending}>
                            {pending ? 'Guardando...' : record ? 'Guardar cambios' : 'Crear registro'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    )
}

function FormField({
    id,
    label,
    error,
    children,
}: {
    id: string
    label: string
    error?: string
    children: React.ReactNode
}) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    )
}
