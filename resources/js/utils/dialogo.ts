/**
 * Diálogo modal nativo (<dialog> + showModal) con el aspecto de Flux: reemplaza a SweetAlert2
 * en notify.confirm/alert/validation/loading. Estilos: bloque .ui-dialogo de resources/css/app.css.
 *
 *   const valor = await dialogo({ tono: 'danger', titulo: '¿Eliminar?', botones: [...] });
 *   // valor = el `valor` del botón pulsado, o null si se cerró con Esc / clic fuera.
 *
 * Un solo diálogo a la vez: abrir otro cierra el anterior (resuelve null), como hacía Swal.
 */

export type TonoDialogo = 'warning' | 'danger' | 'error' | 'success' | 'info' | 'question' | 'loading';

export interface BotonDialogo {
    texto: string;
    valor: string;
    variante?: 'primario' | 'peligro' | 'secundario';
}

export interface OpcionesDialogo {
    titulo: string;
    texto?: string;
    /** HTML de confianza (armado por el código, no por el usuario). Gana sobre `texto`. */
    html?: string | null;
    tono?: TonoDialogo;
    botones?: BotonDialogo[];
    /** Esc y clic fuera cierran (resuelve null). Default true. */
    cerrable?: boolean;
}

const ICONOS: Record<TonoDialogo, string> = {
    warning: 'fa-triangle-exclamation',
    danger: 'fa-triangle-exclamation',
    error: 'fa-circle-xmark',
    success: 'fa-circle-check',
    info: 'fa-circle-info',
    question: 'fa-circle-question',
    loading: 'fa-spinner fa-spin',
};

let actual: HTMLDialogElement | null = null;

function el<K extends keyof HTMLElementTagNameMap>(tag: K, clase: string): HTMLElementTagNameMap[K] {
    const nodo = document.createElement(tag);
    nodo.className = clase;
    return nodo;
}

export function dialogo({ titulo, texto = '', html = null, tono = 'info', botones = [], cerrable = true }: OpcionesDialogo): Promise<string | null> {
    cerrarDialogo();

    const dialog = el('dialog', 'ui-dialogo');
    const cuerpo = el('div', 'ui-dialogo__cuerpo');

    const icono = el('div', `ui-dialogo__icono ui-dialogo__icono--${tono}`);
    icono.setAttribute('aria-hidden', 'true');
    icono.appendChild(el('i', `fa-solid ${ICONOS[tono]}`));

    const textos = el('div', '');
    const h2 = el('h2', 'ui-dialogo__titulo');
    h2.id = `ui-dialogo-${Date.now()}`;
    h2.textContent = titulo;
    dialog.setAttribute('aria-labelledby', h2.id);
    textos.appendChild(h2);
    if (html || texto) {
        const p = el('div', 'ui-dialogo__texto');
        if (html) p.innerHTML = html;
        else p.textContent = texto;
        textos.appendChild(p);
    }

    cuerpo.appendChild(icono);
    cuerpo.appendChild(textos);

    if (botones.length) {
        const fila = el('div', 'ui-dialogo__botones');
        for (const b of botones) {
            const boton = el('button', `ui-dialogo__boton ui-dialogo__boton--${b.variante ?? 'primario'}`);
            boton.type = 'button';
            boton.textContent = b.texto;
            boton.addEventListener('click', () => dialog.close(b.valor));
            fila.appendChild(boton);
        }
        cuerpo.appendChild(fila);
    }

    dialog.appendChild(cuerpo);
    if (tono === 'loading') dialog.setAttribute('aria-busy', 'true');

    // Esc dispara `cancel`: se anula si no es cerrable; si lo es, sigue al close con returnValue ''.
    dialog.addEventListener('cancel', (e) => {
        if (!cerrable) e.preventDefault();
    });
    // El clic en el ::backdrop llega con target = el propio <dialog>.
    dialog.addEventListener('click', (e) => {
        if (cerrable && e.target === dialog) dialog.close('');
    });

    const resultado = new Promise<string | null>((resolve) => {
        dialog.addEventListener('close', () => {
            if (actual === dialog) actual = null;
            dialog.remove();
            resolve(dialog.returnValue || null);
        });
    });

    document.body.appendChild(dialog);
    actual = dialog;
    dialog.showModal();

    return resultado;
}

/** Cierra el diálogo abierto (si hay); su promesa resuelve null. */
export function cerrarDialogo(): void {
    actual?.close('');
}

export function hayDialogoAbierto(): boolean {
    return actual !== null;
}
