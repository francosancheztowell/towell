// Lógica pura del catálogo de Calendarios (resources/js/modulos/catalogos-planeacion/calendarios/logica.ts).
import assert from 'node:assert/strict'
import test from 'node:test'

import {
  construirTurnos,
  filtroDuplicado,
  horariosDelDia,
  maximoHoras,
  pasaFiltros,
  plantillaInicial,
  siguienteCalendario,
  textoHora,
  validarLinea,
  validarPlantilla,
  validarRango,
} from '../../resources/js/modulos/catalogos-planeacion/calendarios/logica.ts'

const dia = (h1: number, h2: number, h3: number, activos = [true, true, true]) => ({
  1: { activo: activos[0] ?? true, horas: h1 },
  2: { activo: activos[1] ?? true, horas: h2 },
  3: { activo: activos[2] ?? true, horas: h3 },
})

test('textoHora da vuelta al día y no rellena la hora', () => {
  assert.equal(textoHora(6 * 3600 + 30 * 60), '6:30:00')
  assert.equal(textoHora(86400 + 60), '0:01:00')
  assert.equal(textoHora(-2), '23:59:58')
})

test('horariosDelDia encadena desde las 6:30 y muestra el fin 2 s antes', () => {
  const h = horariosDelDia(dia(8, 8, 8))
  assert.deepEqual(h[1], { inicio: '6:30:00', fin: '14:29:58' })
  assert.deepEqual(h[2], { inicio: '14:30:00', fin: '22:29:58' })
  assert.deepEqual(h[3], { inicio: '22:30:00', fin: '6:29:58' })
  const conHueco = horariosDelDia(dia(8, 0, 4, [true, true, true]))
  assert.equal(conHueco[2], null, 'turno sin horas no tiene horario')
  assert.equal(conHueco[3]?.inicio, '14:30:00', 'el siguiente toma su lugar')
  assert.equal(horariosDelDia(dia(8, 8, 8, [true, false, true]))[3]?.inicio, '14:30:00')
})

test('maximoHoras deja lo que no usan los demás turnos activos', () => {
  assert.equal(maximoHoras(dia(8, 8, 8), 3), 8)
  assert.equal(maximoHoras(dia(10, 10, 0), 3), 4)
  assert.equal(maximoHoras(dia(20, 8, 0, [true, false, true]), 3), 4)
  assert.equal(maximoHoras(dia(20, 10, 0), 3), 0)
})

test('plantillaInicial: alta todo activo con 8 h; edición toma el detalle del servidor', () => {
  assert.deepEqual(plantillaInicial(null)[2].domingo, { activo: true, horas: 8 })
  const p = plantillaInicial({ 1: { lunes: { horas: 6, activo: true }, martes: { horas: 8, activo: false } } })
  assert.deepEqual(p[1].lunes, { activo: true, horas: 6 })
  assert.deepEqual(p[1].martes, { activo: false, horas: 0 })
  assert.deepEqual(p[3].lunes, { activo: false, horas: 0 })
})

test('validarPlantilla: fechas y tope de 24 h por día', () => {
  const p = plantillaInicial(null)
  assert.equal(validarPlantilla('', '2026-01-02', p), 'Por favor completa las fechas')
  assert.equal(validarPlantilla('2026-01-03', '2026-01-02', p), 'La fecha final debe ser posterior o igual a la fecha inicial')
  assert.equal(validarPlantilla('2026-01-01', '2026-01-02', p), null)
  p[1].jueves.horas = 10
  assert.match(validarPlantilla('2026-01-01', '2026-01-02', p) ?? '', /Jueves no puede ser mayor a 24 \(actual: 26\)/)
})

test('construirTurnos manda solo lo activo con horas, con su horario', () => {
  const p = plantillaInicial(null)
  p[2].lunes = { activo: false, horas: 0 }
  p[3].domingo.horas = 0
  const t = construirTurnos(p)
  assert.deepEqual(t[1]?.lunes, { horas: 8, inicio: '6:30:00', fin: '14:29:58', activo: true })
  assert.equal(t[2]?.lunes, undefined)
  assert.equal(t[3]?.lunes?.inicio, '14:30:00')
  assert.equal(t[3]?.domingo, undefined)
})

test('siguienteCalendario sigue la secuencia de Nombre o CalendarioId', () => {
  assert.deepEqual(siguienteCalendario([]), { id: 'Calendario Tej1', nombre: 'Calendario Tejido 1' })
  assert.deepEqual(
    siguienteCalendario([{ CalendarioId: 'Calendario Tej2', Nombre: 'Tejido 3 turnos' }, { CalendarioId: 'X', Nombre: 'Calendario Tejido 7' }]),
    { id: 'Calendario Tej8', nombre: 'Calendario Tejido 8' },
  )
})

test('validarLinea y validarRango', () => {
  const ok = { CalendarioId: 'CAL', FechaInicio: '2026-01-05T06:30', FechaFin: '2026-01-05T14:30', HorasTurno: '8', Turno: '1' }
  assert.equal(validarLinea(ok), null)
  assert.equal(validarLinea({ ...ok, CalendarioId: ' ' }), 'Por favor completa todos los campos')
  assert.equal(validarLinea({ ...ok, CalendarioId: undefined }), null, 'en edición no se pide calendario')
  assert.equal(validarLinea({ ...ok, HorasTurno: '-1' }), 'Las horas deben ser un número válido mayor o igual a 0')
  assert.equal(validarLinea({ ...ok, FechaFin: ok.FechaInicio }), 'La fecha de fin debe ser posterior a la fecha de inicio')
  assert.equal(validarRango('2026-01-05', '2026-01-06', [1]), null)
  assert.equal(validarRango('2026-01-05', '2026-01-05', [1]), 'La fecha de fin debe ser posterior a la fecha de inicio')
  assert.equal(validarRango('2026-01-05', '2026-01-06', []), 'Por favor selecciona al menos un turno para eliminar')
})

test('filtros por columna: contiene sin mayúsculas, por tabla; sin duplicados', () => {
  const filtros = [{ tabla: 'line' as const, columna: 'Turno', valor: '2' }, { tabla: 'tab' as const, columna: 'Nombre', valor: 'tej' }]
  assert.equal(pasaFiltros(['CAL', '05/01/2026 06:30', '05/01/2026 14:30', '8', '2'], 'line', filtros), true)
  assert.equal(pasaFiltros(['CAL', '05/01/2026 06:30', '05/01/2026 14:30', '8', '1'], 'line', filtros), false)
  assert.equal(pasaFiltros(['CAL', 'Tejido 3 turnos'], 'tab', filtros), true)
  assert.equal(filtroDuplicado(filtros, { tabla: 'line', columna: 'Turno', valor: '2' }), true)
  assert.equal(filtroDuplicado(filtros, { tabla: 'tab', columna: 'Turno', valor: '2' }), false)
})
