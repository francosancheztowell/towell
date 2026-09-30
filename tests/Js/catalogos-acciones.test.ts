// Runtime de components/buttons/catalog-actions.blade.php (resources/js/catalogos/catalog-actions.ts).
import assert from 'node:assert/strict'
import test from 'node:test'

import {
  esAccion,
  nombrePuente,
  registrarAccionesCatalogo,
  resolverAccion,
  resumenCargaExcel,
  type HandlersCatalogo,
} from '../../resources/js/catalogos/catalog-actions.ts'

test('nombrePuente reproduce las funciones globales que llamaban los onclick', () => {
  assert.equal(nombrePuente('agregar', 'Codificacion'), 'agregarCodificacion')
  assert.equal(nombrePuente('restablecer', 'Pesos_rollos'), 'limpiarFiltrosPesos_rollos')
  assert.equal(nombrePuente('subir-excel', 'Telares'), 'subirExcelTelares')
  assert.equal(nombrePuente('recalcular', 'Calendarios'), 'recalcularProgramasCalendarioNavbar')
  assert.equal(nombrePuente('eliminar-rango', 'Calendarios'), 'eliminarCalendariosPorRango')
  assert.equal(esAccion('agregar'), true)
  assert.equal(esAccion('toString'), false)
  assert.equal(esAccion(undefined), false)
})

test('resolverAccion: primero el handler registrado, luego el puente window, si no null', () => {
  const propio = () => 'propio'
  const puente = () => 'puente'
  const registrados = new Map<string, HandlersCatalogo>([['telares', { agregar: propio }]])
  const cfg = { ruta: 'telares', routeJs: 'Telares' }
  assert.equal(resolverAccion(cfg, 'agregar', registrados, { agregarTelares: puente }), propio)
  assert.equal(resolverAccion(cfg, 'editar', registrados, { editarTelares: puente }), puente)
  assert.equal(resolverAccion(cfg, 'eliminar', registrados, { eliminarTelares: 'no-es-función' }), null)
  assert.equal(resolverAccion({ ruta: 'otra', routeJs: 'Otra' }, 'agregar', registrados, {}), null)
})

test('registrarAccionesCatalogo acumula handlers de la misma ruta', () => {
  assert.doesNotThrow(() => {
    registrarAccionesCatalogo('x', { agregar: () => 1 })
    registrarAccionesCatalogo('x', { editar: () => 2 })
  })
})

test('resumenCargaExcel: acepta llaves en español y de getStats(), lista errores y avisa', () => {
  const ok = resumenCargaExcel('a.xlsx', { data: { registros_procesados: 3, registros_creados: 2, registros_actualizados: 1, total_errores: 0, errores: [] } })
  assert.equal(ok.icono, 'success')
  assert.match(ok.texto, /Registros procesados: 3/)
  assert.match(ok.texto, /Nuevos registros: 2/)

  const telares = resumenCargaExcel('t.xlsx', { data: { processed_rows: 5, created_rows: 4, updated_rows: 1, errores: [{ fila: 7, error: 'Salón vacío' }] } })
  assert.equal(telares.icono, 'warning')
  assert.match(telares.texto, /Registros procesados: 5/)
  assert.match(telares.texto, /1\. Fila 7: Salón vacío/)

  const muchos = resumenCargaExcel('m.xlsx', { data: { total_errores: 12, errores: Array.from({ length: 10 }, (_, i) => `e${i}`) } })
  assert.match(muchos.texto, /y 2 errores más/)
})
