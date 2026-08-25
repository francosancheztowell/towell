import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  build: {
    sourcemap: false,
    minify: 'esbuild',
    rollupOptions: {
      output: {
        // Rolldown solo acepta manualChunks como función.
        manualChunks: (id) =>
          /node_modules[\\/](jquery|sweetalert2|select2|toastr|axios)[\\/]/.test(id)
            ? 'vendor'
            : undefined
      }
    }
  },
  plugins: [
    laravel({
      input: [
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/js/app-core.js',
        'resources/js/app-filters.js',
        'resources/css/trazabilidad/index.css',
        'resources/js/trazabilidad/index.ts',
        'resources/css/crudo/dashboard.css',
        'resources/js/crudo/dashboard.ts',
        'resources/css/urd-eng/program-board.css',
        'resources/js/urd-eng/program-board.ts',
        'resources/css/tejido/inventario-telas.css',
        'resources/js/catcodificacion/index.js',
        'resources/js/lmat-lista/index.js',
      ],
      refresh: true,
    }),
    tailwindcss(),
  ],
})
