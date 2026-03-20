<script setup>
import AggregatesTable from '@/Components/AggregatesTable.vue';
import AnalysisPanel from '@/Components/AnalysisPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import Chart from 'chart.js/auto';
import { computed, onMounted, ref } from 'vue';

const page = usePage();
const routePrefix = computed(() => {
    const role = page.props.auth?.user?.user_role ?? 'leader';
    if (role === 'superadmin') return 'superadmin';
    if (role === 'admin') return 'admin';
    return 'leader';
});
const props = defineProps({
    user_id: Number,
    user_name: { type: String, default: null },
    selected_ym: String,
    totals: Object,
    stage_labels: Array,
    stage_data: Array,
    stage_coefficients: {
        type: Array,
        default: () => [],
    },
    type_labels: { type: Array, default: () => [] },
    type_data: { type: Array, default: () => [] },
    type_coefficients: { type: Array, default: () => [] },
    type_points: { type: Array, default: () => [] },
    size_labels: { type: Array, default: () => [] },
    size_data: { type: Array, default: () => [] },
    size_coefficients: { type: Array, default: () => [] },
    size_points: { type: Array, default: () => [] },
    difficulty_labels: { type: Array, default: () => [] },
    difficulty_data: { type: Array, default: () => [] },
    difficulties: { type: Array, default: () => [] },
    event_type_labels: { type: Array, default: () => [] },
    event_type_data: { type: Array, default: () => [] },
    event_type_coefficients: { type: Array, default: () => [] },
    event_type_points: { type: Array, default: () => [] },
    event_total_points: { type: Number, default: 0 },
    total_points: {
        type: Number,
        default: 0,
    },
    total_amount: {
        type: Number,
        default: 0,
    },
    // score is derived from totals; keep as a plain object prop in case server provides it,
    // otherwise we'll compute it from props.totals below.
    score: {
        type: Object,
        default: () => ({ work_hours: 0, desired_hours: 0, total_items: 0 }),
    },
    total_overtime_minutes:       { type: Number, default: 0 },
    overtime_days_normal:         { type: Number, default: 0 },
    overtime_days_excess:         { type: Number, default: 0 },
    overtime_distribution_labels: { type: Array,  default: () => [] },
    overtime_distribution_data:   { type: Array,  default: () => [] },
    overtime_normal_points:       { type: Number, default: 0 },
    overtime_excess_points:       { type: Number, default: 0 },
    total_overtime_points:        { type: Number, default: 0 },
    overtime_normal_coeff:        { type: Number, default: 1.0 },
    overtime_excess_coeff:        { type: Number, default: 1.0 },
    // per-category percentile scores within the comparison group (0–100 each, max 600 overall)
    percentile_scores:            { type: Object, default: () => ({}) },
    category_ranks:               { type: Object, default: () => ({}) },
    group_count:                  { type: Number, default: 0 },
});

// combined totals (assigned + self)
const combinedTotals = computed(() => {
    const a = props.totals?.assigned || {};
    const s = props.totals?.self || {};
    return {
        pages: Number(a.pages || 0) + Number(s.pages || 0),
        work_hours: Number(a.work_hours || 0) + Number(s.work_hours || 0),
        desired_hours: Number(a.desired_hours || 0) + Number(s.desired_hours || 0),
        total_items: Number(a.total_items || 0) + Number(s.total_items || 0),
    };
});

// computed score derived from totals (assigned + self)
const computedScore = computed(() => {
    const a = props.totals?.assigned || {};
    const s = props.totals?.self || {};
    return {
        work_hours: Number(a.work_hours || 0) + Number(s.work_hours || 0),
        desired_hours: Number(a.desired_hours || 0) + Number(s.desired_hours || 0),
        total_items: Number(a.total_items || 0) + Number(s.total_items || 0),
    };
});

// Combined per-stage totals: prefer server-provided stage_data if present,
// otherwise distribute the combined pages across known stages evenly.
const combinedStageTotals = computed(() => {
    const stages = (props.stage_labels || []).length;
    const stageData = (props.stage_data || []).map((v) => Number(v || 0));
    const combinedPages = Number(combinedTotals.value.pages || 0);

    if (stageData.length === stages && stageData.reduce((a, b) => a + b, 0) === combinedPages) {
        // already matches combined pages
        return stageData;
    }

    if (stageData.length === stages && stageData.reduce((a, b) => a + b, 0) > 0) {
        // prefer existing stage breakdown if it has positive totals
        return stageData;
    }

    // fallback: distribute combinedPages evenly across stages
    if (stages === 0) return [];
    const base = Math.floor(combinedPages / stages);
    const rest = combinedPages - base * stages;
    const out = Array.from({ length: stages }, (_, i) => base + (i < rest ? 1 : 0));
    return out;
});

// Build difficulty rows aligned with difficulties/difficulty_labels and stage_difficulty_rows
const difficultyRows = computed(() => {
    const labels = props.difficulty_labels || [];
    const difObjs = props.difficulties || [];
    const rawRows = props.stage_difficulty_rows || [];
    const out = [];

    // If server provided difficulties objects with ids, prefer that ordering and mapping
    if (difObjs && difObjs.length) {
        for (let i = 0; i < difObjs.length; i++) {
            const d = difObjs[i];
            let values = [];
            // If rawRows is an object keyed by difficulty id (assoc mapping from server)
            if (rawRows && typeof rawRows === 'object' && !Array.isArray(rawRows) && rawRows.hasOwnProperty(d.id)) {
                values = rawRows[d.id] || [];
            }
            // If rawRows is an ordered array and lengths match, use by index
            else if (Array.isArray(rawRows) && rawRows.length === difObjs.length) {
                values = rawRows[i] || [];
            }
            // If rawRows is array but possibly keyed by id indices (sparse), try find by id key
            else if (Array.isArray(rawRows) && rawRows[d.id]) {
                values = rawRows[d.id] || [];
            }
            // fallback: empty values and use difficulty_data
            const total =
                values && values.length
                    ? values.reduce((a, b) => a + Number(b || 0), 0)
                    : props.difficulty_data && props.difficulty_data[i]
                      ? Number(props.difficulty_data[i])
                      : 0;
            out.push({ label: d.name || labels[i] || `(${i})`, values, total });
        }
        return out;
    }

    // fallback: use difficulty_labels and aligned rawRows by index
    // If per-stage rows are missing but we have per-stage totals, distribute
    // the difficulty total across stages proportionally so the table aligns
    // visually with the stage columns instead of showing a single colspan cell.
    const stageTotals = (combinedStageTotals.value || []).map((v) => Number(v || 0));
    const totalStageSum = stageTotals.reduce((a, b) => a + b, 0);

    for (let i = 0; i < labels.length; i++) {
        let values = Array.isArray(rawRows) ? rawRows[i] || [] : [];
        let total = 0;

        if (values && values.length) {
            total = values.reduce((a, b) => a + Number(b || 0), 0);
        } else if (props.difficulty_data && typeof props.difficulty_data[i] !== 'undefined') {
            total = Number(props.difficulty_data[i] || 0);

            // Distribute across stages proportionally if we have stage totals
            if (stageTotals.length > 0 && totalStageSum > 0) {
                values = stageTotals.map((st) => {
                    // proportion of this stage
                    const frac = st / totalStageSum;
                    return Math.round(total * frac);
                });

                // Fix rounding to ensure sum(values) === total
                const sumVals = values.reduce((a, b) => a + b, 0);
                const diff = total - sumVals;
                if (diff !== 0) {
                    // add the remainder to the largest stage (or first non-zero)
                    let idx = 0;
                    let maxVal = values[0] || 0;
                    for (let j = 1; j < values.length; j++) {
                        if ((values[j] || 0) > maxVal) {
                            maxVal = values[j];
                            idx = j;
                        }
                    }
                    values[idx] = (values[idx] || 0) + diff;
                }
            }
        }

        out.push({ label: labels[i], values, total });
    }
    return out;
});

// Column sums by stage (useful for bottom row)
const stageColumnSums = computed(() => {
    const cols = (props.stage_labels || []).length;
    const rows = props.stage_difficulty_rows || [];
    // If there are no per-stage difficulty rows, fallback to stage_data which holds per-stage totals
    // compute sums from rows if possible
    let sumsFromRows = Array.from({ length: cols }, () => 0);
    let haveRows = Array.isArray(rows) && rows.length > 0;
    if (haveRows) {
        try {
            for (const r of rows) {
                for (let i = 0; i < cols; i++) {
                    sumsFromRows[i] += Number((r || [])[i] || 0);
                }
            }
        } catch (e) {
            haveRows = false;
            sumsFromRows = Array.from({ length: cols }, () => 0);
        }
    }

    // totals for comparison
    const totalFromRows = sumsFromRows.reduce((a, b) => a + Number(b || 0), 0);
    const totalStageData = (props.stage_data || []).reduce((a, b) => a + Number(b || 0), 0);

    // If row-derived totals mismatch stage_data totals (and stage_data has positive total), prefer stage_data
    if (!haveRows || (totalStageData > 0 && Math.abs(totalFromRows - totalStageData) > 0)) {
        return (props.stage_data || []).map((v) => Number(v || 0));
    }

    return sumsFromRows;
});
// chart refs for summary section
const radarChartRef = ref(null);
const rankingChartRef = ref(null);
let radarInstance = null;
let rankingInstance = null;

// overtime distribution chart
const overtimeChartRef = ref(null);
let overtimeInstance = null;

function formatOvertimeMinutes(min) {
    if (!min) return '—';
    const h = Math.floor(min / 60);
    const m = min % 60;
    return h > 0 ? `${h}h${m > 0 ? m + 'm' : ''}` : `${m}m`;
}

// modal show state for calculation explanation
const showCalcModal = ref(false);

// rank of the viewed user within team
const myRank = computed(() => {
    const r = (props.team_ranking || []).find((r) => String(r.user_id) === String(props.user_id));
    return r ? r.rank : null;
});

// deviation score: prefer server-provided value
const serverDeviation = computed(() => {
    if (props.deviation_score !== null && props.deviation_score !== undefined) {
        return Number(props.deviation_score).toFixed(1);
    }
    return null;
});

// color class for deviation badge
const deviationColorClass = computed(() => {
    const v = props.deviation_score !== null && props.deviation_score !== undefined
        ? Number(props.deviation_score) : null;
    if (v === null) return 'text-gray-500';
    if (v >= 60) return 'text-green-600';
    if (v >= 50) return 'text-blue-600';
    if (v >= 40) return 'text-yellow-600';
    return 'text-red-600';
});

// overtime total points (from server, raw)
const totalOvertimePoints = computed(() => Number(props.total_overtime_points || 0));

// Percentile-based overall score (0–600) — fair cross-category comparison
const overallPoints = computed(() => Number(props.percentile_scores?.overall || 0));

// compute deviation value (z-score scaled to baseline 100 then converted to '偏差値' with mean 50, sd 10)
const deviationValue = computed(() => {
    const baseline = 100;
    const std = Number(props.team_std_points || 0);
    if (!std || std === 0) return 0;
    const z = (Number(overallPoints.value) - baseline) / std;
    return z;
});

const deviationDisplay = computed(() => {
    if (serverDeviation.value !== null) return serverDeviation.value;
    if (!props.team_std_points || Number(props.team_std_points) === 0) return '50.0';
    const dv = 50 + 10 * Number(deviationValue.value || 0);
    return Number(dv).toFixed(1);
});

// compute per-stage points (pages * stage coefficient)
const stagePoints = computed(() => {
    const labels = props.stage_labels || [];
    const data = props.stage_data || [];
    const coeffs = props.stage_coefficients || [];
    return labels.map((l, i) => Number(data[i] || 0) * Number(coeffs[i] || 1));
});

// total points per category
const totalStagePoints = computed(() => stagePoints.value.reduce((a, b) => a + Number(b || 0), 0));
const totalTypePoints = computed(() => (props.type_points || []).reduce((a, b) => a + Number(b || 0), 0));
const totalSizePoints = computed(() => (props.size_points || []).reduce((a, b) => a + Number(b || 0), 0));

// difficulty coefficients extracted from difficulties objects (fallback to 1)
const difficultyCoefficients = computed(() => {
    const difObjs = props.difficulties || [];
    if (Array.isArray(difObjs) && difObjs.length) {
        return difObjs.map((d) => Number(d.coefficient || d.coefficient === 0 ? d.coefficient : 1));
    }
    return [];
});

const difficultyPoints = computed(() => {
    const data = props.difficulty_data || [];
    const coeffs = difficultyCoefficients.value;
    return data.map((v, i) => Number(v || 0) * Number(coeffs[i] || 1));
});

const totalDifficultyPoints = computed(() => difficultyPoints.value.reduce((a, b) => a + Number(b || 0), 0));

const eventPoints = computed(() => (props.event_type_points || []).map((v) => Number(v || 0)));
const totalEventPoints = computed(() => eventPoints.value.reduce((a, b) => a + b, 0));

const buildSummaryCharts = () => {
    // --- Radar chart: カテゴリ別ポイントバランス ---
    if (radarChartRef.value) {
        if (radarInstance) radarInstance.destroy();
        // Use percentile scores (0–100) so all categories are on equal scale
        const ps = props.percentile_scores || {};
        const radarData = [
            Number(ps.stage      || 0),
            Number(ps.size       || 0),
            Number(ps.type       || 0),
            Number(ps.difficulty || 0),
            Number(ps.event      || 0),
            Number(ps.overtime   || 0),
        ];
        const allZero = radarData.every((v) => v === 0);
        radarInstance = new Chart(radarChartRef.value, {
            type: 'radar',
            data: {
                labels: ['ステージ', 'サイズ', '種別', '難易度', 'イベント', '残業'],
                datasets: [{
                    label: 'パーセンタイル',
                    data: allZero ? [0, 0, 0, 0, 0, 0] : radarData,
                    backgroundColor: 'rgba(79, 70, 229, 0.15)',
                    borderColor: '#4F46E5',
                    borderWidth: 2,
                    pointBackgroundColor: '#4F46E5',
                    pointRadius: 4,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { r: { beginAtZero: true, ticks: { font: { size: 11 } } } },
            },
        });
    }

    // --- Horizontal bar chart: チームランキング ---
    if (rankingChartRef.value) {
        if (rankingInstance) rankingInstance.destroy();
        const ranking = [...(props.team_ranking || [])].sort((a, b) => a.rank - b.rank).slice(0, 15);
        const labels = ranking.map((r) => r.name || `#${r.user_id}`);
        const data   = ranking.map((r) => Number(r.total_points || 0));
        const bg     = ranking.map((r) =>
            String(r.user_id) === String(props.user_id) ? '#4F46E5' : '#93C5FD'
        );
        rankingInstance = new Chart(rankingChartRef.value, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: '合計ポイント',
                    data,
                    backgroundColor: bg,
                    borderRadius: 4,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: '#F3F4F6' } },
                    y: { ticks: { font: { size: 11 } } },
                },
            },
        });
    }
};

// 残業バケット用カラー（ローズ系グラデーション）
const overtimeShades = ['#FDA4AF', '#FB7185', '#F43F5E', '#E11D48', '#9F1239'];

const buildOvertimeChart = () => {
    if (!overtimeChartRef.value) return;
    if (overtimeInstance) overtimeInstance.destroy();
    const labels = props.overtime_distribution_labels || [];
    const data   = props.overtime_distribution_data   || [];
    const isEmpty = !data.length || data.every((v) => !Number(v));
    overtimeInstance = new Chart(overtimeChartRef.value, {
        type: 'pie',
        data: {
            labels: isEmpty ? ['データなし'] : labels,
            datasets: [{
                data: isEmpty ? [1] : data,
                backgroundColor: isEmpty ? ['#E5E7EB'] : overtimeShades.slice(0, labels.length),
                borderWidth: 1,
                borderColor: '#fff',
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } },
                tooltip: { enabled: !isEmpty },
            },
        },
    });
};

onMounted(() => {
    try { buildSummaryCharts(); } catch (e) { /* ignore */ }
    try { buildOvertimeChart(); } catch (e) { /* ignore */ }
});
</script>

<template>
    <AppLayout>
        <Head title="作業量分析 - 詳細" />

        <div class="rounded bg-white p-6 shadow">
                    <div class="mb-6 flex items-start justify-between border-b pb-4">
                        <div>
                            <h1 class="text-2xl font-bold">{{ props.user_name ?? `ユーザー #${props.user_id}` }}</h1>
                            <p class="mt-1 text-base text-gray-600">作業量分析 — {{ props.selected_ym?.replace('-', '年').replace(/(\d{2})$/, (m) => parseInt(m, 10) + '月') }}</p>
                        </div>
                        <Link :href="route(`${routePrefix}.workload_analyzer.index`)" class="text-sm text-blue-600 hover:underline">← 一覧に戻る</Link>
                    </div>

                    <div class="space-y-6">
                        <div class="rounded border p-4">
                            <div class="mb-4 flex items-center justify-between">
                                <h2 class="text-lg font-semibold">ポイント総合</h2>
                                <button @click="showCalcModal = true" class="rounded bg-gray-100 px-3 py-1 text-xs text-gray-600 hover:bg-gray-200">
                                    計算方法
                                </button>
                            </div>

                            <!-- Stats cards -->
                            <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
                                <div class="rounded-lg bg-blue-50 p-3 text-center">
                                    <div class="text-xs text-gray-500">合計ポイント</div>
                                    <div class="mt-1 text-2xl font-bold text-blue-700">{{ overallPoints.toFixed(1) }}</div>
                                </div>
                                <div class="rounded-lg bg-indigo-50 p-3 text-center">
                                    <div class="text-xs text-gray-500">偏差値</div>
                                    <div class="mt-1 text-2xl font-bold" :class="deviationColorClass">{{ deviationDisplay }}</div>
                                </div>
                                <div class="rounded-lg bg-green-50 p-3 text-center">
                                    <div class="text-xs text-gray-500">チーム内順位</div>
                                    <div class="mt-1 text-2xl font-bold text-green-700">{{ myRank ?? '-' }} <span class="text-sm font-normal text-gray-500">/ {{ props.team_ranking?.length ?? '-' }}</span></div>
                                </div>
                                <div class="rounded-lg bg-amber-50 p-3 text-center">
                                    <div class="text-xs text-gray-500">パーセンタイル</div>
                                    <div class="mt-1 text-2xl font-bold text-amber-700">{{ props.team_percentile !== null && props.team_percentile !== undefined ? props.team_percentile + '%' : '-' }}</div>
                                </div>
                            </div>

                            <!-- Charts row -->
                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div>
                                    <h3 class="mb-2 text-sm font-medium text-gray-600">カテゴリ別ポイントバランス</h3>
                                    <canvas ref="radarChartRef" style="max-height: 260px"></canvas>
                                </div>
                                <div>
                                    <h3 class="mb-2 text-sm font-medium text-gray-600">チーム内ランキング <span class="text-xs font-normal text-indigo-600">（■ 自分）</span></h3>
                                    <canvas ref="rankingChartRef" style="max-height: 260px"></canvas>
                                </div>
                            </div>

                            <!-- Breakdown list -->
                            <div class="mt-4 border-t pt-4">
                                <h4 class="mb-2 text-sm font-medium text-gray-600">カテゴリ別パーセンタイル（各100点満点）</h4>
                                <div class="grid grid-cols-2 gap-x-8 gap-y-1 text-sm md:grid-cols-6">
                                    <div>ステージ: <span class="font-semibold">{{ (props.percentile_scores?.stage ?? 0).toFixed(1) }}</span></div>
                                    <div>サイズ: <span class="font-semibold">{{ (props.percentile_scores?.size ?? 0).toFixed(1) }}</span></div>
                                    <div>種別: <span class="font-semibold">{{ (props.percentile_scores?.type ?? 0).toFixed(1) }}</span></div>
                                    <div>難易度: <span class="font-semibold">{{ (props.percentile_scores?.difficulty ?? 0).toFixed(1) }}</span></div>
                                    <div>イベント: <span class="font-semibold">{{ (props.percentile_scores?.event ?? 0).toFixed(1) }}</span></div>
                                    <div>残業: <span class="font-semibold">{{ (props.percentile_scores?.overtime ?? 0).toFixed(1) }}</span></div>
                                </div>
                            </div>
                        </div>

                        <AnalysisPanel
                            title="ステージ"
                            scheme="blue"
                            :labels="props.stage_labels"
                            :data="props.stage_data"
                            :coefficients="props.stage_coefficients"
                            :total_points="totalStagePoints"
                            :points="stagePoints"
                            :percentile="props.percentile_scores?.stage ?? null"
                            :rank="props.category_ranks?.stage ?? null"
                            :group_count="props.group_count"
                        />

                        <AnalysisPanel
                            title="サイズ"
                            scheme="green"
                            :labels="props.size_labels"
                            :data="props.size_data"
                            :coefficients="props.size_coefficients"
                            :total_points="totalSizePoints"
                            :points="props.size_points"
                            :percentile="props.percentile_scores?.size ?? null"
                            :rank="props.category_ranks?.size ?? null"
                            :group_count="props.group_count"
                        />

                        <AnalysisPanel
                            title="種別"
                            scheme="amber"
                            :labels="props.type_labels"
                            :data="props.type_data"
                            :coefficients="props.type_coefficients"
                            :total_points="totalTypePoints"
                            :points="props.type_points"
                            :percentile="props.percentile_scores?.type ?? null"
                            :rank="props.category_ranks?.type ?? null"
                            :group_count="props.group_count"
                        />

                        <AnalysisPanel
                            title="難易度"
                            scheme="rose"
                            :labels="props.difficulty_labels"
                            :data="props.difficulty_data"
                            :coefficients="(props.difficulties || []).map((d) => d.coefficient)"
                            :total_points="totalDifficultyPoints"
                            :points="difficultyPoints"
                            :percentile="props.percentile_scores?.difficulty ?? null"
                            :rank="props.category_ranks?.difficulty ?? null"
                            :group_count="props.group_count"
                        />

                        <AnalysisPanel
                            title="イベント"
                            scheme="purple"
                            :labels="props.event_type_labels"
                            :data="props.event_type_data"
                            :coefficients="props.event_type_coefficients"
                            :total_points="totalEventPoints"
                            :points="eventPoints"
                            :percentile="props.percentile_scores?.event ?? null"
                            :rank="props.category_ranks?.event ?? null"
                            :group_count="props.group_count"
                        />

                        <!-- 残業時間分布 (AnalysisPanel 同レイアウト) -->
                        <div class="rounded border-l-4 p-4 shadow-sm" style="border-left-color: #E11D48;">
                            <h3 class="flex items-center gap-2 font-medium">
                                <span class="inline-block h-3 w-3 rounded-full" style="background-color: #E11D48;"></span>
                                残業時間
                            </h3>

                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                <!-- 左: 円グラフ -->
                                <div>
                                    <canvas ref="overtimeChartRef" style="max-height: 240px"></canvas>
                                </div>

                                <!-- 右: 詳細データ -->
                                <div>
                                    <div class="flex gap-4">
                                        <div class="flex-1">
                                            <h4 class="font-medium">詳細</h4>
                                            <table class="mt-2 w-full table-fixed text-sm">
                                                <thead>
                                                    <tr>
                                                        <th
                                                            v-for="(lbl, i) in props.overtime_distribution_labels"
                                                            :key="i"
                                                            class="text-left text-xs"
                                                        >{{ lbl }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td
                                                            v-for="(cnt, i) in props.overtime_distribution_data"
                                                            :key="i"
                                                            class="text-left"
                                                        >{{ cnt }}日</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="mt-4 rounded border p-3" style="background-color: #FFF1F2;">
                                        <div class="space-y-1 text-sm" style="color: #9F1239;">
                                            <div>
                                                総残業時間：
                                                <span class="font-semibold">{{ formatOvertimeMinutes(props.total_overtime_minutes) }}</span>
                                            </div>
                                            <div>
                                                通常残業（〜3時間）：
                                                <span class="font-semibold">{{ props.overtime_days_normal }}日</span>
                                                <span class="ml-1 text-xs">({{ props.overtime_normal_points.toFixed(1) }}pt, 係数{{ props.overtime_normal_coeff.toFixed(2) }})</span>
                                            </div>
                                            <div>
                                                超過残業（4時間〜）：
                                                <span class="font-semibold">{{ props.overtime_days_excess }}日</span>
                                                <span class="ml-1 text-xs">({{ props.overtime_excess_points.toFixed(1) }}pt, 係数{{ props.overtime_excess_coeff.toFixed(2) }})</span>
                                            </div>
                                            <div class="border-t pt-1">
                                                <div class="font-bold">
                                                    パーセンタイル：{{ (props.percentile_scores?.overtime ?? 0).toFixed(1) }} / 100
                                                </div>
                                                <div v-if="props.category_ranks?.overtime && props.group_count" class="font-semibold">
                                                    順位：{{ props.category_ranks.overtime }}位 / {{ props.group_count }}人中
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded border p-4">
                            <h2 class="font-medium">合計</h2>
                            <div class="flex-raw mt-2 flex gap-4">
                                <div class="w-full">
                                    <div class="text-xs font-medium text-gray-600">依頼された作業（合計）</div>
                                    <AggregatesTable :row="{ aggregates: { assigned: props.totals.assigned } }" mode="assigned" />
                                    <div class="mt-2 text-xs text-gray-500">件数: {{ props.totals.assigned.total_items }}</div>
                                </div>
                                <div class="w-full">
                                    <div class="text-xs font-medium text-gray-600">自分の作業（合計）</div>
                                    <AggregatesTable :row="{ aggregates: { self: props.totals.self } }" mode="self" />
                                    <div class="mt-2 text-xs text-gray-500">件数: {{ props.totals.self.total_items }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Calculation modal -->
                    <div v-if="showCalcModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showCalcModal = false">
                        <div class="w-full max-w-2xl overflow-y-auto rounded bg-white p-6 shadow-xl" style="max-height: 90vh">
                            <h3 class="text-lg font-semibold text-gray-800">ポイント・ランクの計算方法</h3>
                            <p class="mt-1 text-xs text-gray-400">※ AI に計算方法を変更させる際は、この内容を参照として渡すこと。</p>

                            <!-- 概要 -->
                            <section class="mt-5">
                                <h4 class="border-b pb-1 text-sm font-semibold text-gray-700">▍概要</h4>
                                <div class="mt-3 space-y-2 text-sm text-gray-700">
                                    <p>
                                        各カテゴリ（ステージ・サイズ・種別・難易度・イベント・残業）ごとに
                                        <strong>生スコア</strong>（分量 × 係数）を計算し、それを
                                        <strong>部署内でパーセンタイルランク（0〜100）</strong>に変換します。
                                        6カテゴリのパーセンタイルを合算した <strong>0〜600 点</strong> が総合ポイントになります。
                                    </p>
                                    <p>
                                        スケールが大きく異なるカテゴリ（例: ページ数 vs 残業分）を
                                        そのまま足すと一方が支配的になるため、パーセンタイル変換により
                                        各カテゴリを <strong>等しい重みで比較</strong> できるようにしています。
                                    </p>
                                </div>
                            </section>

                            <!-- 詳細 -->
                            <section class="mt-5">
                                <h4 class="border-b pb-1 text-sm font-semibold text-gray-700">▍詳細</h4>

                                <div class="mt-3 space-y-4 text-sm text-gray-700">

                                    <!-- 生スコア計算 -->
                                    <div>
                                        <p class="font-medium text-gray-800">① 各カテゴリの生スコア計算</p>
                                        <div class="mt-1 space-y-1 rounded bg-gray-50 p-3 font-mono text-xs">
                                            <div>ステージ  = Σ (ページ × ステージ係数 × 難易度係数)</div>
                                            <div>サイズ    = Σ (ページ × サイズ係数   × 難易度係数)</div>
                                            <div>種別      = Σ (ページ × 種別係数     × 難易度係数)</div>
                                            <div>難易度    = Σ (ページ × 難易度係数)</div>
                                            <div>イベント  = Σ (イベント時間[h] × イベント種別係数)</div>
                                            <div>残業（通常）= 合計残業分[min, ≤180/日] × 通常残業係数</div>
                                            <div>残業（超過）= 合計残業分[min, ＞180/日] × 超過残業係数</div>
                                            <div>残業生スコア = 残業（通常）＋ 残業（超過）</div>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">
                                            係数は「作業量分析 設定」画面で変更可能。
                                            残業の通常/超過の閾値は180分（3時間）。
                                        </p>
                                    </div>

                                    <!-- パーセンタイル変換 -->
                                    <div>
                                        <p class="font-medium text-gray-800">② パーセンタイルランク変換（部署内）</p>
                                        <div class="mt-1 space-y-1 rounded bg-gray-50 p-3 font-mono text-xs">
                                            <div>比較対象: 同部署の全メンバー（N 人）</div>
                                            <div>自分より生スコアが高いメンバー数 = above</div>
                                            <div>自分と同じ生スコアのメンバー数  = tied</div>
                                            <div>平均順位 avgRank = above + (tied + 1) / 2</div>
                                            <div>パーセンタイル = (N − avgRank) / (N − 1) × 100</div>
                                            <div>※ N = 1 の場合は 100 固定</div>
                                            <div>※ 同値タイは平均順位を使い全員に同じパーセンタイルを付与</div>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">
                                            1位 → 100、最下位 → 0、中間は線形補間。
                                        </p>
                                    </div>

                                    <!-- 総合ポイント -->
                                    <div>
                                        <p class="font-medium text-gray-800">③ 総合ポイント（0〜600）</p>
                                        <div class="mt-1 rounded bg-gray-50 p-3 font-mono text-xs">
                                            <div>総合ポイント = ステージ + サイズ + 種別 + 難易度 + イベント + 残業</div>
                                            <div>           （各 0〜100 のパーセンタイル値を合算）</div>
                                        </div>
                                    </div>

                                    <!-- 偏差値 -->
                                    <div>
                                        <p class="font-medium text-gray-800">④ 偏差値（参考値）</p>
                                        <div class="mt-1 rounded bg-gray-50 p-3 font-mono text-xs">
                                            <div>比較グループ: 同会社の全ユーザー</div>
                                            <div>z = (自分の総合ポイント − グループ平均) / グループ標準偏差</div>
                                            <div>偏差値 = 50 + 10 × z</div>
                                            <div>※ 標準偏差 ≒ 0 の場合は表示なし</div>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">
                                            偏差値の比較母集団は「会社全体」。パーセンタイルの母集団（「部署」）とは異なる点に注意。
                                        </p>
                                    </div>

                                    <!-- カテゴリ別順位 -->
                                    <div>
                                        <p class="font-medium text-gray-800">⑤ カテゴリ別順位</p>
                                        <div class="mt-1 rounded bg-gray-50 p-3 font-mono text-xs">
                                            <div>比較対象: 同会社のアクティブユーザー（当月にデータがある全員）</div>
                                            <div>各カテゴリの生スコアで降順ソートし、1位から順位を付与</div>
                                            <div>同値タイは同順位（dense rankではなく単純カウント方式）</div>
                                        </div>
                                    </div>

                                </div>
                            </section>

                            <div class="mt-6 flex justify-end">
                                <button @click="showCalcModal = false" class="rounded border px-4 py-1.5 text-sm hover:bg-gray-50">閉じる</button>
                            </div>
                        </div>
                    </div>
        </div>
    </AppLayout>
</template>
