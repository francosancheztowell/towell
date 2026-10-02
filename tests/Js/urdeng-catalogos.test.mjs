import test from 'node:test';
import assert from 'node:assert/strict';
import { validarUbicacion } from '../../resources/js/modulos/engomado/catalogo-ubicaciones/logica.ts';
import { destinoFormulario, validarNucleo } from '../../resources/js/modulos/engomado/catalogo-nucleos/logica.ts';

test('ubicación: mayúsculas, obligatoria y máximo 10', () => {
    assert.deepEqual(validarUbicacion(' a1 '), { ok: true, datos: { Codigo: 'A1' } });
    assert.equal(validarUbicacion('').ok, false);
    const largo = validarUbicacion('ABCDEFGHIJK');
    assert.equal(largo.ok, false);
    assert.match(largo.ok ? '' : largo.mensaje, /10 caracteres/);
});

test('núcleo: ambos obligatorios y nombre hasta 120', () => {
    assert.deepEqual(validarNucleo('SMIT', ' N1 '), { ok: true, datos: { Salon: 'SMIT', Nombre: 'N1' } });
    assert.equal(validarNucleo('', 'N1').ok, false);
    const sinNombre = validarNucleo('SMIT', '  ');
    assert.equal(sinNombre.ok ? '' : sinNombre.campo, 'Nombre');
    assert.equal(validarNucleo('SMIT', 'x'.repeat(121)).ok, false);
    assert.equal(validarNucleo('SMIT', 'x'.repeat(120)).ok, true);
});

test('núcleo: destino del formulario (POST store / PUT update con id codificado)', () => {
    const rutas = { guardar: '/urd-eng-nucleos', actualizar: '/urd-eng-nucleos/__ID__' };
    assert.deepEqual(destinoFormulario(rutas, null), { accion: '/urd-eng-nucleos', metodo: 'POST' });
    assert.deepEqual(destinoFormulario(rutas, '7'), { accion: '/urd-eng-nucleos/7', metodo: 'PUT' });
    assert.equal(destinoFormulario(rutas, 'a/b').accion, '/urd-eng-nucleos/a%2Fb');
});
