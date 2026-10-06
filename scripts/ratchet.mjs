#!/usr/bin/env node
// Ratchet de deuda (BASE-03): cuenta patrones que el refactor 2026 quiere eliminar y
// falla si alguno sube respecto a scripts/ratchet-baseline.json. Sin dependencias.
//
//   node scripts/ratchet.mjs            compara contra el baseline (CI)
//   node scripts/ratchet.mjs --update   reescribe el baseline con los conteos actuales
//   node scripts/ratchet.mjs --top <metrica>   archivos con mas ocurrencias
//
// Bajar un conteo no falla: se imprime y se sugiere --update para fijar la ganancia.
// 'duplicación %' (CAL-01) no cuenta patrones: la mide jscpd con .jscpd.json.

import { execFileSync } from 'node:child_process'
import { mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, relative, sep } from 'node:path'
import { fileURLToPath } from 'node:url'

const ROOT = join(fileURLToPath(new URL('.', import.meta.url)), '..')
const BASELINE = join(ROOT, 'scripts', 'ratchet-baseline.json')

const FRONT = ['resources/views', 'resources/js', 'public/js']
const FRONT_EXT = ['.php', '.js', '.ts', '.mjs', '.cjs']

const countRegex = (re) => (text) => (text.match(re) ?? []).length

// Argumento balanceado de cada response()->json( ... ) que mencione getMessage().
// Ignora parentesis dentro de strings simples/dobles.
const jsonConGetMessage = (text) => {
  let total = 0
  const needle = 'response()->json('
  let from = 0
  for (;;) {
    const start = text.indexOf(needle, from)
    if (start === -1) return total
    let depth = 1
    let i = start + needle.length
    let quote = null
    for (; i < text.length && depth > 0; i++) {
      const ch = text[i]
      if (quote) {
        if (ch === '\\') i++
        else if (ch === quote) quote = null
      } else if (ch === '"' || ch === "'") quote = ch
      else if (ch === '(') depth++
      else if (ch === ')') depth--
    }
    if (text.slice(start, i).includes('getMessage()')) total++
    from = i
  }
}

export const METRICS = {
  'fetch(': { dirs: FRONT, count: countRegex(/\bfetch\(/g) },
  'Swal.fire': { dirs: FRONT, count: countRegex(/Swal\.fire\b/g) },
  'toastr.': { dirs: FRONT, count: countRegex(/\btoastr\./g) },
  'onclick=': { dirs: FRONT, count: countRegex(/onclick\s*=/gi) },
  'innerHTML =': { dirs: FRONT, count: countRegex(/\.innerHTML\s*\+?=(?!=)/g) },
  'X-CSRF-TOKEN': { dirs: FRONT, count: countRegex(/X-CSRF-TOKEN/gi) },
  'bg-opacity-': { dirs: FRONT, count: countRegex(/\bbg-opacity-/g) },
  '<script> inline en blade': {
    dirs: ['resources/views'],
    only: '.blade.php',
    count: countRegex(/<script\b(?![^>]*\bsrc\s*=)[^>]*>/gi),
  },
  // SEC-01: string SQL con comillas dobles que interpola una variable PHP. Hoy todos
  // los hits son constantes/whitelists (ver 10-01-SUMMARY.md); uno nuevo se revisa.
  'SQL crudo con $interpolado': {
    dirs: ['app'],
    only: '.php',
    count: countRegex(
      /(?:Raw|DB::(?:select|statement|update|insert|delete|unprepared)|->(?:select|statement|update|insert|delete|unprepared))\(\s*"[^"]*\$/g,
    ),
  },
  'getMessage() en response()->json': {
    dirs: ['app'],
    only: '.php',
    count: jsonConGetMessage,
  },
  // CAL-03: un catch sin cuerpo (ni comentario) se traga el error. Las excepciones
  // deliberadas de 22-CONTEXT.md (Monitoreo, EnsureModulePermission...) cuentan igual.
  'catch vacío': {
    dirs: ['app'],
    only: '.php',
    count: countRegex(/\bcatch\s*\([^)]*\)\s*\{\s*\}/g),
  },
  // El handler de bootstrap/app.php ya responde el 5xx JSON con trace_id: un catch genérico
  // en un controller casi siempre lo duplica (y filtra getMessage()). Solo bajan.
  'try { en controllers': {
    dirs: ['app/Http/Controllers'],
    only: '.php',
    count: countRegex(/^\s*try\s*\{/gm),
  },
  'catch genérico en controllers': {
    dirs: ['app/Http/Controllers'],
    only: '.php',
    count: countRegex(/\bcatch\s*\(\s*\\?(?:Throwable|Exception)\b/g),
  },
}

export const DUPLICACION = 'duplicación %'

// Porcentaje de lineas duplicadas segun jscpd (config en .jscpd.json: php -incluye blade-,
// ts y js). Proceso aparte y fuera de measure(): tarda segundos y los tests no lo necesitan.
export function duplicacion(root = ROOT) {
  const out = mkdtempSync(join(tmpdir(), 'jscpd-'))
  try {
    execFileSync(process.execPath, [join(root, 'node_modules', 'jscpd', 'run-jscpd.js'), '--absolute', '--output', out], {
      cwd: root,
      stdio: 'ignore',
    })
    const reporte = JSON.parse(readFileSync(join(out, 'jscpd-report.json'), 'utf8'))
    const rel = (f) => relative(root, f).split(sep).join('/')
    const porPar = {}
    for (const d of reporte.duplicates) {
      const par = `${rel(d.firstFile.name)} <-> ${rel(d.secondFile.name)}`
      porPar[par] = (porPar[par] ?? 0) + d.lines
    }
    return { total: Math.round(reporte.statistics.total.percentage * 100) / 100, porPar }
  } finally {
    rmSync(out, { recursive: true, force: true })
  }
}

function* walk(dir) {
  let entries
  try {
    entries = readdirSync(dir, { withFileTypes: true })
  } catch {
    return
  }
  for (const entry of entries) {
    const full = join(dir, entry.name)
    if (entry.isDirectory()) yield* walk(full)
    else yield full
  }
}

const matches = (file, metric) => {
  if (file.endsWith('.min.js')) return false
  if (metric.only) return file.endsWith(metric.only)
  return FRONT_EXT.some((ext) => file.endsWith(ext))
}

export function measure(root = ROOT) {
  const totals = {}
  const perFile = {}
  const cache = new Map()
  for (const [name, metric] of Object.entries(METRICS)) {
    totals[name] = 0
    perFile[name] = {}
    for (const dir of metric.dirs) {
      for (const file of walk(join(root, dir))) {
        if (!matches(file, metric)) continue
        if (!cache.has(file)) cache.set(file, readFileSync(file, 'utf8'))
        const n = metric.count(cache.get(file))
        if (n > 0) {
          totals[name] += n
          perFile[name][relative(root, file).split(sep).join('/')] = n
        }
      }
    }
  }
  return { totals, perFile }
}

export function compare(baseline, totals) {
  const up = []
  const down = []
  for (const [name, now] of Object.entries(totals)) {
    const before = baseline[name]
    if (before === undefined || now > before) up.push({ name, before: before ?? 0, now })
    else if (now < before) down.push({ name, before, now })
  }
  return { up, down }
}

function main(argv) {
  const { totals, perFile } = measure()
  const dup = duplicacion()
  totals[DUPLICACION] = dup.total
  perFile[DUPLICACION] = dup.porPar

  const topIndex = argv.indexOf('--top')
  if (topIndex !== -1) {
    const name = argv[topIndex + 1]
    if (!perFile[name]) {
      console.error(`Metrica desconocida. Opciones: ${Object.keys(METRICS).join(' | ')}`)
      return 2
    }
    Object.entries(perFile[name])
      .sort((a, b) => b[1] - a[1])
      .slice(0, 20)
      .forEach(([file, n]) => console.log(`${String(n).padStart(5)}  ${file}`))
    return 0
  }

  if (argv.includes('--update')) {
    writeFileSync(BASELINE, `${JSON.stringify(totals, null, 2)}\n`)
    console.log('Baseline actualizado:')
    console.table(totals)
    return 0
  }

  const baseline = JSON.parse(readFileSync(BASELINE, 'utf8'))
  const { up, down } = compare(baseline, totals)

  for (const d of down) console.log(`bajo  ${d.name}: ${d.before} -> ${d.now}`)
  if (down.length && !up.length) {
    console.log('Fija la ganancia con: node scripts/ratchet.mjs --update')
  }
  if (up.length) {
    for (const u of up) {
      console.error(`SUBIO ${u.name}: ${u.before} -> ${u.now}  (top 5; el culpable esta en tu diff)`)
      Object.entries(perFile[u.name])
        .sort((a, b) => b[1] - a[1])
        .slice(0, 5)
        .forEach(([file, n]) => console.error(`        ${n}  ${file}`))
    }
    console.error('\nLa deuda no puede subir. Usa window.http / window.notify / componentes (ver CLAUDE.md).')
    return 1
  }
  console.log(`ratchet ok (${Object.keys(totals).length} metricas, ninguna subio)`)
  return 0
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  process.exitCode = main(process.argv.slice(2))
}
