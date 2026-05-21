import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const ROLE_DASHBOARD_ROUTES = {
    superadmin:       'superadmin.dashboard',
    admin:            'admin.dashboard',
    leader:           'leader.dashboard',
    coordinator:      'coordinator.dashboard',
    proof_coordinator:'proof_coordinator.dashboard',
    clerk:            'clerk.dashboard',
    user:             'user.dashboard',
    prepress:         'prepress.dashboard',
};

// 403/404 ページが表示されたとき、localStorageの壊れたURLをクリアしてダッシュボードへリダイレクト
// ただしゴーストモード（coordinatorのテストユーザーモード）の 403 はリダイレクトしない
router.on('navigate', (event) => {
    const component = event.detail?.page?.component;
    if (component !== 'Errors/403' && component !== 'Errors/404') return;
    const authUser = event.detail?.page?.props?.auth?.user;
    if (authUser?.is_ghost) return;
    const role = authUser?.user_role || 'user';
    const routeName = ROLE_DASHBOARD_ROUTES[role] || 'user.dashboard';
    try { localStorage.removeItem(`lastTab_${role}`); } catch {}
    try {
        router.visit(route(routeName));
    } catch {
        router.visit('/');
    }
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Wait for the CSRF cookie to be set before mounting the SPA to avoid
// race conditions where the first XHR fires without the CSRF/session cookie
// and results in Network / 419 errors in development.
(async () => {
    try {
        // bootstrap.js already calls axios.get('/sanctum/csrf-cookie'), but
        // ensure we await it here so the app mounts only after the cookie is present.
        // NOTE: axios.defaults.baseURL already includes VITE_APP_BASE_PATH (set in bootstrap.js),
        // so we must NOT prepend basePath here — doing so causes double /members/members/... on production.
        if (window.axios) {
            await window.axios.get('/sanctum/csrf-cookie');
        }
    } catch {
        // ignore - we'll still mount the app even if CSRF fetch fails
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
