// Creación de órdenes (Programa Urd-Eng, 19-05): lógica pura de
// resources/js/modulos/programa-urd-eng/creacion-ordenes/logica.ts y comun/fecha-requerimiento.ts.
// El payload esperado está fijado a mano leyendo public/js/modulos/programa_urd_eng/creacion-ordenes.js
// (borrado en 19-05, BUG-033): mismas claves, mismo orden, mismos tipos.
import test from 'node:test';
import assert from 'node:assert/strict';
import {
    agruparTelares,
    armarPayload,
    camposFaltantes,
    celdasMaterialEngomado,
    destinoInicial,
    destinoPorTelar,
    fechaCorta,
    filasMaterialesUrdido,
    leerConstruccion,
    metrajePorTela,
    normalizarDestino,
    normalizarTelares,
    queryMaterialesEngomado,
    totalesSeleccion,
    validarCreacion,
} from '../../resources/js/modulos/programa-urd-eng/creacion-ordenes/logica.ts';
import {
    aLocalISO,
    rangoFechaRequerimiento,
    validarFechaRequerimiento,
} from '../../resources/js/modulos/programa-urd-eng/comun/fecha-requerimiento.ts';

/* Entrada representativa: lo que manda Programación de requerimientos por ?telares= */
const TELARES = [
    { no_telar: '305', fecha_req: '2026-10-02', cuenta: '3040', calibre: 12.5, hilo: ' A12 ', tamano: '28x60', urdido: 'Mc Coy 1', tipo: 'RIZO', destino: '', tipo_atado: 'Normal', metros: '1200.5', kilos: '350.25', agrupar: true },
    { no_telar: '306', fecha_req: '2026-10-02', cuenta: '3040', calibre: 12.5, hilo: 'A12', tamano: '28x60', urdido: 'Mc Coy 1', tipo: 'Rizo', destino: '', tipo_atado: 'Normal', metros: '800', kilos: '200', agrupar: true },
    { no_telar: '207', fecha_req: '2026-10-03', cuenta: '2020', calibre: null, hilo: 'P10', tamano: '', urdido: 'Mc Coy 2', tipo: 'pie', destino: '', tipo_atado: 'Especial', metros: '1,000', kilos: '100', agrupar: false },
];

const M1 = { ItemId: 'H-1', ConfigId: 'C1', InventSizeId: 'S', InventColorId: '', InventLocationId: 'A1', InventBatchId: 'L1', WMSLocationId: 'W1', InventSerialId: 'SER1', TwCalidadFlog: 'LP', TwClienteFlog: 'NP', ProdDate: '2026-09-01 00:00:00', TwTiras: '12', PhysicalInvent: '45.5' };
const M2 = { ItemId: 'H-2', InventSerialId: 'SER2', PhysicalInvent: 10, TwTiras: 3 };
const M3 = { ItemId: 'H-3', PhysicalInvent: '1,234.5', TwTiras: null };

const ENGOMADO = {
    nucleo: 'N1',
    noTelas: '2',
    anchoBalonas: '60',
    metrajeTelas: '1,000.25',
    cuendeadosMin: '2',
    maquinaEngomado: 'WS1',
    lMatEngomado: ' ENG-1 ',
    bomFormula: 'TE-PD-ENF-0025',
    observaciones: ' obs ',
};

function entrada(cambios = {}) {
    const [grupo] = agruparTelares(normalizarTelares(TELARES));
    return {
        estado: { grupo, bomId: 'URD-123', destinoSeleccionado: 'Smit', requiereDestinoManual: true, materialesEngomado: [M1, M2] },
        destinoSelect: 'Smit',
        marcados: [
            { materialId: 'H-1', serialId: 'SER1', material: M1 },
            { materialId: 'H-3', serialId: '', material: M3 },
        ],
        construccion: [
            { julios: ' 4 ', hilos: '600', observaciones: ' ok ' },
            { julios: '', hilos: '', observaciones: 'x' },
            { julios: '', hilos: '300', observaciones: '' },
            { julios: '', hilos: '', observaciones: '' },
        ],
        engomado: { ...ENGOMADO },
        ...cambios,
    };
}

/* Payload que producía el JS viejo para esa entrada (JSON exacto, orden de claves incluido). */
const PAYLOAD_VIEJO = {
    grupo: {
        telaresStr: '305,306',
        noTelarId: '305',
        tipo: 'Rizo',
        cuenta: '3040',
        calibre: 12.5,
        fechaReq: '2026-10-02',
        fibra: 'A12',
        hilo: 'A12',
        tamano: '28x60',
        inventSizeId: '28x60',
        metros: 2000.5,
        kilos: 550.25,
        noProduccion: '',
        salonTejidoId: 'Smit',
        destino: 'Smit',
        maquinaId: 'Mc Coy 1',
        bomId: 'URD-123',
        tipoAtado: 'Normal',
        status: 'Programado',
    },
    materialesEngomado: [
        { itemId: 'H-1', configId: 'C1', inventSizeId: 'S', inventColorId: '', inventLocationId: 'A1', inventBatchId: 'L1', wmsLocationId: 'W1', inventSerialId: 'SER1', kilos: 45.5, conos: 12, loteProv: 'LP', noProv: 'NP', prodDate: '2026-09-01 00:00:00', status: 'Programado' },
        { itemId: 'H-3', configId: '', inventSizeId: '', inventColorId: '', inventLocationId: '', inventBatchId: '', wmsLocationId: '', inventSerialId: '', kilos: 1234.5, conos: 0, loteProv: '', noProv: '', prodDate: null, status: 'Programado' },
    ],
    construccionUrdido: [
        { julios: '4', hilos: '600', observaciones: 'ok' },
        { julios: '', hilos: '300', observaciones: '' },
    ],
    datosEngomado: {
        nucleo: 'N1',
        noTelas: '2',
        anchoBalonas: '60',
        metrajeTelas: '1,000.25',
        cuendeadosMin: '2',
        maquinaEngomado: 'WS1',
        lMatEngomado: 'ENG-1',
        bomFormula: 'TE-PD-ENF-0025',
        observaciones: 'obs',
    },
    fechaRequerimiento: '2026-10-01T08:00',
};

test('payload de crear-ordenes: idéntico al del JS viejo (incluido el orden de claves)', () => {
    const r = validarCreacion(entrada());
    assert.equal(r.ok, true);
    const payload = armarPayload(r.datos, '2026-10-01T08:00');
    assert.deepStrictEqual(payload, PAYLOAD_VIEJO);
    assert.equal(JSON.stringify(payload), JSON.stringify(PAYLOAD_VIEJO));
});

test('payload de un telar individual: calibre vacío → null, destino por telar', () => {
    const grupos = agruparTelares(normalizarTelares(TELARES));
    const solo = grupos[1];
    assert.equal(destinoInicial(solo), 'Jacquard Sulzer');
    const r = validarCreacion(
        entrada({
            estado: { grupo: solo, bomId: 'URD-9', destinoSeleccionado: 'Jacquard Sulzer', requiereDestinoManual: false, materialesEngomado: [] },
        }),
    );
    assert.equal(r.ok, true);
    assert.deepStrictEqual(r.datos.grupo, {
        telaresStr: '207',
        noTelarId: '207',
        tipo: 'Pie',
        cuenta: '2020',
        calibre: null,
        fechaReq: '2026-10-03',
        fibra: 'P10',
        hilo: 'P10',
        tamano: '',
        inventSizeId: '',
        metros: 1000,
        kilos: 100,
        noProduccion: '',
        salonTejidoId: 'Jacquard Sulzer',
        destino: 'Jacquard Sulzer',
        maquinaId: 'Mc Coy 2',
        bomId: 'URD-9',
        tipoAtado: 'Especial',
        status: 'Programado',
    });
    // Sin materiales en el grupo: se usan los de la fila.
    assert.equal(r.datos.materialesEngomado[0].itemId, 'H-1');
});

test('agrupar: por cuenta|tipo|urdido|atado; hilo y calibre no separan; individuales al final', () => {
    const t = normalizarTelares([
        { no_telar: '1', agrupar: true, tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: 'U1', metros: 10, kilos: 5, destino: 'SMIT' },
        { no_telar: '2', agrupar: true, tipo: 'RIZO', cuenta: '40', calibre: 14, hilo: 'PES', urdido: 'U1', metros: 10, kilos: 5 },
        { no_telar: '3', agrupar: true, tipo: 'Pie', cuenta: '40', urdido: 'U1' },
        { no_telar: '4', agrupar: false, tipo: 'Rizo', cuenta: '40', urdido: 'U1' },
        { no_telar: '5', agrupar: true, tipo: 'Rizo', cuenta: '60', urdido: 'U1', destino: 'smith' },
    ]);
    const g = agruparTelares(t);
    assert.deepEqual(g.map((x) => x.telaresStr), ['1,2', '3', '5', '4']);
    assert.equal(g[0].metros, 20);
    assert.equal(g[0].calibre, 12);
    assert.equal(g[0].hilo, 'ALG');
    assert.equal(g[0].destino, '', 'grupo de varios telares: destino manual');
    assert.equal(destinoInicial(g[2]), 'Smit');
    assert.equal(g[3].tipoAtado, 'Normal');
});

test('destino: normalización y mapa por telar', () => {
    assert.equal(normalizarDestino(' itema   nuevo '), 'Itema Nuevo');
    assert.equal(normalizarDestino('JAC'), 'Jacquard Smit');
    assert.equal(normalizarDestino('otro'), '');
    assert.equal(destinoPorTelar('209'), 'Jacquard Sulzer');
    assert.equal(destinoPorTelar(213), 'Jacquard Smit');
    assert.equal(destinoPorTelar('310'), 'Smit');
    assert.equal(destinoPorTelar('317'), 'Itema Viejo');
    assert.equal(destinoPorTelar('320'), 'Itema Nuevo');
    assert.equal(destinoPorTelar('999'), '');
});

test('validaciones en el mismo orden que antes', () => {
    const titulo = (e) => {
        const r = validarCreacion(e);
        return r.ok ? 'ok' : r.aviso.titulo;
    };
    const base = entrada();
    assert.equal(titulo({ ...base, estado: null }), 'Selección requerida');
    assert.equal(titulo({ ...base, estado: { ...base.estado, bomId: ' ' } }), 'BOM ID requerido');
    const sinDestino = validarCreacion({ ...base, destinoSelect: '', estado: { ...base.estado, destinoSeleccionado: '' } });
    assert.equal(sinDestino.ok, false);
    assert.equal(sinDestino.aviso.texto, 'Seleccione el destino del grupo antes de crear la orden.');
    assert.deepEqual(sinDestino.aviso.foco, { tipo: 'destino' });
    // El select sirve de respaldo si el estado no tiene destino.
    assert.equal(titulo({ ...base, destinoSelect: 'SMIT', estado: { ...base.estado, destinoSeleccionado: '' } }), 'ok');
    assert.equal(titulo({ ...base, estado: { ...base.estado, grupo: { ...base.estado.grupo, hilo: '' } } }), 'Fibra/Hilo requerido');
    assert.equal(titulo({ ...base, marcados: [] }), 'Materiales requeridos');
    assert.equal(titulo({ ...base, construccion: [{ julios: '', hilos: '', observaciones: 'solo obs' }] }), 'Construcción requerida');
    const faltan = validarCreacion({ ...base, engomado: { ...ENGOMADO, nucleo: '', lMatEngomado: '  ' } });
    assert.equal(faltan.aviso.titulo, 'Campos requeridos');
    assert.match(faltan.aviso.texto, /Núcleo, L Mat Engomado\.$/);
    assert.deepEqual(faltan.aviso.foco, { tipo: 'engomado', campo: 'nucleo' });
});

test('construcción: más de 15 julios detiene (bug viejo: avisaba y seguía sin esa fila)', () => {
    assert.deepEqual(leerConstruccion([{ julios: '3' }, { julios: '16', hilos: '1' }]), { ok: false, fila: 1 });
    const r = validarCreacion(entrada({ construccion: [{ julios: '20', hilos: '5', observaciones: '' }] }));
    assert.equal(r.aviso.titulo, 'Valor fuera de rango');
    assert.deepEqual(r.aviso.foco, { tipo: 'julios', fila: 0 });
    assert.deepEqual(leerConstruccion([{ julios: '15', hilos: '' }]), { ok: true, filas: [{ julios: '15', hilos: '', observaciones: '' }] });
});

test('campos faltantes: selects vacíos y textos en blanco', () => {
    assert.deepEqual(camposFaltantes(ENGOMADO), []);
    const todos = camposFaltantes({ ...ENGOMADO, nucleo: '', noTelas: '', anchoBalonas: '', metrajeTelas: ' ', cuendeadosMin: '', maquinaEngomado: '', lMatEngomado: '' });
    assert.deepEqual(todos.map(([c]) => c), ['nucleo', 'noTelas', 'anchoBalonas', 'metrajeTelas', 'cuendeadosMin', 'maquinaEngomado', 'lMatEngomado']);
});

test('materiales de urdido: consumo a 3 decimales y kilos = programados × consumo', () => {
    assert.deepEqual(filasMaterialesUrdido([{ ItemId: 'H-1', ConfigId: null, BomQty: '0.51234' }], 1000), [
        { articulo: 'H-1', config: '-', consumo: '0.512', kilos: '512.00' },
    ]);
    assert.equal(
        queryMaterialesEngomado([{ ItemId: 'A', ConfigId: 'C' }, { ItemId: 'A', ConfigId: ' ' }, { ItemId: 'B' }, { ItemId: '' }]),
        'itemIds%5B%5D=A&itemIds%5B%5D=B&configIds%5B%5D=C',
    );
    assert.equal(queryMaterialesEngomado([{ ItemId: null }]), null);
});

test('materiales de engomado: celdas, totales y metraje', () => {
    const celdas = celdasMaterialEngomado(M1);
    assert.equal(celdas.length, 13);
    assert.deepEqual(celdas.slice(8), ['LP', 'NP', '01/09/2026', '12', '45.50']);
    assert.equal(celdasMaterialEngomado(M3)[10], '-');
    assert.deepEqual(totalesSeleccion([M1, M2, M3]), { registros: 3, conos: '15', kilos: '1,290.00' });
    assert.equal(metrajePorTela(2000.5, '2'), '1,000.25');
    assert.equal(metrajePorTela(1000, ''), '500.00', 'No. de telas vacío cuenta como 2 (como antes)');
    assert.equal(metrajePorTela(0, '2'), '');
    assert.equal(fechaCorta('2025-01-15T06:00:00.000Z'), '15/01/2025');
    assert.equal(fechaCorta('15/01/2025'), '15/01/2025');
    assert.equal(fechaCorta(null), '');
});

test('fecha de requerimiento: hora local, mínimo ahora y sugerida +1 h', () => {
    const ahora = new Date(2026, 8, 30, 23, 30, 45);
    assert.equal(aLocalISO(ahora), '2026-09-30T23:30');
    assert.deepEqual(rangoFechaRequerimiento(ahora), { min: '2026-09-30T23:30', sugerida: '2026-10-01T00:30' });
    assert.equal(validarFechaRequerimiento('', '2026-09-30T23:30'), 'Por favor selecciona una fecha y hora de requerimiento.');
    assert.match(validarFechaRequerimiento('2026-09-30T23:29', '2026-09-30T23:30'), /no puede ser anterior/);
    assert.equal(validarFechaRequerimiento('2026-09-30T23:30', '2026-09-30T23:30'), null);
});
