import { HttpError } from '../../../../utils/http.ts';

/** Folio que el servidor manda en un 400 de generar-folio / store (`folio_existente`), o null. */
export function folioExistente(err: unknown): string | null {
    if (!(err instanceof HttpError) || err.status !== 400) return null;
    const cuerpo = (err.data ?? {}) as { folio_existente?: string };
    return cuerpo.folio_existente ?? null;
}
