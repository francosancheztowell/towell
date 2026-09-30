import assert from 'node:assert/strict'
import test from 'node:test'

import {
  MERMA_MAX, claveOperador, evaluarMermaTecleada, julioASeleccionar, mermaParaGuardar,
  normalizarMerma, payloadDevolucion, puedeDesmarcar, revisarParaTerminar, textoMerma,
} from '../../resources/js/modulos/atadores/calificar/logica.ts'

const listo = () => ({
  esKm: false,
  maquinas: [true, false],
  actividades: [true, true],
  devolucion: false,
  filasKm: [],
  dev: { julio: '', ubicacion: '', metros: '', kilos: '' },
  merma: '1.5',
})

test('merma: dos decimales y tope de 5 kg', () => {
  assert.equal(MERMA_MAX, 5)
  assert.equal(normalizarMerma(1.235), 1.24)
  assert.equal(textoMerma(2), '2')
  assert.equal(evaluarMermaTecleada(''), 'vacia')
  assert.equal(evaluarMermaTecleada('5.01'), 'excede')
  assert.equal(evaluarMermaTecleada('4'), 'programar')
  assert.equal(evaluarMermaTecleada('abc'), 'programar', 'lo inválido lo avisa el guardado')
  assert.deepEqual(mermaParaGuardar('1.237'), { valor: 1.24 })
  assert.equal(mermaParaGuardar('x').aviso.titulo, 'Valor inválido')
  assert.equal(mermaParaGuardar('6').aviso.titulo, 'Merma fuera de rango')
})

test('terminar: checklist Jacquard en el orden de siempre', () => {
  assert.equal(revisarParaTerminar(listo()), null)
  assert.equal(revisarParaTerminar({ ...listo(), maquinas: [false] }).titulo, 'Máquinas pendientes')
  assert.equal(revisarParaTerminar({ ...listo(), maquinas: [] }).titulo, 'Máquinas pendientes')
  assert.equal(revisarParaTerminar({ ...listo(), actividades: [true, false] }).titulo, 'Actividades pendientes')
  assert.equal(revisarParaTerminar({ ...listo(), actividades: [] }).titulo, 'Actividades pendientes')
  // Karl Mayer no usa el checklist.
  assert.equal(revisarParaTerminar({ ...listo(), esKm: true, maquinas: [], actividades: [] }), null)
})

test('terminar: devolución Jacquard pide julio, ubicación, metros y kilos', () => {
  const dev = (d) => revisarParaTerminar({ ...listo(), devolucion: true, dev: { julio: 'J1', ubicacion: 'KM1', metros: '10', kilos: '5', ...d } })
  assert.equal(dev({}), null)
  const sinJulio = dev({ julio: '' })
  assert.equal(sinJulio.titulo, 'Julio pendiente')
  assert.equal(sinJulio.foco, 'dev_no_julio')
  assert.equal(dev({ ubicacion: '' }).titulo, 'Ubicación pendiente')
  assert.equal(dev({ metros: '0' }).titulo, 'Devolución incompleta')
  assert.equal(dev({ kilos: '' }).titulo, 'Devolución incompleta')
})

test('terminar: devolución Karl Mayer revisa cada julio del atado anterior', () => {
  const km = (filasKm) => revisarParaTerminar({ ...listo(), esKm: true, devolucion: true, filasKm })
  assert.equal(km([]).titulo, 'Sin atado anterior')
  assert.equal(km([{ julio: 'A', metros: '1', kilos: '1' }, { julio: 'B', metros: '1', kilos: '0' }]).titulo, 'Devolución incompleta')
  assert.equal(km([{ julio: 'A', metros: '1', kilos: '1' }]), null)
})

test('terminar: merma obligatoria y en rango', () => {
  assert.equal(revisarParaTerminar({ ...listo(), merma: ' ' }).titulo, 'Merma pendiente')
  const fuera = revisarParaTerminar({ ...listo(), merma: '9' })
  assert.equal(fuera.titulo, 'Merma fuera de rango')
  assert.equal(fuera.foco, 'mergaKg')
})

test('actividades: solo quien marcó puede desmarcar', () => {
  assert.equal(claveOperador('1001 - Ana'), '1001')
  assert.equal(claveOperador('-'), null)
  assert.equal(claveOperador('Ana'), null)
  assert.equal(puedeDesmarcar('1001 - Ana', '1001'), true)
  assert.equal(puedeDesmarcar('1002 - Luis', '1001'), false)
  assert.equal(puedeDesmarcar('-', '1001'), true)
  assert.equal(puedeDesmarcar('1002 - Luis', null), true)
})

test('devolución Jacquard: vacíos a null y números a float', () => {
  const p = payloadDevolucion(7, { dev_telar: '202', dev_no_julio: 'J-1', dev_kilos: '12.5', dev_metros: '', dev_lote: 'DEV1' })
  assert.equal(p.ref_id, 7)
  assert.equal(p.telar, '202')
  assert.equal(p.no_produccion, 'DEV1')
  assert.equal(p.kilos, 12.5)
  assert.equal(p.metros, null)
  assert.equal(p.ubicacion, null)
})

test('julio a seleccionar: el actual, luego el guardado, luego el sugerido', () => {
  assert.equal(julioASeleccionar('J1', 'J2', 'J3'), 'J1')
  assert.equal(julioASeleccionar(' ', 'J2', 'J3'), 'J2')
  assert.equal(julioASeleccionar('', null, 'J3'), 'J3')
  assert.equal(julioASeleccionar('', undefined, null), '')
})
