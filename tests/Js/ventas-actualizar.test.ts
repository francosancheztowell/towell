// Botón "Actualizar datos" de Ventas: qué hace la página con la respuesta de /ventas/datos/actualizar.
import assert from 'node:assert/strict';
import test from 'node:test';

import { AVISO_AL_DIA, AVISO_CARGANDO, respuestaActualizar } from '../../resources/js/ventas/actualizar-logica.ts';

test('a new version reloads the page', () => {
    assert.deepEqual(respuestaActualizar({ estado: 'publicada', version: '200' }, '100'), { accion: 'recargar' });
});

test('a version published by someone else since the page loaded also reloads', () => {
    assert.deepEqual(respuestaActualizar({ estado: 'al_dia', version: '200' }, '100'), { accion: 'recargar' });
});

test('same version and up to date tells the user there is nothing new', () => {
    assert.deepEqual(respuestaActualizar({ estado: 'al_dia', version: '100' }, '100'), {
        accion: 'avisar',
        tipo: 'info',
        texto: AVISO_AL_DIA,
    });
});

test('a load still writing asks the user to try again shortly', () => {
    assert.deepEqual(respuestaActualizar({ estado: 'cargando', version: '100' }, '100'), {
        accion: 'avisar',
        tipo: 'warning',
        texto: AVISO_CARGANDO,
    });
});
