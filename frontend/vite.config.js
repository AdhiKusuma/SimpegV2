import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const frontendRoot = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [
        react(),
        laravel({
            input: ['src/css/app.css', 'src/app.js'],
            publicDirectory: '../backend/public',
            hotFile: '../backend/public/hot',
            refresh: [
                '../backend/app/**',
                '../backend/resources/views/**',
                '../backend/routes/**',
            ],
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(frontendRoot, 'src'),
        },
    },
    server: {
        fs: {
            allow: [frontendRoot, path.resolve(frontendRoot, '../backend')],
        },
        watch: {
            ignored: ['**/backend/storage/framework/views/**'],
        },
    },
    build: {
        emptyOutDir: true,
        sourcemap: false,
    },
});
