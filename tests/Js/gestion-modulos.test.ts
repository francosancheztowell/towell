/** Gestión de módulos: orden sugerido y filtros (modulos/configuracion/gestion-modulos/logica.ts). */
import assert from 'node:assert/strict';
import test from 'node:test';

import {
    ancestros,
    calcularOrden,
    contarPermisos,
    filtrarUsuarios,
    type ModuloDato,
    type UsuarioPermisos,
} from '../../resources/js/modulos/configuracion/gestion-modulos/logica.ts';

const m = (orden: string, nivel: string, dependencia = '', modulo = orden): ModuloDato => ({ key: orden, orden, modulo, nivel, dependencia });
const modulos = [
    m('100', '1', '', 'Planeación'), m('101', '2', '100'), m('104', '2', '100', 'Catálogos'),
    m('104-2', '3', '104', 'Eficiencias'), m('104-10', '3', '104'), m('1100', '1'),
];

test('orden: nivel 1 = siguiente centena, nivel 2 = tras el último hermano, nivel 3 = padre-N', () => {
    assert.equal(calcularOrden(modulos, '1', ''), '1200');
    assert.equal(calcularOrden(modulos, '2', '100'), '105');
    assert.equal(calcularOrden(modulos, '2', '1100'), '1101');
    // 104-10 es el mayor aunque como texto quede antes que 104-2.
    assert.equal(calcularOrden(modulos, '3', '104'), '104-11');
    assert.equal(calcularOrden(modulos, '2', ''), '');
});

test('ancestros arma la ruta del módulo hasta el principal', () => {
    assert.deepEqual(ancestros(modulos, modulos[3]), ['Planeación', 'Catálogos']);
    assert.deepEqual(ancestros(modulos, modulos[0]), []);
});

const u = (id: number, nombre: string, area: string, acceso: boolean): UsuarioPermisos =>
    ({ id, numero: String(id * 100), nombre, area, puesto: '', acceso, crear: false, modificar: false, eliminar: false, registrar: false });
const usuarios = [u(1, 'José Núñez', 'Tejido', true), u(2, 'Ana Ruiz', 'Planeación', false), u(3, 'Luis Paz', 'Tejido', false)];

test('filtros: texto sin acentos, área y con/sin acceso se combinan', () => {
    assert.deepEqual(filtrarUsuarios(usuarios, { texto: 'nunez', area: '', acceso: 'todos' }).map((x) => x.id), [1]);
    assert.deepEqual(filtrarUsuarios(usuarios, { texto: '300', area: '', acceso: 'todos' }).map((x) => x.id), [3]);
    assert.deepEqual(filtrarUsuarios(usuarios, { texto: '', area: 'Tejido', acceso: 'sin' }).map((x) => x.id), [3]);
    assert.equal(contarPermisos(usuarios).acceso, 1);
});
