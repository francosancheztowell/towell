/** Pestaña 3 — Qué se tejió (Crudo): artículos, telares, salones y segundas del archivo de crudo. */
import Chart from 'chart.js/auto';
import { agruparArticulos, agruparSalones, filtrarCrudo, porcentaje, tonoSegundas, type ArticuloTejido, type FiltrosCrudo } from '../logica.ts';
import type { DatosKardex, DetalleCrudo, TelarCrudo } from '../tipos.ts';
import { abrirModal, celdaBarra, h, kg, kg0, pastilla, tabla, tarjetasKpi, type Columna } from '../ui.ts';

const REF_SALON: Record<string, string> = {
    JACQUARD: 'Ent Jacquard Kg',
    ITEMA: 'Ent Itema Kg',
    SMITH: 'Ent Smith Kg',
    KM: 'Ent Karl Mayer Kg',
};

const pctSeg = (segKg: number, total: number): HTMLElement => pastilla(tonoSegundas(total ? segKg / total : 0), porcentaje(segKg, total));
const kgPorPieza = (kgs: number, pz: number): string => (pz ? (kgs / pz).toFixed(2) : '—');
const tenue = (): string => 'text-ink-muted';
const segundas = (v: number): string => (v > 0.5 ? 'text-red-600' : 'text-ink-muted');

function modalArticulo(d: DatosKardex, g: ArticuloTejido): void {
    const filas = d.crudo.detalle.filter((r) => r.articulo === g.articulo && r.tamano === g.tamano).sort((a, b) => b.kg - a.kg);
    const [, mm] = d.meta.corte.split('/');
    abrirModal({
        titulo: `${g.articulo} · ${g.nombre} · ${g.tamano} — en qué telares se tejió`,
        resumen: [
            { titulo: 'Piezas', valor: kg0(g.piezas) },
            { titulo: 'Kg', valor: kg(g.kg) },
            { titulo: 'kg/pza', valor: kgPorPieza(g.kg, g.piezas) },
            { titulo: 'Segundas', valor: `${kg(g.segKg)} kg`, detalle: porcentaje(g.segKg, g.kg) },
            { titulo: 'Telares', valor: String(filas.length) },
        ],
        cuerpo: tabla<DetalleCrudo>(
            [
                { titulo: 'Telar', texto: true, celda: (r) => h('b', {}, r.telar) },
                { titulo: 'Salón', texto: true, celda: (r) => r.salon },
                { titulo: 'Piezas', celda: (r) => kg0(r.piezas) },
                { titulo: 'kg', celda: (r) => h('b', {}, kg(r.kg)) },
                { titulo: 'kg/pza', celda: (r) => kgPorPieza(r.kg, r.piezas), clase: tenue },
                { titulo: '% kg', celda: (r) => porcentaje(r.kg, g.kg), clase: tenue },
                { titulo: 'Seg. pzas', celda: (r) => kg0(r.segPiezas), clase: tenue },
                { titulo: 'Seg. kg', celda: (r) => kg(r.segKg), clase: (r) => segundas(r.segKg) },
                { titulo: '% seg', celda: (r) => pctSeg(r.segKg, r.kg) },
                { titulo: 'Días', texto: true, celda: (r) => `${String(r.diaIni).padStart(2, '0')}–${String(r.diaFin).padStart(2, '0')}/${mm ?? ''}`, clase: tenue },
                { titulo: 'Entradas', celda: (r) => kg0(r.entradas), clase: tenue },
            ],
            filas,
            { alto: 'max-h-[60vh]' },
        ),
    });
}

function modalTelar(d: DatosKardex, t: TelarCrudo): void {
    const filas = d.crudo.detalle.filter((r) => r.telar === t.telar).sort((a, b) => b.kg - a.kg);
    abrirModal({
        titulo: `Telar ${t.telar} (${t.tipo}) — artículos que tejió`,
        resumen: [
            { titulo: 'Artículos', valor: String(filas.length) },
            { titulo: 'Piezas', valor: kg0(t.pz) },
            { titulo: 'Kg', valor: kg(t.kg) },
            { titulo: 'Segundas', valor: `${kg(t.segKg)} kg`, detalle: porcentaje(t.segKg, t.kg) },
        ],
        cuerpo: tabla<DetalleCrudo>(
            [
                { titulo: 'Artículo', texto: true, celda: (r) => h('b', {}, r.articulo) },
                { titulo: 'Nombre', texto: true, celda: (r) => r.nombre },
                { titulo: 'Tamaño', texto: true, celda: (r) => r.tamano },
                { titulo: 'Piezas', celda: (r) => kg0(r.piezas) },
                { titulo: 'kg', celda: (r) => h('b', {}, kg(r.kg)) },
                { titulo: 'kg/pza', celda: (r) => kgPorPieza(r.kg, r.piezas), clase: tenue },
                { titulo: '% kg', celda: (r) => porcentaje(r.kg, t.kg), clase: tenue },
                { titulo: 'Seg. kg', celda: (r) => kg(r.segKg), clase: (r) => segundas(r.segKg) },
                { titulo: '% seg', celda: (r) => pctSeg(r.segKg, r.kg) },
            ],
            filas,
            { alto: 'max-h-[60vh]' },
        ),
    });
}

function llenarSelect(select: HTMLSelectElement | null, valores: string[]): void {
    select?.append(...valores.map((v) => h('option', { value: v }, v)));
}

function graficas(raiz: HTMLElement, d: DatosKardex, topSegundas: Set<string>): void {
    const dia = raiz.querySelector<HTMLCanvasElement>('[data-kardex-grafica-crudo-dia]');
    if (dia) {
        const kgs = d.crudo.dia.map(([k]) => Math.round(k));
        const segs = d.crudo.dia.map(([, s]) => Math.round(s));
        const pct = d.crudo.dia.map(([k, s]) => (k > 0 ? Number(((100 * s) / k).toFixed(1)) : null));
        new Chart(dia, {
            data: {
                labels: kgs.map((_, i) => i + 1),
                datasets: [
                    { type: 'bar', label: 'kg tejidos', data: kgs, backgroundColor: '#93bdf7', borderRadius: 3, yAxisID: 'y' },
                    { type: 'bar', label: 'kg segundas', data: segs, backgroundColor: '#e34948', borderRadius: 3, yAxisID: 'y' },
                    { type: 'line', label: '% segundas', data: pct, borderColor: '#b45309', backgroundColor: '#b45309', borderWidth: 2, pointRadius: 2.5, tension: 0.25, yAxisID: 'y1' },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10 } } },
                scales: {
                    y: { ticks: { callback: (v) => kg0(Number(v)) } },
                    y1: { position: 'right', grid: { display: false }, ticks: { color: '#b45309', callback: (v) => `${v}%` } },
                    x: { grid: { display: false } },
                },
            },
        });
    }

    const telar = raiz.querySelector<HTMLCanvasElement>('[data-kardex-grafica-telar]');
    if (telar) {
        const top = [...d.crudo.telares].sort((a, b) => b.kg - a.kg).slice(0, 35);
        new Chart(telar, {
            type: 'bar',
            data: {
                labels: top.map((t) => t.telar),
                datasets: [{ label: 'kg', data: top.map((t) => Math.round(t.kg)), backgroundColor: top.map((t) => (topSegundas.has(t.telar) ? '#e34948' : '#2a78d6')), borderRadius: 4, maxBarThickness: 10 }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (c) => {
                                const t = top[c.dataIndex];
                                return t ? `${kg0(c.parsed.x)} kg · ${t.tipo} · seg ${porcentaje(t.segKg, t.kg)}` : '';
                            },
                        },
                    },
                },
                scales: { x: { ticks: { callback: (v) => kg0(Number(v)) } }, y: { grid: { display: false }, ticks: { font: { size: 10 } } } },
            },
        });
    }
}

export function montarCrudo(raiz: HTMLElement, d: DatosKardex): void {
    const c = d.crudo;
    const T = c.total;
    const telares = [...c.telares].sort((a, b) => b.kg - a.kg);
    const porSegundas = [...telares].sort((a, b) => b.segKg - a.segKg).slice(0, 3);
    const rango = new Map(porSegundas.map((t, i) => [t.telar, i + 1]));
    const refProd = Object.values(REF_SALON).reduce((a, k) => a + (c.referenciaKdx[k] ?? 0), 0);

    raiz.querySelector('[data-kardex-crudo-kpis]')?.replaceChildren(
        tarjetasKpi([
            { titulo: 'Producción tejida del mes', valor: kg(d.kpis.crudoKg), unidad: 'kg', detalle: `${kg0(d.kpis.crudoPz)} piezas · KdxCrudo sin rasurado (libro)` },
            { titulo: 'Segundas', valor: kg(T.segKg), unidad: 'kg', detalle: `${kg0(T.segPz)} piezas (${porcentaje(T.segKg, T.kg)} del peso) · top telares: ${porSegundas.map((t) => `${t.telar} (${kg0(t.segKg)} kg)`).join(' · ')}` },
            { titulo: 'Telares y artículos', valor: `${kg0(T.nTel)} · ${kg0(T.nArts)}`, detalle: 'telares activos · artículos tejidos' },
            { titulo: 'Retazo a mermas', valor: kg(T.retazoKg), unidad: 'kg', detalle: 'hoja Retazo del archivo (A-MERMAS)' },
        ]),
    );

    const unicos = (f: (r: DetalleCrudo) => string): string[] => [...new Set(c.detalle.map(f))].filter(Boolean);
    const sel = (n: string): HTMLSelectElement | null => raiz.querySelector<HTMLSelectElement>(`[data-kardex-filtro="${n}"]`);
    const busca = raiz.querySelector<HTMLInputElement>('[data-kardex-filtro="busqueda"]');
    llenarSelect(sel('tamano'), unicos((r) => r.tamano).sort());
    llenarSelect(sel('telar'), unicos((r) => r.telar).sort((a, b) => Number(a) - Number(b)));
    llenarSelect(sel('salon'), unicos((r) => r.salon).sort());

    const pintarArticulos = (): void => {
        const filtros: FiltrosCrudo = { busqueda: busca?.value ?? '', tamano: sel('tamano')?.value ?? '', telar: sel('telar')?.value ?? '', salon: sel('salon')?.value ?? '' };
        const filtrado = filtrarCrudo(c.detalle, filtros);
        const filas = agruparArticulos(filtrado);
        const tot = filas.reduce((a, r) => ({ kg: a.kg + r.kg, pz: a.pz + r.piezas, seg: a.seg + r.segKg }), { kg: 0, pz: 0, seg: 0 });
        const nTel = new Set(filtrado.map((r) => r.telar)).size;
        const maxKg = Math.max(1, ...filas.map((r) => r.kg));
        raiz.querySelector('[data-kardex-crudo-sumas]')?.replaceChildren(
            tarjetasKpi(
                [
                    { titulo: 'Artículos', valor: kg0(filas.length) },
                    { titulo: 'Piezas', valor: kg0(tot.pz) },
                    { titulo: 'Kg', valor: kg(tot.kg) },
                    { titulo: 'Segundas', valor: `${kg(tot.seg)} kg`, detalle: porcentaje(tot.seg, tot.kg) },
                    { titulo: 'Telares', valor: kg0(nTel) },
                ],
                true,
            ),
        );
        const columnas: Columna<ArticuloTejido>[] = [
            { titulo: 'Artículo', texto: true, celda: (r) => h('b', {}, r.articulo) },
            { titulo: 'Nombre', texto: true, celda: (r) => r.nombre },
            { titulo: 'Tamaño', texto: true, celda: (r) => r.tamano },
            { titulo: 'Piezas', celda: (r) => kg0(r.piezas) },
            { titulo: 'kg', celda: (r) => celdaBarra(r.kg, maxKg) },
            { titulo: 'kg/pza', celda: (r) => kgPorPieza(r.kg, r.piezas), clase: tenue },
            { titulo: '% kg', celda: (r) => porcentaje(r.kg, tot.kg), clase: tenue },
            { titulo: 'Seg. kg', celda: (r) => (r.segKg > 0.5 ? kg(r.segKg) : '·'), clase: (r) => segundas(r.segKg) },
            { titulo: '% seg', celda: (r) => pctSeg(r.segKg, r.kg) },
            { titulo: 'Telares', celda: (r) => r.telares.length },
            { titulo: 'Dónde se tejió', texto: true, celda: (r) => r.telares.slice(0, 4).join(', ') + (r.telares.length > 4 ? ` +${r.telares.length - 4}` : ''), clase: tenue },
        ];
        raiz.querySelector('[data-kardex-crudo-articulos]')?.replaceChildren(
            filas.length
                ? tabla(columnas, filas, {
                      alClic: (g) => modalArticulo(d, g),
                      alto: 'max-h-[560px]',
                      total: ['TOTAL', '', '', kg0(tot.pz), kg(tot.kg), kgPorPieza(tot.kg, tot.pz), '100%', kg(tot.seg), porcentaje(tot.seg, tot.kg), kg0(nTel), ''],
                  })
                : h('p', { class: 'rounded-lg border border-dashed border-line p-6 text-center text-sm text-ink-muted' }, 'Ningún artículo coincide con los filtros.'),
        );
    };
    [busca, sel('tamano'), sel('telar'), sel('salon')].forEach((el) => el?.addEventListener('input', pintarArticulos));
    pintarArticulos();

    const salones = agruparSalones(c.detalle);
    const difSalon = (salon: string, kgs: number): number | null => {
        const ref = REF_SALON[salon];
        const rk = ref ? c.referenciaKdx[ref] : undefined;
        return rk === undefined ? null : kgs - rk;
    };
    const textoDif = (dif: number | null): string => (dif === null ? '—' : Math.abs(dif) <= 0.01 ? '0 ✓' : kg(dif));
    raiz.querySelector('[data-kardex-crudo-salones]')?.replaceChildren(
        tabla(
            [
                { titulo: 'Salón', texto: true, celda: (s) => h('b', {}, s.salon) },
                { titulo: 'Telares', celda: (s) => s.telares },
                { titulo: 'Piezas', celda: (s) => kg0(s.piezas) },
                { titulo: 'kg', celda: (s) => h('b', {}, kg(s.kg)) },
                { titulo: 'Seg. pzas', celda: (s) => kg0(s.segPiezas), clase: tenue },
                { titulo: 'Seg. kg', celda: (s) => kg(s.segKg), clase: () => 'text-red-600' },
                { titulo: '% seg (kg)', celda: (s) => pctSeg(s.segKg, s.kg) },
                { titulo: 'kg KdxCrudo', celda: (s) => { const dif = difSalon(s.salon, s.kg); return dif === null ? '—' : kg(s.kg - dif); } },
                { titulo: 'Dif', celda: (s) => textoDif(difSalon(s.salon, s.kg)), clase: (s) => (Math.abs(difSalon(s.salon, s.kg) ?? 0) > 1 ? 'text-red-600' : tenue()) },
            ],
            salones,
            { total: ['TOTAL', kg0(T.nTel), kg0(T.pz), kg(T.kg), kg0(T.segPz), kg(T.segKg), porcentaje(T.segKg, T.kg), kg(refProd), textoDif(T.kg - refProd)] },
        ),
    );

    raiz.querySelector('[data-kardex-crudo-telares]')?.replaceChildren(
        tabla(
            [
                {
                    titulo: 'Telar',
                    texto: true,
                    celda: (t) => h('span', { class: 'inline-flex items-center gap-2' }, h('b', {}, t.telar), rango.has(t.telar) ? pastilla('crit', `#${rango.get(t.telar)} segundas`) : null),
                },
                { titulo: 'Salón', texto: true, celda: (t) => t.tipo },
                { titulo: 'Artículos', celda: (t) => new Set(c.detalle.filter((r) => r.telar === t.telar).map((r) => `${r.articulo}|${r.tamano}`)).size },
                { titulo: 'Piezas', celda: (t) => kg0(t.pz) },
                { titulo: 'kg', celda: (t) => kg(t.kg) },
                { titulo: 'Seg. kg', celda: (t) => kg(t.segKg), clase: (t) => segundas(t.segKg) },
                { titulo: '% seg', celda: (t) => pctSeg(t.segKg, t.kg) },
                { titulo: '% del total', celda: (t) => porcentaje(t.kg, T.kg), clase: tenue },
            ],
            telares,
            { alClic: (t) => modalTelar(d, t), alto: 'max-h-[420px]' },
        ),
    );

    graficas(raiz, d, new Set(rango.keys()));
}
