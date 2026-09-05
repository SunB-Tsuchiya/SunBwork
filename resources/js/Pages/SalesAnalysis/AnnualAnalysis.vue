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

// 年次分析（REVIEW3 13.3節、2026-09-04 Phase 13で月次分析と同じ共通部品へ展開）。

const props = defineProps({
    routePrefix: { type: String, required: true },
    departmentLabels: { type: Object, default: () => ({}) },
    enabledDepartmentKeys: { type: Array, default: () => [] },
    initialDepartmentKey: { type: String, default: 'planning' },
    initialYear: { type: Number, required: true },
    hasAnyData: { type: Boolean, default: false },
});

// 売上分析ルートは superadmin/admin/clerk の各ロールグループ内に複製登録されている
const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;

const { yen, pct, pctClass } = useSalesChart();

const departmentKey = ref(props.initialDepartmentKey);
const year = ref(props.initialYear);
const consolidateClients = ref(false);
const monthlyTableOpen = ref(false);

const loading = ref(false);
const errorMessage = ref('');
const summary = ref(null);

const productKeyword = ref('');
const productResults = ref(null);
const productSearching = ref(false);
// 品名検索は期間ナビゲーターのボタンから開閉する（実機フィードバック対応、2026-09-04）
const productSearchOpen = ref(false);

const monthLabels = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];

const trendChartRef = ref(null);
let trendChartInstance = null;

// 「月別売上」グラフの重ね年数（実機フィードバック対応: 対前年だけでなく3年/5年でも見たい、2026-09-04）
const yearsToShow = ref(2);
const multiYearSeries = ref([]);

const mutedPalette = ['#9CA3AF', '#F59E0B', '#10B981', '#EC4899'];
const seriesColor = (index, total) => (index === total - 1 ? '#4F46E5' : mutedPalette[index % mutedPalette.length]);

const fetchMultiYearSeries = async () => {
    const response = await axios.get(route(rn('api.annual_multi_year_trend')), {
        params: { department_key: departmentKey.value, year: year.value, years: yearsToShow.value },
    });
    multiYearSeries.value = response.data.series;
    await nextTick();
    renderTrendChart();
};

const renderTrendChart = () => {
    if (!trendChartRef.value || !multiYearSeries.value.length) return;
    if (trendChartInstance) trendChartInstance.destroy();

    const total = multiYearSeries.value.length;

    trendChartInstance = new Chart(trendChartRef.value.getContext('2d'), {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: multiYearSeries.value.map((s, i) => ({
                label: `${s.year}年`,
                data: s.months,
                borderColor: seriesColor(i, total),
                backgroundColor: 'transparent',
                borderWidth: i === total - 1 ? 3 : 1.5,
                borderDash: i === total - 1 ? [] : [4, 4],
                spanGaps: true,
                tension: 0.2,
            })),
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true, position: 'bottom' } },
            scales: { y: { beginAtZero: true, ticks: { callback: (v) => `¥${Number(v).toLocaleString()}` } } },
            // 折れ線グラフは既定のヒット判定（点の真上のみ）だとクリックがほぼ反応しないため、
            // 最も近い点（複数年重ね時はどの年の線に近いかも含む）をクリック対象にする
            // （実機フィードバック対応: 月クリックで移動しない問題、2026-09-04）
            interaction: { mode: 'nearest', intersect: false },
            onClick: (evt, elements) => {
                if (!elements.length) return;
                const { index, datasetIndex } = elements[0];
                const targetYear = multiYearSeries.value[datasetIndex]?.year ?? year.value;
                const m = index + 1;
                router.get(route(rn('monthly_analysis')), { department_key: departmentKey.value === 'all' ? props.enabledDepartmentKeys[0] : departmentKey.value, year: targetYear, month: m });
            },
        },
    });
};

const syncQueryString = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('department_key', departmentKey.value);
    url.searchParams.set('year', String(year.value));
    window.history.replaceState(null, '', url);
};

const fetchSummary = async () => {
    loading.value = true;
    errorMessage.value = '';
    productResults.value = null;

    try {
        const response = await axios.get(route(rn('api.annual_summary')), {
            params: {
                department_key: departmentKey.value,
                year: year.value,
                consolidate_clients: consolidateClients.value ? 1 : 0,
            },
        });
        summary.value = response.data;
        await fetchMultiYearSeries();
    } catch (e) {
        errorMessage.value = 'データの取得に失敗しました。';
        summary.value = null;
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
        const response = await axios.get(route(rn('api.annual_products')), {
            params: { department_key: departmentKey.value, year: year.value, keyword: productKeyword.value },
        });
        productResults.value = response.data.orders;
    } catch (e) {
        productResults.value = [];
    } finally {
        productSearching.value = false;
    }
};

const goLatest = async () => {
    const response = await axios.get(route(rn('api.annual_latest_period')), { params: { department_key: departmentKey.value } });
    if (response.data.latest) year.value = response.data.latest.year;
};

const comparisonRangeLabel = computed(() => {
    if (!summary.value) return '';
    const [start, end] = summary.value.comparison_month_range;
    return start === end ? `${start}月のみ` : `${start}〜${end}月`;
});

// REVIEW3 11.2節High-2対応: 欠落月があっても比較数値は隠さず、警告のみ表示する（確定方針）
const missingMonthsLabel = computed(() => {
    if (!summary.value || !summary.value.missing_months?.length) return '';
    return summary.value.missing_months.map((m) => `${m}月`).join('・');
});

const monthStateClass = (m) => {
    if (m.state === 'future') return 'text-gray-300';
    if (m.state === 'no_data') return 'text-gray-400';
    return 'text-gray-900';
};

const refreshKey = computed(() => `${departmentKey.value}-${year.value}-${consolidateClients.value}`);
const refreshKeyNoConsolidate = computed(() => `${departmentKey.value}-${year.value}`);

const fetchClientsPage = async (params) => {
    const response = await axios.get(route(rn('api.annual_clients')), {
        params: {
            department_key: departmentKey.value,
            year: year.value,
            consolidate: consolidateClients.value ? 1 : 0,
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
    const response = await axios.get(route(rn(dimension === 'category' ? 'api.annual_categories' : 'api.annual_items')), {
        params: {
            department_key: departmentKey.value,
            year: year.value,
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

const detailTab = ref('category');
const goToClientAnalysis = (clientName) => router.get(route(rn('client_analysis')), { department_key: departmentKey.value, client_name: clientName });

watch([departmentKey, year], () => {
    syncQueryString();
    fetchSummary();
});
watch(consolidateClients, fetchSummary);
watch(yearsToShow, fetchMultiYearSeries);
onMounted(() => {
    if (props.hasAnyData) fetchSummary();
});
</script>

<template>
    <AppLayout title="年次分析">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route(rn('dashboard'))"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← データ登録状況</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">年次分析</h2>
            </div>
        </template>
        <template #headerExtras>
            <a
                v-if="hasAnyData"
                :href="route(rn('annual_analysis.export'), { department_key: departmentKey, year: year, consolidate_clients: consolidateClients ? 1 : 0 })"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-bold text-gray-700 hover:bg-gray-50"
            >Excel出力</a>
        </template>
        <template #tabs>
            <SalesAnalysisNavigationTabs :route-prefix="routePrefix" active="annual" />
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
                v-model:consolidate-clients="consolidateClients"
                granularity="year"
                allow-all-departments
                :department-labels="departmentLabels"
                :enabled-department-keys="enabledDepartmentKeys"
                :loading="loading"
                @go-latest="goLatest"
            >
                <template #extra>
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                        @click="productSearchOpen = !productSearchOpen"
                    >🔍 品名検索</button>
                </template>
            </PeriodNavigator>

            <!-- 品名検索（実機フィードバック対応: ページ下部ではなく期間ナビゲーターのボタンから開閉、2026-09-04） -->
            <div v-if="productSearchOpen" class="rounded bg-white p-4 shadow">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">品名検索（{{ year }}年・登録済み全月対象）</h3>
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
                                <th class="py-1">月</th>
                                <th class="py-1">受注No</th>
                                <th class="py-1">得意先</th>
                                <th class="py-1">品名</th>
                                <th class="py-1 text-right">金額</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="o in productResults" :key="o.order_number" class="border-t">
                                <td class="py-1">{{ o.sales_month }}月</td>
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
                <p v-if="missingMonthsLabel" class="rounded bg-amber-50 p-3 text-xs text-amber-700">
                    ⚠ {{ summary.year }}年は{{ missingMonthsLabel }}のデータが未登録です（登録済み{{ summary.months_registered }}ヶ月）。期間合計には登録済みの月のみを含めています。
                </p>

                <!-- KPIカード -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">
                            {{ summary.comparison_mode === 'partial' ? `年間売上（${comparisonRangeLabel}・進行中）` : '年間売上' }}
                        </p>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ yen(summary.kpi.period_amount) }}</p>
                        <p
                            v-if="Number(summary.kpi.unallocated_amount ?? 0) !== 0"
                            class="mt-1 text-xs text-amber-600"
                            title="受注金額（N列）と明細金額合計（M列）の差額。取込元Excelの内訳が受注金額と一致していません"
                        >
                            未配賦額: {{ yen(summary.kpi.unallocated_amount) }}
                        </p>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">前年同期比（{{ comparisonRangeLabel }}）</p>
                        <p class="mt-1 text-xl font-bold" :class="pctClass(summary.kpi.amount_rate)">{{ pct(summary.kpi.amount_rate) }}</p>
                        <p class="text-xs text-gray-400">{{ yen(summary.kpi.prior_period_amount) }}</p>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">受注件数</p>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ summary.kpi.order_count }}件</p>
                        <p class="text-xs text-gray-400">前年同期 {{ summary.kpi.prior_order_count }}件</p>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">1案件平均</p>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ yen(summary.kpi.avg_order_amount) }}</p>
                        <p v-if="summary.comparison_mode === 'partial'" class="text-xs text-gray-400">
                            参考: {{ summary.comparison_year }}年通期 {{ yen(summary.kpi.full_prior_year_amount) }}
                        </p>
                    </div>
                </div>

                <!-- 月別推移 -->
                <div class="rounded bg-white p-4 shadow">
                    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">
                            月別売上（{{ yearsToShow === 2 ? `${summary.year}年 対 ${summary.comparison_year}年` : `直近${yearsToShow}年` }}）
                        </h3>
                        <div class="flex rounded border text-xs">
                            <button type="button" class="px-2 py-1" :class="yearsToShow === 2 ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="yearsToShow = 2">対前年</button>
                            <button type="button" class="px-2 py-1" :class="yearsToShow === 3 ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="yearsToShow = 3">過去3年</button>
                            <button type="button" class="px-2 py-1" :class="yearsToShow === 5 ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="yearsToShow = 5">過去5年</button>
                        </div>
                    </div>
                    <p class="mb-3 text-xs text-gray-400">グラフ上の月をクリックすると、その月の月次分析へ移動します。凡例クリックで年ごとの表示/非表示を切り替えられます。</p>
                    <canvas ref="trendChartRef" style="max-height: 280px"></canvas>

                    <button type="button" class="mt-3 text-xs text-indigo-600 hover:underline" @click="monthlyTableOpen = !monthlyTableOpen">
                        {{ monthlyTableOpen ? '▲ 数値表を閉じる' : '▼ 数値表を開く' }}
                    </button>
                    <div v-if="monthlyTableOpen" class="mt-2 overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">月</th>
                                    <th class="py-1 text-right">売上</th>
                                    <th class="py-1 text-right">前年同月</th>
                                    <th class="py-1 text-right">差額</th>
                                    <th class="py-1 text-right">増減率</th>
                                    <th class="py-1 text-right">受注件数</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in summary.monthly" :key="m.month" class="border-t" :class="monthStateClass(m)">
                                    <td class="py-1">
                                        {{ monthLabels[m.month - 1] }}
                                        <span v-if="m.needs_review" title="複数回取込あり">⚠</span>
                                        <span v-if="m.has_issue" title="未配賦額あり">🔺</span>
                                        <span
                                            v-if="m.coverage && !m.coverage.is_complete"
                                            class="rounded bg-amber-100 px-1 text-[10px] font-semibold text-amber-700"
                                            :title="`登録済み部署: ${m.coverage.registered_departments.map((k) => departmentLabels[k] ?? k).join('・')}`"
                                        >一部登録</span>
                                    </td>
                                    <td class="py-1 text-right">{{ m.state === 'future' ? '—' : yen(m.amount) }}</td>
                                    <td class="py-1 text-right">{{ yen(m.prior_year_amount) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(m.rate)">{{ m.diff !== null ? yen(m.diff) : '—' }}</td>
                                    <td class="py-1 text-right" :class="pctClass(m.rate)">{{ m.rate !== null ? pct(m.rate) : '—' }}</td>
                                    <td class="py-1 text-right">{{ m.order_count ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 得意先別（前年同期間との増減寄与込み） -->
                <div class="rounded bg-white p-4 shadow">
                    <RankingPanel
                        title="得意先別"
                        :fetch-page="fetchClientsPage"
                        :sort-options="clientSortOptions"
                        diff-columns
                        diff-label="前年差"
                        rate-label="前年比"
                        :refresh-key="refreshKey"
                        @select-row="goToClientAnalysis"
                    />
                </div>

                <!-- 内訳（分類/項目） -->
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
