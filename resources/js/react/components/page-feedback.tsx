import { AlertCircle, Inbox } from 'lucide-react'
import { Button } from '@/components/ui/button'

export function EmptyState({ title, description }: { title: string; description: string }) {
    return (
        <div className="flex min-h-48 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border bg-card p-8 text-center">
            <Inbox className="size-8 text-muted-foreground" />
            <h3 className="font-semibold text-foreground">{title}</h3>
            <p className="max-w-md text-sm text-muted-foreground">{description}</p>
        </div>
    )
}

export function ErrorState({ message, onRetry }: { message: string; onRetry: () => void }) {
    return (
        <div role="alert" className="flex min-h-48 flex-col items-center justify-center gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-8 text-center">
            <AlertCircle className="size-8 text-destructive" />
            <div>
                <h3 className="font-semibold text-foreground">No fue posible cargar la informacion</h3>
                <p className="mt-1 max-w-lg text-sm text-muted-foreground">{message}</p>
            </div>
            <Button type="button" variant="outline" onClick={onRetry}>Reintentar</Button>
        </div>
    )
}
