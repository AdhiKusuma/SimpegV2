import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const projectRoot = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [
        react(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: ['app/**', 'resources/views/**', 'routes/**'],
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(projectRoot, 'resources/js'),
        },
    },
    server: {
        fs: { allow: [projectRoot] },
        watch: { ignored: ['**/storage/framework/views/**'] },
    },
    build: {
        emptyOutDir: true,
        sourcemap: false,
    },
});
