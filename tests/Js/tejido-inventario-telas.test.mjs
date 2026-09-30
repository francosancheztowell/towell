import test from 'node:test';
import assert from 'node:assert/strict';
import { AIRE_SUPERIOR, destinoScroll, telarDesdeUrl, urlParaTelar } from '../../resources/js/modulos/tejido/inventario-telas/logica.ts';

const base = 'http://x.test/tejido/inventario-telas/jacquard';

test('telarDesdeUrl: ?telar= gana sobre el hash; sin nada es null', () => {
    assert.equal(telarDesdeUrl(`${base}?telar=205`), '205');
    assert.equal(telarDesdeUrl(`${base}?telar=205#telar-300`), '205');
    assert.equal(telarDesdeUrl(`${base}#telar-300`), '300');
    assert.equal(telarDesdeUrl(`${base}#otra`), null);
    assert.equal(telarDesdeUrl(`${base}#telar-`), null);
    assert.equal(telarDesdeUrl(base), null);
});

test('urlParaTelar: pone query y hash; vacío los quita y conserva otros parámetros', () => {
    assert.equal(urlParaTelar(`${base}?x=1`, '205'), `${base}?x=1&telar=205#telar-205`);
    assert.equal(urlParaTelar(`${base}?x=1&telar=205#telar-205`, ''), `${base}?x=1`);
    assert.equal(urlParaTelar(`${base}?telar=1#telar-1`, '7'), `${base}?telar=7#telar-7`);
});

test('destinoScroll: descuenta navbar fijo y aire, nunca negativo', () => {
    assert.equal(AIRE_SUPERIOR, 50);
    assert.equal(destinoScroll(600, 0, 100, 64), 600 + 100 - 64 - 50);
    assert.equal(destinoScroll(600, 100, 0, 0, 0), 500);
    assert.equal(destinoScroll(10, 0, 0, 64), 0);
});
