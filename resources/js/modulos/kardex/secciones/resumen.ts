/** Pestaña 1 — Resumen y flujo: KPIs, flujo de la cadena (SVG) y producción diaria por proceso. */
import Chart from 'chart.js/auto';
import { montarDiagramaFlujo, type AristaFlujo, type NodoFlujo } from '../../../componentes/diagrama-flujo/index.ts';
import { ejemploKardex, type DatosAristaKardex } from '../../../componentes/diagrama-flujo/ejemplo-kardex.ts';
import { estadoCuadre } from '../logica.ts';
import type { DatosKardex } from '../tipos.ts';
import { abrirModal, claseNumero, kg, kg0, tabla, tarjetasKpi } from '../ui.ts';

export const COLORES_PROCESO = { urdido: '#2a78d6', engomado: '#eb6834', tejido: '#1baf7a' } as const;

function detalleNodo(d: DatosKardex, nodo: NodoFlujo): void {
    const c = d.cuadre.find((x) => x.alm === nodo.id);
    const articulos = (d.articulos[nodo.id] ?? []).slice(0, 10);
    const estado = c ? estadoCuadre(c.finalErp, c.metaLibro) : null;
    abrirModal({
        titulo: `${nodo.id} · ${nodo.subtitulo ?? ''}`,
        resumen: c
            ? [
                  { titulo: 'Inicial', valor: kg(c.inicial), unidad: 'kg' },
                  { titulo: 'Entradas', valor: kg(c.entradas), unidad: 'kg' },
                  { titulo: 'Salidas', valor: kg(c.salidas), unidad: 'kg' },
                  { titulo: 'Final ERP', valor: kg(c.finalErp), unidad: 'kg' },
                  { titulo: 'Meta libro', valor: kg(c.metaLibro), unidad: 'kg', detalle: estado?.texto ?? '' },
              ]
            : [{ titulo: 'Producción del mes', valor: nodo.valor ?? '—' }],
        cuerpo: articulos.length
            ? tabla(
                  [
                      { titulo: 'Artículo', texto: true, celda: (r) => r.a },
                      { titulo: 'Config · Tamaño', texto: true, celda: (r) => [r.c, r.t].filter(Boolean).join(' · ') },
                      { titulo: 'Inicial', celda: (r) => kg(r.ini) },
                      { titulo: 'Entradas', celda: (r) => kg(r.ent) },
                      { titulo: 'Salidas', celda: (r) => (r.sal ? `−${kg(r.sal)}` : '·') },
                      { titulo: 'Saldo', celda: (r) => kg(r.saldo), clase: (r) => `font-semibold ${claseNumero(r.saldo)}` },
                  ],
                  articulos,
              )
            : document.createTextNode('Sin desglose por artículo para este almacén.'),
    });
}

function detalleArista(arista: AristaFlujo): void {
    const datos = arista.datos as DatosAristaKardex | undefined;
    const desglose = datos?.desglose ?? [];
    abrirModal({
        titulo: `${arista.de} → ${arista.a}`,
        resumen: [{ titulo: 'Flujo neto', valor: kg0(desglose.reduce((a, x) => a + x.kg, 0)), unidad: 'kg', detalle: 'envío − devolución' }],
        cuerpo: tabla([{ titulo: 'Concepto', texto: true, celda: (x) => x.concepto }, { titulo: 'kg', celda: (x) => kg(x.kg) }], desglose),
    });
}

function grafica(canvas: HTMLCanvasElement, d: DatosKardex): void {
    const p = d.produccionDiaria;
    const serie = (label: string, data: number[], color: string) => ({
        label,
        data,
        borderColor: color,
        backgroundColor: color,
        borderWidth: 2,
        pointRadius: 2.5,
        pointHoverRadius: 5,
        tension: 0.25,
    });
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: p.urdido.map((_, i) => i + 1),
            datasets: [serie('Urdido', p.urdido, COLORES_PROCESO.urdido), serie('Engomado', p.engomado, COLORES_PROCESO.engomado), serie('Tejido', p.tejido, COLORES_PROCESO.tejido)],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${kg0(c.parsed.y)} kg` } } },
            scales: { x: { grid: { display: false } }, y: { ticks: { callback: (v) => kg0(Number(v)) } } },
        },
    });
}

export function montarResumen(raiz: HTMLElement, d: DatosKardex): void {
    const k = d.kpis;
    raiz.querySelector('[data-kardex-kpis]')?.replaceChildren(
        tarjetasKpi([
            { titulo: 'Consumo MP + MPBB', valor: kg0(k.consumoMp + k.consumoMpbb), unidad: 'kg', detalle: `MP ${kg0(k.consumoMp)} · MPBB ${kg0(k.consumoMpbb)} (neto de devoluciones)` },
            { titulo: 'Producción julios urdidos', valor: kg0(k.julUrd), unidad: 'kg', detalle: 'urdidoras MC1–MC3 y KM1 → A-JUL/URD' },
            { titulo: 'Producción julios tela', valor: kg0(k.julTela), unidad: 'kg', detalle: 'engomadoras WP → A-JUL/TELA' },
            { titulo: 'Producción de tejido', valor: kg(k.crudoKg), unidad: 'kg', detalle: `${kg0(k.crudoPz)} piezas · archivo de crudo = KdxCrudo al centavo (sin rasurado)` },
        ]),
    );

    const flujo = raiz.querySelector<HTMLElement>('[data-kardex-flujo]');
    if (flujo) {
        montarDiagramaFlujo(flujo, ejemploKardex, {
            onNodo: (n) => detalleNodo(d, n),
            onArista: detalleArista,
            tooltipArista: (a) => `${a.de} → ${a.a}\n${a.etiqueta} kg · clic para el desglose`,
        });
    }

    const canvas = raiz.querySelector<HTMLCanvasElement>('[data-kardex-grafica-dia]');
    if (canvas) grafica(canvas, d);
}
