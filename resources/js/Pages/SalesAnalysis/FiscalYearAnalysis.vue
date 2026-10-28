<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import SalesAnalysisShell from '@/Components/SalesAnalysis/SalesAnalysisShell.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Chart from 'chart.js/auto';
import { useSalesChart } from '@/Composables/useSalesChart';
import PeriodNavigator from '@/Components/SalesAnalysis/PeriodNavigator.vue';
import RankingPanel from '@/Components/SalesAnalysis/RankingPanel.vue';
import ShareCompositionBar from '@/Components/SalesAnalysis/ShareCompositionBar.vue';

// 期別分析（4月始まり〜翌3月終わりの会計年度専用画面。実機フィードバック対応で年次分析から
// 独立させた、2026-09-04）。構造は年次分析（AnnualAnalysis.vue）と対応しているが、
// 年またぎ集計になるため月配列・APIパラメータ名（fiscal_year）が異なる。

const props = defineProps({
    routePrefix: { type: String, required: true },
    departmentLabels: { type: Object, default: () => ({}) },
    enabledDepartmentKeys: { type: Array, default: () => [] },
    initialDepartmentKey: { type: String, default: 'planning' },
    initialFiscalYear: { type: Number, required: true },
    hasAnyData: { type: Boolean, default: false },
    hasCompanySelected: { type: Boolean, default: true },
    // サン・ブレーンだけがサンエー印刷経由/独自受注の経路区別を持つ（Phase20）
    supportsOrderChannels: { type: Boolean, default: false },
    // サン・ブレーンだけが部署別経費データ（外注加工費等）を参考情報として持つ（Phase21）
    supportsExpenses: { type: Boolean, default: false },
});

// 売上分析ルートは superadmin/admin/clerk の各ロールグループ内に複製登録されている
const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;

const { yen, pct, pctClass, fiscalPeriodLabel } = useSalesChart();

const departmentKey = ref(props.initialDepartmentKey);
const fiscalYear = ref(props.initialFiscalYear);
const consolidateClients = ref(false);
const monthlyTableOpen = ref(false);

const loading = ref(false);
const errorMessage = ref('');
const summary = ref(null);

const productKeyword = ref('');
const productResults = ref(null);
const productSearching = ref(false);
const productSearchOpen = ref(false);

// 月配列は4月始まり（fiscal_month1=4月 … fiscal_month12=翌3月）
const monthLabels = ['4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月', '1月', '2月', '3月'];

/** fiscal_month(1〜12)を、指定した期（fiscalYear年4月始まり）の実際の暦年月へ変換する */
const fiscalMonthToCalendar = (fy, fiscalMonth) => (fiscalMonth <= 9 ? [fy, fiscalMonth + 3] : [fy + 1, fiscalMonth - 9]);

const trendChartRef = ref(null);
let trendChartInstance = null;

// 「月別売上」グラフの重ね期数（対前期/過去3期/過去5期）
const yearsToShow = ref(2);
const multiYearSeries = ref([]);

const mutedPalette = ['#9CA3AF', '#F59E0B', '#10B981', '#EC4899'];
const seriesColor = (index, total) => (index === total - 1 ? '#4F46E5' : mutedPalette[index % mutedPalette.length]);

const fetchMultiYearSeries = async () => {
    const response = await axios.get(route(rn('api.fiscal_year_multi_year_trend')), {
        params: { department_key: departmentKey.value, fiscal_year: fiscalYear.value, years: yearsToShow.value },
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
                label: props.supportsExpenses ? fiscalPeriodLabel(s.fiscal_year) : `${s.fiscal_year}年度`,
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
            interaction: { mode: 'nearest', intersect: false },
            onClick: (evt, elements) => {
                if (!elements.length) return;
                const { index, datasetIndex } = elements[0];
                const targetFiscalYear = multiYearSeries.value[datasetIndex]?.fiscal_year ?? fiscalYear.value;
                const [calYear, calMonth] = fiscalMonthToCalendar(targetFiscalYear, index + 1);
                router.get(route(rn('monthly_analysis')), { department_key: departmentKey.value === 'all' ? props.enabledDepartmentKeys[0] : departmentKey.value, year: calYear, month: calMonth });
            },
        },
    });
};

const syncQueryString = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('department_key', departmentKey.value);
    url.searchParams.set('fiscal_year', String(fiscalYear.value));
    window.history.replaceState(null, '', url);
};

const fetchSummary = async () => {
    loading.value = true;
    errorMessage.value = '';
    productResults.value = null;

    try {
        const response = await axios.get(route(rn('api.fiscal_year_summary')), {
            params: {
                department_key: departmentKey.value,
                fiscal_year: fiscalYear.value,
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
        const response = await axios.get(route(rn('api.fiscal_year_products')), {
            params: { department_key: departmentKey.value, fiscal_year: fiscalYear.value, keyword: productKeyword.value },
        });
        productResults.value = response.data.orders;
    } catch (e) {
        productResults.value = [];
    } finally {
        productSearching.value = false;
    }
};

const goLatest = async () => {
    const response = await axios.get(route(rn('api.fiscal_year_latest_period')), { params: { department_key: departmentKey.value } });
    if (response.data.latest) fiscalYear.value = response.data.latest.fiscal_year;
};

const comparisonRangeLabel = computed(() => {
    if (!summary.value) return '';
    const [start, end] = summary.value.comparison_month_range;
    return start === end ? `${monthLabels[start - 1]}のみ` : `${monthLabels[start - 1]}〜${monthLabels[end - 1]}`;
});

// REVIEW3 11.2節High-2対応と同じ方針: 欠落月があっても比較数値は隠さず、警告のみ表示する
const missingMonthsLabel = computed(() => {
    if (!summary.value || !summary.value.missing_months?.length) return '';
    return summary.value.missing_months.map((m) => monthLabels[m - 1]).join('・');
});

const monthStateClass = (m) => {
    if (m.state === 'future') return 'text-gray-300';
    if (m.state === 'no_data') return 'text-gray-400';
    return 'text-gray-900';
};

const refreshKey = computed(() => `${departmentKey.value}-${fiscalYear.value}-${consolidateClients.value}`);
const refreshKeyNoConsolidate = computed(() => `${departmentKey.value}-${fiscalYear.value}`);

const fetchClientsPage = async (params) => {
    const response = await axios.get(route(rn('api.fiscal_year_clients')), {
        params: {
            department_key: departmentKey.value,
            fiscal_year: fiscalYear.value,
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
    const response = await axios.get(route(rn(dimension === 'category' ? 'api.fiscal_year_categories' : 'api.fiscal_year_items')), {
        params: {
            department_key: departmentKey.value,
            fiscal_year: fiscalYear.value,
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

// 得意先別/分類別/項目別の構成比（ShareCompositionBar）用。RankingPanelのrows-updatedから
// 表示中のTop N行＋真の合計金額を受け取る（新規API呼び出しは発生させない）
const clientShareRows = ref([]);
const clientShareTotal = ref(null);
const categoryShareRows = ref([]);
const categoryShareTotal = ref(null);
const itemShareRows = ref([]);
const itemShareTotal = ref(null);

const detailTab = ref('category');
const goToClientAnalysis = (clientName) => router.get(route(rn('client_analysis')), { department_key: departmentKey.value, client_name: clientName });

// --- 経費セクション（Phase21。粗利計算はせず、参考情報として並べて表示するだけ） ---
const expenseDepartmentTabs = computed(() => [
    ...props.enabledDepartmentKeys.map((k) => ({ key: k, label: props.departmentLabels[k] ?? k })),
    { key: 'shared', label: '共通費' },
]);
const expenseDepartmentKey = ref(props.enabledDepartmentKeys[0] ?? 'planning');
const expenseLoading = ref(false);
const expenseData = ref(null);
const expenseError = ref('');

// 科目別構成比（ShareCompositionBar）。経費テーブル自体は14列あり2カラム化すると窮屈になるため、
// 表の上に横棒1本で構成比だけを別途見せる（新規API呼び出しは発生させない）
const expenseShareRows = computed(() => (expenseData.value?.categories ?? []).map((c) => ({ label: c.category, amount: c.total_amount })));

// アコーディオン: 開いている科目は1つだけ（同じ科目を再クリックすると閉じる）。
// 取得済みの内訳はcacheに保持し、開閉を繰り返しても再取得しない
const expandedCategory = ref(null);
const expandedLoading = ref(false);
const expenseDetailCache = ref({});

const fetchExpenses = async () => {
    if (!props.supportsExpenses) return;

    expenseLoading.value = true;
    expenseError.value = '';
    expandedCategory.value = null;
    expenseDetailCache.value = {};
    try {
        const response = await axios.get(route(rn('api.fiscal_year_expenses')), {
            params: { department_key: expenseDepartmentKey.value, fiscal_year: fiscalYear.value },
        });
        expenseData.value = response.data;
    } catch (e) {
        expenseError.value = '経費データの取得に失敗しました。';
        expenseData.value = null;
    } finally {
        expenseLoading.value = false;
    }
};

const toggleCategory = async (category) => {
    if (expandedCategory.value === category) {
        expandedCategory.value = null;
        return;
    }

    expandedCategory.value = category;
    if (expenseDetailCache.value[category]) return;

    expandedLoading.value = true;
    try {
        const response = await axios.get(route(rn('api.fiscal_year_expenses.vendors')), {
            params: { department_key: expenseDepartmentKey.value, fiscal_year: fiscalYear.value, category },
        });
        expenseDetailCache.value = { ...expenseDetailCache.value, [category]: response.data };
    } finally {
        expandedLoading.value = false;
    }
};

watch([expenseDepartmentKey, fiscalYear], fetchExpenses);

watch([departmentKey, fiscalYear], () => {
    syncQueryString();
    fetchSummary();
});
watch(consolidateClients, fetchSummary);
watch(yearsToShow, fetchMultiYearSeries);
onMounted(() => {
    if (props.hasAnyData) fetchSummary();
    if (props.supportsExpenses) fetchExpenses();
});
</script>

<template>
    <SalesAnalysisShell title="期別分析（4月〜翌3月）" :route-prefix="routePrefix" active="fiscal_year">
        <template #actions>
            <a
                v-if="hasAnyData"
                :href="route(rn('fiscal_year_analysis.export'), { department_key: departmentKey, fiscal_year: fiscalYear, consolidate_clients: consolidateClients ? 1 : 0 })"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-bold text-gray-700 hover:bg-gray-50"
            >Excel出力</a>
        </template>

        <div v-if="!hasAnyData" class="rounded bg-white p-6 shadow">
            <template v-if="!hasCompanySelected">
                <p class="text-sm text-gray-500">会社が選択されていません。画面右上の会社切替から対象の会社を選択してください。</p>
            </template>
            <template v-else>
                <p class="text-sm text-gray-500">まだ取込データがありません。まずはExcelを取り込んでください。</p>
                <Link :href="route(rn('import.create'))" class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700">
                    Excel取込へ
                </Link>
            </template>
        </div>

        <div v-else class="space-y-6">
            <PeriodNavigator
                v-model:department-key="departmentKey"
                v-model:year="fiscalYear"
                v-model:consolidate-clients="consolidateClients"
                granularity="year"
                year-label="年度"
                :show-fiscal-period="supportsExpenses"
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

            <div v-if="productSearchOpen" class="rounded bg-white p-4 shadow">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">品名検索（{{ supportsExpenses ? fiscalPeriodLabel(fiscalYear) : `${fiscalYear}年度` }}・登録済み全月対象）</h3>
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
                                <th class="py-1">年月</th>
                                <th class="py-1">受注No</th>
                                <th class="py-1">得意先</th>
                                <th class="py-1">品名</th>
                                <th class="py-1 text-right">金額</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="o in productResults" :key="o.order_number" class="border-t">
                                <td class="py-1">{{ o.sales_year }}/{{ o.sales_month }}</td>
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
                    ⚠ {{ supportsExpenses ? fiscalPeriodLabel(summary.fiscal_year) : `${summary.fiscal_year}年度` }}は{{ missingMonthsLabel }}のデータが未登録です（登録済み{{ summary.months_registered }}ヶ月）。期間合計には登録済みの月のみを含めています。
                </p>

                <!-- KPIカード -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">
                            {{ summary.comparison_mode === 'partial' ? `期間売上（${comparisonRangeLabel}・進行中）` : '期間売上' }}
                        </p>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ yen(summary.kpi.period_amount) }}</p>
                        <p
                            v-if="Number(summary.kpi.unallocated_amount ?? 0) !== 0"
                            class="mt-1 text-xs text-amber-600"
                            title="受注金額（N列）と明細金額合計（M列）の差額。取込元Excelの内訳が受注金額と一致していません"
                        >
                            未配賦額: {{ yen(summary.kpi.unallocated_amount) }}
                        </p>
                        <p v-if="supportsOrderChannels" class="mt-1 text-xs">
                            <span class="text-blue-700">サンエー印刷経由 {{ yen(summary.kpi.standard_amount) }}</span>
                            ／
                            <span class="text-orange-700">独自受注 {{ yen(summary.kpi.direct_amount) }}</span>
                        </p>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">前期同期比（{{ comparisonRangeLabel }}）</p>
                        <p class="mt-1 text-xl font-bold" :class="pctClass(summary.kpi.amount_rate)">{{ pct(summary.kpi.amount_rate) }}</p>
                        <p class="text-xs text-gray-400">{{ yen(summary.kpi.prior_period_amount) }}</p>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">受注件数</p>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ summary.kpi.order_count }}件</p>
                        <p class="text-xs text-gray-400">前期同期 {{ summary.kpi.prior_order_count }}件</p>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">1案件平均</p>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ yen(summary.kpi.avg_order_amount) }}</p>
                        <p v-if="summary.comparison_mode === 'partial'" class="text-xs text-gray-400">
                            参考: {{ supportsExpenses ? fiscalPeriodLabel(summary.comparison_fiscal_year) : `${summary.comparison_fiscal_year}年度` }}通期 {{ yen(summary.kpi.full_prior_year_amount) }}
                        </p>
                    </div>
                </div>

                <!-- 月別推移 -->
                <div class="rounded bg-white p-4 shadow">
                    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">
                            月別売上（{{ yearsToShow === 2 ? (supportsExpenses ? `${fiscalPeriodLabel(summary.fiscal_year)} 対 ${fiscalPeriodLabel(summary.comparison_fiscal_year)}` : `${summary.fiscal_year}年度 対 ${summary.comparison_fiscal_year}年度`) : `直近${yearsToShow}期` }}）
                        </h3>
                        <div class="flex rounded border text-xs">
                            <button type="button" class="px-2 py-1" :class="yearsToShow === 2 ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="yearsToShow = 2">対前期</button>
                            <button type="button" class="px-2 py-1" :class="yearsToShow === 3 ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="yearsToShow = 3">過去3期</button>
                            <button type="button" class="px-2 py-1" :class="yearsToShow === 5 ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="yearsToShow = 5">過去5期</button>
                        </div>
                    </div>
                    <p class="mb-3 text-xs text-gray-400">グラフ上の月をクリックすると、その月の月次分析へ移動します。凡例クリックで期ごとの表示/非表示を切り替えられます。</p>
                    <canvas ref="trendChartRef" style="max-height: 280px"></canvas>

                    <button type="button" class="mt-3 text-xs text-indigo-600 hover:underline" @click="monthlyTableOpen = !monthlyTableOpen">
                        {{ monthlyTableOpen ? '▲ 数値表を閉じる' : '▼ 数値表を開く' }}
                    </button>
                    <div v-if="monthlyTableOpen" class="mt-2 overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">年月</th>
                                    <th class="py-1 text-right">売上</th>
                                    <th class="py-1 text-right">前期同月</th>
                                    <th class="py-1 text-right">差額</th>
                                    <th class="py-1 text-right">増減率</th>
                                    <th class="py-1 text-right">受注件数</th>
                                    <template v-if="supportsOrderChannels">
                                        <th class="py-1 text-right text-blue-700">サンエー印刷経由</th>
                                        <th class="py-1 text-right text-orange-700">独自受注</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in summary.monthly" :key="m.fiscal_month" class="border-t" :class="monthStateClass(m)">
                                    <td class="py-1">
                                        {{ m.calendar_year }}/{{ m.calendar_month }}
                                        <span v-if="m.needs_review" title="複数回取込あり">⚠</span>
                                        <span v-if="m.has_issue" title="未配賦額あり">🔺</span>
                                        <span
                                            v-if="m.coverage && !m.coverage.is_complete"
                                            class="rounded bg-amber-100 px-1 text-[10px] font-semibold text-amber-700"
                                            :title="`登録済み部署: ${m.coverage.registered_departments.map((k) => departmentLabels[k] ?? k).join('・')}`"
                                        >一部登録</span>
                                        <span
                                            v-if="supportsOrderChannels && m.registration === 'partial'"
                                            class="rounded bg-purple-100 px-1 text-[10px] font-semibold text-purple-700"
                                            title="サンエー印刷経由/独自受注の片方のみ登録済み"
                                        >一部未登録</span>
                                    </td>
                                    <td class="py-1 text-right">{{ m.state === 'future' ? '—' : yen(m.amount) }}</td>
                                    <td class="py-1 text-right">{{ yen(m.prior_year_amount) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(m.rate)">{{ m.diff !== null ? yen(m.diff) : '—' }}</td>
                                    <td class="py-1 text-right" :class="pctClass(m.rate)">{{ m.rate !== null ? pct(m.rate) : '—' }}</td>
                                    <td class="py-1 text-right">{{ m.order_count ?? '—' }}</td>
                                    <template v-if="supportsOrderChannels">
                                        <td class="py-1 text-right text-blue-700">{{ m.standard_amount !== null ? yen(m.standard_amount) : '—' }}</td>
                                        <td class="py-1 text-right text-orange-700">{{ m.direct_amount !== null ? yen(m.direct_amount) : '—' }}</td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 得意先別（前期同期間との増減寄与込み）。表の隣に構成比の積み上げ棒を並べる -->
                <div class="rounded bg-white p-4 shadow">
                    <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
                        <RankingPanel
                            title="得意先別"
                            :fetch-page="fetchClientsPage"
                            :sort-options="clientSortOptions"
                            diff-columns
                            diff-label="前期差"
                            rate-label="前期比"
                            :refresh-key="refreshKey"
                            @select-row="goToClientAnalysis"
                            @rows-updated="(rows, total) => { clientShareRows = rows; clientShareTotal = total; }"
                        />
                        <ShareCompositionBar title="得意先別構成比" :rows="clientShareRows" :total="clientShareTotal" />
                    </div>
                </div>

                <!-- 内訳（分類/項目）。表の隣に構成比の積み上げ棒を並べる -->
                <div class="rounded bg-white p-4 shadow">
                    <div class="mb-3 flex gap-2 border-b text-sm">
                        <button type="button" class="border-b-2 px-3 py-2" :class="detailTab === 'category' ? 'border-indigo-600 font-semibold text-indigo-700' : 'border-transparent text-gray-500'" @click="detailTab = 'category'">分類別</button>
                        <button type="button" class="border-b-2 px-3 py-2" :class="detailTab === 'item' ? 'border-indigo-600 font-semibold text-indigo-700' : 'border-transparent text-gray-500'" @click="detailTab = 'item'">項目別</button>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
                        <RankingPanel
                            v-if="detailTab === 'category'"
                            title="分類"
                            :fetch-page="fetchCategoriesPage"
                            :sort-options="breakdownSortOptions"
                            :refresh-key="refreshKeyNoConsolidate"
                            :clickable="false"
                            @rows-updated="(rows, total) => { categoryShareRows = rows; categoryShareTotal = total; }"
                        />
                        <RankingPanel
                            v-else
                            title="項目"
                            :fetch-page="fetchItemsPage"
                            :sort-options="breakdownSortOptions"
                            :refresh-key="refreshKeyNoConsolidate"
                            :clickable="false"
                            @rows-updated="(rows, total) => { itemShareRows = rows; itemShareTotal = total; }"
                        />
                        <ShareCompositionBar
                            v-if="detailTab === 'category'"
                            title="分類別構成比"
                            :rows="categoryShareRows"
                            :total="categoryShareTotal"
                        />
                        <ShareCompositionBar
                            v-else
                            title="項目別構成比"
                            :rows="itemShareRows"
                            :total="itemShareTotal"
                        />
                    </div>
                </div>
            </template>

            <!-- 経費（Phase21。売上とは別枠の参考情報。粗利計算は行わない） -->
            <div v-if="supportsExpenses" class="rounded border-2 border-dashed border-gray-300 bg-gray-50 p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">経費（参考情報）</h3>
                        <p class="text-xs text-gray-500">売上とは別集計の経費データです。粗利計算は行っていません。</p>
                    </div>
                    <Link
                        :href="route(rn('expense_comparison'), { department_key: expenseDepartmentKey, fiscal_year: fiscalYear, view: 'sheet' })"
                        class="rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-100"
                    >部署シートで開く（Excel形式・前年比較）→</Link>
                    <div class="flex gap-1 rounded border bg-white p-1 text-xs">
                        <button
                            v-for="tab in expenseDepartmentTabs"
                            :key="tab.key"
                            type="button"
                            class="rounded px-2 py-1"
                            :class="expenseDepartmentKey === tab.key ? 'bg-gray-700 text-white' : 'text-gray-600 hover:bg-gray-100'"
                            @click="expenseDepartmentKey = tab.key"
                        >{{ tab.label }}</button>
                    </div>
                </div>

                <p v-if="expenseLoading" class="text-xs text-gray-500">読み込み中...</p>
                <p v-else-if="expenseError" class="rounded bg-red-50 p-2 text-xs text-red-700">{{ expenseError }}</p>
                <p v-else-if="!expenseData?.has_data" class="text-xs text-gray-500">この年度・部署の経費データはまだ取り込まれていません。</p>
                <template v-else>
                    <div class="mb-4 max-w-md rounded bg-white p-3">
                        <ShareCompositionBar title="科目別構成比" :rows="expenseShareRows" :total="expenseData.total_amount" />
                    </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-1">科目</th>
                                <th v-for="m in monthLabels" :key="m" class="py-1 text-right">{{ m }}</th>
                                <th class="py-1 text-right">年間合計</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="row in expenseData.categories" :key="row.category">
                                <tr class="border-t cursor-pointer hover:bg-gray-100" @click="toggleCategory(row.category)">
                                    <td class="py-1">
                                        <span class="mr-1 inline-block w-3 text-gray-400">{{ expandedCategory === row.category ? '▾' : '▸' }}</span>{{ row.category }}
                                        <span v-if="row.is_shared_split" class="ml-1 rounded bg-purple-100 px-1 text-[10px] font-semibold text-purple-700">サンエー印刷と折半</span>
                                    </td>
                                    <td v-for="m in row.months" :key="`${m.calendar_year}-${m.calendar_month}`" class="py-1 text-right">
                                        {{ m.amount > 0 ? yen(m.amount) : '—' }}
                                    </td>
                                    <td class="py-1 text-right font-semibold">{{ yen(row.total_amount) }}</td>
                                </tr>
                                <tr v-if="expandedCategory === row.category">
                                    <td :colspan="14" class="bg-gray-50 p-3">
                                        <p v-if="expandedLoading" class="text-xs text-gray-400">読み込み中...</p>
                                        <template v-else-if="expenseDetailCache[row.category]">
                                            <p class="mb-2 text-[11px] text-gray-500">{{ row.category }}（支払先別内訳。Excelの小計の区切りをそのまま再現しています）</p>
                                            <p v-if="expenseDetailCache[row.category].is_shared_split" class="mb-2 rounded bg-purple-50 px-2 py-1 text-xs text-purple-700">
                                                この科目はサンエー印刷と折半しています（金額はこのファイルの記載額そのままです）。
                                            </p>
                                            <table class="min-w-full max-w-lg text-xs">
                                                <thead>
                                                    <tr class="text-left text-gray-500">
                                                        <th class="py-1">会社名（支払先）</th>
                                                        <th class="py-1 text-right">金額</th>
                                                        <th class="py-1 text-right">構成比</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <template v-for="(group, gi) in expenseDetailCache[row.category].groups" :key="gi">
                                                        <tr v-for="v in group.vendors" :key="v.vendor_name" class="border-t bg-white">
                                                            <td class="py-1">{{ v.vendor_name }}</td>
                                                            <td class="py-1 text-right">{{ yen(v.amount) }}</td>
                                                            <td class="py-1 text-right">{{ v.share_pct !== null ? `${v.share_pct}%` : '—' }}</td>
                                                        </tr>
                                                        <tr v-if="group.subtotal_label" class="border-t bg-gray-100 font-semibold">
                                                            <td class="py-1">{{ group.subtotal_label }}</td>
                                                            <td class="py-1 text-right">{{ yen(group.subtotal_amount) }}</td>
                                                            <td class="py-1 text-right"></td>
                                                        </tr>
                                                    </template>
                                                    <tr class="border-t-2 bg-white font-semibold">
                                                        <td class="py-1">合計</td>
                                                        <td class="py-1 text-right">{{ yen(expenseDetailCache[row.category].total_amount) }}</td>
                                                        <td class="py-1 text-right"></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                            <tr class="border-t-2 font-semibold">
                                <td class="py-1">合計</td>
                                <td v-for="i in 12" :key="i"></td>
                                <td class="py-1 text-right">{{ yen(expenseData.total_amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-2 text-[11px] text-gray-400">科目をクリックすると、下に会社名（支払先）別の内訳が開きます。</p>
                </div>
                </template>
            </div>
        </div>
    </SalesAnalysisShell>
</template>
