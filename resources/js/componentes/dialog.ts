/**
 * Runtime de x-ui.modal-base (DS-02). El modal es un <dialog> no modal: la clase `hidden`
 * decide si se ve (así lo abre y cierra el JS existente, p. ej. Programa Tejido) y aquí solo
 * se sincroniza lo que el <div> anterior no tenía: el atributo `open`, el foco (entra al
 * primer control, queda atrapado con Tab y vuelve al cerrar), Esc y el clic en el fondo.
 *
 * Cerrar siempre pasa por el botón × ([data-ui-modal-close]): así Esc y el fondo ejecutan el
 * mismo `onclose` que definió la vista.
 */

const SELECTOR = 'dialog[data-ui-modal]';
const ENFOCABLES =
    'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

const focoPrevio = new WeakMap<HTMLDialogElement, HTMLElement | null>();
const observados = new WeakSet<HTMLDialogElement>();

export function estaVisible(dialog: HTMLDialogElement): boolean {
    return !dialog.classList.contains('hidden');
}

/** El modal visible de más arriba (el último en el documento). */
export function modalActivo(root: ParentNode = document): HTMLDialogElement | null {
    const visibles = [...root.querySelectorAll<HTMLDialogElement>(SELECTOR)].filter(estaVisible);

    return visibles.at(-1) ?? null;
}

function enfocables(dialog: HTMLDialogElement): HTMLElement[] {
    return [...dialog.querySelectorAll<HTMLElement>(ENFOCABLES)];
}

/** Lleva `open`, `hidden` y el foco al mismo estado. Idempotente. */
export function sincronizar(dialog: HTMLDialogElement): void {
    const visible = estaVisible(dialog);

    if (visible && !dialog.hasAttribute('open')) {
        const activo = document.activeElement;
        focoPrevio.set(dialog, activo instanceof HTMLElement ? activo : null);
        dialog.setAttribute('open', '');
        const controles = enfocables(dialog);
        // El primer control del cuerpo, no la × del header, si lo hay.
        const destino =
            dialog.querySelector<HTMLElement>('[autofocus]') ??
            controles.find((el) => !el.hasAttribute('data-ui-modal-close')) ??
            controles[0];
        destino?.focus();
    } else if (!visible && dialog.hasAttribute('open')) {
        dialog.removeAttribute('open');
        const previo = focoPrevio.get(dialog);
        focoPrevio.delete(dialog);
        if (previo && previo.isConnected) previo.focus();
    }
}

/** Ejecuta el cierre de la vista (el onclick del botón ×). */
export function cerrar(dialog: HTMLDialogElement): void {
    const boton = dialog.querySelector<HTMLElement>('[data-ui-modal-close]');
    if (boton) {
        boton.click();
    } else {
        dialog.classList.add('hidden');
    }
}

/** Abre por id con el mismo efecto que usa la app: quitar `hidden` y bloquear el scroll. */
export function abrir(id: string): void {
    const dialog = document.getElementById(id);
    if (!(dialog instanceof HTMLDialogElement)) return;
    dialog.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    sincronizar(dialog);
}

export function cerrarPorId(id: string): void {
    const dialog = document.getElementById(id);
    if (dialog instanceof HTMLDialogElement) cerrar(dialog);
}

/** Registra los <dialog data-ui-modal> de `root` (los ya registrados se ignoran). */
export function iniciarModales(root: ParentNode = document): void {
    root.querySelectorAll<HTMLDialogElement>(SELECTOR).forEach((dialog) => {
        if (observados.has(dialog)) return;
        observados.add(dialog);
        new MutationObserver(() => sincronizar(dialog)).observe(dialog, {
            attributes: true,
            attributeFilter: ['class'],
        });
        sincronizar(dialog);
    });
}

function hayAlertaEncima(): boolean {
    // Un SweetAlert2 abierto sobre el modal maneja su propio Esc.
    return document.querySelector('.swal2-container') !== null;
}

let escuchando = false;

/**
 * Listeners de documento (una sola vez): Esc, Tab atrapado, clic en el fondo y botones
 * declarativos [data-ui-modal-open="id"] / [data-ui-modal-close-target="id"] (sin id: el modal que lo contiene).
 */
export function escucharDocumento(): void {
    if (escuchando) return;
    escuchando = true;

    document.addEventListener('keydown', (e) => {
        if (e.defaultPrevented) return;
        const dialog = modalActivo();
        if (!dialog) return;

        if (e.key === 'Escape' && !hayAlertaEncima()) {
            e.preventDefault();
            cerrar(dialog);
            return;
        }

        if (e.key === 'Tab') {
            const controles = enfocables(dialog);
            const primero = controles[0];
            const ultimo = controles.at(-1);
            if (!primero || !ultimo) return;
            const dentro = dialog.contains(document.activeElement);
            if (e.shiftKey && (document.activeElement === primero || !dentro)) {
                e.preventDefault();
                ultimo.focus();
            } else if (!e.shiftKey && (document.activeElement === ultimo || !dentro)) {
                e.preventDefault();
                primero.focus();
            }
        }
    });

    document.addEventListener('click', (e) => {
        const objetivo = e.target;
        if (!(objetivo instanceof Element)) return;

        // Apertura declarativa: <button data-ui-modal-open="miModal">, sin onclick.
        const abridor = objetivo.closest<HTMLElement>('[data-ui-modal-open]');
        if (abridor?.dataset.uiModalOpen) {
            abrir(abridor.dataset.uiModalOpen);
            return;
        }

        // Botones propios de cierre (p. ej. "Cancelar" del footer): mismo camino que la ×.
        const cerrador = objetivo.closest<HTMLElement>('[data-ui-modal-close-target]');
        if (cerrador) {
            const id = cerrador.dataset.uiModalCloseTarget;
            const destino = id ? document.getElementById(id) : cerrador.closest(SELECTOR);
            if (destino instanceof HTMLDialogElement) cerrar(destino);
            return;
        }

        const dialog = objetivo.closest<HTMLDialogElement>(SELECTOR);
        if (!dialog || !dialog.hasAttribute('data-ui-modal-backdrop-close')) return;
        if (objetivo === dialog || objetivo.hasAttribute('data-ui-modal-backdrop')) cerrar(dialog);
    });
}
