/**
 * Librerías pesadas que solo usan un par de pantallas: se descargan la primera vez
 * que se piden (import() dinámico, chunk aparte) y no entran al JS del layout.
 * Antes venían de cdnjs con <script>.
 *
 *   const html2canvas = await window.librerias.html2canvas();
 *   const pdfjsLib = await window.librerias.pdfjs();
 */
import type Html2Canvas from 'html2canvas';

type Html2CanvasFn = typeof Html2Canvas;
type PdfJs = typeof import('pdfjs-dist/legacy/build/pdf.mjs');

/**
 * Memoiza una carga asíncrona. Si falla (red caída), la siguiente llamada
 * reintenta en vez de quedarse con la promesa rechazada.
 */
export function unaVez<T>(cargar: () => Promise<T>): () => Promise<T> {
    let pendiente: Promise<T> | null = null;

    return () => {
        pendiente ??= cargar().catch((error: unknown) => {
            pendiente = null;
            throw error;
        });
        return pendiente;
    };
}

const html2canvas = unaVez<Html2CanvasFn>(async () => (await import('html2canvas')).default);

// Build `legacy` de pdf.js: la moderna exige navegadores recientes y en planta hay tablets viejas.
const pdfjs = unaVez<PdfJs>(async () => {
    const [pdfjsLib, worker] = await Promise.all([
        import('pdfjs-dist/legacy/build/pdf.mjs'),
        // ?worker&url: Vite lo empaqueta como .js (un .mjs sin MIME en el servidor rompería el worker).
        import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker&url'),
    ]);
    pdfjsLib.GlobalWorkerOptions.workerSrc = worker.default;
    return pdfjsLib;
});

export const librerias = { html2canvas, pdfjs };

export type Librerias = typeof librerias;

export default librerias;
