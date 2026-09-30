/**
 * Reporte Inv Telas (19-02). Vista: resources/views/modulos/tejido/reportes/inv-telas.blade.php.
 * Solo el modal de rango (máximo 5 días) y el botón atrás del navbar.
 */
import { onReady } from '../../../../utils/dom.ts';
import { iniciarRango } from '../comun/rango.ts';

const RAIZ = '[data-reporte-inv-telas]';

onReady(() => {
    iniciarRango();
    const indice = document.querySelector<HTMLElement>(RAIZ)?.dataset.rutaIndice;
    // PUENTE 19-02: resources/js/app-core.js llama window.volverAlIndice() en el botón atrás (#btn-back).
    if (indice) (window as Window & { volverAlIndice?: () => void }).volverAlIndice = () => window.location.assign(indice);
});
