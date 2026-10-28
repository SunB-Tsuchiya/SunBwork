<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import SalesAnalysisShell from '@/Components/SalesAnalysis/SalesAnalysisShell.vue';
import ExpenseSheetTable from '@/Components/SalesAnalysis/ExpenseSheetTable.vue';
import ExpenseHighlights from '@/Components/SalesAnalysis/ExpenseHighlights.vue';
import ExpenseVendors from '@/Components/SalesAnalysis/ExpenseVendors.vue';
import { Link } from '@inertiajs/vue3';
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
const initialDepartmentKey = new URLSearchParams(window.location.search).get('department_key');
const departmentKey = ref(
    departmentTabs.value.some((t) => t.key === initialDepartmentKey) ? initialDepartmentKey : (props.enabledDepartmentKeys[0] ?? 'planning'),
);

const metaLoading = ref(false);
const metaError = ref('');
const fiscalYears = ref([]);
const categories = ref([]);
const selectedCategory = ref('');
// 基準年度（支払先別の依存度の対象年度。複数年推移では強調表示する）。取込済みの年度の間だけ移動できる
const vendorFiscalYear = ref(null);
// ユーザーが基準年度を操作した後は、データのある最新年度への自動寄せをしない
let baseYearTouched = false;
const urlParams = new URLSearchParams(window.location.search);

// 表示切替: 科目別比較（compare）／部署シート（sheet。Excelの部署シートを1画面で再現。Phase23）／支払先カルテ（vendors。Phase24-3）
const viewMode = ref(['sheet', 'vendors'].includes(urlParams.get('view')) ? urlParams.get('view') : 'compare');
// 支払先カルテで選んでいる支払先（正式名または別名）。部署シートの支払先名のクリックからも設定される
const selectedVendor = ref(urlParams.get('vendor') || null);
const openVendor = (name) => {
    selectedVendor.value = name;
    viewMode.value = 'vendors';
};
const sheetLoading = ref(false);
const sheetData = ref(null);
const sheetError = ref('');
// 部署シートの表（注目ポイントや科目別比較から、特定の科目・支払先の行へ移動するために参照する）
const sheetTableRef = ref(null);
// 科目別比較から「部署シートで見る」で遷移した直後、シートの読み込みが終わってから移動するための保留
const pendingFocus = ref(null);
const focusSheetRow = ({ category, vendor = null }) => sheetTableRef.value?.focusRow(category, vendor);
const openInSheet = (category) => {
    pendingFocus.value = { category };
    viewMode.value = 'sheet';
};
// 比較年度（null=基準年度の前年度）と比較期間（null=自動: 期の途中なら同期間、通年なら通年）。Phase24-1
const compareYear = ref(null);
const comparePeriod = ref(null);

const trendLoading = ref(false);
const trendData = ref(null);
const trendError = ref('');
const vendorLoading = ref(false);
const vendorData = ref(null);
const vendorError = ref('');

const baseYearIndex = computed(() => fiscalYears.value.indexOf(vendorFiscalYear.value));
const canGoPrevYear = computed(() => baseYearIndex.value > 0);
const canGoNextYear = computed(() => baseYearIndex.value >= 0 && baseYearIndex.value < fiscalYears.value.length - 1);
// 比較年度の選択肢（基準年度以外の取込済み年度）
const compareYearOptions = computed(() => fiscalYears.value.filter((fy) => fy !== vendorFiscalYear.value));
// 「◯月まで実績」表示用の月名（最終実績月）
const actualMonthLabel = computed(() => {
    const d = sheetData.value;
    return d && d.actual_months > 0 ? `${d.months[d.actual_months - 1].calendar_month}月` : '';
});
const setBaseYear = (fy) => {
    baseYearTouched = true;
    vendorFiscalYear.value = fy;
};
const goPrevYear = () => { if (canGoPrevYear.value) setBaseYear(fiscalYears.value[baseYearIndex.value - 1]); };
const goNextYear = () => { if (canGoNextYear.value) setBaseYear(fiscalYears.value[baseYearIndex.value + 1]); };
const goLatestYear = () => setBaseYear(fiscalYears.value[fiscalYears.value.length - 1] ?? null);

// 選択中の部署・科目で金額のある会計年度（基準年度にデータが無いときの案内用）
const yearsWithData = computed(() => (trendData.value?.years ?? []).filter((y) => y.amount > 0).map((y) => y.fiscal_year));

const syncQueryString = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('department_key', departmentKey.value);
    url.searchParams.set('view', viewMode.value);
    if (viewMode.value === 'vendors' && selectedVendor.value) url.searchParams.set('vendor', selectedVendor.value);
    else url.searchParams.delete('vendor');
    if (selectedCategory.value) url.searchParams.set('category', selectedCategory.value);
    if (vendorFiscalYear.value) url.searchParams.set('fiscal_year', String(vendorFiscalYear.value));
    window.history.replaceState(null, '', url);
};

let initialQueryApplied = false;

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
        // 初回のみURLクエリの科目・年度を復元する（部署切替後は先頭の科目・最新年度から始める）
        const qCategory = initialQueryApplied ? null : urlParams.get('category');
        const qYear = initialQueryApplied ? NaN : Number(urlParams.get('fiscal_year'));
        selectedCategory.value = categories.value.includes(qCategory) ? qCategory : (categories.value[0] ?? '');
        baseYearTouched = fiscalYears.value.includes(qYear);
        vendorFiscalYear.value = baseYearTouched ? qYear : (fiscalYears.value[fiscalYears.value.length - 1] ?? null);
        initialQueryApplied = true;
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
                backgroundColor: trendData.value.years.map((y) => (y.fiscal_year === vendorFiscalYear.value ? '#4F46E5' : '#C7D2FE')),
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
        // 基準年度を触っていなければ、この部署・科目でデータのある最新年度に寄せる
        if (!baseYearTouched) {
            const withData = response.data.years.filter((y) => y.amount > 0).map((y) => y.fiscal_year);
            if (withData.length) vendorFiscalYear.value = withData[withData.length - 1];
        }
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

// 支払先別グラフの棒1本あたりの太さ・行の高さ・軸ラベル分の余白（px）。件数が変わっても1本の見た目は一定にする
const VENDOR_BAR_THICKNESS = 18;
const VENDOR_ROW_HEIGHT = 34;
const VENDOR_AXIS_PADDING = 44;
const vendorChartHeight = computed(() => (vendorData.value?.vendors?.length ?? 0) * VENDOR_ROW_HEIGHT + VENDOR_AXIS_PADDING);

// 部署切替・年度切替が続いたとき、古い応答が新しい表示を上書きしないよう連番ガードを設ける
let sheetRequestSeq = 0;

const fetchSheet = async () => {
    if (!props.supportsExpenses || viewMode.value !== 'sheet' || !vendorFiscalYear.value) return;

    const requestSeq = ++sheetRequestSeq;
    sheetLoading.value = true;
    sheetError.value = '';
    try {
        const response = await axios.get(route(rn('api.expense_comparison.sheet')), {
            params: {
                department_key: departmentKey.value,
                fiscal_year: vendorFiscalYear.value,
                ...(compareYear.value ? { compare_fiscal_year: compareYear.value } : {}),
                ...(comparePeriod.value ? { compare_period: comparePeriod.value } : {}),
            },
        });
        if (requestSeq !== sheetRequestSeq) return;
        sheetData.value = response.data;
    } catch (e) {
        if (requestSeq !== sheetRequestSeq) return;
        sheetData.value = null;
        sheetError.value = e.response?.data?.message ?? 'データの取得に失敗しました。';
    } finally {
        if (requestSeq === sheetRequestSeq) sheetLoading.value = false;
    }
    if (requestSeq !== sheetRequestSeq || !pendingFocus.value || !sheetData.value?.has_data) return;
    const target = pendingFocus.value;
    pendingFocus.value = null;
    await nextTick();
    focusSheetRow(target);
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
                // 支払先の数に関わらず棒の太さを固定する（少ないと太く、多いと細くなるのを防ぐ）
                barThickness: VENDOR_BAR_THICKNESS,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            // 親（内側ラッパー）のheightをそのまま使う。trueのままだとchart.js既定のアスペクト比を
            // 優先してしまい、親が縦長でも横幅基準で縮んだ高さに描画してしまう
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { font: { size: 10 }, callback: (v) => `¥${Number(v).toLocaleString()}` } },
                // 支払先名は件数が少なくても多くても省略せず全件表示する
                y: { ticks: { font: { size: 10 }, autoSkip: false } },
            },
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
watch(vendorFiscalYear, () => {
    // 基準年度が変わったら、比較年度・比較期間は既定（前年度・自動）に戻す
    compareYear.value = null;
    comparePeriod.value = null;
    fetchVendors();
    renderTrendChart();
    fetchSheet();
    syncQueryString();
});
watch([compareYear, comparePeriod], fetchSheet);
watch([departmentKey, selectedCategory, viewMode, selectedVendor], syncQueryString);
// 部署切替時はfetchMeta内で年度が再設定されるまで古い表示を残さない
watch(departmentKey, () => { sheetData.value = null; });
watch([departmentKey, viewMode], fetchSheet);
// 科目別比較へ戻ったとき、非表示中に更新できなかったグラフを描き直す
watch(viewMode, async (mode) => {
    if (mode !== 'compare') return;
    await nextTick();
    renderTrendChart();
    renderVendorChart();
});

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

                <div class="mb-3 flex gap-1 border-b text-sm">
                    <button
                        type="button"
                        class="-mb-px border-b-2 px-3 py-1.5"
                        :class="viewMode === 'compare' ? 'border-indigo-600 font-semibold text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        @click="viewMode = 'compare'"
                    >科目別比較</button>
                    <button
                        type="button"
                        class="-mb-px border-b-2 px-3 py-1.5"
                        :class="viewMode === 'sheet' ? 'border-indigo-600 font-semibold text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        @click="viewMode = 'sheet'"
                    >部署シート</button>
                    <button
                        type="button"
                        class="-mb-px border-b-2 px-3 py-1.5"
                        :class="viewMode === 'vendors' ? 'border-indigo-600 font-semibold text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        @click="viewMode = 'vendors'"
                    >支払先</button>
                </div>

                <div v-if="viewMode !== 'vendors'" class="mb-3 flex flex-wrap gap-1 rounded border bg-gray-50 p-1 text-xs">
                    <button
                        v-for="tab in departmentTabs"
                        :key="tab.key"
                        type="button"
                        class="rounded px-2 py-1"
                        :class="departmentKey === tab.key ? 'bg-gray-700 text-white' : 'text-gray-600 hover:bg-gray-100'"
                        @click="departmentKey = tab.key"
                    >{{ tab.label }}</button>
                </div>

                <div v-if="fiscalYears.length" class="mb-3 flex flex-wrap items-center gap-2">
                    <label class="text-xs font-medium text-gray-700">基準年度</label>
                    <button type="button" :disabled="!canGoPrevYear" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-40" @click="goPrevYear">← 前年度</button>
                    <select :value="vendorFiscalYear" class="rounded-md border-gray-300 text-sm font-bold shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @change="setBaseYear(Number($event.target.value))">
                        <option v-for="fy in fiscalYears" :key="fy" :value="fy">{{ fiscalPeriodLabel(fy) }}</option>
                    </select>
                    <button type="button" :disabled="!canGoNextYear" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-40" @click="goNextYear">翌年度 →</button>
                    <button type="button" :disabled="!canGoNextYear" class="rounded-md border border-indigo-300 bg-indigo-50 px-2 py-1 text-sm text-indigo-700 hover:bg-indigo-100 disabled:opacity-40" @click="goLatestYear">最新年度</button>
                </div>

                <p v-if="viewMode !== 'vendors' && metaLoading" class="text-xs text-gray-500">読み込み中...</p>
                <p v-else-if="viewMode !== 'vendors' && metaError" class="rounded bg-red-50 p-2 text-xs text-red-700">{{ metaError }}</p>
                <!--
                    データが無いときも行き止まりにしない。取込が1件も無い場合は取込画面へ、
                    その部署にだけ無い場合は他の部署タブ・取込済み年度の案内を出す
                -->
                <div v-else-if="viewMode !== 'vendors' && !categories.length" class="rounded border border-amber-200 bg-amber-50 p-3 text-xs text-gray-700">
                    <template v-if="!fiscalYears.length">
                        <p class="font-semibold">この会社の経費データは、まだ1件も取り込まれていません。</p>
                        <p class="mt-1 text-gray-600">経費のExcelを取り込むと、年度の選択・科目ごとの複数年推移・支払先別の依存度が表示されます。</p>
                    </template>
                    <template v-else>
                        <p class="font-semibold">「{{ departmentTabs.find((t) => t.key === departmentKey)?.label }}」には、取り込み済みの経費データがありません。</p>
                        <p class="mt-1 text-gray-600">他の部署タブを選ぶか、データを取り込んでください。（取込済みの年度: {{ fiscalYears.map((fy) => fiscalPeriodLabel(fy)).join('、') }}）</p>
                    </template>
                    <Link
                        :href="route(rn('expense_import.create'))"
                        class="mt-2 inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-indigo-700"
                    >経費Excel取込へ</Link>
                </div>
                <div v-else-if="viewMode === 'compare'">
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <label class="block text-xs font-medium text-gray-700">科目</label>
                        <button
                            v-if="selectedCategory"
                            type="button"
                            class="rounded border border-indigo-300 bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700 hover:bg-indigo-100"
                            @click="openInSheet(selectedCategory)"
                        >「{{ selectedCategory }}」を部署シートで見る →</button>
                    </div>
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
            <div v-if="viewMode === 'compare' && selectedCategory" class="grid gap-4 lg:grid-cols-2 lg:items-start">
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
                            <tr v-for="y in trendData.years" :key="y.fiscal_year" class="border-t" :class="y.fiscal_year === vendorFiscalYear ? 'bg-indigo-50 font-semibold' : ''">
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
                    </div>

                    <p v-if="vendorLoading" class="text-xs text-gray-500">読み込み中...</p>
                    <p v-else-if="vendorError" class="rounded bg-red-50 p-2 text-xs text-red-700">{{ vendorError }}</p>
                    <p v-else-if="vendorData && !vendorData.has_data" class="text-xs text-gray-500">
                        {{ fiscalPeriodLabel(vendorFiscalYear) }}のこの科目のデータはありません。
                        <template v-if="yearsWithData.length">
                            データのある年度:
                            <button v-for="fy in yearsWithData" :key="fy" type="button" class="mr-1 rounded border border-indigo-300 px-1.5 text-indigo-700 hover:bg-indigo-50" @click="setBaseYear(fy)">{{ fiscalPeriodLabel(fy) }}</button>
                        </template>
                    </p>
                    <!--
                        canvasは常時マウントしたままにする（複数年推移グラフと同じ理由。2026-10-09）。
                        支払先が多い科目（外注加工費等は数十社になる）では、外側の表示枠の高さを常に
                        5件分程度で固定し、内側のラッパーに全件分の高さを持たせてchart.jsにそちら基準で
                        描画させる（canvasのheight属性だけを増減させる方式だと、chart.jsのresponsive設定が
                        外側の表示枠サイズに合わせて棒を圧縮してしまい、支払先が多い科目だけ棒とラベルが
                        ずれて見える不具合があったため、ラッパーを二重にして解消した。2026-10-09）
                    -->
                    <div v-show="!vendorLoading && !vendorError && vendorData?.has_data" class="max-h-72 overflow-y-auto">
                        <div :style="{ height: `${vendorChartHeight}px` }">
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

            <!-- 部署シート: Excelの部署シート（科目→支払先→月→累計、小計・合計）を1画面で再現（Phase23） -->
            <div v-if="viewMode === 'sheet' && !metaLoading && !metaError && categories.length" class="rounded bg-white p-4 shadow">
                <h3 class="mb-2 text-sm font-semibold text-gray-900">
                    {{ departmentTabs.find((t) => t.key === departmentKey)?.label }}シート（{{ fiscalPeriodLabel(vendorFiscalYear) }}）
                </h3>
                <div v-if="sheetData?.has_data" class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
                    <span class="rounded bg-gray-100 px-2 py-1 text-gray-600">{{ actualMonthLabel }}まで実績{{ sheetData.is_partial ? '（期の途中）' : '' }}</span>
                    <label class="flex items-center gap-1 text-gray-700">
                        比較年度
                        <select
                            :value="compareYear ?? ''"
                            class="rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            @change="compareYear = $event.target.value ? Number($event.target.value) : null"
                        >
                            <option value="">前年度（{{ fiscalPeriodLabel(vendorFiscalYear - 1) }}）</option>
                            <option v-for="fy in compareYearOptions.filter((y) => y !== vendorFiscalYear - 1)" :key="fy" :value="fy">{{ fiscalPeriodLabel(fy) }}</option>
                        </select>
                    </label>
                    <div v-if="sheetData.is_partial" class="flex items-center gap-1">
                        <span class="text-gray-700">比較期間</span>
                        <button
                            type="button"
                            class="rounded px-2 py-1"
                            :class="sheetData.compare_period === 'ytd' ? 'bg-gray-700 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-100'"
                            @click="comparePeriod = 'ytd'"
                        >同期間（4月〜{{ actualMonthLabel }}）</button>
                        <button
                            type="button"
                            class="rounded px-2 py-1"
                            :class="sheetData.compare_period === 'full' ? 'bg-gray-700 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-100'"
                            @click="comparePeriod = 'full'"
                        >通年</button>
                    </div>
                    <span v-if="sheetData.is_partial && sheetData.compare_period === 'ytd'" class="text-gray-400">期の途中のため、比較年度も同じ月までの累計で比べています</span>
                </div>
                <p v-if="sheetLoading" class="mb-2 text-xs text-gray-500">読み込み中...</p>
                <p v-if="sheetError" class="rounded bg-red-50 p-2 text-xs text-red-700">{{ sheetError }}</p>
                <p v-else-if="sheetData && !sheetData.has_data" class="text-xs text-gray-500">
                    {{ fiscalPeriodLabel(vendorFiscalYear) }}のこの部署の経費データはありません。上の「基準年度」で、取り込み済みの別の年度を選んでください。
                </p>
                <!-- 読み込み中も表を残す（比較条件の変更のたびに、科目の開閉状態や表示モードが初期化されないように） -->
                <div v-if="sheetData?.has_data" :class="sheetLoading ? 'opacity-60' : ''">
                    <ExpenseHighlights :sheet="sheetData" @focus="focusSheetRow" />
                    <ExpenseSheetTable ref="sheetTableRef" :sheet="sheetData" :reset-key="departmentKey" @vendor="openVendor" />
                </div>
            </div>

            <!-- 支払先カルテ: 全部署・全科目・全年度を支払先で横断して見る（Phase24-3） -->
            <ExpenseVendors
                v-if="viewMode === 'vendors' && vendorFiscalYear && !metaLoading && fiscalYears.length"
                v-model="selectedVendor"
                :route-prefix="routePrefix"
                :department-labels="departmentLabels"
                :enabled-department-keys="enabledDepartmentKeys"
                :fiscal-year="vendorFiscalYear"
            />
        </div>
    </SalesAnalysisShell>
</template>
