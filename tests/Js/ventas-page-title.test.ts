// Ventas: el título del navbar sigue a la subsección elegida en la sidebar.
import assert from 'node:assert/strict';
import test from 'node:test';

import { pageTitle } from '../../resources/js/ventas/page-title.ts';

const BASE = 'Plan de Ventas vs OC vs Real';

test('Resumen General keeps the dashboard title', () => {
    assert.equal(pageTitle('resumen', 'Resumen General', BASE), BASE);
});

test('Every other subsection shows its own name', () => {
    assert.equal(pageTitle('analisis', 'Análisis Histórico', BASE), 'Análisis Histórico');
    assert.equal(pageTitle('pedidos', 'Pedidos Históricos', BASE), 'Pedidos Históricos');
    assert.equal(pageTitle('clientes', '  Ventas por cliente \n', BASE), 'Ventas por cliente');
});

test('An empty label falls back to the dashboard title', () => {
    assert.equal(pageTitle('analisis', '   ', BASE), BASE);
});
