import assert from 'node:assert/strict'
import test from 'node:test'

import { configuracionGrafica } from '../../resources/js/modulos/atadores/reportes/km/grafica.ts'
import { LIMITE_MS, estadoDelSondeo, htmlConfirmacion } from '../../resources/js/modulos/atadores/reportes/oee/logica.ts'

test('gráfica KM: solo filas con efectividad, etiqueta con KM y barra', () => {
  const cfg = configuracionGrafica([
    { etiqueta: 'T1 1001', km: 'KM1', barra: 3, efectividad: 80, enhebrado_min: 50, ideal_min: 40 },
    { etiqueta: 'T2 1002', km: 'KM2', barra: 1, efectividad: null, enhebrado_min: 0, ideal_min: 40 },
  ])
  assert.deepEqual(cfg.data.labels, ['T1 1001 · KM1 B3'])
  assert.deepEqual(cfg.data.datasets.map((d) => d.data), [[80], [50], [40]])
  assert.equal(cfg.data.datasets[0].yAxisID, 'pct')
})

test('OEE: confirmación escapa las semanas y avisa las que se sobreescriben', () => {
  const html = htmlConfirmacion(['2026-W38', '<b>'], [])
  assert.match(html, /2026-W38, &lt;b&gt;/)
  assert.doesNotMatch(html, /sobreescritas/)
  assert.match(htmlConfirmacion(['S1'], ['S1']), /S1<\/strong> ya tienen datos y serán sobreescritas/)
})

test('OEE: el sondeo termina en listo, error o tiempo agotado', () => {
  assert.equal(estadoDelSondeo('completado', 1), 'listo')
  assert.equal(estadoDelSondeo('error', 1), 'error')
  assert.equal(estadoDelSondeo('despachado', 1000), 'seguir')
  assert.equal(estadoDelSondeo(undefined, LIMITE_MS + 1), 'agotado')
})
