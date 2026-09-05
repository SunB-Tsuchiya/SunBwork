<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Chart from 'chart.js/auto';
import { useSalesChart } from '@/Composables/useSalesChart';
import PeriodNavigator from '@/Components/SalesAnalysis/PeriodNavigator.vue';
import RankingPanel from '@/Components/SalesAnalysis/RankingPanel.vue';
import SalesAnalysisNavigationTabs from '@/Components/SalesAnalysis/SalesAnalysisNavigationTabs.vue';

// 月次分析（REVIEW3 13.2節、2026-09-04 Phase 12でPriority Aの完成見本として全面改修）。
// 期間ナビゲーター・Top10/20+全件詳細ドロワーはresources/js/Components/SalesAnalysis/配下の
// 共通部品を使う。他画面（年次・同月比較・得意先分析・左右比較）への展開は次のステップで行う。

const props = defineProps({
    routePrefix: { type: String, required: true },
    departmentLabels: { type: Object, default: () => ({}) },
    enabledDepartmentKeys: { type: Array, default: () => [] },
    initialDepartmentKey: { type: String, default: 'planning' },
    initialYear: { type: Number, required: true },
    initialMonth: { type: Number, required: true },
    initialLatestPeriod: { type: Object, default: null },
    hasAnyData: { type: Boolean, default: false },
});

// 売上分析ルートは superadmin/admin/clerk の各ロールグループ内に複製登録されている
const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;

const { yen, pct, pctClass } = useSalesChart();

const departmentKey = ref(props.initialDepartmentKey);
const year = ref(props.initialYear);
const month = ref(props.initialMonth);
const consolidateClients = ref(false);
const detailTab = ref('category'); // 'category' | 'item'
const detailSearchOpen = ref(false);

const loading = ref(false);
const errorMessage = ref('');

const summary = ref(null);
const periodStatus = ref(null);
const recentTrend = ref([]);
const sameMonthHistory = ref([]);

const productKeyword = ref('');
const productResults = ref(null);
const productSearching = ref(false);

// --- KPI: 同月3年平均との差（sameMonthHistoryから算出。未登録年はnullとして除外する） ---
const same3yrAvg = computed(() => {
    const idx = sameMonthHistory.value.findIndex((r) => r.year === year.value);
    if (idx < 1) return null;
    const priors = sameMonthHistory.value.slice(Math.max(0, idx - 3), idx).map((r) => r.amount).filter((v) => v !== null);
    const current = sameMonthHistory.value[idx]?.amount;
    if (!priors.length || current === null || current === undefined) return null;

    const average = priors.reduce((a, b) => a + b, 0) / priors.length;
    const diff = current - average;
    return { average, diff, rate: average > 0 ? Math.round((diff / average) * 1000) / 10 : null };
});

// --- KPI: 当月カードのミニsparkline（直近6ヶ月、未登録月は欠けとして扱う）。
// 最後の点=選択中の月を赤丸で示す（実機フィードバック対応、2026-09-04。「今どの月を
// 見ているか分からない」との指摘を受け、13ヶ月推移グラフと同じ考え方で強調表示する） ---
const sparklineGeometry = computed(() => {
    const pts = recentTrend.value.slice(-6).map((m) => m.total_amount);
    const valid = pts.filter((v) => v !== null);
    if (valid.length < 2) return { line: '', lastPoint: null };

    const max = Math.max(...valid);
    const min = Math.min(...valid);
    const range = max - min || 1;
    const stepX = 60 / (pts.length - 1);
    const coords = pts.map((v, i) => (v === null ? null : { x: i * stepX, y: 20 - ((v - min) / range) * 20 }));

    const lastPoint = [...coords].reverse().find((c) => c !== null) ?? null;

    return {
        line: coords.filter(Boolean).map((c) => `${c.x},${c.y}`).join(' '),
        lastPoint,
    };
});

// 「年と期の分類」は「期別分析」画面（4月始まり）として独立させたため、ここは暦年（1〜12月）固定
// （実機フィードバック対応: 小さいトグルは分かりにくいとの指摘、2026-09-04）
const currentFiscal = computed(() => summary.value?.fiscal_calendar ?? null);

const refreshKey = computed(() => `${departmentKey.value}-${year.value}-${month.value}-${consolidateClients.value}`);
const refreshKeyNoConsolidate = computed(() => `${departmentKey.value}-${year.value}-${month.value}`);

// --- URLクエリ同期（Inertia再訪問はせず、リロード・深いリンク・画面間移動用に反映するだけ） ---
const syncQueryString = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('department_key', departmentKey.value);
    url.searchParams.set('year', String(year.value));
    url.searchParams.set('month', String(month.value));
    window.history.replaceState(null, '', url);
};

const fetchCore = async () => {
    loading.value = true;
    errorMessage.value = '';
    productResults.value = null;

    const params = { department_key: departmentKey.value, year: year.value, month: month.value };

    try {
        const [summaryRes, trendRes, historyRes] = await Promise.all([
            axios.get(route(rn('api.summary')), { params }),
            // 13ヶ月推移グラフは削除したため、当月カードのsparkline用に直近6ヶ月分だけ取得する
            // （実機フィードバック対応、2026-09-04）
            axios.get(route(rn('api.trend')), { params: { ...params, months: 6 } }),
            axios.get(route(rn('api.same_month_history')), { params: { ...params, years: 5 } }),
        ]);

        summary.value = summaryRes.data;
        periodStatus.value = summaryRes.data.period_status;
        recentTrend.value = trendRes.data.trend;
        sameMonthHistory.value = historyRes.data.history;

        await nextTick();
        renderSameMonthChart();
    } catch (e) {
        errorMessage.value = 'データの取得に失敗しました。';
    } finally {
        loading.value = false;
    }
};

const searchProducts = async () => {
    if (!productKeyword.value.trim()) {
        productResults.value = null;
        return;
    }
    productSearching.value = true;
    try {
        const response = await axios.get(route(rn('api.products')), {
            params: { department_key: departmentKey.value, year: year.value, month: month.value, keyword: productKeyword.value },
        });
        productResults.value = response.data.orders;
    } catch (e) {
        productResults.value = [];
    } finally {
        productSearching.value = false;
    }
};

// --- 期間ナビゲーター操作 ---
const goLatest = async () => {
    const response = await axios.get(route(rn('api.latest_period')), { params: { department_key: departmentKey.value } });
    if (response.data.latest) {
        year.value = response.data.latest.year;
        month.value = response.data.latest.month;
    }
};

const goRegistered = ({ year: y, month: m }) => {
    year.value = y;
    month.value = m;
};

// --- Chart.js ---
const sameMonthChartRef = ref(null);
const clientChartRef = ref(null);
let sameMonthChartInstance = null;
let clientChartInstance = null;

const renderSameMonthChart = () => {
    if (!sameMonthChartRef.value) return;
    if (sameMonthChartInstance) sameMonthChartInstance.destroy();

    const avg = same3yrAvg.value?.average ?? null;

    sameMonthChartInstance = new Chart(sameMonthChartRef.value.getContext('2d'), {
        data: {
            labels: sameMonthHistory.value.map((r) => `${r.year}年`),
            datasets: [
                {
                    type: 'bar',
                    label: `${month.value}月の売上`,
                    data: sameMonthHistory.value.map((r) => r.amount),
                    backgroundColor: '#16A34A',
                },
                ...(avg !== null
                    ? [{
                        type: 'line',
                        label: '3年平均',
                        data: sameMonthHistory.value.map(() => avg),
                        borderColor: '#DC2626',
                        borderDash: [4, 4],
                        pointRadius: 0,
                    }]
                    : []),
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true, position: 'bottom' } },
            scales: { y: { beginAtZero: true, ticks: { callback: (v) => `¥${Number(v).toLocaleString()}` } } },
            onClick: (evt, elements) => {
                if (!elements.length) return;
                const row = sameMonthHistory.value[elements[0].index];
                if (row) year.value = row.year;
            },
        },
    });
};

const renderClientChart = (rows) => {
    if (!clientChartRef.value) return;
    if (clientChartInstance) clientChartInstance.destroy();

    clientChartInstance = new Chart(clientChartRef.value.getContext('2d'), {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.label),
            datasets: [{ label: '金額', data: rows.map((r) => r.amount), backgroundColor: '#4F46E5' }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { callback: (v) => `¥${Number(v).toLocaleString()}` } } },
        },
    });
};

// --- 内訳パネル（得意先/分類/項目）のAPI呼び出し ---
const fetchClientsPage = async (params) => {
    const response = await axios.get(route(rn('api.clients')), {
        params: {
            department_key: departmentKey.value,
            year: year.value,
            month: month.value,
            consolidate: consolidateClients.value ? 1 : 0,
            mode: params.mode,
            limit: params.limit,
            page: params.page,
            keyword: params.keyword || undefined,
            sort: params.sort,
            direction: params.direction,
        },
    });
    return response.data;
};

const fetchBreakdownPage = (dimension) => async (params) => {
    const response = await axios.get(route(rn(dimension === 'category' ? 'api.categories' : 'api.items')), {
        params: {
            department_key: departmentKey.value,
            year: year.value,
            month: month.value,
            limit: params.limit,
            page: params.page,
            keyword: params.keyword || undefined,
            sort: params.sort,
            direction: params.direction,
        },
    });
    return response.data;
};
const fetchCategoriesPage = fetchBreakdownPage('category');
const fetchItemsPage = fetchBreakdownPage('item');

const clientModes = [
    { value: 'current', label: '当月' },
    { value: 'vs_previous', label: '前月増減' },
    { value: 'vs_previous_year', label: '前年同月増減' },
];
const clientSortOptions = [
    { sort: 'amount', direction: 'desc', label: '金額順' },
    { sort: 'diff', direction: 'desc', label: '増加額順' },
    { sort: 'diff', direction: 'asc', label: '減少額順' },
    { sort: 'rate', direction: 'desc', label: '増減率順' },
];
const breakdownSortOptions = [
    { sort: 'amount', direction: 'desc', label: '金額順' },
    { sort: 'label', direction: 'asc', label: '名称順' },
];

const goToClientAnalysis = (clientName) => {
    router.get(route(rn('client_analysis')), { department_key: departmentKey.value, client_name: clientName });
};

watch([departmentKey, year, month], () => {
    syncQueryString();
    fetchCore();
});
watch(consolidateClients, fetchCore);
watch(same3yrAvg, () => nextTick().then(renderSameMonthChart));

onMounted(() => {
    if (props.hasAnyData) fetchCore();
});
</script>

<template>
    <AppLayout title="月次分析">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route(rn('dashboard'))"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← データ登録状況</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">月次分析</h2>
            </div>
        </template>
        <template #tabs>
            <SalesAnalysisNavigationTabs :route-prefix="routePrefix" active="monthly" />
        </template>

        <div v-if="!hasAnyData" class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">まだ取込データがありません。まずはExcelを取り込んでください。</p>
            <Link :href="route(rn('import.create'))" class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700">
                Excel取込へ
            </Link>
        </div>

        <div v-else class="space-y-6">
            <PeriodNavigator
                v-model:department-key="departmentKey"
                v-model:year="year"
                v-model:month="month"
                v-model:consolidate-clients="consolidateClients"
                :department-labels="departmentLabels"
                :enabled-department-keys="enabledDepartmentKeys"
                :period-status="periodStatus"
                :loading="loading"
                @go-latest="goLatest"
                @go-registered="goRegistered"
            >
                <template #extra>
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                        @click="detailSearchOpen = !detailSearchOpen"
                    >🔍 品名検索</button>
                </template>
            </PeriodNavigator>

            <!-- 品名検索（実機フィードバック対応: ページ下部ではなく期間ナビゲーターのボタンから開閉、2026-09-04） -->
            <div v-if="detailSearchOpen" class="rounded bg-white p-4 shadow">
                <div class="flex gap-2">
                    <input
                        v-model="productKeyword"
                        type="text"
                        placeholder="品名の一部を入力"
                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm"
                        @keyup.enter="searchProducts"
                    />
                    <button type="button" class="rounded-md bg-gray-700 px-4 py-1.5 text-sm font-bold text-white hover:bg-gray-800" @click="searchProducts">
                        {{ productSearching ? '検索中...' : '検索' }}
                    </button>
                </div>
                <div v-if="productResults" class="mt-3 overflow-x-auto">
                    <p v-if="productResults.length === 0" class="text-xs text-gray-500">該当する受注はありません。</p>
                    <table v-else class="min-w-full text-xs">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-1">受注No</th>
                                <th class="py-1">得意先</th>
                                <th class="py-1">品名</th>
                                <th class="py-1 text-right">金額</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="o in productResults" :key="o.order_number" class="border-t">
                                <td class="py-1">{{ o.order_number }}</td>
                                <td class="py-1">{{ o.client_name }}</td>
                                <td class="py-1">{{ o.product_name }}</td>
                                <td class="py-1 text-right">{{ yen(o.order_amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <p v-if="errorMessage" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="loading" class="text-sm text-gray-500">読み込み中...</p>

            <template v-if="summary">
                <!-- A. KPI帯 -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded bg-white p-4 shadow">
                        <div class="flex items-start justify-between">
                            <p class="text-xs text-gray-500">当月売上</p>
                            <div class="text-right">
                                <svg width="60" height="20">
                                    <polyline :points="sparklineGeometry.line" fill="none" stroke="#4F46E5" stroke-width="1.5" />
                                    <circle
                                        v-if="sparklineGeometry.lastPoint"
                                        :cx="sparklineGeometry.lastPoint.x"
                                        :cy="sparklineGeometry.lastPoint.y"
                                        r="2.5"
                                        fill="#DC2626"
                                    />
                                </svg>
                                <p class="text-[10px] text-gray-400">直近6ヶ月（赤丸=今月）</p>
                            </div>
                        </div>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ yen(summary.monthly.current?.total_amount) }}</p>
                        <p
                            v-if="Number(summary.monthly.current?.total_unallocated_amount ?? 0) !== 0"
                            class="mt-1 text-xs text-amber-600"
                            title="受注金額（N列）と明細金額合計（M列）の差額。取込元Excelの内訳が受注金額と一致していません"
                        >
                            未配賦額: {{ yen(summary.monthly.current.total_unallocated_amount) }}
                        </p>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">前月比</p>
                        <p class="mt-1 text-xl font-bold" :class="pctClass(summary.monthly.vs_previous.rate)">{{ pct(summary.monthly.vs_previous.rate) }}</p>
                        <p class="text-xs text-gray-400">{{ yen(summary.monthly.previous?.total_amount) }}</p>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">前年同月比</p>
                        <p class="mt-1 text-xl font-bold" :class="pctClass(summary.monthly.vs_previous_year.rate)">{{ pct(summary.monthly.vs_previous_year.rate) }}</p>
                        <p class="text-xs text-gray-400">{{ yen(summary.monthly.previous_year_same_month?.total_amount) }}</p>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">同月3年平均との差</p>
                        <template v-if="same3yrAvg">
                            <p class="mt-1 text-xl font-bold" :class="pctClass(same3yrAvg.rate)">{{ same3yrAvg.rate !== null ? pct(same3yrAvg.rate) : yen(same3yrAvg.diff) }}</p>
                            <p class="text-xs text-gray-400">平均 {{ yen(same3yrAvg.average) }}</p>
                        </template>
                        <p v-else class="mt-1 text-xl font-bold text-gray-400">比較データなし</p>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">年間累計（1〜12月）</p>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ yen(currentFiscal?.current?.total_amount) }}</p>
                        <p class="text-xs" :class="pctClass(currentFiscal?.rate)">前年同期比 {{ pct(currentFiscal?.rate) }}</p>
                    </div>
                </div>

                <!-- C. 同月の複数年比較 -->
                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-1 text-sm font-semibold text-gray-900">{{ month }}月の複数年比較（直近5年）</h3>
                    <p class="mb-3 text-xs text-gray-400">棒グラフをクリックすると、その年に移動します。破線は3年平均です。</p>
                    <canvas ref="sameMonthChartRef" style="max-height: 260px"></canvas>
                </div>

                <!-- D. 得意先比較 -->
                <div class="rounded bg-white p-4 shadow">
                    <canvas ref="clientChartRef" class="mb-4" style="max-height: 260px"></canvas>
                    <RankingPanel
                        title="得意先別"
                        :fetch-page="fetchClientsPage"
                        :modes="clientModes"
                        :sort-options="clientSortOptions"
                        :refresh-key="refreshKey"
                        @rows-updated="(rows) => { renderClientChart(rows); }"
                        @select-row="goToClientAnalysis"
                    />
                </div>

                <!-- E. 内訳（分類/項目） -->
                <div class="rounded bg-white p-4 shadow">
                    <div class="mb-3 flex gap-2 border-b text-sm">
                        <button type="button" class="border-b-2 px-3 py-2" :class="detailTab === 'category' ? 'border-indigo-600 font-semibold text-indigo-700' : 'border-transparent text-gray-500'" @click="detailTab = 'category'">分類別</button>
                        <button type="button" class="border-b-2 px-3 py-2" :class="detailTab === 'item' ? 'border-indigo-600 font-semibold text-indigo-700' : 'border-transparent text-gray-500'" @click="detailTab = 'item'">項目別</button>
                    </div>
                    <RankingPanel
                        v-if="detailTab === 'category'"
                        title="分類"
                        :fetch-page="fetchCategoriesPage"
                        :sort-options="breakdownSortOptions"
                        :refresh-key="refreshKeyNoConsolidate"
                        :clickable="false"
                    />
                    <RankingPanel
                        v-else
                        title="項目"
                        :fetch-page="fetchItemsPage"
                        :sort-options="breakdownSortOptions"
                        :refresh-key="refreshKeyNoConsolidate"
                        :clickable="false"
                    />
                </div>
            </template>
        </div>
    </AppLayout>
</template>
