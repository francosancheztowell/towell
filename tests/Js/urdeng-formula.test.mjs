import test from 'node:test';
import assert from 'node:assert/strict';
import {
    aplicarMaxConsumoTotal,
    clasesStatusPrograma,
    componenteGuardado,
    componentesParaGuardar,
    esPrimerRegistro,
    filaPasaFiltros,
    filtroDeSeleccion,
    limiteConsumo,
    mensajeLimiteConsumo,
    num,
    okDesdeDato,
    okInicial,
    okSiguiente,
    opcionesFormula,
    ordenPorFecha,
    primerConsumoExcedido,
    queryFormulasDisponibles,
    redondear2,
    statusEsFinalizado,
    validarCaptura,
    valoresConConteo,
    VACIO,
} from '../../resources/js/modulos/engomado/captura-formula/logica.ts';

test('status finalizado: Finalizado y Terminado, sin importar mayúsculas ni espacios', () => {
    assert.equal(statusEsFinalizado(' finalizado '), true);
    assert.equal(statusEsFinalizado('Terminado'), true);
    assert.equal(statusEsFinalizado('En Proceso'), false);
    assert.equal(statusEsFinalizado(null), false);
});

test('num y redondear2', () => {
    assert.equal(num('3.5'), 3.5);
    assert.equal(num(''), 0);
    assert.equal(num(undefined), 0);
    assert.equal(redondear2(10.456), 10.46);
});

test('tope de Consumo Total: agua = Litros, AE-021 = 10, resto = 100', () => {
    assert.equal(limiteConsumo({ ItemId: 'X', ItemName: 'Agua suave' }, 250).max, 250);
    assert.equal(limiteConsumo({ ItemId: 'agua-1' }, -5).max, 0);
    assert.equal(limiteConsumo({ ItemId: ' ae-021 ' }, 250).max, 10);
    assert.equal(limiteConsumo({ ItemId: 'AE-014' }, 250).max, 100);
    assert.equal(aplicarMaxConsumoTotal({ ItemId: 'AE-021' }, 42, 200), 10);
    assert.equal(aplicarMaxConsumoTotal({ ItemId: 'AE-014' }, 42, 200), 42);
    assert.match(mensajeLimiteConsumo({ ItemId: 'AE-021' }, 200), /máximo 10: .*Revisa: AE-021$/);
});

test('componentes a guardar: con artículo y Consumo Total > 0', () => {
    const lista = [
        { ItemId: 'A', ConsumoTotal: 5 },
        { ItemId: ' ', ConsumoTotal: 5 },
        { ItemId: 'B', ConsumoTotal: 0 },
        { ItemId: 'C', ConsumoTotal: '2.5' },
    ];
    assert.deepEqual(componentesParaGuardar(lista).map((c) => c.ItemId), ['A', 'C']);
});

test('primer Consumo Total excedido ignora filas sin artículo', () => {
    const lista = [
        { ItemId: '', ConsumoTotal: 999 },
        { ItemId: 'AE-014', ConsumoTotal: 100 },
        { ItemId: 'AE-021', ConsumoTotal: 11 },
    ];
    assert.equal(primerConsumoExcedido(lista, 200)?.ItemId, 'AE-021');
    assert.equal(primerConsumoExcedido(lista.slice(0, 2), 200), undefined);
});

test('componente guardado: normaliza y aplica el tope con los Litros', () => {
    const c = componenteGuardado({ Id: 3, ItemId: 'AE-021', ConsumoTotal: '40', ConsumoUnitario: 0.2 }, 200);
    assert.deepEqual(c, {
        Id: 3, ItemId: 'AE-021', ItemName: '', ConfigId: '', ConsumoUnitario: 0.2,
        ConsumoTotal: 10, Unidad: '', Almacen: '', esNuevo: false,
    });
});

test('query de fórmulas disponibles: bomId y/o fórmula; nada → null', () => {
    assert.equal(queryFormulasDisponibles(' BOM-1 ', ''), 'bomId=BOM-1');
    assert.equal(queryFormulasDisponibles('BOM 1', 'F&1'), 'bomId=BOM+1&formula=F%261');
    assert.equal(queryFormulasDisponibles('', '  '), null);
});

test('opciones de fórmula: AX + la guardada al final si falta', () => {
    assert.deepEqual(opcionesFormula(['F1', 'F2'], 'F9'), ['F1', 'F2', 'F9']);
    assert.deepEqual(opcionesFormula(['F1', 'F2'], 'F2'), ['F1', 'F2']);
    assert.deepEqual(opcionesFormula(undefined, 'F9'), ['F9']);
    assert.deepEqual(opcionesFormula(undefined, ''), []);
});

test('primer registro del folio = Id menor', () => {
    assert.equal(esPrimerRegistro([7, 3, 9], 3), true);
    assert.equal(esPrimerRegistro([7, 3, 9], 7), false);
    assert.equal(esPrimerRegistro([0, NaN], 0), false);
    assert.equal(esPrimerRegistro([], 1), false);
});

test('validación de captura (mismos mensajes que la vista vieja)', () => {
    const ok = { kilos: 0, litros: 200, tiempo: 30, solidos: 10, viscocidad: 7 };
    assert.equal(validarCaptura(ok), null);
    assert.equal(validarCaptura({ ...ok, kilos: -1 }), 'Los Kilos no pueden ser negativos');
    assert.equal(validarCaptura({ ...ok, kilos: NaN }), 'Los Kilos no pueden ser negativos');
    assert.equal(validarCaptura({ ...ok, litros: 0 }), 'Los Litros deben ser mayor a cero');
    assert.equal(validarCaptura({ ...ok, litros: 1500.01 }), 'Los Litros no pueden ser mayor a 1500');
    assert.equal(validarCaptura({ ...ok, tiempo: NaN }), 'El Tiempo Cocinado debe ser mayor a cero');
    assert.equal(validarCaptura({ ...ok, solidos: 0 }), 'El % Sólidos debe ser mayor a cero');
    assert.equal(validarCaptura({ ...ok, viscocidad: -2 }), 'La Viscosidad debe ser mayor a cero');
});

test('filtro: valores únicos con conteo, vacío como (Vacío), orden natural', () => {
    assert.deepEqual(valoresConConteo(['10', '9', ' ', '9', '']), [[VACIO, 2], ['9', 2], ['10', 1]]);
});

test('filtro: una fila pasa si cumple todas las columnas filtradas', () => {
    const filtros = new Map([[1, new Set(['A'])], [2, new Set([VACIO])]]);
    assert.equal(filaPasaFiltros(['x', 'A', ''], filtros), true);
    assert.equal(filaPasaFiltros(['x', 'B', ''], filtros), false);
    assert.equal(filaPasaFiltros(['x', 'A'], filtros), false);
    assert.equal(filaPasaFiltros(['x'], new Map()), true);
});

test('filtro: todos o ninguno marcado quita el filtro', () => {
    assert.equal(filtroDeSeleccion([], 3), null);
    assert.equal(filtroDeSeleccion(['a', 'b', 'c'], 3), null);
    assert.deepEqual([...filtroDeSeleccion(['a'], 3)], ['a']);
});

test('orden por fecha: estable y con vacías al final en ambos sentidos', () => {
    const fechas = ['2026-01-02', '', '2026-01-01', '2026-01-02'];
    assert.deepEqual(ordenPorFecha(fechas, true), [2, 0, 3, 1]);
    assert.deepEqual(ordenPorFecha(fechas, false), [0, 3, 2, 1]);
});

test('calidad: dato → PUT, estado inicial y ciclo', () => {
    assert.equal(okDesdeDato('1'), 1);
    assert.equal(okDesdeDato('0'), 0);
    assert.equal(okDesdeDato(''), null);
    assert.equal(okInicial(''), '1');
    assert.equal(okInicial('0'), '0');
    assert.equal(okSiguiente('1'), '0');
    assert.equal(okSiguiente('0'), '1');
    assert.equal(clasesStatusPrograma('Finalizado'), 'bg-gray-200 text-gray-700');
    assert.equal(clasesStatusPrograma(''), 'bg-gray-100 text-gray-500');
    assert.equal(clasesStatusPrograma('En Proceso'), 'bg-green-100 text-green-700');
});
