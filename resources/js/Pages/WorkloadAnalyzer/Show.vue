<script setup>
import AggregatesTable from '@/Components/AggregatesTable.vue';
import AnalysisPanel from '@/Components/AnalysisPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import Chart from 'chart.js/auto';
import { computed, onMounted, ref } from 'vue';
const props = defineProps({
    user_id: Number,
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
// chart refs for four category charts: stages, sizes, types, difficulty
const chartRefs = ref([]);
let chartInstances = [];

// modal show state for calculation explanation
const showCalcModal = ref(false);

// overall points sum across categories (use available totals)
const overallPoints = computed(() => {
    return (
        Number(totalStagePoints.value || 0) +
        Number(totalSizePoints.value || 0) +
        Number(totalTypePoints.value || 0) +
        Number(totalDifficultyPoints.value || 0)
    );
});

// compute deviation value (z-score scaled to baseline 100 then converted to '偏差値' with mean 50, sd 10)
const deviationValue = computed(() => {
    const baseline = 100;
    const std = Number(props.team_std_points || 0);
    if (!std || std === 0) return 0;
    const z = (Number(overallPoints.value) - baseline) / std;
    return z;
});

const deviationDisplay = computed(() => {
    if (!props.team_std_points || Number(props.team_std_points) === 0) return '50.0 (標準偏差 0 のため)';
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

onMounted(() => {
    // create 4 pie charts if canvases exist: order: stages, sizes, types, difficulty
    try {
        const configs = [
            { labels: props.stage_labels || [], data: (props.stage_data || []).map((v) => Number(v || 0)) },
            { labels: props.size_labels || [], data: (props.size_data || []).map((v) => Number(v || 0)) },
            { labels: props.type_labels || [], data: (props.type_data || []).map((v) => Number(v || 0)) },
            { labels: props.difficulty_labels || [], data: (props.difficulty_data || []).map((v) => Number(v || 0)) },
        ];

        // clear previous instances
        chartInstances.forEach((c) => c && c.destroy && c.destroy());
        chartInstances = [];

        for (let i = 0; i < configs.length; i++) {
            const el = chartRefs.value[i];
            if (!el) {
                chartInstances.push(null);
                continue;
            }
            const cfg = configs[i];
            const ci = new Chart(el, {
                type: 'pie',
                data: {
                    labels: cfg.labels,
                    datasets: [
                        {
                            data: cfg.data,
                            backgroundColor: ['#4F46E5', '#06B6D4', '#F59E0B', '#EF4444', '#10B981'],
                        },
                    ],
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } },
            });
            chartInstances.push(ci);
        }
    } catch (e) {
        // ignore chart creation errors
    }
});
</script>

<template>
    <AppLayout>
        <Head title="作業量分析 - 詳細" />

        <h1 class="text-2xl font-semibold">作業量分析 - 詳細</h1>

        <div class="rounded bg-white p-6 shadow">
                    <div class="mb-4">
                        <div class="text-sm text-gray-500">ユーザー: {{ props.user_id }} / 月: {{ props.selected_ym }}</div>
                    </div>

                    <div class="space-y-6">
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

                        <AnalysisPanel
                            title="ステージ"
                            :labels="props.stage_labels"
                            :data="props.stage_data"
                            :coefficients="props.stage_coefficients"
                            :total_points="totalStagePoints"
                            :points="stagePoints"
                        />

                        <AnalysisPanel
                            title="サイズ"
                            :labels="props.size_labels"
                            :data="props.size_data"
                            :coefficients="props.size_coefficients"
                            :total_points="totalSizePoints"
                            :points="props.size_points"
                        />

                        <AnalysisPanel
                            title="種別"
                            :labels="props.type_labels"
                            :data="props.type_data"
                            :coefficients="props.type_coefficients"
                            :total_points="totalTypePoints"
                            :points="props.type_points"
                        />

                        <AnalysisPanel
                            title="難易度"
                            :labels="props.difficulty_labels"
                            :data="props.difficulty_data"
                            :coefficients="(props.difficulties || []).map((d) => d.coefficient)"
                            :total_points="totalDifficultyPoints"
                            :points="difficultyPoints"
                        />
                    </div>
                    <div class="space-y-6">
                        <div class="rounded border p-4">
                            <h2 class="font-medium">ポイント総合</h2>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <h4 class="font-medium">内訳（ポイント）</h4>
                                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm">
                                        <li>ステージ合計: {{ totalStagePoints.toFixed(1) }} ポイント</li>
                                        <li>サイズ合計: {{ totalSizePoints.toFixed(1) }} ポイント</li>
                                        <li>種別合計: {{ totalTypePoints.toFixed(1) }} ポイント</li>
                                        <li v-if="totalDifficultyPoints !== null">難易度合計: {{ totalDifficultyPoints.toFixed(1) }} ポイント</li>
                                    </ul>
                                </div>

                                <div>
                                    <h4 class="font-medium">合計と偏差値</h4>
                                    <div class="mt-2 text-sm">
                                        <div>
                                            合計ポイント: <span class="font-semibold">{{ overallPoints.toFixed(1) }}</span>
                                        </div>
                                        <div class="mt-1">
                                            100ポイント基準 偏差値: <span class="font-semibold">{{ deviationDisplay }}</span>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button @click="showCalcModal = true" class="rounded bg-blue-600 px-3 py-1 text-sm text-white">
                                            計算方法を表示
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Calculation modal -->
                    <div v-if="showCalcModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                        <div class="w-full max-w-2xl rounded bg-white p-6">
                            <h3 class="text-lg font-medium">ポイントと偏差値の計算方法</h3>
                            <div class="mt-4 space-y-2 text-sm">
                                <div>- 各カテゴリのポイントは、それぞれの分量（ページ等）に係数を掛けて算出されます。</div>
                                <div>- 合計ポイントはカテゴリごとの合計の単純合算です。</div>
                                <div>- 偏差値は 100 を基準にした z スコアを基にスケーリングしています（参考式下記）。</div>
                                <div class="mt-2 rounded bg-gray-100 p-2 font-mono text-xs">
                                    z = (overallPoints - baseline) / std<br />
                                    偏差値 = 50 + 10 * z (baseline=100)
                                </div>
                                <div class="text-xs text-gray-600">注: チーム標準偏差が 0 の場合、偏差値は 50（基準値）になります。</div>
                            </div>

                            <div class="mt-4 flex justify-end">
                                <button @click="showCalcModal = false" class="rounded border px-3 py-1">閉じる</button>
                            </div>
                        </div>
                    </div>
        </div>
    </AppLayout>
</template>
