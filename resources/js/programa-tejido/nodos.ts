/**
 * Armado de DOM sin innerHTML para los módulos del bundle de la grilla.
 *
 * Es la misma firma que el() / icono() de modulos/urdido/comun/pagina.ts; no se importa de ahí
 * porque ese archivo traía utils/http.ts → sweetalert2, que no se podía evaluar en el test del
 * bundle (tests/Js/programa-tejido-bundle.test.ts). sweetalert2 ya se retiró (2026-10): se puede importar.
 */
type Hijo = Node | string | null | undefined | false;

export function el<K extends keyof HTMLElementTagNameMap>(
    tag: K,
    opciones: { clase?: string; texto?: string | number | null; attrs?: Record<string, string> } = {},
    ...hijos: Hijo[]
): HTMLElementTagNameMap[K] {
    const nodo = document.createElement(tag);
    if (opciones.clase) nodo.className = opciones.clase;
    if (opciones.texto !== undefined && opciones.texto !== null) nodo.textContent = String(opciones.texto);
    for (const [k, v] of Object.entries(opciones.attrs ?? {})) nodo.setAttribute(k, v);
    for (const h of hijos) if (h) nodo.append(h);
    return nodo;
}

/** Ícono Font Awesome decorativo. */
export function icono(clases: string): HTMLElement {
    return el('i', { clase: clases, attrs: { 'aria-hidden': 'true' } });
}

/** Espera de carga: círculo girando + texto. */
export function spinner(claseCirculo: string, texto: string, claseTexto = ''): HTMLElement {
    return el('div', { clase: 'flex items-center justify-center gap-2' },
        el('div', { clase: claseCirculo }),
        el('span', { clase: claseTexto, texto }));
}
