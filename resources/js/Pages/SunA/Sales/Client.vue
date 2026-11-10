<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import Chart from 'chart.js/auto';
import ReportShell from '@/Components/SunA/Sales/ReportShell.vue';
import { useSalesChart } from '@/Composables/useSalesChart';

// サンエー印刷 売上レポートの得意先分析（SUNASALES_PLAN1.md §15）。得意先を検索して選び、年別・月別の推移と担当部門の履歴を見る。
// 年次分析のランキングから ?client=キー で開くと、その得意先を最初から表示する。
const props = defineProps({
    initialClientKey: { type: String, default: null },
});

const { yen, pct, pctClass } = useSalesChart();
const keyword = ref('');
const results = ref([]);
const searching = ref(false);
const selectedKey = ref(props.initialClientKey);
const detail = ref(null);
const loading = ref(false);
const errorMessage = ref('');
const monthlyOpen = ref(true);
const monthLabels = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];

let debounce = null;
const search = async () => {
    searching.value = true;
    try {
        const res = await axios.get(route('suna.sales.report.api.client.search'), { params: { keyword: keyword.value, limit: 20 } });
        results.value = res.data.clients;
    } catch (e) {
        results.value = [];
    } finally {
        searching.value = false;
    }
};
watch(keyword, () => { clearTimeout(debounce); debounce = setTimeout(search, 300); });

const chartRef = ref(null);
let chart = null;
const renderChart = () => {
    if (!chartRef.value || !detail.value) return;
    chart?.destroy();
    const years = detail.value.years;
    chart = new Chart(chartRef.value.getContext('2d'), {
        type: 'bar',
        data: {
            labels: years.map((y) => `${y.year}年`),
            datasets: [{ label: '年間売上（登録済みの月の合計）', data: years.map((y) => y.amount), backgroundColor: years.map((_, i) => (i === years.length - 1 ? '#4F46E5' : '#A5B4FC')) }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { callback: (v) => `¥${Number(v).toLocaleString()}` } } },
        },
    });
};

const loadDetail = async () => {
    if (!selectedKey.value) { detail.value = null; return; }
    loading.value = true;
    errorMessage.value = '';
    try {
        const res = await axios.get(route('suna.sales.report.api.client.detail'), { params: { client_key: selectedKey.value } });
        detail.value = res.data;
        await nextTick();
        renderChart();
    } catch (e) {
        detail.value = null;
        errorMessage.value = e.response?.status === 404 ? '得意先が見つかりません。' : 'データの取得に失敗しました。';
    } finally {
        loading.value = false;
    }
};
const select = (key) => {
    selectedKey.value = key;
    const url = new URL(window.location.href);
    url.searchParams.set('client', key);
    window.history.replaceState(null, '', url);
};
watch(selectedKey, loadDetail);
onMounted(() => { search(); loadDetail(); });
onBeforeUnmount(() => chart?.destroy());

const monthAmount = (y, m) => y.months[m]?.amount ?? null;
const hasDeptChange = computed(() => (detail.value?.department_history ?? []).some((h) => h.changed));
</script>

<template>
    <ReportShell title="得意先分析" active="client">
        <div class="space-y-4">
            <div class="rounded bg-white p-4 shadow">
                <label class="block text-xs font-medium text-gray-500">得意先を探す</label>
                <input v-model="keyword" type="text" placeholder="得意先名の一部を入力（空欄なら売上の多い順）" class="mt-1 block w-full max-w-md rounded-md border-gray-300 text-sm shadow-sm" />
                <p v-if="searching" class="mt-2 text-xs text-gray-400">検索中...</p>
                <ul v-else-if="results.length" class="mt-2 grid max-h-56 gap-1 overflow-y-auto sm:grid-cols-2">
                    <li v-for="c in results" :key="c.client_key">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-2 rounded border px-2 py-1.5 text-left text-xs hover:bg-gray-50"
                            :class="selectedKey === c.client_key ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'"
                            @click="select(c.client_key)"
                        >
                            <span class="min-w-0 overflow-hidden text-ellipsis whitespace-nowrap">{{ c.name }}</span>
                            <span class="whitespace-nowrap text-gray-400">{{ yen(c.total_amount) }}（〜{{ c.last_year }}年）</span>
                        </button>
                    </li>
                </ul>
                <p v-else class="mt-2 text-xs text-gray-400">該当する得意先がありません。</p>
            </div>

            <p v-if="errorMessage" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="loading" class="text-sm text-gray-500">読み込み中...</p>
            <p v-if="!selectedKey && !loading" class="rounded bg-white p-6 text-sm text-gray-500 shadow">得意先を選ぶと、年別・月別の推移と担当部門の履歴が表示されます。</p>

            <template v-if="detail">
                <div class="rounded bg-white p-4 shadow">
                    <h2 class="text-base font-bold text-gray-900">{{ detail.name }}</h2>
                    <p class="mt-0.5 text-xs text-gray-500">{{ detail.first_year }}年〜{{ detail.last_year }}年・累計 {{ yen(detail.total_amount) }}</p>
                    <p v-if="detail.members" class="mt-1 text-xs text-indigo-700">
                        名寄せ済み（{{ detail.members.length }}社をまとめて集計）: {{ detail.members.map((m) => m.name).join('、') }}
                    </p>
                    <div class="mt-3"><canvas ref="chartRef" style="max-height: 240px"></canvas></div>
                </div>

                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900">年別の推移</h3>
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
                                    <th class="py-1 text-right">登録月</th>
                                    <th class="py-1">担当部門</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="y in detail.years" :key="y.year" class="border-t">
                                    <td class="py-1 font-semibold">{{ y.year }}年</td>
                                    <td class="py-1 text-right">{{ yen(y.amount) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(y.rate)" :title="y.prior_amount !== null ? `${y.compare_label} を前年の同じ月と比較` : ''">{{ y.diff !== null ? yen(y.diff) : '—' }}</td>
                                    <td class="py-1 text-right" :class="pctClass(y.rate)">{{ y.rate !== null ? pct(y.rate) : '—' }}</td>
                                    <td class="py-1 text-right">{{ y.order_count.toLocaleString() }}件</td>
                                    <td class="py-1 text-right">{{ yen(y.avg_order_price) }}</td>
                                    <td class="py-1 text-right">{{ y.registered_months.length }}か月</td>
                                    <td class="py-1">{{ y.departments.join('・') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-[11px] text-gray-400">前年差・前年比は、その年の登録済みの月を、前年の同じ月と比べています（前年にデータがない最初の年は表示しません）。</p>
                </div>

                <div class="rounded bg-white p-4 shadow">
                    <button type="button" class="mb-2 text-sm font-semibold text-gray-900" @click="monthlyOpen = !monthlyOpen">月別の推移 {{ monthlyOpen ? '▲' : '▼' }}</button>
                    <div v-if="monthlyOpen" class="overflow-x-auto">
                        <table class="min-w-full text-xs tabular-nums">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">年</th>
                                    <th v-for="(l, i) in monthLabels" :key="i" class="py-1 text-right">{{ l }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="y in detail.years" :key="y.year" class="border-t">
                                    <td class="py-1 font-semibold">{{ y.year }}年</td>
                                    <td v-for="m in 12" :key="m" class="py-1 text-right">{{ monthAmount(y, m) === null ? '—' : yen(monthAmount(y, m)) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-[11px] text-gray-400">「—」は未登録の月です（0円とは区別しています）。</p>
                </div>

                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900">担当部門の履歴</h3>
                    <ol class="flex flex-wrap items-center gap-x-1 gap-y-1 text-xs">
                        <li v-for="(h, i) in detail.department_history" :key="h.year" class="flex items-center gap-1">
                            <span v-if="i > 0" class="text-gray-300">→</span>
                            <span class="rounded border px-2 py-0.5" :class="h.changed ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-gray-200 text-gray-700'">
                                {{ h.year }}年 {{ h.label }}<span v-if="h.changed" class="ml-1 font-semibold">（変更）</span>
                            </span>
                        </li>
                    </ol>
                    <p v-if="hasDeptChange" class="mt-2 text-[11px] text-gray-400">担当部門が変わった年は、その年の売上をその年の部門に計上しています。</p>
                </div>
            </template>
        </div>
    </ReportShell>
</template>
