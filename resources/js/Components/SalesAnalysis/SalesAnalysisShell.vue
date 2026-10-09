<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ToastUnified from '@/Components/ToastUnified.vue';
import SuperAdminContextSwitcher from '@/Components/SuperAdminContextSwitcher.vue';
import SalesAnalysisNavigationTabs from '@/Components/SalesAnalysis/SalesAnalysisNavigationTabs.vue';

// 売上分析配下全画面の独立レイアウト（2026-10-09。実機フィードバック対応）。
// AppLayoutは使わない（通常の全社共通ヘッダー・役割別タブ・通知アイコン類を持つと、
// 列数の多い表やグラフを広く見せたい売上分析の用途では視認範囲が狭くなるため）。
// 画面を完全に裸にするCoordinator「銀本進行」（LedgerIndex.vue）とは異なり、
// SuperAdminが分析対象の会社を切り替えられる`SuperAdminContextSwitcher`だけは
// 機能上必須のため、最小限の専用バーとして残している。
// 通知ベル・メール・カレンダー・ヘルプガイド・更新ログ等は売上分析から抜けないと使えない
// （`戻る`リンクで各ロールのダッシュボードへ戻ればすぐに使える）。

const props = defineProps({
    title: { type: String, required: true },
    routePrefix: { type: String, required: true },
    active: { type: String, default: '' },
});

const page = usePage();
const isSuperAdmin = computed(() => !!page.props.auth?.user?.isSuperAdmin);
const dashboardHref = computed(() => {
    try {
        return route(`${props.routePrefix}.dashboard`);
    } catch {
        return null;
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
                    <Link
                        v-if="dashboardHref"
                        :href="dashboardHref"
                        class="rounded bg-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                    >← 通常メニューへ</Link>
                    <h1 class="truncate text-base font-semibold text-gray-800 sm:text-lg">{{ title }}</h1>
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
                <SalesAnalysisNavigationTabs :route-prefix="routePrefix" :active="active" />
            </div>
        </div>

        <div class="px-3 py-3 sm:px-4">
            <slot />
        </div>
    </div>
</template>
