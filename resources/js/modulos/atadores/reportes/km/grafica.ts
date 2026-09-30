/** Gráfica del reporte Atadores KM (efectividad de enhebrado por barra). Sin DOM: tests node. */
import type { ChartConfiguration } from 'chart.js';

export interface FilaKm {
    etiqueta: string;
    km: string;
    barra: string | number;
    efectividad: number | null;
    enhebrado_min: number;
    ideal_min: number;
}

export function configuracionGrafica(filas: readonly FilaKm[]): ChartConfiguration<'bar' | 'line'> {
    const conEfectividad = filas.filter((f) => f.efectividad !== null);

    return {
        type: 'bar',
        data: {
            labels: conEfectividad.map((f) => `${f.etiqueta} · ${f.km} B${f.barra}`),
            datasets: [
                { type: 'line', label: 'Efectividad %', data: conEfectividad.map((f) => f.efectividad as number), yAxisID: 'pct', borderColor: 'rgb(132 204 22)', backgroundColor: 'rgb(132 204 22)', tension: 0 },
                { type: 'bar', label: 'Total enhebrado (min)', data: conEfectividad.map((f) => f.enhebrado_min), backgroundColor: 'rgb(59 130 246)' },
                { type: 'bar', label: 'Ideal (min)', data: conEfectividad.map((f) => f.ideal_min), backgroundColor: 'rgb(220 38 38)' },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Minutos' } },
                pct: { position: 'right', beginAtZero: true, suggestedMax: 100, grid: { drawOnChartArea: false }, ticks: { callback: (v) => `${v}%` } },
            },
        },
    };
}
