<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import Chart from 'chart.js/auto';
import RankingPanel from '@/Components/SalesAnalysis/RankingPanel.vue';
import SalesAnalysisNavigationTabs from '@/Components/SalesAnalysis/SalesAnalysisNavigationTabs.vue';

const props = defineProps({
    routePrefix: { type: String, required: true },
    departmentLabels: { type: Object, default: () => ({}) },
    enabledDepartmentKeys: { type: Array, default: () => [] },
    initialDepartmentKey: { type: String, default: 'all' },
    initialClientName: { type: String, default: null },
    initialStartYear: { type: Number, required: true },
    initialStartMonth: { type: Number, required: true },
    initialEndYear: { type: Number, required: true },
    initialEndMonth: { type: Number, required: true },
    hasAnyData: { type: Boolean, default: false },
});

// 売上分析ルートは superadmin/admin/clerk の各ロールグループ内に複製登録されている
const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;

const monthLabels = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];

const departmentKey = ref(props.initialDepartmentKey);
const consolidateClients = ref(false);
const startYear = ref(props.initialStartYear);
const startMonth = ref(props.initialStartMonth);
const endYear = ref(props.initialEndYear);
const endMonth = ref(props.initialEndMonth);

const errorMessage = ref('');
const rankingTotalAmount = ref(null);

const selectedClient = ref(null);
const detailLoading = ref(false);
const detailResult = ref(null);
// 深いリンク（月次/年次分析の得意先クリック）で来た場合は、ランキング一覧を初期状態で
// たたんでおき、選択済みの得意先の推移が目立つようにする（2026-09-04実機フィードバック対応）
const rankingCollapsed = ref(!!props.initialClientName);
// 受注一覧は初期20件、「全件を見る」で最大200件まで展開する
// （実機フィードバック対応: 200件がそのまま並ぶと長すぎる、2026-09-04）
const ordersExpanded = ref(false);
// 並べ替え（実機フィードバック対応: 金額順でも見たい、2026-09-04）。取得済みの最大200件を
// クライアント側で並べ替えるだけなので、サーバーへの再取得は発生しない
const ordersSort = ref('newest'); // 'newest' | 'amount'
const sortedOrders = computed(() => {
    if (!detailResult.value) return [];
    if (ordersSort.value === 'amount') {
        return [...detailResult.value.orders].sort((a, b) => b.order_amount - a.order_amount);
    }
    return detailResult.value.orders;
});
const displayedOrders = computed(() => (ordersExpanded.value ? sortedOrders.value : sortedOrders.value.slice(0, 20)));

const yen = (v) => (v === null || v === undefined ? '—' : `¥${Number(v).toLocaleString()}`);
const pct = (v) => (v === null || v === undefined ? '比較データなし' : `${v > 0 ? '+' : ''}${v}%`);
const pctClass = (v) => {
    if (v === null || v === undefined) return 'text-gray-400';
    return v > 0 ? 'text-blue-600' : v < 0 ? 'text-red-600' : 'text-gray-500';
};

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
                    label: `${selectedClient.value}`,
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
    const response = await axios.get(route(rn('api.client_analysis.ranking_panel')), {
        params: {
            department_key: departmentKey.value,
            start_year: startYear.value,
            start_month: startMonth.value,
            end_year: endYear.value,
            end_month: endMonth.value,
            consolidate_clients: consolidateClients.value ? 1 : 0,
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
    { sort: 'label', direction: 'asc', label: '得意先名順' },
];

const refreshKey = computed(() => `${departmentKey.value}-${startYear.value}-${startMonth.value}-${endYear.value}-${endMonth.value}-${consolidateClients.value}`);

const showDetail = async (clientName) => {
    selectedClient.value = clientName;
    detailLoading.value = true;
    detailResult.value = null;
    ordersExpanded.value = false;
    ordersSort.value = 'newest';
    // ランキングの得意先をクリックすると先頭の推移エリアの中身が変わるだけなので、
    // 一覧が長いと変化に気づきにくい。ウィンドウ自体を最上部へスクロールして分かりやすくする
    // （実機フィードバック対応、2026-09-04。要素ref+scrollIntoViewだと反応しないケースが
    // あったため、DOMの構造やタイミングに依存しないwindow.scrollToへ変更）
    window.scrollTo({ top: 0, behavior: 'smooth' });
    try {
        const response = await axios.get(route(rn('api.client_analysis.detail')), {
            params: {
                department_key: departmentKey.value,
                client_name: clientName,
                start_year: startYear.value,
                start_month: startMonth.value,
                end_year: endYear.value,
                end_month: endMonth.value,
                consolidate_clients: consolidateClients.value ? 1 : 0,
            },
        });
        detailResult.value = response.data;
    } catch (e) {
        errorMessage.value = '推移データの取得に失敗しました。';
    } finally {
        // detailLoadingをfalseにしてから描画する。先にrenderTrendChart()を呼ぶと
        // v-else-if="detailResult"のcanvasがまだDOMに無く、trendChartRefがnullのまま
        // 関数が早期returnして何も描画されない（実機フィードバックで発覚したバグ、2026-09-04）
        detailLoading.value = false;
    }
    if (detailResult.value) {
        await nextTick();
        renderTrendChart();
    }
};

watch([departmentKey, consolidateClients, startYear, startMonth, endYear, endMonth], () => {
    selectedClient.value = null;
    detailResult.value = null;
});

onMounted(() => {
    if (props.hasAnyData && props.initialClientName) {
        showDetail(props.initialClientName);
    }
});
</script>

<template>
    <AppLayout title="得意先分析">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route(rn('dashboard'))"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← データ登録状況</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">得意先分析</h2>
            </div>
        </template>
        <template #tabs>
            <SalesAnalysisNavigationTabs :route-prefix="routePrefix" active="client_analysis" />
        </template>

        <div v-if="!hasAnyData" class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">まだ取込データがありません。まずはExcelを取り込んでください。</p>
            <Link :href="route(rn('import.create'))" class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700">
                Excel取込へ
            </Link>
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
                    <div>
                        <label class="block text-xs font-medium text-gray-500">得意先統合</label>
                        <div class="mt-1 flex gap-2">
                            <button
                                type="button"
                                class="flex-1 rounded-md border px-2 py-1.5 text-sm"
                                :class="!consolidateClients ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-semibold' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                                @click="consolidateClients = false"
                            >OFF</button>
                            <button
                                type="button"
                                class="flex-1 rounded-md border px-2 py-1.5 text-sm"
                                :class="consolidateClients ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-semibold' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                                @click="consolidateClients = true"
                            >ON</button>
                        </div>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-500">期間</label>
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

            <!-- 個別得意先の推移（選択中はこちらを先頭に表示） -->
            <div v-if="selectedClient" class="rounded border-2 border-indigo-200 bg-white p-4 shadow">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">{{ selectedClient }} の年別推移</h3>
                <p v-if="detailLoading" class="text-sm text-gray-500">読み込み中...</p>
                <template v-else-if="detailResult">
                    <canvas ref="trendChartRef" style="max-height: 280px"></canvas>
                    <p class="mt-2 text-xs text-gray-400">紫=この得意先、グレー=部署合計。バーにマウスを乗せると構成比が出ます。</p>
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
                                    <th class="py-1">品名</th>
                                    <th class="py-1 text-right">金額</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="o in displayedOrders" :key="o.order_number" class="border-t">
                                    <td class="py-1">{{ o.sales_year }}-{{ String(o.sales_month).padStart(2, '0') }}</td>
                                    <td class="py-1">{{ o.order_number }}</td>
                                    <td class="py-1">{{ o.product_name }}</td>
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
                        得意先ランキング
                        <span class="font-normal text-gray-400">（期間内合計 {{ rankingTotalAmount !== null ? yen(rankingTotalAmount) : '—' }}）</span>
                    </h3>
                    <span class="text-xs text-indigo-600">{{ rankingCollapsed ? '▼ 表示する' : '▲ 閉じる' }}</span>
                </button>
                <div v-if="!rankingCollapsed" class="mt-3">
                    <RankingPanel
                        title="得意先"
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
