import test from 'node:test';
import assert from 'node:assert/strict';
import {
    diasEnRango,
    mensajeMaximo,
    MENSAJE_FALTAN,
    MENSAJE_ORDEN,
    MENSAJE_SEMANA,
    semanaLunesDomingo,
    textoSemana,
    urlConsulta,
    validarRango,
    validarSemana,
} from '../../resources/js/modulos/tejido/reportes/comun/rango-logica.ts';
import {
    aplicarSeleccion,
    bloques,
    claveValor,
    compararTextos,
    contarValores,
    filaPasa,
    mapaColumnas,
    ordenarPorBloques,
    posicionMenu,
    separadoresAntes,
    tituloBotonFiltro,
    VACIO,
    visibilidadConGrupos,
} from '../../resources/js/modulos/tejido/reportes/saldos-2026/logica.ts';

/* ── Rango de fechas (inv-telas, promedio paros, marcas finales) ── */

test('validarRango: faltan fechas, orden y los mensajes de antes', () => {
    assert.deepEqual(validarRango('', '2026-09-01'), { ok: false, campo: 'fecha_ini', mensaje: MENSAJE_FALTAN });
    assert.deepEqual(validarRango('2026-09-01', ' '), { ok: false, campo: 'fecha_fin', mensaje: MENSAJE_FALTAN });
    assert.deepEqual(validarRango('2026-09-02', '2026-09-01'), { ok: false, campo: 'fecha_fin', mensaje: MENSAJE_ORDEN });
    assert.deepEqual(validarRango('2026-09-01', '2026-09-01'), { ok: true });
});

test('validarRango con máximo de días (inv-telas: 5, contando ambos extremos)', () => {
    assert.equal(diasEnRango('2026-09-01', '2026-09-05'), 5);
    assert.equal(diasEnRango('2026-02-27', '2026-03-02'), 4);
    assert.equal(diasEnRango('2026-02-30', '2026-03-02'), null);
    assert.deepEqual(validarRango('2026-09-01', '2026-09-05', 5), { ok: true });
    assert.deepEqual(validarRango('2026-09-01', '2026-09-06', 5), { ok: false, campo: 'fecha_fin', mensaje: mensajeMaximo(5) });
    assert.equal(mensajeMaximo(5), 'El rango debe ser de máximo 5 días');
    // Sin máximo (promedio, marcas): cualquier rango en orden.
    assert.deepEqual(validarRango('2026-01-01', '2026-12-31'), { ok: true });
});

test('urlConsulta arma la query y respeta una ruta con ?', () => {
    assert.equal(urlConsulta('/tejido/reportes/inv-telas', { fecha_ini: '2026-09-01', fecha_fin: '2026-09-03' }), '/tejido/reportes/inv-telas?fecha_ini=2026-09-01&fecha_fin=2026-09-03');
    assert.equal(urlConsulta('/r?x=1', { semana: ' 2026-09-29 ' }), '/r?x=1&semana=2026-09-29');
    assert.equal(urlConsulta('/r', { semana: '' }), '/r?');
});

/* ── Semana lunes-domingo (RPM semanal) ── */

test('semanaLunesDomingo: como Carbon startOfWeek(MONDAY)/endOfWeek(SUNDAY)', () => {
    assert.deepEqual(semanaLunesDomingo('2026-09-29'), { lunes: '2026-09-28', domingo: '2026-10-04' }); // martes
    assert.deepEqual(semanaLunesDomingo('2026-09-28'), { lunes: '2026-09-28', domingo: '2026-10-04' }); // lunes
    assert.deepEqual(semanaLunesDomingo('2026-10-04'), { lunes: '2026-09-28', domingo: '2026-10-04' }); // domingo
    assert.deepEqual(semanaLunesDomingo('2026-01-01'), { lunes: '2025-12-29', domingo: '2026-01-04' }); // cruza año
    assert.equal(semanaLunesDomingo(''), null);
    assert.equal(semanaLunesDomingo('2026-13-01'), null);
});

test('textoSemana y validarSemana', () => {
    assert.equal(textoSemana('2026-09-29'), 'Semana: lun 28 sep al dom 4 oct 2026');
    assert.equal(textoSemana('x'), '');
    assert.deepEqual(validarSemana(''), { ok: false, campo: 'semana', mensaje: MENSAJE_SEMANA });
    assert.deepEqual(validarSemana('2026-09-29'), { ok: true });
});

/* ── Saldos 2026 ── */

const c = (colSpan = 1, rowSpan = 1) => ({ colSpan, rowSpan });

test('mapaColumnas respeta rowspan/colspan del encabezado (Rizo/Pie agrupados)', () => {
    // Fila 1: TELAR(rs2) | Rizo(cs3) | Pie(cs3) | Obs(rs2); fila 2: 6 subcolumnas; fila 3: 8 datos.
    const m = mapaColumnas([
        [c(1, 2), c(3), c(3), c(1, 2)],
        [c(), c(), c(), c(), c(), c()],
        [c(), c(), c(), c(), c(), c(), c(), c()],
    ]);
    assert.deepEqual(m.inicio[0], [0, 1, 4, 7]);
    assert.deepEqual(m.inicio[1], [1, 2, 3, 4, 5, 6]);
    assert.deepEqual(m.inicio[2], [0, 1, 2, 3, 4, 5, 6, 7]);
    assert.equal(m.total, 8);
});

test('contarValores: "(vacío)" primero, resto alfabético es y con conteo', () => {
    assert.deepEqual(contarValores(['b', '', 'Á', 'a', 'b ', '  ']), [
        { valor: VACIO, conteo: 2 },
        { valor: 'Á', conteo: 1 },
        { valor: 'a', conteo: 1 },
        { valor: 'b', conteo: 2 },
    ]);
    assert.equal(claveValor('  x '), 'x');
});

test('filaPasa: filtros de texto (contiene, sin mayúsculas) y por valores (exacto)', () => {
    const fila = ['201', 'OP-55', 'Toalla Felpa', ''];
    assert.equal(filaPasa(fila, { texto: {}, valores: {} }), true);
    assert.equal(filaPasa(fila, { texto: { 2: 'felpa' }, valores: {} }), true);
    assert.equal(filaPasa(fila, { texto: { 2: 'liso' }, valores: {} }), false);
    assert.equal(filaPasa(fila, { texto: { 2: '  ' }, valores: {} }), true);
    assert.equal(filaPasa(fila, { texto: {}, valores: { 0: ['201', '202'] } }), true);
    assert.equal(filaPasa(fila, { texto: {}, valores: { 0: ['202'] } }), false);
    assert.equal(filaPasa(fila, { texto: {}, valores: { 3: [VACIO] } }), true);
    assert.equal(filaPasa(fila, { texto: {}, valores: { 9: [VACIO] } }), true, 'columna oculta/inexistente = vacío');
});

test('aplicarSeleccion: todos o ninguno quita el filtro de esa columna', () => {
    assert.deepEqual(aplicarSeleccion({ 1: ['x'] }, 2, ['a'], 3), { 1: ['x'], 2: ['a'] });
    assert.deepEqual(aplicarSeleccion({ 2: ['a'] }, 2, ['a', 'b', 'c'], 3), {});
    assert.deepEqual(aplicarSeleccion({ 2: ['a'] }, 2, [], 3), {});
});

const F = (id, esGrupo, lider, extra = {}) => ({ id, esGrupo, lider, noTelar: '', ordCompartida: '', ...extra });

test('bloques y visibilidad: si una fila del grupo pasa, se ve el grupo completo', () => {
    const filas = [F('a', false, true), F('L', true, true), F('n1', true, false), F('n2', true, false), F('b', false, true)];
    assert.deepEqual(bloques(filas).map((b) => b.map((f) => f.id)), [['a'], ['L', 'n1', 'n2'], ['b']]);
    assert.deepEqual(visibilidadConGrupos(filas, (f) => f.id === 'n2'), [false, true, true, true, false]);
    assert.deepEqual(visibilidadConGrupos(filas, (f) => f.id === 'a'), [true, false, false, false, false]);
});

test('ordenar: numérico con comas, alfabético es-MX, los no-líderes viajan con su líder', () => {
    assert.ok(compararTextos('1,200', '300') > 0);
    assert.ok(compararTextos('1,200', '300', 'desc') < 0);
    assert.ok(compararTextos('ábaco', 'beta') < 0);
    const filas = [F('x', false, true, { t: '30' }), F('L', true, true, { t: '5' }), F('n', true, false, { t: '999' }), F('y', false, true, { t: '10' })];
    assert.deepEqual(ordenarPorBloques(filas, (f) => f.t, 'asc').map((f) => f.id), ['L', 'n', 'y', 'x']);
    assert.deepEqual(ordenarPorBloques(filas, (f) => f.t, 'desc').map((f) => f.id), ['x', 'y', 'L', 'n']);
});

test('separadoresAntes: cambia el telar salvo dentro de la misma orden compartida', () => {
    const filas = [
        F('1', false, true, { noTelar: '201' }),
        F('2', false, true, { noTelar: '201' }),
        F('3', true, true, { noTelar: '202', ordCompartida: '77' }),
        F('4', true, false, { noTelar: '305', ordCompartida: '77' }),
        F('5', false, true, { noTelar: '306' }),
    ];
    assert.deepEqual(separadoresAntes(filas), [2, 4]);
});

test('posicionMenu mantiene el menú dentro del viewport y tituloBotonFiltro', () => {
    assert.deepEqual(posicionMenu(100, 100, 1280, 800), { x: 100, y: 100 });
    assert.deepEqual(posicionMenu(1200, 700, 1280, 800), { x: 1056, y: 456 });
    assert.equal(tituloBotonFiltro(0), 'Filtrar por columna');
    assert.equal(tituloBotonFiltro(2), 'Hay 2 filtro(s) activo(s). Clic para limpiar.');
});
