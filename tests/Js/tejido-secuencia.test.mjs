import test from 'node:test';
import assert from 'node:assert/strict';
import {
    construirPayload,
    esRequerido,
    ordenDesdeLlaves,
    soltarAntes,
    validar,
} from '../../resources/js/modulos/tejido/secuencia/logica.ts';

const telar = [
    { nombre: 'NoTelar', tipo: 'number', requerido: true },
    { nombre: 'TipoTelar', tipo: 'select', requerido: true },
    { nombre: 'Observaciones', tipo: 'textarea', requerido: false },
];
const salon = [
    { nombre: 'NoTelarId', tipo: 'number', requerido: true },
    { nombre: 'SalonTejidoId', tipo: 'text', requerido: true },
    { nombre: 'Orden', tipo: 'number', requerido: 'editar' },
];

test('validar: primer campo requerido vacío, con el texto de antes', () => {
    assert.equal(validar(telar, { NoTelar: '', TipoTelar: 'ITEMA' }, 'crear'), 'El campo NoTelar es requerido');
    assert.equal(validar(telar, { NoTelar: '201', TipoTelar: '  ' }, 'crear'), 'El campo TipoTelar es requerido');
    assert.equal(validar(telar, { NoTelar: '201', TipoTelar: 'ITEMA' }, 'crear'), null);
});

test('Orden solo es obligatorio al editar (al crear, vacío = al final)', () => {
    assert.equal(esRequerido(salon[2], 'crear'), false);
    assert.equal(esRequerido(salon[2], 'editar'), true);
    assert.equal(validar(salon, { NoTelarId: '201', SalonTejidoId: 'X', Orden: '' }, 'crear'), null);
    assert.equal(validar(salon, { NoTelarId: '201', SalonTejidoId: 'X', Orden: '' }, 'editar'), 'El campo Orden es requerido');
});

test('payload: números enteros, texto recortado y vacío → null', () => {
    assert.deepEqual(construirPayload(telar, { NoTelar: '201', TipoTelar: ' ITEMA ', Observaciones: '' }), {
        NoTelar: 201,
        TipoTelar: 'ITEMA',
        Observaciones: null,
    });
    assert.deepEqual(construirPayload(salon, { NoTelarId: '12.7', SalonTejidoId: "d'Ana", Orden: 'x' }), {
        NoTelarId: 12,
        SalonTejidoId: "d'Ana",
        Orden: null,
    });
});

test('orden: llave y campo por variante, posiciones 1..n', () => {
    assert.deepEqual(ordenDesdeLlaves(['7', '3'], { llave: 'Id', campo: 'Secuencia' }), [
        { Id: 7, Secuencia: 1 },
        { Id: 3, Secuencia: 2 },
    ]);
    assert.deepEqual(ordenDesdeLlaves(['301'], { llave: 'NoTelarId', campo: 'Orden' }), [{ NoTelarId: 301, Orden: 1 }]);
});

test('soltar arriba o abajo según la mitad de la fila', () => {
    assert.equal(soltarAntes(104, 100, 10), true);
    assert.equal(soltarAntes(105, 100, 10), false);
});
