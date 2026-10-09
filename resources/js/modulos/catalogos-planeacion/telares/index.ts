/**
 * Catálogo de Telares (catalagos/catalagoTelares): telares de URDCatalogoMaquinas
 * (Jacquard, Itema, Smith, Karl Mayer). Antes: public/js/catalogs/TelaresCatalog.js.
 */
import { onReady } from '../../../utils/dom.ts';
import { arrancarCatalogo } from '../comun/catalogo.ts';
import { resumenTelar } from './logica.ts';

onReady(() => {
    arrancarCatalogo({ resumen: resumenTelar });
});
