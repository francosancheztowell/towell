import test from 'node:test';
import assert from 'node:assert/strict';
import {
    nombreDeDisposicion,
    opcionAutocompletado,
    peticionCeldaProduccion,
    peticionesOficiales,
    validarOficiales,
} from '../../resources/js/urd-eng/logica.ts';

const fila = (cve = '', turno = '', metros = '', nombre = '') => ({ cve, nombre, metros, turno });

test('oficiales: filas vacías quedan como huecos numerados', () => {
    const r = validarOficiales([fila(), fila('  '), fila()]);
    assert.equal(r.ok, true);
    assert.deepEqual(r.oficiales.map((o) => [o.numero, o.cve, o.turno]), [[1, null, null], [2, null, null], [3, null, null]]);
});

test('oficiales: normaliza nombre, metros y turno', () => {
    const r = validarOficiales([fila(' 100 ', '2', '12.5', ' Ana '), fila('200', '1', '', ''), fila()]);
    assert.equal(r.ok, true);
    assert.deepEqual(r.oficiales[0], { numero: 1, cve: '100', nombre: 'Ana', metros: 12.5, turno: 2 });
    assert.deepEqual(r.oficiales[1], { numero: 2, cve: '200', nombre: null, metros: null, turno: 1 });
});

test('oficiales: mismos mensajes que el preConfirm de SweetAlert', () => {
    assert.deepEqual(validarOficiales([fila('100', '1'), fila('100', '2'), fila()]), { ok: false, error: 'El No. Empleado 100 está repetido.' });
    assert.deepEqual(validarOficiales([fila('100', ''), fila(), fila()]), { ok: false, error: 'Selecciona un turno válido (1-4) para el empleado 1.' });
    assert.deepEqual(validarOficiales([fila('100', '5'), fila(), fila()]), { ok: false, error: 'Selecciona un turno válido (1-4) para el empleado 1.' });
    assert.deepEqual(validarOficiales([fila('100', '3'), fila(), fila('200', '3')]), { ok: false, error: 'No puede haber dos oficiales en el turno 3.' });
});

test('peticiones: elimina los que se vaciaron, guarda los capturados, ignora huecos que ya lo eran', () => {
    const actuales = [
        { numero: 1, cve: '100', nombre: 'Ana', turno: 1, metros: 10 },
        { numero: 2, cve: '200', nombre: 'Beto', turno: 2, metros: 5 },
    ];
    const nuevos = [
        { numero: 1, cve: null, nombre: null, turno: null, metros: null },
        { numero: 2, cve: '200', nombre: 'Beto', turno: 3, metros: 7 },
        { numero: 3, cve: null, nombre: null, turno: null, metros: null },
    ];
    assert.deepEqual(peticionesOficiales(9, nuevos, actuales), [
        { tipo: 'eliminar', datos: { registro_id: 9, numero_oficial: 1 } },
        { tipo: 'guardar', datos: { registro_id: 9, numero_oficial: 2, cve_empl: '200', nom_empl: 'Beto', metros: 7, turno: 3 } },
    ]);
});

test('celda de producción: ruta y payload por campo', () => {
    assert.deepEqual(peticionCeldaProduccion('h_inicio', 3, '08:00'), { ruta: 'horas', datos: { registro_id: 3, campo: 'HoraInicial', valor: '08:00' } });
    assert.deepEqual(peticionCeldaProduccion('h_fin', 3, null), { ruta: 'horas', datos: { registro_id: 3, campo: 'HoraFinal', valor: null } });
    assert.deepEqual(peticionCeldaProduccion('fecha', 3, '2026-09-30'), { ruta: 'fecha', datos: { registro_id: 3, fecha: '2026-09-30' } });
    assert.deepEqual(peticionCeldaProduccion('solidos', 3, '1.5'), { ruta: 'campos', datos: { registro_id: 3, campo: 'Solidos', valor: 1.5 } });
    assert.deepEqual(peticionCeldaProduccion('ubicacion', 3, 'A1'), { ruta: 'campos', datos: { registro_id: 3, campo: 'Ubicacion', valor: 'A1' } });
    assert.deepEqual(peticionCeldaProduccion('roturas', 3, null), { ruta: 'campos', datos: { registro_id: 3, campo: 'Roturas', valor: null } });
    assert.equal(peticionCeldaProduccion('otro', 3, '1'), null);
});

test('Content-Disposition: nombre con y sin comillas', () => {
    assert.equal(nombreDeDisposicion('attachment; filename="ORDEN_ENGOMADO_E-1.pdf"'), 'ORDEN_ENGOMADO_E-1.pdf');
    assert.equal(nombreDeDisposicion('attachment; filename=ORDEN_ENGOMADO_E-1_SIMPLE.xlsx'), 'ORDEN_ENGOMADO_E-1_SIMPLE.xlsx');
    assert.equal(nombreDeDisposicion(undefined), '');
    assert.equal(nombreDeDisposicion('inline'), '');
});

test('autocompletado: bom muestra id y nombre; fórmula y lote su valor', () => {
    assert.deepEqual(opcionAutocompletado('bom', { BOMID: 'B-1', NAME: 'Hilo' }), { etiqueta: 'B-1 - Hilo', valor: 'B-1' });
    assert.deepEqual(opcionAutocompletado('bom-formula', { BomFormula: 'F-9' }), { etiqueta: 'F-9', valor: 'F-9' });
    assert.deepEqual(opcionAutocompletado('lote', { LoteProveedor: 'L-2' }), { etiqueta: 'L-2', valor: 'L-2' });
});
