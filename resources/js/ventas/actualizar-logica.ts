/**
 * Respuesta de POST /ventas/datos/actualizar (VentasDatosController::actualizar) y qué hace la página
 * con ella. Sin DOM ni red para poder probarla con node:test.
 */
export interface Actualizacion {
    estado: 'al_dia' | 'cargando' | 'publicada';
    version: string;
}

export type RespuestaActualizar =
    | { accion: 'recargar' }
    | { accion: 'avisar'; tipo: 'info' | 'warning'; texto: string };

export const AVISO_AL_DIA = 'Ya tienes la información más reciente de Ventas.';

export const AVISO_CARGANDO = 'Se están cargando datos de Ventas en este momento. Intenta de nuevo en un minuto.';

/** Cualquier versión distinta a la de la página (la publicó este botón u otro) se ve recargando. */
export const respuestaActualizar = (resultado: Actualizacion, versionPagina: string): RespuestaActualizar => {
    if (resultado.version !== versionPagina) return { accion: 'recargar' };

    return resultado.estado === 'cargando'
        ? { accion: 'avisar', tipo: 'warning', texto: AVISO_CARGANDO }
        : { accion: 'avisar', tipo: 'info', texto: AVISO_AL_DIA };
};
