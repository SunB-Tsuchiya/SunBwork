<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import Chart from 'chart.js/auto';
import { useSalesChart } from '@/Composables/useSalesChart';

// 経費「支払先カルテ」（Phase24-3）。全部署・全科目・全年度を支払先（名寄せ後の名前）で横断して、
// 「どの支払先に、いつ、どれだけ払っているか」を見る。左: 支払先ランキング、右: 選んだ支払先のカルテ。
// 粗利計算はしない（売上とは別集計の参考情報）。

const props = defineProps({
    routePrefix: { type: String, required: true },
    departmentLabels: { type: Object, default: () => ({}) },
    enabledDepartmentKeys: { type: Array, default: () => [] },
    fiscalYear: { type: Number, required: true },
    // 選択中の支払先（正式名または別名）。親が保持してURLに同期する
    modelValue: { type: String, default: null },
});
const emit = defineEmits(['update:modelValue']);

const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;
const { pctClass, fiscalPeriodNumber, fiscalPeriodLabel } = useSalesChart();

const departmentTabs = computed(() => [
    { key: '', label: '全部署' },
    ...props.enabledDepartmentKeys.map((k) => ({ key: k, label: props.departmentLabels[k] ?? k })),
    { key: 'shared', label: '共通費' },
]);
const deptLabel = (k) => (k === 'shared' ? '共通費' : (props.departmentLabels[k] ?? k));
const deptColors = { planning: '#4F46E5', production: '#10B981', ondemand: '#F59E0B', shared: '#6B7280' };
const deptColor = (k) => deptColors[k] ?? '#9CA3AF';

const departmentFilter = ref('');
const q = ref('');
const ranking = ref(null);
const rankingLoading = ref(false);
const rankingError = ref('');

const detail = ref(null);
const detailLoading = ref(false);
const detailError = ref('');

const yen = (v) => (v === null || v === undefined ? '—' : `¥${Math.round(v).toLocaleString()}`);

let rankingSeq = 0;
const fetchRanking = async () => {
    const seq = ++rankingSeq;
    rankingLoading.value = true;
    rankingError.value = '';
    try {
        const response = await axios.get(route(rn('api.expense_vendors.ranking')), {
            params: {
                fiscal_year: props.fiscalYear,
                ...(departmentFilter.value ? { department_key: departmentFilter.value } : {}),
                ...(q.value.trim() ? { q: q.value.trim() } : {}),
            },
        });
        if (seq !== rankingSeq) return;
        ranking.value = response.data;
    } catch (e) {
        if (seq !== rankingSeq) return;
        ranking.value = null;
        rankingError.value = e.response?.data?.message ?? 'データの取得に失敗しました。';
    } finally {
        if (seq === rankingSeq) rankingLoading.value = false;
    }
};

// ---- チャート（canvasは常駐させ、読み込み中はv-showで隠す。経費比較の他グラフと同じ方式） ----
const yearChartRef = ref(null);
const monthChartRef = ref(null);
let yearChart = null;
let monthChart = null;
const monthLabels = ['4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月', '1月', '2月', '3月'];
const yenTicks = { callback: (v) => `¥${Number(v).toLocaleString()}` };

const renderCharts = () => {
    const d = detail.value;
    if (!d?.has_data) return;

    if (yearChartRef.value) {
        yearChart?.destroy();
        const deptKeys = [...new Set(d.years.flatMap((y) => Object.keys(y.by_department)))];
        yearChart = new Chart(yearChartRef.value.getContext('2d'), {
            type: 'bar',
            data: {
                labels: d.years.map((y) => `${fiscalPeriodNumber(y.fiscal_year)}期`),
                datasets: deptKeys.map((k) => ({
                    label: deptLabel(k),
                    data: d.years.map((y) => y.by_department[k] ?? 0),
                    backgroundColor: deptColor(k),
                })),
            },
            options: {
                responsive: true,
                plugins: { legend: { display: deptKeys.length > 1, position: 'bottom' } },
                scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: yenTicks } },
            },
        });
    }

    if (monthChartRef.value) {
        monthChart?.destroy();
        // 支払いのある年度のうち直近5年度を重ねる。基準年度は太い実線、他は細い点線
        const series = d.monthly.filter((s) => s.months.some((v) => v > 0)).slice(-5);
        const palette = ['#9CA3AF', '#F59E0B', '#10B981', '#EC4899', '#0EA5E9'];
        monthChart = new Chart(monthChartRef.value.getContext('2d'), {
            type: 'line',
            data: {
                labels: monthLabels,
                datasets: series.map((s, i) => {
                    const selected = s.fiscal_year === d.fiscal_year;
                    return {
                        label: `${fiscalPeriodNumber(s.fiscal_year)}期`,
                        data: s.months,
                        borderColor: selected ? '#4F46E5' : palette[i % palette.length],
                        backgroundColor: 'transparent',
                        borderWidth: selected ? 3 : 1.5,
                        borderDash: selected ? [] : [4, 4],
                        tension: 0.2,
                    };
                }),
            },
            options: {
                responsive: true,
                plugins: { legend: { display: true, position: 'bottom' } },
                scales: { y: { beginAtZero: true, ticks: yenTicks } },
            },
        });
    }
};

let detailSeq = 0;
const fetchDetail = async () => {
    if (!props.modelValue) {
        detail.value = null;
        return;
    }
    const seq = ++detailSeq;
    detailLoading.value = true;
    detailError.value = '';
    try {
        const response = await axios.get(route(rn('api.expense_vendors.detail')), {
            params: { fiscal_year: props.fiscalYear, vendor: props.modelValue },
        });
        if (seq !== detailSeq) return;
        detail.value = response.data;
    } catch (e) {
        if (seq !== detailSeq) return;
        detail.value = null;
        detailError.value = e.response?.data?.message ?? 'データの取得に失敗しました。';
    } finally {
        if (seq === detailSeq) detailLoading.value = false;
    }
    if (seq !== detailSeq) return;
    await nextTick(); // loading解除後に描画する（非表示のままだとchart.jsが0サイズで計測してしまう）
    renderCharts();
};

let searchTimer = null;
watch(q, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(fetchRanking, 300);
});
watch(departmentFilter, fetchRanking);
watch(() => props.fiscalYear, () => {
    fetchRanking();
    fetchDetail();
});
watch(() => props.modelValue, fetchDetail);

onMounted(() => {
    fetchRanking();
    fetchDetail();
});
onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    yearChart?.destroy();
    monthChart?.destroy();
});

const select = (vendorName) => emit('update:modelValue', vendorName);
const selectedYear = computed(() => detail.value?.years.find((y) => y.fiscal_year === props.fiscalYear) ?? null);
const currentRow = computed(() => ranking.value?.vendors.find((v) => v.vendor_name === detail.value?.vendor_name) ?? null);
const prevLabel = computed(() => fiscalPeriodLabel(props.fiscalYear - 1));
const diffText = (row) => {
    if (row.is_new) return '新規';
    if (row.diff_pct === null || row.diff_pct === undefined) return '—';
    return `${row.diff_pct > 0 ? '+' : ''}${row.diff_pct}%`;
};
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-5 lg:items-start">
        <!-- ランキング -->
        <div class="rounded bg-white p-4 shadow lg:col-span-2">
            <h3 class="mb-2 text-sm font-semibold text-gray-900">支払先ランキング（{{ fiscalPeriodLabel(fiscalYear) }}）</h3>

            <div class="mb-2 flex flex-wrap gap-1 rounded border bg-gray-50 p-1 text-xs">
                <button
                    v-for="t in departmentTabs"
                    :key="t.key"
                    type="button"
                    class="rounded px-2 py-1"
                    :class="departmentFilter === t.key ? 'bg-gray-700 text-white' : 'text-gray-600 hover:bg-gray-100'"
                    @click="departmentFilter = t.key"
                >{{ t.label }}</button>
            </div>
            <input
                v-model="q"
                type="search"
                placeholder="支払先名で検索（名寄せした別名も対象）"
                class="mb-2 w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />

            <p v-if="rankingLoading" class="text-xs text-gray-500">読み込み中...</p>
            <p v-else-if="rankingError" class="rounded bg-red-50 p-2 text-xs text-red-700">{{ rankingError }}</p>
            <p v-else-if="ranking && !ranking.has_data" class="text-xs text-gray-500">{{ fiscalPeriodLabel(fiscalYear) }}の経費データはありません。上の「基準年度」で、取り込み済みの別の年度を選んでください。</p>
            <p v-else-if="ranking && !ranking.vendors.length" class="text-xs text-gray-500">該当する支払先はありません。</p>
            <template v-else-if="ranking">
                <p class="mb-1 text-[11px] text-gray-500">
                    合計 {{ yen(ranking.total_amount) }}・{{ ranking.vendors.length }}社{{ ranking.vendors.length >= 300 ? '（上位300社まで）' : '' }}
                </p>
                <div class="max-h-[70vh] overflow-auto rounded border">
                    <table class="min-w-full text-xs tabular-nums">
                        <thead class="sticky top-0 bg-gray-100 text-gray-600">
                            <tr>
                                <th class="px-2 py-1.5 text-right">順位</th>
                                <th class="px-2 py-1.5 text-left">支払先</th>
                                <th class="px-2 py-1.5 text-right">金額</th>
                                <th class="px-2 py-1.5 text-right">構成比</th>
                                <th class="px-2 py-1.5 text-right" :title="`${prevLabel}との比較`">前年比</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="v in ranking.vendors"
                                :key="v.vendor_name"
                                class="cursor-pointer border-t hover:bg-indigo-50"
                                :class="detail?.vendor_name === v.vendor_name || modelValue === v.vendor_name ? 'bg-indigo-50' : ''"
                                @click="select(v.vendor_name)"
                            >
                                <td class="px-2 py-1 text-right text-gray-500">{{ v.rank ?? '—' }}</td>
                                <td class="px-2 py-1">
                                    <span class="block font-medium text-gray-800">{{ v.vendor_name }}</span>
                                    <span class="mt-0.5 flex flex-wrap gap-1">
                                        <span v-for="k in v.departments" :key="k" class="rounded px-1 text-[10px] text-white" :style="{ backgroundColor: deptColor(k) }">{{ deptLabel(k) }}</span>
                                        <span class="text-[10px] text-gray-400">{{ v.years_active }}年度</span>
                                    </span>
                                </td>
                                <td class="px-2 py-1 text-right">{{ yen(v.amount) }}</td>
                                <td class="px-2 py-1 text-right text-gray-500">{{ v.share_pct !== null ? `${v.share_pct}%` : '—' }}</td>
                                <td class="px-2 py-1 text-right" :class="v.is_new ? 'text-gray-600' : pctClass(v.diff_pct)">{{ diffText(v) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <!-- カルテ -->
        <div class="space-y-4 lg:col-span-3">
            <div v-if="!modelValue" class="rounded bg-white p-6 text-center text-sm text-gray-500 shadow">
                左のランキングから支払先を選ぶと、その支払先の年度推移・内訳・月別の動きが表示されます。
            </div>

            <template v-else>
                <p v-if="detailLoading" class="rounded bg-white p-4 text-xs text-gray-500 shadow">読み込み中...</p>
                <p v-else-if="detailError" class="rounded bg-red-50 p-3 text-xs text-red-700">{{ detailError }}</p>
                <p v-else-if="detail && !detail.has_data" class="rounded bg-white p-4 text-xs text-gray-500 shadow">「{{ modelValue }}」の支払いは見つかりませんでした。</p>

                <div v-show="!detailLoading && !detailError && detail?.has_data" class="space-y-4">
                    <div v-if="detail?.has_data" class="rounded bg-white p-4 shadow">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 class="text-base font-bold text-gray-900">{{ detail.vendor_name }}</h3>
                                <p v-if="detail.aliases.length" class="mt-0.5 text-[11px] text-gray-500">
                                    名寄せ済みの別名: {{ detail.aliases.join('、') }}
                                </p>
                            </div>
                            <button type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="select(null)">× 閉じる</button>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                            <div class="rounded bg-gray-50 p-2">
                                <p class="text-gray-500">{{ fiscalPeriodLabel(fiscalYear) }}の支払額</p>
                                <p class="text-sm font-bold text-gray-900">{{ yen(selectedYear?.total_amount ?? 0) }}</p>
                                <p v-if="currentRow" class="text-[11px]" :class="currentRow.is_new ? 'text-gray-600' : pctClass(currentRow.diff_pct)">前年比 {{ diffText(currentRow) }}</p>
                                <p v-else-if="selectedYear?.diff_pct !== null && selectedYear" class="text-[11px]" :class="pctClass(selectedYear.diff_pct)">前年比 {{ selectedYear.diff_pct > 0 ? '+' : '' }}{{ selectedYear.diff_pct }}%</p>
                            </div>
                            <div class="rounded bg-gray-50 p-2">
                                <p class="text-gray-500">全支払先に占める割合</p>
                                <p class="text-sm font-bold text-gray-900">{{ selectedYear?.share_pct !== null && selectedYear ? `${selectedYear.share_pct}%` : '—' }}</p>
                                <p class="text-[11px] text-gray-400">{{ fiscalPeriodLabel(fiscalYear) }}</p>
                            </div>
                            <div class="rounded bg-gray-50 p-2">
                                <p class="text-gray-500">累計（全取込年度）</p>
                                <p class="text-sm font-bold text-gray-900">{{ yen(detail.cumulative_total) }}</p>
                                <p class="text-[11px] text-gray-400">{{ detail.years_active }}年度</p>
                            </div>
                            <div class="rounded bg-gray-50 p-2">
                                <p class="text-gray-500">取引開始（取込の範囲）</p>
                                <p class="text-sm font-bold text-gray-900">{{ detail.first_fiscal_year ? fiscalPeriodLabel(detail.first_fiscal_year) : '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded bg-white p-4 shadow">
                        <h4 class="mb-2 text-sm font-semibold text-gray-900">年度ごとの支払総額</h4>
                        <canvas ref="yearChartRef" height="110"></canvas>
                        <table v-if="detail?.has_data" class="mt-3 min-w-full text-xs tabular-nums">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">会計年度</th>
                                    <th class="py-1 text-right">支払額</th>
                                    <th class="py-1 text-right">全支払先に占める割合</th>
                                    <th class="py-1 text-right">前年比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="y in detail.years"
                                    :key="y.fiscal_year"
                                    class="border-t"
                                    :class="y.fiscal_year === fiscalYear ? 'bg-indigo-50 font-semibold' : ''"
                                >
                                    <td class="py-1">{{ fiscalPeriodLabel(y.fiscal_year) }}</td>
                                    <td class="py-1 text-right">{{ y.total_amount > 0 ? yen(y.total_amount) : '—' }}</td>
                                    <td class="py-1 text-right">{{ y.total_amount > 0 && y.share_pct !== null ? `${y.share_pct}%` : '—' }}</td>
                                    <td class="py-1 text-right" :class="pctClass(y.diff_pct)">{{ y.diff_pct !== null ? `${y.diff_pct > 0 ? '+' : ''}${y.diff_pct}%` : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded bg-white p-4 shadow">
                        <h4 class="mb-2 text-sm font-semibold text-gray-900">{{ fiscalPeriodLabel(fiscalYear) }}の内訳（部署×科目）</h4>
                        <p v-if="detail?.has_data && !detail.breakdown.length" class="text-xs text-gray-500">この年度の支払いはありません。</p>
                        <table v-else-if="detail?.has_data" class="min-w-full text-xs tabular-nums">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">部署</th>
                                    <th class="py-1">科目</th>
                                    <th class="py-1 text-right">金額</th>
                                    <th class="py-1 text-right">構成比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(b, i) in detail.breakdown" :key="i" class="border-t">
                                    <td class="py-1"><span class="rounded px-1 text-[10px] text-white" :style="{ backgroundColor: deptColor(b.department_key) }">{{ deptLabel(b.department_key) }}</span></td>
                                    <td class="py-1">{{ b.category }}</td>
                                    <td class="py-1 text-right">{{ yen(b.amount) }}</td>
                                    <td class="py-1 text-right text-gray-500">{{ b.share_pct !== null ? `${b.share_pct}%` : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded bg-white p-4 shadow">
                        <h4 class="mb-2 text-sm font-semibold text-gray-900">月別の動き（直近5年度を重ねて表示）</h4>
                        <canvas ref="monthChartRef" height="110"></canvas>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
