import assert from 'node:assert/strict';
import test from 'node:test';

import { rangoATexto, textoARango, tipoDeColumna, valoresDeColumna } from '../../resources/js/programa-tejido/filtro-columna.ts';

const fila = (valor: string, pasaOtros = true, etiqueta = valor) => ({ valor, etiqueta, pasaOtros });

test('agrupa por valor con total y disponibles (conteo cruzado)', () => {
    const v = valoresDeColumna([fila('A12'), fila('A12', false), fila('H'), fila('H'), fila('T16', false)]);
    assert.deepEqual(v.map((x) => [x.valor, x.total, x.disponibles]), [['A12', 2, 1], ['H', 2, 2], ['T16', 1, 0]]);
});

test('los que ya no están disponibles con los otros filtros van al final', () => {
    const v = valoresDeColumna([fila('Z'), fila('A', false), fila('M')]);
    assert.deepEqual(v.map((x) => x.valor), ['M', 'Z', 'A']);
});

test('orden natural: 9 antes que 10 y sin distinguir acentos ni mayúsculas', () => {
    const v = valoresDeColumna([fila('10'), fila('9'), fila('100'), fila('árbol'), fila('Abeto')]);
    assert.deepEqual(v.map((x) => x.valor), ['9', '10', '100', 'Abeto', 'árbol']);
});

test('ignora vacíos y recorta espacios; la etiqueta formateada se conserva', () => {
    const v = valoresDeColumna([fila('  '), fila(' 75 ', true, '75 cm'), fila('75')]);
    assert.deepEqual(v, [{ valor: '75', etiqueta: '75 cm', total: 2, disponibles: 2 }]);
});

test('tipo de columna: fecha, número (con coma de miles) o texto', () => {
    assert.equal(tipoDeColumna(['2026-10-01 06:30:00', '2026-10-02', '']), 'fecha');
    assert.equal(tipoDeColumna(['36', '75.5', '12,500']), 'numero');
    assert.equal(tipoDeColumna(['36', 'JAC 201']), 'texto');
    assert.equal(tipoDeColumna(['', '  ']), 'texto');
});

test('rango ida y vuelta, y vacío = sin filtro', () => {
    assert.equal(rangoATexto({ min: ' 36 ', max: '' }), '36..');
    assert.equal(rangoATexto({ min: '', max: '' }), null);
    assert.deepEqual(textoARango('36..90'), { min: '36', max: '90' });
    assert.deepEqual(textoARango(undefined), { min: '', max: '' });
});
