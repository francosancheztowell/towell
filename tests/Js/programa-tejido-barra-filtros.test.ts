import assert from 'node:assert/strict';
import test from 'node:test';

import {
    chipsDe, deserializar, filtrosDeParametrosViejos, guardarEnUrl, leerDeUrl, pintarBarra, serializar,
    type EstadoFiltros,
} from '../../resources/js/programa-tejido/barra-filtros.ts';

const RAPIDOS = ['ultimos', 'enProceso', 'salonJacquard'];
const vacio = (): EstadoFiltros => ({
    filters: [],
    quick: { ultimos: false, enProceso: false, salonJacquard: false },
    fechas: { fechaInicio: { desde: null, hasta: null }, fechaFinal: { desde: null, hasta: null } },
});
const etiquetas = { Hilo: 'Hilo', NoTelarId: 'Telar' } as Record<string, string>;
const chips = (e: EstadoFiltros) => chipsDe(e, (c) => etiquetas[c] ?? c, (k) => k.toUpperCase());

test('sin filtros no hay chips ni texto en la URL', () => {
    assert.deepEqual(chips(vacio()), []);
    assert.equal(serializar(vacio()), '');
});

test('un chip por columna con sus valores, uno por rápido y uno por rango', () => {
    const e = vacio();
    e.filters = [
        { column: 'Hilo', operator: 'equals', value: 'A12' },
        { column: 'Hilo', operator: 'equals', value: 'H' },
        { column: 'NoTelarId', operator: 'empty', value: '' },
    ];
    e.quick.salonJacquard = true;
    e.fechas.fechaInicio = { desde: '2026-10-01', hasta: null };
    assert.deepEqual(chips(e), [
        { tipo: 'columna', clave: 'Hilo', etiqueta: 'Hilo', valor: 'A12, H' },
        { tipo: 'columna', clave: 'NoTelarId', etiqueta: 'Telar', valor: '(vacío)' },
        { tipo: 'rapido', clave: 'salonJacquard', etiqueta: 'SALONJACQUARD', valor: '' },
        { tipo: 'fecha', clave: 'fechaInicio', etiqueta: 'F. inicio', valor: '01/10/26 – …' },
    ]);
});

test('serializar y deserializar dan el mismo estado', () => {
    const e = vacio();
    e.filters = [{ column: 'Hilo', operator: 'contains', value: 'a, "b" & <c>' }];
    e.quick.ultimos = true;
    e.fechas.fechaFinal = { desde: '2026-10-01', hasta: '2026-10-31' };
    assert.deepEqual(deserializar(serializar(e), RAPIDOS), e);
});

test('la URL editada a mano no rompe: JSON inválido, tipos raros, rápidos y fechas desconocidos', () => {
    assert.equal(deserializar('{no json', RAPIDOS), null);
    assert.equal(deserializar(null, RAPIDOS), null);
    const e = deserializar(JSON.stringify({
        c: [['Hilo', 'equals', 'A12'], ['x'], [1, 2, 3], 'nada'],
        q: ['ultimos', 'inventado'],
        f: { fechaInicio: ['2026-10-01', '<script>'], otraFecha: ['2026-01-01', null] },
    }), RAPIDOS)!;
    assert.deepEqual(e.filters, [{ column: 'Hilo', operator: 'equals', value: 'A12' }]);
    assert.deepEqual(e.quick, { ultimos: true, enProceso: false, salonJacquard: false });
    assert.deepEqual(e.fechas.fechaInicio, { desde: '2026-10-01', hasta: null });
});

test('los enlaces viejos ?salon=&telar= se vuelven filtros', () => {
    assert.deepEqual(filtrosDeParametrosViejos(new URLSearchParams('salon=JACQUARD&telar=203')), [
        { column: 'SalonTejidoId', operator: 'equals', value: 'JACQUARD' },
        { column: 'NoTelarId', operator: 'equals', value: '203' },
    ]);
    const e = leerDeUrl(RAPIDOS, '?telar=203')!;
    assert.deepEqual(e.filters, [{ column: 'NoTelarId', operator: 'equals', value: '203' }]);
    assert.equal(leerDeUrl(RAPIDOS, '?registro_id=5'), null);
});

test('guardarEnUrl usa replaceState, conserva otros parámetros y quita los viejos', () => {
    const llamadas: string[] = [];
    const win = {
        location: { href: 'http://x/planeacion/programa-tejido?registro_id=9&telar=203' },
        history: { state: null, replaceState: (_s: unknown, _t: string, url: URL) => { llamadas.push(String(url)); } },
    } as unknown as Pick<Window, 'location' | 'history'>;
    const e = vacio();
    e.quick.ultimos = true;
    guardarEnUrl(e, win);
    const url = new URL(llamadas[0]!);
    assert.equal(url.searchParams.get('registro_id'), '9');
    assert.equal(url.searchParams.get('telar'), null);
    assert.deepEqual(deserializar(url.searchParams.get('filtros'), RAPIDOS)?.quick.ultimos, true);
});

test('pintarBarra se oculta sin chips y escapa lo que viene de la BD', () => {
    const el = { hidden: false, innerHTML: 'x' } as unknown as HTMLElement;
    pintarBarra(el, [], 57, 57);
    assert.equal(el.hidden, true);
    assert.equal(el.innerHTML, '');

    pintarBarra(el, [{ tipo: 'columna', clave: 'Hilo', etiqueta: 'Hilo', valor: '<img onerror=x>' }], 3, 57);
    assert.equal(el.hidden, false);
    assert.match(el.innerHTML, /<span class="font-semibold text-slate-900">3<\/span> de 57 registros/);
    assert.ok(!el.innerHTML.includes('<img'), 'el valor debe ir escapado');
    assert.match(el.innerHTML, /data-quitar-tipo="todo"/);
});
