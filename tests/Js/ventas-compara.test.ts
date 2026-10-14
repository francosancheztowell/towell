// Tablas de Ventas › Compara: series por subsección y color de las filas por empresa.
import assert from 'node:assert/strict';
import test from 'node:test';

import { PANEL_LAYOUT, columnCount, empresaRowClass } from '../../resources/js/ventas/compara.ts';

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

test('columnCount = label + (series + Δ) × metrics + Cumpl. + Estatus', () => {
    assert.equal(columnCount(2, 5), 1 + 3 * 5 + 2);
    assert.equal(columnCount(3, 5), 1 + 4 * 5 + 2);
});

test('empresaRowClass colors only the Towel / Textil subtotal rows', () => {
    assert.equal(empresaRowClass('empresa', 'Towel'), ' pvoc-empresa pvoc-empresa-towel');
    assert.equal(empresaRowClass('empresa', 'Textil'), ' pvoc-empresa pvoc-empresa-textil');
    assert.equal(empresaRowClass('tipo', 'CE'), '');
    assert.equal(empresaRowClass(undefined, 'Towel'), '');
});
