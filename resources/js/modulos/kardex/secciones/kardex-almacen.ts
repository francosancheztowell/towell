/** Pestaña 2 — Kardex por almacén: réplica de la hoja REs con desglose por artículo y movimientos. */
import {
    ALMACEN_SECCION,
    agruparTejidoPorTipo,
    columnasVisibles,
    desgloseRenglon,
    esCero,
    limpiarEncabezado,
    movimientosSimulados,
    ordenarRenglones,
    type ArticuloDesglose,
} from '../logica.ts';
import type { Celda, DatosKardex, Renglon, SeccionKardex } from '../tipos.ts';
import { abrirModal, almacen, celdaBarra, chip, claseNumero, encabezadoAlmacen, h, kg, tabla, titulo } from '../ui.ts';

interface Estado {
    cerradas: Set<string>;
    orden: Map<string, { col: number; dir: 1 | -1 }>;
    tiposAbiertos: Set<string>;
}

const ICONO: Record<string, string> = {
    mp: 'fa-layer-group',
    urdido: 'fa-gears',
    julurd: 'fa-circle-notch',
    jultela: 'fa-ring',
    tejido: 'fa-table-cells',
    ptejq: 'fa-scissors',
    ptelisos: 'fa-scissors',
    pteitema: 'fa-scissors',
};

const marcaSimulado = (s: SeccionKardex): HTMLElement | null =>
    s.simulado ? h('span', { class: 'rounded bg-amber-300 px-2 py-0.5 text-caption font-semibold uppercase text-amber-950' }, 'Simulado') : null;

const icono = (clave: string): HTMLElement => h('i', { class: `fas ${ICONO[clave] ?? 'fa-box'} opacity-90`, 'aria-hidden': 'true' });

const celda = (v: Celda | undefined, negrita = false): HTMLElement => {
    if (typeof v === 'number' && esCero(v)) return h('span', { class: 'text-slate-300' }, '·');
    const texto = typeof v === 'number' ? kg(v) : String(v ?? '');
    return h(negrita ? 'b' : 'span', { class: typeof v === 'number' ? claseNumero(v) : '' }, texto);
};

function modalMovimientos(r: ArticuloDesglose, mes: string, volver: () => void): void {
    const movs = movimientosSimulados(r);
    const [, mm] = mes.split('/');
    abrirModal({
        titulo: `${r.alm} · ${r.a} ${r.c} ${r.t}${r.loc ? ` · ${r.loc}` : ''} — ${movs.length} movimientos`,
        volver,
        resumen: [
            { titulo: 'Inicial (libro)', valor: kg(r.ini), unidad: 'kg' },
            { titulo: 'Saldo (libro)', valor: kg(r.saldo), unidad: 'kg' },
            { titulo: 'Movs. entradas', valor: kg(r.ent), unidad: 'kg' },
            { titulo: 'Movs. salidas', valor: kg(-r.sal), unidad: 'kg' },
        ],
        cuerpo: h(
            'div',
            {},
            h('p', { class: 'mb-2 text-caption text-amber-700' }, 'Movimientos simulados: el detalle real llega con el archivo de movimientos del mes.'),
            tabla(
                [
                    { titulo: 'Día', celda: (m) => `${String(m.dia).padStart(2, '0')}/${mm ?? ''}` },
                    { titulo: 'Referencia', texto: true, celda: (m) => m.referencia },
                    { titulo: 'Tipo', celda: (m) => m.tipo },
                    { titulo: 'kg', celda: (m) => kg(m.kg), clase: (m) => claseNumero(m.kg) },
                ],
                movs,
                { alto: 'max-h-[55vh]' },
            ),
        ),
    });
}

function modalArticulos(d: DatosKardex, s: SeccionKardex, etiqueta: string): void {
    const items = desgloseRenglon(s.clave, etiqueta, d.articulos);
    const t = items.reduce((a, r) => ({ ini: a.ini + r.ini, ent: a.ent + r.ent, sal: a.sal + r.sal, aj: a.aj + r.aj, saldo: a.saldo + r.saldo }), { ini: 0, ent: 0, sal: 0, aj: 0, saldo: 0 });
    const reabrir = (): void => modalArticulos(d, s, etiqueta);
    abrirModal({
        titulo: `${s.titulo}${etiqueta && etiqueta !== '*' ? ` · ${etiqueta}` : ''} — desglose por artículo`,
        resumen: [
            { titulo: 'Renglones', valor: String(items.length) },
            { titulo: 'Inicial', valor: kg(t.ini) },
            { titulo: 'Entradas', valor: kg(t.ent) },
            { titulo: 'Salidas', valor: `−${kg(t.sal)}` },
            { titulo: 'Saldo', valor: kg(t.saldo) },
        ],
        cuerpo: items.length
            ? tabla(
                  [
                      { titulo: 'Almacén', texto: true, celda: (r) => almacen(r.alm) },
                      { titulo: 'Artículo', texto: true, celda: (r) => h('b', {}, r.a) },
                      { titulo: 'Config · Tamaño · Color', texto: true, celda: (r) => [r.c, r.t, r.o && r.o !== '1000' ? r.o : ''].filter(Boolean).join(' · '), clase: () => 'text-ink-muted' },
                      { titulo: 'Inicial', celda: (r) => kg(r.ini) },
                      { titulo: 'Entradas', celda: (r) => (r.ent ? kg(r.ent) : '·') },
                      { titulo: 'Salidas', celda: (r) => (r.sal ? `−${kg(r.sal)}` : '·') },
                      { titulo: 'Ajuste', celda: (r) => (r.aj ? kg(r.aj) : '·'), clase: (r) => (Math.abs(r.aj) > 0.5 ? 'text-red-600' : 'text-ink-muted') },
                      { titulo: 'Saldo', celda: (r) => h('b', {}, kg(r.saldo)) },
                  ],
                  items,
                  { alClic: (r) => modalMovimientos(r, d.meta.corte, reabrir), alto: 'max-h-[60vh]' },
              )
            : h('p', { class: 'text-sm text-ink-muted' }, 'Sin artículos en la muestra simulada para este renglón.'),
    });
}

/** Fila clicable (abre el desglose) o grupo expandible. */
function fila(celdas: HTMLElement[], accion: () => void, clase = ''): HTMLTableRowElement {
    const tr = h('tr', { class: `cursor-pointer hover:bg-blue-50/60 focus:bg-blue-50 focus:outline-none ${clase}`, tabindex: 0 }, ...celdas);
    tr.addEventListener('click', accion);
    tr.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            accion();
        }
    });
    return tr;
}

const td = (contenido: Node, izquierda = false): HTMLTableCellElement =>
    h('td', { class: `whitespace-nowrap px-3 py-1.5 tabular-nums ${izquierda ? 'text-left' : 'text-right'}` }, contenido);

function tablaSeccion(d: DatosKardex, s: SeccionKardex, est: Estado, repintar: () => void): HTMLElement {
    const cols = columnasVisibles(s);
    const ultima = cols[cols.length - 1] ?? 0;
    const orden = est.orden.get(s.clave);

    const encabezado = (texto: string, col: number | null): HTMLElement => {
        const activo = col !== null && orden?.col === col;
        const boton = h(
            'button',
            { type: 'button', class: 'inline-flex items-center gap-1 uppercase hover:text-primary', 'aria-label': `Ordenar por ${texto}` },
            texto,
            activo ? h('i', { class: `fas ${orden?.dir === 1 ? 'fa-sort-down' : 'fa-sort-up'}`, 'aria-hidden': 'true' }) : null,
        );
        boton.addEventListener('click', () => {
            if (col === null) est.orden.delete(s.clave);
            else est.orden.set(s.clave, { col, dir: activo && orden?.dir === 1 ? -1 : 1 });
            repintar();
        });
        return h('th', { scope: 'col', class: `whitespace-nowrap px-3 py-2 font-semibold ${col === null ? 'text-left' : 'text-right'}`, 'aria-sort': activo ? (orden?.dir === 1 ? 'descending' : 'ascending') : null }, boton);
    };

    const ordenar = (rs: Renglon[]): Renglon[] => (orden ? ordenarRenglones(rs, orden.col, orden.dir) : rs);
    const maximo = (rs: Renglon[]): number => Math.max(1, ...rs.map((r) => Math.abs(typeof r.valores[ultima] === 'number' ? (r.valores[ultima] as number) : 0)));
    const celdasDe = (valores: Celda[], max: number, negrita = false): HTMLTableCellElement[] =>
        cols.map((i) => {
            const v = valores[i];
            return td(i === ultima && typeof v === 'number' ? celdaBarra(v, max) : celda(v, negrita));
        });

    const tbody = h('tbody', { class: 'divide-y divide-slate-100' });
    if (s.clave === 'tejido') {
        const grupos = agruparTejidoPorTipo(s, d.telarTipo);
        const maxGrupo = Math.max(1, ...grupos.map((g) => Math.abs(g.sumas[ultima] ?? 0)));
        for (const g of grupos) {
            const abierto = est.tiposAbiertos.has(g.tipo);
            const etiqueta = h(
                'span',
                { class: 'inline-flex items-center gap-2' },
                h('i', { class: `fas fa-chevron-right text-xs transition-transform ${abierto ? 'rotate-90' : ''}`, 'aria-hidden': 'true' }),
                h('b', {}, g.tipo),
                h('span', { class: 'rounded-full bg-pink-100 px-2 py-0.5 text-caption font-semibold text-pink-700' }, `${g.renglones.length} telares`),
            );
            const tr = fila([td(etiqueta, true), ...celdasDe(g.sumas, maxGrupo, true)], () => {
                if (abierto) est.tiposAbiertos.delete(g.tipo);
                else est.tiposAbiertos.add(g.tipo);
                repintar();
            }, 'bg-pink-50/40');
            tr.setAttribute('aria-expanded', String(abierto));
            tbody.append(tr);
            if (!abierto) continue;
            const telares = ordenar(g.renglones);
            const maxTelar = maximo(telares);
            for (const r of telares) tbody.append(fila([td(h('span', { class: 'ps-6 font-semibold' }, `Telar ${r.etiqueta}`), true), ...celdasDe(r.valores, maxTelar)], () => modalArticulos(d, s, r.etiqueta)));
        }
    } else {
        const rs = ordenar(s.renglones);
        const max = maximo(rs);
        for (const r of rs) tbody.append(fila([td(h('b', {}, r.etiqueta), true), ...celdasDe(r.valores, max)], () => modalArticulos(d, s, r.etiqueta)));
    }
    if (s.total) {
        tbody.append(h('tr', { class: 'bg-slate-50 font-bold' }, td(document.createTextNode('Total general'), true), ...cols.map((i) => td(celda(s.total?.valores[i])))));
    }

    return h(
        'div',
        { class: 'overflow-x-auto' },
        h(
            'table',
            { class: 'min-w-full text-sm text-ink' },
            h('thead', { class: 'bg-slate-100 text-caption text-slate-600' }, h('tr', {}, encabezado(limpiarEncabezado(s.encabezados[0] ?? '') || ' ', null), ...cols.map((i) => encabezado(limpiarEncabezado(s.encabezados[i + 1] ?? ''), i)))),
            tbody,
        ),
    );
}

function tarjetaPretejido(d: DatosKardex, s: SeccionKardex): HTMLElement {
    const valores = s.renglones[0]?.valores ?? [];
    const iSaldo = valores.length - 1;
    const renglones = valores
        .map((v, i) => ({ v, i }))
        .filter(({ v, i }) => typeof v === 'number' && (!esCero(v) || i === 0 || i === iSaldo))
        .map(({ v, i }) =>
            h(
                'div',
                { class: `flex justify-between gap-3 px-4 py-1.5 text-sm ${i === iSaldo ? 'bg-slate-50 font-bold' : ''}` },
                h('span', { class: 'text-ink-muted' }, limpiarEncabezado(s.encabezados[i] ?? '') || 'Saldo'),
                h('span', { class: `tabular-nums ${claseNumero(v as number)}` }, kg(v as number)),
            ),
        );
    const boton = h(
        'button',
        { type: 'button', class: 'block w-full overflow-hidden rounded-xl border border-line bg-surface text-left shadow-sm transition hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary' },
        encabezadoAlmacen(ALMACEN_SECCION[s.clave] ?? '', icono(s.clave), h('span', {}, s.titulo), marcaSimulado(s), chip(`Saldo ${kg(valores[iSaldo] as number)} kg`)),
        h('div', { class: 'divide-y divide-slate-100' }, ...renglones),
    );
    boton.addEventListener('click', () => modalArticulos(d, s, '*'));
    return boton;
}

export function montarKardexAlmacen(raiz: HTMLElement, d: DatosKardex): void {
    const contenedor = raiz.querySelector<HTMLElement>('[data-kardex-secciones]');
    if (!contenedor) return;
    const est: Estado = { cerradas: new Set(), orden: new Map(), tiposAbiertos: new Set() };

    const pintar = (): void => {
        const nodos: HTMLElement[] = [];
        const pretejidos = d.secciones.filter((s) => s.unica);
        let pretejidosPintados = false;
        for (const s of d.secciones) {
            if (s.unica) {
                if (pretejidosPintados) continue;
                pretejidosPintados = true;
                nodos.push(titulo('Pretejidos — trama en telares'), h('div', { class: 'grid gap-4 md:grid-cols-3' }, ...pretejidos.map((p) => tarjetaPretejido(d, p))));
                continue;
            }
            const cerrada = est.cerradas.has(s.clave);
            const cols = columnasVisibles(s);
            const ultima = cols[cols.length - 1] ?? 0;
            const saldo = s.total?.valores[ultima] ?? s.renglones.at(-1)?.valores[ultima] ?? null;
            const cabeza = h(
                'button',
                { type: 'button', class: 'block w-full text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary', 'aria-expanded': String(!cerrada) },
                encabezadoAlmacen(
                    ALMACEN_SECCION[s.clave] ?? '',
                    h('i', { class: `fas fa-chevron-down text-xs transition-transform ${cerrada ? '-rotate-90' : ''}`, 'aria-hidden': 'true' }),
                    icono(s.clave),
                    h('span', {}, s.titulo),
                    marcaSimulado(s),
                    chip(`${s.clave === 'mp' ? 'Final' : 'Saldo'} ${typeof saldo === 'number' ? kg(saldo) : '—'} kg`),
                ),
            );
            cabeza.addEventListener('click', () => {
                if (cerrada) est.cerradas.delete(s.clave);
                else est.cerradas.add(s.clave);
                pintar();
            });
            nodos.push(h('section', { class: 'my-4 overflow-hidden rounded-xl border border-line bg-surface shadow-sm' }, cabeza, cerrada ? null : tablaSeccion(d, s, est, pintar)));
        }
        contenedor.replaceChildren(...nodos);
    };

    pintar();
}
