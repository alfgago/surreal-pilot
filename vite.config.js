import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        cors: {
            origin: ['http://surreal-pilot.local', 'http://localhost', 'http://127.0.0.1'],
            credentials: true,
        },
        hmr: {
            host: 'localhost',
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
            '@/types': path.resolve(__dirname, 'resources/js/types'),
            '@/lib': path.resolve(__dirname, 'resources/js/lib'),
            '@/components': path.resolve(__dirname, 'resources/js/components'),
            '@/pages': path.resolve(__dirname, 'resources/js/Pages'),
            'ziggy-js': path.resolve(__dirname, 'vendor/tightenco/ziggy/dist/index.js'),
        },
    },
    define: {
        global: 'globalThis',
    },
});
