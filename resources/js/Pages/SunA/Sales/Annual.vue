<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Chart from 'chart.js/auto';
import ReportShell from '@/Components/SunA/Sales/ReportShell.vue';
import PeriodBar from '@/Components/SunA/Sales/PeriodBar.vue';
import RankingPanel from '@/Components/SalesAnalysis/RankingPanel.vue';
import ShareCompositionBar from '@/Components/SalesAnalysis/ShareCompositionBar.vue';
import { useSalesChart } from '@/Composables/useSalesChart';
import { useReportExport } from '@/Components/SunA/Sales/useReportExport.js';

// サンエー印刷 売上レポートの年次分析（SUNASALES_PLAN1.md §15）。
// 当年が途中でも、登録済みの月だけを、前年の同じ月と比べる。データの最初の年は前年比・新規・終了を出さない。
const props = defineProps({
    departmentOptions: { type: Array, default: () => [] },
    years: { type: Array, default: () => [] },
    initialYear: { type: Number, default: null },
});

const { yen, pct, pctClass } = useSalesChart();
const { exporting, exportError, exportExcel } = useReportExport();
const query = new URLSearchParams(window.location.search);
const qYear = Number(query.get('year'));
const qDept = query.get('dept');

const deptKey = ref(props.departmentOptions.some((o) => o.key === qDept) ? qDept : 'all');
const year = ref(props.years.includes(qYear) ? qYear : props.initialYear);
const yearsToShow = ref(2);
const loading = ref(false);
const errorMessage = ref('');
const summary = ref(null);
const monthlyTableOpen = ref(false);
const refreshKey = ref(0);
// 部門別: 部は常に、課・直轄は親の部（営業第三部）が開いているときだけ表示。構成比の棒は、部（第三部は全体）だけで作る
const collapsed = ref({}); // 閉じている部（既定は空 = 営業第三部も開いた状態）
const toggleDept = (key) => { collapsed.value = { ...collapsed.value, [key]: !collapsed.value[key] }; };
const visibleDepartments = computed(() => (summary.value?.by_department ?? []).filter((d) => d.level === 0 || !collapsed.value[d.parent]));
const deptShare = computed(() => (summary.value?.by_department ?? []).filter((d) => d.level === 0).map((d) => ({ label: d.label, amount: d.amount })));
const clientShareRows = ref([]);
const clientShareTotal = ref(null);

const monthLabels = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];
const mutedPalette = ['#9CA3AF', '#F59E0B', '#10B981', '#EC4899'];
const seriesColor = (index, total) => (index === total - 1 ? '#4F46E5' : mutedPalette[index % mutedPalette.length]);

const chartRef = ref(null);
let chart = null;

const renderChart = () => {
    if (!chartRef.value || !summary.value?.monthly?.length) return;
    chart?.destroy();
    const series = summary.value.monthly;
    chart = new Chart(chartRef.value.getContext('2d'), {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: series.map((s, i) => ({
                label: `${s.year}年`,
                data: monthLabelsOrder().map((m) => s.months[m]),
                borderColor: seriesColor(i, series.length),
                backgroundColor: 'transparent',
                borderWidth: i === series.length - 1 ? 3 : 1.5,
                borderDash: i === series.length - 1 ? [] : [4, 4],
                spanGaps: false, // 未登録の月は線をつなげない（0円ではない）
                tension: 0.2,
            })),
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true, position: 'bottom' } },
            scales: { y: { beginAtZero: true, ticks: { callback: (v) => `¥${Number(v).toLocaleString()}` } } },
            interaction: { mode: 'nearest', intersect: false },
        },
    });
};
const monthLabelsOrder = () => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

const params = () => ({ year: year.value, dept: deptKey.value });

const fetchSummary = async () => {
    if (!year.value) return;
    loading.value = true;
    errorMessage.value = '';
    try {
        const res = await axios.get(route('suna.sales.report.api.annual.summary'), { params: { ...params(), years: yearsToShow.value } });
        summary.value = res.data;
        refreshKey.value += 1;
        await nextTick();
        renderChart();
    } catch (e) {
        errorMessage.value = 'データの取得に失敗しました。';
        summary.value = null;
    } finally {
        loading.value = false;
    }
};

// 得意先ランキング: RankingPanel が返す行は名前だけなので、行クリックで得意先分析へ移るため、名前 → キーの対応を覚えておく
const clientKeys = {};
const fetchClientsPage = async (p) => {
    const res = await axios.get(route('suna.sales.report.api.annual.clients'), {
        params: { ...params(), mode: p.mode, sort: p.sort, direction: p.direction, keyword: p.keyword, page: p.page, limit: p.limit },
    });
    res.data.rows.forEach((r) => { clientKeys[r.label] = r.client_key; });

    return res.data;
};
const goToClient = (label) => {
    const key = clientKeys[label];
    if (key) router.get(route('suna.sales.report.client'), { client: key });
};

const clientModes = [
    { value: 'all', label: 'すべて' },
    { value: 'increase', label: '増加' },
    { value: 'decrease', label: '減少' },
    { value: 'new', label: '新規' },
    { value: 'ended', label: '終了' },
];
const clientSortOptions = [
    { sort: 'amount', direction: 'desc', label: '金額順' },
    { sort: 'diff', direction: 'desc', label: '前年差の大きい順' },
    { sort: 'diff', direction: 'asc', label: '前年差の小さい順' },
];

const goLatest = () => { if (props.initialYear) year.value = props.initialYear; };
const syncQueryString = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('dept', deptKey.value);
    url.searchParams.set('year', String(year.value));
    window.history.replaceState(null, '', url);
};

watch([deptKey, year], () => { syncQueryString(); fetchSummary(); });
watch(yearsToShow, fetchSummary);
onMounted(fetchSummary);
onBeforeUnmount(() => chart?.destroy());

const kpiCards = computed(() => {
    const k = summary.value?.kpi;
    if (!k) return [];

    return [
        { key: 'amount', label: '売上', m: k.amount, fmt: yen },
        { key: 'orders', label: '受注件数', m: k.order_count, fmt: (v) => (v === null ? '—' : `${Number(v).toLocaleString()}件`) },
        { key: 'avg', label: '平均受注単価', m: k.avg_order_price, fmt: yen },
        { key: 'clients', label: '取引社数', m: k.client_count, fmt: (v) => (v === null ? '—' : `${v}社`) },
    ];
});
</script>

<template>
    <ReportShell title="年次分析" active="annual">
        <template #actions>
            <span v-if="exportError" class="max-w-xs text-xs text-red-600">{{ exportError }}</span>
            <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-50" :disabled="exporting || !summary" @click="exportExcel(route('suna.sales.report.export.annual'), { year, dept: deptKey })">Excel出力</button>
        </template>
        <div v-if="!years.length" class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">まだ売上データが取り込まれていません。</p>
            <Link :href="route('suna.sales.report.index')" class="mt-3 inline-block text-sm text-indigo-600 hover:underline">データ登録状況へ</Link>
        </div>

        <div v-else class="space-y-4">
            <PeriodBar
                v-model:dept-key="deptKey"
                v-model:year="year"
                mode="year"
                :department-options="departmentOptions"
                :years="years"
                :loading="loading"
                @go-latest="goLatest"
            />

            <p v-if="errorMessage" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="loading" class="text-sm text-gray-500">読み込み中...</p>

            <template v-if="summary && summary.has_data">
                <div class="rounded bg-indigo-50 p-3 text-xs text-indigo-800">
                    <p>
                        <span class="font-semibold">{{ year }}年 {{ summary.period.label }}</span>
                        <template v-if="summary.prior.available">を、前年（{{ summary.prior.year }}年）の同じ月と比べています。</template>
                    </p>
                    <p v-if="summary.period.is_partial" class="mt-0.5 text-indigo-600">この年は途中までの登録です（登録済みの月だけを集計）。</p>
                    <p v-if="!summary.prior.available" class="mt-0.5 text-amber-700">
                        前年のデータがないため、前年比と新規・終了の判定は表示しません（データの最初の年です）。
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="c in kpiCards" :key="c.key" class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">{{ c.label }}</p>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ c.fmt(c.m.value) }}</p>
                        <p v-if="summary.prior.available" class="mt-0.5 text-xs">
                            <span class="font-semibold" :class="pctClass(c.m.rate)">{{ pct(c.m.rate) }}</span>
                            <span class="ml-1 text-gray-400">前年同期間 {{ c.fmt(c.m.prior) }}</span>
                        </p>
                    </div>
                </div>

                <div class="rounded bg-white p-4 shadow">
                    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">月別売上（{{ yearsToShow === 2 ? `${year}年 対 ${year - 1}年` : `直近${yearsToShow}年` }}）</h3>
                        <div class="flex rounded border text-xs">
                            <button type="button" class="px-2 py-1" :class="yearsToShow === 2 ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="yearsToShow = 2">対前年</button>
                            <button type="button" class="px-2 py-1" :class="yearsToShow === 3 ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="yearsToShow = 3">過去3年</button>
                            <button type="button" class="px-2 py-1" :class="yearsToShow === 5 ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="yearsToShow = 5">過去5年</button>
                        </div>
                    </div>
                    <p class="mb-2 text-xs text-gray-400">登録のない月は線をつなげません（0円ではなく未登録）。凡例クリックで年ごとの表示/非表示を切り替えられます。</p>
                    <canvas ref="chartRef" style="max-height: 280px"></canvas>

                    <button type="button" class="mt-3 text-xs text-indigo-600 hover:underline" @click="monthlyTableOpen = !monthlyTableOpen">
                        {{ monthlyTableOpen ? '▲ 数値表を閉じる' : '▼ 数値表を開く' }}
                    </button>
                    <div v-if="monthlyTableOpen" class="mt-2 overflow-x-auto">
                        <table class="min-w-full text-xs tabular-nums">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">年</th>
                                    <th v-for="(l, i) in monthLabels" :key="i" class="py-1 text-right">{{ l }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in summary.monthly" :key="s.year" class="border-t">
                                    <td class="py-1 font-semibold">{{ s.year }}年</td>
                                    <td v-for="m in 12" :key="m" class="py-1 text-right">{{ s.months[m] === null ? '—' : yen(s.months[m]) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900">部門別（{{ summary.period.label }}）<span class="ml-2 text-xs font-normal text-gray-400">部門の選択に関わらず、全部門の内訳です</span></h3>
                    <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-xs tabular-nums">
                                <thead>
                                    <tr class="text-left text-gray-500">
                                        <th class="py-1">部門</th>
                                        <th class="py-1 text-right">売上</th>
                                        <th v-if="summary.prior.available" class="py-1 text-right">前年差</th>
                                        <th v-if="summary.prior.available" class="py-1 text-right">前年比</th>
                                        <th class="py-1 text-right">構成比</th>
                                        <th class="py-1 text-right">取引社数</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="d in visibleDepartments" :key="d.key" class="border-t" :class="d.level === 1 ? 'bg-amber-50/40 text-gray-600' : ''">
                                        <td class="min-w-[11rem] whitespace-nowrap py-1" :class="d.level === 1 ? 'pl-6' : ''">
                                            {{ d.level === 1 ? `└ ${d.label}` : d.label }}<span v-if="d.has_children" class="ml-1 text-[10px] text-gray-400">（全体）</span>
                                            <button v-if="d.has_children" type="button" class="ml-1 inline-block w-4 text-indigo-600" :title="collapsed[d.key] ? '課・直轄を開く' : '課・直轄を閉じる'" @click="toggleDept(d.key)">{{ collapsed[d.key] ? '▶' : '▼' }}</button>
                                        </td>
                                        <td class="py-1 text-right">{{ yen(d.amount) }}</td>
                                        <td v-if="summary.prior.available" class="py-1 text-right" :class="pctClass(d.rate)">{{ d.diff !== null ? yen(d.diff) : '—' }}</td>
                                        <td v-if="summary.prior.available" class="py-1 text-right" :class="pctClass(d.rate)">{{ d.rate !== null ? pct(d.rate) : '—' }}</td>
                                        <td class="py-1 text-right">{{ d.share_pct !== null ? `${d.share_pct}%` : '—' }}</td>
                                        <td class="py-1 text-right">{{ d.client_count }}社</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <ShareCompositionBar title="部門別構成比" :rows="deptShare" :top-n="6" />
                    </div>
                </div>

                <div class="rounded bg-white p-4 shadow">
                    <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
                        <RankingPanel
                            title="得意先別"
                            :fetch-page="fetchClientsPage"
                            :modes="clientModes"
                            :sort-options="clientSortOptions"
                            diff-columns
                            diff-label="前年差"
                            rate-label="前年比"
                            :refresh-key="refreshKey"
                            @select-row="goToClient"
                            @rows-updated="(rows, total) => { clientShareRows = rows; clientShareTotal = total; }"
                        />
                        <ShareCompositionBar title="得意先別構成比" :rows="clientShareRows" :total="clientShareTotal" />
                    </div>
                    <p class="mt-2 text-[11px] text-gray-400">
                        新規 = 前年の同じ期間に売上がなく、今の期間にある得意先／終了 = 前年の同じ期間に売上があり、今の期間にない得意先。行をクリックすると得意先分析へ移ります。
                    </p>
                </div>
            </template>
        </div>
    </ReportShell>
</template>
