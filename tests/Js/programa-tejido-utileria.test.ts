/** Utilería (Mover y Finalizar órdenes): lógica pura. */
import assert from 'node:assert/strict';
import test from 'node:test';

import {
    claveId,
    hayCambios,
    mismoTelar,
    moverRegistro,
    textoSeleccion,
    tieneSeleccionSinProduccion,
    tipoSalonDisplay,
} from '../../resources/js/modulos/programa-tejido/utileria/logica.ts';

test('tipoSalonDisplay normaliza los alias del salón', () => {
    assert.equal(tipoSalonDisplay('jac'), 'JACQUARD');
    assert.equal(tipoSalonDisplay('ITEMA'), 'SMIT');
    assert.equal(tipoSalonDisplay('km'), 'KARL MAYER');
    assert.equal(tipoSalonDisplay('OTRO'), 'OTRO');
    assert.equal(tipoSalonDisplay(null), '');
});

test('mismoTelar', () => {
    assert.equal(mismoTelar({ salon: 'SMIT', telar: '201' }, { salon: 'SMIT', telar: '201' }), true);
    assert.equal(mismoTelar({ salon: 'SMIT', telar: '201' }, { salon: 'JACQUARD', telar: '201' }), false);
    assert.equal(mismoTelar(null, { salon: 'SMIT', telar: '201' }), false);
});

test('hayCambios compara orden e ids', () => {
    assert.equal(hayCambios([{ id: 1 }, { id: 2 }], [1, 2]), false);
    assert.equal(hayCambios([{ id: 2 }, { id: 1 }], [1, 2]), true);
    assert.equal(hayCambios([{ id: 1 }], [1, 2]), true);
});

const regs = (...ids: number[]) => ids.map((id) => ({ id, isMoved: false }));

test('moverRegistro entre listas, al final o en una posición', () => {
    const a = regs(1, 2, 3);
    const b = regs(9);
    assert.equal(moverRegistro(a, b, 2, 0), true);
    assert.deepEqual(a.map((r) => r.id), [1, 3]);
    assert.deepEqual(b.map((r) => r.id), [2, 9]);
    assert.equal(b[0]?.isMoved, true);
    moverRegistro(a, b, 1, null);
    assert.deepEqual(b.map((r) => r.id), [2, 9, 1]);
    assert.equal(moverRegistro(a, b, 77, 0), false);
});

test('moverRegistro en la misma lista compensa el índice al bajar', () => {
    const a = regs(1, 2, 3, 4);
    moverRegistro(a, a, 1, 3); // soltar sobre la fila 4 (índice 3) → queda antes de ella
    assert.deepEqual(a.map((r) => r.id), [2, 3, 1, 4]);
    moverRegistro(a, a, 4, 0);
    assert.deepEqual(a.map((r) => r.id), [4, 2, 3, 1]);
});

test('Finalizar: sin producción no se puede, y el texto del pie', () => {
    const ordenes = [{ id: 1, produccion: 10 }, { id: 2, produccion: 0 }, { id: 3, produccion: null }];
    assert.equal(tieneSeleccionSinProduccion([1], ordenes), false);
    assert.equal(tieneSeleccionSinProduccion([1, 2], ordenes), true);
    assert.equal(tieneSeleccionSinProduccion(['3'], ordenes), true);
    assert.equal(tieneSeleccionSinProduccion([99], ordenes), true);
    assert.equal(textoSeleccion(0, false), '');
    assert.equal(textoSeleccion(2, false), '2 orden(es) seleccionada(s)');
    assert.match(textoSeleccion(1, true), /producción es cero/);
    assert.equal(claveId('12'), 12);
    assert.equal(claveId('x'), 'x');
});
