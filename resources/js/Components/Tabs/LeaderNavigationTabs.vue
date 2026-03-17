<script setup>
import { Link } from '@inertiajs/vue3';
const props = defineProps({
    active: { type: String, default: '' },
});
// Leader カラー: orange
const tab = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    props.active === key
        ? 'bg-orange-100 text-orange-700'
        : 'border border-orange-200 text-orange-600 hover:bg-orange-50 hover:text-orange-800',
];
</script>

<template>
    <div class="mb-6">
        <nav class="flex space-x-8" aria-label="Tabs">
            <Link
                :href="route('leader.clients.index')"
                :class="tab('clients')"
            >
                クライアント管理
            </Link>
            <Link
                :href="route('leader.diaryinteractions.index')"
                :class="tab('diaries')"
            >
                日報管理
            </Link>
            <Link
                :href="route('leader.workload_analyzer.index')"
                :class="tab('workload')"
            >
                作業量分析
            </Link>
            <Link
                :href="route('workload_setting.index')"
                :class="tab('workload_setting')"
            >
                作業項目設定
            </Link>
        </nav>
    </div>
</template>
