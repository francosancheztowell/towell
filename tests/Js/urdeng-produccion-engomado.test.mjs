import test from 'node:test';
import assert from 'node:assert/strict';
import {
    calcularNeto,
    camposFaltantes,
    clavesRepetidas,
    etiquetaOficial,
    fechaCorta,
    filasAPropagar,
    horaActual,
    limitarBruto,
    mensajesDuplicados,
    metrosParaPropagar,
    oficialNumero,
    oficialesAGuardar,
    oficialesParaCelda,
    parsearOficiales,
    planPropagacion,
    redondear,
    renglonesFaltantes,
    sumaMetros,
    tooltipOficiales,
    turnosRepetidos,
    valorInicialSelector,
    valorParaCampo,
} from '../../resources/js/modulos/engomado/produccion/logica.ts';

test('neto = bruto − tara; vacíos cuentan como 0 y puede quedar negativo', () => {
    assert.equal(calcularNeto('150.5', '20.5'), 130);
    assert.equal(calcularNeto('', '20'), -20);
    assert.equal(calcularNeto('100', ''), 100);
    assert.equal(calcularNeto('abc', null), 0);
});

test('tope de Kg. Bruto: recorta al máximo con 2 decimales; sin máximo no toca', () => {
    assert.equal(limitarBruto('2500', 2000), '2000.00');
    assert.equal(limitarBruto('2000', 2000), '2000');
    assert.equal(limitarBruto('', 2000), '');
    assert.equal(limitarBruto('99999', null), '99999');
});

test('redondear y valor por columna', () => {
    assert.equal(redondear(' 7.1999998 '), '7.20');
    assert.equal(redondear(''), '');
    assert.equal(redondear('x'), 'x');
    assert.equal(valorParaCampo('Solidos', '7.199'), '7.20');
    assert.equal(valorParaCampo('Roturas', '3.9'), 3);
    assert.equal(valorParaCampo('Ubicacion', 'A-1'), 'A-1');
    assert.equal(valorParaCampo('Canoa1', '80'), 80);
    assert.equal(valorParaCampo('Canoa1', ''), null);
    assert.equal(valorParaCampo('Canoa1', null), null);
});

test('fecha corta, hora actual y valor inicial del selector de temperatura', () => {
    assert.equal(fechaCorta('2026-09-25'), '25/09');
    assert.equal(fechaCorta('25/09'), null);
    assert.equal(horaActual(new Date(2026, 0, 1, 7, 5)), '07:05');
    assert.equal(valorInicialSelector('temp_canoa1', '0'), '80');
    assert.equal(valorInicialSelector('temp_canoa2', ''), '80');
    assert.equal(valorInicialSelector('temp_canoa1', '75'), '75');
    assert.equal(valorInicialSelector('tambor', '0'), '0');
});

test('etiqueta y tooltip de oficiales (turno numérico o texto)', () => {
    assert.deepEqual(etiquetaOficial({ clave: '1001', nombre: 'Ana', turno: 2 }), { clave: '1001', nombreConTurno: 'Ana (T2)' });
    assert.deepEqual(etiquetaOficial({ clave: '1001', nombre: '', turno: '' }), { clave: '1001', nombreConTurno: '1001' });
    assert.deepEqual(etiquetaOficial({ clave: null, nombre: null, turno: null }), { clave: '-', nombreConTurno: '-' });
    const ofs = [
        { numero: 1, clave: '1', nombre: 'Ana', turno: 1, metros: 10 },
        { numero: 2, clave: '2', nombre: '', turno: '', metros: '' },
    ];
    assert.equal(tooltipOficiales(ofs), 'Ana (T1), 2 (T-)');
    assert.equal(tooltipOficiales([]), 'Sin oficiales');
});

test('JSON de la celda: parseo tolerante y oficial por número', () => {
    assert.deepEqual(parsearOficiales('no json'), []);
    assert.deepEqual(parsearOficiales('{"a":1}'), []);
    const ofs = parsearOficiales('[{"numero":2,"clave":"7","nombre":"Beto","metros":5,"turno":1}]');
    assert.equal(oficialNumero(ofs, 2).clave, '7');
    assert.deepEqual(oficialNumero(ofs, 1), { numero: 1, nombre: '', clave: '', metros: '', turno: '' });
});

test('duplicados: No. Operador y turno repetidos entre oficiales', () => {
    const captura = [
        { numero: 1, clave: '100', turno: '1' },
        { numero: 2, clave: '100', turno: '2' },
        { numero: 3, clave: '300', turno: '2' },
    ];
    assert.deepEqual([...clavesRepetidas(captura)], [['100', [1, 2]]]);
    assert.deepEqual([...turnosRepetidos(captura)], [['2', [2, 3]]]);
    const msgs = mensajesDuplicados(clavesRepetidas(captura), turnosRepetidos(captura));
    assert.equal(msgs.length, 2);
    assert.match(msgs[0], /No\. Operador 100 está repetido entre oficiales \(1, 2\)/);
    assert.match(msgs[1], /Turno 2 está repetido/);
    // Turno sin clave no cuenta; sin repetidos no hay mensajes.
    const ok = [
        { numero: 1, clave: '100', turno: '1' },
        { numero: 2, clave: '', turno: '1' },
    ];
    assert.deepEqual(mensajesDuplicados(clavesRepetidas(ok), turnosRepetidos(ok)), []);
});

test('oficiales a guardar: solo con clave o nombre; metros numérico', () => {
    const r = oficialesAGuardar([
        { numero: 1, clave: ' 100 ', nombre: 'Ana', turno: '1', metros: '12.5' },
        { numero: 2, clave: '', nombre: '', turno: '2', metros: '3' },
        { numero: 3, clave: '', nombre: 'Solo nombre', turno: '', metros: '' },
    ]);
    assert.deepEqual(r, [
        { numero_oficial: 1, cve_empl: '100', nom_empl: 'Ana', turno: '1', metros: 12.5 },
        { numero_oficial: 3, cve_empl: null, nom_empl: 'Solo nombre', turno: null, metros: null },
    ]);
    assert.equal(sumaMetros(r), 12.5);
    assert.deepEqual(oficialesParaCelda(r)[1], { numero: 3, nombre: 'Solo nombre', clave: '', metros: '', turno: '' });
});

test('propagación: con Oficial 2 pasa a ser el 1; sin él se copian todos', () => {
    const uno = { numero_oficial: 1, cve_empl: '1', nom_empl: 'A', turno: '1', metros: 5 };
    const dos = { numero_oficial: 2, cve_empl: '2', nom_empl: 'B', turno: '2', metros: 5 };
    assert.deepEqual(planPropagacion([uno, dos]), { reemplazarPrimero: true, oficiales: [{ ...dos, numero_oficial: 1 }] });
    assert.deepEqual(planPropagacion([uno]), { reemplazarPrimero: false, oficiales: [uno] });
});

test('propagación: filas siguientes hasta la primera con H. Inicio', () => {
    assert.deepEqual(filasAPropagar(['08:00', '', '', '10:00', ''], 0), [1, 2]);
    assert.deepEqual(filasAPropagar(['', '', ''], 2), []);
    assert.deepEqual(filasAPropagar(['', ''], -1), []);
});

const filaCompleta = {
    fecha: '2026-09-25',
    tieneOficial: true,
    turno: '1',
    hInicio: '08:00',
    hFin: '09:00',
    julio: '12',
    kgBruto: '500',
    tara: '100.0',
    kgNeto: '400.00',
    metros: '6000',
    solidos: '10.00',
    canoa1: '80',
    canoa2: '82',
    ubicacion: 'A1',
};

test('fila completa no tiene faltantes', () => {
    assert.deepEqual(camposFaltantes(filaCompleta, 2000), []);
});

test('fila: vacíos, canoas en 0, bruto sobre el máximo y neto negativo', () => {
    const faltan = camposFaltantes(
        { ...filaCompleta, tieneOficial: false, hFin: ' ', kgBruto: '2500', kgNeto: '-3', canoa1: '0', canoa2: '', ubicacion: '' },
        2000,
    );
    assert.deepEqual(faltan, [
        'Oficial',
        'H. Fin',
        'Kg. Bruto (excede el máximo permitido)',
        'Kg. Neto (no puede ser negativo)',
        'Temp Canoa 1',
        'Temp Canoa 2',
        'Ubicación',
    ]);
    assert.deepEqual(camposFaltantes({ ...filaCompleta, kgBruto: '' }, null), ['Kg. Bruto']);
    assert.deepEqual(camposFaltantes({ ...filaCompleta, kgBruto: '99999' }, null), []);
});

test('renglones del aviso de finalizar', () => {
    assert.deepEqual(renglonesFaltantes(true, [{ fila: 2, campos: ['Julio', 'Tara'] }]), [
        'Merma con Goma',
        'Merma sin Goma',
        'Fila 2: Julio, Tara',
    ]);
    assert.deepEqual(renglonesFaltantes(false, []), []);
});

test('propagación: metros de la fila destino, si no los del origen; sin metros no se toca', () => {
    assert.equal(metrosParaPropagar(6000, 1000), 6000);
    assert.equal(metrosParaPropagar('', '1000'), 1000);
    assert.equal(metrosParaPropagar(0, null), null);
    assert.equal(metrosParaPropagar(null, -5), null);
});
