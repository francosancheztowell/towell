/**
 * Resumen semanal de Urdido/Engomado (19-01).
 * Vista: resources/views/modulos/urdido/comun/reporte-resumen.blade.php.
 * Modal de rango propio (form GET nativo) y dos gráficas; Chart.js viaja en este bundle
 * (antes: @vite('resources/js/charts.js') + window.Chart).
 */
import Chart from 'chart.js/auto';
import { delegate, onReady } from '../../../../utils/dom.ts';
import { leerDatos } from '../pagina.ts';
import { configEficiencia, configPromedios, type SemanaResumen } from './logica.ts';

interface ConfigResumen {
    datos: SemanaResumen[];
}

function iniciar(): void {
    const raiz = document.querySelector<HTMLElement>('[data-reporte-resumen]');
    const config = leerDatos<ConfigResumen>(raiz, 'reporteResumen');
    const modal = document.querySelector<HTMLElement>('[data-modal-resumen]');

    if (modal) {
        const abrir = (): void => modal.classList.remove('hidden');
        const cerrar = (): void => modal.classList.add('hidden');
        // El botón "Consultar" vive en el navbar (fuera de la raíz): delegación en document.
        delegate(document, 'click', '[data-accion-resumen]', (_ev, el) => {
            if (el.dataset.accionResumen === 'abrir') abrir();
            else cerrar();
        });
        // Clic en el fondo cierra, como antes.
        modal.addEventListener('click', (ev) => {
            if (ev.target === modal) cerrar();
        });
    }

    const datos = config?.datos ?? [];
    if (!datos.length) return;

    const promedios = document.querySelector<HTMLCanvasElement>('canvas[data-grafica-resumen="promedios"]');
    const eficiencia = document.querySelector<HTMLCanvasElement>('canvas[data-grafica-resumen="eficiencia"]');
    if (promedios) new Chart(promedios, configPromedios(datos));
    if (eficiencia) new Chart(eficiencia, configEficiencia(datos));
}

onReady(iniciar);
