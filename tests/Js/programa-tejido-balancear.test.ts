/** Lógica pura del balanceo de órdenes compartidas (balancear-logica.ts). */
import assert from 'node:assert/strict';
import test from 'node:test';

import {
    buildDateRange,
    celdaGantt,
    elegirLider,
    formatearFecha,
    mapWithScaledTimeline,
    parseFechaBackendALocal,
    parseNumber,
    repartirTotal,
    sortRegistrosPorFechaTelar,
    tieneOrdCompartida,
    toDateInputValueLocal,
    type CandidatoLider,
} from '../../resources/js/programa-tejido/balancear-logica.ts';

test('parseFechaBackendALocal lee Y-m-d y Y-m-d H:i:s en hora local', () => {
    const d = parseFechaBackendALocal('2026-03-05');
    assert.equal(d?.getFullYear(), 2026);
    assert.equal(d?.getMonth(), 2);
    assert.equal(d?.getDate(), 5);
    assert.equal(d?.getHours(), 0);
    assert.equal(parseFechaBackendALocal('2026-03-05 14:30:15.000')?.getHours(), 14);
    assert.equal(parseFechaBackendALocal(''), null);
    assert.equal(parseFechaBackendALocal('no es fecha'), null);
});

test('toDateInputValueLocal y formatearFecha', () => {
    assert.equal(toDateInputValueLocal(new Date(2026, 0, 9)), '2026-01-09');
    assert.equal(toDateInputValueLocal('x'), '');
    assert.equal(formatearFecha('2026-01-09 08:00:00'), '09/01/2026');
    assert.equal(formatearFecha('1900-01-01'), '-');
    assert.equal(formatearFecha(null), '-');
});

test('parseNumber quita formato', () => {
    assert.equal(parseNumber('1,234.5 pzas'), 1234.5);
    assert.equal(parseNumber(null), 0);
    assert.equal(parseNumber('--'), 0);
});

test('sortRegistrosPorFechaTelar: Posicion, telar numérico, Id', () => {
    const r = sortRegistrosPorFechaTelar([
        { Id: 3, Posicion: 2, NoTelarId: '10' },
        { Id: 2, Posicion: 1, NoTelarId: '10' },
        { Id: 1, Posicion: 1, NoTelarId: '9' },
        { Id: 0, Posicion: 1, NoTelarId: '9' },
    ]);
    assert.deepEqual(r.map((x) => x.Id), [0, 1, 2, 3]);
});

test('buildDateRange incluye los extremos', () => {
    const dias = buildDateRange(new Date(2026, 0, 30, 15), new Date(2026, 1, 2, 1));
    assert.deepEqual(dias.map((d) => d.getDate()), [30, 31, 1, 2]);
});

const cand = (p: Partial<CandidatoLider>): CandidatoLider => ({
    id: 1, noTelarId: '1', isLeader: false, fechaInicioMs: null, fechaInicioKey: null, fechaCreacionMs: null, pedidoActual: 0, ...p,
});

test('elegirLider respeta el líder persistido', () => {
    const l = elegirLider([cand({ id: 1, fechaInicioMs: 1, fechaInicioKey: 'a' }), cand({ id: 2, isLeader: true, fechaInicioMs: 9, fechaInicioKey: 'b' })]);
    assert.equal(l?.id, 2);
});

test('elegirLider: con inicios distintos gana el que empieza antes', () => {
    const l = elegirLider([cand({ id: 1, fechaInicioMs: 20, fechaInicioKey: 'b' }), cand({ id: 2, fechaInicioMs: 10, fechaInicioKey: 'a' })]);
    assert.equal(l?.id, 2);
});

test('elegirLider: mismo inicio → creado antes; empate → mayor pedido', () => {
    assert.equal(elegirLider([
        cand({ id: 1, fechaInicioKey: 'a', fechaCreacionMs: 50 }),
        cand({ id: 2, fechaInicioKey: 'a', fechaCreacionMs: 40 }),
    ])?.id, 2);
    assert.equal(elegirLider([
        cand({ id: 1, fechaInicioKey: 'a', pedidoActual: 10 }),
        cand({ id: 2, fechaInicioKey: 'a', pedidoActual: 30 }),
    ])?.id, 2);
    assert.equal(elegirLider([]), null);
});

test('tieneOrdCompartida', () => {
    assert.equal(tieneOrdCompartida('12'), true);
    for (const v of ['', ' ', '0', 'null', 'NULL', null, undefined]) assert.equal(tieneOrdCompartida(v), false);
});

test('repartirTotal reparte proporcional, respeta producción y cuadra en el último', () => {
    const r = repartirTotal([{ valor: 100, produccion: 0 }, { valor: 300, produccion: 0 }], 800);
    assert.deepEqual(r, [200, 600]);
    const conMinimo = repartirTotal([{ valor: 100, produccion: 90 }, { valor: 100, produccion: 0 }], 100);
    assert.deepEqual(conMinimo, [90, 10]);
    assert.deepEqual(repartirTotal([{ valor: 5, produccion: 8 }], 3), [8]);
    const suma = repartirTotal([{ valor: 1, produccion: 0 }, { valor: 1, produccion: 0 }, { valor: 1, produccion: 0 }], 10);
    assert.equal(suma.reduce((a, b) => a + b, 0), 10);
});

test('mapWithScaledTimeline prorratea el saldo por horas de cada día', () => {
    const reg = { Id: 7, TotalPedido: 48, Produccion: 0, StdDia: 24, FechaInicio: '2026-01-01 12:00:00', FechaFinal: '2026-01-03 12:00:00' };
    const r = mapWithScaledTimeline(reg, {});
    // 48 h de ventana: 12 h el día 1, 24 h el 2, 12 h el 3 → 12 / 24 / 12 piezas.
    assert.deepEqual(r.map, { '2026-01-01': 12, '2026-01-02': 24, '2026-01-03': 12 });
    assert.deepEqual(r.capByDay, { '2026-01-01': 12, '2026-01-02': 24, '2026-01-03': 12 });
    // Sin saldo no hay barras.
    assert.deepEqual(mapWithScaledTimeline({ ...reg, Produccion: 48 }, {}).map, {});
    // El pedido editado (inputsMap) manda sobre TotalPedido.
    assert.equal(mapWithScaledTimeline(reg, { 7: { pedido: 96 } }).map['2026-01-02'], 48);
});

test('celdaGantt: ámbar con espacio, verde cerca del tope, gris sin std', () => {
    assert.equal(celdaGantt(0, 10, 0).cls, '');
    assert.equal(celdaGantt(50, 100, 0).cls, 'gantt-bar-space');
    assert.equal(celdaGantt(95, 100, 0).cls, 'gantt-bar-at-cap');
    assert.equal(celdaGantt(5, null, 1).cls, 'gantt-bar-alt');
});
