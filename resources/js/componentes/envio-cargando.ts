/**
 * Botón ocupado al guardar: spinner + "Guardando…" + sin doble envío.
 *
 * Formularios clásicos (POST que recarga la página): se activa con `data-envio-cargando` en el
 * <form>; el texto se cambia con `data-texto-cargando` en el botón. El botón Cancelar con
 * formmethod="dialog" no cuenta. El spinner es CSS (`[data-ocupado]::before`, app.css), así que
 * también lo usan los diálogos de notify.form (utils/dialogo.ts) sin tocar su HTML.
 */

const TEXTO_POR_DEFECTO = 'Guardando…';
const originales = new WeakMap<HTMLElement, Node[]>();

/** Pone el botón en estado ocupado. `texto: null` deja el texto como está (solo spinner). */
export function marcarOcupado(boton: HTMLElement, texto: string | null = TEXTO_POR_DEFECTO): void {
    if (boton.hasAttribute('data-ocupado')) return;
    boton.setAttribute('data-ocupado', '');
    boton.setAttribute('aria-busy', 'true');
    if (texto !== null) {
        originales.set(boton, [...boton.childNodes]);
        boton.textContent = texto;
    }
}

export function liberar(boton: HTMLElement): void {
    if (!boton.hasAttribute('data-ocupado')) return;
    boton.removeAttribute('data-ocupado');
    boton.removeAttribute('aria-busy');
    const original = originales.get(boton);
    if (original !== undefined) {
        boton.replaceChildren(...original);
        originales.delete(boton);
    }
    if ('disabled' in boton) (boton as HTMLButtonElement).disabled = false;
}

export type DecisionEnvio = 'ignorar' | 'bloquear' | 'cargar';

/** Qué hacer con un submit. Lógica pura para poder probarla sin DOM. */
export function decidirEnvio(e: {
    conCarga: boolean;
    metodo: string;
    formmethodBoton: string | null;
    yaEnviando: boolean;
    prevenido: boolean;
}): DecisionEnvio {
    if (!e.conCarga) return 'ignorar';
    if (e.metodo === 'dialog' || e.formmethodBoton === 'dialog') return 'ignorar';
    if (e.yaEnviando) return 'bloquear';
    // Otro listener ya lo anuló (validación propia, envío por http…): no es un envío real.
    if (e.prevenido) return 'ignorar';
    return 'cargar';
}

function botonDeEnvio(form: HTMLFormElement, submitter: HTMLElement | null): HTMLElement | null {
    if (submitter) return submitter;
    return form.querySelector<HTMLElement>('button[type="submit"]:not([formmethod="dialog"]), button:not([type])');
}

function liberarTodos(): void {
    document.querySelectorAll<HTMLFormElement>('form[data-enviando]').forEach((f) => f.removeAttribute('data-enviando'));
    document.querySelectorAll<HTMLElement>('[data-ocupado]').forEach(liberar);
}

export function escucharEnviosConCarga(): void {
    // En window y en burbuja: corre después de los listeners de la vista, que pueden anular el envío.
    window.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        const submitter = e.submitter instanceof HTMLElement ? e.submitter : null;

        const decision = decidirEnvio({
            conCarga: form.hasAttribute('data-envio-cargando'),
            metodo: form.method,
            formmethodBoton: submitter?.getAttribute('formmethod') ?? null,
            yaEnviando: form.hasAttribute('data-enviando'),
            prevenido: e.defaultPrevented,
        });

        if (decision === 'bloquear') {
            e.preventDefault();
            return;
        }
        if (decision !== 'cargar') return;

        form.setAttribute('data-enviando', '');
        const boton = botonDeEnvio(form, submitter);
        if (!boton) return;
        marcarOcupado(boton, boton.dataset.textoCargando ?? TEXTO_POR_DEFECTO);
        // Se deshabilita después del submit: deshabilitado antes, el navegador lo saca del envío.
        if (boton instanceof HTMLButtonElement) setTimeout(() => (boton.disabled = true));
    });

    // Volver con "atrás" restaura la página del bfcache tal cual: botones otra vez usables.
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) liberarTodos();
    });
}
