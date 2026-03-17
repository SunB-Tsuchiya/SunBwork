<script setup>
import { Link } from '@inertiajs/vue3';
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
</script>

<template>
    <div class="mb-6">
        <nav class="flex space-x-8" aria-label="Tabs">
            <Link
                v-if="typeof route === 'function' && route().has('superadmin.companies.index')"
                :href="route('superadmin.companies.index')"
                :class="tab('companies')"
            >
                会社の追加と管理
            </Link>
            <Link
                :href="route('superadmin.adminusers.index')"
                :class="tab('users')"
            >
                Adminユーザー管理
            </Link>
            <Link
                v-if="typeof route === 'function' && route().has('superadmin.ai.index')"
                :href="route('superadmin.ai.index')"
                :class="tab('ai')"
            >
                AI設定
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
