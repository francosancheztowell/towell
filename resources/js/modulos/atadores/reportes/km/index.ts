/** Reporte Atadores KM: modal de fechas y gráfica de efectividad (Chart.js en este bundle, ya no charts.js). */
import Chart from 'chart.js/auto';
import { iniciarReporte } from '../comun.ts';
import { configuracionGrafica, type FilaKm } from './grafica.ts';

const raiz = document.getElementById('reporte-atadores');
iniciarReporte(raiz?.dataset.indice);

const canvas = document.getElementById('chartKm');
if (canvas instanceof HTMLCanvasElement && raiz?.dataset.filas) {
    try {
        new Chart(canvas, configuracionGrafica(JSON.parse(raiz.dataset.filas) as FilaKm[]));
    } catch {
        // Datos corruptos: el reporte en tabla sigue visible.
    }
}
