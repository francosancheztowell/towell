// Tablas de Ventas › Compara: series por subsección, color de filas y cumplimiento al día.
import assert from 'node:assert/strict';
import test from 'node:test';

import {
    PANEL_LAYOUT, closingKpis, columnCount, elapsedFraction, expectedCompliance, mexicoToday, rowClass,
} from '../../resources/js/ventas/compara.ts';

test('VtasR. vs PlanVtas shows Plan and Real without the Pedido column', () => {
    const layout = PANEL_LAYOUT.planreal;
    assert.deepEqual(layout.series, ['plan', 'real']);
    assert.ok(!layout.series.includes('pedido'));
    // The difference is always Real − Plan, regardless of the "Comparar Δ y %" filter.
    assert.deepEqual(layout.comparison, ['plan', 'real']);
    assert.equal(layout.deltaLabel, 'Diferencia (Real − Plan)');
});

test('Resumen General and Análisis Histórico keep Plan, Pedido and Real', () => {
    assert.deepEqual(PANEL_LAYOUT.summary.series, ['plan', 'pedido', 'real']);
    assert.deepEqual(PANEL_LAYOUT.analisis.series, ['plan', 'pedido', 'real']);
    assert.equal(PANEL_LAYOUT.summary.comparison, undefined);
});

test('columnCount = label + (series + Δ) × metrics + Cumpl. Pzas. + Cumplimiento Actual + Estatus', () => {
    assert.equal(columnCount(2, 5), 1 + 3 * 5 + 3);
    assert.equal(columnCount(3, 5), 1 + 4 * 5 + 3);
});

test('rowClass colors the Towel / Textil subtotal rows', () => {
    assert.equal(rowClass('empresa', 'Towel'), ' pvoc-empresa pvoc-empresa-towel');
    assert.equal(rowClass('empresa', 'Textil'), ' pvoc-empresa pvoc-empresa-textil');
    assert.equal(rowClass('tipo', 'CE'), '');
    assert.equal(rowClass(undefined, 'Towel'), '');
});

test('rowClass marks article rows with their own color', () => {
    assert.equal(rowClass('articulo', 'A123 Toalla · Lisa · 70x140 · Blanco'), ' pvoc-articulo');
    assert.equal(rowClass('cliente', 'Liverpool'), '');
});

test('elapsedFraction: past months count whole, the current month by days, future months not at all', () => {
    const today = { anio: 2026, mes: 10, dia: 14 };
    assert.equal(elapsedFraction(2026, 9, today), 1);
    assert.equal(elapsedFraction(2025, 12, today), 1);
    assert.equal(elapsedFraction(2026, 10, today), 14 / 31);
    assert.equal(elapsedFraction(2026, 11, today), 0);
    assert.equal(elapsedFraction(2027, 1, today), 0);
    assert.equal(elapsedFraction(2028, 2, { anio: 2028, mes: 2, dia: 29 }), 1);
});

test('expectedCompliance = plan pieces due by today ÷ total plan pieces × 100', () => {
    const today = { anio: 2026, mes: 10, dia: 14 };
    const rows = [
        { anio: '2026', mes: '9', plan: { piezas: 300 } },
        { anio: '2026', mes: '10', plan: { piezas: 310 } },
        { anio: '2026', mes: '11', plan: { piezas: 390 } },
    ];
    // 300 + 310 × 14/31 = 440 of 1000.
    assert.equal(expectedCompliance(rows, today), 44);
    assert.equal(expectedCompliance([{ anio: '2026', mes: '10', plan: { piezas: 0 } }], today), 0);
    assert.equal(expectedCompliance([], today), 0);
});

test('mexicoToday uses the America/Mexico_City date, not UTC', () => {
    // 2026-10-15 03:00 UTC is still 2026-10-14 in Mexico City.
    assert.deepEqual(mexicoToday(new Date('2026-10-15T03:00:00Z')), { anio: 2026, mes: 10, dia: 14 });
});

// KPIs de Resumen General: en pesos (V.N.) salvo la proyección por Kg; días naturales del mes.
const kpiRow = (mes: string, planVn: number, realVn: number, planKg = 0, realKg = 0) =>
    ({ anio: '2026', mes, plan: { vn: planVn, kilos: planKg }, real: { vn: realVn, kilos: realKg } });

test('closingKpis: plan, real al corte, diferencia, avance, proyección y ritmo del mes en curso', () => {
    // 16 de octubre (31 días): van 16, faltan 15.
    const kpis = closingKpis([kpiRow('10', 1000, 400, 300, 120), kpiRow('10', 2100, 500, 0, 30)], { anio: 2026, mes: 10, dia: 16 });
    assert.equal(kpis.plan, 3100);
    assert.equal(kpis.real, 900);
    assert.equal(kpis.diferencia, 2200);
    assert.equal(kpis.avance.toFixed(2), (900 / 3100 * 100).toFixed(2));
    assert.equal(kpis.avanceEsperado.toFixed(2), (16 / 31 * 100).toFixed(2));
    assert.equal(kpis.proyeccion.toFixed(4), (900 / (16 / 31)).toFixed(4));
    assert.equal(kpis.proyeccionKg.toFixed(4), (150 / (16 / 31)).toFixed(4));
    assert.equal(kpis.planKg, 300);
    assert.equal(kpis.realKg, 150);
    assert.equal(kpis.diasRestantes, 15);
    assert.equal(kpis.ritmo!.toFixed(4), (2200 / 15).toFixed(4));
});

test('closingKpis: un mes cerrado proyecta lo vendido y no pide ritmo', () => {
    const kpis = closingKpis([kpiRow('9', 1000, 1200)], { anio: 2026, mes: 10, dia: 16 });
    assert.equal(kpis.proyeccion, 1200);
    assert.equal(kpis.diferencia, -200);
    assert.equal(kpis.diasRestantes, 0);
    assert.equal(kpis.ritmo, null);
});

test('closingKpis: sin plan el avance es 0 y una meta ya superada pide ritmo 0', () => {
    assert.equal(closingKpis([kpiRow('10', 0, 50)], { anio: 2026, mes: 10, dia: 16 }).avance, 0);
    assert.equal(closingKpis([kpiRow('10', 100, 150)], { anio: 2026, mes: 10, dia: 16 }).ritmo, 0);
    assert.equal(closingKpis([], { anio: 2026, mes: 10, dia: 16 }).plan, 0);
});

test('closingKpis: Pedidos suma la V.N. del pedido de los mismos combos', () => {
    const rows = [
        { ...kpiRow('10', 1000, 400), pedido: { vn: 700, kilos: 90 } },
        { ...kpiRow('10', 500, 100), pedido: { vn: 50, kilos: 10 } },
    ];
    const kpis = closingKpis(rows, { anio: 2026, mes: 10, dia: 16 });
    assert.equal(kpis.pedido, 750);
    assert.equal(kpis.pedidoKg, 100);
    assert.equal(closingKpis([kpiRow('10', 1, 1)], { anio: 2026, mes: 10, dia: 16 }).pedido, 0);
});
