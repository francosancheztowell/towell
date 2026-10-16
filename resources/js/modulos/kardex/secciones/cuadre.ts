/** Pestaña 4 — Cuadre 3 fuentes: libro Kdx vs movimientos, y cuadre por almacén contra el ERP. */
import { esCero, estadoCuadre } from '../logica.ts';
import type { DatosKardex } from '../tipos.ts';
import { almacen, h, kg, pastilla, tabla } from '../ui.ts';

const tenue = (): string => 'text-ink-muted';
const rojoSi = (v: number | null, umbral = 1): string => (v !== null && Math.abs(v) > umbral ? 'text-red-600' : tenue());
const desglose = (valores: Record<string, number>): string =>
    Object.entries(valores)
        .filter(([, v]) => Math.abs(v) > 0.01)
        .map(([k, v]) => `${k} = ${kg(v)}`)
        .join(' · ');

export function montarCuadre(raiz: HTMLElement, d: DatosKardex): void {
    const nombre = new Map(d.almacenes.map((a) => [a.id, a.nombre]));
    const [, mmFin] = d.meta.corte.split('/');
    const [ddIni, mmIni] = d.meta.corte_inicial.split('/');
    const [ddFin] = d.meta.corte.split('/');

    raiz.querySelector('[data-kardex-verificacion]')?.replaceChildren(
        tabla(
            [
                { titulo: 'Hoja del libro', texto: true, celda: (v) => h('b', {}, v.hoja) },
                { titulo: 'Columna', texto: true, celda: (v) => v.columna },
                { titulo: 'Valor libro', celda: (v) => kg(v.libro) },
                { titulo: 'Valor movimientos', celda: (v) => kg(v.movimientos) },
                { titulo: 'Dif', celda: (v) => (esCero(v.dif) ? '0 ✓' : kg(v.dif)), clase: (v) => rojoSi(v.dif) },
                { titulo: 'Regla de reproducción', texto: true, celda: (v) => h('span', { class: 'block min-w-64 whitespace-normal' }, v.regla) },
            ],
            d.verificacion,
        ),
    );

    raiz.querySelector('[data-kardex-cuadre]')?.replaceChildren(
        tabla(
            [
                { titulo: 'Almacén', texto: true, celda: (c) => h('div', {}, almacen(c.alm), h('div', { class: 'text-caption text-ink-muted' }, nombre.get(c.alm) ?? '')) },
                { titulo: 'Movs', celda: (c) => kg(c.movs, 0) },
                { titulo: `Inicial física ${ddIni}/${mmIni}`, celda: (c) => kg(c.inicial) },
                { titulo: 'Ent mov', celda: (c) => kg(c.entradas) },
                { titulo: 'Sal mov', celda: (c) => kg(c.salidas) },
                { titulo: 'Ini + movs', celda: (c) => kg(c.iniMasMovs) },
                { titulo: 'Movs tras corte / recuentos', celda: (c) => kg(c.ajusteImplicito), clase: (c) => rojoSi(c.ajusteImplicito) },
                { titulo: `Final ERP ${ddFin}/${mmFin}`, celda: (c) => h('b', {}, kg(c.finalErp)) },
                { titulo: 'Meta libro', celda: (c) => h('b', {}, kg(c.metaLibro)) },
                { titulo: 'Dif ERP vs libro', celda: (c) => (c.finalErp === null || c.metaLibro === null ? '—' : kg(c.finalErp - c.metaLibro)), clase: (c) => rojoSi(c.finalErp === null || c.metaLibro === null ? null : c.finalErp - c.metaLibro) },
                { titulo: 'Estatus', texto: true, celda: (c) => { const e = estadoCuadre(c.finalErp, c.metaLibro); return pastilla(e.tono, e.texto); } },
            ],
            d.cuadre,
        ),
    );

    raiz.querySelector('[data-kardex-columnas-kdx]')?.replaceChildren(
        ...d.columnasKdx.map((k) =>
            h(
                'details',
                { class: 'group border-b border-slate-100 py-2 last:border-0' },
                h('summary', { class: 'flex cursor-pointer items-center gap-2 text-sm font-semibold text-ink' }, h('i', { class: 'fas fa-chevron-right text-xs transition-transform group-open:rotate-90', 'aria-hidden': 'true' }), almacen(k.alm), h('span', { class: 'font-normal text-ink-muted' }, `· hoja ${k.hoja}`)),
                h(
                    'div',
                    { class: 'space-y-1 ps-6 pt-2 text-caption text-slate-600' },
                    h('div', {}, `Inicial ${kg(k.inicial)} · Ajuste ${kg(k.ajuste)} · Saldo ${kg(k.saldo)}`),
                    h('div', {}, h('b', {}, 'Entradas: '), desglose(k.entradas) || '—'),
                    h('div', {}, h('b', {}, 'Salidas: '), desglose(k.salidas) || '—'),
                ),
            ),
        ),
    );

    const f = d.meta.fuentes;
    const fuentes = raiz.querySelector('[data-kardex-fuentes]');
    if (fuentes) fuentes.textContent = `Fuentes: ${f.inicial} · ${f.movs} · ${f.kardex}. Generado ${d.meta.generado}. Cuarentenas excluidas (netean 0). Unidad: kg (Cantidad = Peso Estimado, verificado).`;
}
