import test from 'node:test';
import assert from 'node:assert/strict';
import {
    configEficiencia,
    configEventos,
    configMenciones,
    configRpm,
    etiquetas,
    hayMenciones,
    opcionesBase,
    valor,
} from '../../resources/js/modulos/urdido/panel-control/logica.ts';

const semanas = [
    { semana: 1, efic: '80.5', est: 90, rpm: 400, rpm_est: null, eventos: 3 },
    { semana: 2, efic: null, est: 90, rpm: '410', rpm_est: 420, eventos: null },
];

test('valor: null/undefined cortan la línea, lo demás a número', () => {
    assert.equal(valor(null), null);
    assert.equal(valor(undefined), null);
    assert.equal(valor('12.5'), 12.5);
    assert.equal(valor(0), 0);
});

test('etiquetas S<n>', () => {
    assert.deepEqual(etiquetas(semanas), ['S1', 'S2']);
});

test('eficiencia y rpm conservan huecos; eventos vacíos a 0 con enteros en el eje', () => {
    assert.deepEqual(configEficiencia(semanas).data.datasets[0].data, [80.5, null]);
    assert.deepEqual(configRpm(semanas).data.datasets[1].data, [null, 420]);
    const ev = configEventos(semanas);
    assert.deepEqual(ev.data.datasets[0].data, [3, 0]);
    assert.equal(ev.options.scales.y.ticks.precision, 0);
    // opcionesBase devuelve un objeto nuevo cada vez: eventos no contamina a las demás.
    assert.equal(configRpm(semanas).options.scales.y.ticks.precision, undefined);
});

test('menciones: horizontal, sin leyenda, solo si hay menciones', () => {
    const cats = [{ categoria: 'Paro', menciones: 2 }, { categoria: 'Otro', menciones: 0 }];
    assert.equal(hayMenciones(cats), true);
    assert.equal(hayMenciones([{ categoria: 'X', menciones: 0 }]), false);
    const c = configMenciones(cats);
    assert.equal(c.options.indexAxis, 'y');
    assert.equal(c.options.plugins.legend.display, false);
    assert.deepEqual(c.data.labels, ['Paro', 'Otro']);
    assert.equal(c.options.scales.x.ticks.precision, 0);
});

test('opciones base: título navy y altura libre', () => {
    const o = opcionesBase('T');
    assert.equal(o.maintainAspectRatio, false);
    assert.equal(o.plugins.title.text, 'T');
    assert.equal(o.plugins.title.color, '#1F3864');
});
