/**
 * Panel de Control KM (19-01): 4 gráficas con Chart.js dentro del bundle
 * (antes: @vite('resources/js/charts.js') + script inline con @json).
 * Vista: resources/views/modulos/urdido/reportes-panel-control.blade.php.
 */
import Chart from 'chart.js/auto';
import { onReady } from '../../../utils/dom.ts';
import { leerDatos } from '../comun/pagina.ts';
import {
    configEficiencia,
    configEventos,
    configMenciones,
    configRpm,
    hayMenciones,
    type CategoriaPanel,
    type SemanaPanel,
} from './logica.ts';

interface ConfigPanel {
    semanas: SemanaPanel[];
    categorias: CategoriaPanel[];
}

function lienzo(id: string): HTMLCanvasElement | null {
    const el = document.getElementById(id);
    return el instanceof HTMLCanvasElement ? el : null;
}

function iniciar(): void {
    const raiz = document.querySelector<HTMLElement>('[data-panel-control]');
    const config = leerDatos<ConfigPanel>(raiz, 'panelControl');
    if (!config) return;

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.color = '#4B5563';

    const { semanas, categorias } = config;
    if (semanas.length) {
        const efic = lienzo('panelKmEficienciaChart');
        const rpm = lienzo('panelKmRpmChart');
        const eventos = lienzo('panelKmEventosChart');
        if (efic) new Chart(efic, configEficiencia(semanas));
        if (rpm) new Chart(rpm, configRpm(semanas));
        if (eventos) new Chart(eventos, configEventos(semanas));
    }

    const menciones = lienzo('panelKmMencionesChart');
    if (menciones && categorias.length && hayMenciones(categorias)) {
        new Chart(menciones, configMenciones(categorias));
    }
}

onReady(iniciar);
