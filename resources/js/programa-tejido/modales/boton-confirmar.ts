// Botón primario del diálogo nativo (utils/dialogo.ts) que está abierto ahora mismo.
// Se busca en cada uso: una referencia tomada al montar el modal queda apuntando al botón
// de un diálogo anterior al reabrirlo, y el texto (Duplicar/Dividir/Vincular) y el
// disabled se aplicaban a un botón que ya no se ve.
export function botonConfirmarAbierto(raiz: ParentNode = document): HTMLButtonElement | null {
    return raiz.querySelector<HTMLButtonElement>('dialog.ui-dialogo[open] .ui-dialogo__boton--primario');
}
