import test from 'node:test';
import assert from 'node:assert/strict';
import {
    faltanFechas,
    MENSAJE_FALTAN,
    MENSAJE_ORDEN,
    urlConsulta,
    validarRango,
} from '../../resources/js/modulos/urdido/comun/reporte-rango/logica.ts';

test('faltanFechas: abre solo si falta alguna', () => {
    assert.equal(faltanFechas('', ''), true);
    assert.equal(faltanFechas('2026-09-01', ''), true);
    assert.equal(faltanFechas(null, '2026-09-30'), true);
    assert.equal(faltanFechas('2026-09-01', '2026-09-30'), false);
});

test('validarRango: exige ambas fechas', () => {
    assert.deepEqual(validarRango('', '2026-09-30'), { ok: false, campo: 'fecha_ini', mensaje: MENSAJE_FALTAN });
    assert.deepEqual(validarRango('2026-09-01', '  '), { ok: false, campo: 'fecha_fin', mensaje: MENSAJE_FALTAN });
});

test('validarRango: fin antes que inicio es error; mismo día vale', () => {
    assert.deepEqual(validarRango('2026-09-30', '2026-09-01'), { ok: false, campo: 'fecha_fin', mensaje: MENSAJE_ORDEN });
    assert.deepEqual(validarRango('2026-09-01', '2026-09-01'), { ok: true });
    assert.deepEqual(validarRango('2025-12-31', '2026-01-01'), { ok: true });
});

test('urlConsulta: solo_finalizados explícito 1/0 y omitido sin checkbox', () => {
    const r = 'http://x/urdido/reportesurdido/kaizen';
    assert.equal(
        urlConsulta(r, { fechaIni: '2026-09-01', fechaFin: '2026-09-30', soloFinalizados: true }),
        `${r}?fecha_ini=2026-09-01&fecha_fin=2026-09-30&solo_finalizados=1`,
    );
    assert.equal(
        urlConsulta(r, { fechaIni: '2026-09-01', fechaFin: '2026-09-30', soloFinalizados: false }),
        `${r}?fecha_ini=2026-09-01&fecha_fin=2026-09-30&solo_finalizados=0`,
    );
    assert.equal(urlConsulta(r, { fechaIni: '2026-09-01', fechaFin: '2026-09-30' }), `${r}?fecha_ini=2026-09-01&fecha_fin=2026-09-30`);
    assert.equal(urlConsulta(`${r}?a=1`, { fechaIni: '2026-09-01', fechaFin: '2026-09-02' }), `${r}?a=1&fecha_ini=2026-09-01&fecha_fin=2026-09-02`);
});
