// Rama claude/mejora-ts-programa-tejido-charts: index.js -> index.ts y adiós a charts.js.
//
// Riesgo de pasar a .ts: esbuild/Vite borran los `import { x }` que creen "solo de tipos"
// si nadie usa x en valor. Varios módulos del bundle (boot.ts, balancear.ts, modales/*)
// importan por su efecto (publican window.*): si un import se pierde al compilar, la página
// queda sin funciones y ningún typecheck lo nota. Aquí se compila igual que Vite y se
// comprueba que todo import del fuente sigue en la salida y en el mismo orden.
import assert from 'node:assert/strict'
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs'
import { join } from 'node:path'
import test from 'node:test'

import { transformSync } from 'esbuild'

const raiz = new URL('../../', import.meta.url)
const leer = (ruta) => readFileSync(new URL(ruta, raiz), 'utf8')

const especificadores = (codigo) =>
  [...codigo.matchAll(/^\s*import\s+(?:[\s\S]*?\sfrom\s+)?['"]([^'"]+)['"]/gm)].map((m) => m[1])

test('compilado como en Vite, index.ts conserva todos sus imports y en el mismo orden', () => {
  const fuente = leer('resources/js/programa-tejido/index.ts')
  const tsconfig = JSON.parse(leer('tsconfig.json').replace(/^\s*\/\/.*$/gm, ''))
  const { code } = transformSync(fuente, {
    loader: 'ts',
    format: 'esm',
    target: 'es2022',
    tsconfigRaw: { compilerOptions: tsconfig.compilerOptions },
  })

  const antes = especificadores(fuente)
  const despues = especificadores(code)
  assert.ok(antes.length >= 10, 'se esperaban los imports del bundle')
  assert.deepEqual(despues, antes)
  // boot.ts va primero: marbetes/recalcular leen PT_BOOT al evaluarse.
  assert.equal(antes[0], './boot.ts')
})

test('todo import relativo de index.ts existe en disco (no quedó apuntando a un .js borrado)', () => {
  const fuente = leer('resources/js/programa-tejido/index.ts')
  for (const spec of especificadores(fuente).filter((s) => s.startsWith('.'))) {
    const destino = new URL(spec, new URL('resources/js/programa-tejido/', raiz))
    assert.ok(existsSync(destino), `falta ${spec}`)
  }
  assert.equal(existsSync(new URL('resources/js/programa-tejido/index.js', raiz)), false, 'quedó el index.js viejo junto al .ts')
})

test('charts.js ya no existe ni se pide desde ninguna vista o bundle', () => {
  assert.equal(existsSync(new URL('resources/js/charts.js', raiz)), false)
  assert.equal(existsSync(new URL('resources/js/charts.ts', raiz)), false)
  assert.doesNotMatch(leer('vite.config.js'), /resources\/js\/charts/)

  const culpables = []
  const recorrer = (dir) => {
    for (const nombre of readdirSync(dir)) {
      const ruta = join(dir, nombre)
      if (statSync(ruta).isDirectory()) recorrer(ruta)
      else if (/\.(php|ts|js|mjs)$/.test(nombre)) {
        // Sin comentarios: los que cuentan la historia ("antes: @vite('...charts.js')") no cuentan.
        const txt = readFileSync(ruta, 'utf8').replace(/\{\{--[\s\S]*?--\}\}|\/\*[\s\S]*?\*\/|^\s*\/\/.*$/gm, '')
        // Uso real: @vite('...charts.js') o leer window.Chart (el global que publicaba charts.js).
        if (/@vite\([^)]*js\/charts\.js|window\.Chart\b/.test(txt)) culpables.push(ruta)
      }
    }
  }
  recorrer(new URL('resources/', raiz).pathname.replace(/^\/([A-Za-z]:)/, '$1'))
  assert.deepEqual(culpables, [])
})

test('quien dibuja con Chart.js lo importa en su bundle (sin depender del global)', () => {
  const usuarios = [
    'resources/js/modulos/atadores/reportes/km/index.ts',
    'resources/js/modulos/urdido/comun/reporte-resumen/index.ts',
    'resources/js/modulos/urdido/panel-control/index.ts',
    'resources/js/ventas/ventas-historicas.ts',
  ]
  for (const ruta of usuarios) {
    const txt = leer(ruta)
    if (!/new Chart\(/.test(txt)) continue
    assert.match(txt, /from ['"]chart\.js(\/auto)?['"]/, `${ruta} usa new Chart sin importarlo`)
  }
})
