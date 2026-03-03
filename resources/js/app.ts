import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import axios from 'axios';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';

import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Get CSRF token from multiple potential sources
const getCsrfToken = (): string => {
    // Try meta tag first
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (metaToken) return metaToken;

    // Try from input field (fallback)
    const inputToken = (document.querySelector('input[name="_token"]') as HTMLInputElement)?.value;
    if (inputToken) return inputToken;

    return '';
};

const token = getCsrfToken();

if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
}

// Ensure CSRF token is refreshed with every request
axios.interceptors.request.use((config) => {
    const csrfToken = getCsrfToken();
    if (csrfToken) {
        config.headers['X-CSRF-TOKEN'] = csrfToken;
    }
    return config;
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        // Explicitly set axios as the HTTP client for Inertia
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        // Make axios available to the Inertia app via a provide
        app.provide('$http', axios);

        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

initializeTheme();
