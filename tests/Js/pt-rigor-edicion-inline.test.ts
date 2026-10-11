/**
 * Edición inline de Programa Tejido: contrato entre la grilla y el PUT.
 *
 * Se leen como texto (igual que programa-tejido-rutas.test.ts) las tres fuentes:
 *  - inlineFieldPayloadMap y uiInlineEditableFields de resources/js/programa-tejido/index.ts;
 *  - reglas() y CAMPOS_VACIO_A_NULL de ActualizarProgramaTejidoRequest.php (legacy y v2);
 *  - los campos que EdicionProgramaTejido y sus pasos aplican de verdad a columnas.
 * Si alguien agrega una columna editable sin regla, o una regla que nadie aplica, el
 * servidor la ignoraría en silencio (200 "actualizado" sin cambio): estos tests lo detectan.
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const leer = (ruta: string) => readFileSync(new URL(`../../${ruta}`, import.meta.url), 'utf8');

const indexTs = leer('resources/js/programa-tejido/index.ts');
const requestPhp = leer('app/Http/Requests/Planeacion/ProgramaTejido/ActualizarProgramaTejidoRequest.php');
const edicionPhp = [
    'app/Services/Planeacion/ProgramaTejido/EdicionProgramaTejido.php',
    'app/Services/Planeacion/ProgramaTejido/Edicion/ClaveModelo.php',
].map(leer).join('\n');

function bloque(fuente: string, inicio: RegExp, nombre: string): string {
    const m = fuente.match(inicio);
    assert.ok(m?.[1], `no se encontró ${nombre}`);
    return m[1];
}

// ---------------------------------------------------------------- extracción

const mapaTexto = bloque(indexTs, /const inlineFieldPayloadMap: Record<string, string> = \{([\s\S]*?)\};/, 'inlineFieldPayloadMap');
const MAPA: Record<string, string> = Object.fromEntries(
    [...mapaTexto.matchAll(/(\w+)\s*:\s*'(\w+)'/g)].map((m) => [m[1]!, m[2]!]),
);

const camposUiTexto = bloque(indexTs, /const uiInlineEditableFields: Record<string, any> = \{([\s\S]*?)\n\};/, 'uiInlineEditableFields');

/** uiInlineEditableFields evaluado: se quitan las anotaciones TS y se inyectan los helpers que usa. */
const CAMPOS_UI: Record<string, any> = new Function(
    'toInputDate',
    'formatDateDisplay',
    `return {${camposUiTexto.replace(/:\s*any\b/g, '')}\n};`,
)((v: string) => v, (v: string) => v);

const reglasTexto = bloque(requestPhp, /public static function reglas\(\): array\s*\{\s*return \[([\s\S]*?)\];/, 'reglas()');
const REGLAS: Record<string, string[]> = Object.fromEntries(
    [...reglasTexto.matchAll(/'(\w+)'\s*=>\s*\[([^\]]*)\]/g)].map((m) => [
        m[1]!,
        [...m[2]!.matchAll(/'([^']+)'/g)].map((r) => r[1]!),
    ]),
);

/** Columnas sin las que no se calculan las fórmulas de rollos: la regla no acepta null. */
const OBLIGATORIOS = ['NoTiras', 'PesoCrudo'];

const VACIO_A_NULL = [
    ...bloque(requestPhp, /CAMPOS_VACIO_A_NULL = \[([\s\S]*?)\];/, 'CAMPOS_VACIO_A_NULL').matchAll(/'(\w+)'/g),
].map((m) => m[1]!);

/** Conversión de saveInlineField (valor del input → valor del payload), extraída del propio index.ts. */
const conversionTexto = bloque(
    indexTs,
    /let value = \(input\.value \?\? ''\)\.trim\(\);[\s\S]*?(if \(cfg\.toPayload\)[\s\S]*?else if \(value === ''\) value = null;)/,
    'conversión de saveInlineField',
);
const aPayload = new Function('cfg', 'crudo', `let value = String(crudo ?? '').trim();\n${conversionTexto}\nreturn value;`) as (
    cfg: any,
    crudo: string,
) => unknown;

/** El servidor aplica el campo si alguno de los pasos lo lee de $data. */
const aplicaElServidor = (campo: string) =>
    new RegExp(`\\$data,\\s*'${campo}'|array_key_exists\\('${campo}'|'${campo}'\\s*=>\\s*\\[`).test(edicionPhp);

// ---------------------------------------------------------------- tests

test('el mapa y las reglas se extraen completos', () => {
    assert.equal(Object.keys(MAPA).length, 21);
    assert.equal(Object.keys(CAMPOS_UI).length, 21);
    assert.equal(Object.keys(REGLAS).length, 24);
    assert.ok(VACIO_A_NULL.length > 0);
});

test('cada clave del mapa llega a un campo que acepta ActualizarProgramaTejidoRequest', () => {
    const fuera = Object.entries(MAPA).filter(([, campo]) => !(campo in REGLAS));
    assert.deepEqual(fuera, [], 'el servidor ignoraría estas claves en silencio');
});

test('cada campo aceptado por el PUT lo aplica EdicionProgramaTejido (ninguna regla muerta)', () => {
    const muertos = Object.keys(REGLAS).filter((campo) => !aplicaElServidor(campo));
    assert.deepEqual(muertos, []);
});

test('no hay dos columnas de la grilla que manden el mismo campo', () => {
    const campos = Object.values(MAPA);
    assert.equal(new Set(campos).size, campos.length);
});

test('cada columna editable tiene entrada en el mapa y viceversa (sin caída al nombre de BD)', () => {
    // Sin entrada, saveInlineField manda el nombre de BD ("TotalPedido") y el servidor responde
    // 200 sin cambiar nada (ver EdicionInlineMapaPayloadTest::test_campos_fuera_de_las_reglas...).
    assert.deepEqual(Object.keys(CAMPOS_UI).sort(), Object.keys(MAPA).sort());
    for (const columna of Object.keys(CAMPOS_UI)) {
        assert.ok(!(columna in REGLAS), `${columna} coincide con un campo del PUT: el mapa sobraría`);
    }
});

test('el tipo del input coincide con la regla del servidor', () => {
    const esperado: Record<string, string> = { number: 'numeric', date: 'date', 'datetime-local': 'date', text: 'string', select: 'string' };
    for (const [columna, cfg] of Object.entries(CAMPOS_UI)) {
        const regla = REGLAS[MAPA[columna]!]!;
        const tipo = esperado[cfg.type]!;
        const coincide = regla.includes(tipo) || (tipo === 'numeric' && regla.includes('integer'));
        assert.ok(coincide, `${columna} (${cfg.type}) contra [${regla.join(', ')}]`);
        // NoTiras y PesoCrudo son obligatorios (> 0): vaciarlos da 422 sobre el campo.
        if (!OBLIGATORIOS.includes(columna)) {
            assert.ok(regla.includes('nullable'), `${columna}: vaciar la celda manda null y la regla no lo acepta`);
        }
        assert.ok(regla.includes('sometimes'), `${columna}: sin 'sometimes' un PUT de otro campo fallaría`);
    }
});

test('donde el servidor limita el largo, el input usa el mismo maxLength', () => {
    for (const [columna, cfg] of Object.entries(CAMPOS_UI)) {
        const max = REGLAS[MAPA[columna]!]!.find((r) => r.startsWith('max:'));
        if (max) assert.equal(cfg.maxLength, Number(max.slice(4)), columna);
    }
});

test('donde el input pone min 0, el servidor también rechaza negativos', () => {
    const sinMin = Object.entries(CAMPOS_UI)
        .filter(([columna, cfg]) => {
            const regla = REGLAS[MAPA[columna]!]!;
            return cfg.min === 0 && !regla.includes('min:0') && !regla.includes('gt:0');
        })
        .map(([columna]) => columna);
    assert.deepEqual(sinMin, []);
});

test('NoTiras y PesoCrudo exigen > 0 sin nullable (las fórmulas de rollos los necesitan)', () => {
    for (const columna of OBLIGATORIOS) {
        const regla = REGLAS[MAPA[columna]!]!;
        assert.ok(regla.includes('gt:0') && !regla.includes('nullable'), `${columna}: [${regla.join(', ')}]`);
    }
});

test('los campos numéricos y de fecha del mapa convierten "" en null en el servidor', () => {
    for (const [columna, cfg] of Object.entries(CAMPOS_UI)) {
        if (!['number', 'date', 'datetime-local'].includes(cfg.type)) continue;
        assert.ok(VACIO_A_NULL.includes(MAPA[columna]!), `${columna}: '' llegaría a la regla numeric/date y daría 422`);
    }
});

test('vaciar cualquier celda manda null (no cadena vacía ni 0)', () => {
    for (const [columna, cfg] of Object.entries(CAMPOS_UI)) {
        assert.equal(aPayload(cfg, '   '), null, columna);
    }
});

test('los valores típicos se convierten al tipo que espera la regla', () => {
    const muestra: Record<string, string> = {
        number: '12.5', date: '2026-09-20', 'datetime-local': '2026-09-25T10:30', text: ' ABC ', select: 'X',
    };
    for (const [columna, cfg] of Object.entries(CAMPOS_UI)) {
        const valor = aPayload(cfg, muestra[cfg.type]!);
        const regla = REGLAS[MAPA[columna]!]!;
        if (regla.includes('numeric')) assert.equal(typeof valor, 'number', columna);
        if (regla.includes('date')) assert.match(String(valor), /^\d{4}-\d{2}-\d{2}/, columna);
        if (regla.includes('string')) assert.equal(typeof valor, 'string', columna);
    }
    assert.equal(aPayload(CAMPOS_UI.NoProduccion, ' 36708 '), '36708');
    assert.equal(aPayload(CAMPOS_UI.NombreProyecto, ' P '), 'P');
});

test('AplicacionId "NA" se manda como null (sin aplicación)', () => {
    assert.equal(aPayload(CAMPOS_UI.AplicacionId, 'NA'), null);
    assert.equal(aPayload(CAMPOS_UI.AplicacionId, 'APX'), 'APX');
});

test('número no válido tecleado llega como NaN y JSON lo manda como null', () => {
    // Number('1,5') = NaN y JSON lo volvería null ("vaciar"): saveInlineField corta antes
    // de convertir con un aviso, sin mandar el PUT.
    const valor = aPayload(CAMPOS_UI.LargoCrudo, '1,5');
    assert.ok(Number.isNaN(valor));
    assert.match(indexTs, /cfg\.type === 'number' && \(input\.validity\?\.badInput \|\| \(value !== '' && Number\.isNaN\(Number\(value\)\)\)\)\) \{\s*window\.notify\.warning\(.*\);\s*return;/);
});

test('el mapa vive en un solo lugar (state.blade.php ya no lo duplica)', () => {
    assert.equal([...indexTs.matchAll(/const inlineFieldPayloadMap\b/g)].length, 1);
    assert.equal([...indexTs.matchAll(/const uiInlineEditableFields\b/g)].length, 1);
});
