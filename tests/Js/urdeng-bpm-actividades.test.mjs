import test from 'node:test';
import assert from 'node:assert/strict';
import { alternarSeleccion, valoresEdicion } from '../../resources/js/modulos/urdido/comun/actividades-bpm/logica.ts';

test('clic en la misma fila deselecciona; en otra, selecciona', () => {
    assert.equal(alternarSeleccion(null, '3'), '3');
    assert.equal(alternarSeleccion('3', '3'), null);
    assert.equal(alternarSeleccion('3', '4'), '4');
    assert.equal(alternarSeleccion('3', undefined), null);
});

test('valores del modal Editar: Máquina solo en Urdido', () => {
    const fila = { key: '1', orden: '2', actividad: 'Limpieza & fileta', maquina: 'KM' };
    assert.deepEqual(valoresEdicion(fila, true), { Orden: '2', Actividad: 'Limpieza & fileta', Maquina: 'KM' });
    assert.deepEqual(valoresEdicion(fila, false), { Orden: '2', Actividad: 'Limpieza & fileta' });
    assert.deepEqual(valoresEdicion({}, true), { Orden: '', Actividad: '', Maquina: '' });
});
