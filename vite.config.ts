import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import { execSync } from 'child_process';

const plugins: any[] = [
    laravel({
        input: ['resources/js/app.ts'],
        ssr: 'resources/js/ssr.ts',
        refresh: true,
    }),
    tailwindcss(),
];

// Wayfinder is temporarily disabled due to missing route directories
// if (process.env.SKIP_WAYFINDER !== 'true') {
//     try {
//         execSync('php -v', { stdio: 'ignore', shell: true });
//         plugins.push(wayfinder({ formVariants: true }));
//     } catch {
//         // PHP not available, skip wayfinder
//     }
// }

plugins.push(
    vue({
        template: {
            transformAssetUrls: {
                base: null,
                includeAbsolute: false,
            },
        },
    })
);

export default defineConfig({
    plugins,
    resolve: {
        alias: {
            '@': '/resources/js',
            'ziggy-js': 'ziggy-js', // Ensure ziggy is properly resolved
        },
    },
    server: {
        host: '127.0.0.1',
        middlewareMode: false,
        hmr: {
            host: '127.0.0.1',
            protocol: 'ws',
        },
    },
});
