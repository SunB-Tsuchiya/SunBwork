<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import Chart from 'chart.js/auto';

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

const departmentKey = ref(props.initialDepartmentKey);
const year = ref(props.initialYear);

const loading = ref(false);
const errorMessage = ref('');
const summary = ref(null);

const productKeyword = ref('');
const productResults = ref(null);
const productSearching = ref(false);

const monthLabels = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];

const yen = (v) => (v === null || v === undefined ? '—' : `¥${Number(v).toLocaleString()}`);
const pct = (v) => (v === null || v === undefined ? '比較データなし' : `${v > 0 ? '+' : ''}${v}%`);
const pctClass = (v) => {
    if (v === null || v === undefined) return 'text-gray-400';
    return v > 0 ? 'text-red-600' : v < 0 ? 'text-blue-600' : 'text-gray-500';
};

const trendChartRef = ref(null);
let trendChartInstance = null;

const renderTrendChart = () => {
    if (!trendChartRef.value || !summary.value) return;
    if (trendChartInstance) trendChartInstance.destroy();

    const labels = monthLabels;
    const current = summary.value.monthly.map((m) => m.amount);
    const prior = summary.value.monthly.map((m) => m.prior_year_amount);

    trendChartInstance = new Chart(trendChartRef.value.getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: `${summary.value.year}年`,
                    data: current,
                    borderColor: '#4F46E5',
                    backgroundColor: 'rgba(79,70,229,0.1)',
                    spanGaps: true,
                    tension: 0.2,
                },
                {
                    label: `${summary.value.comparison_year}年`,
                    data: prior,
                    borderColor: '#9CA3AF',
                    backgroundColor: 'rgba(156,163,175,0.1)',
                    spanGaps: true,
                    tension: 0.2,
                    borderDash: [4, 4],
                },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true, position: 'bottom' } },
            scales: { y: { beginAtZero: true, ticks: { callback: (v) => `¥${Number(v).toLocaleString()}` } } },
        },
    });
};

const fetchSummary = async () => {
    loading.value = true;
    errorMessage.value = '';
    productResults.value = null;

    try {
        const response = await axios.get(route(rn('api.annual_summary')), {
            params: { department_key: departmentKey.value, year: year.value },
        });
        summary.value = response.data;
        await nextTick();
        renderTrendChart();
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

const comparisonRangeLabel = computed(() => {
    if (!summary.value) return '';
    const [start, end] = summary.value.comparison_month_range;
    return start === end ? `${start}月のみ` : `${start}〜${end}月`;
});

const monthStateClass = (m) => {
    if (m.state === 'future') return 'text-gray-300';
    if (m.state === 'no_data') return 'text-gray-400';
    return 'text-gray-900';
};

watch([departmentKey, year], fetchSummary);
onMounted(() => {
    if (props.hasAnyData) fetchSummary();
});
</script>

<template>
    <AppLayout title="年次分析">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route(rn('dashboard'))"
                        class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                    >← データ登録状況</Link>
                    <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">年次分析</h2>
                </div>
                <div class="flex gap-3">
                    <Link :href="route(rn('import.create'))" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-indigo-700">Excel取込</Link>
                    <Link :href="route(rn('import_history.index'))" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-bold text-gray-700 hover:bg-gray-50">取込履歴</Link>
                </div>
            </div>
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
                            <option v-for="key in enabledDepartmentKeys" :key="key" :value="key">{{ departmentLabels[key] }}</option>
                            <option value="all">全部署合計</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="button" class="rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-50" @click="year -= 1">← {{ year - 1 }}年</button>
                        <span class="px-2 text-sm font-bold text-gray-900">{{ year }}年</span>
                        <button type="button" class="rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-50" @click="year += 1">{{ year + 1 }}年 →</button>
                    </div>
                </div>
            </div>

            <p v-if="errorMessage" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="loading" class="text-sm text-gray-500">読み込み中...</p>

            <template v-if="summary">
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
                    <h3 class="mb-3 text-sm font-semibold text-gray-900">月別売上（{{ summary.year }}年 対 {{ summary.comparison_year }}年）</h3>
                    <canvas ref="trendChartRef" style="max-height: 280px"></canvas>

                    <div class="mt-4 overflow-x-auto">
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

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <!-- 得意先別 -->
                    <div class="rounded bg-white p-4 shadow">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">得意先別（上位10社・{{ comparisonRangeLabel }}累計）</h3>
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">得意先</th>
                                    <th class="py-1 text-right">金額</th>
                                    <th class="py-1 text-right">構成比</th>
                                    <th class="py-1 text-right">前年同期比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in summary.top_clients" :key="c.client_name" class="border-t">
                                    <td class="py-1">{{ c.client_name }}</td>
                                    <td class="py-1 text-right">{{ yen(c.amount) }}</td>
                                    <td class="py-1 text-right">{{ c.share_pct !== null ? `${c.share_pct}%` : '—' }}</td>
                                    <td class="py-1 text-right" :class="pctClass(c.rate)">{{ c.rate !== null ? pct(c.rate) : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- 分類別 -->
                    <div class="rounded bg-white p-4 shadow">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">分類別（{{ comparisonRangeLabel }}累計）</h3>
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">分類</th>
                                    <th class="py-1 text-right">金額</th>
                                    <th class="py-1 text-right">構成比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in summary.categories" :key="row.label" class="border-t">
                                    <td class="py-1">{{ row.label }}</td>
                                    <td class="py-1 text-right">{{ yen(row.amount) }}</td>
                                    <td class="py-1 text-right">{{ row.share !== null ? `${row.share}%` : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 項目別 -->
                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900">項目別（{{ comparisonRangeLabel }}累計）</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">項目</th>
                                    <th class="py-1 text-right">金額</th>
                                    <th class="py-1 text-right">構成比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in summary.items" :key="row.label" class="border-t">
                                    <td class="py-1">{{ row.label }}</td>
                                    <td class="py-1 text-right">{{ yen(row.amount) }}</td>
                                    <td class="py-1 text-right">{{ row.share !== null ? `${row.share}%` : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 品名検索 -->
                <div class="rounded bg-white p-4 shadow">
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
            </template>
        </div>
    </AppLayout>
</template>
