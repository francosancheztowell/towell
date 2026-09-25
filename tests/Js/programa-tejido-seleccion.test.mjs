/**
 * window.selectedRowIndex sigue a la FILA, no a la posicion (04-perf, corte 6).
 *
 * Antes era un numero congelado al hacer clic: tras insertar una fila antes, borrar
 * otra o reordenar, apuntaba a otra fila y Editar/Eliminar/Ver lineas actuaban sobre
 * ella.
 */
import assert from 'node:assert/strict'
import test from 'node:test'

import { instalarIndiceSeleccion } from '../../resources/js/programa-tejido/seleccion.ts'

const fila = (id) => ({ id, isConnected: true })

function grilla(...ids) {
	const estado = { allRows: ids.map(fila) }
	instalarIndiceSeleccion(estado, () => estado.allRows)
	return estado
}

test('sin seleccion vale -1 y asignar -1 o null limpia', () => {
	const g = grilla('a', 'b')
	assert.equal(g.selectedRowIndex, -1)
	g.selectedRowIndex = 1
	assert.equal(g.selectedRowIndex, 1)
	g.selectedRowIndex = -1
	assert.equal(g.selectedRowIndex, -1)
	g.selectedRowIndex = 0
	g.selectedRowIndex = null
	assert.equal(g.selectedRowIndex, -1)
})

test('insertar una fila antes de la seleccionada no la cambia', () => {
	const g = grilla('a', 'b', 'c')
	g.selectedRowIndex = 1 // b
	g.allRows = [fila('nueva'), ...g.allRows]
	assert.equal(g.selectedRowIndex, 2)
	assert.equal(g.allRows[g.selectedRowIndex].id, 'b')
})

test('borrar otra fila antes de la seleccionada no la cambia', () => {
	const g = grilla('a', 'b', 'c')
	g.selectedRowIndex = 2 // c
	g.allRows = g.allRows.filter((r) => r.id !== 'a')
	assert.equal(g.allRows[g.selectedRowIndex].id, 'c')
})

test('reordenar (arrastrar) conserva la fila seleccionada', () => {
	const g = grilla('a', 'b', 'c')
	g.selectedRowIndex = 0 // a
	g.allRows = [...g.allRows].reverse()
	assert.equal(g.selectedRowIndex, 2)
	assert.equal(g.allRows[g.selectedRowIndex].id, 'a')
})

test('si la fila seleccionada sale del DOM, no hay seleccion', () => {
	const g = grilla('a', 'b')
	g.selectedRowIndex = 0
	g.allRows[0].isConnected = false
	assert.equal(g.selectedRowIndex, -1)
})

test('un indice fuera de rango no selecciona nada', () => {
	const g = grilla('a')
	g.selectedRowIndex = 5
	assert.equal(g.selectedRowIndex, -1)
})
