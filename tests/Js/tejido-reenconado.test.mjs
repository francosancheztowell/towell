import test from 'node:test';
import assert from 'node:assert/strict';
import {
    CAMPOS,
    datasetDeRegistro,
    eficiencia,
    fijo,
    opcionesCalibres,
    opcionesColores,
    opcionesFibras,
    pasaFiltro,
    textoCelda,
    turnoPorMinuto,
    validar,
} from '../../resources/js/modulos/tejido/reenconado/logica.ts';

const completo = {
    Folio: 'RE0001', Date: '2026-09-29', Turno: '1', numero_empleado: '100', nombreEmpl: 'Ana',
    Calibre: '20/1', FibraTrama: 'ALG', CodColor: 'C1', Color: 'Blanco', Cantidad: '10',
    Cabezuela: null, Conos: '4', Horas: '2', Eficiencia: '0.54', Obs: null,
};

test('validar: primer obligatorio vacío con el texto de antes; Cabezuela y Obs son opcionales', () => {
    assert.equal(validar(completo), null);
    assert.equal(validar({ ...completo, Date: null }), 'La fecha es requerida');
    assert.equal(validar({ ...completo, numero_empleado: '' }), 'El número de empleado es requerido');
    assert.equal(validar({ ...completo, Eficiencia: null }), 'La eficiencia es requerida');
    assert.equal(validar({ ...completo, Conos: 0 }), null, '0 numérico cuenta como dato');
});

test('eficiencia: la misma fórmula y redondeo que el controller (Cantidad / round(Horas × 9.3, 2))', () => {
    assert.equal(eficiencia('10', '2'), '0.54'); // 10 / 18.6 = 0.5376
    assert.equal(eficiencia(93, 10), '1.00');
    assert.equal(eficiencia('', '2'), '');
    assert.equal(eficiencia('10', '0'), '');
    assert.equal(eficiencia('abc', '2'), '');
});

test('turnoPorMinuto: 6:30–14:30 = 1, 14:30–22:30 = 2, resto 3', () => {
    assert.equal(turnoPorMinuto(6 * 60 + 29), 3);
    assert.equal(turnoPorMinuto(6 * 60 + 30), 1);
    assert.equal(turnoPorMinuto(14 * 60 + 30), 2);
    assert.equal(turnoPorMinuto(22 * 60 + 30), 3);
});

test('fijo / textoCelda / datasetDeRegistro: 2 decimales en numéricos, vacío en null', () => {
    assert.equal(fijo(null), '');
    assert.equal(fijo('1.5'), '1.50');
    assert.equal(textoCelda('Cantidad', 3), '3.00');
    assert.equal(textoCelda('Conos', 3), '3');
    assert.equal(textoCelda('Obs', null), '');
    const ds = datasetDeRegistro({ ...completo, Horas: 2 });
    assert.equal(ds.numeroEmpleado, '100');
    assert.equal(ds.horas, '2.00');
    assert.equal(ds.cabezuela, '');
    assert.equal(CAMPOS.length, 14, 'una columna por campo de la tabla');
});

test('opciones de catálogos: descarta vacíos y arma la etiqueta del color', () => {
    assert.deepEqual(opcionesCalibres([{ ItemId: '20/1' }, { ItemId: null }, {}]), ['20/1']);
    assert.deepEqual(opcionesFibras(undefined), []);
    assert.deepEqual(opcionesFibras([{ ConfigId: 'ALG' }]), ['ALG']);
    assert.deepEqual(opcionesColores([{ InventColorId: 'C1', Name: 'Blanco' }, { InventColorId: '', Name: 'x' }]), [
        { value: 'C1', label: 'C1 - Blanco', name: 'Blanco' },
    ]);
});

test('filtros: exacto, vacío = todos', () => {
    assert.equal(pasaFiltro({ operador: ' Ana ', calibre: '20/1' }, { operador: 'Ana', calibre: '' }), true);
    assert.equal(pasaFiltro({ operador: 'Ana', calibre: '20/1' }, { operador: 'Beto', calibre: '' }), false);
    assert.equal(pasaFiltro({ operador: 'Ana', calibre: '20/1' }, { operador: '', calibre: '30/1' }), false);
    assert.equal(pasaFiltro({}, { operador: '', calibre: '' }), true);
});
