<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useSalesChart } from '@/Composables/useSalesChart';

// 得意先/分類/項目で共用するTop10/20＋全件詳細ドロワー（REVIEW3 12.2・15.1節、
// 2026-09-04 Phase 12、月次分析を完成見本として新設）。
// `fetchPage`は親が渡すAPI呼び出し関数（部署・年月・統合ON/OFFは親側のクロージャで確定済み）。
// 期間が変わったら親が`refreshKey`を変えることで、このコンポーネントが自動的に再取得する。

const props = defineProps({
    title: { type: String, required: true },
    // (params: {limit, page, keyword, sort, direction, mode}) => Promise<{rows, total_count}>
    fetchPage: { type: Function, required: true },
    // [{ value, label }] 省略時はモード切替なし（分類/項目パネル用）
    modes: { type: Array, default: () => [] },
    // [{ sort, direction, label }] 並べ替え選択肢
    sortOptions: {
        type: Array,
        default: () => [{ sort: 'amount', direction: 'desc', label: '金額順' }],
    },
    // modeトグルが無くても常に差額/増減率列を表示したい場合（例: 年次分析の前年同期間比較）
    diffColumns: { type: Boolean, default: false },
    // 差額列が「何との差額か」を明示するための見出し（例: 年次分析は「前年差」「前年比」）
    diffLabel: { type: String, default: '差額' },
    rateLabel: { type: String, default: '増減率' },
    // 行クリックで遷移先が無いパネル（分類/項目等）では false にし、
    // クリックできそうに見えるのに反応しない状態を避ける（実機フィードバック対応、2026-09-04）
    clickable: { type: Boolean, default: true },
    refreshKey: { type: [String, Number], default: '' },
});

const emit = defineEmits(['rows-updated', 'select-row']);

const { yen, pct, pctClass } = useSalesChart();

const topLimit = ref(10);
const topRows = ref([]);
const topTotalCount = ref(0);
const mode = ref(props.modes[0]?.value ?? 'current');
const sortChoiceIndex = ref(0);

const loadingTop = ref(false);

const currentSortOption = () => props.sortOptions[sortChoiceIndex.value] ?? props.sortOptions[0];

const loadTop = async () => {
    loadingTop.value = true;
    try {
        const opt = currentSortOption();
        const result = await props.fetchPage({
            limit: topLimit.value,
            page: 1,
            keyword: '',
            sort: opt.sort,
            direction: opt.direction,
            mode: mode.value,
        });
        topRows.value = result.rows;
        topTotalCount.value = result.total_count;
        emit('rows-updated', result.rows);
    } finally {
        loadingTop.value = false;
    }
};

watch([topLimit, mode, sortChoiceIndex, () => props.refreshKey], loadTop);
onMounted(loadTop);

// --- 全件詳細ドロワー ---
const drawerOpen = ref(false);
const drawerRows = ref([]);
const drawerTotalCount = ref(0);
const drawerPage = ref(1);
const drawerLimit = ref(20);
const drawerKeyword = ref('');
const drawerLoading = ref(false);
let keywordDebounceTimer = null;

const loadDrawer = async () => {
    drawerLoading.value = true;
    try {
        const opt = currentSortOption();
        const result = await props.fetchPage({
            limit: drawerLimit.value,
            page: drawerPage.value,
            keyword: drawerKeyword.value,
            sort: opt.sort,
            direction: opt.direction,
            mode: mode.value,
        });
        drawerRows.value = result.rows;
        drawerTotalCount.value = result.total_count;
    } finally {
        drawerLoading.value = false;
    }
};

const openDrawer = () => {
    drawerOpen.value = true;
    drawerPage.value = 1;
    drawerKeyword.value = '';
    loadDrawer();
};

watch(drawerKeyword, () => {
    if (!drawerOpen.value) return;
    clearTimeout(keywordDebounceTimer);
    keywordDebounceTimer = setTimeout(() => {
        drawerPage.value = 1;
        loadDrawer();
    }, 300);
});

watch([drawerPage, sortChoiceIndex, mode], () => {
    if (drawerOpen.value) loadDrawer();
});

watch(() => props.refreshKey, () => {
    if (drawerOpen.value) {
        drawerPage.value = 1;
        loadDrawer();
    }
});

const drawerMaxPage = () => Math.max(1, Math.ceil(drawerTotalCount.value / drawerLimit.value));

const showDiff = computed(() => props.diffColumns || (props.modes.length > 0 && mode.value !== 'current'));
</script>

<template>
    <div>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-gray-900">{{ title }}</h3>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <div v-if="modes.length" class="flex rounded border">
                    <button
                        v-for="m in modes"
                        :key="m.value"
                        type="button"
                        class="px-2 py-1"
                        :class="mode === m.value ? 'bg-indigo-600 text-white' : 'text-gray-600'"
                        @click="mode = m.value"
                    >{{ m.label }}</button>
                </div>
                <label class="flex items-center gap-1">
                    並び替え:
                    <select v-model.number="sortChoiceIndex" class="rounded border-gray-300 py-1">
                        <option v-for="(opt, idx) in sortOptions" :key="idx" :value="idx">{{ opt.label }}</option>
                    </select>
                </label>
                <div class="flex rounded border">
                    <button type="button" class="px-2 py-1" :class="topLimit === 10 ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="topLimit = 10">10件</button>
                    <button type="button" class="px-2 py-1" :class="topLimit === 20 ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="topLimit = 20">20件</button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="py-1">{{ title }}</th>
                        <th class="py-1 text-right">金額</th>
                        <th v-if="showDiff" class="py-1 text-right">{{ diffLabel }}</th>
                        <th v-if="showDiff" class="py-1 text-right">{{ rateLabel }}</th>
                        <th v-else class="py-1 text-right">構成比</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in topRows"
                        :key="row.label"
                        class="border-t"
                        :class="clickable ? 'cursor-pointer hover:bg-gray-50' : ''"
                        @click="clickable && emit('select-row', row.label)"
                    >
                        <td class="py-1">{{ row.label }}</td>
                        <td class="py-1 text-right">{{ yen(row.amount) }}</td>
                        <template v-if="showDiff">
                            <td class="py-1 text-right" :class="pctClass(row.rate)">{{ row.diff !== null ? yen(row.diff) : '—' }}</td>
                            <td class="py-1 text-right" :class="pctClass(row.rate)">{{ row.rate !== null ? pct(row.rate) : '—' }}</td>
                        </template>
                        <td v-else class="py-1 text-right">{{ row.share_pct !== null ? `${row.share_pct}%` : '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <button
            v-if="topTotalCount > topLimit"
            type="button"
            class="mt-2 text-xs text-indigo-600 hover:underline"
            @click="openDrawer"
        >全件を見る（{{ topTotalCount }}件）</button>

        <!-- 全件詳細ドロワー -->
        <div v-if="drawerOpen" class="fixed inset-0 z-40 flex justify-end bg-black/30" @click.self="drawerOpen = false">
            <div class="h-full w-full max-w-lg overflow-y-auto bg-white p-4 shadow-xl">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-gray-900">{{ title }}（全件）</h4>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="drawerOpen = false">✕</button>
                </div>
                <input
                    v-model="drawerKeyword"
                    type="text"
                    placeholder="検索"
                    class="mb-3 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                />
                <p v-if="drawerLoading" class="text-xs text-gray-400">読み込み中...</p>
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-1">{{ title }}</th>
                            <th class="py-1 text-right">金額</th>
                            <th v-if="showDiff" class="py-1 text-right">{{ diffLabel }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in drawerRows"
                            :key="row.label"
                            class="border-t"
                            :class="clickable ? 'cursor-pointer hover:bg-gray-50' : ''"
                            @click="clickable && (emit('select-row', row.label), (drawerOpen = false))"
                        >
                            <td class="py-1">{{ row.label }}</td>
                            <td class="py-1 text-right">{{ yen(row.amount) }}</td>
                            <td v-if="showDiff" class="py-1 text-right" :class="pctClass(row.rate)">{{ row.diff !== null ? yen(row.diff) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <div class="mt-3 flex items-center justify-between text-xs">
                    <button type="button" class="rounded border px-2 py-1 disabled:opacity-40" :disabled="drawerPage <= 1" @click="drawerPage -= 1; loadDrawer()">← 前へ</button>
                    <span>{{ drawerPage }} / {{ drawerMaxPage() }}ページ（{{ drawerTotalCount }}件）</span>
                    <button type="button" class="rounded border px-2 py-1 disabled:opacity-40" :disabled="drawerPage >= drawerMaxPage()" @click="drawerPage += 1; loadDrawer()">次へ →</button>
                </div>
            </div>
        </div>
    </div>
</template>
