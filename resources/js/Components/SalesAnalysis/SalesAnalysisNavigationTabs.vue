<script setup>
import { Link, router } from '@inertiajs/vue3';

// 売上分析配下の全画面（データ登録状況・月次/年次/期別/同月/左右比較・得意先分析・
// 得意先統合設定・Excel取込・取込履歴）を横断移動するための共通タブ
// （実機フィードバック対応、2026-09-04）。
// これまで画面ごとにヘッダーへ個別のリンクボタンを置いていたため、画面によって移動先が
// 揃っておらず迷いやすかった問題を解消する。
// 2026-10-09: 売上分析配下はAppLayoutを使わない独立レイアウト（SalesAnalysisShell.vue）へ
// 移行したため、以前ここで補っていた役割別タブ（SuperAdmin/Admin/Clerk）の再描画は不要になり削除した
// （画面を広く使いたいという実機フィードバックを受けた変更）。
// なお「年度累計の暦年/年度切替」スイッチは一度ここに設置したが、「年と期の分類が
// 分かりにくい」との指摘を受けて撤去し、代わりに「期別分析」を独立画面として新設した
// （実機フィードバック対応、2026-09-04）。

const props = defineProps({
    routePrefix: { type: String, required: true },
    active: { type: String, default: '' },
});

const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;

function tryRoute(name) {
    try {
        return route(rn(name));
    } catch {
        return null;
    }
}

// 種別ごとにグループ化（実機フィードバック対応、2026-10-09。画面数が増え平置きだと
// 探しにくくなったため、概要/売上分析/経費/設定・取込の4グループに分けた）
const tabGroups = [
    {
        label: '概要',
        tabs: [{ key: 'dashboard', route: 'dashboard', label: 'データ登録状況' }],
    },
    {
        label: '売上分析',
        tabs: [
            { key: 'monthly', route: 'monthly_analysis', label: '月次分析' },
            { key: 'annual', route: 'annual_analysis', label: '年次分析' },
            { key: 'fiscal_year', route: 'fiscal_year_analysis', label: '期別分析' },
            { key: 'same_month', route: 'same_month_comparison', label: '同月比較' },
            { key: 'side_by_side', route: 'side_by_side_comparison', label: '左右比較' },
            { key: 'client_analysis', route: 'client_analysis', label: '得意先分析' },
            { key: 'product_analysis', route: 'product_analysis', label: '商品分析' },
        ],
    },
    {
        label: '経費',
        tabs: [{ key: 'expense_comparison', route: 'expense_comparison', label: '経費比較' }],
    },
    {
        label: '設定・取込',
        tabs: [
            { key: 'client_groups', route: 'client_groups.index', label: '得意先統合設定' },
            // 年度別データ取込・経費取込の2本は入口を1つにまとめている（実機フィードバック対応、2026-10-09）
            { key: 'import_hub', route: 'import_hub', label: 'Excel取込' },
            { key: 'import_history', route: 'import_history.index', label: '取込履歴' },
        ],
    },
]
    .map((g) => ({ ...g, tabs: g.tabs.map((t) => ({ ...t, href: tryRoute(t.route) })).filter((t) => t.href) }))
    .filter((g) => g.tabs.length > 0);

const tabClass = (key) => [
    'rounded-md px-3 py-1.5 text-xs sm:text-sm font-medium whitespace-nowrap',
    props.active === key
        ? 'bg-indigo-100 text-indigo-700'
        : 'border border-indigo-200 text-indigo-600 hover:bg-indigo-50 hover:text-indigo-800',
];

function onMobileSelect(e) {
    const href = e.target.value;
    if (href) router.get(href);
}
</script>

<template>
    <div>
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-2 shadow-sm">
            <!-- モバイル: ドロップダウン（種別をoptgroupで表現） -->
            <div class="sm:hidden">
                <select
                    class="w-full rounded-md border border-indigo-300 bg-white px-3 py-2 text-sm text-indigo-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    @change="onMobileSelect"
                >
                    <option value="">— ページを選択 —</option>
                    <optgroup v-for="g in tabGroups" :key="g.label" :label="g.label">
                        <option v-for="t in g.tabs" :key="t.key" :value="t.href" :selected="active === t.key">{{ t.label }}</option>
                    </optgroup>
                </select>
            </div>

            <!-- デスクトップ: 種別ごとに区切ったタブ -->
            <nav class="hidden flex-wrap items-center gap-x-4 gap-y-2 sm:flex" aria-label="Tabs">
                <template v-for="(g, gi) in tabGroups" :key="g.label">
                    <div v-if="gi > 0" class="h-5 w-px self-center bg-gray-300" aria-hidden="true"></div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ g.label }}</span>
                        <Link v-for="t in g.tabs" :key="t.key" :href="t.href" :class="tabClass(t.key)">{{ t.label }}</Link>
                    </div>
                </template>
            </nav>
        </div>
    </div>
</template>
