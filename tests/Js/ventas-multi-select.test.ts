// Placement of the Ventas multi-select filter panel (tablets: keyboard and short viewports).
import assert from 'node:assert/strict';
import test from 'node:test';

import { panelPlacement } from '../../resources/js/ventas/multi-select-placement.ts';

const viewport = { width: 1024, height: 768 };

test('the panel opens below the button when there is room', () => {
    const placement = panelPlacement({ top: 100, bottom: 136, left: 50, width: 150 }, viewport, 220);
    assert.deepEqual(placement, { top: 140, bottom: null, left: 50, minWidth: 200, maxHeight: 320 });
});

test('the panel never grows past the space left under the button', () => {
    const placement = panelPlacement({ top: 400, bottom: 436, left: 50, width: 250 }, { width: 1024, height: 680 }, 260);
    assert.equal(placement.top, 440);
    assert.equal(placement.maxHeight, 680 - 440 - 8);
    assert.equal(placement.minWidth, 250);
});

test('the panel opens above the button when the space below is too short', () => {
    const placement = panelPlacement({ top: 600, bottom: 636, left: 50, width: 150 }, viewport, 220);
    assert.equal(placement.top, null);
    assert.equal(placement.bottom, 768 - 600 + 4);
    assert.equal(placement.maxHeight, 320);
});

test('the panel stays inside the viewport horizontally', () => {
    assert.equal(panelPlacement({ top: 100, bottom: 136, left: 950, width: 150 }, viewport, 220).left, 1024 - 220 - 8);
    assert.equal(panelPlacement({ top: 100, bottom: 136, left: -20, width: 150 }, viewport, 220).left, 8);
});
