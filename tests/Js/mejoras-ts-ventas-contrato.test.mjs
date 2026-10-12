// Rama claude/mejora-ts-ventas: dashboard.js, ventas-historicas.js, multi-select.js y
// row-selection.js pasaron a .ts. Se fija:
//   - la vista y vite.config apuntan al .ts que existe (y ninguna vista sigue pidiendo un .js borrado);
//   - los nombres de campo que el TS lee del payload (sf/nf/series) son los que manda PHP
//     (PvVsOcPayloadBuilder y VentasHistoricasPayloadBuilder), porque el TS los lee por nombre;
//   - los cuatro módulos cargan en node (imports y exports nombrados resueltos).
import assert from 'node:assert/strict'
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs'
import { registerHooks } from 'node:module'
import { join } from 'node:path'
import { test } from 'node:test'

const raiz = new URL('../../', import.meta.url)
const leer = (rel) => readFileSync(new URL(rel, raiz), 'utf8')

const constPhp = (php, nombre) => {
  const m = new RegExp(`const ${nombre} = \\[([^\\]]*)\\]`).exec(php)
  assert.ok(m, `no se encontró ${nombre}`)
  return [...m[1].matchAll(/'([^']+)'/g)].map((x) => x[1])
}

test('la vista de Ventas y vite.config piden dashboard.ts y el archivo existe; no quedan .js de ventas', () => {
  const vista = leer('resources/views/livewire/ventas/dashboard-pv-vs-oc.blade.php')
  const vite = leer('vite.config.js')
  const pedidos = [...vista.matchAll(/'(resources\/js\/ventas\/[^']+)'/g)].map((m) => m[1])
  assert.deepEqual(pedidos, ['resources/js/ventas/dashboard.ts'])
  for (const p of pedidos) {
    assert.ok(existsSync(new URL(p, raiz)), `${p} no existe`)
    assert.ok(vite.includes(`'${p}'`), `${p} no es entrada de Vite: @vite fallaría en producción`)
  }
  for (const viejo of ['dashboard', 'ventas-historicas', 'multi-select', 'row-selection']) {
    assert.equal(existsSync(new URL(`resources/js/ventas/${viejo}.js`, raiz)), false, `${viejo}.js debió borrarse`)
  }
  // Ninguna vista Blade sigue pidiendo los .js de ventas.
  const vistas = []
  const recorrer = (dir) => readdirSync(dir).forEach((f) => {
    const p = join(dir, f)
    if (statSync(p).isDirectory()) recorrer(p); else if (p.endsWith('.blade.php')) vistas.push(p)
  })
  recorrer(new URL('resources/views', raiz).pathname.replace(/^\/([A-Za-z]:)/, '$1'))
  const conJs = vistas.filter((v) => /js\/ventas\/[\w-]+\.js/.test(readFileSync(v, 'utf8')))
  assert.deepEqual(conJs, [])
})

test('Compara: los campos que decodePayload lee existen en PvVsOcPayloadBuilder', () => {
  const php = leer('app/Services/Ventas/PvVsOcPayloadBuilder.php')
  const ts = leer('resources/js/ventas/dashboard.ts')
  const sf = constPhp(php, 'SF')
  const nf = constPhp(php, 'NF')
  const series = [.../const SERIES = \[([^\]]*)\]/.exec(php)[1].matchAll(/'(\w+)' =>/g)].map((m) => m[1])
  const sfLeidos = [...ts.matchAll(/field\.(\w+)/g)].map((m) => m[1])
  assert.ok(sfLeidos.length >= 10)
  for (const campo of sfLeidos) assert.ok(sf.includes(campo), `dashboard.ts lee field.${campo} que PHP no manda`)
  // Medidas y series: el TS las tipa como Metric y Series; deben coincidir con las de PHP.
  assert.deepEqual(nf, ['piezas', 'kilos', 'vb', 'desc', 'vn'])
  assert.deepEqual(series, ['plan', 'pedido', 'real'])
  assert.match(ts, /type Metric = 'piezas' \| 'kilos' \| 'vb' \| 'desc' \| 'vn'/)
  assert.match(ts, /type Series = 'plan' \| 'pedido' \| 'real'/)
})

test('Ventas históricas: los campos tipados (Field y medidas) son los SF/NF de VentasHistoricasPayloadBuilder', () => {
  const php = leer('app/Services/Ventas/VentasHistoricasPayloadBuilder.php')
  const ts = leer('resources/js/ventas/ventas-historicas.ts')
  const sf = constPhp(php, 'SF')
  const nf = constPhp(php, 'NF')
  const field = /type Field = ([^;]+);/.exec(ts)[1].match(/'(\w+)'/g).map((s) => s.slice(1, -1))
  assert.deepEqual([...field].sort(), [...sf].sort())
  const medidas = /Record<'piezas' \| 'kilos' \| 'ventasBrutas' \| 'descuentos', number>/.test(ts)
  assert.ok(medidas, 'VhRecord debe tipar las cuatro medidas de NF')
  assert.deepEqual(nf, ['piezas', 'kilos', 'ventasBrutas', 'descuentos'])
})

test('los módulos de Ventas cargan en node con sus exports', async () => {
  // Los imports de ventas no llevan .ts (Vite los resuelve); aquí se resuelven igual.
  registerHooks({
    resolve(especificador, contexto, siguiente) {
      try {
        return siguiente(especificador, contexto)
      } catch (err) {
        if (especificador.startsWith('.') && !/\.\w+$/.test(especificador)) return siguiente(`${especificador}.ts`, contexto)
        throw err
      }
    },
  })
  globalThis.document ??= { querySelectorAll: () => [], addEventListener() {}, getElementById: () => null }
  const ms = await import('../../resources/js/ventas/multi-select.ts')
  assert.equal(typeof ms.createMultiSelect, 'function')
  const rs = await import('../../resources/js/ventas/row-selection.ts')
  assert.equal(typeof rs.bindRowSelection, 'function')
  assert.equal(typeof rs.clearRowSelection, 'function')
  const vh = await import('../../resources/js/ventas/ventas-historicas.ts')
  assert.equal(typeof vh.mountVentasHistoricas, 'function')
  // dashboard.ts se ejecuta al importarse: sin [data-ventas-pvoc-dashboard] no hace nada y no truena.
  await import('../../resources/js/ventas/dashboard.ts')
})
