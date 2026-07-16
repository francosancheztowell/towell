import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import path from 'node:path'

export default defineConfig({
  build: {
    sourcemap: false,
    minify: 'esbuild',
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) return undefined
          if (/node_modules\/(react|react-dom|scheduler)\//.test(id)) return 'react-vendor'
          if (id.includes('node_modules/@tanstack/')) return 'tanstack-vendor'
          if (/node_modules\/(react-hook-form|zod)\//.test(id) || id.includes('node_modules/@hookform/')) {
            return 'forms-vendor'
          }
          if (id.includes('node_modules/@radix-ui/') || /node_modules\/(lucide-react|sonner)\//.test(id)) {
            return 'ui-vendor'
          }
          if (/node_modules\/(jquery|sweetalert2|select2|toastr|axios)\//.test(id)) return 'vendor'

          return undefined
        },
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
        'resources/js/catcodificacion/index.js',
        'resources/js/planeacion/pesos-rollos/main.tsx',
      ],
      refresh: true,
    }),
    react(),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      '@': path.resolve(import.meta.dirname, 'resources/js/react'),
    },
  },
})
