<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import Chart from 'chart.js/auto';
import ReportShell from '@/Components/SunA/Sales/ReportShell.vue';
import { useSalesChart } from '@/Composables/useSalesChart';

// サンエー印刷 売上レポートの部門比較（SUNASALES_PLAN1.md §18）。部・課の売上を、年ごとに並べる。
// 既定は「同じ月まで」に揃える（最新年の登録済みの月に、全ての年を揃える）。「通年」に切り替えると、途中の年に印が付く。
// 営業第三部は「全体」の行を持ち、▶を開くと課・直轄の行が出る（第一部・第二部と、第三部全体を比べられる）。
const { yen, pct, pctClass } = useSalesChart();

const basis = ref('same_month'); // 'same_month' | 'year'
const splitChart = ref(false);   // グラフで、第三部を課・直轄に分けて表示する
const collapsed = ref({}); // 閉じている部（既定は空 = 営業第三部も開いた状態）
const shareYear = ref(null);     // 構成比・前年比を見る年
const loading = ref(false);
const errorMessage = ref('');
const summary = ref(null);

const chartRef = ref(null);
let chart = null;

const years = computed(() => summary.value?.years ?? []);
const rows = computed(() => summary.value?.rows ?? []);
const yearInfo = (y) => years.value.find((i) => i.year === y);

// 表に出す行: 部は常に、課は親の部が開いているときだけ
const visibleRows = computed(() => rows.value.filter((r) => r.level === 0 || !collapsed.value[r.parent]));
const toggle = (key) => { collapsed.value = { ...collapsed.value, [key]: !collapsed.value[key] }; };

// 色: 部ごとに固定（第三部の課は、第三部の色の濃淡）
const PALETTE = { dept1: '#4F46E5', dept2: '#10B981', dept3: '#F59E0B', dept3_sec1: '#F97316', dept3_sec2: '#FACC15', dept3_direct: '#B45309' };
const FALLBACK = ['#EC4899', '#06B6D4', '#8B5CF6', '#84CC16'];
const colorOf = (key, i) => PALETTE[key] ?? FALLBACK[i % FALLBACK.length];

const renderChart = () => {
    if (!chartRef.value || !summary.value?.has_data) return;
    chart?.destroy();
    // 既定は部だけ。切り替えると、課のある部は、その部の全体ではなく課・直轄に置き換える
    const targets = rows.value.filter((r) => r.key !== 'total' && (splitChart.value ? !r.has_children : r.level === 0));
    chart = new Chart(chartRef.value.getContext('2d'), {
        type: 'bar',
        data: {
            labels: years.value.map((y) => `${y.year}年${y.is_partial ? '（途中）' : ''}`),
            datasets: targets.map((r, i) => ({
                label: r.level === 1 ? `${r.label}（第三部）` : r.label,
                data: years.value.map((y) => r.amounts[y.year] ?? 0),
                backgroundColor: colorOf(r.key, i),
                stack: 'dept',
            })),
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ¥${Number(c.parsed.y).toLocaleString()}` } } },
            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { callback: (v) => `¥${Number(v).toLocaleString()}` } } },
        },
    });
};

const fetchSummary = async () => {
    loading.value = true;
    errorMessage.value = '';
    try {
        const res = await axios.get(route('suna.sales.report.api.department.summary'), { params: { basis: basis.value } });
        summary.value = res.data;
        const withData = (res.data.years ?? []).filter((y) => y.has_data);
        if (!shareYear.value || !withData.some((y) => y.year === shareYear.value)) {
            shareYear.value = withData.length ? withData[withData.length - 1].year : null;
        }
        await nextTick();
        renderChart();
    } catch (e) {
        errorMessage.value = 'データの取得に失敗しました。';
        summary.value = null;
    } finally {
        loading.value = false;
    }
};

watch(basis, fetchSummary);
watch(splitChart, renderChart);
onMounted(fetchSummary);
onBeforeUnmount(() => chart?.destroy());

const cell = (r, y) => r.amounts[y];
</script>

<template>
    <ReportShell title="部門比較" active="department">
        <div class="space-y-4">
            <div class="flex flex-wrap items-end gap-4 rounded bg-white p-4 shadow">
                <div>
                    <label class="block text-xs font-medium text-gray-500">集計の期間</label>
                    <div class="mt-1 flex rounded border text-sm">
                        <button type="button" class="px-3 py-1.5" :class="basis === 'same_month' ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="basis = 'same_month'">同じ月まで</button>
                        <button type="button" class="px-3 py-1.5" :class="basis === 'year' ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="basis = 'year'">通年</button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500">グラフの表示</label>
                    <label class="mt-1 flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="splitChart" type="checkbox" class="rounded border-gray-300" />
                        第三部を課・直轄に分けて表示
                    </label>
                </div>
                <div v-if="summary && summary.has_data" class="text-xs text-gray-500">
                    集計: <span class="font-semibold text-gray-800">{{ summary.label }}</span>
                </div>
            </div>

            <p v-if="errorMessage" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="loading" class="text-sm text-gray-500">読み込み中...</p>
            <p v-if="summary && !summary.has_data" class="rounded bg-white p-6 text-sm text-gray-500 shadow">まだ売上データが取り込まれていません。</p>

            <template v-if="summary && summary.has_data">
                <div class="rounded bg-indigo-50 p-3 text-xs text-indigo-800">
                    <template v-if="basis === 'same_month'">最新年に登録のある月（{{ summary.label.replace('各年 ', '') }}）に、すべての年を揃えて集計しています。最新年が途中でも、年どうしを公平に比べられます。</template>
                    <template v-else>通年（1〜12月）で集計しています。途中までの登録の年は「途中」と表示します（その年の登録済みの月だけの合計です）。</template>
                </div>

                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900">部門別の売上（年別）</h3>
                    <canvas ref="chartRef" style="max-height: 300px"></canvas>
                </div>

                <div class="rounded bg-white p-4 shadow">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">部門別の推移</h3>
                        <label class="flex items-center gap-2 text-xs text-gray-500">
                            構成比・前年比の年:
                            <select v-model.number="shareYear" class="rounded border-gray-300 py-1 text-xs">
                                <option v-for="y in years.filter((i) => i.has_data)" :key="y.year" :value="y.year">{{ y.year }}年</option>
                            </select>
                        </label>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs tabular-nums">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">部門</th>
                                    <th v-for="y in years" :key="y.year" class="py-1 text-right">
                                        {{ y.year }}年
                                        <span v-if="!y.has_data" class="ml-1 rounded bg-gray-200 px-1 text-[10px] text-gray-600" title="この期間は売上が登録されていません">未登録</span>
                                        <span v-else-if="y.is_partial" class="ml-1 rounded bg-amber-100 px-1 text-[10px] text-amber-700" :title="`登録済みの月: ${y.partial_label}`">{{ basis === 'year' ? '途中' : '一部未登録' }}</span>
                                    </th>
                                    <th class="py-1 text-right">構成比（{{ shareYear }}年）</th>
                                    <th class="py-1 text-right">前年比（{{ shareYear }}年）</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in visibleRows" :key="r.key" class="border-t" :class="r.key === 'total' ? 'bg-gray-50 font-semibold' : r.level === 1 ? 'bg-amber-50/40 text-gray-600' : ''">
                                    <td class="min-w-[11rem] whitespace-nowrap py-1" :class="r.level === 1 ? 'pl-6' : ''">
                                        {{ r.level === 1 ? `└ ${r.label}` : r.label }}<span v-if="r.has_children" class="ml-1 text-[10px] text-gray-400">（全体）</span>
                                        <button v-if="r.has_children" type="button" class="ml-1 inline-block w-4 text-indigo-600" :title="collapsed[r.key] ? '課・直轄を開く' : '課・直轄を閉じる'" @click="toggle(r.key)">{{ collapsed[r.key] ? '▶' : '▼' }}</button>
                                    </td>
                                    <td v-for="y in years" :key="y.year" class="py-1 text-right">{{ cell(r, y.year) === null ? '—' : yen(cell(r, y.year)) }}</td>
                                    <td class="py-1 text-right">{{ r.shares[shareYear] !== null && r.shares[shareYear] !== undefined ? `${r.shares[shareYear]}%` : '—' }}</td>
                                    <td class="py-1 text-right" :class="pctClass(r.yoy[shareYear])">{{ r.yoy[shareYear] !== null && r.yoy[shareYear] !== undefined ? pct(r.yoy[shareYear]) : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-[11px] text-gray-400">
                        営業第三部の「▶」を開くと、第一課・第二課・直轄の内訳が出ます。「営業第三部（全体）」は、課と直轄の合計です。
                        担当部門は年ごとの所属で集計しています（担当が変わった得意先は、その年の部門に計上）。「—」は、その期間に売上の登録がない年です（0円ではありません）。
                    </p>
                </div>
            </template>
        </div>
    </ReportShell>
</template>
