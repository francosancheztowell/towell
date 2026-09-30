/**
 * PT 03 · shell v2: mensaje del evento `programa-tejido-error` que despacha ProgramaTejidoBoard.
 * Livewire manda los parámetros con nombre como objeto; versiones viejas, como arreglo.
 */
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { mensajeDeError, MENSAJE_POR_DEFECTO } from '../../resources/js/modulos/programa-tejido-v2/logica.ts'

test('toma el mensaje del detalle (objeto o arreglo)', () => {
  assert.equal(mensajeDeError({ mensaje: 'Sin conexión a BD' }), 'Sin conexión a BD')
  assert.equal(mensajeDeError([{ mensaje: 'Otro' }]), 'Otro')
})

test('sin mensaje utilizable cae al texto por defecto', () => {
  for (const detalle of [null, undefined, {}, { mensaje: '  ' }, { mensaje: 42 }, 'texto', []]) {
    assert.equal(mensajeDeError(detalle), MENSAJE_POR_DEFECTO)
  }
})
