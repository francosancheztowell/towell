/**
 * rutaSuperficie() (rutas.ts) debe dar lo mismo que el parche de window.fetch de index.js,
 * que siguen usando los fetch que quedan en la grilla (PT-TS 2). Se extrae rewriteUrl del
 * propio index.js para que, si alguien cambia uno, el test lo note.
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import { rutaSuperficie, type RutasSuperficie } from '../../resources/js/programa-tejido/rutas.ts';

const fuente = readFileSync(new URL('../../resources/js/programa-tejido/index.js', import.meta.url), 'utf8');
const cuerpo = fuente.match(/const rewriteUrl = \(url\) => \{([\s\S]*?)\n {4}\};/)?.[1];

function parcheDeIndex(boot: RutasSuperficie): (url: string) => string {
    assert.ok(cuerpo, 'no se encontró rewriteUrl en index.js');
    const fabrica = new Function(
        'PT_BASE_PATH',
        'PT_API_PATH',
        'PT_LINE_PATH',
        `return (url) => {${cuerpo}\n};`,
    ) as (a: string, b: string, c: string) => (url: string) => string;
    return fabrica(
        boot.basePath || '/planeacion/programa-tejido',
        boot.apiPath || '/programa-tejido',
        boot.linePath || '/req-programa-tejido-line',
    );
}

// Los valores reales de ProgramaTejidoSurface (basePath/apiPath/linePath) y el boot vacío.
const SUPERFICIES: Record<string, RutasSuperficie> = {
    vacio: {},
    programa: { basePath: '/planeacion/programa-tejido', apiPath: '/programa-tejido', linePath: '/planeacion/req-programa-tejido-line' },
    muestras: { basePath: '/planeacion/muestras', apiPath: '/muestras', linePath: '/planeacion/muestras-line' },
};

const URLS = [
    '/planeacion/programa-tejido/balancear',
    '/planeacion/programa-tejido/123/reprogramar',
    '/programa-tejido/ord-compartida/7',
    '/programa-tejido/flogs-id-from-twflogs',
    '/planeacion/req-programa-tejido-line?programa_id=5',
    '/api/otra-cosa',
    'https://ejemplo/planeacion/programa-tejido/x',
];

for (const [nombre, boot] of Object.entries(SUPERFICIES)) {
    test(`rutaSuperficie = parche de fetch (${nombre})`, () => {
        const parche = parcheDeIndex(boot);
        for (const url of URLS) {
            assert.equal(rutaSuperficie(url, boot), parche(url), url);
        }
    });
}

test('Muestras reescribe la base de Programa', () => {
    assert.equal(
        rutaSuperficie('/planeacion/programa-tejido/balancear', SUPERFICIES.muestras),
        '/planeacion/muestras/balancear',
    );
    assert.equal(rutaSuperficie('/programa-tejido/x', SUPERFICIES.muestras), '/muestras/x');
    assert.equal(
        rutaSuperficie('/planeacion/req-programa-tejido-line?programa_id=5', SUPERFICIES.muestras),
        '/planeacion/muestras-line?programa_id=5',
    );
});

test('Programa deja la URL igual', () => {
    for (const url of URLS) assert.equal(rutaSuperficie(url, SUPERFICIES.programa), url);
});
