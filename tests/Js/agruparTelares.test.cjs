/**
 * Test para la función agruparTelares de creacion-ordenes.js
 * Ejecutar: node tests/Js/agruparTelares.test.js
 */
'use strict';

const assert = require('node:assert/strict');

// ── Funciones extraídas de creacion-ordenes.js ──

const isNil   = v => v === null || v === undefined;
const isBlank = v => isNil(v) || String(v).trim() === '';

const normalizarTipo = (tipo) => {
    const up = String(tipo || '').toUpperCase().trim();
    if (up === 'RIZO') return 'Rizo';
    if (up === 'PIE')  return 'Pie';
    return tipo || '';
};

function agruparTelares(telares) {
    const grupos = Object.create(null);
    const singles = [];

    for (const telar of (telares || [])) {
        if (!telar.agrupar) { singles.push(telar); continue; }

        const tipoN = normalizarTipo(telar.tipo) || 'Rizo';
        const up    = String(tipoN || '').toUpperCase();
        const esPie = up === 'PIE';

        const cuenta   = String(telar.cuenta || '').trim();
        const calibre  = !isBlank(telar.calibre) ? parseFloat(telar.calibre) : null;
        const hiloClave = esPie ? '' : (!isBlank(telar.hilo) ? String(telar.hilo).trim() : '');
        const hilo = !isBlank(telar.hilo) ? String(telar.hilo).trim() : '';
        const tamano   = !isBlank(telar.tamano) ? String(telar.tamano).trim() : '';
        const urdido   = String(telar.urdido || '').trim();
        const tipoAtado= String(telar.tipo_atado || 'Normal').trim();
        const destino  = String(telar.destino || '').trim();

        const calibreClave = calibre !== null ? String(calibre) : '';
        const clave = esPie
            ? `${cuenta}|${calibreClave}|${up}|${urdido}|${tipoAtado}`
            : `${cuenta}|${hiloClave}|${calibreClave}|${up}|${urdido}|${tipoAtado}`;

        if (!grupos[clave]) {
            grupos[clave] = { telares:[], cuenta, calibre, hilo, tamano, tipo:tipoN, urdido, tipoAtado, destino,
                              fechaReq: telar.fecha_req || '', metros:0, kilos:0, maquinaId: telar.urdido || telar.maquina_urd || telar.maquinaId || '' };
        }
        grupos[clave].telares.push(telar);
        grupos[clave].metros += telar.metros || 0;
        grupos[clave].kilos  += telar.kilos  || 0;
    }

    const out = Object.values(grupos).map(g => ({ ...g, telaresStr: g.telares.map(t=>t.no_telar).join(',') }));
    for (const t of singles) {
        const calSingle = !isBlank(t.calibre) ? parseFloat(t.calibre) : null;
        out.push({
            telares:[t], telaresStr:t.no_telar, cuenta:t.cuenta || '', calibre: calSingle !== null ? calSingle : '', hilo:t.hilo || '', tamano:t.tamano || '',
            tipo: normalizarTipo(t.tipo) || 'Rizo', urdido:t.urdido || '', tipoAtado:t.tipo_atado || 'Normal', destino:t.destino || '',
            fechaReq:t.fecha_req || '', metros:t.metros || 0, kilos:t.kilos || 0, maquinaId: t.urdido || t.maquina_urd || t.maquinaId || ''
        });
    }
    return out;
}

// ── Tests ──

let passed = 0;
let failed = 0;

function test(name, fn) {
    try {
        fn();
        passed++;
        console.log(`  ✓ ${name}`);
    } catch (e) {
        failed++;
        console.log(`  ✗ ${name}`);
        console.log(`    ${e.message}`);
    }
}

console.log('\n  agruparTelares\n');

test('4 telares con distinto destino se agrupan en 1 solo grupo', () => {
    const telares = [
        { no_telar: '299', agrupar: true, tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: 'Mc Coy 1', tipo_atado: 'Normal', destino: 'ITEMA NUEVO', metros: 100, kilos: 50 },
        { no_telar: '300', agrupar: true, tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: 'Mc Coy 1', tipo_atado: 'Normal', destino: 'ITEMA NUEVO', metros: 100, kilos: 50 },
        { no_telar: '305', agrupar: true, tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: 'Mc Coy 1', tipo_atado: 'Normal', destino: 'SMIT', metros: 100, kilos: 50 },
        { no_telar: '306', agrupar: true, tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: 'Mc Coy 1', tipo_atado: 'Normal', destino: 'SMIT', metros: 100, kilos: 50 },
    ];

    const result = agruparTelares(telares);

    assert.equal(result.length, 1, `Esperaba 1 grupo, obtuvo ${result.length}`);
    assert.equal(result[0].telares.length, 4, `Esperaba 4 telares en el grupo, obtuvo ${result[0].telares.length}`);
    assert.equal(result[0].telaresStr, '299,300,305,306');
    assert.equal(result[0].metros, 400);
    assert.equal(result[0].kilos, 200);
});

test('telares con distinta cuenta NO se agrupan', () => {
    const telares = [
        { no_telar: '299', agrupar: true, tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: 'Mc Coy 1', tipo_atado: 'Normal', destino: 'ITEMA', metros: 50, kilos: 25 },
        { no_telar: '300', agrupar: true, tipo: 'Rizo', cuenta: '60', calibre: 12, hilo: 'ALG', urdido: 'Mc Coy 1', tipo_atado: 'Normal', destino: 'ITEMA', metros: 50, kilos: 25 },
    ];

    const result = agruparTelares(telares);
    assert.equal(result.length, 2, `Esperaba 2 grupos, obtuvo ${result.length}`);
});

test('telares con distinto calibre NO se agrupan', () => {
    const telares = [
        { no_telar: '299', agrupar: true, tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: 'Mc Coy 1', tipo_atado: 'Normal', destino: 'X', metros: 10, kilos: 5 },
        { no_telar: '300', agrupar: true, tipo: 'Rizo', cuenta: '40', calibre: 14, hilo: 'ALG', urdido: 'Mc Coy 1', tipo_atado: 'Normal', destino: 'X', metros: 10, kilos: 5 },
    ];

    const result = agruparTelares(telares);
    assert.equal(result.length, 2);
});

test('telares Rizo con distinto hilo NO se agrupan', () => {
    const telares = [
        { no_telar: '299', agrupar: true, tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: 'Mc Coy 1', tipo_atado: 'Normal', destino: 'X', metros: 10, kilos: 5 },
        { no_telar: '300', agrupar: true, tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'PES', urdido: 'Mc Coy 1', tipo_atado: 'Normal', destino: 'X', metros: 10, kilos: 5 },
    ];

    const result = agruparTelares(telares);
    assert.equal(result.length, 2);
});

test('telares Pie con distinto hilo SI se agrupan (hilo no es clave para Pie)', () => {
    const telares = [
        { no_telar: '299', agrupar: true, tipo: 'Pie', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: 'Mc Coy 1', tipo_atado: 'Normal', destino: 'X', metros: 10, kilos: 5 },
        { no_telar: '300', agrupar: true, tipo: 'Pie', cuenta: '40', calibre: 12, hilo: 'PES', urdido: 'Mc Coy 1', tipo_atado: 'Normal', destino: 'X', metros: 10, kilos: 5 },
    ];

    const result = agruparTelares(telares);
    assert.equal(result.length, 1);
    assert.equal(result[0].telares.length, 2);
});

test('telares sin agrupar van como singles', () => {
    const telares = [
        { no_telar: '299', agrupar: false, tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: '', tipo_atado: 'Normal', destino: 'X', metros: 10, kilos: 5 },
        { no_telar: '300', agrupar: false, tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: '', tipo_atado: 'Normal', destino: 'X', metros: 10, kilos: 5 },
    ];

    const result = agruparTelares(telares);
    assert.equal(result.length, 2);
    assert.equal(result[0].telares.length, 1);
    assert.equal(result[1].telares.length, 1);
});

test('mezcla de agrupables y singles', () => {
    const telares = [
        { no_telar: '299', agrupar: true,  tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: '', tipo_atado: 'Normal', destino: 'A', metros: 10, kilos: 5 },
        { no_telar: '300', agrupar: true,  tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: '', tipo_atado: 'Normal', destino: 'B', metros: 20, kilos: 10 },
        { no_telar: '301', agrupar: false, tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: '', tipo_atado: 'Normal', destino: 'A', metros: 30, kilos: 15 },
    ];

    const result = agruparTelares(telares);
    // 1 grupo (299+300 agrupados) + 1 single (301)
    assert.equal(result.length, 2);

    const grupo = result.find(r => r.telares.length === 2);
    assert.ok(grupo, 'Debe haber un grupo con 2 telares');
    assert.equal(grupo.metros, 30);
    assert.equal(grupo.telaresStr, '299,300');
});

test('telares con distinto tipoAtado NO se agrupan', () => {
    const telares = [
        { no_telar: '299', agrupar: true, tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: '', tipo_atado: 'Normal', destino: 'X', metros: 10, kilos: 5 },
        { no_telar: '300', agrupar: true, tipo: 'Rizo', cuenta: '40', calibre: 12, hilo: 'ALG', urdido: '', tipo_atado: 'Especial', destino: 'X', metros: 10, kilos: 5 },
    ];

    const result = agruparTelares(telares);
    assert.equal(result.length, 2);
});

test('lista vacia retorna array vacio', () => {
    assert.deepEqual(agruparTelares([]), []);
    assert.deepEqual(agruparTelares(null), []);
});

// ── Resumen ──

console.log(`\n  ${passed} passed, ${failed} failed\n`);
process.exit(failed > 0 ? 1 : 0);
