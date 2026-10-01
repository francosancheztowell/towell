/**
 * Modal Duplicar/Dividir/Vincular: el botón de confirmar se busca en el diálogo abierto.
 *
 * El fallo: initModalDuplicar guardaba el botón al montar. Al reabrir el modal quedaba
 * el <dialog> anterior (cerrado) en el DOM y el texto Duplicar/Dividir/Vincular y el
 * disabled se aplicaban a ese botón, no al que se ve.
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import { botonConfirmarAbierto } from '../../resources/js/programa-tejido/modales/boton-confirmar.ts';

const SELECTOR_BOTON = '.ui-dialogo__boton--primario';

// DOM de mentira: diálogos en orden de documento; respeta [open] como lo haría el navegador.
function raizCon(dialogos: { open: boolean; boton: object }[]): ParentNode {
    return {
        querySelector(selector: string) {
            assert.ok(selector.includes('dialog.ui-dialogo') && selector.endsWith(SELECTOR_BOTON), selector);
            const soloAbiertos = selector.includes('[open]');
            return dialogos.find((d) => d.open || !soloAbiertos)?.boton ?? null;
        },
    } as unknown as ParentNode;
}

test('con el diálogo anterior aún en el DOM, devuelve el botón del abierto', () => {
    const viejo = { id: 'viejo' };
    const nuevo = { id: 'nuevo' };
    const raiz = raizCon([{ open: false, boton: viejo }, { open: true, boton: nuevo }]);

    assert.equal(botonConfirmarAbierto(raiz), nuevo);
});

test('sin diálogo abierto devuelve null (los llamadores no truenan)', () => {
    const raiz = raizCon([{ open: false, boton: { id: 'viejo' } }]);

    assert.equal(botonConfirmarAbierto(raiz), null);
});

test('initModalDuplicar no guarda el botón: lo vuelve a buscar en cada uso', () => {
    const src = readFileSync(new URL('../../resources/js/programa-tejido/index.js', import.meta.url), 'utf8');

    assert.match(src, /const confirmButton = ptBotonConfirmarAbierto;/);
    // Ningún uso como valor (confirmButton.disabled / .textContent): siempre confirmButton().
    assert.doesNotMatch(src, /confirmButton\.(disabled|textContent|classList)/);
});
