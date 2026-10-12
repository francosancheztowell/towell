/**
 * Lógica pura del arrastre de prioridad (dragdrop-logica.ts). El contrato del servidor
 * (DragAndDropTejido::mover) está en tests/Feature/Planeacion/Rigor/ProgramaTejidoMoverContratoPosicionTest.php:
 * new_position = índice base 0 en el telar SIN la fila arrastrada, no el valor de Posicion.
 */
import assert from 'node:assert/strict';
import test from 'node:test';

import { indiceDestino, indiceMinimo } from '../../resources/js/programa-tejido/dragdrop-logica.ts';

/** Simula el mover del servidor: saca `id` y lo reinserta en `indice`. */
function mover(ids: number[], id: number, indice: number): number[] {
    const resto = ids.filter((x) => x !== id);
    resto.splice(indice, 0, id);
    return resto;
}

// Telar con Posicion 1, 2, 5, 6 (huecos). El DOM los muestra en ese orden.
const telar = [10, 11, 12, 13];

test('origen antes del destino, soltar ANTES: [11, 10, 12, 13] aunque Posicion tenga huecos', () => {
    // Antes del fix: (5-1)-1 = 3 y el servidor lo dejaba al final.
    const i = indiceDestino(telar, 10, 12, true);
    assert.equal(i, 1);
    assert.deepEqual(mover(telar, 10, i), [11, 10, 12, 13]);
});

test('origen antes del destino, soltar DESPUÉS', () => {
    const i = indiceDestino(telar, 10, 12, false);
    assert.equal(i, 2);
    assert.deepEqual(mover(telar, 10, i), [11, 12, 10, 13]);
});

test('origen antes del destino, soltar antes del último (Posicion 6) ya no sale de rango', () => {
    // Antes del fix: 6-1-1 = 4, fuera de rango en un telar de 4 → 422.
    const i = indiceDestino(telar, 10, 13, true);
    assert.equal(i, 2);
    assert.deepEqual(mover(telar, 10, i), [11, 12, 10, 13]);
});

test('origen después del destino, soltar ANTES y DESPUÉS', () => {
    assert.deepEqual(mover(telar, 13, indiceDestino(telar, 13, 12, true)), [10, 11, 13, 12]);
    assert.deepEqual(mover(telar, 13, indiceDestino(telar, 13, 11, false)), [10, 11, 13, 12]);
    assert.deepEqual(mover(telar, 13, indiceDestino(telar, 13, 10, true)), [13, 10, 11, 12]);
});

test('soltar junto a sí mismo da su índice actual (el cliente lo rechaza sin ir al servidor)', () => {
    assert.equal(indiceDestino(telar, 12, 11, false), 2);
    assert.equal(indiceDestino(telar, 12, 13, true), 2);
    assert.equal(telar.indexOf(12), 2);
});

test('la lista puede venir sin la fila arrastrada (cache del telar) y da lo mismo', () => {
    const sinArrastrada = [11, 12, 13];
    for (const [destino, antes] of [[11, true], [11, false], [12, true], [13, false]] as const) {
        assert.equal(indiceDestino(sinArrastrada, 10, destino, antes), indiceDestino(telar, 10, destino, antes));
    }
});

test('otro telar: la arrastrada no está en la lista, índice directo', () => {
    const otro = [20, 21];
    assert.equal(indiceDestino(otro, 10, 20, true), 0);
    assert.equal(indiceDestino(otro, 10, 20, false), 1);
    assert.equal(indiceDestino(otro, 10, 21, false), 2);
});

test('clamp: destino desconocido o nulo cae al final; nunca negativo ni mayor que el total', () => {
    assert.equal(indiceDestino(telar, 10, null, true), 3);
    assert.equal(indiceDestino(telar, 10, 99, true), 3);
    assert.equal(indiceDestino([20, 21], null, null, false), 2);
    assert.equal(indiceDestino([], 10, null, false), 0);
    for (const d of telar) {
        for (const antes of [true, false]) {
            const i = indiceDestino(telar, 10, d, antes);
            assert.ok(i >= 0 && i <= 3, `fuera de rango con destino ${d}`);
        }
    }
});

test('antes de un EnProceso queda bloqueado; justo después se permite', () => {
    // Telar: 1 (EnProceso), 2, 3. Se arrastra 3.
    const filas = [1, 2, 3];
    const min = indiceMinimo(0);
    assert.equal(min, 1);
    assert.ok(indiceDestino(filas, 3, 1, true) < min);
    assert.ok(indiceDestino(filas, 3, 1, false) >= min);
    assert.equal(indiceMinimo(-1), 0);
});
