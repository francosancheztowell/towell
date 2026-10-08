import { existsSync, readdirSync } from 'node:fs'
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'

// Cada resources/js/modulos/<modulo>/index.ts es una entrada: un módulo nuevo
// (Ola 3) no necesita tocar este archivo. Se carga con @vite('resources/js/modulos/<modulo>/index.ts').
function entradasDeModulos() {
  const raiz = 'resources/js/modulos'
  if (!existsSync(raiz)) return []
  return readdirSync(raiz, { recursive: true })
    .map((ruta) => String(ruta).replaceAll('\\', '/'))
    .filter((ruta) => ruta === 'index.ts' || ruta.endsWith('/index.ts'))
    .map((ruta) => `${raiz}/${ruta}`)
    .sort()
}

// Solo TS (owner, 2026-09-30): las entradas fijas que todavía existen en .js se buscan
// primero en .ts, así una sesión que migra el archivo no tiene que tocar este config.
function entrada(sinExtension) {
  return existsSync(`${sinExtension}.ts`) ? `${sinExtension}.ts` : `${sinExtension}.js`
}

export default defineConfig({
  build: {
    sourcemap: false,
    // Sin `minify`: vite 8 minifica con Oxc (nativo), más rápido y ~400 KB menos que esbuild.
    rolldownOptions: {
      // El aviso PLUGIN_TIMINGS sale por el worker de pdf.js (?url, >1 MB); el build está bien.
      checks: { pluginTimings: false },
      output: {
        // El worker de pdf.js (?url) sale como .js: Apache/Laragon no sirven .mjs con MIME de JS.
        assetFileNames: (asset) =>
          asset.names?.[0]?.endsWith('.mjs') ? 'assets/[name]-[hash].js' : 'assets/[name]-[hash][extname]',
      },
    },
    // Sin manualChunks: un chunk `vendor` global hacía que las 129 páginas del
    // layout bajaran librerías que solo usan unas pocas (15-02).
  },
  plugins: [
    laravel({
      // Set: si otra rama agregó a mano una entrada que el glob ya encuentra, no se duplica.
      input: [...new Set([
        'resources/css/app.css',
        entrada('resources/js/app'),
        entrada('resources/js/app-core'),
        entrada('resources/js/charts'),
        'resources/css/trazabilidad/index.css',
        'resources/js/trazabilidad/index.ts',
        'resources/css/crudo/dashboard.css',
        'resources/js/crudo/dashboard.ts',
        'resources/css/ventas/dashboard.css',
        entrada('resources/js/ventas/dashboard'),
        'resources/css/urd-eng/edicion-ordenes.css',
        'resources/js/urd-eng/edicion-ordenes.ts',
        'resources/js/urd-eng/edicion-orden.ts',
        'resources/css/urd-eng/program-board.css',
        'resources/js/urd-eng/program-board.ts',
        'resources/js/tejido/inventario-telas.ts',
        'resources/css/tejido/inventario-telas.css',
        entrada('resources/js/catcodificacion/index'),
        entrada('resources/js/lmat-lista/index'),
        entrada('resources/js/programa-tejido/index'),
        ...entradasDeModulos(),
      ])],
      refresh: true,
    }),
    tailwindcss(),
  ],
})
