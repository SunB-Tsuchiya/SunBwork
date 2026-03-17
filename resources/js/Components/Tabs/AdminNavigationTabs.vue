<script setup>
import { Link } from '@inertiajs/vue3';
const props = defineProps({
    active: { type: String, default: '' },
});
// Admin カラー: red
const tab = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    props.active === key
        ? 'bg-red-100 text-red-700'
        : 'border border-red-200 text-red-600 hover:bg-red-50 hover:text-red-800',
];
</script>

<template>
    <div class="mb-6">
        <nav class="flex space-x-8" aria-label="Tabs">
            <Link
                v-if="typeof route === 'function' && route().has('admin.companies.index')"
                :href="route('admin.companies.index')"
                :class="tab('companies')"
            >
                会社管理
            </Link>
            <Link
                :href="route('admin.users.index')"
                :class="tab('users')"
            >
                ユーザー管理
            </Link>
            <Link
                :href="route('admin.teams.index')"
                :class="tab('teams')"
            >
                チーム管理
            </Link>
            <Link
                :href="route('admin.diaryinteractions.index')"
                :class="tab('diaries')"
            >
                日報管理
            </Link>
            <Link
                :href="route('admin.clients.index')"
                :class="tab('clients')"
            >
                クライアント管理
            </Link>
            <Link
                :href="route('admin.workload_analyzer.index')"
                :class="tab('workload')"
            >
                作業量分析
            </Link>
        </nav>
    </div>
</template>
