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

export default defineConfig({
  build: {
    sourcemap: false,
    minify: 'esbuild',
    // Sin manualChunks: un chunk `vendor` global hacía que las 129 páginas del
    // layout bajaran librerías que solo usan unas pocas (15-02).
  },
  plugins: [
    laravel({
      // Set: si otra rama agregó a mano una entrada que el glob ya encuentra, no se duplica.
      input: [...new Set([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/js/app-core.js',
        'resources/js/charts.js',
        'resources/css/trazabilidad/index.css',
        'resources/js/trazabilidad/index.ts',
        'resources/css/crudo/dashboard.css',
        'resources/js/crudo/dashboard.ts',
        'resources/css/ventas/dashboard.css',
        'resources/js/ventas/dashboard.js',
        'resources/css/urd-eng/edicion-ordenes.css',
        'resources/js/urd-eng/edicion-ordenes.ts',
        'resources/js/urd-eng/edicion-orden.ts',
        'resources/css/urd-eng/program-board.css',
        'resources/js/urd-eng/program-board.ts',
        'resources/js/tejido/inventario-telas.ts',
        'resources/css/tejido/inventario-telas.css',
        'resources/js/catcodificacion/index.js',
        'resources/js/lmat-lista/index.js',
        'resources/js/programa-tejido/index.js',
        'resources/js/programa-urd-eng/reservar-programar.ts',
        'resources/js/usuarios/qr.ts',
        ...entradasDeModulos(),
      ])],
      refresh: true,
    }),
    tailwindcss(),
  ],
})
