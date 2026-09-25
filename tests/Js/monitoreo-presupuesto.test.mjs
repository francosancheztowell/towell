import assert from 'node:assert/strict'
import { gzipSync } from 'node:zlib'
import { test } from 'node:test'

import { build } from 'esbuild'

// MON-20: el cliente de telemetría no puede pesar más de 5 KB gzip (axios ya está en el bundle).
const PRESUPUESTO = 5 * 1024

test('el cliente de monitoreo cabe en 5 KB gzip', async () => {
  const r = await build({
    entryPoints: ['resources/js/monitoreo/telemetria.ts'],
    bundle: true,
    minify: true,
    format: 'esm',
    target: 'es2022',
    external: ['axios'],
    write: false,
    logLevel: 'silent',
  })
  const bytes = gzipSync(r.outputFiles[0].contents).length
  assert.ok(bytes <= PRESUPUESTO, `${bytes} B gzip > ${PRESUPUESTO} B`)
  console.log(`# monitoreo: ${bytes} B gzip`)
})
