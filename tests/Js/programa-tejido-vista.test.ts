import assert from 'node:assert/strict';
import test from 'node:test';

import { guardarVista, restaurarVista, tomarVista } from '../../resources/js/programa-tejido/vista.ts';

const almacen = () => {
    const m = new Map<string, string>();
    return { getItem: (k: string) => m.get(k) ?? null, setItem: (k: string, v: string) => void m.set(k, v), removeItem: (k: string) => void m.delete(k), m };
};
const scroll = (top = 0, left = 0) => ({ scrollTop: top, scrollLeft: left }) as unknown as Element;

test('guarda scroll vertical y horizontal, y la fila seleccionada para el restaurador existente', () => {
    const a = almacen();
    guardarVista(scroll(840, 1260), '9001', a);
    assert.equal(a.m.get('selectRegistroId'), '9001');
    assert.deepEqual(tomarVista(a), { top: 840, left: 1260 });
});

test('la vista se toma una sola vez (no reaparece en la siguiente carga)', () => {
    const a = almacen();
    guardarVista(scroll(10, 20), null, a);
    assert.ok(tomarVista(a));
    assert.equal(tomarVista(a), null);
    assert.equal(a.m.has('selectRegistroId'), false, 'sin selección no deja id');
});

test('con fila por centrar solo restaura el horizontal; sin ella, ambos', () => {
    const a = almacen();
    guardarVista(scroll(500, 300), null, a);
    const s1 = scroll();
    restaurarVista(s1, true, a);
    assert.deepEqual([s1.scrollTop, s1.scrollLeft], [0, 300]);

    guardarVista(scroll(500, 300), null, a);
    const s2 = scroll();
    restaurarVista(s2, false, a);
    assert.deepEqual([s2.scrollTop, s2.scrollLeft], [500, 300]);
});

test('datos corruptos o almacenamiento bloqueado no rompen la carga', () => {
    const a = almacen();
    a.m.set('pt:vista', '{roto');
    assert.equal(tomarVista(a), null);
    a.m.set('pt:vista', JSON.stringify({ top: 'x', left: 2 }));
    assert.equal(tomarVista(a), null);
    const bloqueado = { getItem: () => { throw new Error('bloqueado'); }, setItem: () => { throw new Error('bloqueado'); }, removeItem: () => {} };
    assert.doesNotThrow(() => guardarVista(scroll(1, 1), '1', bloqueado));
    assert.equal(tomarVista(bloqueado), null);
});
