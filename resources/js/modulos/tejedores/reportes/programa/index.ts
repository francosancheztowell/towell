/**
 * Reportes Programa de Tejedores y de Desarrolladores (mismo esqueleto). Vistas:
 * resources/views/modulos/{tejedores,desarrolladores}/reportes/programa.blade.php.
 * Solo el modal de fechas (partial rango-fechas de Tejido) y el botón atrás.
 */
import { iniciarReporte } from '../../../atadores/reportes/comun.ts';

iniciarReporte(document.getElementById('reporte-programa')?.dataset.indice);
