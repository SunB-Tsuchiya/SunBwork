<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import ReportShell from '@/Components/SunA/Sales/ReportShell.vue';
import { useSalesChart } from '@/Composables/useSalesChart';

// サンエー印刷 売上レポートの左右比較（SUNASALES_PLAN1.md §18）。2つの期間 A・B を並べて比べる。差・増減率は A に対する B。
// 「年と年」は両方の年に登録のある月だけを比べる。「月と月」は「前月と比べる」「前年の同じ月と比べる」の近道がある（月次の見方を兼ねる）。
const props = defineProps({
    departmentOptions: { type: Array, default: () => [] },
    years: { type: Array, default: () => [] },
    monthsByYear: { type: Object, default: () => ({}) },
    latestYear: { type: Number, default: null },
    latestMonth: { type: Number, default: 1 },
});

const { yen, pct, pctClass } = useSalesChart();
const monthLabels = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];

const mode = ref('month');
const dept = ref('all');
const latest = props.latestYear ?? new Date().getFullYear();
// 年と年: A = 前の年、B = 最新年
const yearA = ref(props.years.includes(latest - 1) ? latest - 1 : latest);
const yearB = ref(latest);
// 月と月: B = 最終実績月、A = その前年の同じ月
const monthYearA = ref(latest - 1);
const monthA = ref(props.latestMonth);
const monthYearB = ref(latest);
const monthB = ref(props.latestMonth);

const loading = ref(false);
const errorMessage = ref('');
const summary = ref(null);
const expanded = ref({});

const registered = (year, month) => (props.monthsByYear[year] ?? []).includes(month);

// 近道
const compareWithPrevMonth = () => {
    monthYearB.value = props.latestYear ?? latest;
    monthB.value = props.latestMonth;
    monthYearA.value = monthB.value === 1 ? monthYearB.value - 1 : monthYearB.value;
    monthA.value = monthB.value === 1 ? 12 : monthB.value - 1;
};
const compareWithSameMonthLastYear = () => {
    monthYearB.value = props.latestYear ?? latest;
    monthB.value = props.latestMonth;
    monthYearA.value = monthYearB.value - 1;
    monthA.value = monthB.value;
};

const params = () => (mode.value === 'year'
    ? { mode: 'year', year_a: yearA.value, year_b: yearB.value, dept: dept.value }
    : { mode: 'month', year_a: monthYearA.value, month_a: monthA.value, year_b: monthYearB.value, month_b: monthB.value, dept: dept.value });

const fetchSummary = async () => {
    loading.value = true;
    errorMessage.value = '';
    try {
        const res = await axios.get(route('suna.sales.report.api.compare.summary'), { params: params() });
        summary.value = res.data;
    } catch (e) {
        errorMessage.value = 'データの取得に失敗しました。';
        summary.value = null;
    } finally {
        loading.value = false;
    }
};
watch([mode, dept, yearA, yearB, monthYearA, monthA, monthYearB, monthB], fetchSummary);
onMounted(() => { if (props.years.length) fetchSummary(); });

const toggle = (key) => { expanded.value = { ...expanded.value, [key]: !expanded.value[key] }; };
const visibleDepartments = computed(() => (summary.value?.by_department ?? []).filter((r) => r.level === 0 || expanded.value[r.parent]));

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
const STATUS = { new: { text: '新規', cls: 'bg-blue-100 text-blue-700' }, ended: { text: '終了', cls: 'bg-red-100 text-red-700' } };
</script>

<template>
    <ReportShell title="左右比較" active="compare">
        <div v-if="!years.length" class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">まだ売上データが取り込まれていません。</p>
        </div>

        <div v-else class="space-y-4">
            <div class="rounded bg-white p-4 shadow">
                <div class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500">比べ方</label>
                        <div class="mt-1 flex rounded border text-sm">
                            <button type="button" class="px-3 py-1.5" :class="mode === 'month' ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="mode = 'month'">月と月</button>
                            <button type="button" class="px-3 py-1.5" :class="mode === 'year' ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="mode = 'year'">年と年</button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">部門</label>
                        <select v-model="dept" class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm">
                            <option v-for="o in departmentOptions" :key="o.key" :value="o.key">{{ o.label }}</option>
                        </select>
                    </div>

                    <template v-if="mode === 'year'">
                        <div>
                            <label class="block text-xs font-medium text-gray-500">期間A（比べる元）</label>
                            <select v-model.number="yearA" class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm"><option v-for="y in years" :key="y" :value="y">{{ y }}年</option></select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500">期間B（新しい方）</label>
                            <select v-model.number="yearB" class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm"><option v-for="y in years" :key="y" :value="y">{{ y }}年</option></select>
                        </div>
                    </template>
                    <template v-else>
                        <div>
                            <label class="block text-xs font-medium text-gray-500">期間A（比べる元）</label>
                            <div class="mt-1 flex gap-1">
                                <select v-model.number="monthYearA" class="rounded-md border-gray-300 text-sm shadow-sm"><option v-for="y in years" :key="y" :value="y">{{ y }}年</option></select>
                                <select v-model.number="monthA" class="rounded-md border-gray-300 text-sm shadow-sm"><option v-for="(l, i) in monthLabels" :key="i" :value="i + 1">{{ l }}{{ registered(monthYearA, i + 1) ? '' : '（未登録）' }}</option></select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500">期間B（新しい方）</label>
                            <div class="mt-1 flex gap-1">
                                <select v-model.number="monthYearB" class="rounded-md border-gray-300 text-sm shadow-sm"><option v-for="y in years" :key="y" :value="y">{{ y }}年</option></select>
                                <select v-model.number="monthB" class="rounded-md border-gray-300 text-sm shadow-sm"><option v-for="(l, i) in monthLabels" :key="i" :value="i + 1">{{ l }}{{ registered(monthYearB, i + 1) ? '' : '（未登録）' }}</option></select>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="rounded-md border border-indigo-300 bg-indigo-50 px-2 py-1.5 text-xs text-indigo-700 hover:bg-indigo-100" @click="compareWithPrevMonth">最終実績月を前月と比べる</button>
                            <button type="button" class="rounded-md border border-indigo-300 bg-indigo-50 px-2 py-1.5 text-xs text-indigo-700 hover:bg-indigo-100" @click="compareWithSameMonthLastYear">最終実績月を前年の同じ月と比べる</button>
                        </div>
                    </template>
                </div>
            </div>

            <p v-if="errorMessage" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="loading" class="text-sm text-gray-500">読み込み中...</p>
            <p v-if="summary && !summary.has_data" class="rounded bg-amber-50 p-4 text-sm text-amber-800">{{ summary.reason }}</p>

            <template v-if="summary && summary.has_data">
                <div class="rounded bg-indigo-50 p-3 text-xs text-indigo-800">
                    <span class="font-semibold">A: {{ summary.a.label }}</span> と <span class="font-semibold">B: {{ summary.b.label }}</span> を比べています（差・増減率は A に対する B）。
                    <span v-if="summary.mode === 'year'" class="text-indigo-600">両方の年に登録のある月だけを集計しています。</span>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="c in kpiCards" :key="c.key" class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">{{ c.label }}</p>
                        <p class="mt-1 text-sm text-gray-500">A <span class="font-semibold text-gray-800">{{ c.fmt(c.m.a) }}</span></p>
                        <p class="text-lg font-bold text-gray-900">B {{ c.fmt(c.m.b) }}</p>
                        <p class="mt-0.5 text-xs">
                            <span :class="pctClass(c.m.rate)" class="font-semibold">{{ pct(c.m.rate) }}</span>
                            <span v-if="c.m.diff !== null" class="ml-1 text-gray-400">（差 {{ c.key === 'amount' || c.key === 'avg' ? yen(c.m.diff) : c.m.diff.toLocaleString() }}）</span>
                        </p>
                    </div>
                </div>

                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900">部門別<span class="ml-2 text-xs font-normal text-gray-400">部門の選択に関わらず、全部門の内訳です</span></h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs tabular-nums">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">部門</th>
                                    <th class="py-1 text-right">A</th>
                                    <th class="py-1 text-right">B</th>
                                    <th class="py-1 text-right">差</th>
                                    <th class="py-1 text-right">増減率</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in visibleDepartments" :key="r.key" class="border-t" :class="r.key === 'total' ? 'bg-gray-50 font-semibold' : r.level === 1 ? 'bg-amber-50/40 text-gray-600' : ''">
                                    <td class="py-1" :class="r.level === 1 ? 'pl-6' : ''">
                                        <button v-if="r.has_children" type="button" class="mr-1 inline-block w-4 text-indigo-600" :title="expanded[r.key] ? '課・直轄を閉じる' : '課・直轄を開く'" @click="toggle(r.key)">{{ expanded[r.key] ? '▼' : '▶' }}</button>
                                        {{ r.level === 1 ? `└ ${r.label}` : r.label }}<span v-if="r.has_children && !expanded[r.key]" class="ml-1 text-[10px] text-gray-400">（全体）</span>
                                    </td>
                                    <td class="py-1 text-right">{{ yen(r.a) }}</td>
                                    <td class="py-1 text-right">{{ yen(r.b) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(r.rate)">{{ yen(r.diff) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(r.rate)">{{ r.rate !== null ? pct(r.rate) : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900">得意先別<span class="ml-2 text-xs font-normal text-gray-400">Bの売上の多い順に上位15社（全{{ summary.clients.total_clients }}社）。名寄せ後です</span></h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs tabular-nums">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">得意先</th>
                                    <th class="py-1 text-right">A</th>
                                    <th class="py-1 text-right">B</th>
                                    <th class="py-1 text-right">差</th>
                                    <th class="py-1 text-right">増減率</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in summary.clients.top" :key="c.client_key" class="border-t">
                                    <td class="py-1">{{ c.label }}<span v-if="STATUS[c.status]" class="ml-1 rounded px-1 text-[10px]" :class="STATUS[c.status].cls">{{ STATUS[c.status].text }}</span></td>
                                    <td class="py-1 text-right">{{ yen(c.a) }}</td>
                                    <td class="py-1 text-right">{{ yen(c.b) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(c.rate ?? (c.diff > 0 ? 1 : c.diff < 0 ? -1 : 0))">{{ yen(c.diff) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(c.rate)">{{ c.rate !== null ? pct(c.rate) : '—' }}</td>
                                </tr>
                                <tr v-if="summary.clients.others" class="border-t text-gray-600">
                                    <td class="py-1">その他（{{ summary.clients.others.count }}社）</td>
                                    <td class="py-1 text-right">{{ yen(summary.clients.others.a) }}</td>
                                    <td class="py-1 text-right">{{ yen(summary.clients.others.b) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(summary.clients.others.rate)">{{ yen(summary.clients.others.diff) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(summary.clients.others.rate)">{{ summary.clients.others.rate !== null ? pct(summary.clients.others.rate) : '—' }}</td>
                                </tr>
                                <tr v-if="!summary.clients.top.length"><td colspan="5" class="py-2 text-gray-400">Bに売上のある得意先がありません。</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-[11px] text-gray-400">新規 = Aに売上がなく、Bにある得意先。終了 = Aに売上があり、Bにない得意先（下の一覧）。</p>
                </div>

                <div v-if="summary.clients.ended.length" class="rounded bg-white p-4 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900">終了した得意先<span class="ml-2 text-xs font-normal text-gray-400">Aに売上があり、Bにない得意先（Aの売上の多い順に上位10社。全{{ summary.clients.ended_total }}社）</span></h3>
                    <table class="min-w-full text-xs tabular-nums">
                        <thead>
                            <tr class="text-left text-gray-500"><th class="py-1">得意先</th><th class="py-1 text-right">A</th><th class="py-1 text-right">B</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in summary.clients.ended" :key="c.client_key" class="border-t">
                                <td class="py-1">{{ c.label }}</td>
                                <td class="py-1 text-right">{{ yen(c.a) }}</td>
                                <td class="py-1 text-right text-gray-400">{{ yen(c.b) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </ReportShell>
</template>
