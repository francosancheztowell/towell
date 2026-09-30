/**
 * Utilería de Planeación: tarjetas que abren los modales "Finalizar Órdenes" y "Mover
 * Órdenes". Antes: dos <script> inline (~900 líneas) en planeacion/utileria/*.blade.php.
 * Las rutas llegan en data-pagina de #utileria.
 */
import { leerDatos } from '../../urdido/comun/pagina.ts';
import { delegate, onReady } from '../../../utils/dom.ts';
import { abrirModalFinalizar, iniciarFinalizar } from './finalizar.ts';
import { abrirModalMover, iniciarMover } from './mover.ts';
import type { ConfigUtileria } from './logica.ts';

onReady(() => {
    const raiz = document.getElementById('utileria');
    const cfg = leerDatos<ConfigUtileria>(raiz);
    if (!raiz || !cfg) return;

    iniciarFinalizar(cfg.finalizar);
    iniciarMover(cfg.mover);

    const acciones: Record<string, () => Promise<void>> = {
        'abrir-finalizar': abrirModalFinalizar,
        'abrir-mover': abrirModalMover,
    };
    delegate(raiz, 'click', '[data-accion]', (_e, el) => { void acciones[el.dataset.accion ?? '']?.(); });
});
