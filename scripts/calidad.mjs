#!/usr/bin/env node
// Gates de calidad sobre lo que cambia (CAL-01). Node y no bash para que `composer quality`
// corra igual en Windows/Laragon que en el CI. Sin baselines: todo se compara contra la base.
//
//   node scripts/calidad.mjs base      commit base contra el que se compara
//   node scripts/calidad.mjs archivos  PHP cambiados (sin blade)
//   node scripts/calidad.mjs pint      pint --test sobre los PHP cambiados
//   node scripts/calidad.mjs phpmd     falla si un PHP cambiado trae violaciones PHPMD nuevas
//   node scripts/calidad.mjs audit     composer audit: bloquea advisories nuevos si cambio
//                                      composer.lock; si no, solo avisa
//
// La base: CAMBIADOS_BASE si esta; en un PR, su rama base; en un push, lo que trae el push
// (CAMBIADOS_BEFORE = github.event.before; rama nueva o force-push: HEAD~1); en local, lo
// que aun no esta en el upstream (o origin/main sin upstream) mas el arbol de trabajo.

import { spawnSync } from 'node:child_process'
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const ROOT = join(fileURLToPath(new URL('.', import.meta.url)), '..')
const CERO = '0000000000000000000000000000000000000000'

function run(cmd, args, opts = {}) {
  const r = spawnSync(cmd, args, { cwd: ROOT, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024, ...opts })
  if (r.error) throw r.error
  return r
}

function git(...args) {
  const r = run('git', args)
  if (r.status !== 0) throw new Error(`git ${args.join(' ')}: ${r.stderr.trim()}`)
  return r.stdout.trim()
}

const existe = (ref) => run('git', ['cat-file', '-e', `${ref}^{commit}`]).status === 0

export function base(env = process.env) {
  let ref
  if (env.CAMBIADOS_BASE) ref = env.CAMBIADOS_BASE
  else if (env.GITHUB_EVENT_NAME === 'pull_request') ref = `origin/${env.GITHUB_BASE_REF}`
  else if (env.GITHUB_ACTIONS) {
    const before = env.CAMBIADOS_BEFORE
    // Rama nueva ("before" en ceros) o force-push ("before" ya no esta): el ultimo commit.
    ref = before && before !== CERO && existe(before) ? before : 'HEAD~1'
  } else {
    ref = run('git', ['rev-parse', '--abbrev-ref', '@{upstream}']).stdout.trim() || 'origin/main'
  }
  return git('merge-base', ref, 'HEAD')
}

// Contra el arbol de trabajo (en el CI es HEAD): incluye lo no commiteado. -M detecta
// renombres para que mover un archivo no cuente su deuda como nueva.
export function cambiados(desde) {
  const salida = git('diff', '--name-status', '-M', '--diff-filter=AMR', desde, '--', '*.php')
  const archivos = salida
    ? salida.split('\n').map((linea) => {
        const partes = linea.split('\t')
        return partes[0].startsWith('R') ? { archivo: partes[2], anterior: partes[1] } : { archivo: partes[1], anterior: partes[0] === 'A' ? null : partes[1] }
      })
    : []
  const nuevos = git('ls-files', '--others', '--exclude-standard', '--', '*.php')
  for (const archivo of nuevos ? nuevos.split('\n') : []) archivos.push({ archivo, anterior: null })
  return archivos.filter(({ archivo }) => !archivo.endsWith('.blade.php'))
}

// Una violacion se identifica por regla + clase + metodo, no por linea (que se mueve). En
// unusedcode la descripcion trae el nombre (variable, metodo): entra en la clave. En codesize
// trae el numero medido: no entra, asi un metodo legado que ya violaba y crece no cuenta como
// nuevo (esa deuda la paga su dueno; ver 22-01-PLAN.md D1).
export function clave(v) {
  const donde = [v.class ?? '', v.method ?? v.function ?? ''].join('::')
  return v.ruleSet === 'Unused Code Rules' ? `${v.rule} ${donde} ${v.description}` : `${v.rule} ${donde}`
}

export function nuevas(antes, despues) {
  const cuenta = new Map()
  for (const v of antes) cuenta.set(clave(v), (cuenta.get(clave(v)) ?? 0) + 1)
  return despues.filter((v) => {
    const k = clave(v)
    const n = cuenta.get(k) ?? 0
    if (n > 0) {
      cuenta.set(k, n - 1)
      return false
    }
    return true
  })
}

// Un archivo por invocacion: en lote pdepend aborta cuando dos clases analizadas juntas
// tienen colisiones de traits (pasa en app/Http/Controllers/Engomado).
function phpmdDe(archivo) {
  const r = run('php', [join(ROOT, 'vendor', 'bin', 'phpmd'), archivo, 'json', join(ROOT, 'phpmd.xml')])
  if (r.status !== 0 && r.status !== 2) {
    throw new Error(`phpmd fallo en ${archivo} (exit ${r.status}): ${r.stderr.trim() || r.stdout.trim()}`)
  }
  const json = JSON.parse(r.stdout)
  if (json.errors?.length) throw new Error(`phpmd no pudo analizar ${archivo}: ${json.errors.map((e) => e.message).join('; ')}`)
  return json.files.flatMap((f) => f.violations)
}

function phpmd() {
  const desde = base()
  const archivos = cambiados(desde)
  if (!archivos.length) {
    console.log('phpmd: sin PHP cambiado')
    return 0
  }
  const tmp = mkdtempSync(join(tmpdir(), 'phpmd-base-'))
  let fallos = 0
  try {
    for (const { archivo, anterior } of archivos) {
      let antes = []
      if (anterior) {
        const copia = join(tmp, anterior)
        mkdirSync(dirname(copia), { recursive: true })
        writeFileSync(copia, git('show', `${desde}:${anterior}`))
        antes = phpmdDe(copia)
      }
      const agregadas = nuevas(antes, phpmdDe(join(ROOT, archivo)))
      for (const v of agregadas) {
        console.error(`${archivo}:${v.beginLine}  ${v.rule}  ${v.description}`)
      }
      fallos += agregadas.length
    }
  } finally {
    rmSync(tmp, { recursive: true, force: true })
  }
  if (fallos) {
    console.error(`\nphpmd: ${fallos} violacion(es) nueva(s) en ${archivos.length} PHP cambiado(s). Umbrales en phpmd.xml.`)
    return 1
  }
  console.log(`phpmd ok (${archivos.length} PHP cambiado(s), sin violaciones nuevas)`)
  return 0
}

function pint() {
  const archivos = cambiados(base()).map(({ archivo }) => archivo)
  if (!archivos.length) {
    console.log('pint: sin PHP cambiado')
    return 0
  }
  console.log(archivos.join('\n'))
  return run('php', [join(ROOT, 'vendor', 'bin', 'pint'), '--test', ...archivos], { stdio: 'inherit' }).status
}

// Bajo `composer quality` Composer deja su ruta en COMPOSER_BINARY; suelto, 'composer' del PATH
// (en Windows es un .bat: necesita shell).
function composer(args) {
  const bin = process.env.COMPOSER_BINARY
  return bin ? run('php', [bin, ...args]) : run('composer', args, { shell: process.platform === 'win32' })
}

function advisories(dir) {
  const r = composer(['audit', '--locked', '--format=json', '--abandoned=ignore', `--working-dir=${dir}`])
  const json = JSON.parse(r.stdout || '{}')
  return Object.values(json.advisories ?? {})
    .flat()
    .map((a) => ({ id: a.advisoryId, paquete: a.packageName, titulo: a.title, severidad: a.severity }))
}

function audit() {
  const desde = base()
  const cambioLock = git('diff', '--name-only', desde, '--', 'composer.lock') !== ''
  const ahora = advisories(ROOT)
  if (!cambioLock) {
    if (ahora.length) console.log(`::warning::composer audit: ${ahora.length} advisories en composer.lock (no bloquea: este cambio no toca composer.lock)`)
    else console.log('composer audit: sin advisories')
    return 0
  }
  // Cambio el lock: bloquean solo los advisories que la base no tenia (nada nuevo empeora).
  const tmp = mkdtempSync(join(tmpdir(), 'audit-base-'))
  let antes = []
  try {
    for (const f of ['composer.json', 'composer.lock']) writeFileSync(join(tmp, f), git('show', `${desde}:${f}`))
    antes = advisories(tmp)
  } finally {
    rmSync(tmp, { recursive: true, force: true })
  }
  const vistos = new Set(antes.map((a) => a.id))
  const agregados = ahora.filter((a) => !vistos.has(a.id))
  if (agregados.length) {
    for (const a of agregados) console.error(`${a.paquete}  ${a.severidad ?? '?'}  ${a.id}  ${a.titulo}`)
    console.error(`\ncomposer audit: ${agregados.length} advisory(s) nuevo(s) por el cambio de composer.lock.`)
    return 1
  }
  console.log(`composer audit ok: composer.lock cambio sin advisories nuevos (${ahora.length} preexistentes, ${antes.length} en la base)`)
  return 0
}

const COMANDOS = {
  base: () => (console.log(base()), 0),
  archivos: () => (cambiados(base()).forEach(({ archivo }) => console.log(archivo)), 0),
  pint,
  phpmd,
  audit,
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  const comando = COMANDOS[process.argv[2]]
  if (!comando) {
    console.error(`Uso: node scripts/calidad.mjs ${Object.keys(COMANDOS).join('|')}`)
    process.exitCode = 2
  } else {
    try {
      process.exitCode = comando()
    } catch (e) {
      console.error(`calidad: ${e.message}\nDefine la base a mano con CAMBIADOS_BASE=<rama o commit>.`)
      process.exitCode = 2
    }
  }
}
