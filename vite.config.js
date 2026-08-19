import { defineConfig } from 'vite';
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

/**
 * Generate the service worker from the same content-hashed Vite release.
 * This removes manual cache-version bumps and keeps fixed PWA assets in sync.
 */
function pwaReleasePlugin() {
    return {
        name: 'playersaloons-pwa-release',
        apply: 'build',
        writeBundle(_options, bundle) {
            const staticAssets = ['manifest.json', 'playersaloons_logo.webp', 'icon-192.png', 'icon-512.png'];
            const releaseHash = createHash('sha256');

            releaseHash.update(Object.keys(bundle).sort().join('|'));
            staticAssets.forEach(file => releaseHash.update(readFileSync(resolve('public', file))));

            const release = releaseHash.digest('hex').slice(0, 12);
            const workerTemplate = readFileSync(resolve('resources/js/service-worker.js'), 'utf8');

            writeFileSync(resolve('public/sw.js'), workerTemplate.replaceAll('__PWA_RELEASE__', release));
            writeFileSync(resolve('public/pwa-version.json'), `${JSON.stringify({ release })}\n`);
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [300, 400, 500, 600, 700],
                }),
                bunny('Orbitron', {
                    weights: [400, 500, 600, 700, 800, 900],
                }),
            ],
        }),
        tailwindcss(),
        pwaReleasePlugin(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
