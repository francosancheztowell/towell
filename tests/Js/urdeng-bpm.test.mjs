import test from 'node:test';
import assert from 'node:assert/strict';
import {
    alternar,
    camposAutollenado,
    estadoInicial,
    filaVisible,
    mensajeSinResultados,
} from '../../resources/js/modulos/urdido/comun/bpm/logica.ts';

const ctx = { esSupervisor: false, usuario: 'Ana Pérez' };
const fila = (status, nombreRecibe = 'Ana Pérez', turnoRecibe = '1') => ({ status, nombreRecibe, turnoRecibe });

test('estado inicial: el supervisor ve terminados; el resto, sus folios', () => {
    assert.deepEqual(estadoInicial(true), { terminados: true, misFolios: false, todos: false, turno: '' });
    assert.deepEqual(estadoInicial(false), { terminados: false, misFolios: true, todos: false, turno: '' });
});

test('alternar: "todos" apaga terminados y mis folios; los otros apagan "todos"', () => {
    const todos = alternar(estadoInicial(false), 'todos');
    assert.deepEqual(todos, { terminados: false, misFolios: false, todos: true, turno: '' });
    assert.equal(alternar(todos, 'terminados').todos, false);
    assert.equal(alternar(todos, 'misFolios').todos, false);
    // Apagar un filtro no toca "todos".
    assert.equal(alternar({ terminados: true, misFolios: false, todos: false, turno: '' }, 'terminados').todos, false);
    assert.equal(alternar(todos, 'todos').todos, false);
});

test('terminados ocultos salvo con "Finalizados" o "Todos"', () => {
    const e = estadoInicial(false);
    assert.equal(filaVisible(fila('Terminado'), e, ctx), false);
    assert.equal(filaVisible(fila('Terminado'), { ...e, terminados: true }, ctx), true);
    assert.equal(filaVisible(fila('Terminado'), alternar(e, 'todos'), ctx), true);
    assert.equal(filaVisible(fila('Creado'), e, ctx), true);
});

test('supervisor: autorizados ocultos salvo con "Todos"', () => {
    const sup = { esSupervisor: true, usuario: 'Sup' };
    assert.equal(filaVisible(fila('Autorizado', 'x'), estadoInicial(true), sup), false);
    assert.equal(filaVisible(fila('Autorizado', 'x'), alternar(estadoInicial(true), 'todos'), sup), true);
    // Para no supervisores los autorizados no se esconden por status.
    assert.equal(filaVisible(fila('Autorizado'), estadoInicial(false), ctx), true);
});

test('mis folios compara el nombre sin mayúsculas ni espacios; sin usuario no filtra', () => {
    const e = estadoInicial(false);
    assert.equal(filaVisible(fila('Creado', '  ana pérez '), e, ctx), true);
    assert.equal(filaVisible(fila('Creado', 'Beto'), e, ctx), false);
    assert.equal(filaVisible(fila('Creado', 'Beto'), e, { ...ctx, usuario: '' }), true);
});

test('turno exacto', () => {
    const e = { ...alternar(estadoInicial(false), 'todos'), turno: '2' };
    assert.equal(filaVisible(fila('Creado', 'x', '2'), e, ctx), true);
    assert.equal(filaVisible(fila('Creado', 'x', '1'), e, ctx), false);
});

test('mensaje sin resultados', () => {
    assert.equal(mensajeSinResultados(estadoInicial(false)), 'No tienes folios asignados');
    assert.equal(mensajeSinResultados(estadoInicial(true)), 'Sin resultados con los filtros aplicados');
});

test('autollenado: data-llenar-<dato> del select → data-<dato> de la opción', () => {
    assert.deepEqual(
        camposAutollenado(
            { bpmAutollenar: '', llenarNumero: 'input_CveEmplEnt', llenarTurno: 'input_TurnoEntrega' },
            { numero: '1234', turno: undefined },
        ),
        [['input_CveEmplEnt', '1234'], ['input_TurnoEntrega', '']],
    );
    assert.deepEqual(camposAutollenado({ llenarDepartamento: 'input_Departamento' }, {}), [['input_Departamento', '']]);
    assert.deepEqual(camposAutollenado({ llenar: 'x', llenarVacio: '' }, {}), []);
});
