/**
 * Scroll horizontal de la columna de nombres (Empresa / Tipo / Cliente): una barra al pie del
 * encabezado desplaza a la vez todos los nombres, que conservan el ancho de la columna
 * (column-resize.ts). Así se leen completos sin ensanchar la tabla.
 */
export const labelScrollBar = () => '<div class="pvoc-label-scroll" data-pvoc-label-scroll><div></div></div>';

/** Con los nombres desplazados se quita el "…": el navegador lo deja fijo y tapa el resto del texto. */
const markScrolled = (container: HTMLElement, offset: number) => container.classList.toggle('is-label-scrolled', offset > 0);

const labels = (container: HTMLElement) => [...container.querySelectorAll<HTMLElement>('tbody .pvoc-label-text')];

/** container = contenedor de una tabla de Compara. Devuelve la función que se llama tras cada pintado. */
export const bindLabelScroll = (container: HTMLElement) => {
    // Posición del scroll entre pintados (abrir un nodo re-pinta la tabla).
    let offset = 0;
    let observed: Element | null = null;

    const sync = () => {
        const bar = container.querySelector<HTMLElement>('[data-pvoc-label-scroll]');
        if (!bar) return;
        const spans = labels(container);
        const overflow = Math.max(0, ...spans.map((span) => span.scrollWidth - span.clientWidth));
        // Barra y nombres recorren la misma distancia: largo de la barra + lo que sobra del nombre más largo.
        (bar.firstElementChild as HTMLElement).style.width = `${bar.clientWidth + overflow}px`;
        bar.classList.toggle('is-idle', overflow === 0);
        bar.scrollLeft = Math.min(offset, overflow);
        markScrolled(container, bar.scrollLeft);
        spans.forEach((span) => { span.scrollLeft = bar.scrollLeft; });

        // El ancho de la columna cambia al arrastrarla, con el zoom o con la ventana.
        const header = bar.closest('th');
        if (header && header !== observed) {
            observer?.disconnect();
            observer?.observe(header);
            observed = header;
        }
    };
    const observer = typeof ResizeObserver === 'undefined' ? undefined : new ResizeObserver(() => sync());

    // El evento scroll no burbujea: se escucha en captura.
    container.addEventListener('scroll', (event) => {
        const bar = event.target as HTMLElement;
        if (!(bar instanceof HTMLElement) || !bar.matches('[data-pvoc-label-scroll]')) return;
        offset = bar.scrollLeft;
        markScrolled(container, offset);
        labels(container).forEach((span) => { span.scrollLeft = offset; });
    }, true);

    return sync;
};
