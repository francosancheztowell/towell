import test from 'node:test';
import assert from 'node:assert/strict';
import {
    CLASES_VALOR,
    ICONO_VALOR,
    TODAS_LAS_CLASES_VALOR,
    mensajePendientes,
    pendientes,
    siguienteValor,
    valorDe,
} from '../../resources/js/modulos/urdido/comun/bpm-line/logica.ts';

test('ciclo 0 → 1 → 2 → 0', () => {
    assert.equal(siguienteValor(0), 1);
    assert.equal(siguienteValor(1), 2);
    assert.equal(siguienteValor(2), 0);
});

test('valorDe normaliza el data-valor', () => {
    assert.equal(valorDe('1'), 1);
    assert.equal(valorDe(2), 2);
    assert.equal(valorDe(''), 0);
    assert.equal(valorDe('7'), 0);
    assert.equal(valorDe(undefined), 0);
});

test('pendientes = las que siguen en 0', () => {
    assert.equal(pendientes(['1', '2', '0', '', undefined]), 3);
    assert.equal(pendientes(['1', '2']), 0);
    assert.match(mensajePendientes(3), /^Faltan 3 actividad\(es\) por marcar/);
});

test('clases e íconos por valor', () => {
    assert.deepEqual(CLASES_VALOR[1], ['bg-green-100', 'border-green-400', 'text-green-700']);
    assert.equal(ICONO_VALOR[2], '✗');
    assert.equal(TODAS_LAS_CLASES_VALOR.length, 9);
});
