import assert from 'node:assert/strict';
import test from 'node:test';

import {
    COLUMNAS_LINEA,
    formatoEntero,
    lineasDeRespuesta,
    totalesLineas,
} from '../../resources/js/programa-tejido/lineas-logica.ts';

test('formatoEntero redondea (≥ .5 arriba) con separador de miles', () => {
    assert.equal(formatoEntero(1234.5), '1,235');
    assert.equal(formatoEntero('999.4'), '999');
    assert.equal(formatoEntero(0), '0');
    assert.equal(formatoEntero(null), '');
    assert.equal(formatoEntero(''), '');
    assert.equal(formatoEntero('abc'), 'abc');
});

test('lineasDeRespuesta acepta arreglo, {data:[]} y paginate', () => {
    const fila = { Cantidad: 1 };
    assert.deepEqual(lineasDeRespuesta([fila]), [fila]);
    assert.deepEqual(lineasDeRespuesta({ data: [fila] }), [fila]);
    assert.deepEqual(lineasDeRespuesta({ data: { data: [fila] } }), [fila]);
    assert.equal(lineasDeRespuesta({ data: { total: 0 } }), null);
    assert.equal(lineasDeRespuesta(null), null);
});

test('totalesLineas suma por columna y lo no numérico cuenta 0', () => {
    const t = totalesLineas([
        { Cantidad: '10.5', Kilos: 2, MtsRizo: 'x' },
        { Cantidad: 4, Kilos: null, Rizo: '3' },
    ]);
    assert.equal(t.Cantidad, 14.5);
    assert.equal(t.Kilos, 2);
    assert.equal(t.Rizo, 3);
    assert.equal(t.MtsRizo, 0);
    assert.deepEqual(Object.keys(t), [...COLUMNAS_LINEA]);
});
