/** Botón ocupado al guardar (componentes/envio-cargando.ts). */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import { decidirEnvio, liberar, marcarOcupado } from '../../resources/js/componentes/envio-cargando.ts';

const base = { conCarga: true, metodo: 'post', formmethodBoton: null, yaEnviando: false, prevenido: false };

test('un envío normal de un form con data-envio-cargando muestra la carga', () => {
    assert.equal(decidirEnvio(base), 'cargar');
});

test('sin data-envio-cargando no se toca nada', () => {
    assert.equal(decidirEnvio({ ...base, conCarga: false }), 'ignorar');
});

test('Cancelar (formmethod="dialog") y form method="dialog" no son un guardado', () => {
    assert.equal(decidirEnvio({ ...base, formmethodBoton: 'dialog' }), 'ignorar');
    assert.equal(decidirEnvio({ ...base, metodo: 'dialog' }), 'ignorar');
});

test('el segundo clic mientras se envía se bloquea (sin doble alta)', () => {
    assert.equal(decidirEnvio({ ...base, yaEnviando: true }), 'bloquear');
});

test('si la vista anuló el envío, el botón no se queda girando', () => {
    assert.equal(decidirEnvio({ ...base, prevenido: true }), 'ignorar');
});

// Botón de mentira: lo justo que usan marcarOcupado/liberar (nodos como strings).
function botonFalso(...nodos: string[]) {
    const attrs = new Map<string, string>();
    return {
        childNodes: nodos,
        disabled: true,
        set textContent(t: string) { this.childNodes = [t]; },
        replaceChildren(...n: string[]) { this.childNodes = n; },
        hasAttribute: (k: string) => attrs.has(k),
        setAttribute: (k: string, v: string) => void attrs.set(k, v),
        removeAttribute: (k: string) => void attrs.delete(k),
        attrs,
    };
}

test('marcarOcupado cambia el texto y liberar lo deja como estaba', () => {
    const b = botonFalso('<icono>', ' Guardar');
    marcarOcupado(b as unknown as HTMLElement, 'Guardando…');
    assert.deepEqual(b.childNodes, ['Guardando…']);
    assert.equal(b.attrs.get('aria-busy'), 'true');
    assert.ok(b.attrs.has('data-ocupado'));

    liberar(b as unknown as HTMLElement);
    assert.deepEqual(b.childNodes, ['<icono>', ' Guardar'], 'vuelven los nodos originales (icono incluido)');
    assert.equal(b.attrs.has('data-ocupado'), false);
    assert.equal(b.disabled, false);
});

test('texto null (diálogos de notify.form) deja el texto y solo pone el spinner', () => {
    const b = botonFalso('Aceptar');
    marcarOcupado(b as unknown as HTMLElement, null);
    assert.deepEqual(b.childNodes, ['Aceptar']);
    assert.ok(b.attrs.has('data-ocupado'));
});

test('Módulos: alta y edición usan el botón con carga', () => {
    const blade = readFileSync(new URL('../../resources/views/modulos/gestion-modulos/index.blade.php', import.meta.url), 'utf8');
    assert.equal(blade.match(/<form[^>]*data-envio-cargando/g)?.length, 2);
});
