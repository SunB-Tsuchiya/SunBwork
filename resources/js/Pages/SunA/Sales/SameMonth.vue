<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import Chart from 'chart.js/auto';
import ReportShell from '@/Components/SunA/Sales/ReportShell.vue';
import PeriodBar from '@/Components/SunA/Sales/PeriodBar.vue';
import { useSalesChart } from '@/Composables/useSalesChart';
import { useReportExport } from '@/Components/SunA/Sales/useReportExport.js';

// サンエー印刷 売上レポートの同月比較（SUNASALES_PLAN1.md §15）。選んだ月を年どうしで比べる。
// その月に登録のない年は除外する（0円として並べない）。前年比は、前の年にもその月の登録があるときだけ出す。
const props = defineProps({
    departmentOptions: { type: Array, default: () => [] },
    monthsWithData: { type: Array, default: () => [] },
    initialMonth: { type: Number, default: 1 },
});

const { yen, pct, pctClass } = useSalesChart();
const { exporting, exportError, exportExcel } = useReportExport();
const query = new URLSearchParams(window.location.search);
const qMonth = Number(query.get('month'));
const qDept = query.get('dept');

const deptKey = ref(props.departmentOptions.some((o) => o.key === qDept) ? qDept : 'all');
const month = ref(qMonth >= 1 && qMonth <= 12 ? qMonth : props.initialMonth);
const loading = ref(false);
const errorMessage = ref('');
const summary = ref(null);

const chartRef = ref(null);
let chart = null;

const renderChart = () => {
    if (!chartRef.value || !summary.value?.years?.length) return;
    chart?.destroy();
    const rows = summary.value.years;
    chart = new Chart(chartRef.value.getContext('2d'), {
        type: 'bar',
        data: {
            labels: rows.map((r) => `${r.year}年`),
            datasets: [{ label: `${month.value}月の売上`, data: rows.map((r) => r.amount), backgroundColor: rows.map((_, i) => (i === rows.length - 1 ? '#4F46E5' : '#A5B4FC')) }],
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
        const res = await axios.get(route('suna.sales.report.api.same_month.summary'), { params: { month: month.value, dept: deptKey.value } });
        summary.value = res.data;
        await nextTick();
        renderChart();
    } catch (e) {
        errorMessage.value = 'データの取得に失敗しました。';
        summary.value = null;
    } finally {
        loading.value = false;
    }
};

const goLatest = () => { month.value = props.initialMonth; };
const syncQueryString = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('dept', deptKey.value);
    url.searchParams.set('month', String(month.value));
    window.history.replaceState(null, '', url);
};
watch([deptKey, month], () => { syncQueryString(); fetchSummary(); });
onMounted(fetchSummary);
onBeforeUnmount(() => chart?.destroy());

const matrix = computed(() => summary.value?.by_department ?? { years: [], rows: [] });
// 部門別: 部は常に、課・直轄は親の部（営業第三部）が開いているときだけ表示
const collapsed = ref({}); // 閉じている部（既定は空 = 営業第三部も開いた状態）
const toggleDept = (key) => { collapsed.value = { ...collapsed.value, [key]: !collapsed.value[key] }; };
const visibleDepartmentRows = computed(() => matrix.value.rows.filter((r) => r.level === 0 || !collapsed.value[r.parent]));
</script>

<template>
    <ReportShell title="同月比較" active="same_month">
        <template #actions>
            <span v-if="exportError" class="max-w-xs text-xs text-red-600">{{ exportError }}</span>
            <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-50" :disabled="exporting || !summary" @click="exportExcel(route('suna.sales.report.export.same_month'), { month, dept: deptKey })">Excel出力</button>
        </template>
        <div v-if="!monthsWithData.length" class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">まだ売上データが取り込まれていません。</p>
            <Link :href="route('suna.sales.report.index')" class="mt-3 inline-block text-sm text-indigo-600 hover:underline">データ登録状況へ</Link>
        </div>

        <div v-else class="space-y-4">
            <PeriodBar
                v-model:dept-key="deptKey"
                v-model:month="month"
                mode="month"
                :department-options="departmentOptions"
                :months-with-data="monthsWithData"
                :loading="loading"
                @go-latest="goLatest"
            />

            <p v-if="errorMessage" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="loading" class="text-sm text-gray-500">読み込み中...</p>

            <p v-if="summary && !summary.has_data" class="rounded bg-white p-6 text-sm text-gray-500 shadow">{{ month }}月は、どの年にも売上が登録されていません。</p>

            <template v-if="summary && summary.has_data">
                <div class="rounded bg-indigo-50 p-3 text-xs text-indigo-800">
                    {{ month }}月を、登録のある年どうしで比べています（{{ summary.years.map((y) => y.year).join('・') }}年）。{{ month }}月が未登録の年は含めません。
                </div>

                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900">{{ month }}月の売上（年別）</h3>
                    <canvas ref="chartRef" style="max-height: 260px"></canvas>
                </div>

                <div class="rounded bg-white p-4 shadow">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs tabular-nums">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">年</th>
                                    <th class="py-1 text-right">売上</th>
                                    <th class="py-1 text-right">前年差</th>
                                    <th class="py-1 text-right">前年比</th>
                                    <th class="py-1 text-right">受注件数</th>
                                    <th class="py-1 text-right">平均受注単価</th>
                                    <th class="py-1 text-right">取引社数</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in summary.years" :key="r.year" class="border-t">
                                    <td class="py-1 font-semibold">{{ r.year }}年{{ month }}月</td>
                                    <td class="py-1 text-right">{{ yen(r.amount) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(r.rate)">{{ r.diff !== null ? yen(r.diff) : '—' }}</td>
                                    <td class="py-1 text-right" :class="pctClass(r.rate)">{{ r.diff !== null ? pct(r.rate) : '—' }}</td>
                                    <td class="py-1 text-right">{{ r.order_count.toLocaleString() }}件</td>
                                    <td class="py-1 text-right">{{ yen(r.avg_order_price) }}</td>
                                    <td class="py-1 text-right">{{ r.client_count }}社</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-[11px] text-gray-400">前年差・前年比は、前の年にも{{ month }}月の登録があるときだけ表示します。</p>
                </div>

                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900">部門別（{{ month }}月）<span class="ml-2 text-xs font-normal text-gray-400">部門の選択に関わらず、全部門の内訳です</span></h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs tabular-nums">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">部門</th>
                                    <th v-for="y in matrix.years" :key="y" class="py-1 text-right">{{ y }}年</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in visibleDepartmentRows" :key="r.key" class="border-t" :class="r.level === 1 ? 'bg-amber-50/40 text-gray-600' : ''">
                                    <td class="min-w-[11rem] whitespace-nowrap py-1" :class="r.level === 1 ? 'pl-6' : ''">
                                        {{ r.level === 1 ? `└ ${r.label}` : r.label }}<span v-if="r.has_children" class="ml-1 text-[10px] text-gray-400">（全体）</span>
                                        <button v-if="r.has_children" type="button" class="ml-1 inline-block w-4 text-indigo-600" :title="collapsed[r.key] ? '課・直轄を開く' : '課・直轄を閉じる'" @click="toggleDept(r.key)">{{ collapsed[r.key] ? '▶' : '▼' }}</button>
                                    </td>
                                    <td v-for="y in matrix.years" :key="y" class="py-1 text-right">{{ yen(r.amounts[y]) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </ReportShell>
</template>
