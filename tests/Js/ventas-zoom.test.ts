// Zoom tipo Excel de las tablas de Ventas › Compara.
import assert from 'node:assert/strict';
import test from 'node:test';

import { ZOOM_DEFAULT, ZOOM_MAX, ZOOM_MIN, clampZoom, fitZoom, stepZoom } from '../../resources/js/ventas/zoom.ts';

test('clampZoom keeps the zoom between the limits and rounds to whole percents', () => {
    assert.equal(clampZoom(5), ZOOM_MIN);
    assert.equal(clampZoom(900), ZOOM_MAX);
    assert.equal(clampZoom(87.6), 88);
    assert.equal(clampZoom(Number.NaN), ZOOM_DEFAULT);
});

test('the tables open at 90 %, the size the team chose as normal', () => {
    assert.equal(ZOOM_DEFAULT, 90);
});

test('stepZoom moves to the next multiple of 10 like Excel', () => {
    assert.equal(stepZoom(100, 1), 110);
    assert.equal(stepZoom(100, -1), 90);
    assert.equal(stepZoom(87, 1), 90);
    assert.equal(stepZoom(87, -1), 80);
    assert.equal(stepZoom(ZOOM_MAX, 1), ZOOM_MAX);
    assert.equal(stepZoom(ZOOM_MIN, -1), ZOOM_MIN);
});

test('fitZoom scales the table so every column fits the visible width', () => {
    // A 2000 px table at 100 % in a 1000 px container needs 50 %.
    assert.equal(fitZoom(1000, 2000, 100), 50);
    // At 80 % the same table measures 1600 px: it still needs 50 %.
    assert.equal(fitZoom(1000, 1600, 80), 50);
    // A table narrower than the container never grows past 100 %.
    assert.equal(fitZoom(1000, 500, 100), 100);
    // Never below the minimum, and a table with no width keeps the current zoom.
    assert.equal(fitZoom(100, 5000, 100), ZOOM_MIN);
    assert.equal(fitZoom(1000, 0, 70), 70);
});
