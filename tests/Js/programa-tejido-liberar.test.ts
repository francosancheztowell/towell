/** Liberar Órdenes: lógica pura (modulos/programa-tejido/liberar-ordenes/logica.ts). */
import assert from 'node:assert/strict';
import test from 'node:test';

import {
    calcularRollo,
    esInventSizeFel,
    faltaLMat,
    filaPasaFiltros,
    mensajeLiberar,
    parseNumeroGrid,
    prioridadesIniciales,
    sinComas,
    totalRollos,
    validarMetricasProduccion,
    valoresUnicos,
    type RegistroLiberar,
} from '../../resources/js/modulos/programa-tejido/liberar-ordenes/logica.ts';

test('parseNumeroGrid y sinComas', () => {
    assert.equal(parseNumeroGrid('1,234.5'), 1234.5);
    assert.equal(parseNumeroGrid(''), 0);
    assert.equal(parseNumeroGrid(null), 0);
    assert.equal(parseNumeroGrid('abc'), 0);
    assert.equal(sinComas('12,000'), '12000');
    assert.equal(sinComas(null), null);
});

test('prioridadesIniciales: la propia, la anterior del telar o la de arriba', () => {
    assert.deepEqual(prioridadesIniciales([
        { valor: 'A', anterior: 'x' },
        { valor: '', anterior: 'B' },
        { valor: '', anterior: '' },
        { valor: '  ', anterior: '' },
    ]), ['A', 'B', 'B', 'B']);
    assert.deepEqual(prioridadesIniciales([{ valor: '', anterior: '' }]), ['']);
});

test('calcularRollo: repeticiones, metros y piezas por rollo', () => {
    const base = { pesoRollo: 40, pesoCrudo: 0.5, noTiras: 4, largoCrudo: 60, esFelpa: false, esKm: false, ajusteFel: false };
    // (40 / 0.5 / 4) × 1000 = 20000 repeticiones
    assert.deepEqual(calcularRollo(base), { repeticiones: 20000, mtsRollo: 12000, pzasRollo: 80000 });
    // FEL: metros y piezas a la mitad
    assert.deepEqual(calcularRollo({ ...base, ajusteFel: true }), { repeticiones: 20000, mtsRollo: 6000, pzasRollo: 40000 });
    // Sin largo no hay metros
    assert.equal(calcularRollo({ ...base, largoCrudo: 0 })?.mtsRollo, null);
    // Falta un dato: se limpian las celdas
    assert.equal(calcularRollo({ ...base, pesoCrudo: 0 }), null);
    assert.equal(calcularRollo({ ...base, noTiras: 0 }), null);
    assert.equal(calcularRollo({ ...base, pesoRollo: 0 }), null);
});

test('totalRollos cubre la base del pedido', () => {
    assert.equal(totalRollos(1000, 300), 4);
    assert.equal(totalRollos(0, 300), null);
    assert.equal(totalRollos(1000, null), null);
});

test('esInventSizeFel', () => {
    assert.equal(esInventSizeFel('std-fel'), true);
    assert.equal(esInventSizeFel(' '), false);
    assert.equal(esInventSizeFel(undefined), false);
});

const registro = (p: Partial<RegistroLiberar> = {}): RegistroLiberar => ({
    id: '1', prioridad: '', saldoPedido: '10', noTiras: '2', codigoDibujo: null, bomId: 'B', bomName: 'N', hiloAX: null,
    pesoRollo: '40', repeticiones: '3', saldoMarbete: '3', mtsRollo: '3', pzasRollo: '3', totalRollos: '4', totalPzas: '12',
    densidad: null, observaciones: null, cambioRepaso: 'NO', combinaTram: null, noProduccion: null, asignarFlogs: null, flogsId: null,
    ...p,
});

test('validarMetricasProduccion: primer campo en cero con el número de registro', () => {
    assert.equal(validarMetricasProduccion([registro()]), null);
    assert.match(validarMetricasProduccion([registro(), registro({ noTiras: '0' })]) ?? '', /tiras.*Registro 2\./);
    assert.match(validarMetricasProduccion([registro({ totalPzas: '' })]) ?? '', /Total piezas/);
    assert.match(validarMetricasProduccion([registro({ saldoMarbete: null })]) ?? '', /marbetes/);
});

test('faltaLMat', () => {
    assert.equal(faltaLMat([registro()]), false);
    assert.equal(faltaLMat([registro({ bomName: ' ' })]), true);
    assert.equal(faltaLMat([registro({ bomId: null })]), true);
});

test('mensajeLiberar: validación, message o genérico', () => {
    assert.equal(mensajeLiberar({ message: 'X', errors: { a: ['Primero'] } }), 'Primero');
    assert.equal(mensajeLiberar({ message: 'Solo message' }), 'Solo message');
    assert.equal(mensajeLiberar(null), 'Error al liberar las órdenes.');
    assert.equal(mensajeLiberar({ message: 'Error al liberar las órdenes.', trace_id: 'ab12' }), 'Error al liberar las órdenes. (ref: ab12)');
});

test('filaPasaFiltros: texto contiene (AND) y lista tipo Excel', () => {
    const fila: Record<string, string> = { Maquina: 'SMIT 201', Hilo: '' };
    const valor = (c: string) => fila[c] ?? null;
    assert.equal(filaPasaFiltros(valor, [{ column: 'Maquina', value: 'smit' }], {}), true);
    assert.equal(filaPasaFiltros(valor, [{ column: 'Maquina', value: 'km' }], {}), false);
    assert.equal(filaPasaFiltros(valor, [], { Hilo: ['(vacío)'] }), true);
    assert.equal(filaPasaFiltros(valor, [], { Hilo: ['ALGODON'] }), false);
    assert.equal(filaPasaFiltros(valor, [], { Maquina: [] }), false);
});

test('valoresUnicos cuenta y ordena, con (vacío)', () => {
    assert.deepEqual(valoresUnicos(['b', 'A', '', 'b']), [['(vacío)', 1], ['A', 1], ['b', 2]]);
});
