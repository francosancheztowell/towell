/** Mensaje del evento `programa-tejido-error` que despacha ProgramaTejidoBoard (Livewire). */
export const MENSAJE_POR_DEFECTO = 'No se pudieron cargar los registros.';

export function mensajeDeError(detalle: unknown): string {
    const d = Array.isArray(detalle) ? detalle[0] : detalle;
    const mensaje = d && typeof d === 'object' ? (d as { mensaje?: unknown }).mensaje : undefined;

    return typeof mensaje === 'string' && mensaje.trim() !== '' ? mensaje : MENSAJE_POR_DEFECTO;
}
