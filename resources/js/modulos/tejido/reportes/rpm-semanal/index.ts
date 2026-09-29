/**
 * Reporte RPM semanal (19-02). Vista: resources/views/modulos/tejido/reportes/rpm-semanal.blade.php.
 * Solo el modal "Semana a consultar" (una fecha → lunes a domingo). La gráfica de antes no
 * tenía <canvas> en la vista (código muerto): no se trae Chart.js.
 */
import { onReady } from '../../../../utils/dom.ts';
import { iniciarRango } from '../comun/rango.ts';

onReady(iniciarRango);
