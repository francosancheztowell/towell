/**
 * HANDOFF PT-05 B4: el modal de Redbooth lee sus rutas de data-redbooth-boot (JSON). Si falta el
 * JSON o una ruta, no se inicia (antes: sin el markup del modal, el <script> tampoco hacía nada).
 */
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { leerBoot } from '../../resources/js/modulos/redbooth/logica.ts';

const rutas = { show: '/s/__ID__', destroy: '/d/__ID__', descargaArchivo: '/f/__FILE_ID__', proyectos: '/p', store: '/st' };

test('lee contexto y rutas', () => {
    assert.deepEqual(leerBoot(JSON.stringify({ contexto: 'catcodificados', rutas })), { contexto: 'catcodificados', rutas });
    assert.equal(leerBoot(JSON.stringify({ rutas }))?.contexto, 'programa', 'contexto desconocido o ausente = programa');
    assert.equal(leerBoot(JSON.stringify({ contexto: 'otro', rutas }))?.contexto, 'programa');
});

test('sin JSON válido o con rutas incompletas no se inicia', () => {
    for (const texto of [null, undefined, '', '{', '"x"', '{}', JSON.stringify({ rutas: { ...rutas, store: '' } }), JSON.stringify({ rutas: { ...rutas, show: 1 } })]) {
        assert.equal(leerBoot(texto), null, String(texto));
    }
});
