import test from 'node:test';
import assert from 'node:assert/strict';
import {
    CAMPO_ROTURA,
    NETO_EXCEDE,
    NETO_NEGATIVO,
    SELECTOR_CAMPO,
    aOficialesFila,
    brutoExcede,
    camposFaltantes,
    fechaCorta,
    horaActual,
    juliosOcupados,
    leerOficiales,
    metrosDe,
    modoPropagacion,
    motivoRechazo,
    netoDe,
    netoFueraDeRango,
    oficialEn,
    oficialesSinMetros,
    opcionesJulio,
    peso,
    pesoNum,
    repetidos,
    resumenOficiales,
    sumaMetros,
    tieneOficial,
} from '../../resources/js/modulos/urdido/produccion/logica.ts';

test('peso: 2 decimales y vacío para lo que no es número', () => {
    assert.equal(peso('287.39999'), '287.40');
    assert.equal(peso(50), '50.00');
    assert.equal(peso(''), '');
    assert.equal(peso(null), '');
    assert.equal(pesoNum('abc'), 0);
    assert.equal(pesoNum('10.005'), 10.01);
});

test('neto = bruto − tara redondeado; vacíos cuentan como 0', () => {
    assert.equal(netoDe('287.4', '50.1'), 237.3);
    assert.equal(netoDe('', '50'), -50);
    assert.equal(netoDe('100', null), 100);
});

test('neto fuera de rango: negativo o sobre el tope de 700 kg', () => {
    assert.equal(netoFueraDeRango(-0.01, 700), true);
    assert.equal(netoFueraDeRango(700, 700), false);
    assert.equal(netoFueraDeRango(700.01, 700), true);
    assert.equal(netoFueraDeRango(5000, null), false);
});

test('bruto excede: bruto > tope + tara, solo si hay tope y es número', () => {
    assert.equal(brutoExcede('750', 50, 700), false);
    assert.equal(brutoExcede('750.01', 50, 700), true);
    assert.equal(brutoExcede('7500', 50, null), false);
    assert.equal(brutoExcede('', 50, 700), false);
});

test('fecha corta y hora actual', () => {
    assert.equal(fechaCorta('2026-09-25'), '25/09');
    assert.equal(fechaCorta('25/09'), null);
    assert.equal(horaActual(new Date(2026, 0, 1, 6, 5)), '06:05');
});

test('roturas: display → columna', () => {
    assert.deepEqual({ ...CAMPO_ROTURA }, { hilat: 'Hilatura', maq: 'Maquina', operac: 'Operac', transf: 'Transf' });
});

test('tieneOficial: vacío y "Sin oficiales" no cuentan', () => {
    assert.equal(tieneOficial('  Sin oficiales '), false);
    assert.equal(tieneOficial(''), false);
    assert.equal(tieneOficial('1001 Ana (T2)'), true);
});

const filaCompleta = {
    fecha: '2026-09-25', oficial: '1001', hInicio: '06:00', hFin: '07:00', noJulio: '12',
    kgBruto: '300', tara: '50', kgNeto: '250', metros: '12000', vueltas: '', diametro: '',
};

test('campos faltantes: fila completa no tiene faltantes (sin Karl Mayer)', () => {
    assert.deepEqual(camposFaltantes(filaCompleta, false, 700), []);
});

test('campos faltantes: mismos textos y orden que la vista anterior', () => {
    const vacia = { fecha: '', oficial: 'Sin oficiales', hInicio: '', hFin: '', noJulio: '', kgBruto: ' ', tara: '', kgNeto: '', metros: '' };
    assert.deepEqual(camposFaltantes(vacia, true, 700), [
        'Fecha', 'Oficial', 'H. Inicio', 'H. Fin', 'No. Julio', 'Kg. Bruto', 'Tara', 'Metros', 'Vueltas', 'Diámetro',
    ]);
    for (const campo of camposFaltantes(vacia, true, 700)) assert.ok(SELECTOR_CAMPO[campo], campo);
});

test('campos faltantes: neto negativo o excedido', () => {
    assert.deepEqual(camposFaltantes({ ...filaCompleta, kgNeto: '-1' }, false, 700), [NETO_NEGATIVO]);
    assert.deepEqual(camposFaltantes({ ...filaCompleta, kgNeto: '701' }, false, 700), [NETO_EXCEDE]);
    assert.deepEqual(camposFaltantes({ ...filaCompleta, kgNeto: '701' }, false, null), []);
});

test('julios: los elegidos en otras filas no se ofrecen; el propio sí', () => {
    const valores = ['5', '', '7', '5'];
    assert.deepEqual([...juliosOcupados(valores, 0)].sort(), ['5', '7']);
    assert.deepEqual([...juliosOcupados(valores, 1)].sort(), ['5', '7']);
    assert.deepEqual([...juliosOcupados(['5', '7'], 0)], ['7']);

    const catalogo = [{ julio: 5, tara: 48.5 }, { julio: '7', tara: null }, { julio: 9, tara: '51.2' }];
    assert.deepEqual(opcionesJulio(catalogo, juliosOcupados(['5', '7', ''], 0)), [
        { valor: '5', tara: '48.5' },
        { valor: '9', tara: '51.2' },
    ]);
    assert.deepEqual(opcionesJulio(catalogo, new Set())[1], { valor: '7', tara: '0' });
});

test('julios: ninguna fila termina con un julio de otra (simulación de cambio)', () => {
    const catalogo = [1, 2, 3, 4].map((julio) => ({ julio, tara: 10 }));
    const valores = ['1', '2', ''];
    valores[2] = '3';
    valores.forEach((v, i) => {
        const opciones = opcionesJulio(catalogo, juliosOcupados(valores, i)).map((o) => o.valor);
        if (v) assert.ok(opciones.includes(v));
        valores.forEach((otro, j) => {
            if (j !== i && otro) assert.ok(!opciones.includes(otro), `fila ${i} ofrece ${otro}`);
        });
    });
});

test('oficiales: leer json de la fila y posición vacía por defecto', () => {
    assert.deepEqual(leerOficiales('no json'), []);
    assert.deepEqual(leerOficiales('{"a":1}'), []);
    const of = leerOficiales('[{"numero":1,"nombre":"Ana","clave":"1001","metros":6000,"turno":2}]');
    assert.equal(oficialEn(of, 1).nombre, 'Ana');
    assert.deepEqual(oficialEn(of, 2), { numero: 2, nombre: '', clave: '', metros: '', turno: '' });
    assert.equal(metrosDe(of, 1), 6000);
    assert.equal(metrosDe(of, 2), null);
});

test('oficiales: texto de la celda, suma de metros y forma json', () => {
    const payload = [
        { numero_oficial: 1, cve_empl: '1001', nom_empl: 'Ana', turno: '2', metros: 4000 },
        { numero_oficial: 2, cve_empl: '1002', nom_empl: '', turno: null, metros: 2000.5 },
    ];
    const filas = aOficialesFila(payload);
    assert.deepEqual(filas[1], { numero: 2, nombre: null, clave: '1002', metros: 2000.5, turno: null });
    assert.deepEqual(resumenOficiales(filas), { codigos: '1001, 1002', nombres: [{ nombre: 'Ana', turno: '2' }] });
    assert.deepEqual(resumenOficiales([{ numero: 1, nombre: 'Beto', clave: null, metros: null, turno: null }]), {
        codigos: '-',
        nombres: [{ nombre: 'Beto', turno: '-' }],
    });
    assert.equal(sumaMetros(payload), 6000.5);
    assert.equal(sumaMetros([]), '');
});

test('oficiales: repetidos por clave/turno y sin metros', () => {
    const rep = repetidos([{ numero: 1, valor: 'A' }, { numero: 2, valor: '' }, { numero: 3, valor: 'A' }]);
    assert.deepEqual([...rep.entries()], [['A', [1, 3]]]);
    assert.equal(repetidos([{ numero: 1, valor: '' }, { numero: 2, valor: '' }]).size, 0);
    assert.deepEqual(
        oficialesSinMetros([
            { numero_oficial: 1, cve_empl: 'x', nom_empl: null, turno: null, metros: 0 },
            { numero_oficial: 2, cve_empl: 'y', nom_empl: null, turno: null, metros: null },
            { numero_oficial: 3, cve_empl: 'z', nom_empl: null, turno: null, metros: 1 },
        ]),
        [1, 2],
    );
});

test('propagación: con oficial 2 pasa a ser el 1; si no, se copian todos', () => {
    const uno = { numero_oficial: 1, cve_empl: '1', nom_empl: 'A', turno: '1', metros: 5 };
    const dos = { numero_oficial: 2, cve_empl: '2', nom_empl: 'B', turno: '2', metros: 5 };
    assert.deepEqual(modoPropagacion([uno, dos]), { modo: 'segundo', segundo: dos });
    assert.deepEqual(modoPropagacion([uno]), { modo: 'todos' });
});

test('motivo de rechazo: errores 422 aplanados, luego error/message', () => {
    assert.equal(motivoRechazo({ errors: { metros: ['Los Metros son obligatorios.'], turno: 'x' } }), 'Los Metros son obligatorios. x');
    assert.equal(motivoRechazo({ error: 'Registro no encontrado' }), 'Registro no encontrado');
    assert.equal(motivoRechazo(null), 'Error desconocido');
});
