import test from 'node:test';
import assert from 'node:assert/strict';
import {
    CAMPOS,
    clasificarErrorFolio,
    claveServidor,
    construirLinea,
    efiDesdeBd,
    limitarAlEscribir,
    normalizarAlSalir,
    rango,
    sugerencia,
    valoresDesdeLinea,
} from '../../resources/js/modulos/tejido/marcas-finales/nuevo/logica.ts';
import {
    botonesDeshabilitados,
    esVacioOCero,
    fechaIso,
    lineasIncompletas,
    resumenIncompletas,
    validarRegistro,
} from '../../resources/js/modulos/tejido/marcas-finales/consultar/logica.ts';
import { nombrePdf } from '../../resources/js/modulos/tejido/marcas-finales/reporte/logica.ts';

/* ---------- Captura (nuevo) ---------- */

test('rangos por columna iguales al max del Blade; tipo desconocido → 0-100', () => {
    assert.deepEqual(rango('marcas'), [0, 250]);
    assert.deepEqual(rango('horas'), [0, 9999]);
    assert.deepEqual(rango('efi'), [0, 100]);
    assert.deepEqual(rango('x'), [0, 100]);
});

test('al escribir solo se corta lo que pasa del máximo', () => {
    assert.equal(limitarAlEscribir('efi', '101'), '100');
    assert.equal(limitarAlEscribir('marcas', '300'), '250');
    assert.equal(limitarAlEscribir('marcas', '250'), null);
    assert.equal(limitarAlEscribir('horas', '7.'), null); // no rompe lo que se está tecleando
    assert.equal(limitarAlEscribir('efi', ''), null);
});

test('al salir: vacío → 0, negativo → mínimo, tope → máximo, válido intacto', () => {
    assert.equal(normalizarAlSalir('efi', ''), '0');
    assert.equal(normalizarAlSalir('efi', 'abc'), '0');
    assert.equal(normalizarAlSalir('trama', '-3'), '0');
    assert.equal(normalizarAlSalir('horas', '10000'), '9999');
    assert.equal(normalizarAlSalir('horas', '7.5'), '7.5');
});

test('sugerencia al entrar en cero: STD para % Efi, 100 para Marcas', () => {
    assert.equal(sugerencia('efi', '0', '83'), '83');
    assert.equal(sugerencia('efi', '', '0'), null);
    assert.equal(sugerencia('efi', '0', undefined), null);
    assert.equal(sugerencia('efi', '75', '83'), null);
    assert.equal(sugerencia('marcas', '0', undefined), '100');
    assert.equal(sugerencia('marcas', '120', undefined), null);
    assert.equal(sugerencia('trama', '0', undefined), null);
});

test('payload de una línea: nombres del servidor y números', () => {
    assert.equal(claveServidor('efi'), 'PorcentajeEfi');
    assert.equal(claveServidor('marcas'), 'Marcas');
    assert.deepEqual(construirLinea('201', { efi: '80', marcas: '120', horas: '7.5', trama: '', pie: 'x' }), {
        NoTelarId: '201',
        PorcentajeEfi: 80,
        Trama: 0,
        Pie: 0,
        Rizo: 0,
        Otros: 0,
        Marcas: 120,
        Horas: 7.5,
    });
    assert.equal(CAMPOS.length, 7);
});

test('% Efi guardado: entero 0-100; los folios viejos en fracción ya no salen en 0', () => {
    assert.equal(efiDesdeBd(82), 82);
    assert.equal(efiDesdeBd('80'), 80);
    assert.equal(efiDesdeBd(0.82), 82);
    assert.equal(efiDesdeBd(null), 0);
    assert.equal(efiDesdeBd('abc'), 0);
    assert.equal(efiDesdeBd(1), 1);
    assert.deepEqual(valoresDesdeLinea({ Eficiencia: 0.8, Marcas: 120, Horas: null, Trama: 3 }), {
        efi: '80', marcas: '120', horas: '0', trama: '3', pie: '0', rizo: '0', otros: '0',
    });
});

test('errores de generar-folio: mismas ramas que el script anterior', () => {
    assert.deepEqual(clasificarErrorFolio(400, { folio_existente: 'FM0009', creado_por_otro: true }), { tipo: 'en-creacion', folio: 'FM0009' });
    assert.deepEqual(clasificarErrorFolio(400, { folio_existente: 'FM0009', message: 'Ya existe' }), { tipo: 'en-proceso', folio: 'FM0009', mensaje: 'Ya existe' });
    assert.deepEqual(clasificarErrorFolio(409, { folio_existente: 'FM0007' }), { tipo: 'duplicado', mensaje: 'Ya existe un folio con la misma fecha y turno.' });
    assert.deepEqual(clasificarErrorFolio(422, { message: 'Turno inválido.' }), { tipo: 'invalido', mensaje: 'Turno inválido.' });
    assert.deepEqual(clasificarErrorFolio(422, null), { tipo: 'invalido', mensaje: 'Debe seleccionar fecha y turno.' });
    assert.deepEqual(clasificarErrorFolio(500, { message: 'x' }), { tipo: 'error' });
    assert.deepEqual(clasificarErrorFolio(400, {}), { tipo: 'error' });
});

/* ---------- Consultar ---------- */

test('botones: editar/finalizar solo En Proceso; visualizar y supervisor con cualquier folio', () => {
    assert.deepEqual(botonesDeshabilitados(null, null), { editar: true, finalizar: true, visualizar: true, supervisor: true });
    assert.deepEqual(botonesDeshabilitados('FM1', 'En Proceso'), { editar: false, finalizar: false, visualizar: false, supervisor: false });
    assert.deepEqual(botonesDeshabilitados('FM1', 'Finalizado'), { editar: true, finalizar: true, visualizar: false, supervisor: false });
});

test('vacío o cero', () => {
    for (const v of [null, undefined, '', '  ', 0, '0', -1, 'abc']) assert.equal(esVacioOCero(v), true, String(v));
    for (const v of [1, '2', 0.5]) assert.equal(esVacioOCero(v), false, String(v));
});

test('antes de finalizar: telares con campos vacíos y el resumen de la confirmación', () => {
    const lineas = [
        { NoTelarId: '201', Eficiencia: 80, Marcas: 100, Horas: 8, Trama: 1, Pie: 1, Rizo: 1, Otros: 1 },
        { NoTelarId: '202', Eficiencia: 0, Marcas: 100, Horas: 8, Trama: 1, Pie: 1, Rizo: 1, Otros: null },
        { Eficiencia: 80, Marcas: '', Horas: 8, Trama: 1, Pie: 1, Rizo: 1, Otros: 1 },
    ];
    const inc = lineasIncompletas(lineas);
    assert.deepEqual(inc, [
        { telar: '202', campos: ['% Efi', 'Otros'] },
        { telar: 'Línea 3', campos: ['Marcas'] },
    ]);
    assert.equal(resumenIncompletas(inc), 'Hay 3 campo(s) vacío(s) o en cero en 2 telar(es). ¿Deseas continuar?');
});

test('fecha del JSON → input date, y validación del modal de supervisor', () => {
    assert.equal(fechaIso('2026-09-29T06:00:00.000000Z'), '2026-09-29');
    assert.equal(fechaIso('2026-09-29'), '2026-09-29');
    assert.equal(fechaIso(null), '');
    const base = { Date: '2026-09-29', Turno: '1', numero_empleado: '', nombreEmpl: '', Status: 'En Proceso' };
    assert.equal(validarRegistro(base), null);
    assert.equal(validarRegistro({ ...base, Date: '' }), 'Fecha y Turno son obligatorios.');
    assert.equal(validarRegistro({ ...base, Turno: '' }), 'Fecha y Turno son obligatorios.');
});

/* ---------- Reporte ---------- */

test('nombre del PDF', () => {
    assert.equal(nombrePdf('2026-09-29'), 'marcas_finales_2026-09-29.pdf');
    assert.equal(nombrePdf('29/09/2026'), 'marcas_finales_29-09-2026.pdf');
});
