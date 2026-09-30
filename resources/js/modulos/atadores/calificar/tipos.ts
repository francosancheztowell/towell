/** Config de la página (data-pagina de #calificar-atado, armada en calificar-atadores/index.blade.php). */
import type { ConfigProcesoKm } from '../comun/proceso-km.ts';

export interface ConfigCalificar {
    rutas: {
        guardar: string;
        programa: string;
        devolucionGuardar: string;
        devolucionEliminar: string;
        devolucionJulios: string;
        devolucionUbicaciones: string;
    };
    usuario: { cve: string; nombre: string } | null;
    esKm: boolean;
    atado: {
        noJulio: string;
        noOrden: string;
        refId: number | null;
        telar: string | null;
        tipo: string | null;
        soloLectura: boolean;
    } | null;
    devolucion: { id: number; no_julio: string | null; ubicacion: string | null; bloqueada_por_ax: boolean } | null;
    km: ConfigProcesoKm | null;
}
