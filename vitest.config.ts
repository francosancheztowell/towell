import path from 'node:path'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, 'resources/js/react'),
        },
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['resources/js/react/test/setup.ts'],
        include: ['resources/js/**/*.test.{ts,tsx}'],
        clearMocks: true,
    },
})
