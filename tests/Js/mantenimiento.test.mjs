import test from 'node:test';
import assert from 'node:assert/strict';
import {
    fechaCorta,
    MENSAJE_FALTAN_FECHAS,
    MENSAJE_ORDEN_FECHAS,
    relojLocal,
    urlConRango,
    validarRango,
} from '../../resources/js/modulos/mantenimiento/comun/fechas.ts';
import {
    departamentoDelArea,
    departamentoParaOrden,
    gruposDeMaquinas,
    opcionesDeFallas,
    ordenSugerida,
    sinEspacios,
} from '../../resources/js/modulos/mantenimiento/nuevo-paro/logica.ts';
import { acotarCalidad, payloadCierre, validarCierre } from '../../resources/js/modulos/mantenimiento/finalizar-paro/logica.ts';
import {
    departamentosCombo,
    esActivo,
    hayFiltro,
    parametrosCarga,
    pasaFiltros,
    statusTrasCarga,
    valoresUnicos,
} from '../../resources/js/modulos/mantenimiento/solicitudes/logica.ts';

test('fechaCorta toma el día del prefijo, sin correrse por la zona horaria', () => {
    assert.equal(fechaCorta('2026-09-29T00:00:00.000000Z'), '29/09/2026');
    assert.equal(fechaCorta('2026-01-05'), '05/01/2026');
    assert.equal(fechaCorta(null), '');
    assert.equal(fechaCorta('basura'), '');
});

test('relojLocal usa la hora local con ceros', () => {
    assert.deepEqual(relojLocal(new Date(2026, 0, 5, 7, 3)), { fecha: '2026-01-05', hora: '07:03' });
});

test('validarRango y urlConRango', () => {
    assert.equal(validarRango('', '2026-09-01'), MENSAJE_FALTAN_FECHAS);
    assert.equal(validarRango('2026-09-02', '2026-09-01'), MENSAJE_ORDEN_FECHAS);
    assert.equal(validarRango('2026-09-01', '2026-09-01'), null);
    assert.equal(urlConRango('/r', '2026-09-01', '2026-09-30'), '/r?fecha_ini=2026-09-01&fecha_fin=2026-09-30');
    assert.equal(urlConRango('/r?x=1', '2026-09-01', '2026-09-30'), '/r?x=1&fecha_ini=2026-09-01&fecha_fin=2026-09-30');
});

test('orden de trabajo: sin espacios y máximo 20', () => {
    assert.equal(sinEspacios(' 45 867 '), '45867');
    assert.equal(ordenSugerida('  ABC 123 '), 'ABC123');
    assert.equal(ordenSugerida('x'.repeat(25)).length, 20);
    assert.equal(ordenSugerida(undefined), '');
});

test('departamentoDelArea ignora mayúsculas y espacios', () => {
    assert.equal(departamentoDelArea(['Engomado', 'Urdido '], ' urdido'), 'Urdido ');
    assert.equal(departamentoDelArea(['Engomado'], 'Sistemas'), null);
    assert.equal(departamentoDelArea(['Engomado'], null), null);
});

test('Calidad busca la OT en el departamento de origen de la máquina', () => {
    assert.equal(departamentoParaOrden('Urdido', 'Engomado'), 'Urdido');
    assert.equal(departamentoParaOrden('Calidad', 'Engomado'), 'Engomado');
    assert.equal(departamentoParaOrden('calidad', 'Tejido'), 'calidad');
});

test('gruposDeMaquinas solo agrupa en Calidad', () => {
    const maquinas = [
        { MaquinaId: 'MC1', DepartamentoOrigen: 'Urdido' },
        { MaquinaId: '205', DepartamentoOrigen: 'Tejido' },
    ];
    assert.equal(gruposDeMaquinas('Urdido', maquinas), null);
    assert.deepEqual(gruposDeMaquinas('Calidad', maquinas)?.map((g) => g.grupo), ['Tejido', 'Urdido']);
    assert.equal(gruposDeMaquinas('Calidad', [{ MaquinaId: '1' }]), null);
});

test('opcionesDeFallas: ambos combos valen el Id y se saltan filas incompletas', () => {
    const r = opcionesDeFallas([
        { Id: 1, Falla: 'Rotura', Descripcion: 'Hilo roto' },
        { Id: 2, Falla: 'Freno', Descripcion: '' },
        { Id: '', Falla: 'Sin id' },
        { Id: 3, Falla: ' ' },
    ]);
    assert.deepEqual(r.fallas, [['1', 'Rotura'], ['2', 'Freno']]);
    assert.deepEqual(r.descripciones, [['1', 'Hilo roto']]);
});

test('cierre: valida atendió y calificación, y arma el payload', () => {
    assert.equal(acotarCalidad('7'), 5);
    assert.equal(acotarCalidad(null), 0);
    const base = { atendio: 'Mecánico', turno: '', calidad: 4, obsCierre: '' };
    assert.equal(validarCierre({ ...base, atendio: ' ' })?.campo, 'atendio');
    assert.equal(validarCierre({ ...base, calidad: 0 })?.campo, 'calidad');
    assert.equal(validarCierre(base), null);
    assert.deepEqual(payloadCierre({ ...base, turno: '2', obsCierre: 'ok' }), { atendio: 'Mecánico', turno: '2', calidad: 4, obs_cierre: 'ok' });
    assert.deepEqual(payloadCierre(base), { atendio: 'Mecánico', turno: null, calidad: 4, obs_cierre: null });
});

test('solicitudes: query de carga', () => {
    assert.equal(parametrosCarga('default', undefined, false), '');
    assert.equal(parametrosCarga('todos', '', true), '?alcance=todos&incluir_finalizados=1');
    assert.equal(parametrosCarga('depto', 'Urdido', false), '?depto=Urdido');
    assert.equal(parametrosCarga('depto', '', false), '');
});

test('solicitudes: combos y filtros', () => {
    const paros = [
        { Depto: 'Urdido', Estatus: 'Activo', MaquinaId: 'MC1', NomEmpl: 'Ana', CveEmpl: '1' },
        { Depto: 'Engomado ', Estatus: 'Terminado', MaquinaId: 'WP2', NomEmpl: 'Beto', CveEmpl: '2' },
    ];
    assert.deepEqual(valoresUnicos(paros, 'Estatus'), ['Activo', 'Terminado']);
    assert.deepEqual(departamentosCombo(['Calidad', 'Urdido'], paros), ['Calidad', 'Engomado', 'Urdido']);

    const sin = { depto: '', status: '', maquina: '', soloMias: false };
    const u = { nombre: 'Ana', numeroEmpleado: '9' };
    assert.equal(hayFiltro(sin), false);
    assert.equal(pasaFiltros(paros[1], { ...sin, depto: 'Engomado' }, u), true);
    assert.equal(pasaFiltros(paros[1], { ...sin, soloMias: true }, u), false);
    assert.equal(pasaFiltros(paros[1], { ...sin, soloMias: true }, { nombre: 'x', numeroEmpleado: '2' }), true);
    assert.equal(pasaFiltros(paros[0], { ...sin, status: 'Terminado' }, u), false);
});

test('solicitudes: status tras recargar', () => {
    assert.equal(statusTrasCarga(['Activo', 'Terminado'], 'Terminado', false, false), '');
    assert.equal(statusTrasCarga(['Activo', 'Terminado'], 'Terminado', true, true), 'Activo');
    assert.equal(statusTrasCarga(['Activo', 'Terminado'], 'Terminado', true, false), 'Terminado');
    assert.equal(statusTrasCarga(['Terminado'], '', true, false), '');
    assert.equal(esActivo(' ACTIVO '), true);
});
