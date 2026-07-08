import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function reloadForStaleSession() {
    if (window.__sbwStaleSessionReloading) return;

    window.__sbwStaleSessionReloading = true;
    window.location.reload();
}

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

/**
 * フルURL・相対パス両方に対応してエリアロールを返す。
 * 例: 'http://localhost:8000/coordinator/clients/16/edit' → 'coordinator'
 *     '/proof-coordinator/requests/3'                    → 'proof_coordinator'
 * 判定できない場合は null（呼び出し側で user_role にフォールバック）
 */
function areaFromUrl(url) {
    if (!url) return null;
    let pathname;
    try {
        // new URL() はフルURL・相対パスどちらも pathname に正規化してくれる
        pathname = new URL(url, window.location.origin).pathname;
    } catch {
        pathname = url;
    }
    // 本番の basePath (/members など) を除去してから比較
    const basePath = (import.meta.env.VITE_APP_BASE_PATH || '').replace(/\/$/, '');
    if (basePath) pathname = pathname.replace(new RegExp('^' + basePath), '');
    pathname = pathname.replace(/^\//, '');

    if (pathname.startsWith('superadmin/') || pathname === 'superadmin') return 'superadmin';
    if (pathname.startsWith('admin/') || pathname === 'admin') return 'admin';
    if (pathname.startsWith('leader/') || pathname === 'leader' || pathname.startsWith('workload_setting/')) return 'leader';
    // ルートプレフィックスは 'proof-coordinator'（ハイフン）だが localStorage キーは 'proof_coordinator'
    if (pathname.startsWith('proof-coordinator/') || pathname === 'proof-coordinator') return 'proof_coordinator';
    if (pathname.startsWith('coordinator/') || pathname === 'coordinator') return 'coordinator';
    if (pathname.startsWith('clerk/') || pathname === 'clerk') return 'clerk';
    if (pathname.startsWith('prepress/') || pathname === 'prepress') return 'prepress';
    return null;
}

/**
 * 404/403 発生時: localStorage の壊れた URL を削除してエリアの dashboard へ戻す。
 * failedUrl から発生エリアを特定するため、SuperAdmin が coordinator エリアで踏んでも
 * coordinator.dashboard へ戻れる。
 */
function redirectOnError(failedUrl, authUser) {
    if (authUser?.is_ghost) return;
    // URL プレフィックスで判定 → 取れなければ user_role で判定 → それも無ければ 'user'
    const area = areaFromUrl(failedUrl) ?? authUser?.user_role ?? 'user';
    const routeName = ROLE_DASHBOARD_ROUTES[area] || 'user.dashboard';
    try { localStorage.removeItem(`lastTab_${area}`); } catch {}
    try {
        router.visit(route(routeName));
    } catch {
        router.visit('/');
    }
}

router.on('before', (event) => {
    const token = csrfToken();
    if (!token) return;

    event.detail.visit.headers = {
        ...event.detail.visit.headers,
        'X-CSRF-TOKEN': token,
    };
});

function fullPageVisit(url) {
    if (!url) {
        window.location.reload();
        return;
    }

    window.location.href = new URL(url, window.location.origin).toString();
}

// Inertia JSON として 404 ページが描画されるケース
router.on('navigate', (event) => {
    const component = event.detail?.page?.component;
    // 403 は権限なしページをそのまま表示する。
    if (component !== 'Errors/404') return;
    const authUser = event.detail?.page?.props?.auth?.user;
    // page.url はサーバーが返した現在のページURL（リクエストした失敗URL）
    const failedUrl = event.detail?.page?.url;
    redirectOnError(failedUrl, authUser);
});

// サーバーが非 Inertia の HTML 404/403 を返したケース（削除済みリソースへのアクセス等）
// Inertia はデフォルトで document.write() してSPAを破壊するため preventDefault() で防ぐ
// axios レスポンスオブジェクトには .url はなく、XHR の responseURL か config.url を使う
router.on('invalid', (event) => {
    const status = event.detail?.response?.status;

    if (status === 419 || status === 401) {
        event.preventDefault();
        reloadForStaleSession();
        return;
    }

    if (status === 403) {
        event.preventDefault();
        const res = event.detail?.response;
        const failedUrl = res?.request?.responseURL
            ?? res?.config?.url
            ?? window.location.href;
        fullPageVisit(failedUrl);
        return;
    }

    if (status !== 404) return;
    event.preventDefault();
    const res = event.detail?.response;
    const failedUrl = res?.request?.responseURL   // XHR: 最終URL（フル）
        ?? res?.config?.url                        // axios config: リクエストURL
        ?? null;
    const authUser = router.page?.props?.auth?.user;
    redirectOnError(failedUrl, authUser);
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
