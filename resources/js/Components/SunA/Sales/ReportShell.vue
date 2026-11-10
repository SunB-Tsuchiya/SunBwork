<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ToastUnified from '@/Components/ToastUnified.vue';
import SuperAdminContextSwitcher from '@/Components/SuperAdminContextSwitcher.vue';
import ReportNavigationTabs from '@/Components/SunA/Sales/ReportNavigationTabs.vue';

// サンエー印刷 売上レポート（営業）配下の全画面の独立レイアウト。
// サン・ブレーンの売上分析（SalesAnalysisShell）と同じ作り: AppLayout は使わず、左右いっぱいに表やグラフを広く見せる。
// SuperAdmin が分析対象の会社を切り替えられる SuperAdminContextSwitcher だけは、機能上必須のため残す。
// サン・ブレーンの SalesAnalysisShell は変更しない（SunA の専用コンポーネントとして別に持つ）。

defineProps({
    title: { type: String, required: true },
    active: { type: String, default: '' },
});

const page = usePage();
const isSuperAdmin = computed(() => !!page.props.auth?.user?.isSuperAdmin);
const dashboardHref = computed(() => {
    try {
        return route('dashboard');
    } catch {
        return '/';
    }
});
</script>

<template>
    <Head :title="title" />
    <div class="min-h-screen bg-gray-100">
        <ToastUnified />

        <div class="sticky top-0 z-40 border-b border-gray-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 sm:px-4">
                <div class="flex min-w-0 items-center gap-3">
                    <Link :href="dashboardHref" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-300">← 通常メニューへ</Link>
                    <h1 class="truncate text-base font-semibold text-gray-800 sm:text-lg">売上レポート<span class="ml-2 text-sm font-normal text-gray-500">{{ title }}</span></h1>
                </div>
                <div class="flex flex-shrink-0 items-center gap-3">
                    <slot name="actions" />
                    <template v-if="isSuperAdmin">
                        <span class="text-xs font-medium text-gray-500">会社切替:</span>
                        <SuperAdminContextSwitcher />
                    </template>
                </div>
            </div>
            <div class="px-3 pb-2 sm:px-4">
                <ReportNavigationTabs :active="active" />
            </div>
        </div>

        <div class="px-3 py-3 sm:px-4">
            <slot />
        </div>
    </div>
</template>
