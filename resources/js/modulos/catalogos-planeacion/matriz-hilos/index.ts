/** Matriz de Hilos (catalagos/matriz-hilos). Antes: public/js/catalogs/MatrizHilosCatalog.js. */
import { onReady } from '../../../utils/dom.ts';
import { arrancarCatalogo } from '../comun/catalogo.ts';
import { procesarHilo } from './logica.ts';

onReady(() => {
    arrancarCatalogo({ procesar: procesarHilo, resumen: (v) => `Hilo: ${String(v.Hilo ?? '')}` });
});
