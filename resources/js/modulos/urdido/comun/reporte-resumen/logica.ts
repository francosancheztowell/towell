/**
 * Resumen semanal de Urdido/Engomado (19-01): configuración de las dos gráficas.
 * Pura (sin DOM ni Chart): la prueban tests/Js/urdeng-reporte-resumen.test.mjs.
 */
import type { ChartConfiguration } from 'chart.js';

/** Una fila de $datosSemanales (buildReporteSemanalData*). */
export interface SemanaResumen {
    semana_label?: string;
    peso_promedio?: number | string | null;
    metros_promedio?: number | string | null;
    cuenta_promedio?: number | string | null;
    eficiencia?: number | string | null;
}

const numero = (v: number | string | null | undefined): number => {
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
};

/** Barras de promedios por julio (peso, metros, cuenta). */
export function configPromedios(datos: SemanaResumen[]): ChartConfiguration<'bar', number[], string> {
    const labels = datos.map((d) => d.semana_label ?? '');
    const serie = (label: string, clave: keyof SemanaResumen, rgb: string) => ({
        label,
        data: datos.map((d) => numero(d[clave])),
        backgroundColor: `rgba(${rgb}, 0.5)`,
        borderColor: `rgba(${rgb}, 1)`,
        borderWidth: 1,
    });

    return {
        type: 'bar',
        data: {
            labels,
            datasets: [
                serie('Peso Promedio x Julio', 'peso_promedio', '54, 162, 235'),
                serie('Metros Promedio x Julio', 'metros_promedio', '255, 99, 132'),
                serie('Cuenta Promedio x Julio', 'cuenta_promedio', '75, 192, 192'),
            ],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' },
                title: { display: true, text: 'Promedios por Semana' },
            },
            scales: { y: { beginAtZero: true } },
        },
    };
}

/** Línea de eficiencia semanal en % (eje 0-100). */
export function configEficiencia(datos: SemanaResumen[]): ChartConfiguration<'line', number[], string> {
    return {
        type: 'line',
        data: {
            labels: datos.map((d) => d.semana_label ?? ''),
            datasets: [
                {
                    label: 'Eficiencia Semanal (%)',
                    data: datos.map((d) => numero(d.eficiencia)),
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.1,
                },
            ],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' },
                title: { display: true, text: 'Eficiencia por Semana' },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { callback: (value) => `${value}%` },
                },
            },
        },
    };
}
