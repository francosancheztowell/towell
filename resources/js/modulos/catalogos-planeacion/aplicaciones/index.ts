/** Catálogo de Aplicaciones (catalagos/aplicaciones). Antes: public/js/catalogs/AplicacionesCatalog.js. */
import { onReady } from '../../../utils/dom.ts';
import { arrancarCatalogo } from '../comun/catalogo.ts';
import { procesarAplicacion, resumenAplicacion } from './logica.ts';

onReady(() => {
    arrancarCatalogo({ procesar: procesarAplicacion, resumen: resumenAplicacion });
});
