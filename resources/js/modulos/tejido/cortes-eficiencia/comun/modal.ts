/** Abre/cierra un x-ui.modal-base (runtime de componentes; respaldo si aún no cargó). */
export function abrirModal(id: string): void {
    if (window.uiModal) {
        window.uiModal.abrir(id);
        return;
    }
    document.getElementById(id)?.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

export function cerrarModal(id: string): void {
    if (window.uiModal) {
        window.uiModal.cerrar(id);
        return;
    }
    document.getElementById(id)?.classList.add('hidden');
    document.body.style.overflow = '';
}
