import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Wait for the CSRF cookie to be set before mounting the SPA to avoid
// race conditions where the first XHR fires without the CSRF/session cookie
// and results in Network / 419 errors in development.
(async () => {
    try {
        // bootstrap.js already calls axios.get('/sanctum/csrf-cookie'), but
        // ensure we await it here so the app mounts only after the cookie is present.
        if (window.axios) {
            await window.axios.get('/sanctum/csrf-cookie');
        }
    } catch (e) {
        // ignore - we'll still mount the app even if CSRF fetch fails
        console.debug('csrf-cookie fetch failed (ignored)', e);
    }

    createInertiaApp({
        title: (title) => `${title} - ${appName}`,
        resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
        setup({ el, App, props, plugin }) {
            return createApp({ render: () => h(App, props) })
                .use(plugin)
                .use(ZiggyVue)
                .mount(el);
        },
        progress: {
            color: '#4B5563',
        },
    });
})();
