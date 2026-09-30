import test from 'node:test';
import assert from 'node:assert/strict';
import {
    agregarAMultiple,
    aplicarLiberado,
    aplicarReservaLocal,
    asRows,
    candidatasLote,
    elegirPieza,
    enMultiple,
    esMismoTelar,
    estadoBotones,
    estadoDe,
    etiquetaTipo,
    etiquetasOrdenes,
    fechaDataset,
    filtrarInventarioPorTelar,
    filtrarLocal,
    filtrosActivos,
    fmt,
    loteDeSeleccion,
    matchCuenta,
    matchLote,
    motivoRechazoMultiple,
    normalizeTipo,
    ordenarFilas,
    payloadEdicion,
    payloadReserva,
    quitarDeMultiple,
    rowJulios,
    rowOrdenes,
    salonCorto,
    salonesDe,
    siguienteOrden,
    telarDesdeDataset,
    telaresParaProgramar,
    validarReserva,
    alternarEstado,
    alternarSalon,
} from '../../resources/js/modulos/programa-urd-eng/reservar-programar/logica.ts';
import { urlConTelares } from '../../resources/js/modulos/programa-urd-eng/comun/contrato-flujo.ts';

/** Telar seleccionado con valores por defecto (rizo, libre). */
const telar = (extra = {}) => ({
    id: '10',
    no_telar: '201',
    tipo: 'Rizo',
    cuenta: '3156',
    salon: 'Jacquard',
    calibre: '12',
    hilo: 'H1',
    no_julio: '',
    julios: [],
    ordenes: [],
    max_julios: 1,
    no_orden: '',
    fecha: '2026-09-30',
    turno: '1',
    tipo_atado: 'Normal',
    reservado: false,
    programado: false,
    is_reservado: false,
    is_programado: false,
    ...extra,
});

const pieza = (extra = {}) => ({
    itemId: 'IT',
    configId: 'CF',
    inventSizeId: '3156-A',
    inventColorId: 'C',
    inventLocationId: 'L',
    inventBatchId: '00061',
    wmsLocationId: 'W1',
    inventSerialId: '00061-744',
    metros: 1500,
    numJulio: '00061-744',
    tipo: 'Rizo',
    data: { ItemId: 'IT', Tipo: 'Rizo', InventBatchId: '00061', InventSerialId: '00061-744', WMSLocationId: 'W1', Metros: 1500, InventQty: 80, ProdDate: '2026-09-01' },
    ...extra,
});

/* ---------- normalización y estado ---------- */

test('normalizeTipo y etiquetaTipo (Karl Mayer lleva "Barra")', () => {
    assert.equal(normalizeTipo(' rizo '), 'Rizo');
    assert.equal(normalizeTipo('PIE'), 'Pie');
    assert.equal(normalizeTipo('3'), '3');
    assert.equal(normalizeTipo(''), '-');
    assert.equal(etiquetaTipo({ tipo: '2', max_julios: 4 }), 'Barra 2');
    assert.equal(etiquetaTipo({ tipo: 'Rizo', max_julios: 1 }), 'Rizo');
    assert.equal(etiquetaTipo({ tipo: '', max_julios: 1 }), 'N/A');
});

test('estado de la fila: julio + orden implica reservado; flags capitalizados', () => {
    assert.equal(estadoDe({ no_julio: 'J1', no_orden: 'O1' }), 'reservado');
    assert.equal(estadoDe({ Reservado: true }), 'reservado');
    assert.equal(estadoDe({ Programado: true }), 'programado');
    assert.equal(estadoDe({ no_julio: 'J1' }), 'libre');
});

test('julios y órdenes de una barra alineados por posición', () => {
    const r = { julios: ['A', ' ', 'B'], ordenes: ['O1'], max_julios: 4 };
    assert.deepEqual(rowJulios(r), ['A', 'B']);
    assert.deepEqual(rowOrdenes(r), ['O1', '']);
    assert.deepEqual(etiquetasOrdenes(r), ['O1']);
    assert.deepEqual(rowJulios({ no_julio: 'J9' }), ['J9']);
    assert.deepEqual(etiquetasOrdenes({ no_orden: 'X', julios: ['J'], ordenes: ['Y'] }), ['X']);
});

test('lote por prefijo del serial y cuenta por prefijo', () => {
    assert.equal(matchLote('00061', '', '00061-744'), true);
    assert.equal(matchLote('00061', '00062', '00062-1'), false);
    assert.equal(matchLote('', 'x', 'y'), true);
    assert.equal(matchCuenta('3156', '3156-A'), true);
    assert.equal(matchCuenta('3156', '3157'), false);
    assert.equal(matchCuenta('', '3156'), false);
});

test('formato: números, fechas y badges', () => {
    assert.equal(fmt.num('12.345'), '12.35');
    assert.equal(fmt.num(null), '');
    assert.equal(fmt.num('abc'), '');
    assert.equal(fmt.num(1500.4, 0), '1500');
    assert.equal(fmt.date(''), '');
    assert.equal(fmt.date('no-es-fecha'), '');
    assert.match(fmt.date('2026-09-30T00:00:00Z'), /30/);
    assert.equal(fmt.salonBadge('JACQUARD'), 'bg-pink-100 text-pink-700');
    assert.equal(fmt.salonBadge(null), 'bg-pink-100 text-pink-700');
    assert.equal(fmt.salonBadge('Otro'), 'bg-indigo-100 text-indigo-700');
    assert.equal(fmt.tipoBadge('pie'), 'bg-teal-100 text-teal-700');
    assert.equal(fechaDataset('2026-09-30 06:30:00'), '2026-09-30');
});

test('asRows acepta objeto, lista o nada', () => {
    assert.deepEqual(asRows({ a: 1 }), [{ a: 1 }]);
    assert.deepEqual(asRows([{ a: 1 }]), [{ a: 1 }]);
    assert.deepEqual(asRows(null), []);
});

test('telarDesdeDataset arma la selección desde data-*', () => {
    const t = telarDesdeDataset({ id: '5', telar: '201', tipo: 'RIZO', julios: 'A,B', ordenes: 'O1,', maxJulios: '4', isReservado: 'true' }, 'Especial');
    assert.equal(t.tipo, 'Rizo');
    assert.deepEqual(t.julios, ['A', 'B']);
    assert.deepEqual(t.ordenes, ['O1', '']);
    assert.equal(t.max_julios, 4);
    assert.equal(t.is_reservado, true);
    assert.equal(t.tipo_atado, 'Especial');
    assert.equal(telarDesdeDataset({}).id, null);
});

test('esMismoTelar: por id si lo hay, si no telar + tipo', () => {
    assert.equal(esMismoTelar({ id: 10, no_telar: '1' }, { id: '10', no_telar: '9', tipo: '' }), true);
    assert.equal(esMismoTelar({ no_telar: '201', tipo: 'RIZO' }, { id: null, no_telar: '201', tipo: 'Rizo' }), true);
    assert.equal(esMismoTelar({ no_telar: '201', tipo: 'PIE' }, { id: null, no_telar: '201', tipo: 'Rizo' }), false);
});

/* ---------- orden y filtros ---------- */

test('ordenarFilas: numérico, vacíos al final, estado y multi-columna', () => {
    const rows = [{ no_telar: '10' }, { no_telar: '' }, { no_telar: '9' }];
    assert.deepEqual(ordenarFilas(rows, [{ column: 'no_telar', direction: 'asc' }]).map((r) => r.no_telar), ['9', '10', '']);
    assert.deepEqual(ordenarFilas(rows, [{ column: 'no_telar', direction: 'desc' }]).map((r) => r.no_telar), ['10', '9', '']);
    const est = [{ id: 1 }, { id: 2, Programado: true }, { id: 3, reservado: true }];
    assert.deepEqual(ordenarFilas(est, [{ column: 'estado', direction: 'desc' }]).map((r) => r.id), [3, 2, 1]);
    const multi = [{ a: 'x', b: 2 }, { a: 'x', b: 1 }, { a: 'a', b: 3 }];
    assert.deepEqual(
        ordenarFilas(multi, [{ column: 'a', direction: 'asc' }, { column: 'b', direction: 'asc' }]).map((r) => r.b),
        [3, 1, 2],
    );
    assert.notEqual(ordenarFilas(rows, []), rows, 'devuelve copia');
});

test('siguienteOrden: clic simple asc → desc → nada; aditivo acumula', () => {
    assert.deepEqual(siguienteOrden([], 'a'), [{ column: 'a', direction: 'asc' }]);
    assert.deepEqual(siguienteOrden([{ column: 'a', direction: 'asc' }], 'a'), [{ column: 'a', direction: 'desc' }]);
    assert.deepEqual(siguienteOrden([{ column: 'a', direction: 'desc' }], 'a'), []);
    assert.deepEqual(siguienteOrden([{ column: 'a', direction: 'asc' }], 'b'), [{ column: 'b', direction: 'asc' }]);
    const actual = [{ column: 'a', direction: 'asc' }];
    assert.deepEqual(siguienteOrden(actual, 'b', true), [{ column: 'a', direction: 'asc' }, { column: 'b', direction: 'asc' }]);
    assert.deepEqual(siguienteOrden(actual, 'a', true), [{ column: 'a', direction: 'desc' }]);
    assert.equal(actual[0].direction, 'asc', 'no muta la lista original');
    assert.deepEqual(siguienteOrden([{ column: 'a', direction: 'desc' }, { column: 'b', direction: 'asc' }], 'a', true), [{ column: 'b', direction: 'asc' }]);
});

test('filtrarLocal: texto, estado, números, cuenta por prefijo y telar vacío', () => {
    const rows = [
        { no_telar: '201', calibre: 12, estado: '', reservado: true, InventSizeId: '3156-A', NoTelarId: '' },
        { no_telar: '305', calibre: 12.5, Programado: true, InventSizeId: '43156', NoTelarId: '201' },
    ];
    assert.equal(filtrarLocal(rows, [{ column: 'no_telar', value: '20' }]).length, 1);
    assert.equal(filtrarLocal(rows, [{ column: 'estado', value: 'program' }])[0].no_telar, '305');
    assert.equal(filtrarLocal(rows, [{ column: 'calibre', value: '12' }]).length, 2, '12.5 también contiene "12" (como antes)');
    assert.equal(filtrarLocal(rows, [{ column: 'calibre', value: '12.50' }]).length, 1);
    assert.equal(filtrarLocal(rows, [{ column: 'InventSizeId', value: '3156' }]).length, 1);
    assert.equal(filtrarLocal(rows, [{ column: 'NoTelarId', value: 'disponible' }])[0].no_telar, '201');
    assert.equal(filtrarLocal(rows, [{ column: 'reservado', value: 'sí' }])[0].no_telar, '201');
    assert.equal(filtrarLocal(rows, []), rows);
});

test('filtrosActivos ignora valores vacíos; chips alternan', () => {
    assert.deepEqual(filtrosActivos({ a: 'x', b: ' ', c: '' }), [{ column: 'a', value: 'x' }]);
    const map = {};
    alternarSalon(map, 'ITEMA');
    assert.equal(map.salon, 'ITEMA');
    alternarSalon(map, 'ITEMA');
    assert.equal(map.salon, undefined);
    alternarEstado(map, 'libre');
    assert.equal(map.estado, 'libre');
    alternarEstado(map, '');
    assert.equal(map.estado, undefined);
});

test('chips de salón: únicos, ordenados y abreviados', () => {
    assert.deepEqual(salonesDe([{ salon: 'SMIT' }, { salon: 'ITEMA' }, { salon: 'SMIT' }, { salon: '' }]), ['ITEMA', 'SMIT']);
    assert.equal(salonCorto('ITEMA'), 'SMI');
    assert.equal(salonCorto('Karl Mayer'), 'KM');
    assert.equal(salonCorto('Jacquard'), 'JAC');
});

test('inventario para el telar: tipo, cuenta, lote y piezas de otro telar', () => {
    const inv = [
        { InventSerialId: '00061-1', InventBatchId: '00061', InventSizeId: '3156', Tipo: 'Rizo', NoTelarId: '' },
        { InventSerialId: '00061-2', InventBatchId: '00061', InventSizeId: '3156', Tipo: 'Pie', NoTelarId: '' },
        { InventSerialId: '00070-1', InventBatchId: '00070', InventSizeId: '9999', Tipo: 'Rizo', NoTelarId: '' },
        { InventSerialId: '00080-1', InventBatchId: '00080', InventSizeId: '3156', Tipo: 'Rizo', NoTelarId: '305' },
        { InventSerialId: '00090-1', InventBatchId: '00090', InventSizeId: '0000', Tipo: 'Pie', NoTelarId: '201' },
    ];
    assert.deepEqual(filtrarInventarioPorTelar(inv, telar()).map((r) => r.InventSerialId), ['00061-1', '00090-1']);
    // Con orden: solo su lote (y lo ya asignado al telar).
    assert.deepEqual(filtrarInventarioPorTelar(inv, telar({ no_orden: '00070', cuenta: '' })).map((r) => r.InventSerialId), ['00070-1', '00090-1']);
    // Barra llena: solo sus julios.
    const barra = telar({ tipo: '1', max_julios: 2, julios: ['00061-1', '00061-2'], no_orden: '00061', cuenta: '' });
    assert.deepEqual(filtrarInventarioPorTelar(inv, barra).map((r) => r.InventSerialId), ['00061-1', '00061-2', '00090-1']);
});

/* ---------- selección ---------- */

test('selección múltiple: rechaza reservados, con orden y grupos distintos', () => {
    assert.equal(motivoRechazoMultiple(telar({ is_reservado: true }), [])?.titulo, 'Telar no disponible');
    assert.equal(motivoRechazoMultiple(telar({ no_orden: 'O1' }), [])?.titulo, 'Telar con orden');
    assert.equal(motivoRechazoMultiple(telar(), []), null);
    assert.equal(motivoRechazoMultiple(telar({ id: '11', cuenta: '9999' }), [telar()]), null, 'la cuenta puede variar');
    assert.equal(motivoRechazoMultiple(telar({ id: '11', calibre: '14' }), [telar()])?.tipo, 'warning');
    assert.equal(motivoRechazoMultiple(telar({ id: '11' }), [telar({ reservado: true })])?.titulo, 'Telar no disponible en selección');
});

test('agregar / quitar de la selección múltiple sin duplicar', () => {
    let sel = agregarAMultiple([], telar());
    sel = agregarAMultiple(sel, telar());
    assert.equal(sel.length, 1);
    assert.equal(enMultiple(sel, { id: 10 }), true);
    assert.equal(enMultiple(sel, { id: 99, no_telar: '201', tipo: 'RIZO' }), false, 'con id en la selección y en la fila compara id');
    sel = quitarDeMultiple(sel, telar());
    assert.deepEqual(sel, []);
    const sinId = telar({ id: null });
    assert.equal(enMultiple(agregarAMultiple([], sinId), { no_telar: '201', tipo: 'rizo' }), true);
});

test('elegirPieza: rizo reemplaza; barra acumula hasta llenar y alterna', () => {
    const a = pieza();
    const b = pieza({ inventSerialId: '00061-745', numJulio: '00061-745' });
    const c = pieza({ inventSerialId: '00061-746', numJulio: '00061-746' });
    assert.deepEqual(elegirPieza([a], b, telar()), { ok: true, seleccion: [b] });
    assert.equal(elegirPieza([], pieza({ tipo: 'Pie' }), telar()).ok, false);

    const barra = telar({ tipo: '1', max_julios: 4, julios: ['X', 'Y'] });
    const uno = { ...a, tipo: '1' };
    const dos = { ...b, tipo: '1' };
    const tres = { ...c, tipo: '1' };
    let r = elegirPieza([], uno, barra);
    r = elegirPieza(r.seleccion, dos, barra);
    assert.equal(r.seleccion.length, 2);
    const lleno = elegirPieza(r.seleccion, tres, barra);
    assert.equal(lleno.ok, false);
    assert.match(lleno.aviso.texto, /Solo quedan 2/);
    assert.deepEqual(elegirPieza(r.seleccion, uno, barra), { ok: true, seleccion: [dos] });
    assert.equal(elegirPieza([], uno, telar({ tipo: '1', max_julios: 2, julios: ['X', 'Y'] })).aviso.titulo, 'Barra llena');
});

test('lote: sale de la primera pieza o de la orden; candidatas del lote y tipo', () => {
    assert.equal(loteDeSeleccion(telar({ no_orden: 'O9' }), []), 'O9');
    assert.equal(loteDeSeleccion(telar({ no_orden: 'O9' }), [pieza()]), '00061');
    const filas = [
        { disabled: false, inventBatchId: '00061', tipo: '1', n: 1 },
        { disabled: true, inventBatchId: '00061', tipo: '1', n: 2 },
        { disabled: false, inventBatchId: '00061', tipo: '', n: 3 },
        { disabled: false, inventBatchId: '00061', tipo: '2', n: 4 },
        { disabled: false, inventBatchId: '00061', tipo: '1', n: 5 },
        { disabled: false, inventBatchId: '00062', tipo: '1', n: 6 },
    ];
    assert.deepEqual(candidatasLote(filas, '00061', '1', 2).map((f) => f.n), [1, 3]);
    assert.deepEqual(candidatasLote(filas, '00061', '1', 9).map((f) => f.n), [1, 3, 5]);
});

test('botones: reservar pide tipos iguales; liberar solo reservado; programar respeta la múltiple', () => {
    assert.deepEqual(estadoBotones(null, [], []), { programar: true, reservar: true, liberar: true });
    assert.deepEqual(estadoBotones(telar(), [], []), { programar: false, reservar: true, liberar: true });
    assert.deepEqual(estadoBotones(telar(), [pieza()], []), { programar: false, reservar: false, liberar: true });
    assert.equal(estadoBotones(telar(), [pieza({ tipo: 'Pie', data: { Tipo: 'Pie' } })], []).reservar, true);
    assert.equal(estadoBotones(telar(), [pieza({ tipo: '', data: {} })], []).reservar, false, 'pieza sin tipo entra');
    assert.deepEqual(estadoBotones(telar({ reservado: true, julios: ['J'] }), [pieza()], []), { programar: true, reservar: true, liberar: false });
    const barra = telar({ reservado: true, tipo: '1', max_julios: 4, julios: ['A'] });
    assert.equal(estadoBotones(barra, [pieza({ tipo: '1' })], []).reservar, false, 'barra con hueco admite otro julio');
    assert.equal(estadoBotones(null, [], [telar(), telar({ id: '2', no_orden: 'O' })]).programar, true);
    assert.equal(estadoBotones(null, [], [telar()]).programar, false);
});

/* ---------- programar ---------- */

test('programar individual: completa con la fila original y arma la URL de siempre', () => {
    const base = [{ id: '10', no_telar: '201', tipo: 'RIZO', cuenta: '3156', hilo: 'HX', tipo_atado: 'Especial' }];
    const r = telaresParaProgramar(telar({ hilo: '', tipo_atado: '' }), [], base);
    assert.equal(r.ok, true);
    assert.deepEqual(r.telares, [
        { id: '10', no_telar: '201', tipo: 'Rizo', cuenta: '3156', salon: 'Jacquard', calibre: '12', hilo: 'HX', fecha: '2026-09-30', turno: '1', tipo_atado: 'Especial' },
    ]);
    const url = urlConTelares('/programa-urd-eng/programacion-requerimientos', r.telares);
    assert.ok(url.startsWith('/programa-urd-eng/programacion-requerimientos?telares=%5B%7B'));
    assert.deepEqual(JSON.parse(decodeURIComponent(url.split('telares=')[1])), r.telares);
});

test('programar: avisos de reservado, programado, con orden y sin telar; múltiple tal cual', () => {
    assert.equal(telaresParaProgramar(null, [], []).aviso.titulo, 'Selecciona un telar');
    assert.equal(telaresParaProgramar(telar({ reservado: true }), [], []).aviso.titulo, 'Telar reservado');
    assert.equal(telaresParaProgramar(telar({ programado: true }), [], []).aviso.titulo, 'Telar programado');
    assert.equal(telaresParaProgramar(telar({ no_orden: 'O' }), [], []).aviso.titulo, 'Telar con orden');
    const multi = [telar(), telar({ id: '11', no_telar: '202' })];
    assert.deepEqual(telaresParaProgramar(null, multi, []).telares, multi);
    assert.equal(telaresParaProgramar(null, [telar({ programado: true })], []).ok, false);
});

/* ---------- reservar / liberar ---------- */

test('validarReserva en el orden de siempre', () => {
    assert.equal(validarReserva(null, []).texto, 'Selecciona un telar');
    assert.equal(validarReserva(telar({ id: null }), []).tipo, 'error');
    assert.equal(validarReserva(telar({ reservado: true, julios: ['J'] }), [pieza()]).texto, 'Este telar ya está reservado');
    assert.equal(validarReserva(telar(), []).texto, 'Selecciona una fila de inventario');
    assert.equal(validarReserva(telar({ tipo: '1', max_julios: 4, julios: ['00061-744'] }), [pieza({ tipo: '1' })]).texto, 'Ese julio ya está en esta barra');
    assert.match(validarReserva(telar(), [pieza(), pieza({ inventSerialId: 'Z' })]).texto, /Solo quedan 1/);
    assert.equal(validarReserva(telar({ tipo: 'Pie' }), [pieza()]).titulo, 'Advertencia');
    assert.equal(validarReserva(telar(), [pieza({ data: { ...pieza().data, NoTelarId: '305' } })]).texto, 'Esa pieza ya tiene telar asignado');
    assert.equal(validarReserva(telar(), [pieza()]), null);
});

test('payloadReserva: mismo contrato que el POST de antes', () => {
    assert.deepEqual(payloadReserva(telar(), pieza()), {
        NoTelarId: '201',
        SalonTejidoId: 'Jacquard',
        ItemId: 'IT',
        ConfigId: null,
        InventSizeId: null,
        InventColorId: null,
        InventLocationId: null,
        InventBatchId: '00061',
        WMSLocationId: 'W1',
        InventSerialId: '00061-744',
        Tipo: 'Rizo',
        Metros: 1500,
        InventQty: 80,
        ProdDate: '2026-09-01',
        fecha: '2026-09-30',
        turno: '1',
        tej_inventario_telares_id: 10,
        telar: { metros: 1500, no_julio: '00061-744', no_orden: '00061', localidad: 'W1' },
    });
});

test('reserva local: rizo reemplaza; barra agrega en la primera posición libre', () => {
    const rizo = { no_julio: 'OLD', no_orden: 'O0' };
    aplicarReservaLocal(rizo, pieza(), '00061', 1);
    assert.deepEqual(rizo, { no_julio: '00061-744', no_orden: '00061', metros: 1500, julios: ['00061-744'], ordenes: ['00061'] });

    const barra = { max_julios: 4, julios: ['A'], ordenes: ['OA'] };
    aplicarReservaLocal(barra, pieza(), '00061', 4);
    assert.deepEqual(barra.julios, ['A', '00061-744']);
    assert.deepEqual(barra.ordenes, ['OA', '00061']);
    assert.equal(barra.no_julio, 'A');
    assert.equal(barra.reservado, true);
});

test('liberar: optimista deja la fila vacía; con respuesta conserva lo del servidor', () => {
    const fila = { no_julio: 'J', no_orden: 'O', hilo: 'H', julios: ['J'], reservado: true, metros: 9 };
    aplicarLiberado(fila, undefined);
    assert.equal(fila.no_julio, '');
    assert.equal(fila.hilo, 'H', 'la fibra no se pierde');
    assert.equal(fila.reservado, false);
    assert.equal(fila.metros, 0);
    aplicarLiberado(fila, { hilo: 'H2', metros: 5 });
    assert.equal(fila.hilo, 'H2');
    assert.equal(fila.metros, 5);
});

test('payloadEdicion: cuenta texto, calibre número o null', () => {
    const ref = { id: 10, no_telar: '201', tipo: 'RIZO' };
    assert.deepEqual(payloadEdicion('cuenta', '3156', ref), { no_telar: '201', tipo: 'Rizo', id: 10, cuenta: '3156' });
    assert.deepEqual(payloadEdicion('calibre', '12.5', ref), { no_telar: '201', tipo: 'Rizo', id: 10, calibre: 12.5 });
    assert.deepEqual(payloadEdicion('calibre', '', { ...ref, id: null }), { no_telar: '201', tipo: 'Rizo', id: null, calibre: null });
});
