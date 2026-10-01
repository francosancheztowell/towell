/**
 * <dialog> nativo en el top layer: [data-dialog-abrir="id"] abre el dialog con ese id (showModal).
 * Esc y el foco los da el navegador; un <form method="dialog"> cierra sin JS; clic en el fondo cierra.
 * Delegado en document: wire:navigate reemplaza el <body>.
 */
export function escucharDialogsNativos(): void {
    document.addEventListener('click', (e) => {
        const target = e.target as HTMLElement;
        const disparador = target.closest<HTMLElement>('[data-dialog-abrir]');
        if (disparador) {
            const dialog = document.getElementById(disparador.dataset.dialogAbrir ?? '');
            if (dialog instanceof HTMLDialogElement) dialog.showModal();
            return;
        }
        // El clic en el ::backdrop llega con target = el propio <dialog>.
        if (target instanceof HTMLDialogElement && target.open) target.close();
    });
}
