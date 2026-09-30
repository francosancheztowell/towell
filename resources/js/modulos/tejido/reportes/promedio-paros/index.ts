/**
 * Promedio Paros y Eficiencia (19-02). Vista: resources/views/modulos/tejido/reportes/promedio-paros-eficiencia.blade.php.
 * Solo el modal de rango de fechas.
 */
import { onReady } from '../../../../utils/dom.ts';
import { iniciarRango } from '../comun/rango.ts';

onReady(iniciarRango);
