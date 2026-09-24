#!/usr/bin/env node
// Ratchet de deuda (BASE-03): cuenta patrones que el refactor 2026 quiere eliminar y
// falla si alguno sube respecto a scripts/ratchet-baseline.json. Sin dependencias.
//
//   node scripts/ratchet.mjs            compara contra el baseline (CI)
//   node scripts/ratchet.mjs --update   reescribe el baseline con los conteos actuales
//   node scripts/ratchet.mjs --top <metrica>   archivos con mas ocurrencias
//
// Bajar un conteo no falla: se imprime y se sugiere --update para fijar la ganancia.

import { readFileSync, readdirSync, writeFileSync } from 'node:fs'
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
  'getMessage() en response()->json': {
    dirs: ['app'],
    only: '.php',
    count: jsonConGetMessage,
  },
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
