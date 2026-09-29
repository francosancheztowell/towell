/**
 * Descargas de Cortes de Eficiencia (PDF/Excel del servidor) y PDF → imagen con pdf.js.
 */
import { HttpError, http } from '../../../../utils/http.ts';
import { librerias } from '../../../../utils/librerias.ts';

export function descargarBlob(blob: Blob, nombre: string): void {
    const url = URL.createObjectURL(blob);
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = nombre;
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();
    URL.revokeObjectURL(url);
}

/**
 * Pide un archivo (blob). Si el servidor contesta un error JSON ({success, message, error}),
 * el HttpError lleva ese JSON en `data` para que mensajeError() lo lea.
 */
export async function pedirArchivo(metodo: 'get' | 'post', url: string, datos?: Record<string, string>): Promise<Blob> {
    try {
        return metodo === 'get'
            ? await http.get<Blob>(url, { responseType: 'blob' })
            : await http.post<Blob>(url, datos, { responseType: 'blob' });
    } catch (err) {
        if (err instanceof HttpError && err.data instanceof Blob) {
            try {
                err.data = JSON.parse(await err.data.text()) as unknown;
            } catch {
                err.data = null;
            }
        }
        throw err;
    }
}

export interface OpcionesImagen {
    escala: number;
    calidad: number;
    /** true: todas las páginas una debajo de otra; false: solo la primera. */
    todasLasPaginas: boolean;
}

/** Renderiza el PDF con pdf.js y lo convierte en un JPEG. */
export async function pdfAJpeg(pdf: Blob, opciones: OpcionesImagen): Promise<Blob> {
    const pdfjsLib = await librerias.pdfjs();
    const documento = await pdfjsLib.getDocument({ data: new Uint8Array(await pdf.arrayBuffer()) }).promise;
    const total = opciones.todasLasPaginas ? documento.numPages : 1;

    const lienzos: HTMLCanvasElement[] = [];
    for (let i = 1; i <= total; i++) {
        const pagina = await documento.getPage(i);
        const viewport = pagina.getViewport({ scale: opciones.escala });
        const lienzo = document.createElement('canvas');
        lienzo.width = Math.ceil(viewport.width);
        lienzo.height = Math.ceil(viewport.height);
        const contexto = lienzo.getContext('2d', { alpha: false });
        if (!contexto) throw new Error('No se pudo preparar la imagen.');
        await pagina.render({ canvas: null, canvasContext: contexto, viewport, background: '#ffffff' }).promise;
        lienzos.push(lienzo);
    }

    let final = lienzos[0];
    if (!final) throw new Error('El PDF no tiene páginas.');
    if (lienzos.length > 1) {
        final = document.createElement('canvas');
        final.width = Math.max(...lienzos.map((c) => c.width));
        final.height = lienzos.reduce((suma, c) => suma + c.height, 0);
        const ctx = final.getContext('2d');
        if (!ctx) throw new Error('No se pudo preparar la imagen.');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, final.width, final.height);
        let y = 0;
        for (const c of lienzos) {
            ctx.drawImage(c, 0, y);
            y += c.height;
        }
    }

    const lienzoFinal = final;
    return new Promise<Blob>((resolve, reject) => {
        lienzoFinal.toBlob((b) => (b ? resolve(b) : reject(new Error('No se pudo convertir el PDF a imagen.'))), 'image/jpeg', opciones.calidad);
    });
}

/** Pone un botón en "trabajando" (spinner + texto) y devuelve cómo restaurarlo. */
export function ocupado(boton: HTMLButtonElement | null, texto = ''): () => void {
    if (!boton) return () => {};
    const antes = Array.from(boton.childNodes);
    const icono = document.createElement('i');
    icono.className = texto ? 'fa fa-spinner fa-spin mr-2' : 'fa fa-spinner fa-spin';
    icono.setAttribute('aria-hidden', 'true');
    boton.replaceChildren(icono, ...(texto ? [document.createTextNode(` ${texto}`)] : []));
    boton.disabled = true;
    boton.setAttribute('aria-busy', 'true');
    return () => {
        boton.replaceChildren(...antes);
        boton.disabled = false;
        boton.removeAttribute('aria-busy');
    };
}
