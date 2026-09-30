/** Alineación: lógica pura (modulos/programa-tejido/alineacion/logica.ts). */
import assert from 'node:assert/strict';
import test from 'node:test';

import { claseCelda, claseFila, filtrarFilas, valorCelda, valoresColumna } from '../../resources/js/modulos/programa-tejido/alineacion/logica.ts';

const filas = [
    { NoTelarId: '201', RazSN: 'SI' },
    { NoTelarId: '202', RazSN: 'no' },
    { NoTelarId: '201 ', RazSN: null },
];

test('filtrarFilas: OR dentro de la columna, sin mayúsculas ni espacios', () => {
    assert.equal(filtrarFilas(filas, []).length, 3);
    assert.deepEqual(filtrarFilas(filas, [{ column: 'NoTelarId', value: '201' }]).map((f) => f.NoTelarId), ['201', '201 ']);
    assert.equal(filtrarFilas(filas, [{ column: 'NoTelarId', value: '201' }, { column: 'NoTelarId', value: '202' }]).length, 3);
    assert.equal(filtrarFilas(filas, [{ column: 'NoTelarId', value: '201' }, { column: 'RazSN', value: 'si' }]).length, 1);
});

test('valorCelda: decimales de PesoGRM2 y DiasPorEjecutar, Med. Cen. sin tocar', () => {
    assert.equal(valorCelda('PesoGRM2', '412.5'), '412.500');
    assert.equal(valorCelda('DiasPorEjecutar', 3), '3.00');
    assert.equal(valorCelda('AnchoToalla', '6/2'), '6/2');
    assert.equal(valorCelda('PesoGRM2', ''), '');
    assert.equal(valorCelda('X', null), '');
});

test('claseFila y claseCelda', () => {
    assert.match(claseFila(true, true, true), /alineacion-row-alerta-selected/);
    assert.match(claseFila(true, false, true), /bg-blue-500/);
    assert.match(claseFila(false, true, false), /alineacion-row-alerta$/);
    assert.match(claseFila(false, false, false), /bg-gray-50/);
    assert.match(claseCelda('RazSN', ' si ', false), /bg-red-600/);
    assert.match(claseCelda('Otra', 'SI', true), /text-white/);
    assert.match(claseCelda('Otra', 'SI', false), /text-gray-700/);
});

test('valoresColumna: distintos no vacíos con conteo', () => {
    assert.deepEqual(valoresColumna(filas, 'NoTelarId'), [['201', 2], ['202', 1]]);
    assert.deepEqual(valoresColumna(filas, 'RazSN'), [['SI', 1], ['no', 1]]);
});
