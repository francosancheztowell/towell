import test from 'node:test';
import assert from 'node:assert/strict';
import {
    agruparPorCuenta,
    cuentaYCalibreDeTamano,
    estiloTipo,
    etiquetaSemana,
    fechaHoyISO,
    filtrarPorGrupo,
    filtrarTamanos,
    formatearCantidad,
    formatearNumeroInput,
    itemTieneDatos,
    itemsResumen,
    kilosProgramados,
    limpiarNumeroInput,
    mensajeGuardado,
    mensajeResumenVacio,
    normalizarEntrada,
    normalizarTipo,
    payloadActualizarTelar,
    primerCampoFaltante,
    puedeContinuar,
    soloCaracteresNumericos,
    telaresParaCreacion,
    totalesPorTelar,
    totalesResumen,
    validarGrupo,
} from '../../resources/js/modulos/programa-urd-eng/programacion-requerimientos/logica.ts';
import { parsearTelares, urlConTelares } from '../../resources/js/modulos/programa-urd-eng/comun/contrato-flujo.ts';

// Intl de node puede usar espacio fino o coma según ICU: se compara sin depender del separador.
const sinMiles = (s) => s.replace(/[,\s  ]/g, '');

test('normalizarTipo y normalizarEntrada (hilo siempre null)', () => {
    assert.equal(normalizarTipo('RIZO'), 'Rizo');
    assert.equal(normalizarTipo(' pie '), 'Pie');
    assert.equal(normalizarTipo('Otro'), 'Otro');
    assert.equal(normalizarTipo(null), '');
    assert.deepEqual(normalizarEntrada([{ no_telar: '201', tipo: 'PIE', hilo: 'H1' }]), [{ no_telar: '201', tipo: 'Pie', hilo: null }]);
    assert.deepEqual(normalizarEntrada(null), []);
});

test('validarGrupo: vacío, sin tipo, tipo distinto, calibre distinto, válido', () => {
    assert.deepEqual(validarGrupo([]), { valido: false, mensaje: 'No hay telares seleccionados' });
    assert.equal(validarGrupo([{ no_telar: '1', tipo: '' , hilo: null }]).mensaje, 'El telar debe tener un tipo definido');
    const tipos = validarGrupo([
        { no_telar: '1', tipo: 'Rizo', hilo: null },
        { no_telar: '2', tipo: 'Pie', hilo: null },
    ]);
    assert.equal(tipos.valido, false);
    assert.match(tipos.mensaje, /telar 2 tiene tipo "Pie" pero se esperaba "Rizo"/);
    const cal = validarGrupo([
        { no_telar: '1', tipo: 'Rizo', calibre: '12', hilo: null },
        { no_telar: '2', tipo: 'RIZO', calibre: 12.5, hilo: null },
    ]);
    assert.equal(cal.valido, false);
    assert.deepEqual(
        validarGrupo([
            { no_telar: '1', tipo: 'Rizo', calibre: '12', hilo: null },
            { no_telar: '2', tipo: 'RIZO', calibre: '', hilo: null },
        ]),
        { valido: true, tipo: 'Rizo', calibre: 12, hilo: null },
    );
});

test('filtrarPorGrupo y agruparPorCuenta (orden de aparición; sin cuenta va solo)', () => {
    const telares = [
        { no_telar: '1', tipo: 'Rizo', cuenta: '4112', calibre: 12, hilo: null },
        { no_telar: '2', tipo: 'Pie', cuenta: '4112', hilo: null },
        { no_telar: '3', tipo: 'Rizo', cuenta: '3800', hilo: null },
        { no_telar: '4', tipo: 'Rizo', cuenta: '4112', calibre: 12.001, hilo: null },
        { no_telar: '5', tipo: 'Rizo', cuenta: '', hilo: null },
    ];
    const filtrados = filtrarPorGrupo(telares, { valido: true, tipo: 'Rizo', calibre: 12, hilo: null });
    assert.deepEqual(filtrados.map((t) => t.no_telar), ['1', '3', '4', '5']);
    assert.deepEqual(agruparPorCuenta(filtrados).map((g) => g.map((t) => t.no_telar)), [['1', '4'], ['3'], ['5']]);
});

test('fechas: hoy en zona de planta y rango de semana', () => {
    assert.equal(fechaHoyISO('America/Mexico_City', new Date('2026-01-01T03:00:00Z')), '2025-12-31');
    assert.equal(etiquetaSemana({ inicio: '2026-09-28', fin: '2026-10-04' }), '28/09 - 04/10');
    assert.equal(etiquetaSemana({ inicio: '2026-09-28' }), '');
    assert.equal(etiquetaSemana(null), '');
});

test('números de inputs y celdas', () => {
    assert.equal(sinMiles(formatearNumeroInput('1234.5')), '1234.50');
    assert.equal(sinMiles(formatearNumeroInput('1,234.5')), '1234.50');
    assert.equal(formatearNumeroInput(0), '');
    assert.equal(formatearNumeroInput('abc'), '');
    assert.equal(limpiarNumeroInput('1,234.50'), '1234.50');
    assert.equal(limpiarNumeroInput(''), '');
    assert.equal(soloCaracteresNumericos('12a,3.4 m'), '12,3.4');
    assert.equal(formatearCantidad(0), '-');
    assert.equal(formatearCantidad(-3), '-');
    assert.equal(sinMiles(formatearCantidad('1500')), '1500.00');
});

test('tamaño → cuenta y calibre; sugerencias', () => {
    assert.deepEqual(cuentaYCalibreDeTamano('4112-12.5/1'), { cuenta: '4112', calibre: '12.5' });
    assert.deepEqual(cuentaYCalibreDeTamano('  '), { cuenta: '', calibre: '' });
    assert.equal(cuentaYCalibreDeTamano('4112'), null);
    const lista = Array.from({ length: 80 }, (_, i) => `41${i}-12/1`);
    assert.equal(filtrarTamanos(lista, '').length, 60);
    assert.deepEqual(filtrarTamanos(['A-1/1', 'b-2/1'], 'B'), ['b-2/1']);
});

test('estilo del select Tipo', () => {
    assert.deepEqual(estiloTipo('Pie'), { backgroundColor: '#ccfbf1', color: '#0f766e' });
    assert.deepEqual(estiloTipo('Rizo'), { backgroundColor: '#fee2e2', color: '#be123c' });
    assert.deepEqual(estiloTipo(''), { backgroundColor: '#fee2e2', color: '#be123c' });
});

test('payload de actualizar telar: id/fecha/turno opcionales y tipo normalizado', () => {
    assert.deepEqual(payloadActualizarTelar('hilo', 'H-20', '201', 'RIZO', { inventarioId: '15', fecha: '2026-09-28', turno: '2' }), {
        no_telar: '201', hilo: 'H-20', solo_inventario: true, id: 15, fecha: '2026-09-28', turno: '2', tipo: 'Rizo',
    });
    assert.deepEqual(payloadActualizarTelar('tipo', 'PIE', '201', 'Rizo', { inventarioId: '', fecha: '', turno: '' }), {
        no_telar: '201', tipo: 'Pie', solo_inventario: true,
    });
    assert.equal('id' in payloadActualizarTelar('hilo', 'x', '1', '', { inventarioId: 'abc', fecha: '', turno: '' }), false);
});

test('mensaje de guardado con detalle', () => {
    assert.equal(mensajeGuardado('hilo'), 'Hilo actualizado');
    assert.equal(
        mensajeGuardado('tipo', { tej_inventario_telares: 1, urd_programa_urdido: 0, eng_programa_engomado: 2 }),
        'Tipo actualizado: 1 en TejInventarioTelares, 2 en EngProgramaEngomado',
    );
});

const semanaRizo = (telar, base, extra = {}) => ({
    TelarId: telar, CuentaRizo: '4112', Hilo: 'H20', Modelo: 'M1',
    SemActualMtsRizo: base, SemActual1MtsRizo: base, SemActual2MtsRizo: 0, SemActual3MtsRizo: 0, SemActual4MtsRizo: 0,
    SemActualKilosRizo: base / 10, SemActual1KilosRizo: base / 10,
    Total: base * 2, TotalKilos: base / 5, ...extra,
});

test('itemsResumen: Rizo usa el calibre validado y no filtra por hilo', () => {
    const v = { valido: true, tipo: 'Rizo', calibre: 12, hilo: null };
    const items = itemsResumen({ rizo: [semanaRizo('201', 100), semanaRizo('202', 50, { Hilo: '' })], pie: [{ TelarId: 'x' }] }, v);
    assert.equal(items.length, 2);
    assert.deepEqual(items[0].metros, [100, 100, 0, 0, 0]);
    assert.deepEqual(items[0].kilos, [10, 10, 0, 0, 0]);
    assert.equal(items[0].calibre, 12);
    assert.equal(items[1].hilo, '-');
    assert.equal(items[0].modelo, 'M1');
});

test('itemsResumen: Pie filtra por calibre ±0.11 y acepta claves alternativas', () => {
    const v = { valido: true, tipo: 'Pie', calibre: 10, hilo: null };
    const items = itemsResumen({
        pie: [
            { telarId: '301', CalibrePie: '10.1', SemActual: 40, Total: 40 },
            { TelarId: '302', CalibrePie: 10.5, SemActualMtsPie: 30 },
            { TelarId: '303', SemActual2MtsPie: 7, SemActual2KilosPie: 1 },
        ],
    }, v);
    assert.deepEqual(items.map((i) => i.telar), ['301', '303']);
    assert.deepEqual(items[0].metros, [40, 0, 0, 0, 0]);
    assert.equal(items[1].calibre, '-');
    assert.deepEqual(items[1].kilos, [0, 0, 1, 0, 0]);
});

test('totales por semana, por telar, kilos programados y filas con datos', () => {
    const v = { valido: true, tipo: 'Rizo', calibre: null, hilo: null };
    const items = itemsResumen({ rizo: [semanaRizo('201', 100), semanaRizo('201', 50), semanaRizo('202', 0)] }, v);
    const t = totalesResumen(items);
    assert.deepEqual(t.metros, [150, 150, 0, 0, 0]);
    assert.deepEqual(t.kilos, [15, 15, 0, 0, 0]);
    assert.equal(t.totalMetros, 300);
    assert.equal(t.totalKilos, 30);
    const porTelar = totalesPorTelar(items);
    assert.deepEqual(porTelar.get('201'), { totalMetros: 300, totalKilos: 30 });
    assert.equal(kilosProgramados(porTelar.get('201'), 150), 15);
    assert.equal(kilosProgramados(porTelar.get('202'), 150), 0);
    assert.equal(kilosProgramados(undefined, 150), 0);
    assert.deepEqual(items.map(itemTieneDatos), [true, true, false]);
});

test('mensaje de resumen vacío distingue "hay registros que no coinciden" de "no hay registros"', () => {
    const pie = { valido: true, tipo: 'Pie', calibre: 10, hilo: null };
    const hay = mensajeResumenVacio({ pie: [{}, {}] }, pie, []);
    assert.match(hay, /Hilo: No aplica/);
    assert.match(hay, /Existen 2 registro\(s\).*\(calibre\)/);
    const rizo = { valido: true, tipo: 'Rizo', calibre: null, hilo: null };
    const nada = mensajeResumenVacio({ rizo: [] }, rizo, [{ inicio: '2026-09-28' }, {}, {}, {}, { fin: '2026-11-01' }]);
    assert.match(nada, /Calibre: N\/A/);
    assert.match(nada, /Hilo: Todos/);
    assert.match(nada, /\(2026-09-28 a 2026-11-01\)/);
});

const fila = (extra = {}) => ({
    telar: '201, 202', fecha_req: '2026-10-01', cuenta: '4112', calibre: '12', tamano: '4112-12/1', hilo: 'H20',
    urdido: 'MC1', tipo: 'Rizo', tipo_atado: 'Normal', metros: '1,500.00', kilos: '150.00',
    grupo: [{ no_telar: '201' }, { no_telar: '202' }], ...extra,
});

test('botón Siguiente y primer campo faltante', () => {
    assert.equal(puedeContinuar([]), false);
    assert.equal(puedeContinuar([fila()]), true);
    assert.equal(puedeContinuar([fila({ hilo: '' })]), false);
    assert.equal(puedeContinuar([fila({ telar: '' , hilo: '' }), fila()]), true);
    assert.equal(primerCampoFaltante([fila()]), null);
    assert.deepEqual(primerCampoFaltante([fila(), fila({ telar: '300', tamano: '', hilo: '' })]), {
        indice: 1, campo: 'tamano', mensaje: 'Telar 300: complete el campo "Tamaño".',
    });
    assert.equal(primerCampoFaltante([fila({ kilos: ',' })]).campo, 'kilos');
});

test('contrato hacia creación de órdenes: un renglón por telar del grupo', () => {
    const salida = telaresParaCreacion([fila(), fila({ grupo: [], telar: '305', calibre: '', tipo: 'PIE', metros: '', kilos: '' })]);
    assert.equal(salida.length, 3);
    assert.deepEqual(salida[0], {
        no_telar: '201', fecha_req: '2026-10-01', cuenta: '4112', calibre: 12, hilo: 'H20', tamano: '4112-12/1',
        urdido: 'MC1', tipo: 'Rizo', destino: '', tipo_atado: 'Normal', metros: '1500.00', kilos: '150.00', agrupar: true,
    });
    assert.equal(salida[1].no_telar, '202');
    assert.deepEqual([salida[2].no_telar, salida[2].calibre, salida[2].tipo, salida[2].metros, salida[2].kilos], ['305', null, 'Pie', '0', '0']);
});

test('contrato de flujo: parseo tolerante y URL con ?telares=', () => {
    const telares = [{ no_telar: '201', tipo: 'Rizo', salon: 'SMIT 50%' }];
    assert.deepEqual(parsearTelares(JSON.stringify(telares)), telares);
    assert.deepEqual(parsearTelares(encodeURIComponent(JSON.stringify(telares))), telares);
    assert.deepEqual(parsearTelares('{"no":1}'), []);
    assert.deepEqual(parsearTelares('%%no json'), []);
    assert.deepEqual(parsearTelares(null), []);
    const url = urlConTelares('/programa-urd-eng/creacion-ordenes', telares);
    assert.equal(url.split('?telares=')[0], '/programa-urd-eng/creacion-ordenes');
    assert.deepEqual(JSON.parse(new URL(url, 'http://x').searchParams.get('telares')), telares);
    assert.match(urlConTelares('/a?b=1', []), /^\/a\?b=1&telares=/);
});
