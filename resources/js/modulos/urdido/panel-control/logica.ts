/**
 * Panel de Control KM (19-01): configuración de las 4 gráficas, pura (sin DOM ni Chart).
 * La prueba tests/Js/urdeng-reporte-panel-control.test.mjs.
 */
import type { ChartConfiguration, ChartOptions, ChartType } from 'chart.js';

/** Una fila de semanas_detalle (PanelControlKmService). */
export interface SemanaPanel {
    semana?: number | string;
    efic?: number | string | null;
    est?: number | string | null;
    rpm?: number | string | null;
    rpm_est?: number | string | null;
    eventos?: number | string | null;
}

/** Una fila de categorias (menciones por tipo de observación). */
export interface CategoriaPanel {
    categoria?: string;
    menciones?: number | string | null;
}

export const NAVY = '#1F3864';
export const AZUL = '#2E75B6';
export const AMBAR = '#BF8F00';
export const GRISNAVY = '#A6B8D4';

/** null corta la línea (equivale al NA() del Excel). */
export function valor(v: number | string | null | undefined): number | null {
    return v === null || v === undefined ? null : Number(v);
}

export function etiquetas(semanas: SemanaPanel[]): string[] {
    return semanas.map((s) => `S${s.semana ?? ''}`);
}

/** Hay gráfica de menciones solo si alguna categoría tiene menciones. */
export function hayMenciones(categorias: CategoriaPanel[]): boolean {
    return categorias.some((c) => Number(c.menciones ?? 0) > 0);
}

/** Opciones comunes de las gráficas del panel (mismo estilo que el Excel). */
export function opcionesBase<T extends 'line' | 'bar'>(titulo: string): ChartOptions<T> {
    const opciones: ChartOptions<'line' | 'bar'> = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, font: { size: 10 } } },
            title: { display: true, text: titulo, color: NAVY, font: { size: 12, weight: 'bold' } },
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 9 } } },
            y: { beginAtZero: true, grid: { color: '#EEF1F7' }, ticks: { font: { size: 9 } } },
        },
    };
    return opciones as ChartOptions<T>;
}

type Config<T extends ChartType> = ChartConfiguration<T, (number | null)[], string>;

export function configEficiencia(semanas: SemanaPanel[]): Config<'line'> {
    return {
        type: 'line',
        data: {
            labels: etiquetas(semanas),
            datasets: [
                {
                    label: 'Eficiencia real',
                    data: semanas.map((s) => valor(s.efic)),
                    borderColor: NAVY,
                    backgroundColor: NAVY,
                    borderWidth: 2,
                    pointRadius: 2,
                    spanGaps: false,
                    tension: 0.25,
                },
                {
                    label: 'Estándar',
                    data: semanas.map((s) => valor(s.est)),
                    borderColor: AZUL,
                    backgroundColor: AZUL,
                    borderWidth: 2,
                    borderDash: [6, 4],
                    pointRadius: 0,
                    spanGaps: false,
                    tension: 0.25,
                },
            ],
        },
        options: opcionesBase<'line'>('Eficiencia real vs estándar por semana (%)'),
    };
}

export function configRpm(semanas: SemanaPanel[]): Config<'bar'> {
    return {
        type: 'bar',
        data: {
            labels: etiquetas(semanas),
            datasets: [
                { label: 'RPM real', data: semanas.map((s) => valor(s.rpm)), backgroundColor: NAVY, borderRadius: 2 },
                {
                    label: 'RPM estándar',
                    data: semanas.map((s) => valor(s.rpm_est)),
                    backgroundColor: GRISNAVY,
                    borderRadius: 2,
                },
            ],
        },
        options: opcionesBase<'bar'>('RPM real vs estándar por semana'),
    };
}

export function configEventos(semanas: SemanaPanel[]): Config<'bar'> {
    const opciones = opcionesBase<'bar'>('Eventos registrados por semana');
    const y = opciones.scales?.['y'];
    if (y?.ticks) Object.assign(y.ticks, { precision: 0 });
    return {
        type: 'bar',
        data: {
            labels: etiquetas(semanas),
            datasets: [
                {
                    label: 'Eventos',
                    data: semanas.map((s) => Number(s.eventos || 0)),
                    backgroundColor: AMBAR,
                    borderRadius: 2,
                },
            ],
        },
        options: opciones,
    };
}

export function configMenciones(categorias: CategoriaPanel[]): Config<'bar'> {
    const opciones = opcionesBase<'bar'>('Menciones por tipo de observación');
    opciones.indexAxis = 'y';
    if (opciones.plugins?.legend) opciones.plugins.legend.display = false;
    opciones.scales = {
        x: { beginAtZero: true, grid: { color: '#EEF1F7' }, ticks: { font: { size: 9 }, precision: 0 } },
        y: { grid: { display: false }, ticks: { font: { size: 10 } } },
    };
    return {
        type: 'bar',
        data: {
            labels: categorias.map((c) => c.categoria ?? ''),
            datasets: [
                {
                    label: 'Menciones',
                    data: categorias.map((c) => Number(c.menciones || 0)),
                    backgroundColor: NAVY,
                    borderRadius: 2,
                },
            ],
        },
        options: opciones,
    };
}
