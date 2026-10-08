import test from 'node:test';
import assert from 'node:assert/strict';
import {
    construirLinea,
    datosEdicion,
    esVacioOCero,
    estandarDe,
    folioParaFecha,
    horaActual,
    horaDeTexto,
    hoyLocal,
    limitarValor,
    maxRpm,
    parsePct,
    pctGuardado,
    pctStd,
    recortarHora,
    revisarLineas,
    sugerirValor,
    tituloObservacion,
    valorGuardado,
    valorLinea,
} from '../../resources/js/modulos/tejido/cortes-eficiencia/comun/logica.ts';

test('RPM: tope 750 en los telares 401/402 y 500 en el resto; eficiencia 0..100', () => {
    assert.equal(maxRpm(401), 750);
    assert.equal(maxRpm(402), 750);
    assert.equal(maxRpm(201), 500);
    assert.equal(limitarValor('800', 'rpm', 401), 750);
    assert.equal(limitarValor('700', 'rpm', 201), 500);
    assert.equal(limitarValor('-5', 'rpm', 201), 0);
    assert.equal(limitarValor('abc', 'eficiencia', 201), 0);
    assert.equal(limitarValor('120', 'eficiencia', 401), 100);
    assert.equal(limitarValor('87.9', 'eficiencia', 201), 87);
});

test('eficiencia estándar: fracción o porcentaje → "NN%"', () => {
    assert.equal(pctStd(0.85), '85%');
    assert.equal(pctStd('0.855'), '86%');
    assert.equal(pctStd(85), '85%');
    assert.equal(pctStd(null), '0%');
    assert.equal(pctStd('x'), '0%');
    assert.equal(pctGuardado(null), '');
    assert.equal(pctGuardado('84.6'), '85%');
    assert.equal(parsePct('85%'), 85);
    assert.equal(parsePct(''), null);
    assert.equal(parsePct(null), null);
});

test('valores guardados: RPM entero, eficiencia sin decimales, vacío no pinta', () => {
    assert.equal(valorGuardado('rpm', '410.7'), '410');
    assert.equal(valorGuardado('eficiencia', '87.6'), '88');
    assert.equal(valorGuardado('rpm', null), null);
    assert.equal(valorGuardado('eficiencia', ''), null);
});

test('valor sugerido: horario anterior distinto de 0, si no el estándar', () => {
    assert.equal(sugerirValor(1, 400, { 1: 390 }), 400);
    assert.equal(sugerirValor(2, 400, { 1: 390 }), 390);
    assert.equal(sugerirValor(2, 400, { 1: 0 }), 400);
    assert.equal(sugerirValor(3, 400, { 1: 390, 2: 0 }), 390);
    assert.equal(sugerirValor(3, 400, { 1: 390, 2: 395 }), 395);
    assert.equal(sugerirValor(3, 400, {}), 400);
    assert.equal(estandarDe('eficiencia', '85%'), 85);
    assert.equal(estandarDe('rpm', '399.6'), 400);
    assert.equal(estandarDe('rpm', ''), 0);
});

test('horas: "--:--" no cuenta como tomada; HH:MM local y recorte de segundos', () => {
    assert.equal(horaDeTexto('--:--'), null);
    assert.equal(horaDeTexto(' -- '), null);
    assert.equal(horaDeTexto(''), null);
    assert.equal(horaDeTexto(' 07:05 '), '07:05');
    assert.equal(horaActual(new Date(2026, 8, 24, 7, 5)), '07:05');
    assert.equal(recortarHora('15:30:00.0000000'), '15:30');
    assert.equal(recortarHora('07:05'), '07:05');
});

test('hoyLocal usa la fecha local, no la UTC de toISOString()', () => {
    const noche = new Date(2026, 8, 24, 23, 30);
    assert.equal(hoyLocal(noche), '2026-09-24');
    assert.equal(hoyLocal(new Date(2026, 0, 5, 0, 1)), '2026-01-05');
});

test('línea para store(): 0 viaja como null salvo con observación marcada', () => {
    assert.equal(valorLinea(false, 0), null);
    assert.equal(valorLinea(true, 0), 0);
    assert.equal(valorLinea(false, 410), 410);

    const linea = construirLinea({
        telar: 201,
        rpmStd: '400',
        efStd: '85%',
        rpm: { 1: 410, 2: 0, 3: 0 },
        eficiencia: { 1: 88, 2: 0, 3: 0 },
        marcado: { 1: false, 2: true, 3: false },
        obs: { 1: '', 2: 'Paro por hilo', 3: '' },
    });
    assert.deepEqual(linea, {
        NoTelar: 201, SalonTejidoId: null, RpmStd: 400, EficienciaStd: 85,
        RpmR1: 410, EficienciaR1: 88, RpmR2: 0, EficienciaR2: 0, RpmR3: null, EficienciaR3: null,
        ObsR1: null, ObsR2: 'Paro por hilo', ObsR3: null, StatusOB1: 0, StatusOB2: 1, StatusOB3: 0,
    });

    const sinStd = construirLinea({
        telar: 202, rpmStd: null, efStd: '', rpm: { 1: 0, 2: 0, 3: 0 }, eficiencia: { 1: 0, 2: 0, 3: 0 },
        marcado: { 1: false, 2: false, 3: false }, obs: { 1: '', 2: '', 3: '' },
    });
    assert.equal(sinStd.RpmStd, null);
    assert.equal(sinStd.EficienciaStd, null);
});

test('antes de finalizar: cuenta telares y campos vacíos o en cero', () => {
    assert.equal(esVacioOCero(null), true);
    assert.equal(esVacioOCero(' '), true);
    assert.equal(esVacioOCero('abc'), true);
    assert.equal(esVacioOCero('0'), true);
    assert.equal(esVacioOCero(-1), true);
    assert.equal(esVacioOCero('12'), false);

    const completa = { RpmR1: 1, EficienciaR1: 1, RpmR2: 1, EficienciaR2: 1, RpmR3: 1, EficienciaR3: 1 };
    assert.deepEqual(revisarLineas([completa]), { telares: 0, campos: 0 });
    assert.deepEqual(revisarLineas([completa, { ...completa, RpmR3: 0, EficienciaR3: null }, { RpmR1: 5 }]), { telares: 2, campos: 7 });
});

test('title de la observación recortado a 80 caracteres', () => {
    assert.equal(tituloObservacion('  '), '');
    assert.equal(tituloObservacion(' falla '), 'Obs: falla');
    assert.equal(tituloObservacion('x'.repeat(90)), `Obs: ${'x'.repeat(80)}…`);
});

test('consultar: folio por fecha y datos del modal de supervisor', () => {
    const filas = [{ folio: 'CE0001', fecha: '2026-09-23' }, { folio: 'CE0002', fecha: '2026-09-24' }, { folio: 'CE0003', fecha: '2026-09-24' }];
    assert.equal(folioParaFecha(filas, '2026-09-24'), 'CE0002');
    assert.equal(folioParaFecha(filas, '2026-09-25'), null);

    assert.deepEqual(datosEdicion({ folio: 'CE1', fecha: '2026-09-24', turno: '2', status: 'Finalizado', usuario: 'Ana', noEmpleado: '77' }), {
        fecha: '2026-09-24', turno: '2', empleado: '77', nombre: 'Ana', status: 'Finalizado',
    });
    assert.deepEqual(datosEdicion({}), { fecha: '', turno: '1', empleado: '', nombre: '', status: 'En Proceso' });
});
