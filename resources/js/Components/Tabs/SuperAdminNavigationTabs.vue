<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    active: { type: String, default: '' },
});

// SuperAdmin カラー: yellow
const tab = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    props.active === key
        ? 'bg-yellow-100 text-yellow-700'
        : 'border border-yellow-200 text-yellow-600 hover:bg-yellow-50 hover:text-yellow-800',
];

function tryRoute(name) {
    try { return route(name); } catch { return null; }
}

const tabs = computed(() => [
    { key: 'dashboard', href: tryRoute('superadmin.dashboard'), label: 'ダッシュボード' },
    {
        key: 'companies',
        href: tryRoute('superadmin.companies.index'),
        label: '会社の追加と管理',
        condition: typeof route === 'function' && route().has('superadmin.companies.index'),
    },
    { key: 'all_users', href: tryRoute('superadmin.users.index'), label: 'ユーザー管理' },
    { key: 'users', href: tryRoute('superadmin.adminusers.index'), label: 'Adminユーザー管理' },
    { key: 'admin_permissions', href: tryRoute('superadmin.admin_permissions.index'), label: 'Admin権限管理' },
    { key: 'position_titles', href: tryRoute('superadmin.position_titles.index'), label: '役職称号管理' },
    {
        key: 'ai',
        href: tryRoute('superadmin.ai.index'),
        label: 'AI設定',
        condition: typeof route === 'function' && route().has('superadmin.ai.index'),
    },
    {
        key: 'workload',
        href: tryRoute('superadmin.workload_analyzer.index'),
        label: '作業量分析',
        condition: typeof route === 'function' && route().has('superadmin.workload_analyzer.index'),
    },
    {
        key: 'billing_transport_input',
        href: tryRoute('superadmin.billing.transport.index'),
        label: '交通費入力',
        condition: typeof route === 'function' && route().has('superadmin.billing.transport.index'),
    },
    {
        key: 'billing_transport_list',
        href: tryRoute('superadmin.billing.transport.list'),
        label: '交通費一覧',
        condition: typeof route === 'function' && route().has('superadmin.billing.transport.list'),
    },
    {
        key: 'scripts',
        href: tryRoute('superadmin.scripts.index'),
        label: 'スクリプト管理',
        condition: typeof route === 'function' && route().has('superadmin.scripts.index'),
    },
    {
        key: 'demo_pages',
        href: tryRoute('superadmin.demo_pages.index'),
        label: 'デモページ管理',
        condition: typeof route === 'function' && route().has('superadmin.demo_pages.index'),
    },
    { key: 'debug', href: tryRoute('debug.api'), label: 'APIデバッグページ' },
].filter(t => t.condition !== false && t.href));

function onMobileSelect(e) {
    const href = e.target.value;
    if (href) router.get(href);
}
</script>

<template>
    <div class="mb-4">
        <!-- モバイル: ドロップダウン -->
        <div class="sm:hidden">
            <select
                @change="onMobileSelect"
                class="w-full rounded-md border border-yellow-300 bg-white px-3 py-2 text-sm text-yellow-700 shadow-sm focus:border-yellow-500 focus:outline-none focus:ring-1 focus:ring-yellow-500"
            >
                <option value="">— ページを選択 —</option>
                <option
                    v-for="t in tabs"
                    :key="t.key"
                    :value="t.href"
                    :selected="active === t.key"
                >{{ t.label }}</option>
            </select>
        </div>

        <!-- デスクトップ: タブ -->
        <nav class="hidden sm:flex flex-wrap gap-2" aria-label="Tabs">
            <Link
                v-if="typeof route === 'function' && route().has('superadmin.dashboard')"
                :href="route('superadmin.dashboard')"
                :class="tab('dashboard')"
            >
                ダッシュボード
            </Link>
            <Link
                v-if="typeof route === 'function' && route().has('superadmin.companies.index')"
                :href="route('superadmin.companies.index')"
                :class="tab('companies')"
            >
                会社の追加と管理
            </Link>
            <Link
                v-if="typeof route === 'function' && route().has('superadmin.users.index')"
                :href="route('superadmin.users.index')"
                :class="tab('all_users')"
            >
                ユーザー管理
            </Link>
            <Link
                :href="route('superadmin.adminusers.index')"
                :class="tab('users')"
            >
                Adminユーザー管理
            </Link>
            <Link
                :href="route('superadmin.admin_permissions.index')"
                :class="tab('admin_permissions')"
            >
                Admin権限管理
            </Link>
            <Link
                :href="route('superadmin.position_titles.index')"
                :class="tab('position_titles')"
            >
                役職称号管理
            </Link>
            <Link
                v-if="typeof route === 'function' && route().has('superadmin.ai.index')"
                :href="route('superadmin.ai.index')"
                :class="tab('ai')"
            >
                AI設定
            </Link>
            <Link
                v-if="typeof route === 'function' && route().has('superadmin.workload_analyzer.index')"
                :href="route('superadmin.workload_analyzer.index')"
                :class="tab('workload')"
            >
                作業量分析
            </Link>
            <Link
                v-if="typeof route === 'function' && route().has('superadmin.billing.transport.index')"
                :href="route('superadmin.billing.transport.index')"
                :class="tab('billing_transport_input')"
            >
                交通費入力
            </Link>
            <Link
                v-if="typeof route === 'function' && route().has('superadmin.billing.transport.list')"
                :href="route('superadmin.billing.transport.list')"
                :class="tab('billing_transport_list')"
            >
                交通費一覧
            </Link>
            <Link
                v-if="typeof route === 'function' && route().has('superadmin.scripts.index')"
                :href="route('superadmin.scripts.index')"
                :class="tab('scripts')"
            >
                スクリプト管理
            </Link>
            <Link
                v-if="typeof route === 'function' && route().has('superadmin.demo_pages.index')"
                :href="route('superadmin.demo_pages.index')"
                :class="tab('demo_pages')"
            >
                デモページ管理
            </Link>
            <Link
                :href="route('debug.api')"
                :class="tab('debug')"
            >
                APIデバッグページ
            </Link>
        </nav>
    </div>
</template>
