// Ancho de columnas tipo Excel en las tablas de Ventas › Compara.
import assert from 'node:assert/strict';
import test from 'node:test';

import { COLUMN_MIN_EM, colgroupHtml, columnKeys, resizeWidth, tableWidthEm } from '../../resources/js/ventas/column-resize.ts';

test('columnKeys follows the cell order: label, series × metrics, Δ × metrics, Cumpl., Estatus', () => {
    assert.deepEqual(columnKeys(['plan', 'real'], ['piezas', 'kilos']), [
        'label', 'plan:piezas', 'plan:kilos', 'real:piezas', 'real:kilos', 'delta:piezas', 'delta:kilos', 'cumpl', 'estatus',
    ]);
});

test('resizeWidth converts the dragged pixels to em and never goes below the minimum', () => {
    // 12 px font: dragging 24 px to the right widens 2 em.
    assert.equal(resizeWidth(6, 24, 12), 8);
    assert.equal(resizeWidth(6, -12, 12), 5);
    assert.equal(resizeWidth(6, -500, 12), COLUMN_MIN_EM);
    // Without a usable font size the width does not change.
    assert.equal(resizeWidth(6, 24, 0), 6);
});

test('tableWidthEm adds the widths of the visible columns', () => {
    assert.equal(tableWidthEm(['label', 'plan:piezas'], { label: 12, 'plan:piezas': 6.5 }), 18.5);
});

test('colgroupHtml has one col per column and only sets the widths it knows', () => {
    assert.equal(colgroupHtml(['label', 'cumpl'], {}), '<colgroup><col data-col="label"><col data-col="cumpl"></colgroup>');
    assert.equal(colgroupHtml(['label', 'cumpl'], { label: 10 }),
        '<colgroup><col data-col="label" style="width:10em"><col data-col="cumpl"></colgroup>');
});
