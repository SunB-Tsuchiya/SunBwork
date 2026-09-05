<script setup>
import { Link, router } from '@inertiajs/vue3';
import SuperAdminNavigationTabs from '@/Components/Tabs/SuperAdminNavigationTabs.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';

// 売上分析配下の全画面（データ登録状況・月次/年次/期別/同月/左右比較・得意先分析・
// 得意先統合設定・Excel取込・取込履歴）を横断移動するための共通タブ
// （実機フィードバック対応、2026-09-04）。
// これまで画面ごとにヘッダーへ個別のリンクボタンを置いていたため、画面によって移動先が
// 揃っておらず迷いやすかった問題を解消する。AppLayoutの`#tabs`スロットで使う既存パターン
// （例: UserNavigationTabs）に合わせている。
// このコンポーネントが`#tabs`を上書きすると、AppLayout既定の役割別（SuperAdmin等）タブが
// 出なくなってしまうため、routePrefixから役割を判定して同じ役割別タブもあわせて描画する
// （実機フィードバック対応: 役割別タブも残したい、2026-09-04）。
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

const tabs = [
    { key: 'dashboard', route: 'dashboard', label: 'データ登録状況' },
    { key: 'monthly', route: 'monthly_analysis', label: '月次分析' },
    { key: 'annual', route: 'annual_analysis', label: '年次分析' },
    { key: 'fiscal_year', route: 'fiscal_year_analysis', label: '期別分析' },
    { key: 'same_month', route: 'same_month_comparison', label: '同月比較' },
    { key: 'side_by_side', route: 'side_by_side_comparison', label: '左右比較' },
    { key: 'client_analysis', route: 'client_analysis', label: '得意先分析' },
    { key: 'product_analysis', route: 'product_analysis', label: '商品分析' },
    { key: 'client_groups', route: 'client_groups.index', label: '得意先統合設定' },
    { key: 'import', route: 'import.create', label: 'Excel取込' },
    { key: 'import_history', route: 'import_history.index', label: '取込履歴' },
]
    .map((t) => ({ ...t, href: tryRoute(t.route) }))
    .filter((t) => t.href);

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
        <!-- 役割別タブ（SuperAdmin/Admin/Clerk）。AppLayout既定と同じものをここでも表示する -->
        <SuperAdminNavigationTabs v-if="routePrefix === 'superadmin'" active="sales_analysis" />
        <AdminNavigationTabs v-else-if="routePrefix === 'admin'" active="sales_analysis" />
        <ClerkNavigationTabs v-else-if="routePrefix === 'clerk'" active="sales_analysis" />

        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-2 shadow-sm">
            <!-- モバイル: ドロップダウン -->
            <div class="sm:hidden">
                <select
                    class="w-full rounded-md border border-indigo-300 bg-white px-3 py-2 text-sm text-indigo-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    @change="onMobileSelect"
                >
                    <option value="">— ページを選択 —</option>
                    <option v-for="t in tabs" :key="t.key" :value="t.href" :selected="active === t.key">{{ t.label }}</option>
                </select>
            </div>

            <!-- デスクトップ: タブ -->
            <nav class="hidden flex-wrap gap-2 sm:flex" aria-label="Tabs">
                <Link v-for="t in tabs" :key="t.key" :href="t.href" :class="tabClass(t.key)">{{ t.label }}</Link>
            </nav>
        </div>
    </div>
</template>
