import * as React from 'react'
import * as DialogPrimitive from '@radix-ui/react-dialog'
import { X } from 'lucide-react'
import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '@/lib/utils'

export const Sheet = DialogPrimitive.Root
export const SheetTrigger = DialogPrimitive.Trigger
export const SheetClose = DialogPrimitive.Close

const sheetVariants = cva('fixed z-[10001] bg-card p-6 text-card-foreground shadow-2xl outline-none', {
    variants: {
        side: {
            top: 'inset-x-0 top-0 border-b border-border',
            bottom: 'inset-x-0 bottom-0 border-t border-border',
            left: 'inset-y-0 left-0 h-full w-3/4 border-r border-border sm:max-w-sm',
            right: 'inset-y-0 right-0 h-full w-3/4 border-l border-border sm:max-w-sm',
        },
    },
    defaultVariants: { side: 'right' },
})

interface SheetContentProps
    extends React.ComponentPropsWithoutRef<typeof DialogPrimitive.Content>,
        VariantProps<typeof sheetVariants> {}

export const SheetContent = React.forwardRef<React.ElementRef<typeof DialogPrimitive.Content>, SheetContentProps>(
    ({ side, className, children, ...props }, ref) => (
        <DialogPrimitive.Portal>
            <DialogPrimitive.Overlay className="fixed inset-0 z-[10000] bg-slate-950/55 backdrop-blur-sm" />
            <DialogPrimitive.Content ref={ref} className={cn(sheetVariants({ side }), className)} {...props}>
                {children}
                <DialogPrimitive.Close className="absolute right-4 top-4 rounded-sm text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring">
                    <X className="size-4" />
                    <span className="sr-only">Cerrar</span>
                </DialogPrimitive.Close>
            </DialogPrimitive.Content>
        </DialogPrimitive.Portal>
    ),
)
SheetContent.displayName = DialogPrimitive.Content.displayName

export function SheetHeader({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('flex flex-col space-y-2 text-left', className)} {...props} />
}

export const SheetTitle = DialogPrimitive.Title
export const SheetDescription = DialogPrimitive.Description
