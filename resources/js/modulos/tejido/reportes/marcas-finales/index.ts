/**
 * Reporte Marcas Finales (19-02). Vista: resources/views/modulos/tejido/reportes/reporte-marcas-finales.blade.php.
 * Solo el modal de rango de fechas.
 */
import { onReady } from '../../../../utils/dom.ts';
import { iniciarRango } from '../comun/rango.ts';

onReady(iniciarRango);
