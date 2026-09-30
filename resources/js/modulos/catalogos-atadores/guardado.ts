/**
 * Lo que el servidor devolvió al guardar (19-03, HANDOFF 16 C3).
 *
 * catalog-base.ts relee la fila guardada por su llave tomada del formulario. Con la llave `Id`
 * de Comentarios eso no sirve (el Id no está en el formulario: lo asigna la base), así que el alta
 * y la edición responden `data` con la fila y aquí se guarda para pintarla con su Id real.
 */
import type { Dependencias, Registro } from '../../catalogos/catalog-base.ts';

type HttpCatalogo = Dependencias['http'];

export interface CapturaGuardado {
    http: HttpCatalogo;
    /** La última fila devuelta por un POST/PUT (y la olvida), o null. */
    tomar(): Registro | null;
}

function filaDe(respuesta: unknown): Registro | null {
    const data = (respuesta as { data?: unknown } | null)?.data;

    return data !== null && typeof data === 'object' && !Array.isArray(data) ? (data as Registro) : null;
}

export function capturarGuardado(http: HttpCatalogo): CapturaGuardado {
    let ultima: Registro | null = null;
    const recordar = async (peticion: Promise<unknown>): Promise<unknown> => {
        ultima = null;
        const respuesta = await peticion;
        ultima = filaDe(respuesta);

        return respuesta;
    };

    return {
        http: {
            get: (url) => http.get(url),
            delete: (url) => http.delete(url),
            post: (url, data) => recordar(http.post(url, data)),
            put: (url, data) => recordar(http.put(url, data)),
        },
        tomar() {
            const fila = ultima;
            ultima = null;

            return fila;
        },
    };
}
