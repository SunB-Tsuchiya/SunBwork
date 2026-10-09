<script setup>
import SalesAnalysisShell from '@/Components/SalesAnalysis/SalesAnalysisShell.vue';
import { Link } from '@inertiajs/vue3';

// 「Excel取込」タブの入口。年度別データ（売上Excel）取込と経費取込のどちらに進むかを
// ここで選ばせる（実機フィードバック対応、2026-10-09。従来は2本が別タブに分かれていた）。

const props = defineProps({
    routePrefix: { type: String, required: true },
    hasCompanySelected: { type: Boolean, default: true },
    supportsExpenses: { type: Boolean, default: false },
});

const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;
</script>

<template>
    <SalesAnalysisShell title="Excel取込" :route-prefix="routePrefix" active="import_hub">
        <div v-if="!hasCompanySelected" class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">会社が選択されていません。画面右上の会社切替から対象の会社を選択してください。</p>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2">
            <Link
                :href="route(rn('import.create'))"
                class="block rounded-lg border border-gray-200 bg-white p-6 shadow hover:border-indigo-300 hover:shadow-md"
            >
                <h3 class="text-sm font-semibold text-gray-900">年度別データの読み込み</h3>
                <p class="mt-2 text-xs text-gray-500">月次・年次の売上データ（受注Excel）を部署別に取り込みます。</p>
            </Link>

            <Link
                v-if="supportsExpenses"
                :href="route(rn('expense_import.create'))"
                class="block rounded-lg border border-gray-200 bg-white p-6 shadow hover:border-indigo-300 hover:shadow-md"
            >
                <h3 class="text-sm font-semibold text-gray-900">経費読み込み</h3>
                <p class="mt-2 text-xs text-gray-500">部署別経費表（主材料費・外注加工費等）を取り込みます。売上とは別集計の参考情報です。</p>
            </Link>
            <div v-else class="rounded-lg border border-gray-100 bg-gray-50 p-6">
                <h3 class="text-sm font-semibold text-gray-400">経費読み込み</h3>
                <p class="mt-2 text-xs text-gray-400">この会社では利用できません。</p>
            </div>
        </div>
    </SalesAnalysisShell>
</template>
