import test from 'node:test';
import assert from 'node:assert/strict';
import { urlFiltro, validarJulio } from '../../resources/js/modulos/urdido/catalogo-julios/logica.ts';
import { validarMaquina } from '../../resources/js/modulos/urdido/catalogo-maquinas/logica.ts';
import { validarUbicacion } from '../../resources/js/modulos/engomado/catalogo-ubicaciones/logica.ts';
import { destinoFormulario, validarNucleo } from '../../resources/js/modulos/engomado/catalogo-nucleos/logica.ts';

test('julio: No. Julio obligatorio, tara vacía = 0, tara no numérica = error', () => {
    assert.deepEqual(validarJulio('  ', '1', 'Urdido'), { ok: false, campo: 'NoJulio', mensaje: 'El No. Julio es requerido' });
    assert.deepEqual(validarJulio(' J01 ', '', 'Engomado'), { ok: true, datos: { NoJulio: 'J01', Tara: 0, Departamento: 'Engomado' } });
    assert.deepEqual(validarJulio('J01', '10.5', 'Urdido'), { ok: true, datos: { NoJulio: 'J01', Tara: 10.5, Departamento: 'Urdido' } });
    assert.equal(validarJulio('J01', 'abc', 'Urdido').ok, false);
});

test('urlFiltro omite vacíos y codifica', () => {
    assert.equal(urlFiltro('/urdido/catalogos-julios', { no_julio: '  ' }), '/urdido/catalogos-julios');
    assert.equal(urlFiltro('/x', { no_julio: ' 1 2 ', otro: '' }), '/x?no_julio=1+2');
    assert.equal(urlFiltro('/x', { a: 'á&b', b: 'c' }), '/x?a=%C3%A1%26b&b=c');
});

test('máquina: id obligatorio, opcionales vacíos a null', () => {
    assert.equal(validarMaquina('', 'a', 'b').ok, false);
    assert.deepEqual(validarMaquina(' MC 1 ', ' ', 'Urdido '), {
        ok: true,
        datos: { MaquinaId: 'MC 1', Nombre: null, Departamento: 'Urdido' },
    });
});

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
