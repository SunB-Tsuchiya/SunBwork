<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import SalesAnalysisShell from '@/Components/SalesAnalysis/SalesAnalysisShell.vue';
import axios from 'axios';
import Chart from 'chart.js/auto';
import { useSalesChart } from '@/Composables/useSalesChart';

// 経費比較画面（Phase22）。期別分析（Phase21）の経費セクションだけでは分からなかった
// 「科目の複数年推移」「支払先（外注先）への依存度」を見るための画面。粗利計算はしない。
// 経費データの出どころが会計年度単位のため暦年（年次分析）への展開はしていない
// （PLAN1.md Phase22節、2026-10-09ユーザー確認済み）。

const props = defineProps({
    routePrefix: { type: String, required: true },
    departmentLabels: { type: Object, default: () => ({}) },
    enabledDepartmentKeys: { type: Array, default: () => [] },
    hasCompanySelected: { type: Boolean, default: true },
    supportsExpenses: { type: Boolean, default: false },
});

const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;
const { yen, pctClass, fiscalPeriodLabel } = useSalesChart();

const departmentTabs = computed(() => [
    ...props.enabledDepartmentKeys.map((k) => ({ key: k, label: props.departmentLabels[k] ?? k })),
    { key: 'shared', label: '共通費' },
]);
const departmentKey = ref(props.enabledDepartmentKeys[0] ?? 'planning');

const metaLoading = ref(false);
const metaError = ref('');
const fiscalYears = ref([]);
const categories = ref([]);
const selectedCategory = ref('');
const vendorFiscalYear = ref(null);

const trendLoading = ref(false);
const trendData = ref(null);
const trendError = ref('');
const vendorLoading = ref(false);
const vendorData = ref(null);
const vendorError = ref('');

const fetchMeta = async () => {
    if (!props.supportsExpenses) return;

    metaLoading.value = true;
    metaError.value = '';
    trendData.value = null;
    vendorData.value = null;
    try {
        const response = await axios.get(route(rn('api.expense_comparison.meta')), {
            params: { department_key: departmentKey.value },
        });
        fiscalYears.value = response.data.fiscal_years;
        categories.value = response.data.categories;
        selectedCategory.value = categories.value[0] ?? '';
        vendorFiscalYear.value = fiscalYears.value[fiscalYears.value.length - 1] ?? null;
    } catch (e) {
        metaError.value = 'データの取得に失敗しました。';
        fiscalYears.value = [];
        categories.value = [];
    } finally {
        metaLoading.value = false;
    }
};

const trendChartRef = ref(null);
let trendChartInstance = null;

const renderTrendChart = () => {
    if (!trendChartRef.value || !trendData.value?.years?.length) return;
    if (trendChartInstance) trendChartInstance.destroy();

    trendChartInstance = new Chart(trendChartRef.value.getContext('2d'), {
        type: 'bar',
        data: {
            labels: trendData.value.years.map((y) => fiscalPeriodLabel(y.fiscal_year)),
            datasets: [{
                label: selectedCategory.value,
                data: trendData.value.years.map((y) => y.amount),
                backgroundColor: '#4F46E5',
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { callback: (v) => `¥${Number(v).toLocaleString()}` } } },
        },
    });
};

// 科目によって支払先数が大きく異なり（外注加工費は数十社、他は数社程度）、
// レスポンスの到着順が送信順と入れ替わることがある。古いリクエストの応答が後から届いて
// 新しい選択を上書きしてしまわないよう、リクエストごとに連番を振り最新のものだけ反映する
let trendRequestSeq = 0;

const fetchTrend = async () => {
    if (!props.supportsExpenses || !selectedCategory.value) return;

    const requestSeq = ++trendRequestSeq;
    trendLoading.value = true;
    trendError.value = '';
    try {
        const response = await axios.get(route(rn('api.expense_comparison.trend')), {
            params: { department_key: departmentKey.value, category: selectedCategory.value },
        });
        if (requestSeq !== trendRequestSeq) return; // より新しいリクエストが既に発行されている
        trendData.value = response.data;
    } catch (e) {
        if (requestSeq !== trendRequestSeq) return;
        trendData.value = null;
        trendError.value = e.response?.data?.message ?? 'データの取得に失敗しました。';
    } finally {
        if (requestSeq === trendRequestSeq) trendLoading.value = false;
    }
    if (requestSeq !== trendRequestSeq) return;
    // trendLoadingをfalseにした後でcanvasを描画する（v-showの表示判定がtrendLoadingに
    // 依存しており、非表示（display:none）のままchart.jsに描画させると0サイズで
    // 計測されてしまい、2回目以降の科目切替でグラフが出なくなる不具合があったため）
    await nextTick();
    renderTrendChart();
};

const vendorChartRef = ref(null);
let vendorChartInstance = null;

const renderVendorChart = () => {
    if (!vendorChartRef.value || !vendorData.value?.vendors?.length) return;
    if (vendorChartInstance) vendorChartInstance.destroy();

    vendorChartInstance = new Chart(vendorChartRef.value.getContext('2d'), {
        type: 'bar',
        data: {
            labels: vendorData.value.vendors.map((v) => v.vendor_name),
            datasets: [{
                label: '金額',
                data: vendorData.value.vendors.map((v) => v.amount),
                backgroundColor: '#0EA5E9',
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            // 親（内側ラッパー）のheightをそのまま使う。trueのままだとchart.js既定のアスペクト比を
            // 優先してしまい、親が縦長でも横幅基準で縮んだ高さに描画してしまう
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { callback: (v) => `¥${Number(v).toLocaleString()}` } } },
        },
    });
};

// trendと同じ理由（支払先数が多い科目ほどレスポンスが遅くなり得る）で連番ガードを設ける
let vendorRequestSeq = 0;

const fetchVendors = async () => {
    if (!props.supportsExpenses || !selectedCategory.value || !vendorFiscalYear.value) return;

    const requestSeq = ++vendorRequestSeq;
    vendorLoading.value = true;
    vendorError.value = '';
    try {
        const response = await axios.get(route(rn('api.expense_comparison.vendors')), {
            params: { department_key: departmentKey.value, category: selectedCategory.value, fiscal_year: vendorFiscalYear.value },
        });
        if (requestSeq !== vendorRequestSeq) return;
        vendorData.value = response.data;
    } catch (e) {
        if (requestSeq !== vendorRequestSeq) return;
        vendorData.value = null;
        vendorError.value = e.response?.data?.message ?? 'データの取得に失敗しました。';
    } finally {
        if (requestSeq === vendorRequestSeq) vendorLoading.value = false;
    }
    if (requestSeq !== vendorRequestSeq) return;
    // trendと同じ理由でloading解除後に描画する
    await nextTick();
    renderVendorChart();
};

watch(departmentKey, fetchMeta);
watch(selectedCategory, () => {
    fetchTrend();
    fetchVendors();
});
watch(vendorFiscalYear, fetchVendors);

onMounted(() => {
    if (props.supportsExpenses) fetchMeta();
});
</script>

<template>
    <SalesAnalysisShell title="経費比較" :route-prefix="routePrefix" active="expense_comparison">
        <div v-if="!hasCompanySelected" class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">会社が選択されていません。画面右上の会社切替から対象の会社を選択してください。</p>
        </div>

        <div v-else-if="!supportsExpenses" class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">この会社では経費比較機能は利用できません。</p>
        </div>

        <div v-else class="space-y-4">
            <div class="rounded bg-white p-4 shadow">
                <p class="mb-2 text-xs text-gray-500">売上とは別集計の経費データです。粗利計算は行っていません。科目ごとの複数年推移・支払先への依存度を参考情報として表示します。</p>

                <div class="mb-3 flex flex-wrap gap-1 rounded border bg-gray-50 p-1 text-xs">
                    <button
                        v-for="tab in departmentTabs"
                        :key="tab.key"
                        type="button"
                        class="rounded px-2 py-1"
                        :class="departmentKey === tab.key ? 'bg-gray-700 text-white' : 'text-gray-600 hover:bg-gray-100'"
                        @click="departmentKey = tab.key"
                    >{{ tab.label }}</button>
                </div>

                <p v-if="metaLoading" class="text-xs text-gray-500">読み込み中...</p>
                <p v-else-if="metaError" class="rounded bg-red-50 p-2 text-xs text-red-700">{{ metaError }}</p>
                <p v-else-if="!categories.length" class="text-xs text-gray-500">この部署の経費データはまだ取り込まれていません。</p>
                <div v-else>
                    <label class="mb-1 block text-xs font-medium text-gray-700">科目</label>
                    <div class="flex flex-wrap gap-1">
                        <button
                            v-for="c in categories"
                            :key="c"
                            type="button"
                            class="rounded px-2 py-1 text-xs"
                            :class="selectedCategory === c ? 'bg-indigo-600 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-100'"
                            @click="selectedCategory = c"
                        >{{ c }}</button>
                    </div>
                </div>
            </div>

            <!-- 画面全体を使えるようになったため、複数年推移と支払先比較を横に並べて見比べられるようにしている（lg以上） -->
            <div v-if="selectedCategory" class="grid gap-4 lg:grid-cols-2 lg:items-start">
                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900">複数年推移（{{ selectedCategory }}）</h3>
                    <p v-if="trendLoading" class="text-xs text-gray-500">読み込み中...</p>
                    <p v-else-if="trendError" class="rounded bg-red-50 p-2 text-xs text-red-700">{{ trendError }}</p>
                    <!--
                        canvasはloading/dataの切替で毎回マウント・アンマウントせず常時マウントしたままにする
                        （科目切替のたびにcanvasを作り直すと、chart.jsの描画が初回しか反映されない不具合が
                        あったため、既存の他画面（期別分析の複数年推移グラフ等）と同じ「canvasは常駐させ
                        v-showで隠すだけ」の方式に統一した。2026-10-09）
                    -->
                    <canvas v-show="!trendLoading && !trendError && trendData" ref="trendChartRef" height="90"></canvas>
                    <table v-if="!trendLoading && trendData" class="mt-4 min-w-full text-xs">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-1">会計年度</th>
                                <th class="py-1 text-right">金額</th>
                                <th class="py-1 text-right">部署総経費に占める割合</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="y in trendData.years" :key="y.fiscal_year" class="border-t">
                                <td class="py-1">
                                    {{ fiscalPeriodLabel(y.fiscal_year) }}
                                    <span v-if="y.is_shared_split" class="ml-1 rounded bg-purple-100 px-1 text-[10px] font-semibold text-purple-700">折半</span>
                                </td>
                                <td class="py-1 text-right">{{ yen(y.amount) }}</td>
                                <td class="py-1 text-right">{{ y.share_pct !== null ? `${y.share_pct}%` : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="rounded bg-white p-4 shadow">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">支払先別の依存度（{{ selectedCategory }}）</h3>
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-700">会計年度</label>
                            <select v-model.number="vendorFiscalYear" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="fy in fiscalYears" :key="fy" :value="fy">{{ fiscalPeriodLabel(fy) }}</option>
                            </select>
                        </div>
                    </div>

                    <p v-if="vendorLoading" class="text-xs text-gray-500">読み込み中...</p>
                    <p v-else-if="vendorError" class="rounded bg-red-50 p-2 text-xs text-red-700">{{ vendorError }}</p>
                    <p v-else-if="vendorData && !vendorData.has_data" class="text-xs text-gray-500">この会計年度・科目のデータはありません。</p>
                    <!--
                        canvasは常時マウントしたままにする（複数年推移グラフと同じ理由。2026-10-09）。
                        支払先が多い科目（外注加工費等は数十社になる）では、外側の表示枠の高さを常に
                        5件分程度で固定し、内側のラッパーに全件分の高さを持たせてchart.jsにそちら基準で
                        描画させる（canvasのheight属性だけを増減させる方式だと、chart.jsのresponsive設定が
                        外側の表示枠サイズに合わせて棒を圧縮してしまい、支払先が多い科目だけ棒とラベルが
                        ずれて見える不具合があったため、ラッパーを二重にして解消した。2026-10-09）
                    -->
                    <div v-show="!vendorLoading && !vendorError && vendorData?.has_data" class="max-h-44 overflow-y-auto">
                        <div :style="{ height: `${vendorData?.vendors?.length ? Math.max(60, vendorData.vendors.length * 24) : 60}px` }">
                            <canvas ref="vendorChartRef"></canvas>
                        </div>
                    </div>
                    <template v-if="!vendorLoading && vendorData?.has_data">
                        <table class="mt-4 min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">支払先（会社名）</th>
                                    <th class="py-1 text-right">{{ fiscalPeriodLabel(vendorFiscalYear) }}</th>
                                    <th class="py-1 text-right">構成比</th>
                                    <th class="py-1 text-right">{{ fiscalPeriodLabel(vendorData.previous_fiscal_year) }}</th>
                                    <th class="py-1 text-right">増減</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="v in vendorData.vendors" :key="v.vendor_name" class="border-t">
                                    <td class="py-1">{{ v.vendor_name }}</td>
                                    <td class="py-1 text-right">{{ yen(v.amount) }}</td>
                                    <td class="py-1 text-right">{{ v.share_pct !== null ? `${v.share_pct}%` : '—' }}</td>
                                    <td class="py-1 text-right text-gray-500">{{ v.previous_amount !== null ? yen(v.previous_amount) : '新規' }}</td>
                                    <td class="py-1 text-right" :class="pctClass(v.diff_pct)">
                                        {{ v.diff !== null ? `${v.diff > 0 ? '+' : ''}${yen(v.diff)}` : '—' }}
                                        <span v-if="v.diff_pct !== null">（{{ v.diff_pct > 0 ? '+' : '' }}{{ v.diff_pct }}%）</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="!vendorData.previous_has_data" class="mt-2 text-[11px] text-gray-400">{{ fiscalPeriodLabel(vendorData.previous_fiscal_year) }}のデータが無いため、前年比較はできません。</p>
                    </template>
                </div>
            </div>
        </div>
    </SalesAnalysisShell>
</template>
