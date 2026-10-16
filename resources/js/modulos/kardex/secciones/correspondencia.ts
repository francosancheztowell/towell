/** Pestaña 5 — Correspondencia E↔S: los dos lados de cada traspaso y consumo vs producción por proceso. */
import { esCero } from '../logica.ts';
import type { DatosKardex, ProcesoCorrespondencia, Traspaso } from '../tipos.ts';
import { abrirModal, almacen, celdaBarra, encabezadoAlmacen, h, kg, kg0, pastilla, tabla, tarjetasKpi } from '../ui.ts';

const tenue = (): string => 'text-ink-muted';

function modalPar(t: Traspaso): void {
    abrirModal({
        titulo: `Traspasos ${t.origen} → ${t.destino} — los dos lados del espejo`,
        resumen: [
            { titulo: `Salidas en ${t.origen}${t.corregido ? ' (corregidas)' : ''}`, valor: `${kg(t.kgSalida)} kg`, detalle: `${kg0(t.movsSalida)} movimientos S` },
            { titulo: `Entradas en ${t.destino}`, valor: `${kg(t.kgEntrada)} kg`, detalle: `${kg0(t.movsEntrada)} movimientos E` },
            { titulo: 'Diferencia del par', valor: `${kg(t.dif)} kg`, detalle: esCero(t.dif) ? 'cuadra exacto' : 'salidas sin entrada espejo — revisar' },
        ],
        cuerpo: h(
            'p',
            { class: 'text-sm text-ink-muted' },
            'El listado movimiento por movimiento de ambos lados se muestra al conectar el archivo de movimientos del mes (hoy los datos son simulados).',
            t.corregido ? ' ✎ Este par incluye traspasos con destino corregido: la salida se capturó hacia otro almacén.' : '',
        ),
    });
}

function tarjetaProceso(p: ProcesoCorrespondencia, nombre: string): HTMLElement {
    const pct = p.consumido ? (p.dif / p.consumido) * 100 : 0;
    const signo = (v: number): string => (v > 0 ? '+' : '');
    const estado = Math.abs(pct) < 0.5 ? pastilla('ok', 'Cierra') : pastilla(Math.abs(pct) < 6 ? 'warn' : 'na', `${signo(p.dif)}${kg(p.dif)} kg (${signo(pct)}${pct.toFixed(1)}%)`);
    const renglon = (etiqueta: Node, valor: string, clase = ''): HTMLElement =>
        h('div', { class: `flex items-center justify-between gap-3 px-4 py-2 text-sm ${clase}` }, h('span', {}, etiqueta), h('span', { class: 'font-semibold tabular-nums' }, valor));

    const consumos = p.consumos.map((c) =>
        renglon(h('span', { class: 'inline-flex flex-wrap items-center gap-1' }, 'Consumo desde ', almacen(c.origen), h('span', { class: 'text-ink-muted' }, `(${kg0(c.movs)} movs)`)), `−${kg(c.kg)}`, 'text-red-700'),
    );
    if (p.consumos.length > 1) consumos.push(renglon(document.createTextNode(`Total consumido hacia ${p.destino}`), `−${kg(p.consumido)}`, 'text-red-700'));

    return h(
        'article',
        { class: 'overflow-hidden rounded-xl border border-line bg-surface shadow-sm' },
        encabezadoAlmacen(p.destino, h('span', {}, `${p.destino} — ${nombre}`), h('span', { class: 'ms-auto' }, estado)),
        h(
            'div',
            { class: 'divide-y divide-slate-100' },
            ...consumos,
            renglon(h('span', {}, `Producción que entró en ${p.destino} `, h('span', { class: 'text-ink-muted' }, `(${p.fuente || `${kg0(p.movsProduccion)} movs`})`)), kg(p.producido), 'bg-slate-50 font-bold'),
            renglon(document.createTextNode(p.etiquetaDif), `${signo(p.dif)}${kg(p.dif)} (${signo(pct)}${pct.toFixed(1)}%)`, p.dif < 0 ? 'text-red-700' : ''),
        ),
        h('p', { class: 'border-t border-dashed border-line bg-blue-50/50 px-4 py-2.5 text-caption leading-relaxed text-slate-600' }, p.nota),
    );
}

export function montarCorrespondencia(raiz: HTMLElement, d: DatosKardex): void {
    const C = d.correspondencia;
    const K = C.kpi;
    const [, mm] = d.meta.corte.split('/');
    const nombre = new Map(d.almacenes.map((a) => [a.id, a.nombre]));
    nombre.set('A-CRUDO', 'Crudo (tela tejida)');

    raiz.querySelector('[data-kardex-corresp-kpis]')?.replaceChildren(
        tarjetasKpi([
            { titulo: 'Pares de traspaso verificados', valor: kg0(K.nPares), detalle: `${kg(K.kgTraspasado)} kg traspasados entre almacenes en el mes` },
            { titulo: 'Pares que cuadran', valor: `${K.nCuadran} de ${K.nPares}`, detalle: 'salida del origen = entrada al destino, al centavo' },
            { titulo: 'Destinos corregidos', valor: `${kg0(K.nRedir)} traspasos`, detalle: `${kg(K.kgRedir)} kg venían con el destino mal declarado en la salida` },
            { titulo: 'Diferencia neta global', valor: kg(K.difNeta), unidad: 'kg', detalle: 'todo lo que salió entró en algún almacén' },
        ]),
    );

    const cabeza = raiz.querySelector('[data-kardex-corresp-encabezado]');
    cabeza?.replaceChildren(
        h('b', {}, K.nCuadran === K.nPares ? 'Sí corresponden. ' : 'Hay pares sin espejo. '),
        `Cada traspaso del mes entra completo en el almacén contraparte (diferencia neta global ${kg(K.difNeta)} kg). En `,
        h('b', {}, `${K.nRedir} traspasos`),
        ` (${kg(K.kgRedir)} kg) el destino declarado en la salida venía mal capturado; la tabla ya los presenta corregidos al almacén donde realmente entró el material.`,
        K.sinEspejo ? h('b', {}, ` Ojo: ${kg0(K.sinEspejo)} traspasos con salida sin entrada espejo — revisar.`) : '',
    );

    const traspasos = [...C.traspasos].sort((a, b) => Number(a.interno) - Number(b.interno) || Math.max(b.kgSalida, b.kgEntrada) - Math.max(a.kgSalida, a.kgEntrada));
    const maxKg = Math.max(1, ...traspasos.map((t) => Math.max(t.kgSalida, t.kgEntrada)));
    raiz.querySelector('[data-kardex-corresp-traspasos]')?.replaceChildren(
        tabla(
            [
                { titulo: 'Origen', texto: true, celda: (t) => almacen(t.origen) },
                { titulo: 'Destino', texto: true, celda: (t) => h('span', { class: 'inline-flex items-center gap-1' }, '→ ', almacen(t.destino)) },
                {
                    titulo: '',
                    texto: true,
                    celda: (t) =>
                        h(
                            'span',
                            { class: 'inline-flex gap-1' },
                            t.interno ? pastilla('na', 'Interno') : t.fueraTramo ? pastilla('na', 'Fuera del tramo') : null,
                            t.corregido ? pastilla('warn', '✎ Destino corregido') : null,
                        ),
                },
                { titulo: 'Salidas del origen', celda: (t) => celdaBarra(t.kgSalida, maxKg) },
                { titulo: '# movs S', celda: (t) => kg0(t.movsSalida), clase: tenue },
                { titulo: 'Entradas al destino', celda: (t) => kg(t.kgEntrada) },
                { titulo: '# movs E', celda: (t) => kg0(t.movsEntrada), clase: tenue },
                { titulo: 'Dif', celda: (t) => (esCero(t.dif) ? '·' : kg(t.dif)), clase: (t) => (esCero(t.dif) ? tenue() : 'text-red-600') },
                { titulo: 'Estado', texto: true, celda: (t) => (esCero(t.dif) ? pastilla('ok', 'Cuadra') : pastilla('crit', 'Sin espejo')) },
            ],
            traspasos,
            { alClic: modalPar, alto: 'max-h-[560px]' },
        ),
    );

    const redir = raiz.querySelector<HTMLElement>('[data-kardex-corresp-redirigidos]');
    if (redir && C.redirigidos.length) {
        const corregido = (r: (typeof C.redirigidos)[number]): number => {
            const declarado = new Map<string, number>();
            r.declarado.forEach((x) => declarado.set(x.alm, (declarado.get(x.alm) ?? 0) + x.kg));
            return r.real.reduce((a, x) => a + Math.max(0, x.kg - (declarado.get(x.alm) ?? 0)), 0);
        };
        const lista = (xs: Array<{ alm: string; kg: number }>): HTMLElement => h('span', {}, ...xs.flatMap((x, i) => [i ? ' · ' : '', `${x.alm} `, h('b', {}, kg(x.kg))]));
        redir.hidden = false;
        redir.replaceChildren(
            h(
                'div',
                { class: 'mb-3 rounded-r-lg border-l-4 border-amber-400 bg-amber-50 px-4 py-3 text-sm text-amber-900' },
                h('b', {}, `Correcciones aplicadas — los ${C.redirigidos.length} traspasos con destino mal declarado: `),
                'en cada uno los kg cuadran (salió = entró), pero parte del material entró en un almacén distinto al «Almacén Destino» capturado en la salida. Es un tema de captura, no de inventario.',
            ),
            tabla(
                [
                    { titulo: 'Día', celda: (r) => `${String(r.dia).padStart(2, '0')}/${mm ?? ''}` },
                    { titulo: 'Movimiento', texto: true, celda: (r) => r.id },
                    { titulo: 'Artículo', texto: true, celda: (r) => h('b', {}, r.articulo) },
                    { titulo: 'Sale de', texto: true, celda: (r) => r.almacen },
                    { titulo: 'Destino declarado', texto: true, celda: (r) => lista(r.declarado) },
                    { titulo: 'Dónde entró realmente', texto: true, celda: (r) => lista(r.real) },
                    { titulo: 'kg corregidos', celda: (r) => h('b', {}, kg(corregido(r))), clase: () => 'text-red-600' },
                ],
                C.redirigidos,
            ),
        );
    }

    raiz.querySelector('[data-kardex-corresp-procesos]')?.replaceChildren(...C.procesos.map((p) => tarjetaProceso(p, nombre.get(p.destino) ?? p.destino)));
}
