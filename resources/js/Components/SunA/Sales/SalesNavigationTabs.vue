<script setup>
import { Link } from '@inertiajs/vue3';

// 営業エリア（AppLayout を使う画面）の共通タブ: ダッシュボード / カレンダー / 売上分析（営業はリマインダー機能を使わない）。
// 売上分析は独立レイアウトの売上レポート（ReportShell）へ移る。営業の色: indigo。
defineProps({
    active: { type: String, default: '' },
});

const tab = (active, key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    active === key
        ? 'bg-indigo-100 text-indigo-700'
        : 'border border-indigo-200 text-indigo-600 hover:bg-indigo-50 hover:text-indigo-800',
];
</script>

<template>
    <div class="mb-4">
        <nav class="flex flex-wrap gap-2" aria-label="Tabs">
            <Link :href="route('suna.sales.dashboard')" :class="tab(active, 'dashboard')">ダッシュボード</Link>
            <Link :href="route('suna.sales.calendar')" :class="tab(active, 'calendar')">カレンダー</Link>
            <Link :href="route('suna.sales.report.index')" :class="tab(active, 'sales_analysis')">売上分析</Link>
        </nav>
    </div>
</template>
