// Lógica pura de los catálogos de Planeación (19-06b): filtros, reglas por catálogo y acciones del navbar.
import assert from 'node:assert/strict'
import test from 'node:test'

import {
  coincide,
  faltanteObligatorio,
  filtrosActivos,
  leerValores,
  numeroONull,
  opcionesDependientes,
  rangoInvalido,
  type FiltroCatalogo,
} from '../../resources/js/modulos/catalogos-planeacion/comun/logica.ts'
import { nombreDesde, procesarTelar } from '../../resources/js/modulos/catalogos-planeacion/telares/logica.ts'
import { procesarAplicacion } from '../../resources/js/modulos/catalogos-planeacion/aplicaciones/logica.ts'
import { procesarHilo } from '../../resources/js/modulos/catalogos-planeacion/matriz-hilos/logica.ts'
import { procesarPeso, validarPeso } from '../../resources/js/modulos/catalogos-planeacion/pesos-rollos/logica.ts'
import {
  coincideCalibre,
  procesarCalibre,
  reglasPorTipo,
  tiposConActual,
  validarCalibre,
} from '../../resources/js/modulos/catalogos-planeacion/matriz-calibres/logica.ts'
import {
  aFormularioEstandar,
  procesarEstandar,
  validarEstandar,
} from '../../resources/js/modulos/catalogos-planeacion/estandar/logica.ts'

const FILTROS_EFICIENCIA: FiltroCatalogo[] = [
  { nombre: 'salon', campo: 'SalonTejidoId', etiqueta: 'Salón', modo: 'contiene' },
  { nombre: 'densidad', campo: 'Densidad', etiqueta: 'Densidad', modo: 'igual', porDefecto: 'Normal' },
  { nombre: 'eficiencia_min', campo: 'Eficiencia', etiqueta: 'Eficiencia Mínima (%)', modo: 'min', escala: 100 },
  { nombre: 'eficiencia_max', campo: 'Eficiencia', etiqueta: 'Eficiencia Máxima (%)', modo: 'max', escala: 100 },
]

test('coincide: contiene sin mayúsculas, igual con valor por defecto y rangos escalados', () => {
  const fila = { SalonTejidoId: 'JACQUARD', Densidad: null, Eficiencia: 0.85 }
  assert.equal(coincide(fila, FILTROS_EFICIENCIA, {}), true, 'sin filtros pasa todo')
  assert.equal(coincide(fila, FILTROS_EFICIENCIA, { salon: 'jacq' }), true)
  assert.equal(coincide(fila, FILTROS_EFICIENCIA, { salon: 'smith' }), false)
  assert.equal(coincide(fila, FILTROS_EFICIENCIA, { densidad: 'Normal' }), true, 'Densidad vacía cuenta como Normal')
  assert.equal(coincide(fila, FILTROS_EFICIENCIA, { densidad: 'Alta' }), false)
  assert.equal(coincide(fila, FILTROS_EFICIENCIA, { eficiencia_min: '80', eficiencia_max: '90' }), true)
  assert.equal(coincide(fila, FILTROS_EFICIENCIA, { eficiencia_min: '86' }), false)
  assert.equal(coincide(fila, FILTROS_EFICIENCIA, { eficiencia_max: '84' }), false)
  assert.equal(coincide(fila, FILTROS_EFICIENCIA, { salon: '   ' }), true, 'solo espacios = sin filtro')
})

test('filtrosActivos cuenta los no vacíos y rangoInvalido detecta min > max', () => {
  assert.equal(filtrosActivos({ a: 'x', b: '', c: ' ' }), 1)
  assert.match(rangoInvalido(FILTROS_EFICIENCIA, { eficiencia_min: '90', eficiencia_max: '10' }) ?? '', /no puede ser mayor/)
  assert.equal(rangoInvalido(FILTROS_EFICIENCIA, { eficiencia_min: '10', eficiencia_max: '90' }), null)
  assert.equal(rangoInvalido(FILTROS_EFICIENCIA, { eficiencia_min: '10' }), null)
})

test('leerValores tolera JSON roto y opcionesDependientes devuelve textos', () => {
  assert.deepEqual(leerValores('{"Id":1,"Hilo":"H-1"}'), { Id: 1, Hilo: 'H-1' })
  assert.deepEqual(leerValores('no-json'), {})
  assert.deepEqual(leerValores('[1,2]'), {})
  assert.deepEqual(leerValores(undefined), {})
  assert.deepEqual(opcionesDependientes({ SMITH: [299, 300] }, 'SMITH'), ['299', '300'])
  assert.deepEqual(opcionesDependientes({ SMITH: [299] }, 'JACQUARD'), [])
})

test('faltanteObligatorio usa la etiqueta y numeroONull limpia números', () => {
  const campos = [{ nombre: 'Hilo', tipo: 'text', requerido: true, etiqueta: 'Hilo' }, { nombre: 'N1', tipo: 'number' }]
  assert.equal(faltanteObligatorio(campos, { Hilo: ' ' }), 'Hilo es obligatorio')
  assert.equal(faltanteObligatorio(campos, { Hilo: 'H' }), null)
  assert.equal(numeroONull(''), null)
  assert.equal(numeroONull('1.5'), 1.5)
  assert.equal(numeroONull('abc'), null)
})

test('Telares: nombre sugerido como el servidor (makeName) y se completa si viene vacío', () => {
  assert.equal(nombreDesde('Jacquard', '200'), 'JAC 200')
  assert.equal(nombreDesde('SMITH', '305'), 'Smith 305')
  assert.equal(nombreDesde('itema', '7'), 'ITE 7')
  assert.equal(procesarTelar({ SalonTejidoId: 'JACQUARD', NoTelarId: '201', Nombre: '' }).Nombre, 'JAC 201')
  assert.equal(procesarTelar({ SalonTejidoId: 'JACQUARD', NoTelarId: '201', Nombre: 'Mío' }).Nombre, 'Mío')
})

test('Aplicaciones: Factor vacío no se envía', () => {
  assert.deepEqual(procesarAplicacion({ AplicacionId: 'A', Nombre: 'B', Factor: '' }), { AplicacionId: 'A', Nombre: 'B' })
  assert.deepEqual(procesarAplicacion({ AplicacionId: 'A', Nombre: 'B', Factor: '1.5' }), { AplicacionId: 'A', Nombre: 'B', Factor: '1.5' })
})

test('Matriz de Hilos: números a número o null, texto vacío a null salvo Hilo', () => {
  assert.deepEqual(procesarHilo({ Hilo: 'H-1', Calibre: '12.5', Calibre2: '', Fibra: '', N1: 'x' }), {
    Hilo: 'H-1', Calibre: 12.5, Calibre2: null, Fibra: null, N1: null,
  })
})

test('Pesos por Rollos: peso numérico >= 0', () => {
  assert.equal(validarPeso({ PesoRollo: '-1' }), 'El peso debe ser un número válido mayor o igual a 0')
  assert.equal(validarPeso({ PesoRollo: 'abc' }), 'El peso debe ser un número válido mayor o igual a 0')
  assert.equal(validarPeso({ PesoRollo: '0' }), null)
  assert.deepEqual(procesarPeso({ ItemId: ' IT ', PesoRollo: '25.5' }), { ItemId: 'IT', PesoRollo: 25.5 })
})

// Antes tests/Js/matriz-calibres-catalog.test.cjs sobre public/js/catalogs/MatrizCalibresCatalog.js.
test('Matriz de Calibres: las barras exigen cuenta, fibra y calibre; trama no lleva cuenta; PIE uno de los dos', () => {
  const salida = { ItemId: 'JULIO-URDIDO', ConfigId: 'ALG-OPEN', InventSizeId: '2028-370/1', InventColorId: '1000' }
  assert.match(validarCalibre({ Tipo: 'BARRA2', Calibre: '75', FibraId: 'FIL', Cuenta: '', ...salida }) ?? '', /Cuenta/)
  assert.match(validarCalibre({ Tipo: 'BARRA2', Calibre: '', FibraId: 'FIL', Cuenta: '2104', ...salida }) ?? '', /Fibra y Calibre/)
  assert.equal(validarCalibre({ Tipo: 'BARRA2', Calibre: '75', FibraId: 'FIL', Cuenta: '2104', ...salida }), null)
  assert.equal(validarCalibre({ Tipo: 'TRAMA', Calibre: '75', FibraId: 'FIL', Cuenta: '', ...salida }), null)
  assert.match(validarCalibre({ Tipo: 'PIE', Calibre: '', FibraId: '', Cuenta: '1', ...salida }) ?? '', /al menos Fibra o Calibre/)
  assert.equal(validarCalibre({ Tipo: 'PIE', Calibre: '', FibraId: 'FIL', Cuenta: '1', ...salida }), null)
  assert.match(validarCalibre({ Tipo: 'RIZO', Calibre: '1', FibraId: 'F', Cuenta: '1', ...salida, ItemId: '' }) ?? '', /ItemId/)
})

test('Matriz de Calibres: procesar normaliza (mayúsculas, trama sin cuenta, calibre a un decimal)', () => {
  assert.deepEqual(procesarCalibre({ Tipo: 'trama', FibraId: 'fil', Cuenta: '2104', Calibre: '74.96' }), {
    Tipo: 'TRAMA', FibraId: 'FIL', Cuenta: null, Calibre: 75,
  })
  assert.deepEqual(procesarCalibre({ Tipo: 'PIE', FibraId: '', Cuenta: 'a1', Calibre: '' }), {
    Tipo: 'PIE', FibraId: null, Cuenta: 'A1', Calibre: null,
  })
  assert.deepEqual(reglasPorTipo('TRAMA'), { cuentaHabilitada: false, calibreRequerido: true })
  assert.deepEqual(reglasPorTipo('PIE'), { cuentaHabilitada: true, calibreRequerido: false })
})

test('Matriz de Calibres: un tipo desconocido no se pierde al editar; búsqueda local', () => {
  const tipos = ['RIZO', 'PIE', 'TRAMA', 'BARRA1', 'BARRA2', 'BARRA3', 'BARRA4']
  assert.deepEqual(tiposConActual(tipos, 'otro'), [...tipos, 'OTRO'])
  assert.deepEqual(tiposConActual(tipos, 'BARRA3'), tipos)
  const fila = { Id: 9, Tipo: 'RIZO', Calibre: 12, ItemId: 'JULIO-URDIDO' }
  assert.equal(coincideCalibre(fila, '', ''), true)
  assert.equal(coincideCalibre(fila, 'julio', 'RIZO'), true)
  assert.equal(coincideCalibre(fila, 'julio', 'PIE'), false)
  assert.equal(coincideCalibre(fila, 'nada', ''), false)
})

test('Eficiencia/Velocidad: % ↔ 0..1 y validación por variante', () => {
  assert.equal(aFormularioEstandar('eficiencia', { Eficiencia: 0.855 }).Eficiencia, 86)
  assert.equal(aFormularioEstandar('velocidad', { Velocidad: 180 }).Velocidad, 180)
  assert.equal(validarEstandar('eficiencia', { Eficiencia: '101' }), 'La eficiencia debe estar entre 0% y 100%')
  assert.equal(validarEstandar('eficiencia', { Eficiencia: '78' }), null)
  assert.equal(validarEstandar('velocidad', { Velocidad: '' }), 'La velocidad debe ser un número válido')
  assert.equal(validarEstandar('velocidad', { Velocidad: '850' }), null)
  assert.deepEqual(procesarEstandar('eficiencia', { SalonTejidoId: 'SMITH', NoTelarId: '300', FibraId: ' H ', Densidad: '', Eficiencia: '78' }), {
    SalonTejidoId: 'SMITH', NoTelarId: '300', FibraId: 'H', Densidad: 'Normal', Eficiencia: 0.78,
  })
  assert.deepEqual(procesarEstandar('velocidad', { SalonTejidoId: 'SMITH', NoTelarId: '300', FibraId: 'H', Densidad: 'Alta', Velocidad: '850' }), {
    SalonTejidoId: 'SMITH', NoTelarId: '300', FibraId: 'H', Densidad: 'Alta', Velocidad: 850,
  })
})
