/**
 * Diálogo modal nativo (<dialog> + showModal) con el aspecto de Flux: reemplaza a SweetAlert2
 * en notify.confirm/alert/validation/loading. Estilos: bloque .ui-dialogo de resources/css/app.css.
 *
 *   const valor = await dialogo({ tono: 'danger', titulo: '¿Eliminar?', botones: [...] });
 *   // valor = el `valor` del botón pulsado, o null si se cerró con Esc / clic fuera.
 *
 * Un solo diálogo a la vez: abrir otro cierra el anterior (resuelve null), como hacía Swal.
 */
import { liberar, marcarOcupado } from '../componentes/envio-cargando.ts';

export type TonoDialogo = 'warning' | 'danger' | 'error' | 'success' | 'info' | 'question' | 'loading';

export interface BotonDialogo {
    texto: string;
    valor: string;
    variante?: 'primario' | 'peligro' | 'secundario';
    /** Este botón corre `validar` antes de cerrar (y es el que dispara Enter en un formulario). */
    valida?: boolean;
}

export interface ContextoValidar {
    /** Contenedor del contenido (para leer los campos). */
    raiz: HTMLElement;
    /** Muestra un error bajo el contenido; el diálogo sigue abierto. */
    error(mensaje: string): void;
}

export type AnchoDialogo = 'md' | 'lg' | 'xl' | '2xl' | '3xl';

export interface OpcionesDialogo {
    titulo: string;
    texto?: string;
    /** HTML de confianza (armado por el código, no por el usuario). Gana sobre `texto`. */
    html?: string | null;
    /** null = sin ícono (formularios). */
    tono?: TonoDialogo | null;
    botones?: BotonDialogo[];
    /** Esc y clic fuera cierran (resuelve null). Default true. */
    cerrable?: boolean;
    /** Formulario: contenido alineado a la izquierda, Enter envía con el botón `valida`. */
    formulario?: boolean;
    ancho?: AnchoDialogo;
    /** Corre al pulsar el botón `valida`: false, error() o una excepción lo dejan abierto. */
    validar?: ((ctx: ContextoValidar) => unknown) | undefined;
    /** Recibe lo que devolvió `validar` (si cerró por el botón `valida`). */
    alValidar?: (datos: unknown) => void;
    /** Ya en el DOM y abierto: para enganchar listeners o llenar campos. */
    alAbrir?: ((raiz: HTMLElement) => void) | undefined;
}

// Heroicons outline (los mismos que flux:icon, de vendor/livewire/flux). SVG inline: no depende
// de que cargue la fuente de Font Awesome.
const TRIANGULO = 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z';
const ICONOS: Record<TonoDialogo, string> = {
    warning: TRIANGULO,
    danger: TRIANGULO,
    error: 'm9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    success: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    info: 'm11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z',
    question: 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z',
    loading: 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99',
};

const SVG_NS = 'http://www.w3.org/2000/svg';

function icono(tono: TonoDialogo): SVGSVGElement {
    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.5');
    if (tono === 'loading') svg.setAttribute('class', 'ui-dialogo__girando');
    const path = document.createElementNS(SVG_NS, 'path');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    path.setAttribute('d', ICONOS[tono]);
    svg.appendChild(path);
    return svg;
}

let actual: HTMLDialogElement | null = null;

function el<K extends keyof HTMLElementTagNameMap>(tag: K, clase: string): HTMLElementTagNameMap[K] {
    const nodo = document.createElement(tag);
    nodo.className = clase;
    return nodo;
}

function pintar(contenido: HTMLElement, html: string | null, texto: string): void {
    // Único sink de HTML de los diálogos (sustituye los `html:` de Swal); quien llama escapa los datos.
    if (html) contenido.innerHTML = html;
    else contenido.textContent = texto;
    contenido.hidden = !html && !texto;
}

export function dialogo({
    titulo,
    texto = '',
    html = null,
    tono = 'info',
    botones = [],
    cerrable = true,
    formulario = false,
    ancho = 'md',
    validar,
    alValidar,
    alAbrir,
}: OpcionesDialogo): Promise<string | null> {
    cerrarDialogo();

    const clases = ['ui-dialogo', `ui-dialogo--${ancho}`];
    if (formulario) clases.push('ui-dialogo--formulario');
    const dialog = el('dialog', clases.join(' '));
    // <form>: Enter en un campo envía con el botón `valida` (submit), como en Swal.
    const cuerpo = el(formulario ? 'form' : 'div', 'ui-dialogo__cuerpo');

    if (tono) {
        const circulo = el('div', `ui-dialogo__icono ui-dialogo__icono--${tono}`);
        circulo.setAttribute('aria-hidden', 'true');
        circulo.appendChild(icono(tono));
        cuerpo.appendChild(circulo);
    }

    const textos = el('div', 'ui-dialogo__textos');
    const h2 = el('h2', 'ui-dialogo__titulo');
    h2.id = `ui-dialogo-${Date.now()}`;
    h2.textContent = titulo;
    dialog.setAttribute('aria-labelledby', h2.id);
    textos.appendChild(h2);
    const contenido = el('div', 'ui-dialogo__texto');
    pintar(contenido, html, texto);
    textos.appendChild(contenido);
    cuerpo.appendChild(textos);

    const error = el('p', 'ui-dialogo__error');
    error.setAttribute('role', 'alert');
    error.hidden = true;
    cuerpo.appendChild(error);

    const mostrarError = (mensaje: string): void => {
        error.textContent = mensaje;
        error.hidden = !mensaje;
    };

    const fila = el('div', 'ui-dialogo__botones');
    const elementos = new Map<BotonDialogo, HTMLButtonElement>();
    const enviar = async (b: BotonDialogo): Promise<void> => {
        if (!b.valida || !validar) {
            dialog.close(b.valor);
            return;
        }
        mostrarError('');
        let fallo = false;
        const ctx: ContextoValidar = {
            raiz: contenido,
            error: (m) => {
                fallo = true;
                mostrarError(m);
            },
        };
        dialog.setAttribute('aria-busy', 'true');
        fila.querySelectorAll('button').forEach((x) => (x.disabled = true));
        // Spinner en el botón que se presionó (CSS [data-ocupado]::before); el texto no cambia.
        const presionado = elementos.get(b);
        if (presionado) marcarOcupado(presionado, null);
        try {
            const datos = await validar(ctx);
            if (datos === false || fallo) return;
            alValidar?.(datos);
            dialog.close(b.valor);
        } catch (e) {
            mostrarError(e instanceof Error ? e.message : String(e));
        } finally {
            dialog.removeAttribute('aria-busy');
            if (presionado) liberar(presionado);
            fila.querySelectorAll('button').forEach((x) => (x.disabled = false));
        }
    };

    for (const b of botones) {
        const boton = el('button', `ui-dialogo__boton ui-dialogo__boton--${b.variante ?? 'primario'}`);
        boton.type = formulario && b.valida ? 'submit' : 'button';
        boton.textContent = b.texto;
        elementos.set(b, boton);
        if (boton.type === 'button') boton.addEventListener('click', () => void enviar(b));
        fila.appendChild(boton);
    }
    if (botones.length) cuerpo.appendChild(fila);
    if (formulario) {
        const principal = botones.find((b) => b.valida);
        cuerpo.addEventListener('submit', (e) => {
            e.preventDefault();
            if (principal && !dialog.hasAttribute('aria-busy')) void enviar(principal);
        });
    }

    dialog.appendChild(cuerpo);
    if (tono === 'loading') dialog.setAttribute('aria-busy', 'true');

    // Esc dispara `cancel`: se anula si no es cerrable; si lo es, sigue al close con returnValue ''.
    // Mientras `validar` corre (aria-busy) tampoco se cierra: la petición no debe quedar huérfana.
    const ocupado = (): boolean => dialog.hasAttribute('aria-busy');
    dialog.addEventListener('cancel', (e) => {
        if (!cerrable || ocupado()) e.preventDefault();
    });
    // El clic en el ::backdrop llega con target = el propio <dialog>.
    dialog.addEventListener('click', (e) => {
        if (cerrable && !ocupado() && e.target === dialog) dialog.close('');
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
    alAbrir?.(contenido);

    return resultado;
}

/** Cambia título/texto del diálogo abierto (p. ej. el progreso de un loading). */
export function actualizarDialogo({ titulo, texto, html }: { titulo?: string | undefined; texto?: string | undefined; html?: string | undefined }): void {
    if (!actual) return;
    const h2 = actual.querySelector('.ui-dialogo__titulo');
    const contenido = actual.querySelector<HTMLElement>('.ui-dialogo__texto');
    if (h2 && titulo !== undefined) h2.textContent = titulo;
    if (contenido && (html !== undefined || texto !== undefined)) pintar(contenido, html ?? null, texto ?? '');
}

/** Cierra el diálogo abierto (si hay); su promesa resuelve null. */
export function cerrarDialogo(): void {
    actual?.close('');
}

export function hayDialogoAbierto(): boolean {
    return actual !== null;
}
