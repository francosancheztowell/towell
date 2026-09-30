import test from 'node:test';
import assert from 'node:assert/strict';
import {
    armarPayload,
    cuentaYCalibre,
    errorFechaRequerimiento,
    fechaHoraLocal,
    filaDetalle,
    filtrarTamanos,
    formatFecha,
    formatKilos,
    formularioValido,
    listaDeCatalogo,
    opcionesBom,
    tamanoInvalido,
    totalesSeleccion,
} from '../../resources/js/modulos/programa-urd-eng/karl-mayer/logica.ts';
import {
    aNumero,
    claveMaterial,
    ordenarMateriales,
    siguienteDireccion,
} from '../../resources/js/modulos/programa-urd-eng/comun/inventario-materiales.ts';

/* ---------- Entrada representativa (inventario TI_PRO como lo devuelve materiales-urdido-completo) ---------- */
const INVENTARIO = [
    {
        ItemId: 'H-100', ConfigId: '30/1', InventSizeId: '2960-12/1', InventColorId: 'CRUDO', InventLocationId: 'A-HIL',
        InventBatchId: '00061', WMSLocationId: 'R1', InventSerialId: '00061-744', TwCalidadFlog: 'LP-9', TwClienteFlog: 'PROV1',
        ProdDate: '2026-09-01 00:00:00.000', TwTiras: '12', PhysicalInvent: '1,234.5',
    },
    {
        ItemId: 'H-200', ConfigId: '20/1', InventSizeId: null, InventColorId: 'BLANCO', InventLocationId: 'A-HIL',
        InventBatchId: '', WMSLocationId: 'R2', InventSerialId: '00077-1', TwCalidadFlog: null, TwClienteFlog: '',
        ProdDate: null, TwTiras: 8, PhysicalInvent: 98.25,
    },
    // Sin ItemId ni serie: el JS viejo la descartaba del payload.
    { ItemId: '', InventSerialId: '', PhysicalInvent: 5 },
];

function formularioRepresentativo() {
    const fd = new FormData();
    fd.append('_token', 'tok');
    const campos = {
        no_telar: '401', barras: '2', fibra: '30/1', tamano: '2960-12/1', cuenta: '2960', calibre: '12', metros: '1500.5',
        fecha_programada: '2026-09-30', tipo_atado: 'Normal', bom_id: 'BOM-KM-01', lote_proveedor: '00061', observaciones: 'Urgente',
    };
    for (const [k, v] of Object.entries(campos)) fd.append(k, v);
    for (const [j, h, o] of [['4', '120', 'a'], ['', '', ''], ['2', '', ''], ['', '', '']]) {
        fd.append('julios[]', j);
        fd.append('hilos[]', h);
        fd.append('obs[]', o);
    }
    return fd;
}

/**
 * Oráculo: copia literal del armado de payload de crear-karl-mayer.blade.php (antes de 19-05).
 * Las filas guardaban el material en data-material-data (JSON) y getMaterialesSeleccionados lo releía.
 */
function payloadViejo(formData, filasSeleccionadas, fechaRequerimientoKM) {
    const toNumber = (v, def = 0) => {
        if (v === null || v === undefined) return def;
        const num = parseFloat(String(v).replace(/,/g, ''));
        return Number.isNaN(num) ? def : num;
    };
    const getMaterialesSeleccionados = () => filasSeleccionadas.map((m) => {
        const material = JSON.parse(JSON.stringify(m));
        return {
            itemId: material.ItemId || '',
            configId: material.ConfigId || '',
            inventSizeId: material.InventSizeId || '',
            inventColorId: material.InventColorId || '',
            inventLocationId: material.InventLocationId || '',
            inventBatchId: material.InventBatchId || '',
            wmsLocationId: material.WMSLocationId || '',
            inventSerialId: material.InventSerialId || '',
            kilos: toNumber(material.PhysicalInvent, 0),
            conos: toNumber(material.TwTiras, 0),
            loteProv: material.TwCalidadFlog || '',
            noProv: material.TwClienteFlog || '',
            prodDate: material.ProdDate || null,
        };
    }).filter((m) => m && (m.itemId || m.inventSerialId));
    const payload = {
        _token: formData.get('_token'),
        no_telar: formData.get('no_telar'),
        barras: formData.get('barras'),
        fibra: formData.get('fibra'),
        tamano: formData.get('tamano'),
        cuenta: formData.get('cuenta'),
        calibre: formData.get('calibre'),
        metros: formData.get('metros'),
        fecha_programada: formData.get('fecha_programada'),
        tipo_atado: formData.get('tipo_atado'),
        bom_id: formData.get('bom_id'),
        lote_proveedor: formData.get('lote_proveedor'),
        observaciones: formData.get('observaciones'),
        julios: formData.getAll('julios[]'),
        hilos: formData.getAll('hilos[]'),
        obs: formData.getAll('obs[]'),
        materiales: getMaterialesSeleccionados(),
    };
    payload.fechaRequerimiento = fechaRequerimientoKM;
    return payload;
}

test('caracterización: payload idéntico al del JS viejo (salvo _token, que ahora va en el header de http)', () => {
    const fd = formularioRepresentativo();
    const nuevo = armarPayload(fd, INVENTARIO, '2026-10-02T08:30');
    const { _token, ...viejo } = payloadViejo(fd, INVENTARIO, '2026-10-02T08:30');
    assert.equal(_token, 'tok');
    assert.deepEqual(JSON.parse(JSON.stringify(nuevo)), JSON.parse(JSON.stringify(viejo)));
    assert.equal(nuevo.materiales.length, 2);
    assert.deepEqual(nuevo.materiales[0], {
        itemId: 'H-100', configId: '30/1', inventSizeId: '2960-12/1', inventColorId: 'CRUDO', inventLocationId: 'A-HIL',
        inventBatchId: '00061', wmsLocationId: 'R1', inventSerialId: '00061-744', kilos: 1234.5, conos: 12,
        loteProv: 'LP-9', noProv: 'PROV1', prodDate: '2026-09-01 00:00:00.000',
    });
    assert.deepEqual(nuevo.julios, ['4', '', '2', '']);
    assert.equal(nuevo.fechaRequerimiento, '2026-10-02T08:30');
});

test('formularioValido: mismas reglas que habilitaban "Crear Orden"', () => {
    const fd = formularioRepresentativo();
    assert.equal(formularioValido(fd, 1), true);
    assert.equal(formularioValido(fd, 0), false, 'sin materiales');

    const sinCalibre = formularioRepresentativo();
    sinCalibre.set('calibre', '  ');
    assert.equal(formularioValido(sinCalibre, 1), false);

    const metrosMal = formularioRepresentativo();
    metrosMal.set('metros', '-1');
    assert.equal(formularioValido(metrosMal, 1), false);
    metrosMal.set('metros', 'abc');
    assert.equal(formularioValido(metrosMal, 1), false);
    metrosMal.set('metros', '0');
    assert.equal(formularioValido(metrosMal, 1), true);

    const sinJulios = formularioRepresentativo();
    sinJulios.delete('julios[]');
    sinJulios.delete('hilos[]');
    for (let i = 0; i < 4; i++) { sinJulios.append('julios[]', ''); sinJulios.append('hilos[]', ' '); }
    assert.equal(formularioValido(sinJulios, 1), false, 'al menos un julio o hilos');
    sinJulios.set('hilos[]', '10');
    assert.equal(formularioValido(sinJulios, 1), true);
});

test('cuentaYCalibre desde Tamaño', () => {
    assert.deepEqual(cuentaYCalibre('2960-12/1'), { cuenta: '2960', calibre: '12' });
    assert.deepEqual(cuentaYCalibre(' 3200 - 8.5 /1 '), { cuenta: '3200', calibre: '8.5' });
    assert.deepEqual(cuentaYCalibre('ESPECIAL'), { cuenta: 'ESPECIAL', calibre: '' });
    assert.deepEqual(cuentaYCalibre(''), { cuenta: '', calibre: '' });
});

test('tamaños: filtro sin mayúsculas, tope 60 y validación contra el catálogo', () => {
    const opciones = Array.from({ length: 80 }, (_, i) => `29${String(i).padStart(2, '0')}-12/1`);
    assert.equal(filtrarTamanos(opciones, '').length, 60);
    assert.deepEqual(filtrarTamanos(['ABC-1/1', 'xyz'], 'abc'), ['ABC-1/1']);
    assert.equal(tamanoInvalido('2900-12/1', opciones), false);
    assert.equal(tamanoInvalido('9999', opciones), true);
    assert.equal(tamanoInvalido('  ', opciones), false);
});

test('catálogos y BOM', () => {
    assert.deepEqual(listaDeCatalogo({ success: true, data: [{ ConfigId: '30/1' }, { ConfigId: '' }, {}] }, 'ConfigId'), ['30/1']);
    assert.deepEqual(listaDeCatalogo({ success: false, data: [{ ConfigId: 'x' }] }, 'ConfigId'), []);
    const filas = Array.from({ length: 20 }, (_, i) => ({ BOMID: `B${i}`, NAME: `Nombre ${i}` }));
    assert.equal(opcionesBom(filas).length, 15);
    assert.deepEqual(opcionesBom({ data: [{ bomId: 'b1', name: 'n1' }, { NAME: 'sin id' }] }), [{ valor: 'b1', nombre: 'n1' }]);
});

test('fila de inventario y totales de lo seleccionado', () => {
    const f = filaDetalle(INVENTARIO[0]);
    assert.equal(f.clave, 'H-100_00061-744');
    assert.equal(f.celdas.length, 13);
    assert.deepEqual(f.celdas.slice(8), ['PROV1', 'LP-9', '01/09/2026', '12', '1,234.50']);
    const g = filaDetalle(INVENTARIO[1]);
    assert.deepEqual(g.celdas.slice(8, 11), ['-', '-', '-']);
    assert.equal(g.celdas[2], '');

    assert.deepEqual(totalesSeleccion([]), { registros: 'Total: 0', conos: '', kilos: '', lote: '' });
    assert.deepEqual(
        totalesSeleccion([{ conos: 8, kilos: 98.25, lote: '' }, { conos: 12.7, kilos: 1234.5, lote: '00061' }]),
        { registros: 'Total: 2', conos: '20', kilos: '1,332.75', lote: '00061' },
    );
});

test('formatos de fecha y kilos', () => {
    assert.equal(formatFecha('2026-09-01T00:00:00Z'), '01/09/2026');
    assert.equal(formatFecha('05/03/2026'), '05/03/2026');
    assert.equal(formatFecha(''), '');
    assert.equal(formatFecha('no-fecha'), 'no-fecha');
    assert.equal(formatKilos('abc'), '0.00');
    assert.equal(formatKilos(1234.567), '1,234.57');
});

test('fecha de requerimiento: hora local de la app (antes UTC) y validación', () => {
    // 2026-09-30 20:15 UTC = 14:15 en Ciudad de México. El JS viejo usaba toISOString() → "20:15".
    assert.equal(fechaHoraLocal('America/Mexico_City', new Date('2026-09-30T20:15:42Z')), '2026-09-30T14:15');
    assert.equal(fechaHoraLocal('America/Mexico_City', new Date('2026-10-01T06:00:00Z')), '2026-10-01T00:00');
    assert.match(errorFechaRequerimiento('', '2026-09-30T14:15') ?? '', /selecciona una fecha/);
    assert.match(errorFechaRequerimiento('2026-09-30T14:00', '2026-09-30T14:15') ?? '', /anterior/);
    assert.equal(errorFechaRequerimiento('2026-09-30T14:15', '2026-09-30T14:15'), null);
});

test('comun/inventario-materiales: orden por columna igual al JS viejo', () => {
    const lista = [
        { ItemId: 'b', TwTiras: '10', PhysicalInvent: '1,000', ProdDate: '2026-01-02' },
        { ItemId: 'A', TwTiras: 2, PhysicalInvent: 50, ProdDate: null },
        { ItemId: 'c', TwTiras: null, PhysicalInvent: '7', ProdDate: '2025-12-31' },
    ];
    assert.deepEqual(ordenarMateriales(lista, 'itemId', 'asc').map((m) => m.ItemId), ['A', 'b', 'c']);
    assert.deepEqual(ordenarMateriales(lista, 'kilos', 'desc').map((m) => m.ItemId), ['b', 'A', 'c']);
    assert.deepEqual(ordenarMateriales(lista, 'conos', 'asc').map((m) => m.ItemId), ['c', 'A', 'b']);
    assert.deepEqual(ordenarMateriales(lista, 'prodDate', 'asc').map((m) => m.ItemId), ['A', 'c', 'b']);
    assert.equal(ordenarMateriales(lista, null, null), lista);
    assert.deepEqual(ordenarMateriales(lista, 'desconocida', 'asc').map((m) => m.ItemId), ['b', 'A', 'c']);
    assert.equal(siguienteDireccion({ columna: 'kilos', direccion: 'asc' }, 'kilos'), 'desc');
    assert.equal(siguienteDireccion({ columna: 'kilos', direccion: 'desc' }, 'kilos'), 'asc');
    assert.equal(siguienteDireccion({ columna: 'kilos', direccion: 'asc' }, 'conos'), 'asc');
    assert.equal(claveMaterial({}), '_');
    assert.equal(aNumero('1,234.5', null), 1234.5);
    assert.equal(aNumero('x', null), null);
});
