import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  build: {
    sourcemap: false,
    minify: 'esbuild',
    rollupOptions: {
      output: {
        manualChunks: {
          vendor: ['jquery', 'sweetalert2', 'select2', 'toastr', 'axios']
        }
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
        'resources/js/trazabilidad/index.js',
        'resources/js/catcodificacion/index.js',
        'resources/js/lmat-lista/index.js',
      ],
      refresh: true,
    }),
    tailwindcss(),
  ],
})
