<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import Chart from 'chart.js/auto';
import { useSalesChart } from '@/Composables/useSalesChart';

// 経費「部署比較」（Phase24-4）。企画・制作・オンデマンド・共通費の年間合計と構成比、月別の推移（積み上げ）、
// 科目×部署のクロス表を1画面で見比べる。比較年度（期の途中は同期間）との増減も出す。粗利計算はしない。

const props = defineProps({
    data: { type: Object, required: true },
    departmentLabels: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['open-sheet']);

const { pctClass, fiscalPeriodLabel } = useSalesChart();

const deptLabel = (k) => (k === 'shared' ? '共通費' : (props.departmentLabels[k] ?? k));
const deptColors = { planning: '#4F46E5', production: '#10B981', ondemand: '#F59E0B', shared: '#6B7280' };
const deptColor = (k) => deptColors[k] ?? '#9CA3AF';
const yen = (v) => (v === null || v === undefined ? '—' : `¥${Math.round(v).toLocaleString()}`);
const prevLabel = computed(() => fiscalPeriodLabel(props.data.previous_fiscal_year));
const signedYen = (v) => (v === null ? '—' : `${v > 0 ? '+' : v < 0 ? '−' : ''}¥${Math.abs(Math.round(v)).toLocaleString()}`);
const pctText = (v) => (v === null || v === undefined ? '—' : `${v > 0 ? '+' : ''}${v}%`);

const monthLabels = computed(() => props.data.months.map((m) => `${m.calendar_month}月`));
const maxCross = computed(() => Math.max(1, ...props.data.cross.flatMap((c) => Object.values(c.by_department))));
const heat = (v) => (v > 0 ? { backgroundColor: `rgba(79, 70, 229, ${(0.05 + 0.4 * Math.sqrt(v / maxCross.value)).toFixed(3)})` } : {});
const shownDepartments = computed(() => props.data.departments.filter((d) => d.has_data));

// ---- 月別推移（部署の積み上げ棒＋比較年度の合計の点線） ----
const chartRef = ref(null);
let chart = null;
const renderChart = () => {
    if (!chartRef.value) return;
    chart?.destroy();
    const datasets = shownDepartments.value.map((d) => ({
        type: 'bar',
        label: deptLabel(d.department_key),
        data: d.month_totals,
        backgroundColor: deptColor(d.department_key),
        stack: 'dept',
    }));
    if (props.data.previous_has_data) {
        datasets.push({
            type: 'line',
            label: `${prevLabel.value}（合計）`,
            data: props.data.previous_month_totals,
            borderColor: '#111827',
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [5, 4],
            pointRadius: 2,
            tension: 0.2,
        });
    }
    chart = new Chart(chartRef.value.getContext('2d'), {
        type: 'bar',
        data: { labels: monthLabels.value, datasets },
        options: {
            responsive: true,
            plugins: { legend: { display: true, position: 'bottom' } },
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true, ticks: { callback: (v) => `¥${Number(v).toLocaleString()}` } },
            },
        },
    });
};
watch(() => props.data, async () => {
    await nextTick();
    renderChart();
}, { immediate: true, flush: 'post' });
onBeforeUnmount(() => chart?.destroy());
</script>

<template>
    <div class="space-y-4">
        <div class="rounded bg-white p-4 shadow">
            <h3 class="mb-2 text-sm font-semibold text-gray-900">部署別の合計（{{ fiscalPeriodLabel(data.fiscal_year) }}）</h3>
            <table class="min-w-full text-xs tabular-nums">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="py-1">部署</th>
                        <th class="py-1 text-right">金額</th>
                        <th class="w-40 py-1 pl-3">構成比</th>
                        <th class="py-1 text-right">{{ data.previous_has_data ? prevLabel : '比較年度' }}</th>
                        <th class="py-1 text-right">増減額</th>
                        <th class="py-1 text-right">増減率</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="d in data.departments"
                        :key="d.department_key"
                        class="border-t"
                        :class="d.has_data ? 'cursor-pointer hover:bg-indigo-50' : 'text-gray-400'"
                        :title="d.has_data ? 'この部署の部署シートを開く' : ''"
                        @click="d.has_data && emit('open-sheet', { department: d.department_key })"
                    >
                        <td class="py-1.5">
                            <span class="mr-1 inline-block h-2.5 w-2.5 rounded-sm align-middle" :style="{ backgroundColor: deptColor(d.department_key) }"></span>{{ deptLabel(d.department_key) }}
                        </td>
                        <td class="py-1.5 text-right font-medium">{{ d.has_data ? yen(d.total_amount) : '—' }}</td>
                        <td class="py-1.5 pl-3">
                            <span v-if="d.share_pct !== null && d.has_data" class="flex items-center gap-2">
                                <span class="h-2 flex-1 rounded bg-gray-100"><span class="block h-2 rounded" :style="{ width: `${d.share_pct}%`, backgroundColor: deptColor(d.department_key) }"></span></span>
                                <span class="w-10 text-right text-gray-600">{{ d.share_pct }}%</span>
                            </span>
                        </td>
                        <td class="py-1.5 text-right text-gray-500">{{ d.previous_total_amount !== null && d.has_data ? yen(d.previous_total_amount) : '—' }}</td>
                        <td class="py-1.5 text-right" :class="pctClass(d.diff)">{{ d.has_data ? signedYen(d.diff) : '—' }}</td>
                        <td class="py-1.5 text-right" :class="pctClass(d.diff_pct)">{{ d.has_data ? pctText(d.diff_pct) : '—' }}</td>
                    </tr>
                    <tr class="border-t-2 font-semibold">
                        <td class="py-1.5">合計</td>
                        <td class="py-1.5 text-right">{{ yen(data.total_amount) }}</td>
                        <td></td>
                        <td class="py-1.5 text-right text-gray-500">{{ data.previous_total_amount !== null ? yen(data.previous_total_amount) : '—' }}</td>
                        <td class="py-1.5 text-right" :class="pctClass(data.previous_total_amount !== null ? data.total_amount - data.previous_total_amount : null)">
                            {{ data.previous_total_amount !== null ? signedYen(data.total_amount - data.previous_total_amount) : '—' }}
                        </td>
                        <td class="py-1.5 text-right" :class="pctClass(data.diff_pct)">{{ pctText(data.diff_pct) }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!data.previous_has_data" class="mt-2 text-[11px] text-gray-400">{{ fiscalPeriodLabel(data.previous_fiscal_year) }}のデータが無いため、比較は表示できません。</p>
            <p v-else class="mt-2 text-[11px] text-gray-400">部署の行をクリックすると、その部署の部署シートを開きます。共通費はサンエー印刷との折半を含みます（記載額のまま）。</p>
        </div>

        <div class="rounded bg-white p-4 shadow">
            <h3 class="mb-2 text-sm font-semibold text-gray-900">月別の推移（部署の積み上げ）</h3>
            <canvas ref="chartRef" height="110"></canvas>
        </div>

        <div class="rounded bg-white p-4 shadow">
            <h3 class="mb-2 text-sm font-semibold text-gray-900">科目×部署</h3>
            <div class="max-h-[60vh] overflow-auto rounded border">
                <table class="min-w-full border-separate border-spacing-0 text-xs tabular-nums">
                    <thead>
                        <tr class="text-gray-600">
                            <th class="sticky left-0 top-0 z-30 min-w-[160px] border-b bg-gray-100 px-2 py-1.5 text-left">科目</th>
                            <th v-for="d in shownDepartments" :key="d.department_key" class="sticky top-0 z-20 min-w-[96px] border-b bg-gray-100 px-2 py-1.5 text-right">{{ deptLabel(d.department_key) }}</th>
                            <th class="sticky top-0 z-20 min-w-[104px] border-b border-l bg-gray-100 px-2 py-1.5 text-right">合計</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in data.cross" :key="c.category">
                            <td class="sticky left-0 z-10 border-b bg-white px-2 py-1">{{ c.category }}</td>
                            <td
                                v-for="d in shownDepartments"
                                :key="d.department_key"
                                class="border-b px-2 py-1 text-right"
                                :class="c.by_department[d.department_key] ? 'cursor-pointer hover:ring-1 hover:ring-inset hover:ring-indigo-400' : 'text-gray-300'"
                                :style="heat(c.by_department[d.department_key] ?? 0)"
                                @click="c.by_department[d.department_key] && emit('open-sheet', { department: d.department_key, category: c.category })"
                            >{{ c.by_department[d.department_key] ? Math.round(c.by_department[d.department_key]).toLocaleString() : '—' }}</td>
                            <td class="border-b border-l px-2 py-1 text-right font-semibold">{{ Math.round(c.total_amount).toLocaleString() }}</td>
                        </tr>
                        <tr v-if="!data.cross.length">
                            <td :colspan="shownDepartments.length + 2" class="px-2 py-3 text-gray-400">この年度の経費データはありません。</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-[11px] text-gray-400">金額のセルをクリックすると、その部署の部署シートで、その科目の行を開きます。科目名は名寄せ設定を反映しています。</p>
        </div>
    </div>
</template>
