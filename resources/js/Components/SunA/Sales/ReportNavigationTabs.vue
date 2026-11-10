<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

// サンエー印刷 売上レポート（営業）の全画面を横断移動する共通タブ。サン・ブレーンの売上分析の
// SalesAnalysisNavigationTabs と同じ見た目・グループ分け。画面が増えたら（段階2・3）ここにタブを足す。
// 取込は「取込できる人」にだけ出す（auth.salesReportCanImport。サーバー側のルートでも同じ条件で保護している）。

const props = defineProps({
    active: { type: String, default: '' },
});

const page = usePage();
const canImport = computed(() => !!page.props.auth?.salesReportCanImport);

function tryRoute(name) {
    try {
        return route(`suna.sales.report.${name}`);
    } catch {
        return null;
    }
}

const groups = computed(() => [
    {
        label: '概要',
        tabs: [{ key: 'index', route: 'index', label: 'データ登録状況' }],
    },
    {
        label: '設定・取込',
        tabs: [
            { key: 'import', route: 'import.create', label: 'Excel取込', show: canImport.value },
            { key: 'history', route: 'history.index', label: '取込履歴' },
        ],
    },
]
    .map((g) => ({ ...g, tabs: g.tabs.filter((t) => t.show !== false).map((t) => ({ ...t, href: tryRoute(t.route) })).filter((t) => t.href) }))
    .filter((g) => g.tabs.length > 0));

const tabClass = (key) => [
    'rounded-md px-3 py-1.5 text-xs sm:text-sm font-medium whitespace-nowrap',
    props.active === key ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100',
];
</script>

<template>
    <nav class="flex flex-wrap items-center gap-x-2 gap-y-1">
        <template v-for="(g, gi) in groups" :key="g.label">
            <span v-if="gi > 0" class="mx-1 hidden h-5 border-l border-gray-300 sm:inline-block"></span>
            <span class="text-[10px] text-gray-400">{{ g.label }}</span>
            <Link v-for="t in g.tabs" :key="t.key" :href="t.href" :class="tabClass(t.key)">{{ t.label }}</Link>
        </template>
    </nav>
</template>
