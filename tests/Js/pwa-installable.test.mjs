// Criterios que Chrome exige para ofrecer "Instalar app". Antes vivia como
// public/pwa-check.mjs (servido en el web root y sin correr en CI); ahora es test.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { test } from 'node:test'
import { fileURLToPath } from 'node:url'

const PUBLIC = join(fileURLToPath(new URL('.', import.meta.url)), '..', '..', 'public')
const manifest = JSON.parse(readFileSync(join(PUBLIC, 'manifest.json'), 'utf8'))
const sw = readFileSync(join(PUBLIC, 'sw.js'), 'utf8')

// Ancho/alto del header PNG (IHDR: bytes 16-23).
const pngSize = (rel) => {
  const buf = readFileSync(join(PUBLIC, rel))
  return [buf.readUInt32BE(16), buf.readUInt32BE(20)]
}

test('manifest instalable: nombre, start_url y display', () => {
  assert.ok(manifest.name || manifest.short_name, 'falta name y short_name')
  assert.ok(manifest.start_url, 'falta start_url')
  assert.ok(['fullscreen', 'standalone', 'minimal-ui'].includes(manifest.display), `display "${manifest.display}" no es instalable`)
})

for (const need of [192, 512]) {
  test(`manifest declara icono PNG ${need}x${need} y el archivo mide eso`, () => {
    const icon = (manifest.icons ?? []).find((i) => (i.sizes ?? '').split(' ').includes(`${need}x${need}`))
    assert.ok(icon, `falta icono ${need}x${need}: Chrome no ofrece instalar`)
    assert.deepEqual(pngSize(icon.src), [need, need])
  })
}

test('sw.js tiene fetch handler que responde', () => {
  assert.match(sw, /addEventListener\(\s*["']fetch["']/)
  assert.match(sw, /respondWith/)
})

test('sw.js deja pasar POST sin interceptar (protege login/CSRF)', () => {
  assert.match(sw, /req\.method\s*!==\s*["']GET["']/)
})
