<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import Chart from 'chart.js/auto';
import PeriodNavigator from '@/Components/SalesAnalysis/PeriodNavigator.vue';
import SalesAnalysisNavigationTabs from '@/Components/SalesAnalysis/SalesAnalysisNavigationTabs.vue';

const props = defineProps({
    routePrefix: { type: String, required: true },
    departmentLabels: { type: Object, default: () => ({}) },
    enabledDepartmentKeys: { type: Array, default: () => [] },
    initialDepartmentKey: { type: String, default: 'planning' },
    initialMonth: { type: Number, required: true },
    hasAnyData: { type: Boolean, default: false },
});

// 売上分析ルートは superadmin/admin/clerk の各ロールグループ内に複製登録されている
const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;

const departmentKey = ref(props.initialDepartmentKey);
const month = ref(props.initialMonth);
const yearsRequested = ref(5);
const consolidateClients = ref(false);

const loading = ref(false);
const errorMessage = ref('');
const summary = ref(null);

const monthLabels = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];

const yen = (v) => (v === null || v === undefined ? '—' : `¥${Number(v).toLocaleString()}`);
const pct = (v) => (v === null || v === undefined ? '比較データなし' : `${v > 0 ? '+' : ''}${v}%`);
const pctClass = (v) => {
    if (v === null || v === undefined) return 'text-gray-400';
    return v > 0 ? 'text-blue-600' : v < 0 ? 'text-red-600' : 'text-gray-500';
};

const yearlyChartRef = ref(null);
let yearlyChartInstance = null;

const renderYearlyChart = () => {
    if (!yearlyChartRef.value || !summary.value) return;
    if (yearlyChartInstance) yearlyChartInstance.destroy();

    yearlyChartInstance = new Chart(yearlyChartRef.value.getContext('2d'), {
        type: 'bar',
        data: {
            labels: summary.value.yearly.map((y) => `${y.year}年`),
            datasets: [
                {
                    label: `${monthLabels[summary.value.month - 1]}の売上`,
                    data: summary.value.yearly.map((y) => y.amount),
                    backgroundColor: summary.value.yearly.map((y) => (y.has_issue ? 'rgba(217,119,6,0.6)' : 'rgba(79,70,229,0.6)')),
                },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { callback: (v) => `¥${Number(v).toLocaleString()}` } } },
        },
    });
};

const fetchSummary = async () => {
    loading.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.get(route(rn('api.same_month_comparison')), {
            params: {
                department_key: departmentKey.value,
                month: month.value,
                years: yearsRequested.value,
                consolidate_clients: consolidateClients.value ? 1 : 0,
            },
        });
        summary.value = response.data;
        await nextTick();
        renderYearlyChart();
    } catch (e) {
        errorMessage.value = 'データの取得に失敗しました。';
        summary.value = null;
    } finally {
        loading.value = false;
    }
};

const yearStateLabel = (y) => {
    if (y.state === 'future') return 'まだ来ていない';
    if (y.state === 'no_data') return '未登録';
    return '登録済み';
};

const yearRowClass = (y) => (y.state === 'future' || y.state === 'no_data' ? 'text-gray-400' : 'text-gray-900');

const goLatest = async () => {
    const response = await axios.get(route(rn('api.same_month_comparison.latest_period')), { params: { department_key: departmentKey.value } });
    if (response.data.latest) month.value = response.data.latest.month;
};

const syncQueryString = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('department_key', departmentKey.value);
    url.searchParams.set('month', String(month.value));
    window.history.replaceState(null, '', url);
};

watch([departmentKey, month], () => {
    syncQueryString();
    fetchSummary();
});
watch([yearsRequested, consolidateClients], fetchSummary);
onMounted(() => {
    if (props.hasAnyData) fetchSummary();
});
</script>

<template>
    <AppLayout title="同月比較">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route(rn('dashboard'))"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← データ登録状況</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">同月比較</h2>
            </div>
        </template>
        <template #tabs>
            <SalesAnalysisNavigationTabs :route-prefix="routePrefix" active="same_month" />
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
                v-model:month="month"
                v-model:consolidate-clients="consolidateClients"
                granularity="month-cyclic"
                allow-all-departments
                :department-labels="departmentLabels"
                :enabled-department-keys="enabledDepartmentKeys"
                :loading="loading"
                @go-latest="goLatest"
            />

            <div class="rounded bg-white p-4 shadow">
                <label class="block text-xs font-medium text-gray-500">年数</label>
                <div class="mt-1 flex gap-2">
                    <button
                        type="button"
                        class="rounded-md border px-3 py-1.5 text-sm"
                        :class="yearsRequested === 5 ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-semibold' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                        @click="yearsRequested = 5"
                    >直近5年</button>
                    <button
                        type="button"
                        class="rounded-md border px-3 py-1.5 text-sm"
                        :class="yearsRequested === 10 ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-semibold' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                        @click="yearsRequested = 10"
                    >直近10年</button>
                </div>
            </div>

            <p v-if="errorMessage" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="loading" class="text-sm text-gray-500">読み込み中...</p>

            <template v-if="summary">
                <!-- 年別テーブル・グラフ -->
                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900">{{ monthLabels[summary.month - 1] }}の売上（{{ summary.years[0] }}〜{{ summary.years[summary.years.length - 1] }}年）</h3>
                    <canvas ref="yearlyChartRef" style="max-height: 260px"></canvas>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">年</th>
                                    <th class="py-1">状態</th>
                                    <th class="py-1 text-right">売上</th>
                                    <th class="py-1 text-right">前年差</th>
                                    <th class="py-1 text-right">増減率</th>
                                    <th class="py-1 text-right">受注件数</th>
                                    <th class="py-1 text-right">1案件平均</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="y in summary.yearly" :key="y.year" class="border-t" :class="yearRowClass(y)">
                                    <td class="py-1">
                                        {{ y.year }}年
                                        <span v-if="y.needs_review" title="複数回取込あり">⚠</span>
                                        <span v-if="y.has_issue" title="未配賦額あり">🔺</span>
                                    </td>
                                    <td class="py-1">{{ yearStateLabel(y) }}</td>
                                    <td class="py-1 text-right">{{ yen(y.amount) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(y.prior_year_rate)">{{ y.prior_year_diff !== null ? yen(y.prior_year_diff) : '—' }}</td>
                                    <td class="py-1 text-right" :class="pctClass(y.prior_year_rate)">{{ y.prior_year_rate !== null ? pct(y.prior_year_rate) : '—' }}</td>
                                    <td class="py-1 text-right">{{ y.order_count ?? '—' }}</td>
                                    <td class="py-1 text-right">{{ yen(y.avg_order_amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 得意先別 年次推移 -->
                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900">得意先別 年次推移（上位15社＋その他）</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">得意先</th>
                                    <th v-for="y in summary.client_matrix.years" :key="y" class="py-1 text-right">{{ y }}年</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in summary.client_matrix.clients" :key="c.client_name" class="border-t">
                                    <td class="py-1">{{ c.client_name }}</td>
                                    <td v-for="y in summary.client_matrix.years" :key="y" class="py-1 text-right">
                                        {{ c.amounts[String(y)] === null ? '—' : yen(c.amounts[String(y)]) }}
                                    </td>
                                </tr>
                                <tr v-if="summary.client_matrix.others_amount > 0" class="border-t text-gray-400">
                                    <td class="py-1">その他</td>
                                    <td class="py-1 text-right" :colspan="summary.client_matrix.years.length">{{ yen(summary.client_matrix.others_amount) }}（最新年合計）</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="rounded bg-white p-4 shadow">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">新規得意先（前年同月になく今年ある）</h3>
                        <p v-if="summary.new_clients.length === 0" class="text-xs text-gray-500">該当なし</p>
                        <table v-else class="min-w-full text-xs">
                            <tbody>
                                <tr v-for="c in summary.new_clients" :key="c.client_name" class="border-t">
                                    <td class="py-1">{{ c.client_name }}</td>
                                    <td class="py-1 text-right">{{ yen(c.amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">離脱得意先（前年同月にあり今年ない）</h3>
                        <p v-if="summary.departed_clients.length === 0" class="text-xs text-gray-500">該当なし</p>
                        <table v-else class="min-w-full text-xs">
                            <tbody>
                                <tr v-for="c in summary.departed_clients" :key="c.client_name" class="border-t">
                                    <td class="py-1">{{ c.client_name }}</td>
                                    <td class="py-1 text-right">前年 {{ yen(c.prior_year_amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="rounded bg-white p-4 shadow">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">増加額上位</h3>
                        <p v-if="summary.top_increase.length === 0" class="text-xs text-gray-500">比較データなし</p>
                        <table v-else class="min-w-full text-xs">
                            <tbody>
                                <tr v-for="c in summary.top_increase" :key="c.client_name" class="border-t">
                                    <td class="py-1">{{ c.client_name }}</td>
                                    <td class="py-1 text-right" :class="pctClass(c.rate)">{{ yen(c.diff) }} ({{ pct(c.rate) }})</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">減少額上位</h3>
                        <p v-if="summary.top_decrease.length === 0" class="text-xs text-gray-500">比較データなし</p>
                        <table v-else class="min-w-full text-xs">
                            <tbody>
                                <tr v-for="c in summary.top_decrease" :key="c.client_name" class="border-t">
                                    <td class="py-1">{{ c.client_name }}</td>
                                    <td class="py-1 text-right" :class="pctClass(c.rate)">{{ yen(c.diff) }} ({{ pct(c.rate) }})</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 分類・項目別（1・3・5年前比較） -->
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="rounded bg-white p-4 shadow">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">
                            分類別（{{ summary.category_item_comparison.reference_year ?? '—' }}年を基準に1・3・5年前と比較）
                        </h3>
                        <p v-if="summary.category_item_comparison.categories.length === 0" class="text-xs text-gray-500">データなし</p>
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead>
                                    <tr class="text-left text-gray-500">
                                        <th class="py-1">分類</th>
                                        <th class="py-1 text-right">今年</th>
                                        <th v-for="c in summary.category_item_comparison.categories[0].comparisons" :key="c.years_ago" class="py-1 text-right">{{ c.years_ago }}年前</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in summary.category_item_comparison.categories" :key="row.label" class="border-t">
                                        <td class="py-1">{{ row.label }}</td>
                                        <td class="py-1 text-right">{{ yen(row.amount) }}</td>
                                        <td v-for="c in row.comparisons" :key="c.years_ago" class="py-1 text-right" :class="pctClass(c.rate)">
                                            {{ c.amount === null ? 'データなし' : `${yen(c.amount)} (${pct(c.rate)})` }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">
                            項目別（{{ summary.category_item_comparison.reference_year ?? '—' }}年を基準に1・3・5年前と比較）
                        </h3>
                        <p v-if="summary.category_item_comparison.items.length === 0" class="text-xs text-gray-500">データなし</p>
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead>
                                    <tr class="text-left text-gray-500">
                                        <th class="py-1">項目</th>
                                        <th class="py-1 text-right">今年</th>
                                        <th v-for="c in summary.category_item_comparison.items[0].comparisons" :key="c.years_ago" class="py-1 text-right">{{ c.years_ago }}年前</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in summary.category_item_comparison.items" :key="row.label" class="border-t">
                                        <td class="py-1">{{ row.label }}</td>
                                        <td class="py-1 text-right">{{ yen(row.amount) }}</td>
                                        <td v-for="c in row.comparisons" :key="c.years_ago" class="py-1 text-right" :class="pctClass(c.rate)">
                                            {{ c.amount === null ? 'データなし' : `${yen(c.amount)} (${pct(c.rate)})` }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
