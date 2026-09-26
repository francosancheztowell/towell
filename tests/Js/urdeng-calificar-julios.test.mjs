import test from 'node:test';
import assert from 'node:assert/strict';
import {
    clasesDefecto,
    defectoSeleccionado,
    fechaCorta,
    fechaHoy,
    operadorDelJulio,
    TODAS_LAS_CLASES_DEFECTO,
} from '../../resources/js/modulos/urdido/comun/calificar-julios/logica.ts';

test('operador: el de mayor metraje con nombre', () => {
    assert.equal(operadorDelJulio({ Metros1: 10, NomEmpl1: 'Ana', Metros2: '30', NomEmpl2: ' Beto ', Metros3: 5, NomEmpl3: 'Caro' }), 'Beto');
});

test('operador: empate de metros → el primero con nombre', () => {
    assert.equal(operadorDelJulio({ Metros1: 30, NomEmpl1: '', Metros2: 30, NomEmpl2: 'Beto' }), 'Beto');
});

test('operador: sin metros → primer nombre capturado; nada → vacío', () => {
    assert.equal(operadorDelJulio({ NomEmpl2: 'Dani' }), 'Dani');
    assert.equal(operadorDelJulio({}), '');
});

test('fechaCorta acepta ISO, con hora y vacío', () => {
    assert.equal(fechaCorta('2026-04-07T00:00:00.000000Z'), '2026-04-07');
    assert.equal(fechaCorta('2026-04-07 14:30:00'), '2026-04-07');
    assert.equal(fechaCorta(null), '');
});

test('fechaHoy usa la zona de la app', () => {
    // 2026-01-01 03:00 UTC es todavía 31 de diciembre en Ciudad de México.
    assert.equal(fechaHoy('America/Mexico_City', new Date('2026-01-01T03:00:00Z')), '2025-12-31');
});

test('clases por clave de defecto y catálogo completo', () => {
    assert.deepEqual(clasesDefecto('RHC'), ['bg-red-100', 'text-red-800']);
    assert.deepEqual(clasesDefecto(''), []);
    assert.deepEqual(clasesDefecto('NO-EXISTE'), []);
    assert.ok(TODAS_LAS_CLASES_DEFECTO.includes('bg-purple-100'));
});

test('defecto seleccionado compara como entero', () => {
    assert.equal(defectoSeleccionado({ ClaveDefecto: '7' }, { Id: 7, Clave: 'RHC' }), true);
    assert.equal(defectoSeleccionado({ ClaveDefecto: null }, { Id: 7, Clave: 'RHC' }), false);
    assert.equal(defectoSeleccionado({ ClaveDefecto: 8 }, { Id: '7', Clave: 'RHC' }), false);
});
