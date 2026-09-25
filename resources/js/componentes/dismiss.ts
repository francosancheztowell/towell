/**
 * Autocierre de avisos: los elementos con [data-ui-autoclose="ms"] se quitan solos
 * (x-ui.flash lo pone en éxito/info; los errores se quedan hasta que el usuario los cierra).
 * El cierre manual de x-ui.alert no necesita JS (checkbox + has-checked en el componente).
 */

export function iniciarAutocierre(root: ParentNode = document): void {
    root.querySelectorAll<HTMLElement>('[data-ui-autoclose]').forEach((el) => {
        const ms = Number(el.dataset.uiAutoclose);
        el.removeAttribute('data-ui-autoclose');
        if (ms > 0) setTimeout(() => el.remove(), ms);
    });
}
