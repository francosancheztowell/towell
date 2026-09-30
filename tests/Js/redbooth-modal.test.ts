/** Lógica pura del modal de Redbooth (logica.ts): fechas, avatar, estado, mensajes. */
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { fmtDate, iniciales, mensajeRespuesta, safeAvatarUrl, textoEstado } from '../../resources/js/modulos/redbooth/logica.ts';

test('fmtDate: vacío, día local, epoch en segundos y texto no fecha', () => {
    assert.equal(fmtDate(null), '—');
    assert.equal(fmtDate('2026-03-05'), new Date(2026, 2, 5).toLocaleDateString('es-MX', { dateStyle: 'medium' }));
    assert.equal(fmtDate(1767225600), new Date(1767225600 * 1000).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' }));
    assert.equal(fmtDate('mañana'), 'mañana');
});

test('safeAvatarUrl solo acepta https', () => {
    assert.equal(safeAvatarUrl({ avatar_url: 'https://cdn/x.png' }), 'https://cdn/x.png');
    assert.equal(safeAvatarUrl({ avatar_url: 'http://cdn/x.png' }), '');
    assert.equal(safeAvatarUrl({ avatar_url: 'javascript:alert(1)' }), '');
    assert.equal(safeAvatarUrl(null), '');
});

test('iniciales y estado', () => {
    assert.equal(iniciales('ana maría lópez'), 'AM');
    assert.equal(iniciales(''), 'RB');
    assert.equal(textoEstado('OPEN'), 'Abierto');
    assert.equal(textoEstado('resolved'), 'Finalizado');
    assert.equal(textoEstado('paused'), 'paused');
    assert.equal(textoEstado(''), 'Sin estado');
});

test('mensajeRespuesta: primer error de validación, luego message, luego genérico', () => {
    assert.equal(mensajeRespuesta({ errors: { a: ['Falta a'] }, message: 'x' }, 'g'), 'Falta a');
    assert.equal(mensajeRespuesta({ message: 'Sin permiso' }, 'g'), 'Sin permiso');
    assert.equal(mensajeRespuesta(null, 'g'), 'g');
});
