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
    initialMonth: { type: Number, required: true },
    hasAnyData: { type: Boolean, default: false },
});

// 売上分析ルートは superadmin/admin/clerk の各ロールグループ内に複製登録されている
const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;

const departmentKey = ref(props.initialDepartmentKey);
const year = ref(props.initialYear);
const month = ref(props.initialMonth);
const consolidateClients = ref(false);
const fiscalMode = ref('calendar'); // 'calendar' | 'fiscal_april'
const showAllClients = ref(false);

const loading = ref(false);
const errorMessage = ref('');

const summary = ref(null);
const trend = ref([]);
const clients = ref(null);
const categories = ref(null);
const items = ref(null);

const productKeyword = ref('');
const productResults = ref(null);
const productSearching = ref(false);

const yen = (v) => (v === null || v === undefined ? '—' : `¥${Number(v).toLocaleString()}`);
const pct = (v) => (v === null || v === undefined ? '比較データなし' : `${v > 0 ? '+' : ''}${v}%`);
const pctClass = (v) => {
    if (v === null || v === undefined) return 'text-gray-400';
    return v > 0 ? 'text-red-600' : v < 0 ? 'text-blue-600' : 'text-gray-500';
};

const fetchAll = async () => {
    loading.value = true;
    errorMessage.value = '';
    productResults.value = null;

    const params = { department_key: departmentKey.value, year: year.value, month: month.value };

    try {
        const [summaryRes, trendRes, clientsRes, categoriesRes, itemsRes] = await Promise.all([
            axios.get(route(rn('api.summary')), { params }),
            axios.get(route(rn('api.trend')), { params: { ...params, years: 5 } }),
            axios.get(route(rn('api.clients')), { params: { ...params, consolidate: consolidateClients.value ? 1 : 0, limit: null } }),
            axios.get(route(rn('api.categories')), { params }),
            axios.get(route(rn('api.items')), { params }),
        ]);

        summary.value = summaryRes.data;
        trend.value = trendRes.data.trend;
        clients.value = clientsRes.data;
        categories.value = categoriesRes.data;
        items.value = itemsRes.data;

        await nextTick();
        renderTrendChart();
        renderClientChart();
        renderCategoryChart();
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

// --- Chart.js ---
const trendChartRef = ref(null);
const clientChartRef = ref(null);
const categoryChartRef = ref(null);
let trendChartInstance = null;
let clientChartInstance = null;
let categoryChartInstance = null;

const renderTrendChart = () => {
    if (!trendChartRef.value) return;
    if (trendChartInstance) trendChartInstance.destroy();

    const labels = trend.value.map((m) => `${m.year}/${String(m.month).padStart(2, '0')}`);
    const data = trend.value.map((m) => m.total_amount);

    trendChartInstance = new Chart(trendChartRef.value.getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: '月間売上',
                data,
                borderColor: '#4F46E5',
                backgroundColor: 'rgba(79,70,229,0.1)',
                spanGaps: true,
                tension: 0.2,
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { callback: (v) => `¥${Number(v).toLocaleString()}` } } },
        },
    });
};

const renderClientChart = () => {
    if (!clientChartRef.value || !clients.value) return;
    if (clientChartInstance) clientChartInstance.destroy();

    const top = (clients.value.ranking || []).slice(0, 10);
    clientChartInstance = new Chart(clientChartRef.value.getContext('2d'), {
        type: 'bar',
        data: {
            labels: top.map((c) => c.name),
            datasets: [{ label: '売上', data: top.map((c) => c.amount), backgroundColor: '#16A34A' }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { callback: (v) => `¥${Number(v).toLocaleString()}` } } },
        },
    });
};

const renderCategoryChart = () => {
    if (!categoryChartRef.value || !categories.value) return;
    if (categoryChartInstance) categoryChartInstance.destroy();

    const rows = categories.value.breakdown || [];
    categoryChartInstance = new Chart(categoryChartRef.value.getContext('2d'), {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.label),
            datasets: [{ label: '金額', data: rows.map((r) => r.amount), backgroundColor: '#D97706' }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { callback: (v) => `¥${Number(v).toLocaleString()}` } } },
        },
    });
};

const displayedClients = computed(() => {
    if (!clients.value) return [];
    return showAllClients.value ? clients.value.ranking : clients.value.ranking.slice(0, 10);
});

const currentFiscal = computed(() => (summary.value ? summary.value[fiscalMode.value === 'calendar' ? 'fiscal_calendar' : 'fiscal_april'] : null));

watch(consolidateClients, () => fetchAll());
onMounted(() => {
    if (props.hasAnyData) fetchAll();
});
</script>

<template>
    <AppLayout title="月次分析">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route(rn('dashboard'))"
                        class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                    >← データ登録状況</Link>
                    <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">月次分析</h2>
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
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div>
                        <label class="block text-xs font-medium text-gray-500">部署</label>
                        <select v-model="departmentKey" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option v-for="key in enabledDepartmentKeys" :key="key" :value="key">{{ departmentLabels[key] }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">年</label>
                        <input v-model.number="year" type="number" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">月</label>
                        <input v-model.number="month" type="number" min="1" max="12" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                    </div>
                    <div class="flex items-end">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                            <input v-model="consolidateClients" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                            会社統合
                        </label>
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="w-full rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-indigo-700" @click="fetchAll">
                            表示更新
                        </button>
                    </div>
                </div>
            </div>

            <p v-if="errorMessage" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="loading" class="text-sm text-gray-500">読み込み中...</p>

            <template v-if="summary">
                <!-- KPIカード -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">当月売上</p>
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
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-gray-500">年度累計</p>
                            <div class="flex rounded border text-[10px]">
                                <button type="button" class="px-1.5 py-0.5" :class="fiscalMode === 'calendar' ? 'bg-indigo-600 text-white' : 'text-gray-500'" @click="fiscalMode = 'calendar'">暦年</button>
                                <button type="button" class="px-1.5 py-0.5" :class="fiscalMode === 'fiscal_april' ? 'bg-indigo-600 text-white' : 'text-gray-500'" @click="fiscalMode = 'fiscal_april'">年度(4月)</button>
                            </div>
                        </div>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ yen(currentFiscal?.current?.total_amount) }}</p>
                        <p class="text-xs" :class="pctClass(currentFiscal?.rate)">前年同期比 {{ pct(currentFiscal?.rate) }}</p>
                    </div>
                </div>

                <!-- 5年推移 -->
                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900">月別売上推移（5年）</h3>
                    <canvas ref="trendChartRef" style="max-height: 280px"></canvas>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <!-- 得意先別 -->
                    <div class="rounded bg-white p-4 shadow">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">得意先別（上位10社）</h3>
                        <canvas ref="clientChartRef" style="max-height: 260px"></canvas>

                        <div v-if="clients" class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead>
                                    <tr class="text-left text-gray-500">
                                        <th class="py-1">得意先</th>
                                        <th class="py-1 text-right">金額</th>
                                        <th class="py-1 text-right">構成比</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in displayedClients" :key="c.name" class="border-t">
                                        <td class="py-1">{{ c.name }}</td>
                                        <td class="py-1 text-right">{{ yen(c.amount) }}</td>
                                        <td class="py-1 text-right">{{ c.share !== null ? `${c.share}%` : '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <button
                                v-if="clients.all_count > 10"
                                type="button"
                                class="mt-2 text-xs text-indigo-600 hover:underline"
                                @click="showAllClients = !showAllClients"
                            >
                                {{ showAllClients ? '上位10社のみ表示' : `全件表示（${clients.all_count}社）` }}
                            </button>
                        </div>
                    </div>

                    <!-- 分類別 -->
                    <div class="rounded bg-white p-4 shadow">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">分類別</h3>
                        <canvas ref="categoryChartRef" style="max-height: 260px"></canvas>
                    </div>
                </div>

                <!-- 項目別 -->
                <div v-if="items" class="rounded bg-white p-4 shadow">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900">項目別</h3>
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
                                <tr v-for="row in items.breakdown" :key="row.label" class="border-t">
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
                    <h3 class="mb-3 text-sm font-semibold text-gray-900">品名検索</h3>
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
            </template>
        </div>
    </AppLayout>
</template>
