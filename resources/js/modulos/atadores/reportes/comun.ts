/**
 * Común de los reportes de Atadores (19-03): el modal de fechas es el de Tejido (19-02,
 * modulos/tejido/reportes/partials/rango-fechas.blade.php + comun/rango.ts), igual de genérico;
 * HANDOFF: subirlo a un componente compartido.
 */
import { iniciarRango } from '../../tejido/reportes/comun/rango.ts';

declare global {
    interface Window {
        volverAlIndice?: () => void;
    }
}

export function iniciarReporte(indice: string | undefined): void {
    iniciarRango();
    // PUENTE 19-03: resources/js/app-core.js llama window.volverAlIndice() en el botón atrás (#btn-back).
    if (indice) window.volverAlIndice = () => window.location.assign(indice);
}
