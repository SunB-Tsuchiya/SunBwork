<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import Chart from 'chart.js/auto';
import RankingPanel from '@/Components/SalesAnalysis/RankingPanel.vue';
import SalesAnalysisNavigationTabs from '@/Components/SalesAnalysis/SalesAnalysisNavigationTabs.vue';
import { useSalesChart } from '@/Composables/useSalesChart';

const props = defineProps({
    routePrefix: { type: String, required: true },
    departmentLabels: { type: Object, default: () => ({}) },
    enabledDepartmentKeys: { type: Array, default: () => [] },
    initialDepartmentKey: { type: String, default: 'all' },
    initialProductName: { type: String, default: null },
    initialStartYear: { type: Number, required: true },
    initialStartMonth: { type: Number, required: true },
    initialEndYear: { type: Number, required: true },
    initialEndMonth: { type: Number, required: true },
    hasAnyData: { type: Boolean, default: false },
    hasCompanySelected: { type: Boolean, default: true },
});

// 売上分析ルートは superadmin/admin/clerk の各ロールグループ内に複製登録されている
const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;

const { yen, pct, pctClass } = useSalesChart();

const monthLabels = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];

const departmentKey = ref(props.initialDepartmentKey);
const startYear = ref(props.initialStartYear);
const startMonth = ref(props.initialStartMonth);
const endYear = ref(props.initialEndYear);
const endMonth = ref(props.initialEndMonth);

const errorMessage = ref('');
const rankingTotalAmount = ref(null);

const selectedProduct = ref(null);
const detailLoading = ref(false);
const detailResult = ref(null);
// 得意先分析と同じく、深いリンクで来た場合はランキング一覧を初期状態でたたんでおく
const rankingCollapsed = ref(!!props.initialProductName);
const ordersExpanded = ref(false);
const ordersSort = ref('newest'); // 'newest' | 'amount'
const sortedOrders = computed(() => {
    if (!detailResult.value) return [];
    if (ordersSort.value === 'amount') {
        return [...detailResult.value.orders].sort((a, b) => b.order_amount - a.order_amount);
    }
    return detailResult.value.orders;
});
const displayedOrders = computed(() => (ordersExpanded.value ? sortedOrders.value : sortedOrders.value.slice(0, 20)));

const trendChartRef = ref(null);
let trendChartInstance = null;

const renderTrendChart = () => {
    if (!trendChartRef.value || !detailResult.value) return;
    if (trendChartInstance) trendChartInstance.destroy();

    const rows = detailResult.value.yearly;

    trendChartInstance = new Chart(trendChartRef.value.getContext('2d'), {
        type: 'bar',
        data: {
            labels: rows.map((y) => `${y.year}年`),
            datasets: [
                {
                    label: `${selectedProduct.value}`,
                    data: rows.map((y) => y.amount),
                    backgroundColor: 'rgba(79,70,229,0.8)',
                },
                {
                    label: '全体（該当部署合計）',
                    data: rows.map((y) => y.company_amount),
                    backgroundColor: 'rgba(156,163,175,0.5)',
                },
            ],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true, position: 'bottom' },
                tooltip: {
                    callbacks: {
                        afterBody: (items) => {
                            const row = rows[items[0]?.dataIndex];
                            return row?.share_pct !== null && row?.share_pct !== undefined ? `構成比: ${row.share_pct}%` : '';
                        },
                    },
                },
            },
            scales: { y: { beginAtZero: true, ticks: { callback: (v) => `¥${Number(v).toLocaleString()}` } } },
        },
    });
};

const fetchRankingPage = async (params) => {
    const response = await axios.get(route(rn('api.product_analysis.ranking_panel')), {
        params: {
            department_key: departmentKey.value,
            start_year: startYear.value,
            start_month: startMonth.value,
            end_year: endYear.value,
            end_month: endMonth.value,
            limit: params.limit,
            page: params.page,
            keyword: params.keyword || undefined,
            sort: params.sort,
            direction: params.direction,
        },
    });
    rankingTotalAmount.value = response.data.total_amount;
    return response.data;
};

const rankingSortOptions = [
    { sort: 'amount', direction: 'desc', label: '金額順' },
    { sort: 'label', direction: 'asc', label: '品名順' },
];

const refreshKey = computed(() => `${departmentKey.value}-${startYear.value}-${startMonth.value}-${endYear.value}-${endMonth.value}`);

const showDetail = async (productName) => {
    selectedProduct.value = productName;
    detailLoading.value = true;
    detailResult.value = null;
    ordersExpanded.value = false;
    ordersSort.value = 'newest';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    try {
        const response = await axios.get(route(rn('api.product_analysis.detail')), {
            params: {
                department_key: departmentKey.value,
                product_name: productName,
                start_year: startYear.value,
                start_month: startMonth.value,
                end_year: endYear.value,
                end_month: endMonth.value,
            },
        });
        detailResult.value = response.data;
    } catch (e) {
        errorMessage.value = '推移データの取得に失敗しました。';
    } finally {
        detailLoading.value = false;
    }
    if (detailResult.value) {
        await nextTick();
        renderTrendChart();
    }
};

watch([departmentKey, startYear, startMonth, endYear, endMonth], () => {
    selectedProduct.value = null;
    detailResult.value = null;
});

// 「新規/取扱終了商品」パネル（ランキングの期間指定とは独立し、常に直近登録年対前年で固定）
const yoyLoading = ref(false);
const yoyResult = ref(null);

const fetchYearOverYear = async () => {
    yoyLoading.value = true;
    try {
        const response = await axios.get(route(rn('api.product_analysis.year_over_year')), {
            params: { department_key: departmentKey.value },
        });
        yoyResult.value = response.data;
    } finally {
        yoyLoading.value = false;
    }
};

watch(departmentKey, fetchYearOverYear);

onMounted(() => {
    if (!props.hasAnyData) return;
    fetchYearOverYear();
    if (props.initialProductName) {
        showDetail(props.initialProductName);
    }
});
</script>

<template>
    <AppLayout title="商品分析">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route(rn('dashboard'))"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← データ登録状況</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">商品分析</h2>
            </div>
        </template>
        <template #tabs>
            <SalesAnalysisNavigationTabs :route-prefix="routePrefix" active="product_analysis" />
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
            <!-- フィルタ -->
            <div class="rounded bg-white p-4 shadow">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500">部署</label>
                        <select v-model="departmentKey" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="all">全部署合計</option>
                            <option v-for="key in enabledDepartmentKeys" :key="key" :value="key">{{ departmentLabels[key] }}</option>
                        </select>
                    </div>
                    <div class="col-span-2 sm:col-span-3">
                        <label class="block text-xs font-medium text-gray-500">期間（ランキング・推移用）</label>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <input v-model.number="startYear" type="number" class="w-20 rounded-md border-gray-300 text-sm shadow-sm" />年
                            <select v-model.number="startMonth" class="rounded-md border-gray-300 text-sm shadow-sm">
                                <option v-for="(l, idx) in monthLabels" :key="idx" :value="idx + 1">{{ l }}</option>
                            </select>
                            <span class="text-gray-400">〜</span>
                            <input v-model.number="endYear" type="number" class="w-20 rounded-md border-gray-300 text-sm shadow-sm" />年
                            <select v-model.number="endMonth" class="rounded-md border-gray-300 text-sm shadow-sm">
                                <option v-for="(l, idx) in monthLabels" :key="idx" :value="idx + 1">{{ l }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="errorMessage" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>

            <!-- 新規/取扱終了商品（直近登録年 対 前年で固定、ランキングの期間指定とは独立） -->
            <div class="rounded bg-white p-4 shadow">
                <h3 class="text-sm font-semibold text-gray-900">
                    新規/取扱終了商品
                    <span v-if="yoyResult?.latest_year && yoyResult?.prior_year" class="font-normal text-gray-400">
                        （{{ yoyResult.prior_year }}年 対 {{ yoyResult.latest_year }}年で固定比較）
                    </span>
                </h3>
                <p v-if="yoyLoading" class="mt-2 text-xs text-gray-400">読み込み中...</p>
                <template v-else-if="yoyResult">
                    <p v-if="!yoyResult.has_comparison_pair" class="mt-2 text-xs text-gray-500">前年のデータが未登録のため比較できません。</p>
                    <template v-else>
                        <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div>
                                <h4 class="mb-2 text-xs font-semibold text-gray-700">新規商品（前年になく今年ある）</h4>
                                <p v-if="yoyResult.new_products.length === 0" class="text-xs text-gray-500">該当なし</p>
                                <table v-else class="min-w-full text-xs">
                                    <tbody>
                                        <tr v-for="p in yoyResult.new_products" :key="p.product_name" class="border-t">
                                            <td class="py-1">{{ p.product_name }}</td>
                                            <td class="py-1 text-right">{{ yen(p.amount) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div>
                                <h4 class="mb-2 text-xs font-semibold text-gray-700">取扱終了商品（前年にあり今年ない）</h4>
                                <p v-if="yoyResult.discontinued_products.length === 0" class="text-xs text-gray-500">該当なし</p>
                                <table v-else class="min-w-full text-xs">
                                    <tbody>
                                        <tr v-for="p in yoyResult.discontinued_products" :key="p.product_name" class="border-t">
                                            <td class="py-1">{{ p.product_name }}</td>
                                            <td class="py-1 text-right">前年 {{ yen(p.prior_year_amount) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div>
                                <h4 class="mb-2 text-xs font-semibold text-gray-700">増加額上位</h4>
                                <p v-if="yoyResult.top_increase.length === 0" class="text-xs text-gray-500">比較データなし</p>
                                <table v-else class="min-w-full text-xs">
                                    <tbody>
                                        <tr v-for="p in yoyResult.top_increase" :key="p.product_name" class="border-t">
                                            <td class="py-1">{{ p.product_name }}</td>
                                            <td class="py-1 text-right" :class="pctClass(p.rate)">{{ yen(p.diff) }}（{{ pct(p.rate) }}）</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div>
                                <h4 class="mb-2 text-xs font-semibold text-gray-700">減少額上位</h4>
                                <p v-if="yoyResult.top_decrease.length === 0" class="text-xs text-gray-500">比較データなし</p>
                                <table v-else class="min-w-full text-xs">
                                    <tbody>
                                        <tr v-for="p in yoyResult.top_decrease" :key="p.product_name" class="border-t">
                                            <td class="py-1">{{ p.product_name }}</td>
                                            <td class="py-1 text-right" :class="pctClass(p.rate)">{{ yen(p.diff) }}（{{ pct(p.rate) }}）</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </template>
            </div>

            <!-- 個別商品の推移（選択中はこちらを先頭に表示） -->
            <div v-if="selectedProduct" class="rounded border-2 border-indigo-200 bg-white p-4 shadow">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">{{ selectedProduct }} の年別推移</h3>
                <p v-if="detailLoading" class="text-sm text-gray-500">読み込み中...</p>
                <template v-else-if="detailResult">
                    <canvas ref="trendChartRef" style="max-height: 280px"></canvas>
                    <p class="mt-2 text-xs text-gray-400">紫=この商品、グレー=部署合計。バーにマウスを乗せると構成比が出ます。</p>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">年</th>
                                    <th class="py-1 text-right">売上</th>
                                    <th class="py-1 text-right">構成比</th>
                                    <th class="py-1 text-right">前年差</th>
                                    <th class="py-1 text-right">増減率</th>
                                    <th class="py-1 text-right">受注件数</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="y in detailResult.yearly" :key="y.year" class="border-t">
                                    <td class="py-1">{{ y.year }}年</td>
                                    <td class="py-1 text-right">{{ y.amount !== null ? yen(y.amount) : '未登録' }}</td>
                                    <td class="py-1 text-right">{{ y.share_pct !== null ? `${y.share_pct}%` : '—' }}</td>
                                    <td class="py-1 text-right" :class="pctClass(y.prior_year_rate)">{{ y.prior_year_diff !== null ? yen(y.prior_year_diff) : '—' }}</td>
                                    <td class="py-1 text-right" :class="pctClass(y.prior_year_rate)">{{ y.prior_year_rate !== null ? pct(y.prior_year_rate) : '—' }}</td>
                                    <td class="py-1 text-right">{{ y.order_count ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- この商品を購入している得意先ランキング（商品分析ならではの視点） -->
                    <div class="mt-4">
                        <h4 class="mb-2 text-xs font-semibold text-gray-700">この商品を購入している得意先（期間内合計・上位10件）</h4>
                        <p v-if="detailResult.client_ranking.length === 0" class="text-xs text-gray-500">該当なし</p>
                        <table v-else class="min-w-full text-xs">
                            <tbody>
                                <tr v-for="c in detailResult.client_ranking" :key="c.client_name" class="border-t">
                                    <td class="py-1">{{ c.client_name }}</td>
                                    <td class="py-1 text-right">{{ yen(c.amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-2 mt-4 flex flex-wrap items-center justify-between gap-2">
                        <h4 class="text-xs font-semibold text-gray-700">受注一覧（最大200件）</h4>
                        <label class="flex items-center gap-1 text-xs text-gray-600">
                            並び替え:
                            <select v-model="ordersSort" class="rounded border-gray-300 py-1 text-xs">
                                <option value="newest">新しい順</option>
                                <option value="amount">金額順</option>
                            </select>
                        </label>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">年月</th>
                                    <th class="py-1">受注No</th>
                                    <th class="py-1">得意先</th>
                                    <th class="py-1 text-right">金額</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="o in displayedOrders" :key="o.order_number" class="border-t">
                                    <td class="py-1">{{ o.sales_year }}-{{ String(o.sales_month).padStart(2, '0') }}</td>
                                    <td class="py-1">{{ o.order_number }}</td>
                                    <td class="py-1">{{ o.client_name }}</td>
                                    <td class="py-1 text-right">{{ yen(o.order_amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button
                        v-if="detailResult.orders.length > 20"
                        type="button"
                        class="mt-2 text-xs text-indigo-600 hover:underline"
                        @click="ordersExpanded = !ordersExpanded"
                    >{{ ordersExpanded ? '▲ 20件のみ表示' : `▼ 全件を見る（${detailResult.orders.length}件）` }}</button>
                </template>
            </div>

            <!-- ランキング -->
            <div class="rounded bg-white p-4 shadow">
                <button
                    type="button"
                    class="flex w-full items-center justify-between text-left"
                    @click="rankingCollapsed = !rankingCollapsed"
                >
                    <h3 class="text-sm font-semibold text-gray-900">
                        商品ランキング
                        <span class="font-normal text-gray-400">（期間内合計 {{ rankingTotalAmount !== null ? yen(rankingTotalAmount) : '—' }}）</span>
                    </h3>
                    <span class="text-xs text-indigo-600">{{ rankingCollapsed ? '▼ 表示する' : '▲ 閉じる' }}</span>
                </button>
                <div v-if="!rankingCollapsed" class="mt-3">
                    <RankingPanel
                        title="商品"
                        :fetch-page="fetchRankingPage"
                        :sort-options="rankingSortOptions"
                        :refresh-key="refreshKey"
                        @select-row="showDetail"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
