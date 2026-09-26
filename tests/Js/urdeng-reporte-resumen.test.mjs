import test from 'node:test';
import assert from 'node:assert/strict';
import { configEficiencia, configPromedios } from '../../resources/js/modulos/urdido/comun/reporte-resumen/logica.ts';

const datos = [
    { semana_label: 'SEM-36-2026', peso_promedio: 120.5, metros_promedio: '800', cuenta_promedio: 3200, eficiencia: 85.2 },
    { semana_label: 'SEM-37-2026', peso_promedio: 0, metros_promedio: 10, cuenta_promedio: null, eficiencia: null },
];

test('promedios: barras con las 3 series en orden y números', () => {
    const c = configPromedios(datos);
    assert.equal(c.type, 'bar');
    assert.deepEqual(c.data.labels, ['SEM-36-2026', 'SEM-37-2026']);
    assert.deepEqual(c.data.datasets.map((d) => d.label), ['Peso Promedio x Julio', 'Metros Promedio x Julio', 'Cuenta Promedio x Julio']);
    assert.deepEqual(c.data.datasets[1].data, [800, 10]);
    assert.deepEqual(c.data.datasets[2].data, [3200, 0]);
    assert.equal(c.data.datasets[0].backgroundColor, 'rgba(54, 162, 235, 0.5)');
});

test('eficiencia: línea 0-100 con % en el eje y null → 0', () => {
    const c = configEficiencia(datos);
    assert.equal(c.type, 'line');
    assert.deepEqual(c.data.datasets[0].data, [85.2, 0]);
    assert.equal(c.options.scales.y.max, 100);
    assert.equal(c.options.scales.y.ticks.callback(50), '50%');
});

test('sin datos: series vacías', () => {
    assert.deepEqual(configPromedios([]).data.labels, []);
    assert.deepEqual(configEficiencia([]).data.datasets[0].data, []);
});
