<script setup>
import { Link } from '@inertiajs/vue3';
const props = defineProps({
    active: { type: String, default: '' },
});
</script>

<template>
    <div class="mb-6">
        <nav class="flex space-x-8" aria-label="Tabs">
            <Link
                v-if="typeof route === 'function' && route().has('superadmin.companies.index')"
                :href="route('superadmin.companies.index')"
                :class="[
                    'rounded-md px-3 py-2 text-sm font-medium',
                    active === 'companies'
                        ? 'bg-green-100 text-green-700'
                        : 'border border-green-200 text-green-600 hover:bg-green-50 hover:text-green-800',
                ]"
            >
                会社の追加と管理
            </Link>
            <Link
                :href="route('superadmin.adminusers.index')"
                :class="[
                    'rounded-md px-3 py-2 text-sm font-medium',
                    active === 'users' ? 'bg-red-100 text-red-700' : 'border border-red-200 text-red-600 hover:bg-red-50 hover:text-red-800',
                ]"
            >
                Adminユーザー管理
            </Link>
            <!-- AI設定: SuperAdmin 用リンク -->
            <Link
                v-if="typeof route === 'function' && route().has('superadmin.ai.index')"
                :href="route('superadmin.ai.index')"
                :class="[
                    'rounded-md px-3 py-2 text-sm font-medium',
                    active === 'ai' ? 'bg-blue-100 text-blue-700' : 'border border-blue-200 text-blue-600 hover:bg-blue-50 hover:text-blue-800',
                ]"
            >
                AI設定
            </Link>
            <Link
                :href="route('debug.api')"
                class="rounded-md border border-blue-200 px-3 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50 hover:text-blue-800"
            >
                APIデバッグページ
            </Link>
        </nav>
    </div>
</template>
